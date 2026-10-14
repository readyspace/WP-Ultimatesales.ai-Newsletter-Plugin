<?php
namespace CleverSpeed\Newsletter;
defined('ABSPATH') || exit;
require_once __DIR__ . '/config.php';

final class Policy {

    public static function plain(string $value): string {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    public static function eligible(array $contact): bool {
        if (!Config::ready()) return false;
        $tags = $contact['tags'] ?? null;
        if (($contact['locationId'] ?? '') !== Config::get('location_id') ||
            !is_string($contact['id'] ?? null) || $contact['id'] === '' ||
            !is_string($contact['email'] ?? null) || !filter_var($contact['email'], FILTER_VALIDATE_EMAIL) ||
            !is_array($tags) || !in_array(Config::get('confirmed_tag'), $tags, true)) {
            return false;
        }
        // Unknown global DND is not permission to send.
        if (!array_key_exists('dnd', $contact) || $contact['dnd'] !== false) {
            return false;
        }
        // Present email states must be explicitly inactive. A genuinely absent
        // channel is left to the native marketing campaign's final suppression.
        $channels = array_key_exists('dndSettings', $contact) ? $contact['dndSettings'] : [];
        if (!is_array($channels)) return false;
        foreach ($channels as $channel => $setting) {
            if (!is_string($channel)) return false;
            if (strcasecmp($channel, 'email') !== 0) continue;
            if (!is_array($setting) || !is_string($setting['status'] ?? null) ||
                strtolower($setting['status']) !== 'inactive') return false;
        }
        foreach ([Config::get('pending_tag'), Config::get('unsubscribed_tag')] as $tag) {
            if (in_array($tag, $tags, true)) return false;
        }
        return true;
    }

    public static function excerpt(string $text): string {
        $text = self::plain($text);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) < 10 || count($words) > 150) {
            throw new \RuntimeException('Add a 10–150 word Excerpt; aim for 60–90 words. Full article content is never substituted.');
        }
        // Do not allow editor text to introduce provider merge tokens.
        return str_replace(['{{', '}}'], ['', ''], $text);
    }

    public static function publicUrl(string $permalink): string {
        $parts = wp_parse_url($permalink);
        if (!$parts || !in_array($parts['host'] ?? '', [wp_parse_url(Config::get('cms_origin'), PHP_URL_HOST), wp_parse_url(Config::get('public_origin'), PHP_URL_HOST)], true) ||
            ($parts['scheme'] ?? '') !== 'https' || isset($parts['query']) || isset($parts['fragment']) ||
            isset($parts['user']) || isset($parts['port'])) {
            throw new \RuntimeException('The post must have a clean configured-site HTTPS permalink.');
        }
        $path = $parts['path'] ?? '/';
        if ($path === '/' || str_contains($path, '..') || str_contains($path, '//')) {
            throw new \RuntimeException('Unsafe or missing article path.');
        }
        return Config::get('public_origin') . $path;
    }

    public static function samePublicArticle(string $candidate, string $url): bool {
        $expected = wp_parse_url($url);
        $actual = wp_parse_url($candidate);
        if (!$expected || !$actual) return false;
        foreach ([$expected, $actual] as $parts) {
            $path = $parts['path'] ?? '/';
            if (($parts['scheme'] ?? '') !== 'https' ||
                ('https://' . ($parts['host'] ?? '')) !== Config::get('public_origin') ||
                isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) ||
                isset($parts['query']) || isset($parts['fragment']) ||
                $path === '/' || str_contains($path, '..') || str_contains($path, '//')) return false;
        }
        // Only the final slash may differ; no other route normalization occurs.
        return rtrim($actual['path'], '/') === rtrim($expected['path'], '/');
    }

    public static function assertPublicArticle(string $html, string $url, string $title): string {
        $dom = new \DOMDocument();
        $old = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($old);
        $xpath = new \DOMXPath($dom);
        $h1 = $xpath->query('//h1');
        $canonical = $xpath->query('//link[contains(concat(" ",normalize-space(@rel)," ")," canonical ")]/@href');
        if ($h1->length !== 1 || self::plain($h1->item(0)->textContent) !== self::plain($title) ||
            $canonical->length !== 1 || !self::samePublicArticle($canonical->item(0)->value, $url)) {
            throw new \RuntimeException('Public page title/canonical is not the expected article; email held.');
        }
        foreach ($xpath->query('//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots"]/@content') as $node) {
            if (stripos($node->value, 'noindex') !== false) throw new \RuntimeException('Public article is noindex; email held.');
        }
        return $canonical->item(0)->value;
    }

    public static function content(string $title, string $excerpt, string $url): string {
        $escape = static fn($s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = str_replace(['{{', '}}'], '', self::plain($title));
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $escape($title) . '</title></head>' .
            '<body style="margin:0;padding:0;background:#edf2f3;color:#192d40;font-family:Arial,Helvetica,sans-serif">' .
            '<div style="display:none;font-size:1px;color:#edf2f3;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden">' . $escape(Config::get('preview_text')) . '</div>' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#edf2f3"><tr><td align="center" style="padding:24px 12px">' .
            '<!--[if mso]><table role="presentation" width="600"><tr><td><![endif]-->' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background:#ffffff;border:1px solid #dce5e9;border-radius:12px">' .
            '<tr><td style="padding:28px 28px;background:#192d40;border-radius:12px 12px 0 0"><p style="margin:0;color:#ffffff;font-size:26px;font-weight:bold;letter-spacing:-0.5px">' . $escape(Config::get('brand')) . '</p><p style="margin:8px 0 0;color:#d5e5e1;font-size:14px;line-height:21px">' . $escape(Config::get('preview_text')) . '</p></td></tr>' .
            '<tr><td style="padding:30px 28px 12px"><p style="margin:0 0 14px;color:#247264;font-size:12px;font-weight:bold;letter-spacing:1px">THE LATEST GUIDE</p>' .
            '<h1 style="margin:0 0 22px;color:#192d40;font-size:28px;line-height:36px;font-weight:bold">' . $escape($title) . '</h1>' .
            '<p style="margin:0 0 24px;color:#344b5d;font-size:17px;line-height:28px">' . $escape($excerpt) . '</p>' .
            '<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td bgcolor="#247264" style="border-radius:6px;padding:15px 22px"><a href="' . $escape($url) . '" style="color:#ffffff;text-decoration:none;font-size:16px;font-weight:bold;line-height:22px">Read the full guide &#8594;</a></td></tr></table>' .
            '<p style="margin:24px 0 18px;font-size:14px;line-height:22px;color:#526574">Have a question about the guide? Reply to this email.</p></td></tr>' .
            '<tr><td style="padding:22px 28px;background:#f5f8f7;border-top:1px solid #e0e8e5;border-radius:0 0 12px 12px">' .
            '<p style="margin:0 0 12px;color:#526574;font-size:12px;line-height:19px">You receive this email because you confirmed your ' . $escape(Config::get('brand')) . ' newsletter subscription.</p>' .
            '<p style="margin:0 0 12px;color:#526574;font-size:12px;line-height:19px">' . $escape(Config::get('legal_name')) . '<br>' . $escape(Config::get('postal_address')) . '</p>' .
            '<p style="margin:0;font-size:12px;line-height:20px"><a href="' . $escape(Config::get('privacy_url')) . '" style="color:#247264;text-decoration:underline">Privacy notice</a> &nbsp;·&nbsp; <a href="{{email.unsubscribe_link}}" style="color:#247264;text-decoration:underline">Unsubscribe</a></p>' .
            '</td></tr></table><!--[if mso]></td></tr></table><![endif]--></td></tr></table></body></html>';
    }
}
