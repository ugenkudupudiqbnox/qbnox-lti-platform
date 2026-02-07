<?php
namespace PB_LTI\Controllers;

use Firebase\JWT\JWT;
use PB_LTI\Services\ContentService;

class DeepLinkController {

    /**
     * Handle Deep Linking request from REST API
     *
     * GET request: Show content picker UI
     * POST request: Process selection and return signed JWT
     */
    public static function handle($request) {
        $method = $request->get_method();

        if ($method === 'POST' && $request->get_param('selected_book_id')) {
            // Process selection and return JWT
            return self::process_selection($request);
        }

        // Show content picker UI
        return self::show_content_picker($request);
    }

    /**
     * Handle Deep Linking request from LTI launch (via JWT)
     * Called by LaunchController when message_type is LtiDeepLinkingRequest
     */
    public static function handle_deep_linking_launch($claims) {
        // Extract Deep Linking settings from JWT
        $deep_link_settings = $claims->{'https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings'} ?? null;

        if (!$deep_link_settings) {
            error_log('[PB-LTI] No deep linking settings in JWT');
            wp_die('Invalid Deep Linking request: missing deep_linking_settings');
        }

        $return_url = $deep_link_settings->deep_link_return_url ?? null;
        $client_id = $claims->aud ?? null;
        $deployment_id = $claims->{'https://purl.imsglobal.org/spec/lti/claim/deployment_id'} ?? null;

        if (!$return_url || !$client_id) {
            error_log('[PB-LTI] Missing return URL or client ID');
            wp_die('Invalid Deep Linking request: missing required parameters');
        }

        // Get all books
        $books = ContentService::get_all_books();

        // Prepare data for picker view
        $data = [
            'books' => $books,
            'return_url' => $return_url,
            'client_id' => $client_id,
            'deployment_id' => $deployment_id
        ];

        // Render content picker
        ob_start();
        include PB_LTI_PATH . 'views/deep-link-picker.php';
        $html = ob_get_clean();

        // Set proper Content-Type header and return HTML
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    /**
     * Show content picker interface
     */
    private static function show_content_picker($request) {
        // Get all books in the network
        $books = ContentService::get_all_books();

        // Prepare data for view
        $data = [
            'books' => $books,
            'return_url' => $request->get_param('deep_link_return_url'),
            'client_id' => $request->get_param('client_id'),
            'deployment_id' => $request->get_param('deployment_id')
        ];

        // Render the picker view
        ob_start();
        include PB_LTI_PATH . 'views/deep-link-picker.php';
        $html = ob_get_clean();

        // Set proper Content-Type header and return HTML
        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
        exit;
    }

    /**
     * Process content selection and return signed JWT
     */
    private static function process_selection($request) {
        global $wpdb;

        // Get parameters
        $book_id = intval($request->get_param('selected_book_id'));
        $content_id = $request->get_param('selected_content_id');
        $return_url = $request->get_param('deep_link_return_url');
        $client_id = $request->get_param('client_id');

        if (!$book_id || !$return_url || !$client_id) {
            return new \WP_Error('invalid_params', 'Missing required parameters', ['status' => 400]);
        }

        // Fetch private key from database
        $key_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}lti_keys WHERE kid = 'pb-lti-2024'");
        if (!$key_row) {
            return new \WP_Error('no_keys', 'RSA keys not configured', ['status' => 500]);
        }

        // Get selected content details
        $content_item = ContentService::get_content_item(
            $book_id,
            !empty($content_id) ? intval($content_id) : null
        );

        if (!$content_item) {
            return new \WP_Error('invalid_selection', 'Selected content not found', ['status' => 404]);
        }

        // Build Deep Linking JWT response
        $jwt_payload = [
            'iss' => home_url(),
            'aud' => $client_id,
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => wp_generate_password(32, false),
            'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiDeepLinkingResponse',
            'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
            'https://purl.imsglobal.org/spec/lti-dl/claim/content_items' => [$content_item]
        ];

        // Sign JWT with RS256
        $jwt = JWT::encode($jwt_payload, $key_row->private_key, 'RS256', 'pb-lti-2024');

        // LTI 1.3 Deep Linking requires POST, not GET
        // Return an auto-submitting form instead of redirect
        header('Content-Type: text/html; charset=UTF-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head><title>Returning to course...</title></head>
        <body>
            <p>Returning to course...</p>
            <form id="deeplink-return-form" method="POST" action="<?php echo esc_url($return_url); ?>">
                <input type="hidden" name="JWT" value="<?php echo esc_attr($jwt); ?>">
            </form>
            <script>
                document.getElementById('deeplink-return-form').submit();
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

