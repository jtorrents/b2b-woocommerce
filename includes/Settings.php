<?php
/**
 * Settings Handler
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class
 *
 * Handles all plugin settings and API key management
 *
 * @since 1.0.0
 */
class Settings {

    const OPTION_API_KEY = 'b2brouter_api_key';
    const OPTION_ACCOUNT_ID = 'b2brouter_account_id';
    const OPTION_ENVIRONMENT = 'b2brouter_environment';
    const OPTION_INVOICE_MODE = 'b2brouter_invoice_mode';
    const OPTION_TRANSACTION_COUNT = 'b2brouter_transaction_count';
    const OPTION_SHOW_WELCOME = 'b2brouter_show_welcome';
    const OPTION_ACTIVATED = 'b2brouter_activated';

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Constructor
    }

    /**
     * Get API key
     *
     * @since 1.0.0
     * @return string The API key
     */
    public function get_api_key() {
        return get_option(self::OPTION_API_KEY, '');
    }

    /**
     * Set API key
     *
     * @since 1.0.0
     * @param string $api_key The API key to save
     * @return bool True on success, false on failure
     */
    public function set_api_key($api_key) {
        return update_option(self::OPTION_API_KEY, sanitize_text_field($api_key));
    }

    /**
     * Get account ID
     *
     * @since 1.0.0
     * @return string The account ID
     */
    public function get_account_id() {
        return get_option(self::OPTION_ACCOUNT_ID, '');
    }

    /**
     * Set account ID
     *
     * @since 1.0.0
     * @param string $account_id The account ID to save
     * @return bool True on success, false on failure
     */
    public function set_account_id($account_id) {
        return update_option(self::OPTION_ACCOUNT_ID, sanitize_text_field($account_id));
    }

    /**
     * Get environment (staging or production)
     *
     * @since 1.0.0
     * @return string The environment ('staging' or 'production')
     */
    public function get_environment() {
        return get_option(self::OPTION_ENVIRONMENT, 'staging');
    }

    /**
     * Set environment
     *
     * @since 1.0.0
     * @param string $environment The environment ('staging' or 'production')
     * @return bool True on success, false on failure
     */
    public function set_environment($environment) {
        if (in_array($environment, array('staging', 'production'))) {
            return update_option(self::OPTION_ENVIRONMENT, $environment);
        }
        return false;
    }

    /**
     * Get API base URL for current environment
     *
     * @since 1.0.0
     * @return string The API base URL
     */
    public function get_api_base_url() {
        $environment = $this->get_environment();

        if ($environment === 'production') {
            return 'https://api.b2brouter.net';
        }

        return 'https://api-staging.b2brouter.net';
    }

    /**
     * Get invoice mode (automatic or manual)
     *
     * @since 1.0.0
     * @return string The invoice mode ('automatic' or 'manual')
     */
    public function get_invoice_mode() {
        return get_option(self::OPTION_INVOICE_MODE, 'manual');
    }

    /**
     * Set invoice mode
     *
     * @since 1.0.0
     * @param string $mode The invoice mode ('automatic' or 'manual')
     * @return bool True on success, false on failure
     */
    public function set_invoice_mode($mode) {
        if (in_array($mode, array('automatic', 'manual'))) {
            return update_option(self::OPTION_INVOICE_MODE, $mode);
        }
        return false;
    }

    /**
     * Get transaction count
     *
     * @since 1.0.0
     * @return int The transaction count
     */
    public function get_transaction_count() {
        return (int) get_option(self::OPTION_TRANSACTION_COUNT, 0);
    }

    /**
     * Increment transaction count
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public function increment_transaction_count() {
        $count = $this->get_transaction_count();
        return update_option(self::OPTION_TRANSACTION_COUNT, $count + 1);
    }

    /**
     * Check if API key is configured
     *
     * @since 1.0.0
     * @return bool True if API key is configured, false otherwise
     */
    public function is_api_key_configured() {
        $api_key = $this->get_api_key();
        return !empty($api_key);
    }

    /**
     * Should show welcome page
     *
     * @since 1.0.0
     * @return bool True if welcome page should be shown, false otherwise
     */
    public function should_show_welcome() {
        return get_option(self::OPTION_SHOW_WELCOME, '0') === '1';
    }

    /**
     * Mark welcome page as shown
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public function mark_welcome_shown() {
        return update_option(self::OPTION_SHOW_WELCOME, '0');
    }

    /**
     * Validate API key with B2Brouter
     *
     * @since 1.0.0
     * @param string $api_key The API key to validate
     * @return array{valid: bool, message: string} Validation result
     */
    public function validate_api_key($api_key) {
        try {
            if (empty($api_key)) {
                return array(
                    'valid' => false,
                    'message' => __('API key cannot be empty', 'b2brouter-woocommerce')
                );
            }

            // Try to initialize the client
            if (!class_exists('B2BRouter\B2BRouterClient')) {
                return array(
                    'valid' => false,
                    'message' => __('B2Brouter PHP SDK not found. Please install dependencies.', 'b2brouter-woocommerce')
                );
            }

            // Create client with environment setting
            $options = array('api_base' => $this->get_api_base_url());
            $client = new \B2BRouter\B2BRouterClient($api_key, $options);

            // Call GET /accounts to validate the key and retrieve account ID
            $url = $client->getApiBase() . '/accounts?limit=1';

            $headers = array(
                'X-B2B-API-Key' => $api_key,
                'X-B2B-API-Version' => $client->getApiVersion(),
                'Accept' => 'application/json'
            );

            $response = $client->getHttpClient()->request(
                'GET',
                $url,
                $headers,
                null,
                $client->getTimeout()
            );

            // Check if request was successful
            if ($response['status'] !== 200) {
                $body = json_decode($response['body'], true);
                $error_message = isset($body['message']) ? $body['message'] : __('Invalid API key', 'b2brouter-woocommerce');

                return array(
                    'valid' => false,
                    'message' => $error_message
                );
            }

            // Parse response and extract first account ID
            $body = json_decode($response['body'], true);

            if (!isset($body['accounts']) || empty($body['accounts'])) {
                return array(
                    'valid' => false,
                    'message' => __('No accounts found for this API key', 'b2brouter-woocommerce')
                );
            }

            $first_account = $body['accounts'][0];
            $account_id = (string) $first_account['id'];

            // Store the account ID
            $this->set_account_id($account_id);

            return array(
                'valid' => true,
                'message' => sprintf(
                    __('API key is valid. Using account: %s', 'b2brouter-woocommerce'),
                    $first_account['name']
                )
            );
        } catch (\Exception $e) {
            return array(
                'valid' => false,
                'message' => sprintf(
                    __('API key validation failed: %s', 'b2brouter-woocommerce'),
                    $e->getMessage()
                )
            );
        }
    }
}
