<?php
namespace PB_LTI\Admin;

use PB_LTI\Services\H5PActivityDetector;
use PB_LTI\Services\H5PResultsManager;

/**
 * H5P Results Meta Box
 *
 * Adds LMS Grade Reporting configuration to chapter edit screen
 */
class H5PResultsMetaBox {

    /**
     * Initialize meta box hooks
     */
    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post', [__CLASS__, 'save_meta_box'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Register meta box
     */
    public static function add_meta_box() {
        $post_types = ['chapter', 'front-matter', 'back-matter'];

        foreach ($post_types as $post_type) {
            add_meta_box(
                'pb_lti_h5p_results',
                '📊 LMS Grade Reporting (LTI AGS)',
                [__CLASS__, 'render_meta_box'],
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        global $post;
        if (!in_array($post->post_type, ['chapter', 'front-matter', 'back-matter'])) {
            return;
        }

        wp_enqueue_style(
            'pb-lti-h5p-results',
            plugin_dir_url(__FILE__) . '../assets/css/h5p-results-metabox.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'pb-lti-h5p-results',
            plugin_dir_url(__FILE__) . '../assets/js/h5p-results-metabox.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }

    /**
     * Render meta box content
     *
     * @param \WP_Post $post Current post object
     */
    public static function render_meta_box($post) {
        // Security nonce
        wp_nonce_field('pb_lti_h5p_results', 'pb_lti_h5p_results_nonce');

        // Get current configuration
        $config = H5PResultsManager::get_configuration($post->ID);
        $grading_enabled = $config['enabled'];
        $aggregate_method = $config['aggregate'];

        // Detect H5P activities in content
        $activities = H5PActivityDetector::find_h5p_activities($post->ID);

        if (empty($activities)) {
            echo '<div class="notice notice-info inline">';
            echo '<p><strong>ℹ️ No H5P activities detected in this chapter.</strong></p>';
            echo '<p>Add H5P content using the <code>[h5p id="X"]</code> shortcode, then save the chapter to configure grading.</p>';
            echo '</div>';
            return;
        }

        ?>
        <div class="pb-lti-h5p-results-wrapper">
            <!-- Enable Grading Toggle -->
            <div class="pb-lti-section">
                <label class="pb-lti-toggle">
                    <input type="checkbox"
                           name="pb_lti_h5p_grading_enabled"
                           value="1"
                           <?php checked($grading_enabled, true); ?>
                           class="pb-lti-enable-grading">
                    <span class="pb-lti-toggle-label">
                        <strong>Enable LMS Grade Reporting for this Chapter</strong>
                    </span>
                </label>
                <p class="description">
                    When enabled, student scores will be sent to the LMS gradebook via LTI Assignment and Grade Services (AGS).
                </p>
            </div>

            <!-- Grading Configuration (only shown when enabled) -->
            <div class="pb-lti-grading-config" style="<?php echo $grading_enabled ? '' : 'display:none;'; ?>">

                <!-- Aggregate Method -->
                <div class="pb-lti-section">
                    <h3>📊 Score Aggregation Method</h3>
                    <select name="pb_lti_h5p_aggregate" class="regular-text">
                        <option value="sum" <?php selected($aggregate_method, 'sum'); ?>>
                            Sum - Add all activity scores
                        </option>
                        <option value="average" <?php selected($aggregate_method, 'average'); ?>>
                            Average - Calculate mean score
                        </option>
                        <option value="weighted" <?php selected($aggregate_method, 'weighted'); ?>>
                            Weighted - Custom weights per activity
                        </option>
                    </select>
                    <p class="description">
                        How should multiple H5P activity scores be combined?
                    </p>
                </div>

                <!-- Activity List -->
                <div class="pb-lti-section">
                    <h3>🎯 H5P Activities Configuration</h3>
                    <p class="description">Select which activities to include and configure grading for each.</p>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" id="pb-lti-select-all">
                                </th>
                                <th style="width: 5%;">Position</th>
                                <th style="width: 35%;">Activity</th>
                                <th style="width: 15%;">Library</th>
                                <th style="width: 10%;">Max Score</th>
                                <th style="width: 20%;">Grading Scheme</th>
                                <th style="width: 15%;">Weight</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity):
                                $h5p_id = $activity['id'];
                                $is_included = isset($config['activities'][$h5p_id]['include']) ?
                                              $config['activities'][$h5p_id]['include'] : false;
                                $scheme = isset($config['activities'][$h5p_id]['scheme']) ?
                                         $config['activities'][$h5p_id]['scheme'] : 'best';
                                $weight = isset($config['activities'][$h5p_id]['weight']) ?
                                         $config['activities'][$h5p_id]['weight'] : 1.0;
                            ?>
                            <tr class="pb-lti-activity-row" data-h5p-id="<?php echo esc_attr($h5p_id); ?>">
                                <th class="check-column">
                                    <input type="checkbox"
                                           name="pb_lti_activities[<?php echo $h5p_id; ?>][include]"
                                           value="1"
                                           <?php checked($is_included, true); ?>
                                           class="pb-lti-activity-checkbox">
                                </th>
                                <td><?php echo $activity['position']; ?></td>
                                <td>
                                    <strong><?php echo esc_html($activity['title']); ?></strong>
                                    <div class="row-actions">
                                        <span class="h5p-id">ID: <?php echo $h5p_id; ?></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html($activity['library']); ?></td>
                                <td><?php echo $activity['max_score']; ?></td>
                                <td>
                                    <select name="pb_lti_activities[<?php echo $h5p_id; ?>][scheme]"
                                            class="small-text pb-lti-scheme-select">
                                        <option value="best" <?php selected($scheme, 'best'); ?>>
                                            🏆 Best Attempt
                                        </option>
                                        <option value="average" <?php selected($scheme, 'average'); ?>>
                                            📊 Average
                                        </option>
                                        <option value="first" <?php selected($scheme, 'first'); ?>>
                                            1️⃣ First Attempt
                                        </option>
                                        <option value="last" <?php selected($scheme, 'last'); ?>>
                                            🔄 Last Attempt
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number"
                                           name="pb_lti_activities[<?php echo $h5p_id; ?>][weight]"
                                           value="<?php echo esc_attr($weight); ?>"
                                           min="0"
                                           max="10"
                                           step="0.1"
                                           class="small-text pb-lti-weight-input"
                                           <?php echo $aggregate_method !== 'weighted' ? 'disabled' : ''; ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Summary -->
                <div class="pb-lti-section pb-lti-summary">
                    <h4>📋 Summary</h4>
                    <ul>
                        <li>
                            <strong>Total Activities:</strong>
                            <span class="pb-lti-total-count"><?php echo count($activities); ?></span>
                        </li>
                        <li>
                            <strong>Included in Grading:</strong>
                            <span class="pb-lti-included-count">
                                <?php echo count(array_filter($config['activities'], function($a) { return $a['include']; })); ?>
                            </span>
                        </li>
                        <li>
                            <strong>Total Maximum Score:</strong>
                            <span class="pb-lti-max-score">
                                <?php echo H5PActivityDetector::get_chapter_max_score($post->ID); ?>
                            </span>
                        </li>
                    </ul>

                    <div style="margin-top: 15px;">
                        <button type="button"
                                id="pb-lti-view-results"
                                class="button button-primary"
                                data-post-id="<?php echo esc_attr($post->ID); ?>">
                            📊 View Detailed Student Results
                        </button>
                    </div>
                </div>

                <!-- Sync Existing Grades -->
                <div class="pb-lti-section pb-lti-sync">
                    <h4>🔄 Sync Existing Grades</h4>
                    <p class="description">
                        If students completed H5P activities before this grading configuration was enabled,
                        you can retroactively send their scores to the LMS gradebook.
                    </p>
                    <button type="button"
                            id="pb-lti-sync-existing-grades"
                            class="button button-secondary"
                            data-post-id="<?php echo esc_attr($post->ID); ?>">
                        🔄 Sync Existing Grades to LMS
                    </button>
                    <span class="pb-lti-sync-spinner spinner" style="float: none; margin-left: 10px;"></span>
                    <div id="pb-lti-sync-results" class="notice" style="display: none; margin-top: 15px;"></div>
                    <p class="description" style="margin-top: 10px;">
                        <strong>Note:</strong> Only grades for students who previously accessed this chapter via LTI will be synced.
                        Students who accessed directly (not through LMS) will be skipped.
                    </p>
                </div>

                <!-- Help Text -->
                <div class="pb-lti-section pb-lti-help">
                    <h4>ℹ️ How It Works</h4>
                    <ol>
                        <li><strong>Enable grading</strong> for this chapter using the toggle above.</li>
                        <li><strong>Select activities</strong> to include in the final grade calculation.</li>
                        <li><strong>Choose grading scheme</strong> for each activity:
                            <ul>
                                <li><strong>Best Attempt:</strong> Uses highest score across all attempts</li>
                                <li><strong>Average:</strong> Calculates mean of all attempts</li>
                                <li><strong>First Attempt:</strong> Only uses the first attempt score</li>
                                <li><strong>Last Attempt:</strong> Only uses the most recent attempt</li>
                            </ul>
                        </li>
                        <li><strong>Set aggregation method</strong> to combine multiple activity scores.</li>
                        <li>When students complete activities, scores are <strong>automatically sent to the LMS gradebook</strong> via LTI AGS.</li>
                    </ol>
                    <p>
                        <strong>Note:</strong> Grades are only sent for students who access this chapter via an LTI launch from your LMS.
                    </p>
                </div>
            </div>
        </div>

        <!-- Results Viewer Modal -->
        <div id="pb-lti-results-modal" style="display:none;">
            <div class="pb-lti-modal-content">
                <div class="pb-lti-modal-header">
                    <h2>📊 H5P Chapter Results: <span class="pb-lti-modal-title"></span></h2>
                    <button type="button" class="pb-lti-modal-close">&times;</button>
                </div>
                <div class="pb-lti-modal-body">
                    <div id="pb-lti-modal-loading" style="text-align: center; padding: 40px;">
                        <span class="spinner is-active" style="float: none;"></span>
                        <p>Loading results...</p>
                    </div>
                    <div id="pb-lti-modal-error" style="display: none;" class="notice notice-error"></div>
                    <div id="pb-lti-modal-data" style="display: none;">
                        <div id="pb-lti-results-list-view">
                            <div class="pb-lti-modal-controls" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; padding: 12px; border: 1px solid #e5e7eb; border-radius: 4px;">
                                <div class="pb-lti-search-box" style="flex: 1; margin-right: 20px;">
                                    <input type="text" id="pb-lti-search-input" placeholder="🔍 Search by name or email..." style="width: 100%; max-width: 400px; padding: 8px 12px;">
                                </div>
                                <div class="pb-lti-pagination-controls" style="display: flex; align-items: center; gap: 10px;">
                                    <span>Rows:</span>
                                    <select id="pb-lti-per-page" style="width: 70px;">
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                    <div class="pb-lti-page-nav" style="display: flex; align-items: center; gap: 5px;">
                                        <button type="button" class="button pb-lti-prev-page" disabled>&larr;</button>
                                        <span id="pb-lti-page-info">Page 1 of 1</span>
                                        <button type="button" class="button pb-lti-next-page" disabled>&rarr;</button>
                                    </div>
                                </div>
                            </div>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width: 25%;">Student</th>
                                        <th style="width: 25%;">Email</th>
                                        <th style="width: 20%;">Calculated Score</th>
                                        <th style="width: 15%;">Grade</th>
                                        <th style="width: 15%; text-align:center;">Details</th>
                                    </tr>
                                </thead>
                                <tbody id="pb-lti-results-table-body"></tbody>
                            </table>
                        </div>
                        <div id="pb-lti-student-detail-view" style="display: none;">
                            <div style="margin-bottom: 20px;">
                                <button type="button" class="button button-secondary pb-lti-back-to-list">
                                    &larr; Back to Student List
                                </button>
                                <span style="margin-left: 15px; font-weight: 600; font-size: 1.1em; vertical-align: middle;">
                                    Student Details: <span id="pb-lti-detail-student-name"></span>
                                </span>
                            </div>
                            <div id="pb-lti-student-detail-content"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .pb-lti-h5p-results-wrapper {
                padding: 15px;
            }
            .pb-lti-section {
                margin-bottom: 25px;
                padding-bottom: 20px;
                border-bottom: 1px solid #ddd;
            }
            .pb-lti-section:last-child {
                border-bottom: none;
            }
            .pb-lti-toggle {
                display: flex;
                align-items: center;
                gap: 10px;
                cursor: pointer;
            }
            .pb-lti-toggle input[type="checkbox"] {
                width: 20px;
                height: 20px;
            }
            .pb-lti-activity-row.disabled {
                opacity: 0.5;
            }
            .pb-lti-summary {
                background: #f0f9ff;
                padding: 15px;
                border-radius: 5px;
                border: 1px solid #bae6fd;
            }
            .pb-lti-summary ul {
                margin: 10px 0 0 20px;
            }
            .pb-lti-help {
                background: #fffbeb;
                padding: 15px;
                border-radius: 5px;
                border: 1px solid #fef3c7;
            }
            .pb-lti-help ol {
                margin-left: 20px;
            }
            .pb-lti-help ul {
                margin-left: 40px;
                list-style-type: disc;
            }
            .pb-lti-sync {
                background: #f0fdf4;
                padding: 15px;
                border-radius: 5px;
                border: 1px solid #bbf7d0;
            }
            .pb-lti-sync button {
                margin-top: 10px;
            }
            #pb-lti-sync-results {
                padding: 10px;
                margin-top: 15px;
            }
            #pb-lti-sync-results ul {
                margin-left: 20px;
                list-style-type: disc;
            }
            #pb-lti-sync-results details {
                margin-top: 10px;
                padding: 10px;
                background: rgba(0,0,0,0.05);
                border-radius: 3px;
            }

            /* Modal Styles */
            #pb-lti-results-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            }
            .pb-lti-modal-content {
                background: white;
                width: 80%;
                max-width: 1200px;
                height: 80vh;
                border-radius: 8px;
                display: flex;
                flex-direction: column;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            }
            .pb-lti-modal-header {
                padding: 15px 25px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #f8f9fa;
                border-radius: 8px 8px 0 0;
            }
            .pb-lti-modal-header h2 {
                margin: 0;
            }
            .pb-lti-modal-close {
                border: none;
                background: none;
                font-size: 28px;
                cursor: pointer;
                color: #555;
            }
            .pb-lti-modal-close:hover {
                color: #000;
            }
            .pb-lti-modal-body {
                flex: 1;
                overflow-y: auto;
                padding: 25px;
            }
            .pb-lti-student-details {
                margin-top: 10px;
                background: #fff;
                padding: 15px;
                border: 1px solid #eee;
                border-radius: 4px;
            }
            .pb-lti-activity-summary {
                margin-bottom: 10px;
                padding: 8px;
                border-bottom: 1px dashed #eee;
            }
            .pb-lti-activity-summary:last-child {
                border-bottom: none;
            }
            .pb-lti-badge {
                padding: 3px 8px;
                background: #e2e8f0;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
            }
            .pb-lti-badge-best { background: #dcfce7; color: #166534; }
            .pb-lti-badge-average { background: #dbeafe; color: #1e40af; }
            .pb-lti-badge-first { background: #fef3c7; color: #92400e; }
            .pb-lti-badge-last { background: #f3e8ff; color: #6b21a8; }
            .pb-lti-sync-history h4 {
                margin: 20px 0 10px;
                border-bottom: 2px solid #334155;
                padding-bottom: 5px;
                color: #334155;
            }
            .pb-lti-sync-history table td {
                padding: 10px;
            }
            .pb-lti-student-details h4 {
                margin: 20px 0 10px;
                padding-bottom: 7px;
                border-bottom: 2px solid #2271b1;
                color: #1d2327;
                font-size: 1.1em;
            }
            .pb-lti-student-details h4:first-of-type {
                margin-top: 0;
            }
            .pb-lti-back-to-list {
                font-weight: 600 !important;
            }
            #pb-lti-detail-student-name {
                color: #2271b1;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Toggle grading configuration
            $('.pb-lti-enable-grading').on('change', function() {
                $('.pb-lti-grading-config').toggle(this.checked);
            });

            // Select all activities
            $('#pb-lti-select-all').on('change', function() {
                $('.pb-lti-activity-checkbox').prop('checked', this.checked);
                updateSummary();
            });

            // Update summary when checkboxes change
            $('.pb-lti-activity-checkbox').on('change', function() {
                updateSummary();
            });

            // Enable/disable weight inputs based on aggregate method
            $('select[name="pb_lti_h5p_aggregate"]').on('change', function() {
                const isWeighted = $(this).val() === 'weighted';
                $('.pb-lti-weight-input').prop('disabled', !isWeighted);
            });

            function updateSummary() {
                const checked = $('.pb-lti-activity-checkbox:checked').length;
                $('.pb-lti-included-count').text(checked);
            }

            // Sync existing grades AJAX handler
            $('#pb-lti-sync-existing-grades').on('click', function() {
                const $button = $(this);
                const $spinner = $('.pb-lti-sync-spinner');
                const $results = $('#pb-lti-sync-results');
                const postId = $button.data('post-id');

                // Confirm before syncing
                if (!confirm('This will sync all existing H5P grades for this chapter to the LMS gradebook. Continue?')) {
                    return;
                }

                // Show loading state
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $results.hide().removeClass('notice-success notice-error notice-warning');

                // Make AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pb_lti_sync_existing_grades',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('pb_lti_sync_grades'); ?>'
                    },
                    success: function(response) {
                        $spinner.removeClass('is-active');
                        $button.prop('disabled', false);

                        if (response.success) {
                            $results.addClass('notice-success')
                                   .html('<p><strong>✅ Success:</strong> ' + response.data.message + '</p>')
                                   .show();

                            // Show detailed results if available
                            if (response.data.results) {
                                const r = response.data.results;
                                const details = '<ul>' +
                                    '<li>Successfully synced: ' + r.success + '</li>' +
                                    '<li>Skipped (no LTI context): ' + r.skipped + '</li>' +
                                    '<li>Failed: ' + r.failed + '</li>' +
                                    '</ul>';
                                $results.find('p').append(details);

                                if (r.errors && r.errors.length > 0) {
                                    $results.find('p').append(
                                        '<details style="margin-top: 10px;">' +
                                        '<summary>View Errors</summary>' +
                                        '<ul><li>' + r.errors.join('</li><li>') + '</li></ul>' +
                                        '</details>'
                                    );
                                }
                            }
                        } else {
                            $results.addClass('notice-error')
                                   .html('<p><strong>❌ Error:</strong> ' + response.data.message + '</p>')
                                   .show();
                        }
                    },
                    error: function(xhr, status, error) {
                        $spinner.removeClass('is-active');
                        $button.prop('disabled', false);
                        $results.addClass('notice-error')
                               .html('<p><strong>❌ Error:</strong> ' + error + '</p>')
                               .show();
                    }
                });
            });

            // Results Viewer Modal Handlers
            let allResults = [];
            let currentPage = 1;
            let rowsPerPage = 10;
            let currentSearch = '';

            function renderResults() {
                const $tableBody = $('#pb-lti-results-table-body');
                $tableBody.empty();

                // 1. Filter results based on search
                let filtered = allResults;
                if (currentSearch) {
                    const search = currentSearch.toLowerCase();
                    filtered = allResults.filter(user => 
                        (user.display_name && user.display_name.toLowerCase().includes(search)) ||
                        (user.user_email && user.user_email.toLowerCase().includes(search))
                    );
                }

                // 2. Handle Pagination Calculation
                const totalRows = filtered.length;
                const totalPages = Math.ceil(totalRows / rowsPerPage) || 1;
                
                // Ensure current page is valid
                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;

                const start = (currentPage - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                const pageData = filtered.slice(start, end);

                // 3. Update UI Controls
                $('#pb-lti-page-info').text(`Page ${currentPage} of ${totalPages}`);
                $('.pb-lti-prev-page').prop('disabled', currentPage === 1);
                $('.pb-lti-next-page').prop('disabled', currentPage === totalPages);

                // 4. Render Rows
                if (pageData.length === 0) {
                    $tableBody.append('<tr><td colspan="5" style="text-align:center; padding: 20px;">No matching student results found.</td></tr>');
                    return;
                }

                pageData.forEach(function(user) {
                    let detailsHtml = '<div class="pb-lti-student-details">';
                    
                    detailsHtml += '<h4>Specific H5P Activity Results</h4>';
                    Object.values(user.activities).forEach(function(activity) {
                        const badgeClass = 'pb-lti-badge-' + activity.grading_scheme;
                        detailsHtml += `<div class="pb-lti-activity-summary">
                            <strong>${activity.title} (ID: ${activity.id})</strong><br>
                            <span class="pb-lti-badge ${badgeClass}">${activity.grading_scheme.toUpperCase()}</span>
                            Score: ${activity.calculated_score} / ${activity.max_score} | 
                            Entries in Result DB: ${activity.attempts.length}
                        </div>`;
                    });
                    
                    detailsHtml += '<div class="pb-lti-sync-history"><h4>Submission/Sync History (Attempts)</h4>';
                    if (user.history && user.history.length > 0) {
                        detailsHtml += '<table class="wp-list-table widefat striped" style="margin-top:10px;">';
                        detailsHtml += '<thead><tr><th>Time</th><th>Aggregated Score sent to LMS</th></tr></thead><tbody>';
                        user.history.forEach(function(h) {
                            const scoreText = h.score !== null ? `${h.score} / ${h.max_score}` : 'N/A (Legacy entry)';
                            detailsHtml += `<tr>
                                <td>${h.finished}</td>
                                <td><strong>${scoreText}</strong></td>
                            </tr>`;
                        });
                        detailsHtml += '</tbody></table>';
                    } else {
                        detailsHtml += '<p>No sync events found in results log.</p>';
                    }
                    detailsHtml += '</div>';
                    detailsHtml += '</div>';

                    const row = `<tr>
                        <td><strong>${user.display_name}</strong></td>
                        <td>${user.user_email}</td>
                        <td>${user.total_calculated_score} / ${user.total_max}</td>
                        <td><strong>${Math.round(user.total_percentage)}%</strong></td>
                        <td style="text-align: center;">
                            <button type="button" class="button button-link pb-lti-show-details" 
                                    title="View Detailed Student Results"
                                    data-student-name="${user.display_name}">
                                <span class="dashicons dashicons-visibility" style="font-size: 20px; width: 20px; height: 20px; vertical-align: middle;"></span>
                            </button>
                            <div class="pb-lti-student-details-html" style="display:none;">${detailsHtml}</div>
                        </td>
                    </tr>`;
                    $tableBody.append(row);
                });
            }

            // Results Viewer Modal Handlers
            $('#pb-lti-view-results').on('click', function() {
                const postId = $(this).data('post-id');
                const $modal = $('#pb-lti-results-modal');
                const $title = $('.pb-lti-modal-title');
                const $loading = $('#pb-lti-modal-loading');
                const $error = $('#pb-lti-modal-error');
                const $data = $('#pb-lti-modal-data');

                // Reset modal state
                $title.text($('#title').val() || 'Chapter ' + postId);
                $loading.show();
                $error.hide();
                $data.hide();
                $modal.css('display', 'flex'); 

                // Fetch data via AJAX
                $.post(ajaxurl, {
                    action: 'qb_lti_get_h5p_results',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('pb_lti_h5p_results_nonce'); ?>'
                }, function(response) {
                    $loading.hide();
                    
                    if (response.success) {
                        allResults = response.data.results || [];
                        currentPage = 1;
                        currentSearch = '';
                        $('#pb-lti-search-input').val('');
                        renderResults();
                        $data.fadeIn();
                    } else {
                        $error.text(response.data.message || 'Unknown error fetching results').show();
                    }
                }).fail(function() {
                    $loading.hide();
                    $error.text('Network error. Failed to fetch results.').show();
                });
            });

            // Pagination & Search Events
            $('#pb-lti-search-input').on('input', function() {
                currentSearch = $(this).val();
                currentPage = 1;
                renderResults();
            });

            // Prevent Form Submission on Enter in search box
            $('#pb-lti-search-input').on('keydown', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    return false;
                }
            });

            $('#pb-lti-per-page').on('change', function() {
                rowsPerPage = parseInt($(this).val());
                currentPage = 1;
                renderResults();
            });

            $('.pb-lti-prev-page').on('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    renderResults();
                }
            });

            $('.pb-lti-next-page').on('click', function() {
                currentPage++;
                renderResults();
            });

            // Handle clicking the Eye icon (Student Detail View)
            $(document).on('click', '.pb-lti-show-details', function(e) {
                e.preventDefault();
                const studentName = $(this).data('student-name');
                const detailsHtml = $(this).siblings('.pb-lti-student-details-html').html();
                
                $('#pb-lti-detail-student-name').text(studentName);
                $('#pb-lti-student-detail-content').html(detailsHtml);
                
                $('#pb-lti-results-list-view').hide();
                $('#pb-lti-student-detail-view').fadeIn();
            });

            // Handle Back to List button
            $(document).on('click', '.pb-lti-back-to-list', function() {
                $('#pb-lti-student-detail-view').hide();
                $('#pb-lti-results-list-view').fadeIn();
            });

            // Reset view when opening the modal again
            $('#pb-lti-view-results').on('click', function() {
                $('#pb-lti-student-detail-view').hide();
                $('#pb-lti-results-list-view').show();
            });

            // Close modal
            $('.pb-lti-modal-close').on('click', function() {
                $('#pb-lti-results-modal').hide();
            });

            // Close on click outside (more robust)
            $('#pb-lti-results-modal').on('mousedown', function(e) {
                if ($(e.target).is('#pb-lti-results-modal')) {
                    $(this).hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Save meta box data
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     */
    public static function save_meta_box($post_id, $post) {
        // Security checks
        if (!isset($_POST['pb_lti_h5p_results_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['pb_lti_h5p_results_nonce'], 'pb_lti_h5p_results')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!in_array($post->post_type, ['chapter', 'front-matter', 'back-matter'])) {
            return;
        }

        // Prepare configuration data
        $config = [
            'enabled' => isset($_POST['pb_lti_h5p_grading_enabled']),
            'aggregate' => isset($_POST['pb_lti_h5p_aggregate']) ?
                          sanitize_text_field($_POST['pb_lti_h5p_aggregate']) : 'sum',
            'activities' => []
        ];

        if (isset($_POST['pb_lti_activities']) && is_array($_POST['pb_lti_activities'])) {
            foreach ($_POST['pb_lti_activities'] as $h5p_id => $activity_data) {
                $config['activities'][(int)$h5p_id] = [
                    'include' => isset($activity_data['include']),
                    'scheme' => sanitize_text_field($activity_data['scheme'] ?? 'best'),
                    'weight' => floatval($activity_data['weight'] ?? 1.0)
                ];
            }
        }

        // Save configuration
        H5PResultsManager::save_configuration($post_id, $config);

        // Log the configuration
        error_log('[PB-LTI H5P Results] Saved configuration for post ' . $post_id . ': ' . json_encode($config));
    }
}
