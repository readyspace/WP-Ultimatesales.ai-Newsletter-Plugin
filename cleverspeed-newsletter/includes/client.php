<?php
namespace CleverSpeed\Newsletter;

final class Client {
    private const BASE = 'https://services.leadconnectorhq.com';
    public static function credentialReady(): bool { return Credential::ready(); }

    public function request(string $method, string $path, ?array $body = null, string $version = 'v3'): array {
        if (!self::credentialReady()) throw new \RuntimeException('Private server credential is missing or not owner-only.');
        if ($path[0] !== '/' || str_contains($path, '://')) throw new \RuntimeException('Invalid API path.');
        $token = Credential::read();
        $args = ['method' => $method, 'timeout' => 30, 'redirection' => 0, 'sslverify' => true,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Version' => $version,
                'Accept' => 'application/json', 'Content-Type' => 'application/json']];
        if ($body !== null) $args['body'] = wp_json_encode($body);
        $response = wp_remote_request(self::BASE . $path, $args);
        unset($token, $args);
        if (is_wp_error($response)) throw new \RuntimeException('UltimateSales.AI transport failure. Remote outcome may be unknown; do not blindly resend.');
        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            // Never persist response bodies: they can contain contact data or sensitive diagnostics.
            throw new \RuntimeException('UltimateSales.AI returned HTTP ' . $status . '; review required.');
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) throw new \RuntimeException('Unexpected UltimateSales.AI response; review required.');
        return $data;
    }

    public static function campaignsPath(): string {
        return '/emails/locations/' . Config::get('location_id') . '/campaigns/emails';
    }

    public function recipients(): array {
        $ids = [];
        $emails = [];
        $seen = [];
        // Bounded pagination. A limit/error holds the entire campaign, never sends a partial list.
        for ($page = 1; $page <= 100; $page++) {
            $data = $this->request('POST', '/contacts/search', ['locationId' => Config::get('location_id'),
                'page' => $page, 'pageLimit' => 100,
                'filters' => [['field' => 'tags', 'operator' => 'contains', 'value' => Config::get('confirmed_tag')]]], '2021-07-28');
            if (!isset($data['contacts']) || !is_array($data['contacts'])) throw new \RuntimeException('Unexpected contacts response; email held.');
            foreach ($data['contacts'] as $contact) {
                $id = $contact['id'] ?? '';
                if (!$id || isset($seen[$id])) throw new \RuntimeException('Contact pagination is incomplete or repeated; email held.');
                $seen[$id] = true;
                // Search returns explicit DND/channel state; Get Contact may omit it.
                // The worker repeats this fresh search immediately before scheduling.
                // Missing/unknown DND still fails closed in Policy::eligible().
                if (Policy::eligible($contact)) {
                    $email = strtolower($contact['email']);
                    if (!isset($emails[$email])) $ids[] = $id;
                    $emails[$email] = true;
                }
            }
            if (count($data['contacts']) < 100) return $ids;
        }
        throw new \RuntimeException('Subscriber safety limit reached; review pagination before sending.');
    }

    public function create(string $name, string $html, string $user): array {
        return $this->request('POST', self::campaignsPath(), ['name' => $name, 'editorType' => 'html',
            'editorContent' => $html, 'timeZone' => Config::get('timezone'), 'userId' => $user]);
    }

    public function campaign(string $id): array {
        return $this->request('GET', self::campaignsPath() . '/' . rawurlencode($id));
    }

    public function verifyDraft(array $campaign, string $name, string $title, string $excerpt, string $url): void {
        if (($campaign['status'] ?? '') !== 'draft' || ($campaign['name'] ?? '') !== $name || !empty($campaign['deleted'])) {
            throw new \RuntimeException('Remote campaign is not the expected draft; email held.');
        }
        $bodyUrl = $campaign['editorContentUrl'] ?? '';
        $parts = parse_url($bodyUrl);
        $googleStorage = ($parts['host'] ?? '') === 'storage.googleapis.com';
        $firebaseStorage = ($parts['host'] ?? '') === 'firebasestorage.googleapis.com' &&
            str_starts_with(rawurldecode($parts['path'] ?? ''), '/v0/b/highlevel-backend.appspot.com/o/location/' . Config::get('location_id') . '/emails/');
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || (!$googleStorage && !$firebaseStorage) ||
            isset($parts['user']) || isset($parts['port']) || isset($parts['fragment'])) {
            throw new \RuntimeException('Campaign content verification URL needs review; email held.');
        }
        $response = wp_safe_remote_get($bodyUrl, ['timeout' => 20, 'redirection' => 0, 'limit_response_size' => 500000]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) throw new \RuntimeException('Cannot verify saved campaign body; email held.');
        $body = wp_remote_retrieve_body($response);
        $plain = Policy::plain($body);
        foreach ([Policy::plain($title), $excerpt] as $text) {
            if (!str_contains($plain, $text)) throw new \RuntimeException('Saved campaign text differs; email held.');
        }
        if (!str_contains(html_entity_decode($body), $url) || !str_contains($body, '{{unsubscribe}}')) {
            throw new \RuntimeException('Saved campaign article/unsubscribe link missing; email held.');
        }
    }

    public function send(string $id, string $title, array $ids, string $user): array {
        if (!$ids) throw new \RuntimeException('No eligible subscribers; no campaign send requested.');
        return $this->request('POST', self::campaignsPath() . '/' . rawurlencode($id) . '/schedule', [
            'scheduleType' => 'immediate', 'timeZone' => Config::get('timezone'), 'userId' => $user,
            'emailMeta' => ['subject' => Config::get('brand') . ': ' . str_replace(['{{','}}'], '', Policy::plain($title)),
                'fromName' => Config::get('brand'), 'fromEmail' => Config::get('from_email'),
                'replyToAddress' => Config::get('reply_email'), 'previewText' => Config::get('preview_text')],
            'recipients' => ['type' => 'contact', 'contactIds' => array_values($ids)],
            'scheduleConfig' => ['tracking' => ['clickTracking' => false, 'utmTracking' => false], 'resend' => ['enabled' => false]],
        ]);
    }
}
