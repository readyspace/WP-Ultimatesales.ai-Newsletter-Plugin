<?php
/** Standalone synthetic-only tests; requires no WordPress boot or provider connection. */
if (!defined('ABSPATH')) define('ABSPATH', '/test-only/');

$GLOBALS['can_manage_options'] = false;
$GLOBALS['wp_site_title'] = 'Synthetic WordPress site';
$GLOBALS['remote_request_count'] = 0;
function current_user_can($cap) { return $cap === 'manage_options' && $GLOBALS['can_manage_options']; }
function get_bloginfo($key) { return $key === 'name' ? $GLOBALS['wp_site_title'] : ''; }
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function wp_parse_url($value) { return parse_url($value); }
function wp_remote_request(...$args) { ++$GLOBALS['remote_request_count']; throw new RuntimeException('Unexpected provider HTTP'); }

$root = $argv[1] ?? dirname(__DIR__) . '/cleverspeed-newsletter';
require $root . '/includes/sending-configuration-status.php';

use CleverSpeed\Newsletter\SendingConfigurationStatus as Status;

$tests = 0;
function check($condition, $description): void {
    global $tests;
    ++$tests;
    if (!$condition) throw new RuntimeException('FAIL: ' . $description);
}
function output(array $config, array $settings = ['mode'=>'off'], bool $verified = false): string {
    ob_start();
    try { Status::render($config, $settings, $verified); return ob_get_contents(); }
    finally { ob_end_clean(); }
}
function site(string $name, string $domain, string $loc, string $tag): array {
    return [
        'brand' => $name, 'public_origin' => 'https://' . $domain,
        'location_id' => $loc, 'from_email' => 'news@' . $domain,
        'reply_email' => 'reply@' . $domain,
        'confirmed_tag' => $tag, 'pending_tag' => $tag . '-pending',
        'unsubscribed_tag' => $tag . '-unsubscribed',
    ];
}
$one = site('Publication One', 'one.example.test', 'TEST_00001', 'one-confirmed');
$two = site('Publication Two', 'two.example.test', 'TEST_00002', 'two-confirmed');

// Render is independently capability-gated even if invoked outside Plugin::page().
check(output($one) === '', 'non-administrator sees no heading or configuration');
$GLOBALS['can_manage_options'] = true;
$html = output($one, ['mode'=>'draft'], true);
check(str_contains($html, 'Sending configuration'), 'administrator can see new section');
check(str_contains($html, 'Publication One'), 'saved publication name shown');
check(str_contains($html, 'https://one.example.test'), 'saved public origin shown');
check(str_contains($html, 'TEST_00001'), 'saved provider location ID shown');
check(str_contains($html, 'news@one.example.test'), 'saved sender email shown');
check(str_contains($html, 'reply@one.example.test'), 'saved reply-to email shown');
check(str_contains($html, 'one-confirmed'), 'confirmed tag shown');
check(str_contains($html, 'one-confirmed-pending'), 'pending exclusion tag shown');
check(str_contains($html, 'one-confirmed-unsubscribed'), 'unsubscribed exclusion tag shown');
check(str_contains($html, 'Draft-only'), 'sending mode shown');
check(str_contains($html, 'Recorded for this configuration'), 'gate status shown');
check(str_contains($html, 'not a new live provider check'), 'gate is not mistaken for automatic verification');
check(str_contains($html, 'Do Not Disturb'), 'DND explanation included');
check(str_contains($html, 'must explicitly be inactive'), 'email DND rule explained');
check(str_contains($html, 'Duplicate email addresses'), 'deduplication explained');
check(str_contains($html, 'not prove consent'), 'count disclaimer included');
check(str_contains($html, 'Unavailable — not checked'), 'no misleading current eligible count');
check(str_contains($html, 'Not checked'), 'no invented count check timestamp');
check($GLOBALS['remote_request_count'] === 0, 'render made no provider requests');

// Every call receives a separate per-installation configuration; no Academy defaults.
$second = output($two, ['mode'=>'live'], false);
check(str_contains($second, 'Publication Two') && !str_contains($second, 'Publication One'), 'site B has its own publication');
check(str_contains($second, 'TEST_00002') && !str_contains($second, 'TEST_00001'), 'site B has its own provider ID');
check(str_contains($second, 'two-confirmed') && !str_contains($second, 'one-confirmed'), 'site B has its own tag');
check(str_contains($second, 'https://two.example.test') && !str_contains($second, 'https://one.example.test'), 'site B has its own public origin');
check(str_contains($second, 'Live'), 'site B current mode');
check(str_contains($second, 'Not recorded for this configuration'), 'site B unverified gate');
check(!str_contains($second, 'ReadySpace Academy'), 'site output is not Academy-hardcoded');

// Absent values are missing, bad values unavailable; malformed values never break rendering.
$missing = output(['brand'=>'', 'from_email'=>[], 'reply_email'=>null, 'public_origin'=>'http://not-secure.test',
    'location_id'=>'illegal!ID', 'confirmed_tag'=>"bad\r\nheader"]);
check(str_contains($missing, 'Not configured'), 'missing or blank fields are identified');
check(str_contains($missing, 'Unavailable'), 'malformed fields do not expose errors');
check(!str_contains($missing, 'http://not-secure.test'), 'bad URL hidden');
check(!str_contains($missing, 'illegal!ID'), 'bad provider ID hidden');
check(!str_contains($missing, "bad\r\nheader"), 'unsafe value not echoed');

$evil = $one;
$evil['brand'] = '<svg onload=alert(1)>';
$evil['token'] = 'PRIVATE-TOKEN-MUST-NOT-SHOW';
$evil['api_key'] = 'PRIVATE-API-KEY-MUST-NOT-SHOW';
$evil['contacts'] = [['email'=>'private.person@example.test', 'name'=>'PRIVATE-CONTACT-MUST-NOT-SHOW']];
$evil['confirmed_tag'] = 'confirmed & interested';
$evil['reply_email'] = 'invalid-address';
$danger = output($evil, ['mode'=>'<img src=x onerror=alert(1)>'], false);
check(!str_contains($danger, '<svg'), 'HTML injection is not rendered');
check(!str_contains($danger, 'PRIVATE-TOKEN'), 'unknown token field never displayed');
check(!str_contains($danger, 'PRIVATE-API-KEY'), 'unknown secret key never displayed');
check(!str_contains($danger, 'private.person@example.test'), 'individual contact not displayed');
check(!str_contains($danger, 'PRIVATE-CONTACT'), 'contact name not displayed');
check(str_contains($danger, 'confirmed &amp; interested'), 'valid configured text is HTML-escaped');
check(!str_contains($danger, 'invalid-address'), 'invalid address hidden');
check(!str_contains($danger, '<img'), 'invalid mode never rendered raw');

$readOnlyConfig = $one;
$readOnlySettings = ['mode'=>'live','verified'=>true,'user_id'=>'test-staff'];
$copyConfig = $readOnlyConfig; $copySettings = $readOnlySettings;
output($readOnlyConfig, $readOnlySettings, true);
check($copyConfig === $readOnlyConfig && $copySettings === $readOnlySettings, 'render does not mutate configuration or settings');
check($GLOBALS['remote_request_count'] === 0, 'all cases made zero provider HTTP calls');
echo "PASS {$tests} read-only sending configuration status assertions (synthetic data, no HTTP)\n";
