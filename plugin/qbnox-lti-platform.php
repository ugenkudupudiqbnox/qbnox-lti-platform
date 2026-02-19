<?php
/**
 * Plugin Name: Pressbooks LTI Platform
 * Version: 0.8.0
 * Network: true
 */
defined('ABSPATH') || exit;

error_log('[PB-LTI] Main plugin file loaded at ' . date('Y-m-d H:i:s'));

define('PB_LTI_VERSION','0.8.0');
define('PB_LTI_PATH',plugin_dir_path(__FILE__));

// CRITICAL: Load cookie override BEFORE WordPress pluggable.php
// This allows us to override wp_set_auth_cookie() with SameSite=None support
require_once PB_LTI_PATH.'lti-cookie-override.php';
error_log('[PB-LTI] Cookie override loaded');

error_log('[PB-LTI] Loading bootstrap.php from: ' . PB_LTI_PATH);

require_once PB_LTI_PATH.'bootstrap.php';

error_log('[PB-LTI] Bootstrap loaded successfully');
