<?php
/**
 * WP-CLI command to test SAP B1 form submission end-to-end
 * 
 * Usage: wp shift8-gravitysap test-submission --form_id=3
 * 
 * This command simulates the entire form submission process:
 * 1. Loads form configuration and field mappings
 * 2. Generates sample data for all mapped fields
 * 3. Creates a temporary entry in Gravity Forms
 * 4. Runs the exact same submission process as a real form
 * 5. Reports detailed results including SAP response
 * 6. Cleans up the test entry
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Test SAP B1 form submission end-to-end
 */
class Shift8_GravitySAP_Test_Command {
    
    /**
     * Test form submission to SAP B1
     * 
     * ## OPTIONS
     * 
     * --form_id=<form_id>
     * : The Gravity Forms form ID to test
     * 
     * [--cleanup]
     * : Whether to delete the test entry after submission (default: true)
     * 
     * ## EXAMPLES
     * 
     *     wp shift8-gravitysap test-submission --form_id=3
     *     wp shift8-gravitysap test-submission --form_id=3 --cleanup=false
     * 
     * @param array $args
     * @param array $assoc_args
     */
    public function test_submission($args, $assoc_args) {
        $form_id = isset($assoc_args['form_id']) ? absint($assoc_args['form_id']) : 0;
        $cleanup = isset($assoc_args['cleanup']) ? $assoc_args['cleanup'] !== 'false' : true;
        
        if (!$form_id) {
            WP_CLI::error('Please specify a form ID: --form_id=3');
        }
        
        WP_CLI::line('');
        WP_CLI::line('═══════════════════════════════════════════════════════');
        WP_CLI::line('  SHIFT8 GRAVITYSAP - END-TO-END SUBMISSION TEST');
        WP_CLI::line('═══════════════════════════════════════════════════════');
        WP_CLI::line('');
        
        // Load form
        WP_CLI::line('📋 Loading form configuration...');
        $form = GFAPI::get_form($form_id);
        
        if (!$form) {
            WP_CLI::error("Form ID {$form_id} not found");
        }
        
        WP_CLI::success("Form loaded: {$form['title']} (ID: {$form_id})");
        
        // Check SAP integration settings
        $settings = isset($form['sap_integration_settings']) ? $form['sap_integration_settings'] : array();
        
        if (empty($settings) || empty($settings['enabled']) || $settings['enabled'] !== '1') {
            WP_CLI::error('SAP Integration is not enabled for this form');
        }
        
        WP_CLI::line('');
        WP_CLI::line('⚙️  SAP Integration Settings:');
        WP_CLI::line('   Enabled: Yes');
        WP_CLI::line('   Card Type: ' . ($settings['card_type'] ?? 'N/A'));
        WP_CLI::line('   Card Code Prefix: ' . ($settings['card_code_prefix'] ?? 'N/A'));
        WP_CLI::line('   Mapped Fields: ' . count($settings['field_mapping'] ?? array()));
        WP_CLI::line('   Create Quotation: ' . ((!empty($settings['create_quotation']) && $settings['create_quotation'] === '1') ? 'Yes' : 'No'));
        if (!empty($settings['create_quotation']) && $settings['create_quotation'] === '1') {
            $field_mappings = count($settings['quotation_field_mapping'] ?? array());
            $itemcode_mappings = count($settings['quotation_itemcode_mapping'] ?? array());
            WP_CLI::line('   Quotation Field Mappings: ' . $field_mappings);
            WP_CLI::line('   ItemCode Mappings: ' . $itemcode_mappings);
        }
        
        // Generate sample data
        WP_CLI::line('');
        WP_CLI::line('🔧 Generating sample data for mapped fields...');
        
        // Get real ItemCodes from SAP first if quotation is enabled
        $real_item_codes = array();
        if (!empty($settings['create_quotation']) && $settings['create_quotation'] === '1') {
            WP_CLI::line('   🔍 Querying SAP B1 for real ItemCodes...');
            $real_item_codes = $this->get_real_item_codes();
            if (!empty($real_item_codes)) {
                WP_CLI::line('   ✓ Found ' . count($real_item_codes) . ' ItemCodes: ' . implode(', ', array_slice($real_item_codes, 0, 5)) . (count($real_item_codes) > 5 ? '...' : ''));
            } else {
                WP_CLI::line('   ⚠️  Could not get real ItemCodes, using fallback values');
            }
        }
        
        $sample_data = $this->generate_sample_data($form, $settings, $real_item_codes);
        
        // Add first/last name fields if they exist (for theme function to combine)
        $first_name_field = GFAPI::get_field($form, 52);
        $last_name_field = GFAPI::get_field($form, 53);
        
        if ($first_name_field && $last_name_field) {
            $sample_data[52] = 'Test';
            $sample_data[53] = 'User ' . rand(1000, 9999);
            WP_CLI::line('   ℹ️  Added first/last name fields for theme function');
        }
        
        WP_CLI::line('');
        WP_CLI::line('📝 Sample Entry Data:');
        foreach ($sample_data as $field_id => $value) {
            $field = GFAPI::get_field($form, $field_id);
            $label = $field ? $field->label : "Field {$field_id}";
            WP_CLI::line("   [{$field_id}] {$label}: {$value}");
        }
        
        // Create test entry
        WP_CLI::line('');
        WP_CLI::line('💾 Creating test entry in Gravity Forms...');
        
        $entry_data = array(
            'form_id' => $form_id,
            'date_created' => current_time('mysql'),
            'is_starred' => 0,
            'is_read' => 0,
            'ip' => '127.0.0.1',
            'source_url' => home_url('/test-submission/'),
            'user_agent' => 'WP-CLI Test',
            'status' => 'active'
        );
        
        $entry_id = GFAPI::add_entry($entry_data);
        
        if (is_wp_error($entry_id)) {
            WP_CLI::error('Failed to create test entry: ' . $entry_id->get_error_message());
        }
        
        // Now update the entry with field values
        foreach ($sample_data as $field_id => $value) {
            GFAPI::update_entry_field($entry_id, $field_id, $value);
        }
        
        WP_CLI::success("Test entry created: ID {$entry_id}");
        
        // Get the full entry
        $entry = GFAPI::get_entry($entry_id);
        
        // Trigger theme function to populate combined fields (if exists)
        // This simulates the gform_entry_pre_save hook
        if (has_action('gform_entry_pre_save')) {
            WP_CLI::line('');
            WP_CLI::line('🔧 Triggering theme function to combine fields...');
            $entry = apply_filters('gform_entry_pre_save', $entry, $form);
            GFAPI::update_entry($entry);
            $entry = GFAPI::get_entry($entry_id); // Reload
            WP_CLI::line('   ✓ Field 67 (Combined Name): ' . ($entry[67] ?? 'EMPTY'));
        }
        
        WP_CLI::line('');
        WP_CLI::line('📊 Entry Data After Theme Processing:');
        foreach ($settings['field_mapping'] as $sap_field => $field_id) {
            $value = isset($entry[$field_id]) ? $entry[$field_id] : 'EMPTY';
            WP_CLI::line("   [{$field_id}] {$sap_field}: {$value}");
        }
        
        // Map entry to Business Partner data
        WP_CLI::line('');
        WP_CLI::line('🔄 Mapping entry to SAP Business Partner data...');
        
        $plugin = Shift8_GravitySAP::get_instance();
        $reflection = new ReflectionClass($plugin);
        $map_method = $reflection->getMethod('map_entry_to_business_partner');
        $map_method->setAccessible(true);
        
        $business_partner_data = $map_method->invoke($plugin, $settings, $entry, $form);
        
        WP_CLI::line('');
        WP_CLI::line('📤 Business Partner Data to Send:');
        WP_CLI::line(json_encode($business_partner_data, JSON_PRETTY_PRINT));
        
        // Validate required fields
        WP_CLI::line('');
        WP_CLI::line('✓ Validating required fields...');
        
        $required_fields = array('CardName', 'EmailAddress');
        $missing = array();
        
        foreach ($required_fields as $field) {
            if (empty($business_partner_data[$field])) {
                $missing[] = $field;
                WP_CLI::warning("Missing required field: {$field}");
            } else {
                WP_CLI::line("   ✓ {$field}: {$business_partner_data[$field]}");
            }
        }
        
        if (!empty($missing)) {
            if ($cleanup) {
                GFAPI::delete_entry($entry_id);
                WP_CLI::line('🧹 Test entry cleaned up');
            }
            WP_CLI::error('Cannot proceed - missing required fields: ' . implode(', ', $missing));
        }
        
        // Submit to SAP B1
        WP_CLI::line('');
        WP_CLI::line('🚀 Submitting to SAP B1...');
        
        $sap_settings = get_option('shift8_gravitysap_settings', array());
        
        if (empty($sap_settings['sap_endpoint']) || empty($sap_settings['sap_company_db'])) {
            if ($cleanup) {
                GFAPI::delete_entry($entry_id);
                WP_CLI::line('🧹 Test entry cleaned up');
            }
            WP_CLI::error('SAP connection settings are not configured');
        }
        
        $sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);
        
        try {
            require_once plugin_dir_path(__FILE__) . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);
            
            WP_CLI::line('   ✓ SAP Service initialized');
            WP_CLI::line('   ✓ Endpoint: ' . $sap_settings['sap_endpoint']);
            WP_CLI::line('   ✓ Company DB: ' . $sap_settings['sap_company_db']);
            
            $result = $sap_service->create_business_partner($business_partner_data);
            
            if (is_wp_error($result)) {
                WP_CLI::line('');
                WP_CLI::error('❌ SAP SUBMISSION FAILED: ' . $result->get_error_message());
                
                if ($cleanup) {
                    GFAPI::delete_entry($entry_id);
                    WP_CLI::line('🧹 Test entry cleaned up');
                }
                
                exit(1);
            }
            
