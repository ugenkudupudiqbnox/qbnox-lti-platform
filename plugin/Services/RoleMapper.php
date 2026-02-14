<?php
namespace PB_LTI\Services;

class RoleMapper {
    public static function login_user($claims) {
        $roles = $claims->{'https://purl.imsglobal.org/spec/lti/claim/roles'} ?? [];
        $wp_role = 'subscriber';

        foreach ($roles as $role) {
            if (str_contains($role, 'Instructor')) {
                $wp_role = 'editor';
                break;
            }
        }

        // Use LTI user ID (sub claim) as primary identifier
        $lti_user_id = $claims->sub;
        $platform_issuer = $claims->iss;

        // Look up existing user by LTI ID first
        $user_id = self::get_user_by_lti_id($lti_user_id, $platform_issuer);

        if (!$user_id) {
            // Create new user
            $email = $claims->email ?? $lti_user_id . '@lti.local';
            $username = sanitize_user($lti_user_id, true);

            // Ensure unique username
            $base_username = $username;
            $counter = 1;
            while (username_exists($username)) {
                $username = $base_username . $counter;
                $counter++;
            }

            $user_id = wp_create_user($username, wp_generate_password(), $email);

            // Store LTI ID mapping
            update_user_meta($user_id, '_lti_user_id', $lti_user_id);
            update_user_meta($user_id, '_lti_platform_issuer', $platform_issuer);

            error_log('[PB-LTI] Created new user ' . $user_id . ' for LTI user: ' . $lti_user_id);
        }

        $user = get_user_by('id', $user_id);
        $user->set_role($wp_role);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        return $user->ID;
    }

    private static function get_user_by_lti_id($lti_user_id, $platform_issuer) {
        global $wpdb;

        // Look up user by LTI ID and platform
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta}
             WHERE meta_key = '_lti_user_id' AND meta_value = %s
             AND user_id IN (
                 SELECT user_id FROM {$wpdb->usermeta}
                 WHERE meta_key = '_lti_platform_issuer' AND meta_value = %s
             )",
            $lti_user_id,
            $platform_issuer
        ));

        return $user_id ? (int)$user_id : null;
    }
}
