<?php
namespace PB_LTI\Controllers;

use PB_LTI\Services\H5PResultsManager;
use PB_LTI\Services\H5PActivityDetector;

class ResultsController {

    public static function init() {
        // Register query var to prevent WordPress from stripping it during canonical redirects
        add_filter('query_vars', function($vars) {
            $vars[] = 'pb_lti_results_viewer';
            return $vars;
        });

        // Trigger Results Viewer if the query param is present
        // Use 'wp' hook which is earlier than 'template_redirect' but still has full user auth context
        add_action('wp', [self::class, 'register_frontend_viewer'], 0);
    }

    /**
     * Frontend listener for ?pb_lti_results_viewer=1
     */
    public static function register_frontend_viewer() {
        // Handle both $_GET and $_REQUEST for flexibility
        $is_viewer = (isset($_GET['pb_lti_results_viewer']) && $_GET['pb_lti_results_viewer'] === '1' ) || 
                     (isset($_REQUEST['pb_lti_results_viewer']) && $_REQUEST['pb_lti_results_viewer'] === '1');

        if (!$is_viewer) {
            return;
        }

        error_log('[PB-LTI] Results Viewer triggered - user logged in: ' . (is_user_logged_in() ? 'yes' : 'no') . ' (blog: ' . get_current_blog_id() . ')');

        // MUST ensure we are in the context of the child blog if it's a multisite results request
        // Sometimes the redirect lands on the primary blog but has the ?pb_lti_results_viewer=1 param
        // In that case we need to resolve which blog the user should be seeing.
        
        // However, if we are in template_redirect, WordPress has usually already mapped the blog.

        // Must be logged in via WP (which LaunchController handles)
        if (!is_user_logged_in()) {
            error_log('[PB-LTI] Viewer trigger failed: User not logged in. Cookie mismatch?');
            wp_die('You must be logged in to view results. Please ensure third-party cookies are enabled in your browser settings.');
        }

        error_log('[PB-LTI] Rendering results page for blog ' . get_current_blog_id() . ' for user ' . get_current_user_id());
        self::render_results_page();
        exit;
    }

