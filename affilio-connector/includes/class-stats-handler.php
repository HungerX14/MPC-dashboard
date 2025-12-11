<?php
/**
 * Stats Handler for Affilio Connector
 *
 * @package Affilio_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class handling WordPress statistics collection
 */
class Affilio_Stats_Handler {

    /**
     * Get all statistics for the site
     *
     * @return array Statistics data matching StatsDTO expected format
     */
    public function get_all_stats(): array {
        return [
            // Required fields expected by Affilio StatsDTO
            'total_posts' => $this->count_posts(),
            'total_categories' => $this->count_categories(),
            'total_tags' => $this->count_tags(),
            'total_pages' => $this->count_pages(),
            'total_comments' => $this->count_comments(),
            'total_users' => $this->count_users(),
            'site_title' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'wordpress_version' => get_bloginfo('version'),

            // Additional useful stats
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'admin_email' => get_option('admin_email'),
            'timezone' => get_option('timezone_string') ?: 'UTC',
            'language' => get_locale(),
            'posts_per_page' => (int) get_option('posts_per_page'),
            'plugin_version' => AFFILIO_CONNECTOR_VERSION,

            // Post status breakdown
            'posts_by_status' => $this->get_posts_by_status(),

            // Recent activity
            'last_post_date' => $this->get_last_post_date(),
            'last_modified_date' => $this->get_last_modified_date(),
        ];
    }

    /**
     * Count published posts
     */
    private function count_posts(): int {
        $count = wp_count_posts('post');
        return (int) $count->publish;
    }

    /**
     * Count all categories
     */
    private function count_categories(): int {
        return (int) wp_count_terms(['taxonomy' => 'category', 'hide_empty' => false]);
    }

    /**
     * Count all tags
     */
    private function count_tags(): int {
        $count = wp_count_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]);
        return is_wp_error($count) ? 0 : (int) $count;
    }

    /**
     * Count published pages
     */
    private function count_pages(): int {
        $count = wp_count_posts('page');
        return (int) $count->publish;
    }

    /**
     * Count approved comments
     */
    private function count_comments(): int {
        $count = wp_count_comments();
        return (int) $count->approved;
    }

    /**
     * Count all users
     */
    private function count_users(): int {
        $user_count = count_users();
        return (int) $user_count['total_users'];
    }

    /**
     * Get posts count by status
     */
    private function get_posts_by_status(): array {
        $counts = wp_count_posts('post');

        return [
            'publish' => (int) $counts->publish,
            'draft' => (int) $counts->draft,
            'pending' => (int) $counts->pending,
            'private' => (int) $counts->private,
            'future' => (int) $counts->future,
            'trash' => (int) $counts->trash,
        ];
    }

    /**
     * Get the date of the last published post
     */
    private function get_last_post_date(): ?string {
        $posts = get_posts([
            'numberposts' => 1,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (empty($posts)) {
            return null;
        }

        return $posts[0]->post_date;
    }

    /**
     * Get the date of the last modified post
     */
    private function get_last_modified_date(): ?string {
        $posts = get_posts([
            'numberposts' => 1,
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        if (empty($posts)) {
            return null;
        }

        return $posts[0]->post_modified;
    }
}
