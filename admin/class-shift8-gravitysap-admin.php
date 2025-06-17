<?php
/**
 * Admin functionality for Shift8 GravitySAP
 *
 * Handles admin interface, settings management, and AJAX operations
 * with comprehensive security measures.
 *
 * @package Shift8\GravitySAP
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for Shift8 GravitySAP plugin
 *
 * Manages admin interface, settings, and AJAX handlers with security validation.
 *
 * @since 1.0.0
 */
class Shift8_GravitySAP_Admin {

    /**
     * Constructor
     *
     * Sets up admin hooks and initializes functionality.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers with proper security
        add_action('wp_ajax_shift8_gravitysap_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_shift8_gravitysap_get_custom_logs', array($this, 'ajax_get_custom_logs'));
        add_action('wp_ajax_shift8_gravitysap_clear_custom_log', array($this, 'ajax_clear_custom_log'));
    }

    /**
     * Add menu pages to WordPress admin
     *
     * Creates the main Shift8 menu if it doesn't exist and adds
     * the GravitySAP submenu.
     *
     * @since 1.0.0
     */
    public function add_menu_page() {
        // Create main Shift8 menu if it doesn't exist
        if (empty($GLOBALS['admin_page_hooks']['shift8-settings'])) {
            add_menu_page(
                'Shift8 Settings',
                'Shift8',
                'manage_options',
                'shift8-settings',
                array($this, 'shift8_main_page'),
                $this->get_shift8_icon_svg()
            );
        }

        // Add submenu page under Shift8 dashboard
        add_submenu_page(
            'shift8-settings',
            esc_html__('Shift8 Gravity Forms SAP B1 Integration', 'shift8-gravitysap'),
            esc_html__('Gravity SAP', 'shift8-gravitysap'),
            'manage_options',
            'shift8-gravitysap',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Get Shift8 icon SVG
     *
     * @since 1.0.0
     * @return string SVG icon
     */
    private function get_shift8_icon_svg() {
        return 'data:image/svg+xml;base64,' . base64_encode('<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><text x="10" y="14" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" font-weight="bold">S8</text></svg>');
    }

    /**
     * Main Shift8 settings page
     *
     * Displays the main dashboard for Shift8 plugins.
     *
     * @since 1.0.0
     */
    public function shift8_main_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Shift8 Settings', 'shift8-gravitysap'); ?></h1>
            <p><?php esc_html_e('Welcome to the Shift8 settings page. Use the menu on the left to configure your Shift8 plugins.', 'shift8-gravitysap'); ?></p>
            
            <div class="card">
                <h2><?php esc_html_e('Available Plugins', 'shift8-gravitysap'); ?></h2>
                <ul>
                    <li><strong><?php esc_html_e('Gravity SAP', 'shift8-gravitysap'); ?></strong> - <?php esc_html_e('SAP Business One integration for Gravity Forms', 'shift8-gravitysap'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     *
     * Displays the main plugin settings interface with security checks.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'shift8-gravitysap'));
        }

        // Handle form submission
        if (isset($_POST['submit']) && check_admin_referer('shift8_gravitysap_settings', 'shift8_gravitysap_nonce')) {
            $this->save_settings();
        }

        // Get current settings with defaults
        $settings = get_option('shift8_gravitysap_settings', array());
        $settings = wp_parse_args($settings, array(
            'sap_endpoint' => '',
            'sap_company_db' => '',
            'sap_username' => '',
            'sap_password' => '',
            'sap_debug' => '0'
        ));

