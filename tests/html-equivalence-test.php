<?php
/** Pure DOM fixtures, no WordPress bootstrap or network. */
if (!defined('ABSPATH')) define('ABSPATH', '/unused/');
require $argv[1] . '/includes/html-equivalence.php';
use CleverSpeed\Newsletter\HtmlEquivalence;
$checks=0;
function assert_html(bool $condition,string $label): void { ++$GLOBALS['checks'];if(!$condition)throw new RuntimeException('FAIL: '.$label); }
$cell='padding:22px 28px;background:#f5f8f7;border-top:1px solid #e0e8e5;border-radius:0 0 12px 12px';
$text='margin:0 0 12px;color:#526574;font-size:12px;line-height:19px';
$links='margin:0;font-size:12px;line-height:20px';
$spacer='<!--[if mso]><p style="margin:0;line-height:8px;mso-line-height-rule:exactly;font-size:8px;background:#f5f8f7;">&nbsp;</p><![endif]-->';
$expected='<!doctype html><html lang="en"><body><table><tr><td style="'.$cell.'">'.
    '<p style="'.$text.'">You receive this email because you confirmed your Fictional Newsletter newsletter subscription.</p>'.
    '<p style="'.$text.'">Synthetic Legal Organisation<br>Synthetic postal address</p>'.
    '<p style="'.$links.'"><a href="https://example.org/privacy-policy/" style="color:#247264;text-decoration:underline">Privacy notice</a> &nbsp;·&nbsp; <a href="{{email.unsubscribe_link}}" style="color:#247264;text-decoration:underline">Unsubscribe</a></p>'.
    '</td></tr></table></body></html>';
