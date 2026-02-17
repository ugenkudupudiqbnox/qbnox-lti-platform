<?php
namespace PB_LTI\Services;

/**
 * ContentService - Query Pressbooks books, chapters, and content
 *
 * Pressbooks uses WordPress multisite where:
 * - Each book = a separate site in the network
 * - Content within books = custom post types (chapter, front-matter, back-matter, part)
 */
class ContentService {

    /**
     * Get all books in the Pressbooks network
     *
     * @return array Array of book objects with id, name, url
     */
    public static function get_all_books() {
        if (!is_multisite()) {
            return [];
        }

        $sites = get_sites([
            'number' => 100,
            'orderby' => 'registered',
            'order' => 'DESC'
        ]);

        $books = [];
        foreach ($sites as $site) {
            // Skip main site (network root)
            if ($site->blog_id == 1) {
                continue;
            }

            switch_to_blog($site->blog_id);

            $books[] = [
                'id' => $site->blog_id,
                'title' => get_bloginfo('name'),
                'url' => get_site_url($site->blog_id),
                'path' => trim($site->path, '/'),
                'description' => get_bloginfo('description')
            ];

            restore_current_blog();
        }

        return $books;
    }

    /**
     * Get book structure (parts, chapters, front/back matter)
     *
     * @param int $blog_id Book ID (site ID)
     * @return array Book structure with chapters organized by parts
     */
    public static function get_book_structure($blog_id) {
        if (!is_multisite() || !$blog_id) {
            return [];
        }

        switch_to_blog($blog_id);

        $structure = [
            'book_info' => [
                'id' => $blog_id,
                'title' => get_bloginfo('name'),
                'url' => get_site_url($blog_id)
            ],
            'front_matter' => [],
            'parts' => [],
            'chapters' => [],
            'back_matter' => []
        ];

        // Shared query args to respect Pressbooks visibility settings
        $visibility_args = [
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'post_status' => 'publish',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_pb_show_web',
                    'value' => '0',
                    'compare' => '!='
                ],
                [
                    'key' => '_pb_show_web',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ];

        // Get front matter
        $front_matter = get_posts(array_merge($visibility_args, [
            'post_type' => 'front-matter'
        ]));

        foreach ($front_matter as $post) {
            $structure['front_matter'][] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'type' => 'front-matter'
            ];
        }

        // Get parts (optional organizational structure)
        $parts = get_posts(array_merge($visibility_args, [
            'post_type' => 'part'
        ]));

        foreach ($parts as $part) {
            $structure['parts'][] = [
                'id' => $part->ID,
                'title' => $part->post_title,
                'chapters' => []
            ];
        }

        // Get chapters
        $chapters = get_posts(array_merge($visibility_args, [
            'post_type' => 'chapter'
        ]));

        foreach ($chapters as $chapter) {
            $chapter_data = [
                'id' => $chapter->ID,
                'title' => $chapter->post_title,
                'url' => get_permalink($chapter->ID),
                'type' => 'chapter'
            ];

            // Try to organize chapters by part
            $part_id = get_post_meta($chapter->ID, 'pb_part', true);
            if ($part_id && isset($structure['parts'])) {
                foreach ($structure['parts'] as &$part) {
                    if ($part['id'] == $part_id) {
                        $part['chapters'][] = $chapter_data;
                        continue 2;
                    }
                }
            }

            // If no part, add to root chapters
            $structure['chapters'][] = $chapter_data;
        }

        // Get back matter
        $back_matter = get_posts(array_merge($visibility_args, [
            'post_type' => 'back-matter'
        ]));

        foreach ($back_matter as $post) {
            $structure['back_matter'][] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'type' => 'back-matter'
            ];
        }

        restore_current_blog();

        return $structure;
    }

    /**
     * Get content item details for Deep Linking response
     *
     * @param int $blog_id Book ID
     * @param int $post_id Content ID (optional, defaults to book home)
     * @return array Content item with title, url, description
     */
    public static function get_content_item($blog_id, $post_id = null) {
        if (!is_multisite() || !$blog_id) {
            return null;
        }

        switch_to_blog($blog_id);

        if ($post_id) {
            $post = get_post($post_id);
            if (!$post) {
                restore_current_blog();
                return null;
            }

            $item = [
                'type' => 'ltiResourceLink',
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'text' => wp_trim_words($post->post_content, 30)
            ];

            // Explicit Grading Control: Only request a lineItem if grading is enabled AND H5P exists
            $grading_enabled = get_post_meta($post_id, '_lti_h5p_grading_enabled', true);
            $has_h5p = false;
            
            if ($grading_enabled) {
                $activities = H5PActivityDetector::find_h5p_activities($post_id);
                $has_h5p = !empty($activities);
            }

            if ($grading_enabled && $has_h5p) {
                $item['lineItem'] = [
                    'scoreMaximum' => 100,
                    'label' => $post->post_title,
                    'resourceId' => 'pb_chapter_' . $blog_id . '_' . $post_id,
                    'tag' => 'pressbooks-lti'
                ];
            }
        } else {
            // Whole book
            $item = [
                'type' => 'ltiResourceLink',
                'title' => get_bloginfo('name'),
                'url' => get_site_url($blog_id),
                'text' => get_bloginfo('description')
            ];
        }

        restore_current_blog();

        return $item;
    }
}
