<?php
require_once __DIR__ . '/fixture.php';
require $argv[1] . '/includes/policy.php';
use CleverSpeed\Newsletter\Policy;
$tests = 0;
function check($condition,$label) { global $tests; ++$tests; if (!$condition) throw new RuntimeException('FAIL: '.$label); }
function rejects($fn,$label) { try { $fn(); } catch (RuntimeException $e) { check(true,$label); return; } check(false,$label); }
$good=['id'=>'test','locationId'=>\CleverSpeed\Newsletter\Config::get('location_id'),'email'=>'reader@example.org','tags'=>[\CleverSpeed\Newsletter\Config::get('confirmed_tag')],'dnd'=>false,'dndSettings'=>[]];
check(Policy::eligible($good),'confirmed subscriber');
foreach ([['dnd'=>true],['dnd'=>null],['dnd'=>'false'],['email'=>'invalid'],['locationId'=>'other'],['tags'=>[]],['dndSettings'=>['Email'=>['status'=>'active']]],['dndSettings'=>['Email'=>['status'=>'permanent']]],['tags'=>[\CleverSpeed\Newsletter\Config::get('confirmed_tag'),'newsletter-pending']],['tags'=>[\CleverSpeed\Newsletter\Config::get('confirmed_tag'),'newsletter-unsubscribed']]] as $bad) check(!Policy::eligible(array_replace($good,$bad)),'ineligible subscriber');
$unknown=$good;unset($unknown['dnd']);check(!Policy::eligible($unknown),'missing DND fails closed');
check(Policy::eligible(array_replace($good,['dndSettings'=>['SMS'=>['status'=>'active']]])),'SMS suppression does not imply email consent changes');
foreach ([['id'=>['invalid']],['email'=>['invalid']],['tags'=>'newsletter-confirmed'],['dndSettings'=>null],['dndSettings'=>'invalid'],['dndSettings'=>['Email'=>null]],['dndSettings'=>['Email'=>'active']],['dndSettings'=>['Email'=>[]]],['dndSettings'=>['Email'=>['status'=>'unknown']]]] as $bad) check(!Policy::eligible(array_replace($good,$bad)),'malformed consent fields fail closed');
foreach (['Email','email','eMaIl'] as $channel) {
    foreach (['inactive','INACTIVE'] as $status) check(Policy::eligible(array_replace($good,['dndSettings'=>[$channel=>['status'=>$status]]])),'case-insensitive explicit inactive email DND');
    foreach (['active','permanent','disabled','unknown','inactive ',''] as $status) check(!Policy::eligible(array_replace($good,['dndSettings'=>[$channel=>['status'=>$status]]])),'explicit suppressed or unknown email DND rejected regardless of key case');
}
foreach ([['email'=>null],['EMAIL'=>'inactive'],['eMaIl'=>['status'=>null]],['Email'=>['status'=>'inactive'],'email'=>['status'=>'active']],['Email'=>['status'=>'inactive'],'email'=>[]],['Email'=>['status'=>'inactive'],0=>['status'=>'inactive']]] as $channels) check(!Policy::eligible(array_replace($good,['dndSettings'=>$channels])),'malformed or conflicting email channel states fail closed');
check(Policy::eligible(array_replace($good,['dndSettings'=>['Email'=>['status'=>'inactive'],'email'=>['status'=>'INACTIVE']]])),'consistent duplicate email channels allowed');
$absent=$good;unset($absent['dndSettings']);check(Policy::eligible($absent),'absent email metadata delegates final suppression to native campaign');
foreach ([true,null,'false'] as $dnd) check(!Policy::eligible(array_replace($absent,['dnd'=>$dnd])),'absent email metadata never relaxes explicit global DND');
unset($absent['dnd']);check(!Policy::eligible($absent),'absent global and email metadata fail closed');
check(Policy::publicUrl('https://cms.example.org/example/')==='https://example.org/example/','headless public URL candidate');
foreach (['https://evil.example/article/','https://example.org/','https://cms.example.org/?p=2','http://example.org/a/','https://x@example.org/a/','https://example.org:443/a/','https://example.org/a/../b/'] as $url) rejects(fn()=>Policy::publicUrl($url),'unsafe permalink');
$excerpt='Check the task that is slow before you change your internet plan.';
check(Policy::excerpt('<p>'.$excerpt.'</p>')===$excerpt,'clean excerpt');
rejects(fn()=>Policy::excerpt(''),'empty excerpt');
rejects(fn()=>Policy::excerpt(str_repeat('word ',151)),'long excerpt');
$url='https://example.org/test/';$title='A clear & useful guide';
$html='<html><head><link rel="canonical" href="'.$url.'"></head><body><h1>A clear &amp; useful guide</h1></body></html>';
Policy::assertPublicArticle($html,$url,$title);check(true,'correct article');
check(Policy::assertPublicArticle(str_replace($url,rtrim($url,'/'),$html),$url,$title)==='https://example.org/test','same article slashless canonical becomes campaign URL');
check(Policy::assertPublicArticle($html,rtrim($url,'/'),$title)===$url,'same article slashful canonical becomes campaign URL');
foreach (['https://example.org/other/','https://example.org/test/?q=1','https://example.org/test/#section','https://x@example.org/test/','https://example.org:443/test/','//example.org/test/','http://example.org/test/','https://example.org/','https://example.org/test//','https://example.org/a/../test/','https://cms.example.org/test/'] as $candidate) {
    check(!Policy::samePublicArticle($candidate,$url),'unsafe or different redirect route rejected');
    rejects(fn()=>Policy::assertPublicArticle(str_replace($url,$candidate,$html),$url,$title),'unsafe or different canonical rejected');
}
rejects(fn()=>Policy::assertPublicArticle(str_replace($url,'https://cms.example.org/test/',$html),$url,$title),'wrong canonical');
rejects(fn()=>Policy::assertPublicArticle(str_replace('</body>','<h1>Extra</h1></body>',$html),$url,$title),'duplicate H1');
rejects(fn()=>Policy::assertPublicArticle(str_replace('</head>','<meta name="robots" content="noindex"></head>',$html),$url,$title),'noindex');
rejects(fn()=>Policy::assertPublicArticle($html,$url,'Wrong title'),'wrong title');
$email=Policy::content('<script>attack</script>'.$title,$excerpt,$url);
check(!str_contains($email,'<script>'),'escaped template');
check(str_contains($email,'href="{{email.unsubscribe_link}}"'),'native email unsubscribe anchor');
check(!str_contains($email,'{{unsubscribe}}'),'obsolete unsubscribe token absent');
check(str_contains($email,$url),'public article link');
check(!str_contains(Policy::excerpt($excerpt.' {{contact.email}}'),'{{'),'no injected merge tokens');
check(Policy::plain('<script>unsafe()</script><style>hidden</style>Safe excerpt')==='Safe excerpt','script/style contents are not newsletter text');
echo "PASS $tests policy checks\n";
