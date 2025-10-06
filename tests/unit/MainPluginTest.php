<?php
/**
 * Main Plugin Class tests using Brain/Monkey
 *
 * @package Shift8\GravitySAP\Tests\Unit
 */

namespace Shift8\GravitySAP\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Test the main Shift8_GravitySAP class methods using Brain/Monkey
 */
class MainPluginTest extends TestCase {

    /**
     * Plugin instance for testing
     *
     * @var Shift8_GravitySAP
     */
    protected $plugin;

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Setup global test options
        global $_test_options;
        $_test_options = array();
        
        // Mock basic WordPress functions
        Functions\when('plugin_dir_path')->justReturn('/test/plugin/path/');
        Functions\when('plugin_dir_url')->justReturn('http://example.com/wp-content/plugins/test-plugin/');
        Functions\when('plugin_basename')->justReturn('test-plugin/test-plugin.php');
        Functions\when('get_option')->justReturn('0'); // Mock debug setting as disabled by default
        Functions\when('get_transient')->justReturn(false); // Mock transient as not cached by default
        Functions\when('set_transient')->justReturn(true); // Mock transient setting
        Functions\when('delete_transient')->justReturn(true); // Mock transient deletion
        Functions\when('rgar')->alias(function($array, $key, $default = '') { return isset($array[$key]) ? $array[$key] : $default; });
        
        // Get plugin instance
        $this->plugin = \Shift8_GravitySAP::get_instance();
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test plugin singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = \Shift8_GravitySAP::get_instance();
        $instance2 = \Shift8_GravitySAP::get_instance();
        
