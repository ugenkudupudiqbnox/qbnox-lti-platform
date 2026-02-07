<?php
/**
 * AJAX Handlers for LTI plugin
 */

defined('ABSPATH') || exit;

use PB_LTI\Services\ContentService;

/**
 * AJAX handler: Get book structure (chapters, parts, etc.)
 */
add_action('wp_ajax_pb_lti_get_book_structure', 'pb_lti_ajax_get_book_structure');
add_action('wp_ajax_nopriv_pb_lti_get_book_structure', 'pb_lti_ajax_get_book_structure');

function pb_lti_ajax_get_book_structure() {
    $book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;

    if (!$book_id) {
        wp_send_json_error(['message' => 'Invalid book ID']);
        return;
    }

    $structure = ContentService::get_book_structure($book_id);

    if ($structure) {
        wp_send_json_success($structure);
    } else {
        wp_send_json_error(['message' => 'Book not found']);
    }
}
