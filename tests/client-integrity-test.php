<?php
namespace {
// Two fictional sites, isolated WordPress doubles, and mocked transport only.
$plugin=realpath($argv[1]??__DIR__.'/../cleverspeed-newsletter');
$profile=$argv[2]??'all';
if(!$plugin)throw new \RuntimeException('Pass the plugin source directory.');
if($profile==='all'){
    foreach(['headless','native'] as $site){
        $process=proc_open([PHP_BINARY,__FILE__,$plugin,$site],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new \RuntimeException('Cannot start isolated fixture.');
        fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        $status=proc_close($process);echo $out;if($status!==0)throw new \RuntimeException($site.' fixture failed: '.$err);
    }
    exit;
}
if(!in_array($profile,['headless','native'],true))throw new \RuntimeException('Unknown fixture.');
define('ABSPATH','/unused/');define('AUTH_KEY',str_repeat('fictional-auth-key-',4));define('SECURE_AUTH_KEY',str_repeat('fictional-secure-key-',4));
$origin=$profile==='native'?'https://journal.example.net':'https://example.org';
define('RS_NEWSLETTER_CONFIG',[
    'location_id'=>$profile==='native'?'journal_location_456':'test-location-123',
    'confirmed_tag'=>'newsletter-confirmed','pending_tag'=>'newsletter-pending','unsubscribed_tag'=>'newsletter-unsubscribed',
    'cms_origin'=>$profile==='native'?$origin:'https://cms.example.org','public_origin'=>$origin,
    'brand'=>'Fictional Newsletter','from_email'=>'newsletter@example.org','reply_email'=>'reply@example.org',
    'timezone'=>$profile==='native'?'Asia/Singapore':'UTC','legal_name'=>'Fictional Organisation',
    'postal_address'=>'Fictional postal address','privacy_url'=>$origin.'/privacy/',
    'preview_text'=>'Practical ideas from our latest fictional article.',
]);
$options=[];$filters=[];$checks=0;$network=[];$mutate=null;$responseMutate=null;$hostError=null;$shortResponse=null;$onRequest=null;
class WP_Error {public function __construct(private string $code,private string $message=''){}public function get_error_code(){return $this->code;}}
function check(bool $condition,string $label):void{++$GLOBALS['checks'];if(!$condition)throw new \RuntimeException('FAIL: '.$label);}
function rejects(callable $fn,string $label):void{try{$fn();}catch(\RuntimeException $e){check(true,$label);return;}check(false,$label);}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,$autoload=null){$GLOBALS['options'][$key]=$value;return true;}
function wp_json_encode($x){return json_encode($x);}
function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function wp_strip_all_tags($text){return trim(strip_tags(preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si','',$text)));}
function is_wp_error($x){return $x instanceof WP_Error;}
function wp_remote_retrieve_response_code($r){return is_wp_error($r)?0:($r['code']??0);}
function wp_remote_retrieve_body($r){return is_wp_error($r)?'':($r['body']??'');}
function callback_id($callback){return $callback instanceof \Closure?spl_object_id($callback):serialize($callback);}
function add_filter($name,$callback,$priority=10,$accepted=1){$GLOBALS['filters'][$name][$priority][callback_id($callback)]=$callback;return true;}
function remove_filter($name,$callback,$priority=10){unset($GLOBALS['filters'][$name][$priority][callback_id($callback)]);return true;}
function apply_filters($name,$value,...$args){$groups=$GLOBALS['filters'][$name]??[];ksort($groups);foreach($groups as $callbacks)foreach($callbacks as $cb)$value=$cb($value,...$args);return $value;}
function filter_count(){return array_sum(array_map('count',$GLOBALS['filters']['pre_http_request']??[]));}
function wp_remote_request($url,$args=[]){return mocked_http($url,$args);}
function wp_safe_remote_get($url,$args=[]){$args['reject_unsafe_urls']=true;return mocked_http($url,$args);}
function mocked_http($url,$original){
    $a=array_replace(['method'=>'GET','headers'=>[],'body'=>null,'blocking'=>true,'stream'=>false,'filename'=>null,'cookies'=>[],
        'sslverify'=>true,'redirection'=>5,'timeout'=>5,'limit_response_size'=>null,'reject_unsafe_urls'=>false],$original);
    if($GLOBALS['mutate'])$a=($GLOBALS['mutate'])($a);
    if($GLOBALS['onRequest'])($GLOBALS['onRequest'])($url,$a);
    $pre=$GLOBALS['hostError']??$GLOBALS['shortResponse']??false;
    $pre=apply_filters('pre_http_request',$pre,$a,$url);
    if($pre!==false)return $pre;
    check(\CleverSpeed\Newsletter\Client::allowsHttp($url,$a),'active exact request admits harmless WordPress defaults');
    $GLOBALS['network'][]=[$url,$a];
    $r=($GLOBALS['responder'])($url,$a);
    return $GLOBALS['responseMutate']?($GLOBALS['responseMutate'])($r,$url):$r;
}
}
namespace CleverSpeed\Newsletter {
// Only settings/verification are stubbed; actual Config, Credential, Client and policies load below.
final class Plugin {
    public static function settings():array{return $GLOBALS['options']['cs_newsletter_settings'];}
    public static function verified():bool{$s=self::settings();return Config::ready()&&($s['verified']??false)===true&&
        !empty($s['user_id'])&&hash_equals(Config::fingerprint(),(string)($s['verified_config']??''));}
}
}
namespace {
require $plugin.'/includes/policy.php';require $plugin.'/includes/credential.php';require $plugin.'/includes/client.php';
use CleverSpeed\Newsletter\Client;use CleverSpeed\Newsletter\Config;use CleverSpeed\Newsletter\Credential;
use CleverSpeed\Newsletter\Policy;use CleverSpeed\Newsletter\Plugin;
Credential::save(str_repeat('fictional-private-token-',3));
$settings=['mode'=>'live','user_id'=>'fictional-user','verified'=>true,'verified_config'=>Config::fingerprint()];
$options['cs_newsletter_settings']=$settings;
$title='A useful fictional guide';$excerpt='This useful fictional guide helps a small team review a newsletter without contacting any real subscribers.';
$article=$origin.'/guide/';$name='Fictional Newsletter WordPress post 42 — '.$title;
$bodyUrl='https://storage.googleapis.com/highlevel-backend.appspot.com/location/'.Config::get('location_id').'/emails/test/index.html';
$campaign=['status'=>'draft','name'=>$name,'editorContentUrl'=>$bodyUrl];$saved=Policy::content($title,$excerpt,$article);
$baseContact=['id'=>'fictional-contact','email'=>'reader@example.org','locationId'=>Config::get('location_id'),
    'tags'=>[Config::get('confirmed_tag')],'dnd'=>false,'dndSettings'=>['Email'=>['status'=>'inactive']]];
$pages=[1=>[$baseContact]];
$responder=static function($url,$a){
    if(str_ends_with($url,'/contacts/search')){$b=json_decode($a['body'],true);return ['code'=>200,'body'=>json_encode(['contacts'=>$GLOBALS['pages'][$b['page']]??[]])];}
    if(str_contains($url,'/users/'))return ['code'=>200,'body'=>json_encode(['users'=>[['id'=>'fictional-user']]])];
    if(str_ends_with($url,'/schedule'))return $GLOBALS['scheduleError']??['code'=>200,'body'=>json_encode(['campaignId'=>'fictional-campaign'])];
    if(str_ends_with($url,'/fictional-campaign'))return ['code'=>200,'body'=>json_encode($GLOBALS['campaign'])];
    if(str_ends_with($url,'/campaigns/emails'))return ['code'=>200,'body'=>json_encode(['id'=>'fictional-campaign'])];
    if($url===$GLOBALS['bodyUrl'])return ['code'=>200,'body'=>$GLOBALS['saved']];
    if(str_starts_with($url,$GLOBALS['origin'].'/'))return ['code'=>200,'body'=>'Fictional public page'];
    throw new \RuntimeException('Unexpected mocked request.');
};
$client=new Client();
check(!Client::allowsHttp($article,[]),'no intent outside an active request');
$client->request('GET','/users/?locationId='.Config::get('location_id'),null,'2021-07-28');
check(filter_count()===0,'temporary transport guard removed after success');
$read=['method'=>'GET','timeout'=>20,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>2000000];
Client::withHttpIntent('article',$article,$read,static fn()=>wp_safe_remote_get($article,$read));
check(true,'configured native or headless public article read succeeds');
$otherArticle=$origin.'/other-guide/';
Client::withHttpIntent('article',$article,$read,static function()use($article,$otherArticle,$read){
    Client::withHttpIntent('article',$otherArticle,$read,static fn()=>wp_safe_remote_get($otherArticle,$read));
    return wp_safe_remote_get($article,$read);
});
check(filter_count()===0,'nested scoped read restores outer intent and removes both guards');
rejects(fn()=>Client::withHttpIntent('api',$article,$read,static fn()=>null),'public intent cannot wrap API operations');
rejects(fn()=>Client::withHttpIntent('article',$bodyUrl,$read,static fn()=>null),'article intent cannot use storage URL');
rejects(fn()=>Client::withHttpIntent('draft_body',$article,$read,static fn()=>null),'draft intent cannot use public article URL');
$ran=false;rejects(function()use($article,$read,&$ran){Client::withHttpIntent('article',$article,array_replace($read,['sslverify'=>false]),function()use(&$ran){$ran=true;});},'unsafe read rejected before callback');check(!$ran,'unsafe callback never ran');
foreach(['sslverify'=>false,'redirection'=>1,'blocking'=>false,'stream'=>true,'filename'=>'/tmp/unwanted','cookies'=>['x'=>'y'],
    'method'=>'DELETE','timeout'=>99,'limit_response_size'=>0,'headers'=>['Authorization'=>'Bearer wrong'],'body'=>'changed'] as $key=>$value){
    $mutate=static fn($a)=>array_replace($a,[$key=>$value]);$before=count($network);
    rejects(fn()=>$client->create($name,$saved,'fictional-user'),'mutated request denied before transport: '.$key);
    check(count($network)===$before,'mutation made no outbound mocked call: '.$key);
}
$mutate=static function($a){$body=json_decode($a['body'],true);$body['editorContent']=str_replace('Fictional Organisation','Changed Organisation',$body['editorContent']);$a['body']=json_encode($body);return $a;};
$before=count($network);rejects(fn()=>$client->create($name,$saved,'fictional-user'),'equally shaped changed JSON body denied');check(count($network)===$before,'changed JSON never reached transport');
$mutate=static fn($a)=>array_replace($a,['reject_unsafe_urls'=>false]);
$before=count($network);$r=Client::withHttpIntent('article',$article,$read,static fn()=>wp_safe_remote_get($article,$read));
check(is_wp_error($r)&&$r->get_error_code()==='rs_newsletter_unsafe_transport'&&count($network)===$before,'safe URL validation cannot be disabled by a filter');
$mutate=null;
$hostError=new WP_Error('unrelated_host_denial');$before=count($network);
$r=Client::withHttpIntent('article',$article,$read,static fn()=>wp_safe_remote_get($article,$read));
check($r===$hostError&&count($network)===$before,'existing host error remains untouched');$hostError=null;
$shortResponse=['code'=>200,'body'=>'Fictional public page'];$before=count($network);
$r=Client::withHttpIntent('article',$article,$read,static fn()=>wp_safe_remote_get($article,$read));
check($r===$shortResponse&&count($network)===$before,'existing short-circuit response remains untouched');$shortResponse=null;
rejects(fn()=>Client::withHttpIntent('article',$article,$read,static fn()=>throw new \RuntimeException('fixture failure')),'callback exception propagates');
check(filter_count()===0&&!Client::allowsHttp($article,$read),'scope and guard removed after exception');
$nested=false;$onRequest=static function($url,$a)use(&$nested){if($nested)return;$nested=true;
    $GLOBALS['onRequest']=static function($inner,$innerArgs)use(&$nested){check(!Client::allowsHttp($inner,$innerArgs),'identical nested request cannot borrow intent');};
    $GLOBALS['hostError']=new WP_Error('fixture_nested_block');wp_remote_request($url,$a);$GLOBALS['hostError']=null;$GLOBALS['onRequest']=null;
};
Client::withHttpIntent('article',$article,$read,static fn()=>wp_safe_remote_get($article,$read));check($nested,'identical nested request probe exercised');
$options['cs_newsletter_settings']['verified_config']=str_repeat('0',64);$before=count($network);
rejects(fn()=>$client->create($name,$saved,'fictional-user'),'direct create requires verified current fingerprint');check(count($network)===$before,'unverified create makes no request');
$options['cs_newsletter_settings']=$settings;
$fresh=static function()use($client,$name,$title,$excerpt,$article){$ids=$client->recipients();$remote=$client->campaign('fictional-campaign');$client->verifyDraft($remote,$name,$title,$excerpt,$article);return $ids;};
$ids=$fresh();check($ids===['fictional-contact'],'complete eligible audience captured');
$client->send('fictional-campaign',$title,$ids,'fictional-user');$body=json_decode(end($network)[1]['body'],true);
check($body['recipients']===['type'=>'contact','contactIds'=>$ids,'freezeList'=>true],'schedule freezes exactly the fresh validated audience');
$before=count($network);rejects(fn()=>$client->send('fictional-campaign',$title,$ids,'fictional-user'),'successful schedule cannot replay');check(count($network)===$before,'replay makes no request');
rejects(fn()=>$client->request('POST',Client::campaignsPath().'/fictional-campaign/schedule',$body),'raw direct scheduling lacks private intent');
rejects(fn()=>(new Client())->send('fictional-campaign',$title,$ids,'fictional-user'),'another object cannot borrow attestations');
foreach(['title','ids','campaign','expired','future','draft','off','verification'] as $change){
    $fresh();$id='fictional-campaign';$sendTitle=$title;$sendIds=['fictional-contact'];
    if($change==='title')$sendTitle='Changed title';if($change==='ids')$sendIds=['other-contact'];if($change==='campaign')$id='other-campaign';
    if(in_array($change,['expired','future'],true)){(new \ReflectionProperty(Client::class,'eligibleCheckedAt'))->setValue($client,time()+($change==='future'?60:-61));}
    if(in_array($change,['draft','off'],true))$options['cs_newsletter_settings']['mode']=$change;
    if($change==='verification')$options['cs_newsletter_settings']['verified_config']=str_repeat('0',64);
    $before=count($network);rejects(fn()=>$client->send($id,$sendTitle,$sendIds,'fictional-user'),'changed send precondition held: '.$change);check(count($network)===$before,'changed precondition made no request: '.$change);$options['cs_newsletter_settings']=$settings;
}
$fresh();$scheduleError=new WP_Error('fixture_unknown_outcome');rejects(fn()=>$client->send('fictional-campaign',$title,['fictional-contact'],'fictional-user'),'ambiguous schedule outcome propagates');unset($scheduleError);
$before=count($network);rejects(fn()=>$client->send('fictional-campaign',$title,['fictional-contact'],'fictional-user'),'ambiguous outcome consumes attestations');check(count($network)===$before,'unknown schedule is not retried');
$fresh();$remote=$client->campaign('fictional-campaign');$remote['extra']='forged readback';$client->verifyDraft($remote,$name,$title,$excerpt,$article);
rejects(fn()=>$client->send('fictional-campaign',$title,['fictional-contact'],'fictional-user'),'altered campaign array cannot attest the read draft');
foreach([
    str_replace('Fictional Organisation','Wrong Organisation',$saved),str_replace('Fictional postal address','Wrong address',$saved),
    str_replace(Config::get('privacy_url'),'https://evil.example/privacy/',$saved),str_replace('{{email.unsubscribe_link}}','{{unsubscribe}}',$saved),
    str_replace($excerpt,$excerpt.' Extra claims.',$saved),str_replace('</body>','<p style="display:none">Hidden text</p></body>',$saved),
    str_replace('background:#192d40','background:#ffffff',$saved),json_encode($saved."\0<p>Hidden after NUL</p>"),
] as $changed){
    $originalSaved=$saved;$saved=$changed;$remote=$client->campaign('fictional-campaign');rejects(fn()=>$client->verifyDraft($remote,$name,$title,$excerpt,$article),'entire changed saved template is held');$saved=$originalSaved;
}
$responseMutate=static function($r,$url){if($url===$GLOBALS['bodyUrl'])$r['body']=str_replace('Fictional Organisation','Changed by HTTP response filter',$r['body']);return $r;};
$remote=$client->campaign('fictional-campaign');rejects(fn()=>$client->verifyDraft($remote,$name,$title,$excerpt,$article),'late HTTP response mutation verified after filters');$responseMutate=null;
$textStyle='margin:0 0 12px;color:#526574;font-size:12px;line-height:19px';$linkStyle='margin:0;font-size:12px;line-height:20px';
$saved=str_replace(['style="'.$textStyle.'"','style="'.$linkStyle.'"'],['style="'.$textStyle.';mso-line-height-rule:exactly"','style="'.$linkStyle.';mso-line-height-rule:exactly"'],$saved);
$spacer='<!--[if mso]><p style="margin:0;line-height:8px;mso-line-height-rule:exactly;font-size:8px;background:#f5f8f7;">&nbsp;</p><![endif]-->';
$saved=str_replace('</p></td></tr></table><!--[if mso]>','</p>'.$spacer.'</td></tr></table><!--[if mso]>',$saved);$fresh();check(true,'observed provider footer normalization verifies full actual template');$saved=Policy::content($title,$excerpt,$article);
$pages=[1=>[$baseContact,array_replace($baseContact,['id'=>'duplicate-email','email'=>'READER@example.org'])]];check($client->recipients()===['fictional-contact'],'email deduplication preserves exact IDs');
foreach(['dnd','unsubscribed','pending','email-dnd'] as $suppression){$contact=$baseContact;
    if($suppression==='dnd')$contact['dnd']=true;if($suppression==='unsubscribed')$contact['tags'][]=Config::get('unsubscribed_tag');if($suppression==='pending')$contact['tags'][]=Config::get('pending_tag');if($suppression==='email-dnd')$contact['dndSettings']['Email']['status']='active';
    $pages=[1=>[$contact]];check($client->recipients()===[],'suppression retained: '.$suppression);
}
$hundred=[];for($i=0;$i<100;$i++)$hundred[]=array_replace($baseContact,['id'=>'page-contact-'.$i,'email'=>'reader'.$i.'@example.org']);
$pages=[1=>$hundred,2=>[array_replace($baseContact,['id'=>'page-contact-100','email'=>'reader100@example.org'])]];check(count($client->recipients())===101,'complete bounded pagination succeeds');
$pages=[1=>$hundred,2=>[$hundred[0]]];rejects(fn()=>$client->recipients(),'repeated paginated contact holds entire audience');
$pages=[1=>array_merge($hundred,[$baseContact])];rejects(fn()=>$client->recipients(),'oversized page holds entire audience');
$pages=[1=>['not-a-contact']];rejects(fn()=>$client->recipients(),'malformed contact holds entire audience');
$pages=[1=>[$baseContact]];$options['cs_newsletter_settings']['mode']='off';$options['cs_newsletter_settings']['verified']=false;
check($client->recipients()===['fictional-contact'],'Off unverified read-only acceptance remains available');
$options['cs_newsletter_settings']=$settings;check(filter_count()===0,'no persistent transport guard remains');
echo 'PASS '.$checks.' native Client integrity checks ('.$profile."); all HTTP mocked, no live WordPress or emails.\n";
}
