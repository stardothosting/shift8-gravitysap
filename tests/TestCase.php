<?php
/**
 * Base test case for all plugin tests
 *
 * @package Shift8\GravitySAP\Tests
 */

namespace Shift8\GravitySAP\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case class that provides common functionality for all tests
 */
abstract class TestCase extends PHPUnitTestCase {

    /**
     * Plugin settings used in tests
     *
     * @var array
     */
    protected $test_settings = array();

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Set up test settings
        $this->test_settings = array(
            'sap_endpoint' => 'https://test-sap-server:50000/b1s/v1',
            'sap_company_db' => 'TESTDB',
            'sap_username' => 'test_user',
            'sap_password' => 'encrypted_test_password',
            'sap_debug' => '1'
        );
        
        // Clear any existing options
        delete_option('shift8_gravitysap_settings');
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        // Clean up options
        delete_option('shift8_gravitysap_settings');
        
        parent::tearDown();
    }

    /**
     * Helper method to create mock settings
     *
     * @param array $overrides Optional settings to override defaults
     * @return array
     */
    protected function create_test_settings($overrides = array()) {
        return array_merge($this->test_settings, $overrides);
    }

    /**
     * Helper method to save settings to WordPress options
     *
     * @param array $settings Settings to save
     * @return bool
     */
    protected function save_test_settings($settings = null) {
        if ($settings === null) {
            $settings = $this->test_settings;
        }
        return update_option('shift8_gravitysap_settings', $settings);
    }

    /**
     * Assert that a WordPress hook exists
     *
     * @param string $hook_name The hook name to check
     * @param string|null $function_name Optional specific function name
     * @param int|null $priority Optional priority to check
     */
    protected function assertHookExists($hook_name, $function_name = null, $priority = null) {
        global $wp_filter;
        
        $this->assertArrayHasKey($hook_name, $wp_filter, "Hook '$hook_name' does not exist");
        
        if ($function_name !== null) {
            $hook_found = false;
            if (isset($wp_filter[$hook_name])) {
                foreach ($wp_filter[$hook_name]->callbacks as $priority_callbacks) {
                    foreach ($priority_callbacks as $callback) {
                        $callback_name = '';
                        if (is_string($callback['function'])) {
                            $callback_name = $callback['function'];
                        } elseif (is_array($callback['function']) && count($callback['function']) === 2) {
                            $callback_name = get_class($callback['function'][0]) . '::' . $callback['function'][1];
                        }
                        
                        if (strpos($callback_name, $function_name) !== false) {
                            $hook_found = true;
                            break 2;
                        }
                    }
                }
            }
            $this->assertTrue($hook_found, "Function '$function_name' not found in hook '$hook_name'");
        }
    }

    /**
     * Create a mock Gravity Form for testing
     *
     * @param array $form_data Optional form data overrides
     * @return array
     */
    protected function create_mock_gravity_form($form_data = array()) {
        $default_form = array(
            'id' => '1',
            'title' => 'Test Form',
            'fields' => array(
                array(
                    'id' => '1',
                    'type' => 'text',
                    'label' => 'Full Name',
                    'adminLabel' => 'name'
                ),
                array(
                    'id' => '2', 
                    'type' => 'email',
                    'label' => 'Email Address',
                    'adminLabel' => 'email'
                ),
                array(
                    'id' => '3',
                    'type' => 'phone',
                    'label' => 'Phone Number',
                    'adminLabel' => 'phone'
                )
            )
        );

        return array_merge($default_form, $form_data);
    }

    /**
     * Create mock form entry data
     *
     * @param array $entry_data Optional entry data overrides
     * @return array
     */
    protected function create_mock_entry($entry_data = array()) {
        $default_entry = array(
            'id' => '1',
            'form_id' => '1',
            '1' => 'John Doe',
            '2' => 'john.doe@example.com',
            '3' => '+1-555-123-4567',
            'date_created' => current_time('mysql'),
            'is_starred' => '0',
            'is_read' => '0',
            'ip' => '127.0.0.1',
            'source_url' => 'http://example.com/test-form',
            'user_agent' => 'Test User Agent'
        );

        return array_merge($default_entry, $entry_data);
    }
} 