<?php
/**
 * REST API Endpoints for Affilio Connector
 *
 * @package Affilio_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class handling all REST API endpoints
 */
class Affilio_API_Endpoints {

    /**
     * Token manager instance
     */
    private Affilio_Token_Manager $token_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->token_manager = new Affilio_Token_Manager();
    }

    /**
     * Register all REST routes
     */
    public function register_routes(): void {
        // Stats endpoint - GET /wp-json/ma-plateforme/v1/stats
        register_rest_route(AFFILIO_CONNECTOR_API_NAMESPACE, '/stats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Publish endpoint - POST /wp-json/ma-plateforme/v1/publish
        register_rest_route(AFFILIO_CONNECTOR_API_NAMESPACE, '/publish', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'publish_article'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => $this->get_publish_args(),
        ]);

        // Health check endpoint - GET /wp-json/ma-plateforme/v1/health
        register_rest_route(AFFILIO_CONNECTOR_API_NAMESPACE, '/health', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'health_check'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Categories endpoint - GET /wp-json/ma-plateforme/v1/categories
        register_rest_route(AFFILIO_CONNECTOR_API_NAMESPACE, '/categories', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_categories'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Tags endpoint - GET /wp-json/ma-plateforme/v1/tags
        register_rest_route(AFFILIO_CONNECTOR_API_NAMESPACE, '/tags', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_tags'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Posts endpoint - GET /wp-json/ma-plateforme/v1/posts
        register_rest_route(AFFILIO_CONNECTOR_API_NAMESPACE, '/posts', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_posts'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => $this->get_list_args(),
        ]);

        // Single post endpoint - GET /wp-json/ma-plateforme/v1/posts/{id}
        register_rest_route(AFFILIO_CONNECTOR_API_NAMESPACE, '/posts/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_post'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        // Pages endpoint - GET /wp-json/ma-plateforme/v1/pages
        register_rest_route(AFFILIO_CONNECTOR_API_NAMESPACE, '/pages', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_pages'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => $this->get_list_args(),
        ]);

        // Single page endpoint - GET /wp-json/ma-plateforme/v1/pages/{id}
        register_rest_route(AFFILIO_CONNECTOR_API_NAMESPACE, '/pages/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_page'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    /**
     * Check request permission via Bearer token
     */
    public function check_permission(WP_REST_Request $request): bool|WP_Error {
        // Check if plugin is enabled
        if (get_option('affilio_connector_enabled') !== '1') {
            return new WP_Error(
                'affilio_disabled',
                __('Affilio Connector est desactive.', 'affilio-connector'),
                ['status' => 403]
            );
        }

        // Get Authorization header
        $auth_header = $request->get_header('Authorization');

        if (empty($auth_header)) {
            return new WP_Error(
                'missing_token',
                __('Token d\'authentification manquant.', 'affilio-connector'),
                ['status' => 401]
            );
        }

        // Extract Bearer token
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
            return new WP_Error(
                'invalid_token_format',
                __('Format de token invalide. Utilisez: Bearer <token>', 'affilio-connector'),
                ['status' => 401]
            );
        }

        $provided_token = $matches[1];

        // Validate token
        if (!$this->token_manager->validate_token($provided_token)) {
            return new WP_Error(
                'invalid_token',
                __('Token invalide.', 'affilio-connector'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Get site statistics
     */
    public function get_stats(WP_REST_Request $request): WP_REST_Response {
        $stats_handler = new Affilio_Stats_Handler();
        $stats = $stats_handler->get_all_stats();

        return new WP_REST_Response($stats, 200);
    }

    /**
     * Publish a new article
     */
    public function publish_article(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $article_handler = new Affilio_Article_Handler();

        $article_data = [
            'title' => $request->get_param('title'),
            'content' => $request->get_param('content'),
            'status' => $request->get_param('status') ?? 'draft',
            'categories' => $request->get_param('categories') ?? [],
            'tags' => $request->get_param('tags') ?? [],
            'excerpt' => $request->get_param('excerpt'),
            'featured_image' => $request->get_param('featured_image'),
        ];

        $result = $article_handler->create_post($article_data);

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 201);
    }

    /**
     * Health check endpoint
     */
    public function health_check(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'status' => 'ok',
            'plugin_version' => AFFILIO_CONNECTOR_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'timestamp' => current_time('c'),
        ], 200);
    }

    /**
     * Get all categories
     */
    public function get_categories(WP_REST_Request $request): WP_REST_Response {
        $categories = get_categories([
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        $result = array_map(function ($cat) {
            return [
                'id' => $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'count' => $cat->count,
            ];
        }, $categories);

        return new WP_REST_Response($result, 200);
    }

    /**
     * Get all tags
     */
    public function get_tags(WP_REST_Request $request): WP_REST_Response {
        $tags = get_tags([
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        $result = array_map(function ($tag) {
            return [
                'id' => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'count' => $tag->count,
            ];
        }, $tags ?: []);

        return new WP_REST_Response($result, 200);
    }

    /**
     * Get posts list
     */
    public function get_posts(WP_REST_Request $request): WP_REST_Response {
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 10;
        $status = $request->get_param('status') ?? 'any';
        $search = $request->get_param('search') ?? '';

        $args = [
            'post_type' => 'post',
            'post_status' => $status === 'any' ? ['publish', 'draft', 'pending', 'private'] : $status,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $posts = array_map([$this, 'format_post'], $query->posts);

        return new WP_REST_Response([
            'posts' => $posts,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        ], 200);
    }

    /**
     * Get single post
     */
    public function get_post(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return new WP_Error(
                'post_not_found',
                __('Article non trouve.', 'affilio-connector'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response($this->format_post($post), 200);
    }

    /**
     * Get pages list
     */
    public function get_pages(WP_REST_Request $request): WP_REST_Response {
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 10;
        $status = $request->get_param('status') ?? 'any';
        $search = $request->get_param('search') ?? '';

        $args = [
            'post_type' => 'page',
            'post_status' => $status === 'any' ? ['publish', 'draft', 'pending', 'private'] : $status,
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ];

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $pages = array_map([$this, 'format_page'], $query->posts);

        return new WP_REST_Response([
            'pages' => $pages,
            'total' => $query->found_posts,
            'pages_count' => $query->max_num_pages,
        ], 200);
    }

    /**
     * Get single page
     */
    public function get_page(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $page_id = (int) $request->get_param('id');
        $page = get_post($page_id);

        if (!$page || $page->post_type !== 'page') {
            return new WP_Error(
                'page_not_found',
                __('Page non trouvee.', 'affilio-connector'),
                ['status' => 404]
            );
        }

        return new WP_REST_Response($this->format_page($page), 200);
    }

    /**
     * Format a post for API response
     */
    private function format_post(WP_Post $post): array {
        $author = get_userdata($post->post_author);
        $featured_image_id = get_post_thumbnail_id($post->ID);
        $featured_image = $featured_image_id ? wp_get_attachment_url($featured_image_id) : null;

        // Get categories
        $categories = wp_get_post_categories($post->ID, ['fields' => 'all']);
        $formatted_categories = array_map(function ($cat) {
            return [
                'id' => $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ];
        }, $categories);

        // Get tags
        $tags = wp_get_post_tags($post->ID);
        $formatted_tags = array_map(function ($tag) {
            return [
                'id' => $tag->term_id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ];
        }, $tags ?: []);

        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'excerpt' => get_the_excerpt($post),
            'content' => $post->post_content,
            'status' => $post->post_status,
            'url' => get_permalink($post->ID),
            'featured_image' => $featured_image,
            'author' => $author ? [
                'id' => $author->ID,
                'name' => $author->display_name,
                'avatar' => get_avatar_url($author->ID, ['size' => 48]),
            ] : null,
            'categories' => $formatted_categories,
            'tags' => $formatted_tags,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'type' => 'post',
        ];
    }

    /**
     * Format a page for API response
     */
    private function format_page(WP_Post $page): array {
        $author = get_userdata($page->post_author);
        $featured_image_id = get_post_thumbnail_id($page->ID);
        $featured_image = $featured_image_id ? wp_get_attachment_url($featured_image_id) : null;

        return [
            'id' => $page->ID,
            'title' => $page->post_title,
            'slug' => $page->post_name,
            'excerpt' => get_the_excerpt($page),
            'content' => $page->post_content,
            'status' => $page->post_status,
            'url' => get_permalink($page->ID),
            'featured_image' => $featured_image,
            'author' => $author ? [
                'id' => $author->ID,
                'name' => $author->display_name,
                'avatar' => get_avatar_url($author->ID, ['size' => 48]),
            ] : null,
            'parent' => $page->post_parent,
            'menu_order' => $page->menu_order,
            'date' => $page->post_date,
            'modified' => $page->post_modified,
            'type' => 'page',
        ];
    }

    /**
     * Get list endpoint arguments schema
     */
    private function get_list_args(): array {
        return [
            'page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'per_page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'status' => [
                'required' => false,
                'type' => 'string',
                'default' => 'any',
                'enum' => ['any', 'publish', 'draft', 'pending', 'private'],
            ],
            'search' => [
                'required' => false,
                'type' => 'string',
                'default' => '',
            ],
        ];
    }

    /**
     * Get publish endpoint arguments schema
     */
    private function get_publish_args(): array {
        return [
            'title' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    return !empty(trim($value));
                },
            ],
            'content' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
            ],
            'status' => [
                'required' => false,
                'type' => 'string',
                'default' => 'draft',
                'enum' => ['draft', 'publish', 'pending', 'private'],
            ],
            'categories' => [
                'required' => false,
                'type' => 'array',
                'default' => [],
                'items' => ['type' => 'string'],
            ],
            'tags' => [
                'required' => false,
                'type' => 'array',
                'default' => [],
                'items' => ['type' => 'string'],
            ],
            'excerpt' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'featured_image' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ],
        ];
    }
}
