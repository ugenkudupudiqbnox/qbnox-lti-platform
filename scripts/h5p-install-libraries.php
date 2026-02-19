<?php
/**
 * Install H5P libraries on site 2 (test-book) from a pre-downloaded .h5p file.
 *
 * How it works:
 *   The ArithmeticQuiz .h5p package bundles ~29 libraries including itself.
 *   Installing it as CONTENT (isLibrary=FALSE) registers all bundled libraries
 *   AND creates the content item — one step, no Hub required.
 *
 * Run: docker exec pressbooks php /tmp/h5p-install-libraries.php
 */
define('WP_USE_THEMES', false);
require_once('/var/www/pressbooks/web/wp/wp-load.php');

$source_h5p = '/tmp/H5P.ArithmeticQuiz.h5p';

if (!file_exists($source_h5p)) {
    echo "❌ File not found: $source_h5p\n";
    echo "   Download it first with:\n";
    echo "   curl -sL https://api.h5p.org/v1/content-types/H5P.ArithmeticQuiz -o /tmp/H5P.ArithmeticQuiz.h5p\n";
    exit(1);
}

echo "📦 H5P library installer\n";
echo "File: $source_h5p (" . number_format(filesize($source_h5p)) . " bytes)\n";

function install_h5p_on_current_site($source_h5p) {
    global $wpdb;
    $site_id = get_current_blog_id();
    echo "\n=== Processing Site $site_id (" . get_bloginfo('url') . ") ===\n";

    // Idempotency check — skip if ArithmeticQuiz is already present
    $already = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}h5p_libraries WHERE machine_name = 'H5P.ArithmeticQuiz'"
    );
    if ($already > 0) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}h5p_libraries");
        echo "✅ H5P.ArithmeticQuiz already installed ($count libraries total). Nothing to do.\n";
        return;
    }

    $h5p       = H5P_Plugin::get_instance();
    $core      = $h5p->get_h5p_instance('core');
    $interface = $h5p->get_h5p_instance('interface');
    $validator = $h5p->get_h5p_instance('validator');

    // Allow overwriting older library versions during install
    $core->mayUpdateLibraries(TRUE);

    // Place the file where H5P expects the upload (static path, generated once per request)
    $expected_path = $interface->getUploadedH5pPath();
    wp_mkdir_p(dirname($expected_path));

    if (!copy($source_h5p, $expected_path)) {
        echo "❌ Could not copy file to: $expected_path\n";
        return;
    }
    echo "Staged at: $expected_path\n";

    // Validate and save as CONTENT (isLibrary=FALSE) — this installs all bundled libraries
    // and creates a content record. It is the same path taken by a manual WP Admin upload.
    if ($validator->isValidPackage(FALSE, FALSE)) {
        echo "✅ Package valid — installing libraries + content...\n";
        $storage    = new H5PStorage($core->h5pF, $core);
        $content_id = $storage->savePackage(['disable' => 0], NULL, FALSE);
        echo "Content ID: $content_id\n";
        foreach ((array)$interface->getMessages('info')  as $m) echo "ℹ️  $m\n";
        foreach ((array)$interface->getMessages('error') as $m) echo "⚠️  $m\n";
    } else {
        echo "❌ Validation failed for site $site_id:\n";
        foreach ((array)$interface->getMessages('error') as $e) echo "   - $e\n";
    }
    
    // Summary
    $libs = $wpdb->get_results(
        "SELECT machine_name, major_version, minor_version FROM {$wpdb->prefix}h5p_libraries WHERE machine_name = 'H5P.ArithmeticQuiz' OR library_id IN (SELECT library_id FROM {$wpdb->prefix}h5p_libraries_languages)"
    );
    echo "H5P.ArithmeticQuiz version check: \n";
    foreach ($libs as $l) {
        if ($l->machine_name === 'H5P.ArithmeticQuiz') {
            echo "  ✅ {$l->machine_name} {$l->major_version}.{$l->minor_version}\n";
        }
    }
}

// Loop through sites
if (is_multisite()) {
    $sites = get_sites(['number' => 100]);
    foreach ($sites as $site) {
        $blog_id = $site->blog_id;
        switch_to_blog($blog_id);
        install_h5p_on_current_site($source_h5p);
        restore_current_blog();
    }
} else {
    install_h5p_on_current_site($source_h5p);
}

echo "\n✅ H5P multi-site library installation complete.\n";
