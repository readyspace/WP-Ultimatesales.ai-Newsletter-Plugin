<?php
namespace CleverSpeed\Newsletter;
defined('ABSPATH') || exit;

/** Private per-site configuration. No pilot/customer defaults are shipped. */
final class Config {
    public static function all(): array {
        $c = defined('RS_NEWSLETTER_CONFIG') ? RS_NEWSLETTER_CONFIG : [];
        return self::validate(is_array($c) ? $c : []);
    }
    public static function get(string $key): string {
        $c = self::all();
        if (!array_key_exists($key, $c)) throw new \RuntimeException('Unknown newsletter configuration field.');
        return $c[$key];
    }
    public static function ready(): bool {
        try { self::all(); return true; } catch (\Throwable $e) { return false; }
    }
    public static function fingerprint(): string {
        return hash('sha256', json_encode(self::all(), JSON_UNESCAPED_SLASHES));
    }
    public static function validate(array $c): array {
        $keys = ['location_id','confirmed_tag','pending_tag','unsubscribed_tag','cms_origin','public_origin',
            'brand','from_email','reply_email','timezone','legal_name','postal_address','privacy_url','preview_text'];
        foreach ($keys as $key) {
            if (!isset($c[$key]) || !is_string($c[$key]) || trim($c[$key]) === '' || strlen($c[$key]) > 500 ||
                preg_match('/[\x00-\x1f\x7f<>]/', $c[$key]) || str_contains($c[$key], '{{') || str_contains($c[$key], '}}')) {
                throw new \RuntimeException('Complete the validated private newsletter configuration first.');
            }
            $c[$key] = trim($c[$key]);
        }
        if (!preg_match('/^[A-Za-z0-9_-]{5,100}$/D', $c['location_id'])) throw new \RuntimeException('Invalid location ID.');
        if (count(array_unique([$c['confirmed_tag'],$c['pending_tag'],$c['unsubscribed_tag']])) !== 3) throw new \RuntimeException('Consent tags must be distinct.');
        foreach (['from_email','reply_email'] as $key) {
            if (!filter_var($c[$key], FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Invalid sender or reply address.');
        }
        foreach (['cms_origin','public_origin','privacy_url'] as $key) {
            $p = wp_parse_url($c[$key]);
            if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) ||
                isset($p['port']) || isset($p['query']) || isset($p['fragment']) || !filter_var($c[$key], FILTER_VALIDATE_URL)) {
                throw new \RuntimeException('Use clean HTTPS origins and privacy URL.');
            }
            if ($key !== 'privacy_url' && ($p['path'] ?? '') !== '') throw new \RuntimeException('Origins must not contain a path or trailing slash.');
        }
        if (!str_starts_with($c['privacy_url'], $c['public_origin'] . '/')) throw new \RuntimeException('Privacy URL must be on the configured public origin.');
        if (!in_array($c['timezone'], \DateTimeZone::listIdentifiers(), true) && $c['timezone'] !== 'UTC') throw new \RuntimeException('Invalid timezone.');
        return array_intersect_key($c, array_flip($keys));
    }
}
