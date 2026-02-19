<?php
namespace PB_LTI\Services;

/**
 * H5PMultisiteSetup
 *
 * Ensures H5P is correctly configured and contains necessary libraries 
 * for every new book/site created in the Pressbooks network.
 */
class H5PMultisiteSetup {

    /**
     * Initialize multisite hooks
     */
    public static function init() {
        // Run on site initialization (WP 5.1+)
        add_action('wp_initialize_site', [__CLASS__, 'on_new_site_created']);
        
        // Fallback for older WP versions or different site creation paths
        add_action('wpmu_new_blog', [__CLASS__, 'on_new_site_created_legacy'], 10, 1);
    }

    /**
     * Configure H5P for a newly created site
     *
     * @param \WP_Site $site
     */
    public static function on_new_site_created($site) {
        $blog_id = $site->blog_id;
        self::setup_site($blog_id);
    }
    
    /**
     * Legacy hook handler
     */
    public static function on_new_site_created_legacy($blog_id) {
        self::setup_site($blog_id);
    }

    /**
     * Perform the actual setup for a specific site
     */
    private static function setup_site($blog_id) {
        switch_to_blog($blog_id);

        error_log("[PB-LTI] Initializing H5P for new blog $blog_id");

        // 1. Force H5P table creation if they don't exist for this blog
        if (class_exists('H5P_Plugin')) {
            \H5P_Plugin::update_database();
        }

        // 2. Set critical H5P options for the new blog
        update_option('h5p_hub_is_enabled', 1);
        update_option('h5p_upload_libraries', 1);
        update_option('h5p_track_user', 1);
        update_option('h5p_has_request_user_consent', 1);
        update_option('h5p_library_updates_disabled', 0);
        update_option('h5p_save_content_state', 1);

        // 2. Pre-install ArithmeticQuiz if it's missing
        // This ensures the main library is present for any LTI test content
        self::ensure_main_libraries_installed();

        restore_current_blog();
    }

    /**
     * Installs the required H5P libraries if they don't exist in the current site
     */
    private static function ensure_main_libraries_installed() {
        if (!class_exists('H5P_Plugin')) {
            return;
        }

        global $wpdb;
        $has_arithmetic = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$wpdb->prefix}h5p_libraries WHERE machine_name = %s LIMIT 1", 'H5P.ArithmeticQuiz')
        );

        if (!$has_arithmetic) {
            $blog_id = get_current_blog_id();
            error_log("[PB-LTI] ArithmeticQuiz missing from site $blog_id. Attempting install...");

            // Logic from h5p-install-libraries.php - prioritize local package for reliability in lab
            $local_package = '/tmp/H5P.ArithmeticQuiz.h5p';
            if (file_exists($local_package)) {
                self::install_from_file($local_package);
            } else {
                self::install_from_hub();
            }
        }
    }

    /**
     * Install H5P package from a local file path
     */
    private static function install_from_file($file_path) {
        try {
            $h5p       = \H5P_Plugin::get_instance();
            $core      = $h5p->get_h5p_instance('core');
            $interface = $h5p->get_h5p_instance('interface');
            $validator = $h5p->get_h5p_instance('validator');

            $core->mayUpdateLibraries(TRUE);

            // Mock the upload
            $path = $interface->getUploadedH5pPath();
            @copy($file_path, $path);

            if ($validator->isValidPackage(true, false)) {
                $storage = $h5p->get_h5p_instance('storage');
                $storage->savePackage(null, null, true);
                error_log("[PB-LTI] Successfully installed ArithmeticQuiz from local package for site " . get_current_blog_id());
            } else {
                error_log("[PB-LTI] Local H5P package validation failed for site " . get_current_blog_id());
            }
            
            @unlink($path);
        } catch (\Exception $e) {
            error_log("[PB-LTI] Error during local H5P install: " . $e->getMessage());
        }
    }

    /**
     * Fallback to Hub install if local package is missing
     */
    private static function install_from_hub() {
        try {
            $plugin = \H5P_Plugin::get_instance();
            $core = $plugin->get_h5p_instance('core');
            $core->updateContentTypeCache();
            
            if (class_exists('H5P_Plugin_Admin')) {
                $admin = \H5P_Plugin_Admin::get_instance();
                $refl_admin = new \ReflectionMethod('H5P_Plugin_Admin', 'get_h5peditor_instance');
                $refl_admin->setAccessible(true);
                $editor = $refl_admin->invoke($admin);

                if ($editor && isset($editor->ajax)) {
                    $refl = new \ReflectionMethod('H5PEditorAjax', 'libraryInstall');
                    $refl->setAccessible(true);
                    ob_start();
                    $refl->invoke($editor->ajax, 'H5P.ArithmeticQuiz', 1, 1);
                    ob_end_clean();
                    error_log("[PB-LTI] Successfully triggered ArithmeticQuiz install on Hub for site " . get_current_blog_id());
                }
            }
        } catch (\Exception $e) {
            error_log("[PB-LTI] Warning: Automatic H5P library installation from Hub failed: " . $e->getMessage());
        }
    }
}
