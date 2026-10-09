<?php
// Narrow test doubles for the WordPress helpers used by isolated suites.
// Absolute HTTPS URLs follow PHP parsing; real WordPress parsing is tested separately.
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_strip_all_tags($text) {
    return trim(strip_tags(preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text)));
}
