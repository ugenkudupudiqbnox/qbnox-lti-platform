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
            // Extract user information from LTI claims
            $email = $claims->email ?? $lti_user_id . '@lti.local';
            $given_name = $claims->given_name ?? '';
            $family_name = $claims->family_name ?? '';
            $full_name = $claims->name ?? trim($given_name . ' ' . $family_name);

            // Extract Moodle username if available
            // LTI 1.3 sends Moodle username in preferred_username claim
            $moodle_username = $claims->preferred_username ?? '';

            // Also check custom Moodle extension claim
            if (empty($moodle_username) && isset($claims->{'https://purl.imsglobal.org/spec/lti/claim/ext'})) {
                $ext = $claims->{'https://purl.imsglobal.org/spec/lti/claim/ext'};
                $moodle_username = $ext->user_username ?? '';
            }

            // Debug logging
            error_log('[PB-LTI RoleMapper] User creation - LTI ID: ' . $lti_user_id);
            error_log('[PB-LTI RoleMapper] Moodle username: ' . ($moodle_username ?: 'NOT PROVIDED'));
            error_log('[PB-LTI RoleMapper] Email: ' . $email);
            error_log('[PB-LTI RoleMapper] Given name: ' . ($given_name ?: 'NOT PROVIDED'));
            error_log('[PB-LTI RoleMapper] Family name: ' . ($family_name ?: 'NOT PROVIDED'));
            error_log('[PB-LTI RoleMapper] Full name: ' . ($full_name ?: 'NOT PROVIDED'));

            // Create username - priority order:
            // 1. Use Moodle username directly if available
            // 2. Fall back to firstname.lastname format
            // 3. Fall back to LTI user ID
            if (!empty($moodle_username)) {
                // Use Moodle's actual username
                $username = $moodle_username;
            } elseif (!empty($given_name) && !empty($family_name)) {
                // Use firstname.lastname format
                $username = strtolower($given_name . '.' . $family_name);
            } else {
                // Fall back to LTI user ID
                $username = $lti_user_id;
            }

            // Sanitize username (WordPress requirements)
            $username = sanitize_user($username, true);

            // Ensure unique username
            $base_username = $username;
            $counter = 1;
            while (username_exists($username)) {
                $username = $base_username . $counter;
                $counter++;
            }

            // Create user with real email
            $user_id = wp_create_user($username, wp_generate_password(), $email);

            if (is_wp_error($user_id)) {
                error_log('[PB-LTI] Failed to create user: ' . $user_id->get_error_message());
                return null;
            }

            // Set user's real name information
            wp_update_user([
                'ID' => $user_id,
                'first_name' => $given_name,
                'last_name' => $family_name,
                'display_name' => $full_name ?: $username,
                'nickname' => $full_name ?: $username
            ]);

            // Store LTI ID mapping
            update_user_meta($user_id, '_lti_user_id', $lti_user_id);
            update_user_meta($user_id, '_lti_platform_issuer', $platform_issuer);

            error_log(sprintf(
                '[PB-LTI] Created new user %d (%s) for LTI user: %s - Name: %s, Email: %s',
                $user_id,
                $username,
                $lti_user_id,
                $full_name,
                $email
            ));
        } else {
            // Update existing user's information if it has changed
            $email = $claims->email ?? null;
            $given_name = $claims->given_name ?? '';
            $family_name = $claims->family_name ?? '';
            $full_name = $claims->name ?? trim($given_name . ' ' . $family_name);

            $update_data = ['ID' => $user_id];
            $needs_update = false;

            // Update email if provided and different
            if ($email) {
                $user = get_user_by('id', $user_id);
                if ($user && $user->user_email !== $email) {
                    $update_data['user_email'] = $email;
                    $needs_update = true;
                }
            }

            // Update names if provided
            if ($given_name || $family_name || $full_name) {
                $update_data['first_name'] = $given_name;
                $update_data['last_name'] = $family_name;
                if ($full_name) {
                    $update_data['display_name'] = $full_name;
                }
                $needs_update = true;
            }

            if ($needs_update) {
                wp_update_user($update_data);
                error_log(sprintf(
                    '[PB-LTI] Updated user %d info - Name: %s, Email: %s',
                    $user_id,
                    $full_name,
                    $email ?? 'not provided'
                ));
            }
        }

        $user = get_user_by('id', $user_id);
        $user->set_role($wp_role);
        wp_set_current_user($user->ID);

        // Set auth cookie with "remember me" for LTI contexts (14 days)
        // This is CRITICAL for embedded contexts where session cookies might not work
        $remember = true;  // 14 days instead of session-only
        $secure = is_ssl(); // Use secure cookies if on HTTPS

        wp_set_auth_cookie($user->ID, $remember, $secure);

        error_log('[PB-LTI RoleMapper] Set auth cookie for user ' . $user->ID . ' (remember: ' . ($remember ? 'yes' : 'no') . ', secure: ' . ($secure ? 'yes' : 'no') . ')');

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
