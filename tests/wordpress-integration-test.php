<?php
/**
 * Actual WordPress bootstrap, filters, options, posts, cron and MySQL ledger.
 * Invoked only by scripts/test-wordpress-integration.sh. All data is fictional.
 * Every HTTP request is intercepted before transport; no email is delivered.
 */
use CleverSpeed\Newsletter\Client;
use CleverSpeed\Newsletter\Config;
use CleverSpeed\Newsletter\Credential;
use CleverSpeed\Newsletter\HttpPolicy;
use CleverSpeed\Newsletter\Plugin;
use CleverSpeed\Newsletter\Policy;

if (!defined('WP_CLI') || !WP_CLI || !str_contains(ABSPATH, 'rs-newsletter-wordpress.') ||
    !preg_match('/^rs_newsletter_test(?:_[a-zA-Z0-9_]+)?$/D', DB_NAME)) {
    throw new RuntimeException('This integration suite requires its disposable test laboratory.');
}
$GLOBALS['rs_checks'] = 0;
$GLOBALS['rs_mail_calls'] = 0;
function rs_check(bool $condition, string $name): void {
    ++$GLOBALS['rs_checks'];
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
}
function rs_throws(callable $operation, string $name): void {
    try { $operation(); } catch (RuntimeException $e) { rs_check(true, $name); return; }
    rs_check(false, $name);
}
function rs_response(string $body, int $code = 200, array $headers = []): array {
    return ['headers'=>$headers, 'body'=>$body, 'response'=>['code'=>$code,'message'=>'Test fixture'],
        'cookies'=>[], 'filename'=>null, 'http_response'=>null];
}
function rs_no_transport($pre, array $args, string $url) {
    return $pre === false ? new WP_Error('rs_test_no_transport', 'Real HTTP is disabled in this laboratory.') : $pre;
}
add_filter('pre_http_request', 'rs_no_transport', PHP_INT_MAX, 3);
add_filter('pre_wp_mail', static function ($pre, array $mail) {
    ++$GLOBALS['rs_mail_calls'];
    return true;
}, PHP_INT_MAX, 2);

$phase = getenv('RS_TEST_PHASE');
if ($phase === 'before_activation') {
    update_option('permalink_structure', '/%postname%/');
    $ids = [];
    for ($i = 0; $i < 3; ++$i) {
        $id = wp_insert_post(['post_type'=>'post', 'post_status'=>'publish', 'post_name'=>'archive-' . $i,
            'post_title'=>'Fictional archive article ' . $i, 'post_content'=>'Fictional archived article.',
            'post_excerpt'=>'This fictional archived article must never be sent again after a plugin upgrade.'], true);
        rs_check(!is_wp_error($id), 'archive fixture inserted before first activation');
        $ids[] = $id;
    }
    update_option('rs_test_archive_ids', $ids, false);
    echo "PASS archive fixture prepared before baseline activation\n";
    return;
}

