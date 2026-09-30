<?php
// Isolated in-memory options. Never load WordPress or real credentials.
define('AUTH_KEY',str_repeat('test-only-auth-key-',4)); define('SECURE_AUTH_KEY',str_repeat('test-only-secure-key-',4));
function get_option($name,$default=[]) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name,$value,$autoload=true) { $GLOBALS['options'][$name]=$value;$GLOBALS['autoload']=$autoload;return true; }
require $argv[1].'/includes/credential.php';
use CleverSpeed\Newsletter\Credential;
$tests=0;
function check($yes,$name) { global $tests; ++$tests;if (!$yes) throw new RuntimeException('FAIL '.$name); }
check(!Credential::ready(),'missing credential fails closed');
$token=str_repeat('dummy-test-token-',4);
Credential::save($token);
check(Credential::ready(),'encrypted token readable');
check(Credential::read()===$token,'roundtrip');
check(!str_contains(json_encode($GLOBALS['options']),$token),'plaintext not stored');
check($GLOBALS['autoload']===false,'autoload disabled');
$first=$GLOBALS['options'];Credential::save($token);
check($first!==$GLOBALS['options'],'random nonce each save');
foreach (['short',str_repeat('x',4097),$token."\r\nHeader: injected",'{{'.$token.'}}'] as $bad) {
    try { Credential::save($bad); check(false,'reject invalid'); } catch (RuntimeException $e) { check(Credential::read()===$token,'invalid input preserves value'); }
}
$good=$GLOBALS['options'];
foreach (['cipher','iv','tag'] as $field) {
    $GLOBALS['options']=$good;$GLOBALS['options']['rs_newsletter_credential'][$field]=base64_encode(str_repeat('x',16));
    check(!Credential::ready(),'tampered '.$field.' rejected');
}
$GLOBALS['options']=$good;$GLOBALS['options']['rs_newsletter_credential']['v']=2;
check(!Credential::ready(),'unknown format rejected');
echo "PASS $tests credential checks (dummy tokens only)\n";
