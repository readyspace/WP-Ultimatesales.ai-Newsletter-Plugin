<?php
namespace CleverSpeed\Newsletter;
defined('ABSPATH') || exit;
require_once __DIR__ . '/http-policy.php';
require_once __DIR__ . '/html-equivalence.php';

final class Client {
    private const BASE = 'https://services.leadconnectorhq.com';
    /** Credentials exist here only during this exact synchronous request. */
    private static ?array $httpIntent = null;
    private array $eligibleIds = [];
    private int $eligibleCheckedAt = 0;
    private string $eligibleFingerprint = '';
    private string $draftReadId = '';
    private string $draftReadHash = '';
    private string $verifiedCampaign = '';
    private string $verifiedTitle = '';
    private string $verifiedFingerprint = '';
    private ?array $pendingSchedule = null;

    /** Scope only bounded unauthenticated newsletter reads, never API writes. */
    public static function withHttpIntent(string $operation, string $url, array $args, callable $request): mixed {
        if (!in_array($operation, ['article', 'draft_body'], true)) throw new \RuntimeException('Invalid newsletter HTTP operation.');
        $config = Config::all();
        $allowedUrl = $operation === 'article'
            ? HttpPolicy::articleUrlAllowed($url, $config['public_origin'])
            : HttpPolicy::contentUrlAllowed($url, $config['location_id']);
        $expectedLimit = $operation === 'article' ? 2000000 : 500000;
        $method = $args['method'] ?? 'GET';
        if (!$allowedUrl || !is_string($method) || strtoupper($method) !== 'GET' ||
            ($args['timeout'] ?? null) !== 20 || ($args['limit_response_size'] ?? null) !== $expectedLimit ||
            !HttpPolicy::allowed($url, $args, $config, '', Plugin::settings())) {
            throw new \RuntimeException('Unsafe newsletter read request; email held.');
        }
        return self::scopedHttp($operation, $url, $args, $request);
    }

    private static function scopedHttp(string $operation, string $url, array $args, callable $request): mixed {
        $previous = self::$httpIntent;
        self::$httpIntent = ['operation'=>$operation, 'url'=>$url, 'args'=>$args,
            'config_fingerprint'=>Config::fingerprint()];
        $guard = static function ($pre, $parsedArgs, $actualUrl) {
            if ($pre !== false || !self::directRequest()) return $pre;
            return self::allowsHttp($actualUrl, $parsedArgs) ? false :
                new \WP_Error('rs_newsletter_unsafe_transport', 'Newsletter request arguments changed; email held.');
        };
        try {
            add_filter('pre_http_request', $guard, PHP_INT_MAX, 3);
            return $request();
        } finally {
            self::$httpIntent = $previous;
            remove_filter('pre_http_request', $guard, PHP_INT_MAX);
        }
    }