            // Success!
            WP_CLI::line('');
            WP_CLI::line('═══════════════════════════════════════════════════════');
            WP_CLI::success('✅ SUBMISSION SUCCESSFUL!');
            WP_CLI::line('═══════════════════════════════════════════════════════');
            WP_CLI::line('');
            WP_CLI::line('📊 SAP Response:');
            WP_CLI::line('   CardCode: ' . $result['CardCode']);
            WP_CLI::line('   CardName: ' . $result['CardName']);
            WP_CLI::line('   CardType: ' . $result['CardType']);
            
            if (isset($result['Address'])) {
                WP_CLI::line('');
                WP_CLI::line('📍 Address Information:');
                WP_CLI::line('   Address: ' . ($result['Address'] ?? 'N/A'));
                WP_CLI::line('   City: ' . ($result['City'] ?? 'N/A'));
                WP_CLI::line('   State: ' . ($result['BillToState'] ?? 'N/A'));
                WP_CLI::line('   ZipCode: ' . ($result['ZipCode'] ?? 'N/A'));
                WP_CLI::line('   Country: ' . ($result['Country'] ?? 'N/A'));
            }
            
            WP_CLI::line('');
            WP_CLI::line('📞 Contact Information:');
            WP_CLI::line('   Email: ' . ($result['EmailAddress'] ?? 'N/A'));
            WP_CLI::line('   Phone: ' . ($result['Phone1'] ?? 'N/A'));
            WP_CLI::line('   Website: ' . ($result['Website'] ?? 'N/A'));
            
            // Update the test entry with SAP status
            gform_update_meta($entry_id, 'sap_b1_status', 'success');
            gform_update_meta($entry_id, 'sap_b1_cardcode', $result['CardCode']);
            
            WP_CLI::line('');
            WP_CLI::line("✓ Test entry {$entry_id} updated with SAP status");
            
            // Verify data by querying SAP B1
            WP_CLI::line('');
            WP_CLI::line('🔍 VERIFYING DATA IN SAP B1...');
            WP_CLI::line('   Querying SAP for Business Partner: ' . $result['CardCode']);
            
            $verification = $this->verify_sap_data($sap_service, $result['CardCode'], $business_partner_data);
            
            if ($verification['success']) {
                WP_CLI::line('');
                WP_CLI::line('═══════════════════════════════════════════════════════');
                WP_CLI::success('✅ VERIFICATION SUCCESSFUL!');
                WP_CLI::line('═══════════════════════════════════════════════════════');
                WP_CLI::line('');
                WP_CLI::line('📋 Field Comparison:');
                
                foreach ($verification['comparisons'] as $comparison) {
                    $status = $comparison['match'] ? '✓' : '✗';
                    $color = $comparison['match'] ? 'success' : 'warning';
                    
                    if ($comparison['match']) {
                        WP_CLI::line("   {$status} {$comparison['field']}: {$comparison['sent']}");
                    } else {
                        WP_CLI::warning("   {$status} {$comparison['field']}: Sent '{$comparison['sent']}' but SAP has '{$comparison['received']}'");
                    }
                }
                
                WP_CLI::line('');
                WP_CLI::line("✓ Matched: {$verification['matched']}/{$verification['total']} fields");
                
                if ($verification['matched'] < $verification['total']) {
                    WP_CLI::warning("⚠️  Some fields did not match - review above");
                }
            } else {
                WP_CLI::error('❌ VERIFICATION FAILED: ' . $verification['error']);
            }
            
