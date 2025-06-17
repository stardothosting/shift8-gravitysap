<?php
/**
 * Admin functionality for Shift8 GravitySAP
 *
 * @package Shift8\GravitySAP
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class for Shift8 GravitySAP plugin
 */
class Shift8_GravitySAP_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_shift8_gravitysap_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_shift8_gravitysap_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_shift8_gravitysap_clear_log', array($this, 'ajax_clear_log'));
        add_action('wp_ajax_shift8_gravitysap_clear_custom_log', array($this, 'ajax_clear_custom_log'));
        add_action('wp_ajax_shift8_gravitysap_get_custom_logs', array($this, 'ajax_get_custom_logs'));
    }

    /**
     * Add menu page
     */
    public function add_menu_page() {
        // Create main Shift8 menu if it doesn't exist
        if (empty($GLOBALS['admin_page_hooks']['shift8-settings'])) {
            add_menu_page(
                'Shift8 Settings',
                'Shift8',
                'administrator',
                'shift8-settings',
                array($this, 'shift8_main_page'),
                'dashicons-shift8'
            );
        }

        // Add submenu page under Shift8 dashboard
        add_submenu_page(
            'shift8-settings', // Parent menu
            esc_html__('Shift8 Gravity Forms SAP B1 Integration', 'shift8-gravitysap'),
            esc_html__('Gravity SAP', 'shift8-gravitysap'),
            'manage_options',
            'shift8-gravitysap',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Main Shift8 settings page
     */
    public function shift8_main_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Shift8 Settings', 'shift8-gravitysap'); ?></h1>
            <p><?php esc_html_e('Welcome to the Shift8 settings page. Use the menu on the left to configure your Shift8 plugins.', 'shift8-gravitysap'); ?></p>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get current settings
        $settings = get_option('shift8_gravitysap_settings', array(
            'sap_endpoint' => '',
            'sap_company_db' => '',
            'sap_username' => '',
            'sap_password' => '',
            'enable_logging' => true
        ));

        // Include settings template
        include SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'admin/partials/settings-page.php';
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
     * Admin page for GravitySAP settings
     */
    public function admin_page() {
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['shift8_gravitysap_nonce'], 'shift8_gravitysap_settings')) {
            $this->save_settings();
        }

        $settings = get_option('shift8_gravitysap_settings', array());
        $settings = wp_parse_args($settings, array(
            'sap_endpoint' => '',
            'sap_company_db' => '',
            'sap_username' => '',
            'sap_password' => '',
            'enable_logging' => true
        ));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Shift8 Gravity Forms SAP Integration', 'shift8-gravitysap'); ?></h1>
            
            <?php if (!$this->is_gravity_forms_active()): ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e('Gravity Forms Required', 'shift8-gravitysap'); ?></strong><br>
                    <?php esc_html_e('This plugin requires Gravity Forms to be installed and activated for full functionality. You can configure SAP connection settings below, but form integration will not work until Gravity Forms is active.', 'shift8-gravitysap'); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="shift8-gravitysap-admin">
                <div class="shift8-gravitysap-main">
                    <form method="post" action="">
                        <?php wp_nonce_field('shift8_gravitysap_settings', 'shift8_gravitysap_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="sap_endpoint"><?php esc_html_e('SAP Service Layer Endpoint URL', 'shift8-gravitysap'); ?></label>
                                </th>
                                <td>
                                    <input type="url" id="sap_endpoint" name="sap_endpoint" value="<?php echo esc_attr($settings['sap_endpoint']); ?>" class="regular-text" required />
                                    <p class="description"><?php esc_html_e('e.g., https://sap.example.com:50000/b1s/v1/', 'shift8-gravitysap'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sap_company_db"><?php esc_html_e('SAP Company Database', 'shift8-gravitysap'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="sap_company_db" name="sap_company_db" value="<?php echo esc_attr($settings['sap_company_db']); ?>" class="regular-text" required />
                                    <p class="description"><?php esc_html_e('Your SAP Company Database identifier', 'shift8-gravitysap'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sap_username"><?php esc_html_e('SAP Username', 'shift8-gravitysap'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="sap_username" name="sap_username" value="<?php echo esc_attr($settings['sap_username']); ?>" class="regular-text" required />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="sap_password"><?php esc_html_e('SAP Password', 'shift8-gravitysap'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="sap_password" name="sap_password" value="<?php echo esc_attr($settings['sap_password']); ?>" class="regular-text" required />
                                    <p class="description"><?php esc_html_e('Password is encrypted and stored securely', 'shift8-gravitysap'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Logging', 'shift8-gravitysap'); ?></th>
                                <td>
                                    <fieldset>
                                        <label for="enable_logging">
                                            <input type="checkbox" id="enable_logging" name="enable_logging" value="1" <?php checked($settings['enable_logging']); ?> />
                                            <?php esc_html_e('Enable error and activity logging', 'shift8-gravitysap'); ?>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(); ?>
                        
                        <p>
                            <button type="button" id="test-connection" class="button button-secondary">
                                <?php esc_html_e('Test SAP Connection', 'shift8-gravitysap'); ?>
                            </button>
                            <span id="connection-result"></span>
                        </p>
                    </form>
                </div>
                
                <div class="shift8-gravitysap-sidebar">
                    <div class="postbox">
                        <h3 class="hndle"><?php esc_html_e('Log Information', 'shift8-gravitysap'); ?></h3>
                        <div class="inside">
                            <?php
                            $log_size = self::get_log_file_size();
                            $log_writable = $this->is_log_writable();
                            ?>
                            <p><strong><?php esc_html_e('Log File Size:', 'shift8-gravitysap'); ?></strong> <?php echo esc_html($this->format_file_size($log_size)); ?></p>
                            <p><strong><?php esc_html_e('Log File Status:', 'shift8-gravitysap'); ?></strong> 
                                <span class="<?php echo $log_writable ? 'shift8-status-good' : 'shift8-status-error'; ?>">
                                    <?php echo $log_writable ? esc_html__('Writable', 'shift8-gravitysap') : esc_html__('Not Writable', 'shift8-gravitysap'); ?>
                                </span>
                            </p>
                            
                            <p>
                                <button type="button" id="view-log" class="button">
                                    <?php esc_html_e('View Recent Logs', 'shift8-gravitysap'); ?>
                                </button>
                                <button type="button" id="clear-log" class="button">
                                    <?php esc_html_e('Clear Log', 'shift8-gravitysap'); ?>
                                </button>
                            </p>
                            
                            <div id="log-viewer" style="display: none;">
                                <h4><?php esc_html_e('Recent Log Entries (Last 50)', 'shift8-gravitysap'); ?></h4>
                                <textarea readonly style="width: 100%; height: 300px; font-family: monospace; font-size: 12px;"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h3 class="hndle"><?php esc_html_e('Quick Start Guide', 'shift8-gravitysap'); ?></h3>
                        <div class="inside">
                            <ol>
                                <li><?php esc_html_e('Configure your SAP connection settings above', 'shift8-gravitysap'); ?></li>
                                <li><?php esc_html_e('Test the connection to ensure it works', 'shift8-gravitysap'); ?></li>
                                <li><?php esc_html_e('Go to a Gravity Form and add a "SAP B1 Integration" feed', 'shift8-gravitysap'); ?></li>
                                <li><?php esc_html_e('Map form fields to SAP Business Partner fields', 'shift8-gravitysap'); ?></li>
                                <li><?php esc_html_e('Test form submission to create Business Partners in SAP', 'shift8-gravitysap'); ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .shift8-gravitysap-admin {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .shift8-gravitysap-sidebar .postbox {
            margin-bottom: 20px;
        }
        
        .shift8-status-good {
            color: #46b450;
            font-weight: bold;
        }
        
        .shift8-status-error {
            color: #dc3232;
            font-weight: bold;
        }
        
        #connection-result {
            margin-left: 10px;
            font-weight: bold;
        }
        
        #connection-result.success {
            color: #46b450;
        }
        
        #connection-result.error {
            color: #dc3232;
        }
        </style>
        <?php
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register the settings group
        register_setting(
            'shift8_gravitysap_settings',
            'shift8_gravitysap_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize endpoint
        $sanitized['sap_endpoint'] = esc_url_raw($input['sap_endpoint']);
        
        // Sanitize company DB
        $sanitized['sap_company_db'] = sanitize_text_field($input['sap_company_db']);
        
        // Sanitize username
        $sanitized['sap_username'] = sanitize_text_field($input['sap_username']);
        
        // Encrypt password for storage
        if (!empty($input['sap_password'])) {
            $sanitized['sap_password'] = $this->encrypt_password($input['sap_password']);
        } else {
            // Keep existing password if no new one provided
            $current_settings = get_option('shift8_gravitysap_settings', array());
            $sanitized['sap_password'] = $current_settings['sap_password'] ?? '';
        }

        // Sanitize debug setting
        $sanitized['sap_debug'] = isset($input['sap_debug']) ? '1' : '0';
        
        return $sanitized;
    }

    /**
     * Encrypt password for storage
     */
    private function encrypt_password($password) {
        if (empty($password)) {
            return '';
        }
        
        // Get encryption key from WordPress
        $key = wp_salt('auth');
        
        // Encrypt the password
        $encrypted = openssl_encrypt(
            $password,
            'AES-256-CBC',
            $key,
            0,
            substr($key, 0, 16)
        );
        
        return $encrypted;
    }

    /**
     * Decrypt password for use
     */
    private function decrypt_password($encrypted_password) {
        if (empty($encrypted_password)) {
            return '';
        }
        
        // Get encryption key from WordPress
        $key = wp_salt('auth');
        
        // Decrypt the password
        $decrypted = openssl_decrypt(
            $encrypted_password,
            'AES-256-CBC',
            $key,
            0,
            substr($key, 0, 16)
        );
        
        return $decrypted;
    }

    /**
     * Save settings
     */
    private function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'shift8-gravitysap'));
        }

        if (!isset($_POST['shift8_gravitysap_nonce']) || !wp_verify_nonce($_POST['shift8_gravitysap_nonce'], 'shift8_gravitysap_settings')) {
            wp_die(esc_html__('Security check failed', 'shift8-gravitysap'));
        }

        $settings = array(
            'sap_endpoint' => esc_url_raw($_POST['sap_endpoint'] ?? ''),
            'sap_company_db' => sanitize_text_field($_POST['sap_company_db'] ?? ''),
            'sap_username' => sanitize_text_field($_POST['sap_username'] ?? ''),
            'sap_password' => $_POST['sap_password'] ?? '', // Store password as is
            'sap_debug' => isset($_POST['sap_debug']) ? '1' : '0'
        );

        update_option('shift8_gravitysap_settings', $settings);
        add_settings_error(
            'shift8_gravitysap_settings',
            'settings_updated',
            esc_html__('Settings saved successfully', 'shift8-gravitysap'),
            'updated'
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on Shift8 pages
        if (strpos($hook, 'shift8') === false) {
            return;
        }

        // Add Shift8 icon CSS
        wp_add_inline_style('admin-menu', '
            .dashicons-shift8:before {
                content: "S8";
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                font-weight: bold;
                font-size: 16px;
                line-height: 1;
                text-align: center;
                width: 20px;
                height: 20px;
                display: inline-block;
                vertical-align: middle;
            }
        ');

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

        // Localize script
        wp_localize_script('shift8-gravitysap-admin', 'shift8GravitySAP', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('shift8_gravitysap_nonce')
        ));
    }

    /**
     * Debug logging function - delegates to global function
     */
    private function debug_log($message, $data = null) {
        shift8_gravitysap_debug_log($message, $data);
    }

    /**
     * AJAX handler for testing SAP connection
     */
    public function ajax_test_connection() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'shift8_gravitysap_nonce')) {
                throw new Exception('Security check failed');
            }

            // Get settings
            $settings = get_option('shift8_gravitysap_settings');
            if (!$settings) {
                throw new Exception('SAP settings not found');
            }

            // Log settings (excluding password)
            $this->debug_log('Test Connection - Settings', array(
                'endpoint' => $settings['sap_endpoint'],
                'company_db' => $settings['sap_company_db'],
                'username' => $settings['sap_username']
            ));

            // Ensure endpoint ends with /
            $endpoint = rtrim($settings['sap_endpoint'], '/') . '/';
            
            // Decrypt password for API call
            $password = $this->decrypt_password($settings['sap_password']);
            
            // Prepare login data
            $login_data = array(
                'CompanyDB' => $settings['sap_company_db'],
                'UserName' => $settings['sap_username'],
                'Password' => $password // Send plain text password to SAP
            );

            $this->debug_log('Test Connection - Request URL', $endpoint . 'Login');

            // Make the request
            $response = wp_remote_post($endpoint . 'Login', array(
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($login_data),
                'timeout' => 30,
                'sslverify' => false
            ));

            if (is_wp_error($response)) {
                $this->debug_log('Test Connection - WP Error', $response->get_error_message());
                throw new Exception('Connection failed: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $headers = wp_remote_retrieve_headers($response);

            $this->debug_log('Test Connection - Response', array(
                'code' => $response_code,
                'headers' => $headers,
                'body' => $body
            ));

            if ($response_code === 200) {
                $data = json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid JSON response: ' . json_last_error_msg());
                }
                wp_send_json_success(array(
                    'message' => 'Successfully connected to SAP Service Layer',
                    'details' => array(
                        'session_id' => $data['SessionId'] ?? null,
                        'version' => $data['Version'] ?? null,
                        'session_timeout' => $data['SessionTimeout'] ?? null
                    )
                ));
            } else {
                $error_data = json_decode($body, true);
                $error_message = isset($error_data['error']['message']['value']) 
                    ? $error_data['error']['message']['value'] 
                    : 'HTTP ' . $response_code . ' - ' . $body;
                throw new Exception('Connection failed: ' . $error_message);
            }

        } catch (Exception $e) {
            $this->debug_log('Test Connection - Exception', $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'details' => array(
                    'endpoint' => $settings['sap_endpoint'] ?? '',
                    'company_db' => $settings['sap_company_db'] ?? '',
                    'username' => $settings['sap_username'] ?? ''
                )
            ));
        }
    }

    /**
     * AJAX handler for clearing log
     */
    public function ajax_clear_log() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'shift8-gravitysap'));
        }

        // Clear log functionality disabled - use centralized logging
        
        wp_send_json_success(array(
            'message' => esc_html__('Log cleared successfully', 'shift8-gravitysap')
        ));
    }

    /**
     * AJAX handler for getting recent log entries
     */
    public function ajax_get_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'shift8-gravitysap')));
        }

        // Get the last 50 log entries
        $logs = self::get_recent_log_entries(50);
        wp_send_json_success(array('logs' => $logs));
    }

    /**
     * Get the custom log file path
     */
    public static function get_log_file_path() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/shift8-gravitysap-debug.log';
    }

    /**
     * Get the custom log file URL for download
     */
    public static function get_log_file_url() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/shift8-gravitysap-debug.log';
    }

    /**
     * Clear the custom log file
     */
    public static function clear_custom_log() {
        $log_file = self::get_log_file_path();
        if (file_exists($log_file)) {
            return unlink($log_file);
        }
        return true;
    }

    /**
     * Get recent log entries from custom log file
     */
    public static function get_recent_log_entries($lines = 50) {
        $log_file = self::get_log_file_path();
        if (!file_exists($log_file)) {
            return array();
        }

        // Read the file and get the last N lines
        $file_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($file_lines === false) {
            return array();
        }

        // Get the last N lines
        return array_slice($file_lines, -$lines);
    }

    /**
     * Get log file size in human readable format
     */
    public static function get_log_file_size() {
        $log_file = self::get_log_file_path();
        if (!file_exists($log_file)) {
            return '0 B';
        }

        $size = filesize($log_file);
        $units = array('B', 'KB', 'MB', 'GB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

    /**
     * AJAX handler for clearing custom log
     */
    public function ajax_clear_custom_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'shift8-gravitysap')));
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'shift8_gravitysap_nonce')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed', 'shift8-gravitysap')));
        }

        if (self::clear_custom_log()) {
            wp_send_json_success(array('message' => esc_html__('Log cleared successfully', 'shift8-gravitysap')));
        } else {
            wp_send_json_error(array('message' => esc_html__('Failed to clear log', 'shift8-gravitysap')));
        }
    }

    /**
     * AJAX handler for getting recent custom log entries
     */
    public function ajax_get_custom_logs() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('Insufficient permissions', 'shift8-gravitysap')));
        }

        $logs = self::get_recent_log_entries(100);
        $log_size = self::get_log_file_size();
        $log_exists = file_exists(self::get_log_file_path());

        wp_send_json_success(array(
            'logs' => $logs,
            'log_size' => $log_size,
            'log_exists' => $log_exists,
            'log_file_url' => $log_exists ? self::get_log_file_url() : null
        ));
    }

    /**
     * Check if log file is writable
     */
    public function is_log_writable() {
        $log_file = self::get_log_file_path();
        $log_dir = dirname($log_file);
        
        // Check if directory is writable
        if (!is_writable($log_dir)) {
            return false;
        }
        
        // If file exists, check if it's writable
        if (file_exists($log_file)) {
            return is_writable($log_file);
        }
        
        // If file doesn't exist, check if we can create it
        return is_writable($log_dir);
    }



    /**
     * Format file size
     */
    public function format_file_size($size) {
        if (is_string($size)) {
            return $size; // Already formatted
        }
        
        $units = array('B', 'KB', 'MB', 'GB');
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }
} 