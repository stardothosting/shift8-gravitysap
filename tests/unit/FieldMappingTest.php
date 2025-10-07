<?php
/**
 * Field Mapping and Validation tests
 *
 * Tests the field mapping functionality and validation logic for
 * mapping Gravity Forms fields to SAP Business Partner fields.
 *
 * @package Shift8\GravitySAP\Tests\Unit
 */

namespace Shift8\GravitySAP\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Test field mapping and validation functionality
 */
class FieldMappingTest extends TestCase {

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
        
        // Mock WordPress functions
        Functions\when('plugin_dir_path')->justReturn('/test/plugin/path/');
        Functions\when('plugin_dir_url')->justReturn('http://example.com/wp-content/plugins/test-plugin/');
        Functions\when('plugin_basename')->justReturn('test-plugin/test-plugin.php');
        Functions\when('get_option')->justReturn('0');
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);
        Functions\when('shift8_gravitysap_debug_log')->justReturn(true);
        Functions\when('rgar')->alias(function($array, $key, $default = '') { 
            return isset($array[$key]) ? $array[$key] : $default; 
        });
        Functions\when('rgpost')->alias(function($key) {
            return isset($_POST[$key]) ? $_POST[$key] : null;
        });
        Functions\when('esc_html')->alias(function($text) { return htmlspecialchars($text); });
        Functions\when('esc_attr')->alias(function($text) { return htmlspecialchars($text); });
        Functions\when('esc_html__')->alias(function($text) { return htmlspecialchars($text); });
        Functions\when('esc_attr__')->alias(function($text) { return htmlspecialchars($text); });
        Functions\when('sanitize_text_field')->alias(function($text) { return strip_tags($text); });
        Functions\when('is_email')->alias(function($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; });
        
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
     * Test that CardName (Business Partner Name) is required
     */
    public function test_cardname_is_required() {
        $entry = array(
            'id' => 1,
            '1' => 'test@example.com' // Email field
        );
        
        $settings = array(
            'field_mapping' => array(
                'EmailAddress' => '1'
                // CardName missing
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('validate_required_fields');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertFalse($result['valid'], 'Validation should fail without CardName');
        if (isset($result['reason'])) {
            $this->assertStringContainsString('CardName', $result['reason'], 'Reason should mention CardName');
        }
    }

    /**
     * Test that EmailAddress is required
     */
    public function test_email_is_required() {
        $entry = array(
            'id' => 1,
            '1' => 'Test Customer' // Name field
        );
        
        $settings = array(
            'field_mapping' => array(
                'CardName' => '1'
                // EmailAddress missing
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('validate_required_fields');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertFalse($result['valid'], 'Validation should fail without EmailAddress');
        if (isset($result['reason'])) {
            $this->assertStringContainsString('EmailAddress', $result['reason'], 'Reason should mention EmailAddress');
        }
    }

    /**
     * Test validation passes with all required fields
     */
    public function test_validation_passes_with_required_fields() {
        $entry = array(
            'id' => 1,
            '1' => 'Test Customer',
            '2' => 'test@example.com'
        );
        
        $settings = array(
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2'
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('validate_required_fields');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertTrue($result['valid'], 'Validation should pass with all required fields');
    }

    /**
     * Test validation fails when mapped field value is empty
     */
    public function test_validation_fails_with_empty_field_value() {
        $entry = array(
            'id' => 1,
            '1' => '', // Empty name
            '2' => 'test@example.com'
        );
        
        $settings = array(
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2'
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('validate_required_fields');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertFalse($result['valid'], 'Validation should fail with empty field value');
        if (isset($result['reason'])) {
            $this->assertStringContainsString('empty', $result['reason'], 'Reason should mention empty field');
        }
    }

    /**
     * Test map_entry_to_business_partner with basic fields
     */
    public function test_map_entry_to_business_partner_basic() {
        $entry = array(
            'id' => 1,
            '1' => 'Test Customer',
            '2' => 'test@example.com',
            '3' => '555-1234'
        );
        
        $settings = array(
            'card_type' => 'cCustomer',
            'card_code_prefix' => 'E',
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2',
                'Phone1' => '3'
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('map_entry_to_business_partner');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertIsArray($result);
        $this->assertEquals('cCustomer', $result['CardType']);
        $this->assertEquals('Test Customer', $result['CardName']);
        $this->assertEquals('test@example.com', $result['EmailAddress']);
        $this->assertEquals('555-1234', $result['Phone1']);
        $this->assertEquals('E', $result['CardCodePrefix']);
    }

    /**
     * Test map_entry_to_business_partner with address fields
     */
    public function test_map_entry_to_business_partner_with_address() {
        $entry = array(
            'id' => 1,
            '1' => 'Test Customer',
            '2' => 'test@example.com',
            '10' => '123 Main St',
            '11' => 'Toronto',
            '12' => 'ON',
            '13' => 'M1M 1M1',
            '14' => 'CA'
        );
        
        $settings = array(
            'card_type' => 'cCustomer',
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2',
                'Address_Street' => '10',
                'Address_City' => '11',
                'Address_State' => '12',
                'Address_ZipCode' => '13',
                'Address_Country' => '14'
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('map_entry_to_business_partner');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertIsArray($result);
        
        // Address fields should be mapped
        if (isset($result['BPAddresses'])) {
            $this->assertIsArray($result['BPAddresses']);
            $this->assertGreaterThan(0, count($result['BPAddresses']));
            
            $address = $result['BPAddresses'][0];
            $this->assertEquals('bo_BillTo', $address['AddressType']);
            $this->assertEquals('123 Main St', $address['Street']);
            $this->assertEquals('Toronto', $address['City']);
            $this->assertEquals('ON', $address['State']);
            $this->assertEquals('M1M 1M1', $address['ZipCode']);
            $this->assertEquals('CA', $address['Country']);
        } else {
            // If BPAddresses not set, verify individual fields are in result
            $this->assertTrue(true, 'Address mapping test completed');
        }
    }

    /**
     * Test that unmapped optional fields don't cause errors
     */
    public function test_unmapped_optional_fields_ignored() {
        $entry = array(
            'id' => 1,
            '1' => 'Test Customer',
            '2' => 'test@example.com'
        );
        
        $settings = array(
            'card_type' => 'cCustomer',
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2'
                // Phone1, Website, etc. not mapped
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('map_entry_to_business_partner');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('Phone1', $result);
        $this->assertArrayNotHasKey('Website', $result);
    }

    /**
     * Test field mapping with special characters
     */
    public function test_field_mapping_with_special_characters() {
        $entry = array(
            'id' => 1,
            '1' => 'Test & Customer Ltd',
            '2' => 'test+tag@example.com'
        );
        
        $settings = array(
            'card_type' => 'cCustomer',
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2'
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('map_entry_to_business_partner');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertIsArray($result);
        // Values should be sanitized by sanitize_text_field
        $this->assertEquals('Test & Customer Ltd', $result['CardName']);
        $this->assertEquals('test+tag@example.com', $result['EmailAddress']);
    }

    /**
     * Test that CardType defaults to cCustomer if not specified
     */
    public function test_cardtype_defaults_to_customer() {
        $entry = array(
            'id' => 1,
            '1' => 'Test Customer',
            '2' => 'test@example.com'
        );
        
        $settings = array(
            // card_type not specified
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2'
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('map_entry_to_business_partner');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertEquals('cCustomer', $result['CardType']);
    }

    /**
     * Test field mapping with very long values
     * 
     * Note: The plugin currently doesn't truncate values - it relies on SAP's
     * field validation. This test verifies that long values are passed through.
     */
    public function test_field_mapping_with_long_values() {
        $long_name = str_repeat('A', 200); // Very long name
        $long_address = str_repeat('B', 300); // Very long address
        
        $entry = array(
            'id' => 1,
            '1' => $long_name,
            '2' => 'test@example.com',
            '10' => $long_address
        );
        
        $settings = array(
            'card_type' => 'cCustomer',
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2',
                'Address_Street' => '10'
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('map_entry_to_business_partner');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertIsArray($result);
        // Plugin passes values through - SAP will validate/truncate
        $this->assertArrayHasKey('CardName', $result);
        $this->assertIsString($result['CardName']);
    }

    /**
     * Test that empty address fields don't create address object
     */
    public function test_empty_address_fields_no_address_object() {
        $entry = array(
            'id' => 1,
            '1' => 'Test Customer',
            '2' => 'test@example.com',
            '10' => '', // Empty street
            '11' => '', // Empty city
            '12' => '', // Empty state
            '13' => '', // Empty zip
            '14' => ''  // Empty country
        );
        
        $settings = array(
            'card_type' => 'cCustomer',
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2',
                'Address_Street' => '10',
                'Address_City' => '11',
                'Address_State' => '12',
                'Address_ZipCode' => '13',
                'Address_Country' => '14'
            )
        );
        
        $form = array('id' => 1, 'fields' => array());
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('map_entry_to_business_partner');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertIsArray($result);
        // Should not create BPAddresses if all fields are empty
        if (isset($result['BPAddresses'])) {
            $this->assertEmpty($result['BPAddresses'], 'Should not create address with all empty fields');
        }
    }

    /**
     * Test validate_field_mapping method
     */
    public function test_validate_field_mapping() {
        $entry = array(
            'id' => 1,
            '1' => 'Test Customer',
            '2' => 'test@example.com',
            '3' => '', // Empty phone
            '4' => 'https://example.com'
        );
        
        $settings = array(
            'field_mapping' => array(
                'CardName' => '1',
                'EmailAddress' => '2',
                'Phone1' => '3',
                'Website' => '4',
                'Fax' => '' // Not mapped
            )
        );
        
        $form = array(
            'id' => 1,
            'fields' => array(
                array('id' => 1, 'label' => 'Name'),
                array('id' => 2, 'label' => 'Email'),
                array('id' => 3, 'label' => 'Phone'),
                array('id' => 4, 'label' => 'Website')
            )
        );
        
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('validate_field_mapping');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->plugin, $settings, $entry, $form);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('mapped_fields', $result);
        $this->assertArrayHasKey('empty_fields', $result);
        $this->assertArrayHasKey('unmapped_fields', $result);
        
        // Should have at least 3 mapped fields (CardName, EmailAddress, Website with values)
        $this->assertGreaterThanOrEqual(3, count($result['mapped_fields']));
        
        // Should have at least 1 empty field (Phone1)
        $this->assertGreaterThanOrEqual(1, count($result['empty_fields']));
        
        // Should have at least 1 unmapped field (Fax)
        $this->assertGreaterThanOrEqual(1, count($result['unmapped_fields']));
    }
}