            // Test Sales Quotation creation if enabled
            if (!empty($settings['create_quotation']) && $settings['create_quotation'] === '1') {
                $this->test_quotation_creation($entry_id, $form, $settings, $result['CardCode'], $sap_service);
            }
            
        } catch (Exception $e) {
            WP_CLI::line('');
            WP_CLI::error('❌ EXCEPTION: ' . $e->getMessage());
            
            if ($cleanup) {
                GFAPI::delete_entry($entry_id);
                WP_CLI::line('🧹 Test entry cleaned up');
            }
            
            exit(1);
        }
        
        // Cleanup
        if ($cleanup) {
            WP_CLI::line('');
            WP_CLI::line('🧹 Cleaning up test entry...');
            $deleted = GFAPI::delete_entry($entry_id);
            if ($deleted) {
                WP_CLI::success("Test entry {$entry_id} deleted");
            }
        } else {
            WP_CLI::line('');
            WP_CLI::line("ℹ️  Test entry {$entry_id} preserved (use --cleanup to delete)");
        }
        
        WP_CLI::line('');
        WP_CLI::line('═══════════════════════════════════════════════════════');
        WP_CLI::line('  TEST COMPLETE');
        WP_CLI::line('═══════════════════════════════════════════════════════');
        WP_CLI::line('');
    }
    
    /**
     * Verify data in SAP B1 by querying and comparing
     */
    private function verify_sap_data($sap_service, $card_code, $sent_data) {
        try {
            // Use reflection to access private make_request method
            $reflection = new ReflectionClass($sap_service);
            $make_request_method = $reflection->getMethod('make_request');
            $make_request_method->setAccessible(true);
            
            // Query SAP for the Business Partner
            $response = $make_request_method->invoke(
                $sap_service,
                'GET',
                "/BusinessPartners('{$card_code}')"
            );
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error' => $response->get_error_message()
                );
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                return array(
                    'success' => false,
                    'error' => "SAP returned status code {$status_code}"
                );
            }
            
            $body = wp_remote_retrieve_body($response);
            $sap_data = json_decode($body, true);
            
            if (!$sap_data) {
                return array(
                    'success' => false,
                    'error' => 'Failed to parse SAP response'
                );
            }
            
            // Compare sent data with received data
            $comparisons = array();
            $matched = 0;
            $total = 0;
            
            // Compare main fields
            $fields_to_check = array(
                'CardName' => 'CardName',
                'CardType' => 'CardType',
                'EmailAddress' => 'EmailAddress',
                'Phone1' => 'Phone1',
                'Website' => 'Website',
            );
            
            foreach ($fields_to_check as $sent_field => $sap_field) {
                if (isset($sent_data[$sent_field])) {
                    $total++;
                    $sent_value = $sent_data[$sent_field];
                    $received_value = $sap_data[$sap_field] ?? '';
                    $match = ($sent_value === $received_value);
                    
                    if ($match) {
                        $matched++;
                    }
                    
                    $comparisons[] = array(
                        'field' => $sent_field,
                        'sent' => $sent_value,
                        'received' => $received_value,
                        'match' => $match
                    );
                }
            }
            
            // Compare address fields
            if (isset($sent_data['BPAddresses'][0])) {
                $sent_address = $sent_data['BPAddresses'][0];
                
                $address_fields = array(
                    'Street' => 'Address',
                    'City' => 'City',
                    'State' => 'BillToState',
                    'ZipCode' => 'ZipCode',
                    'Country' => 'Country'
                );
                
                foreach ($address_fields as $bp_field => $main_field) {
                    if (isset($sent_address[$bp_field])) {
                        $total++;
                        $sent_value = $sent_address[$bp_field];
                        $received_value = $sap_data[$main_field] ?? '';
                        $match = ($sent_value === $received_value);
                        
                        if ($match) {
                            $matched++;
                        }
                        
                        $comparisons[] = array(
                            'field' => "Address.{$bp_field}",
                            'sent' => $sent_value,
                            'received' => $received_value,
                            'match' => $match
                        );
                    }
                }
            }
            
            return array(
                'success' => true,
                'comparisons' => $comparisons,
                'matched' => $matched,
                'total' => $total,
                'sap_data' => $sap_data
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Generate sample data for mapped fields
     */
    private function generate_sample_data($form, $settings, $real_item_codes = array()) {
        $field_mapping = isset($settings['field_mapping']) ? $settings['field_mapping'] : array();
        $quotation_mapping = isset($settings['quotation_field_mapping']) ? $settings['quotation_field_mapping'] : array();
        $sample_data = array();
        
        // Sample data templates for Business Partner
        $samples = array(
            'CardName' => 'Test User ' . rand(1000, 9999),
            'CardName_FirstName' => 'Test',
            'CardName_LastName' => 'User ' . rand(1000, 9999),
            'EmailAddress' => 'test' . rand(1000, 9999) . '@example.com',
            'Phone1' => '416-555-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'Phone2' => '416-555-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'Fax' => '416-555-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'Website' => 'https://example-' . rand(100, 999) . '.com',
            'BPAddresses.Street' => 'Sample value for Address',
            'BPAddresses.City' => 'Toronto',
            'BPAddresses.State' => 'ON',
            'BPAddresses.ZipCode' => 'M' . rand(1, 9) . chr(rand(65, 90)) . ' ' . rand(1, 9) . chr(rand(65, 90)) . rand(1, 9),
            'BPAddresses.Country' => 'CA',
            'ContactEmployees.FirstName' => 'Test',
            'ContactEmployees.LastName' => 'User ' . rand(1000, 9999),
            'ContactEmployees.Phone1' => '416-555-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'ContactEmployees.E_Mail' => 'test' . rand(1000, 9999) . '@example.com',
            'ContactEmployees.Address' => 'Sample value for Address',
            'Notes' => 'Test submission from WP-CLI at ' . current_time('mysql'),
            'FederalTaxID' => rand(100000000, 999999999),
            'ContactPerson' => 'Test Contact',
        );
        
        // Sample data templates for Quotation
        $quotation_samples = array(
            'Comments' => 'Test quotation created via WP-CLI at ' . current_time('mysql'),
        );
        
        // Use real ItemCodes if provided, otherwise fallback
        if (!empty($real_item_codes)) {
            $sample_items = $real_item_codes;
        } else {
            // Use a single, simple ItemCode that's likely to exist
            // Most SAP systems have basic test items like this
            $sample_items = array(
                'A00001'  // Very common default ItemCode in SAP B1
            );
        }
        
        // Map Business Partner sample data to actual field IDs
        foreach ($field_mapping as $sap_field => $field_id) {
            if (isset($samples[$sap_field])) {
                $sample_data[$field_id] = $samples[$sap_field];
            } else {
                // Generate generic sample data based on field type
                $field = GFAPI::get_field($form, $field_id);
                if ($field) {
                    $sample_data[$field_id] = $this->generate_field_value($field);
                }
            }
        }
        
        // Map Quotation sample data to actual field IDs
        // With the new two-part mapping: quotation_field_mapping (form fields) + quotation_itemcode_mapping (SAP ItemCodes)
        foreach ($quotation_mapping as $quotation_field => $field_id) {
            if (strpos($quotation_field, 'ItemCode') !== false) {
                // For ItemCode fields, generate sample data for the mapped form field
                // The actual ItemCode comes from quotation_itemcode_mapping
                if (!empty($field_id)) {
                    $sample_data[$field_id] = 'checked'; // Simulate checkbox being checked
                }
            } elseif (isset($quotation_samples[$quotation_field])) {
                $sample_data[$field_id] = $quotation_samples[$quotation_field];
            } elseif (strpos($quotation_field, 'ItemDescription') !== false) {
                $sample_data[$field_id] = 'Test item description ' . rand(1, 100);
            } elseif (strpos($quotation_field, 'Quantity') !== false) {
                $sample_data[$field_id] = rand(1, 5);
            } elseif (strpos($quotation_field, 'UnitPrice') !== false) {
                $sample_data[$field_id] = rand(10, 500) . '.' . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
            } elseif (strpos($quotation_field, 'DiscountPercent') !== false) {
                $sample_data[$field_id] = rand(0, 20);
            } elseif (strpos($quotation_field, 'TaxCode') !== false) {
                $sample_data[$field_id] = 'TAX1';
            } else {
                // Generate generic sample data based on field type
                $field = GFAPI::get_field($form, $field_id);
                if ($field) {
                    $sample_data[$field_id] = $this->generate_field_value($field);
                }
            }
        }
        
        return $sample_data;
    }
    
    /**
     * Generate sample value based on field type
     */
    private function generate_field_value($field) {
        switch ($field->type) {
            case 'email':
                return 'test' . rand(1000, 9999) . '@example.com';
            
            case 'phone':
                return '416-555-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            
            case 'website':
                return 'https://example-' . rand(100, 999) . '.com';
            
            case 'number':
                return rand(1, 1000);
            
            case 'textarea':
                return 'Sample text for ' . $field->label;
            
            case 'select':
            case 'radio':
                if (!empty($field->choices)) {
                    return $field->choices[0]['value'];
                }
                return 'Option 1';
            
            case 'checkbox':
                if (!empty($field->choices)) {
                    return $field->choices[0]['value'];
                }
                return 'Yes';
            
            case 'address':
                return array(
                    'street' => rand(100, 999) . ' Test Street',
                    'city' => 'Toronto',
                    'state' => 'ON',
                    'zip' => 'M' . rand(1, 9) . chr(rand(65, 90)) . ' ' . rand(1, 9) . chr(rand(65, 90)) . rand(1, 9),
                    'country' => 'Canada'
                );
            
            case 'name':
                return array(
                    'first' => 'Test',
                    'last' => 'User ' . rand(1000, 9999)
                );
            
            default:
                return 'Sample value for ' . $field->label;
        }
    }
    
    /**
     * Test Sales Quotation creation
     */
    private function test_quotation_creation($entry_id, $form, $settings, $card_code, $sap_service) {
        WP_CLI::line('');
        WP_CLI::line('═══════════════════════════════════════════════════════');
        WP_CLI::line('🧾 TESTING SALES QUOTATION CREATION');
        WP_CLI::line('═══════════════════════════════════════════════════════');
        WP_CLI::line('');
        
        try {
            // Get the entry data
            $entry = GFAPI::get_entry($entry_id);
            if (is_wp_error($entry)) {
                WP_CLI::error('Failed to get entry data for quotation testing');
                return;
            }
            
                WP_CLI::line('📋 Quotation Configuration:');
                $quotation_mapping = isset($settings['quotation_field_mapping']) ? $settings['quotation_field_mapping'] : array();
                $itemcode_mapping = isset($settings['quotation_itemcode_mapping']) ? $settings['quotation_itemcode_mapping'] : array();
                WP_CLI::line('   Form Field Mappings: ' . count($quotation_mapping));
                WP_CLI::line('   ItemCode Mappings: ' . count($itemcode_mapping));
                WP_CLI::line('   Business Partner: ' . $card_code);
                
                if (empty($quotation_mapping) || empty($itemcode_mapping)) {
                    WP_CLI::warning('⚠️  Incomplete quotation mapping (need both form fields AND ItemCodes) - skipping quotation test');
                    return;
                }
                
                // Show which ItemCodes will be used
                WP_CLI::line('');
                WP_CLI::line('📦 Configured ItemCodes:');
                $price_mapping = isset($settings['quotation_price_mapping']) ? $settings['quotation_price_mapping'] : array();
                foreach ($itemcode_mapping as $quotation_field => $item_code) {
                    if (strpos($quotation_field, 'ItemCode') !== false) {
                        $form_field_id = $quotation_mapping[$quotation_field] ?? 'Not mapped';
                        $custom_price = isset($price_mapping[$quotation_field]) ? $price_mapping[$quotation_field] : null;
                        $price_info = $custom_price ? ' [Price: $' . number_format($custom_price, 2) . ']' : ' [Price: SAP Auto]';
                        WP_CLI::line('   ' . $quotation_field . ': ' . $item_code . ' (triggered by field #' . $form_field_id . ')' . $price_info);
                    }
                }
            
            // Use the same method as the main plugin
            $plugin = Shift8_GravitySAP::get_instance();
            $reflection = new ReflectionClass($plugin);
            $method = $reflection->getMethod('create_sales_quotation_from_entry');
            $method->setAccessible(true);
            
            WP_CLI::line('');
            WP_CLI::line('🚀 Creating Sales Quotation...');
            WP_CLI::line('   Using CardCode: ' . $card_code);
            
            $quotation_result = $method->invoke($plugin, $entry, $form, $settings, $card_code, $sap_service);
            
            if ($quotation_result && isset($quotation_result['DocEntry'])) {
                // Update entry with quotation info
                gform_update_meta($entry_id, 'sap_b1_quotation_docentry', $quotation_result['DocEntry']);
                gform_update_meta($entry_id, 'sap_b1_quotation_docnum', $quotation_result['DocNum'] ?? '');
                
                WP_CLI::line('');
                WP_CLI::line('═══════════════════════════════════════════════════════');
                WP_CLI::success('✅ QUOTATION CREATION SUCCESSFUL!');
                WP_CLI::line('═══════════════════════════════════════════════════════');
                WP_CLI::line('');
                WP_CLI::line('📊 SAP Quotation Response:');
                WP_CLI::line('   DocEntry: ' . $quotation_result['DocEntry']);
                WP_CLI::line('   DocNum: ' . ($quotation_result['DocNum'] ?? 'N/A'));
                WP_CLI::line('   CardCode: ' . ($quotation_result['CardCode'] ?? $card_code));
                
                if (isset($quotation_result['DocumentLines']) && is_array($quotation_result['DocumentLines'])) {
                    WP_CLI::line('');
                    WP_CLI::line('📦 Line Items (' . count($quotation_result['DocumentLines']) . ' items):');
                    foreach ($quotation_result['DocumentLines'] as $index => $line) {
                        $line_num = $index + 1;
                        WP_CLI::line("   Line {$line_num}:");
                        WP_CLI::line('     ItemCode: ' . ($line['ItemCode'] ?? 'N/A'));
                        WP_CLI::line('     Description: ' . ($line['ItemDescription'] ?? 'N/A'));
                        WP_CLI::line('     Quantity: ' . ($line['Quantity'] ?? 'N/A'));
                        if (isset($line['UnitPrice'])) {
                            WP_CLI::line('     Unit Price: ' . $line['UnitPrice']);
                        }
                        if (isset($line['DiscountPercent'])) {
                            WP_CLI::line('     Discount: ' . $line['DiscountPercent'] . '%');
                        }
                    }
                }
                
                WP_CLI::line('');
                WP_CLI::line("✓ Test entry {$entry_id} updated with quotation info");
                
                // Verify quotation in SAP B1
                WP_CLI::line('');
                WP_CLI::line('🔍 VERIFYING QUOTATION IN SAP B1...');
                WP_CLI::line('   Querying SAP for Quotation DocEntry: ' . $quotation_result['DocEntry']);
                
                $verification = $sap_service->get_sales_quotation($quotation_result['DocEntry']);
                
                if ($verification) {
                    WP_CLI::line('');
                    WP_CLI::line('═══════════════════════════════════════════════════════');
                    WP_CLI::success('✅ QUOTATION VERIFICATION SUCCESSFUL!');
                    WP_CLI::line('═══════════════════════════════════════════════════════');
                    WP_CLI::line('');
                    WP_CLI::line('📋 Verification Results:');
                    WP_CLI::line('   ✓ Quotation exists in SAP B1');
                    WP_CLI::line('   ✓ DocEntry: ' . ($verification['DocEntry'] ?? 'N/A'));
                    WP_CLI::line('   ✓ DocNum: ' . ($verification['DocNum'] ?? 'N/A'));
                    WP_CLI::line('   ✓ CardCode: ' . ($verification['CardCode'] ?? 'N/A'));
                    WP_CLI::line('   ✓ Status: ' . ($verification['DocumentStatus'] ?? 'N/A'));
                    
                    if (isset($verification['DocumentLines']) && is_array($verification['DocumentLines'])) {
                        WP_CLI::line('   ✓ Line Items: ' . count($verification['DocumentLines']) . ' items verified');
                    }
                } else {
                    WP_CLI::warning('⚠️  Could not verify quotation in SAP B1 (may still exist)');
                }
                
            } else {
                WP_CLI::error('❌ QUOTATION CREATION FAILED: No DocEntry returned');
            }
            
        } catch (Exception $e) {
            WP_CLI::line('');
            WP_CLI::error('❌ QUOTATION CREATION FAILED: ' . $e->getMessage());
            WP_CLI::line('   Business Partner was created successfully, but quotation failed');
        }
    }
    
    /**
     * Get real ItemCodes from SAP B1 for testing
     */
    private function get_real_item_codes() {
        try {
            // Get SAP settings
            $sap_settings = get_option('shift8_gravitysap_settings', array());
            
            if (empty($sap_settings['sap_endpoint']) || empty($sap_settings['sap_company_db'])) {
                return array();
            }
            
            $sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);
            
            require_once plugin_dir_path(__FILE__) . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);
            
            // Use reflection to access private make_request method
            $reflection = new ReflectionClass($sap_service);
            $method = $reflection->getMethod('make_request');
            $method->setAccessible(true);
            
            // Query for first 20 items
            $response = $method->invoke($sap_service, 'GET', '/Items?$top=20&$select=ItemCode,ItemName');
            
            if (is_wp_error($response)) {
                return array();
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return array();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!isset($data['value']) || !is_array($data['value'])) {
                return array();
            }
            
            $item_codes = array();
            foreach ($data['value'] as $item) {
                if (!empty($item['ItemCode'])) {
                    $item_codes[] = $item['ItemCode'];
                }
            }
            
            return $item_codes;
            
        } catch (Exception $e) {
            // Silently fail and use fallback items
            return array();
        }
    }
}

