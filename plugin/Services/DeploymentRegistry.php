<?php
namespace PB_LTI\Services;

class DeploymentRegistry {
    public static function validate(string $iss, string $deployment_id) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->base_prefix}lti_deployments WHERE platform_issuer=%s AND deployment_id=%s",
            $iss, $deployment_id
        ));
        if (!$exists) {
            throw new \Exception('Invalid deployment_id: ' . $deployment_id . ' for issuer ' . $iss);
        }
    }
}
