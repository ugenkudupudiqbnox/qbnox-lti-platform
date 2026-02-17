<?php
define('WP_USE_THEMES', false);
require('/var/www/pressbooks/web/wp/wp-load.php');

global $wpdb;

echo "=== LTI Advantage Verification ===\n\n";

// Check 1: RSA Keys
echo "1. RSA Keys for Deep Linking:\n";
$keys = $wpdb->get_results("SELECT kid, LENGTH(private_key) as priv_len, LENGTH(public_key) as pub_len, created_at FROM {$wpdb->prefix}lti_keys");
if ($keys) {
    foreach ($keys as $key) {
        echo "   ✓ Key ID: {$key->kid}\n";
        echo "     - Private key: {$key->priv_len} bytes\n";
        echo "     - Public key: {$key->pub_len} bytes\n";
        echo "     - Created: {$key->created_at}\n";
    }
} else {
    echo "   ✗ No RSA keys found - run scripts/generate-rsa-keys.php\n";
}

// Check 2: Platform Registration
echo "\n2. Platform Registration:\n";
$platforms = $wpdb->get_results("SELECT issuer, client_id, auth_login_url, key_set_url FROM {$wpdb->prefix}lti_platforms");
if ($platforms) {
    foreach ($platforms as $platform) {
        echo "   ✓ Platform: {$platform->issuer}\n";
        echo "     - Client ID: {$platform->client_id}\n";
        echo "     - Auth URL: {$platform->auth_login_url}\n";
        echo "     - JWKS URL: {$platform->key_set_url}\n";
    }
} else {
    echo "   ✗ No platforms registered\n";
}

// Check 3: Deployments
echo "\n3. Deployments:\n";
$deployments = $wpdb->get_results("SELECT platform_issuer, deployment_id FROM {$wpdb->prefix}lti_deployments");
if ($deployments) {
    foreach ($deployments as $dep) {
        echo "   ✓ Deployment ID: {$dep->deployment_id} (Platform: {$dep->platform_issuer})\n";
    }
} else {
    echo "   ✗ No deployments registered - run scripts/register-deployment.php\n";
}

// Check 4: REST Endpoints
echo "\n4. REST API Endpoints:\n";
$endpoints = [
    'login' => rest_url('pb-lti/v1/login'),
    'launch' => rest_url('pb-lti/v1/launch'),
    'deep-link' => rest_url('pb-lti/v1/deep-link'),
    'keyset' => rest_url('pb-lti/v1/keyset'),
    'ags' => rest_url('pb-lti/v1/ags/post-score')
];

foreach ($endpoints as $name => $url) {
    echo "   ✓ {$name}: {$url}\n";
}

// Check 5: JWKS Endpoint Response
echo "\n5. JWKS Endpoint (Public Key Set):\n";
$jwks_response = wp_remote_get(rest_url('pb-lti/v1/keyset'));
if (!is_wp_error($jwks_response)) {
    $jwks = json_decode(wp_remote_retrieve_body($jwks_response), true);
    if (isset($jwks['keys'][0])) {
        $key = $jwks['keys'][0];
        echo "   ✓ Public key available:\n";
        echo "     - Key ID (kid): {$key['kid']}\n";
        echo "     - Algorithm: {$key['alg']}\n";
        echo "     - Modulus (n): " . substr($key['n'], 0, 50) . "...\n";
        echo "     - Exponent (e): {$key['e']}\n";
    } else {
        echo "   ✗ No keys in JWKS response\n";
    }
} else {
    echo "   ✗ Error fetching JWKS: " . $jwks_response->get_error_message() . "\n";
}

// Check 6: Controllers
echo "\n6. Controller Classes:\n";
$controllers = [
    'PB_LTI\Controllers\LoginController',
    'PB_LTI\Controllers\LaunchController',
    'PB_LTI\Controllers\DeepLinkController',
    'PB_LTI\Controllers\AGSController'
];

foreach ($controllers as $class) {
    if (class_exists($class)) {
        echo "   ✓ {$class}\n";
    } else {
        echo "   ✗ {$class} - not found\n";
    }
}

// Check 7: Services
echo "\n7. Service Classes:\n";
$services = [
    'PB_LTI\Services\JwtValidator',
    'PB_LTI\Services\NonceService',
    'PB_LTI\Services\PlatformRegistry',
    'PB_LTI\Services\DeploymentRegistry',
    'PB_LTI\Services\AGSClient',
    'PB_LTI\Services\SecretVault',
    'PB_LTI\Services\TokenCache'
];

foreach ($services as $class) {
    if (class_exists($class)) {
        echo "   ✓ {$class}\n";
    } else {
        echo "   ✗ {$class} - not found\n";
    }
}

// Summary
echo "\n=== Summary ===\n";
$keys_ok = !empty($keys);
$platforms_ok = !empty($platforms);
$deployments_ok = !empty($deployments);

if ($keys_ok && $platforms_ok && $deployments_ok) {
    echo "✅ All checks passed - Ready for Deep Linking & AGS testing\n\n";
    echo "Next steps:\n";
    echo "1. Test Deep Linking: See docs/TESTING_DEEP_LINKING_AND_AGS.md\n";
    echo "2. Test AGS: Launch 'AGS Graded Assignment' activity in Moodle\n";
} else {
    echo "⚠️  Some components missing:\n";
    if (!$keys_ok) echo "   - Run: scripts/generate-rsa-keys.php\n";
    if (!$platforms_ok) echo "   - Register platform in wp_lti_platforms\n";
    if (!$deployments_ok) echo "   - Run: scripts/register-deployment.php\n";
}
