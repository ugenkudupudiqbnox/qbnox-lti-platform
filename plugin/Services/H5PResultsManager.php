<?php
namespace PB_LTI\Services;

/**
 * H5PResultsManager
 *
 * Manages H5P grading configuration and score calculation for chapters
 */
class H5PResultsManager {

    const GRADING_BEST = 'best';
    const GRADING_AVERAGE = 'average';
    const GRADING_FIRST = 'first';
    const GRADING_LAST = 'last';

    /**
     * Save H5P grading configuration for a chapter
     *
     * @param int $post_id Chapter post ID
     * @param array $config Configuration array
     */
    public static function save_configuration($post_id, $config) {
        global $wpdb;
        $table = $wpdb->prefix . 'lti_h5p_grading_config';

        // Delete existing configuration
        $wpdb->delete($table, ['post_id' => $post_id]);

        // Save new configuration for each H5P activity
        if (!empty($config['activities'])) {
            foreach ($config['activities'] as $h5p_id => $activity_config) {
                if (!empty($activity_config['include'])) {
                    $wpdb->insert($table, [
                        'post_id' => $post_id,
                        'h5p_id' => $h5p_id,
                        'include_in_scoring' => 1,
                        'grading_scheme' => $activity_config['scheme'] ?? self::GRADING_BEST,
                        'weight' => $activity_config['weight'] ?? 1.0,
                        'created_at' => current_time('mysql')
                    ]);
                }
            }
        }

        // Save overall chapter settings
        update_post_meta($post_id, '_lti_h5p_grading_enabled', !empty($config['enabled']));
        update_post_meta($post_id, '_lti_h5p_grading_aggregate', $config['aggregate'] ?? 'sum');
    }

