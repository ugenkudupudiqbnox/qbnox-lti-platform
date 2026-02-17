<?php
define('WP_USE_THEMES', false);
require('/var/www/pressbooks/web/wp/wp-load.php');

global $wpdb;

echo "=== Generating RSA Key Pair ===\n";

// Generate RSA key pair
$config = array(
    "digest_alg" => "sha256",
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
);

$res = openssl_pkey_new($config);
openssl_pkey_export($res, $private_key);
$public_key_details = openssl_pkey_get_details($res);
$public_key = $public_key_details['key'];

echo "✓ Keys generated\n";

// Store in database
$kid = 'pb-lti-2024';

$existing = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}lti_keys WHERE kid = %s",
    $kid
));

if ($existing) {
    $wpdb->update(
        $wpdb->prefix . 'lti_keys',
        array(
            'private_key' => $private_key,
            'public_key' => $public_key
        ),
        array('kid' => $kid),
        array('%s', '%s'),
        array('%s')
    );
    echo "✓ Updated existing key\n";
} else {
    $wpdb->insert(
        $wpdb->prefix . 'lti_keys',
        array(
            'kid' => $kid,
            'private_key' => $private_key,
            'public_key' => $public_key,
            'created_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s')
    );
    echo "✓ Stored new key (ID: {$wpdb->insert_id})\n";
}

// Extract modulus and exponent for JWKS
$n = base64_encode($public_key_details['rsa']['n']);
$e = base64_encode($public_key_details['rsa']['e']);

echo "\n=== JWKS Components ===\n";
echo "kid: $kid\n";
echo "n (first 50 chars): " . substr($n, 0, 50) . "...\n";
echo "e: $e\n";

echo "\n✓ Keys ready for Deep Linking!\n";
