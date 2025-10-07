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
        
        // Generate sample data
        WP_CLI::line('');
        WP_CLI::line('🔧 Generating sample data for mapped fields...');
        
        $sample_data = $this->generate_sample_data($form, $settings);
        
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
    private function generate_sample_data($form, $settings) {
        $field_mapping = isset($settings['field_mapping']) ? $settings['field_mapping'] : array();
        $sample_data = array();
        
        // Sample data templates
        $samples = array(
            'CardName' => 'Test User ' . rand(1000, 9999),
            'CardName_FirstName' => 'Test',
            'CardName_LastName' => 'User ' . rand(1000, 9999),
            'EmailAddress' => 'test' . rand(1000, 9999) . '@example.com',
            'Phone1' => '416-555-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'Phone2' => '416-555-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'Fax' => '416-555-' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'Website' => 'https://example-' . rand(100, 999) . '.com',
            'BPAddresses.Street' => rand(100, 999) . ' Test Street',
            'BPAddresses.City' => 'Toronto',
            'BPAddresses.State' => 'ON',
            'BPAddresses.ZipCode' => 'M' . rand(1, 9) . chr(rand(65, 90)) . ' ' . rand(1, 9) . chr(rand(65, 90)) . rand(1, 9),
            'BPAddresses.Country' => 'CA',
            'Notes' => 'Test submission from WP-CLI at ' . current_time('mysql'),
            'FederalTaxID' => rand(100000000, 999999999),
            'ContactPerson' => 'Test Contact',
        );
        
        // Map sample data to actual field IDs
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
}

WP_CLI::add_command('shift8-gravitysap', 'Shift8_GravitySAP_Test_Command');
