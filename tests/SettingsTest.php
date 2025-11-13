<?php
/**
 * Comprehensive tests for Settings class
 *
 * @package B2Brouter\WooCommerce\Tests
 */

use PHPUnit\Framework\TestCase;
use B2Brouter\WooCommerce\Settings;

/**
 * Settings test case
 *
 * Tests all public methods and edge cases for the Settings class
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

        // Reset static options before each test
        global $wp_options;
        $wp_options = array();

        // Create Settings instance (no dependencies needed!)
        $this->settings = new Settings();
    }

    /**
     * Tear down test
     *
     * @return void
     */
    public function tearDown(): void {
        parent::tearDown();

        // Clean up
        global $wp_options;
        $wp_options = array();
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
     * Test that Settings constants are defined correctly
     *
     * @return void
     */
    public function test_constants_are_defined() {
        $this->assertEquals('b2brouter_api_key', Settings::OPTION_API_KEY);
        $this->assertEquals('b2brouter_account_id', Settings::OPTION_ACCOUNT_ID);
        $this->assertEquals('b2brouter_invoice_mode', Settings::OPTION_INVOICE_MODE);
        $this->assertEquals('b2brouter_transaction_count', Settings::OPTION_TRANSACTION_COUNT);
        $this->assertEquals('b2brouter_show_welcome', Settings::OPTION_SHOW_WELCOME);
        $this->assertEquals('b2brouter_activated', Settings::OPTION_ACTIVATED);
    }

    // ========== API Key Tests ==========

    /**
     * Test get_api_key returns empty string by default
     *
     * @return void
     */
    public function test_get_api_key_returns_empty_by_default() {
        $api_key = $this->settings->get_api_key();
        $this->assertEquals('', $api_key);
    }

    /**
     * Test set_api_key stores the API key
     *
     * @return void
     */
    public function test_set_api_key_stores_value() {
        $result = $this->settings->set_api_key('test-api-key-123');
        $this->assertTrue($result);

        $stored = $this->settings->get_api_key();
        $this->assertEquals('test-api-key-123', $stored);
    }

    /**
     * Test set_api_key sanitizes input
     *
     * @return void
     */
    public function test_set_api_key_sanitizes_input() {
        $this->settings->set_api_key('  key-with-spaces  ');
        $stored = $this->settings->get_api_key();
        $this->assertEquals('key-with-spaces', $stored);
    }

    /**
     * Test set_api_key handles empty string
     *
     * @return void
     */
    public function test_set_api_key_accepts_empty_string() {
        $result = $this->settings->set_api_key('');
        $this->assertTrue($result);
        $this->assertEquals('', $this->settings->get_api_key());
    }

    /**
     * Test is_api_key_configured returns false when empty
     *
     * @return void
     */
    public function test_is_api_key_configured_returns_false_when_empty() {
        $this->assertFalse($this->settings->is_api_key_configured());
    }

    /**
     * Test is_api_key_configured returns true when set
     *
     * @return void
     */
    public function test_is_api_key_configured_returns_true_when_set() {
        $this->settings->set_api_key('some-key');
        $this->assertTrue($this->settings->is_api_key_configured());
    }

    // ========== Account ID Tests ==========

    /**
     * Test get_account_id returns empty string by default
     *
     * @return void
     */
    public function test_get_account_id_returns_empty_by_default() {
        $account_id = $this->settings->get_account_id();
        $this->assertEquals('', $account_id);
    }

    /**
     * Test set_account_id stores the account ID
     *
     * @return void
     */
    public function test_set_account_id_stores_value() {
        $result = $this->settings->set_account_id('211162');
        $this->assertTrue($result);

        $stored = $this->settings->get_account_id();
        $this->assertEquals('211162', $stored);
    }

    /**
     * Test set_account_id sanitizes input
     *
     * @return void
     */
    public function test_set_account_id_sanitizes_input() {
        $this->settings->set_account_id('  211162  ');
        $stored = $this->settings->get_account_id();
        $this->assertEquals('211162', $stored);
    }

    // ========== Invoice Mode Tests ==========

    /**
     * Test get_invoice_mode returns 'manual' by default
     *
     * @return void
     */
    public function test_get_invoice_mode_returns_manual_by_default() {
        $mode = $this->settings->get_invoice_mode();
        $this->assertEquals('manual', $mode);
    }

    /**
     * Test set_invoice_mode accepts 'automatic'
     *
     * @return void
     */
    public function test_set_invoice_mode_accepts_automatic() {
        $result = $this->settings->set_invoice_mode('automatic');
        $this->assertTrue($result);
        $this->assertEquals('automatic', $this->settings->get_invoice_mode());
    }

    /**
     * Test set_invoice_mode accepts 'manual'
     *
     * @return void
     */
    public function test_set_invoice_mode_accepts_manual() {
        $result = $this->settings->set_invoice_mode('manual');
        $this->assertTrue($result);
        $this->assertEquals('manual', $this->settings->get_invoice_mode());
    }

    /**
     * Test set_invoice_mode rejects invalid values
     *
     * @return void
     */
    public function test_set_invoice_mode_rejects_invalid_values() {
        $result = $this->settings->set_invoice_mode('invalid');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_mode('');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_mode('AUTO');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_mode('something_random');
        $this->assertFalse($result);
    }

    /**
     * Test invoice mode is case sensitive
     *
     * @return void
     */
    public function test_invoice_mode_is_case_sensitive() {
        $result = $this->settings->set_invoice_mode('Automatic');
        $this->assertFalse($result);

        $result = $this->settings->set_invoice_mode('MANUAL');
        $this->assertFalse($result);
    }

    // ========== Transaction Count Tests ==========

    /**
     * Test get_transaction_count returns 0 by default
     *
     * @return void
     */
    public function test_get_transaction_count_returns_zero_by_default() {
        $count = $this->settings->get_transaction_count();
        $this->assertEquals(0, $count);
        $this->assertIsInt($count);
    }

    /**
     * Test increment_transaction_count increases count
     *
     * @return void
     */
    public function test_increment_transaction_count_increases_value() {
        $initial = $this->settings->get_transaction_count();
        $this->assertEquals(0, $initial);

        $result = $this->settings->increment_transaction_count();
        $this->assertTrue($result);

        $after_first = $this->settings->get_transaction_count();
        $this->assertEquals(1, $after_first);

        $this->settings->increment_transaction_count();
        $after_second = $this->settings->get_transaction_count();
        $this->assertEquals(2, $after_second);
    }

    /**
     * Test transaction count returns integer
     *
     * @return void
     */
    public function test_transaction_count_always_returns_integer() {
        $count = $this->settings->get_transaction_count();
        $this->assertIsInt($count);

        $this->settings->increment_transaction_count();
        $count = $this->settings->get_transaction_count();
        $this->assertIsInt($count);
    }

    // ========== Welcome Screen Tests ==========

    /**
     * Test should_show_welcome returns false by default
     *
     * @return void
     */
    public function test_should_show_welcome_returns_false_by_default() {
        $should_show = $this->settings->should_show_welcome();
        $this->assertFalse($should_show);
    }

    /**
     * Test mark_welcome_shown sets flag to not show
     *
     * @return void
     */
    public function test_mark_welcome_shown_prevents_showing() {
        $result = $this->settings->mark_welcome_shown();
        $this->assertTrue($result);

        $should_show = $this->settings->should_show_welcome();
        $this->assertFalse($should_show);
    }

    // ========== API Key Validation Tests ==========

    /**
     * Test validate_api_key rejects empty key
     *
     * @return void
     */
    public function test_validate_api_key_rejects_empty_string() {
        $result = $this->settings->validate_api_key('');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('cannot be empty', $result['message']);
    }

    /**
     * Test validate_api_key checks for SDK presence
     *
     * @return void
     */
    public function test_validate_api_key_requires_sdk() {
        // The bootstrap file creates a mock B2BRouterClient class
        // Verify the namespaced class exists
        $this->assertTrue(class_exists('B2BRouter\B2BRouterClient'));
    }

    /**
     * Test validate_api_key returns proper structure
     *
     * @return void
     */
    public function test_validate_api_key_returns_proper_structure() {
        $result = $this->settings->validate_api_key('test-key');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertIsBool($result['valid']);
        $this->assertIsString($result['message']);
    }

    /**
     * Test validate_api_key with invalid key returns error
     *
     * Note: With real SDK installed, this makes actual API calls
     *
     * @return void
     */
    public function test_validate_api_key_with_invalid_key() {
        $result = $this->settings->validate_api_key('invalid-key');

        $this->assertIsArray($result);
        $this->assertFalse($result['valid']);
        $this->assertIsString($result['message']);
    }

    // ========== Integration Tests ==========

    /**
     * Test complete workflow of setting up plugin
     *
     * @return void
     */
    public function test_complete_setup_workflow() {
        // Step 1: Check initial state
        $this->assertFalse($this->settings->is_api_key_configured());
        $this->assertEquals('manual', $this->settings->get_invoice_mode());
        $this->assertEquals(0, $this->settings->get_transaction_count());

        // Step 2: Configure API key
        $this->settings->set_api_key('my-api-key');
        $this->assertTrue($this->settings->is_api_key_configured());

        // Step 3: Set to automatic mode
        $this->assertTrue($this->settings->set_invoice_mode('automatic'));
        $this->assertEquals('automatic', $this->settings->get_invoice_mode());

        // Step 4: Generate invoices (increment counter)
        for ($i = 0; $i < 5; $i++) {
            $this->settings->increment_transaction_count();
        }
        $this->assertEquals(5, $this->settings->get_transaction_count());

        // Step 5: Verify configuration persists
        $this->assertEquals('my-api-key', $this->settings->get_api_key());
        $this->assertEquals('automatic', $this->settings->get_invoice_mode());
    }

    /**
     * Test settings can be updated multiple times
     *
     * @return void
     */
    public function test_settings_can_be_updated_multiple_times() {
        // Update API key multiple times
        $this->settings->set_api_key('key1');
        $this->assertEquals('key1', $this->settings->get_api_key());

        $this->settings->set_api_key('key2');
        $this->assertEquals('key2', $this->settings->get_api_key());

        // Update mode multiple times
        $this->settings->set_invoice_mode('automatic');
        $this->assertEquals('automatic', $this->settings->get_invoice_mode());

        $this->settings->set_invoice_mode('manual');
        $this->assertEquals('manual', $this->settings->get_invoice_mode());
    }

    /**
     * Test all public methods are covered
     *
     * This test serves as documentation of the Settings class public API
     *
     * @return void
     */
    public function test_all_public_methods_exist() {
        $methods = [
            'get_api_key',
            'set_api_key',
            'get_account_id',
            'set_account_id',
            'get_invoice_mode',
            'set_invoice_mode',
            'get_transaction_count',
            'increment_transaction_count',
            'is_api_key_configured',
            'should_show_welcome',
            'mark_welcome_shown',
            'validate_api_key',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->settings, $method),
                "Method {$method} should exist in Settings class"
            );
        }
    }
}
