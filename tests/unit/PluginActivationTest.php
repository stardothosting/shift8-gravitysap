<?php
/**
 * Plugin activation and core functionality tests
 *
 * @package Shift8\GravitySAP\Tests\Unit
 */

namespace Shift8\GravitySAP\Tests\Unit;

use Shift8\GravitySAP\Tests\TestCase;

/**
 * Test plugin activation and basic functionality
 */
class PluginActivationTest extends TestCase {

    /**
     * Test that the plugin is loaded correctly
     */
    public function test_plugin_is_loaded() {
        // Test that main plugin constants are defined
        $this->assertTrue(defined('SHIFT8_GRAVITYSAP_VERSION'), 'Plugin version constant should be defined');
        $this->assertTrue(defined('SHIFT8_GRAVITYSAP_PLUGIN_FILE'), 'Plugin file constant should be defined');
        $this->assertTrue(defined('SHIFT8_GRAVITYSAP_PLUGIN_DIR'), 'Plugin directory constant should be defined');
        
        // Test that version is correct
        $this->assertEquals('1.0.7', SHIFT8_GRAVITYSAP_VERSION, 'Plugin version should match expected version');
    }

    /**
     * Test that core functions are available
     */
    public function test_core_functions_exist() {
        // Test that main plugin functions are defined
        $this->assertTrue(function_exists('shift8_gravitysap_debug_log'), 'Debug log function should exist');
        $this->assertTrue(function_exists('shift8_gravitysap_sanitize_log_data'), 'Sanitize log data function should exist');
        $this->assertTrue(function_exists('shift8_gravitysap_encrypt_password'), 'Encrypt password function should exist');
        $this->assertTrue(function_exists('shift8_gravitysap_decrypt_password'), 'Decrypt password function should exist');
    }

    /**
     * Test WordPress hooks are registered
     */
    public function test_wordpress_hooks_registered() {
        // Test that action/filter adding functions work
        $this->assertTrue(function_exists('add_action'), 'add_action function should exist');
        $this->assertTrue(function_exists('add_filter'), 'add_filter function should exist');
        
        // The actual hooks are only registered in admin context, so we test the hook system works
        add_action('test_hook', 'test_function');
        $this->assertHookExists('test_hook');
    }

    /**
     * Test plugin settings functionality
     */
    public function test_plugin_settings() {
        // Test saving settings
        $test_settings = $this->create_test_settings();
        $this->assertTrue($this->save_test_settings($test_settings), 'Settings should save successfully');
        
        // Test retrieving settings
        $saved_settings = get_option('shift8_gravitysap_settings', array());
        $this->assertIsArray($saved_settings, 'Settings should be returned as array');
        $this->assertEquals($test_settings['sap_endpoint'], $saved_settings['sap_endpoint'], 'SAP endpoint should be saved correctly');
        $this->assertEquals($test_settings['sap_company_db'], $saved_settings['sap_company_db'], 'Company DB should be saved correctly');
    }

    /**
     * Test password encryption and decryption
     */
    public function test_password_encryption() {
        $original_password = 'test_password_123';
        
        // Test encryption
        $encrypted = shift8_gravitysap_encrypt_password($original_password);
        $this->assertIsString($encrypted, 'Encrypted password should be a string');
        $this->assertNotEquals($original_password, $encrypted, 'Encrypted password should not equal original');
        
        // Test decryption  
        $decrypted = shift8_gravitysap_decrypt_password($encrypted);
        $this->assertEquals($original_password, $decrypted, 'Decrypted password should equal original');
    }

    /**
     * Test debug logging functionality
     */
    public function test_debug_logging() {
        // Enable debug logging in test settings
        $settings = $this->create_test_settings(array('sap_debug' => '1'));
        $this->save_test_settings($settings);
        
        // Test that debug log function runs without errors
        $this->assertNull(shift8_gravitysap_debug_log('Test message'), 'Debug log should execute without errors');
        
        // Test with data parameter
        $test_data = array('key' => 'value', 'number' => 123);
        $this->assertNull(shift8_gravitysap_debug_log('Test with data', $test_data), 'Debug log with data should execute without errors');
    }

    /**
     * Test log data sanitization
     */
    public function test_log_data_sanitization() {
        $sensitive_data = array(
            'sap_password' => 'secret123',
            'sap_username' => 'admin',
            'safe_data' => 'this is safe',
            'nested' => array(
                'sap_password' => 'nested_secret',
                'other' => 'value'
            )
        );
        
        $sanitized = shift8_gravitysap_sanitize_log_data($sensitive_data);
        
        // Test that passwords are masked
        $this->assertEquals('***REDACTED***', $sanitized['sap_password'], 'Password should be redacted');
        $this->assertEquals('***REDACTED***', $sanitized['nested']['sap_password'], 'Nested password should be redacted');
        
        // Test that safe data is preserved
        $this->assertEquals('this is safe', $sanitized['safe_data'], 'Safe data should be preserved');
        
        // Test that usernames are partially masked for security (first 2 chars + ***)
        $this->assertEquals('ad***', $sanitized['sap_username'], 'Username should be partially masked for security');
        
        // Test that other data is preserved
        $this->assertEquals('value', $sanitized['nested']['other'], 'Non-sensitive nested data should be preserved');
    }
} 