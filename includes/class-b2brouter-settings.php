<?php
/**
 * Settings Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class B2Brouter_Settings {

    private static $instance = null;

    const OPTION_API_KEY = 'b2brouter_api_key';
    const OPTION_INVOICE_MODE = 'b2brouter_invoice_mode';
    const OPTION_TRANSACTION_COUNT = 'b2brouter_transaction_count';
    const OPTION_SHOW_WELCOME = 'b2brouter_show_welcome';
    const OPTION_ACTIVATED = 'b2brouter_activated';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Constructor
    }

    /**
     * Get API key
     */
    public function get_api_key() {
        return get_option(self::OPTION_API_KEY, '');
    }

    /**
     * Set API key
     */
    public function set_api_key($api_key) {
        return update_option(self::OPTION_API_KEY, sanitize_text_field($api_key));
    }

    /**
     * Get invoice mode (automatic or manual)
     */
    public function get_invoice_mode() {
        return get_option(self::OPTION_INVOICE_MODE, 'manual');
    }

    /**
     * Set invoice mode
     */
    public function set_invoice_mode($mode) {
        if (in_array($mode, array('automatic', 'manual'))) {
            return update_option(self::OPTION_INVOICE_MODE, $mode);
        }
        return false;
    }

    /**
     * Get transaction count
     */
    public function get_transaction_count() {
        return (int) get_option(self::OPTION_TRANSACTION_COUNT, 0);
    }

    /**
     * Increment transaction count
     */
    public function increment_transaction_count() {
        $count = $this->get_transaction_count();
        return update_option(self::OPTION_TRANSACTION_COUNT, $count + 1);
    }

    /**
     * Check if API key is configured
     */
    public function is_api_key_configured() {
        $api_key = $this->get_api_key();
        return !empty($api_key);
    }

    /**
     * Should show welcome page
     */
    public function should_show_welcome() {
        return get_option(self::OPTION_SHOW_WELCOME, '0') === '1';
    }

    /**
     * Mark welcome page as shown
     */
    public function mark_welcome_shown() {
        return update_option(self::OPTION_SHOW_WELCOME, '0');
    }

    /**
     * Validate API key with B2Brouter
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
            if (!class_exists('B2BRouter\Client\B2BRouterClient')) {
                return array(
                    'valid' => false,
                    'message' => __('B2Brouter PHP SDK not found. Please install dependencies.', 'b2brouter-woocommerce')
                );
            }

            $client = new \B2BRouter\Client\B2BRouterClient($api_key);

            // Try to list invoices to validate the key
            $result = $client->invoices->all(['limit' => 1]);

            return array(
                'valid' => true,
                'message' => __('API key is valid', 'b2brouter-woocommerce')
            );
        } catch (Exception $e) {
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
