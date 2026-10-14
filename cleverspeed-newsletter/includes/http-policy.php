<?php
namespace CleverSpeed\Newsletter;
defined('ABSPATH') || exit;

/** Pure request validation. It neither registers hooks nor retains credentials. */
final class HttpPolicy {
    private const API_HOST = 'services.leadconnectorhq.com';

    /** Validate the authority and decode the path once, rejecting ambiguous encodings. */
    public static function cleanUrl(string $url): ?array {
        if (!filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) return null;
        $parts = wp_parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
            isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) return null;
        $path = rawurldecode($parts['path'] ?? '');
        if (preg_match('/[\x00-\x20\x7f\\\\%]/', $path) || str_contains($path, '//')) return null;
        foreach (explode('/', $path) as $segment) if ($segment === '.' || $segment === '..') return null;
        return $parts;
    }

    private static function originValid(string $origin): bool {
        $parts = self::cleanUrl($origin);
        return $parts !== null && !isset($parts['path']) && !isset($parts['query']);
    }

    /** Public article paths may have either final-slash form, on the configured origin only. */
    public static function articleUrlAllowed(string $url, string $origin): bool {
        $parts = self::cleanUrl($url);
        if (!$parts || !self::originValid($origin) || ('https://' . $parts['host']) !== $origin ||
            isset($parts['query']) || empty($parts['path']) || $parts['path'] === '/') return false;
        return !preg_match('~^/(?:wp-admin|wp-json|api|cms|login|login\.php|wp-login\.php)(?:/|$)~i', rawurldecode($parts['path']));
    }

    /** Only this location's email objects in the provider's exact known storage bucket. */
    public static function contentUrlAllowed(string $url, string $location): bool {
        $parts = self::cleanUrl($url);
        if (!$parts || !preg_match('/^[A-Za-z0-9_-]{5,100}$/D', $location)) return false;
        $prefix = match ($parts['host']) {
            'firebasestorage.googleapis.com' => '/v0/b/highlevel-backend.appspot.com/o/location/' . $location . '/emails/',
            'storage.googleapis.com' => '/highlevel-backend.appspot.com/location/' . $location . '/emails/',
            default => '',
        };
        $path = rawurldecode($parts['path'] ?? '');
        return $prefix !== '' && str_starts_with($path, $prefix) && strlen($path) > strlen($prefix) &&
            strlen($parts['query'] ?? '') <= 2048;
    }

    private static function configValid(array $config): bool {
        foreach (['location_id','confirmed_tag','cms_origin','public_origin','brand','from_email','reply_email',
            'timezone','privacy_url','preview_text'] as $key) {
            $value = $config[$key] ?? null;
            if (!is_string($value) || trim($value) === '' || trim($value) !== $value || strlen($value) > 500 ||
                preg_match('/[\x00-\x1f\x7f<>]/', $value) || str_contains($value, '{{') || str_contains($value, '}}')) return false;
        }
        return preg_match('/^[A-Za-z0-9_-]{5,100}$/D', $config['location_id']) === 1 &&
            self::originValid($config['cms_origin']) && self::originValid($config['public_origin']) &&
            self::articleUrlAllowed($config['privacy_url'], $config['public_origin']) &&
            filter_var($config['from_email'], FILTER_VALIDATE_EMAIL) !== false &&
            filter_var($config['reply_email'], FILTER_VALIDATE_EMAIL) !== false &&
            ($config['timezone'] === 'UTC' || in_array($config['timezone'], \DateTimeZone::listIdentifiers(), true));
    }

    private static function headers($headers): ?array {
        if (!is_array($headers)) return null;
        $normalized = [];
        foreach ($headers as $key => $value) {
            if (!is_string($key) || !is_string($value)) return null;
            $key = strtolower($key);
            if (array_key_exists($key, $normalized)) return null;
            $normalized[$key] = $value;
        }
        return $normalized;
    }

    private static function noBody(array $args): bool {
        return !array_key_exists('body', $args) || $args['body'] === null || $args['body'] === '';
    }

    private static function templateLinksAllowed(string $html, array $config): bool {
        if ($html === '' || strlen($html) > 500000 || str_contains($html, "\0") ||
            preg_match('~<(?:script|iframe|form|object|embed)\b~i', $html)) return false;
        if (!preg_match_all('~\bhref\s*=\s*([\'"])(.*?)\1~is', $html, $links) || count($links[2]) !== 3) return false;
        $hrefs = array_map(static fn($href) => html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $links[2]);
        return self::articleUrlAllowed($hrefs[0], $config['public_origin']) &&
            $hrefs[1] === $config['privacy_url'] && $hrefs[2] === '{{email.unsubscribe_link}}';
    }

    /** Client request-intent and fresh draft/audience attestations are independent additional gates. */
    public static function allowed(string $url, array $args, array $config, string $token, array $settings): bool {
        if (!self::configValid($config) || !in_array($settings['mode'] ?? null, ['off','draft','live'], true) ||
            ($args['sslverify'] ?? null) !== true || ($args['redirection'] ?? null) !== 0 ||
            ($args['blocking'] ?? true) !== true || ($args['stream'] ?? false) !== false ||
            (array_key_exists('cookies', $args) && $args['cookies'] !== []) ||
            (array_key_exists('filename', $args) && !in_array($args['filename'], [null,''], true))) return false;
        $parts = self::cleanUrl($url);
        if (!$parts) return false;
        $method = $args['method'] ?? 'GET';
        if (!is_string($method)) return false;
        $method = strtoupper($method);
        $headers = self::headers($args['headers'] ?? []);
        if ($headers === null) return false;

        if ($parts['host'] !== self::API_HOST) {
            // No CRM header, body or credential is added to article/storage reads.
            if ($method !== 'GET' || !self::noBody($args) || $headers !== [] || ($args['timeout'] ?? null) !== 20) return false;
            return (($args['limit_response_size'] ?? null) === 2000000 && self::articleUrlAllowed($url, $config['public_origin'])) ||
                (($args['limit_response_size'] ?? null) === 500000 && self::contentUrlAllowed($url, $config['location_id']));
        }

        if (!preg_match('/^[A-Za-z0-9._-]{30,4096}$/D', $token) || ($args['timeout'] ?? null) !== 30 ||
            ($args['limit_response_size'] ?? null) !== 2000000 ||
            !is_string($headers['authorization'] ?? null) || !hash_equals('Bearer ' . $token, $headers['authorization']) ||
            ($headers['accept'] ?? null) !== 'application/json' || ($headers['content-type'] ?? null) !== 'application/json' ||
            !is_string($headers['version'] ?? null) ||
            array_diff(array_keys($headers), ['authorization','version','accept','content-type'])) return false;
        $location = $config['location_id'];
        $path = $parts['path'] ?? '';
        if ($method === 'GET' && $path === '/users/' && ($parts['query'] ?? null) === 'locationId=' . $location) {
            return $headers['version'] === '2021-07-28' && self::noBody($args);
        }
        if (isset($parts['query'])) return false;
        $body = is_string($args['body'] ?? null) ? json_decode($args['body'], true) : null;
        if ($method === 'POST' && $path === '/contacts/search') {
            if ($headers['version'] !== '2021-07-28' || !is_array($body) ||
                !is_int($body['page'] ?? null) || $body['page'] < 1 || $body['page'] > 100) return false;
            return $body === ['locationId'=>$location, 'page'=>$body['page'], 'pageLimit'=>100,
                'filters'=>[['field'=>'tags','operator'=>'contains','value'=>$config['confirmed_tag']]]];
        }
        $root = '/emails/locations/' . $location . '/campaigns/emails';
        if ($headers['version'] !== 'v3') return false;
        if ($method === 'GET' && preg_match('~^' . preg_quote($root, '~') . '/[A-Za-z0-9_-]{1,100}$~D', $path)) {
            return self::noBody($args);
        }
        $user = $settings['user_id'] ?? null;
        if (!is_string($user) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $user) ||
            ($settings['verified'] ?? null) !== true || !is_array($body)) return false;
        if ($method === 'POST' && $path === $root) {
            return in_array($settings['mode'], ['draft','live'], true) &&
                array_keys($body) === ['name','editorType','editorContent','timeZone','userId'] &&
                is_string($body['name']) && strlen($body['name']) <= 500 &&
                !preg_match('/[\x00-\x1f\x7f]/', $body['name']) &&
                preg_match('~^' . preg_quote($config['brand'], '~') . ' WordPress post [1-9][0-9]* — .+$~uD', $body['name']) &&
                $body['editorType'] === 'html' && is_string($body['editorContent']) &&
                self::templateLinksAllowed($body['editorContent'], $config) &&
                $body['timeZone'] === $config['timezone'] && $body['userId'] === $user;
        }
        if ($method !== 'POST' || $settings['mode'] !== 'live' ||
            !preg_match('~^' . preg_quote($root, '~') . '/[A-Za-z0-9_-]{1,100}/schedule$~D', $path) ||
            array_keys($body) !== ['scheduleType','timeZone','userId','emailMeta','recipients','scheduleConfig']) return false;
        $meta = $body['emailMeta']; $recipients = $body['recipients'];
        if (!is_array($meta) || array_keys($meta) !== ['subject','fromName','fromEmail','replyToAddress','previewText'] ||
            !is_string($meta['subject']) || strlen($meta['subject']) > 500 ||
            !str_starts_with($meta['subject'], $config['brand'] . ': ') ||
            trim(substr($meta['subject'], strlen($config['brand'] . ': '))) === '' ||
            str_contains($meta['subject'], '{{') || str_contains($meta['subject'], '}}') ||
            preg_match('/[\x00-\x1f\x7f]/', $meta['subject']) ||
            !is_array($recipients) || array_keys($recipients) !== ['type','contactIds','freezeList'] ||
            $recipients['type'] !== 'contact' || $recipients['freezeList'] !== true ||
            !is_array($recipients['contactIds']) || !$recipients['contactIds'] ||
            !array_is_list($recipients['contactIds']) || count($recipients['contactIds']) > 10000) return false;
        foreach ($recipients['contactIds'] as $id) if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $id)) return false;
        if (count(array_unique($recipients['contactIds'])) !== count($recipients['contactIds'])) return false;
        return $body['scheduleType'] === 'immediate' && $body['timeZone'] === $config['timezone'] && $body['userId'] === $user &&
            $meta['fromName'] === $config['brand'] && $meta['fromEmail'] === $config['from_email'] &&
            $meta['replyToAddress'] === $config['reply_email'] && $meta['previewText'] === $config['preview_text'] &&
            $body['scheduleConfig'] === ['tracking'=>['clickTracking'=>false,'utmTracking'=>false], 'resend'=>['enabled'=>false]];
    }
}