rs_check(class_exists(Plugin::class), 'plugin loaded through real WordPress active-plugin bootstrap');
rs_check(home_url() === Config::get('cms_origin'), 'WordPress home matches the chosen conventional/headless configuration');
global $wpdb;
if ($phase === 'seed') {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $header = get_plugin_data(WP_PLUGIN_DIR . '/readyspace-newsletter-for-ultimatesales-ai/cleverspeed-newsletter.php', false, false);
    rs_check($header['Version'] === '0.3.3', 'baseline source is the actual published 0.3.3 code');
    $ids = get_option('rs_test_archive_ids');
    foreach ($ids as $id) rs_check(Plugin::record($id)['state'] === 'excluded', 'first activation excludes existing archive');
    Credential::save(str_repeat('fictional-test-token-', 4));
    update_option('cs_newsletter_settings', ['mode'=>'live', 'user_id'=>'author-test', 'cutover'=>1234567890,
        'verified'=>true, 'verified_config'=>Config::fingerprint(), 'operator_note'=>'Preserve existing settings'], false);
    Plugin::set($ids[1], ['state'=>'submitted', 'campaign_id'=>'historic-campaign', 'source_id'=>'historic-source',
        'recipient_count'=>7, 'attempts'=>1, 'content_hash'=>str_repeat('a',64), 'note'=>'Previously accepted; never resend']);
    Plugin::set($ids[2], ['state'=>'uncertain', 'campaign_id'=>'uncertain-campaign', 'attempts'=>1,
        'note'=>'Ambiguous historic outcome; never retry automatically']);
    $event = wp_get_scheduled_event(Plugin::HOOK);
    rs_check($event !== false, 'baseline activation schedules real WordPress cron event');
    update_option('rs_test_upgrade_snapshot', [
        'settings'=>get_option('cs_newsletter_settings'), 'credential'=>get_option('rs_newsletter_credential'),
        'installed'=>get_option('cs_newsletter_installed'), 'cron'=>(array)$event,
        'ledger'=>$wpdb->get_results('SELECT * FROM ' . Plugin::table() . ' ORDER BY post_id', ARRAY_A),
        'fingerprint'=>Config::fingerprint(),
    ], false);
    echo "PASS published 0.3.3 state prepared for an actual file upgrade\n";
    return;
}
rs_check($phase === 'test', 'known laboratory phase');
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$header = get_plugin_data(WP_PLUGIN_DIR . '/readyspace-newsletter-for-ultimatesales-ai/cleverspeed-newsletter.php', false, false);
rs_check($header['Version'] === '0.4.0', 'new release bootstrapped after replacing the active directory');
$snapshot = get_option('rs_test_upgrade_snapshot');
rs_check(get_option('cs_newsletter_settings') === $snapshot['settings'], 'file upgrade preserves all existing settings');
rs_check(get_option('rs_newsletter_credential') === $snapshot['credential'], 'file upgrade preserves encrypted credential byte for byte');
rs_check(get_option('cs_newsletter_installed') === $snapshot['installed'], 'file upgrade preserves original archive cutover marker');
rs_check((array)wp_get_scheduled_event(Plugin::HOOK) === $snapshot['cron'], 'file upgrade preserves scheduled event');
rs_check($wpdb->get_results('SELECT * FROM ' . Plugin::table() . ' ORDER BY post_id', ARRAY_A) === $snapshot['ledger'], 'file upgrade preserves complete archive and accepted/uncertain ledger');
rs_check(Credential::read() === str_repeat('fictional-test-token-',4), 'credential decrypts with real WordPress salts after upgrade');
$guard = getenv('RS_TEST_ISOLATION') === 'guard';
if ($guard) {
    rs_check(Config::fingerprint() !== $snapshot['fingerprint'] && !Plugin::verified(), 'new opt-in isolation setting requires connection reverification');
    $settings = Plugin::settings();
    $settings['verified_config'] = Config::fingerprint();
    update_option('cs_newsletter_settings', $settings, false);
} else {
    rs_check(Config::fingerprint() === $snapshot['fingerprint'] && Plugin::verified(), 'ordinary upgrade preserves existing configuration fingerprint and verification');
}
Plugin::activate();
rs_check($wpdb->get_results('SELECT * FROM ' . Plugin::table() . ' ORDER BY post_id', ARRAY_A) === $snapshot['ledger'], 'idempotent schema activation preserves ledger');
rs_check((array)wp_get_scheduled_event(Plugin::HOOK) === $snapshot['cron'], 'idempotent activation does not duplicate or move cron');
foreach (get_option('rs_test_archive_ids') as $id) {
    $before = Plugin::record($id);
    Plugin::process($id);
    rs_check(Plugin::record($id) === $before, 'archive/accepted/uncertain state cannot re-enter the send path');
}

// Assert that the newsletter introduces no CMS-wide mail, index or redirect rules.
rs_check(apply_filters('pre_wp_mail', null, ['to'=>'reader@example.org','subject'=>'Fictional native mail','message'=>'Native fixture']) === true, 'native mail reaches the existing transport fixture');
rs_check(wp_mail('reader@example.org', 'Fictional native mail', 'Native mail fixture'), 'WordPress native mail remains usable without delivering mail');
rs_check($GLOBALS['rs_mail_calls'] === 2, 'newsletter does not intercept native WordPress mail');
rs_check(apply_filters('wp_headers', ['X-Test'=>'unchanged']) === ['X-Test'=>'unchanged'], 'CMS HTTP headers remain unchanged');
rs_check(apply_filters('xmlrpc_enabled', true) === true, 'newsletter does not disable XML-RPC globally');
update_option('blog_public', '1');
rs_check(empty(apply_filters('wp_robots', [])['noindex']), 'public conventional WordPress retains normal index settings');
update_option('blog_public', '0');
rs_check(!empty(apply_filters('wp_robots', [])['noindex']), 'existing CMS noindex setting is preserved');
update_option('blog_public', '1');
foreach (['wp_headers','wp_robots','pre_wp_mail','xmlrpc_enabled','template_redirect','login_redirect'] as $hook) {
    foreach ($GLOBALS['wp_filter'][$hook]->callbacks ?? [] as $callbacks) {
        foreach ($callbacks as $callback) {
            $fn = $callback['function'];
            $owner = is_array($fn) ? (is_object($fn[0]) ? get_class($fn[0]) : (string)$fn[0]) : (is_string($fn) ? $fn : '');
            rs_check(!str_starts_with($owner, 'CleverSpeed\\Newsletter\\'), 'newsletter installs no global CMS hook on ' . $hook);
        }
    }
}