    /**
     * Get H5P grading configuration for a chapter
     *
     * @param int $post_id Chapter post ID
     * @return array Configuration
     */
    public static function get_configuration($post_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lti_h5p_grading_config';

        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE post_id = %d",
            $post_id
        ), ARRAY_A);

        $config = [
            'enabled' => get_post_meta($post_id, '_lti_h5p_grading_enabled', true),
            'aggregate' => get_post_meta($post_id, '_lti_h5p_grading_aggregate', true) ?: 'sum',
            'activities' => []
        ];

        foreach ($activities as $activity) {
            $config['activities'][$activity['h5p_id']] = [
                'include' => (bool)$activity['include_in_scoring'],
                'scheme' => $activity['grading_scheme'],
                'weight' => (float)$activity['weight']
            ];
        }

        return $config;
    }

    /**
     * Get configured H5P activities for a chapter
     *
     * @param int $post_id Chapter post ID
     * @return array Array of H5P IDs configured for grading
     */
    public static function get_configured_activities($post_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'lti_h5p_grading_config';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT h5p_id, grading_scheme, weight FROM {$table}
             WHERE post_id = %d AND include_in_scoring = 1",
            $post_id
        ), ARRAY_A);

        return $results;
    }

    /**
     * Calculate final score for a student based on grading scheme
     *
     * @param int $user_id WordPress user ID
     * @param int $post_id Chapter post ID
     * @param int $h5p_id H5P content ID
     * @param string $grading_scheme Grading scheme (best, average, first, last)
     * @param array|null $current_data Current result data ['score' => float, 'max_score' => float]
     * @return array ['score' => float, 'max_score' => float]
     */
    public static function calculate_score($user_id, $post_id, $h5p_id, $grading_scheme, $current_data = null) {
        global $wpdb;

        // Get all attempts for this user and H5P content
        $results_table = $wpdb->prefix . 'h5p_results';
        $attempts = $wpdb->get_results($wpdb->prepare(
            "SELECT score, max_score, finished FROM {$results_table}
             WHERE user_id = %d AND content_id = %d
             ORDER BY finished ASC",
            $user_id,
            $h5p_id
        ), ARRAY_A);

        // Include the current result being saved via the H5P hook (prevents 0/0 on first attempt)
        if ($current_data) {
            $attempts[] = [
                'score' => $current_data['score'],
                'max_score' => $current_data['max_score'] ?? 100,
                'finished' => time() // Assume finished now
            ];
        }

        if (empty($attempts)) {
            return ['score' => 0, 'max_score' => 0];
        }

        $max_score = $attempts[0]['max_score'];

        switch ($grading_scheme) {
            case self::GRADING_BEST:
                $best_score = 0;
                foreach ($attempts as $attempt) {
                    if ($attempt['score'] > $best_score) {
                        $best_score = $attempt['score'];
                    }
                }
                return ['score' => $best_score, 'max_score' => $max_score];

            case self::GRADING_AVERAGE:
                $total = 0;
                $count = 0;
                foreach ($attempts as $attempt) {
                    $total += $attempt['score'];
                    $count++;
                }
                $average = ($count > 0) ? ($total / $count) : 0;
                return ['score' => $average, 'max_score' => $max_score];

            case self::GRADING_FIRST:
                return [
                    'score' => $attempts[0]['score'],
                    'max_score' => $max_score
                ];

            case self::GRADING_LAST:
                $last = end($attempts);
                return [
                    'score' => $last['score'],
                    'max_score' => $max_score
                ];

            default:
                return ['score' => 0, 'max_score' => $max_score];
        }
    }

    /**
     * Calculate aggregate score for all configured activities in a chapter
     *
     * @param int $user_id WordPress user ID
     * @param int $post_id Chapter post ID
     * @param int|null $current_h5p_id H5P content ID currently being saved (optional)
     * @param array|null $current_data Current result data (optional: ['score' => float, 'max_score' => float])
     * @return array ['score' => float, 'max_score' => float, 'percentage' => float]
     */
    public static function calculate_chapter_score($user_id, $post_id, $current_h5p_id = null, $current_data = null) {
        $config = self::get_configuration($post_id);
        $configured_activities = self::get_configured_activities($post_id);

        if (empty($configured_activities)) {
            return ['score' => 0, 'max_score' => 0, 'percentage' => 0];
        }

        $total_score = 0;
        $total_max = 0;
        $weighted_score = 0;
        $total_weight = 0;

        foreach ($configured_activities as $activity) {
            // Check if this activity is the one currently being saved
            $activity_current_data = ($current_h5p_id == $activity['h5p_id']) ? $current_data : null;

            $result = self::calculate_score(
                $user_id,
                $post_id,
                $activity['h5p_id'],
                $activity['grading_scheme'],
                $activity_current_data
            );

            if ($result['max_score'] <= 0) {
                // Skip activities with no questions/no results
                continue;
            }

            if ($config['aggregate'] === 'weighted') {
                $weight = $activity['weight'];
                $weighted_score += ($result['score'] / $result['max_score']) * $weight;
                $total_weight += $weight;
            } else {
                // Sum or average
                $total_score += $result['score'];
                $total_max += $result['max_score'];
            }
        }

        if ($config['aggregate'] === 'weighted' && $total_weight > 0) {
            $percentage = ($weighted_score / $total_weight) * 100;
            return [
                'score' => $weighted_score,
                'max_score' => $total_weight,
                'percentage' => $percentage
            ];
        }

        if ($config['aggregate'] === 'average' && !empty($configured_activities)) {
            $average_score = $total_score / count($configured_activities);
            $average_max = $total_max / count($configured_activities);
            $percentage = $average_max > 0 ? ($average_score / $average_max) * 100 : 0;
            return [
                'score' => $average_score,
                'max_score' => $average_max,
                'percentage' => $percentage
            ];
        }

        // Sum (default)
        $percentage = $total_max > 0 ? ($total_score / $total_max) * 100 : 0;
        return [
            'score' => $total_score,
            'max_score' => $total_max,
            'percentage' => $percentage
        ];
    }

    /**
     * Check if grading is enabled for a chapter
     *
     * @param int $post_id Chapter post ID
     * @return bool
     */
    public static function is_grading_enabled($post_id) {
        return (bool)get_post_meta($post_id, '_lti_h5p_grading_enabled', true);
    }

    /**
     * Get all attempts for a user in a chapter
     *
     * @param int $user_id WordPress user ID
     * @param int $post_id Chapter post ID
     * @return array Array of attempts with scores
     */
    public static function get_user_attempts($user_id, $post_id) {
        $configured_activities = self::get_configured_activities($post_id);
        $attempts = [];

        foreach ($configured_activities as $activity) {
            global $wpdb;
            $results_table = $wpdb->prefix . 'h5p_results';

            $activity_attempts = $wpdb->get_results($wpdb->prepare(
                "SELECT id, score, max_score, finished FROM {$results_table}
                 WHERE user_id = %d AND content_id = %d
                 ORDER BY finished DESC",
                $user_id,
                $activity['h5p_id']
            ), ARRAY_A);

            $attempts[$activity['h5p_id']] = [
                'h5p_id' => $activity['h5p_id'],
                'grading_scheme' => $activity['grading_scheme'],
                'attempts' => $activity_attempts,
                'calculated_score' => self::calculate_score(
                    $user_id,
                    $post_id,
                    $activity['h5p_id'],
                    $activity['grading_scheme']
                )
            ];
        }

        return $attempts;
    }

    /**
     * Get detailed H5P results for all users for a chapter
     *
     * @param int $post_id Chapter post ID
     * @return array Results grouped by user
     */
    public static function get_chapter_results($post_id) {
        global $wpdb;
        
        // Find which blog this post belongs to
        $blog_id = get_current_blog_id();
        if (is_multisite()) {
            // We can't use url_to_postid or similar reverse lookups reliably here
            // But we know that chapters live across blogs. 
            // If the current blog doesn't have this post, we need to find it.
            $post = get_post($post_id);
            if (!$post) {
                // Post not in current blog - search across network blogs
                $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs} WHERE site_id = '{$wpdb->siteid}' AND spam = 0 AND deleted = 0");
                foreach ($blog_ids as $bid) {
                    switch_to_blog($bid);
                    $post = get_post($post_id);
                    if ($post) {
                        $blog_id = $bid;
                        // Don't restore yet, we need this blog's context for queries
                        break;
                    }
                    restore_current_blog();
                }
            } else {
                switch_to_blog($blog_id); // Re-switch to ensure consistency
            }
        }

        error_log("[PB-LTI] get_chapter_results for post $post_id on blog $blog_id. Prefix: " . $wpdb->prefix);

        $config = self::get_configuration($post_id);
        $activities = self::get_configured_activities($post_id);

        if (empty($activities)) {
            error_log("[PB-LTI] No configured activities for post $post_id. Attempting auto-detection.");
            // Detection logic if not explicitly configured
            $post = get_post($post_id);
            if ($post) {
                preg_match_all('/\[h5p id="(\d+)"\]/', $post->post_content, $matches);
                if (!empty($matches[1])) {
                    foreach (array_unique($matches[1]) as $hid) {
                        $activities[] = [
                            'h5p_id' => (int)$hid,
                            'grading_scheme' => 'best',
                            'weight' => 1.0
                        ];
                    }
                    error_log("[PB-LTI] Auto-detected H5P IDs: " . implode(',', array_column($activities, 'h5p_id')));
                }
            }
        }

        if (empty($activities)) {
            error_log("[PB-LTI] No H5P activities found for post $post_id");
            return [];
        }

        $results_table = $wpdb->prefix . 'h5p_results';
        $users_table = $wpdb->users;

        // Get all H5P IDs
        $h5p_ids = array_column($activities, 'h5p_id');
        $placeholders = implode(',', array_fill(0, count($h5p_ids), '%d'));

        // Query for results joined with user data
        $query = $wpdb->prepare(
            "SELECT r.id, r.user_id, r.content_id, r.score, r.max_score, r.finished, u.display_name, u.user_email
             FROM {$results_table} r
             JOIN {$users_table} u ON r.user_id = u.ID
             WHERE r.content_id IN ($placeholders)
             ORDER BY u.display_name ASC, r.finished DESC",
            ...$h5p_ids
        );
        
        error_log("[PB-LTI] Query: $query");

        $raw_results = $wpdb->get_results($query, ARRAY_A);
        error_log("[PB-LTI] Raw results count: " . count($raw_results));
        $user_results = [];

        // Group by user
        foreach ($raw_results as $row) {
            $user_id = (int)$row['user_id'];
            if (!isset($user_results[$user_id])) {
                $user_results[$user_id] = [
                    'user_id' => $user_id,
                    'display_name' => $row['display_name'],
                    'user_email' => $row['user_email'],
                    'activities' => [],
                    'total_calculated_score' => 0,
                    'total_percentage' => 0
                ];
            }

            $h5p_id = (int)$row['content_id'];
            if (!isset($user_results[$user_id]['activities'][$h5p_id])) {
                $user_results[$user_id]['activities'][$h5p_id] = [
                    'id' => $h5p_id,
                    'result_id' => (int)$row['id'],
                    'title' => self::get_h5p_title($h5p_id),
                    'attempts' => [],
                    'grading_scheme' => $config['activities'][$h5p_id]['scheme'] ?? 'best',
                    'calculated_score' => 0,
                    'max_score' => (int)$row['max_score']
                ];
            }

            $user_results[$user_id]['activities'][$h5p_id]['attempts'][] = [
                'id' => (int)$row['id'],
                'score' => (float)$row['score'],
                'max_score' => (float)$row['max_score'],
                'finished' => $row['finished']
            ];
        }

        // Calculate final grade for each user
        foreach ($user_results as $uid => &$data) {
            foreach ($data['activities'] as $hid => &$activity) {
                $calc = self::calculate_score($uid, $post_id, $hid, $activity['grading_scheme']);
                $activity['calculated_score' ] = $calc['score'];
            }
            $chapter_score = self::calculate_chapter_score($uid, $post_id);
            $data['total_calculated_score'] = $chapter_score['score'];
            $data['total_percentage'] = $chapter_score['percentage'];
            $data['total_max'] = $chapter_score['max_score'];

            // Fetch attempt history from sync logs for this user/post
            $sync_table = $wpdb->prefix . 'lti_h5p_grade_sync_log';
            $data['history'] = $wpdb->get_results($wpdb->prepare(
                "SELECT result_id, score_sent as score, max_score, synced_at as finished, status, error_message 
                 FROM {$sync_table} 
                 WHERE user_id = %d AND post_id = %d 
                 ORDER BY synced_at DESC",
                $uid,
                $post_id
            ), ARRAY_A);
        }

        if (is_multisite()) {
            restore_current_blog();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'h5p_contents';
        return $wpdb->get_var($wpdb->prepare("SELECT title FROM {$table} WHERE id = %d", $h5p_id)) ?: "H5P #$h5p_id";
    }
}
