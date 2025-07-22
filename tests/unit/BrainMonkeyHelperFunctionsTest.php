<?php
/**
 * Helper Functions tests using Brain/Monkey
 *
 * @package Shift8\GravitySAP\Tests\Unit
 */

namespace Shift8\GravitySAP\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Test global helper functions using Brain/Monkey
 */
class BrainMonkeyHelperFunctionsTest extends TestCase {

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Setup global test options
        global $_test_options;
        $_test_options = array();
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test password encryption and decryption
     */
    public function test_password_encryption_decryption() {
        // Mock wp_salt function
        Functions\when('wp_salt')->justReturn('test_salt_auth_1234567890abcdef');
        
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
        // Mock get_option to return debug disabled
        Functions\expect('get_option')
            ->once()
            ->with('shift8_gravitysap_settings', array())
            ->andReturn(array('sap_debug' => '0'));
        
        // Should not call any file operations since debug is disabled
        shift8_gravitysap_debug_log('Test message', array('test' => 'data'));
        
        $this->assertTrue(true, 'Debug logging with disabled debug completed without errors');
    }

    /**
     * Test debug logging with enabled debug
     */
    public function test_debug_logging_enabled() {
        // Mock get_option to return debug enabled
        Functions\when('get_option')
            ->justReturn(array('sap_debug' => '1'));
        
        // Mock current_time
        Functions\when('current_time')
            ->justReturn('2025-01-22 14:30:00');
        
        // Mock sanitize_text_field
        Functions\when('sanitize_text_field')
            ->justReturn('Test debug message');
        
        // Mock wp_json_encode
        Functions\when('wp_json_encode')
            ->justReturn('{"key":"value"}');
        
        // Mock wp_upload_dir
        Functions\when('wp_upload_dir')
            ->justReturn(array(
                'basedir' => '/tmp/test_uploads',
                'baseurl' => 'http://example.com/uploads'
            ));
        
        // Mock filesystem operations with simpler approach
        Functions\when('is_dir')->justReturn(true);
        Functions\when('WP_Filesystem')->justReturn(true);
        
        // Test logging - will use the global mock from bootstrap
        shift8_gravitysap_debug_log('Test debug message', array('key' => 'value'));
        
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
        // Mock get_option for debug setting check
        Functions\when('get_option')
            ->justReturn(array(
                'sap_debug' => '1',
                'sap_username' => 'testuser',
                'sap_password' => 'testpass'
            ));
        
        // Mock current_time for the debug logging
        Functions\when('current_time')
            ->justReturn('2025-01-22 14:30:00');
        
        // Mock sanitize_text_field for the log message
        Functions\when('sanitize_text_field')
            ->justReturn('Debug setting check');
        
        // Mock wp_json_encode for the debug output
        Functions\when('wp_json_encode')
            ->justReturn('{"sap_debug":"1","sap_username":"te***","sap_password":"***REDACTED***"}');
        
        // Mock wp_upload_dir for the file operations
        Functions\when('wp_upload_dir')
            ->justReturn(array('basedir' => '/tmp/uploads'));
        
        // Test the special debug check message
        shift8_gravitysap_debug_log('Debug setting check');
        
        $this->assertTrue(true, 'Debug setting check completed without errors');
    }

    /**
     * Test encryption with special characters
     */
    public function test_password_encryption_special_chars() {
        Functions\when('wp_salt')->justReturn('test_salt_auth_1234567890abcdef');
        
        $special_password = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $encrypted = shift8_gravitysap_encrypt_password($special_password);
        $decrypted = shift8_gravitysap_decrypt_password($encrypted);
        
        $this->assertEquals($special_password, $decrypted, 'Special characters should be preserved through encryption/decryption');
    }

    /**
     * Test encryption with unicode characters
     */
    public function test_password_encryption_unicode() {
        Functions\when('wp_salt')->justReturn('test_salt_auth_1234567890abcdef');
        
        $unicode_password = '测试密码123αβγδε';
        
        $encrypted = shift8_gravitysap_encrypt_password($unicode_password);
        $decrypted = shift8_gravitysap_decrypt_password($encrypted);
        
        $this->assertEquals($unicode_password, $decrypted, 'Unicode characters should be preserved through encryption/decryption');
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
            'PASSWORD' => 'secret5',
            'Pass' => 'secret6',
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
            'USERNAME' => 'testuser5',
            'User' => 'testuser6',
        );
        
        $sanitized = shift8_gravitysap_sanitize_log_data($data);
        
        foreach (['username', 'sap_username', 'user', 'login', 'USERNAME', 'User'] as $field) {
            $expected = strlen($data[$field]) > 2 ? substr($data[$field], 0, 2) . '***' : '***';
            $this->assertEquals($expected, $sanitized[$field], "Field '$field' should be properly masked");
        }
    }
} 