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

// Import WordPress functions
use function wp_remote_request;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function is_wp_error;

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
        $this->password = $settings['sap_password'];
    }

    /**
     * Create Business Partner in SAP
     */
    public function create_business_partner($business_partner_data) {
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

        // Generate unique CardCode if not provided
        if (empty($business_partner_data['CardCode'])) {
            $business_partner_data['CardCode'] = $this->generate_card_code($business_partner_data['CardName']);
        }

        $response = $this->make_request('POST', '/BusinessPartners', $business_partner_data);

        if (is_wp_error($response)) {
            throw new Exception('Failed to create Business Partner: ' . $response->get_error_message());
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
            
            $error_message = 'Unknown error';
            if (!empty($error_data['error']['message']['value'])) {
                $error_message = $error_data['error']['message']['value'];
            }
            
            throw new Exception('SAP Business Partner creation failed: ' . $error_message);
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
     * Authenticate with SAP Service Layer
     */
    private function authenticate() {
        // Use the password as-is (should be plaintext for SAP API)
        // The password might be base64 encoded for storage, but SAP expects plaintext
        $password_to_use = $this->password;
        
        // If the password looks like base64, try to decode it
        if (base64_encode(base64_decode($this->password, true)) === $this->password) {
            $decoded = base64_decode($this->password, true);
            if ($decoded !== false) {
                // Ensure the decoded password is valid UTF-8
                if (mb_check_encoding($decoded, 'UTF-8')) {
                    $password_to_use = $decoded;
                    shift8_gravitysap_debug_log('Using decoded password for SAP authentication');
                } else {
                    // If decoded password is not valid UTF-8, use original (it might already be plaintext)
                    shift8_gravitysap_debug_log('Decoded password is not valid UTF-8, using original password');
                }
            }
        }

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

        // Debug the login data before JSON encoding
        shift8_gravitysap_debug_log(
            sprintf('SAP Login Data Debug:\nCompanyDB: "%s" (type: %s)\nUserName: "%s" (type: %s)\nPassword: "%s" (type: %s)\nPassword length: %d',
                $this->company_db, gettype($this->company_db),
                $this->username, gettype($this->username),
                substr($password_to_use, 0, 3) . '***', gettype($password_to_use),
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
                shift8_gravitysap_debug_log('SAP Login: Raw login data: ' . print_r($login_data, true));
                throw new Exception('Failed to encode login data as JSON: ' . $json_error);
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
            throw new Exception('SAP Authentication failed: ' . $error_message);
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
            throw new Exception('SAP Authentication failed: ' . $error_message);
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
     * Test connection using cURL
     */
    public function test_connection_curl() {
        try {
            if (!function_exists('curl_init')) {
                throw new Exception('cURL is not installed on this server');
            }

            $url = $this->endpoint . '/Login';
            
            $login_data = array(
                'CompanyDB' => $this->company_db,
                'UserName' => $this->username,
                'Password' => $this->password
            );

            $ch = curl_init();
            if ($ch === false) {
                throw new Exception('Failed to initialize cURL');
            }

            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($login_data),
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Accept: application/json'
                ),
                CURLOPT_VERBOSE => true
            ));

            // Create a temporary file handle for CURL debug output
            $verbose = fopen('php://temp', 'w+');
            if ($verbose === false) {
                throw new Exception('Failed to create temporary file for cURL verbose output');
            }
            curl_setopt($ch, CURLOPT_STDERR, $verbose);

            $response = curl_exec($ch);
            if ($response === false) {
                $curl_error = curl_error($ch);
                $curl_errno = curl_errno($ch);
                throw new Exception('cURL Error: ' . $curl_error . ' (Error #' . $curl_errno . ')');
            }

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Get verbose debug information
            rewind($verbose);
            $verbose_log = stream_get_contents($verbose);
            
            curl_close($ch);
            fclose($verbose);

            // Log the request and response
            shift8_gravitysap_debug_log("SAP cURL Request:\n" . $verbose_log);
            shift8_gravitysap_debug_log("SAP cURL Response:\n" . $response);

            if ($http_code === 200) {
                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON response: ' . json_last_error_msg());
                }
                return array(
                    'success' => true,
                    'message' => 'Successfully connected to SAP Business One Service Layer',
                    'details' => array(
                        'session_id' => $data['SessionId'],
                        'version' => $data['Version'],
                        'session_timeout' => $data['SessionTimeout']
                    )
                );
            }

            $error_data = json_decode($response, true);
            $error_message = isset($error_data['error']['message']['value']) 
                ? $error_data['error']['message']['value'] 
                : 'HTTP ' . $http_code . ' - ' . $response;

            throw new Exception('Connection failed: ' . $error_message);

        } catch (Exception $e) {
            shift8_gravitysap_debug_log('SAP Connection Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'details' => array(
                    'request_url' => isset($url) ? $url : null,
                    'request_data' => isset($login_data) ? $login_data : null,
                    'verbose_log' => isset($verbose_log) ? $verbose_log : null,
                    'response' => isset($response) ? $response : null,
                    'http_code' => isset($http_code) ? $http_code : null
                )
            );
        }
    }
} 