<?php
namespace PB_LTI\Services;

/**
 * H5PGradeSyncEnhanced
 *
 * Enhanced H5P grade sync with chapter-level grading configuration support
 * Replaces H5PGradeSync when Pressbooks Results feature is enabled
 */
class H5PGradeSyncEnhanced {

    /**
     * Initialize H5P grade sync hooks
     */
    public static function init() {
        // Hook into H5P result saving
        add_action('h5p_alter_user_result', [__CLASS__, 'sync_grade_to_lms'], 10, 4);
    }

    /**
     * Send H5P grade to LMS when result is saved
     * Checks for chapter-level grading configuration and sends aggregate scores
     *
     * @param array $data Result data
     * @param int $result_id H5P result ID
     * @param int $content_id H5P content ID
     * @param int $user_id WordPress user ID
     */
    public static function sync_grade_to_lms($data, $result_id, $content_id, $user_id) {
        error_log('[PB-LTI H5P Enhanced] Result saved - User: ' . $user_id . ', H5P: ' . $content_id . ', Score: ' . $data['score'] . '/' . $data['max_score']);

        // Get global LTI context (user-level)
        $platform_issuer = get_user_meta($user_id, '_lti_platform_issuer', true);
        $lti_user_id = get_user_meta($user_id, '_lti_user_id', true);

        if (empty($platform_issuer) || empty($lti_user_id)) {
            error_log('[PB-LTI H5P Enhanced] No LTI context for user ' . $user_id . ' - skipping grade sync');
            return;
        }

        // Find which chapter contains this H5P activity
        $post_id = self::find_chapter_containing_h5p($content_id);
        if (!$post_id) {
            error_log('[PB-LTI H5P Enhanced] Could not find chapter for H5P ' . $content_id);
            return;
        }

        // Get chapter-specific lineitem for this user
        $lineitem_key = '_lti_ags_lineitem_user_' . $user_id;
        $lineitem_url = get_post_meta($post_id, $lineitem_key, true);

        // Fallback to old user meta storage for backward compatibility
        if (empty($lineitem_url)) {
            error_log('[PB-LTI H5P Enhanced] No chapter-specific lineitem for post ' . $post_id . ', user ' . $user_id . ' - checking user meta fallback');
            $lineitem_url = get_user_meta($user_id, '_lti_ags_lineitem', true);
        }

        if (empty($lineitem_url)) {
            error_log('[PB-LTI H5P Enhanced] No lineitem URL found for post ' . $post_id . ', user ' . $user_id . ' - skipping grade sync');
            return;
        }

        error_log('[PB-LTI H5P Enhanced] Using lineitem for post ' . $post_id . ', user ' . $user_id . ': ' . $lineitem_url);

        // Check if chapter has grading configuration enabled
        if (!H5PResultsManager::is_grading_enabled($post_id)) {
            error_log('[PB-LTI H5P Enhanced] Grading not enabled for post ' . $post_id . ' - falling back to individual sync');
            self::sync_individual_activity($data, $user_id, $lti_user_id, $platform_issuer, $lineitem_url);
            return;
        }

        // Check if this specific H5P is configured for grading
        $configured = H5PResultsManager::get_configured_activities($post_id);
        $is_configured = false;
        foreach ($configured as $activity) {
            if ($activity['h5p_id'] == $content_id) {
                $is_configured = true;
                break;
            }
        }

        if (!$is_configured) {
            error_log('[PB-LTI H5P Enhanced] H5P ' . $content_id . ' not configured for grading in post ' . $post_id . ' - falling back to individual sync');
            self::sync_individual_activity($data, $user_id, $lti_user_id, $platform_issuer, $lineitem_url);
            return;
        }

        // Calculate chapter-level score based on configuration (passing current data to include it)
        $chapter_score = H5PResultsManager::calculate_chapter_score($user_id, $post_id, $content_id, $data);

        error_log(sprintf(
            '[PB-LTI H5P Enhanced] Chapter %d aggregated score for user %d: %.2f/%.2f (%.1f%%)',
            $post_id,
            $user_id,
            $chapter_score['score'],
            $chapter_score['max_score'],
            $chapter_score['percentage']
        ));

        // Get platform configuration for OAuth2
        global $wpdb;
        $platform = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->base_prefix}lti_platforms WHERE issuer = %s",
            $platform_issuer
        ));

        if (!$platform) {
            error_log('[PB-LTI H5P Enhanced] Platform not found for issuer: ' . $platform_issuer);
            return;
        }

        // Fetch lineitem details to detect scale vs points
        $lineitem = AGSClient::fetch_lineitem($platform, $lineitem_url);

        $final_score = $chapter_score['score'];
        $final_max = $chapter_score['max_score'];

        if ($lineitem) {
            // Detect scale type
            $scale_type = ScaleMapper::detect_scale($lineitem);

            if ($scale_type && $scale_type !== 'unknown') {
                // Map percentage to scale value
                $mapped = ScaleMapper::map_to_scale($chapter_score['percentage'], $scale_type);
                $final_score = $mapped['score'];
                $final_max = $mapped['max'];
                error_log('[PB-LTI H5P Enhanced] Using scale grading: ' . $mapped['label'] . ' (value: ' . $final_score . ')');
            } else {
                error_log('[PB-LTI H5P Enhanced] Using point grading: ' . $final_score . '/' . $final_max);
            }
        }

        // Send grade via AGS
        try {
            $result = AGSClient::post_score(
                $platform,
                $lineitem_url,
                $lti_user_id,
                $final_score,
                $final_max,
                'Completed',
                'FullyGraded'
            );

            if ($result['success']) {
                error_log('[PB-LTI H5P Enhanced] ✅ Chapter grade posted successfully to LMS');
            } else {
                error_log('[PB-LTI H5P Enhanced] ❌ Failed to post grade: ' . ($result['error'] ?? 'Unknown error'));
            }

            // Store sync status and scores in log
            self::update_sync_timestamp(
                $user_id, 
                $post_id, 
                $result_id, 
                $final_score, 
                $final_max, 
                $result['success'] ? 'success' : 'failed',
                $result['success'] ? null : ($result['error'] ?? 'Unknown error')
            );
        } catch (\Exception $e) {
            error_log('[PB-LTI H5P Enhanced] ❌ Failed to post grade: ' . $e->getMessage());
            self::update_sync_timestamp($user_id, $post_id, $result_id, $final_score, $final_max, 'failed', $e->getMessage());
        }
    }

    /**
     * Sync individual H5P activity (fallback when no chapter configuration exists)
     *
     * @param array $data Result data
     * @param int $user_id WordPress user ID
     * @param string $lti_user_id LTI user ID
     * @param string $platform_issuer Platform issuer
     * @param string $lineitem_url AGS lineitem URL
     */
    private static function sync_individual_activity($data, $user_id, $lti_user_id, $platform_issuer, $lineitem_url) {
        global $wpdb;

        $score = $data['score'];
        $max_score = $data['max_score'];
        $percentage = $max_score > 0 ? ($score / $max_score) * 100 : 0;

        $platform = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->base_prefix}lti_platforms WHERE issuer = %s",
            $platform_issuer
        ));

        if (!$platform) {
            return;
        }

        $lineitem = AGSClient::fetch_lineitem($platform, $lineitem_url);

        $final_score = $score;
        $final_max = $max_score;

        if ($lineitem) {
            $scale_type = ScaleMapper::detect_scale($lineitem);
            if ($scale_type && $scale_type !== 'unknown') {
                $mapped = ScaleMapper::map_to_scale($percentage, $scale_type);
                $final_score = $mapped['score'];
                $final_max = $mapped['max'];
            }
        }

        $result = AGSClient::post_score(
            $platform,
            $lineitem_url,
            $lti_user_id,
            $final_score,
            $final_max,
            'Completed',
            'FullyGraded'
        );

        $post_id = self::find_chapter_containing_h5p($data['content_id']);
        if ($post_id) {
            self::update_sync_timestamp(
                $user_id, 
                $post_id, 
                0, 
                $final_score, 
                $final_max,
                $result['success'] ? 'success' : 'failed',
                $result['success'] ? null : ($result['error'] ?? 'Unknown error')
            );
        }

        if ($result['success']) {
            error_log('[PB-LTI H5P Enhanced] Individual grade sync successful');
        } else {
            error_log('[PB-LTI H5P Enhanced] Individual grade sync failed: ' . ($result['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Find chapter that contains a specific H5P activity
     *
     * @param int $h5p_id H5P content ID
     * @return int|null Post ID or null if not found
     */
    private static function find_chapter_containing_h5p($h5p_id) {
        global $wpdb;

        // Search in chapters, front-matter, and back-matter
        $post_types = ['chapter', 'front-matter', 'back-matter'];

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_content FROM {$wpdb->posts}
             WHERE post_type IN ('" . implode("','", $post_types) . "')
             AND post_status = 'publish'
             AND post_content LIKE %s",
            '%[h5p%id="' . $h5p_id . '"%'
        ));

        foreach ($results as $post) {
            // Verify the H5P ID is actually in this post
            if (preg_match('/\[h5p(?:-iframe)?\s+id=["\']?' . $h5p_id . '["\']?\]/i', $post->post_content)) {
                return $post->ID;
            }
        }

        return null;
    }

    /**
     * Update sync timestamp for tracking
     *
     * @param int $user_id WordPress user ID
     * @param int $post_id Chapter post ID
     * @param int $result_id H5P result ID
     * @param float $score Score sent
     * @param float $max_score Maximum score
     * @param string $status Sync status (success, failed)
     * @param string $error Error message if failed
     */
    private static function update_sync_timestamp($user_id, $post_id, $result_id, $score = null, $max_score = null, $status = 'success', $error = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'lti_h5p_grade_sync_log';

        $wpdb->insert($table, [
            'user_id' => $user_id,
            'post_id' => $post_id,
            'result_id' => $result_id,
            'score_sent' => $score,
            'max_score' => $max_score,
            'synced_at' => current_time('mysql'),
            'status' => $status,
            'error_message' => $error
        ]);
    }

    /**
     * Get last sync time for a user and chapter
     *
     * @param int $user_id WordPress user ID
     * @param int $post_id Chapter post ID
     * @return string|null Last sync timestamp
     */
    public static function get_last_sync_time($user_id, $post_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'lti_h5p_grade_sync_log';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT synced_at FROM {$table}
             WHERE user_id = %d AND post_id = %d
             ORDER BY synced_at DESC
             LIMIT 1",
            $user_id,
            $post_id
        ));

        return $result;
    }

    /**
     * Sync existing/historical H5P grades for a chapter
     *
     * This method finds all H5P results for a chapter that haven't been synced yet
     * and posts them to the LMS via AGS. Useful for retroactive grade synchronization.
     *
     * @param int $post_id Chapter post ID
     * @param int|null $user_id Optional: specific user ID to sync (null = all users)
     * @return array Results summary with success/failure counts
     */
    public static function sync_existing_grades($post_id, $user_id = null) {
        global $wpdb;

        $results = [
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => []
        ];

        // Check if grading is enabled for this chapter
        if (!H5PResultsManager::is_grading_enabled($post_id)) {
            $results['errors'][] = 'Grading not enabled for this chapter';
            return $results;
        }

        // Get configured H5P activities for this chapter
        $configured = H5PResultsManager::get_configured_activities($post_id);
        if (empty($configured)) {
            $results['errors'][] = 'No H5P activities configured for grading';
            return $results;
        }

        $h5p_ids = array_column($configured, 'h5p_id');

        // Find all users who have completed these H5P activities
        $results_table = $wpdb->prefix . 'h5p_results';

        $where_user = $user_id ? $wpdb->prepare(" AND user_id = %d", $user_id) : "";

        $query = "SELECT DISTINCT user_id, content_id, MAX(id) as latest_result_id
             FROM {$results_table}
             WHERE content_id IN (" . implode(',', array_map('intval', $h5p_ids)) . ")
             {$where_user}
             GROUP BY user_id, content_id
             ORDER BY user_id, content_id";

        $h5p_results = $wpdb->get_results($query);

        error_log('[PB-LTI H5P Sync] Found ' . count($h5p_results) . ' H5P results to potentially sync for post ' . $post_id);

        // Group by user
        $users_to_sync = [];
        foreach ($h5p_results as $result) {
            if (!isset($users_to_sync[$result->user_id])) {
                $users_to_sync[$result->user_id] = [];
            }
            $users_to_sync[$result->user_id][] = $result->content_id;
        }

        // Process each user
        foreach ($users_to_sync as $wp_user_id => $content_ids) {
            // Check if user has LTI context (global)
            $platform_issuer = get_user_meta($wp_user_id, '_lti_platform_issuer', true);
            $lti_user_id = get_user_meta($wp_user_id, '_lti_user_id', true);

            if (empty($platform_issuer) || empty($lti_user_id)) {
                error_log('[PB-LTI H5P Sync] User ' . $wp_user_id . ' has no LTI context - skipping');
                $results['skipped']++;
                continue;
            }

            // Get chapter-specific lineitem for this user
            $lineitem_key = '_lti_ags_lineitem_user_' . $wp_user_id;
            $lineitem_url = get_post_meta($post_id, $lineitem_key, true);

            // Fallback to old user meta storage for backward compatibility
            if (empty($lineitem_url)) {
                error_log('[PB-LTI H5P Sync] No chapter-specific lineitem for post ' . $post_id . ', user ' . $wp_user_id . ' - checking user meta fallback');
                $lineitem_url = get_user_meta($wp_user_id, '_lti_ags_lineitem', true);
            }

            if (empty($lineitem_url)) {
                error_log('[PB-LTI H5P Sync] User ' . $wp_user_id . ' has no lineitem URL for post ' . $post_id . ' - skipping');
                $results['skipped']++;
                continue;
            }

            // Calculate chapter score for this user
            $chapter_score = H5PResultsManager::calculate_chapter_score($wp_user_id, $post_id);

            if ($chapter_score['max_score'] == 0) {
                error_log('[PB-LTI H5P Sync] User ' . $wp_user_id . ' has no valid scores - skipping');
                $results['skipped']++;
                continue;
            }

            // Get platform for OAuth2
            $platform = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->base_prefix}lti_platforms WHERE issuer = %s",
                $platform_issuer
            ));

            if (!$platform) {
                error_log('[PB-LTI H5P Sync] Platform not found for issuer: ' . $platform_issuer);
                $results['failed']++;
                $results['errors'][] = 'Platform not found for user ' . $wp_user_id;
                continue;
            }

            // Fetch lineitem to detect scale type
            $lineitem = AGSClient::fetch_lineitem($platform, $lineitem_url);

            $final_score = $chapter_score['score'];
            $final_max = $chapter_score['max_score'];

            if ($lineitem) {
                $scale_type = ScaleMapper::detect_scale($lineitem);
                if ($scale_type && $scale_type !== 'unknown') {
                    $mapped = ScaleMapper::map_to_scale($chapter_score['percentage'], $scale_type);
                    $final_score = $mapped['score'];
                    $final_max = $mapped['max'];
                    error_log('[PB-LTI H5P Sync] Using scale grading for user ' . $wp_user_id . ': ' . $mapped['label']);
                }
            }

            // Post grade via AGS
            try {
                $result = AGSClient::post_score(
                    $platform,
                    $lineitem_url,
                    $lti_user_id,
                    $final_score,
                    $final_max,
                    'Completed',
                    'FullyGraded'
                );

                if ($result['success']) {
                    error_log(sprintf(
                        '[PB-LTI H5P Sync] ✅ Synced grade for user %d: %.2f/%.2f (%.1f%%)',
                        $wp_user_id,
                        $final_score,
                        $final_max,
                        $chapter_score['percentage']
                    ));

                    // Log sync status and scores
                    self::update_sync_timestamp(
                        $wp_user_id, 
                        $post_id, 
                        0, 
                        $final_score, 
                        $final_max,
                        $result['success'] ? 'success' : 'failed',
                        $result['success'] ? null : ($result['error'] ?? 'Unknown error')
                    );
                    $results['success']++;
                } else {
                    error_log('[PB-LTI H5P Sync] ❌ Failed for user ' . $wp_user_id . ': ' . ($result['error'] ?? 'Unknown error'));
                    self::update_sync_timestamp($wp_user_id, $post_id, 0, $final_score, $final_max, 'failed', $result['error'] ?? 'Unknown error');
                    $results['failed']++;
                    $results['errors'][] = 'User ' . $wp_user_id . ': ' . ($result['error'] ?? 'Unknown error');
                }
            } catch (\Exception $e) {
                error_log('[PB-LTI H5P Sync] ❌ Exception for user ' . $wp_user_id . ': ' . $e->getMessage());
                self::update_sync_timestamp($wp_user_id, $post_id, 0, $final_score, $final_max, 'failed', $e->getMessage());
                $results['failed']++;
                $results['errors'][] = 'User ' . $wp_user_id . ': ' . $e->getMessage();
            }
        }

        return $results;
    }
}
