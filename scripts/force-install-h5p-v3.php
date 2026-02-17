<?php
/**
 * Script to force-install H5P Arithmetic Quiz library on all sites.
 * This script impersonates an admin and uses the H5P storage engine.
 */

// Load WordPress multisite environment if not loaded
require_once(ABSPATH . 'wp-admin/includes/user.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');

$file = '/tmp/arithmetic.h5p';
if (!file_exists($file)) {
    die("Error: $file not found in container.\n");
}

function install_h5p_on_current_site($file) {
    global $wpdb;
    $site_id = get_current_blog_id();
    echo "Processing site $site_id (" . get_bloginfo('url') . ")\n";

    $plugin = H5P_Plugin::get_instance();
    $interface = $plugin->get_h5p_instance('interface');
    $validator = $plugin->get_h5p_instance('validator');
    $storage = $plugin->get_h5p_instance('storage');

    // Ensure we are super admin
    wp_set_current_user(1);
    
    $temp_file = $interface->getUploadedH5pPath();
    $temp_dir = $interface->getUploadedH5pFolderPath();
    
    if (!is_dir(dirname($temp_file))) {
        mkdir(dirname($temp_file), 0777, true);
    }
    
    copy($file, $temp_file);
    
    echo "Validating package...\n";
    if ($validator->isValidPackage(false, false)) {
        echo "Package valid. Saving package content...\n";
        // This will install all missing libraries from the package
        $contentId = $storage->savePackage(null, null, false);
        if ($contentId) {
            echo "SUCCESS: Installed on site $site_id. Content ID: $contentId\n";
            
            // Log libraries found in DB now
            $res = $wpdb->get_results("SELECT name, major_version FROM {$wpdb->prefix}h5p_libraries WHERE name = 'H5P.ArithmeticQuiz'");
            if (!empty($res)) {
                echo "- H5P.ArithmeticQuiz found in DB.\n";
            } else {
                echo "- ERROR: H5P.ArithmeticQuiz STILL MISSING from DB for site $site_id.\n";
            }
        } else {
            echo "FAILURE: Could not save package on site $site_id. Errors: ";
            print_r($interface->getMessages('error'));
        }
    } else {
        echo "FAILURE: Validation failed on site $site_id. Errors: ";
        print_r($interface->getMessages('error'));
    }
    
    // Clean up temp dir
    if (is_dir($temp_dir)) {
        H5PCore::deleteFileTree($temp_dir);
    }
}

// Loop through sites
if (is_multisite()) {
    $sites = get_sites(['number' => 100]);
    foreach ($sites as $site) {
        $blog_id = $site->blog_id;
        switch_to_blog($blog_id);
        install_h5p_on_current_site($file);
        restore_current_blog();
    }
} else {
    install_h5p_on_current_site($file);
}

