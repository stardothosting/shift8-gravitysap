<?php
/**
 * Plugin Name: Shift8 Gravity Forms SAP B1 Integration
 * Plugin URI: https://www.shift8web.ca
 * Description: Integrates Gravity Forms with SAP Business One using the Gravity Forms Add-On Framework to create Business Partner records.
 * Version: 1.0.0
 * Author: Shift8 Web
 * Author URI: https://shift8web.ca
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: shift8-gravitysap
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Network: false
 *
 * Copyright 2025 Shift8 Web
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Immediate test to see if this file is being loaded
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    shift8_gravitysap_debug_log('Plugin file is being loaded by WordPress');
}

// Plugin constants
define('SHIFT8_GRAVITYSAP_VERSION', '1.0.0');
define('SHIFT8_GRAVITYSAP_PLUGIN_FILE', __FILE__);
define('SHIFT8_GRAVITYSAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SHIFT8_GRAVITYSAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SHIFT8_GRAVITYSAP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Global debug logging function
 * Checks if debug logging is enabled before logging
 */
function shift8_gravitysap_debug_log($message, $data = null) {
    // Check if debug logging is enabled
    $settings = get_option('shift8_gravitysap_settings', array());
    
    // Debug the debug setting itself (always log this)
    if ($message === 'Debug setting check') {
        error_log('[Shift8 GravitySAP Debug Check] Settings: ' . print_r($settings, true));
    }
    
    if (!isset($settings['sap_debug']) || $settings['sap_debug'] !== '1') {
        return;
    }

    // Format the log message
    $timestamp = current_time('Y-m-d H:i:s');
    $log_message = '[' . $timestamp . '] [Shift8 GravitySAP] ' . $message;
    if ($data !== null) {
        $log_message .= ' - Data: ' . print_r($data, true);
    }
    $log_message .= PHP_EOL;

    // Get WordPress uploads directory
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/shift8-gravitysap-debug.log';

    // Ensure the uploads directory exists and is writable
    if (!is_dir($upload_dir['basedir'])) {
        wp_mkdir_p($upload_dir['basedir']);
    }

    // Write to custom log file
    if (is_writable($upload_dir['basedir']) || is_writable($log_file)) {
        file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    } else {
        // Fallback to system error log if custom log file isn't writable
        error_log('[Shift8 GravitySAP] ' . $message . ($data ? ' - Data: ' . print_r($data, true) : ''));
    }
}

// Check for minimum PHP version
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Shift8 Gravity Forms SAP B1 Integration requires PHP 7.4 or higher. Please upgrade PHP.', 'shift8-gravitysap');
        echo '</p></div>';
    });
    return;
}

