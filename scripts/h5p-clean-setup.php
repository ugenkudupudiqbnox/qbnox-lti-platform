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

echo "Getting H5P Plugin instances...\n";
$h5p_plugin = \H5P_Plugin::get_instance();
$interface = $h5p_plugin->get_h5p_instance('interface');
$core = $h5p_plugin->get_h5p_instance('core');
$storage = $h5p_plugin->get_h5p_instance('storage');
$validator = $h5p_plugin->get_h5p_instance('validator');

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
if ('832,860p'core->updateContentTypeCache()) {
   echo "⚠️ Hub sync returned false.\n";
} else {
   echo "✅ H5P Hub sync complete.\n";
}

// 3. Ensure ArithmeticQuiz is installed (it's our primary test artifact)
$libraries = $core->loadLibraries();
$has_arithmetic_quiz = false;
foreach ($libraries as $lib_name => $versions) {
    if ($lib_name === 'H5P.ArithmeticQuiz') {
        $has_arithmetic_quiz = true;
        break;
    }
}

if ('832,860p'has_arithmetic_quiz) {
    echo "Installing H5P.ArithmeticQuiz from Hub...\n";
    try {
        if (class_exists('H5PEditorAjax')) {
            $editor_ajax = new H5PEditorAjax($core, \H5P_Plugin::get_instance()->get_editor(), $interface);
            $refl = new ReflectionMethod('H5PEditorAjax', 'libraryInstall');
            $refl->setAccessible(true);
            $refl->invoke($editor_ajax, 'H5P.ArithmeticQuiz', 1, 1);
            echo "✅ H5P.ArithmeticQuiz installed.\n";
        }
    } catch (Exception $e) {
        echo "⚠️ Error installing ArithmeticQuiz: " . $e->getMessage() . "\n";
    }
} else {
    echo "✅ H5P.ArithmeticQuiz is already installed.\n";
}

// 4. Import artifacts
$import_dir = '/tmp/h5p_imports';
if (is_dir($import_dir)) {
    $files = glob($import_dir . '/*.h5p');
    echo "Found " . count($files) . " H5P artifacts to import.\n";
    
    foreach ($files as $file) {
        $filename = basename($file);
        echo "Processing $filename...\n";
        
        $official_tmp_path = $interface->getUploadedH5pPath();
        if (!copy($file, $official_tmp_path)) {
            echo "     ❌ Failed to copy to $official_tmp_path\n";
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
                    $v_errs = $validator->getMessages('error');
                    if ($v_errs) print_r($v_errs);
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
