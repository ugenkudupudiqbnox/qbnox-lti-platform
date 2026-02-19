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
        $selected_chapter_ids = $request->get_param('selected_chapter_ids');  // Comma-separated IDs
        $is_results_viewer = $request->get_param('is_results_viewer') === '1';
        $return_url = $request->get_param('deep_link_return_url');
        $client_id = $request->get_param('client_id');

        if (!$book_id || !$return_url || !$client_id) {
            return new \WP_Error('invalid_params', 'Missing required parameters', ['status' => 400]);
        }

        // Look up platform issuer from client_id
        $platform = $wpdb->get_row($wpdb->prepare(
            "SELECT issuer FROM {$wpdb->prefix}lti_platforms WHERE client_id = %s",
            $client_id
        ));

        if (!$platform) {
            return new \WP_Error('unknown_platform', 'Platform not registered', ['status' => 400]);
        }

        // Fetch private key from database
        $key_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}lti_keys WHERE kid = 'pb-lti-2024'");
        if (!$key_row) {
            return new \WP_Error('no_keys', 'RSA keys not configured', ['status' => 500]);
        }

        // Get selected content details
        $content_items = [];

        if ($is_results_viewer) {
            // Results Viewer requested
            $results_item = ContentService::get_results_viewer_item($book_id);
            if ($results_item) {
                $content_items[] = $results_item;
                error_log('[PB-LTI Deep Link] Results Viewer selected for book ' . $book_id . ' (URL: ' . $results_item['url'] . ')');
            }
        } elseif (!empty($selected_chapter_ids)) {
            // Specific chapters selected - create activity for each selected chapter
            $chapter_ids = array_map('intval', explode(',', $selected_chapter_ids));
            error_log('[PB-LTI Deep Link] Selected chapters (IDs: ' . implode(', ', $chapter_ids) . ') from book ' . $book_id);

            foreach ($chapter_ids as $chapter_id) {
                $chapter_item = ContentService::get_content_item($book_id, $chapter_id);
                if ($chapter_item) {
                    $content_items[] = $chapter_item;
                }
            }

            error_log('[PB-LTI Deep Link] Created ' . count($content_items) . ' activities for selected chapters');
        } elseif (empty($content_id)) {
            // Whole book selected (no chapter selection made) - create activity for each chapter
            error_log('[PB-LTI Deep Link] Whole book selected (ID: ' . $book_id . ') - creating activities for all chapters');

            $book_structure = ContentService::get_book_structure($book_id);
            if (!$book_structure || empty($book_structure['chapters'])) {
                return new \WP_Error('no_chapters', 'No chapters found in selected book', ['status' => 404]);
            }

            // Create content item for each chapter
            foreach ($book_structure['chapters'] as $chapter) {
                $chapter_item = ContentService::get_content_item($book_id, $chapter['id']);
                if ($chapter_item) {
                    $content_items[] = $chapter_item;
                }
            }

            // Also include front matter and back matter if they exist
            if (!empty($book_structure['front_matter'])) {
                foreach ($book_structure['front_matter'] as $item) {
                    $front_item = ContentService::get_content_item($book_id, $item['id']);
                    if ($front_item) {
                        array_unshift($content_items, $front_item);  // Add to beginning
                    }
                }
            }

            if (!empty($book_structure['back_matter'])) {
                foreach ($book_structure['back_matter'] as $item) {
                    $back_item = ContentService::get_content_item($book_id, $item['id']);
                    if ($back_item) {
                        $content_items[] = $back_item;  // Add to end
                    }
                }
            }

            error_log('[PB-LTI Deep Link] Created ' . count($content_items) . ' activities for whole book');
        } else {
            // Single chapter/content selected
            $content_item = ContentService::get_content_item(
                $book_id,
                intval($content_id)
            );

            if (!$content_item) {
                return new \WP_Error('invalid_selection', 'Selected content not found', ['status' => 404]);
            }

            $content_items[] = $content_item;
            error_log('[PB-LTI Deep Link] Single content selected: ' . $content_item['title']);
        }

        if (empty($content_items)) {
            return new \WP_Error('no_content', 'No valid content items found', ['status' => 404]);
        }

        // Build Deep Linking JWT response
        // CRITICAL: For Moodle compatibility:
        // - iss MUST be the client_id (tool's identifier in the platform)
        // - aud MUST be the platform's issuer URL
        $jwt_payload = [
            'iss' => $client_id,  // Tool's identifier (client_id)
            'aud' => $platform->issuer,  // Platform's issuer URL
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => wp_generate_password(32, false),
            'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiDeepLinkingResponse',
            'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
            'https://purl.imsglobal.org/spec/lti-dl/claim/content_items' => $content_items
        ];

        // Debug logging
        error_log('[PB-LTI Deep Link] JWT Claims: iss=' . $client_id . ', aud=' . $platform->issuer);

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

