<?php
/**
 * AJAX Handlers for LTI plugin
 */

defined('ABSPATH') || exit;

use PB_LTI\Services\ContentService;
use PB_LTI\Services\H5PGradeSyncEnhanced;

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

/**
 * AJAX handler: Sync existing H5P grades for a chapter
 */
add_action('wp_ajax_pb_lti_sync_existing_grades', 'pb_lti_ajax_sync_existing_grades');

function pb_lti_ajax_sync_existing_grades() {
    // Security check
    check_ajax_referer('pb_lti_sync_grades', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$post_id) {
        wp_send_json_error(['message' => 'Invalid post ID']);
        return;
    }

    // Check user capabilities
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    // Run the sync
    try {
        $results = H5PGradeSyncEnhanced::sync_existing_grades($post_id);

        if ($results['success'] > 0 || $results['skipped'] > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    'Sync complete: %d succeeded, %d skipped, %d failed',
                    $results['success'],
                    $results['skipped'],
                    $results['failed']
                ),
                'results' => $results
            ]);
        } else {
            wp_send_json_error([
                'message' => 'No grades were synced. ' . implode(' ', $results['errors']),
                'results' => $results
            ]);
        }
    } catch (\Exception $e) {
        wp_send_json_error([
            'message' => 'Error during sync: ' . $e->getMessage()
        ]);
    }
}

/**
 * AJAX handler: Get all H5P results for a chapter
 */
add_action('wp_ajax_qb_lti_get_h5p_results', 'pb_lti_ajax_get_h5p_results');

function pb_lti_ajax_get_h5p_results() {
    // Security check
    check_ajax_referer('pb_lti_h5p_results_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    if (!$post_id) {
        wp_send_json_error(['message' => 'Invalid post ID']);
        return;
    }

    $is_instructor = current_user_can('edit_post', $post_id) || is_super_admin();
    $current_user_id = get_current_user_id();

    try {
        $results = \PB_LTI\Services\H5PResultsManager::get_chapter_results($post_id);
        
        // If not instructor, only return the current student's results
        if (!$is_instructor) {
            if (isset($results[$current_user_id])) {
                $results = [$current_user_id => $results[$current_user_id]];
            } else {
                $results = []; // No results for this student yet
            }
        }

        wp_send_json_success([
            'results' => array_values($results),
            'last_sync' => get_post_meta($post_id, '_lti_last_grade_sync', true) ?: 'Never',
            'is_instructor' => $is_instructor
        ]);
    } catch (\Exception $e) {
        wp_send_json_error(['message' => 'Error fetching results: ' . $e->getMessage()]);
    }
}
