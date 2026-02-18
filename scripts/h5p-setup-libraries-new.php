<?php
/**
 * Setup H5P libraries and import artifacts for the LTI platform.
 * Target: Site 2 (test-book)
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!defined('WP_ADMIN')) define('WP_ADMIN', true);
global $current_user;
$current_user = get_user_by('login', 'admin');
if ($current_user) {
    echo "Using user: " . $current_user->user_login . " (ID: " . $current_user->ID . ")\n";
    wp_set_current_user($current_user->ID);
} else {
    echo "Warning: No user 'admin' found. Using ID 1 as fallback.\n";
    wp_set_current_user(1);
    $current_user = get_user_by('id', 1);
}

echo "Getting H5P Plugin properties...\n";
$h5p_plugin = \H5P_Plugin::get_instance();
$refl = new ReflectionObject($h5p_plugin);

$prop_interface = $refl->getProperty('interface');
$prop_interface->setAccessible(true);
$interface = $prop_interface->getValue($h5p_plugin);

$prop_core = $refl->getProperty('core');
$prop_core->setAccessible(true);
$core = $prop_core->getValue($h5p_plugin);

// Storage and Validator 
$storage = new \H5PStorage($interface, $core);
$validator = new \H5PValidator($interface, $core);

// 1. Set options
echo "Checking H5P options...\n";
update_option('h5p_hub_is_enabled', '1');
update_option('h5p_send_usage_statistics', '0');
update_option('h5p_track_user', '0');
update_option('h5p_export', '1');
update_option('h5p_embed', '1');
update_option('h5p_copyright', '1');
update_option('h5p_icon', '1');
echo "✅ H5P options updated.\n";

// 2. Hub sync
echo "Syncing H5P Content Type Cache from Hub...\n";
if (--allow-rootcore->updateContentTypeCache()) {
   echo "⚠️ Hub sync returned false.\n";
} else {
   echo "✅ H5P Hub sync complete.\n";
}

// 3. Import artifacts
$import_dir = '/tmp/h5p_imports';
if (is_dir($import_dir)) {
    $files = glob($import_dir . '/*.h5p');
    echo "Found " . count($files) . " H5P artifacts.\n";
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "Processing $filename...\n";
        
        $official_tmp_path = $interface->getUploadedH5pPath();
        if (!copy($file, $official_tmp_path)) {
            echo "     ❌ Copy failed\n";
            continue;
        }

        $isFull = true;
        try {
            if ($validator->isValidPackage($isFull, false)) {
                $main_json = $core->mainJsonData;
                $title = !empty($main_json['title']) ? $main_json['title'] : str_replace('.h5p', '', $filename);
                
                $content_data = array(
                    'title' => $title,
                    'metadata' => (object) array('title' => $title),
                    'params' => '{}',
                    'uploaded' => true,
                    'disable' => 0
                );

                $zip = new ZipArchive();
                if ($zip->open($official_tmp_path) === TRUE) {
                    $c_json = $zip->getFromName('content/content.json');
                    if ($c_json) $content_data['params'] = $c_json;
                    $zip->close();
                }

                $id = $storage->savePackage($content_data, NULL, $isFull);
                
                if ($id) {
                    echo "     ✅ Imported (ID: $id)\n";
                } else {
                    global $wpdb;
                    echo "     ❌ Save FAILED. DB Error: " . $wpdb->last_error . "\n";
                }
            } else {
                echo "     ❌ Validation FAILED.\n";
                print_r($validator->getMessages('error'));
            }
        } catch (Exception $e) {
            echo "     💥 Error: " . $e->getMessage() . "\n";
            echo "     Line: " . $e->getLine() . "\n";
        }
        
        if (file_exists($official_tmp_path)) @unlink($official_tmp_path);
    }
} else {
    echo "⚠️ Import directory $import_dir not found.\n";
}
echo "✅ Done.\n";
