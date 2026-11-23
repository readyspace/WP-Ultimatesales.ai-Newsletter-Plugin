<?php
/**
 * Read-only, administrator-only status for this installation's newsletter settings.
 *
 * Copyright (C) 2026 ReadySpace.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
namespace CleverSpeed\Newsletter;

defined('ABSPATH') || exit;

final class SendingConfigurationStatus {
    /**
     * Render only an allowlisted subset of the private, per-installation settings.
     * No API calls, option writes or background work are performed here.
     */
    public static function render(array $config, array $settings, bool $verificationRecorded): void {
        if (!current_user_can('manage_options')) return;

        $mode = match ($settings['mode'] ?? null) {
            'off' => 'Off',
            'draft' => 'Draft-only',
            'live' => 'Live',
            default => 'Unavailable',
        };

        echo '<section aria-labelledby="cs-newsletter-sending-config">';
        echo '<h2 id="cs-newsletter-sending-config">Sending configuration</h2>';
        echo '<p>These are the saved values for this WordPress installation, not values independently verified with UltimateSales.AI. This is a read-only view.</p>';
        echo '<table class="widefat striped"><tbody>';
        self::row('WordPress site title (site setting)', self::siteTitle());
        self::row('Newsletter publication name (configured)', self::field($config, 'brand'));
        self::row('Public website (configured)', self::field($config, 'public_origin'));
        self::row('UltimateSales.AI location ID (configured)', self::field($config, 'location_id'));
        self::row('Sender name (configured)', self::field($config, 'brand'));
        self::row('Sender email (configured)', self::field($config, 'from_email'));
        self::row('Reply-To email (configured)', self::field($config, 'reply_email'));
        self::row('Confirmed-subscriber tag (configured)', self::field($config, 'confirmed_tag'));
        self::row('Pending-subscriber exclusion tag (configured)', self::field($config, 'pending_tag'));
        self::row('Unsubscribed exclusion tag (configured)', self::field($config, 'unsubscribed_tag'));
        self::row('Sending mode (saved)', $mode);
        self::row('Connection-verification gate (recorded)', $verificationRecorded
            ? 'Recorded for this configuration' : 'Not recorded for this configuration');
        self::row('Current eligible-recipient count', 'Unavailable — not checked on this screen');
        self::row('Eligible-recipient count checked at', 'Not checked');
        echo '</tbody></table>';

        echo '<p><strong>How recipients are selected:</strong> The plugin searches the configured UltimateSales.AI location for contacts tagged with the confirmed-subscriber tag shown above. It only retains contacts belonging to that location, with a valid contact ID, valid email address, and the confirmed tag. Contacts carrying either the pending or unsubscribed tag are excluded. Duplicate email addresses are counted once.</p>';
        echo '<p><strong>Do Not Disturb and other suppression:</strong> Global contact Do Not Disturb (DND) must be explicitly false; a missing or unknown global DND state excludes the contact. If email-channel DND settings are present, the email status must explicitly be inactive. If email-channel DND settings are absent, the provider\'s native campaign suppression must still be applied. Contacts that fail the email, location, tag or DND checks are excluded. Malformed contact records, invalid or repeated contact IDs, and incomplete or failed searches stop the entire audience check.</p>';
        echo '<p><strong>What these statuses mean:</strong> The recorded connection gate is an operator-maintained verification state tied to this configuration, not a new live provider check. This page does not fetch or cache contact records. The plugin evaluates eligibility again when preparing a newsletter, but even a current count would not prove consent, successful delivery, or eligibility for a future send.</p>';
        echo '</section>';
    }

    private static function siteTitle(): string {
        $name = get_bloginfo('name');
        return self::displayText($name);
    }

    /** Do not render arbitrary keys such as credentials or contact records. */
    private static function field(array $config, string $key): string {
        if (!array_key_exists($key, $config)) return 'Not configured';
        $value = self::displayText($config[$key]);
        if ($value === 'Not configured' || $value === 'Unavailable') return $value;

        if ($key === 'location_id' && !preg_match('/^[A-Za-z0-9_-]{5,100}$/D', $value)) return 'Unavailable';
        if (in_array($key, ['from_email', 'reply_email'], true) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Unavailable';
        }
        if ($key === 'public_origin') {
            $parts = wp_parse_url($value);
            if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
                isset($parts['user']) || isset($parts['pass']) ||
                isset($parts['port']) || isset($parts['query']) || isset($parts['fragment']) ||
                ($parts['path'] ?? '') !== '' || !filter_var($value, FILTER_VALIDATE_URL)) {
                return 'Unavailable';
            }
        }
        return $value;
    }

    private static function displayText(mixed $value): string {
        if (!is_string($value)) return 'Unavailable';
        $text = trim($value);
        if ($text === '') return 'Not configured';
        // Mirror the private configuration's basic safety bounds; invalid fields are never echoed raw.
        if (strlen($text) > 500 || preg_match('/[\x00-\x1f\x7f<>]/', $text) ||
            str_contains($text, '{{') || str_contains($text, '}}')) return 'Unavailable';
        return $text;
    }

    private static function row(string $label, string $value): void {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
    }
}
