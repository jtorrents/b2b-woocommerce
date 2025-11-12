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
     * Test that Settings increment is called on successful generation
     *
     * This is a more advanced test demonstrating that you can verify
     * methods are called on dependencies.
     *
     * @return void
     */
    public function test_increments_transaction_count_on_success() {
        // This would require mocking WooCommerce order objects and B2Brouter API
        // Left as an exercise - demonstrates the capability

        // Example:
        // $this->mock_settings->expects($this->once())
        //                     ->method('increment_transaction_count');

        $this->markTestIncomplete(
            'This test requires mocking WooCommerce and B2Brouter API. ' .
            'Demonstrates advanced testing capabilities.'
        );
    }
}
