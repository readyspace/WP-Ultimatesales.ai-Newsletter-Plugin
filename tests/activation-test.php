<?php
require_once __DIR__ . '/wp-functions.php';
// Isolated activation checks. No WordPress database, HTTP or live options.
$mockRoot = sys_get_temp_dir() . '/rs-newsletter-activation-' . bin2hex(random_bytes(8));
mkdir($mockRoot . '/wp-admin/includes', 0700, true);
file_put_contents($mockRoot . '/wp-admin/includes/upgrade.php', '<?php');
register_shutdown_function(static function() use ($mockRoot) {
    unlink($mockRoot . '/wp-admin/includes/upgrade.php');
    rmdir($mockRoot . '/wp-admin/includes'); rmdir($mockRoot . '/wp-admin'); rmdir($mockRoot);
});
define('ABSPATH', $mockRoot . '/');
function register_activation_hook(...$a) {} function register_deactivation_hook(...$a) {}
function add_filter(...$a) {} function add_action(...$a) {}
function dbDelta($sql) { $GLOBALS['schema'] = $sql; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function add_option($name, $value, ...$a) { $GLOBALS['options'][$name] = $value; return true; }
function wp_next_scheduled(...$a) { return false; }
function wp_schedule_event(...$a) { ++$GLOBALS['scheduled']; return true; }
class ActivationDB {
    public $prefix = 'test_'; public $posts = 'test_posts';
    public $table_exists = true; public $returned_table = 'test_cs_newsletter_jobs'; public $seed_result = 0; public $seed_queries = 0;
    function get_charset_collate() { return ''; }
    function esc_like($value) { return addcslashes($value, '_%\\'); }
    function prepare($sql, ...$args) { return json_encode([$sql, $args]); }
    function get_var($sql) { return $this->table_exists ? $this->returned_table : null; }
    function query($query) { [$sql, $args] = json_decode($query, true); $GLOBALS['seed_statement'] = $sql; $GLOBALS['seed_arguments'] = $args; ++$this->seed_queries; return $this->seed_result; }
}
$wpdb = new ActivationDB();
require $argv[1] . '/cleverspeed-newsletter.php';
use CleverSpeed\Newsletter\Plugin;
$tests = 0;
function check($condition, $label) { global $tests; ++$tests; if (!$condition) throw new RuntimeException('FAIL ' . $label); }
function reset_case() {
    global $wpdb; $wpdb = new ActivationDB(); $GLOBALS['options'] = []; $GLOBALS['scheduled'] = 0;
}
foreach (['missing_table', 'wrong_table', 'failed_archive_seed'] as $fault) {
    reset_case();
    if ($fault === 'missing_table') $wpdb->table_exists = false;
    elseif ($fault === 'wrong_table') $wpdb->returned_table = 'other_cs_newsletter_jobs';
    else $wpdb->seed_result = false;
    $stopped = false;
    try { Plugin::activate(); } catch (RuntimeException $e) { $stopped = true; }
    check($stopped, $fault . ' stops activation');
    check(!$GLOBALS['options'], $fault . ' leaves installation markers absent');
    check($GLOBALS['scheduled'] === 0, $fault . ' does not start a worker');
}
foreach ([0, 5] as $seeded) {
    reset_case(); $wpdb->seed_result = $seeded; Plugin::activate();
    check(!empty($GLOBALS['options']['cs_newsletter_installed']), 'successful archive seed marks installation');
    check($GLOBALS['options']['cs_newsletter_settings']['mode'] === 'off', 'activation starts with sending off');
    check($GLOBALS['scheduled'] === 1, 'successful activation schedules one worker');
    check($wpdb->seed_queries === 1, 'first installation seeds once');
    check($GLOBALS['seed_arguments'] === ['test_cs_newsletter_jobs', 'test_posts'], 'archive seed identifiers are prepared separately');
}
reset_case(); $wpdb->prefix = 'MiXeD_'; $wpdb->posts = 'MiXeD_posts'; $wpdb->returned_table = 'mixed_cs_newsletter_jobs';
Plugin::activate();
check(!empty($GLOBALS['options']['cs_newsletter_installed']), 'case-folded database table names permit activation');
check($GLOBALS['seed_arguments'] === ['MiXeD_cs_newsletter_jobs', 'MiXeD_posts'], 'case-folded lookup preserves configured identifier arguments');
reset_case();
$GLOBALS['options'] = ['cs_newsletter_installed'=>123, 'cs_newsletter_settings'=>['mode'=>'draft']];
$wpdb->seed_result = false; Plugin::activate();
check($wpdb->seed_queries === 0, 'reactivation preserves the existing archive ledger');
check($GLOBALS['options']['cs_newsletter_settings']['mode'] === 'draft', 'reactivation preserves existing settings');
echo "PASS $tests activation checks (mocked database; no sends)\n";
