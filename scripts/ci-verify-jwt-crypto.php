
<?php
// Full JWT signature verification using JWKS + OpenSSL

$jwt = $argv[1] ?? null;
$jwksUrl = getenv('JWKS_URL') ?: (getenv('PRESSBOOKS_URL') ?: 'https://pressbooks.local') . '/wp-json/pb-lti/v1/keyset';

if (!$jwt) {
    fwrite(STDERR, "JWT missing\n");
    exit(1);
}

list($header64, $payload64, $sig64) = explode('.', $jwt);

$header = json_decode(base64_decode(strtr($header64, '-_', '+/')), true);
$sig = base64_decode(strtr($sig64, '-_', '+/'));
$data = $header64 . '.' . $payload64;

$jwks = json_decode(file_get_contents($jwksUrl), true);
$kid = $header['kid'] ?? null;

$key = null;
foreach ($jwks['keys'] as $jwk) {
    if ($jwk['kid'] === $kid) {
        $key = $jwk;
        break;
    }
}

if (!$key) {
    fwrite(STDERR, "Signing key not found\n");
    exit(1);
}

// Build PEM key
$mod = base64_decode(strtr($key['n'], '-_', '+/'));
$exp = base64_decode(strtr($key['e'], '-_', '+/'));
$pubkey = openssl_pkey_get_details(openssl_pkey_new([
    'rsa' => [
        'n' => $mod,
        'e' => $exp
    ]
]));

$verified = openssl_verify($data, $sig, $pubkey['key'], OPENSSL_ALGO_SHA256);

if ($verified !== 1) {
    fwrite(STDERR, "JWT signature invalid\n");
    exit(1);
}

echo "JWT signature cryptographically valid\n";
