<?php
namespace PB_LTI\Services;

/**
 * Maps H5P percentage scores to Moodle scale values
 * Supports specific Moodle scales with custom mapping logic
 */
class ScaleMapper {

    /**
     * Known Moodle scales with their mapping logic
     */
    private static $scales = [
        // Default competence scale (2 items: 0-1)
        'competence' => [
            'items' => ['Not yet competent', 'Competent'],
            'max' => 1,
            'thresholds' => [
                0.5 => 0,  // < 50% = Not yet competent
                1.0 => 1,  // >= 50% = Competent
            ]
        ],
        // Separate and Connected ways of knowing (3 items: 0-2)
        'ways_of_knowing' => [
            'items' => ['Mostly separate knowing', 'Separate and connected', 'Mostly connected knowing'],
            'max' => 2,
            'thresholds' => [
                0.4 => 0,  // < 40% = Mostly separate knowing
                0.7 => 1,  // 40-70% = Separate and connected
                1.0 => 2,  // >= 70% = Mostly connected knowing
            ]
        ]
    ];

    /**
     * Detect scale type from lineitem details
     *
     * @param array $lineitem Lineitem details from Moodle
     * @return string|null Scale type or null if points-based
     */
    public static function detect_scale($lineitem) {
        if (!isset($lineitem['scoreMaximum'])) {
            return null;
        }

        $max = (float)$lineitem['scoreMaximum'];

        // Match scoreMaximum to known scales
        if ($max == 1) {
            return 'competence';
        } elseif ($max == 2) {
            return 'ways_of_knowing';
        }

        // If scoreMaximum is small (< 10), it's likely a scale
        // but not one we recognize
        if ($max < 10) {
            error_log('[PB-LTI Scale] Unknown scale detected with max=' . $max);
            return 'unknown';
        }

        // Points-based grading
        return null;
    }

    /**
     * Map H5P percentage score to scale value
     *
     * @param float $percentage Score as percentage (0-100)
     * @param string $scale_type Scale type identifier
     * @return array ['score' => scale_value, 'max' => scale_max, 'label' => scale_label]
     */
    public static function map_to_scale($percentage, $scale_type) {
        if (!isset(self::$scales[$scale_type])) {
            // Unknown scale - just pass through as-is
            return ['score' => $percentage, 'max' => 100, 'label' => 'Unknown scale'];
        }

        $scale = self::$scales[$scale_type];
        $normalized = $percentage / 100.0;  // Convert to 0.0-1.0

        // Find matching threshold
        $scale_value = 0;
        foreach ($scale['thresholds'] as $threshold => $value) {
            if ($normalized < $threshold) {
                $scale_value = $value;
                break;
            }
            $scale_value = $value;  // Use last value if >= all thresholds
        }

        $label = $scale['items'][$scale_value] ?? 'Unknown';

        error_log(sprintf(
            '[PB-LTI Scale] Mapped %.1f%% to scale value %d (%s) on %s scale',
            $percentage,
            $scale_value,
            $label,
            $scale_type
        ));

        return [
            'score' => $scale_value,
            'max' => $scale['max'],
            'label' => $label
        ];
    }

    /**
     * Get scale information by type
     *
     * @param string $scale_type Scale type identifier
     * @return array|null Scale configuration or null if not found
     */
    public static function get_scale_info($scale_type) {
        return self::$scales[$scale_type] ?? null;
    }
}