    /** Read-only host integration: match the active request after harmless WP defaults. */
    public static function allowsHttp(string $url, array $args): bool {
        $intent = self::$httpIntent;
        if ($intent === null || !hash_equals($intent['url'], $url) || !self::directRequest()) return false;
        try {
            if (!hash_equals($intent['config_fingerprint'], Config::fingerprint())) return false;
            $expected = $intent['args'];
            $method = $args['method'] ?? 'GET'; $expectedMethod = $expected['method'] ?? 'GET';
            if (!is_string($method) || !is_string($expectedMethod) || strtoupper($method) !== strtoupper($expectedMethod)) return false;
            $body = $args['body'] ?? ''; $expectedBody = $expected['body'] ?? '';
            if (!is_string($body) || !is_string($expectedBody) || !hash_equals($expectedBody, $body) ||
                ($args['sslverify'] ?? null) !== true || ($args['redirection'] ?? null) !== 0 ||
                ($args['blocking'] ?? true) !== true || ($args['stream'] ?? false) !== false ||
                !empty($args['filename']) || !empty($args['cookies'])) return false;
            foreach (['timeout','limit_response_size'] as $key) {
                if (!array_key_exists($key, $expected) || ($args[$key] ?? null) !== $expected[$key]) return false;
            }
            $normalize = static function ($headers): ?array {
                if (!is_array($headers)) return null;
                $result = [];
                foreach ($headers as $key=>$value) {
                    if (!is_string($key) || !is_string($value) || array_key_exists(strtolower($key), $result)) return null;
                    $result[strtolower($key)] = $value;
                }
                ksort($result); return $result;
            };
            $actualHeaders = $normalize($args['headers'] ?? []);
            $expectedHeaders = $normalize($expected['headers'] ?? []);
            if ($actualHeaders === null || $expectedHeaders === null || $actualHeaders !== $expectedHeaders) return false;
            $operation = $intent['operation'];
            if ($operation !== 'api' && ($args['reject_unsafe_urls'] ?? null) !== true) return false;
            if ($operation === 'article' && !HttpPolicy::articleUrlAllowed($url, Config::get('public_origin'))) return false;
            if ($operation === 'draft_body' && !self::contentUrlAllowed($url)) return false;
            if (!in_array($operation, ['api','article','draft_body'], true)) return false;
            $token = $operation === 'api' ? Credential::read() : '';
            $settings = Plugin::settings();
            $allowed = HttpPolicy::allowed($url, $args, Config::all(), $token, $settings);
            unset($token);
            if (!$allowed) return false;
            return $operation !== 'api' || !self::isCampaignWrite(strtoupper($method), $url) || Plugin::verified();
        } catch (\Throwable $e) { return false; }
    }

