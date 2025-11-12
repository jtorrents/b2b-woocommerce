<?php
/**
 * Plugin Name: B2Brouter for WooCommerce
 * Plugin URI: https://b2brouter.com
 * Description: Generate and send electronic invoices from WooCommerce orders using B2Brouter
 * Version: 1.0.0
 * Author: B2Brouter
 * Author URI: https://b2brouter.net
 * License: MIT
 * License URI: https://opensource.org/license/mit
 * Text Domain: b2brouter-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 *
 * @package B2Brouter\WooCommerce
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('B2BROUTER_WC_VERSION', '1.0.0');
define('B2BROUTER_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('B2BROUTER_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('B2BROUTER_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('B2Brouter for WooCommerce requires WooCommerce to be installed and active.', 'b2brouter-woocommerce');
        echo '</p></div>';
    });
    return;
}

// Load Composer autoloader
if (file_exists(B2BROUTER_WC_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once B2BROUTER_WC_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Main Plugin Class
 *
 * @since 1.0.0
 */
class B2Brouter_WooCommerce {

    /**
     * Instance of this class
     *
     * @since 1.0.0
     * @var B2Brouter_WooCommerce
     */
    private static $instance = null;

    /**
     * Dependency container
     *
     * @since 1.0.0
     * @var array
     */
    private $container = array();

    /**
     * Get instance
     *
     * @since 1.0.0
     * @return B2Brouter_WooCommerce
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     *
     * @since 1.0.0
     * @return void
     */
    private function init() {
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize hooks
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init_plugin'));
    }

    /**
     * Initialize plugin dependencies and classes
     *
     * @since 1.0.0
     * @return void
     */
    public function init_plugin() {
        // Initialize dependency container
        $this->init_container();

        // Initialize classes
        $this->container['settings'];
        $this->container['admin'];
        $this->container['order_handler'];
    }

    /**
     * Initialize dependency injection container
     *
     * @since 1.0.0
     * @return void
     */
    private function init_container() {
        // Register Settings (no dependencies)
        $this->container['settings'] = function() {
            return new \B2Brouter\WooCommerce\Settings();
        };

        // Register Invoice_Generator (depends on Settings)
        $this->container['invoice_generator'] = function() {
            return new \B2Brouter\WooCommerce\Invoice_Generator(
                $this->get('settings')
            );
        };

        // Register Admin (depends on Settings and Invoice_Generator)
        $this->container['admin'] = function() {
            return new \B2Brouter\WooCommerce\Admin(
                $this->get('settings'),
                $this->get('invoice_generator')
            );
        };

        // Register Order_Handler (depends on Settings and Invoice_Generator)
        $this->container['order_handler'] = function() {
            return new \B2Brouter\WooCommerce\Order_Handler(
                $this->get('settings'),
                $this->get('invoice_generator')
            );
        };
    }

    /**
     * Get service from container
     *
     * @since 1.0.0
     * @param string $key Service key
     * @return mixed Service instance
     */
    private function get($key) {
        if (!isset($this->container[$key])) {
            throw new \Exception("Service '{$key}' not found in container.");
        }

        // If it's a callable, execute it once and cache the result
        if (is_callable($this->container[$key])) {
            $this->container[$key] = call_user_func($this->container[$key]);
        }

        return $this->container[$key];
    }

    /**
     * Load plugin textdomain
     *
     * @since 1.0.0
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'b2brouter-woocommerce',
            false,
            dirname(B2BROUTER_WC_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin activation
     *
     * @since 1.0.0
     * @return void
     */
    public function activate() {
        // Set default options
        if (!get_option('b2brouter_activated')) {
            add_option('b2brouter_activated', '1');
            add_option('b2brouter_show_welcome', '1');
            add_option('b2brouter_invoice_mode', 'manual');
            add_option('b2brouter_transaction_count', 0);
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     *
     * @since 1.0.0
     * @return void
     */
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }
}

// Initialize the plugin
function b2brouter_woocommerce_init() {
    return B2Brouter_WooCommerce::get_instance();
}

// Start the plugin
b2brouter_woocommerce_init();
