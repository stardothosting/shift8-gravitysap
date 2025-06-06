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
error_log('SHIFT8 GRAVITYSAP: Plugin file is being loaded by WordPress');

// Plugin constants
define('SHIFT8_GRAVITYSAP_VERSION', '1.0.0');
define('SHIFT8_GRAVITYSAP_PLUGIN_FILE', __FILE__);
define('SHIFT8_GRAVITYSAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SHIFT8_GRAVITYSAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SHIFT8_GRAVITYSAP_PLUGIN_BASENAME', plugin_basename(__FILE__));

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
        error_log('Shift8 GravitySAP: Plugin init() called');
        
        // Load textdomain
        load_plugin_textdomain('shift8-gravitysap', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize admin functionality (always available)
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Add logging functionality
        add_action('init', array($this, 'init_logging'));
        
        // Debug Gravity Forms detection
        $gf_active = $this->is_gravity_forms_active();
        error_log('Shift8 GravitySAP: Gravity Forms active check: ' . ($gf_active ? 'true' : 'false'));
        error_log('Shift8 GravitySAP: GFForms class exists: ' . (class_exists('GFForms') ? 'true' : 'false'));
        
        // Load the Gravity Forms Add-On with fallback integration
        add_action('gform_loaded', array($this, 'load_addon'), 5);
        
        // Also add direct hooks as fallback (what we know works)
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
        
        // Save settings
        add_action('gform_pre_form_settings_save', array($this, 'save_form_settings'));
        
        // Process form submissions
        add_action('gform_after_submission', array($this, 'process_form_submission'), 10, 2);
        
        error_log('Shift8 GravitySAP: Direct integration hooks registered');
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
        
        error_log('Shift8 GravitySAP: load_addon() called');
        
        if (!class_exists('GFForms')) {
            error_log('Shift8 GravitySAP: GFForms class not found');
            return;
        }
        
        if (!method_exists('GFForms', 'include_addon_framework')) {
            error_log('Shift8 GravitySAP: GFForms::include_addon_framework method not found');
            return;
        }
        
        // Check if addon framework is already loaded
        if (!class_exists('GFAddOn')) {
            error_log('Shift8 GravitySAP: Including addon framework');
            GFForms::include_addon_framework();
        }
        
        if (!class_exists('GFAddOn')) {
            error_log('Shift8 GravitySAP: GFAddOn class still not available after including framework');
            return;
        }
        
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-gf-shift8-gravitysap-addon.php';
        
        if (!class_exists('GF_Shift8_GravitySAP_AddOn')) {
            error_log('Shift8 GravitySAP: GF_Shift8_GravitySAP_AddOn class not found after including file');
            return;
        }
        
        error_log('Shift8 GravitySAP: Registering addon');
        GFAddOn::register('GF_Shift8_GravitySAP_AddOn');
        
        $addon_loaded = true;
        error_log('Shift8 GravitySAP: Add-on registration complete');
    }
    
    /**
     * Initialize admin functionality
     */
    public function init_admin() {
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'admin/class-shift8-gravitysap-admin.php';
        new Shift8_GravitySAP_Admin();
    }
    
    /**
     * Initialize logging
     */
    public function init_logging() {
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-logger.php';
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
        // Create log file if it doesn't exist
        $log_file = SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'shift8-gravitysap.log';
        if (!file_exists($log_file)) {
            file_put_contents($log_file, '');
        }
        
        // Set default options
        if (!get_option('shift8_gravitysap_settings')) {
            add_option('shift8_gravitysap_settings', array(
                'sap_endpoint' => '',
                'sap_company_db' => '',
                'sap_username' => '',
                'sap_password' => '',
                'enable_logging' => true
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
        $settings = rgar($form, 'sap_integration_settings', array());
        
        echo '<h3><span><i class="fa fa-cog"></i> ' . esc_html__('SAP Business One Integration', 'shift8-gravitysap') . '</span></h3>';
        echo '<table class="form-table">';
        
        // Enable checkbox
        echo '<tr><th scope="row"><label for="sap_enabled">' . esc_html__('Enable SAP Integration', 'shift8-gravitysap') . '</label></th>';
        echo '<td><input type="checkbox" id="sap_enabled" name="sap_enabled" value="1" ' . checked(rgar($settings, 'enabled'), '1', false) . ' />';
        echo '<label for="sap_enabled">' . esc_html__('Send form submissions to SAP Business One', 'shift8-gravitysap') . '</label></td></tr>';
        
        // Feed name
        echo '<tr><th scope="row"><label for="sap_feed_name">' . esc_html__('Feed Name', 'shift8-gravitysap') . '</label></th>';
        echo '<td><input type="text" id="sap_feed_name" name="sap_feed_name" value="' . esc_attr(rgar($settings, 'feed_name')) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Enter a name to identify this SAP integration', 'shift8-gravitysap') . '</p></td></tr>';
        
        // Business Partner Type
        echo '<tr><th scope="row"><label for="sap_card_type">' . esc_html__('Business Partner Type', 'shift8-gravitysap') . '</label></th>';
        echo '<td><select id="sap_card_type" name="sap_card_type">';
        echo '<option value="cCustomer" ' . selected(rgar($settings, 'card_type'), 'cCustomer', false) . '>' . esc_html__('Customer', 'shift8-gravitysap') . '</option>';
        echo '<option value="cSupplier" ' . selected(rgar($settings, 'card_type'), 'cSupplier', false) . '>' . esc_html__('Vendor', 'shift8-gravitysap') . '</option>';
        echo '<option value="cLid" ' . selected(rgar($settings, 'card_type'), 'cLid', false) . '>' . esc_html__('Lead', 'shift8-gravitysap') . '</option>';
        echo '</select></td></tr>';
        
        // Field Mapping
        echo '<tr><th scope="row">' . esc_html__('Field Mapping', 'shift8-gravitysap') . '</th>';
        echo '<td><table class="widefat"><thead><tr><th>' . esc_html__('SAP Field', 'shift8-gravitysap') . '</th><th>' . esc_html__('Gravity Form Field', 'shift8-gravitysap') . '</th></tr></thead><tbody>';
        
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
            echo '<tr><td>' . esc_html($label) . '</td><td>';
            echo '<select name="sap_field_mapping[' . esc_attr($sap_field) . ']">';
            echo '<option value="">' . esc_html__('Select a field', 'shift8-gravitysap') . '</option>';
            
            foreach ($form['fields'] as $field) {
                if (in_array($field->type, array('text', 'email', 'phone', 'address', 'name'))) {
                    $field_mapping = rgar($settings, 'field_mapping');
                    $selected = selected(rgar($field_mapping, $sap_field), $field->id, false);
                    echo '<option value="' . esc_attr($field->id) . '" ' . $selected . '>' . esc_html(GFCommon::get_label($field)) . '</option>';
                }
            }
            echo '</select></td></tr>';
        }
        
        echo '</tbody></table></td></tr>';
        echo '</table>';
        echo '<p class="submit"><input type="submit" name="gform-settings-save" value="' . esc_attr__('Update Settings', 'shift8-gravitysap') . '" class="button-primary" /></p>';
    }

    /**
     * Save form settings
     */
    public function save_form_settings($form) {
        $settings = array(
            'enabled' => rgpost('sap_enabled'),
            'feed_name' => rgpost('sap_feed_name'),
            'card_type' => rgpost('sap_card_type'),
            'field_mapping' => rgpost('sap_field_mapping')
        );
        
        $form['sap_integration_settings'] = $settings;
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
                Shift8_GravitySAP_Logger::log_info(sprintf('Successfully created Business Partner in SAP for entry ID: %s', $entry['id']));
                
                // Add entry note
                GFFormsModel::add_note($entry['id'], 0, 'Shift8 SAP Integration', 
                    sprintf('Business Partner successfully created in SAP B1. Card Code: %s', $result['CardCode']));
            }
            
        } catch (Exception $e) {
            Shift8_GravitySAP_Logger::log_error(sprintf('Error processing SAP submission for entry ID %s: %s', $entry['id'], $e->getMessage()));
            
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
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('shift8_gravitysap_cleanup_logs');
    }
}

// Initialize plugin
error_log('Shift8 GravitySAP: Plugin file loaded, initializing...');
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Shift8 GravitySAP: Starting plugin initialization');
}
Shift8_GravitySAP::get_instance(); 