// Load Composer autoloader
if (file_exists(SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'vendor/autoload.php';
}

// Main plugin class
class Shift8_GravitySAP {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        shift8_gravitysap_debug_log('Plugin init() called');
        
        // Load textdomain
        load_plugin_textdomain('shift8-gravitysap', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize admin functionality (always available)
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Debug Gravity Forms detection
        $gf_active = $this->is_gravity_forms_active();
        shift8_gravitysap_debug_log('Gravity Forms active check', array(
            'gf_active' => $gf_active ? 'true' : 'false',
            'GFForms_exists' => class_exists('GFForms') ? 'true' : 'false'
        ));
        
        // Load the Gravity Forms Add-On Framework (full integration)
        // TEMPORARILY DISABLED - USING DIRECT INTEGRATION INSTEAD
        // add_action('gform_loaded', array($this, 'load_addon'), 5);
        
        // ENABLE DIRECT INTEGRATION - working approach
        add_action('init', array($this, 'init_direct_integration'), 15);
        
        // Check if Gravity Forms is active for warnings
        if (!$gf_active) {
            add_action('admin_notices', array($this, 'gravity_forms_missing_notice'));
        }
    }
    
    /**
     * Check if Gravity Forms is active
     */
    private function is_gravity_forms_active() {
        // Check if Gravity Forms plugin is active
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        return is_plugin_active('gravityforms/gravityforms.php') || class_exists('GFForms');
    }
    
    /**
     * Initialize direct Gravity Forms integration (fallback approach)
     */
    public function init_direct_integration() {
        if (!class_exists('GFForms')) {
            return;
        }
        
        // Add form settings tab
        add_filter('gform_form_settings_menu', array($this, 'add_form_settings_menu'), 10, 2);
        
        // Handle settings page  
        add_action('gform_form_settings_page_sap_integration', array($this, 'form_settings_page'));
        
        // Save settings - use the correct hook for form settings
        add_filter('gform_pre_form_settings_save', array($this, 'save_form_settings'));
        
        // Debugging hook for form save process only
        add_action('gform_form_settings_save', array($this, 'debug_form_save'), 10, 2);
        
        // Process form submissions
        add_action('gform_after_submission', array($this, 'process_form_submission'), 10, 2);
    }

    /**
     * Load Gravity Forms Add-On
     */
    public function load_addon() {
        // Prevent multiple registrations
        static $addon_loaded = false;
        if ($addon_loaded) {
            return;
        }
        
        shift8_gravitysap_debug_log('load_addon() called');
        
        if (!class_exists('GFForms')) {
            shift8_gravitysap_debug_log('GFForms class not found');
            return;
        }
        
        if (!method_exists('GFForms', 'include_addon_framework')) {
            shift8_gravitysap_debug_log('GFForms::include_addon_framework method not found');
            return;
        }
        
        // Check if addon framework is already loaded
        if (!class_exists('GFAddOn')) {
            shift8_gravitysap_debug_log('Including addon framework');
            GFForms::include_addon_framework();
        }
        
        if (!class_exists('GFAddOn')) {
            shift8_gravitysap_debug_log('GFAddOn class still not available after including framework');
            return;
        }
        
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-gf-shift8-gravitysap-addon.php';
        
        if (!class_exists('GF_Shift8_GravitySAP_AddOn')) {
            shift8_gravitysap_debug_log('GF_Shift8_GravitySAP_AddOn class not found after including file');
            return;
        }
        
        shift8_gravitysap_debug_log('Registering addon');
        GFAddOn::register('GF_Shift8_GravitySAP_AddOn');
        
        $addon_loaded = true;
        shift8_gravitysap_debug_log('Add-on registration complete');
    }
    
    /**
     * Initialize admin functionality
     */
    public function init_admin() {
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'admin/class-shift8-gravitysap-admin.php';
        new Shift8_GravitySAP_Admin();
    }
    
    /**
     * Show notice if Gravity Forms is not active
     */
    public function gravity_forms_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Shift8 Gravity Forms SAP B1 Integration requires Gravity Forms to be installed and activated.', 'shift8-gravitysap');
        echo '</p></div>';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        if (!get_option('shift8_gravitysap_settings')) {
            add_option('shift8_gravitysap_settings', array(
                'sap_endpoint' => '',
                'sap_company_db' => '',
                'sap_username' => '',
                'sap_password' => ''
            ));
        }
    }
    
    /**
     * Add SAP Integration tab to form settings
     */
    public function add_form_settings_menu($menu_items, $form_id) {
        $menu_items[] = array(
            'name' => 'sap_integration',
            'label' => esc_html__('SAP Integration', 'shift8-gravitysap'),
            'icon' => '<i class="fa fa-cog"></i>'
        );
        return $menu_items;
    }

    /**
     * Display the form settings page
     */
    public function form_settings_page() {
        $form_id = rgget('id');
        $form = GFAPI::get_form($form_id);
        
        // Check if this is a save request
        if (rgpost('gform-settings-save')) {
            shift8_gravitysap_debug_log('=== SAVE REQUEST DETECTED ===', array(
                'POST' => $_POST
            ));
            
            // Process the save
            $form = $this->save_form_settings($form);
            GFAPI::update_form($form);
            
            // Reload the form from database to get fresh settings
            $form = GFAPI::get_form($form_id);
            
            // Show success message
            GFCommon::add_message('SAP Integration settings saved successfully!');
        }
        
        // Check if this is a test request
        if (rgpost('test-sap-integration')) {
            shift8_gravitysap_debug_log('Debug setting check'); // This will always log to check settings
            shift8_gravitysap_debug_log('=== TEST INTEGRATION REQUEST ===');
            $test_result = $this->test_sap_integration($form);
            
            if ($test_result['success']) {
                GFCommon::add_message('Test successful! Business Partner created in SAP: ' . $test_result['message']);
            } else {
                GFCommon::add_error_message('Test failed: ' . $test_result['message']);
            }
        }
        
        // Get current settings (either fresh from DB after save, or existing)
        $settings = rgar($form, 'sap_integration_settings', array());
        
        // Use Gravity Forms proper page structure
        GFFormSettings::page_header();
        ?>
        
        <form method="post" id="gform-settings" action="">
            <?php wp_nonce_field('gforms_save_form', 'gforms_save_form') ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($form_id); ?>" />
            <input type="hidden" name="subview" value="sap_integration" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sap_enabled"><?php esc_html_e('Enable SAP Integration', 'shift8-gravitysap'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="sap_enabled" name="sap_enabled" value="1" <?php checked(rgar($settings, 'enabled'), '1'); ?> />
                        <label for="sap_enabled"><?php esc_html_e('Send form submissions to SAP Business One', 'shift8-gravitysap'); ?></label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="sap_feed_name"><?php esc_html_e('Feed Name', 'shift8-gravitysap'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="sap_feed_name" name="sap_feed_name" value="<?php echo esc_attr(rgar($settings, 'feed_name')); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Enter a name to identify this SAP integration', 'shift8-gravitysap'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="sap_card_type"><?php esc_html_e('Business Partner Type', 'shift8-gravitysap'); ?></label>
                    </th>
                    <td>
                        <select id="sap_card_type" name="sap_card_type">
                            <option value="cCustomer" <?php selected(rgar($settings, 'card_type'), 'cCustomer'); ?>><?php esc_html_e('Customer', 'shift8-gravitysap'); ?></option>
                            <option value="cSupplier" <?php selected(rgar($settings, 'card_type'), 'cSupplier'); ?>><?php esc_html_e('Vendor', 'shift8-gravitysap'); ?></option>
                            <option value="cLid" <?php selected(rgar($settings, 'card_type'), 'cLid'); ?>><?php esc_html_e('Lead', 'shift8-gravitysap'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Field Mapping', 'shift8-gravitysap'); ?></th>
                    <td>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('SAP Field', 'shift8-gravitysap'); ?></th>
                                    <th><?php esc_html_e('Gravity Form Field', 'shift8-gravitysap'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sap_fields = array(
                                    'CardName' => esc_html__('Business Partner Name', 'shift8-gravitysap'),
                                    'EmailAddress' => esc_html__('Email Address', 'shift8-gravitysap'),
                                    'Phone1' => esc_html__('Phone Number', 'shift8-gravitysap'),
                                    'BPAddresses.Street' => esc_html__('Street Address', 'shift8-gravitysap'),
                                    'BPAddresses.City' => esc_html__('City', 'shift8-gravitysap'),
                                    'BPAddresses.State' => esc_html__('State/Province', 'shift8-gravitysap'),
                                    'BPAddresses.ZipCode' => esc_html__('Zip/Postal Code', 'shift8-gravitysap'),
                                );
                                
                                foreach ($sap_fields as $sap_field => $label) {
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($label); ?></td>
                                        <td>
                                            <select name="sap_field_mapping[<?php echo esc_attr($sap_field); ?>]">
                                                <option value=""><?php esc_html_e('Select a field', 'shift8-gravitysap'); ?></option>
                                                <?php
                                                foreach ($form['fields'] as $field) {
                                                    if (in_array($field->type, array('text', 'email', 'phone', 'address', 'name'))) {
                                                        $field_mapping = rgar($settings, 'field_mapping');
                                                        $selected = selected(rgar($field_mapping, $sap_field), $field->id, false);
                                                        echo '<option value="' . esc_attr($field->id) . '" ' . $selected . '>' . esc_html(GFCommon::get_label($field)) . '</option>';
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="gform-settings-save" value="<?php esc_attr_e('Update Settings', 'shift8-gravitysap'); ?>" class="button-primary" />
            </p>
        </form>
        
        <?php if (!empty(rgar($settings, 'enabled')) && !empty(rgar($settings, 'field_mapping'))): ?>
        <hr style="margin: 30px 0;" />
        
        <form method="post" id="test-sap-form">
            <?php wp_nonce_field('gforms_save_form', 'gforms_save_form') ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($form_id); ?>" />
            <input type="hidden" name="subview" value="sap_integration" />
            
            <h3><?php esc_html_e('Test SAP Integration', 'shift8-gravitysap'); ?></h3>
            <p><?php esc_html_e('Send test data to SAP Business One to validate your field mapping configuration.', 'shift8-gravitysap'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Test Data', 'shift8-gravitysap'); ?></label>
                    </th>
                    <td>
                        <table class="widefat field-mapping-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('SAP Field', 'shift8-gravitysap'); ?></th>
                                    <th><?php esc_html_e('Mapped Form Field', 'shift8-gravitysap'); ?></th>
                                    <th><?php esc_html_e('Test Value', 'shift8-gravitysap'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $field_mapping = rgar($settings, 'field_mapping', array());
                                $sap_fields = array(
                                    'CardName' => esc_html__('Business Partner Name', 'shift8-gravitysap'),
                                    'EmailAddress' => esc_html__('Email Address', 'shift8-gravitysap'),
                                    'Phone1' => esc_html__('Phone Number', 'shift8-gravitysap'),
                                    'BPAddresses.Street' => esc_html__('Street Address', 'shift8-gravitysap'),
                                    'BPAddresses.City' => esc_html__('City', 'shift8-gravitysap'),
                                    'BPAddresses.State' => esc_html__('State/Province', 'shift8-gravitysap'),
                                    'BPAddresses.ZipCode' => esc_html__('Zip/Postal Code', 'shift8-gravitysap'),
                                );
                                
                                // Default test values based on field type
                                $default_test_values = array(
                                    'CardName' => 'Test Customer ' . date('Y-m-d H:i:s'),
                                    'EmailAddress' => 'test@example.com',
                                    'Phone1' => '+1-555-123-4567',
                                    'BPAddresses.Street' => '123 Test Street',
                                    'BPAddresses.City' => 'Test City',
                                    'BPAddresses.State' => 'Test State',
                                    'BPAddresses.ZipCode' => '12345',
                                );
                                
                                foreach ($sap_fields as $sap_field => $label) {
                                    $mapped_field_id = rgar($field_mapping, $sap_field);
                                    if (!empty($mapped_field_id)) {
                                        $mapped_field = GFFormsModel::get_field($form, $mapped_field_id);
                                        $field_label = $mapped_field ? GFCommon::get_label($mapped_field) : 'Field ID: ' . $mapped_field_id;
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($label); ?></td>
                                            <td><?php echo esc_html($field_label); ?></td>
                                            <td>
                                                <input type="text" 
                                                       name="test_values[<?php echo esc_attr($sap_field); ?>]" 
                                                       value="<?php echo esc_attr(rgar($default_test_values, $sap_field)); ?>" 
                                                       class="regular-text" />
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                        <p class="description">
                            <?php esc_html_e('These test values will be sent to SAP Business One using your current field mapping configuration.', 'shift8-gravitysap'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="test-sap-integration" value="<?php esc_attr_e('Test Integration', 'shift8-gravitysap'); ?>" class="button-secondary" />
                <span class="description" style="margin-left: 10px;">
                    <?php esc_html_e('This will create a test Business Partner in SAP Business One.', 'shift8-gravitysap'); ?>
                </span>
            </p>
        </form>
        <?php endif; ?>
        
        <?php
        GFFormSettings::page_footer();
    }

    /**
     * Save form settings
     */
    public function save_form_settings($form) {
        shift8_gravitysap_debug_log('=== SAVE METHOD TRIGGERED ===');
        shift8_gravitysap_debug_log('save_form_settings called - DIRECT INTEGRATION', array(
            'form_id' => $form['id'],
            'POST' => $_POST,
            'REQUEST' => $_REQUEST,
            'posted_data' => array(
                'sap_enabled' => rgpost('sap_enabled'),
                'sap_feed_name' => rgpost('sap_feed_name'),
                'sap_card_type' => rgpost('sap_card_type'),
                'sap_field_mapping' => rgpost('sap_field_mapping')
            ),
            'current_subview' => rgget('subview'),
            'is_sap_integration_save' => rgget('subview') === 'sap_integration'
        ));
        
        // Only process our settings when we're on our subview
        if (rgget('subview') !== 'sap_integration') {
            shift8_gravitysap_debug_log('Not our subview, skipping save');
            return $form;
        }
        
        $settings = array(
            'enabled' => rgpost('sap_enabled'),
            'feed_name' => rgpost('sap_feed_name'),
            'card_type' => rgpost('sap_card_type'),
            'field_mapping' => rgpost('sap_field_mapping')
        );
        
        shift8_gravitysap_debug_log('Settings prepared for save', array(
            'settings' => $settings,
            'form_before' => $form
        ));
        
        $form['sap_integration_settings'] = $settings;
        
        shift8_gravitysap_debug_log('Form updated with settings', array(
            'form_after' => $form
        ));
        
        return $form;
    }

    /**
     * Process form submission
     */
    public function process_form_submission($entry, $form) {
        $settings = rgar($form, 'sap_integration_settings');
        
        if (empty($settings['enabled'])) {
            return;
        }
        
        try {
            // Get plugin settings
            $plugin_settings = get_option('shift8_gravitysap_settings', array());
            
            if (empty($plugin_settings['sap_endpoint']) || empty($plugin_settings['sap_username']) || empty($plugin_settings['sap_password'])) {
                throw new Exception('SAP connection settings are incomplete');
            }

            // Map form fields to SAP Business Partner data
            $business_partner_data = $this->map_entry_to_business_partner($settings, $entry, $form);
            
            // Initialize SAP service
            require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($plugin_settings);
            
            // Create Business Partner in SAP
            $result = $sap_service->create_business_partner($business_partner_data);
            
            if ($result) {
                // Add entry note
                GFFormsModel::add_note($entry['id'], 0, 'Shift8 SAP Integration', 
                    sprintf('Business Partner successfully created in SAP B1. Card Code: %s', $result['CardCode']));
            }
            
        } catch (Exception $e) {
            // Add error note to entry
            GFFormsModel::add_note($entry['id'], 0, 'Shift8 SAP Integration', 
                sprintf('Error creating Business Partner in SAP B1: %s', $e->getMessage()));
        }
    }

    /**
     * Map entry data to Business Partner structure
     */
    private function map_entry_to_business_partner($settings, $entry, $form) {
        $field_mapping = rgar($settings, 'field_mapping', array());
        $card_type = rgar($settings, 'card_type', 'cCustomer');
        
        $business_partner = array('CardType' => $card_type);

        foreach ($field_mapping as $sap_field => $field_id) {
            if (empty($field_id)) {
                continue;
            }

            $field_value = rgar($entry, $field_id);
            
            if (empty($field_value)) {
                continue;
            }

            // Handle address fields specially
            if (strpos($sap_field, 'BPAddresses.') === 0) {
                $address_field = str_replace('BPAddresses.', '', $sap_field);
                
                if (!isset($business_partner['BPAddresses'])) {
                    $business_partner['BPAddresses'] = array(array('AddressType' => 'bo_BillTo'));
                }
                
                $business_partner['BPAddresses'][0][$address_field] = $field_value;
            } else {
                $business_partner[$sap_field] = $field_value;
            }
        }

        return $business_partner;
    }

    /**
     * Test SAP Integration with custom test values
     */
    public function test_sap_integration($form) {
        shift8_gravitysap_debug_log('=== TESTING SAP INTEGRATION ===');
        
        try {
            // Get plugin settings
            $plugin_settings = get_option('shift8_gravitysap_settings', array());
            
            if (empty($plugin_settings['sap_endpoint']) || empty($plugin_settings['sap_username']) || empty($plugin_settings['sap_password'])) {
                return array(
                    'success' => false,
                    'message' => 'SAP connection settings are incomplete. Please configure SAP settings first.'
                );
            }
            
            // Get form settings and test values
            $settings = rgar($form, 'sap_integration_settings', array());
            $test_values = rgpost('test_values', array());
            
            if (empty($test_values)) {
                return array(
                    'success' => false,
                    'message' => 'No test values provided.'
                );
            }
            
            shift8_gravitysap_debug_log('Test values received', $test_values);
            
            // Create business partner data from test values
            $business_partner_data = $this->map_test_values_to_business_partner($settings, $test_values);
            
            shift8_gravitysap_debug_log('Business partner data prepared', $business_partner_data);
            
            // Initialize SAP service
            require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($plugin_settings);
            
            // Create Business Partner in SAP
            $result = $sap_service->create_business_partner($business_partner_data);
            
            if ($result && isset($result['CardCode'])) {
                shift8_gravitysap_debug_log('Test successful', $result);
                return array(
                    'success' => true,
                    'message' => 'Card Code: ' . $result['CardCode']
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'SAP service did not return a valid result'
                );
            }
            
        } catch (Exception $e) {
            shift8_gravitysap_debug_log('Test failed with exception', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Map test values to Business Partner structure
     */
    private function map_test_values_to_business_partner($settings, $test_values) {
        $card_type = rgar($settings, 'card_type', 'cCustomer');
        
        $business_partner = array('CardType' => $card_type);
        
        foreach ($test_values as $sap_field => $test_value) {
            if (empty($test_value)) {
                continue;
            }
            
            // Handle address fields specially
            if (strpos($sap_field, 'BPAddresses.') === 0) {
                $address_field = str_replace('BPAddresses.', '', $sap_field);
                
                if (!isset($business_partner['BPAddresses'])) {
                    $business_partner['BPAddresses'] = array(array('AddressType' => 'bo_BillTo'));
                }
                
                $business_partner['BPAddresses'][0][$address_field] = $test_value;
            } else {
                $business_partner[$sap_field] = $test_value;
            }
        }
        
        return $business_partner;
    }

    /**
     * Debug form save process
     */
    public function debug_form_save($form, $is_new) {
        shift8_gravitysap_debug_log('=== FORM SAVE DETECTED ===', array(
            'form_id' => $form['id'],
            'is_new' => $is_new,
            'POST' => $_POST,
            'current_subview' => rgget('subview')
        ));
    }
    


    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('shift8_gravitysap_cleanup_logs');
    }
}

// Initialize plugin
shift8_gravitysap_debug_log('Plugin file loaded, initializing...');
shift8_gravitysap_debug_log('Starting plugin initialization');
Shift8_GravitySAP::get_instance(); 