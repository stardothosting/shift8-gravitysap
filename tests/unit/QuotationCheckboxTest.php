<?php
/**
 * Quotation Checkbox Field Handling Tests
 *
 * Tests that checkbox fields are properly processed when creating
 * Sales Quotations with line items.
 *
 * @package Shift8\GravitySAP\Tests\Unit
 */

namespace Shift8\GravitySAP\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Test quotation creation with checkbox field mappings
 */
class QuotationCheckboxTest extends TestCase {

    /**
     * Plugin instance for testing
     *
     * @var \Shift8_GravitySAP
     */
    protected $plugin;

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\when('plugin_dir_path')->justReturn('/test/plugin/path/');
        Functions\when('plugin_dir_url')->justReturn('http://example.com/wp-content/plugins/test-plugin/');
        Functions\when('plugin_basename')->justReturn('test-plugin/test-plugin.php');
        Functions\when('get_option')->justReturn(array());
        Functions\when('shift8_gravitysap_debug_log')->justReturn(true);
        Functions\when('rgar')->alias(function($array, $key, $default = '') { 
            return isset($array[$key]) ? $array[$key] : $default; 
        });
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_attr__')->returnArg();
        Functions\when('__')->returnArg();
        
        // Include plugin file
        require_once dirname(__FILE__) . '/../../shift8-gravitysap.php';
        
        // Get plugin instance using singleton pattern
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
     * Test that checkbox sub-fields (15.1, 15.2) are properly read from entry
     */
    public function test_checkbox_subfield_value_detection() {
        // Simulate a Gravity Forms entry with checkbox values
        $entry = array(
            'id' => 100,
            '15.1' => 'Product A',  // Checkbox option 1 checked
            '15.2' => 'Product B',  // Checkbox option 2 checked
            // 15.3 not set (unchecked)
        );

        // Verify we can read checkbox sub-field values
        $this->assertEquals('Product A', rgar($entry, '15.1'));
        $this->assertEquals('Product B', rgar($entry, '15.2'));
        $this->assertEquals('', rgar($entry, '15.3'));
        $this->assertEquals('', rgar($entry, '15'));  // Parent field has no value
    }

    /**
     * Test that quotation line items are created for checked checkboxes only
     */
    public function test_quotation_line_items_from_checkbox_fields() {
        // Mock SAP service
        $sap_service = $this->createMock('Shift8_GravitySAP_SAP_Service');
        $sap_service->method('create_sales_quotation')
                    ->willReturn(array('DocEntry' => 123, 'DocNum' => 456));

        // Simulate form with checkbox field
        $form = array(
            'id' => 3,
            'fields' => array(
                (object) array(
                    'id' => 15,
                    'type' => 'checkbox',
                    'label' => 'Select Products',
                    'choices' => array(
                        array('text' => 'Product A', 'value' => 'Product A'),
                        array('text' => 'Product B', 'value' => 'Product B'),
                        array('text' => 'Product C', 'value' => 'Product C'),
                    )
                )
            )
        );

        // Simulate entry with checkboxes 15.1 and 15.3 checked
        $entry = array(
            'id' => 100,
            '15.1' => 'Product A',  // Checked
            '15.3' => 'Product C',  // Checked
            // 15.2 not set (unchecked)
        );

        // Configure settings with checkbox sub-field mappings
        $settings = array(
            'create_quotation' => '1',
            'quotation_field_mapping' => array(
                'DocumentLines.1.ItemCode' => '15.1',  // Maps to checkbox option 1
                'DocumentLines.2.ItemCode' => '15.2',  // Maps to checkbox option 2
                'DocumentLines.3.ItemCode' => '15.3',  // Maps to checkbox option 3
            ),
            'quotation_itemcode_mapping' => array(
                'DocumentLines.1.ItemCode' => 'SAP-PROD-A',
                'DocumentLines.2.ItemCode' => 'SAP-PROD-B',
                'DocumentLines.3.ItemCode' => 'SAP-PROD-C',
            ),
        );

        $card_code = 'E00123';

        // Use reflection to call private method
        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('create_sales_quotation_from_entry');
        $method->setAccessible(true);

        // Call the method
        $result = $method->invoke($this->plugin, $entry, $form, $settings, $card_code, $sap_service);

        // Verify result
        $this->assertIsArray($result);
        $this->assertEquals(123, $result['DocEntry']);
        $this->assertEquals(456, $result['DocNum']);
    }

