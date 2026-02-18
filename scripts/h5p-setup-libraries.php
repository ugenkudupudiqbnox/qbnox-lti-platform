<?php
/**
 * H5P Library and Content Setup Script
 */

if (!defined('ABSPATH')) {
    exit;
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

    foreach ($files as $file) {
        $filename = basename($file);
        if (strpos($filename, 'full') !== false) continue;
        
        echo "   - Importing $filename...\n";
        
        // Use a random temp name
        $upload_dir = wp_upload_dir();
        $h5p_tmp_dir = $upload_dir['basedir'] . '/h5p/temp';
        @mkdir($h5p_tmp_dir, 0755, true);
        
        $dest_file = $h5p_tmp_dir . '/' . uniqid('h5p_') . '.h5p';
        copy($file, $dest_file);

        $reflStorage = new ReflectionClass(get_class($storage));
        if ($reflStorage->hasProperty('uploaded_path')) {
            $prop = $reflStorage->getProperty('uploaded_path');
            $prop->setAccessible(true);
            $prop->setValue($storage, $dest_file);
        }

        try {
            if ($validator->isValidPackage(true, false)) {
                $id = $storage->savePackage();
                echo "     ✅ Imported (ID: $id)\n";
                
                $title = !empty($storage->content['title']) ? $storage->content['title'] : $filename;
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
            } else {
                echo "     ❌ Validation FAILED for $filename\n";
                $errors = $h5p->get_h5p_instance('interface')->getMessages('error');
                if ($errors) {
                    foreach ($errors as $error) {
                         echo "        - " . $error->message . "\n";
                    }
                }
            }
        } catch (Exception $e) {
            echo "     💥 Error: " . $e->getMessage() . "\n";
        }
        
        // Clean up
        if (file_exists($dest_file)) {
            @unlink($dest_file);
        }
    }
}

echo "✅ H5P setup and import completed.\n";
