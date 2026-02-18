<?php
/**
 * H5P Library and Content Setup Script
 */

if (!defined('ABSPATH')) {
    exit;
}

// 0. Mock a super admin user for CLI context to bypass H5P permission checks
if (php_sapi_name() == 'cli') {
    $admins = get_super_admins();
    if (!empty($admins)) {
        $user = get_user_by('login', reset($admins));
        if ($user) {
            wp_set_current_user($user->ID);
        }
    } else {
        // Fallback to User ID 1
        wp_set_current_user(1);
    }
}

// 1. Loop through ALL blogs and set the options 
if (is_multisite()) {
    $blogs = get_sites();
    foreach ($blogs as $blog) {
        switch_to_blog($blog->blog_id);
        echo "🛠️ Configuring H5P options for blog {$blog->blog_id}...\n";
        
        update_option('h5p_hub_is_enabled', 1);
        update_option('h5p_upload_libraries', 1);
        update_option('h5p_track_user', 1);
        update_option('h5p_has_request_user_consent', 1);
        update_option('h5p_send_usage_statistics', 1);
        update_option('h5p_library_updates_disabled', 0);
        update_option('h5p_save_content_state', 1);
        update_option('h5p_save_content_frequency', 30);
        
        if ($blog->blog_id == 1) {
            update_option('h5p_multisite_capabilities', 1);
        }
        
        restore_current_blog();
    }
}

// Ensure we're in the Site 2 context
if (function_exists('switch_to_blog')) {
    switch_to_blog(2);
}

// Ensure Editor classes are available
if (!class_exists('H5PEditor')) {
    $plugin_path = WP_PLUGIN_DIR . '/h5p/';
    if (file_exists($plugin_path . 'h5p-editor-php-library/h5peditor.class.php')) {
        require_once $plugin_path . 'h5p-editor-php-library/h5peditor.class.php';
        require_once $plugin_path . 'h5p-editor-php-library/h5peditor-ajax.class.php';
        require_once $plugin_path . 'h5p-editor-php-library/h5peditor-ajax.interface.php';
        require_once $plugin_path . 'h5p-editor-php-library/h5peditor-file.class.php';
        require_once $plugin_path . 'h5p-editor-php-library/h5peditor-storage.interface.php';
        require_once $plugin_path . 'admin/class-h5p-editor-wordpress-ajax.php';
        require_once $plugin_path . 'admin/class-h5p-editor-wordpress-storage.php';
    }
}

$h5p = H5P_Plugin::get_instance();
$core = $h5p->get_h5p_instance('core');
$storage = $h5p->get_h5p_instance('storage');
$validator = $h5p->get_h5p_instance('validator');
$interface = $h5p->get_h5p_instance('interface');

// 1. Force refresh Hub content cache
echo "🔄 Updating Hub content type cache...\n";
$core->updateContentTypeCache();

// Wait a second for it to settle?
sleep(2);

// 2. Pre-install H5P.ArithmeticQuiz
echo "📥 Attempting to pre-download H5P.ArithmeticQuiz from Hub...\n";
try {
    $machineName = 'H5P.ArithmeticQuiz';
    if (!$core->h5pF->getLibraryId($machineName)) {
        // Double check hub cache
        $wp_ajax = new H5PEditorWordPressAjax();
        $ct = $wp_ajax->getContentTypeCache($machineName);
        if ($ct) {
            echo "   - Hub item found, triggering install...\n";
            $editor_storage = new H5PEditorWordPressStorage();
            $editor = new H5PEditor($core, $editor_storage, $wp_ajax);
            $ajax = $editor->ajax; 
            
            ob_start(); // Trap H5PCore::ajaxError output
            $reflAjax = new ReflectionClass('H5PEditorAjax');
            $method = $reflAjax->getMethod('libraryInstall');
            $method->setAccessible(true);
            $method->invoke($ajax, $machineName);
            $result = ob_get_clean();
            
            if (strpos($result, '"success":true') !== false) {
                 echo "     ✅ Library $machineName installed.\n";
            } else {
                 echo "     ❌ Library $machineName install failed: $result\n";
            }
        } else {
            echo "   ⚠️ Content type $machineName not found in Hub cache. Check internet connection.\n";
        }
    } else {
        echo "   - $machineName already present.\n";
    }
} catch (Exception $e) {
    echo "   ⚠️ Library pre-install failed: " . $e->getMessage() . "\n";
}

