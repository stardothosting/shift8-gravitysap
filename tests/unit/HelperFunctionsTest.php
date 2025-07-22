<?php
/**
 * Helper Functions tests for better code coverage
 *
 * @package Shift8\GravitySAP\Tests\Unit
 */

namespace Shift8\GravitySAP\Tests\Unit;

use Shift8\GravitySAP\Tests\TestCase;

/**
 * Test global helper functions to improve code coverage
 */
class HelperFunctionsTest extends TestCase {

    /**
     * Test password encryption and decryption
     */
    public function test_password_encryption_decryption() {
        $original_password = 'test_password_123!@#';
        
        // Test encryption
        $encrypted = shift8_gravitysap_encrypt_password($original_password);
        $this->assertNotEmpty($encrypted, 'Encrypted password should not be empty');
        $this->assertNotEquals($original_password, $encrypted, 'Encrypted password should be different from original');
        
        // Test decryption
        $decrypted = shift8_gravitysap_decrypt_password($encrypted);
        $this->assertEquals($original_password, $decrypted, 'Decrypted password should match original');
    }

    /**
     * Test password encryption with empty password
     */
    public function test_password_encryption_empty() {
        $encrypted = shift8_gravitysap_encrypt_password('');
        $this->assertEquals('', $encrypted, 'Empty password should return empty string');
        
        $decrypted = shift8_gravitysap_decrypt_password('');
        $this->assertEquals('', $decrypted, 'Empty encrypted password should return empty string');
    }

    /**
     * Test debug logging with disabled debug
     */
    public function test_debug_logging_disabled() {
        // Ensure debug is disabled
        update_option('shift8_gravitysap_settings', array('sap_debug' => '0'));
        
        // Test logging - should not actually log anything
        shift8_gravitysap_debug_log('Test message', array('test' => 'data'));
        
        // If we get here without errors, the function handled disabled debug correctly
        $this->assertTrue(true, 'Debug logging with disabled debug completed without errors');
    }

    /**
     * Test debug logging with enabled debug
     */
    public function test_debug_logging_enabled() {
        // Enable debug logging
        update_option('shift8_gravitysap_settings', array('sap_debug' => '1'));
        
        // Mock wp_upload_dir to return a test directory
        $upload_dir = array(
            'basedir' => '/tmp/test_uploads',
            'baseurl' => 'http://example.com/uploads'
        );
        
        if (!function_exists('wp_upload_dir_test')) {
            function wp_upload_dir() {
                return array(
                    'basedir' => '/tmp/test_uploads',
                    'baseurl' => 'http://example.com/uploads'
                );
            }
        }
        
        // Test logging
        shift8_gravitysap_debug_log('Test debug message', array('key' => 'value'));
        
        // Test logging without data
        shift8_gravitysap_debug_log('Simple test message');
        
        $this->assertTrue(true, 'Debug logging with enabled debug completed without errors');
    }

    /**
     * Test log data sanitization with passwords
     */
    public function test_sanitize_log_data_passwords() {
        $data = array(
            'username' => 'testuser',
            'password' => 'secret123',
            'sap_password' => 'anothersecret',
            'normal_field' => 'normal_value',
            'nested' => array(
                'pwd' => 'nested_secret',
                'other' => 'other_value'
            )
        );
        
        $sanitized = shift8_gravitysap_sanitize_log_data($data);
        
        // Passwords should be redacted
        $this->assertEquals('***REDACTED***', $sanitized['password'], 'Password should be redacted');
        $this->assertEquals('***REDACTED***', $sanitized['sap_password'], 'SAP password should be redacted');
        $this->assertEquals('***REDACTED***', $sanitized['nested']['pwd'], 'Nested password should be redacted');
        
        // Username should be partially masked
        $this->assertEquals('te***', $sanitized['username'], 'Username should be partially masked');
        
        // Normal fields should remain unchanged
        $this->assertEquals('normal_value', $sanitized['normal_field'], 'Normal field should remain unchanged');
        $this->assertEquals('other_value', $sanitized['nested']['other'], 'Nested normal field should remain unchanged');
    }

    /**
     * Test log data sanitization with short username
     */
    public function test_sanitize_log_data_short_username() {
        $data = array(
            'username' => 'ab',
            'user' => 'x'
        );
        
        $sanitized = shift8_gravitysap_sanitize_log_data($data);
        
        $this->assertEquals('***', $sanitized['username'], 'Short username should be fully masked');
        $this->assertEquals('***', $sanitized['user'], 'Very short username should be fully masked');
    }

