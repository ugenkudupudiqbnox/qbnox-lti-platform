<?php
namespace PB_LTI\Services;

use GuzzleHttp\Client;
use Firebase\JWT\JWT;

class AGSClient {

    /**
     * Post score to Moodle gradebook via AGS
     *
     * @param object $platform Platform configuration
     * @param string $lineitem_url AGS lineitem URL
     * @param int $user_id WordPress user ID
     * @param float $score Score given (0-100)
     * @param float $max_score Maximum score (default 100)
     * @param string $activity_progress Activity progress status
     * @param string $grading_progress Grading progress status
     * @return array Result array with success status
     */
    public static function post_score($platform, $lineitem_url, $user_id, $score, $max_score = 100, $activity_progress = 'Completed', $grading_progress = 'FullyGraded') {
        try {
            // Get OAuth2 token
            $token = TokenCache::get($platform->issuer);
            if (!$token) {
                $token = self::fetch_token($platform);
            }

            // Parse URL and add /scores to path (before query string)
            $url_parts = parse_url($lineitem_url);
            $scores_url = $url_parts['scheme'] . '://' . $url_parts['host'];
            if (isset($url_parts['port'])) {
                $scores_url .= ':' . $url_parts['port'];
            }
            $scores_url .= $url_parts['path'] . '/scores';
            if (isset($url_parts['query'])) {
                $scores_url .= '?' . $url_parts['query'];
            }

            // Post score to AGS endpoint
            $client = new Client();
            $response = $client->post($scores_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/vnd.ims.lis.v1.score+json'
                ],
                'json' => [
                    'userId' => (string)$user_id,
                    'scoreGiven' => (float)$score,
                    'scoreMaximum' => (float)$max_score,
                    'activityProgress' => $activity_progress,
                    'gradingProgress' => $grading_progress,
                    'timestamp' => date('c')
                ]
            ]);

            return ['success' => true, 'status' => $response->getStatusCode()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function send_score(string $lineitem_url, float $score, string $user_id, $platform, array $allowed_scopes) {
        self::enforce_scope($allowed_scopes, 'https://purl.imsglobal.org/spec/lti-ags/scope/score');

        $token = TokenCache::get($platform->issuer);
        if (!$token) {
            $token = self::fetch_token($platform);
        }

        $client = new Client();
        $client->post($lineitem_url . '/scores', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/vnd.ims.lis.v1.score+json'
            ],
            'json' => [
                'userId' => $user_id,
                'scoreGiven' => $score,
                'scoreMaximum' => 100,
                'activityProgress' => 'Completed',
                'gradingProgress' => 'FullyGraded'
            ]
        ]);
    }

    /**
     * Fetch OAuth2 access token using JWT client assertion (RFC 7523)
     * Required for LTI 1.3 Advantage token endpoint
     */
    private static function fetch_token($platform): string {
        global $wpdb;

        // Get tool's private key for signing JWT client assertion
        $key_row = $wpdb->get_row($wpdb->prepare(
            "SELECT private_key FROM {$wpdb->base_prefix}lti_keys WHERE kid = %s",
            'pb-lti-2024'
        ));

        if (!$key_row) {
            throw new \Exception('Private key not found for JWT signing');
        }

        // Create JWT client assertion
        // Per RFC 7523 and LTI 1.3 Security spec
        $jwt_payload = [
            'iss' => $platform->client_id,  // Issuer: tool's client ID
            'sub' => $platform->client_id,  // Subject: tool's client ID
            'aud' => $platform->auth_token_url,  // Audience: token endpoint
            'iat' => time(),
            'exp' => time() + 60,  // Valid for 60 seconds
            'jti' => bin2hex(random_bytes(16))  // Unique token ID
        ];

        // Sign JWT with tool's private key
        $client_assertion = \Firebase\JWT\JWT::encode(
            $jwt_payload,
            $key_row->private_key,
            'RS256',
            'pb-lti-2024'
        );

        // Request access token using JWT client assertion
        $client = new Client();
        $res = $client->post($platform->auth_token_url, [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion' => $client_assertion,
                'scope' => 'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem.readonly https://purl.imsglobal.org/spec/lti-ags/scope/score'
            ]
        ]);

        $data = json_decode($res->getBody(), true);

        if (!isset($data['access_token'])) {
            throw new \Exception('No access token in response');
        }

        // Cache the token
        TokenCache::set($platform->issuer, $data['access_token'], $data['expires_in'] ?? 3600);

        return $data['access_token'];
    }

    /**
     * Fetch lineitem details from Moodle
     *
     * @param object $platform Platform configuration
     * @param string $lineitem_url AGS lineitem URL
     * @return array|null Lineitem details or null on failure
     */
    public static function fetch_lineitem($platform, $lineitem_url) {
        try {
            // Get OAuth2 token
            $token = TokenCache::get($platform->issuer);
            if (!$token) {
                $token = self::fetch_token($platform);
            }

            // Fetch lineitem details
            $client = new Client();
            $response = $client->get($lineitem_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/vnd.ims.lis.v2.lineitem+json'
                ]
            ]);

            $lineitem = json_decode($response->getBody(), true);
            error_log('[PB-LTI AGS] Fetched lineitem: ' . json_encode($lineitem));

            return $lineitem;
        } catch (\Exception $e) {
            error_log('[PB-LTI AGS] Failed to fetch lineitem: ' . $e->getMessage());
            return null;
        }
    }

    private static function enforce_scope(array $scopes, string $required): void {
        if (!in_array($required, $scopes, true)) {
            throw new \Exception('Required AGS scope not granted');
        }
    }
}
