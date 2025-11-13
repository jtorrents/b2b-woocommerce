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
// Global options storage for tests
global $wp_options;
$wp_options = array();

if (!function_exists('get_option')) {
    /**
     * Mock get_option function
     *
     * @param string $option Option name
     * @param mixed $default Default value
     * @return mixed Option value
     */
    function get_option($option, $default = false) {
        global $wp_options;
        return isset($wp_options[$option]) ? $wp_options[$option] : $default;
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
        global $wp_options;
        $wp_options[$option] = $value;
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
     * @return WC_Order|false Returns mock order or false
     */
    function wc_get_order($order_id) {
        global $mock_orders;
        if (isset($mock_orders[$order_id])) {
            return $mock_orders[$order_id];
        }
        return false;
    }
}

if (!function_exists('error_log')) {
    /**
     * Mock error_log function
     *
     * @param string $message Message to log
     * @return bool Success
     */
    function error_log($message) {
        // Silence errors in tests
        return true;
    }
}

// Global storage for WordPress actions and filters
global $wp_actions, $wp_filters, $wp_meta_boxes;
$wp_actions = array();
$wp_filters = array();
$wp_meta_boxes = array();

if (!function_exists('add_action')) {
    /**
     * Mock add_action function
     *
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
     * @param int $accepted_args Accepted args
     * @return bool Success
     */
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_actions;
        if (!isset($wp_actions[$hook])) {
            $wp_actions[$hook] = array();
        }
        $wp_actions[$hook][] = array('callback' => $callback, 'priority' => $priority);
        return true;
    }
}

if (!function_exists('add_filter')) {
    /**
     * Mock add_filter function
     *
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
     * @param int $accepted_args Accepted args
     * @return bool Success
     */
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_filters;
        if (!isset($wp_filters[$hook])) {
            $wp_filters[$hook] = array();
        }
        $wp_filters[$hook][] = array('callback' => $callback, 'priority' => $priority);
        return true;
    }
}

if (!function_exists('add_meta_box')) {
    /**
     * Mock add_meta_box function
     *
     * @param string $id ID
     * @param string $title Title
     * @param callable $callback Callback
     * @param string $screen Screen
     * @param string $context Context
     * @param string $priority Priority
     * @return void
     */
    function add_meta_box($id, $title, $callback, $screen, $context = 'advanced', $priority = 'default') {
        global $wp_meta_boxes;
        if (!isset($wp_meta_boxes[$screen])) {
            $wp_meta_boxes[$screen] = array();
        }
        $wp_meta_boxes[$screen][$id] = array(
            'id' => $id,
            'title' => $title,
            'callback' => $callback,
            'context' => $context,
            'priority' => $priority
        );
    }
}

if (!function_exists('add_query_arg')) {
    /**
     * Mock add_query_arg function
     *
     * @param array $args Arguments
     * @param string $url URL
     * @return string Modified URL
     */
    function add_query_arg($args, $url) {
        $query = http_build_query($args);
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $separator . $query;
    }
}

if (!function_exists('admin_url')) {
    /**
     * Mock admin_url function
     *
     * @param string $path Path
     * @return string URL
     */
    function admin_url($path = '') {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('_n')) {
    /**
     * Mock _n (plural translation) function
     *
     * @param string $single Singular text
     * @param string $plural Plural text
     * @param int $number Number
     * @param string $domain Domain
     * @return string Text
     */
    function _n($single, $plural, $number, $domain = 'default') {
        return $number == 1 ? $single : $plural;
    }
}

if (!function_exists('esc_html_e')) {
    /**
     * Mock esc_html_e function
     *
     * @param string $text Text to escape and echo
     * @param string $domain Text domain
     * @return void
     */
    function esc_html_e($text, $domain = 'default') {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr__')) {
    /**
     * Mock esc_attr__ function
     *
     * @param string $text Text to escape
     * @param string $domain Text domain
     * @return string Escaped text
     */
    function esc_attr__($text, $domain = 'default') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('add_menu_page')) {
    /**
     * Mock add_menu_page function
     *
     * @return void
     */
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null) {
        global $wp_menu_pages;
        if (!isset($wp_menu_pages)) {
            $wp_menu_pages = array();
        }
        $wp_menu_pages[$menu_slug] = compact('page_title', 'menu_title', 'capability', 'function');
    }
}

if (!function_exists('add_submenu_page')) {
    /**
     * Mock add_submenu_page function
     *
     * @return void
     */
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '') {
        global $wp_submenu_pages;
        if (!isset($wp_submenu_pages)) {
            $wp_submenu_pages = array();
        }
        if (!isset($wp_submenu_pages[$parent_slug])) {
            $wp_submenu_pages[$parent_slug] = array();
        }
        $wp_submenu_pages[$parent_slug][$menu_slug] = compact('page_title', 'menu_title', 'capability', 'function');
    }
}

