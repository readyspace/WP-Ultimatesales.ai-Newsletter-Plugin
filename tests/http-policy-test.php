<?php
// Standalone pure fixtures: no WordPress bootstrap, database, HTTP or real secrets.
if (!defined('ABSPATH')) define('ABSPATH', '/unused/');
require_once __DIR__ . '/wp-functions.php';
require ($argv[1] ?? dirname(__DIR__) . '/cleverspeed-newsletter') . '/includes/http-policy.php';
use CleverSpeed\Newsletter\HttpPolicy;
$checks = 0;
function check_http(bool $condition, string $label): void {
    ++$GLOBALS['checks'];
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
}
function fixture_config(string $location, string $origin, string $cms, string $tag, string $brand): array {
    return ['location_id'=>$location,'confirmed_tag'=>$tag,'pending_tag'=>$tag.'-pending','unsubscribed_tag'=>$tag.'-unsubscribed',
        'cms_origin'=>$cms,'public_origin'=>$origin,'brand'=>$brand,'from_email'=>'sender@example.org','reply_email'=>'reply@example.org',
        'timezone'=>'UTC','legal_name'=>'Fictional Publisher','postal_address'=>'Fictional address','privacy_url'=>$origin.'/privacy/',
        'preview_text'=>'Fictional guide notifications.'];
}
function read_args(int $size = 2000000): array {
    return ['method'=>'GET','timeout'=>20,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>$size];
}
function api_args(string $token, string $method, ?array $body = null, string $version = 'v3'): array {
    $args = ['method'=>$method,'timeout'=>30,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>2000000,
        'headers'=>['Authorization'=>'Bearer '.$token,'Version'=>$version,'Accept'=>'application/json','Content-Type'=>'application/json']];
    if ($body !== null) $args['body'] = json_encode($body);
    return $args;
}
function create_body(array $config): array {
    return ['name'=>$config['brand'].' WordPress post 42 — A useful guide','editorType'=>'html',
        'editorContent'=>'<html><body><a href="'.$config['public_origin'].'/guide/">Read</a><a href="'.$config['privacy_url'].'">Privacy</a><a href="{{email.unsubscribe_link}}">Unsubscribe</a></body></html>',
        'timeZone'=>$config['timezone'],'userId'=>'fixture-user'];
}
function schedule_body(array $config, array $ids = ['fictional-one','fictional-two']): array {
    return ['scheduleType'=>'immediate','timeZone'=>$config['timezone'],'userId'=>'fixture-user',
        'emailMeta'=>['subject'=>$config['brand'].': A useful guide','fromName'=>$config['brand'],'fromEmail'=>$config['from_email'],
            'replyToAddress'=>$config['reply_email'],'previewText'=>$config['preview_text']],
        'recipients'=>['type'=>'contact','contactIds'=>$ids,'freezeList'=>true],
        'scheduleConfig'=>['tracking'=>['clickTracking'=>false,'utmTracking'=>false],'resend'=>['enabled'=>false]]];
}
$token = str_repeat('fictional-token-', 3);
$api = 'https://services.leadconnectorhq.com';
$live = ['mode'=>'live','verified'=>true,'user_id'=>'fixture-user'];
$off = ['mode'=>'off','verified'=>false,'user_id'=>''];
$configs = [
    fixture_config('location-first','https://publication.example.org','https://cms.example.org','first-confirmed','First Publication'),
    array_replace(fixture_config('location-second','https://native.example.net','https://native.example.net','second-opt-in','Second Publication'),
        ['from_email'=>'sender@native.example.net','reply_email'=>'editor@native.example.net','timezone'=>'Asia/Tokyo','preview_text'=>'Second publication updates.']),
];
foreach ($configs as $config) {
    $location = $config['location_id']; $origin = $config['public_origin'];
    $root = $api.'/emails/locations/'.$location.'/campaigns/emails';
    $article = $origin.'/guide/';
    $storage = 'https://storage.googleapis.com/highlevel-backend.appspot.com/location/'.$location.'/emails/draft/index.html';
    $firebase = 'https://firebasestorage.googleapis.com/v0/b/highlevel-backend.appspot.com/o/location%2F'.$location.'%2Femails%2Fdraft%2Findex.html?alt=media&token=fictional';
    foreach ([$article,rtrim($article,'/'),$origin.'/guides/topic/',$origin.'/caf%C3%A9/'] as $url) {
        check_http(HttpPolicy::allowed($url,read_args(),$config,'',$off),'portable unauthenticated public article');
    }
    foreach ([$storage,$firebase] as $url) check_http(HttpPolicy::allowed($url,read_args(500000),$config,'',$off),'portable location-bound stored body');
    $read = read_args(); $read += ['blocking'=>true,'stream'=>false,'cookies'=>[],'filename'=>null,'headers'=>[],'body'=>null];
    check_http(HttpPolicy::allowed($article,$read,$config,'',$off),'ordinary WordPress HTTP defaults supported');
    check_http(HttpPolicy::allowed($api.'/users/?locationId='.$location,api_args($token,'GET',null,'2021-07-28'),$config,$token,$off),'staff read allowed during Off acceptance');
    $contacts = ['locationId'=>$location,'page'=>1,'pageLimit'=>100,'filters'=>[['field'=>'tags','operator'=>'contains','value'=>$config['confirmed_tag']]]];
    foreach ([1,100] as $page) {
        $contacts['page']=$page;
        check_http(HttpPolicy::allowed($api.'/contacts/search',api_args($token,'POST',$contacts,'2021-07-28'),$config,$token,$off),'bounded confirmed-tag search');
    }
    check_http(HttpPolicy::allowed($root.'/campaign-1',api_args($token,'GET'),$config,$token,$off),'configured campaign read');
    $create=create_body($config); $schedule=schedule_body($config);
    foreach (['draft','live'] as $mode) check_http(HttpPolicy::allowed($root,api_args($token,'POST',$create),$config,$token,array_replace($live,['mode'=>$mode])),'verified creation mode');
    check_http(HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',$schedule),$config,$token,$live),'verified frozen immediate schedule');

    foreach (['http://'.$location.'.example/guide/',$origin,$origin.'/', $origin.'/wp-admin/',$origin.'/WP-JSON/posts',
        $origin.'/api/submit',$origin.'/cms/article',$origin.'/login',$origin.'/wp-login.php',$origin.'/guide/?a=1',$origin.'/guide/#part',
        $origin.'/guide//part',$origin.'/guide/../other',$origin.'/guide/%2e%2e/other',$origin.'/guide/%252e%252e/other',
        $origin.'/guide/%2fother',$origin.'/guide/%252fother',$origin.'/guide/%00',$origin.'/guide/%0a',$origin.'/guide/%5cother',
        $origin.'/guide/%255cother',$origin.'/guide/%ZZ','https://evil.example/guide/','https://user@'.parse_url($origin,PHP_URL_HOST).'/guide/',
        $origin.':443/guide/','//'.parse_url($origin,PHP_URL_HOST).'/guide/'] as $url) {
        check_http(!HttpPolicy::allowed($url,read_args(),$config,'',$off),'unsafe or nonarticle URL denied');
    }
    check_http(HttpPolicy::articleUrlAllowed($origin.'/cms-news/', $origin),'nonreserved article slug remains valid');
    if ($config['cms_origin'] !== $origin) check_http(!HttpPolicy::allowed($config['cms_origin'].'/guide/',read_args(),$config,'',$off),'headless CMS origin is not public article origin');
    foreach ([['sslverify',false],['sslverify','true'],['redirection',1],['redirection','0'],['blocking',false],['stream',true],
        ['cookies',['cookie'=>'value']],['cookies',null],['filename','/tmp/output'],['timeout',21],['limit_response_size',0],
        ['headers',['Authorization'=>'Bearer '.$token]],['body','unexpected'],['method','POST']] as [$key,$value]) {
        check_http(!HttpPolicy::allowed($article,array_replace(read_args(),[$key=>$value]),$config,'',$off),'changed public read transport denied: '.$key);
    }
    foreach (['https://storage.googleapis.com/other.appspot.com/location/'.$location.'/emails/draft.html',
        str_replace($location,'other-location',$storage), str_replace('/emails/draft/index.html','/emails/',$storage),
        str_replace('/emails/draft/index.html','/emails/../private.html',$storage),
        str_replace('/emails/draft/index.html','/emails/%252e%252e/private.html',$storage),
        str_replace('highlevel-backend.appspot.com','wrong-bucket.appspot.com',$firebase),
        str_replace($location,'other-location',$firebase), $storage.'?token='.str_repeat('a',2049),
        'https://storage.googleapis.com.evil.example/highlevel-backend.appspot.com/location/'.$location.'/emails/draft.html'] as $url) {
        check_http(!HttpPolicy::allowed($url,read_args(500000),$config,'',$off),'unsafe or foreign stored body denied');
    }
    check_http(!HttpPolicy::allowed($storage,read_args(),$config,'',$off),'stored body cannot use article response bound');
    check_http(!HttpPolicy::allowed($article,read_args(500000),$config,'',$off),'article cannot use draft response bound');
    $args=api_args($token,'GET');
    foreach (['timeout'=>20,'limit_response_size'=>500000,'sslverify'=>false,'redirection'=>1,'blocking'=>false,
        'stream'=>true,'cookies'=>['x'=>'y'],'filename'=>'output','body'=>[]] as $key=>$value) {
        check_http(!HttpPolicy::allowed($root.'/campaign-1',array_replace($args,[$key=>$value]),$config,$token,$off),'changed API transport denied: '.$key);
    }
    foreach (['Authorization'=>'Bearer wrong','Version'=>'bad','Accept'=>'text/html','Content-Type'=>'text/plain','X-Unexpected'=>'x'] as $key=>$value) {
        $changed=$args;$changed['headers'][$key]=$value;
        check_http(!HttpPolicy::allowed($root.'/campaign-1',$changed,$config,$token,$off),'changed API headers denied');
    }
    $lower=$args;$lower['headers']=array_change_key_case($lower['headers'],CASE_LOWER);
    check_http(HttpPolicy::allowed($root.'/campaign-1',$lower,$config,$token,$off),'case-normalized unique API headers');
    $duplicate=$args;$duplicate['headers']['authorization']='Bearer '.$token;
    check_http(!HttpPolicy::allowed($root.'/campaign-1',$duplicate,$config,$token,$off),'duplicate differently-cased header denied');
    foreach ([$api.'/users/?locationId=other-location',$api.'/users/?locationId='.$location.'&extra=1',
        $api.'/users/?locationId='.$location.'&locationId='.$location,$api.'/users?locationId='.$location,
        $root.'/campaign-1?extra=1',$api.'/emails/locations/other-location/campaigns/emails/campaign-1',$root.'/bad%2Fid'] as $url) {
        check_http(!HttpPolicy::allowed($url,api_args($token,'GET',null,str_contains($url,'/users')?'2021-07-28':'v3'),$config,$token,$off),'unscoped API read denied');
    }
    $contacts['page']=1;
    foreach ([['locationId'=>'other-location'],['page'=>0],['page'=>101],['page'=>'1'],['pageLimit'=>101],
        ['filters'=>[]],['filters'=>[['field'=>'tags','operator'=>'contains','value'=>'wrong-confirmed']]],['extra'=>true]] as $change) {
        check_http(!HttpPolicy::allowed($api.'/contacts/search',api_args($token,'POST',array_replace($contacts,$change),'2021-07-28'),$config,$token,$off),'changed contact-search scope denied');
    }
    foreach ([$off,array_replace($live,['verified'=>false]),array_replace($live,['verified'=>'true']),array_replace($live,['user_id'=>'other-user'])] as $settings) {
        check_http(!HttpPolicy::allowed($root,api_args($token,'POST',$create),$config,$token,$settings),'unverified/off/wrong-user create denied');
        check_http(!HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',$schedule),$config,$token,$settings),'unverified/off/wrong-user schedule denied');
    }
    check_http(!HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',$schedule),$config,$token,array_replace($live,['mode'=>'draft'])),'draft mode never schedules');
    foreach ([['name'=>'Foreign WordPress post 42 — A useful guide'],['name'=>$config['brand'].' WordPress post 0 — A guide'],
        ['name'=>$create['name']."\nInjected"],['editorType'=>'builder'],['timeZone'=>'wrong'],['userId'=>'other'],['extra'=>true],
        ['editorContent'=>str_replace($config['public_origin'].'/guide/','https://evil.example/guide/',$create['editorContent'])],
        ['editorContent'=>str_replace($config['privacy_url'],'https://evil.example/privacy/',$create['editorContent'])],
        ['editorContent'=>str_replace('{{email.unsubscribe_link}}','{{unsubscribe}}',$create['editorContent'])],
        ['editorContent'=>$create['editorContent'].'<script>unsafe()</script>'],
        ['editorContent'=>$create['editorContent'].'<a href="https://evil.example/">Extra</a>']] as $change) {
        check_http(!HttpPolicy::allowed($root,api_args($token,'POST',array_replace($create,$change)),$config,$token,$live),'changed campaign creation denied');
    }
    foreach (['scheduleType'=>'later','timeZone'=>'wrong','userId'=>'other','extra'=>true,
        'scheduleConfig'=>['tracking'=>['clickTracking'=>true,'utmTracking'=>false],'resend'=>['enabled'=>false]],
        'recipients'=>['type'=>'contact','contactIds'=>['fictional-one'],'freezeList'=>false]] as $key=>$value) {
        check_http(!HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',array_replace($schedule,[$key=>$value])),$config,$token,$live),'changed schedule denied');
    }
    foreach (['subject'=>'Foreign: Guide','fromName'=>'Wrong','fromEmail'=>'wrong@example.org','replyToAddress'=>'wrong@example.org',
        'previewText'=>'Wrong','extra'=>'value'] as $key=>$value) {
        $changed=$schedule;$changed['emailMeta'][$key]=$value;
        check_http(!HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',$changed),$config,$token,$live),'changed schedule metadata denied');
    }
    foreach ([$config['brand'].': ',$config['brand'].': {{contact.name}}',$config['brand'].": Guide\nInjected"] as $subject) {
        $changed=$schedule;$changed['emailMeta']['subject']=$subject;
        check_http(!HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',$changed),$config,$token,$live),'unsafe subject denied');
    }
    foreach ([[],['fictional-one','fictional-one'],['id/unsafe'],[7],['first'=> 'fictional-one'],array_fill(0,10001,'overflow')] as $ids) {
        check_http(!HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',schedule_body($config,$ids)),$config,$token,$live),'empty/duplicate/unsafe/nonlist/overflow audience denied');
    }
    $maximum=array_map(static fn($number)=>'fictional-'.$number,range(1,10000));
    check_http(HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',schedule_body($config,$maximum)),$config,$token,$live),'complete unique audience at exact safety limit');
    $maximum[]='fictional-10001';
    check_http(!HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',schedule_body($config,$maximum)),$config,$token,$live),'unique audience beyond safety limit denied');
    $changed=$schedule;$changed['scheduleConfig']['resend']['enabled']=true;
    check_http(!HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',$changed),$config,$token,$live),'automatic resend cannot be enabled');
    $changed=$schedule;unset($changed['recipients']['freezeList']);
    check_http(!HttpPolicy::allowed($root.'/campaign-1/schedule',api_args($token,'POST',$changed),$config,$token,$live),'missing frozen-list flag denied');
    foreach (['','short',str_repeat('a',29),str_repeat('a',4097),$token."\n"] as $badToken) {
        check_http(!HttpPolicy::allowed($root.'/campaign-1',api_args($badToken,'GET'),$config,$badToken,$off),'invalid private credential format denied');
    }
    foreach ([['public_origin'=>'http://bad.example'],['cms_origin'=>$config['cms_origin'].'/'],['location_id'=>'bad/id'],
        ['confirmed_tag'=>''],['brand'=>''],['privacy_url'=>'https://evil.example/privacy/'],['from_email'=>'invalid'],['timezone'=>'invalid']] as $change) {
        check_http(!HttpPolicy::allowed($article,read_args(),array_replace($config,$change),'',$off),'invalid relevant configuration fails closed');
    }
}
// Cross-location/configuration calls cannot reuse another publication's routes or audience filters.
$first=$configs[0];$second=$configs[1];
$firstUrl=$api.'/emails/locations/'.$first['location_id'].'/campaigns/emails/campaign-1';
check_http(!HttpPolicy::allowed($firstUrl,api_args($token,'GET'),$second,$token,$off),'multiple sites cannot share campaign location accidentally');
check_http(!HttpPolicy::allowed($first['public_origin'].'/guide/',read_args(),$second,'',$off),'multiple sites cannot share article origin accidentally');
check_http(!HttpPolicy::allowed($second['public_origin'].'/guide/',read_args(),$second,'',['mode'=>'unknown']),'unknown mode denied');
echo 'PASS '.$checks." generic HTTP policy checks (pure fixtures; no WordPress or network)\n";
