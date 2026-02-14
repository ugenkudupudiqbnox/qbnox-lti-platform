<?php
namespace PB_LTI\Services;

/**
 * LogoutLinkService
 *
 * Adds "Return to LMS" logout link for LTI users
 */
class LogoutLinkService {

    /**
     * Initialize logout link hooks
     */
    public static function init() {
        // Add logout link to admin bar
        add_action('admin_bar_menu', [__CLASS__, 'add_logout_link_to_admin_bar'], 100);

        // Note: Logout button in content removed per user request
        // Users can use the admin bar "Return to LMS" link instead
    }

    /**
     * Add "Return to LMS" link to WordPress admin bar for LTI users
     */
    public static function add_logout_link_to_admin_bar($wp_admin_bar) {
        // Only show for logged-in users
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Check if this is an LTI user
        $is_lti_user = !empty(get_user_meta($user_id, '_lti_user_id', true));

        if (!$is_lti_user) {
            return; // Not an LTI user, use default WordPress logout
        }

        // Get return URL
        $return_url = get_user_meta($user_id, '_lti_return_url', true);
        $platform_issuer = get_user_meta($user_id, '_lti_platform_issuer', true);

        // Construct logout URL if not stored
        if (!$return_url && $platform_issuer) {
            $parsed = parse_url($platform_issuer);
            $return_url = $parsed['scheme'] . '://' . $parsed['host'];
        }

        // Add custom logout link that goes through our logout endpoint
        $logout_url = rest_url('pb-lti/v1/logout');

        $wp_admin_bar->add_node([
            'id'    => 'lti-return-to-lms',
            'title' => '← Return to LMS',
            'href'  => $logout_url,
            'meta'  => [
                'class' => 'lti-logout-link',
                'title' => 'Log out and return to your Learning Management System'
            ]
        ]);

        // Also update the default logout link to use our endpoint
        $wp_admin_bar->remove_node('logout');
        $wp_admin_bar->add_node([
            'id'     => 'logout',
            'parent' => 'user-actions',
            'title'  => 'Log Out',
            'href'   => $logout_url,
        ]);
    }

    /**
     * Add logout button to content for LTI users (when in embed mode)
     */
    public static function add_logout_button_to_content($content) {
        // Only show for logged-in LTI users
        if (!is_user_logged_in()) {
            return $content;
        }

        // Only show in LTI embed mode
        if (!isset($_GET['lti_launch'])) {
            return $content;
        }

        $user_id = get_current_user_id();

        // Check if this is an LTI user
        $is_lti_user = !empty(get_user_meta($user_id, '_lti_user_id', true));

        if (!$is_lti_user) {
            return $content;
        }

        // Add logout button at the bottom of content
        $logout_url = rest_url('pb-lti/v1/logout');

        $logout_button = '
        <div class="lti-logout-container" style="margin-top: 2em; padding-top: 2em; border-top: 1px solid #ddd; text-align: center;">
            <a href="' . esc_url($logout_url) . '" class="button lti-logout-button" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">
                ← Return to LMS
            </a>
        </div>';

        return $content . $logout_button;
    }
}
