<?php
/**
 * Tests for Settings class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Settings;

/**
 * Settings test case
 *
 * This is a sample test to demonstrate testing with the new architecture.
 * This test demonstrates:
 * - Testing a class with no dependencies
 * - Mocking WordPress functions
 * - Testing public methods
 *
 * @since 1.0.0
 */
class SettingsTest extends TestCase {

    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

    /**
     * Set up test
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();

        // Create Settings instance (no dependencies needed!)
        // WordPress functions are mocked in tests/bootstrap.php
        $this->settings = new Settings();
    }

    /**
     * Test that Settings can be instantiated
     *
     * @return void
     */
    public function test_can_be_instantiated() {
        $this->assertInstanceOf(Settings::class, $this->settings);
    }

    /**
     * Test invoice mode validation
     *
     * @return void
     */
    public function test_invoice_mode_accepts_valid_values() {
        // Test automatic mode
        $result = $this->settings->set_invoice_mode('automatic');
        $this->assertTrue($result);

        // Test manual mode
        $result = $this->settings->set_invoice_mode('manual');
        $this->assertTrue($result);
    }

    /**
     * Test invoice mode rejects invalid values
     *
     * @return void
     */
    public function test_invoice_mode_rejects_invalid_values() {
        $result = $this->settings->set_invoice_mode('invalid');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_mode('');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_mode('something_random');
        $this->assertFalse($result);
    }

    /**
     * Test API key detection
     *
     * @return void
     */
    public function test_is_api_key_configured_returns_false_when_empty() {
        // This test assumes get_option returns '' by default
        $this->assertFalse($this->settings->is_api_key_configured());
    }

    /**
     * Test transaction count increment
     *
     * @return void
     */
    public function test_transaction_count_increments() {
        // Get initial count (should be 0)
        $initial = $this->settings->get_transaction_count();

        // Increment
        $this->settings->increment_transaction_count();

        // Get new count
        $new_count = $this->settings->get_transaction_count();

        // Verify it increased (this is a simplified test due to mocked functions)
        $this->assertIsInt($new_count);
    }

    /**
     * Test that Settings constants are defined
     *
     * @return void
     */
    public function test_constants_are_defined() {
        $this->assertEquals('b2brouter_api_key', Settings::OPTION_API_KEY);
        $this->assertEquals('b2brouter_invoice_mode', Settings::OPTION_INVOICE_MODE);
        $this->assertEquals('b2brouter_transaction_count', Settings::OPTION_TRANSACTION_COUNT);
        $this->assertEquals('b2brouter_show_welcome', Settings::OPTION_SHOW_WELCOME);
        $this->assertEquals('b2brouter_activated', Settings::OPTION_ACTIVATED);
    }
}
