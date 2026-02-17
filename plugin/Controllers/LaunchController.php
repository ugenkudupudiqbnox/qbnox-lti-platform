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

        // Get target link URI from claims early to detect target blog
        $target_link_uri = $claims->{'https://purl.imsglobal.org/spec/lti/claim/target_link_uri'} ?? home_url();

        // Resolve target URL to extract blog_id (needed for correct user login/association)
        $resolved = self::resolve_url($target_link_uri);
        $target_blog_id = $resolved['blog_id'] ?? get_current_blog_id();

        // Regular LTI launch - login user and redirect (passing target_blog_id)
        $user_id = RoleMapper::login_user($claims, $target_blog_id);

        // Store AGS context for grade passback (if available)
        $ags_claim = $claims->{'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint'} ?? null;
        if ($ags_claim && isset($ags_claim->lineitem)) {
            // Use already resolved post_id and blog_id
            $post_id = $resolved['post_id'] ?? 0;
            $blog_id = $target_blog_id;

            // Store global LTI context (user-level)
            update_user_meta($user_id, '_lti_platform_issuer', $claims->iss);
            update_user_meta($user_id, '_lti_user_id', $claims->sub); // LTI user ID for grade posting

            // Store chapter-specific AGS context (user + post level)
            if ($post_id) {
                // Switch to target blog to update post meta
                if (is_multisite() && $blog_id != get_current_blog_id()) {
                    switch_to_blog($blog_id);
                }

                // Use post meta to store per-user, per-chapter lineitem
                $lineitem_key = '_lti_ags_lineitem_user_' . $user_id;
                update_post_meta($post_id, $lineitem_key, $ags_claim->lineitem);

                // Also store scope and resource link for this specific chapter
                $scope_key = '_lti_ags_scope_user_' . $user_id;
                update_post_meta($post_id, $scope_key, $ags_claim->scope ?? []);

                $resource_link_key = '_lti_resource_link_id_user_' . $user_id;
                $resource_link_id = $claims->{'https://purl.imsglobal.org/spec/lti/claim/resource_link'}->id ?? '';
                update_post_meta($post_id, $resource_link_key, $resource_link_id);

                error_log('[PB-LTI] Stored AGS context for user ' . $user_id . ', post ' . $post_id . ' (blog ' . $blog_id . ') - lineitem: ' . $ags_claim->lineitem);

                if (is_multisite()) {
                    restore_current_blog();
                }
            } else {
                error_log('[PB-LTI] Warning: Could not resolve post_id from target URL: ' . $target_link_uri);
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
     * Resolve URL to post_id and blog_id (handles WordPress multisite)
     *
     * @param string $url The URL to resolve
     * @return array Array with post_id and blog_id
     */
    public static function resolve_url($url) {
        $result = [
            'post_id' => null,
            'blog_id' => get_current_blog_id(),
        ];

        // For multisite, we need to find the correct blog first
        if (is_multisite()) {
            $blog_id = get_blog_id_from_url(parse_url($url, PHP_URL_HOST), parse_url($url, PHP_URL_PATH));

            if ($blog_id) {
                $result['blog_id'] = $blog_id;
                switch_to_blog($blog_id);
                $result['post_id'] = url_to_postid($url);
                restore_current_blog();

                if ($result['post_id']) {
                    error_log('[PB-LTI] Resolved post_id ' . $result['post_id'] . ' from URL (blog ' . $blog_id . '): ' . $url);
                    return $result;
                }
            }
        } else {
            // Single site - use standard function
            $result['post_id'] = url_to_postid($url);
            if ($result['post_id']) {
                error_log('[PB-LTI] Resolved post_id ' . $result['post_id'] . ' from URL: ' . $url);
                return $result;
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
                    $result['blog_id'] = $blog_id;
                    switch_to_blog($blog_id);

                    // Get post by slug using get_posts (more reliable than get_page_by_path for non-top-level)
                    $posts = get_posts([
                        'name' => $chapter_slug,
                        'post_type' => ['chapter', 'front-matter', 'back-matter', 'part'],
                        'posts_per_page' => 1,
                        'fields' => 'ids'
                    ]);

                    restore_current_blog();

                    if (!empty($posts)) {
                        $result['post_id'] = $posts[0];
                        error_log('[PB-LTI] Resolved post_id ' . $result['post_id'] . ' from manual parsing (blog ' . $blog_id . '): ' . $url);
                        return $result;
                    }
                }
            }
        }

        error_log('[PB-LTI] Warning: Could not resolve post_id for URL: ' . $url);
        return $result;
    }
}