    /** An HTTP hook cannot borrow an outer intent, even with identical copied arguments. */
    private static function directRequest(): bool {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 32);
        $wrappers = ['wp_remote_request','wp_remote_get','wp_remote_post','wp_remote_head',
            'wp_safe_remote_request','wp_safe_remote_get','wp_safe_remote_post'];
        foreach ($trace as $i=>$frame) {
            if (isset($frame['class']) || !in_array($frame['function'] ?? '', $wrappers, true)) continue;
            // Accommodate a safe wrapper delegating to its ordinary wrapper.
            if (in_array($frame['function'], ['wp_remote_get','wp_remote_post','wp_remote_request'], true) &&
                ($trace[$i+1]['function'] ?? '') === 'wp_safe_' . substr($frame['function'], 3) &&
                !isset($trace[$i+1]['class'])) ++$i;
            $callback = $trace[$i+1] ?? [];
            $scope = $trace[$i+2] ?? [];
            return ($callback['file'] ?? '') === __FILE__ &&
                ($scope['class'] ?? '') === self::class && ($scope['function'] ?? '') === 'scopedHttp';
        }
        return false;
    }

    private static function isCampaignWrite(string $method, string $url): bool {
        if ($method !== 'POST') return false;
        $path = wp_parse_url($url, PHP_URL_PATH);
        return $path === self::campaignsPath() || (is_string($path) && str_ends_with($path, '/schedule'));
    }

    private static function json(array $body): string {
        $encoded = wp_json_encode($body);
        if (!is_string($encoded)) throw new \RuntimeException('Cannot encode newsletter request; email held.');
        return $encoded;
    }

    public static function credentialReady(): bool { return Credential::ready(); }

    public function request(string $method, string $path, ?array $body = null, string $version = 'v3'): array {
        if (!self::credentialReady()) throw new \RuntimeException('Private server credential is missing or not owner-only.');
        if ($path === '' || $path[0] !== '/' || str_contains($path, '://')) throw new \RuntimeException('Invalid API path.');
        $method = strtoupper($method);
        $encoded = $body === null ? null : self::json($body);
        if (str_ends_with($path, '/schedule')) {
            if ($method !== 'POST' || $encoded === null || $this->pendingSchedule === null ||
                $this->pendingSchedule['path'] !== $path ||
                !hash_equals($this->pendingSchedule['body_hash'], hash('sha256', $encoded))) {
                throw new \RuntimeException('Schedule requires freshly verified newsletter intent.');
            }
            // Consume authorization before transport, including ambiguous transport failures.
            $this->pendingSchedule = null;
        }
        $token = Credential::read();
        $args = ['method' => $method, 'timeout' => 30, 'redirection' => 0, 'sslverify' => true, 'limit_response_size'=>2000000,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Version' => $version,
                'Accept' => 'application/json', 'Content-Type' => 'application/json']];
        if ($encoded !== null) $args['body'] = $encoded;
        $url = self::BASE . $path;
        if ((self::isCampaignWrite($method, $url) && !Plugin::verified()) ||
            !HttpPolicy::allowed($url, $args, Config::all(), $token, Plugin::settings())) {
            unset($token, $args);
            throw new \RuntimeException('Newsletter API request requires verified configuration and permitted operation.');
        }
        try { $response = self::scopedHttp('api', $url, $args, static fn()=>wp_remote_request($url, $args)); }
        finally { unset($token, $args); }
        if (is_wp_error($response)) throw new \RuntimeException('UltimateSales.AI transport failure. Remote outcome may be unknown; do not blindly resend.');
        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            // Never persist response bodies: they can contain contact data or sensitive diagnostics.
            throw new \RuntimeException('UltimateSales.AI returned HTTP ' . (int) $status . '; review required.');
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) throw new \RuntimeException('Unexpected UltimateSales.AI response; review required.');
        return $data;
    }

    public static function campaignsPath(): string {
        return '/emails/locations/' . Config::get('location_id') . '/campaigns/emails';
    }

    public function recipients(): array {
        $this->eligibleIds = []; $this->eligibleCheckedAt = 0; $this->eligibleFingerprint = '';
        $ids = [];
        $emails = [];
        $seen = [];
        // Bounded pagination. A limit/error holds the entire campaign, never sends a partial list.
        for ($page = 1; $page <= 100; $page++) {
            $data = $this->request('POST', '/contacts/search', ['locationId' => Config::get('location_id'),
                'page' => $page, 'pageLimit' => 100,
                'filters' => [['field' => 'tags', 'operator' => 'contains', 'value' => Config::get('confirmed_tag')]]], '2021-07-28');
            if (!isset($data['contacts']) || !is_array($data['contacts']) || !array_is_list($data['contacts']) || count($data['contacts']) > 100) throw new \RuntimeException('Unexpected contacts response; email held.');
            foreach ($data['contacts'] as $contact) {
                if (!is_array($contact)) throw new \RuntimeException('Unexpected contact record; email held.');
                $id = $contact['id'] ?? '';
                if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $id) || isset($seen[$id])) throw new \RuntimeException('Contact pagination is incomplete or repeated; email held.');
                $seen[$id] = true;
                // Recheck fresh membership before scheduling. Global DND must
                // be explicit false; absent email metadata remains subject to
                // the native marketing campaign's final suppression.
                if (Policy::eligible($contact)) {
                    $email = strtolower($contact['email']);
                    if (!isset($emails[$email])) $ids[] = $id;
                    $emails[$email] = true;
                }
            }
            if (count($data['contacts']) < 100) {
                $this->eligibleIds = $ids; $this->eligibleCheckedAt = time();
                $this->eligibleFingerprint = Config::fingerprint();
                return $ids;
            }
        }
        throw new \RuntimeException('Subscriber safety limit reached; review pagination before sending.');
    }

    public function create(string $name, string $html, string $user): array {
        return $this->request('POST', self::campaignsPath(), ['name' => $name, 'editorType' => 'html',
            'editorContent' => $html, 'timeZone' => Config::get('timezone'), 'userId' => $user]);
    }

    public function campaign(string $id): array {
        $this->draftReadId = ''; $this->draftReadHash = '';
        $this->verifiedCampaign = ''; $this->verifiedTitle = ''; $this->verifiedFingerprint = '';
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $id)) throw new \RuntimeException('Invalid campaign identifier.');
        $campaign = $this->request('GET', self::campaignsPath() . '/' . rawurlencode($id));
        $this->draftReadId = $id;
        $this->draftReadHash = hash('sha256', self::json($campaign));
        return $campaign;
    }

    public function verifyDraft(array $campaign, string $name, string $title, string $excerpt, string $url): void {
        $this->verifiedCampaign = ''; $this->verifiedTitle = ''; $this->verifiedFingerprint = '';
        if (($campaign['status'] ?? '') !== 'draft' || ($campaign['name'] ?? '') !== $name || !empty($campaign['deleted'])) {
            throw new \RuntimeException('Remote campaign is not the expected draft; email held.');
        }
        $bodyUrl = $campaign['editorContentUrl'] ?? '';
        if (!is_string($bodyUrl) || !self::contentUrlAllowed($bodyUrl)) {
            throw new \RuntimeException('Campaign content verification URL needs review; email held.');
        }
        $args = ['method'=>'GET', 'timeout'=>20, 'redirection'=>0, 'sslverify'=>true, 'limit_response_size'=>500000];
        $response = self::withHttpIntent('draft_body', $bodyUrl, $args, static fn()=>wp_safe_remote_get($bodyUrl, $args));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) throw new \RuntimeException('Cannot verify saved campaign body; email held.');
        // This comparison runs after every WordPress HTTP response filter has completed.
        if (!HtmlEquivalence::equivalent(Policy::content($title, $excerpt, $url), wp_remote_retrieve_body($response))) {
            throw new \RuntimeException('Saved campaign content differs from the expected newsletter; email held.');
        }
        if ($this->draftReadId !== '' && hash_equals($this->draftReadHash, hash('sha256', self::json($campaign)))) {
            $this->verifiedCampaign = $this->draftReadId;
            $this->verifiedTitle = str_replace(['{{','}}'], '', Policy::plain($title));
            $this->verifiedFingerprint = Config::fingerprint();
        }
    }

    public static function contentUrlAllowed(string $url): bool {
        try { return HttpPolicy::contentUrlAllowed($url, Config::get('location_id')); }
        catch (\Throwable $e) { return false; }
    }

    public function send(string $id, string $title, array $ids, string $user): array {
        if (!$ids) throw new \RuntimeException('No eligible subscribers; no campaign send requested.');
        $now = time(); $fingerprint = Config::fingerprint();
        if (Plugin::settings()['mode'] !== 'live' || !Plugin::verified() ||
            $this->verifiedCampaign !== $id || $this->verifiedTitle !== str_replace(['{{','}}'], '', Policy::plain($title)) ||
            !hash_equals($this->verifiedFingerprint, $fingerprint) || !hash_equals($this->eligibleFingerprint, $fingerprint) ||
            $this->eligibleCheckedAt === 0 || $now < $this->eligibleCheckedAt || $now - $this->eligibleCheckedAt > 60 ||
            $ids !== $this->eligibleIds) {
            throw new \RuntimeException('Send requires this exact verified draft and a fresh complete eligible audience.');
        }
        $this->verifiedCampaign = ''; $this->verifiedTitle = ''; $this->verifiedFingerprint = '';
        $this->eligibleIds = []; $this->eligibleCheckedAt = 0; $this->eligibleFingerprint = '';
        $path = self::campaignsPath() . '/' . rawurlencode($id) . '/schedule';
        $body = [
            'scheduleType' => 'immediate', 'timeZone' => Config::get('timezone'), 'userId' => $user,
            'emailMeta' => ['subject' => Config::get('brand') . ': ' . str_replace(['{{','}}'], '', Policy::plain($title)),
                'fromName' => Config::get('brand'), 'fromEmail' => Config::get('from_email'),
                'replyToAddress' => Config::get('reply_email'), 'previewText' => Config::get('preview_text')],
            'recipients' => ['type' => 'contact', 'contactIds' => array_values($ids), 'freezeList'=>true],
            'scheduleConfig' => ['tracking' => ['clickTracking' => false, 'utmTracking' => false], 'resend' => ['enabled' => false]],
        ];
        $this->pendingSchedule = ['path'=>$path, 'body_hash'=>hash('sha256', self::json($body))];
        try { return $this->request('POST', $path, $body); }
        finally { $this->pendingSchedule = null; }
    }
}
