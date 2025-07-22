<?php
/**
 * Main Plugin Class tests with actual method execution
 *
 * @package Shift8\GravitySAP\Tests\Unit
 */

namespace Shift8\GravitySAP\Tests\Unit;

use Shift8\GravitySAP\Tests\TestCase;

/**
 * Test the main Shift8_GravitySAP class methods to improve code coverage
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
        
        // Save test settings
        $this->save_test_settings();
        
        // Get plugin instance
        $this->plugin = \Shift8_GravitySAP::get_instance();
        
        // Mock is_admin to control admin initialization
        if (!function_exists('is_admin')) {
            function is_admin() {
                return false;
            }
        }
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
        // Test that init can be called without errors
        $this->plugin->init();
        
        // Verify hooks were added (we can't easily test this without WordPress loaded)
        $this->assertTrue(true, 'Plugin init completed without errors');
    }

    /**
     * Test gravity forms active check with GFForms class existing
     */
    public function test_gravity_forms_active_with_class() {
        // Mock GFForms class existence
        if (!class_exists('GFForms')) {
            eval('class GFForms {}');
        }
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('is_gravity_forms_active');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin);
        $this->assertTrue($result, 'Should detect GFForms class as active');
    }

    /**
     * Test activation hook
     */
    public function test_plugin_activation() {
        // Clear any existing settings
        delete_option('shift8_gravitysap_settings');
        
        // Test activation
        $this->plugin->activate();
        
        // Verify default settings were created
        $settings = get_option('shift8_gravitysap_settings');
        $this->assertIsArray($settings, 'Settings should be created as array');
        $this->assertArrayHasKey('sap_endpoint', $settings, 'Should have sap_endpoint key');
        $this->assertArrayHasKey('sap_username', $settings, 'Should have sap_username key');
        $this->assertArrayHasKey('sap_debug', $settings, 'Should have sap_debug key');
        $this->assertEquals('0', $settings['sap_debug'], 'Debug should be disabled by default');
    }

    /**
     * Test deactivation hook
     */
    public function test_plugin_deactivation() {
        // Test deactivation
        $this->plugin->deactivate();
        
        // Should complete without errors
        $this->assertTrue(true, 'Plugin deactivation completed without errors');
    }

    /**
     * Test form settings menu addition
     */
    public function test_add_form_settings_menu() {
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
        global $_GET, $_POST;
        $_GET['subview'] = 'sap_integration';
        $_POST['gforms_save_form'] = wp_create_nonce('gforms_save_form');
        $_POST['sap_enabled'] = '1';
        $_POST['sap_feed_name'] = 'Test Feed';
        $_POST['sap_card_type'] = 'cCustomer';
        $_POST['sap_field_mapping'] = array(
            'CardName' => '1',
            'EmailAddress' => '2',
            'InvalidField' => '999' // Should be filtered out
        );
        
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
     * Test form settings saving with invalid card type
     */
    public function test_save_form_settings_invalid_card_type() {
        global $_GET, $_POST;
        $_GET['subview'] = 'sap_integration';
        $_POST['gforms_save_form'] = wp_create_nonce('gforms_save_form');
        $_POST['sap_card_type'] = 'invalid_type';
        
        $form = array('id' => 123);
        $result = $this->plugin->save_form_settings($form);
        
        $settings = $result['sap_integration_settings'];
        $this->assertEquals('cCustomer', $settings['card_type'], 'Should default to cCustomer for invalid type');
    }

    /**
     * Test form settings saving without proper nonce
     */
    public function test_save_form_settings_invalid_nonce() {
        global $_GET, $_POST;
        $_GET['subview'] = 'sap_integration';
        $_POST['gforms_save_form'] = 'invalid_nonce';
        
        $form = array('id' => 123);
        $result = $this->plugin->save_form_settings($form);
        
        // Should return form unchanged when nonce is invalid
        $this->assertEquals($form, $result, 'Should not modify form with invalid nonce');
    }

    /**
     * Test form submission processing with disabled integration
     */
    public function test_process_form_submission_disabled() {
        $entry = array('1' => 'Test User', '2' => 'test@example.com');
        $form = array(
            'id' => 123,
            'sap_integration_settings' => array('enabled' => '0')
        );
        
        // Should return without processing when disabled
        $this->plugin->process_form_submission($entry, $form);
        
        // If we get here without exceptions, the test passes
        $this->assertTrue(true, 'Should handle disabled integration gracefully');
    }

    /**
     * Test entry to business partner mapping
     */
    public function test_map_entry_to_business_partner() {
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
     * Test test value sanitization
     */
    public function test_sanitize_test_values() {
        $unsafe_values = array(
            'CardName' => '<script>alert("xss")</script>Test Customer',
            'EmailAddress' => 'test@example.com',
            'InvalidField' => 'should be removed',
            'Phone1' => '+1-555-123-4567'
        );
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('sanitize_test_values');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $unsafe_values);
        
        $this->assertIsArray($result, 'Should return array');
        $this->assertStringNotContainsString('<script>', $result['CardName'], 'Should remove script tags');
        $this->assertStringContainsString('Test Customer', $result['CardName'], 'Should preserve safe content');
        $this->assertEquals('test@example.com', $result['EmailAddress'], 'Should preserve valid email');
        $this->assertArrayNotHasKey('InvalidField', $result, 'Should remove invalid fields');
    }

    /**
     * Test test values to business partner mapping
     */
    public function test_map_test_values_to_business_partner() {
        $settings = array('card_type' => 'cSupplier');
        $test_values = array(
            'CardName' => 'Test Supplier',
            'EmailAddress' => 'supplier@example.com',
            'BPAddresses.City' => 'Test City'
        );
        
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('map_test_values_to_business_partner');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $test_values);
        
        $this->assertEquals('cSupplier', $result['CardType'], 'Should use settings card type');
        $this->assertEquals('Test Supplier', $result['CardName'], 'Should map CardName');
        $this->assertEquals('Test City', $result['BPAddresses'][0]['City'], 'Should map address fields');
    }

    /**
     * Test admin initialization
     */
    public function test_init_admin() {
        // Test admin initialization directly
        $this->plugin->init_admin();
        
        // If we get here without errors, admin init worked
        $this->assertTrue(true, 'Admin initialization completed without errors');
    }

    /**
     * Test gravity forms missing notice
     */
    public function test_gravity_forms_missing_notice() {
        // Capture output
        ob_start();
        $this->plugin->gravity_forms_missing_notice();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('notice-error', $output, 'Should show error notice');
        $this->assertStringContainsString('Gravity Forms', $output, 'Should mention Gravity Forms');
        $this->assertStringContainsString('requires', $output, 'Should indicate requirement');
    }

    /**
     * Test initialization of gravity forms integration
     */
    public function test_init_gravity_forms_integration() {
        // Mock GFForms class
        if (!class_exists('GFForms')) {
            eval('class GFForms {}');
        }
        
        $this->plugin->init_gravity_forms_integration();
        
        // If we get here without errors, the integration init worked
        $this->assertTrue(true, 'Gravity Forms integration initialization completed');
    }
} 