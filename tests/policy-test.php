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
check(Policy::publicUrl('https://cms.example.org/example/')==='https://example.org/example/','headless public URL candidate');
foreach (['https://evil.example/article/','https://example.org/','https://cms.example.org/?p=2','http://example.org/a/','https://x@example.org/a/','https://example.org:443/a/','https://example.org/a/../b/'] as $url) rejects(fn()=>Policy::publicUrl($url),'unsafe permalink');
$excerpt='Check the task that is slow before you change your internet plan.';
check(Policy::excerpt('<p>'.$excerpt.'</p>')===$excerpt,'clean excerpt');
rejects(fn()=>Policy::excerpt(''),'empty excerpt');
rejects(fn()=>Policy::excerpt(str_repeat('word ',151)),'long excerpt');
$url='https://example.org/test/';$title='A clear & useful guide';
$html='<html><head><link rel="canonical" href="'.$url.'"></head><body><h1>A clear &amp; useful guide</h1></body></html>';
Policy::assertPublicArticle($html,$url,$title);check(true,'correct article');
rejects(fn()=>Policy::assertPublicArticle(str_replace($url,'https://cms.example.org/test/',$html),$url,$title),'wrong canonical');
rejects(fn()=>Policy::assertPublicArticle(str_replace('</body>','<h1>Extra</h1></body>',$html),$url,$title),'duplicate H1');
rejects(fn()=>Policy::assertPublicArticle(str_replace('</head>','<meta name="robots" content="noindex"></head>',$html),$url,$title),'noindex');
rejects(fn()=>Policy::assertPublicArticle($html,$url,'Wrong title'),'wrong title');
$email=Policy::content('<script>attack</script>'.$title,$excerpt,$url);
check(!str_contains($email,'<script>'),'escaped template');
check(str_contains($email,'{{unsubscribe}}'),'unsubscribe token');
check(str_contains($email,$url),'public article link');
check(!str_contains(Policy::excerpt($excerpt.' {{contact.email}}'),'{{'),'no injected merge tokens');
echo "PASS $tests policy checks\n";