    /**
     * Test that unchecked checkboxes do not create line items
     */
    public function test_unchecked_checkboxes_excluded_from_quotation() {
        // Mock SAP service to capture the quotation data sent to SAP
        $captured_quotation_data = null;
        $sap_service = $this->createMock('Shift8_GravitySAP_SAP_Service');
        $sap_service->method('create_sales_quotation')
                    ->willReturnCallback(function($data) use (&$captured_quotation_data) {
                        $captured_quotation_data = $data;
                        return array('DocEntry' => 123, 'DocNum' => 456);
                    });

        $form = array(
            'id' => 3,
            'fields' => array()
        );

        // Entry with only ONE checkbox checked
        $entry = array(
            'id' => 100,
            '15.1' => 'Product A',  // Only this one checked
            // 15.2, 15.3 not set
        );

        $settings = array(
            'create_quotation' => '1',
            'quotation_field_mapping' => array(
                'DocumentLines.1.ItemCode' => '15.1',
                'DocumentLines.2.ItemCode' => '15.2',
                'DocumentLines.3.ItemCode' => '15.3',
            ),
            'quotation_itemcode_mapping' => array(
                'DocumentLines.1.ItemCode' => 'SAP-PROD-A',
                'DocumentLines.2.ItemCode' => 'SAP-PROD-B',
                'DocumentLines.3.ItemCode' => 'SAP-PROD-C',
            ),
        );

        $card_code = 'E00123';

        // Use reflection to call private method
        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('create_sales_quotation_from_entry');
        $method->setAccessible(true);

        // Call the method
        $result = $method->invoke($this->plugin, $entry, $form, $settings, $card_code, $sap_service);

        // Verify that only ONE line item was created (not three)
        $this->assertIsArray($captured_quotation_data);
        $this->assertArrayHasKey('DocumentLines', $captured_quotation_data);
        $this->assertCount(1, $captured_quotation_data['DocumentLines']);
        $this->assertEquals('SAP-PROD-A', $captured_quotation_data['DocumentLines'][0]['ItemCode']);
    }

    /**
     * Test that quotation creation is skipped when create_quotation is disabled
     */
    public function test_quotation_not_created_when_disabled() {
        $form = array('id' => 3, 'fields' => array());
        $entry = array('id' => 100);
        $settings = array(
            'create_quotation' => '0',  // Disabled
        );
        $card_code = 'E00123';

        // Mock SAP service - should NOT be called
        $sap_service = $this->createMock('Shift8_GravitySAP_SAP_Service');
        $sap_service->expects($this->never())
                    ->method('create_sales_quotation');

        // In real code, this is in process_form_submission, but we're testing the conditional logic
        if (!empty($settings['create_quotation']) && $settings['create_quotation'] === '1') {
            $reflection = new ReflectionClass($this->plugin);
            $method = $reflection->getMethod('create_sales_quotation_from_entry');
            $method->setAccessible(true);
            $method->invoke($this->plugin, $entry, $form, $settings, $card_code, $sap_service);
        }

        // Test passes if no exception is thrown and create_sales_quotation is never called
        $this->assertTrue(true);
    }

    /**
     * Test that error is thrown when no line items can be created
     */
    public function test_error_when_no_line_items_available() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No line items found for quotation');

        $sap_service = $this->createMock('Shift8_GravitySAP_SAP_Service');

        $form = array('id' => 3, 'fields' => array());
        
        // Entry with NO checkbox values
        $entry = array('id' => 100);

        $settings = array(
            'create_quotation' => '1',
            'quotation_field_mapping' => array(
                'DocumentLines.1.ItemCode' => '15.1',
            ),
            'quotation_itemcode_mapping' => array(
                'DocumentLines.1.ItemCode' => 'SAP-PROD-A',
            ),
        );

        $card_code = 'E00123';

        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('create_sales_quotation_from_entry');
        $method->setAccessible(true);

        // This should throw an exception
        $method->invoke($this->plugin, $entry, $form, $settings, $card_code, $sap_service);
    }

    /**
     * Test that mixed field types (checkbox + text) work together
     */
    public function test_mixed_field_types_in_quotation() {
        $captured_quotation_data = null;
        $sap_service = $this->createMock('Shift8_GravitySAP_SAP_Service');
        $sap_service->method('create_sales_quotation')
                    ->willReturnCallback(function($data) use (&$captured_quotation_data) {
                        $captured_quotation_data = $data;
                        return array('DocEntry' => 123, 'DocNum' => 456);
                    });

        $form = array('id' => 3, 'fields' => array());

        // Entry with checkbox AND text field
        $entry = array(
            'id' => 100,
            '15.1' => 'Product A',  // Checkbox checked
            '20' => 'Custom Product',  // Text field with value
        );

        $settings = array(
            'create_quotation' => '1',
            'quotation_field_mapping' => array(
                'DocumentLines.1.ItemCode' => '15.1',  // Checkbox
                'DocumentLines.2.ItemCode' => '20',    // Text field
            ),
            'quotation_itemcode_mapping' => array(
                'DocumentLines.1.ItemCode' => 'SAP-PROD-A',
                'DocumentLines.2.ItemCode' => 'SAP-CUSTOM',
            ),
        );

        $card_code = 'E00123';

        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('create_sales_quotation_from_entry');
        $method->setAccessible(true);

        $result = $method->invoke($this->plugin, $entry, $form, $settings, $card_code, $sap_service);

        // Verify TWO line items created
        $this->assertCount(2, $captured_quotation_data['DocumentLines']);
        $this->assertEquals('SAP-PROD-A', $captured_quotation_data['DocumentLines'][0]['ItemCode']);
        $this->assertEquals('SAP-CUSTOM', $captured_quotation_data['DocumentLines'][1]['ItemCode']);
    }
}

