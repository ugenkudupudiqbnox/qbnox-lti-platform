<?php
namespace PB_LTI\Controllers;

use Firebase\JWT\JWT;

class DeepLinkController {
    public static function handle($request) {
        // Minimal Deep Linking response: return selected Pressbooks URL
        $return_url = $request->get_param('deep_link_return_url');
        $content_url = home_url('/');

        $jwt = JWT::encode([
            'iss' => home_url(),
            'aud' => $request->get_param('client_id'),
            'iat' => time(),
            'exp' => time() + 300,
            'https://purl.imsglobal.org/spec/lti-dl/claim/content_items' => [[
                'type' => 'ltiResourceLink',
                'title' => 'Pressbooks Content',
                'url' => $content_url
            ]]
        ], 'CHANGE_ME_PRIVATE_KEY', 'RS256');

        wp_redirect($return_url . '?JWT=' . urlencode($jwt));
        exit;
    }
}
