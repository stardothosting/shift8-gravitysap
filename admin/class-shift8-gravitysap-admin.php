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
            esc_html__('Shift8 Integration for Gravity Forms and SAP Business One', 'shift8-gravity-forms-sap-b1-integration'),
            esc_html__('Gravity SAP', 'shift8-gravity-forms-sap-b1-integration'),
            'manage_options',
            'shift8-gravity-forms-sap-b1-integration',
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
            <h1><?php esc_html_e('Shift8 Settings', 'shift8-gravity-forms-sap-b1-integration'); ?></h1>
            <p><?php esc_html_e('Welcome to the Shift8 settings page. Use the menu on the left to configure your Shift8 plugins.', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
            
            <div class="card">
                <h2><?php esc_html_e('Available Plugins', 'shift8-gravity-forms-sap-b1-integration'); ?></h2>
                <ul>
                    <li><strong><?php esc_html_e('Gravity SAP', 'shift8-gravity-forms-sap-b1-integration'); ?></strong> - <?php esc_html_e('SAP Business One integration for Gravity Forms', 'shift8-gravity-forms-sap-b1-integration'); ?></li>
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
        // Verify user capabilities - require administrator capability for SAP credentials
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'shift8-gravity-forms-sap-b1-integration'));
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
            'sap_debug' => '0',
            'sap_ssl_verify' => '0'
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
     * NOTE: We use a custom form handler instead of WordPress Settings API
     * to avoid conflicts with checkbox handling. Settings are saved via save_settings().
     *
     * @since 1.0.0
     */
    public function register_settings() {
        // Intentionally empty - we handle saves manually in save_settings()
    }

    /**
     * Save settings
     *
     * Handles direct POST submission with nonce verification.
     * All settings are sanitized and validated before saving.
     *
     * @since 1.0.0
     */
    private function save_settings() {
        // Verify nonce
        if (!isset($_POST['shift8_gravitysap_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['shift8_gravitysap_nonce'])), 'shift8_gravitysap_settings')) {
            wp_die(esc_html__('Security check failed. Please try again.', 'shift8-gravity-forms-sap-b1-integration'));
        }
        
        shift8_gravitysap_debug_log('Settings save initiated', array(
            'has_endpoint' => isset($_POST['sap_endpoint']),
            'has_company_db' => isset($_POST['sap_company_db']),
            'has_username' => isset($_POST['sap_username']),
            'has_password' => isset($_POST['sap_password']) && !empty($_POST['sap_password']),
            'debug_enabled' => isset($_POST['sap_debug']),
            'ssl_verify_enabled' => isset($_POST['sap_ssl_verify'])
        ));
        
        // Get existing settings first, then update only changed fields
        $settings = get_option('shift8_gravitysap_settings', array());
        
        // Update settings from form
        $settings['sap_endpoint'] = isset($_POST['sap_endpoint']) ? esc_url_raw(trim(sanitize_text_field(wp_unslash($_POST['sap_endpoint'])))) : '';
        $settings['sap_company_db'] = isset($_POST['sap_company_db']) ? sanitize_text_field(trim(sanitize_text_field(wp_unslash($_POST['sap_company_db'])))) : '';
        $settings['sap_username'] = isset($_POST['sap_username']) ? sanitize_user(trim(sanitize_text_field(wp_unslash($_POST['sap_username'])))) : '';
        $settings['sap_debug'] = isset($_POST['sap_debug']) ? '1' : '0';
        $settings['sap_ssl_verify'] = isset($_POST['sap_ssl_verify']) ? '1' : '0';
        
        // Handle password encryption
        if (!empty($_POST['sap_password'])) {
            $settings['sap_password'] = shift8_gravitysap_encrypt_password(trim(sanitize_text_field(wp_unslash($_POST['sap_password']))));
        }
        // If password is empty, keep the existing one (already in $settings from get_option)
        
        // Update settings
        update_option('shift8_gravitysap_settings', $settings);
        
        shift8_gravitysap_debug_log('Settings saved successfully', array(
            'endpoint_set' => !empty($settings['sap_endpoint']),
            'company_db_set' => !empty($settings['sap_company_db']),
            'username_set' => !empty($settings['sap_username']),
            'password_set' => !empty($settings['sap_password']),
            'debug_enabled' => $settings['sap_debug'] === '1',
            'ssl_verify_enabled' => $settings['sap_ssl_verify'] === '1'
        ));
        
        // Show success message
        add_settings_error(
            'shift8_gravitysap_settings',
            'settings_updated',
            esc_html__('Settings saved successfully!', 'shift8-gravity-forms-sap-b1-integration'),
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
                'testing' => esc_html__('Testing...', 'shift8-gravity-forms-sap-b1-integration'),
                'success' => esc_html__('Success!', 'shift8-gravity-forms-sap-b1-integration'),
                'error' => esc_html__('Error:', 'shift8-gravity-forms-sap-b1-integration'),
                'confirm_clear' => esc_html__('Are you sure you want to clear the log file?', 'shift8-gravity-forms-sap-b1-integration')
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'shift8_gravitysap_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed.', 'shift8-gravity-forms-sap-b1-integration')
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
                            esc_html__('Missing required setting: %s', 'shift8-gravity-forms-sap-b1-integration'),
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
                    'message' => esc_html__('Successfully connected to SAP Service Layer!', 'shift8-gravity-forms-sap-b1-integration')
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

} 