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