WP_CLI::add_command('shift8-gravitysap', 'Shift8_GravitySAP_Test_Command');

/**
 * WP-CLI command to test ItemCode loading
 */
class Shift8_GravitySAP_ItemCode_Test_Command {
    
    /**
     * Test ItemCode loading using the same functions as the admin button
     *
     * ## EXAMPLES
     *
     *     wp shift8-gravitysap-itemcodes load
     *     wp shift8-gravitysap-itemcodes load --force-refresh
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function load($args, $assoc_args) {
        WP_CLI::line('');
        WP_CLI::line('═══════════════════════════════════════════════════════');
        WP_CLI::line('  SHIFT8 GRAVITYSAP - ITEMCODE LOADING TEST');
        WP_CLI::line('═══════════════════════════════════════════════════════');
        WP_CLI::line('');
        
        $force_refresh = isset($assoc_args['force-refresh']);
        
        WP_CLI::line('🔧 Test Configuration:');
        WP_CLI::line('   Force Refresh: ' . ($force_refresh ? 'Yes' : 'No'));
        WP_CLI::line('');
        
        try {
            // Get the plugin instance
            $plugin = Shift8_GravitySAP::get_instance();
            
            WP_CLI::line('📋 Testing SAP Connection...');
            
            // Test basic SAP connection first
            $sap_settings = get_option('shift8_gravitysap_settings', array());
            if (empty($sap_settings['sap_endpoint']) || empty($sap_settings['sap_company_db'])) {
                WP_CLI::error('❌ SAP settings not configured');
                return;
            }
            
            WP_CLI::line('   ✓ SAP Endpoint: ' . $sap_settings['sap_endpoint']);
            WP_CLI::line('   ✓ Company DB: ' . $sap_settings['sap_company_db']);
            WP_CLI::line('   ✓ Username: ' . $sap_settings['sap_username']);
            WP_CLI::line('   ✓ Password: ' . (!empty($sap_settings['sap_password']) ? 'Set' : 'Not set'));
            WP_CLI::line('');
            
            // Clear persistent storage if force refresh
            if ($force_refresh) {
                WP_CLI::line('🔄 Clearing ItemCode persistent storage...');
                delete_option('shift8_gravitysap_item_codes_data');
                WP_CLI::line('   ✓ Persistent storage cleared');
                WP_CLI::line('');
            }
            
            // Check current persistent storage status
            $stored_data = get_option('shift8_gravitysap_item_codes_data', array());
            if (!empty($stored_data['items']) && !$force_refresh) {
                $last_updated = !empty($stored_data['last_updated_formatted']) ? $stored_data['last_updated_formatted'] : 'Unknown';
                WP_CLI::line('💾 Found persistent ItemCodes: ' . count($stored_data['items']) . ' (Last updated: ' . $last_updated . ')');
                WP_CLI::line('');
            }
            
            WP_CLI::line('🔍 Loading ItemCodes using plugin method...');
            
            // Use reflection to access the private get_sap_item_codes method
            $reflection = new ReflectionClass($plugin);
            $method = $reflection->getMethod('get_sap_item_codes');
            $method->setAccessible(true);
            
            // Call the exact same method as the button
            $items = $method->invoke($plugin);
            
            if (empty($items)) {
                WP_CLI::line('');
                WP_CLI::line('═══════════════════════════════════════════════════════');
                WP_CLI::line('❌ NO ITEMCODES FOUND');
                WP_CLI::line('═══════════════════════════════════════════════════════');
                WP_CLI::line('');
                WP_CLI::line('🔍 Debugging Information:');
                WP_CLI::line('   This uses the exact same get_sap_item_codes() method as the admin button');
                WP_CLI::line('   If the "Test SAP Connection" works but this fails, there may be an issue with:');
                WP_CLI::line('   - ItemCode query permissions');
                WP_CLI::line('   - SAP B1 Items table access');
                WP_CLI::line('   - Authentication context differences');
                WP_CLI::line('');
                
                // Try to get more debug info
                WP_CLI::line('🔧 Additional Debug Test:');
                $this->debug_sap_connection($sap_settings);
                
                WP_CLI::error('ItemCode loading failed');
                return;
            }
            
            WP_CLI::line('');
            WP_CLI::line('═══════════════════════════════════════════════════════');
            WP_CLI::success('✅ ITEMCODES LOADED SUCCESSFULLY!');
            WP_CLI::line('═══════════════════════════════════════════════════════');
            WP_CLI::line('');
            WP_CLI::line('📊 Results:');
            WP_CLI::line('   Total ItemCodes: ' . count($items));
            $stored_data = get_option('shift8_gravitysap_item_codes_data', array());
            $storage_status = !empty($stored_data['items']) ? 'Stored (' . count($stored_data['items']) . ' items)' : 'Not stored';
            WP_CLI::line('   Storage Status: ' . $storage_status);
            WP_CLI::line('');
            
            WP_CLI::line('📋 Sample ItemCodes (first 10):');
            $count = 0;
            foreach ($items as $item_code => $item_name) {
                WP_CLI::line('   ' . $item_code . ' - ' . $item_name);
                $count++;
                if ($count >= 10) {
                    if (count($items) > 10) {
                        WP_CLI::line('   ... and ' . (count($items) - 10) . ' more');
                    }
                    break;
                }
            }
            
            WP_CLI::line('');
            WP_CLI::line('✅ The admin button should now work properly!');
            
        } catch (Exception $e) {
            WP_CLI::line('');
            WP_CLI::error('❌ EXCEPTION: ' . $e->getMessage());
        }
    }
    
        /**
         * Check item UoM details
         */
        public function item($args, $assoc_args) {
            $item_code = isset($args[0]) ? $args[0] : '100086';
            WP_CLI::line('Checking Item ' . $item_code . ' UoM details...');
            
            try {
                // Get SAP service
                $sap_settings = get_option('shift8_gravitysap_settings', array());
                $sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);
                
                require_once plugin_dir_path(__FILE__) . 'includes/class-shift8-gravitysap-sap-service.php';
                $sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);
                
                // Use reflection to access private methods
                $reflection = new ReflectionClass($sap_service);
                
                // Ensure authentication first
                $auth_method = $reflection->getMethod('ensure_authenticated');
                $auth_method->setAccessible(true);
                $auth_result = $auth_method->invoke($sap_service);
                
                if (!$auth_result) {
                    WP_CLI::error('SAP Authentication failed');
                    return;
                }
                
                $method = $reflection->getMethod('make_request');
                $method->setAccessible(true);
                
                $response = $method->invoke($sap_service, 'GET', '/Items(' . $item_code . ')?$select=ItemCode,ItemName,InventoryUoMEntry,SalesUoMEntry,PurchaseUoMEntry');
                
                if (is_wp_error($response)) {
                    WP_CLI::error('SAP Error: ' . $response->get_error_message());
                    return;
                }
                
