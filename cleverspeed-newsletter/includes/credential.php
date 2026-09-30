<?php
namespace CleverSpeed\Newsletter;

/** No plaintext credentials in options, HTML, logs or API responses. */
final class Credential {
    private const OPTION = 'rs_newsletter_credential';
    private const AAD = 'readyspace-newsletter-credential-v1';
    private static function key(): string {
        if (!function_exists('openssl_encrypt') || !defined('AUTH_KEY') || !defined('SECURE_AUTH_KEY') ||
            strlen(AUTH_KEY) < 32 || strlen(SECURE_AUTH_KEY) < 32 ||
            str_contains(AUTH_KEY, 'put your unique phrase here') || str_contains(SECURE_AUTH_KEY, 'put your unique phrase here')) {
            throw new \RuntimeException('Credential storage requires OpenSSL and strong WordPress keys in wp-config.php.');
        }
        return hash('sha256', self::AAD . AUTH_KEY . SECURE_AUTH_KEY, true);
    }
    public static function read(): string {
        $record = get_option(self::OPTION, []);
        if (!is_array($record) || ($record['v'] ?? null) !== 1) throw new \RuntimeException('Save an integration credential first.');
        $iv = base64_decode($record['iv'] ?? '', true);
        $tag = base64_decode($record['tag'] ?? '', true);
        $cipher = base64_decode($record['cipher'] ?? '', true);
        if ($iv === false || strlen($iv) !== 12 || $tag === false || strlen($tag) !== 16 || $cipher === false) {
            throw new \RuntimeException('Stored credential cannot be read. Re-enter it securely.');
        }
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::AAD);
        if ($plain === false || !self::valid($plain)) throw new \RuntimeException('Stored credential cannot be read. Re-enter it securely.');
        return $plain;
    }
    public static function ready(): bool {
        try { self::read(); return true; } catch (\Throwable $e) { return false; }
    }
    private static function valid(string $token): bool {
        return strlen($token) >= 30 && strlen($token) <= 4096 && preg_match('/^[A-Za-z0-9._-]+$/D', $token) === 1;
    }
    public static function save(string $token): void {
        $token = trim($token);
        if (!self::valid($token)) throw new \RuntimeException('Enter a valid private integration token without spaces.');
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($token, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::AAD, 16);
        unset($token);
        if ($cipher === false) throw new \RuntimeException('Could not encrypt the credential. Nothing was saved.');
        $record = ['v'=>1,'iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'cipher'=>base64_encode($cipher)];
        if (!update_option(self::OPTION, $record, false)) throw new \RuntimeException('Could not save the credential.');
    }
}
