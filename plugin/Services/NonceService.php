<?php
namespace PB_LTI\Services;

class NonceService {
    public static function consume(string $nonce) {
        global $wpdb;
        $table = $wpdb->base_prefix . 'lti_nonces';

        if ($wpdb->get_var($wpdb->prepare("SELECT nonce FROM $table WHERE nonce=%s", $nonce))) {
            throw new \Exception('Replay detected: nonce ' . $nonce . ' already used');
        }

        $wpdb->insert($table, [
            'nonce' => $nonce,
            'expires_at' => gmdate('Y-m-d H:i:s', time()+60)
        ]);
    }
}
