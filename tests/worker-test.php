<?php
require_once __DIR__ . '/fixture.php';
// Standalone mocked WordPress runtime. No database, HTTP, email, or production option mutations.
 define('DB_NAME','offline-test'); define('ARRAY_A','ARRAY_A');
define('AUTH_KEY',str_repeat('test-only-auth-key-',4)); define('SECURE_AUTH_KEY',str_repeat('test-only-secure-key-',4));
function register_activation_hook(...$a) {} function register_deactivation_hook(...$a) {}
function add_filter(...$a) {} function add_action(...$a) {}
function wp_json_encode($a) { return json_encode($a); }
function get_option($name,$default=[]) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name,$value,...$a) { $GLOBALS['options'][$name]=$value; return true; }
function get_post($id) { return $GLOBALS['post']; }
function get_post_meta(...$a) { return $GLOBALS['skip'] ?? ''; }
function get_permalink(...$a) { return $GLOBALS['fault']==='slashful_redirect' ? 'https://cms.example.org/test' : 'https://cms.example.org/test/'; }
function is_wp_error($x) { return $x instanceof Exception; }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
function wp_remote_retrieve_header($r,$h) { return $r['headers'][$h] ?? ''; }
function wp_safe_remote_get($url,$args=[]) {
    if (str_starts_with($url,'https://storage.googleapis.com/') || str_starts_with($url,'https://firebasestorage.googleapis.com/')) return ['code'=>200,'body'=>$GLOBALS['saved_html']];
    $GLOBALS['public_requests'][]=[$url,$args];
    $fault=$GLOBALS['fault'];$canonical='https://example.org/test/';
    if ($fault==='slashless_canonical') $canonical='https://example.org/test';
    if (in_array($fault,['slashless_redirect','second_redirect','redirect_final_noindex','redirect_final_header_noindex'],true)) {
        if ($url==='https://example.org/test/') return ['code'=>301,'body'=>'','headers'=>['location'=>'https://example.org/test']];
        if ($fault==='second_redirect') return ['code'=>308,'body'=>'','headers'=>['location'=>'https://example.org/test/']];
        $canonical='https://example.org/test';
    }
    if ($fault==='slashful_redirect' && $url==='https://example.org/test') return ['code'=>308,'body'=>'','headers'=>['location'=>'https://example.org/test/']];
    $redirects=[
        'redirect_other_path'=>'https://example.org/other/',
        'redirect_other_origin'=>'https://foreign.example/test',
        'redirect_cms_origin'=>'https://cms.example.org/test',
        'redirect_query'=>'https://example.org/test?q=1',
        'redirect_fragment'=>'https://example.org/test#section',
        'redirect_auth'=>'https://x@example.org/test',
        'redirect_port'=>'https://example.org:443/test',
        'redirect_relative'=>'//example.org/test',
        'redirect_http'=>'http://example.org/test',
        'redirect_root'=>'https://example.org/',
        'redirect_double_slash'=>'https://example.org/test//',
        'redirect_same_url'=>'https://example.org/test/',
    ];
    if (isset($redirects[$fault])) return ['code'=>301,'body'=>'','headers'=>['location'=>$redirects[$fault]]];
    if ($fault==='temporary_redirect') return ['code'=>302,'body'=>'','headers'=>['location'=>'https://example.org/test']];
    $html='<html><head><link rel="canonical" href="'.$canonical.'"></head><body><h1>Useful test article</h1></body></html>';
    if ($fault==='redirect_final_noindex') $html=str_replace('</head>','<meta name="robots" content="noindex"></head>',$html);
    return ['code'=>200,'body'=>$html,'headers'=>$fault==='redirect_final_header_noindex' ? ['x-robots-tag'=>'noindex'] : []];
}
function wp_remote_request($url,$args) {
    $GLOBALS['requests']++;
    $path=parse_url($url,PHP_URL_PATH);$method=$args['method'];
    $contact=['id'=>'contact-test','locationId'=>'test-location-123','email'=>'reader@example.org','tags'=>['newsletter-confirmed'],'dnd'=>false,'dndSettings'=>['Email'=>['status'=>'inactive']]];
    if ($GLOBALS['fault']==='no_recipients') $contact['dnd']=true;
    if ($path==='/contacts/search') {
        $GLOBALS['searches']++;
        if ($GLOBALS['fault']==='missing_dnd') unset($contact['dnd']);
        if ($GLOBALS['fault']==='email_suppressed') $contact['dndSettings']=['Email'=>['status'=>'active']];
        if ($GLOBALS['fault']==='unsubscribe_before_send' && $GLOBALS['searches']>1) $contact['dnd']=true;
        if ($GLOBALS['fault']==='malformed_email_dnd') $contact['dndSettings']=['Email'=>'active'];
        if ($GLOBALS['fault']==='lowercase_suppressed') $contact['dndSettings']=['email'=>['status'=>'active']];
        if ($GLOBALS['fault']==='email_disabled') $contact['dndSettings']=['EMAIL'=>['status'=>'disabled']];
        if ($GLOBALS['fault']==='conflicting_email_states') $contact['dndSettings']=['Email'=>['status'=>'inactive'],'email'=>['status'=>'active']];
        if ($GLOBALS['fault']==='numeric_email_channels') $contact['dndSettings']=[['status'=>'inactive']];
        if ($GLOBALS['fault']==='absent_email_channel' || $GLOBALS['fault']==='missing_dnd_without_email') unset($contact['dndSettings']);
        if ($GLOBALS['fault']==='missing_dnd_without_email') unset($contact['dnd']);
        if ($GLOBALS['fault']==='lowercase_allowed') $contact['dndSettings']=['email'=>['status'=>'INACTIVE']];
        if ($GLOBALS['fault']==='sms_suppressed') $contact['dndSettings']=['SMS'=>['status'=>'active']];
        if ($GLOBALS['fault']==='email_suppressed_before_send' && $GLOBALS['searches']>1) $contact['dndSettings']=['email'=>['status'=>'active']];
        $data=['contacts'=>[$contact]];
        if ($GLOBALS['fault']==='malformed_contact') $data['contacts']=['invalid'];
        if ($GLOBALS['fault']==='malformed_contact_id') $data['contacts'][0]['id']=['invalid'];
        if ($GLOBALS['fault']==='malformed_contact_list') $data['contacts']=['unexpected'=>$contact];
    }
    elseif ($path==='/contacts/contact-test') throw new RuntimeException('Individual endpoint must not replace explicit search consent');
    elseif (str_ends_with($path,'/schedule')) {
        $GLOBALS['sends']++;
        $GLOBALS['send_body']=json_decode($args['body'],true);
        if ($GLOBALS['fault']==='send_timeout') return new Exception('timeout');
        $data=['campaignId'=>'campaign-test','sourceId'=>'source-test'];
    } elseif ($method==='POST') {
        $GLOBALS['creates']++;
        $body=json_decode($args['body'],true);
        $GLOBALS['saved_html']=$body['editorContent'];$GLOBALS['campaign_name']=$body['name'];
        if ($GLOBALS['fault']==='legacy_unsubscribe') $GLOBALS['saved_html']=str_replace('{{email.unsubscribe_link}}','{{unsubscribe}}',$GLOBALS['saved_html']);
        if ($GLOBALS['fault']==='missing_unsubscribe') $GLOBALS['saved_html']=str_replace('{{email.unsubscribe_link}}','',$GLOBALS['saved_html']);
        if ($GLOBALS['fault']==='unsubscribe_outside_anchor') {
            $GLOBALS['saved_html']=str_replace('href="{{email.unsubscribe_link}}"','href="#unsubscribe"',$GLOBALS['saved_html']);
            $GLOBALS['saved_html']=str_replace('</body>','<p>{{email.unsubscribe_link}}</p></body>',$GLOBALS['saved_html']);
        }
        if ($GLOBALS['fault']==='duplicate_unsubscribe') $GLOBALS['saved_html']=str_replace('</body>','<a href="{{email.unsubscribe_link}}">Duplicate</a></body>',$GLOBALS['saved_html']);
        if ($GLOBALS['fault']==='extra_legacy_unsubscribe') $GLOBALS['saved_html']=str_replace('</body>','<a href="{{unsubscribe}}">Legacy</a></body>',$GLOBALS['saved_html']);
        if ($GLOBALS['fault']==='create_timeout') return new Exception('timeout');
        $data=['id'=>'campaign-test','status'=>'draft'];
    } else {
        $data=['id'=>'campaign-test','name'=>$GLOBALS['campaign_name'],'status'=>'draft','editorContentUrl'=>'https://storage.googleapis.com/example.html'];
        if ($GLOBALS['fault']==='unexpected_remote_sent') $data['status']='sent';
        if ($GLOBALS['fault']==='firebase_content') $data['editorContentUrl']='https://firebasestorage.googleapis.com/v0/b/highlevel-backend.appspot.com/o/location%2Ftest-location-123%2Femails%2Ftest%2Findex.html?alt=media&token=dummy';
        if ($GLOBALS['fault']==='wrong_firebase_location') $data['editorContentUrl']='https://firebasestorage.googleapis.com/v0/b/highlevel-backend.appspot.com/o/location%2Fother%2Femails%2Ftest%2Findex.html';
        if ($GLOBALS['fault']==='untrusted_content_host') $data['editorContentUrl']='https://example.org/email.html';
    }
    return ['code'=>200,'body'=>json_encode($data)];
}
#[AllowDynamicProperties] class WP_Post {}
class FakeDB {
    public $prefix='test_'; public $jobs=[]; public $lock=true; public $write_result=null;
    function prepare($sql,...$args) { return json_encode([$sql,$args]); }
    function get_var($sql) { return $this->lock ? 1 : 0; }
    function get_row($q,$mode) { [$sql,$a]=json_decode($q,true);return $this->jobs[$a[1]] ?? null; }
    function update($table,$data,$where) { if ($this->write_result !== null) return $this->write_result; $id=$where['post_id']; if (!isset($this->jobs[$id])) return 0; $this->jobs[$id]=array_merge($this->jobs[$id],$data);return 1; }
    function query($q) {
        [$sql,$a]=json_decode($q,true);
        if (str_starts_with($sql,'INSERT IGNORE') && !isset($this->jobs[$a[1]])) {
            $this->jobs[$a[1]]=['post_id'=>$a[1],'state'=>$a[2],'due_at'=>$a[3],'note'=>$a[4],'campaign_id'=>'','attempts'=>0];
        }
        return 1;
    }
}
$wpdb=new FakeDB();
require $argv[1].'/cleverspeed-newsletter.php';
use CleverSpeed\Newsletter\Plugin;
$tests=0;
function check($condition,$name) { global $tests;++$tests;if (!$condition) throw new RuntimeException('FAIL '.$name); }
function reset_case($mode='live',$fault='') {
    global $wpdb;
    $GLOBALS['options']=['cs_newsletter_settings'=>['mode'=>$mode,'user_id'=>'author-test','verified'=>true,'verified_config'=>\CleverSpeed\Newsletter\Config::fingerprint()]];
    \CleverSpeed\Newsletter\Credential::save(str_repeat('dummy-test-token-',4));
    $GLOBALS['fault']=$fault;$GLOBALS['skip']='';$GLOBALS['creates']=0;$GLOBALS['sends']=0;$GLOBALS['requests']=0;$GLOBALS['searches']=0;$GLOBALS['public_requests']=[];
    $GLOBALS['post']=new WP_Post();
    foreach (['ID'=>90000001,'post_type'=>'post','post_status'=>'publish','post_password'=>'','post_title'=>'Useful test article',
        'post_excerpt'=>'Read this useful guide before you change the way your team connects to work.'] as $k=>$v) $GLOBALS['post']->$k=$v;
    $wpdb->lock=true;$wpdb->write_result=null;$wpdb->jobs=[90000001=>['post_id'=>90000001,'state'=>'queued','due_at'=>0,'campaign_id'=>'','attempts'=>0]];
}
reset_case(); Plugin::process(90000001);
check($wpdb->jobs[90000001]['state']==='submitted','successful mock send');
check($GLOBALS['creates']===1 && $GLOBALS['sends']===1,'one campaign and one send');
check($GLOBALS['send_body']['recipients']['contactIds']===['contact-test'],'exact eligible IDs');
check($GLOBALS['send_body']['scheduleConfig']['resend']['enabled']===false,'resend disabled');
check($GLOBALS['searches']===2,'fresh explicit search consent checked before create and before send');
check(str_contains($GLOBALS['saved_html'],'href="https://example.org/test/"'),'conventional WordPress final-slash canonical preserved');
Plugin::process(90000001);check($GLOBALS['sends']===1,'completed job cannot resend');
foreach (['slashless_canonical','slashless_redirect','slashful_redirect'] as $fault) {
    reset_case('live',$fault);Plugin::process(90000001);
    $campaignUrl=$fault==='slashful_redirect' ? 'https://example.org/test/' : 'https://example.org/test';
    check($GLOBALS['creates']===1 && $GLOBALS['sends']===1 && str_contains($GLOBALS['saved_html'],'href="'.$campaignUrl.'"'),$fault.' validated canonical used by campaign');
    check(count($GLOBALS['public_requests'])===($fault==='slashless_canonical' ? 2 : 4),$fault.' bounded public checks repeated before schedule');
    foreach ($GLOBALS['public_requests'] as [$publicUrl,$args]) check($args['redirection']===0 && $args['limit_response_size']===2000000,'manual final-slash route retains response bounds and disables automatic redirects');
}
foreach (['redirect_other_path','redirect_other_origin','redirect_cms_origin','redirect_query','redirect_fragment','redirect_auth','redirect_port','redirect_relative','redirect_http','redirect_root','redirect_double_slash','redirect_same_url','temporary_redirect','second_redirect','redirect_final_noindex','redirect_final_header_noindex'] as $fault) {
    reset_case('live',$fault);Plugin::process(90000001);
    check(!$GLOBALS['creates'] && !$GLOBALS['sends'] && !$GLOBALS['requests'] && $wpdb->jobs[90000001]['state']==='held',$fault.' held before provider API');
    check(count($GLOBALS['public_requests'])===(in_array($fault,['second_redirect','redirect_final_noindex','redirect_final_header_noindex'],true) ? 2 : 1),$fault.' public route requests bounded');
}
reset_case('off');Plugin::process(90000001);check($GLOBALS['requests']===0,'off means no API');
reset_case();$GLOBALS['options']['cs_newsletter_settings']['verified_config']='stale';Plugin::process(90000001);check($GLOBALS['requests']===0,'configuration changes invalidate verification');
reset_case('draft');Plugin::process(90000001);check($wpdb->jobs[90000001]['state']==='draft_ready' && $GLOBALS['sends']===0,'draft-only never sends');
$GLOBALS['options']['cs_newsletter_settings']['mode']='live';Plugin::process(90000001);check($GLOBALS['sends']===0,'mode change does not send old draft');
reset_case();$wpdb->lock=false;Plugin::process(90000001);check($GLOBALS['requests']===0,'concurrent worker lock');
reset_case();$GLOBALS['post']->post_excerpt='';Plugin::process(90000001);check($wpdb->jobs[90000001]['state']==='held' && $GLOBALS['requests']===0,'missing excerpt held before API');
reset_case();$GLOBALS['post']->post_status='draft';Plugin::process(90000001);check($GLOBALS['requests']===0,'unpublished article held');
reset_case();$GLOBALS['skip']='1';Plugin::process(90000001);check($GLOBALS['requests']===0,'opt-out held');
reset_case('live','no_recipients');Plugin::process(90000001);check($wpdb->jobs[90000001]['state']==='skipped' && !$GLOBALS['creates'],'no eligible subscribers no campaign');
foreach (['missing_dnd','email_suppressed','lowercase_suppressed','email_disabled','conflicting_email_states','numeric_email_channels','missing_dnd_without_email','malformed_email_dnd','malformed_contact','malformed_contact_id','malformed_contact_list'] as $fault) {
    reset_case('live',$fault);Plugin::process(90000001);check(!$GLOBALS['creates'] && !$GLOBALS['sends'],$fault.' fails closed');
}
foreach (['lowercase_allowed','absent_email_channel','sms_suppressed'] as $fault) {
    reset_case('live',$fault);Plugin::process(90000001);check($GLOBALS['creates']===1 && $GLOBALS['sends']===1,$fault.' remains subject to native final suppression');
}
foreach ([0,false] as $write_result) {
    reset_case();$wpdb->write_result=$write_result;
    try { Plugin::process(90000001); } catch (RuntimeException $e) {}
    check(!$GLOBALS['creates'] && !$GLOBALS['sends'],'unwritten ledger state blocks mutating API requests');
}
reset_case('live','unsubscribe_before_send');Plugin::process(90000001);check($GLOBALS['creates']===1 && !$GLOBALS['sends'],'fresh opt-out prevents send');
reset_case('live','email_suppressed_before_send');Plugin::process(90000001);check($GLOBALS['creates']===1 && !$GLOBALS['sends'],'fresh lowercase Email suppression prevents send');
reset_case('live','firebase_content');Plugin::process(90000001);check($GLOBALS['sends']===1,'verified location-specific Firebase content supported');
foreach (['wrong_firebase_location','untrusted_content_host'] as $fault) {
    reset_case('live',$fault);Plugin::process(90000001);check(!$GLOBALS['sends'],$fault.' is rejected');
}
foreach (['create_timeout','send_timeout'] as $fault) {
    reset_case('live',$fault);Plugin::process(90000001);check($wpdb->jobs[90000001]['state']==='uncertain',$fault.' held');
    $before=$GLOBALS['requests'];Plugin::process(90000001);check($before===$GLOBALS['requests'],$fault.' not retried');
}
reset_case('live','unexpected_remote_sent');Plugin::process(90000001);check($GLOBALS['sends']===0,'remote sent status never rescheduled');
foreach (['legacy_unsubscribe','missing_unsubscribe','unsubscribe_outside_anchor','duplicate_unsubscribe','extra_legacy_unsubscribe'] as $fault) {
    reset_case('live',$fault);Plugin::process(90000001);
    check($GLOBALS['creates']===1 && $GLOBALS['sends']===0 && $wpdb->jobs[90000001]['state']==='held',$fault.' draft held before schedule');
    $before=$GLOBALS['requests'];Plugin::process(90000001);
    check($before===$GLOBALS['requests'],$fault.' retained draft is not retried');
}
reset_case();$wpdb->jobs=[];Plugin::transition('publish','draft',$GLOBALS['post']);
check($wpdb->jobs[90000001]['state']==='queued' && $wpdb->jobs[90000001]['due_at']>=time()+299,'first publish delay');
$wpdb->jobs[90000001]['state']='submitted';Plugin::transition('publish','draft',$GLOBALS['post']);
check($wpdb->jobs[90000001]['state']==='submitted','republish preserves ledger');
$wpdb->jobs=[];Plugin::transition('publish','publish',$GLOBALS['post']);check(!$wpdb->jobs,'updates excluded');
reset_case('off');$wpdb->jobs=[];Plugin::transition('publish','draft',$GLOBALS['post']);check($wpdb->jobs[90000001]['state']==='excluded','off publications excluded');
echo "PASS $tests worker checks (all HTTP mocked; no sends)\n";
