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
}
