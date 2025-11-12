<?php
/**
 * WooCommerce Order Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class B2Brouter_Order_Handler {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Automatic invoice generation on order completed
        add_action('woocommerce_order_status_completed', array($this, 'maybe_generate_invoice_automatic'));

        // Add meta box to order admin
        add_action('add_meta_boxes', array($this, 'add_invoice_meta_box'));

        // Add invoice column to orders list
        add_filter('manage_edit-shop_order_columns', array($this, 'add_invoice_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_invoice_column'), 20, 2);

        // Add bulk action for generating invoices
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_action'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_action'), 10, 3);
        add_action('admin_notices', array($this, 'bulk_action_notices'));
    }

    /**
     * Maybe generate invoice automatically
     */
    public function maybe_generate_invoice_automatic($order_id) {
        $settings = B2Brouter_Settings::get_instance();

        // Check if automatic mode is enabled
        if ($settings->get_invoice_mode() !== 'automatic') {
            return;
        }

        // Check if API key is configured
        if (!$settings->is_api_key_configured()) {
            return;
        }

        // Check if invoice already exists
        $generator = B2Brouter_Invoice_Generator::get_instance();
        if ($generator->has_invoice($order_id)) {
            return;
        }

        // Generate invoice
        $generator->generate_invoice($order_id);
    }

    /**
     * Add invoice meta box to order admin
     */
    public function add_invoice_meta_box() {
        add_meta_box(
            'b2brouter_invoice',
            __('B2Brouter Invoice', 'b2brouter-woocommerce'),
            array($this, 'render_invoice_meta_box'),
            'shop_order',
            'side',
            'default'
        );

        // WooCommerce HPOS compatibility
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') &&
            Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            add_meta_box(
                'b2brouter_invoice',
                __('B2Brouter Invoice', 'b2brouter-woocommerce'),
                array($this, 'render_invoice_meta_box'),
                'woocommerce_page_wc-orders',
                'side',
                'default'
            );
        }
    }

    /**
     * Render invoice meta box
     */
    public function render_invoice_meta_box($post_or_order) {
        $order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order($post_or_order->ID);
        $order_id = $order->get_id();

        $settings = B2Brouter_Settings::get_instance();
        $generator = B2Brouter_Invoice_Generator::get_instance();

        $has_invoice = $generator->has_invoice($order_id);
        $invoice_id = $generator->get_invoice_id($order_id);

        ?>
        <div class="b2brouter-invoice-meta-box">
            <?php if ($has_invoice): ?>
                <p class="b2brouter-invoice-status">
                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                    <?php esc_html_e('Invoice Generated', 'b2brouter-woocommerce'); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Invoice ID:', 'b2brouter-woocommerce'); ?></strong>
                    <br><?php echo esc_html($invoice_id); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Invoice Number:', 'b2brouter-woocommerce'); ?></strong>
                    <br><?php echo esc_html($order->get_meta('_b2brouter_invoice_number')); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Generated Date:', 'b2brouter-woocommerce'); ?></strong>
                    <br><?php echo esc_html($order->get_meta('_b2brouter_invoice_date')); ?>
                </p>
            <?php else: ?>
                <p class="b2brouter-invoice-status">
                    <span class="dashicons dashicons-warning" style="color: #f0b849;"></span>
                    <?php esc_html_e('Invoice Not Generated', 'b2brouter-woocommerce'); ?>
                </p>

                <?php if (!$settings->is_api_key_configured()): ?>
                    <p class="description">
                        <?php esc_html_e('API key not configured.', 'b2brouter-woocommerce'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=b2brouter')); ?>">
                            <?php esc_html_e('Configure now', 'b2brouter-woocommerce'); ?>
                        </a>
                    </p>
                <?php else: ?>
                    <p>
                        <button type="button"
                                class="button button-primary b2brouter-generate-invoice"
                                data-order-id="<?php echo esc_attr($order_id); ?>">
                            <?php esc_html_e('Generate Invoice', 'b2brouter-woocommerce'); ?>
                        </button>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Click to manually generate an invoice for this order.', 'b2brouter-woocommerce'); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <p>
                <a href="https://b2brouter.com/invoices" target="_blank" class="button button-secondary">
                    <?php esc_html_e('View in B2Brouter', 'b2brouter-woocommerce'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Add invoice column to orders list
     */
    public function add_invoice_column($columns) {
        $new_columns = array();

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            // Add invoice column after order number
            if ($key === 'order_number') {
                $new_columns['b2brouter_invoice'] = __('Invoice', 'b2brouter-woocommerce');
            }
        }

        return $new_columns;
    }

    /**
     * Render invoice column
     */
    public function render_invoice_column($column, $post_id) {
        if ($column !== 'b2brouter_invoice') {
            return;
        }

        $order = wc_get_order($post_id);
        if (!$order) {
            return;
        }

        $generator = B2Brouter_Invoice_Generator::get_instance();
        $has_invoice = $generator->has_invoice($post_id);

        if ($has_invoice) {
            echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="' . esc_attr__('Invoice generated', 'b2brouter-woocommerce') . '"></span>';
        } else {
            echo '<span class="dashicons dashicons-minus" style="color: #ddd;" title="' . esc_attr__('No invoice', 'b2brouter-woocommerce') . '"></span>';
        }
    }

    /**
     * Add bulk action
     */
    public function add_bulk_action($actions) {
        $actions['b2brouter_generate_invoices'] = __('Generate B2Brouter Invoices', 'b2brouter-woocommerce');
        return $actions;
    }

    /**
     * Handle bulk action
     */
    public function handle_bulk_action($redirect_to, $action, $post_ids) {
        if ($action !== 'b2brouter_generate_invoices') {
            return $redirect_to;
        }

        $generator = B2Brouter_Invoice_Generator::get_instance();
        $success_count = 0;
        $error_count = 0;

        foreach ($post_ids as $post_id) {
            $result = $generator->generate_invoice($post_id);
            if ($result['success']) {
                $success_count++;
            } else {
                $error_count++;
            }
        }

        $redirect_to = add_query_arg(array(
            'b2brouter_bulk_success' => $success_count,
            'b2brouter_bulk_error' => $error_count,
        ), $redirect_to);

        return $redirect_to;
    }

    /**
     * Show bulk action notices
     */
    public function bulk_action_notices() {
        if (!isset($_GET['b2brouter_bulk_success']) && !isset($_GET['b2brouter_bulk_error'])) {
            return;
        }

        $success_count = isset($_GET['b2brouter_bulk_success']) ? intval($_GET['b2brouter_bulk_success']) : 0;
        $error_count = isset($_GET['b2brouter_bulk_error']) ? intval($_GET['b2brouter_bulk_error']) : 0;

        if ($success_count > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo sprintf(
                _n(
                    '%d invoice generated successfully.',
                    '%d invoices generated successfully.',
                    $success_count,
                    'b2brouter-woocommerce'
                ),
                $success_count
            );
            echo '</p></div>';
        }

        if ($error_count > 0) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo sprintf(
                _n(
                    '%d invoice failed to generate.',
                    '%d invoices failed to generate.',
                    $error_count,
                    'b2brouter-woocommerce'
                ),
                $error_count
            );
            echo '</p></div>';
        }
    }
}
