<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment by loading Composer autoloader
 * and defining WordPress functions that are used by the plugin.
 *
 * @package B2Brouter\WooCommerce\Tests
 */

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define ABSPATH constant for WordPress compatibility
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// Mock WordPress functions used by the plugin
if (!function_exists('get_option')) {
    /**
     * Mock get_option function
     *
     * @param string $option Option name
     * @param mixed $default Default value
     * @return mixed Option value
     */
    function get_option($option, $default = false) {
        static $options = array();
        return isset($options[$option]) ? $options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    /**
     * Mock update_option function
     *
     * @param string $option Option name
     * @param mixed $value Option value
     * @return bool Success
     */
    function update_option($option, $value) {
        static $options = array();
        $options[$option] = $value;
        return true;
    }
}

if (!function_exists('add_option')) {
    /**
     * Mock add_option function
     *
     * @param string $option Option name
     * @param mixed $value Option value
     * @return bool Success
     */
    function add_option($option, $value) {
        return update_option($option, $value);
    }
}

if (!function_exists('sanitize_text_field')) {
    /**
     * Mock sanitize_text_field function
     *
     * @param string $str String to sanitize
     * @return string Sanitized string
     */
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('__')) {
    /**
     * Mock translation function
     *
     * @param string $text Text to translate
     * @param string $domain Text domain
     * @return string Translated text
     */
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    /**
     * Mock esc_html function
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    /**
     * Mock esc_attr function
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    /**
     * Mock esc_url function
     *
     * @param string $url URL to escape
     * @return string Escaped URL
     */
    function esc_url($url) {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('current_time')) {
    /**
     * Mock current_time function
     *
     * @param string $type Type of time ('mysql' or 'timestamp')
     * @return string|int Current time
     */
    function current_time($type) {
        if ($type === 'mysql') {
            return date('Y-m-d H:i:s');
        }
        return time();
    }
}

if (!function_exists('wc_get_order')) {
    /**
     * Mock wc_get_order function
     *
     * @param int $order_id Order ID
     * @return false Returns false by default (override in tests)
     */
    function wc_get_order($order_id) {
        return false;
    }
}

// Mock B2Brouter SDK classes for testing
if (!class_exists('B2BRouter\Client\B2BRouterClient')) {
    /**
     * Mock B2BRouter Client for testing
     *
     * This allows tests to run without the actual SDK installed.
     * In real tests, you should mock this class using PHPUnit.
     */
    class B2BRouterClient {
        public $invoices;

        public function __construct($api_key) {
            $this->invoices = new class {
                public function create($data) {
                    return ['id' => 'test-invoice-id', 'number' => 'INV-001'];
                }
                public function send($id) {
                    return true;
                }
                public function all($params) {
                    return [];
                }
            };
        }
    }
}