// Real pre_http_request ordering: a host's guard runs first; compatibility runs last.
remove_filter('pre_http_request', 'rs_no_transport', PHP_INT_MAX);
$GLOBALS['rs_test_host_error'] = false;
add_filter('pre_http_request', static function ($pre, array $args, string $url) {
    return $GLOBALS['rs_test_host_error'];
}, 10, 3);
$probePost = get_post(get_option('rs_test_archive_ids')[0]);
$readUrl = Policy::publicUrl(get_permalink($probePost));
$readArgs = ['method'=>'GET','timeout'=>20,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>2000000];
rs_check(!Client::allowsHttp($readUrl, $readArgs), 'no newsletter permission outside active request intent');
$GLOBALS['rs_scope_nested'] = false;
$GLOBALS['rs_scope_expected'] = false;
$probe = static function ($pre, array $args, string $url) use ($readUrl,$probePost) {
    if ($GLOBALS['rs_scope_nested']) {
        rs_check(!Client::allowsHttp($url,$args), 'nested copied request cannot borrow the outer newsletter intent');
        return new WP_Error('rs_test_nested_blocked','Nested fixture request intentionally blocked.');
    }
    rs_check(Client::allowsHttp($url,$args), 'exact active article request is recognized through real WordPress HTTP defaults');
    rs_check($pre === $GLOBALS['rs_scope_expected'], 'compatibility preserves or lifts only the expected preemptive result');
    $wrong = $args; $wrong['headers'] = ['Authorization'=>'Bearer unrelated-secret'];
    rs_check(!Client::allowsHttp($url,$wrong), 'different headers cannot borrow article request intent');
    $wrong = $args; $wrong['method'] = 'POST';
    rs_check(!Client::allowsHttp($url,$wrong), 'different method cannot borrow article request intent');
    $wrong = $args; $wrong['body'] = '{}';
    rs_check(!Client::allowsHttp($url,$wrong), 'different body cannot borrow article request intent');
    $wrong = $args; $wrong['sslverify'] = false;
    rs_check(!Client::allowsHttp($url,$wrong), 'TLS verification cannot be disabled by another filter');
    rs_check(!Client::allowsHttp($url . '?secret=1',$args), 'different URL cannot borrow article request intent');
    $GLOBALS['rs_scope_nested'] = true;
    try {
        $nested = wp_safe_remote_get($url,$args);
        rs_check(is_wp_error($nested) && $nested->get_error_code() === 'rs_test_nested_blocked', 'identical nested WordPress HTTP request remains blocked');
        $nested = wp_safe_remote_get('https://unrelated.example.org/integration-article/',$args);
        rs_check(is_wp_error($nested) && $nested->get_error_code() === 'rs_test_nested_blocked', 'unrelated nested WordPress HTTP request remains blocked');
    } finally { $GLOBALS['rs_scope_nested'] = false; }
    // The synthetic probe result avoids transport even for preserved error test cases.
    return rs_response('<html><head><link rel="canonical" href="' . esc_attr($readUrl) . '"></head><body><h1>' . esc_html($probePost->post_title) . '</h1></body></html>');
};
add_filter('pre_http_request',$probe,PHP_INT_MAX,3);
foreach (['http_request_failed','http_request_not_executed','unrelated_plugin_error'] as $code) {
    $error = new WP_Error($code,'Fictional host restriction');
    $GLOBALS['rs_test_host_error'] = $GLOBALS['rs_scope_expected'] = $error;
    Plugin::article($probePost->ID);
}
$selected = new WP_Error('rs_test_headless_http_blocked','Fictional selected host guard');
$GLOBALS['rs_test_host_error'] = $selected;
$GLOBALS['rs_scope_expected'] = $guard ? false : $selected;
Plugin::article($probePost->ID);
$multi = new WP_Error('rs_test_headless_http_blocked','Fictional selected host guard');
$multi->add('second_error','Independent restriction');
$GLOBALS['rs_test_host_error'] = $GLOBALS['rs_scope_expected'] = $multi;
Plugin::article($probePost->ID);
foreach ([rs_response('Existing response'), true, null] as $pre) {
    $GLOBALS['rs_test_host_error'] = $GLOBALS['rs_scope_expected'] = $pre;
    Plugin::article($probePost->ID);
}
remove_filter('pre_http_request',$probe,PHP_INT_MAX);
rs_check(!Client::allowsHttp($readUrl,$readArgs), 'permission cleared after successful scoped read');
try {
    Client::withHttpIntent('article',$readUrl,$readArgs,static function () { throw new RuntimeException('Fictional callback failure'); });
} catch (RuntimeException $e) {}
rs_check(!Client::allowsHttp($readUrl,$readArgs), 'permission cleared after scoped read throws');
$credential = get_option('rs_newsletter_credential');
delete_option('rs_newsletter_credential');
$error = new WP_Error('rs_test_headless_http_blocked','Fictional selected host guard');
$GLOBALS['rs_test_host_error'] = $error;
rs_throws(static fn()=>Plugin::article($probePost->ID), 'missing credential never relaxes a host restriction');
update_option('rs_newsletter_credential',$credential,false);
$GLOBALS['rs_test_host_error'] = $guard ? new WP_Error('rs_test_headless_http_blocked','Fictional selected host guard') : false;

