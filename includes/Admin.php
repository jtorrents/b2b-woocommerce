<?php
/**
 * Admin Interface Handler
 *
 * @package B2Brouter\WooCommerce
 * @since 1.0.0
 */

namespace B2Brouter\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 *
 * Handles all admin interface, settings pages, and AJAX operations
 *
 * @since 1.0.0
 */
class Admin {

    /**
     * Settings instance
     *
     * @since 1.0.0
     * @var Settings
     */
    private $settings;

    /**
     * Invoice Generator instance
     *
     * @since 1.0.0
     * @var Invoice_Generator
     */
    private $invoice_generator;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @param Settings $settings Settings instance
     * @param Invoice_Generator $invoice_generator Invoice generator instance
     */
    public function __construct(Settings $settings, Invoice_Generator $invoice_generator) {
        $this->settings = $settings;
        $this->invoice_generator = $invoice_generator;

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Show welcome page on activation
        add_action('admin_init', array($this, 'maybe_show_welcome'));

        // Add settings link on plugins page
        add_filter('plugin_action_links_' . B2BROUTER_WC_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));

        // Admin bar counter
        add_action('admin_bar_menu', array($this, 'add_admin_bar_counter'), 100);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Handle AJAX requests
        add_action('wp_ajax_b2brouter_validate_api_key', array($this, 'ajax_validate_api_key'));
        add_action('wp_ajax_b2brouter_generate_invoice', array($this, 'ajax_generate_invoice'));
    }

    /**
     * Add admin menu
     *
     * @since 1.0.0
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __('B2Brouter', 'b2brouter-woocommerce'),
            __('B2Brouter', 'b2brouter-woocommerce'),
            'manage_options',
            'b2brouter',
            array($this, 'render_settings_page'),
            'dashicons-media-document',
            56
        );

        add_submenu_page(
            'b2brouter',
            __('Settings', 'b2brouter-woocommerce'),
            __('Settings', 'b2brouter-woocommerce'),
            'manage_options',
            'b2brouter',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'b2brouter',
            __('Welcome', 'b2brouter-woocommerce'),
            __('Welcome', 'b2brouter-woocommerce'),
            'manage_options',
            'b2brouter-welcome',
            array($this, 'render_welcome_page')
        );
    }

    /**
     * Register settings
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        register_setting('b2brouter_settings', 'b2brouter_api_key');
        register_setting('b2brouter_settings', 'b2brouter_invoice_mode');
    }

    /**
     * Maybe show welcome page on activation
     *
     * @since 1.0.0
     * @return void
     */
    public function maybe_show_welcome() {
        if ($this->settings->should_show_welcome()) {
            $this->settings->mark_welcome_shown();
            wp_safe_redirect(admin_url('admin.php?page=b2brouter-welcome'));
            exit;
        }
    }

    /**
     * Add plugin action links
     *
     * @since 1.0.0
     * @param array $links Existing plugin action links
     * @return array Modified plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=b2brouter'),
            __('Settings', 'b2brouter-woocommerce')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Add admin bar counter
     *
     * @since 1.0.0
     * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance
     * @return void
     */
    public function add_admin_bar_counter($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $count = $this->settings->get_transaction_count();

        $wp_admin_bar->add_node(array(
            'id'    => 'b2brouter-counter',
            'title' => sprintf(
                '<span class="ab-icon dashicons dashicons-media-document"></span> <span class="ab-label">%s</span>',
                sprintf(__('Invoices: %d', 'b2brouter-woocommerce'), $count)
            ),
            'href'  => admin_url('admin.php?page=b2brouter'),
            'meta'  => array(
                'title' => __('B2Brouter Invoices', 'b2brouter-woocommerce'),
            ),
        ));
    }

    /**
     * Enqueue admin scripts
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        // Load on B2Brouter pages, order edit pages, and WooCommerce HPOS order pages
        $allowed_hooks = array('post.php', 'edit.php', 'woocommerce_page_wc-orders');

        if (strpos($hook, 'b2brouter') === false && !in_array($hook, $allowed_hooks)) {
            return;
        }

        wp_enqueue_style(
            'b2brouter-admin',
            B2BROUTER_WC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            B2BROUTER_WC_VERSION
        );

        wp_enqueue_script(
            'b2brouter-admin',
            B2BROUTER_WC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            B2BROUTER_WC_VERSION,
            true
        );

        wp_localize_script('b2brouter-admin', 'b2brouterAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('b2brouter_nonce'),
            'strings' => array(
                'validating' => __('Validating...', 'b2brouter-woocommerce'),
                'generating' => __('Generating invoice...', 'b2brouter-woocommerce'),
                'success' => __('Success!', 'b2brouter-woocommerce'),
                'error' => __('Error', 'b2brouter-woocommerce'),
            ),
        ));
    }

    /**
     * AJAX: Validate API key
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_validate_api_key() {
        check_ajax_referer('b2brouter_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'b2brouter-woocommerce')));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        $result = $this->settings->validate_api_key($api_key);

        if ($result['valid']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Generate invoice
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_generate_invoice() {
        check_ajax_referer('b2brouter_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'b2brouter-woocommerce')));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(array('message' => __('Invalid order ID', 'b2brouter-woocommerce')));
        }

        $result = $this->invoice_generator->generate_invoice($order_id);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Render welcome page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_welcome_page() {
        ?>
        <div class="wrap b2brouter-welcome">
            <h1><?php esc_html_e('Welcome to B2Brouter for WooCommerce', 'b2brouter-woocommerce'); ?></h1>

            <div class="b2brouter-welcome-content">
                <div class="b2brouter-card">
                    <h2><?php esc_html_e('Get Started with Electronic Invoicing', 'b2brouter-woocommerce'); ?></h2>

                    <p><?php esc_html_e('Thank you for installing B2Brouter for WooCommerce! This plugin allows you to automatically generate and send electronic invoices for your WooCommerce orders.', 'b2brouter-woocommerce'); ?></p>

                    <h3><?php esc_html_e('Requirements', 'b2brouter-woocommerce'); ?></h3>
                    <ul class="b2brouter-checklist">
                        <li><?php esc_html_e('An active eDocExchange subscription is required', 'b2brouter-woocommerce'); ?></li>
                        <li><?php esc_html_e('The subscription provides an API key to activate the plugin', 'b2brouter-woocommerce'); ?></li>
                        <li><?php esc_html_e('Invoices are generated and sent through B2Brouter', 'b2brouter-woocommerce'); ?></li>
                        <li><?php esc_html_e('Advanced configuration (transports, formats, taxes) is done in your B2Brouter account', 'b2brouter-woocommerce'); ?></li>
                    </ul>

                    <h3><?php esc_html_e('Next Steps', 'b2brouter-woocommerce'); ?></h3>
                    <ol class="b2brouter-steps">
                        <li><?php esc_html_e('Activate your eDocExchange subscription on B2Brouter', 'b2brouter-woocommerce'); ?></li>
                        <li><?php esc_html_e('Copy your API key from B2Brouter', 'b2brouter-woocommerce'); ?></li>
                        <li><?php esc_html_e('Return to WordPress and configure the plugin with your API key', 'b2brouter-woocommerce'); ?></li>
                    </ol>

                    <div class="b2brouter-actions">
                        <a href="https://app.b2brouter.net" class="button button-primary button-hero" target="_blank">
                            <?php esc_html_e('Go to B2Brouter - Activate Subscription', 'b2brouter-woocommerce'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=b2brouter')); ?>" class="button button-secondary button-hero">
                            <?php esc_html_e('Configure Plugin', 'b2brouter-woocommerce'); ?>
                        </a>
                    </div>
                </div>

                <div class="b2brouter-card b2brouter-info">
                    <h3><?php esc_html_e('Need Help?', 'b2brouter-woocommerce'); ?></h3>
                    <p><?php esc_html_e('Visit our documentation or contact support if you need assistance.', 'b2brouter-woocommerce'); ?></p>
                    <p>
                        <a href="https://app.b2brouter.net" target="_blank"><?php esc_html_e('Documentation', 'b2brouter-woocommerce'); ?></a> |
                        <a href="https://app.b2brouter.net" target="_blank"><?php esc_html_e('Support', 'b2brouter-woocommerce'); ?></a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings_page() {
        $api_key = $this->settings->get_api_key();
        $invoice_mode = $this->settings->get_invoice_mode();
        $transaction_count = $this->settings->get_transaction_count();
        $api_configured = $this->settings->is_api_key_configured();

        if (isset($_POST['b2brouter_save_settings']) && check_admin_referer('b2brouter_settings')) {
            // Save API key
            if (isset($_POST['b2brouter_api_key'])) {
                $new_api_key = sanitize_text_field($_POST['b2brouter_api_key']);
                $this->settings->set_api_key($new_api_key);
                $api_key = $new_api_key;
            }

            // Save invoice mode
            if (isset($_POST['b2brouter_invoice_mode'])) {
                $this->settings->set_invoice_mode(sanitize_text_field($_POST['b2brouter_invoice_mode']));
                $invoice_mode = $this->settings->get_invoice_mode();
            }

            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'b2brouter-woocommerce') . '</p></div>';
        }

        ?>
        <div class="wrap b2brouter-settings">
            <h1><?php esc_html_e('B2Brouter Settings', 'b2brouter-woocommerce'); ?></h1>

            <?php if (!$api_configured): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php esc_html_e('API key is not configured. Please enter your B2Brouter API key below.', 'b2brouter-woocommerce'); ?>
                        <a href="https://app.b2brouter.net" target="_blank"><?php esc_html_e('Get your API key', 'b2brouter-woocommerce'); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="b2brouter-form">
                <?php wp_nonce_field('b2brouter_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="b2brouter_api_key"><?php esc_html_e('API Key', 'b2brouter-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="b2brouter_api_key"
                                   name="b2brouter_api_key"
                                   value="<?php echo esc_attr($api_key); ?>"
                                   class="regular-text"
                                   placeholder="<?php esc_attr_e('Enter your B2Brouter API key', 'b2brouter-woocommerce'); ?>">
                            <button type="button" id="b2brouter_validate_key" class="button button-secondary">
                                <?php esc_html_e('Validate Key', 'b2brouter-woocommerce'); ?>
                            </button>
                            <span id="b2brouter_validation_result"></span>
                            <p class="description">
                                <?php esc_html_e('Enter your B2Brouter API key to enable invoice generation.', 'b2brouter-woocommerce'); ?>
                                <a href="https://app.b2brouter.net" target="_blank"><?php esc_html_e('Get your API key', 'b2brouter-woocommerce'); ?></a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Invoice Generation Mode', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio"
                                           name="b2brouter_invoice_mode"
                                           value="automatic"
                                           <?php checked($invoice_mode, 'automatic'); ?>>
                                    <?php esc_html_e('Automatic', 'b2brouter-woocommerce'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Generate invoice automatically when order is completed', 'b2brouter-woocommerce'); ?></p>

                                <label>
                                    <input type="radio"
                                           name="b2brouter_invoice_mode"
                                           value="manual"
                                           <?php checked($invoice_mode, 'manual'); ?>>
                                    <?php esc_html_e('Manual', 'b2brouter-woocommerce'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Generate invoice manually using a button in the order admin', 'b2brouter-woocommerce'); ?></p>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Transaction Counter', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <strong><?php echo esc_html($transaction_count); ?></strong>
                            <p class="description"><?php esc_html_e('Total number of invoices generated from this plugin', 'b2brouter-woocommerce'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('B2Brouter Account', 'b2brouter-woocommerce'); ?>
                        </th>
                        <td>
                            <a href="https://app.b2brouter.net" class="button button-secondary" target="_blank">
                                <?php esc_html_e('Access B2Brouter Account Settings', 'b2brouter-woocommerce'); ?>
                            </a>
                            <p class="description"><?php esc_html_e('Configure advanced settings like transports, formats, and taxes in your B2Brouter account.', 'b2brouter-woocommerce'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="b2brouter_save_settings" class="button button-primary">
                        <?php esc_html_e('Save Settings', 'b2brouter-woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }
}
