<?php
/**
 * Business Partner Lookup tests for v1.4.0 features
 *
 * Tests the centralized BP lookup functionality in SAP Service
 *
 * @package Shift8\GravitySAP\Tests\Unit
 */

namespace Shift8\GravitySAP\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Test the Business Partner lookup functionality
 */
class BPLookupTest extends TestCase {

    /**
     * SAP Service instance for testing
     *
     * @var Shift8_GravitySAP_SAP_Service
     */
    protected $sap_service;

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\when('wp_remote_request')->justReturn(array(
            'response' => array('code' => 200),
            'body' => json_encode(array('SessionId' => 'test_session_123'))
        ));
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn(json_encode(array('SessionId' => 'test_session_123')));
        Functions\when('wp_remote_retrieve_headers')->justReturn(array());
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('esc_html')->alias(function($text) { return htmlspecialchars($text); });
        Functions\when('esc_url_raw')->alias(function($url) { return filter_var($url, FILTER_SANITIZE_URL); });
        Functions\when('get_option')->justReturn('0');
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);
        Functions\when('shift8_gravitysap_debug_log')->justReturn(true);
        Functions\when('shift8_gravitysap_decrypt_password')->justReturn('decrypted_password');
        Functions\when('rgar')->alias(function($array, $key, $default = '') { 
            return isset($array[$key]) ? $array[$key] : $default; 
        });
        
        // Include SAP Service class
        require_once dirname(dirname(__DIR__)) . '/includes/class-shift8-gravitysap-sap-service.php';
        
        // Create instance with test settings
        $test_settings = array(
            'sap_endpoint' => 'https://test-server:50000/b1s/v1',
            'sap_company_db' => 'TEST_DB',
            'sap_username' => 'test_user',
            'sap_password' => 'test_password'
        );
        
        $this->sap_service = new \Shift8_GravitySAP_SAP_Service($test_settings);
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Mock sequential HTTP responses
     */
    private function mock_sequential_http_responses($responses, $codes = null) {
        if ($codes === null) {
            $codes = array_fill(0, count($responses), 200);
        }
        
        $call_count = 0;
        
        Functions\when('wp_remote_request')->alias(function() use ($responses, $codes, &$call_count) {
            $response_body = isset($responses[$call_count]) ? $responses[$call_count] : '{}';
            $response_code = isset($codes[$call_count]) ? $codes[$call_count] : 200;
            $call_count++;
            
            return array(
                'response' => array('code' => $response_code),
                'body' => $response_body
            );
        });
        
        Functions\when('wp_remote_retrieve_response_code')->alias(function() use ($codes, &$call_count) {
            $code_index = max(0, $call_count - 1);
            return isset($codes[$code_index]) ? $codes[$code_index] : 200;
        });
        
        Functions\when('wp_remote_retrieve_body')->alias(function() use ($responses, &$call_count) {
            $response_index = max(0, $call_count - 1);
            return isset($responses[$response_index]) ? $responses[$response_index] : '{}';
        });
    }

    /**
     * Test find_existing_business_partner returns found when exact match exists
     */
    public function test_find_existing_bp_exact_match() {
        // Mock responses: auth, exact match query returns a result
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode(array('value' => array( // exact match found
                    array(
                        'CardCode' => 'C10001',
                        'CardName' => 'Test Company Inc',
                        'BPAddresses' => array(
                            array(
                                'Country' => 'CA',
                                'ZipCode' => 'M5V 1A1'
                            )
                        )
                    )
                )))
            ),
            array(200, 200)
        );
        
        $result = $this->sap_service->find_existing_business_partner(
            'Test Company Inc',
            'CA',
            'M5V 1A1'
        );
        
        $this->assertTrue($result['found'], 'Should find matching BP');
        $this->assertEquals('C10001', $result['card_code'], 'Should return correct CardCode');
        $this->assertEquals('Test Company Inc', $result['card_name'], 'Should return correct CardName');
    }

    /**
     * Test find_existing_business_partner returns not found when no match
     */
    public function test_find_existing_bp_no_match() {
        // Mock responses: auth, exact query empty, startswith query empty
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode(array('value' => array())), // exact match - none found
                json_encode(array('value' => array()))  // startswith - none found
            ),
            array(200, 200, 200)
        );
        
        $result = $this->sap_service->find_existing_business_partner(
            'Nonexistent Company',
            'CA',
            'M5V 1A1'
        );
        
        $this->assertFalse($result['found'], 'Should not find matching BP');
        $this->assertNull($result['card_code'], 'CardCode should be null');
    }

    /**
     * Test find_existing_business_partner case-insensitive name match
     */
    public function test_find_existing_bp_case_insensitive() {
        // Mock responses: exact query returns result with different case
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode(array('value' => array( // name with different case
                    array(
                        'CardCode' => 'C10001',
                        'CardName' => 'TEST COMPANY INC', // Uppercase in SAP
                        'BPAddresses' => array(
                            array(
                                'Country' => 'CA',
                                'ZipCode' => 'M5V 1A1'
                            )
                        )
                    )
                )))
            ),
            array(200, 200)
        );
        
        // Search with lowercase
        $result = $this->sap_service->find_existing_business_partner(
            'test company inc',
            'CA',
            'M5V 1A1'
        );
        
        $this->assertTrue($result['found'], 'Should find BP with case-insensitive match');
        $this->assertEquals('C10001', $result['card_code']);
    }

    /**
     * Test find_existing_business_partner with startswith fallback
     */
    public function test_find_existing_bp_startswith_fallback() {
        // Mock responses: exact query empty, startswith query finds match
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode(array('value' => array())), // exact match - none
                json_encode(array('value' => array( // startswith finds match
                    array(
                        'CardCode' => 'C10001',
                        'CardName' => 'Test Company Inc.',
                        'BPAddresses' => array(
                            array(
                                'Country' => 'CA',
                                'ZipCode' => 'M5V 1A1'
                            )
                        )
                    )
                )))
            ),
            array(200, 200, 200)
        );
        
        $result = $this->sap_service->find_existing_business_partner(
            'Test Company Inc.', // Exact
            'CA',
            'M5V 1A1'
        );
        
        $this->assertTrue($result['found'], 'Should find via startswith fallback');
        $this->assertEquals('C10001', $result['card_code'], 'Should return correct CardCode');
    }

    /**
     * Test find_existing_business_partner requires country match
     */
    public function test_find_existing_bp_requires_country_match() {
        // Mock responses: BP found but with different country
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode(array('value' => array( // name matches but different country
                    array(
                        'CardCode' => 'C10001',
                        'CardName' => 'Test Company Inc',
                        'BPAddresses' => array(
                            array(
                                'Country' => 'US', // Different country
                                'ZipCode' => 'M5V 1A1'
                            )
                        )
                    )
                )))
            ),
            array(200, 200)
        );
        
        $result = $this->sap_service->find_existing_business_partner(
            'Test Company Inc',
            'CA', // Looking for CA
            'M5V 1A1'
        );
        
        // Should not match because country is different
        $this->assertFalse($result['found'], 'Should not match with different country');
    }

    /**
     * Test find_existing_business_partner requires postal code
     */
    public function test_find_existing_bp_requires_postal() {
        // Search with empty postal code - should return not found with error
        $result = $this->sap_service->find_existing_business_partner(
            'Test Company Inc',
            'CA',
            '' // Empty postal - should fail validation
        );
        
        $this->assertFalse($result['found'], 'Should not match with empty postal code');
        $this->assertNotNull($result['error'], 'Should have error message');
        $this->assertStringContainsString('required', $result['error'], 'Error should mention required fields');
    }

    /**
     * Test find_existing_business_partner handles API error gracefully
     */
    public function test_find_existing_bp_api_error() {
        // Mock responses: auth success, query fails
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode(array( // API error
                    'error' => array(
                        'message' => array('value' => 'Internal server error')
                    )
                ))
            ),
            array(200, 500)
        );
        
        $result = $this->sap_service->find_existing_business_partner(
            'Test Company Inc',
            'CA',
            'M5V 1A1'
        );
        
        // Should return not found on error, not throw exception
        $this->assertFalse($result['found'], 'Should return not found on API error');
    }

    /**
     * Test find_existing_business_partner with multiple BPAddresses
     */
    public function test_find_existing_bp_multiple_addresses() {
        // Mock responses: BP found with multiple addresses, second one matches
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode(array('value' => array(
                    array(
                        'CardCode' => 'C10001',
                        'CardName' => 'Test Company Inc',
                        'BPAddresses' => array(
                            array(
                                'Country' => 'US', // First address - US
                                'ZipCode' => '10001'
                            ),
                            array(
                                'Country' => 'CA', // Second address - CA (match)
                                'ZipCode' => 'M5V 1A1'
                            )
                        )
                    )
                )))
            ),
            array(200, 200)
        );
        
        $result = $this->sap_service->find_existing_business_partner(
            'Test Company Inc',
            'CA',
            'M5V 1A1'
        );
        
        $this->assertTrue($result['found'], 'Should find BP when any address matches');
    }

    /**
     * Test find_existing_business_partner returns first match from multiple BPs
     */
    public function test_find_existing_bp_returns_first_match() {
        // Mock responses: Multiple BPs found
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode(array('value' => array(
                    array(
                        'CardCode' => 'C10001',
                        'CardName' => 'Test Company Inc',
                        'BPAddresses' => array(
                            array('Country' => 'CA', 'ZipCode' => 'M5V 1A1')
                        )
                    ),
                    array(
                        'CardCode' => 'C10002', // Second match
                        'CardName' => 'Test Company Inc',
                        'BPAddresses' => array(
                            array('Country' => 'CA', 'ZipCode' => 'M5V 1A1')
                        )
                    )
                )))
            ),
            array(200, 200)
        );
        
        $result = $this->sap_service->find_existing_business_partner(
            'Test Company Inc',
            'CA',
            'M5V 1A1'
        );
        
        $this->assertTrue($result['found']);
        $this->assertEquals('C10001', $result['card_code'], 'Should return first matching CardCode');
    }

    /**
     * Test find_existing_business_partner with special characters in name
     */
    public function test_find_existing_bp_special_characters() {
        // Mock responses with special character name
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode(array('value' => array(
                    array(
                        'CardCode' => 'C10001',
                        'CardName' => "O'Brien & Sons Ltd.",
                        'BPAddresses' => array(
                            array('Country' => 'CA', 'ZipCode' => 'M5V 1A1')
                        )
                    )
                )))
            ),
            array(200, 200)
        );
        
        $result = $this->sap_service->find_existing_business_partner(
            "O'Brien & Sons Ltd.",
            'CA',
            'M5V 1A1'
        );
        
        $this->assertTrue($result['found'], 'Should handle special characters in name');
    }
    
    /**
     * Test get_business_partner returns BP data
     */
    public function test_get_business_partner_success() {
        $bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 1,
                    'Name' => 'John Doe',
                    'FirstName' => 'John',
                    'LastName' => 'Doe'
                )
            )
        );
        
        // Mock a single successful GET response
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode($bp_data) // GET BP response
            ),
            array(200, 200)
        );
        
        $result = $this->sap_service->get_business_partner('C10001');
        
        $this->assertNotNull($result, 'Should return BP data');
        $this->assertEquals('C10001', $result['CardCode']);
        $this->assertEquals('Test Company', $result['CardName']);
        $this->assertArrayHasKey('ContactEmployees', $result);
    }
    
    /**
     * Test get_business_partner returns null for empty card code
     */
    public function test_get_business_partner_empty_card_code() {
        $result = $this->sap_service->get_business_partner('');
        
        $this->assertNull($result, 'Should return null for empty CardCode');
    }
    
    /**
     * Test add_contact_to_business_partner method exists
     */
    public function test_add_contact_to_business_partner_method_exists() {
        $this->assertTrue(
            method_exists($this->sap_service, 'add_contact_to_business_partner'),
            'add_contact_to_business_partner method should exist'
        );
    }
    
    /**
     * Test add_contact_to_business_partner validates required input
     */
    public function test_add_contact_to_business_partner_requires_card_code() {
        $result = $this->sap_service->add_contact_to_business_partner('', array(
            'FirstName' => 'John',
            'LastName' => 'Doe'
        ));
        
        $this->assertNull($result, 'Should return null for empty CardCode');
    }
    
    /**
     * Test add_contact_to_business_partner requires contact data
     */
    public function test_add_contact_to_business_partner_requires_contact_data() {
        $result = $this->sap_service->add_contact_to_business_partner('C10001', array());
        
        $this->assertNull($result, 'Should return null for empty contact data');
    }
    
    /**
     * Test add_contact_to_business_partner success
     */
    public function test_add_contact_to_business_partner_success() {
        // Mock PATCH response (204 No Content = success) and then GET to retrieve updated BP
        $updated_bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 1,
                    'Name' => 'Existing Contact'
                ),
                array(
                    'InternalCode' => 2,
                    'Name' => 'John Doe',
                    'FirstName' => 'John',
                    'LastName' => 'Doe'
                )
            )
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                '',  // PATCH success (204 returns empty body)
                json_encode($updated_bp_data)  // GET to find InternalCode
            ),
            array(200, 204, 200)
        );
        
        $result = $this->sap_service->add_contact_to_business_partner('C10001', array(
            'FirstName' => 'John',
            'LastName' => 'Doe'
        ));
        
        $this->assertNotNull($result, 'Should return contact data on success');
        $this->assertEquals('John Doe', $result['Name']);
        $this->assertEquals('C10001', $result['CardCode']);
    }
    
    /**
     * Test add_contact_to_business_partner returns InternalCode
     */
    public function test_add_contact_to_business_partner_returns_internal_code() {
        // Mock PATCH response and GET to retrieve updated BP with InternalCode
        $updated_bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 5,
                    'Name' => 'Jane Smith',
                    'FirstName' => 'Jane',
                    'LastName' => 'Smith'
                )
            )
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                '',  // PATCH success (204 returns empty body)
                json_encode($updated_bp_data)  // GET to find InternalCode
            ),
            array(200, 204, 200)
        );
        
        $result = $this->sap_service->add_contact_to_business_partner('C10001', array(
            'FirstName' => 'Jane',
            'LastName' => 'Smith'
        ));
        
        $this->assertNotNull($result, 'Should return contact data on success');
        $this->assertArrayHasKey('InternalCode', $result, 'Should include InternalCode');
        $this->assertEquals(5, $result['InternalCode'], 'Should return correct InternalCode');
    }
    
    /**
     * Test add_contact_to_business_partner uses existing Name field
     */
    public function test_add_contact_to_business_partner_uses_existing_name() {
        // Test that method accepts pre-constructed Name field
        $updated_bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 3,
                    'Name' => 'Primary Contact'
                )
            )
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                '',  // PATCH success
                json_encode($updated_bp_data)  // GET to find InternalCode
            ),
            array(200, 204, 200)
        );
        
        // Contact data with only Name, no FirstName/LastName
        $result = $this->sap_service->add_contact_to_business_partner('C10001', array(
            'Name' => 'Primary Contact'
        ));
        
        $this->assertNotNull($result, 'Should accept pre-constructed Name');
        $this->assertEquals('Primary Contact', $result['Name']);
        $this->assertEquals(3, $result['InternalCode']);
    }
    
    /**
     * Test add_contact_to_business_partner with only first name
     */
    public function test_add_contact_to_business_partner_first_name_only() {
        $updated_bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 4,
                    'Name' => 'Madonna',
                    'FirstName' => 'Madonna'
                )
            )
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                '',  // PATCH success
                json_encode($updated_bp_data)  // GET
            ),
            array(200, 204, 200)
        );
        
        $result = $this->sap_service->add_contact_to_business_partner('C10001', array(
            'FirstName' => 'Madonna'
        ));
        
        $this->assertNotNull($result);
        $this->assertEquals('Madonna', $result['Name']);
    }
    
    /**
     * Test find_existing_contact method exists
     */
    public function test_find_existing_contact_method_exists() {
        $this->assertTrue(
            method_exists($this->sap_service, 'find_existing_contact'),
            'find_existing_contact method should exist'
        );
    }
    
    /**
     * Test find_existing_contact finds matching contact by name and email
     */
    public function test_find_existing_contact_by_name_and_email() {
        $bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 1,
                    'Name' => 'John Doe',
                    'E_Mail' => 'john@example.com'
                ),
                array(
                    'InternalCode' => 2,
                    'Name' => 'Jane Smith',
                    'E_Mail' => 'jane@example.com'
                )
            )
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode($bp_data) // GET BP
            ),
            array(200, 200)
        );
        
        $result = $this->sap_service->find_existing_contact('C10001', 'Jane Smith', 'jane@example.com');
        
        $this->assertNotNull($result, 'Should find matching contact');
        $this->assertEquals(2, $result['InternalCode']);
        $this->assertEquals('Jane Smith', $result['Name']);
    }
    
    /**
     * Test find_existing_contact is case insensitive
     */
    public function test_find_existing_contact_case_insensitive() {
        $bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 1,
                    'Name' => 'John Doe',
                    'E_Mail' => 'John@Example.COM'
                )
            )
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode($bp_data) // GET BP
            ),
            array(200, 200)
        );
        
        // Search with different case
        $result = $this->sap_service->find_existing_contact('C10001', 'JOHN DOE', 'john@example.com');
        
        $this->assertNotNull($result, 'Should find contact with case-insensitive match');
        $this->assertEquals(1, $result['InternalCode']);
    }
    
    /**
     * Test find_existing_contact returns null when no match
     */
    public function test_find_existing_contact_no_match() {
        $bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 1,
                    'Name' => 'John Doe',
                    'E_Mail' => 'john@example.com'
                )
            )
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode($bp_data) // GET BP
            ),
            array(200, 200)
        );
        
        // Search for non-existent contact
        $result = $this->sap_service->find_existing_contact('C10001', 'Jane Smith', 'jane@example.com');
        
        $this->assertNull($result, 'Should return null when no match found');
    }
    
    /**
     * Test find_existing_contact matches by name only when email not provided
     */
    public function test_find_existing_contact_name_only() {
        $bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 3,
                    'Name' => 'Bob Wilson',
                    'E_Mail' => 'bob@example.com'
                )
            )
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode($bp_data) // GET BP
            ),
            array(200, 200)
        );
        
        // Search with name only (no email)
        $result = $this->sap_service->find_existing_contact('C10001', 'Bob Wilson', '');
        
        $this->assertNotNull($result, 'Should find contact by name when email not provided');
        $this->assertEquals(3, $result['InternalCode']);
    }
    
    /**
     * Test find_existing_contact requires both name and email to match when email provided
     */
    public function test_find_existing_contact_requires_email_match() {
        $bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 1,
                    'Name' => 'John Doe',
                    'E_Mail' => 'john@example.com'
                )
            )
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode($bp_data) // GET BP
            ),
            array(200, 200)
        );
        
        // Search with correct name but wrong email
        $result = $this->sap_service->find_existing_contact('C10001', 'John Doe', 'wrong@example.com');
        
        $this->assertNull($result, 'Should not match when email differs');
    }
    
    /**
     * Test find_existing_contact returns null for empty card code
     */
    public function test_find_existing_contact_empty_card_code() {
        $result = $this->sap_service->find_existing_contact('', 'John Doe', 'john@example.com');
        
        $this->assertNull($result, 'Should return null for empty CardCode');
    }
    
    /**
     * Test find_existing_contact returns null for empty contact name
     */
    public function test_find_existing_contact_empty_name() {
        $result = $this->sap_service->find_existing_contact('C10001', '', 'john@example.com');
        
        $this->assertNull($result, 'Should return null for empty contact name');
    }
    
    /**
     * Test find_existing_contact returns null when BP has no contacts
     */
    public function test_find_existing_contact_bp_has_no_contacts() {
        $bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array() // Empty contacts array
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode($bp_data) // GET BP with no contacts
            ),
            array(200, 200)
        );
        
        $result = $this->sap_service->find_existing_contact('C10001', 'John Doe', 'john@example.com');
        
        $this->assertNull($result, 'Should return null when BP has no contacts');
    }
    
    /**
     * Test find_existing_contact matches when existing contact has no email
     */
    public function test_find_existing_contact_existing_has_no_email() {
        $bp_data = array(
            'CardCode' => 'C10001',
            'CardName' => 'Test Company',
            'ContactEmployees' => array(
                array(
                    'InternalCode' => 1,
                    'Name' => 'John Doe'
                    // No E_Mail field
                )
            )
        );
        
        $this->mock_sequential_http_responses(
            array(
                json_encode(array('SessionId' => 'test_session')), // auth
                json_encode($bp_data) // GET BP
            ),
            array(200, 200)
        );
        
        // Search with name only (no email) - should match
        $result = $this->sap_service->find_existing_contact('C10001', 'John Doe', '');
        
        $this->assertNotNull($result, 'Should match by name when neither has email');
        $this->assertEquals(1, $result['InternalCode']);
    }
}
