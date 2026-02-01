<?php
defined('ABSPATH') || exit;

if (file_exists(PB_LTI_PATH . 'vendor/autoload.php')) {
    require_once PB_LTI_PATH . 'vendor/autoload.php';
}

require_once PB_LTI_PATH . 'services/SecretVault.php';
require_once PB_LTI_PATH . 'services/AGSClient.php';
