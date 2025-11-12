<?php
/**
 * Plugin Name: B2Brouter for WooCommerce
 * Plugin URI: https://b2brouter.com
 * Description: Generate and send electronic invoices from WooCommerce orders using B2Brouter
 * Version: 1.0.0
 * Author: B2Brouter
 * Author URI: https://b2brouter.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: b2brouter-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
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
 */
class B2Brouter_WooCommerce {

    /**
     * Instance of this class
     * @var B2Brouter_WooCommerce
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Load plugin classes
        $this->load_dependencies();

        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize hooks
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init_hooks'));
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once B2BROUTER_WC_PLUGIN_DIR . 'includes/class-b2brouter-settings.php';
        require_once B2BROUTER_WC_PLUGIN_DIR . 'includes/class-b2brouter-invoice-generator.php';
        require_once B2BROUTER_WC_PLUGIN_DIR . 'includes/class-b2brouter-admin.php';
        require_once B2BROUTER_WC_PLUGIN_DIR . 'includes/class-b2brouter-order-handler.php';

        // Initialize classes
        B2Brouter_Settings::get_instance();
        B2Brouter_Admin::get_instance();
        B2Brouter_Order_Handler::get_instance();
    }

    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Add any initialization hooks here
    }

    /**
     * Load plugin textdomain
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
