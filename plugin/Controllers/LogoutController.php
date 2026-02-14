<?php
namespace PB_LTI\Controllers;

/**
 * LogoutController
 *
 * Handles LTI user logout and redirects back to LMS
 */
class LogoutController {

    /**
     * Handle logout request
     * Logs out WordPress user and redirects back to LMS (Moodle)
     */
    public static function handle($request) {
        // Get current user
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_REST_Response([
                'message' => 'No user logged in'
            ], 400);
        }

        // Get LTI return URL (stored during launch)
        $return_url = get_user_meta($user_id, '_lti_return_url', true);
        $platform_issuer = get_user_meta($user_id, '_lti_platform_issuer', true);

        // If no return URL stored, try to construct from platform issuer
        if (!$return_url && $platform_issuer) {
            $parsed = parse_url($platform_issuer);
            $return_url = $parsed['scheme'] . '://' . $parsed['host'] . '/login/logout.php';
        }

        // Default fallback to Moodle home if nothing else works
        if (!$return_url) {
            $return_url = home_url();
        }

        error_log('[PB-LTI Logout] User ' . $user_id . ' logging out, redirecting to: ' . $return_url);

        // Check if this is an LTI user (has LTI context)
        $is_lti_user = !empty(get_user_meta($user_id, '_lti_user_id', true));

        // Log out the WordPress user
        wp_logout();

        // If this was an LTI user, redirect back to LMS
        if ($is_lti_user) {
            wp_redirect($return_url);
            exit;
        }

        // For non-LTI users, just show success
        return new \WP_REST_Response([
            'message' => 'Logged out successfully'
        ], 200);
    }

    /**
     * Initialize logout hooks
     */
    public static function init() {
        // Hook into WordPress logout to redirect LTI users back to LMS
        add_action('wp_logout', [__CLASS__, 'handle_wordpress_logout']);
    }

    /**
     * Handle native WordPress logout
     * If user is LTI user, store redirect URL before logout completes
     */
    public static function handle_wordpress_logout() {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return;
        }

        // Check if this is an LTI user
        $is_lti_user = !empty(get_user_meta($user_id, '_lti_user_id', true));

        if (!$is_lti_user) {
            return; // Not an LTI user, use default WordPress logout behavior
        }

        // Get return URL for redirect
        $return_url = get_user_meta($user_id, '_lti_return_url', true);
        $platform_issuer = get_user_meta($user_id, '_lti_platform_issuer', true);

        // If no return URL stored, try to construct from platform issuer
        if (!$return_url && $platform_issuer) {
            $parsed = parse_url($platform_issuer);
            $return_url = $parsed['scheme'] . '://' . $parsed['host'] . '/login/logout.php';
        }

        if ($return_url) {
            error_log('[PB-LTI Logout] LTI user logging out via wp_logout(), redirecting to: ' . $return_url);

            // Store return URL in session/cookie for redirect after logout
            setcookie('lti_logout_redirect', $return_url, time() + 60, '/', '', true, true);
        }
    }

    /**
     * Check for logout redirect after WordPress logout completes
     * This runs on every page load to check if we need to redirect after logout
     */
    public static function check_logout_redirect() {
        // Only run if user is NOT logged in (logout completed)
        if (is_user_logged_in()) {
            return;
        }

        // Check for logout redirect cookie
        if (isset($_COOKIE['lti_logout_redirect'])) {
            $return_url = $_COOKIE['lti_logout_redirect'];

            // Clear the cookie
            setcookie('lti_logout_redirect', '', time() - 3600, '/', '', true, true);

            error_log('[PB-LTI Logout] Redirecting to LMS after logout: ' . $return_url);

            // Redirect to LMS
            wp_redirect($return_url);
            exit;
        }
    }
}