                $code = wp_remote_retrieve_response_code($response);
                if ($code !== 200) {
                    WP_CLI::error('HTTP Error: ' . $code);
                    return;
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if ($data) {
                    WP_CLI::line('Item Details:');
                    WP_CLI::line('  ItemCode: ' . $data['ItemCode']);
                    WP_CLI::line('  ItemName: ' . $data['ItemName']);
                    WP_CLI::line('  InventoryUoMEntry: ' . $data['InventoryUoMEntry']);
                    WP_CLI::line('  SalesUoMEntry: ' . $data['SalesUoMEntry']);
                    WP_CLI::line('  PurchaseUoMEntry: ' . $data['PurchaseUoMEntry']);
                } else {
                    WP_CLI::warning('No item data found');
                }
                
            } catch (Exception $e) {
                WP_CLI::error('Exception: ' . $e->getMessage());
            }
        }

        /**
         * Test UoM codes from SAP
         */
        public function uom($args, $assoc_args) {
            WP_CLI::line('Querying SAP B1 for Unit of Measure codes...');
            
            try {
                // Get the plugin instance
                $plugin = Shift8_GravitySAP::get_instance();
                
                // Get SAP service
                $sap_settings = get_option('shift8_gravitysap_settings', array());
                $sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);
                
                require_once plugin_dir_path(__FILE__) . 'includes/class-shift8-gravitysap-sap-service.php';
                $sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);
                
                // Use reflection to access private methods
                $reflection = new ReflectionClass($sap_service);
                
                // Ensure authentication first
                $auth_method = $reflection->getMethod('ensure_authenticated');
                $auth_method->setAccessible(true);
                $auth_result = $auth_method->invoke($sap_service);
                
                if (!$auth_result) {
                    WP_CLI::error('SAP Authentication failed');
                    return;
                }
                
                $method = $reflection->getMethod('make_request');
                $method->setAccessible(true);
                
                $response = $method->invoke($sap_service, 'GET', '/UnitOfMeasurements?$top=20&$select=Code,Name');
                
                if (is_wp_error($response)) {
                    WP_CLI::error('SAP Error: ' . $response->get_error_message());
                    return;
                }
                
                $code = wp_remote_retrieve_response_code($response);
                if ($code !== 200) {
                    WP_CLI::error('HTTP Error: ' . $code);
                    return;
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (isset($data['value']) && !empty($data['value'])) {
                    WP_CLI::line('Available UoM codes:');
                    foreach ($data['value'] as $uom) {
                        WP_CLI::line('  ' . $uom['Code'] . ' - ' . $uom['Name']);
                    }
                } else {
                    WP_CLI::warning('No UoM codes found');
                }
                
            } catch (Exception $e) {
                WP_CLI::error('Exception: ' . $e->getMessage());
            }
        }

        /**
         * Debug SAP connection in more detail
         */
        private function debug_sap_connection($sap_settings) {
        try {
            WP_CLI::line('   Testing direct SAP Service Layer connection...');
            
            // Decrypt password
            $sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);
            
            // Create SAP service
            require_once plugin_dir_path(__FILE__) . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);
            
            // Use reflection to test authentication
            $reflection = new ReflectionClass($sap_service);
            $auth_method = $reflection->getMethod('ensure_authenticated');
            $auth_method->setAccessible(true);
            
            $auth_result = $auth_method->invoke($sap_service);
            WP_CLI::line('   Authentication: ' . ($auth_result ? '✓ Success' : '❌ Failed'));
            
            if ($auth_result) {
                // Try a simple Items query
                $request_method = $reflection->getMethod('make_request');
                $request_method->setAccessible(true);
                
                WP_CLI::line('   Testing Items query...');
                $response = $request_method->invoke($sap_service, 'GET', '/Items?$top=1&$select=ItemCode,ItemName');
                
                if (is_wp_error($response)) {
                    WP_CLI::line('   Items Query: ❌ ' . $response->get_error_message());
                } else {
                    $code = wp_remote_retrieve_response_code($response);
                    WP_CLI::line('   Items Query: ' . ($code === 200 ? '✓ Success' : '❌ HTTP ' . $code));
                    
                    if ($code === 200) {
                        $body = wp_remote_retrieve_body($response);
                        $data = json_decode($body, true);
                        if (isset($data['value']) && !empty($data['value'])) {
                            WP_CLI::line('   Sample Item: ' . $data['value'][0]['ItemCode'] . ' - ' . ($data['value'][0]['ItemName'] ?? 'No name'));
                        } else {
                            WP_CLI::line('   Items Response: ❌ No items in response');
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            WP_CLI::line('   Debug Error: ' . $e->getMessage());
        }
    }
}

WP_CLI::add_command('shift8-gravitysap-itemcodes', 'Shift8_GravitySAP_ItemCode_Test_Command');

/**
 * Query SAP B1 master data (Groups, Currencies, Price Lists, etc.)
 */
class Shift8_GravitySAP_MasterData_Command {
    
    /**
     * List Business Partner Groups from SAP B1
     *
     * ## EXAMPLES
     *
     *     wp shift8-gravitysap-masterdata groups
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function groups($args, $assoc_args) {
        $this->query_sap_endpoint(
            '/BusinessPartnerGroups',
            'Business Partner Groups',
            array('Code', 'Name', 'Type'),
            function($item) {
                return sprintf(
                    "Code: %-6s | Name: %s | Type: %s",
                    $item['Code'] ?? 'N/A',
                    $item['Name'] ?? 'N/A',
                    $item['Type'] ?? 'N/A'
                );
            }
        );
    }
    
    /**
     * List Currencies from SAP B1
     *
     * ## EXAMPLES
     *
     *     wp shift8-gravitysap-masterdata currencies
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function currencies($args, $assoc_args) {
        $this->query_sap_endpoint(
            '/Currencies',
            'Currencies',
            array('Code', 'Name'),
            function($item) {
                return sprintf(
                    "Code: %-5s | Name: %s",
                    $item['Code'] ?? 'N/A',
                    $item['Name'] ?? 'N/A'
                );
            }
        );
    }
    
    /**
     * List Price Lists from SAP B1
     *
     * ## EXAMPLES
     *
     *     wp shift8-gravitysap-masterdata pricelists
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function pricelists($args, $assoc_args) {
        $this->query_sap_endpoint(
            '/PriceLists',
            'Price Lists',
            array('PriceListNo', 'PriceListName', 'BasePriceList'),
            function($item) {
                return sprintf(
                    "PriceListNo: %-4s | Name: %-30s | BasePriceList: %s",
                    $item['PriceListNo'] ?? 'N/A',
                    $item['PriceListName'] ?? 'N/A',
                    $item['BasePriceList'] ?? 'N/A'
                );
            }
        );
    }
    
    /**
     * List all master data types available
     *
     * ## EXAMPLES
     *
     *     wp shift8-gravitysap-masterdata list
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function list($args, $assoc_args) {
        WP_CLI::line('');
        WP_CLI::line('Available SAP B1 Master Data Commands:');
        WP_CLI::line('======================================');
        WP_CLI::line('');
        WP_CLI::line('  wp shift8-gravitysap-masterdata groups      - List Business Partner Groups (for GroupCode field)');
        WP_CLI::line('  wp shift8-gravitysap-masterdata currencies  - List Currencies (for Currency field)');
        WP_CLI::line('  wp shift8-gravitysap-masterdata pricelists  - List Price Lists (for PriceListNum field)');
        WP_CLI::line('');
        WP_CLI::line('These commands show you the actual SAP codes to use in your form field mappings.');
        WP_CLI::line('');
    }
    
    /**
     * Generic method to query SAP endpoint and display results
     *
     * @param string $endpoint SAP API endpoint
     * @param string $title Display title
     * @param array $select_fields Fields to select
     * @param callable $formatter Function to format each item
     */
    private function query_sap_endpoint($endpoint, $title, $select_fields, $formatter) {
        WP_CLI::line('');
        WP_CLI::line("Querying SAP B1 for {$title}...");
        WP_CLI::line('');
        
        try {
            // Get SAP settings
            $sap_settings = get_option('shift8_gravitysap_settings', array());
            
            if (empty($sap_settings['sap_endpoint']) || empty($sap_settings['sap_username']) || empty($sap_settings['sap_password'])) {
                WP_CLI::error('SAP connection settings not configured. Please configure in WordPress Admin > Settings > Shift8 GravitySAP.');
                return;
            }
            
            // Decrypt password
            $sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);
            
            // Create SAP service
            require_once plugin_dir_path(__FILE__) . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);
            
            // Authenticate
            $reflection = new ReflectionClass($sap_service);
            $auth_method = $reflection->getMethod('ensure_authenticated');
            $auth_method->setAccessible(true);
            
            if (!$auth_method->invoke($sap_service)) {
                WP_CLI::error('Failed to authenticate with SAP B1');
                return;
            }
            
            // Build query with $select
            $select_param = implode(',', $select_fields);
            $query_endpoint = "{$endpoint}?\$select={$select_param}";
            
            // Make request
            $request_method = $reflection->getMethod('make_request');
            $request_method->setAccessible(true);
            
            $response = $request_method->invoke($sap_service, 'GET', $query_endpoint);
            
            if (is_wp_error($response)) {
                WP_CLI::error('SAP API Error: ' . $response->get_error_message());
                return;
            }
            
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                WP_CLI::error("SAP API returned HTTP {$code}");
                return;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!isset($data['value']) || empty($data['value'])) {
                WP_CLI::warning("No {$title} found in SAP B1");
                return;
            }
            
            WP_CLI::success("Found " . count($data['value']) . " {$title}:");
            WP_CLI::line('');
            WP_CLI::line(str_repeat('-', 80));
            
            foreach ($data['value'] as $item) {
                WP_CLI::line($formatter($item));
            }
            
            WP_CLI::line(str_repeat('-', 80));
            WP_CLI::line('');
            WP_CLI::line("Use the 'Code' values (not the Names) when mapping form fields to SAP.");
            WP_CLI::line('');
            
        } catch (Exception $e) {
            WP_CLI::error('Error: ' . $e->getMessage());
        }
    }
}

WP_CLI::add_command('shift8-gravitysap-masterdata', 'Shift8_GravitySAP_MasterData_Command');

/**
 * Test Business Partner lookup for duplicate detection
 * 
 * This command tests the performance and accuracy of looking up existing
 * Business Partners in SAP B1 based on name, country, and postal code.
 */
class Shift8_GravitySAP_BP_Lookup_Command {
    
