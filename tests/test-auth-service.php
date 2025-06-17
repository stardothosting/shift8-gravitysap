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
    die("Error: Missing required SAP settings: " . esc_html(implode(', ', $missing_settings)) . "\n" .
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
    echo "Success: " . esc_html($result['message']) . "\n";
} else {
    echo "Error: " . esc_html($result['message']) . "\n";
}

// Note: Namespaced AuthService class was removed - authentication is handled within SAP service

// Test SAP service
try {
    echo "\nTesting SAP service...\n";
    
    $sap_service = new Shift8_GravitySAP_SAP_Service($settings);
    $result = $sap_service->test_connection();
    
    echo "Connection test result: " . esc_html(wp_json_encode($result)) . "\n";
    
} catch (\Exception $e) {
    echo "SAP service test failed: " . esc_html($e->getMessage()) . "\n";
} 