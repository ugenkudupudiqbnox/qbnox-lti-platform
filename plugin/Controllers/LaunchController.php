<?php
namespace PB_LTI\Controllers;

use PB_LTI\Services\JwtValidator;
use PB_LTI\Services\NonceService;
use PB_LTI\Services\DeploymentRegistry;
use PB_LTI\Services\RoleMapper;

class LaunchController {
    public static function handle($request) {
        $jwt = $request->get_param('id_token');
        if (!$jwt) {
            return new \WP_Error('missing_token', 'Missing id_token', ['status'=>400]);
        }

        $claims = JwtValidator::validate($jwt);

        DeploymentRegistry::validate(
            $claims->iss,
            $claims->{'https://purl.imsglobal.org/spec/lti/claim/deployment_id'}
        );

        NonceService::consume($claims->nonce);

        // Check message type - handle Deep Linking requests differently
        $message_type = $claims->{'https://purl.imsglobal.org/spec/lti/claim/message_type'} ?? 'LtiResourceLinkRequest';

        if ($message_type === 'LtiDeepLinkingRequest') {
            // This is a Deep Linking request - forward to DeepLinkController
            error_log('[PB-LTI] Deep Linking request detected, showing content picker');
            return DeepLinkController::handle_deep_linking_launch($claims);
        }

        // Regular LTI launch - login user and redirect
        $user_id = RoleMapper::login_user($claims);

        // Get target link URI from claims
        $target_link_uri = $claims->{'https://purl.imsglobal.org/spec/lti/claim/target_link_uri'} ?? home_url();

        // Store LMS return URL for logout (use launch_presentation or construct from issuer)
        $launch_presentation = $claims->{'https://purl.imsglobal.org/spec/lti/claim/launch_presentation'} ?? null;
        $return_url = $launch_presentation->return_url ?? null;

        // If no return URL, construct logout URL from platform issuer
        if (!$return_url) {
            $platform_issuer = $claims->iss;
            // Convert issuer to logout URL (e.g., https://moodle.example.com/login/logout.php)
            $parsed = parse_url($platform_issuer);
            $return_url = $parsed['scheme'] . '://' . $parsed['host'] . '/login/logout.php';
        }

        update_user_meta($user_id, '_lti_return_url', $return_url);
        error_log('[PB-LTI] Stored return URL for user ' . $user_id . ': ' . $return_url);

        // Store AGS context for grade passback (if available)
        $ags_claim = $claims->{'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint'} ?? null;
        if ($ags_claim && isset($ags_claim->lineitem)) {
            // Extract post_id from target URL (handles multisite URLs)
            $post_id = self::get_post_id_from_url($target_link_uri);

            // Store global LTI context (user-level)
            update_user_meta($user_id, '_lti_platform_issuer', $claims->iss);
            update_user_meta($user_id, '_lti_user_id', $claims->sub); // LTI user ID for grade posting

            // Store chapter-specific AGS context (user + post level)
            if ($post_id) {
                // Use post meta to store per-user, per-chapter lineitem
                $lineitem_key = '_lti_ags_lineitem_user_' . $user_id;
                update_post_meta($post_id, $lineitem_key, $ags_claim->lineitem);

                // Also store scope and resource link for this specific chapter
                $scope_key = '_lti_ags_scope_user_' . $user_id;
                update_post_meta($post_id, $scope_key, $ags_claim->scope ?? []);

                $resource_link_key = '_lti_resource_link_id_user_' . $user_id;
                $resource_link_id = $claims->{'https://purl.imsglobal.org/spec/lti/claim/resource_link'}->id ?? '';
                update_post_meta($post_id, $resource_link_key, $resource_link_id);

                error_log('[PB-LTI] Stored AGS context for user ' . $user_id . ', post ' . $post_id . ' - lineitem: ' . $ags_claim->lineitem);
            } else {
                error_log('[PB-LTI] Warning: Could not extract post_id from target URL: ' . $target_link_uri);
                // Fall back to old behavior (store in user meta) for non-post launches
                update_user_meta($user_id, '_lti_ags_lineitem', $ags_claim->lineitem);
                update_user_meta($user_id, '_lti_ags_scope', $ags_claim->scope ?? []);
                update_user_meta($user_id, '_lti_resource_link_id', $claims->{'https://purl.imsglobal.org/spec/lti/claim/resource_link'}->id ?? '');
                error_log('[PB-LTI] Stored AGS context for user ' . $user_id . ' (no post_id) - lineitem: ' . $ags_claim->lineitem);
            }
        }

        // Add LTI embed parameter to show clean view (just content, no site chrome)
        $target_link_uri = add_query_arg('lti_launch', '1', $target_link_uri);

        // Redirect to target or home
        wp_redirect($target_link_uri);
        exit;
    }

    /**
     * Get post ID from URL (handles WordPress multisite)
     *
     * @param string $url The URL to extract post_id from
     * @return int|null Post ID or null if not found
     */
    private static function get_post_id_from_url($url) {
        // For multisite, we need to switch to the correct blog first
        if (is_multisite()) {
            // Get the blog ID from the URL
            $blog_id = get_blog_id_from_url(parse_url($url, PHP_URL_HOST), parse_url($url, PHP_URL_PATH));

            if ($blog_id) {
                // Switch to the target blog
                switch_to_blog($blog_id);

                // Get the post ID
                $post_id = url_to_postid($url);

                // Switch back to the original blog
                restore_current_blog();

                if ($post_id) {
                    error_log('[PB-LTI] Extracted post_id ' . $post_id . ' from URL (blog ' . $blog_id . '): ' . $url);
                    return $post_id;
                }
            }
        } else {
            // Single site - use standard function
            $post_id = url_to_postid($url);
            if ($post_id) {
                error_log('[PB-LTI] Extracted post_id ' . $post_id . ' from URL: ' . $url);
                return $post_id;
            }
        }

        // If url_to_postid failed, try parsing the URL manually
        // Pressbooks chapter URLs typically follow: /book-slug/chapter/chapter-slug/
        $path = parse_url($url, PHP_URL_PATH);
        if (preg_match('#/([^/]+)/(chapter|part|front-matter|back-matter)/([^/]+)/?$#', $path, $matches)) {
            $book_slug = $matches[1];
            $type = $matches[2];
            $chapter_slug = $matches[3];

            // Get blog ID for this book
            if (is_multisite()) {
                global $wpdb;
                $blog_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT blog_id FROM {$wpdb->blogs} WHERE path LIKE %s OR path LIKE %s",
                    '/' . $book_slug . '/',
                    '/' . $book_slug
                ));

                if ($blog_id) {
                    switch_to_blog($blog_id);

                    // Get post by slug
                    $post = get_page_by_path($chapter_slug, OBJECT, ['chapter', 'front-matter', 'back-matter', 'part']);

                    restore_current_blog();

                    if ($post) {
                        error_log('[PB-LTI] Extracted post_id ' . $post->ID . ' from manual parsing (blog ' . $blog_id . '): ' . $url);
                        return $post->ID;
                    }
                }
            }
        }

        error_log('[PB-LTI] Warning: Could not extract post_id from URL: ' . $url);
        return null;
    }
}
