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

        // Store AGS context for grade passback (if available)
        $ags_claim = $claims->{'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint'} ?? null;
        if ($ags_claim && isset($ags_claim->lineitem)) {
            // Store AGS endpoint in user meta for later grade posting
            update_user_meta($user_id, '_lti_ags_lineitem', $ags_claim->lineitem);
            update_user_meta($user_id, '_lti_ags_scope', $ags_claim->scope ?? []);
            update_user_meta($user_id, '_lti_platform_issuer', $claims->iss);
            update_user_meta($user_id, '_lti_resource_link_id', $claims->{'https://purl.imsglobal.org/spec/lti/claim/resource_link'}->id ?? '');
            update_user_meta($user_id, '_lti_user_id', $claims->sub); // LTI user ID for grade posting

            error_log('[PB-LTI] Stored AGS context for user ' . $user_id . ' - lineitem: ' . $ags_claim->lineitem);
        }

        // Get target link URI from claims
        $target_link_uri = $claims->{'https://purl.imsglobal.org/spec/lti/claim/target_link_uri'} ?? home_url();

        // Add LTI embed parameter to show clean view (just content, no site chrome)
        $target_link_uri = add_query_arg('lti_launch', '1', $target_link_uri);

        // Redirect to target or home
        wp_redirect($target_link_uri);
        exit;
    }
}
