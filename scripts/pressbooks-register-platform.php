<?php
define('WP_USE_THEMES', false);
require_once('/var/www/pressbooks/web/wp/wp-load.php');

global $wpdb;

$issuer = $argv[1] ?? 'http://moodle.local';
$client_id = $argv[2] ?? '';
$deployment_id = $argv[3] ?? '1';

echo "=== Registering Platform in Pressbooks ===\n";
echo "Issuer: $issuer\n";
echo "Client ID: $client_id\n";
echo "Deployment ID: $deployment_id\n";

$table = $wpdb->prefix . 'lti_platforms';

// Check if platform exists
$existing = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table WHERE issuer = %s",
    $issuer
));

$data = [
    'issuer' => $issuer,
    'client_id' => $client_id,
    'auth_login_url' => $issuer . '/mod/lti/auth.php',
    'key_set_url' => $issuer . '/mod/lti/certs.php',
    'token_url' => $issuer . '/mod/lti/token.php',
    'created_at' => current_time('mysql')
];

if ($existing) {
    echo "✓ Platform already exists, updating all fields...\n";
    $wpdb->update($table, $data, ['id' => $existing->id]);
    if ($wpdb->last_error) {
        echo "✗ Update failed: " . $wpdb->last_error . "\n";
        exit(1);
    }
    echo "✓ Platform updated (ID: {$existing->id})\n";
} else {
    $result = $wpdb->insert($table, $data);
    if ($result) {
        echo "✓ Platform registered (ID: {$wpdb->insert_id})\n";
    } else {
        echo "✗ Failed to register platform: " . $wpdb->last_error . "\n";
        exit(1);
    }
}

// Upsert deployment — update deployment_id in case it changed
$dep_table = $wpdb->prefix . 'lti_deployments';
$dep_existing = $wpdb->get_row($wpdb->prepare(
    "SELECT id, deployment_id FROM $dep_table WHERE platform_issuer = %s",
    $issuer
));

if (!$dep_existing) {
    $wpdb->insert($dep_table, [
        'platform_issuer' => $issuer,
        'deployment_id'   => $deployment_id
    ]);
    echo "✓ Deployment $deployment_id registered\n";
} elseif ($dep_existing->deployment_id !== $deployment_id) {
    $wpdb->update($dep_table, ['deployment_id' => $deployment_id], ['id' => $dep_existing->id]);
    echo "✓ Deployment updated: {$dep_existing->deployment_id} → $deployment_id\n";
} else {
    echo "✓ Deployment $deployment_id already current\n";
}