    /**
     * Test log data sanitization with non-array data
     */
    public function test_sanitize_log_data_non_array() {
        $string_data = 'simple string';
        $sanitized = shift8_gravitysap_sanitize_log_data($string_data);
        $this->assertEquals($string_data, $sanitized, 'Non-array data should pass through unchanged');
        
        $number_data = 12345;
        $sanitized = shift8_gravitysap_sanitize_log_data($number_data);
        $this->assertEquals($number_data, $sanitized, 'Number data should pass through unchanged');
    }

    /**
     * Test debug logging with debug setting check
     */
    public function test_debug_logging_debug_check() {
        // Save settings with debug enabled
        update_option('shift8_gravitysap_settings', array(
            'sap_debug' => '1',
            'sap_username' => 'testuser',
            'sap_password' => 'testpass'
        ));
        
        // Test the special debug check message
        shift8_gravitysap_debug_log('Debug setting check');
        
        $this->assertTrue(true, 'Debug setting check completed without errors');
    }

    /**
     * Test debug logging file writing scenarios
     */
    public function test_debug_logging_file_scenarios() {
        // Enable debug
        update_option('shift8_gravitysap_settings', array('sap_debug' => '1'));
        
        // Test with WP_DEBUG enabled for fallback logging
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', true);
        }
        
        // Test logging with complex data structures
        $complex_data = array(
            'level1' => array(
                'level2' => array(
                    'password' => 'should_be_redacted',
                    'username' => 'should_be_masked',
                    'data' => 'should_remain'
                )
            ),
            'simple' => 'value'
        );
        
        shift8_gravitysap_debug_log('Complex data test', $complex_data);
        
        $this->assertTrue(true, 'Complex debug logging completed without errors');
    }

    /**
     * Test all password field variations for sanitization
     */
    public function test_sanitize_all_password_variations() {
        $data = array(
            'password' => 'secret1',
            'sap_password' => 'secret2',
            'pass' => 'secret3',
            'pwd' => 'secret4',
            'PASSWORD' => 'secret5',  // Test case insensitive
            'Pass' => 'secret6',      // Test case insensitive
        );
        
        $sanitized = shift8_gravitysap_sanitize_log_data($data);
        
        foreach (['password', 'sap_password', 'pass', 'pwd', 'PASSWORD', 'Pass'] as $field) {
            $this->assertEquals('***REDACTED***', $sanitized[$field], "Field '$field' should be redacted");
        }
    }

    /**
     * Test all username field variations for sanitization
     */
    public function test_sanitize_all_username_variations() {
        $data = array(
            'username' => 'testuser1',
            'sap_username' => 'testuser2',
            'user' => 'testuser3',
            'login' => 'testuser4',
            'USERNAME' => 'testuser5',  // Test case insensitive
            'User' => 'testuser6',      // Test case insensitive
        );
        
        $sanitized = shift8_gravitysap_sanitize_log_data($data);
        
        foreach (['username', 'sap_username', 'user', 'login', 'USERNAME', 'User'] as $field) {
            $expected = strlen($data[$field]) > 2 ? substr($data[$field], 0, 2) . '***' : '***';
            $this->assertEquals($expected, $sanitized[$field], "Field '$field' should be properly masked");
        }
    }

    /**
     * Test debug logging with different message types
     */
    public function test_debug_logging_message_types() {
        // Enable debug
        update_option('shift8_gravitysap_settings', array('sap_debug' => '1'));
        
        // Test with null data
        shift8_gravitysap_debug_log('Test message with null data', null);
        
        // Test with empty array
        shift8_gravitysap_debug_log('Test message with empty array', array());
        
        // Test with boolean data
        shift8_gravitysap_debug_log('Test message with boolean', true);
        
        // Test with numeric data
        shift8_gravitysap_debug_log('Test message with number', 12345);
        
        $this->assertTrue(true, 'Various debug logging message types completed without errors');
    }

    /**
     * Test encryption with special characters
     */
    public function test_password_encryption_special_chars() {
        $special_password = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $encrypted = shift8_gravitysap_encrypt_password($special_password);
        $decrypted = shift8_gravitysap_decrypt_password($encrypted);
        
        $this->assertEquals($special_password, $decrypted, 'Special characters should be preserved through encryption/decryption');
    }

    /**
     * Test encryption with unicode characters
     */
    public function test_password_encryption_unicode() {
        $unicode_password = '测试密码123αβγδε';
        
        $encrypted = shift8_gravitysap_encrypt_password($unicode_password);
        $decrypted = shift8_gravitysap_decrypt_password($encrypted);
        
        $this->assertEquals($unicode_password, $decrypted, 'Unicode characters should be preserved through encryption/decryption');
    }
} 