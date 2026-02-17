<?php
define('WP_USE_THEMES', false);
require_once('/var/www/pressbooks/web/wp/wp-load.php');

global $wpdb;
$tables = [
    $wpdb->prefix . 'lti_platforms',
    $wpdb->prefix . 'lti_deployments',
    $wpdb->prefix . 'lti_lineitems',
    $wpdb->prefix . 'lti_keys',
    $wpdb->prefix . 'lti_audit'
];

foreach ($tables as $table) {
    echo "Dropping $table...\n";
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

echo "Running migrations...\n";
require_once(PB_LTI_PATH . 'db/schema.php');
require_once(PB_LTI_PATH . 'db/migrate.php');
pb_lti_run_migrations();

echo "✅ Done\n";
