<?php
/**
 * Admin Settings for Affilio Connector
 *
 * @package Affilio_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class handling admin settings page
 */
class Affilio_Admin_Settings {

    /**
     * Token manager instance
     */
    private Affilio_Token_Manager $token_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->token_manager = new Affilio_Token_Manager();

        // AJAX handlers
        add_action('wp_ajax_affilio_regenerate_token', [$this, 'ajax_regenerate_token']);
        add_action('wp_ajax_affilio_test_connection', [$this, 'ajax_test_connection']);
    }

    /**
     * Register settings
     */
    public function register(): void {
        register_setting('affilio_connector_options', 'affilio_connector_enabled', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_checkbox'],
            'default' => '1',
        ]);

        register_setting('affilio_connector_options', 'affilio_connector_default_author', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        ]);

        // Settings sections
        add_settings_section(
            'affilio_connector_main',
            __('Configuration', 'affilio-connector'),
            [$this, 'render_main_section'],
            'affilio-connector'
        );

        // Settings fields
        add_settings_field(
            'affilio_connector_enabled',
            __('Activer le connecteur', 'affilio-connector'),
            [$this, 'render_enabled_field'],
            'affilio-connector',
            'affilio_connector_main'
        );

        add_settings_field(
            'affilio_connector_default_author',
            __('Auteur par defaut', 'affilio-connector'),
            [$this, 'render_author_field'],
            'affilio-connector',
            'affilio_connector_main'
        );
    }

    /**
     * Render the admin page
     */
    public function render(): void {
        $token = $this->token_manager->get_token();
        $has_token = !empty($token);
        $api_url = get_rest_url(null, AFFILIO_CONNECTOR_API_NAMESPACE);
        ?>
        <div class="wrap affilio-admin-wrap">
            <h1>
                <span class="affilio-logo">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </span>
                <?php echo esc_html__('Affilio Connector', 'affilio-connector'); ?>
            </h1>

            <div class="affilio-admin-container">
                <!-- Connection Info Card -->
                <div class="affilio-card affilio-card-primary">
                    <div class="affilio-card-header">
                        <h2><?php esc_html_e('Informations de connexion', 'affilio-connector'); ?></h2>
                        <span class="affilio-status <?php echo $has_token ? 'affilio-status-success' : 'affilio-status-warning'; ?>">
                            <?php echo $has_token ? esc_html__('Configure', 'affilio-connector') : esc_html__('Non configure', 'affilio-connector'); ?>
                        </span>
                    </div>
                    <div class="affilio-card-body">
                        <p class="affilio-description">
                            <?php esc_html_e('Utilisez ces informations dans votre plateforme Affilio pour connecter ce site.', 'affilio-connector'); ?>
                        </p>

                        <!-- Site URL -->
                        <div class="affilio-field-group">
                            <label><?php esc_html_e('URL du site', 'affilio-connector'); ?></label>
                            <div class="affilio-input-group">
                                <input type="text" readonly value="<?php echo esc_url(get_site_url()); ?>" id="affilio-site-url" class="affilio-input">
                                <button type="button" class="affilio-btn affilio-btn-secondary affilio-copy-btn" data-target="affilio-site-url">
                                    <span class="dashicons dashicons-clipboard"></span>
                                </button>
                            </div>
                        </div>

                        <!-- API Token -->
                        <div class="affilio-field-group">
                            <label><?php esc_html_e('Token API', 'affilio-connector'); ?></label>
                            <div class="affilio-input-group">
                                <input type="text" readonly value="<?php echo esc_attr($token); ?>" id="affilio-api-token" class="affilio-input affilio-token-input">
                                <button type="button" class="affilio-btn affilio-btn-secondary affilio-copy-btn" data-target="affilio-api-token">
                                    <span class="dashicons dashicons-clipboard"></span>
                                </button>
                                <button type="button" class="affilio-btn affilio-btn-warning" id="affilio-regenerate-token">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e('Regenerer', 'affilio-connector'); ?>
                                </button>
                            </div>
                            <p class="affilio-help-text">
                                <?php esc_html_e('Ce token permet a Affilio de communiquer avec votre site. Gardez-le secret.', 'affilio-connector'); ?>
                            </p>
                        </div>

                        <!-- API Endpoint Info -->
                        <div class="affilio-field-group">
                            <label><?php esc_html_e('Endpoint API', 'affilio-connector'); ?></label>
                            <div class="affilio-input-group">
                                <input type="text" readonly value="<?php echo esc_url($api_url); ?>" id="affilio-api-endpoint" class="affilio-input">
                                <button type="button" class="affilio-btn affilio-btn-secondary affilio-copy-btn" data-target="affilio-api-endpoint">
                                    <span class="dashicons dashicons-clipboard"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Test Connection -->
                        <div class="affilio-field-group">
                            <button type="button" class="affilio-btn affilio-btn-primary" id="affilio-test-connection">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e('Tester la connexion', 'affilio-connector'); ?>
                            </button>
                            <span id="affilio-test-result" class="affilio-test-result"></span>
                        </div>
                    </div>
                </div>

                <!-- Settings Card -->
                <div class="affilio-card">
                    <div class="affilio-card-header">
                        <h2><?php esc_html_e('Parametres', 'affilio-connector'); ?></h2>
                    </div>
                    <div class="affilio-card-body">
                        <form method="post" action="options.php">
                            <?php
                            settings_fields('affilio_connector_options');
                            do_settings_sections('affilio-connector');
                            submit_button(__('Enregistrer les modifications', 'affilio-connector'));
                            ?>
                        </form>
                    </div>
                </div>

                <!-- API Documentation Card -->
                <div class="affilio-card">
                    <div class="affilio-card-header">
                        <h2><?php esc_html_e('Endpoints disponibles', 'affilio-connector'); ?></h2>
                    </div>
                    <div class="affilio-card-body">
                        <table class="affilio-endpoints-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Methode', 'affilio-connector'); ?></th>
                                    <th><?php esc_html_e('Endpoint', 'affilio-connector'); ?></th>
                                    <th><?php esc_html_e('Description', 'affilio-connector'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="affilio-method affilio-method-get">GET</span></td>
                                    <td><code>/stats</code></td>
                                    <td><?php esc_html_e('Statistiques du site', 'affilio-connector'); ?></td>
                                </tr>
                                <tr>
                                    <td><span class="affilio-method affilio-method-post">POST</span></td>
                                    <td><code>/publish</code></td>
                                    <td><?php esc_html_e('Publier un article', 'affilio-connector'); ?></td>
                                </tr>
                                <tr>
                                    <td><span class="affilio-method affilio-method-get">GET</span></td>
                                    <td><code>/health</code></td>
                                    <td><?php esc_html_e('Verification de sante', 'affilio-connector'); ?></td>
                                </tr>
                                <tr>
                                    <td><span class="affilio-method affilio-method-get">GET</span></td>
                                    <td><code>/categories</code></td>
                                    <td><?php esc_html_e('Liste des categories', 'affilio-connector'); ?></td>
                                </tr>
                                <tr>
                                    <td><span class="affilio-method affilio-method-get">GET</span></td>
                                    <td><code>/tags</code></td>
                                    <td><?php esc_html_e('Liste des tags', 'affilio-connector'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render main section description
     */
    public function render_main_section(): void {
        echo '<p>' . esc_html__('Configurez les parametres du connecteur Affilio.', 'affilio-connector') . '</p>';
    }

    /**
     * Render enabled field
     */
    public function render_enabled_field(): void {
        $enabled = get_option('affilio_connector_enabled', '1');
        ?>
        <label class="affilio-toggle">
            <input type="checkbox" name="affilio_connector_enabled" value="1" <?php checked($enabled, '1'); ?>>
            <span class="affilio-toggle-slider"></span>
            <span class="affilio-toggle-label"><?php esc_html_e('Autoriser les connexions depuis Affilio', 'affilio-connector'); ?></span>
        </label>
        <?php
    }

    /**
     * Render author field
     */
    public function render_author_field(): void {
        $default_author = get_option('affilio_connector_default_author');
        $users = get_users(['role__in' => ['administrator', 'editor', 'author']]);
        ?>
        <select name="affilio_connector_default_author" class="affilio-select">
            <option value=""><?php esc_html_e('-- Selectionner un auteur --', 'affilio-connector'); ?></option>
            <?php foreach ($users as $user) : ?>
                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($default_author, $user->ID); ?>>
                    <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('L\'auteur qui sera attribue aux articles crees via Affilio.', 'affilio-connector'); ?></p>
        <?php
    }

    /**
     * Sanitize checkbox value
     */
    public function sanitize_checkbox($value): string {
        return $value === '1' ? '1' : '0';
    }

    /**
     * AJAX handler for token regeneration
     */
    public function ajax_regenerate_token(): void {
        check_ajax_referer('affilio_connector_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission refusee.', 'affilio-connector')]);
        }

        $new_token = $this->token_manager->regenerate_token();

        wp_send_json_success([
            'token' => $new_token,
            'message' => __('Token regenere avec succes.', 'affilio-connector'),
        ]);
    }

    /**
     * AJAX handler for connection test
     */
    public function ajax_test_connection(): void {
        check_ajax_referer('affilio_connector_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission refusee.', 'affilio-connector')]);
        }

        // Test if REST API is accessible
        $test_url = get_rest_url(null, AFFILIO_CONNECTOR_API_NAMESPACE . '/health');
        $token = $this->token_manager->get_token();

        $response = wp_remote_get($test_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 10,
            'sslverify' => false, // For local testing
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Erreur de connexion: %s', 'affilio-connector'),
                    $response->get_error_message()
                ),
            ]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code === 200 && isset($body['status']) && $body['status'] === 'ok') {
            wp_send_json_success([
                'message' => __('Connexion reussie ! L\'API fonctionne correctement.', 'affilio-connector'),
                'data' => $body,
            ]);
        } else {
            wp_send_json_error([
                'message' => sprintf(
                    __('Erreur API (code %d): %s', 'affilio-connector'),
                    $status_code,
                    $body['message'] ?? __('Reponse invalide', 'affilio-connector')
                ),
            ]);
        }
    }
}
