<?php
/**
 * Plugin Name: Shift8 Gravity Forms SAP B1 Integration
 * Plugin URI: https://www.shift8web.ca
 * Description: Integrates Gravity Forms with SAP Business One using the Gravity Forms Add-On Framework to create Business Partner records.
 * Version: 1.0.0
 * Author: Shift8 Web
 * Author URI: https://www.shift8web.ca
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: shift8-gravitysap
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Network: false
 *
 * Copyright 2025 Shift8 Web (email: info@shift8web.ca)
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
        
        // Always try to load the Gravity Forms Add-On
        add_action('gform_loaded', array($this, 'load_addon'), 5);
        error_log('Shift8 GravitySAP: gform_loaded hook registered');
        
        // Also try to load it immediately if GFForms class exists
        if (class_exists('GFForms')) {
            error_log('Shift8 GravitySAP: GFForms class exists, loading addon immediately');
            $this->load_addon();
        }
        
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
     * Load Gravity Forms Add-On
     */
    public function load_addon() {
        error_log('Shift8 GravitySAP: load_addon() called');
        
        if (!method_exists('GFForms', 'include_addon_framework')) {
            error_log('Shift8 GravitySAP: GFForms::include_addon_framework method not found');
            return;
        }
        
        error_log('Shift8 GravitySAP: Including addon framework');
        GFForms::include_addon_framework();
        
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-gf-shift8-gravitysap-addon.php';
        
        error_log('Shift8 GravitySAP: Registering addon');
        GFAddOn::register('GF_Shift8_GravitySAP_AddOn');
        
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
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('shift8_gravitysap_cleanup_logs');
    }
}

// Initialize plugin
error_log('Shift8 GravitySAP: Plugin file loaded, initializing...');
Shift8_GravitySAP::get_instance(); 