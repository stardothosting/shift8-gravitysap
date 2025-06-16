<?php
/**
 * Test file for Shift8 GravitySAP AuthService
 *
 * @package Shift8\GravitySAP\Tests
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load WordPress test environment
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

// Load our plugin
require_once dirname(dirname(__FILE__)) . '/shift8-gravitysap.php';

// Get settings from WordPress options
$settings = get_option('shift8_gravitysap_settings', array());

// Validate required settings
$required_settings = array('sap_endpoint', 'sap_company_db', 'sap_username', 'sap_password');
$missing_settings = array();

foreach ($required_settings as $setting) {
    if (empty($settings[$setting])) {
        $missing_settings[] = $setting;
    }
}

if (!empty($missing_settings)) {
    die("Error: Missing required SAP settings: " . implode(', ', $missing_settings) . "\n" .
        "Please configure these settings in the WordPress admin under Shift8 > SAP Integration.\n");
}

// Enable logging for testing
$settings['enable_logging'] = true;

// Initialize logger
require_once dirname(dirname(__FILE__)) . '/includes/class-shift8-gravitysap-logger.php';

// Initialize SAP service
require_once dirname(dirname(__FILE__)) . '/includes/class-shift8-gravitysap-sap-service.php';
$sap_service = new Shift8_GravitySAP_SAP_Service($settings);

// Test connection
$result = $sap_service->test_connection();

if ($result['success']) {
    echo "Success: " . $result['message'] . "\n";
} else {
    echo "Error: " . $result['message'] . "\n";
}

// Test authentication
try {
    echo "Testing SAP authentication...\n";
    
    $auth_service = new \Shift8\GravitySAP\Services\AuthService($settings);
    $session_id = $auth_service->authenticate();
    
    echo "Authentication successful!\n";
    echo "Session ID: " . $session_id . "\n";
    
    // Test session headers
    $headers = $auth_service->get_session_headers();
    echo "Session headers: " . print_r($headers, true) . "\n";
    
    // Test logout
    $auth_service->logout();
    echo "Logout successful!\n";
    
} catch (\Exception $e) {
    echo "Authentication failed: " . $e->getMessage() . "\n";
}

// Test SAP service
try {
    echo "\nTesting SAP service...\n";
    
    $sap_service = new Shift8_GravitySAP_SAP_Service($settings);
    $result = $sap_service->test_connection();
    
    echo "Connection test result: " . print_r($result, true) . "\n";
    
} catch (\Exception $e) {
    echo "SAP service test failed: " . $e->getMessage() . "\n";
} 