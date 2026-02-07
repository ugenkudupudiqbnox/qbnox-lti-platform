<?php
defined('ABSPATH') || exit;
if (file_exists(PB_LTI_PATH.'vendor/autoload.php')) {
  require PB_LTI_PATH.'vendor/autoload.php';
}

// Load all Services
require_once PB_LTI_PATH.'Services/SecretVault.php';
require_once PB_LTI_PATH.'Services/AuditLogger.php';
require_once PB_LTI_PATH.'Services/PlatformRegistry.php';
require_once PB_LTI_PATH.'Services/DeploymentRegistry.php';
require_once PB_LTI_PATH.'Services/NonceService.php';
require_once PB_LTI_PATH.'Services/JwtValidator.php';
require_once PB_LTI_PATH.'Services/RoleMapper.php';
require_once PB_LTI_PATH.'Services/TokenCache.php';
require_once PB_LTI_PATH.'Services/AGSClient.php';
require_once PB_LTI_PATH.'Services/LineItemService.php';
require_once PB_LTI_PATH.'Services/ContentService.php';

// Load all Controllers
require_once PB_LTI_PATH.'Controllers/LoginController.php';
require_once PB_LTI_PATH.'Controllers/LaunchController.php';
require_once PB_LTI_PATH.'Controllers/DeepLinkController.php';
require_once PB_LTI_PATH.'Controllers/AGSController.php';

// Load admin, routes, and AJAX handlers
require_once PB_LTI_PATH.'admin/menu.php';
require_once PB_LTI_PATH.'routes/rest.php';
require_once PB_LTI_PATH.'ajax/handlers.php';