// Provider and public-page fixtures run after the compatibility callback and before transport.
// WordPress HTTP defaults, parsing and filter ordering are all real.
function rs_mock_http($pre, array $args, string $url) {
    if ($pre !== false) return $pre;
    // A later capture fixture verifies the plugin's own final argument guard.
    if (!empty($GLOBALS['rs_mutation_probe'])) return false;
    if (!Client::allowsHttp($url,$args)) return new WP_Error('rs_test_no_transport','HTTP lacks an exact newsletter intent.');
    $token = Credential::read();
    $valid = HttpPolicy::allowed($url,$args,Config::all(),$token,Plugin::settings());
    unset($token);
    if (!$valid) return new WP_Error('rs_test_no_transport','HTTP is outside the newsletter request policy.');
    $mock = &$GLOBALS['rs_mock'];
    ++$mock['requests'];
    $mock['seen'][] = ['url'=>$url,'args'=>$args];
    $host = wp_parse_url($url,PHP_URL_HOST);
    $path = wp_parse_url($url,PHP_URL_PATH);
    if ($host === wp_parse_url(Config::get('public_origin'),PHP_URL_HOST)) {
        ++$mock['articles'];
        $post = get_post($mock['post']);
        $robots = $mock['fault'] === 'article_noindex' ? '<meta name="robots" content="noindex">' : '';
        return rs_response('<html><head><link rel="canonical" href="' . esc_attr($url) . '">' . $robots . '</head><body><h1>' . esc_html($post->post_title) . '</h1></body></html>');
    }
    if (in_array($host,['storage.googleapis.com','firebasestorage.googleapis.com'],true)) {
        ++$mock['body_reads'];
        $html = $mock['saved_html'];
        if ($mock['fault'] === 'changed_footer') $html = str_replace('Example postal address; replace before use','An entirely different legal address',$html);
        if ($mock['fault'] === 'changed_link') $html = str_replace('https://example.org/privacy/','https://foreign.example.org/privacy/',$html);
        if ($mock['fault'] === 'known_outlook_normalization') {
            $html = str_replace('</head>','<!--outlook-fixes-applied--></head>',$html);
            $html = str_replace('style="color:#ffffff;text-decoration:none;','style="color:#ffffff;mso-style-textfill-fill-color:#ffffff;text-decoration:none;',$html);
        }
        if ($mock['fault'] === 'json_encoded_html') $html = wp_json_encode($html);
        return rs_response($html);
    }
    if ($host !== 'services.leadconnectorhq.com') return new WP_Error('rs_test_no_transport','Unrecognized fixture host.');
    $body = isset($args['body']) && is_string($args['body']) ? json_decode($args['body'],true) : null;
    if ($path === '/contacts/search') {
        ++$mock['searches'];
        $contact = ['id'=>'contact-test','locationId'=>Config::get('location_id'),'email'=>'reader@example.org',
            'tags'=>[Config::get('confirmed_tag')],'dnd'=>false,'dndSettings'=>['Email'=>['status'=>'inactive']]];
        if ($mock['fault'] === 'unsubscribe_before_send' && $mock['searches'] > 1) $contact['tags'][] = Config::get('unsubscribed_tag');
        return rs_response(wp_json_encode(['contacts'=>[$contact]]));
    }
    if ($path === Client::campaignsPath() && $args['method'] === 'POST') {
        ++$mock['creates'];
        $mock['saved_html'] = $body['editorContent'];
        $mock['name'] = $body['name'];
        $mock['campaign'] = 'campaign-test';
        return rs_response(wp_json_encode(['id'=>$mock['campaign'],'status'=>'draft']));
    }
    if ($path === Client::campaignsPath() . '/campaign-test' && $args['method'] === 'GET') {
        return rs_response(wp_json_encode(['id'=>'campaign-test','status'=>'draft','name'=>$mock['name'],
            'editorContentUrl'=>'https://storage.googleapis.com/highlevel-backend.appspot.com/location/test-location-123/emails/campaign-test/index.html']));
    }
    if ($path === Client::campaignsPath() . '/campaign-test/schedule' && $args['method'] === 'POST') {
        ++$mock['sends'];
        $mock['schedule'] = $body;
        if ($mock['fault'] === 'schedule_timeout') return new WP_Error('http_request_failed','Fictional ambiguous transport failure');
        return rs_response(wp_json_encode(['campaignId'=>'campaign-test','sourceId'=>'source-test']));
    }
    throw new RuntimeException('Unexpected HTTP operation reached the provider fixture.');
}
add_filter('pre_http_request','rs_mock_http',PHP_INT_MAX,3);
function rs_reset_mock(string $mode = 'live', string $fault = ''): int {
    $settings = Plugin::settings();
    $settings['mode'] = $mode; $settings['verified'] = true; $settings['verified_config'] = Config::fingerprint();
    update_option('cs_newsletter_settings',$settings,false);
    $id = wp_insert_post(['post_type'=>'post','post_status'=>'publish','post_name'=>'newsletter-fixture-' . wp_generate_uuid4(),
        'post_title'=>'Useful fictional newsletter article','post_content'=>'An article used only in this test laboratory.',
        'post_excerpt'=>'Read this useful guide before you change the way your team connects to work.'],true);
    rs_check(!is_wp_error($id), 'real WordPress first publication creates a fixture post');
    Plugin::set($id,['due_at'=>0]);
    $GLOBALS['rs_mock'] = ['post'=>$id,'fault'=>$fault,'requests'=>0,'articles'=>0,'body_reads'=>0,'searches'=>0,'creates'=>0,'sends'=>0,'seen'=>[]];
    return $id;
}
foreach (['article','draft_body'] as $operation) {
foreach (['method'=>'POST','headers'=>['Authorization'=>'Bearer unrelated-secret'],'body'=>'changed',
    'sslverify'=>false,'redirection'=>5,'blocking'=>false,'stream'=>true,'timeout'=>99,
    'limit_response_size'=>99,'cookies'=>['fictional-cookie'],'filename'=>'/tmp/fictional-newsletter-file',
    'reject_unsafe_urls'=>false] as $field=>$value) {
    $id = rs_reset_mock();
    if ($operation === 'draft_body') {
        $article = Plugin::article($id);
        $name = Config::get('brand') . ' WordPress post ' . $id . ' — ' . $article['title'];
        $draftClient = new Client();
        $created = $draftClient->create($name,Policy::content($article['title'],$article['excerpt'],$article['url']),'author-test');
        $remote = $draftClient->campaign($created['id']);
    }
    $beforeRequests = $GLOBALS['rs_mock']['requests'];
    $GLOBALS['rs_mutation_probe'] = true;
    $tail = static function ($pre, array $args, string $url) use ($guard,$field,$operation) {
        rs_check(is_wp_error($pre) && $pre->get_error_code() === ($guard ? 'rs_test_headless_http_blocked' : 'rs_newsletter_unsafe_transport'), 'real WordPress argument filter cannot change newsletter ' . $operation . ' ' . $field);
        return is_wp_error($pre) ? $pre : new WP_Error('rs_test_no_transport','Capture fixture refuses real transport.');
    };
    $mutator = static function (array $args, string $url) use ($field,$value,$tail) {
        // Registered during argument parsing, after the scoped plugin guard, to capture its result.
        add_filter('pre_http_request',$tail,PHP_INT_MAX,3);
        $args[$field] = $value;
        return $args;
    };
    add_filter('http_request_args',$mutator,10,2);
    try {
        if ($operation === 'article') {
            rs_throws(static fn()=>Plugin::article($id), 'mutated real WordPress transport is held before public verification');
        } else {
            rs_throws(static fn()=>$draftClient->verifyDraft($remote,$name,$article['title'],$article['excerpt'],$article['url']), 'mutated real WordPress transport is held before saved draft verification');
        }
    } finally {
        remove_filter('http_request_args',$mutator,10);
        remove_filter('pre_http_request',$tail,PHP_INT_MAX);
        $GLOBALS['rs_mutation_probe'] = false;
    }
    rs_check($GLOBALS['rs_mock']['requests'] === $beforeRequests, 'argument mutation never reaches provider/public transport fixture');
}
}
$id = rs_reset_mock();
rs_check(Plugin::record($id)['state'] === 'queued', 'real publication hook queues a first publication');
Plugin::process($id);
$mock = $GLOBALS['rs_mock'];
rs_check(Plugin::record($id)['state'] === 'submitted', 'real MySQL worker reaches one fictional accepted campaign');
rs_check($mock['creates'] === 1 && $mock['sends'] === 1 && $mock['body_reads'] === 1, 'exactly one create, complete saved body read, and schedule');
rs_check($mock['articles'] === 2 && $mock['searches'] === 2, 'public article and consent audience are freshly checked before schedule');
rs_check($mock['schedule']['recipients'] === ['type'=>'contact','contactIds'=>['contact-test'],'freezeList'=>true], 'native campaign receives the exact frozen audience');
rs_check($mock['schedule']['scheduleConfig']['resend']['enabled'] === false, 'native campaign resends remain disabled');
rs_check($mock['schedule']['scheduleConfig']['tracking'] === ['clickTracking'=>false,'utmTracking'=>false], 'native campaign does not rewrite newsletter links');
rs_check(str_contains($mock['saved_html'], 'href="' . Config::get('public_origin') . '/newsletter-fixture-'), 'newsletter uses configured public origin in both site layouts');
Plugin::process($id);
rs_check($GLOBALS['rs_mock']['requests'] === $mock['requests'], 'accepted real ledger job cannot schedule again');
wp_update_post(['ID'=>$id,'post_title'=>'Edited after acceptance']);
wp_update_post(['ID'=>$id,'post_status'=>'draft']);
wp_update_post(['ID'=>$id,'post_status'=>'publish']);
rs_check(Plugin::record($id)['state'] === 'submitted', 'edits and republishing preserve accepted ledger exclusion');
foreach (['known_outlook_normalization','json_encoded_html'] as $fault) {
    $id = rs_reset_mock('live',$fault);
    Plugin::process($id);
    rs_check(Plugin::record($id)['state'] === 'submitted' && $GLOBALS['rs_mock']['sends'] === 1, $fault . ' preserves the entire expected newsletter through real HTTP');
}
foreach (['changed_footer','changed_link','unsubscribe_before_send','article_noindex','schedule_timeout'] as $fault) {
    $id = rs_reset_mock('live',$fault);
    Plugin::process($id);
    $mock = $GLOBALS['rs_mock'];
    $expectedState = $fault === 'schedule_timeout' ? 'uncertain' : 'held';
    rs_check(Plugin::record($id)['state'] === $expectedState, $fault . ' fails closed in real ledger');
    rs_check($mock['sends'] === ($fault === 'schedule_timeout' ? 1 : 0), $fault . ' cannot produce an unsafe repeated schedule');
    Plugin::process($id);
    rs_check($GLOBALS['rs_mock']['requests'] === $mock['requests'], $fault . ' retained/ambiguous job is not retried');
}
$id = rs_reset_mock('draft');
Plugin::process($id);
rs_check(Plugin::record($id)['state'] === 'draft_ready' && $GLOBALS['rs_mock']['sends'] === 0, 'draft mode verifies full saved HTML without a send');
$id = rs_reset_mock('off');
Plugin::process($id);
rs_check(Plugin::record($id)['state'] === 'excluded' && $GLOBALS['rs_mock']['requests'] === 0, 'off-mode publication is excluded without HTTP');
rs_reset_mock();
$client = new Client();
rs_throws(static fn()=>$client->request('POST',Client::campaignsPath() . '/campaign-test/schedule',[]), 'direct schedule cannot bypass verified draft and fresh audience');
rs_throws(static fn()=>$client->send('campaign-test','Useful fictional newsletter article',['unrelated-contact'],'author-test'), 'send rejects contacts without a fresh audience and verified draft');
rs_throws(static fn()=>$client->request('GET','/contacts/unrelated-contact'), 'individual contact endpoint is outside newsletter API policy');
rs_throws(static fn()=>$client->request('GET','/emails/locations/other-location/campaigns/emails/campaign-test'), 'another location is outside newsletter API policy');
rs_check($GLOBALS['rs_mock']['requests'] === 0, 'invalid direct API calls never reach a provider fixture');
$article = Plugin::article($GLOBALS['rs_mock']['post']);
$name = Config::get('brand') . ' WordPress post ' . $GLOBALS['rs_mock']['post'] . ' — ' . $article['title'];
$html = Policy::content($article['title'],$article['excerpt'],$article['url']);
$client->recipients();
$created = $client->create($name,$html,'author-test');
$remote = $client->campaign($created['id']);
$client->verifyDraft($remote,$name,$article['title'],$article['excerpt'],$article['url']);
$fresh = $client->recipients();
rs_throws(static fn()=>$client->send($created['id'],$article['title'],['unrelated-contact'],'author-test'), 'verified direct client still rejects a different audience');
rs_check($GLOBALS['rs_mock']['sends'] === 0, 'different audience cannot reach schedule transport');
$client->send($created['id'],$article['title'],$fresh,'author-test');
rs_check($GLOBALS['rs_mock']['sends'] === 1, 'verified direct client can schedule its exact freshly confirmed audience once');
$requests = $GLOBALS['rs_mock']['requests'];
rs_throws(static fn()=>$client->send($created['id'],$article['title'],$fresh,'author-test'), 'same verified client cannot replay a schedule');
rs_throws(static fn()=>$client->request('POST',Client::campaignsPath() . '/campaign-test/schedule',$GLOBALS['rs_mock']['schedule']), 'direct request cannot replay a previously valid schedule payload');
rs_check($GLOBALS['rs_mock']['requests'] === $requests, 'schedule replay attempts never reach transport');
$globalResponse = wp_remote_get('https://unrelated.example.org/arbitrary-path/');
rs_check(is_wp_error($globalResponse) && $globalResponse->get_error_code() === ($guard ? 'rs_test_headless_http_blocked' : 'rs_test_no_transport'), 'unrelated WordPress HTTP retains the host policy');
rs_check($GLOBALS['rs_mock']['requests'] === $requests, 'unrelated global HTTP never acquires newsletter transport permission');
Plugin::deactivate();
rs_check(wp_next_scheduled(Plugin::HOOK) === false, 'real deactivation clears the newsletter cron event');
Plugin::activate();
rs_check(wp_next_scheduled(Plugin::HOOK) !== false, 'real reactivation schedules queue without replaying archive');
rs_check(get_option('cs_newsletter_installed') === $snapshot['installed'], 'reactivation keeps original installation cutover');
foreach (get_option('rs_test_archive_ids') as $id) rs_check(Plugin::record($id) === array_values(array_filter($snapshot['ledger'],static fn($row)=>(int)$row['post_id'] === (int)$id))[0], 'reactivation preserves all historic archive states');
echo 'PASS ' . $GLOBALS['rs_checks'] . ' real WordPress checks / ' . get_bloginfo('version') . ' / PHP ' . PHP_VERSION . ' / ' . getenv('RS_TEST_LAYOUT') . ' / ' . getenv('RS_TEST_ISOLATION') . " (all newsletter HTTP intercepted; no emails)\n";
