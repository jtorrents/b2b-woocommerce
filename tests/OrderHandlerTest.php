<?php
/**
 * Tests for Order_Handler class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Order_Handler;
use B2Brouter\WooCommerce\Settings;
use B2Brouter\WooCommerce\Invoice_Generator;

/**
 * Order_Handler test case
 *
 * @since 1.0.0
 */
class OrderHandlerTest extends TestCase {

    /**
     * Mock Settings instance
     *
     * @var Settings
     */
    private $mock_settings;

    /**
     * Mock Invoice_Generator instance
     *
     * @var Invoice_Generator
     */
    private $mock_invoice_generator;

    /**
     * Order_Handler instance
     *
     * @var Order_Handler
     */
    private $handler;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Reset global state
        global $wp_actions, $wp_filters, $wp_meta_boxes, $mock_orders;
        $wp_actions = array();
        $wp_filters = array();
        $wp_meta_boxes = array();
        $mock_orders = array();

        // Create mocks
        $this->mock_settings = $this->createMock(Settings::class);
        $this->mock_invoice_generator = $this->createMock(Invoice_Generator::class);

        // Create handler
        $this->handler = new Order_Handler($this->mock_settings, $this->mock_invoice_generator);
    }

    /**
     * Test that Order_Handler can be instantiated
     *
     * @return void
     */
    public function test_can_be_instantiated() {
        $this->assertInstanceOf(Order_Handler::class, $this->handler);
    }

    /**
     * Test that WordPress hooks are registered
     *
     * @return void
     */
    public function test_registers_wordpress_hooks() {
        global $wp_actions, $wp_filters;

        // Check actions
        $this->assertArrayHasKey('woocommerce_order_status_completed', $wp_actions);
        $this->assertArrayHasKey('add_meta_boxes', $wp_actions);
        $this->assertArrayHasKey('manage_shop_order_posts_custom_column', $wp_actions);
        $this->assertArrayHasKey('admin_notices', $wp_actions);

        // Check filters
        $this->assertArrayHasKey('manage_edit-shop_order_columns', $wp_filters);
        $this->assertArrayHasKey('bulk_actions-edit-shop_order', $wp_filters);
        $this->assertArrayHasKey('handle_bulk_actions-edit-shop_order', $wp_filters);
    }

    /**
     * Test maybe_generate_invoice_automatic when mode is automatic
     *
     * @return void
     */
    public function test_maybe_generate_invoice_automatic_generates_when_enabled() {
        global $mock_orders;

        $order = new WC_Order(200);
        $mock_orders[200] = $order;

        $this->mock_settings->method('get_invoice_mode')
                           ->willReturn('automatic');
        $this->mock_settings->method('is_api_key_configured')
                           ->willReturn(true);
        $this->mock_invoice_generator->method('has_invoice')
                                    ->willReturn(false);
        $this->mock_invoice_generator->expects($this->once())
                                    ->method('generate_invoice')
                                    ->with(200);

        $this->handler->maybe_generate_invoice_automatic(200);

        unset($mock_orders[200]);
    }

    /**
     * Test maybe_generate_invoice_automatic when mode is manual
     *
     * @return void
     */
    public function test_maybe_generate_invoice_automatic_skips_when_manual_mode() {
        $this->mock_settings->method('get_invoice_mode')
                           ->willReturn('manual');

        $this->mock_invoice_generator->expects($this->never())
                                    ->method('generate_invoice');

        $this->handler->maybe_generate_invoice_automatic(200);
    }

    /**
     * Test maybe_generate_invoice_automatic when API key not configured
     *
     * @return void
     */
    public function test_maybe_generate_invoice_automatic_skips_when_no_api_key() {
        $this->mock_settings->method('get_invoice_mode')
                           ->willReturn('automatic');
        $this->mock_settings->method('is_api_key_configured')
                           ->willReturn(false);

        $this->mock_invoice_generator->expects($this->never())
                                    ->method('generate_invoice');

        $this->handler->maybe_generate_invoice_automatic(200);
    }

    /**
     * Test maybe_generate_invoice_automatic when invoice already exists
     *
     * @return void
     */
    public function test_maybe_generate_invoice_automatic_skips_when_invoice_exists() {
        $this->mock_settings->method('get_invoice_mode')
                           ->willReturn('automatic');
        $this->mock_settings->method('is_api_key_configured')
                           ->willReturn(true);
        $this->mock_invoice_generator->method('has_invoice')
                                    ->willReturn(true);

        $this->mock_invoice_generator->expects($this->never())
                                    ->method('generate_invoice');

        $this->handler->maybe_generate_invoice_automatic(200);
    }

    /**
     * Test add_invoice_meta_box registers meta box
     *
     * @return void
     */
    public function test_add_invoice_meta_box_registers_meta_box() {
        global $wp_meta_boxes;

        $this->handler->add_invoice_meta_box();

        $this->assertArrayHasKey('shop_order', $wp_meta_boxes);
        $this->assertArrayHasKey('b2brouter_invoice', $wp_meta_boxes['shop_order']);
        $this->assertEquals('B2Brouter Invoice', $wp_meta_boxes['shop_order']['b2brouter_invoice']['title']);
    }

    /**
     * Test render_invoice_meta_box with order that has invoice
     *
     * @return void
     */
    public function test_render_invoice_meta_box_shows_invoice_details() {
        global $mock_orders;

        $order = new WC_Order(201);
        $order->add_meta_data('_b2brouter_invoice_id', 'inv-123', true);
        $order->add_meta_data('_b2brouter_invoice_number', 'INV-001', true);
        $order->add_meta_data('_b2brouter_invoice_date', '2025-11-13 10:00:00', true);
        $mock_orders[201] = $order;

        $this->mock_invoice_generator->method('has_invoice')
                                    ->willReturn(true);
        $this->mock_invoice_generator->method('get_invoice_id')
                                    ->willReturn('inv-123');

        ob_start();
        $this->handler->render_invoice_meta_box($order);
        $output = ob_get_clean();

        $this->assertStringContainsString('Invoice Generated', $output);
        $this->assertStringContainsString('inv-123', $output);
        $this->assertStringContainsString('INV-001', $output);

        unset($mock_orders[201]);
    }

    /**
     * Test render_invoice_meta_box with order without invoice
     *
     * @return void
     */
    public function test_render_invoice_meta_box_shows_generate_button() {
        global $mock_orders;

        $order = new WC_Order(202);
        $mock_orders[202] = $order;

        $this->mock_invoice_generator->method('has_invoice')
                                    ->willReturn(false);
        $this->mock_settings->method('is_api_key_configured')
                           ->willReturn(true);

        ob_start();
        $this->handler->render_invoice_meta_box($order);
        $output = ob_get_clean();

        $this->assertStringContainsString('Invoice Not Generated', $output);
        $this->assertStringContainsString('Generate Invoice', $output);
        $this->assertStringContainsString('data-order-id="202"', $output);

        unset($mock_orders[202]);
    }

    /**
     * Test render_invoice_meta_box when API key not configured
     *
     * @return void
     */
    public function test_render_invoice_meta_box_shows_api_key_warning() {
        global $mock_orders;

        $order = new WC_Order(203);
        $mock_orders[203] = $order;

        $this->mock_invoice_generator->method('has_invoice')
                                    ->willReturn(false);
        $this->mock_settings->method('is_api_key_configured')
                           ->willReturn(false);

        ob_start();
        $this->handler->render_invoice_meta_box($order);
        $output = ob_get_clean();

        $this->assertStringContainsString('API key not configured', $output);
        $this->assertStringContainsString('Configure now', $output);

        unset($mock_orders[203]);
    }

    /**
     * Test add_invoice_column adds column
     *
     * @return void
     */
    public function test_add_invoice_column_adds_invoice_column() {
        $columns = array(
            'cb' => '<input type="checkbox" />',
            'order_number' => 'Order',
            'order_status' => 'Status',
        );

        $result = $this->handler->add_invoice_column($columns);

        $this->assertArrayHasKey('b2brouter_invoice', $result);
        $this->assertEquals('Invoice', $result['b2brouter_invoice']);

        // Verify it's inserted after order_number
        $keys = array_keys($result);
        $order_number_index = array_search('order_number', $keys);
        $invoice_index = array_search('b2brouter_invoice', $keys);
        $this->assertEquals($order_number_index + 1, $invoice_index);
    }

    /**
     * Test render_invoice_column with invoice
     *
     * @return void
     */
    public function test_render_invoice_column_shows_checkmark_when_invoice_exists() {
        global $mock_orders;

        $order = new WC_Order(204);
        $mock_orders[204] = $order;

        $this->mock_invoice_generator->method('has_invoice')
                                    ->willReturn(true);

        ob_start();
        $this->handler->render_invoice_column('b2brouter_invoice', 204);
        $output = ob_get_clean();

        $this->assertStringContainsString('dashicons-yes-alt', $output);
        $this->assertStringContainsString('Invoice generated', $output);

        unset($mock_orders[204]);
    }

    /**
     * Test render_invoice_column without invoice
     *
     * @return void
     */
    public function test_render_invoice_column_shows_minus_when_no_invoice() {
        global $mock_orders;

        $order = new WC_Order(205);
        $mock_orders[205] = $order;

        $this->mock_invoice_generator->method('has_invoice')
                                    ->willReturn(false);

        ob_start();
        $this->handler->render_invoice_column('b2brouter_invoice', 205);
        $output = ob_get_clean();

        $this->assertStringContainsString('dashicons-minus', $output);
        $this->assertStringContainsString('No invoice', $output);

        unset($mock_orders[205]);
    }

    /**
     * Test render_invoice_column skips wrong column
     *
     * @return void
     */
    public function test_render_invoice_column_skips_other_columns() {
        ob_start();
        $this->handler->render_invoice_column('order_status', 999);
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    /**
     * Test add_bulk_action adds action
     *
     * @return void
     */
    public function test_add_bulk_action_adds_invoice_generation() {
        $actions = array(
            'trash' => 'Move to trash',
        );

        $result = $this->handler->add_bulk_action($actions);

        $this->assertArrayHasKey('b2brouter_generate_invoices', $result);
        $this->assertEquals('Generate B2Brouter Invoices', $result['b2brouter_generate_invoices']);
    }

    /**
     * Test handle_bulk_action processes orders
     *
     * @return void
     */
    public function test_handle_bulk_action_generates_invoices() {
        $this->mock_invoice_generator->method('generate_invoice')
                                    ->willReturn(array('success' => true));

        $redirect_to = 'http://example.com/wp-admin/edit.php';
        $result = $this->handler->handle_bulk_action($redirect_to, 'b2brouter_generate_invoices', array(100, 101, 102));

        $this->assertStringContainsString('b2brouter_bulk_success=3', $result);
        $this->assertStringContainsString('b2brouter_bulk_error=0', $result);
    }

    /**
     * Test handle_bulk_action counts errors
     *
     * @return void
     */
    public function test_handle_bulk_action_counts_errors() {
        $this->mock_invoice_generator->method('generate_invoice')
                                    ->willReturnOnConsecutiveCalls(
                                        array('success' => true),
                                        array('success' => false),
                                        array('success' => true)
                                    );

        $redirect_to = 'http://example.com/wp-admin/edit.php';
        $result = $this->handler->handle_bulk_action($redirect_to, 'b2brouter_generate_invoices', array(100, 101, 102));

        $this->assertStringContainsString('b2brouter_bulk_success=2', $result);
        $this->assertStringContainsString('b2brouter_bulk_error=1', $result);
    }

    /**
     * Test handle_bulk_action skips wrong action
     *
     * @return void
     */
    public function test_handle_bulk_action_skips_other_actions() {
        $redirect_to = 'http://example.com/wp-admin/edit.php';
        $result = $this->handler->handle_bulk_action($redirect_to, 'trash', array(100));

        $this->assertEquals($redirect_to, $result);
    }

    /**
     * Test bulk_action_notices shows success
     *
     * @return void
     */
    public function test_bulk_action_notices_shows_success_message() {
        $_GET['b2brouter_bulk_success'] = '5';

        ob_start();
        $this->handler->bulk_action_notices();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice-success', $output);
        $this->assertStringContainsString('5 invoices generated successfully', $output);

        unset($_GET['b2brouter_bulk_success']);
    }

    /**
     * Test bulk_action_notices shows error
     *
     * @return void
     */
    public function test_bulk_action_notices_shows_error_message() {
        $_GET['b2brouter_bulk_error'] = '3';

        ob_start();
        $this->handler->bulk_action_notices();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice-error', $output);
        $this->assertStringContainsString('3 invoices failed to generate', $output);

        unset($_GET['b2brouter_bulk_error']);
    }

    /**
     * Test bulk_action_notices singular form
     *
     * @return void
     */
    public function test_bulk_action_notices_uses_singular_for_one() {
        $_GET['b2brouter_bulk_success'] = '1';

        ob_start();
        $this->handler->bulk_action_notices();
        $output = ob_get_clean();

        $this->assertStringContainsString('1 invoice generated successfully', $output);

        unset($_GET['b2brouter_bulk_success']);
    }

    /**
     * Test bulk_action_notices does nothing without parameters
     *
     * @return void
     */
    public function test_bulk_action_notices_skips_when_no_parameters() {
        ob_start();
        $this->handler->bulk_action_notices();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }
}
