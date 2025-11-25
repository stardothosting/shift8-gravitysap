<?php
/**
 * Test script to discover SAP B1 Items API structure
 * Run this directly in browser or via CLI to see what data SAP returns
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// Get SAP settings
$sap_settings = get_option('shift8_gravitysap_settings', array());

if (empty($sap_settings['sap_endpoint']) || empty($sap_settings['sap_company_db'])) {
    die('ERROR: SAP settings not configured. Please configure SAP connection first.');
}

// Decrypt password
require_once(__DIR__ . '/shift8-gravitysap.php');
$sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);

echo "<h1>SAP B1 Items API Discovery</h1>";
echo "<pre>";
echo "SAP Endpoint: " . esc_html($sap_settings['sap_endpoint']) . "\n";
echo "Company DB: " . esc_html($sap_settings['sap_company_db']) . "\n";
echo "Username: " . esc_html($sap_settings['sap_username']) . "\n\n";

// Create SAP service
require_once(__DIR__ . '/includes/class-shift8-gravitysap-sap-service.php');
$sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);

// Use reflection to access private methods
$reflection = new ReflectionClass($sap_service);

// Authenticate
echo "=== STEP 1: Authenticating ===\n";
$auth_method = $reflection->getMethod('ensure_authenticated');
$auth_method->setAccessible(true);
$authenticated = $auth_method->invoke($sap_service);

if (!$authenticated) {
    die("ERROR: Authentication failed!\n");
}
echo "✓ Authentication successful\n\n";

// Get make_request method
$request_method = $reflection->getMethod('make_request');
$request_method->setAccessible(true);

// Test 1: Get first item without filters
echo "=== TEST 1: Get first item (no filters) ===\n";
$response = $request_method->invoke($sap_service, 'GET', '/Items?$top=1');
if (!is_wp_error($response)) {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    echo "Response Code: " . wp_remote_retrieve_response_code($response) . "\n";
    echo "First Item Structure:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "ERROR: " . $response->get_error_message() . "\n\n";
}

// Test 2: Get items with specific fields
echo "=== TEST 2: Get 5 items with ItemCode and ItemName ===\n";
$response = $request_method->invoke($sap_service, 'GET', '/Items?$top=5&$select=ItemCode,ItemName');
if (!is_wp_error($response)) {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    echo "Response Code: " . wp_remote_retrieve_response_code($response) . "\n";
    echo "Items:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "ERROR: " . $response->get_error_message() . "\n\n";
}

// Test 3: Get ALL items count
echo "=== TEST 3: Get total item count ===\n";
$response = $request_method->invoke($sap_service, 'GET', '/Items?$select=ItemCode&$inlinecount=allpages&$top=1');
if (!is_wp_error($response)) {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    echo "Response Code: " . wp_remote_retrieve_response_code($response) . "\n";
    if (isset($data['odata.count'])) {
        echo "Total Items in SAP: " . $data['odata.count'] . "\n\n";
    } else {
        echo "Count data:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    }
} else {
    echo "ERROR: " . $response->get_error_message() . "\n\n";
}

// Test 4: Get items with Valid filter
echo "=== TEST 4: Get 5 VALID items only ===\n";
$response = $request_method->invoke($sap_service, 'GET', '/Items?$top=5&$select=ItemCode,ItemName,Valid&$filter=Valid eq \'Y\'');
if (!is_wp_error($response)) {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    echo "Response Code: " . wp_remote_retrieve_response_code($response) . "\n";
    echo "Valid Items:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "ERROR: " . $response->get_error_message() . "\n\n";
}

// Test 5: Search for specific item "CS-Designer Suede Full"
echo "=== TEST 5: Search for 'CS-Designer Suede Full' ===\n";
$response = $request_method->invoke($sap_service, 'GET', '/Items?$filter=contains(ItemName, \'Designer\')&$select=ItemCode,ItemName');
if (!is_wp_error($response)) {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    echo "Response Code: " . wp_remote_retrieve_response_code($response) . "\n";
    echo "Matching Items:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "ERROR: " . $response->get_error_message() . "\n\n";
}

// Test 6: Get ALL items (up to 500)
echo "=== TEST 6: Get ALL items (up to 500) ===\n";
$response = $request_method->invoke($sap_service, 'GET', '/Items?$top=500&$select=ItemCode,ItemName&$orderby=ItemCode');
if (!is_wp_error($response)) {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    echo "Response Code: " . wp_remote_retrieve_response_code($response) . "\n";
    if (isset($data['value'])) {
        echo "Total Items Retrieved: " . count($data['value']) . "\n";
        echo "First 10 Items:\n";
        foreach (array_slice($data['value'], 0, 10) as $item) {
            echo "  - {$item['ItemCode']}: {$item['ItemName']}\n";
        }
        echo "\nLast 10 Items:\n";
        foreach (array_slice($data['value'], -10) as $item) {
            echo "  - {$item['ItemCode']}: {$item['ItemName']}\n";
        }
        
        // Search for CS-Designer in the results
        echo "\n=== Searching for 'CS-Designer' or 'Suede' in results ===\n";
        $found = false;
        foreach ($data['value'] as $item) {
            if (stripos($item['ItemName'], 'Designer') !== false || stripos($item['ItemName'], 'Suede') !== false) {
                echo "  FOUND: {$item['ItemCode']}: {$item['ItemName']}\n";
                $found = true;
            }
        }
        if (!$found) {
            echo "  NOT FOUND in retrieved items\n";
        }
    } else {
        echo "Unexpected response structure:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "ERROR: " . $response->get_error_message() . "\n\n";
}

echo "</pre>";
echo "<p><strong>Test Complete!</strong></p>";