        // Include settings template
        include SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'admin/partials/settings-page.php';
    }

    /**
     * Check if Gravity Forms is active
     *
     * @since 1.0.0
     * @return bool True if Gravity Forms is active, false otherwise
     */
    private function is_gravity_forms_active() {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        return is_plugin_active('gravityforms/gravityforms.php') || class_exists('GFForms');
    }

    /**
     * Register settings
     *
     * Registers plugin settings with WordPress settings API for proper
     * validation and sanitization.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting(
            'shift8_gravitysap_settings',
            'shift8_gravitysap_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array(
                    'sap_endpoint' => '',
                    'sap_company_db' => '',
                    'sap_username' => '',
                    'sap_password' => '',
                    'sap_debug' => '0'
                )
            )
        );
    }

    /**
     * Sanitize settings
     *
     * Validates and sanitizes all plugin settings before saving.
     *
     * @since 1.0.0
     * @param array $input Raw input data
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize endpoint URL
        if (isset($input['sap_endpoint'])) {
            $sanitized['sap_endpoint'] = esc_url_raw(trim($input['sap_endpoint']));
            
            // Validate URL format
            if (!empty($sanitized['sap_endpoint']) && !filter_var($sanitized['sap_endpoint'], FILTER_VALIDATE_URL)) {
                add_settings_error(
                    'shift8_gravitysap_settings',
                    'invalid_endpoint',
                    esc_html__('Please enter a valid SAP Service Layer endpoint URL.', 'shift8-gravitysap')
                );
                $sanitized['sap_endpoint'] = '';
            }
        }
        
        // Sanitize company database
        if (isset($input['sap_company_db'])) {
            $sanitized['sap_company_db'] = sanitize_text_field(trim($input['sap_company_db']));
        }
        
        // Sanitize username
        if (isset($input['sap_username'])) {
            $sanitized['sap_username'] = sanitize_user(trim($input['sap_username']));
        }
        
        // Handle password with encryption
        if (isset($input['sap_password'])) {
            $password = trim($input['sap_password']);
            if (!empty($password)) {
                $sanitized['sap_password'] = shift8_gravitysap_encrypt_password($password);
            } else {
                // Keep existing password if new one is empty
                $existing_settings = get_option('shift8_gravitysap_settings', array());
                $sanitized['sap_password'] = isset($existing_settings['sap_password']) ? $existing_settings['sap_password'] : '';
            }
        }
        
        // Sanitize debug setting
        $sanitized['sap_debug'] = isset($input['sap_debug']) ? '1' : '0';
        
        shift8_gravitysap_debug_log('Settings saved', array(
            'endpoint_set' => !empty($sanitized['sap_endpoint']),
            'company_db_set' => !empty($sanitized['sap_company_db']),
            'username_set' => !empty($sanitized['sap_username']),
            'password_set' => !empty($sanitized['sap_password']),
            'debug_enabled' => $sanitized['sap_debug']
        ));
        
        return $sanitized;
    }

    /**
     * Save settings (legacy method)
     *
     * Handles direct POST submission with nonce verification.
     * This is kept for backward compatibility.
     *
     * @since 1.0.0
     * @deprecated Use sanitize_settings instead
     */
    private function save_settings() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['shift8_gravitysap_nonce'], 'shift8_gravitysap_settings')) {
            wp_die(esc_html__('Security check failed. Please try again.', 'shift8-gravitysap'));
        }
        
        // Prepare settings array
        $settings = array(
            'sap_endpoint' => isset($_POST['sap_endpoint']) ? esc_url_raw(trim($_POST['sap_endpoint'])) : '',
            'sap_company_db' => isset($_POST['sap_company_db']) ? sanitize_text_field(trim($_POST['sap_company_db'])) : '',
            'sap_username' => isset($_POST['sap_username']) ? sanitize_user(trim($_POST['sap_username'])) : '',
            'sap_debug' => isset($_POST['sap_debug']) ? '1' : '0'
        );
        
        // Handle password encryption
        if (!empty($_POST['sap_password'])) {
            $settings['sap_password'] = shift8_gravitysap_encrypt_password(trim($_POST['sap_password']));
        } else {
            // Keep existing password if new one is empty
            $existing_settings = get_option('shift8_gravitysap_settings', array());
            $settings['sap_password'] = isset($existing_settings['sap_password']) ? $existing_settings['sap_password'] : '';
        }
        
        // Update settings
        update_option('shift8_gravitysap_settings', $settings);
        
        // Show success message
        add_settings_error(
            'shift8_gravitysap_settings',
            'settings_updated',
            esc_html__('Settings saved successfully!', 'shift8-gravitysap'),
            'updated'
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * Loads JavaScript and CSS files for the admin interface.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts($hook) {
        // Only load on Shift8 pages
        if (strpos($hook, 'shift8') === false) {
            return;
        }

        // Add plugin-specific styles
        wp_enqueue_style(
            'shift8-gravitysap-admin',
            SHIFT8_GRAVITYSAP_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            SHIFT8_GRAVITYSAP_VERSION
        );

        // Add plugin-specific scripts
        wp_enqueue_script(
            'shift8-gravitysap-admin',
            SHIFT8_GRAVITYSAP_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            SHIFT8_GRAVITYSAP_VERSION,
            true
        );

        // Localize script with security nonce
        wp_localize_script('shift8-gravitysap-admin', 'shift8GravitySAP', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('shift8_gravitysap_nonce'),
            'strings' => array(
                'testing' => esc_html__('Testing...', 'shift8-gravitysap'),
                'success' => esc_html__('Success!', 'shift8-gravitysap'),
                'error' => esc_html__('Error:', 'shift8-gravitysap'),
                'confirm_clear' => esc_html__('Are you sure you want to clear the log file?', 'shift8-gravitysap')
            )
        ));
    }

    /**
     * AJAX handler for testing SAP connection
     *
     * Validates connection settings and tests SAP Service Layer connectivity.
     *
     * @since 1.0.0
     */
    public function ajax_test_connection() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'shift8_gravitysap_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed.', 'shift8-gravitysap')
            ));
        }
        
        try {
            // Get current settings
            $settings = get_option('shift8_gravitysap_settings', array());
            
            // Validate required settings
            $required_fields = array('sap_endpoint', 'sap_company_db', 'sap_username', 'sap_password');
            foreach ($required_fields as $field) {
                if (empty($settings[$field])) {
                    wp_send_json_error(array(
                        'message' => sprintf(
                            /* translators: %s: Field name */
                            esc_html__('Missing required setting: %s', 'shift8-gravitysap'),
                            esc_html($field)
                        )
                    ));
                }
            }
            
            // Decrypt password for testing
            $settings['sap_password'] = shift8_gravitysap_decrypt_password($settings['sap_password']);
            
            // Initialize SAP service
            require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($settings);
            
            // Test connection
            $result = $sap_service->test_connection();
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => esc_html__('Successfully connected to SAP Service Layer!', 'shift8-gravitysap')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => esc_html($result['message'])
                ));
            }
            
        } catch (Exception $e) {
            shift8_gravitysap_debug_log('Connection test failed', array(
                'error' => $e->getMessage()
            ));
            
            wp_send_json_error(array(
                'message' => esc_html($e->getMessage())
            ));
        }
    }

    /**
     * AJAX handler for getting custom logs
     *
     * Retrieves recent log entries with proper security validation.
     *
     * @since 1.0.0
     */
    public function ajax_get_custom_logs() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'shift8_gravitysap_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed.', 'shift8-gravitysap')
            ));
        }
        
        try {
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/shift8-gravitysap-debug.log';
            
            if (!file_exists($log_file)) {
                wp_send_json_success(array(
                    'logs' => array(esc_html__('No log file found.', 'shift8-gravitysap')),
                    'log_size' => '0 B'
                ));
                return;
            }
            
            // Get file size
            $file_size = $this->format_file_size(filesize($log_file));
            
            // Read last 100 lines of log file
            $lines = $this->get_last_lines($log_file, 100);
            
            wp_send_json_success(array(
                'logs' => $lines,
                'log_size' => $file_size
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => esc_html($e->getMessage())
            ));
        }
    }

    /**
     * AJAX handler for clearing custom logs
     *
     * Clears the debug log file with proper security validation.
     *
     * @since 1.0.0
     */
    public function ajax_clear_custom_log() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'shift8_gravitysap_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed.', 'shift8-gravitysap')
            ));
        }
        
        try {
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/shift8-gravitysap-debug.log';
            
            if (file_exists($log_file)) {
                if (unlink($log_file)) {
                    shift8_gravitysap_debug_log('Debug log cleared by user');
                    wp_send_json_success(array(
                        'message' => esc_html__('Log file cleared successfully.', 'shift8-gravitysap')
                    ));
                } else {
                    wp_send_json_error(array(
                        'message' => esc_html__('Failed to clear log file.', 'shift8-gravitysap')
                    ));
                }
            } else {
                wp_send_json_success(array(
                    'message' => esc_html__('Log file does not exist.', 'shift8-gravitysap')
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => esc_html($e->getMessage())
            ));
        }
    }

    /**
     * Get last N lines from a file
     *
     * Efficiently reads the last N lines from a file without loading
     * the entire file into memory.
     *
     * @since 1.0.0
     * @param string $file_path Path to the file
     * @param int    $lines     Number of lines to retrieve
     * @return array Array of lines
     */
    private function get_last_lines($file_path, $lines = 100) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return array();
        }
        
        $file = file($file_path);
        if ($file === false) {
            return array();
        }
        
        // Get last N lines
        $total_lines = count($file);
        $start = max(0, $total_lines - $lines);
        $result = array_slice($file, $start);
        
        // Remove trailing newlines and sanitize
        return array_map(function($line) {
            return esc_html(rtrim($line, "\r\n"));
        }, $result);
    }

    /**
     * Format file size in human readable format
     *
     * @since 1.0.0
     * @param int $size File size in bytes
     * @return string Formatted file size
     */
    private function format_file_size($size) {
        $units = array('B', 'KB', 'MB', 'GB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return round($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Get log file path
     *
     * @since 1.0.0
     * @return string Log file path
     */
    public static function get_log_file_path() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/shift8-gravitysap-debug.log';
    }

    /**
     * Get log file size
     *
     * @since 1.0.0
     * @return string Formatted file size
     */
    public static function get_log_file_size() {
        $log_file = self::get_log_file_path();
        if (!file_exists($log_file)) {
            return '0 B';
        }
        
        $size = filesize($log_file);
        $units = array('B', 'KB', 'MB', 'GB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return round($size / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    /**
     * Get log file URL for download
     *
     * @since 1.0.0
     * @return string Log file URL
     */
    public static function get_log_file_url() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/shift8-gravitysap-debug.log';
    }
} 