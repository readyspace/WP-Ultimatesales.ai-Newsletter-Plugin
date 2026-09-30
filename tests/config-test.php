<?php
require $argv[1] . '/includes/config.php';
use CleverSpeed\Newsletter\Config;
$tests = 0;
function check($ok, $label) { global $tests; ++$tests; if (!$ok) throw new RuntimeException('FAIL ' . $label); }
check(!Config::ready(), 'missing config fails closed');
require __DIR__ . '/fixture.php';
check(Config::ready(), 'complete fictional configuration');
foreach (array_keys(RS_NEWSLETTER_CONFIG) as $key) {
    $c = RS_NEWSLETTER_CONFIG; unset($c[$key]);
    try { Config::validate($c); check(false, 'missing field ' . $key); } catch (RuntimeException $e) {
        if (str_starts_with($e->getMessage(), 'FAIL ')) throw $e;
        check(true, 'reject missing ' . $key);
    }
}
foreach ([['public_origin'=>'http://example.org'],['public_origin'=>'https://example.org/path'],
    ['from_email'=>'invalid'],['brand'=>'{{contact.email}}'],['timezone'=>'not-a-timezone'],
    ['privacy_url'=>'https://other.example/privacy/'],['pending_tag'=>'newsletter-confirmed'],
    ['location_id'=>'unsafe/id'],['brand'=>"Injected\r\nHeader"]] as $bad) {
    try { Config::validate(array_replace(RS_NEWSLETTER_CONFIG,$bad)); check(false,'unsafe configuration'); }
    catch (RuntimeException $e) { if (str_starts_with($e->getMessage(),'FAIL ')) throw $e; check(true,'unsafe configuration rejected'); }
}
echo "PASS $tests configuration checks\n";
