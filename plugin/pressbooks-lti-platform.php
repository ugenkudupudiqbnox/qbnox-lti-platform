<?php
/**
 * Plugin Name: Pressbooks LTI Platform
 * Description: Platform-grade LTI 1.3 Tool implementation for Pressbooks (Bedrock).
 * Version: 0.6.0
 * Network: true
 */
defined('ABSPATH') || exit;

define('PB_LTI_VERSION', '0.6.0');
define('PB_LTI_PATH', plugin_dir_path(__FILE__));
define('PB_LTI_URL', plugin_dir_url(__FILE__));

require_once PB_LTI_PATH . 'bootstrap.php';
