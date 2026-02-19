<?php
namespace PB_LTI\Services;

class PlatformRegistry {
    public static function find(string $iss) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->base_prefix}lti_platforms WHERE issuer=%s", $iss)
        );
    }
}
