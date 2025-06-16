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
     * Register settings
     */
    public function register_settings() {
        register_setting('shift8_gravitysap_settings', 'shift8_gravitysap_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['sap_endpoint'] = esc_url_raw($input['sap_endpoint']);
        $sanitized['sap_company_db'] = sanitize_text_field($input['sap_company_db']);
        $sanitized['sap_username'] = sanitize_text_field($input['sap_username']);
        $sanitized['sap_password'] = sanitize_text_field($input['sap_password']);
        $sanitized['enable_logging'] = isset($input['enable_logging']) ? '1' : '0';
        
        return $sanitized;
    }

    /**
     * Debug logging function
     */
    private function debug_log($message, $data = null) {
        // Check if debug logging is enabled
        $settings = get_option('shift8_gravitysap_settings', array());
        if (!isset($settings['enable_logging']) || $settings['enable_logging'] !== '1') {
            return;
        }

        // Format the log message
        $log_message = '[Shift8 GravitySAP] ' . $message;
        if ($data !== null) {
            $log_message .= ' - Data: ' . print_r($data, true);
        }

        // Log to WordPress debug log
        error_log($log_message);
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
            'sap_password' => ''
        ));

        // Include settings template
        include SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'admin/partials/settings-page.php';
    }

    /**
     * Enqueue admin scripts
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
     * Test SAP connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('shift8_gravitysap_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        try {
            // Get endpoint from POST data and ensure it does not end with a slash
            $endpoint = isset($_POST['endpoint']) ? trim($_POST['endpoint']) : '';
            $login_url = rtrim($endpoint, '/') . '/Login';
            $company_db = trim(sanitize_text_field($_POST['company_db']));
            $username = trim(sanitize_text_field($_POST['username']));
            $password = trim(sanitize_text_field($_POST['password']));

            // Log request details (excluding password)
            $this->debug_log('Test Connection - Request', array(
                'endpoint' => $endpoint,
                'login_url' => $login_url,
                'company_db' => $company_db,
                'username' => $username
            ));

            // Prepare login data
            $login_data = array(
                'CompanyDB' => $company_db,
                'UserName' => $username,
                'Password' => $password
            );
            $this->debug_log('Test Connection - JSON Body', $login_data);

            // Make the request to login_url
            $response = wp_remote_post($login_url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'curl/7.68.0'
                ),
                'body' => json_encode($login_data),
                'timeout' => 30,
                'sslverify' => false
            ));

            // Log the full raw response for debugging
            $this->debug_log('Test Connection - Full Raw Response', $response);

            if (is_wp_error($response)) {
                $this->debug_log('Test Connection - WP Error', $response->get_error_message());
                throw new Exception($response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $headers = wp_remote_retrieve_headers($response);

            // Log response details
            $this->debug_log('Test Connection - Response', array(
                'code' => $response_code,
                'headers' => $headers,
                'body' => $body
            ));

            if ($response_code !== 200) {
                $error_data = json_decode($body, true);
                $error = isset($error_data['error']['message']['value']) 
                    ? $error_data['error']['message']['value'] 
                    : 'HTTP ' . $response_code;
                throw new Exception($error);
            }

            $this->debug_log('Test Connection - Success', array(
                'session_id' => json_decode($body, true)['SessionId'] ?? null,
                'version' => json_decode($body, true)['Version'] ?? null
            ));

            wp_send_json_success('Connection successful');

        } catch (Exception $e) {
            $this->debug_log('Test Connection - Exception', $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX handler for clearing log
     */
    public function ajax_clear_log() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'shift8-gravitysap'));
        }

        Shift8_GravitySAP_Logger::clear_log();
        
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
        $logs = Shift8_GravitySAP_Logger::get_log_entries(50);
        wp_send_json_success(array('logs' => $logs));
    }
} 