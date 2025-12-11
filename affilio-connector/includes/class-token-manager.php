<?php
/**
 * Token Manager for Affilio Connector
 *
 * @package Affilio_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class handling API token generation and validation
 */
class Affilio_Token_Manager {

    /**
     * Option name for storing the token
     */
    private const TOKEN_OPTION = 'affilio_connector_api_token';

    /**
     * Token length
     */
    private const TOKEN_LENGTH = 64;

    /**
     * Get the current API token
     */
    public function get_token(): string {
        return get_option(self::TOKEN_OPTION, '');
    }

    /**
     * Generate a new API token
     */
    public function generate_token(): string {
        $token = $this->create_secure_token();

        update_option(self::TOKEN_OPTION, $token);

        return $token;
    }

    /**
     * Regenerate the API token (useful if compromised)
     */
    public function regenerate_token(): string {
        return $this->generate_token();
    }

    /**
     * Validate a provided token
     */
    public function validate_token(string $provided_token): bool {
        $stored_token = $this->get_token();

        if (empty($stored_token) || empty($provided_token)) {
            return false;
        }

        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($stored_token, $provided_token);
    }

    /**
     * Delete the API token
     */
    public function delete_token(): bool {
        return delete_option(self::TOKEN_OPTION);
    }

    /**
     * Check if a token exists
     */
    public function has_token(): bool {
        return !empty($this->get_token());
    }

    /**
     * Create a cryptographically secure token
     */
    private function create_secure_token(): string {
        // Use WordPress's built-in function if available (WP 4.4+)
        if (function_exists('wp_generate_password')) {
            // Generate a random string without special chars for URL safety
            $token = wp_generate_password(self::TOKEN_LENGTH, false, false);
        } else {
            // Fallback to PHP's random_bytes
            $token = bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
        }

        // Add a prefix for easy identification in logs
        return 'aff_' . $token;
    }

    /**
     * Get token creation/modification time
     */
    public function get_token_created_at(): ?int {
        // WordPress doesn't store option timestamps by default
        // We store it separately when generating
        return get_option('affilio_connector_token_created', null);
    }

    /**
     * Get masked token for display (shows only first and last 4 chars)
     */
    public function get_masked_token(): string {
        $token = $this->get_token();

        if (empty($token)) {
            return '';
        }

        $length = strlen($token);

        if ($length <= 12) {
            return str_repeat('*', $length);
        }

        return substr($token, 0, 8) . str_repeat('*', $length - 12) . substr($token, -4);
    }
}
