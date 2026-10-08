<?php
if (!defined('ABSPATH')) define('ABSPATH', '/unused/');
require_once __DIR__ . '/wp-functions.php';
// Fictional configuration. Tests mock all HTTP and must not load WordPress.
define('RS_NEWSLETTER_CONFIG', [
    'location_id'=>'test-location-123',
    'confirmed_tag'=>'newsletter-confirmed',
    'pending_tag'=>'newsletter-pending',
    'unsubscribed_tag'=>'newsletter-unsubscribed',
    'cms_origin'=>'https://cms.example.org',
    'public_origin'=>'https://example.org',
    'brand'=>'Example Newsletter',
    'from_email'=>'newsletter@example.org',
    'reply_email'=>'reply@example.org',
    'timezone'=>'UTC',
    'legal_name'=>'Example Organisation',
    'postal_address'=>'Example postal address; replace before use',
    'privacy_url'=>'https://example.org/privacy/',
    'preview_text'=>'Practical ideas from our latest article.',
]);
