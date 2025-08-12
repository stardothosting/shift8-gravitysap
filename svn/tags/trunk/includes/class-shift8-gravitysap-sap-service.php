<?php
/**
 * SAP Business One Service Layer Integration
 *
 * @package Shift8\GravitySAP
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAP Service Layer integration class
 */
class Shift8_GravitySAP_SAP_Service {

    /**
     * SAP Service Layer endpoint
     */
    private $endpoint;

    /**
     * SAP Company Database
     */
    private $company_db;

    /**
     * SAP Username
     */
    private $username;

    /**
     * SAP Password
     */
    private $password;

    /**
     * Session ID for authenticated requests
     */
    private $session_id;

    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->endpoint = rtrim($settings['sap_endpoint'], '/');
        $this->company_db = $settings['sap_company_db'];
        $this->username = $settings['sap_username'];
        
        // Handle both encrypted and decrypted passwords
        // If password looks encrypted (base64), decrypt it; otherwise use as-is
        $password = $settings['sap_password'];
        if (!empty($password) && base64_encode(base64_decode($password, true)) === $password) {
            // Looks like base64 - try to decrypt
            $this->password = shift8_gravitysap_decrypt_password($password);
        } else {
            // Use as-is (already decrypted by caller)
            $this->password = $password;
        }
    }

    /**
     * Test reading existing Business Partners to verify API connectivity
     */
    public function test_read_business_partners() {
        try {
            // Ensure we have a valid session
            if (!$this->ensure_authenticated()) {
                throw new Exception('Failed to authenticate with SAP');
            }
            
            // Try to read Business Partners with simpler query first
            $response = $this->make_request('GET', '/BusinessPartners');
            
            if (is_wp_error($response)) {
                throw new Exception('Failed to read Business Partners: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                shift8_gravitysap_debug_log('Successfully read Business Partners', array(
                    'count' => count($data['value'] ?? []),
                    'first_bp' => $data['value'][0] ?? 'none'
                ));
                
                return array(
                    'success' => true,
                    'message' => 'Successfully read ' . count($data['value'] ?? []) . ' Business Partners',
                    'data' => $data['value'] ?? []
                );
            } else {
                // Log the full error response for debugging
                $body = wp_remote_retrieve_body($response);
                shift8_gravitysap_debug_log('Business Partner read failed', array(
                    'status' => $response_code,
                    'body' => $body
                ));
                throw new Exception('Read failed with HTTP ' . $response_code . ' - ' . $body);
            }
            
        } catch (Exception $e) {
            shift8_gravitysap_debug_log('Read Business Partners failed: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Create Business Partner in SAP
     */
    public function create_business_partner($business_partner_data) {
        // First, test if we can read existing Business Partners
        shift8_gravitysap_debug_log('=== STARTING BUSINESS PARTNER CREATION ===');
        
        $read_test = $this->test_read_business_partners();
        if (!$read_test['success']) {
            throw new Exception('Cannot read Business Partners - API issue: ' . esc_html($read_test['message']));
        }
        
        shift8_gravitysap_debug_log('✅ Business Partner read test passed - API is working');
        
        // Ensure we have a valid session
        if (!$this->ensure_authenticated()) {
            throw new Exception('Failed to authenticate with SAP');
        }

        // Validate required fields
        if (empty($business_partner_data['CardName'])) {
            throw new Exception('CardName is required for Business Partner creation');
        }

        if (empty($business_partner_data['CardType'])) {
            throw new Exception('CardType is required for Business Partner creation');
        }

        // Check available numbering series before attempting creation
        $available_series = $this->get_available_numbering_series();
        shift8_gravitysap_debug_log('Available numbering series', array('series' => $available_series));
        
        // Ensure CardType is set correctly
        if (!isset($business_partner_data['CardType'])) {
            $business_partner_data['CardType'] = 'cCustomer';
        }
        
        // Remove any Series or CardCode to let SAP auto-generate
        unset($business_partner_data['Series']);
        unset($business_partner_data['CardCode']);
        
        // If we have available series, use the first one
        if (!empty($available_series)) {
            $business_partner_data['Series'] = $available_series[0];
            shift8_gravitysap_debug_log('Using numbering series', array('series' => $available_series[0]));
        }
        
        shift8_gravitysap_debug_log('Creating Business Partner with full data', $business_partner_data);

        $response = $this->make_request('POST', '/BusinessPartners', $business_partner_data);

        if (is_wp_error($response)) {
            throw new Exception('Failed to create Business Partner: ' . esc_html($response->get_error_message()));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 201) {
            // Successfully created
            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);
            
            shift8_gravitysap_debug_log(
                'Business Partner created successfully',
                array(
                    'CardCode' => $result['CardCode'],
                    'CardName' => $result['CardName']
                )
            );
            
            return $result;
        } else {
            // Handle error response
            $body = wp_remote_retrieve_body($response);
            $error_data = json_decode($body, true);
            
            shift8_gravitysap_debug_log('Business Partner creation failed', array(
                'status_code' => $response_code,
                'response_body' => $body,
                'available_series' => $available_series
            ));
            
            $error_message = 'Unknown error';
            if (!empty($error_data['error']['message']['value'])) {
                $error_message = $error_data['error']['message']['value'];
            }
            
            // Provide enhanced context for numbering series error
            if (strpos($error_message, 'numbering series') !== false) {
                $help_message = "\n\nSAP CONFIGURATION REQUIRED:\n";
                $help_message .= "1. Log into SAP Business One as Administrator\n";
                $help_message .= "2. Go to Administration > System Initialization > Document Numbering\n";
                $help_message .= "3. Find 'Business Partners' in the list\n";
                $help_message .= "4. Create a new numbering series (e.g., BP######## where # represents numbers)\n";
                $help_message .= "5. Set the series as Primary or Default\n";
                $help_message .= "6. Save the configuration\n\n";
                
                if (empty($available_series)) {
                    $help_message .= "STATUS: No numbering series found for Business Partners.\n";
                } else {
                    $help_message .= "STATUS: Found series (" . implode(', ', $available_series) . ") but creation still failed.\n";
                }
                
                $error_message .= $help_message;
            }
            
            throw new Exception('SAP Business Partner creation failed: ' . esc_html($error_message));
        }
    }

    /**
     * Make HTTP request to SAP Service Layer
     */
    private function make_request($method, $endpoint, $data = null) {
        $url = $this->endpoint . $endpoint;
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );

        // Add session cookie for authenticated requests
        if ($this->session_id) {
            $headers['Cookie'] = 'B1SESSION=' . $this->session_id;
        }

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => false // Skip SSL verification for testing
        );

        // Only add body if we have data
        if ($data !== null) {
            $args['body'] = json_encode($data);
        }

        // Log the request details
        shift8_gravitysap_debug_log(
            'SAP Request Details',
            array(
                'URL' => $url,
                'Method' => $method,
                'Headers' => $headers,
                'Body' => $data
            )
        );

        // Make the request
        $response = wp_remote_request($url, $args);

        // Log the response details
        if (is_wp_error($response)) {
            shift8_gravitysap_debug_log(
                'SAP Request Failed',
                array('error' => $response->get_error_message())
            );
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_headers = wp_remote_retrieve_headers($response);

            shift8_gravitysap_debug_log(
                'SAP Response Details',
                array(
                    'Status' => $response_code,
                    'Headers' => $response_headers,
                    'Body' => $response_body
                )
            );
        }

        return $response;
    }

    /**
     * Debug numbering series configuration
     */
    private function debug_numbering_series() {
        try {
            shift8_gravitysap_debug_log('=== DEBUGGING NUMBERING SERIES ===');
            
            // Try to get series information
            $series_response = $this->make_request('GET', '/SeriesService_GetDocumentSeries');
            if (!is_wp_error($series_response)) {
                $series_code = wp_remote_retrieve_response_code($series_response);
                $series_body = wp_remote_retrieve_body($series_response);
                shift8_gravitysap_debug_log('All Series Response', array(
                    'code' => $series_code,
                    'body' => substr($series_body, 0, 500) // Truncate for log
                ));
            }
            
            // Also try to get Business Partner specific series
            $bp_series_response = $this->make_request('GET', "/SeriesService_GetDocumentSeries?\$filter=ObjectCode eq '2'");
            if (!is_wp_error($bp_series_response)) {
                $bp_code = wp_remote_retrieve_response_code($bp_series_response);
                $bp_body = wp_remote_retrieve_body($bp_series_response);
                shift8_gravitysap_debug_log('BP Series Response', array(
                    'code' => $bp_code,
                    'body' => $bp_body
                ));
            }
            
        } catch (Exception $e) {
            shift8_gravitysap_debug_log('Debug numbering series failed: ' . $e->getMessage());
        }
    }

    /**
     * Get available numbering series for Business Partners
     */
    private function get_available_numbering_series() {
        try {
            // Ensure we have a valid session
            if (!$this->ensure_authenticated()) {
                return array();
            }

            $series = array();
            
            // Method 1: Extract series from existing Business Partners (most reliable)
            $response = $this->make_request('GET', "/BusinessPartners?\$select=Series&\$top=10");
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                shift8_gravitysap_debug_log('Method 1 - Extract series from existing BPs', array(
                    'bp_count' => count($data['value'] ?? []),
                    'response_sample' => array_slice($data['value'] ?? [], 0, 3)
                ));
                
                if (!empty($data['value'])) {
                    foreach ($data['value'] as $bp) {
                        if (!empty($bp['Series']) && !in_array($bp['Series'], $series)) {
                            $series[] = $bp['Series'];
                            shift8_gravitysap_debug_log('Found series from BP', array('series' => $bp['Series']));
                        }
                    }
                }
            }
            
            // Method 2: Try SeriesService (for comparison, but we know it fails)
            $series_response = $this->make_request('GET', "/SeriesService_GetDocumentSeries?\$filter=ObjectCode eq '2'");
            shift8_gravitysap_debug_log('Method 2 - SeriesService response', array(
                'is_error' => is_wp_error($series_response),
                'status' => is_wp_error($series_response) ? null : wp_remote_retrieve_response_code($series_response),
                'note' => 'This API endpoint appears to not work for Business Partners'
            ));
            
            // If we found series from existing BPs, use those
            if (!empty($series)) {
                shift8_gravitysap_debug_log('Successfully found series from existing Business Partners: ' . implode(', ', $series));
                return $series;
            }
            
            // Method 3: If no existing BPs, try some common series numbers
            shift8_gravitysap_debug_log('No existing BPs found, will try creation without specifying series');
            return array(); // Let SAP auto-assign
            
        } catch (Exception $e) {
            shift8_gravitysap_debug_log('Exception getting numbering series: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Generate a unique CardCode based on CardName
     */
    private function generate_card_code($card_name) {
        // Clean the name and create a base code
        $base_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $card_name), 0, 8));
        
        // Add timestamp to ensure uniqueness
        $timestamp = substr(time(), -4);
        
        return $base_code . $timestamp;
    }

    /**
     * Test SAP connection
     */
    public function test_connection() {
        try {
            // Try to authenticate
            $this->authenticate();
            
            // If we get here, authentication was successful
            return array(
                'success' => true,
                'message' => 'Successfully connected to SAP Service Layer'
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Test Business Partner numbering series configuration
     */
    public function test_numbering_series() {
        try {
            // Ensure we have a valid session
            if (!$this->ensure_authenticated()) {
                throw new Exception('Failed to authenticate with SAP');
            }

            $available_series = $this->get_available_numbering_series();
            
            $result = array(
                'success' => !empty($available_series),
                'series_count' => count($available_series),
                'available_series' => $available_series
            );
            
            if (empty($available_series)) {
                $result['message'] = 'No numbering series configured for Business Partners. Please configure in SAP B1 Administration > System Initialization > Document Numbering.';
                $result['recommendations'] = array(
                    'Contact your SAP Administrator to configure Business Partner numbering series',
                    'In SAP B1: Administration > System Initialization > Document Numbering',
                    'Find "Business Partners" and create a new series (e.g., BP########)',
                    'Set the series as Primary or Default and save'
                );
            } else {
                $result['message'] = 'Found ' . count($available_series) . ' numbering series for Business Partners: ' . implode(', ', $available_series);
            }
            
            shift8_gravitysap_debug_log('Numbering series test result', $result);
            return $result;
            
        } catch (Exception $e) {
            shift8_gravitysap_debug_log('Numbering series test failed: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Failed to check numbering series: ' . $e->getMessage(),
                'series_count' => 0,
                'available_series' => array()
            );
        }
    }

    /**
     * Decrypt password using the global function (removed - use global function instead)
     */

    /**
     * Authenticate with SAP Service Layer
     */
    private function authenticate() {
        // Use the password as-is (it should already be decrypted by the caller)
        $password_to_use = $this->password;
        shift8_gravitysap_debug_log('Using password as provided by caller (admin class handles decryption)');

        // Ensure all values are valid UTF-8 for JSON encoding
        $company_db_clean = mb_convert_encoding($this->company_db, 'UTF-8', 'UTF-8');
        $username_clean = mb_convert_encoding($this->username, 'UTF-8', 'UTF-8');
        $password_clean = mb_convert_encoding($password_to_use, 'UTF-8', 'UTF-8');

        // Format exactly like the working curl command
        $login_data = array(
            'CompanyDB' => $company_db_clean,
            'UserName' => $username_clean,
            'Password' => $password_clean
        );

        // Debug the login data before JSON encoding (sanitized)
        shift8_gravitysap_debug_log(
            sprintf('SAP Login Data Debug:\nCompanyDB: "%s" (type: %s)\nUserName: "%s" (type: %s)\nPassword: "***REDACTED***" (type: %s)\nPassword length: %d',
                $this->company_db, gettype($this->company_db),
                substr($this->username, 0, 2) . '***', gettype($this->username),
                gettype($password_to_use),
                strlen($password_to_use)
            )
        );

        // Log the request (without the password)
        shift8_gravitysap_debug_log(
            sprintf('SAP Login Request: %s/Login - CompanyDB: %s, UserName: %s', 
                $this->endpoint, $this->company_db, $this->username)
        );

        // Make the request with exact same format as curl
        $url = $this->endpoint . '/Login';
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30,
            'sslverify' => false
        );

        // Only add body if we have data (same pattern as make_request method)
        if ($login_data !== null && !empty($login_data)) {
            $json_body = json_encode($login_data);
            if ($json_body !== false) {
                $args['body'] = $json_body;
                shift8_gravitysap_debug_log('SAP Login: Successfully encoded body: ' . $json_body);
            } else {
                $json_error = json_last_error_msg();
                shift8_gravitysap_debug_log('SAP Login: Failed to encode login data as JSON. Error: ' . $json_error);
                shift8_gravitysap_debug_log('SAP Login: Raw login data: ' . wp_json_encode($login_data));
                throw new Exception('Failed to encode login data as JSON: ' . esc_html($json_error));
            }
        } else {
            shift8_gravitysap_debug_log('SAP Login: No login data provided');
        }

        // Log the exact request being sent
        shift8_gravitysap_debug_log(
            sprintf('SAP Login Request Details:\nURL: %s\nHeaders: %s\nArgs: %s',
                $url,
                json_encode($args['headers']),
                json_encode($args)
            )
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            shift8_gravitysap_debug_log('SAP Authentication failed: ' . $error_message);
            throw new Exception('SAP Authentication failed: ' . esc_html($error_message));
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log the response code and body
        shift8_gravitysap_debug_log(
            sprintf('SAP Login Response Code: %d, Body: %s', $response_code, $body)
        );
        
        if ($response_code !== 200) {
            $error_data = json_decode($body, true);
            
            $error_message = 'Unknown error';
            if (!empty($error_data['error']['message']['value'])) {
                $error_message = $error_data['error']['message']['value'];
            }
            
            shift8_gravitysap_debug_log('SAP Authentication failed: ' . $error_message);
            throw new Exception('SAP Authentication failed: ' . esc_html($error_message));
        }

        $data = json_decode($body, true);

        if (empty($data['SessionId'])) {
            shift8_gravitysap_debug_log('SAP Authentication failed: No session ID received');
            throw new Exception('SAP Authentication failed: No session ID received');
        }

        $this->session_id = $data['SessionId'];
        shift8_gravitysap_debug_log('SAP Authentication successful. SessionId: ' . $this->session_id);
        
        return $this->session_id;
    }

    /**
     * Ensure we have a valid authenticated session
     */
    private function ensure_authenticated() {
        if (!$this->session_id) {
            return $this->authenticate();
        }
        return true;
    }

    /**
     * Logout from SAP Service Layer
     */
    public function logout() {
        if ($this->session_id) {
            $this->make_request('POST', '/Logout', null);
            $this->session_id = null;
            shift8_gravitysap_debug_log('SAP Logout successful');
        }
    }

    /**
     * Destructor - ensure we logout when the object is destroyed
     */
    public function __destruct() {
        // Skip logout during testing to prevent destructor issues
        if (defined('SHIFT8_GRAVITYSAP_TESTING') && SHIFT8_GRAVITYSAP_TESTING) {
            return;
        }
        $this->logout();
    }

    /**
     * Generate cURL command for manual testing
     */
    public static function generate_curl_test_command($settings) {
        $endpoint = rtrim($settings['sap_endpoint'], '/');
        $login_data = array(
            'CompanyDB' => $settings['sap_company_db'],
            'UserName' => $settings['sap_username'],
            'Password' => $settings['sap_password']
        );

        $curl_command = sprintf(
            'curl -X POST "%s/Login" \\' . "\n" .
            '  -H "Content-Type: application/json" \\' . "\n" .
            '  -H "Accept: application/json" \\' . "\n" .
            '  -d \'%s\' \\' . "\n" .
            '  -k -v',
            $endpoint,
            json_encode($login_data, JSON_PRETTY_PRINT)
        );

        return $curl_command;
    }

    /**
     * Generate WordPress HTTP API test code for manual testing
     */
    public static function generate_wp_http_test_code($settings) {
        $endpoint = rtrim($settings['sap_endpoint'], '/');
        $login_data = array(
            'CompanyDB' => $settings['sap_company_db'],
            'UserName' => $settings['sap_username'],
            'Password' => $settings['sap_password']
        );

        $test_code = sprintf(
            '<?php' . "\n" .
            '// WordPress HTTP API Test Code' . "\n" .
            '$url = "%s/Login";' . "\n" .
            '$args = array(' . "\n" .
            '    "timeout" => 30,' . "\n" .
            '    "method" => "POST",' . "\n" .
            '    "body" => \'%s\',' . "\n" .
            '    "headers" => array(' . "\n" .
            '        "Content-Type" => "application/json",' . "\n" .
            '        "Accept" => "application/json"' . "\n" .
            '    ),' . "\n" .
            '    "sslverify" => false' . "\n" .
            ');' . "\n" .
            '$response = wp_remote_request($url, $args);' . "\n" .
            'if (is_wp_error($response)) {' . "\n" .
            '    echo "Error: " . $response->get_error_message();' . "\n" .
            '} else {' . "\n" .
            '    echo "Response Code: " . wp_remote_retrieve_response_code($response) . "\n";' . "\n" .
            '    echo "Response Body: " . wp_remote_retrieve_body($response) . "\n";' . "\n" .
            '}' . "\n" .
            '?>',
            $endpoint,
            json_encode($login_data)
        );

        return $test_code;
    }

    /**
     * Test connection using WordPress HTTP API (enhanced debugging)
     */
    public function test_connection_http_api() {
        try {
            $url = $this->endpoint . '/Login';
            
            $login_data = array(
                'CompanyDB' => $this->company_db,
                'UserName' => $this->username,
                'Password' => $this->password
            );

            $args = array(
                'timeout' => 30,
                'method' => 'POST',
                'body' => json_encode($login_data),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ),
                'sslverify' => false, // Equivalent to CURLOPT_SSL_VERIFYPEER => false
                'sslcertificates' => '', // Equivalent to CURLOPT_SSL_VERIFYHOST => false
            );

            shift8_gravitysap_debug_log('SAP HTTP Request URL: ' . $url);
            shift8_gravitysap_debug_log('SAP HTTP Request Args: ' . wp_json_encode($args));

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                throw new Exception('WordPress HTTP API Error: ' . esc_html($error_message));
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_headers = wp_remote_retrieve_headers($response);

            // Log the request and response details
            shift8_gravitysap_debug_log('SAP HTTP Response Code: ' . $http_code);
            shift8_gravitysap_debug_log('SAP HTTP Response Headers: ' . wp_json_encode($response_headers));
            shift8_gravitysap_debug_log('SAP HTTP Response Body: ' . $response_body);

            if ($http_code === 200) {
                $data = json_decode($response_body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON response: ' . json_last_error_msg());
                }
                return array(
                    'success' => true,
                    'message' => 'Successfully connected to SAP Business One Service Layer',
                    'details' => array(
                        'session_id' => isset($data['SessionId']) ? $data['SessionId'] : null,
                        'version' => isset($data['Version']) ? $data['Version'] : null,
                        'session_timeout' => isset($data['SessionTimeout']) ? $data['SessionTimeout'] : null
                    )
                );
            }

            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']['value']) 
                ? $error_data['error']['message']['value'] 
                : 'HTTP ' . $http_code . ' - ' . $response_body;

            throw new Exception('Connection failed: ' . $error_message);

        } catch (Exception $e) {
            shift8_gravitysap_debug_log('SAP Connection Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'details' => array(
                    'request_url' => isset($url) ? $url : null,
                    'request_data' => isset($login_data) ? $login_data : null,
                    'response' => isset($response_body) ? $response_body : null,
                    'http_code' => isset($http_code) ? $http_code : null
                )
            );
        }
    }
} 