// 3. Import artifacts
$import_dir = '/tmp/h5p_imports';
if (is_dir($import_dir)) {
    $files = glob($import_dir . '/*.h5p');
    echo "📦 Found " . count($files) . " package(s) for import.\n";

    // Sort to process 'full' packages first (which contain libraries)
    usort($files, function($a, $b) {
        $aFull = (strpos($a, 'full') !== false);
        $bFull = (strpos($b, 'full') !== false);
        if ($aFull && !$bFull) return -1;
        if (!$aFull && $bFull) return 1;
        return 0;
    });

    foreach ($files as $file) {
        $filename = basename($file);
        if (strpos($filename, 'full') !== false && filesize($file) < 1000) {
             echo "   - Skipping $filename (too small, likely corrupt/redirect).\n";
             continue;
        }

        $isFull = (strpos($filename, 'full') !== false);
        echo "   - Importing $filename... " . ($isFull ? "(potential library source)" : "") . "\n";
        
        // Use the OFFICIAL temp path from the H5P interface
        $official_tmp_path = $interface->getUploadedH5pPath();
        @mkdir(dirname($official_tmp_path), 0755, true);
        
        if (!copy($file, $official_tmp_path)) {
             echo "     ❌ Error: Could not copy $file to $official_tmp_path\n";
             continue;
        }

        // Fresh objects
        $validator = $h5p->get_h5p_instance('validator');
        $storage = $h5p->get_h5p_instance('storage');

        try {
            if ($validator->isValidPackage($isFull, false)) {
                // Manually prepare content array with metadata to satisfy H5PWordPress::updateContent requirements
                $title = !empty($core->mainJsonData['title']) ? $core->mainJsonData['title'] : str_replace('.h5p', '', $filename);
                $content_data = array(
                    'metadata' => (object) array(
                        'title' => $title
                    ),
                    'uploaded' => true,
                    'disable' => 0
                );

                $id = $storage->savePackage($content_data, NULL, $isFull);
                
                if ($id) {
                    echo "     ✅ Imported " . ($isFull ? "Libraries" : "Content") . " (ID: $id)\n";
                    $c_title = "H5P: " . $title;
                    if (!get_page_by_title($c_title, OBJECT, 'chapter')) {
                        $pid = wp_insert_post([
                            'post_title' => $c_title,
                            'post_content' => "[h5p id=\"$id\"]\n\nActivity: $title",
                            'post_status' => 'publish',
                            'post_type' => 'chapter',
                            'post_author' => 1
                        ]);
                        if ($pid && !is_wp_error($pid)) {
                            echo "     📖 Created chapter $pid for activity.\n";
                        }
                    }
                } else if ($isFull) {
                    echo "     ✅ Libraries imported from full package (No Content created).\n";
                } else {
                    echo "     ❌ Error: storage->savePackage() failed to return an ID.\n";
                }
            } else {
                echo "     ❌ Validation FAILED for $filename\n";
                $messages = $h5p->get_h5p_instance('interface')->getMessages('error');
                if ($messages) {
                    foreach ($messages as $msg) {
                         echo "        - " . (is_object($msg) ? $msg->message : $msg) . "\n";
                    }
                }
            }
        } catch (Exception $e) {
            echo "     💥 Error: " . $e->getMessage() . "\n";
        }
        
        // Clean up 
        if (file_exists($official_tmp_path)) {
            @unlink($official_tmp_path);
        }
    }
}

echo "✅ H5P setup and import completed.\n";