$rules=str_replace(['style="'.$text.'"','style="'.$links.'"'],['style="'.$text.';mso-line-height-rule:exactly"','style="'.$links.';mso-line-height-rule:exactly"'],$expected);
$saved=str_replace('</td>',$spacer.'</td>',$rules);
foreach([$expected,$rules,str_replace('</td>',$spacer.'</td>',$expected),$saved] as $legitimate){
    assert_html(HtmlEquivalence::equivalent($expected,$legitimate),'exact official footer or observed Outlook additions accepted');
}
$font=str_replace('color:#247264;text-decoration:underline','color:#247264;text-decoration:underline;mso-style-textfill-fill-color:#247264',$saved);
assert_html(HtmlEquivalence::equivalent($expected,$font),'existing matching-colour Outlook normalization remains accepted');
$bad=[
    'cell background'=>str_replace('background:#f5f8f7;border-top','background:#ffffff;border-top',$saved),
    'cell padding'=>str_replace('padding:22px 28px','padding:22px 24px',$saved),
    'cell border'=>str_replace('border-top:1px solid #e0e8e5','border-top:1px solid #ddd',$saved),
    'text colour'=>str_replace('color:#526574','color:#ffffff',$saved),
    'text line height'=>str_replace('line-height:19px','line-height:20px',$saved),
    'text alteration'=>str_replace('Synthetic Legal Organisation','Another Organisation',$saved),
    'consent alteration'=>str_replace('confirmed your','attended',$saved),
    'privacy URL'=>str_replace('https://example.org/privacy-policy/','https://evil.example/privacy/',$saved),
    'unsubscribe URL'=>str_replace('{{email.unsubscribe_link}}','https://evil.example/unsubscribe/',$saved),
    'legacy unsubscribe'=>str_replace('{{email.unsubscribe_link}}','{{unsubscribe}}',$saved),
    'extra paragraph'=>str_replace($spacer,'<p style="'.$text.'">Extra marketing copy</p>'.$spacer,$saved),
    'missing paragraph'=>str_replace('<p style="'.$text.';mso-line-height-rule:exactly">Synthetic Legal Organisation<br>Synthetic postal address</p>','',$saved),
    'repeated spacer'=>str_replace($spacer,$spacer.$spacer,$saved),
    'nontrailing spacer'=>str_replace('</p><p style="'.$links.';mso-line-height-rule:exactly">','</p>'.$spacer.'<p style="'.$links.';mso-line-height-rule:exactly">',str_replace($spacer,'',$saved)),
    'spacer text'=>str_replace('>&nbsp;</p><![endif]-->','>Hidden marketing text</p><![endif]-->',$saved),
    'spacer link'=>str_replace('>&nbsp;</p><![endif]-->','><a href="https://evil.example/">Hidden link</a></p><![endif]-->',$saved),
    'spacer colour'=>str_replace('font-size:8px;background:#f5f8f7','font-size:8px;background:#f6f6f6',$saved),
    'spacer height'=>str_replace('line-height:8px','line-height:9px',$saved),
    'spacer script'=>str_replace('>&nbsp;</p><![endif]-->','>&nbsp;<script>alert(1)</script></p><![endif]-->',$saved),
    'different mso rule'=>str_replace('mso-line-height-rule:exactly','mso-line-height-rule:at-least',$saved),
    'duplicate mso rule'=>str_replace($text.';mso-line-height-rule:exactly',$text.';mso-line-height-rule:exactly;mso-line-height-rule:exactly',$saved),
    'extra mso rule outside footer'=>str_replace('</body>','<p style="margin:0;mso-line-height-rule:exactly">Outside footer</p></body>',$saved),
    'wrong textfill colour'=>str_replace('color:#247264;text-decoration:underline','color:#247264;text-decoration:underline;mso-style-textfill-fill-color:#ffffff',$saved),
];
foreach($bad as $label=>$html)assert_html(!HtmlEquivalence::equivalent($expected,$html),'alteration held: '.$label);
// Directly verify spacer suppression is tied to the exact original child shape.
foreach(['valid'=>$saved,'extra'=>$bad['extra paragraph'],'missing'=>$bad['missing paragraph'],'repeated'=>$bad['repeated spacer'],'moved'=>$bad['nontrailing spacer'],'wrong colour'=>$bad['spacer colour']] as $label=>$html){
    $dom=new DOMDocument();$old=libxml_use_internal_errors(true);$dom->loadHTML($html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($old);
    $xpath=new DOMXPath($dom);$allowed=0;foreach($xpath->query('//comment()') as $comment)if((new ReflectionMethod(HtmlEquivalence::class,'footerSpacer'))->invoke(null,$comment))$allowed++;
    assert_html($allowed===($label==='valid'?1:0),'spacer shape check: '.$label);
}
$legacy='<html><body><table><tr><td style="padding:22px 24px;background:#f6f6f6;border-top:1px solid #ddd;font-size:12px;line-height:20px"><p style="margin:0 0 12px">Legal text</p><p style="margin:0">Privacy links</p></td></tr></table></body></html>';
$legacySaved=str_replace(['style="margin:0 0 12px"','style="margin:0"'],['style="margin:0 0 12px;mso-line-height-rule:exactly"','style="margin:0;mso-line-height-rule:exactly"'],$legacy);
$legacySaved=str_replace('</td>',str_replace('background:#f5f8f7','background:#f6f6f6',$spacer).'</td>',$legacySaved);
assert_html(HtmlEquivalence::equivalent($legacy,$legacySaved),'legacy footer normalization unchanged');
assert_html(!HtmlEquivalence::equivalent('',''),'empty HTML fails closed');
assert_html(!HtmlEquivalence::equivalent($expected,$expected."\0"),'NUL bytes fail closed');
assert_html(!HtmlEquivalence::equivalent($expected,json_encode($expected."\0<p>Hidden text</p>")),'decoded NUL cannot hide changed markup');
assert_html(!HtmlEquivalence::equivalent($expected,str_replace('href="https://example.org/privacy-policy/"','href="https://example.org/privacy-policy/" href="https://evil.example/"',$expected)),'duplicate href cannot hide a second URL');
assert_html(!HtmlEquivalence::equivalent($expected,str_replace('style="'.$cell.'"','style="'.$cell.'" style="display:none"',$expected)),'duplicate style cannot hide changed presentation');
$previous=libxml_use_internal_errors(true);$unrelated=new DOMDocument();$unrelated->loadHTML('<a href="one" href="two">Unrelated markup</a>');
assert_html(HtmlEquivalence::equivalent($expected,$expected),'unrelated prior libxml errors do not hold valid newsletter');libxml_use_internal_errors($previous);
assert_html(!HtmlEquivalence::equivalent($expected,str_repeat('x',500001)),'oversized HTML fails closed');
assert_html(HtmlEquivalence::equivalent($expected,json_encode($saved)),'provider JSON-string representation normalized');
echo 'PASS '.$checks." narrow footer equivalence checks; pure DOM fixtures, no network.\n";
