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
    wp_set_current_user($current_user->ID);
} else {
    wp_set_current_user(1);
    $current_user = get_user_by('id', 1);
}

$h5p_plugin = \H5P_Plugin::get_instance();
$interface = $h5p_plugin->get_h5p_instance('interface');
$core = $h5p_plugin->get_h5p_instance('core');
$storage = $h5p_plugin->get_h5p_instance('storage');
$validator = $h5p_plugin->get_h5p_instance('validator');

echo "Syncing H5P Content Type Cache...\n";
if (!$core->updateContentTypeCache()) {
   echo "⚠️ Hub sync returned false.\n";
} else {
   echo "✅ H5P Hub sync complete.\n";
}

$import_dir = '/tmp/h5p_imports';
if (is_dir($import_dir)) {
    $files = glob($import_dir . '/*.h5p');
    echo "Found " . count($files) . " artifacts.\n";
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "Processing $filename...\n";
        
        $official_tmp_path = $interface->getUploadedH5pPath();
        if (!copy($file, $official_tmp_path)) {
            continue;
        }

        try {
            if ($validator->isValidPackage(true, false)) {
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

                $id = $storage->savePackage($content_data, NULL, true);
                
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
        }
        
        if (file_exists($official_tmp_path)) @unlink($official_tmp_path);
    }
}
echo "✅ Done.\n";