    /**
     * Search for existing Business Partners matching criteria
     *
     * ## OPTIONS
     *
     * --name=<name>
     * : Business Partner name to search for (case-insensitive)
     *
     * --country=<country>
     * : 2-letter country code (e.g., US, CA, GB)
     *
     * --postal=<postal>
     * : Postal/ZIP code
     *
     * [--verbose]
     * : Show detailed timing and query information
     *
     * ## EXAMPLES
     *
     *     wp shift8-gravitysap-bp-lookup search --name="Test Company" --country=US --postal=12345
     *     wp shift8-gravitysap-bp-lookup search --name="Acme Corp" --country=CA --postal="M5V 1A1" --verbose
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function search($args, $assoc_args) {
        $name = isset($assoc_args['name']) ? sanitize_text_field($assoc_args['name']) : '';
        $country = isset($assoc_args['country']) ? strtoupper(sanitize_text_field($assoc_args['country'])) : '';
        $postal = isset($assoc_args['postal']) ? sanitize_text_field($assoc_args['postal']) : '';
        $verbose = isset($assoc_args['verbose']);
        
        if (empty($name)) {
            WP_CLI::error('Please specify a Business Partner name: --name="Company Name"');
            return;
        }
        
        if (empty($country)) {
            WP_CLI::error('Please specify a country code: --country=US');
            return;
        }
        
        if (empty($postal)) {
            WP_CLI::error('Please specify a postal code: --postal=12345');
            return;
        }
        
        WP_CLI::line('');
        WP_CLI::line('=== Business Partner Lookup Test ===');
        WP_CLI::line('');
        WP_CLI::line('Search Criteria:');
        WP_CLI::line("  Name:    {$name} (case-insensitive)");
        WP_CLI::line("  Country: {$country}");
        WP_CLI::line("  Postal:  {$postal}");
        WP_CLI::line('');
        
        try {
            // Get SAP settings
            $sap_settings = get_option('shift8_gravitysap_settings', array());
            
            if (empty($sap_settings['sap_endpoint']) || empty($sap_settings['sap_username']) || empty($sap_settings['sap_password'])) {
                WP_CLI::error('SAP connection settings not configured.');
                return;
            }
            
            // Decrypt password
            $sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);
            
            // Create SAP service
            require_once plugin_dir_path(__FILE__) . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);
            
            // Authenticate
            $reflection = new ReflectionClass($sap_service);
            $auth_method = $reflection->getMethod('ensure_authenticated');
            $auth_method->setAccessible(true);
            
            $auth_start = microtime(true);
            if (!$auth_method->invoke($sap_service)) {
                WP_CLI::error('Failed to authenticate with SAP B1');
                return;
            }
            $auth_time = round((microtime(true) - $auth_start) * 1000, 2);
            
            if ($verbose) {
                WP_CLI::line("Authentication time: {$auth_time}ms");
            }
            
            // Perform the lookup using centralized method
            $lookup_start = microtime(true);
            $result = $sap_service->find_existing_business_partner($name, $country, $postal);
            $lookup_time = round((microtime(true) - $lookup_start) * 1000, 2);
            
            WP_CLI::line('');
            WP_CLI::line(str_repeat('-', 60));
            WP_CLI::line('');
            
            if ($result['found']) {
                WP_CLI::success("Found matching Business Partner!");
                WP_CLI::line('');
                WP_CLI::line("  CardCode: {$result['card_code']}");
                WP_CLI::line("  CardName: {$result['card_name']}");
                if (!empty($result['address_country'])) {
                    WP_CLI::line("  Country:  {$result['address_country']}");
                }
                if (!empty($result['address_postal'])) {
                    WP_CLI::line("  Postal:   {$result['address_postal']}");
                }
            } else {
                WP_CLI::warning("No matching Business Partner found.");
                WP_CLI::line("  A new Business Partner would be created for this submission.");
            }
            
            WP_CLI::line('');
            WP_CLI::line('Performance Metrics:');
            WP_CLI::line("  Authentication: {$auth_time}ms");
            WP_CLI::line("  Lookup Query:   {$lookup_time}ms");
            WP_CLI::line("  Total Time:     " . round($auth_time + $lookup_time, 2) . "ms");
            
            if (isset($result['records_scanned'])) {
                WP_CLI::line("  Records Scanned: {$result['records_scanned']}");
            }
            
            WP_CLI::line('');
            
            // Performance recommendation
            $total_time = $auth_time + $lookup_time;
            if ($total_time > 3000) {
                WP_CLI::warning("Lookup time exceeds 3 seconds. Consider moving SAP processing to a background cron job.");
            } elseif ($total_time > 1000) {
                WP_CLI::line("Note: Lookup time is moderate. For high-traffic forms, consider async processing.");
            } else {
                WP_CLI::success("Lookup time is acceptable for synchronous processing.");
            }
            
            WP_CLI::line('');
            
        } catch (Exception $e) {
            WP_CLI::error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Find an existing Business Partner matching the criteria
     *
     * SAP B1 Service Layer has limited OData support - it doesn't support:
     * - tolower() for case-insensitive comparisons
     * - any() for filtering on collections
     * 
     * Strategy: Query BPs with CardName containing the search term, then filter
     * client-side for exact match (case-insensitive) and address criteria.
     *
     * @param ReflectionMethod $request_method The SAP make_request method
     * @param object $sap_service The SAP service instance
     * @param string $name Business Partner name (case-insensitive)
     * @param string $country 2-letter country code
    /**
     * Benchmark the lookup performance with multiple queries
     *
     * ## OPTIONS
     *
     * [--iterations=<num>]
     * : Number of test iterations (default: 5)
     *
     * ## EXAMPLES
     *
     *     wp shift8-gravitysap-bp-lookup benchmark
     *     wp shift8-gravitysap-bp-lookup benchmark --iterations=10
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function benchmark($args, $assoc_args) {
        $iterations = isset($assoc_args['iterations']) ? absint($assoc_args['iterations']) : 5;
        
        if ($iterations < 1 || $iterations > 20) {
            WP_CLI::error('Iterations must be between 1 and 20');
            return;
        }
        
        WP_CLI::line('');
        WP_CLI::line('=== Business Partner Lookup Benchmark ===');
        WP_CLI::line('');
        WP_CLI::line("Running {$iterations} iterations with sample queries...");
        WP_CLI::line('');
        
        try {
            // Get SAP settings
            $sap_settings = get_option('shift8_gravitysap_settings', array());
            
            if (empty($sap_settings['sap_endpoint'])) {
                WP_CLI::error('SAP connection settings not configured.');
                return;
            }
            
            // Decrypt password
            $sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);
            
            // Create SAP service
            require_once plugin_dir_path(__FILE__) . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);
            
            // Authenticate once
            $reflection = new ReflectionClass($sap_service);
            $auth_method = $reflection->getMethod('ensure_authenticated');
            $auth_method->setAccessible(true);
            
            if (!$auth_method->invoke($sap_service)) {
                WP_CLI::error('Failed to authenticate with SAP B1');
                return;
            }
            
            $request_method = $reflection->getMethod('make_request');
            $request_method->setAccessible(true);
            
            // Run benchmark queries (simple count query, no filtering)
            $times = array();
            
            for ($i = 1; $i <= $iterations; $i++) {
                $start = microtime(true);
                
                // Simple query to measure baseline latency
                $response = $request_method->invoke(
                    $sap_service, 
                    'GET', 
                    '/BusinessPartners?$top=1&$select=CardCode,CardName'
                );
                
                $elapsed = round((microtime(true) - $start) * 1000, 2);
                $times[] = $elapsed;
                
                $status = is_wp_error($response) ? 'ERROR' : 'OK';
                WP_CLI::line("  Iteration {$i}: {$elapsed}ms ({$status})");
            }
            
            // Calculate statistics
            $avg = round(array_sum($times) / count($times), 2);
            $min = round(min($times), 2);
            $max = round(max($times), 2);
            
            WP_CLI::line('');
            WP_CLI::line(str_repeat('-', 40));
            WP_CLI::line('');
            WP_CLI::line('Results:');
            WP_CLI::line("  Average: {$avg}ms");
            WP_CLI::line("  Min:     {$min}ms");
            WP_CLI::line("  Max:     {$max}ms");
            WP_CLI::line('');
            
            // Recommendations
            if ($avg > 2000) {
                WP_CLI::warning("Average response time > 2s. Strongly recommend async processing via cron.");
            } elseif ($avg > 500) {
                WP_CLI::line("Average response time is moderate. Consider async processing for better UX.");
            } else {
                WP_CLI::success("Response times are good for synchronous processing.");
            }
            
            WP_CLI::line('');
            
        } catch (Exception $e) {
            WP_CLI::error('Error: ' . $e->getMessage());
        }
    }
}

WP_CLI::add_command('shift8-gravitysap-bp-lookup', 'Shift8_GravitySAP_BP_Lookup_Command');

/**
 * Streamlined SAP B1 query commands for manual verification and debugging.
 *
 * Provides direct querying of SAP B1 records by their unique identifiers.
 * Designed for rapid manual testing: submit a form, grab the CardCode from
 * the GF entry meta, then verify the full record in SAP B1.
 *
 * @since 1.4.9
 */
class Shift8_GravitySAP_SAP_Query_Command {

