<?php
defined('ABSPATH') || exit;

use PB_LTI\Controllers\LoginController;
use PB_LTI\Controllers\LaunchController;
use PB_LTI\Controllers\DeepLinkController;
use PB_LTI\Controllers\AGSController;

// Debug logging
error_log('[PB-LTI] routes/rest.php loaded at ' . date('Y-m-d H:i:s'));

// Register routes at plugins_loaded with high priority
add_action('plugins_loaded', function () {
    error_log('[PB-LTI] plugins_loaded hook fired');

    // Then register on rest_api_init
    add_action('rest_api_init', function () {
        error_log('[PB-LTI] rest_api_init hook fired, registering routes');

    // OIDC Login Initiation
    register_rest_route('pb-lti/v1', '/login', [
        'methods' => ['GET', 'POST'],
        'callback' => [LoginController::class, 'handle'],
        'permission_callback' => '__return_true',
    ]);

    // LTI Launch
    register_rest_route('pb-lti/v1', '/launch', [
        'methods' => 'POST',
        'callback' => [LaunchController::class, 'handle'],
        'permission_callback' => '__return_true',
    ]);

    // Deep Linking
    register_rest_route('pb-lti/v1', '/deep-link', [
        'methods' => ['GET', 'POST'],
        'callback' => [DeepLinkController::class, 'handle'],
        'permission_callback' => '__return_true',
    ]);

    // JWKS (Public Key Set)
    register_rest_route('pb-lti/v1', '/keyset', [
        'methods' => 'GET',
        'callback' => function() {
            global $wpdb;
            $key = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}lti_keys WHERE kid = 'pb-lti-2024'");
            
            if (!$key) {
                return new WP_REST_Response(['error' => 'No keys configured'], 500);
            }
            
            // Extract public key components
            $public_key_details = openssl_pkey_get_details(openssl_pkey_get_public($key->public_key));
            $n = rtrim(strtr(base64_encode($public_key_details['rsa']['n']), '+/', '-_'), '=');
            $e = rtrim(strtr(base64_encode($public_key_details['rsa']['e']), '+/', '-_'), '=');
            
            $keys = [
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'use' => 'sig',
                        'kid' => 'pb-lti-2024',
                        'alg' => 'RS256',
                        'n' => $n,
                        'e' => $e
                    ]
                ]
            ];
            return new WP_REST_Response($keys, 200);
        },
        'permission_callback' => '__return_true',
    ]);

    // Assignment & Grade Services
    register_rest_route('pb-lti/v1', '/ags/post-score', [
        'methods' => 'POST',
        'callback' => [AGSController::class, 'post_score'],
        'permission_callback' => '__return_true',
    ]);
    }, 10); // rest_api_init priority 10
}, 1); // plugins_loaded priority 1 (early)
