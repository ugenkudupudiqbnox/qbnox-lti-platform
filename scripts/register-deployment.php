<?php
define('WP_USE_THEMES', false);
require('/var/www/pressbooks/web/wp/wp-load.php');

global $wpdb;

$platform_issuer = getenv('MOODLE_URL') ?: 'http://moodle.local:8080';
$deployment_id = '1';

echo "=== Registering Deployment ===\n";

// Check if deployment already exists
$existing = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}lti_deployments WHERE platform_issuer = %s AND deployment_id = %s",
    $platform_issuer,
    $deployment_id
));

if ($existing) {
    echo "✓ Deployment already exists (ID: {$existing->id})\n";
} else {
    $result = $wpdb->insert(
        $wpdb->prefix . 'lti_deployments',
        array(
            'platform_issuer' => $platform_issuer,
            'deployment_id' => $deployment_id,
            'created_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s')
    );
    
    if ($result) {
        echo "✓ Deployment registered successfully (ID: {$wpdb->insert_id})\n";
    } else {
        echo "✗ Failed to register deployment\n";
        echo "Error: " . $wpdb->last_error . "\n";
    }
}

echo "\n=== All Deployments ===\n";
$deployments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}lti_deployments");
foreach ($deployments as $dep) {
    echo "ID: {$dep->id}, Platform: {$dep->platform_issuer}, Deployment: {$dep->deployment_id}\n";
}