    /**
     * Look up a Business Partner in SAP B1 by CardCode.
     *
     * ## OPTIONS
     *
     * <card_code>
     * : The SAP B1 CardCode (e.g., E00115)
     *
     * [--contacts]
     * : Also display Contact Persons
     *
     * [--quotations]
     * : Also display linked Sales Quotations
     *
     * [--json]
     * : Output raw JSON response
     *
     * ## EXAMPLES
     *
     *     wp sap-query bp E00115
     *     wp sap-query bp E00115 --contacts --quotations
     *     wp sap-query bp E00115 --json
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function bp($args, $assoc_args) {
        $card_code = isset($args[0]) ? sanitize_text_field($args[0]) : '';
        $show_contacts = isset($assoc_args['contacts']);
        $show_quotations = isset($assoc_args['quotations']);
        $output_json = isset($assoc_args['json']);

        if (empty($card_code)) {
            WP_CLI::error('Please provide a CardCode: wp sap-query bp E00115');
        }

        $sap_service = $this->get_sap_service();
        if (!$sap_service) {
            return;
        }

        $reflection = new ReflectionClass($sap_service);
        $method = $reflection->getMethod('make_request');
        $method->setAccessible(true);

        WP_CLI::line('');
        WP_CLI::line('Querying SAP B1 for Business Partner: ' . $card_code);
        WP_CLI::line(str_repeat('-', 60));

        $response = $method->invoke($sap_service, 'GET', "/BusinessPartners('" . $card_code . "')");
        $data = $this->parse_response($response);

        if (!$data) {
            WP_CLI::error("Business Partner '{$card_code}' not found in SAP B1");
            return;
        }

        if ($output_json) {
            WP_CLI::line(wp_json_encode($data, JSON_PRETTY_PRINT));
            return;
        }

        WP_CLI::line('');
        WP_CLI::line('  CardCode:     ' . ($data['CardCode'] ?? 'N/A'));
        WP_CLI::line('  CardName:     ' . ($data['CardName'] ?? 'N/A'));
        WP_CLI::line('  CardType:     ' . ($data['CardType'] ?? 'N/A'));
        WP_CLI::line('  Series:       ' . ($data['Series'] ?? 'N/A'));
        WP_CLI::line('  GroupCode:    ' . ($data['GroupCode'] ?? 'N/A'));
        WP_CLI::line('  Email:        ' . ($data['EmailAddress'] ?? 'N/A'));
        WP_CLI::line('  Phone:        ' . ($data['Phone1'] ?? 'N/A'));
        WP_CLI::line('  Website:      ' . ($data['Website'] ?? 'N/A'));
        WP_CLI::line('  Currency:     ' . ($data['Currency'] ?? 'N/A'));
        WP_CLI::line('  PriceList:    ' . ($data['PriceListNum'] ?? 'N/A'));
        WP_CLI::line('  FederalTaxID: ' . ($data['FederalTaxID'] ?? 'N/A'));
        WP_CLI::line('  CreateDate:   ' . ($data['CreateDate'] ?? 'N/A'));
        WP_CLI::line('  Valid:        ' . ($data['Valid'] ?? 'N/A'));

        if (!empty($data['BPAddresses']) && is_array($data['BPAddresses'])) {
            WP_CLI::line('');
            WP_CLI::line('  Addresses:');
            foreach ($data['BPAddresses'] as $addr) {
                $type = ($addr['AddressType'] ?? '') === 'bo_BillTo' ? 'Bill-To' : 'Ship-To';
                WP_CLI::line("    [{$type}] {$addr['Street']}, {$addr['City']}, {$addr['State']} {$addr['ZipCode']}, {$addr['Country']}");
            }
        }

        if ($show_contacts && !empty($data['ContactEmployees']) && is_array($data['ContactEmployees'])) {
            WP_CLI::line('');
            WP_CLI::line('  Contact Persons:');
            foreach ($data['ContactEmployees'] as $contact) {
                $code = $contact['InternalCode'] ?? 'N/A';
                $name = $contact['Name'] ?? 'N/A';
                $email = $contact['E_Mail'] ?? '';
                $phone = $contact['Phone1'] ?? '';
                WP_CLI::line("    [{$code}] {$name} | {$email} | {$phone}");
            }
        }

        if ($show_quotations) {
            WP_CLI::line('');
            WP_CLI::line('  Sales Quotations:');
            $q_response = $method->invoke(
                $sap_service,
                'GET',
                "/Quotations?\$filter=CardCode eq '{$card_code}'&\$select=DocEntry,DocNum,DocDate,DocTotal,DocCurrency,DocumentStatus&\$orderby=DocEntry desc&\$top=20"
            );
            $q_data = $this->parse_response($q_response);

            if ($q_data && !empty($q_data['value'])) {
                foreach ($q_data['value'] as $q) {
                    $status = ($q['DocumentStatus'] ?? '') === 'bost_Open' ? 'Open' : ($q['DocumentStatus'] ?? 'N/A');
                    WP_CLI::line(sprintf(
                        '    DocNum: %-8s | DocEntry: %-8s | Date: %s | Total: %s %s | Status: %s',
                        $q['DocNum'] ?? 'N/A',
                        $q['DocEntry'] ?? 'N/A',
                        $q['DocDate'] ?? 'N/A',
                        $q['DocTotal'] ?? '0',
                        $q['DocCurrency'] ?? '',
                        $status
                    ));
                }
            } else {
                WP_CLI::line('    (none)');
            }
        }

        WP_CLI::line('');
    }

    /**
     * Look up a Sales Quotation in SAP B1 by DocEntry.
     *
     * ## OPTIONS
     *
     * <doc_entry>
     * : The SAP B1 DocEntry (numeric ID)
     *
     * [--json]
     * : Output raw JSON response
     *
     * ## EXAMPLES
     *
     *     wp sap-query quotation 285
     *     wp sap-query quotation 285 --json
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function quotation($args, $assoc_args) {
        $doc_entry = isset($args[0]) ? intval($args[0]) : 0;
        $output_json = isset($assoc_args['json']);

        if (empty($doc_entry)) {
            WP_CLI::error('Please provide a DocEntry: wp sap-query quotation 285');
        }

        $sap_service = $this->get_sap_service();
        if (!$sap_service) {
            return;
        }

        $reflection = new ReflectionClass($sap_service);
        $method = $reflection->getMethod('make_request');
        $method->setAccessible(true);

        WP_CLI::line('');
        WP_CLI::line('Querying SAP B1 for Sales Quotation DocEntry: ' . $doc_entry);
        WP_CLI::line(str_repeat('-', 60));

        $response = $method->invoke($sap_service, 'GET', '/Quotations(' . $doc_entry . ')');
        $data = $this->parse_response($response);

        if (!$data) {
            WP_CLI::error("Sales Quotation DocEntry '{$doc_entry}' not found in SAP B1");
            return;
        }

        if ($output_json) {
            WP_CLI::line(wp_json_encode($data, JSON_PRETTY_PRINT));
            return;
        }

        WP_CLI::line('');
        WP_CLI::line('  DocEntry:           ' . ($data['DocEntry'] ?? 'N/A'));
        WP_CLI::line('  DocNum:             ' . ($data['DocNum'] ?? 'N/A'));
        WP_CLI::line('  CardCode:           ' . ($data['CardCode'] ?? 'N/A'));
        WP_CLI::line('  CardName:           ' . ($data['CardName'] ?? 'N/A'));
        WP_CLI::line('  ContactPersonCode:  ' . ($data['ContactPersonCode'] ?? 'N/A'));
        WP_CLI::line('  DocDate:            ' . ($data['DocDate'] ?? 'N/A'));
        WP_CLI::line('  DocDueDate:         ' . ($data['DocDueDate'] ?? 'N/A'));
        WP_CLI::line('  DocTotal:           ' . ($data['DocTotal'] ?? '0') . ' ' . ($data['DocCurrency'] ?? ''));
        WP_CLI::line('  DocumentStatus:     ' . ($data['DocumentStatus'] ?? 'N/A'));
        WP_CLI::line('  Comments:           ' . ($data['Comments'] ?? 'N/A'));

        if (!empty($data['DocumentLines']) && is_array($data['DocumentLines'])) {
            WP_CLI::line('');
            WP_CLI::line('  Line Items (' . count($data['DocumentLines']) . '):');
            foreach ($data['DocumentLines'] as $i => $line) {
                $num = $i + 1;
                WP_CLI::line("    Line {$num}:");
                WP_CLI::line('      ItemCode:    ' . ($line['ItemCode'] ?? 'N/A'));
                WP_CLI::line('      Description: ' . ($line['ItemDescription'] ?? 'N/A'));
                WP_CLI::line('      Quantity:    ' . ($line['Quantity'] ?? 'N/A'));
                WP_CLI::line('      UnitPrice:   ' . ($line['UnitPrice'] ?? 'N/A'));
                WP_CLI::line('      LineTotal:   ' . ($line['LineTotal'] ?? 'N/A'));
                if (!empty($line['WarehouseCode'])) {
                    WP_CLI::line('      Warehouse:   ' . $line['WarehouseCode']);
                }
            }
        }

        WP_CLI::line('');
    }

    /**
     * Look up a Gravity Forms entry and its linked SAP B1 records.
     *
     * Reads the SAP identifiers stored in entry meta and queries SAP B1
     * to verify the records exist and display their details.
     *
     * ## OPTIONS
     *
     * [<entry_id>]
     * : The Gravity Forms entry ID. Omit and use --form_id to get the latest entry.
     *
     * [--form_id=<form_id>]
     * : Get the latest entry for this form ID (instead of specifying entry_id)
     *
     * [--verify]
     * : Also query SAP B1 to verify the linked records exist
     *
     * [--json]
     * : Output raw JSON of all SAP meta
     *
     * ## EXAMPLES
     *
     *     wp sap-query entry 132
     *     wp sap-query entry 132 --verify
     *     wp sap-query entry --form_id=3 --verify
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function entry($args, $assoc_args) {
        $entry_id = isset($args[0]) ? intval($args[0]) : 0;
        $form_id = isset($assoc_args['form_id']) ? absint($assoc_args['form_id']) : 0;
        $verify = isset($assoc_args['verify']);
        $output_json = isset($assoc_args['json']);

        if (empty($entry_id) && !empty($form_id)) {
            $search = GFAPI::get_entries($form_id, array('status' => 'active'), array('key' => 'date_created', 'direction' => 'DESC'), array('offset' => 0, 'page_size' => 1));
            if (empty($search)) {
                WP_CLI::error("No entries found for form #{$form_id}");
                return;
            }
            $entry_id = intval($search[0]['id']);
            WP_CLI::line("Using latest entry #{$entry_id} from form #{$form_id}");
        }

        if (empty($entry_id)) {
            WP_CLI::error('Provide an entry ID or --form_id: wp sap-query entry 132 OR wp sap-query entry --form_id=3');
        }

        $entry = GFAPI::get_entry($entry_id);
        if (is_wp_error($entry)) {
            WP_CLI::error("Entry #{$entry_id} not found");
            return;
        }

        $meta_keys = array(
            'sap_b1_status',
            'sap_b1_cardcode',
            'sap_b1_bp_matched',
            'sap_b1_contact_internal_code',
            'sap_b1_contact_name',
            'sap_b1_quotation_docentry',
            'sap_b1_quotation_docnum',
            'sap_b1_error',
        );

        $meta = array();
        foreach ($meta_keys as $key) {
            $val = gform_get_meta($entry_id, $key);
            $meta[$key] = $val ? $val : '';
        }

        if ($output_json) {
            WP_CLI::line(wp_json_encode($meta, JSON_PRETTY_PRINT));
            return;
        }

        WP_CLI::line('');
        WP_CLI::line('Gravity Forms Entry #' . $entry_id . ' - SAP B1 Integration Data');
        WP_CLI::line(str_repeat('-', 60));
        WP_CLI::line('');
        WP_CLI::line('  Form ID:              ' . ($entry['form_id'] ?? 'N/A'));
        WP_CLI::line('  Date Created:         ' . ($entry['date_created'] ?? 'N/A'));
        WP_CLI::line('');
        WP_CLI::line('  SAP Status:           ' . ($meta['sap_b1_status'] ?: '(not set)'));
        WP_CLI::line('  CardCode:             ' . ($meta['sap_b1_cardcode'] ?: '(not set)'));
        WP_CLI::line('  BP Matched (existing):' . ($meta['sap_b1_bp_matched'] ? 'Yes' : 'No (new BP created)'));
        WP_CLI::line('  Contact Name:         ' . ($meta['sap_b1_contact_name'] ?: '(not set)'));
        WP_CLI::line('  Contact InternalCode: ' . ($meta['sap_b1_contact_internal_code'] ?: '(not set)'));
        WP_CLI::line('  Quotation DocEntry:   ' . ($meta['sap_b1_quotation_docentry'] ?: '(not set)'));
        WP_CLI::line('  Quotation DocNum:     ' . ($meta['sap_b1_quotation_docnum'] ?: '(not set)'));

        if (!empty($meta['sap_b1_error'])) {
            WP_CLI::line('  Error:                ' . $meta['sap_b1_error']);
        }

        if ($verify && !empty($meta['sap_b1_cardcode'])) {
            WP_CLI::line('');
            WP_CLI::line('Verifying against SAP B1...');
            WP_CLI::line(str_repeat('-', 60));

            $sap_service = $this->get_sap_service();
            if ($sap_service) {
                $reflection = new ReflectionClass($sap_service);
                $req = $reflection->getMethod('make_request');
                $req->setAccessible(true);

                $bp_response = $req->invoke($sap_service, 'GET', "/BusinessPartners('" . $meta['sap_b1_cardcode'] . "')");
                $bp_data = $this->parse_response($bp_response);

                if ($bp_data) {
                    WP_CLI::success('Business Partner ' . $meta['sap_b1_cardcode'] . ' exists in SAP B1');
                    WP_CLI::line('    CardName: ' . ($bp_data['CardName'] ?? 'N/A'));
                    WP_CLI::line('    Email:    ' . ($bp_data['EmailAddress'] ?? 'N/A'));

                    if (!empty($meta['sap_b1_contact_internal_code']) && !empty($bp_data['ContactEmployees'])) {
                        $found = false;
                        foreach ($bp_data['ContactEmployees'] as $c) {
                            if (isset($c['InternalCode']) && (string) $c['InternalCode'] === (string) $meta['sap_b1_contact_internal_code']) {
                                WP_CLI::success('Contact Person InternalCode ' . $meta['sap_b1_contact_internal_code'] . ' verified');
                                WP_CLI::line('      Name:  ' . ($c['Name'] ?? 'N/A'));
                                WP_CLI::line('      Email: ' . ($c['E_Mail'] ?? 'N/A'));
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            WP_CLI::warning('Contact InternalCode ' . $meta['sap_b1_contact_internal_code'] . ' not found on BP');
                        }
                    }
                } else {
                    WP_CLI::warning('Business Partner ' . $meta['sap_b1_cardcode'] . ' NOT found in SAP B1');
                }

                if (!empty($meta['sap_b1_quotation_docentry'])) {
                    $q_response = $req->invoke($sap_service, 'GET', '/Quotations(' . intval($meta['sap_b1_quotation_docentry']) . ')');
                    $q_data = $this->parse_response($q_response);

                    if ($q_data) {
                        WP_CLI::success('Quotation DocEntry ' . $meta['sap_b1_quotation_docentry'] . ' exists in SAP B1');
                        WP_CLI::line('    DocNum:   ' . ($q_data['DocNum'] ?? 'N/A'));
                        WP_CLI::line('    CardCode: ' . ($q_data['CardCode'] ?? 'N/A'));
                        WP_CLI::line('    DocTotal: ' . ($q_data['DocTotal'] ?? '0') . ' ' . ($q_data['DocCurrency'] ?? ''));
                        WP_CLI::line('    Status:   ' . ($q_data['DocumentStatus'] ?? 'N/A'));
                    } else {
                        WP_CLI::warning('Quotation DocEntry ' . $meta['sap_b1_quotation_docentry'] . ' NOT found in SAP B1');
                    }
                }
            }
        }

        WP_CLI::line('');
    }

    /**
     * Search for Business Partners by name (case-insensitive contains).
     *
     * ## OPTIONS
     *
     * <name>
     * : Partial name to search for
     *
     * [--limit=<num>]
     * : Maximum results to return (default: 20)
     *
     * ## EXAMPLES
     *
     *     wp sap-query search "Emilie Cohen"
     *     wp sap-query search "Marino" --limit=5
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function search($args, $assoc_args) {
        $name = isset($args[0]) ? sanitize_text_field($args[0]) : '';
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : 20;

        if (empty($name)) {
            WP_CLI::error('Please provide a search term: wp sap-query search "Company Name"');
        }

        $sap_service = $this->get_sap_service();
        if (!$sap_service) {
            return;
        }

        $reflection = new ReflectionClass($sap_service);
        $method = $reflection->getMethod('make_request');
        $method->setAccessible(true);

        $escaped_name = str_replace("'", "''", $name);
        $endpoint = "/BusinessPartners?\$filter=contains(CardName, '{$escaped_name}')&\$select=CardCode,CardName,CardType,EmailAddress,Phone1,Valid&\$top={$limit}&\$orderby=CardCode";

        WP_CLI::line('');
        WP_CLI::line('Searching SAP B1 for Business Partners matching: "' . $name . '"');
        WP_CLI::line(str_repeat('-', 60));

        $response = $method->invoke($sap_service, 'GET', $endpoint);
        $data = $this->parse_response($response);

        if (!$data || empty($data['value'])) {
            WP_CLI::warning('No Business Partners found matching "' . $name . '"');
            WP_CLI::line('');
            return;
        }

        WP_CLI::line('');
        WP_CLI::line(sprintf('  %-12s %-35s %-6s %-30s %s', 'CardCode', 'CardName', 'Type', 'Email', 'Valid'));
        WP_CLI::line('  ' . str_repeat('-', 100));

        foreach ($data['value'] as $bp) {
            WP_CLI::line(sprintf(
                '  %-12s %-35s %-6s %-30s %s',
                $bp['CardCode'] ?? '',
                mb_substr($bp['CardName'] ?? '', 0, 35),
                $bp['CardType'] ?? '',
                mb_substr($bp['EmailAddress'] ?? '', 0, 30),
                $bp['Valid'] ?? ''
            ));
        }

        WP_CLI::line('');
        WP_CLI::line('Found ' . count($data['value']) . ' result(s). Use `wp sap-query bp <CardCode>` for full details.');
        WP_CLI::line('');
    }

    /**
     * Initialize and return an authenticated SAP service instance.
     *
     * @return Shift8_GravitySAP_SAP_Service|null
     */
    private function get_sap_service() {
        $sap_settings = get_option('shift8_gravitysap_settings', array());

        if (empty($sap_settings['sap_endpoint']) || empty($sap_settings['sap_username']) || empty($sap_settings['sap_password'])) {
            WP_CLI::error('SAP connection settings not configured.');
            return null;
        }

        $sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);

        require_once plugin_dir_path(__FILE__) . 'includes/class-shift8-gravitysap-sap-service.php';
        $sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);

        $reflection = new ReflectionClass($sap_service);
        $auth = $reflection->getMethod('ensure_authenticated');
        $auth->setAccessible(true);

        if (!$auth->invoke($sap_service)) {
            WP_CLI::error('Failed to authenticate with SAP B1');
            return null;
        }

        return $sap_service;
    }

    /**
     * Parse a SAP Service Layer response into an array.
     *
     * @param mixed $response wp_remote_post/get response
     * @return array|null Parsed data or null on failure
     */
    private function parse_response($response) {
        if (is_wp_error($response)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data ?: null;
    }
}

WP_CLI::add_command('sap-query', 'Shift8_GravitySAP_SAP_Query_Command');
