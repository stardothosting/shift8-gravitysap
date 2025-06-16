<?php
error_log('[Shift8 GravitySAP] Main plugin file loaded');
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
    error_log('SHIFT8 GRAVITYSAP: Plugin file is being loaded by WordPress');
}

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
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Shift8 GravitySAP: Plugin init() called');
        }
        
        // Load textdomain
        load_plugin_textdomain('shift8-gravitysap', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize admin functionality
        if (is_admin()) {
            require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'admin/class-shift8-gravitysap-admin.php';
            new Shift8_GravitySAP_Admin();
        }
        
        // Load the Gravity Forms Add-On
        add_action('gform_loaded', function() {
            error_log('[Shift8 GravitySAP] gform_loaded action fired');
            if (!class_exists('GFForms')) {
                error_log('[Shift8 GravitySAP] GFForms class not found');
                return;
            }
            if (!class_exists('GFAddOn')) {
                error_log('[Shift8 GravitySAP] GFAddOn class not found, including addon framework');
                GFForms::include_addon_framework();
            }
            $addon_path = plugin_dir_path(__FILE__) . 'includes/class-gf-shift8-gravitysap-addon.php';
            error_log('[Shift8 GravitySAP] Requiring add-on file: ' . $addon_path);
            require_once $addon_path;
            error_log('[Shift8 GravitySAP] Registering add-on: GF_Shift8_GravitySAP_AddOn');
            GFAddOn::register('GF_Shift8_GravitySAP_AddOn');
            
            // Initialize the addon
            $addon = GF_Shift8_GravitySAP_AddOn::get_instance();
            $addon->init();
        });
        
        // Check if Gravity Forms is active for warnings
        if (!$this->is_gravity_forms_active()) {
            add_action('admin_notices', array($this, 'gravity_forms_missing_notice'));
        }
    }
    
    /**
     * Check if Gravity Forms is active
     */
    private function is_gravity_forms_active() {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active('gravityforms/gravityforms.php') || class_exists('GFForms');
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
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up any scheduled tasks
        wp_clear_scheduled_hook('shift8_gravitysap_cleanup');
    }
}

// Initialize plugin
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    error_log('Shift8 GravitySAP: Plugin file loaded, initializing...');
    error_log('Shift8 GravitySAP: Starting plugin initialization');
}
Shift8_GravitySAP::get_instance();

function shift8_gravitysap_debug_log($message, $data = null) {
    // Use the same logging logic as admin/class-shift8-gravitysap-admin.php
    $settings = get_option('shift8_gravitysap_settings', array());
    if (!isset($settings['enable_logging']) || $settings['enable_logging'] !== '1') {
        return;
    }
    $log_message = '[Shift8 GravitySAP] ' . $message;
    if ($data !== null) {
        $log_message .= ' - Data: ' . print_r($data, true);
    }
    error_log($log_message);
} 