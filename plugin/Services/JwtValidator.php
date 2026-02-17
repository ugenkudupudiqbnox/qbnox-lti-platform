<?php
namespace PB_LTI\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class JwtValidator {
    public static function validate(string $jwt) {
        // Decode header and payload to extract issuer
        $parts = explode('.', $jwt);
        $payload = json_decode(JWT::urlsafeB64Decode($parts[1]));

        // Find platform by issuer
        $platform = PlatformRegistry::find($payload->iss);
        if (!$platform) {
            throw new \Exception('Unknown issuer: ' . $payload->iss);
        }

        // Validate audience
        if (!in_array($platform->client_id, (array)$payload->aud, true)) {
            throw new \Exception('Invalid audience');
        }

        // Fetch JWKS and parse keys
        $jwks_json = @file_get_contents($platform->key_set_url);
        if ($jwks_json === false) {
            $error = error_get_last();
            throw new \Exception('Failed to fetch JWKS from ' . $platform->key_set_url . ': ' . ($error['message'] ?? 'Unknown error'));
        }

        $jwks = json_decode($jwks_json, true);
        
        if (!isset($jwks['keys'])) {
            throw new \Exception('Invalid JWKS format: "keys" property missing in ' . $platform->key_set_url);
        }

        // Parse the key set and decode JWT
        $keys = JWK::parseKeySet($jwks);
        
        // Decode and validate JWT
        return JWT::decode($jwt, $keys);
    }
}