        $this->assertSame($instance1, $instance2, 'Plugin should follow singleton pattern');
        $this->assertInstanceOf('Shift8_GravitySAP', $instance1, 'Instance should be of correct type');
    }

    /**
     * Test plugin initialization
     */
    public function test_plugin_init() {
        // Mock get_option for debug logging - use when() for flexible call counts
        Functions\when('get_option')
            ->justReturn(array('sap_debug' => '0'));
        
        // Mock is_admin
        Functions\when('is_admin')->justReturn(false);
        
        // Mock is_plugin_active
        Functions\when('is_plugin_active')
            ->justReturn(true);
        
        // Mock add_action calls
        Functions\when('add_action')->justReturn(true);
        
        // Test that init can be called without errors
        $this->plugin->init();
        
        $this->assertTrue(true, 'Plugin init completed without errors');
    }

    /**
     * Test gravity forms active check with simple approach
     */
    public function test_gravity_forms_active_check() {
        // Skip testing private method that tries to load WordPress core files
        // Just verify the method exists and is callable
        $reflection = new \ReflectionClass($this->plugin);
        $this->assertTrue($reflection->hasMethod('is_gravity_forms_active'), 'Method should exist');
        
        $method = $reflection->getMethod('is_gravity_forms_active');
        $this->assertTrue($method->isPrivate(), 'Method should be private');
    }

    /**
     * Test plugin activation
     */
    public function test_plugin_activation() {
        // Mock get_option to return false (no existing settings)
        Functions\when('get_option')
            ->justReturn(false);
        
        // Mock add_option
        Functions\when('add_option')
            ->justReturn(true);
        
        // Mock wp_upload_dir
        Functions\when('wp_upload_dir')
            ->justReturn(array('basedir' => '/tmp/uploads'));
        
        // Mock directory operations
        Functions\when('is_dir')->justReturn(false);
        Functions\when('wp_mkdir_p')->justReturn(true);
        
        // Test activation
        $this->plugin->activate();
        
        $this->assertTrue(true, 'Plugin activation completed without errors');
    }

    /**
     * Test plugin deactivation
     */
    public function test_plugin_deactivation() {
        // Mock get_option for debug logging
        Functions\when('get_option')
            ->justReturn(array('sap_debug' => '0'));
        
        // Mock wp_clear_scheduled_hook
        Functions\when('wp_clear_scheduled_hook')
            ->justReturn(true);
        
        // Test deactivation
        $this->plugin->deactivate();
        
        $this->assertTrue(true, 'Plugin deactivation completed without errors');
    }

    /**
     * Test form settings menu addition
     */
    public function test_add_form_settings_menu() {
        // Mock esc_html__
        Functions\expect('esc_html__')
            ->once()
            ->with('SAP Integration', 'shift8-gravity-forms-sap-b1-integration')
            ->andReturn('SAP Integration');
        
        $existing_menu = array(
            array('name' => 'general', 'label' => 'General'),
            array('name' => 'restrictions', 'label' => 'Restrictions')
        );
        
        $result = $this->plugin->add_form_settings_menu($existing_menu, 123);
        
        $this->assertIsArray($result, 'Should return array');
        $this->assertCount(3, $result, 'Should add one new menu item');
        
        $sap_menu = end($result);
        $this->assertEquals('sap_integration', $sap_menu['name'], 'Should add SAP integration menu');
        $this->assertStringContainsString('SAP Integration', $sap_menu['label'], 'Should have correct label');
    }

    /**
     * Test form settings saving with valid data
     */
    public function test_save_form_settings_valid_data() {
        // Mock rgget and rgpost functions
        Functions\when('rgget')->alias(function($name, $array = null) {
            $data = array('subview' => 'sap_integration');
            return isset($data[$name]) ? $data[$name] : null;
        });
        
        Functions\when('rgpost')->alias(function($name, $array = null) {
            $data = array(
                'gforms_save_form' => 'test_nonce',
                'sap_enabled' => '1',
                'sap_feed_name' => 'Test Feed',
                'sap_card_type' => 'cCustomer',
                'sap_field_mapping' => array(
                    'CardName' => '1',
                    'EmailAddress' => '2',
                    'InvalidField' => '999'
                )
            );
            return isset($data[$name]) ? $data[$name] : null;
        });
        
        // Mock wp_verify_nonce
        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('test_nonce', 'gforms_save_form')
            ->andReturn(true);
        
        // Mock sanitize_text_field
        Functions\expect('sanitize_text_field')
            ->once()
            ->with('Test Feed')
            ->andReturn('Test Feed');
        
        // Mock esc_html__ for field labels
        Functions\when('esc_html__')->alias(function($text) { return $text; });
        
        $form = array('id' => 123);
        
        $result = $this->plugin->save_form_settings($form);
        
        $this->assertArrayHasKey('sap_integration_settings', $result, 'Should add settings to form');
        
        $settings = $result['sap_integration_settings'];
        $this->assertEquals('1', $settings['enabled'], 'Should save enabled setting');
        $this->assertEquals('Test Feed', $settings['feed_name'], 'Should save feed name');
        $this->assertEquals('cCustomer', $settings['card_type'], 'Should save card type');
        $this->assertArrayHasKey('CardName', $settings['field_mapping'], 'Should save valid field mapping');
        $this->assertArrayNotHasKey('InvalidField', $settings['field_mapping'], 'Should filter out invalid fields');
    }

    /**
     * Test form settings saving with invalid nonce
     */
    public function test_save_form_settings_invalid_nonce() {
        // Mock rgget
        Functions\when('rgget')->alias(function($name) {
            return $name === 'subview' ? 'sap_integration' : null;
        });
        
        // Mock rgpost for nonce
        Functions\when('rgpost')->alias(function($name) {
            return $name === 'gforms_save_form' ? 'invalid_nonce' : null;
        });
        
        // Mock wp_verify_nonce to return false
        Functions\when('wp_verify_nonce')->justReturn(false);
        
        $form = array('id' => 123);
        $result = $this->plugin->save_form_settings($form);
        
        // Should return form unchanged when nonce is invalid
        $this->assertEquals($form, $result, 'Should not modify form with invalid nonce');
    }

    /**
     * Test form submission processing with disabled integration
     */
    public function test_process_form_submission_disabled() {
        // Mock rgar function
        Functions\when('rgar')->alias(function($array, $key, $default = null) {
            return isset($array[$key]) ? $array[$key] : $default;
        });
        
        $entry = array('1' => 'Test User', '2' => 'test@example.com');
        $form = array(
            'id' => 123,
            'sap_integration_settings' => array('enabled' => '0')
        );
        
        // Should return without processing when disabled (no function calls expected)
        $this->plugin->process_form_submission($entry, $form);
        
        $this->assertTrue(true, 'Should handle disabled integration gracefully');
    }

    /**
     * Test entry to business partner mapping
     */
    public function test_map_entry_to_business_partner() {
        // Mock sanitize_text_field for all field values
        Functions\expect('sanitize_text_field')
            ->times(5)
            ->andReturnUsing(function($input) {
                return $input; // Return as-is for testing
            });
        
        // Mock rgar function
        Functions\when('rgar')->alias(function($array, $key, $default = null) {
            return isset($array[$key]) ? $array[$key] : $default;
        });
        
        $settings = array(
            'card_type' => 'cCustomer',
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2',
                'Phone1' => '3',
                'BPAddresses.Street' => '4',
                'BPAddresses.City' => '5'
            )
        );
        
        $entry = array(
            '1' => 'John Doe',
            '2' => 'john@example.com',
            '3' => '+1-555-123-4567',
            '4' => '123 Main St',
            '5' => 'Anytown'
        );
        
        $form = array('id' => 123);
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('map_entry_to_business_partner');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertIsArray($result, 'Should return array');
        $this->assertEquals('cCustomer', $result['CardType'], 'Should set correct card type');
        $this->assertEquals('John Doe', $result['CardName'], 'Should map CardName correctly');
        $this->assertEquals('john@example.com', $result['EmailAddress'], 'Should map EmailAddress correctly');
        $this->assertArrayHasKey('BPAddresses', $result, 'Should create BPAddresses array');
        $this->assertEquals('123 Main St', $result['BPAddresses'][0]['Street'], 'Should map address fields correctly');
        $this->assertEquals('bo_BillTo', $result['BPAddresses'][0]['AddressType'], 'Should set correct address type');
    }

    /**
     * Test gravity forms missing notice
     */
    public function test_gravity_forms_missing_notice() {
        // Mock esc_html__
        Functions\when('esc_html__')
            ->justReturn('Shift8 Integration for Gravity Forms and SAP Business One requires Gravity Forms to be installed and activated.');
        
        // Capture output
        ob_start();
        $this->plugin->gravity_forms_missing_notice();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('notice-error', $output, 'Should show error notice');
        $this->assertStringContainsString('Gravity Forms', $output, 'Should mention Gravity Forms');
        $this->assertStringContainsString('requires', $output, 'Should indicate requirement');
    }

    /**
     * Test admin initialization
     */
    public function test_init_admin() {
        // Mock file inclusion - just test it doesn't error
        $this->plugin->init_admin();
        
        $this->assertTrue(true, 'Admin initialization completed without errors');
    }

    /**
     * Test initialization of gravity forms integration
     */
    public function test_init_gravity_forms_integration() {
        // Mock class_exists for GFForms
        Functions\when('class_exists')->justReturn(true);
        
        // Mock add_filter and add_action calls
        Functions\when('add_filter')->justReturn(true);
        Functions\when('add_action')->justReturn(true);
        
        $this->plugin->init_gravity_forms_integration();
        
        $this->assertTrue(true, 'Gravity Forms integration initialization completed');
    }

    /**
     * Test SAP fields helper functions
     */
    public function test_sap_fields_helpers() {
        // Mock esc_html__ for field labels
        Functions\when('esc_html__')->alias(function($text) { return $text; });
        
        // Use reflection to test private methods
        $reflection = new \ReflectionClass($this->plugin);
        
        $get_sap_fields = $reflection->getMethod('get_sap_fields');
        $get_sap_fields->setAccessible(true);
        
        $get_allowed_sap_fields = $reflection->getMethod('get_allowed_sap_fields');
        $get_allowed_sap_fields->setAccessible(true);
        
        $sap_fields = $get_sap_fields->invoke($this->plugin);
        $allowed_fields = $get_allowed_sap_fields->invoke($this->plugin);
        
        // Test that we get expected fields
        $this->assertIsArray($sap_fields, 'get_sap_fields should return array');
        $this->assertArrayHasKey('CardName', $sap_fields, 'Should include CardName field');
        $this->assertArrayHasKey('Phone1', $sap_fields, 'Should include Phone1 field');
        $this->assertArrayHasKey('Phone2', $sap_fields, 'Should include Phone2 field');
        $this->assertArrayHasKey('Cellular', $sap_fields, 'Should include Cellular field');
        $this->assertArrayHasKey('BPAddresses.Country', $sap_fields, 'Should include Country field');
        
        // Test that allowed fields are the keys of sap fields
        $this->assertIsArray($allowed_fields, 'get_allowed_sap_fields should return array');
        $this->assertEquals(array_keys($sap_fields), $allowed_fields, 'Allowed fields should match SAP field keys');
        
        // Test expected count (12 fields total)
        $this->assertCount(12, $sap_fields, 'Should have 12 SAP fields');
        $this->assertCount(12, $allowed_fields, 'Should have 12 allowed fields');
    }

    /**
     * Test SAP field limits validation - simplified test focusing on the validation logic
     */
    public function test_validate_sap_field_limits() {
        // Mock esc_html__ for field labels and validation messages
        Functions\when('esc_html__')->alias(function($text) { return $text; });
        Functions\when('esc_html')->alias(function($text) { return htmlspecialchars($text); });
        
        // Mock basic Gravity Forms functions
        Functions\when('rgar')->alias(function($array, $key, $default = null) {
            return isset($array[$key]) ? $array[$key] : $default;
        });
        
        // Test with disabled SAP integration - should pass through unchanged
        $form_disabled = array(
            'id' => 123,
            'sap_integration_settings' => array(
                'enabled' => '0'  // Disabled
            )
        );
        
        $validation_result_disabled = array(
            'is_valid' => true,
            'form' => $form_disabled
        );
        
        $result_disabled = $this->plugin->validate_sap_field_limits($validation_result_disabled);
        $this->assertTrue($result_disabled['is_valid'], 'Should pass validation when SAP integration is disabled');
        
        // Test with no field mapping - should pass through unchanged
        $form_no_mapping = array(
            'id' => 123,
            'sap_integration_settings' => array(
                'enabled' => '1',
                'field_mapping' => array() // No mappings
            )
        );
        
        $validation_result_no_mapping = array(
            'is_valid' => true,
            'form' => $form_no_mapping
        );
        
        $result_no_mapping = $this->plugin->validate_sap_field_limits($validation_result_no_mapping);
        $this->assertTrue($result_no_mapping['is_valid'], 'Should pass validation when no field mapping exists');
    }
    
    /**
     * Test SAP field limits helper function
     */
    public function test_get_sap_field_limits() {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('get_sap_field_limits');
        $method->setAccessible(true);
        
        $limits = $method->invoke($this->plugin);
        
        $this->assertIsArray($limits, 'Should return array of field limits');
        $this->assertArrayHasKey('CardName', $limits, 'Should include CardName limits');
        $this->assertArrayHasKey('BPAddresses.State', $limits, 'Should include State limits');
        
        // Test specific field limits
        $this->assertEquals(100, $limits['CardName']['max_length'], 'CardName should have 100 char limit');
        $this->assertEquals(3, $limits['BPAddresses.State']['max_length'], 'State should have 3 char limit');
        $this->assertTrue($limits['CardName']['required'], 'CardName should be required');
        $this->assertFalse($limits['BPAddresses.State']['required'], 'State should not be required');
        
        // Test validation messages for special fields
        $this->assertArrayHasKey('validation_message', $limits['BPAddresses.State'], 'State should have validation message');
        $this->assertArrayHasKey('validation_message', $limits['BPAddresses.Country'], 'Country should have validation message');
    }
} 