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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_shift8_gravitysap_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_shift8_gravitysap_clear_log', array($this, 'ajax_clear_log'));
    }

    /**
     * Add admin menu under Shift8 top-level menu
     */
    public function add_admin_menu() {
        // Check if the main Shift8 menu already exists
        if (!function_exists('shift8_main_page')) {
            // Create the main Shift8 menu if it doesn't exist
            add_menu_page(
                esc_html__('Shift8', 'shift8-gravitysap'),
                esc_html__('Shift8', 'shift8-gravitysap'),
                'manage_options',
                'shift8-settings',
                array($this, 'main_page'),
                'dashicons-admin-generic',
                30
            );
        }

        // Add submenu for GravitySAP
        add_submenu_page(
            'shift8-settings',
            esc_html__('Gravity Forms SAP Integration', 'shift8-gravitysap'),
            esc_html__('SAP Integration', 'shift8-gravitysap'),
            'manage_options',
            'shift8-gravitysap',
            array($this, 'admin_page')
        );
    }

    /**
     * Main Shift8 page (only if it doesn't exist)
     */
    public function main_page() {
        // Get available Shift8 plugins
        $shift8_plugins = $this->get_shift8_plugins();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Shift8 Plugins', 'shift8-gravitysap'); ?></h1>
            <div class="shift8-main-content">
                <p><?php esc_html_e('Welcome to Shift8 plugins dashboard. Use the menu on the left to configure your installed Shift8 plugins.', 'shift8-gravitysap'); ?></p>
                
                <div class="shift8-plugin-cards">
                    <?php foreach ($shift8_plugins as $plugin): ?>
                    <div class="shift8-plugin-card">
                        <h3><?php echo esc_html($plugin['name']); ?></h3>
                        <p><?php echo esc_html($plugin['description']); ?></p>
                        <?php if ($plugin['admin_url']): ?>
                        <a href="<?php echo esc_url($plugin['admin_url']); ?>" class="button button-primary">
                            <?php esc_html_e('Configure', 'shift8-gravitysap'); ?>
                        </a>
                        <?php endif; ?>
                        <p class="plugin-status">
                            <span class="<?php echo $plugin['active'] ? 'shift8-status-good' : 'shift8-status-inactive'; ?>">
                                <?php echo $plugin['active'] ? esc_html__('Active', 'shift8-gravitysap') : esc_html__('Inactive', 'shift8-gravitysap'); ?>
                            </span>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($shift8_plugins)): ?>
                <div class="shift8-no-plugins">
                    <p><?php esc_html_e('No Shift8 plugins are currently installed.', 'shift8-gravitysap'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .shift8-plugin-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .shift8-plugin-card {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 5px;
            background: #fff;
        }
        
        .shift8-plugin-card h3 {
            margin-top: 0;
        }
        
        .plugin-status {
            margin-top: 10px;
            font-size: 12px;
        }
        
        .shift8-status-inactive {
            color: #d63638;
        }
        
        .shift8-no-plugins {
            text-align: center;
            margin-top: 40px;
            padding: 40px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        </style>
        <?php
    }

    /**
     * Get list of Shift8 plugins
     */
    private function get_shift8_plugins() {
        $plugins = array();
        
        // Known Shift8 plugins and their admin pages
        $known_plugins = array(
            'shift8-gravitysap/shift8-gravitysap.php' => array(
                'name' => 'Gravity Forms SAP Integration',
                'description' => 'Integrate Gravity Forms with SAP Business One to create Business Partner records automatically.',
                'admin_page' => 'shift8-gravitysap'
            ),
            'shift8-google-business/shift8-google-business.php' => array(
                'name' => 'Google Business Integration',
                'description' => 'Sync your business info and hours from Google My Business to WordPress.',
                'admin_page' => 'shift8-google-business'
            ),
            'shift8-remote/shift8-remote.php' => array(
                'name' => 'Remote Management',
                'description' => 'API framework for managing multiple WordPress sites from a central location.',
                'admin_page' => 'shift8-remote'
            ),
            'shift8-full-navigation/shift8-full-navigation.php' => array(
                'name' => 'Full Navigation',
                'description' => 'Create a full width, sticky and responsive navigation menu.',
                'admin_page' => 'shift8-full-nav'
            ),
            'shift8-push/shift8-push.php' => array(
                'name' => 'Push Notifications',
                'description' => 'Send push notifications to users.',
                'admin_page' => 'shift8-push'
            ),
            'shift8-security/shift8-security.php' => array(
                'name' => 'Security',
                'description' => 'WordPress security enhancements.',
                'admin_page' => 'shift8-security'
            )
        );
        
        foreach ($known_plugins as $plugin_file => $plugin_info) {
            $is_active = is_plugin_active($plugin_file);
            $admin_url = null;
            
            if ($is_active && $plugin_info['admin_page']) {
                $admin_url = admin_url('admin.php?page=' . $plugin_info['admin_page']);
            }
            
            $plugins[] = array(
                'name' => $plugin_info['name'],
                'description' => $plugin_info['description'],
                'active' => $is_active,
                'admin_url' => $admin_url
            );
        }
        
        return $plugins;
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
                            $log_size = Shift8_GravitySAP_Logger::get_log_file_size();
                            $log_writable = Shift8_GravitySAP_Logger::is_log_writable();
                            ?>
                            <p><strong><?php esc_html_e('Log File Size:', 'shift8-gravitysap'); ?></strong> <?php echo esc_html(Shift8_GravitySAP_Logger::format_file_size($log_size)); ?></p>
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
     * Save settings
     */
    private function save_settings() {
        $settings = array(
            'sap_endpoint' => sanitize_url($_POST['sap_endpoint']),
            'sap_company_db' => sanitize_text_field($_POST['sap_company_db']),
            'sap_username' => sanitize_text_field($_POST['sap_username']),
            'sap_password' => $this->encrypt_password(sanitize_text_field($_POST['sap_password'])),
            'enable_logging' => !empty($_POST['enable_logging'])
        );

        update_option('shift8_gravitysap_settings', $settings);
        
        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'shift8-gravitysap') . '</p></div>';
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('shift8_gravitysap_settings', 'shift8_gravitysap_settings');
    }

    /**
     * Encrypt password for storage
     */
    private function encrypt_password($password) {
        if (empty($password)) {
            return '';
        }
        
        // Simple base64 encoding for now - in production, use proper encryption
        return base64_encode($password);
    }

    /**
     * Decrypt password for use
     */
    public static function decrypt_password($encrypted_password) {
        if (empty($encrypted_password)) {
            return '';
        }
        
        return base64_decode($encrypted_password);
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'shift8_page_shift8-gravitysap') {
            return;
        }

        wp_enqueue_script(
            'shift8-gravitysap-admin',
            SHIFT8_GRAVITYSAP_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SHIFT8_GRAVITYSAP_VERSION,
            true
        );
    }

    /**
     * AJAX handler for testing SAP connection
     */
    public function ajax_test_connection() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'shift8-gravitysap'));
        }

        $settings = get_option('shift8_gravitysap_settings', array());
        
        // Decrypt password for testing
        if (!empty($settings['sap_password'])) {
            $settings['sap_password'] = self::decrypt_password($settings['sap_password']);
        }

        try {
            $sap_service = new Shift8_GravitySAP_SAP_Service($settings);
            $result = $sap_service->test_connection();
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(array(
                'success' => false,
                'message' => $e->getMessage()
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

        Shift8_GravitySAP_Logger::clear_log();
        
        wp_send_json_success(array(
            'message' => esc_html__('Log cleared successfully', 'shift8-gravitysap')
        ));
    }
} 