if (!function_exists('register_setting')) {
    /**
     * Mock register_setting function
     *
     * @return void
     */
    function register_setting($option_group, $option_name, $args = array()) {
        // Do nothing in tests
    }
}

if (!function_exists('wp_send_json_success')) {
    /**
     * Mock wp_send_json_success function
     *
     * @param mixed $data Data to send
     * @return void
     */
    function wp_send_json_success($data = null) {
        echo json_encode(array('success' => true, 'data' => $data));
        exit;
    }
}

if (!function_exists('wp_send_json_error')) {
    /**
     * Mock wp_send_json_error function
     *
     * @param mixed $data Data to send
     * @return void
     */
    function wp_send_json_error($data = null) {
        echo json_encode(array('success' => false, 'data' => $data));
        exit;
    }
}

if (!function_exists('check_ajax_referer')) {
    /**
     * Mock check_ajax_referer function
     *
     * @return bool Success
     */
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
        return true;
    }
}

if (!function_exists('wp_create_nonce')) {
    /**
     * Mock wp_create_nonce function
     *
     * @param string $action Action
     * @return string Nonce
     */
    function wp_create_nonce($action = -1) {
        return 'test-nonce-' . md5($action);
    }
}

if (!function_exists('wp_nonce_field')) {
    /**
     * Mock wp_nonce_field function
     *
     * @return void
     */
    function wp_nonce_field($action = -1, $name = "_wpnonce", $referer = true, $echo = true) {
        $nonce = wp_create_nonce($action);
        $output = '<input type="hidden" name="' . $name . '" value="' . $nonce . '" />';
        if ($echo) {
            echo $output;
        }
        return $output;
    }
}

if (!function_exists('settings_fields')) {
    /**
     * Mock settings_fields function
     *
     * @param string $option_group Option group
     * @return void
     */
    function settings_fields($option_group) {
        echo '<input type="hidden" name="option_page" value="' . esc_attr($option_group) . '" />';
    }
}

if (!function_exists('submit_button')) {
    /**
     * Mock submit_button function
     *
     * @param string $text Button text
     * @param string $type Button type
     * @param string $name Button name
     * @param bool $wrap Wrap in paragraph
     * @param array $other_attributes Other attributes
     * @return void
     */
    function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null) {
        if (is_null($text)) {
            $text = 'Save Changes';
        }
        $button = '<button type="submit" name="' . esc_attr($name) . '" class="button button-' . esc_attr($type) . '">' . esc_html($text) . '</button>';
        if ($wrap) {
            $button = '<p>' . $button . '</p>';
        }
        echo $button;
    }
}

if (!function_exists('current_user_can')) {
    /**
     * Mock current_user_can function
     *
     * @param string $capability Capability
     * @return bool Always true in tests
     */
    function current_user_can($capability) {
        return true;
    }
}

