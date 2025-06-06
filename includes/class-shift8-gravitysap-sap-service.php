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
     * Session timeout
     */
    private $session_timeout;

    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->endpoint = rtrim($settings['sap_endpoint'], '/');
        $this->company_db = $settings['sap_company_db'];
        $this->username = $settings['sap_username'];
        $this->password = $settings['sap_password'];
        $this->session_timeout = 30 * 60; // 30 minutes
    }

    /**
     * Authenticate with SAP Service Layer
     */
    public function authenticate() {
        $login_data = array(
            'CompanyDB' => $this->company_db,
            'UserName' => $this->username,
            'Password' => $this->password
        );

        $response = $this->make_request('POST', '/Login', $login_data, false);

        if (is_wp_error($response)) {
            throw new Exception('SAP Authentication failed: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['SessionId'])) {
            throw new Exception('SAP Authentication failed: No session ID received');
        }

        $this->session_id = $data['SessionId'];
        $this->store_session();

        return true;
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
            
            Shift8_GravitySAP_Logger::log_info(
                sprintf('Business Partner created successfully. CardCode: %s, CardName: %s', 
                    $result['CardCode'], $result['CardName'])
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
    private function make_request($method, $endpoint, $data = null, $require_auth = true) {
        $url = $this->endpoint . $endpoint;
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );

        // Add session cookie for authenticated requests
        if ($require_auth && $this->session_id) {
            $headers['Cookie'] = 'B1SESSION=' . $this->session_id;
        }

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => false // Note: In production, you should verify SSL certificates
        );

        if ($data) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        // Log request for debugging
        Shift8_GravitySAP_Logger::log_debug(
            sprintf('SAP Request: %s %s - Response Code: %s', 
                $method, $endpoint, wp_remote_retrieve_response_code($response))
        );

        return $response;
    }

    /**
     * Ensure we have a valid authenticated session
     */
    private function ensure_authenticated() {
        // Check if we have a stored session that's still valid
        if ($this->restore_session()) {
            return true;
        }

        // Otherwise, authenticate
        try {
            return $this->authenticate();
        } catch (Exception $e) {
            Shift8_GravitySAP_Logger::log_error('SAP Authentication failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Store session data in WordPress transient
     */
    private function store_session() {
        $session_data = array(
            'session_id' => $this->session_id,
            'expires' => time() + $this->session_timeout
        );

        set_transient('shift8_gravitysap_session', $session_data, $this->session_timeout);
    }

    /**
     * Restore session from WordPress transient
     */
    private function restore_session() {
        $session_data = get_transient('shift8_gravitysap_session');

        if ($session_data && $session_data['expires'] > time()) {
            $this->session_id = $session_data['session_id'];
            return true;
        }

        return false;
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
            $this->authenticate();
            
            // Try to get Company Info to verify connection
            $response = $this->make_request('GET', '/CompanyService_GetCompanyInfo');
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                return array(
                    'success' => true,
                    'message' => 'Successfully connected to SAP Business One'
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Failed to retrieve company information from SAP'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Logout from SAP Service Layer
     */
    public function logout() {
        if ($this->session_id) {
            $this->make_request('POST', '/Logout');
            $this->session_id = null;
            delete_transient('shift8_gravitysap_session');
        }
    }

    /**
     * Destructor - ensure logout
     */
    public function __destruct() {
        $this->logout();
    }
} 