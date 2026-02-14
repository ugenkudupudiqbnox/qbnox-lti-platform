<?php
namespace PB_LTI\Services;

/**
 * H5PGradeSync
 *
 * Automatically sends H5P activity scores to Moodle gradebook via LTI AGS
 * when students complete H5P activities in Pressbooks.
 */
class H5PGradeSync {

    /**
     * Initialize H5P grade sync hooks
     */
    public static function init() {
        // Hook into H5P result saving
        add_action('h5p_alter_user_result', [__CLASS__, 'sync_grade_to_moodle'], 10, 4);
    }

    /**
     * Send H5P grade to Moodle when result is saved
     *
     * @param array $data Result data
     * @param int $result_id H5P result ID
     * @param int $content_id H5P content ID
     * @param int $user_id WordPress user ID
     */
    public static function sync_grade_to_moodle($data, $result_id, $content_id, $user_id) {
        error_log('[PB-LTI H5P] Result saved - User: ' . $user_id . ', Score: ' . $data['score'] . '/' . $data['max_score']);

        // Check if user has an active LTI context (came from Moodle)
        $lineitem_url = get_user_meta($user_id, '_lti_ags_lineitem', true);
        $platform_issuer = get_user_meta($user_id, '_lti_platform_issuer', true);
        $lti_user_id = get_user_meta($user_id, '_lti_user_id', true);

        if (empty($lineitem_url) || empty($platform_issuer) || empty($lti_user_id)) {
            error_log('[PB-LTI H5P] No AGS context for user ' . $user_id . ' - skipping grade sync');
            return;
        }

        // Calculate score as percentage
        $score = $data['score'];
        $max_score = $data['max_score'];

        if ($max_score == 0) {
            error_log('[PB-LTI H5P] Max score is 0 - cannot calculate grade');
            return;
        }

        $percentage = ($score / $max_score) * 100;

        error_log('[PB-LTI H5P] H5P Result - Score: ' . $percentage . '% (' . $score . '/' . $max_score . ')');

        // Get platform configuration for OAuth2
        global $wpdb;
        // Use base_prefix for network-level tables (not blog-specific)
        $platform = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->base_prefix}lti_platforms WHERE issuer = %s",
            $platform_issuer
        ));

        if (!$platform) {
            error_log('[PB-LTI H5P] Platform not found for issuer: ' . $platform_issuer);
            return;
        }

        // Fetch lineitem details to detect if it's a scale or points
        $lineitem = AGSClient::fetch_lineitem($platform, $lineitem_url);

        $final_score = $score;
        $final_max = $max_score;

        if ($lineitem) {
            // Detect scale type
            $scale_type = ScaleMapper::detect_scale($lineitem);

            if ($scale_type && $scale_type !== 'unknown') {
                // Map percentage to scale value
                $mapped = ScaleMapper::map_to_scale($percentage, $scale_type);
                $final_score = $mapped['score'];
                $final_max = $mapped['max'];
                error_log('[PB-LTI H5P] Using scale grading: ' . $mapped['label'] . ' (value: ' . $final_score . ')');
            } else {
                error_log('[PB-LTI H5P] Using point grading: ' . $score . '/' . $max_score);
            }
        } else {
            error_log('[PB-LTI H5P] Could not fetch lineitem - using raw H5P score');
        }

        // Send grade via AGS
        try {
            $result = AGSClient::post_score(
                $platform,
                $lineitem_url,
                $lti_user_id,  // Use LTI user ID, not WordPress user ID
                $final_score,
                $final_max,
                'Completed',
                'FullyGraded'
            );

            if ($result['success']) {
                error_log('[PB-LTI H5P] ✅ Grade posted successfully to Moodle');

                // Store last sync time
                update_user_meta($user_id, '_lti_h5p_last_grade_sync', time());
            } else {
                error_log('[PB-LTI H5P] ❌ Failed to post grade: ' . ($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            error_log('[PB-LTI H5P] ❌ Exception posting grade: ' . $e->getMessage());
        }
    }
}