if (!function_exists('esc_attr_e')) {
    /**
     * Mock esc_attr_e function
     *
     * @param string $text Text to escape and echo
     * @param string $domain Text domain
     * @return void
     */
    function esc_attr_e($text, $domain = 'default') {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('selected')) {
    /**
     * Mock selected function
     *
     * @param mixed $selected Selected value
     * @param mixed $current Current value
     * @param bool $echo Echo or return
     * @return string Selected attribute
     */
    function selected($selected, $current, $echo = true) {
        $result = ($selected == $current) ? ' selected="selected"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('checked')) {
    /**
     * Mock checked function
     *
     * @param mixed $checked Checked value
     * @param mixed $current Current value
     * @param bool $echo Echo or return
     * @return string Checked attribute
     */
    function checked($checked, $current, $echo = true) {
        $result = ($checked == $current) ? ' checked="checked"' : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

// Define B2BROUTER_WC_PLUGIN_BASENAME constant for plugin action links
if (!defined('B2BROUTER_WC_PLUGIN_BASENAME')) {
    define('B2BROUTER_WC_PLUGIN_BASENAME', 'b2brouter-woocommerce/b2brouter-woocommerce.php');
}

// Global storage for mock orders
global $mock_orders;
$mock_orders = array();

// Mock WooCommerce Order class
if (!class_exists('WC_Order')) {
    /**
     * Mock WC_Order class
     */
    class WC_Order {
        private $id;
        private $data = array();
        private $meta_data = array();
        private $items = array();
        private $notes = array();

        public function __construct($order_id = 0) {
            $this->id = $order_id;
            // Default data
            $this->data = array(
                'billing_first_name' => 'John',
                'billing_last_name' => 'Doe',
                'billing_company' => '',
                'billing_email' => 'john@example.com',
                'billing_address_1' => '123 Main St',
                'billing_city' => 'New York',
                'billing_postcode' => '10001',
                'billing_country' => 'US',
                'currency' => 'USD',
                'order_number' => $order_id,
                'shipping_total' => 0,
                'shipping_tax' => 0,
            );
        }

        public function get_id() { return $this->id; }
        public function get_billing_first_name() { return $this->data['billing_first_name']; }
        public function get_billing_last_name() { return $this->data['billing_last_name']; }
        public function get_billing_company() { return $this->data['billing_company']; }
        public function get_billing_email() { return $this->data['billing_email']; }
        public function get_billing_address_1() { return $this->data['billing_address_1']; }
        public function get_billing_city() { return $this->data['billing_city']; }
        public function get_billing_postcode() { return $this->data['billing_postcode']; }
        public function get_billing_country() { return $this->data['billing_country']; }
        public function get_currency() { return $this->data['currency']; }
        public function get_order_number() { return $this->data['order_number']; }
        public function get_shipping_total() { return $this->data['shipping_total']; }
        public function get_shipping_tax() { return $this->data['shipping_tax']; }

        public function set_billing_first_name($value) { $this->data['billing_first_name'] = $value; }
        public function set_billing_last_name($value) { $this->data['billing_last_name'] = $value; }
        public function set_billing_company($value) { $this->data['billing_company'] = $value; }
        public function set_shipping_total($value) { $this->data['shipping_total'] = $value; }
        public function set_shipping_tax($value) { $this->data['shipping_tax'] = $value; }

        public function get_meta($key, $single = true) {
            return isset($this->meta_data[$key]) ? $this->meta_data[$key] : '';
        }

        public function add_meta_data($key, $value, $unique = false) {
            $this->meta_data[$key] = $value;
        }

        public function add_order_note($note) {
            $this->notes[] = $note;
        }

        public function save() {
            return true;
        }

        public function get_items($type = 'line_item') {
            return $this->items;
        }

        public function add_item($item) {
            $this->items[] = $item;
        }

        public function get_item_subtotal($item, $inc_tax = false, $round = true) {
            return 10.00; // Mock price
        }
    }
}

// Mock WC_Order_Item_Product class
if (!class_exists('WC_Order_Item_Product')) {
    class WC_Order_Item_Product {
        private $data = array();
        private $product = null;

        public function __construct($name = 'Test Product') {
            $this->data = array(
                'name' => $name,
                'quantity' => 1,
                'total' => 10.00,
                'taxes' => array('total' => array()),
            );
        }

        public function get_name() { return $this->data['name']; }
        public function get_quantity() { return $this->data['quantity']; }
        public function get_total() { return $this->data['total']; }
        public function get_taxes() { return $this->data['taxes']; }
        public function get_product() { return $this->product; }

        public function set_quantity($qty) { $this->data['quantity'] = $qty; }
        public function set_total($total) { $this->data['total'] = $total; }
        public function set_taxes($taxes) { $this->data['taxes'] = $taxes; }
    }
}

// Mock B2Brouter SDK classes for testing
// Must use eval to create namespaced class at runtime
if (!class_exists('B2BRouter\Client\B2BRouterClient')) {
    eval('
    namespace B2BRouter\Client {
        class B2BRouterClient {
            public $invoices;

            public function __construct($api_key) {
                $this->invoices = new class {
                    public function create($data) {
                        return ["id" => "test-invoice-id", "number" => "INV-001"];
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
    ');
}
