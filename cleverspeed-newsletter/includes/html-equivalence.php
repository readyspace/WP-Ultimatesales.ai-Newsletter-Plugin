<?php
namespace CleverSpeed\Newsletter;
defined('ABSPATH') || exit;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;
use DOMText;
use RuntimeException;
use Throwable;

/** Whole-template comparison with only reviewed provider Outlook normalizations. */
final class HtmlEquivalence {
    /** Normalize only the observed Outlook additions; preserve all other ordered CSS declarations. */
    private static function compareStyle(string $style, ?DOMElement $element=null): array {
        $parts=[];$colors=[];$fills=[];
        foreach(explode(';',$style) as $decl){if(trim($decl)==='')continue;$pair=explode(':',$decl,2);if(count($pair)!==2)return [$style];$key=strtolower(trim($pair[0]));$value=trim($pair[1]);$parts[]=[$key,$value];if($key==='color')$colors[]=$value;if($key==='mso-style-textfill-fill-color')$fills[]=$value;}
        if(count($colors)===1&&count($fills)===1&&preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i',$colors[0])&&strtolower($colors[0])===strtolower($fills[0]))$parts=array_values(array_filter($parts,static fn($p)=>$p[0]!=='mso-style-textfill-fill-color'));
        if($element instanceof DOMElement&&$element->tagName==='p'&&($footer=self::footerKind($element->parentNode))!==null){
            $rules=array_values(array_filter($parts,static fn($p)=>$p[0]==='mso-line-height-rule'));
            $remaining=array_values(array_filter($parts,static fn($p)=>$p[0]!=='mso-line-height-rule'));
            $allowed=$footer==='legacy'?[[['margin','0']],[['margin','0 0 12px']]]:[
                self::compareStyle('margin:0 0 12px;color:#526574;font-size:12px;line-height:19px'),
                self::compareStyle('margin:0;font-size:12px;line-height:20px'),
            ];
            if(count($rules)===1&&$rules[0][1]==='exactly'&&in_array($remaining,$allowed,true))$parts=$remaining;
        }
        return $parts;
    }
    /** Only the two reviewed footer cells, not arbitrary CSS or conditional content. */
    private static function footerKind(?DOMNode $node): ?string {
        if(!($node instanceof DOMElement)||$node->tagName!=='td')return null;
        $style=self::compareStyle($node->getAttribute('style'));
        if($style===self::compareStyle('padding:22px 24px;background:#f6f6f6;border-top:1px solid #ddd;font-size:12px;line-height:20px'))return 'legacy';
        if($style===self::compareStyle('padding:22px 28px;background:#f5f8f7;border-top:1px solid #e0e8e5;border-radius:0 0 12px 12px'))return 'directory';
        return null;
    }
    private static function footerCell(?DOMNode $node): bool {
        return self::footerKind($node)!==null;
    }
    /** Ignore only the exact empty trailing spacer observed in the saved legal footer. */
    private static function footerSpacer(DOMComment $comment): bool {
        $footer=self::footerKind($comment->parentNode);
        if($footer===null)return false;
        $background=$footer==='legacy'?'#f6f6f6':'#f5f8f7';
        if(trim($comment->data)!=='[if mso]><p style="margin:0;line-height:8px;mso-line-height-rule:exactly;font-size:8px;background:'.$background.';">&nbsp;</p><![endif]')return false;
        $meaningful=[];foreach($comment->parentNode->childNodes as $child)if(!($child instanceof DOMText)||trim($child->textContent)!=='')$meaningful[]=$child;
        $styles=$footer==='legacy'?['margin:0 0 12px','margin:0']:[
            'margin:0 0 12px;color:#526574;font-size:12px;line-height:19px',
            'margin:0 0 12px;color:#526574;font-size:12px;line-height:19px',
            'margin:0;font-size:12px;line-height:20px',
        ];
        if(count($meaningful)!==count($styles)+1||end($meaningful)!==$comment)return false;
        foreach($styles as $i=>$style){
            if(!($meaningful[$i] instanceof DOMElement)||$meaningful[$i]->tagName!=='p'||
                self::compareStyle($meaningful[$i]->getAttribute('style'),$meaningful[$i])!==self::compareStyle($style))return false;
        }
        return true;
    }
    /** Allow only a balanced same-colour Outlook font wrapper inside its existing link. */
    private static function fontComment(DOMComment $comment): bool {
        $parent=$comment->parentNode;if(!($parent instanceof DOMElement)||$parent->tagName!=='a')return false;
        $comments=[];$meaningful=[];
        foreach($parent->childNodes as $child){if($child instanceof DOMComment)$comments[]=$child;if(!($child instanceof DOMText)||trim($child->textContent)!=='')$meaningful[]=$child;}
        if(count($comments)!==2||count($meaningful)<3||$meaningful[0]!==$comments[0]||end($meaningful)!==$comments[1])return false;
        if(!preg_match('/^\[if mso\]><font color="(#[0-9a-f]{3}(?:[0-9a-f]{3})?)"><!\[endif\]$/i',trim($comments[0]->data),$m)||trim($comments[1]->data)!=='[if mso]></font><![endif]')return false;
        $colors=[];foreach(self::compareStyle($parent->getAttribute('style')) as $decl)if(is_array($decl)&&$decl[0]==='color')$colors[]=$decl[1];
        return count($colors)===1&&strtolower($colors[0])===strtolower($m[1]);
    }

    /** Compare parsed markup/text/attributes with the observed Outlook marker and formatting normalization. */
    private static function signature(string $html): string {
            if($html===''||strlen($html)>500000||str_contains($html,"\0"))throw new RuntimeException('Invalid newsletter HTML');
            $decoded=json_decode($html,true);if(is_string($decoded))$html=$decoded;
            if($html===''||strlen($html)>500000||str_contains($html,"\0"))throw new RuntimeException('Invalid decoded newsletter HTML');
            $old=libxml_use_internal_errors(true);$dom=new DOMDocument();
            libxml_clear_errors();
            try{
                $ok=$dom->loadHTML('<?xml encoding="UTF-8"?>'.$html,LIBXML_NONET);
                if(!$ok)throw new RuntimeException('Unparseable HTML');
                // libxml discards duplicate attributes; never normalize away a second URL/style.
                foreach(libxml_get_errors() as $error)if($error->code===42)throw new RuntimeException('Ambiguous duplicate HTML attribute');
            }
            finally{libxml_clear_errors();libxml_use_internal_errors($old);}
            $walk=static function($node)use(&$walk){
                if($node instanceof DOMComment){return (trim($node->data)==='outlook-fixes-applied'||self::fontComment($node)||self::footerSpacer($node))?null:['comment',$node->data];}
                if($node instanceof DOMProcessingInstruction)return [$node->target,$node->data];
                if($node instanceof DOMText){$text=preg_replace('/\s+/u',' ',$node->textContent);return trim($text)===''?null:['text',trim($text)];}
                $attributes=[];if($node instanceof DOMElement)foreach($node->attributes as $a)$attributes[$a->name]=$a->name==='style'?self::compareStyle($a->value,$node):$a->value;ksort($attributes);
                $children=[];foreach($node->childNodes as $c){$v=$walk($c);if($v!==null)$children[]=$v;}
                return [$node->nodeType,$node->nodeName,$attributes,$children];
            };
            return json_encode($walk($dom),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }
    public static function equivalent(string $expected,string $saved): bool {
        try{return hash_equals(self::signature($expected),self::signature($saved));}catch(Throwable $e){return false;}
    }
}