    /**
     * Minimalist standalone results page for use inside LMS iframes
     */
    private static function render_results_page() {
        $blog_id = get_current_blog_id();
        $book_title = get_bloginfo('name');
        $chapters = self::get_grading_chapters($blog_id);

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>📊 Results Viewer | <?php echo esc_html($book_title); ?></title>
            <?php 
            wp_enqueue_style('common');
            wp_enqueue_style('forms');
            wp_enqueue_style('buttons');
            wp_enqueue_style('list-tables');
            wp_enqueue_style('dashicons');
            wp_head(); 
            ?>
            <style>
                body { background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; }
                .pb-lti-viewer-container { max-width: 1000px; margin: 30px auto; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 8px; }
                .pb-lti-viewer-header { background: #2271b1; color: #fff; padding: 25px 30px; border-radius: 8px 8px 0 0; }
                .pb-lti-viewer-header h1 { margin: 0; font-size: 20px; }
                .pb-lti-viewer-content { padding: 25px; }
                
                .chapter-selector-wrapper { 
                    background: #f8fafc; 
                    padding: 20px; 
                    border-radius: 6px; 
                    border: 1px solid #e2e8f0; 
                    margin-bottom: 25px;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .chapter-selector-wrapper label { font-weight: 600; color: #334155; }
                #chapter-select { flex: 1; max-width: 400px; padding: 8px; border-radius: 4px; border: 1px solid #d1d5db; }
                
                .results-display-area { min-height: 200px; position: relative; }
                
                #detail-view { background: #fff; padding: 10px; }
                
                /* Import shared styles */
                <?php if (file_exists(PB_LTI_PATH . 'assets/css/results-viewer.css')): ?>
                    <?php include PB_LTI_PATH . 'assets/css/results-viewer.css'; ?>
                <?php endif; ?>
                
                #search-input { width: 100%; max-width: 350px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; }
                .spinner { display: inline-block; visibility: visible; float: none; vertical-align: middle; }
            </style>
        </head>
        <body class="wp-core-ui">
            <div class="pb-lti-viewer-container">
                <div class="pb-lti-viewer-header">
                    <h1>📊 Pressbooks Results Viewer</h1>
                    <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 13px;">Book: <?php echo esc_html($book_title); ?></p>
                </div>
                <div class="pb-lti-viewer-content">
                    <?php if (empty($chapters)): ?>
                        <div style="text-align:center; padding: 40px;">
                            <span class="dashicons dashicons-warning" style="font-size: 40px; width:40px; height:40px; color:#94a3b8;"></span>
                            <h2>No grading chapters found</h2>
                            <p>Enabled LTI H5P Grading for chapters to see student scores here.</p>
                        </div>
                    <?php else: ?>
                        <div class="chapter-selector-wrapper">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <label for="chapter-select">Select Chapter:</label>
                                <select id="chapter-select">
                                    <?php foreach ($chapters as $index => $chapter): ?>
                                        <option value="<?php echo $chapter['id']; ?>" data-title="<?php echo esc_attr($chapter['title']); ?>" <?php selected($index, 0); ?>>
                                            <?php echo esc_html($chapter['title']); ?> (<?php echo $chapter['h5p_count']; ?> activities)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="activity-selector-container" style="display:none; align-items:center; gap:10px; border-left: 2px solid #e2e8f0; padding-left: 15px;">
                                <label for="global-activity-select">Select Activity:</label>
                                <select id="global-activity-select" style="min-width:250px;">
                                    <!-- Populated by JS -->
                                </select>
                            </div>

                            <span id="loading-indicator" style="display:none;"><span class="spinner is-active"></span></span>
                        </div>

                        <div class="results-display-area">
                            <div id="results-error" style="display:none; color:#dc2626; padding:20px; background:#fef2f2; border-radius:6px; margin-bottom:20px;"></div>
                            
                            <div id="results-data" style="display:none;">
                                <div id="list-view">
                                    <div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center; background:#f9fafb; padding:15px; border-radius:6px; border:1px solid #e5e7eb;">
                                        <input type="text" id="search-input" placeholder="🔍 Search students by name or email...">
                                        <div style="display:flex; gap:10px; align-items:center;">
                                            <button type="button" class="button button-secondary prev-page">&larr;</button>
                                            <span id="page-info" style="font-weight:600;"></span>
                                            <button type="button" class="button button-secondary next-page">&rarr;</button>
                                            <select id="per-page" style="width:70px;"><option value="10">10</option><option value="25">25</option><option value="50">50</option></select>
                                        </div>
                                    </div>
                                    <table class="wp-list-table widefat striped fixed">
                                        <thead><tr><th style="width:25%;">Student</th><th style="width:25%;">Email</th><th style="width:15%;">Score</th><th style="width:15%;">Grade</th><th style="width:10%; text-align:center;">Details</th></tr></thead>
                                        <tbody id="results-table-body"></tbody>
                                    </table>
                                </div>
                                
                                <div id="detail-view" style="display:none;">
                                    <div style="margin-bottom:20px; display:flex; align-items:center; gap:15px;">
                                        <button type="button" class="button back-to-list">&larr; Back to List</button>
                                        <h3 id="detail-student-name" style="margin:0; color:#2271b1;"></h3>
                                    </div>
                                    <div id="detail-content"></div>
                                </div>
                            </div>

                            <div id="empty-results-state" style="text-align:center; padding:60px 20px; color:#64748b;">
                                <span class="dashicons dashicons-clipboard" style="font-size:48px; width:48px; height:48px; margin-bottom:15px; opacity:0.5;"></span>
                                <p>Please select a chapter from the dropdown above to view student scores.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
            jQuery(document).ready(function($) {
                let allResults = [], page = 1, limit = 10, search = '', selectedActivityId = null, instructorMode = false;

                $('#chapter-select').on('change', function() {
                    const postId = $(this).val();
                    if (!postId) {
                        $('#results-data').hide();
                        $('#activity-selector-container').hide();
                        $('#empty-results-state').show();
                        return;
                    }

                    $('#loading-indicator').show();
                    $('#results-error').hide();
                    $('#empty-results-state').hide();
                    $('#detail-view').hide();
                    $('#list-view').show();

                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'qb_lti_get_h5p_results',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('pb_lti_h5p_results_nonce'); ?>'
                    }, function(r) {
                        $('#loading-indicator').hide();
                        if(r.success) {
                            allResults = r.data.results || [];
                            instructorMode = r.data.is_instructor || false;
                            
                            // Populate Global Activity Selector
                            const activities = {};
                            allResults.forEach(u => {
                                Object.values(u.activities || {}).forEach(act => {
                                    activities[act.id] = act.title;
                                });
                            });

                            const $gas = $('#global-activity-select').empty();
                            if (Object.keys(activities).length > 0) {
                                Object.entries(activities).forEach(([id, title]) => {
                                    $gas.append(`<option value="act-${id}">${title}</option>`);
                                });
                                selectedActivityId = $gas.val();
                            }

                            page = 1; 
                            render();
                            $('#results-data').fadeIn();

                            // STUDENT AUTO-REDIRECTION:
                            // If not an instructor, auto-show their specific detail view
                            if (!instructorMode && allResults.length === 1) {
                                $('#list-view').hide();
                                const user = allResults[0];
                                $('#detail-student-name').text('Your Progress: ' + user.display_name);
                                $('#detail-content').html($('.details-store').first().html());
                                $('#activity-selector-container').css('display', 'flex');
                                if (selectedActivityId) {
                                    $('#detail-content').find('.' + selectedActivityId).show();
                                }
                                $('#detail-view').fadeIn();
                                $('.back-to-list').hide(); // Don't let students go back to empty list view
                            } else {
                                $('.back-to-list').show(); // Re-show for instructors
                            }
                        } else {
                            $('#results-error').text(r.data.message || 'Error').show();
                            $('#results-data').hide();
                        }
                    });
                });

                $('#global-activity-select').on('change', function() {
                    selectedActivityId = $(this).val();
                    // If we're currently in detail view, update the visibility of activity groups
                    if ($('#detail-view').is(':visible')) {
                        $('#detail-view').find('.activity-attempts-group').hide();
                        if (selectedActivityId) {
                            $('#detail-view').find('.' + selectedActivityId).fadeIn();
                        }
                    }
                    render(); // Re-render to update the display if needed
                });

                function render() {
                    let filtered = allResults.filter(u => !search || (u.display_name && u.display_name.toLowerCase().includes(search.toLowerCase())) || (u.user_email && u.user_email.toLowerCase().includes(search.toLowerCase())));
                    let total = Math.ceil(filtered.length / limit) || 1;
                    if (page > total) page = total;
                    const data = filtered.slice((page-1)*limit, page*limit);
                    
                    if (instructorMode) {
                        $('#search-input').closest('div').show();
                        $('#page-info').text(`Page ${page} of ${total}`);
                        $('.prev-page').prop('disabled', page === 1).show();
                        $('.next-page').prop('disabled', page === total).show();
                        $('#per-page').show();
                    } else {
                        $('#search-input').closest('div').hide();
                        $('.prev-page, .next-page, #page-info, #per-page').hide();
                    }

                    const $tbody = $('#results-table-body').empty();
                    if(data.length === 0) { $tbody.append('<tr><td colspan="5" style="text-align:center;">No students found.</td></tr>'); return; }

                    data.forEach(user => {
                        let detailsHtml = '<div class="pb-lti-student-details">';
                        detailsHtml += '<h4>📊 Individual Activity Attempts</h4>';
                        
                        if (Object.keys(user.activities).length === 0) {
                            detailsHtml += '<p style="color:#64748b; padding:10px;">No H5P activities found for this student.</p>';
                        } else {
                            Object.values(user.activities).forEach(act => {
                                detailsHtml += `<div class="activity-attempts-group act-${act.id}" style="display:none; margin-bottom:25px;">`;
                                detailsHtml += `<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:2px solid #e2e8f0; padding-bottom:5px;">`;
                                detailsHtml += `<span style="font-weight:600; color:#1e40af;">${act.title}</span>`;
                                detailsHtml += `<span style="background:#dbeafe; color:#1e40af; padding:2px 8px; border-radius:12px; font-size:11px; text-transform:uppercase; font-weight:bold;">${act.grading_scheme}</span>`;
                                detailsHtml += `</div>`;
                                
                                // Retrieve historical attempts from the sync log that matches this result_id
                                const activityHistory = (user.history || []).filter(h => h.result_id == act.result_id);
                                
                                if (activityHistory.length > 0) {
                                    detailsHtml += '<table class="wp-list-table widefat striped" style="border:1px solid #e2e8f0;"><thead><tr><th>Finished At</th><th>Status</th><th style="text-align:right;">Aggregated Sync</th></tr></thead><tbody>';
                                    activityHistory.forEach(att => {
                                        const scoreDisplay = (att.score !== null && att.max_score !== null) ? `<strong>${att.score}/${att.max_score}</strong>` : '(No Score)';
                                        const statusIcon = att.status === 'failed' ? '❌' : '✅';
                                        detailsHtml += `<tr><td>${att.finished}</td><td><span title="${att.status}">${statusIcon}</span></td><td style="text-align:right;">${scoreDisplay}</td></tr>`;
                                        if (att.error_message) {
                                            detailsHtml += `<tr><td colspan="3" style="color:#dc2626; font-size:11px; padding-top:0;">Error: ${att.error_message}</td></tr>`;
                                        }
                                    });
                                    detailsHtml += '</tbody></table>';
                                } else if (act.attempts && act.attempts.length > 0) {
                                    // Fallback if no sync history exists yet (showing latest H5P result)
                                    detailsHtml += '<table class="wp-list-table widefat striped" style="border:1px solid #e2e8f0;"><thead><tr><th>Last Finished</th><th style="text-align:right;">Current Score</th></tr></thead><tbody>';
                                    act.attempts.forEach(att => {
                                        detailsHtml += `<tr><td>${att.finished}</td><td style="text-align:right;"><strong>${att.score} / ${att.max_score}</strong></td></tr>`;
                                    });
                                    detailsHtml += '</tbody></table>';
                                } else {
                                    detailsHtml += '<div style="text-align:center; padding:20px; color:#64748b; background:#fff; border:1px solid #e2e8f0; border-radius:4px;">No attempts recorded.</div>';
                                }
                                detailsHtml += '</div>';
                            });
                        }
                        detailsHtml += '</div>';

                        $tbody.append(`<tr>
                            <td><strong>${user.display_name}</strong></td>
                            <td>${user.user_email}</td>
                            <td>${user.total_calculated_score}/${user.total_max}</td>
                            <td><strong>${Math.round(user.total_percentage)}%</strong></td>
                            <td style="text-align:center;"><button class="button v-details" data-name="${user.display_name}">👁️</button><div class="details-store" style="display:none;">${detailsHtml}</div></td>
                        </tr>`);
                    });
                }

                $(document).on('click', '.v-details', function() { 
                    $('#detail-student-name').text($(this).data('name')); 
                    const $content = $(this).siblings('.details-store').html();
                    $('#detail-content').html($content); 
                    $('#list-view').hide(); 
                    $('#detail-view').fadeIn();
                    
                    // Show global activity selector when entering detail view
                    if ($('#global-activity-select option').length > 0) {
                        $('#activity-selector-container').css('display', 'flex');
                    }
                    
                    // Show the globally selected activity in the detail view
                    if (selectedActivityId) {
                        $('#detail-content').find('.' + selectedActivityId).show();
                    }
                });
                
                $('.back-to-list').click(() => { 
                    $('#detail-view').hide(); 
                    $('#activity-selector-container').hide(); 
                    $('#list-view').fadeIn(); 
                });

                $('#search-input').on('keydown', e => { if(e.which===13) e.preventDefault(); });
                $('#search-input').on('input', function() { search = $(this).val(); page = 1; render(); });
                $('#per-page').change(function() { limit = parseInt($(this).val()); page = 1; render(); });
                $('.prev-page').click(() => { if(page>1){page--; render();} });
                $('.next-page').click(() => { page++; render(); });

                // Auto-load first chapter on page load
                if ($('#chapter-select').val()) {
                    $('#chapter-select').trigger('change');
                }
            });
            </script>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }

    private static function get_grading_chapters($blog_id) {
        switch_to_blog($blog_id);
        $posts = get_posts(['post_type'=>['chapter','front-matter','back-matter'], 'posts_per_page'=>-1, 'meta_key'=>'_lti_h5p_grading_enabled', 'meta_value'=>'1']);
        $res = [];
        foreach($posts as $p) {
            $acts = H5PActivityDetector::find_h5p_activities($p->ID);
            $res[] = ['id'=>$p->ID, 'title'=>$p->post_title, 'h5p_count'=>count($acts)];
        }
        restore_current_blog();

        // If no chapters found on current blog AND we are super admins, look for other blogs
        // This handles cases where Moodle Admins launch onto the main site context
        if (empty($res) && is_super_admin()) {
            $blogs = get_sites(['site_id' => 1, 'number' => 20]);
            foreach ($blogs as $blog) {
                if ($blog->blog_id == $blog_id) continue;
                switch_to_blog($blog->blog_id);
                $other_posts = get_posts(['post_type'=>['chapter','front-matter','back-matter'], 'posts_per_page'=>-1, 'meta_key'=>'_lti_h5p_grading_enabled', 'meta_value'=>'1']);
                foreach($other_posts as $p) {
                    $acts = H5PActivityDetector::find_h5p_activities($p->ID);
                    $res[] = ['id'=>$p->ID, 'title' => '[' . get_bloginfo('name') . '] ' . $p->post_title, 'h5p_count'=>count($acts)];
                }
                restore_current_blog();
                if (count($res) > 50) break; // Limit the global selector size
            }
        }
        return $res;
    }
}
