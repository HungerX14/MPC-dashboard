<?php
/**
 * Plugin Name: Affilio Connector
 * Plugin URI: https://affilio.app
 * Description: Connecte votre site WordPress a la plateforme Affilio pour la gestion centralisee de vos publications et statistiques.
 * Version: 1.0.0
 * Author: Affilio
 * Author URI: https://affilio.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: affilio-connector
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AFFILIO_CONNECTOR_VERSION', '1.0.0');
define('AFFILIO_CONNECTOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AFFILIO_CONNECTOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AFFILIO_CONNECTOR_API_NAMESPACE', 'ma-plateforme/v1');

/**
 * Main plugin class
 */
final class Affilio_Connector {

    /**
     * Single instance of the class
     */
    private static ?Affilio_Connector $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance(): Affilio_Connector {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->ensure_defaults();
    }

    /**
     * Ensure default options exist
     */
    private function ensure_defaults(): void {
        // S'assurer que l'option enabled existe et est à '1' par défaut
        if (get_option('affilio_connector_enabled') === false) {
            add_option('affilio_connector_enabled', '1');
        }

        // Générer un token si nécessaire
        $token_manager = new Affilio_Token_Manager();
        if (empty($token_manager->get_token())) {
            $token_manager->generate_token();
        }
    }

    /**
     * Load required files
     */
    private function load_dependencies(): void {
        require_once AFFILIO_CONNECTOR_PLUGIN_DIR . 'includes/class-token-manager.php';
        require_once AFFILIO_CONNECTOR_PLUGIN_DIR . 'includes/class-api-endpoints.php';
        require_once AFFILIO_CONNECTOR_PLUGIN_DIR . 'includes/class-article-handler.php';
        require_once AFFILIO_CONNECTOR_PLUGIN_DIR . 'includes/class-stats-handler.php';

        if (is_admin()) {
            require_once AFFILIO_CONNECTOR_PLUGIN_DIR . 'admin/class-admin-settings.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Initialize components
        add_action('init', [$this, 'init']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }
    }

    /**
     * Plugin activation
     */
    public function activate(): void {
        // Generate API token if not exists
        $token_manager = new Affilio_Token_Manager();
        if (empty($token_manager->get_token())) {
            $token_manager->generate_token();
        }

        // Set default options
        if (get_option('affilio_connector_enabled') === false) {
            add_option('affilio_connector_enabled', '1');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Initialize plugin
     */
    public function init(): void {
        load_plugin_textdomain('affilio-connector', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        $api_endpoints = new Affilio_API_Endpoints();
        $api_endpoints->register_routes();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu(): void {
        // Menu principal dans la sidebar
        add_menu_page(
            __('Affilio Connector', 'affilio-connector'),
            __('Affilio', 'affilio-connector'),
            'manage_options',
            'affilio-connector',
            [$this, 'render_admin_page'],
            'dashicons-chart-line',
            30
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        $admin_settings = new Affilio_Admin_Settings();
        $admin_settings->register();
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void {
        // toplevel_page_affilio-connector pour menu principal
        // settings_page_affilio-connector pour sous-menu Réglages
        if ($hook !== 'toplevel_page_affilio-connector' && $hook !== 'settings_page_affilio-connector') {
            return;
        }

        wp_enqueue_style(
            'affilio-connector-admin',
            AFFILIO_CONNECTOR_PLUGIN_URL . 'admin/css/admin.css',
            [],
            AFFILIO_CONNECTOR_VERSION
        );

        wp_enqueue_script(
            'affilio-connector-admin',
            AFFILIO_CONNECTOR_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            AFFILIO_CONNECTOR_VERSION,
            true
        );

        wp_localize_script('affilio-connector-admin', 'affilioConnector', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('affilio_connector_nonce'),
            'strings' => [
                'copied' => __('Token copie !', 'affilio-connector'),
                'regenerateConfirm' => __('Etes-vous sur de vouloir regenerer le token ? L\'ancien token ne fonctionnera plus.', 'affilio-connector'),
            ],
        ]);
    }

    /**
     * Render admin page
     */
    public function render_admin_page(): void {
        $admin_settings = new Affilio_Admin_Settings();
        $admin_settings->render();
    }
}

/**
 * Initialize plugin
 */
function affilio_connector_init(): Affilio_Connector {
    return Affilio_Connector::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'affilio_connector_init');
