<?php
/**
 * Article Handler for Affilio Connector
 *
 * @package Affilio_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class handling article/post creation
 */
class Affilio_Article_Handler {

    /**
     * Create a new WordPress post from Affilio data
     *
     * @param array $data Article data from API request
     * @return array|WP_Error Result with post info or error
     */
    public function create_post(array $data): array|WP_Error {
        // Prepare post data
        $post_data = [
            'post_title' => $data['title'],
            'post_content' => $data['content'],
            'post_status' => $this->sanitize_status($data['status'] ?? 'draft'),
            'post_type' => 'post',
            'post_author' => $this->get_default_author(),
        ];

        // Add excerpt if provided
        if (!empty($data['excerpt'])) {
            $post_data['post_excerpt'] = $data['excerpt'];
        }

        // Insert the post
        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return new WP_Error(
                'post_creation_failed',
                sprintf(
                    __('Erreur lors de la creation de l\'article: %s', 'affilio-connector'),
                    $post_id->get_error_message()
                ),
                ['status' => 500]
            );
        }

        // Handle categories
        if (!empty($data['categories'])) {
            $this->set_post_categories($post_id, $data['categories']);
        }

        // Handle tags
        if (!empty($data['tags'])) {
            $this->set_post_tags($post_id, $data['tags']);
        }

        // Handle featured image
        if (!empty($data['featured_image'])) {
            $this->set_featured_image($post_id, $data['featured_image']);
        }

        // Get the created post
        $post = get_post($post_id);

        return [
            'success' => true,
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'status' => $post->post_status,
            'title' => $post->post_title,
            'created_at' => $post->post_date,
        ];
    }

    /**
     * Set post categories, creating them if they don't exist
     */
    private function set_post_categories(int $post_id, array $categories): void {
        $category_ids = [];

        foreach ($categories as $category_name) {
            $category_name = trim($category_name);

            if (empty($category_name)) {
                continue;
            }

            // Check if category exists
            $term = get_term_by('name', $category_name, 'category');

            if ($term) {
                $category_ids[] = $term->term_id;
            } else {
                // Create new category
                $new_term = wp_insert_term($category_name, 'category');

                if (!is_wp_error($new_term)) {
                    $category_ids[] = $new_term['term_id'];
                }
            }
        }

        if (!empty($category_ids)) {
            wp_set_post_categories($post_id, $category_ids);
        }
    }

    /**
     * Set post tags, creating them if they don't exist
     */
    private function set_post_tags(int $post_id, array $tags): void {
        $tag_names = array_map('trim', $tags);
        $tag_names = array_filter($tag_names);

        if (!empty($tag_names)) {
            // wp_set_post_tags will create tags if they don't exist
            wp_set_post_tags($post_id, $tag_names);
        }
    }

    /**
     * Set featured image from URL
     */
    private function set_featured_image(int $post_id, string $image_url): void {
        // Download and attach the image
        $attachment_id = $this->upload_image_from_url($image_url, $post_id);

        if ($attachment_id && !is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    /**
     * Upload an image from URL to WordPress media library
     */
    private function upload_image_from_url(string $url, int $post_id): int|WP_Error {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download file to temp location
        $tmp = download_url($url);

        if (is_wp_error($tmp)) {
            return $tmp;
        }

        // Get file info
        $file_array = [
            'name' => basename(parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
        ];

        // If no extension, try to determine from content type
        if (!pathinfo($file_array['name'], PATHINFO_EXTENSION)) {
            $file_array['name'] .= '.jpg';
        }

        // Upload to media library
        $attachment_id = media_handle_sideload($file_array, $post_id);

        // Clean up temp file
        if (file_exists($tmp)) {
            @unlink($tmp);
        }

        return $attachment_id;
    }

    /**
     * Sanitize post status
     */
    private function sanitize_status(string $status): string {
        $allowed_statuses = ['draft', 'publish', 'pending', 'private'];

        return in_array($status, $allowed_statuses, true) ? $status : 'draft';
    }

    /**
     * Get default author for posts
     */
    private function get_default_author(): int {
        // Try to get configured author
        $default_author = get_option('affilio_connector_default_author');

        if ($default_author) {
            return (int) $default_author;
        }

        // Fall back to first admin
        $admins = get_users(['role' => 'administrator', 'number' => 1]);

        if (!empty($admins)) {
            return $admins[0]->ID;
        }

        // Ultimate fallback to current user or user ID 1
        return get_current_user_id() ?: 1;
    }
}
