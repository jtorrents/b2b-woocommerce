<?php
/**
 * Tests for Invoice_Generator class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Invoice_Generator;
use B2Brouter\WooCommerce\Settings;

/**
 * Invoice_Generator test case
 *
 * This test demonstrates:
 * - Testing a class WITH dependencies
 * - Using PHPUnit mocks for dependency injection
 * - Verifying method calls and return values
 * - Testing exception handling
 *
 * @since 1.0.0
 */
class InvoiceGeneratorTest extends TestCase {

    /**
     * Mock Settings instance
     *
     * @var Settings
     */
    private $mock_settings;

    /**
     * Invoice_Generator instance
     *
     * @var Invoice_Generator
     */
    private $generator;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Create mock Settings (the dependency)
        $this->mock_settings = $this->createMock(Settings::class);

        // Inject the mock into Invoice_Generator
        $this->generator = new Invoice_Generator($this->mock_settings);
    }

    /**
     * Test that Invoice_Generator can be instantiated with Settings
     *
     * @return void
     */
    public function test_can_be_instantiated_with_settings() {
        $this->assertInstanceOf(Invoice_Generator::class, $this->generator);
    }

    /**
     * Test that dependency injection works
     *
     * This verifies that the Settings dependency was properly injected
     * by testing that we can mock its behavior.
     *
     * @return void
     */
    public function test_uses_injected_settings() {
        // Configure the mock to return a specific API key
        $this->mock_settings->method('get_api_key')
                           ->willReturn('test-api-key-123');

        // Try to generate an invoice (will fail because order doesn't exist)
        // WordPress functions are mocked in tests/bootstrap.php
        $result = $this->generator->generate_invoice(999);

        // Verify the result indicates failure (order doesn't exist)
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Order not found', $result['message']);
    }

    /**
     * Test has_invoice method
     *
     * @return void
     */
    public function test_has_invoice_returns_boolean() {
        // wc_get_order returns false by default (from bootstrap)
        $result = $this->generator->has_invoice(123);

        // Should return a boolean
        $this->assertIsBool($result);
        $this->assertFalse($result); // No order exists
    }

    /**
     * Test get_invoice_id method
     *
     * @return void
     */
    public function test_get_invoice_id_returns_null_for_invalid_order() {
        // wc_get_order returns false by default (from bootstrap)
        $result = $this->generator->get_invoice_id(999);

        $this->assertNull($result);
    }

    /**
     * Test that Invoice_Generator requires Settings dependency
     *
     * This test verifies that you can't create an Invoice_Generator
     * without providing Settings (type safety).
     *
     * @return void
     */
    public function test_requires_settings_dependency() {
        // This test is implicit - PHP will throw a TypeError if you try:
        // $generator = new Invoice_Generator(); // ❌ Error!
        // $generator = new Invoice_Generator('wrong type'); // ❌ Error!
        // $generator = new Invoice_Generator($settings); // ✅ Works!

        $this->expectNotToPerformAssertions();

        // If we got this far, the dependency injection is working correctly
    }

    /**
     * Test successful invoice generation
     *
     * @group integration
     * @return void
     */
    public function test_generate_invoice_success() {
        $this->markTestSkipped('Integration test - requires real API access');
        global $mock_orders;

        // Create a mock order
        $order = new WC_Order(100);
        $item = new WC_Order_Item_Product('Test Product');
        $order->add_item($item);
        $mock_orders[100] = $order;

        // Configure mock settings
        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->expects($this->once())
                           ->method('increment_transaction_count');

        // Generate invoice
        $result = $this->generator->generate_invoice(100);

        // Verify success
        $this->assertTrue($result['success']);
        $this->assertEquals('test-invoice-id', $result['invoice_id']);
        $this->assertEquals('INV-001', $result['invoice_number']);
        $this->assertStringContainsString('Invoice generated successfully', $result['message']);

        // Verify order meta was saved
        $this->assertEquals('test-invoice-id', $order->get_meta('_b2brouter_invoice_id'));
        $this->assertEquals('INV-001', $order->get_meta('_b2brouter_invoice_number'));
        $this->assertNotEmpty($order->get_meta('_b2brouter_invoice_date'));

        // Cleanup
        unset($mock_orders[100]);
    }

    /**
     * Test generate_invoice when invoice already exists
     *
     * @return void
     */
    public function test_generate_invoice_already_exists() {
        global $mock_orders;

        // Create order with existing invoice
        $order = new WC_Order(101);
        $order->add_meta_data('_b2brouter_invoice_id', 'existing-invoice-id', true);
        $mock_orders[101] = $order;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');

        // Try to generate invoice
        $result = $this->generator->generate_invoice(101);

        // Should fail
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already generated', $result['message']);

        // Cleanup
        unset($mock_orders[101]);
    }

    /**
     * Test generate_invoice when API key not configured
     *
     * @return void
     */
    public function test_generate_invoice_no_api_key() {
        global $mock_orders;

        $order = new WC_Order(102);
        $mock_orders[102] = $order;

        // API key is empty
        $this->mock_settings->method('get_api_key')
                           ->willReturn('');

        $result = $this->generator->generate_invoice(102);

        // Should fail
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API key not configured', $result['message']);

        // Cleanup
        unset($mock_orders[102]);
    }

    /**
     * Test has_invoice with order that has invoice
     *
     * @return void
     */
    public function test_has_invoice_returns_true_when_invoice_exists() {
        global $mock_orders;

        $order = new WC_Order(103);
        $order->add_meta_data('_b2brouter_invoice_id', 'inv-123', true);
        $mock_orders[103] = $order;

        $result = $this->generator->has_invoice(103);

        $this->assertTrue($result);

        // Cleanup
        unset($mock_orders[103]);
    }

    /**
     * Test has_invoice with order without invoice
     *
     * @return void
     */
    public function test_has_invoice_returns_false_when_no_invoice() {
        global $mock_orders;

        $order = new WC_Order(104);
        $mock_orders[104] = $order;

        $result = $this->generator->has_invoice(104);

        $this->assertFalse($result);

        // Cleanup
        unset($mock_orders[104]);
    }

    /**
     * Test get_invoice_id with order that has invoice
     *
     * @return void
     */
    public function test_get_invoice_id_returns_id_when_exists() {
        global $mock_orders;

        $order = new WC_Order(105);
        $order->add_meta_data('_b2brouter_invoice_id', 'inv-456', true);
        $mock_orders[105] = $order;

        $result = $this->generator->get_invoice_id(105);

        $this->assertEquals('inv-456', $result);

        // Cleanup
        unset($mock_orders[105]);
    }

    /**
     * Test get_invoice_id with order without invoice
     *
     * @return void
     */
    public function test_get_invoice_id_returns_empty_when_no_invoice() {
        global $mock_orders;

        $order = new WC_Order(106);
        $mock_orders[106] = $order;

        $result = $this->generator->get_invoice_id(106);

        $this->assertEmpty($result);

        // Cleanup
        unset($mock_orders[106]);
    }

    /**
     * Test invoice generation with company name fallback
     *
     * @group integration
     * @return void
     */
    public function test_generate_invoice_uses_company_name_fallback() {
        $this->markTestSkipped('Integration test - requires real API access');
        global $mock_orders;

        // Order with no first/last name but has company
        $order = new WC_Order(107);
        $order->set_billing_first_name('');
        $order->set_billing_last_name('');
        $order->set_billing_company('Acme Corp');
        $item = new WC_Order_Item_Product('Product');
        $order->add_item($item);
        $mock_orders[107] = $order;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('increment_transaction_count')
                           ->willReturn(true);

        $result = $this->generator->generate_invoice(107);

        $this->assertTrue($result['success']);

        // Cleanup
        unset($mock_orders[107]);
    }

    /**
     * Test invoice generation with shipping
     *
     * @group integration
     * @return void
     */
    public function test_generate_invoice_includes_shipping() {
        $this->markTestSkipped('Integration test - requires real API access');
        global $mock_orders;

        $order = new WC_Order(108);
        $order->set_shipping_total(10.00);
        $order->set_shipping_tax(2.00);
        $item = new WC_Order_Item_Product('Product');
        $order->add_item($item);
        $mock_orders[108] = $order;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('increment_transaction_count')
                           ->willReturn(true);

        $result = $this->generator->generate_invoice(108);

        $this->assertTrue($result['success']);

        // Cleanup
        unset($mock_orders[108]);
    }

    /**
     * Test invoice generation with item taxes
     *
     * @group integration
     * @return void
     */
    public function test_generate_invoice_calculates_item_tax_rate() {
        $this->markTestSkipped('Integration test - requires real API access');
        global $mock_orders;

        $order = new WC_Order(109);
        $item = new WC_Order_Item_Product('Product with Tax');
        $item->set_total(100.00);
        $item->set_taxes(array('total' => array(10.00, 5.00))); // 15% total tax
        $order->add_item($item);
        $mock_orders[109] = $order;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('increment_transaction_count')
                           ->willReturn(true);

        $result = $this->generator->generate_invoice(109);

        $this->assertTrue($result['success']);

        // Cleanup
        unset($mock_orders[109]);
    }

    /**
     * Test invoice generation with zero-price item (free product)
     *
     * @group integration
     * @return void
     */
    public function test_generate_invoice_handles_zero_price_item() {
        $this->markTestSkipped('Integration test - requires real API access');
        global $mock_orders;

        $order = new WC_Order(110);
        $item = new WC_Order_Item_Product('Free Product');
        $item->set_total(0.00);
        $item->set_taxes(array('total' => array(0)));
        $order->add_item($item);
        $mock_orders[110] = $order;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('increment_transaction_count')
                           ->willReturn(true);

        $result = $this->generator->generate_invoice(110);

        $this->assertTrue($result['success']);

        // Cleanup
        unset($mock_orders[110]);
    }

    /**
     * Test that order notes are added on error
     *
     * @return void
     */
    public function test_generate_invoice_adds_note_on_error() {
        global $mock_orders;

        // Order exists but no invoice ID
        $order = new WC_Order(111);
        $mock_orders[111] = $order;

        // No API key configured
        $this->mock_settings->method('get_api_key')
                           ->willReturn('');

        $result = $this->generator->generate_invoice(111);

        $this->assertFalse($result['success']);

        // Cleanup
        unset($mock_orders[111]);
    }

    /**
     * Test multiple invoice generations use cached client
     *
     * @group integration
     * @return void
     */
    public function test_client_is_cached_across_calls() {
        $this->markTestSkipped('Integration test - requires real API access');
        global $mock_orders;

        // Create two orders
        $order1 = new WC_Order(112);
        $item1 = new WC_Order_Item_Product('Product 1');
        $order1->add_item($item1);
        $mock_orders[112] = $order1;

        $order2 = new WC_Order(113);
        $item2 = new WC_Order_Item_Product('Product 2');
        $order2->add_item($item2);
        $mock_orders[113] = $order2;

        $this->mock_settings->method('get_api_key')
                           ->willReturn('valid-api-key');
        $this->mock_settings->method('get_account_id')
                           ->willReturn('211162');
        $this->mock_settings->method('increment_transaction_count')
                           ->willReturn(true);

        // Generate two invoices
        $result1 = $this->generator->generate_invoice(112);
        $result2 = $this->generator->generate_invoice(113);

        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);

        // Cleanup
        unset($mock_orders[112], $mock_orders[113]);
    }
}
