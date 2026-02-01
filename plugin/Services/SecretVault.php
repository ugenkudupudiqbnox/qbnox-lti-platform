<?php
namespace PB_LTI\Services;

class SecretVault {

    private static function key(): string {
        if (defined('PB_LTI_SECRET_KEY')) {
            return PB_LTI_SECRET_KEY;
        }
        return hash('sha256', AUTH_KEY . SECURE_AUTH_KEY);
    }

    public static function store(string $issuer, string $secret): void {
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt(
            $secret,
            'aes-256-gcm',
            self::key(),
            0,
            $iv,
            $tag
        );

        update_site_option('pb_lti_secret_' . md5($issuer), [
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => $ciphertext
        ]);
    }

    public static function retrieve(string $issuer): ?string {
        $stored = get_site_option('pb_lti_secret_' . md5($issuer));
        if (!$stored) {
            return null;
        }

        return openssl_decrypt(
            $stored['data'],
            'aes-256-gcm',
            self::key(),
            0,
            base64_decode($stored['iv']),
            base64_decode($stored['tag'])
        );
    }
}
