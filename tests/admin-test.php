<?php
require_once __DIR__ . '/fixture.php';
// Isolated handler tests. No WordPress bootstrap, live options or HTTP.
define('ABSPATH','/unused/');
define('AUTH_KEY',str_repeat('test-only-auth-key-',4)); define('SECURE_AUTH_KEY',str_repeat('test-only-secure-key-',4));
function register_activation_hook(...$a) {} function register_deactivation_hook(...$a) {}
function add_filter(...$a) {} function add_action(...$a) {}
function current_user_can($cap) { return $GLOBALS['admin']; }
class Stop extends RuntimeException {} class Redirect extends RuntimeException {}
function wp_die(...$a) { throw new Stop('stopped'); }
function check_admin_referer($a) { if (!$GLOBALS['nonce']) wp_die(); }
function is_ssl() { return $GLOBALS['ssl']; }
function wp_unslash($s) { return stripslashes($s); }
function get_option($n,$d=[]) { return $GLOBALS['options'][$n] ?? $d; }
function update_option($n,$v,...$a) { if ($GLOBALS['db_failure']) return false; $GLOBALS['options'][$n]=$v;return true; }
function admin_url($s) { return 'https://example.test/wp-admin/'.$s; }
function wp_safe_redirect($url) { throw new Redirect('redirected'); }
require $argv[1].'/cleverspeed-newsletter.php';
use CleverSpeed\Newsletter\Plugin;
use CleverSpeed\Newsletter\Credential;
$tests=0;
function check($ok,$label) { global $tests;++$tests;if (!$ok) throw new RuntimeException('FAIL '.$label); }
function reset_case() {
    $GLOBALS['options']=['cs_newsletter_settings'=>['mode'=>'live','verified'=>true,'user_id'=>'test-staff']];
    $GLOBALS['admin']=true;$GLOBALS['nonce']=true;$GLOBALS['ssl']=true;$GLOBALS['db_failure']=false;
    $_SERVER['REQUEST_METHOD']='POST';$_POST=['token'=>str_repeat('dummy-new-token-',4)];
}
foreach (['admin','nonce','ssl'] as $gate) {
    reset_case();$GLOBALS[$gate]=false;
    try{Plugin::saveCredential();}catch(Stop $e){}
    check(!Credential::ready(),$gate.' rejected before credential write');
    check(Plugin::settings()['mode']==='live',$gate.' has no option mutation');
}
reset_case();$_SERVER['REQUEST_METHOD']='GET';try{Plugin::saveCredential();}catch(Stop $e){}
check(!Credential::ready(),'GET rejected');
reset_case();$GLOBALS['db_failure']=true;try{Plugin::saveCredential();}catch(Stop $e){}
check(!Credential::ready(),'failed stop prevents credential change');
reset_case();try{Plugin::saveCredential();}catch(Redirect $e){}
check(Credential::ready(),'valid admin save succeeds');
check(Plugin::settings()['mode']==='off','save stops sending');
check(Plugin::settings()['verified']===false,'save resets verification');
check(Plugin::settings()['user_id']==='','save resets staff identity');
check(!isset($_POST['token']),'submitted token removed from request');
$before=Credential::read();$_POST=['token'=>'invalid'];
try{Plugin::saveCredential();}catch(Stop $e){}
check(Credential::read()===$before,'invalid replacement preserves credential');
check(Plugin::settings()['mode']==='off','invalid replacement stays off');
check(!isset($_POST['token']),'invalid input removed');
echo "PASS $tests admin handler checks (mocked gates; no HTTP)\n";
