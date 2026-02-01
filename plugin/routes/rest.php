<?php
defined('ABSPATH') || exit;

use PB_LTI\Controllers\LoginController;
use PB_LTI\Controllers\LaunchController;
use PB_LTI\Controllers\DeepLinkController;
use PB_LTI\Controllers\AGSController;

add_action('rest_api_init', function () {

    register_rest_route('pb-lti/v1', '/login', [
        'methods' => 'POST',
        'callback' => [LoginController::class, 'handle'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('pb-lti/v1', '/launch', [
        'methods' => 'POST',
        'callback' => [LaunchController::class, 'handle'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('pb-lti/v1', '/deep-link', [
        'methods' => 'POST',
        'callback' => [DeepLinkController::class, 'handle'],
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('pb-lti/v1', '/ags/scores', [
        'methods' => 'POST',
        'callback' => [AGSController::class, 'post_score'],
        'permission_callback' => '__return_true',
    ]);
});
