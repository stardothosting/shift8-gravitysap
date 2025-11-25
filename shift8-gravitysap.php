<?php
/**
 * Plugin Name: Shift8 Integration for Gravity Forms and SAP Business One
 * Plugin URI: https://github.com/stardothosting/shift8-gravitysap
 * Description: Integrates Gravity Forms with SAP Business One, automatically creating Business Partners from form submissions.
 * Version: 1.2.5
 * Author: Shift8 Web
 * Author URI: https://shift8web.ca
 * Text Domain: shift8-gravity-forms-sap-b1-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Network: false
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
define('SHIFT8_GRAVITYSAP_VERSION', '1.2.5');
define('SHIFT8_GRAVITYSAP_PLUGIN_FILE', __FILE__);
define('SHIFT8_GRAVITYSAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SHIFT8_GRAVITYSAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SHIFT8_GRAVITYSAP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Sanitize sensitive data for logging
 *
 * Recursively sanitizes array data to prevent sensitive information
 * like passwords and usernames from appearing in logs.
 *
 * @since 1.0.0
 * @param mixed $data The data to sanitize
 * @return mixed Sanitized data with sensitive fields redacted
 */
function shift8_gravitysap_sanitize_log_data($data) {
    if (is_array($data)) {
        $sanitized = array();
        foreach ($data as $key => $value) {
            $key_lower = strtolower($key);
            if (in_array($key_lower, array('password', 'sap_password', 'pass', 'pwd'), true)) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (in_array($key_lower, array('username', 'sap_username', 'user', 'login'), true)) {
                $sanitized[$key] = strlen($value) > 2 ? substr($value, 0, 2) . '***' : '***';
            } elseif (is_array($value)) {
                $sanitized[$key] = shift8_gravitysap_sanitize_log_data($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
    return $data;
}

/**
 * Global debug logging function
 *
 * Uses WordPress default error_log() for security. Requires both WP_DEBUG
 * and user setting to be enabled. Automatically sanitizes sensitive data
 * to prevent credential exposure.
 *
 * @since 1.0.0
 * @param string $message The log message
 * @param mixed  $data    Optional data to include in the log
 */
function shift8_gravitysap_debug_log($message, $data = null) {
    // SECURITY: Require WP_DEBUG to be enabled
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    // Check if user has enabled debug logging in plugin settings
    $settings = get_option('shift8_gravitysap_settings', array());
    if (!isset($settings['sap_debug']) || $settings['sap_debug'] !== '1') {
        return;
    }
    
    // Sanitize sensitive data from logs
    if ($data !== null) {
        $data = shift8_gravitysap_sanitize_log_data($data);
    }

    // Format the log message
    $log_message = '[Shift8 GravitySAP] ' . $message;
    if ($data !== null) {
        $log_message .= ' - Data: ' . wp_json_encode($data);
    }

    // Use WordPress default error_log() - logs to debug.log if WP_DEBUG_LOG is enabled
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log($log_message);
    }
}

/**
 * Encrypt password for storage
 *
 * @since 1.0.0
 * @param string $password The password to encrypt
 * @return string The encrypted password
 */
function shift8_gravitysap_encrypt_password($password) {
    if (empty($password)) {
        return '';
    }
    
    // Use WordPress salts for encryption key
    $key = wp_salt('auth');
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
    
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt password from storage
 *
 * @since 1.0.0
 * @param string $encrypted_password The encrypted password
 * @return string The decrypted password
 */
function shift8_gravitysap_decrypt_password($encrypted_password) {
    if (empty($encrypted_password)) {
        return '';
    }
    
    $key = wp_salt('auth');
    $data = base64_decode($encrypted_password);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

/**
 * Filter SSL verification setting
 *
 * @since 1.2.1
 */
add_filter('shift8_gravitysap_sslverify', function($verify) {
    $settings = get_option('shift8_gravitysap_settings', array());
    // Return user's setting, default to false (disabled) if not set for backwards compatibility
    return isset($settings['sap_ssl_verify']) && $settings['sap_ssl_verify'] === '1';
});

// Check for minimum PHP version
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
                    echo esc_html__('Shift8 Integration for Gravity Forms and SAP Business One requires PHP 7.4 or higher. Please upgrade PHP.', 'shift8-gravity-forms-sap-b1-integration');
        echo '</p></div>';
    });
    return;
}

// Composer autoloader not needed - plugin uses WordPress-style class loading

/**
 * Main plugin class
 *
 * Handles plugin initialization, Gravity Forms integration,
 * and SAP Business One communication.
 *
 * @since 1.0.0
 */
class Shift8_GravitySAP {
    
    /**
     * Plugin instance
     *
     * @since 1.0.0
     * @var Shift8_GravitySAP|null
     */
    private static $instance = null;
    
    /**
     * Get plugin instance (Singleton pattern)
     *
     * @since 1.0.0
     * @return Shift8_GravitySAP
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     *
     * Sets up plugin hooks and initialization.
     *
     * @since 1.0.0
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Form processing hooks
        add_action('gform_after_submission', array($this, 'process_form_submission'), 10, 2);
        
        // Add SAP B1 status column to entries list
        add_filter('gform_entry_list_columns', array($this, 'add_sap_status_column'), 10, 2);
        add_filter('gform_entries_field_value', array($this, 'display_sap_status_column'), 10, 4);
        add_filter('gform_get_entries', array($this, 'load_sap_entry_meta'), 10, 2);
        
        // Add retry functionality for failed submissions
        add_action('wp_ajax_retry_sap_submission', array($this, 'ajax_retry_sap_submission'));
        add_action('wp_ajax_load_itemcodes', array($this, 'ajax_load_itemcodes'));
        add_action('admin_footer', array($this, 'add_retry_button_script'));
        
        
        // Form validation hook for SAP field limits
        add_filter('gform_validation', array($this, 'validate_sap_field_limits'), 20); // Run after other validation
    }
    
    /**
     * Initialize plugin
     *
     * Loads textdomain, checks dependencies, and sets up integrations.
     *
     * @since 1.0.0
     */
    public function init() {
        shift8_gravitysap_debug_log('Plugin init() called');
        
        // WordPress automatically loads translations for plugins hosted on WordPress.org since version 4.6
        
        // Initialize admin functionality
        if (is_admin()) {
            $this->init_admin();
        }
        
        // Check if Gravity Forms is active
        $gf_active = $this->is_gravity_forms_active();
        shift8_gravitysap_debug_log('Gravity Forms active check', array(
            'gf_active' => $gf_active ? 'true' : 'false',
            'GFForms_exists' => class_exists('GFForms') ? 'true' : 'false'
        ));
        
        // Initialize Gravity Forms integration
        if ($gf_active) {
            add_action('init', array($this, 'init_gravity_forms_integration'), 15);
        } else {
            add_action('admin_notices', array($this, 'gravity_forms_missing_notice'));
        }
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
     * Initialize Gravity Forms integration
     *
     * Sets up form settings, submission processing, and related hooks.
     *
     * @since 1.0.0
     */
    public function init_gravity_forms_integration() {
        if (!class_exists('GFForms')) {
            return;
        }
        
        // Add form settings tab
        add_filter('gform_form_settings_menu', array($this, 'add_form_settings_menu'), 10, 2);
        
        // Handle settings page  
        add_action('gform_form_settings_page_sap_integration', array($this, 'form_settings_page'));
        
        // Save form settings
        add_filter('gform_pre_form_settings_save', array($this, 'save_form_settings'));
        
        // Process form submissions
        // add_action('gform_after_submission', array($this, 'process_form_submission'), 10, 2); // Moved to constructor
    }
    
    /**
     * Initialize admin functionality
     *
     * @since 1.0.0
     */
    public function init_admin() {
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'admin/class-shift8-gravitysap-admin.php';
        new Shift8_GravitySAP_Admin();
    }
    
    /**
     * Show notice if Gravity Forms is not active
     *
     * @since 1.0.0
     */
    public function gravity_forms_missing_notice() {
        echo '<div class="notice notice-error"><p>';
                    echo esc_html__('Shift8 Integration for Gravity Forms and SAP Business One requires Gravity Forms to be installed and activated.', 'shift8-gravity-forms-sap-b1-integration');
        echo '</p></div>';
    }
    
    /**
     * Plugin activation
     *
     * Sets up default options and performs initial setup.
     *
     * @since 1.0.0
     */
    public function activate() {
        // Set default options if they don't exist
        if (!get_option('shift8_gravitysap_settings')) {
            add_option('shift8_gravitysap_settings', array(
                'sap_endpoint' => '',
                'sap_company_db' => '',
                'sap_username' => '',
                'sap_password' => '',
                'sap_debug' => '0'
            ));
        }
        
        // Create uploads directory for logs if it doesn't exist
        $upload_dir = wp_upload_dir();
        if (!is_dir($upload_dir['basedir'])) {
            wp_mkdir_p($upload_dir['basedir']);
        }
        
        shift8_gravitysap_debug_log('Plugin activated');
    }
    
    /**
     * Add SAP Integration tab to form settings
     *
     * @since 1.0.0
     * @param array $menu_items Current menu items
     * @param int   $form_id    Form ID
     * @return array Modified menu items
     */
    public function add_form_settings_menu($menu_items, $form_id) {
        $menu_items[] = array(
            'name' => 'sap_integration',
            'label' => esc_html__('SAP Integration', 'shift8-gravity-forms-sap-b1-integration'),
            'icon' => 'dashicons-admin-links dashicons' // Alternatives: dashicons-database, dashicons-cloud, dashicons-networking, dashicons-plugins-checked
        );
        return $menu_items;
    }

    /**
     * Display the form settings page
     *
     * Renders the SAP integration configuration interface for individual forms.
     *
     * @since 1.0.0
     */
    public function form_settings_page() {
        // Verify user capabilities - use Gravity Forms capability
        if (!GFCommon::current_user_can_any('gravityforms_edit_forms')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'shift8-gravity-forms-sap-b1-integration'));
        }
        
        $form_id = absint(rgget('id'));
        if (!$form_id) {
            wp_die(esc_html__('Invalid form ID.', 'shift8-gravity-forms-sap-b1-integration'));
        }
        
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            wp_die(esc_html__('Form not found.', 'shift8-gravity-forms-sap-b1-integration'));
        }
        
        // Handle form submissions with proper nonce verification
        if (rgpost('gform-settings-save') && wp_verify_nonce(rgpost('gforms_save_form'), 'gforms_save_form')) {
            $form = $this->save_form_settings($form);
            GFAPI::update_form($form);
            $form = GFAPI::get_form($form_id); // Reload fresh data
            GFCommon::add_message(esc_html__('SAP Integration settings saved successfully!', 'shift8-gravity-forms-sap-b1-integration'));
        }
        
        // Handle test integration with proper nonce verification
        if (rgpost('test-sap-integration') && wp_verify_nonce(rgpost('gforms_save_form'), 'gforms_save_form')) {
            $test_result = $this->test_sap_integration($form);
            
            if ($test_result['success']) {
                // Message is already escaped in the SAP service, don't double-escape
                GFCommon::add_message(esc_html__('Test successful! Business Partner created in SAP: ', 'shift8-gravity-forms-sap-b1-integration') . $test_result['message']);
            } else {
                GFCommon::add_error_message(esc_html__('Test failed: ', 'shift8-gravity-forms-sap-b1-integration') . esc_html($test_result['message']));
            }
        }
        
        // Handle field mapping test
        if (rgpost('test-field-mapping') && wp_verify_nonce(rgpost('gforms_save_form'), 'gforms_save_form')) {
            $test_result = $this->test_field_mapping($form);
            
            if ($test_result['success']) {
                GFCommon::add_message(esc_html__('Field mapping test completed: ', 'shift8-gravity-forms-sap-b1-integration') . esc_html($test_result['message']));
            } else {
                GFCommon::add_error_message(esc_html__('Field mapping test failed: ', 'shift8-gravity-forms-sap-b1-integration') . esc_html($test_result['message']));
            }
        }
        
        // Handle debug entry data
        if (rgpost('debug-entry-data') && wp_verify_nonce(rgpost('gforms_save_form'), 'gforms_save_form')) {
            $debug_result = $this->debug_entry_data($form);
            GFCommon::add_message('<pre>' . esc_html($debug_result) . '</pre>');
        }
        
        // Handle test SAP submission
        if (rgpost('test-sap-submission') && wp_verify_nonce(rgpost('gforms_save_form'), 'gforms_save_form')) {
            $test_result = $this->test_sap_submission($form);
            if ($test_result['success']) {
                GFCommon::add_message('✅ SAP submission test successful: ' . esc_html($test_result['message']));
            } else {
                GFCommon::add_error_message('❌ SAP submission test failed: ' . esc_html($test_result['message']));
            }
        }
        
        // Handle numbering series test with proper nonce verification
        if (rgpost('test-numbering-series') && wp_verify_nonce(rgpost('gforms_save_form'), 'gforms_save_form')) {
            $test_result = $this->test_numbering_series();
            
            if ($test_result['success']) {
                GFCommon::add_message(esc_html__('Numbering series check successful: ', 'shift8-gravity-forms-sap-b1-integration') . esc_html($test_result['message']));
            } else {
                GFCommon::add_error_message(esc_html__('Numbering series check failed: ', 'shift8-gravity-forms-sap-b1-integration') . esc_html($test_result['message']));
            }
        }
        
        // Get current settings
        $settings = rgar($form, 'sap_integration_settings', array());
        
        // Render page
        $this->render_form_settings_page($form_id, $form, $settings);
    }
    
    /**
     * Render the form settings page HTML
     *
     * @since 1.0.0
     * @param int   $form_id  Form ID
     * @param array $form     Form data
     * @param array $settings Current settings
     */
    private function render_form_settings_page($form_id, $form, $settings) {
        GFFormSettings::page_header();
        ?>
        
        <form method="post" id="gform-settings" action="">
            <?php wp_nonce_field('gforms_save_form', 'gforms_save_form') ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($form_id); ?>" />
            <input type="hidden" name="subview" value="sap_integration" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sap_enabled"><?php esc_html_e('Enable SAP Integration', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="sap_enabled" name="sap_enabled" value="1" <?php checked(rgar($settings, 'enabled'), '1'); ?> />
                        <label for="sap_enabled"><?php esc_html_e('Send form submissions to SAP Business One', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="sap_feed_name"><?php esc_html_e('Feed Name', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="sap_feed_name" name="sap_feed_name" value="<?php echo esc_attr(rgar($settings, 'feed_name')); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Enter a name to identify this SAP integration', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="sap_card_type"><?php esc_html_e('Business Partner Type', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                    </th>
                    <td>
                        <select id="sap_card_type" name="sap_card_type">
                            <option value="cCustomer" <?php selected(rgar($settings, 'card_type'), 'cCustomer'); ?>><?php esc_html_e('Customer', 'shift8-gravity-forms-sap-b1-integration'); ?></option>
                            <option value="cSupplier" <?php selected(rgar($settings, 'card_type'), 'cSupplier'); ?>><?php esc_html_e('Vendor', 'shift8-gravity-forms-sap-b1-integration'); ?></option>
                            <option value="cLid" <?php selected(rgar($settings, 'card_type'), 'cLid'); ?>><?php esc_html_e('Lead', 'shift8-gravity-forms-sap-b1-integration'); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="sap_card_code_prefix"><?php esc_html_e('Business Partner Code Prefix', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                    </th>
                    <td>
                        <select id="sap_card_code_prefix" name="sap_card_code_prefix">
                            <?php
                            $current_prefix = rgar($settings, 'card_code_prefix', '');
                            $prefixes = array(
                                '' => 'Auto (Use SAP Default)',
                                'D' => 'D - Distributor',
                                'E' => 'E - EndUser',
                                'O' => 'O - OEM',
                                'M' => 'M - Manual'
                            );
                            foreach ($prefixes as $value => $label) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($value),
                                    selected($current_prefix, $value, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Choose which Business Partner code prefix to use (must match your SAP configuration).', 'shift8-gravity-forms-sap-b1-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="sap_create_quotation"><?php esc_html_e('Create Sales Quotation', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="sap_create_quotation" name="sap_create_quotation" value="1" <?php checked(rgar($settings, 'create_quotation'), '1'); ?> />
                        <label for="sap_create_quotation"><?php esc_html_e('Automatically create a Sales Quotation in SAP B1 after creating the Business Partner', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                        <p class="description">
                            <?php esc_html_e('When enabled, a Sales Quotation will be created and linked to the newly created Business Partner. Configure quotation field mapping below.', 'shift8-gravity-forms-sap-b1-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Business Partner Field Mapping', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                    <td>
                        <?php $this->render_field_mapping_table($form, $settings); ?>
                    </td>
                </tr>
                
                <tr id="sap-quotation-mapping-row" style="display: <?php echo rgar($settings, 'create_quotation') === '1' ? 'table-row' : 'none'; ?>;">
                    <th scope="row"><?php esc_html_e('Sales Quotation Field Mapping', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                    <td>
                        <?php $this->render_quotation_field_mapping_table($form, $settings); ?>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="gform-settings-save" value="<?php esc_attr_e('Update Settings', 'shift8-gravity-forms-sap-b1-integration'); ?>" class="button-primary" />
            </p>
        </form>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle quotation field mapping visibility
            jQuery('#sap_create_quotation').on('change', function() {
                if (jQuery(this).is(':checked')) {
                    jQuery('#sap-quotation-mapping-row').show();
                } else {
                    jQuery('#sap-quotation-mapping-row').hide();
                }
            });
        });
        </script>
        
        <?php if (!empty(rgar($settings, 'enabled'))): ?>
            <?php $this->render_test_sections($form_id, $settings); ?>
        <?php endif; ?>
        
        <?php
        GFFormSettings::page_footer();
    }
    
    /**
     * Get available SAP Business Partner fields for mapping
     *
     * @since 1.1.0
     * @return array Array of SAP field keys and labels
     */
    private function get_sap_fields() {
        return array(
            'CardName' => esc_html__('Business Partner Name', 'shift8-gravity-forms-sap-b1-integration'),
            'EmailAddress' => esc_html__('Email Address', 'shift8-gravity-forms-sap-b1-integration'),
            'Phone1' => esc_html__('Telephone 1', 'shift8-gravity-forms-sap-b1-integration'),
            'Phone2' => esc_html__('Telephone 2', 'shift8-gravity-forms-sap-b1-integration'),
            'Cellular' => esc_html__('Mobile Phone', 'shift8-gravity-forms-sap-b1-integration'),
            'Fax' => esc_html__('Fax Number', 'shift8-gravity-forms-sap-b1-integration'),
            'Website' => esc_html__('Website', 'shift8-gravity-forms-sap-b1-integration'),
            'BPAddresses.Street' => esc_html__('Address: Street (BPAddresses)', 'shift8-gravity-forms-sap-b1-integration'),
            'BPAddresses.City' => esc_html__('Address: City (BPAddresses)', 'shift8-gravity-forms-sap-b1-integration'),
            'BPAddresses.State' => esc_html__('Address: State/Province (BPAddresses)', 'shift8-gravity-forms-sap-b1-integration'),
            'BPAddresses.ZipCode' => esc_html__('Address: Zip/Postal Code (BPAddresses)', 'shift8-gravity-forms-sap-b1-integration'),
            'BPAddresses.Country' => esc_html__('Address: Country (BPAddresses)', 'shift8-gravity-forms-sap-b1-integration'),
            'ContactEmployees.FirstName' => esc_html__('Contact Person: First Name', 'shift8-gravity-forms-sap-b1-integration'),
            'ContactEmployees.LastName' => esc_html__('Contact Person: Last Name', 'shift8-gravity-forms-sap-b1-integration'),
            'ContactEmployees.Phone1' => esc_html__('Contact Person: Phone', 'shift8-gravity-forms-sap-b1-integration'),
            'ContactEmployees.E_Mail' => esc_html__('Contact Person: Email', 'shift8-gravity-forms-sap-b1-integration'),
            'ContactEmployees.Address' => esc_html__('Contact Person: Address', 'shift8-gravity-forms-sap-b1-integration'),
        );
    }

    /**
     * Get allowed SAP field keys for validation
     *
     * @since 1.1.0
     * @return array Array of allowed SAP field keys
     */
    private function get_allowed_sap_fields() {
        return array_keys($this->get_sap_fields());
    }

    /**
     * Get SAP Business One field length limits and validation rules
     *
     * @since 1.1.0
     * @return array Array of SAP field validation rules
     */
    private function get_sap_field_limits() {
        return array(
            'CardName' => array(
                'max_length' => 100,
                'required' => true,
                'description' => 'Business Partner Name'
            ),
            'CardName_FirstName' => array(
                'max_length' => 50,
                'required' => false,
                'description' => 'First Name (for CardName)',
                'is_composite' => true,
                'composite_parent' => 'CardName'
            ),
            'CardName_LastName' => array(
                'max_length' => 50,
                'required' => false,
                'description' => 'Last Name (for CardName)',
                'is_composite' => true,
                'composite_parent' => 'CardName'
            ),
            'EmailAddress' => array(
                'max_length' => 100,
                'required' => false,
                'format' => 'email',
                'description' => 'Email Address'
            ),
            'Phone1' => array(
                'max_length' => 20,
                'required' => false,
                'description' => 'Telephone 1'
            ),
            'Phone2' => array(
                'max_length' => 20,
                'required' => false,
                'description' => 'Telephone 2'
            ),
            'Cellular' => array(
                'max_length' => 20,
                'required' => false,
                'description' => 'Mobile Phone'
            ),
            'Fax' => array(
                'max_length' => 20,
                'required' => false,
                'description' => 'Fax Number'
            ),
            'Website' => array(
                'max_length' => 254,
                'required' => false,
                'format' => 'url',
                'description' => 'Website URL'
            ),
            'BPAddresses.Street' => array(
                'max_length' => 100,
                'required' => false,
                'description' => 'Street Address'
            ),
            'BPAddresses.City' => array(
                'max_length' => 25,
                'required' => false,
                'description' => 'City'
            ),
            'BPAddresses.State' => array(
                'max_length' => 3,
                'required' => false,
                'description' => 'State/Province',
                'validation_message' => 'Use state codes (CA, NY, TX) - not full state names'
            ),
            'BPAddresses.ZipCode' => array(
                'max_length' => 20,
                'required' => false,
                'description' => 'Zip/Postal Code'
            ),
            'BPAddresses.Country' => array(
                'max_length' => 2,
                'required' => false,
                'description' => 'Country',
                'validation_message' => 'Use 2-letter country codes (US, CA, GB) - not full country names'
            ),
            'ContactEmployees.FirstName' => array(
                'max_length' => 50,
                'required' => false,
                'description' => 'Contact Person First Name'
            ),
            'ContactEmployees.LastName' => array(
                'max_length' => 50,
                'required' => false,
                'description' => 'Contact Person Last Name'
            ),
            'ContactEmployees.Phone1' => array(
                'max_length' => 20,
                'required' => false,
                'description' => 'Contact Person Phone'
            ),
            'ContactEmployees.E_Mail' => array(
                'max_length' => 100,
                'required' => false,
                'format' => 'email',
                'description' => 'Contact Person Email'
            ),
            'ContactEmployees.Address' => array(
                'max_length' => 254,
                'required' => false,
                'description' => 'Contact Person Address'
            ),
        );
    }
    
    /**
     * Get available SAP Sales Quotation fields for mapping
     *
     * @since 1.2.2
     * @return array Array of SAP quotation field keys and labels
     */
    private function get_sap_quotation_fields() {
        $fields = array(
            'Comments' => esc_html__('Comments/Notes', 'shift8-gravity-forms-sap-b1-integration'),
        );
        
        // Add 20 item slots (enough for most forms)
        // These are collected dynamically - only items with ItemCode will be added to the quotation
        for ($i = 1; $i <= 20; $i++) {
            $fields["DocumentLines.{$i}.ItemCode"] = sprintf(esc_html__('Item Code #%d', 'shift8-gravity-forms-sap-b1-integration'), $i);
            $fields["DocumentLines.{$i}.ItemDescription"] = sprintf(esc_html__('Description #%d', 'shift8-gravity-forms-sap-b1-integration'), $i);
            $fields["DocumentLines.{$i}.Quantity"] = sprintf(esc_html__('Quantity #%d', 'shift8-gravity-forms-sap-b1-integration'), $i);
            $fields["DocumentLines.{$i}.UnitPrice"] = sprintf(esc_html__('Unit Price #%d', 'shift8-gravity-forms-sap-b1-integration'), $i);
            $fields["DocumentLines.{$i}.DiscountPercent"] = sprintf(esc_html__('Discount %% #%d', 'shift8-gravity-forms-sap-b1-integration'), $i);
            $fields["DocumentLines.{$i}.TaxCode"] = sprintf(esc_html__('Tax Code #%d', 'shift8-gravity-forms-sap-b1-integration'), $i);
        }
        
        return $fields;
    }
    
    /**
     * Get SAP Sales Quotation field limits and validation rules
     *
     * @since 1.2.2
     * @return array Array of SAP quotation field validation rules
     */
    private function get_sap_quotation_field_limits() {
        $limits = array(
            'Comments' => array(
                'max_length' => 254,
                'required' => false,
                'description' => 'Comments or notes for the quotation'
            ),
        );
        
        // Add limits for each item field (all optional - only items with ItemCode are processed)
        $field_templates = array(
            'ItemCode' => array(
                'max_length' => 20,
                'required' => false,
                'description' => 'SAP Item Code (if provided, item will be added to quotation)'
            ),
            'ItemDescription' => array(
                'max_length' => 100,
                'required' => false,
                'description' => 'Description of the item'
            ),
            'Quantity' => array(
                'required' => false,
                'format' => 'number',
                'description' => 'Quantity (defaults to 1 if not provided)'
            ),
            'UnitPrice' => array(
                'required' => false,
                'format' => 'number',
                'description' => 'Unit price (optional)'
            ),
            'DiscountPercent' => array(
                'required' => false,
                'format' => 'number',
                'description' => 'Discount percentage (0-100)'
            ),
            'TaxCode' => array(
                'max_length' => 8,
                'required' => false,
                'description' => 'Tax code (must exist in SAP)'
            ),
        );
        
        // Apply templates to all 20 line items
        for ($i = 1; $i <= 20; $i++) {
            foreach ($field_templates as $field_name => $field_limits) {
                $limits["DocumentLines.{$i}.{$field_name}"] = $field_limits;
            }
        }
        
        return $limits;
    }
    
    /**
     * Get available SAP ItemCodes for quotation mapping
     *
     * @since 1.2.2
     * @return array Array of ItemCode => ItemName pairs
     */
    private function get_sap_item_codes() {
        // Check for manual refresh request
        $force_refresh = isset($_GET['refresh_itemcodes']) && $_GET['refresh_itemcodes'] === '1';
        
        // Get persistent ItemCode storage
        $option_key = 'shift8_gravitysap_item_codes_data';
        $stored_data = get_option($option_key, array());
        
        // Check if we have valid cached ItemCodes (unless forcing refresh)
        if (!$force_refresh && !empty($stored_data['items']) && is_array($stored_data['items'])) {
            return $stored_data['items'];
        }
        
        try {
            // Get SAP settings
            $sap_settings = get_option('shift8_gravitysap_settings', array());
            
            if (empty($sap_settings['sap_endpoint']) || empty($sap_settings['sap_company_db'])) {
                return !empty($stored_data['items']) ? $stored_data['items'] : array();
            }
            
            // Decrypt password
            $sap_settings['sap_password'] = shift8_gravitysap_decrypt_password($sap_settings['sap_password']);
            
            // Create SAP service
            require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($sap_settings);
            
            // CRITICAL: Ensure authentication before making request
            $reflection = new ReflectionClass($sap_service);
            $auth_method = $reflection->getMethod('ensure_authenticated');
            $auth_method->setAccessible(true);
            
            if (!$auth_method->invoke($sap_service)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    shift8_gravitysap_debug_log('ItemCode loading failed: Authentication failed');
                }
                // Return existing cached data if authentication fails
                return !empty($stored_data['items']) ? $stored_data['items'] : array();
            }
            
            // Use reflection to access private make_request method
            $method = $reflection->getMethod('make_request');
            $method->setAccessible(true);
            
            // SAP B1 has a server-side pagination limit (typically 20 items per request)
            // We need to paginate through all items using $skip
            $items = array();
            $page_size = 20; // SAP B1's default page size
            $skip = 0;
            $total_fetched = 0;
            $max_items = 3000; // Safety limit to prevent infinite loops
            
            shift8_gravitysap_debug_log('Starting paginated ItemCode fetch', array('page_size' => $page_size));
            
            while ($total_fetched < $max_items) {
                // Query for items with pagination
                $query = "/Items?\$top={$page_size}&\$skip={$skip}&\$select=ItemCode,ItemName&\$filter=Valid eq 'Y'&\$orderby=ItemCode";
                $response = $method->invoke($sap_service, 'GET', $query);
                
                if (is_wp_error($response)) {
                    shift8_gravitysap_debug_log('ItemCode fetch failed at skip=' . $skip, array('error' => $response->get_error_message()));
                    break;
                }
                
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code !== 200) {
                    shift8_gravitysap_debug_log('ItemCode fetch failed with status ' . $response_code . ' at skip=' . $skip);
                    break;
                }
                
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                if (!isset($data['value']) || !is_array($data['value']) || empty($data['value'])) {
                    // No more items to fetch
                    shift8_gravitysap_debug_log('No more items at skip=' . $skip);
                    break;
                }
                
                // Add items from this page
                $page_count = 0;
                foreach ($data['value'] as $item) {
                    if (!empty($item['ItemCode'])) {
                        $items[$item['ItemCode']] = $item['ItemName'] ?? $item['ItemCode'];
                        $page_count++;
                    }
                }
                
                $total_fetched += $page_count;
                shift8_gravitysap_debug_log("Fetched page at skip={$skip}", array('items_in_page' => $page_count, 'total_so_far' => $total_fetched));
                
                // If we got fewer items than page_size, we've reached the end
                if ($page_count < $page_size) {
                    shift8_gravitysap_debug_log('Reached end of items (partial page)', array('final_count' => $total_fetched));
                    break;
                }
                
                // Move to next page
                $skip += $page_size;
            }
            
            // Store persistently with metadata
            $new_data = array(
                'items' => $items,
                'last_updated' => current_time('timestamp'),
                'last_updated_formatted' => current_time('mysql'),
                'count' => count($items),
                'pages_fetched' => ceil($total_fetched / $page_size),
                'source' => 'sap_api'
            );
            
            update_option($option_key, $new_data);
            
            // Log success with detailed debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // Check if the specific test item exists
                $test_item_found = false;
                $test_item_key = '';
                foreach ($items as $code => $name) {
                    if (stripos($name, 'CS-Designer Suede Full') !== false || stripos($code, 'CS-Designer Suede Full') !== false) {
                        $test_item_found = true;
                        $test_item_key = $code;
                        break;
                    }
                }
                
                shift8_gravitysap_debug_log('ItemCodes loaded and stored persistently', array(
                    'total_count' => count($items),
                    'pages_fetched' => ceil($total_fetched / $page_size),
                    'items_per_page' => $page_size,
                    'force_refresh' => $force_refresh,
                    'storage' => 'wp_options',
                    'first_5_items' => array_slice($items, 0, 5, true),
                    'last_5_items' => array_slice($items, -5, 5, true),
                    'test_item_CS_Designer_Suede_Full' => $test_item_found ? "FOUND: {$test_item_key} = {$items[$test_item_key]}" : 'NOT FOUND in ' . count($items) . ' items'
                ));
            }
            
            return $items;
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                shift8_gravitysap_debug_log('ItemCode loading exception', array(
                    'error' => $e->getMessage(),
                    'returning_cached' => !empty($stored_data['items'])
                ));
            }
            
            // Return existing cached data if exception occurs
            return !empty($stored_data['items']) ? $stored_data['items'] : array();
        }
    }

    /**
     * Render field mapping table
     *
     * @since 1.0.0
     * @param array $form     Form data
     * @param array $settings Current settings
     */
    private function render_field_mapping_table($form, $settings) {
        $sap_fields = $this->get_sap_fields();
        $sap_limits = $this->get_sap_field_limits();
        
        // Debug information for field mapping
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<!-- DEBUG: Available form fields: ';
            foreach ($form['fields'] as $field) {
                echo $field->id . ':' . $field->type . ':' . $field->label . '; ';
            }
            echo ' -->';
        }
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('SAP Field', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                    <th><?php esc_html_e('Gravity Form Field', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                    <th><?php esc_html_e('SAP Limits', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sap_fields as $sap_field => $label): ?>
                    <tr>
                        <td>
                            <?php echo esc_html($label); ?>
                            <?php if (isset($sap_limits[$sap_field]['required']) && $sap_limits[$sap_field]['required']): ?>
                                <span style="color: red;">*</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <select name="sap_field_mapping[<?php echo esc_attr($sap_field); ?>]">
                                <option value=""><?php esc_html_e('Select a field', 'shift8-gravity-forms-sap-b1-integration'); ?></option>
                                <?php
                                foreach ($form['fields'] as $field) {
                                    // Include more field types for better mapping coverage
                                    $allowed_types = array(
                                        'text', 'email', 'phone', 'address', 'name', 'hidden', 'website',
                                        'select', 'multiselect', 'radio', 'checkbox', 'textarea', 'number',
                                        'date', 'time', 'list', 'fileupload', 'post_title', 'post_content',
                                        'post_excerpt', 'post_tags', 'post_category', 'post_image', 'post_custom_field'
                                    );
                                    
                                    if (in_array($field->type, $allowed_types, true)) {
                                        $field_mapping = rgar($settings, 'field_mapping');
                                        $selected = selected(rgar($field_mapping, $sap_field), $field->id, false);
                                        echo '<option value="' . esc_attr($field->id) . '" ' . esc_attr($selected) . '>' . esc_html(GFCommon::get_label($field)) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </td>
                        <td>
                            <?php if (isset($sap_limits[$sap_field])): ?>
                                <small style="color: #666;">
                                    Max: <?php echo esc_html($sap_limits[$sap_field]['max_length']); ?> chars
                                    <?php if (isset($sap_limits[$sap_field]['validation_message'])): ?>
                                        <br><em><?php echo esc_html($sap_limits[$sap_field]['validation_message']); ?></em>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            <span style="color: red;">*</span> <?php esc_html_e('Required fields', 'shift8-gravity-forms-sap-b1-integration'); ?>
            <br><?php esc_html_e('Form validation will automatically check these SAP field limits before submission.', 'shift8-gravity-forms-sap-b1-integration'); ?>
        </p>
        <?php
    }
    
    /**
     * Render quotation field mapping table
     *
     * @since 1.2.2
     * @param array $form     Form data
     * @param array $settings Current settings
     */
    private function render_quotation_field_mapping_table($form, $settings) {
        $quotation_fields = $this->get_sap_quotation_fields();
        $quotation_limits = $this->get_sap_quotation_field_limits();
        ?>
        
        <!-- ItemCode Management Section -->
        <div class="itemcode-management" style="background: #fff; padding: 15px; border: 1px solid #c3c4c7; border-radius: 4px; margin-bottom: 20px;">
            <h4 style="margin-top: 0;">
                <span class="dashicons dashicons-products" style="vertical-align: middle;"></span>
                <?php esc_html_e('SAP ItemCode Management', 'shift8-gravity-forms-sap-b1-integration'); ?>
            </h4>
            
            <div class="itemcode-status-section">
                <?php
                $stored_data = get_option('shift8_gravitysap_item_codes_data', array());
                $available_items = !empty($stored_data['items']) ? $stored_data['items'] : array();
                $last_updated = !empty($stored_data['last_updated_formatted']) ? $stored_data['last_updated_formatted'] : null;
                
                if (empty($available_items)):
                ?>
                    <p style="margin: 10px 0;">
                        <span class="dashicons dashicons-info" style="color: #0073aa; vertical-align: middle;"></span>
                        <?php esc_html_e('ItemCodes need to be loaded from your SAP B1 system before you can map them to form fields.', 'shift8-gravity-forms-sap-b1-integration'); ?>
                    </p>
                    
                    <button type="button" 
                            class="button button-primary load-all-itemcodes-btn" 
                            onclick="loadAllItemCodes()"
                            style="margin-right: 10px;">
                        <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                        <?php esc_html_e('Load ItemCodes from SAP B1', 'shift8-gravity-forms-sap-b1-integration'); ?>
                    </button>
                    
                    <span class="itemcode-global-status" style="color: #666;">
                        <?php esc_html_e('Click to load available ItemCodes from your SAP B1 system', 'shift8-gravity-forms-sap-b1-integration'); ?>
                    </span>
                <?php else: ?>
                    <p style="margin: 10px 0;">
                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a; vertical-align: middle;"></span>
                        <?php echo sprintf(esc_html__('✅ %d ItemCodes loaded and ready for mapping', 'shift8-gravity-forms-sap-b1-integration'), count($available_items)); ?>
                        <?php if ($last_updated): ?>
                            <br><small style="color: #666;">
                                <?php echo sprintf(esc_html__('Last updated: %s', 'shift8-gravity-forms-sap-b1-integration'), esc_html($last_updated)); ?>
                            </small>
                        <?php endif; ?>
                    </p>
                    
                    <button type="button" 
                            class="button button-secondary reload-all-itemcodes-btn" 
                            onclick="loadAllItemCodes(true)"
                            style="margin-right: 10px;">
                        <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                        <?php esc_html_e('Reload ItemCodes', 'shift8-gravity-forms-sap-b1-integration'); ?>
                    </button>
                    
                    <input type="text" 
                           class="itemcode-global-search" 
                           placeholder="<?php esc_attr_e('Search all ItemCodes...', 'shift8-gravity-forms-sap-b1-integration'); ?>"
                           onkeyup="filterAllItemCodes(this.value)"
                           style="width: 250px; margin-right: 10px;">
                    
                    <span class="itemcode-global-status" style="color: #00a32a;">
                        <?php echo sprintf(esc_html__('Last updated: %s', 'shift8-gravity-forms-sap-b1-integration'), date('M j, Y g:i A')); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 15px;">
            <h4 style="margin-top: 0;"><?php esc_html_e('Sales Quotation Configuration', 'shift8-gravity-forms-sap-b1-integration'); ?></h4>
            <p><?php esc_html_e('Map form fields to Sales Quotation line items. The quotation will be automatically linked to the Business Partner created above.', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
            <p><strong><?php esc_html_e('How it works:', 'shift8-gravity-forms-sap-b1-integration'); ?></strong></p>
            <ul style="margin-left: 20px;">
                <li><?php esc_html_e('Map each form field to an Item Code slot (#1, #2, #3, etc.)', 'shift8-gravity-forms-sap-b1-integration'); ?></li>
                <li><?php esc_html_e('Only items with an Item Code value will be added to the quotation', 'shift8-gravity-forms-sap-b1-integration'); ?></li>
                <li><?php esc_html_e('Perfect for checkbox fields - only checked items appear in the quotation', 'shift8-gravity-forms-sap-b1-integration'); ?></li>
                <li><?php esc_html_e('Quantity defaults to 1 if not provided', 'shift8-gravity-forms-sap-b1-integration'); ?></li>
                <li><?php esc_html_e('The Customer field is automatically set to the newly created Business Partner', 'shift8-gravity-forms-sap-b1-integration'); ?></li>
            </ul>
        </div>
        
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('SAP Quotation Field', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                    <th><?php esc_html_e('Gravity Form Field', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                    <th><?php esc_html_e('SAP Requirements', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quotation_fields as $quotation_field => $label): ?>
                    <tr>
                        <td>
                            <?php echo esc_html($label); ?>
                            <?php if (isset($quotation_limits[$quotation_field]['required']) && $quotation_limits[$quotation_field]['required']): ?>
                                <span style="color: red;">*</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (strpos($quotation_field, 'ItemCode') !== false): ?>
                                <!-- For ItemCode fields, show form field dropdown AND ItemCode selection -->
                                <div class="itemcode-mapping-container">
                                    <div style="margin-bottom: 10px;">
                                        <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                                            <?php esc_html_e('Form Field (triggers this line item):', 'shift8-gravity-forms-sap-b1-integration'); ?>
                                        </label>
                                        <select name="sap_quotation_field_mapping[<?php echo esc_attr($quotation_field); ?>]" 
                                                style="min-width: 300px;">
                                            <option value=""><?php esc_html_e('Select a field', 'shift8-gravity-forms-sap-b1-integration'); ?></option>
                                            <?php
                                            foreach ($form['fields'] as $field) {
                                                $allowed_types = array(
                                                    'text', 'email', 'phone', 'address', 'name', 'hidden', 'website',
                                                    'select', 'multiselect', 'radio', 'checkbox', 'textarea', 'number',
                                                    'date', 'time', 'list', 'fileupload', 'post_title', 'post_content',
                                                    'post_excerpt', 'post_tags', 'post_category', 'post_image', 'post_custom_field'
                                                );
                                                
                                                if (in_array($field->type, $allowed_types, true)) {
                                                    $quotation_mapping = rgar($settings, 'quotation_field_mapping');
                                                    $selected = selected(rgar($quotation_mapping, $quotation_field), $field->id, false);
                                                    echo '<option value="' . esc_attr($field->id) . '" ' . esc_attr($selected) . '>' . esc_html(GFCommon::get_label($field)) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                                            <?php esc_html_e('SAP ItemCode (when field has value):', 'shift8-gravity-forms-sap-b1-integration'); ?>
                                        </label>
                                        <select name="sap_quotation_itemcode_mapping[<?php echo esc_attr($quotation_field); ?>]" 
                                                class="itemcode-dropdown" 
                                                style="min-width: 300px;">
                                            <option value=""><?php esc_html_e('Select SAP ItemCode', 'shift8-gravity-forms-sap-b1-integration'); ?></option>
                                            <?php
                                            // Check if ItemCodes are already loaded
                                            $stored_data = get_option('shift8_gravitysap_item_codes_data', array());
                                            $available_items = !empty($stored_data['items']) ? $stored_data['items'] : array();
                                            $itemcode_mapping = rgar($settings, 'quotation_itemcode_mapping');
                                            $selected_item = rgar($itemcode_mapping, $quotation_field);
                                            
                                            if (!empty($available_items)) {
                                                foreach ($available_items as $item_code => $item_name) {
                                                    $selected = selected($selected_item, $item_code, false);
                                                    echo '<option value="' . esc_attr($item_code) . '" ' . esc_attr($selected) . '>' . esc_html($item_code . ' - ' . $item_name) . '</option>';
                                                }
                                            } elseif (!empty($selected_item)) {
                                                // Show previously selected item even if not loaded
                                                echo '<option value="' . esc_attr($selected_item) . '" selected>' . esc_html($selected_item . ' (Previously selected)') . '</option>';
                                            }
                                            ?>
                                        </select>
                                        <?php if (empty($available_items)): ?>
                                            <p class="description" style="color: #d63638; margin-top: 5px;">
                                                <?php esc_html_e('⚠️ Use the "Load ItemCodes" button above to populate this dropdown', 'shift8-gravity-forms-sap-b1-integration'); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="margin-top: 10px;">
                                        <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                                            <?php esc_html_e('Unit Price (optional):', 'shift8-gravity-forms-sap-b1-integration'); ?>
                                        </label>
                                        <?php
                                        $price_mapping = rgar($settings, 'quotation_price_mapping', array());
                                        $current_price = rgar($price_mapping, $quotation_field, '');
                                        ?>
                                        <input type="number" 
                                               name="sap_quotation_price_mapping[<?php echo esc_attr($quotation_field); ?>]" 
                                               value="<?php echo esc_attr($current_price); ?>"
                                               step="0.01" 
                                               min="0" 
                                               placeholder="0.00"
                                               style="width: 150px;" />
                                        <p class="description" style="margin-top: 3px; font-size: 11px; color: #666;">
                                            <?php esc_html_e('Leave empty to use SAP item master pricing. Set a value to override the price.', 'shift8-gravity-forms-sap-b1-integration'); ?>
                                        </p>
                                    </div>
                                    
                                    <p class="description" style="margin-top: 8px; font-style: italic; color: #666;">
                                        <?php esc_html_e('When the selected form field has a value, a quotation line will be created with the selected ItemCode.', 'shift8-gravity-forms-sap-b1-integration'); ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <!-- For non-ItemCode fields, show form field dropdown -->
                                <select name="sap_quotation_field_mapping[<?php echo esc_attr($quotation_field); ?>]">
                                    <option value=""><?php esc_html_e('Select a field', 'shift8-gravity-forms-sap-b1-integration'); ?></option>
                                    <?php
                                    foreach ($form['fields'] as $field) {
                                        $allowed_types = array(
                                            'text', 'email', 'phone', 'address', 'name', 'hidden', 'website',
                                            'select', 'multiselect', 'radio', 'checkbox', 'textarea', 'number',
                                            'date', 'time', 'list', 'fileupload', 'post_title', 'post_content',
                                            'post_excerpt', 'post_tags', 'post_category', 'post_image', 'post_custom_field'
                                        );
                                        
                                        if (in_array($field->type, $allowed_types, true)) {
                                            $quotation_mapping = rgar($settings, 'quotation_field_mapping');
                                            $selected = selected(rgar($quotation_mapping, $quotation_field), $field->id, false);
                                            echo '<option value="' . esc_attr($field->id) . '" ' . esc_attr($selected) . '>' . esc_html(GFCommon::get_label($field)) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($quotation_limits[$quotation_field])): ?>
                                <small style="color: #666;">
                                    <?php if (isset($quotation_limits[$quotation_field]['max_length'])): ?>
                                        Max: <?php echo esc_html($quotation_limits[$quotation_field]['max_length']); ?> chars
                                    <?php endif; ?>
                                    <?php if (isset($quotation_limits[$quotation_field]['format'])): ?>
                                        <br>Format: <?php echo esc_html($quotation_limits[$quotation_field]['format']); ?>
                                    <?php endif; ?>
                                    <?php if (isset($quotation_limits[$quotation_field]['description'])): ?>
                                        <br><em><?php echo esc_html($quotation_limits[$quotation_field]['description']); ?></em>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            <?php esc_html_e('All fields are optional. Only line items with an Item Code will be included in the quotation.', 'shift8-gravity-forms-sap-b1-integration'); ?>
            <br><?php esc_html_e('The Customer (CardCode) field is automatically populated with the newly created Business Partner.', 'shift8-gravity-forms-sap-b1-integration'); ?>
        </p>
        <?php
    }
    
    /**
     * Render test sections (numbering series and integration tests)
     *
     * @since 1.0.0
     * @param int   $form_id  Form ID
     * @param array $settings Current settings
     */
    private function render_test_sections($form_id, $settings) {
        ?>
        <hr style="margin: 30px 0;" />
        
        <!-- Field Mapping Test -->
        <form method="post" id="test-field-mapping-form" style="margin-bottom: 20px;">
            <?php wp_nonce_field('gforms_save_form', 'gforms_save_form') ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($form_id); ?>" />
            <input type="hidden" name="subview" value="sap_integration" />
            
            <h3><?php esc_html_e('Test Field Mapping', 'shift8-gravity-forms-sap-b1-integration'); ?></h3>
            <p><?php esc_html_e('Test the field mapping configuration with sample data to see which fields are mapped and which are missing.', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
            
            <p class="submit">
                <input type="submit" name="test-field-mapping" value="<?php esc_attr_e('Test Field Mapping', 'shift8-gravity-forms-sap-b1-integration'); ?>" class="button-secondary" />
            </p>
        </form>
        
        <!-- Debug Entry Data -->
        <form method="post" id="debug-entry-data-form" style="margin-bottom: 20px;">
            <?php wp_nonce_field('gforms_save_form', 'gforms_save_form') ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($form_id); ?>" />
            <input type="hidden" name="subview" value="sap_integration" />
            
            <h3><?php esc_html_e('Debug Entry Data', 'shift8-gravity-forms-sap-b1-integration'); ?></h3>
            <p><?php esc_html_e('Show the raw entry data and field mapping to help debug why the Name field is blank.', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
            
            <p class="submit">
                <input type="submit" name="debug-entry-data" value="<?php esc_attr_e('Debug Entry Data', 'shift8-gravity-forms-sap-b1-integration'); ?>" class="button-secondary" />
            </p>
        </form>
        
        <!-- Test SAP Submission -->
        <form method="post" id="test-sap-submission-form" style="margin-bottom: 20px;">
            <?php wp_nonce_field('gforms_save_form', 'gforms_save_form') ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($form_id); ?>" />
            <input type="hidden" name="subview" value="sap_integration" />
            
            <h3><?php esc_html_e('Test SAP Submission', 'shift8-gravity-forms-sap-b1-integration'); ?></h3>
            <p><?php esc_html_e('Test SAP submission with the most recent entry to see detailed error information.', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
            
            <p class="submit">
                <input type="submit" name="test-sap-submission" value="<?php esc_attr_e('Test SAP Submission', 'shift8-gravity-forms-sap-b1-integration'); ?>" class="button-secondary" />
            </p>
        </form>
        
        <!-- Numbering Series Test -->
        <form method="post" id="test-numbering-series-form" style="margin-bottom: 20px;">
            <?php wp_nonce_field('gforms_save_form', 'gforms_save_form') ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($form_id); ?>" />
            <input type="hidden" name="subview" value="sap_integration" />
            
            <h3><?php esc_html_e('Test SAP Numbering Series', 'shift8-gravity-forms-sap-b1-integration'); ?></h3>
            <p><?php esc_html_e('Check if SAP Business One has the required numbering series configured for Business Partners.', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
            
            <p class="submit">
                <input type="submit" name="test-numbering-series" value="<?php esc_attr_e('Test Numbering Series', 'shift8-gravity-forms-sap-b1-integration'); ?>" class="button-secondary" />
                <span class="description" style="margin-left: 10px;">
                    <?php esc_html_e('This will check if SAP B1 has the required numbering series configuration.', 'shift8-gravity-forms-sap-b1-integration'); ?>
                </span>
            </p>
        </form>
        
        <?php if (!empty(rgar($settings, 'field_mapping'))): ?>
        <!-- Business Partner Creation Test -->
        <form method="post" id="test-sap-form">
            <?php wp_nonce_field('gforms_save_form', 'gforms_save_form') ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($form_id); ?>" />
            <input type="hidden" name="subview" value="sap_integration" />
            
            <h3><?php esc_html_e('Test SAP Integration', 'shift8-gravity-forms-sap-b1-integration'); ?></h3>
            <p><?php esc_html_e('Send test data to SAP Business One to validate your field mapping configuration.', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
            
            <?php $this->render_test_data_table($settings); ?>
            
            <p class="submit">
                <input type="submit" name="test-sap-integration" value="<?php esc_attr_e('Test Integration', 'shift8-gravity-forms-sap-b1-integration'); ?>" class="button-secondary" />
                <span class="description" style="margin-left: 10px;">
                    <?php esc_html_e('This will create a test Business Partner in SAP Business One.', 'shift8-gravity-forms-sap-b1-integration'); ?>
                </span>
            </p>
        </form>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render test data table
     *
     * @since 1.0.0
     * @param array $settings Current settings
     */
    private function render_test_data_table($settings) {
        $field_mapping = rgar($settings, 'field_mapping', array());
        
        // Use the SAME field array as the mapping table
        $sap_fields = $this->get_sap_fields();
        
        // Default test values - designed to comply with SAP Business One field length limits
        // Important: State field is limited to ~3-4 characters (use state codes like 'CA', 'NY')
        // CardName, Street, and City should be kept reasonably short to avoid SAP errors
        $default_test_values = array(
            'CardName' => 'Test Customer ' . gmdate('md-His'),
            'EmailAddress' => 'test@example.com',
            'Phone1' => '555-123-4567',
            'Phone2' => '555-987-6543',
            'Cellular' => '555-456-7890',
            'Fax' => '555-654-3210',
            'Website' => 'https://example.com',
            'BPAddresses.Street' => '123 Main St',
            'BPAddresses.City' => 'Anytown',
            'BPAddresses.State' => 'CA',
            'BPAddresses.ZipCode' => '12345',
            'BPAddresses.Country' => 'US',
        );
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php esc_html_e('Test Data', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                </th>
                <td>
                    <table class="widefat field-mapping-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('SAP Field', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                                <th><?php esc_html_e('Test Value', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sap_fields as $sap_field => $label): ?>
                                <?php $mapped_field_id = rgar($field_mapping, $sap_field); ?>
                                <?php if (!empty($mapped_field_id)): ?>
                                    <tr>
                                        <td><?php echo esc_html($label); ?></td>
                                        <td>
                                            <input type="text" 
                                                   name="test_values[<?php echo esc_attr($sap_field); ?>]" 
                                                   value="<?php echo esc_attr(rgar($default_test_values, $sap_field)); ?>" 
                                                   class="regular-text" />
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description">
                        <?php esc_html_e('These test values will be sent to SAP Business One using your current field mapping configuration.', 'shift8-gravity-forms-sap-b1-integration'); ?>
                        <br><strong><?php esc_html_e('Note:', 'shift8-gravity-forms-sap-b1-integration'); ?></strong> 
                        <?php esc_html_e('Use state codes (CA, NY) not full names, and 2-letter country codes (US, CA) to avoid SAP field length errors.', 'shift8-gravity-forms-sap-b1-integration'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save form settings
     *
     * Validates and sanitizes form settings before saving.
     *
     * @since 1.0.0
     * @param array $form Form data
     * @return array Modified form data
     */
    public function save_form_settings($form) {
        // Only process our settings when we're on our subview
        if (rgget('subview') !== 'sap_integration') {
            return $form;
        }
        
        // Verify nonce
        if (!wp_verify_nonce(rgpost('gforms_save_form'), 'gforms_save_form')) {
            return $form;
        }
        
        // Sanitize and validate settings
        $settings = array(
            'enabled' => rgpost('sap_enabled') === '1' ? '1' : '0',
            'feed_name' => sanitize_text_field(rgpost('sap_feed_name')),
            'card_type' => in_array(rgpost('sap_card_type'), array('cCustomer', 'cSupplier', 'cLid'), true) ? rgpost('sap_card_type') : 'cCustomer',
            'card_code_prefix' => sanitize_text_field(rgpost('sap_card_code_prefix')),
            'create_quotation' => rgpost('sap_create_quotation') === '1' ? '1' : '0',
            'field_mapping' => array(),
            'quotation_field_mapping' => array()
        );
        
        // Validate and sanitize Business Partner field mapping
        $field_mapping = rgpost('sap_field_mapping');
        if (is_array($field_mapping)) {
            $allowed_sap_fields = $this->get_allowed_sap_fields();
            
            foreach ($field_mapping as $sap_field => $field_id) {
                if (in_array($sap_field, $allowed_sap_fields, true) && is_numeric($field_id) && $field_id > 0) {
                    $settings['field_mapping'][$sap_field] = absint($field_id);
                }
            }
        }
        
        // Validate and sanitize Sales Quotation field mapping
        $quotation_mapping = rgpost('sap_quotation_field_mapping');
        if (is_array($quotation_mapping)) {
            $allowed_quotation_fields = array_keys($this->get_sap_quotation_fields());
            
            foreach ($quotation_mapping as $quotation_field => $field_id) {
                if (in_array($quotation_field, $allowed_quotation_fields, true) && is_numeric($field_id) && $field_id > 0) {
                    $settings['quotation_field_mapping'][$quotation_field] = absint($field_id);
                }
            }
        }
        
        // Validate and sanitize Sales Quotation ItemCode mapping
        $quotation_itemcode_mapping = rgpost('sap_quotation_itemcode_mapping');
        if (is_array($quotation_itemcode_mapping)) {
            $settings['quotation_itemcode_mapping'] = array();
            $allowed_quotation_fields = array_keys($this->get_sap_quotation_fields());
            
            foreach ($quotation_itemcode_mapping as $quotation_field => $item_code) {
                if (in_array($quotation_field, $allowed_quotation_fields, true) && !empty($item_code)) {
                    $settings['quotation_itemcode_mapping'][$quotation_field] = sanitize_text_field($item_code);
                }
            }
        }
        
        // Validate and sanitize Sales Quotation Price mapping
        $quotation_price_mapping = rgpost('sap_quotation_price_mapping');
        if (is_array($quotation_price_mapping)) {
            $settings['quotation_price_mapping'] = array();
            $allowed_quotation_fields = array_keys($this->get_sap_quotation_fields());
            
            foreach ($quotation_price_mapping as $quotation_field => $price) {
                if (in_array($quotation_field, $allowed_quotation_fields, true) && !empty($price)) {
                    // Validate that price is a valid positive number
                    $sanitized_price = floatval($price);
                    if ($sanitized_price >= 0) {
                        $settings['quotation_price_mapping'][$quotation_field] = $sanitized_price;
                    }
                }
            }
        }
        
        $form['sap_integration_settings'] = $settings;
        
        shift8_gravitysap_debug_log('Form settings saved', array(
            'form_id' => $form['id'],
            'settings' => $settings
        ));
        
        return $form;
    }

    /**
     * Process form submission
     *
     * Handles form submissions and sends data to SAP Business One.
     *
     * @since 1.0.0
     * @param array $entry Entry data
     * @param array $form  Form data
     */
    public function process_form_submission($entry, $form) {
        $settings = rgar($form, 'sap_integration_settings');
        
        // Initialize status tracking
        if (!empty($entry['id'])) {
            $this->update_entry_sap_status($entry['id'], 'processing', '', '');
        }
        
        if (empty($settings['enabled']) || $settings['enabled'] !== '1') {
            if (!empty($entry['id'])) {
                $this->update_entry_sap_status($entry['id'], 'skipped', '', 'SAP Integration not enabled for this form');
            }
            return;
        }
        
        try {
            // STEP 1: Validate SAP connection settings
            $plugin_settings = get_option('shift8_gravitysap_settings', array());
            if (empty($plugin_settings['sap_endpoint']) || empty($plugin_settings['sap_username']) || empty($plugin_settings['sap_password'])) {
                throw new Exception('SAP connection settings are incomplete - check plugin settings');
            }
            
            // STEP 2: Validate required fields BEFORE attempting SAP connection
            $validation_result = $this->validate_required_fields($settings, $entry, $form);
            if (!$validation_result['valid']) {
                shift8_gravitysap_debug_log('❌ FIELD VALIDATION FAILED', array(
                    'entry_id' => $entry['id'],
                    'validation_error' => $validation_result['error'],
                    'field_mapping' => rgar($settings, 'field_mapping', array()),
                    'entry_data' => $entry
                ));
                throw new Exception('Field validation failed: ' . $validation_result['error']);
            }
            
            shift8_gravitysap_debug_log('✅ FIELD VALIDATION PASSED', array(
                'entry_id' => $entry['id'],
                'field_mapping' => rgar($settings, 'field_mapping', array())
            ));
            
            // STEP 3: Map form fields to SAP Business Partner data
            $business_partner_data = $this->map_entry_to_business_partner($settings, $entry, $form);
            
            shift8_gravitysap_debug_log('📋 MAPPED BUSINESS PARTNER DATA', array(
                'entry_id' => $entry['id'],
                'business_partner_data' => $business_partner_data
            ));
            
            // STEP 4: Validate field mapping
            $mapping_validation = $this->validate_field_mapping($settings, $entry, $form);
            shift8_gravitysap_debug_log('Field mapping validation', $mapping_validation);
            
            // STEP 5: Initialize SAP service
            $plugin_settings['sap_password'] = shift8_gravitysap_decrypt_password($plugin_settings['sap_password']);
            require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($plugin_settings);
            
            // STEP 6: Create Business Partner in SAP
            $result = $sap_service->create_business_partner($business_partner_data);
            
            // STEP 7: Handle success
            if ($result && isset($result['CardCode'])) {
                $this->update_entry_sap_status($entry['id'], 'success', $result['CardCode'], '');
                
                // Add success note to entry
                GFFormsModel::add_note(
                    $entry['id'], 
                    0, 
                    'Shift8 SAP Integration', 
                    sprintf(
                        /* translators: %s: SAP Business Partner Card Code */
                        esc_html__('✅ SUCCESS: Business Partner created in SAP B1. Card Code: %s', 'shift8-gravity-forms-sap-b1-integration'), 
                        esc_html($result['CardCode'])
                    )
                );
                
                shift8_gravitysap_debug_log('✅ SAP CONFIRMATION: Business Partner created successfully', array(
                    'CardCode' => $result['CardCode'],
                    'CardName' => $result['CardName'] ?? 'N/A',
                    'CardType' => $result['CardType'] ?? 'N/A',
                    'Series' => $result['Series'] ?? 'N/A',
                    'EntryID' => $entry['id'],
                    'FormID' => $form['id']
                ));
                
                // STEP 8: Create Sales Quotation if enabled
                if (!empty($settings['create_quotation']) && $settings['create_quotation'] === '1') {
                    try {
                        $quotation_result = $this->create_sales_quotation_from_entry($entry, $form, $settings, $result['CardCode'], $sap_service);
                        
                        if ($quotation_result && isset($quotation_result['DocEntry'])) {
                            // Update entry with quotation info
                            gform_update_meta($entry['id'], 'sap_b1_quotation_docentry', $quotation_result['DocEntry']);
                            gform_update_meta($entry['id'], 'sap_b1_quotation_docnum', $quotation_result['DocNum'] ?? '');
                            
                            // Add quotation note
                            GFFormsModel::add_note(
                                $entry['id'], 
                                0, 
                                'Shift8 SAP Integration', 
                                sprintf(
                                    /* translators: 1: DocNum, 2: DocEntry */
                                    esc_html__('✅ SUCCESS: Sales Quotation created in SAP B1. Doc #%1$s (Entry: %2$s)', 'shift8-gravity-forms-sap-b1-integration'), 
                                    esc_html($quotation_result['DocNum'] ?? 'N/A'),
                                    esc_html($quotation_result['DocEntry'])
                                )
                            );
                            
                            shift8_gravitysap_debug_log('✅ SAP QUOTATION: Sales Quotation created successfully', array(
                                'DocEntry' => $quotation_result['DocEntry'],
                                'DocNum' => $quotation_result['DocNum'] ?? 'N/A',
                                'CardCode' => $result['CardCode'],
                                'EntryID' => $entry['id']
                            ));
                        }
                    } catch (Exception $quotation_error) {
                        // Log quotation error but don't fail the whole submission
                        shift8_gravitysap_debug_log('⚠️ SAP QUOTATION FAILED (BP was created successfully)', array(
                            'error' => $quotation_error->getMessage(),
                            'CardCode' => $result['CardCode'],
                            'EntryID' => $entry['id']
                        ));
                        
                        GFFormsModel::add_note(
                            $entry['id'], 
                            0, 
                            'Shift8 SAP Integration', 
                            sprintf(
                                /* translators: %s: Error message */
                                esc_html__('⚠️ WARNING: Sales Quotation creation failed: %s (Business Partner was created successfully)', 'shift8-gravity-forms-sap-b1-integration'), 
                                esc_html($quotation_error->getMessage())
                            )
                        );
                    }
                }
            } else {
                throw new Exception('SAP returned success but no CardCode - check SAP logs');
            }
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $this->update_entry_sap_status($entry['id'], 'failed', '', $error_message);
            
            // Add detailed error note to entry
            GFFormsModel::add_note(
                $entry['id'], 
                0, 
                'Shift8 SAP Integration', 
                sprintf(
                    /* translators: %s: Error message */
                    esc_html__('❌ FAILED: %s', 'shift8-gravity-forms-sap-b1-integration'), 
                    esc_html($error_message)
                )
            );
            
            shift8_gravitysap_debug_log('❌ SAP SUBMISSION FAILED', array(
                'entry_id' => $entry['id'],
                'form_id' => $form['id'],
                'error' => $error_message,
                'entry_data' => $entry
            ));
        }
    }

    /**
     * Update entry SAP status in database
     *
     * @since 1.1.0
     * @param int    $entry_id Entry ID
     * @param string $status   Status (processing, success, failed, skipped)
     * @param string $cardcode SAP CardCode if successful
     * @param string $error    Error message if failed
     */
    private function update_entry_sap_status($entry_id, $status, $cardcode = '', $error = '') {
        // Use Gravity Forms native meta functions for proper persistence
        gform_update_meta($entry_id, 'sap_b1_status', $status);
        
        // Update CardCode if provided
        if (!empty($cardcode)) {
            gform_update_meta($entry_id, 'sap_b1_cardcode', $cardcode);
        }
        
        // Update error if provided
        if (!empty($error)) {
            gform_update_meta($entry_id, 'sap_b1_error', $error);
        }
        
        shift8_gravitysap_debug_log('SAP Status Updated', array(
            'entry_id' => $entry_id,
            'status' => $status,
            'cardcode' => $cardcode,
            'error' => $error
        ));
    }

    /**
     * Validate required fields before SAP submission
     *
     * @since 1.1.0
     * @param array $settings Form settings
     * @param array $entry    Entry data
     * @param array $form     Form data
     * @return array Validation result
     */
    private function validate_required_fields($settings, $entry, $form) {
        $field_mapping = rgar($settings, 'field_mapping', array());
        $errors = array();
        
        // Check if CardName is mapped and has a value
        if (empty($field_mapping['CardName'])) {
            $errors[] = 'CardName (Business Partner Name) field is not mapped';
        } else {
            $cardname_field_id = $field_mapping['CardName'];
            $cardname_value = rgar($entry, $cardname_field_id);
            
            if (empty($cardname_value)) {
                $field_label = $this->get_field_label($form, $cardname_field_id);
                $errors[] = "CardName (Business Partner Name) field '{$field_label}' is empty";
            }
        }
        
        // Check if EmailAddress is mapped and has a value
        if (empty($field_mapping['EmailAddress'])) {
            $errors[] = 'EmailAddress field is not mapped';
        } else {
            $email_field_id = $field_mapping['EmailAddress'];
            $email_value = rgar($entry, $email_field_id);
            
            if (empty($email_value)) {
                $field_label = $this->get_field_label($form, $email_field_id);
                $errors[] = "EmailAddress field '{$field_label}' is empty";
            } elseif (!is_email($email_value)) {
                $field_label = $this->get_field_label($form, $email_field_id);
                $errors[] = "EmailAddress field '{$field_label}' contains invalid email: {$email_value}";
            }
        }
        
        if (!empty($errors)) {
            return array(
                'valid' => false,
                'error' => implode('; ', $errors)
            );
        }
        
        return array('valid' => true);
    }

    /**
     * Map entry data to Business Partner structure
     *
     * @since 1.0.0
     * @param array $settings Form settings
     * @param array $entry    Entry data
     * @param array $form     Form data
     * @return array Business Partner data structure
     */
    private function map_entry_to_business_partner($settings, $entry, $form) {
        $field_mapping = rgar($settings, 'field_mapping', array());
        $card_type = rgar($settings, 'card_type', 'cCustomer');
        $card_code_prefix = rgar($settings, 'card_code_prefix', '');
        
        $business_partner = array('CardType' => $card_type);
        
        // Add the prefix if specified
        if (!empty($card_code_prefix)) {
            $business_partner['CardCodePrefix'] = $card_code_prefix;
        }

        // Handle composite CardName (FirstName + LastName)
        $first_name = '';
        $last_name = '';
        if (!empty($field_mapping['CardName_FirstName'])) {
            $first_name = rgar($entry, $field_mapping['CardName_FirstName']);
        }
        if (!empty($field_mapping['CardName_LastName'])) {
            $last_name = rgar($entry, $field_mapping['CardName_LastName']);
        }
        
        // If both first and last name are provided, combine them for CardName
        if (!empty($first_name) || !empty($last_name)) {
            $business_partner['CardName'] = trim(sanitize_text_field($first_name) . ' ' . sanitize_text_field($last_name));
        }
        
        // Track contact person data
        $contact_person_data = array();
        
        foreach ($field_mapping as $sap_field => $field_id) {
            // Skip composite fields as they're handled above
            if ($sap_field === 'CardName_FirstName' || $sap_field === 'CardName_LastName') {
                continue;
            }
            
            if (empty($field_id)) {
                continue;
            }

            $field_value = rgar($entry, $field_id);
            
            if (empty($field_value)) {
                continue;
            }

            // Sanitize field value
            $field_value = sanitize_text_field($field_value);

            // Handle Contact Person fields
            if (strpos($sap_field, 'ContactEmployees.') === 0) {
                $contact_field = str_replace('ContactEmployees.', '', $sap_field);
                $contact_person_data[$contact_field] = $field_value;
            }
            // Handle address fields for BPAddresses collection
            elseif (strpos($sap_field, 'BPAddresses.') === 0) {
                $address_field = str_replace('BPAddresses.', '', $sap_field);
                
                if (!isset($business_partner['BPAddresses'])) {
                    // Initialize with required fields for SAP Business One
                    // AddressName is REQUIRED for SAP to save the address properly
                    $business_partner['BPAddresses'] = array(array(
                        'AddressType' => 'bo_BillTo',
                        'AddressName' => 'Primary', // Required field
                        'RowNum' => 0
                    ));
                }
                $business_partner['BPAddresses'][0][$address_field] = $field_value;
            }
            // Handle main Business Partner fields
            else {
                $business_partner[$sap_field] = $field_value;
            }
        }
        
        // Create Contact Person entry if we have contact data
        if (!empty($contact_person_data)) {
            $contact_person = array();
            
            // Set the contact name
            if (!empty($contact_person_data['FirstName']) || !empty($contact_person_data['LastName'])) {
                $contact_person['Name'] = trim(
                    ($contact_person_data['FirstName'] ?? '') . ' ' . 
                    ($contact_person_data['LastName'] ?? '')
                );
            } else {
                $contact_person['Name'] = $business_partner['CardName'] ?? 'Primary Contact';
            }
            
            // Add first/last name
            if (!empty($contact_person_data['FirstName'])) {
                $contact_person['FirstName'] = $contact_person_data['FirstName'];
            }
            if (!empty($contact_person_data['LastName'])) {
                $contact_person['LastName'] = $contact_person_data['LastName'];
            }
            
            // Add contact fields
            if (!empty($contact_person_data['E_Mail'])) {
                $contact_person['E_Mail'] = $contact_person_data['E_Mail'];
            }
            if (!empty($contact_person_data['Phone1'])) {
                $contact_person['Phone1'] = $contact_person_data['Phone1'];
            }
            if (!empty($contact_person_data['Address'])) {
                $contact_person['Address'] = $contact_person_data['Address'];
            }
            
            $business_partner['ContactEmployees'] = array($contact_person);
        }

        return $business_partner;
    }

    /**
     * Validate field mapping and provide detailed mapping information
     *
     * @since 1.1.0
     * @param array $settings Form settings
     * @param array $entry    Entry data
     * @param array $form     Form data
     * @return array Mapping validation results
     */
    private function validate_field_mapping($settings, $entry, $form) {
        $field_mapping = rgar($settings, 'field_mapping', array());
        $mapped_fields = array();
        $unmapped_fields = array();
        $empty_fields = array();
        $available_sap_fields = $this->get_sap_fields();
        
        // Check each mapped field
        foreach ($field_mapping as $sap_field => $field_id) {
            if (empty($field_id)) {
                $unmapped_fields[] = array(
                    'sap_field' => $sap_field,
                    'sap_label' => $available_sap_fields[$sap_field] ?? $sap_field,
                    'reason' => 'No Gravity Forms field mapped'
                );
                continue;
            }
            
            $field_value = rgar($entry, $field_id);
            $field_label = $this->get_field_label($form, $field_id);
            
            if (empty($field_value)) {
                $empty_fields[] = array(
                    'sap_field' => $sap_field,
                    'sap_label' => $available_sap_fields[$sap_field] ?? $sap_field,
                    'gf_field_id' => $field_id,
                    'gf_field_label' => $field_label,
                    'reason' => 'Field value is empty'
                );
            } else {
                $mapped_fields[] = array(
                    'sap_field' => $sap_field,
                    'sap_label' => $available_sap_fields[$sap_field] ?? $sap_field,
                    'gf_field_id' => $field_id,
                    'gf_field_label' => $field_label,
                    'value' => $field_value
                );
            }
        }
        
        // Check for unmapped SAP fields
        foreach ($available_sap_fields as $sap_field => $sap_label) {
            if (!isset($field_mapping[$sap_field]) || empty($field_mapping[$sap_field])) {
                $unmapped_fields[] = array(
                    'sap_field' => $sap_field,
                    'sap_label' => $sap_label,
                    'reason' => 'Not mapped to any Gravity Forms field'
                );
            }
        }
        
        return array(
            'mapped_fields' => $mapped_fields,
            'unmapped_fields' => $unmapped_fields,
            'empty_fields' => $empty_fields,
            'total_mapped' => count($mapped_fields),
            'total_unmapped' => count($unmapped_fields),
            'total_empty' => count($empty_fields)
        );
    }
    
    /**
     * Create Sales Quotation from entry data
     *
     * @since 1.2.2
     * @param array  $entry       Entry data
     * @param array  $form        Form data
     * @param array  $settings    Form settings
     * @param string $card_code   Business Partner CardCode
     * @param object $sap_service SAP Service instance
     * @return array Created quotation data
     * @throws Exception If creation fails
     */
    private function create_sales_quotation_from_entry($entry, $form, $settings, $card_code, $sap_service) {
        shift8_gravitysap_debug_log('=== STARTING SALES QUOTATION CREATION ===');
        
        $quotation_mapping = rgar($settings, 'quotation_field_mapping', array());
        
        if (empty($quotation_mapping)) {
            throw new Exception('No quotation field mapping configured');
        }
        
        // Initialize quotation data
        $quotation_data = array(
            'CardCode' => $card_code,
            'DocumentLines' => array()
        );
        
        // Add Comments if mapped
        if (!empty($quotation_mapping['Comments'])) {
            $comments_value = rgar($entry, $quotation_mapping['Comments']);
            if (!empty($comments_value)) {
                $quotation_data['Comments'] = $comments_value;
            }
        }
        
        // Collect line items dynamically
        $line_items = array();
        
        // Loop through all 20 potential item slots
        for ($i = 1; $i <= 20; $i++) {
            $item_code_key = "DocumentLines.{$i}.ItemCode";
            
            // Check if this slot has an ItemCode mapping
            if (empty($quotation_mapping[$item_code_key])) {
                continue; // Skip unmapped slots
            }
            
            // Get the form field ID that triggers this line item
            $form_field_id = rgar($quotation_mapping, $item_code_key);
            if (empty($form_field_id)) {
                continue; // No form field mapped
            }
            
            // Check if the form field has a value in the entry
            $field_value = rgar($entry, $form_field_id);
            if (empty($field_value)) {
                continue; // Form field is empty, skip this line item
            }
            
            // Get the SAP ItemCode for this slot
            $itemcode_mapping = rgar($settings, 'quotation_itemcode_mapping', array());
            $sap_item_code = rgar($itemcode_mapping, $item_code_key);
            if (empty($sap_item_code)) {
                continue; // No SAP ItemCode configured
            }
            
            // Check if custom price is set for this item
            $price_mapping = rgar($settings, 'quotation_price_mapping', array());
            $custom_price = rgar($price_mapping, $item_code_key);
            
            // We have both a form field value AND an ItemCode! Build the line item
            $line_item = array(
                'ItemCode' => $sap_item_code,
                'UoMEntry' => 1, // Default UoM entry (usually "Each")
                'Quantity' => 1.0, // Ensure quantity is always set as float
                'WarehouseCode' => '01', // Default warehouse (required for display)
                'LineType' => 'dlt_Regular', // Regular line type (required for display)
                'TreeType' => 'iProductionTree', // Force production tree type for UI visibility
                'VatGroup' => '' // Empty VAT group to match working items
            );
            
            // Add custom price if configured, otherwise let SAP auto-populate from item master
            if (!empty($custom_price) && is_numeric($custom_price) && floatval($custom_price) > 0) {
                $line_item['UnitPrice'] = floatval($custom_price);
            }
            // If no custom price set, SAP B1 will auto-populate UnitPrice from ItemCode master data
            
            // Add optional fields for this line item
            $optional_fields = array('ItemDescription', 'Quantity', 'UnitPrice', 'DiscountPercent', 'TaxCode');
            
            foreach ($optional_fields as $field_name) {
                $field_key = "DocumentLines.{$i}.{$field_name}";
                
                if (!empty($quotation_mapping[$field_key])) {
                    $field_value = rgar($entry, $quotation_mapping[$field_key]);
                    
                    if (!empty($field_value)) {
                        // Convert numeric fields
                        if (in_array($field_name, array('Quantity', 'UnitPrice', 'DiscountPercent'), true)) {
                            $field_value = floatval($field_value);
                        }
                        
                        $line_item[$field_name] = $field_value;
                    }
                }
            }
            
            // Ensure Quantity is always a float (SAP requirement)
            if (!isset($line_item['Quantity']) || !is_numeric($line_item['Quantity'])) {
                $line_item['Quantity'] = 1.0;
            } else {
                $line_item['Quantity'] = floatval($line_item['Quantity']);
            }
            
            // Log line item creation with pricing info
            $price_source = isset($line_item['UnitPrice']) ? 'Custom Price: ' . $line_item['UnitPrice'] : 'SAP Auto-Price';
            shift8_gravitysap_debug_log('Line item created', array(
                'ItemCode' => $line_item['ItemCode'],
                'Quantity' => $line_item['Quantity'],
                'PriceSource' => $price_source,
                'FormField' => $item_code_key,
                'FormFieldValue' => $field_value
            ));
            
            $line_items[] = $line_item;
        }
        
        // Validate we have at least one line item
        if (empty($line_items)) {
            throw new Exception('No line items found for quotation (no Item Codes provided)');
        }
        
        $quotation_data['DocumentLines'] = $line_items;
        
        shift8_gravitysap_debug_log('Sales Quotation data prepared', array(
            'CardCode' => $card_code,
            'LineItemCount' => count($line_items),
            'QuotationData' => $quotation_data
        ));
        
        // Create the quotation in SAP
        return $sap_service->create_sales_quotation($quotation_data);
    }
    
    /**
     * Get field label from form data
     *
     * @since 1.1.0
     * @param array $form     Form data
     * @param int   $field_id Field ID
     * @return string Field label
     */
    private function get_field_label($form, $field_id) {
        if (empty($form['fields']) || !is_array($form['fields'])) {
            return 'Field ' . $field_id;
        }
        
        foreach ($form['fields'] as $field) {
            if ($field['id'] == $field_id) {
                return $field['label'] ?? 'Field ' . $field_id;
            }
        }
        
        return 'Field ' . $field_id;
    }

    /**
     * Load SAP entry meta into entries array
     *
     * @since 1.1.0
     * @param array $entries Array of entries
     * @param array $form    Form object
     * @return array Entries with SAP meta loaded
     */
    public function load_sap_entry_meta($entries, $form) {
        if (empty($entries) || !is_array($entries)) {
            return $entries;
        }
        
        foreach ($entries as &$entry) {
            if (empty($entry['id'])) {
                continue;
            }
            
            // Load SAP meta using Gravity Forms native functions
            $status = gform_get_meta($entry['id'], 'sap_b1_status');
            if ($status) {
                $entry['sap_b1_status'] = $status;
            }
            
            $cardcode = gform_get_meta($entry['id'], 'sap_b1_cardcode');
            if ($cardcode) {
                $entry['sap_b1_cardcode'] = $cardcode;
            }
            
            $error = gform_get_meta($entry['id'], 'sap_b1_error');
            if ($error) {
                $entry['sap_b1_error'] = $error;
            }
        }
        
        return $entries;
    }
    
    /**
     * Add SAP B1 Status column to entries list
     *
     * @since 1.1.0
     * @param array $columns Existing columns
     * @param int   $form_id Form ID
     * @return array Modified columns
     */
    public function add_sap_status_column($columns, $form_id) {
        // Check if this form has SAP integration enabled
        $form = GFAPI::get_form($form_id);
        if (!$form || empty($form['sap_integration_settings']['enabled'])) {
            return $columns;
        }
        
        // Replace default columns with business-relevant columns
        $columns = array();
        $columns['cb'] = '<input type="checkbox" />'; // Keep checkbox column
        $columns['name'] = 'Name';
        $columns['email'] = 'Email';
        $columns['company'] = 'Company';
        $columns['country'] = 'Country';
        $columns['sap_b1_status'] = 'SAP B1 Status';
        $columns['date_created'] = 'Date';
        
        return $columns;
    }
    
    /**
     * Display custom column values for SAP-enabled forms
     *
     * @since 1.1.0
     * @param string $value   Current value
     * @param int    $form_id Form ID
     * @param string $field_id Field ID
     * @param array  $entry   Entry data
     * @return string Column value
     */
    public function display_sap_status_column($value, $form_id, $field_id, $entry) {
        // Check if this form has SAP integration enabled
        $form = GFAPI::get_form($form_id);
        if (!$form || empty($form['sap_integration_settings']['enabled'])) {
            return $value;
        }
        
        switch ($field_id) {
            case 'name':
                return $this->get_combined_name($entry, $form);
                
            case 'email':
                return $this->get_email_value($entry, $form);
                
            case 'company':
                return $this->get_company_value($entry, $form);
                
            case 'country':
                return $this->get_country_value($entry, $form);
                
            case 'sap_b1_status':
                return $this->get_sap_status_display($entry);
                
            case 'date_created':
                return $this->get_date_created($entry);
                
            default:
                return $value;
        }
    }
    
    /**
     * Get combined first and last name
     */
    private function get_combined_name($entry, $form) {
        $settings = rgar($form, 'sap_integration_settings', array());
        $field_mapping = rgar($settings, 'field_mapping', array());
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('DEBUG: get_combined_name - Field mapping: ' . print_r($field_mapping, true));
            error_log('DEBUG: get_combined_name - Entry data: ' . print_r($entry, true));
        }
        
        // Try to get CardName field first
        if (!empty($field_mapping['CardName'])) {
            $cardname_field_id = $field_mapping['CardName'];
            $cardname_value = rgar($entry, $cardname_field_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('DEBUG: CardName field ID: ' . $cardname_field_id . ', Value: ' . $cardname_value);
            }
            
            if (!empty($cardname_value)) {
                return esc_html($cardname_value);
            }
        }
        
        // Method 1: Look for name fields and combine them
        $first_name = '';
        $last_name = '';
        
        foreach ($form['fields'] as $field) {
            if ($field->type === 'name') {
                // Try different sub-field patterns
                $field_id = $field->id;
                
                // Standard Gravity Forms name field sub-fields
                $first_name = rgar($entry, $field_id . '.3') ?: rgar($entry, $field_id . '_3') ?: rgar($entry, $field_id . '3');
                $last_name = rgar($entry, $field_id . '.6') ?: rgar($entry, $field_id . '_6') ?: rgar($entry, $field_id . '6');
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('DEBUG: Name field ID ' . $field_id . ' - First: "' . $first_name . '", Last: "' . $last_name . '"');
                }
                
                if (!empty($first_name) || !empty($last_name)) {
                    break;
                }
            }
        }
        
        // Method 2: Look for separate first/last name fields
        if (empty($first_name) && empty($last_name)) {
            foreach ($form['fields'] as $field) {
                $field_label_lower = strtolower($field->label);
                $field_value = rgar($entry, $field->id);
                
                if (strpos($field_label_lower, 'first') !== false && strpos($field_label_lower, 'name') !== false && !empty($field_value)) {
                    $first_name = $field_value;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('DEBUG: Found first name field: ' . $field->label . ' = ' . $field_value);
                    }
                }
                if (strpos($field_label_lower, 'last') !== false && strpos($field_label_lower, 'name') !== false && !empty($field_value)) {
                    $last_name = $field_value;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('DEBUG: Found last name field: ' . $field->label . ' = ' . $field_value);
                    }
                }
            }
        }
        
        // Method 3: Look for any field with "name" in the label
        if (empty($first_name) && empty($last_name)) {
            foreach ($form['fields'] as $field) {
                $field_label_lower = strtolower($field->label);
                $field_value = rgar($entry, $field->id);
                
                if (strpos($field_label_lower, 'name') !== false && !empty($field_value)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('DEBUG: Found name field by label: ' . $field->label . ' = ' . $field_value);
                    }
                    return esc_html($field_value);
                }
            }
        }
        
        // Combine first and last name
        $full_name = trim($first_name . ' ' . $last_name);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('DEBUG: Final combined name: "' . $full_name . '"');
        }
        
        return !empty($full_name) ? esc_html($full_name) : '—';
    }
    
    /**
     * Get email value
     */
    private function get_email_value($entry, $form) {
        $settings = rgar($form, 'sap_integration_settings', array());
        $field_mapping = rgar($settings, 'field_mapping', array());
        
        if (!empty($field_mapping['EmailAddress'])) {
            $email_value = rgar($entry, $field_mapping['EmailAddress']);
            return !empty($email_value) ? esc_html($email_value) : '—';
        }
        
        return '—';
    }
    
    /**
     * Get company value
     */
    private function get_company_value($entry, $form) {
        // Look for company field in form
        foreach ($form['fields'] as $field) {
            if (stripos($field->label, 'company') !== false || 
                stripos($field->label, 'organization') !== false ||
                stripos($field->label, 'business') !== false) {
                $company_value = rgar($entry, $field->id);
                return !empty($company_value) ? esc_html($company_value) : '—';
            }
        }
        
        return '—';
    }
    
    /**
     * Get country value
     */
    private function get_country_value($entry, $form) {
        $settings = rgar($form, 'sap_integration_settings', array());
        $field_mapping = rgar($settings, 'field_mapping', array());
        
        if (!empty($field_mapping['BPAddresses.Country'])) {
            $country_value = rgar($entry, $field_mapping['BPAddresses.Country']);
            return !empty($country_value) ? esc_html($country_value) : '—';
        }
        
        return '—';
    }
    
    /**
     * Get SAP status display
     */
    private function get_sap_status_display($entry) {
        $sap_status = rgar($entry, 'sap_b1_status', '');
        $sap_cardcode = rgar($entry, 'sap_b1_cardcode', '');
        $sap_error = rgar($entry, 'sap_b1_error', '');
        $entry_id = rgar($entry, 'id');
        
        if (!empty($sap_cardcode)) {
            return '<span style="color: green; font-weight: bold;">✅ SUCCESS</span><br><small>CardCode: ' . esc_html($sap_cardcode) . '</small>';
        } elseif (!empty($sap_error)) {
            $retry_button = '<br><button type="button" class="button button-small retry-sap-submission" data-entry-id="' . esc_attr($entry_id) . '" style="margin-top: 5px;">🔄 Retry</button>';
            return '<span style="color: red; font-weight: bold;">❌ FAILED</span><br><small>' . esc_html($sap_error) . '</small>' . $retry_button;
        } else {
            return '<span style="color: orange;">⏳ PENDING</span>';
        }
    }
    
    /**
     * Get date created
     */
    private function get_date_created($entry) {
        $date_created = rgar($entry, 'date_created');
        return !empty($date_created) ? esc_html(gf_apply_filters(array('gform_date_display', $entry['form_id']), $date_created, $entry)) : '—';
    }

    /**
     * AJAX handler for retrying SAP submission
     *
     * @since 1.1.0
     */
    public function ajax_retry_sap_submission() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'retry_sap_submission')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('gform_full_access')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
        if (empty($entry_id)) {
            wp_send_json_error('Invalid entry ID');
        }
        
        try {
            // Get entry and form data
            $entry = GFAPI::get_entry($entry_id);
            if (is_wp_error($entry)) {
                wp_send_json_error('Entry not found');
            }
            
            $form = GFAPI::get_form($entry['form_id']);
            if (is_wp_error($form)) {
                wp_send_json_error('Form not found');
            }
            
            // Clear previous error status
            $this->update_entry_sap_status($entry_id, 'processing', '', '');
            
            // Retry the submission
            $this->process_form_submission($entry, $form);
            
            // Get updated status
            $updated_entry = GFAPI::get_entry($entry_id);
            $sap_status = rgar($updated_entry, 'sap_b1_status', '');
            $sap_cardcode = rgar($updated_entry, 'sap_b1_cardcode', '');
            $sap_error = rgar($updated_entry, 'sap_b1_error', '');
            
            if (!empty($sap_cardcode)) {
                wp_send_json_success(array(
                    'message' => 'Submission successful!',
                    'cardcode' => $sap_cardcode,
                    'status' => 'success'
                ));
            } elseif (!empty($sap_error)) {
                wp_send_json_error(array(
                    'message' => 'Submission failed: ' . $sap_error,
                    'error' => $sap_error,
                    'status' => 'failed'
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'Submission status unknown',
                    'status' => 'unknown'
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Retry failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX handler for loading ItemCodes from SAP B1
     *
     * @since 1.2.2
     */
    public function ajax_load_itemcodes() {
        // Increase execution time for large datasets
        set_time_limit(120); // 2 minutes
        
        // Add debugging
        error_log('AJAX ITEMCODE: Handler called');
        shift8_gravitysap_debug_log('AJAX ItemCode loading started');
        
        // Verify nonce
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'shift8_gravitysap_load_itemcodes')) {
            error_log('AJAX ITEMCODE: Nonce verification failed');
            shift8_gravitysap_debug_log('ItemCode loading failed: Invalid nonce');
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            error_log('AJAX ITEMCODE: Insufficient permissions');
            shift8_gravitysap_debug_log('ItemCode loading failed: Insufficient permissions');
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        error_log('AJAX ITEMCODE: Security checks passed');
        shift8_gravitysap_debug_log('ItemCode loading: Security checks passed');
        
        try {
            // Force refresh ItemCodes
            $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === 'true';
            
            // Clear persistent storage if forcing refresh
            if ($force_refresh) {
                delete_option('shift8_gravitysap_item_codes_data');
                shift8_gravitysap_debug_log('Cleared ItemCode cache for forced refresh');
            }
            
            // Load ItemCodes using the existing method
            $items = $this->get_sap_item_codes();
            
            if (empty($items)) {
                shift8_gravitysap_debug_log('No ItemCodes returned from SAP');
                wp_send_json_error(array(
                    'message' => 'No ItemCodes found. Please check your SAP B1 connection settings.'
                ));
                return;
            }
            
            // Get metadata about the stored data
            $stored_data = get_option('shift8_gravitysap_item_codes_data', array());
            $last_updated = !empty($stored_data['last_updated_formatted']) ? $stored_data['last_updated_formatted'] : 'Unknown';
            $pages_fetched = !empty($stored_data['pages_fetched']) ? $stored_data['pages_fetched'] : 0;
            
            // Return success with ItemCodes and metadata
            error_log('AJAX ITEMCODE: Returning success with ' . count($items) . ' items');
            shift8_gravitysap_debug_log('AJAX ItemCode loading completed successfully', array(
                'total_items' => count($items),
                'pages_fetched' => $pages_fetched
            ));
            
            wp_send_json_success(array(
                'items' => $items,
                'count' => count($items),
                'pages_fetched' => $pages_fetched,
                'last_updated' => $last_updated,
                'storage_type' => 'persistent',
                'message' => sprintf('Successfully loaded %d ItemCodes from SAP B1 in %d pages (Last updated: %s)', count($items), $pages_fetched, $last_updated)
            ));
            
        } catch (Exception $e) {
            error_log('AJAX ITEMCODE: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to load ItemCodes: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Add retry button JavaScript
     *
     * @since 1.1.0
     */
    public function add_retry_button_script() {
        $screen = get_current_screen();
        // Load on both entries pages AND settings pages
        if (!$screen || (strpos($screen->id, 'gf_entries') === false && strpos($screen->id, 'gf_edit_forms') === false)) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle retry button clicks
            jQuery(document).on('click', '.retry-sap-submission', function(e) {
                e.preventDefault();
                
                var button = jQuery(this);
                var entryId = button.data('entry-id');
                var originalText = button.text();
                
                // Disable button and show loading
                button.prop('disabled', true).text('⏳ Retrying...');
                
                // Make AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'retry_sap_submission',
                        entry_id: entryId,
                        nonce: '<?php echo wp_create_nonce('retry_sap_submission'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            button.text('✅ Success!').css('color', 'green');
                            
                            // Reload page after 2 seconds to show updated status
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            // Show error message
                            button.text('❌ Failed').css('color', 'red');
                            alert('Retry failed: ' + (response.data.message || 'Unknown error'));
                            
                            // Reset button after 3 seconds
                            setTimeout(function() {
                                button.prop('disabled', false).text(originalText).css('color', '');
                            }, 3000);
                        }
                    },
                    error: function() {
                        // Show error message
                        button.text('❌ Error').css('color', 'red');
                        alert('Network error occurred');
                        
                        // Reset button after 3 seconds
                        setTimeout(function() {
                            button.prop('disabled', false).text(originalText).css('color', '');
                        }, 3000);
                    }
                });
            });
        });
        
        // ItemCode loading functions (load on all admin pages for now)
        <?php if (is_admin()): ?>
        
        // Add CSS for spinning animation
        var style = document.createElement('style');
        style.textContent = '@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }';
        document.head.appendChild(style);
        
        console.log('ItemCode JavaScript loaded on:', window.location.href);
        
        // Global function to load ALL ItemCodes (master button)
        window.loadAllItemCodes = function(forceRefresh = false) {
            console.log('loadAllItemCodes called with forceRefresh:', forceRefresh);
            
            var loadButton = jQuery('.load-all-itemcodes-btn');
            var reloadButton = jQuery('.reload-all-itemcodes-btn');
            var status = jQuery('.itemcode-global-status');
            var dropdowns = jQuery('.itemcode-dropdown');
            
            console.log('Found elements:', {
                loadButton: loadButton.length,
                reloadButton: reloadButton.length,
                status: status.length,
                dropdowns: dropdowns.length
            });
            
            // Disable buttons and show loading with animation
            loadButton.prop('disabled', true).html('<span class="dashicons dashicons-update" style="vertical-align: middle; animation: rotation 2s infinite linear;"></span> Loading...');
            reloadButton.prop('disabled', true).html('<span class="dashicons dashicons-update" style="vertical-align: middle; animation: rotation 2s infinite linear;"></span> Loading...');
            status.html('<span class="dashicons dashicons-update" style="vertical-align: middle; animation: rotation 2s infinite linear;"></span> Loading ItemCodes from SAP B1... This may take 30-60 seconds for large datasets.');
            
            console.log('Making AJAX request to:', ajaxurl);
            
            // Make AJAX request
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'load_itemcodes',
                    force_refresh: forceRefresh ? 'true' : 'false',
                    nonce: '<?php echo wp_create_nonce('shift8_gravitysap_load_itemcodes'); ?>'
                },
                timeout: 60000, // 60 second timeout (increased for larger datasets)
                success: function(response) {
                    console.log('AJAX response:', response);
                    
                    // Handle case where response is not JSON
                    if (typeof response === 'string') {
                        try {
                            response = JSON.parse(response);
                        } catch (e) {
                            console.log('Failed to parse response as JSON:', response);
                            status.html('<span style="color: #d63638;">❌ Invalid response from server</span>');
                            loadButton.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Load ItemCodes from SAP B1');
                            reloadButton.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Reload ItemCodes');
                            return;
                        }
                    }
                    
                    if (response && response.success) {
                        // Update all ItemCode dropdowns
                        dropdowns.each(function() {
                            var dropdown = jQuery(this);
                            var selectedValue = dropdown.val();
                            
                            // Clear existing options (except first)
                            dropdown.find('option:not(:first)').remove();
                            
                            // Add new options
                            jQuery.each(response.data.items, function(itemCode, itemName) {
                                var selected = selectedValue === itemCode ? 'selected' : '';
                                dropdown.append('<option value="' + itemCode + '" ' + selected + '>' + itemCode + ' - ' + itemName + '</option>');
                            });
                        });
                        
                        // Remove warning messages from individual fields
                        jQuery('.itemcode-dropdown').next('p.description').remove();
                        
                        // Update the management section to show loaded state
                        var pagesInfo = response.data.pages_fetched ? ' (fetched in ' + response.data.pages_fetched + ' pages)' : '';
                        jQuery('.itemcode-status-section').html(
                            '<p style="margin: 10px 0;">' +
                            '<span class="dashicons dashicons-yes-alt" style="color: #00a32a; vertical-align: middle;"></span> ' +
                            'Successfully loaded <strong>' + response.data.count + '</strong> ItemCodes from SAP B1' + pagesInfo +
                            '</p>' +
                            '<button type="button" class="button button-secondary reload-all-itemcodes-btn" onclick="loadAllItemCodes(true)" style="margin-right: 10px;">' +
                            '<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Reload ItemCodes' +
                            '</button>' +
                            '<input type="text" class="itemcode-global-search" placeholder="Search all ItemCodes..." onkeyup="filterAllItemCodes(this.value)" style="width: 250px; margin-right: 10px;">' +
                            '<span class="itemcode-global-status" style="color: #00a32a;">Last updated: ' + new Date().toLocaleString() + '</span>'
                        );
                        
                    } else {
                        status.html('<span style="color: #d63638;">❌ ' + response.data.message + '</span>');
                        loadButton.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Load ItemCodes from SAP B1');
                        reloadButton.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Reload ItemCodes');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error:', {xhr: xhr, status: status, error: error, responseText: xhr.responseText});
                    
                    var errorMessage = 'Network error occurred';
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out (30s) - SAP may be slow';
                    } else if (status === 'parsererror') {
                        errorMessage = 'Invalid response from server';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Permission denied - check user capabilities';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error - check error logs';
                    } else if (xhr.status !== 0) {
                        errorMessage = 'HTTP ' + xhr.status + ' error';
                    }
                    
                    status.html('<span style="color: #d63638;">❌ ' + errorMessage + '</span>');
                    loadButton.prop('disabled', false).html('<span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Load ItemCodes from SAP B1');
                    reloadButton.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Reload ItemCodes');
                },
                complete: function() {
                    console.log('AJAX request completed');
                }
            });
        };
        
        // Global function to filter ALL ItemCodes
        window.filterAllItemCodes = function(searchTerm) {
            searchTerm = searchTerm.toLowerCase();
            
            jQuery('.itemcode-dropdown').each(function() {
                var dropdown = jQuery(this);
                dropdown.find('option').each(function() {
                    var option = jQuery(this);
                    var text = option.text().toLowerCase();
                    
                    if (text.indexOf(searchTerm) !== -1 || option.val() === '') {
                        option.show();
                    } else {
                        option.hide();
                    }
                });
            });
        };
        
        <?php endif; ?>
        
        </script>
        <?php
    }

    /**
     * Debug entry data to help troubleshoot name field issues
     *
     * @since 1.1.0
     * @param array $form Form data
     * @return string Debug information
     */
    private function debug_entry_data($form) {
        $settings = rgar($form, 'sap_integration_settings', array());
        $field_mapping = rgar($settings, 'field_mapping', array());
        
        $debug_info = "=== DEBUG ENTRY DATA ===\n\n";
        
        // Show field mapping
        $debug_info .= "FIELD MAPPING:\n";
        foreach ($field_mapping as $sap_field => $field_id) {
            $debug_info .= "- {$sap_field} → Field ID: {$field_id}\n";
        }
        $debug_info .= "\n";
        
        // Show form fields
        $debug_info .= "FORM FIELDS:\n";
        foreach ($form['fields'] as $field) {
            $debug_info .= "- ID: {$field->id}, Type: {$field->type}, Label: {$field->label}\n";
        }
        $debug_info .= "\n";
        
        // Get recent entries
        $entries = GFAPI::get_entries($form['id'], array(), null, array('page_size' => 3));
        
        if (!empty($entries)) {
            $debug_info .= "RECENT ENTRIES:\n";
            foreach ($entries as $entry) {
                $debug_info .= "\nEntry ID: {$entry['id']}\n";
                $debug_info .= "Date: {$entry['date_created']}\n";
                
                // Show all entry data
                foreach ($entry as $key => $value) {
                    if (!empty($value) && !in_array($key, array('id', 'form_id', 'date_created', 'date_updated', 'is_starred', 'is_read', 'ip', 'source_url', 'user_agent', 'payment_status', 'payment_date', 'payment_amount', 'payment_method', 'transaction_id', 'is_fulfilled', 'created_by', 'transaction_type', 'status'))) {
                        $debug_info .= "  {$key}: {$value}\n";
                    }
                }
                
                // Test name field specifically
                $debug_info .= "\nNAME FIELD TEST:\n";
                $name_result = $this->get_combined_name($entry, $form);
                $debug_info .= "Result: {$name_result}\n";
                
                // Check CardName mapping specifically
                if (!empty($field_mapping['CardName'])) {
                    $cardname_field_id = $field_mapping['CardName'];
                    $cardname_value = rgar($entry, $cardname_field_id);
                    $debug_info .= "CardName field ID {$cardname_field_id}: '{$cardname_value}'\n";
                }
            }
        } else {
            $debug_info .= "No entries found for this form.\n";
        }
        
        return $debug_info;
    }

    /**
     * Test SAP submission with most recent entry
     *
     * @since 1.1.0
     * @param array $form Form data
     * @return array Test result
     */
    private function test_sap_submission($form) {
        try {
            $settings = rgar($form, 'sap_integration_settings', array());
            
            if (empty($settings['enabled']) || $settings['enabled'] !== '1') {
                return array(
                    'success' => false,
                    'message' => 'SAP Integration is not enabled for this form'
                );
            }
            
            // Get the most recent entry
            $entries = GFAPI::get_entries($form['id'], array(), null, array('page_size' => 1));
            
            if (empty($entries)) {
                return array(
                    'success' => false,
                    'message' => 'No entries found for this form'
                );
            }
            
            $entry = $entries[0];
            
            // Test the submission process
            $this->process_form_submission($entry, $form);
            
            // Get updated entry to check status
            $updated_entry = GFAPI::get_entry($entry['id']);
            $sap_status = rgar($updated_entry, 'sap_b1_status', '');
            $sap_cardcode = rgar($updated_entry, 'sap_b1_cardcode', '');
            $sap_error = rgar($updated_entry, 'sap_b1_error', '');
            
            if (!empty($sap_cardcode)) {
                return array(
                    'success' => true,
                    'message' => 'Business Partner created successfully with CardCode: ' . $sap_cardcode
                );
            } elseif (!empty($sap_error)) {
                return array(
                    'success' => false,
                    'message' => 'Submission failed: ' . $sap_error
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'Submission status unknown - check debug logs'
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Test field mapping configuration
     *
     * @since 1.1.0
     * @param array $form Form data
     * @return array Test result
     */
    private function test_field_mapping($form) {
        try {
            $settings = rgar($form, 'sap_integration_settings', array());
            
            if (empty($settings['enabled']) || $settings['enabled'] !== '1') {
                return array(
                    'success' => false,
                    'message' => 'SAP Integration is not enabled for this form'
                );
            }
            
            // Get the most recent real entry instead of using sample data
            $entries = GFAPI::get_entries($form['id'], array(), null, array('page_size' => 1));
            
            if (empty($entries)) {
                return array(
                    'success' => false,
                    'message' => 'No entries found for this form. Please submit a test form first.'
                );
            }
            
            $real_entry = $entries[0];
            
            // Test field mapping validation with real entry data
            $mapping_validation = $this->validate_field_mapping($settings, $real_entry, $form);
            
            $message = sprintf(
                'Mapped: %d fields, Unmapped: %d fields, Empty: %d fields',
                $mapping_validation['total_mapped'],
                $mapping_validation['total_unmapped'],
                $mapping_validation['total_empty']
            );
            
            // Add detailed field mapping information
            $message .= "\n\nFIELD MAPPING DETAILS:\n";
            $field_mapping = rgar($settings, 'field_mapping', array());
            foreach ($field_mapping as $sap_field => $field_id) {
                $field_value = rgar($real_entry, $field_id);
                $field_label = $this->get_field_label($form, $field_id);
                $message .= "- {$sap_field} → Field ID {$field_id} ({$field_label}): '{$field_value}'\n";
            }
            
            // Show all entry data to help debug field IDs
            $message .= "\n\nALL ENTRY DATA (non-empty fields):\n";
            foreach ($real_entry as $key => $value) {
                if (!empty($value) && !in_array($key, array('id', 'form_id', 'date_created', 'date_updated', 'is_starred', 'is_read', 'ip', 'source_url', 'user_agent', 'payment_status', 'payment_date', 'payment_amount', 'payment_method', 'transaction_id', 'is_fulfilled', 'created_by', 'transaction_type', 'status'))) {
                    $message .= "- Field ID {$key}: '{$value}'\n";
                }
            }
            
            // Add detailed information
            if (!empty($mapping_validation['mapped_fields'])) {
                $message .= "\n\nMapped Fields:\n";
                foreach ($mapping_validation['mapped_fields'] as $field) {
                    $message .= sprintf("- %s (%s) → %s: %s\n", 
                        $field['sap_label'], 
                        $field['sap_field'], 
                        $field['gf_field_label'], 
                        $field['value']
                    );
                }
            }
            
            if (!empty($mapping_validation['unmapped_fields'])) {
                $message .= "\nUnmapped Fields:\n";
                foreach ($mapping_validation['unmapped_fields'] as $field) {
                    $message .= sprintf("- %s (%s): %s\n", 
                        $field['sap_label'], 
                        $field['sap_field'], 
                        $field['reason']
                    );
                }
            }
            
            if (!empty($mapping_validation['empty_fields'])) {
                $message .= "\nEmpty Fields:\n";
                foreach ($mapping_validation['empty_fields'] as $field) {
                    $message .= sprintf("- %s (%s) → %s: %s\n", 
                        $field['sap_label'], 
                        $field['sap_field'], 
                        $field['gf_field_label'], 
                        $field['reason']
                    );
                }
            }
            
            return array(
                'success' => true,
                'message' => $message,
                'validation' => $mapping_validation
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Field mapping test failed: ' . esc_html($e->getMessage())
            );
        }
    }

    /**
     * Test SAP Integration with custom test values
     *
     * @since 1.0.0
     * @param array $form Form data
     * @return array Test result
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
            $test_values = $this->sanitize_test_values(rgpost('test_values', array()));
            
            if (empty($test_values)) {
                return array(
                    'success' => false,
                    'message' => 'No test values provided.'
                );
            }
            
            // Create business partner data from test values
            $business_partner_data = $this->map_test_values_to_business_partner($settings, $test_values);
            
            // Decrypt password for testing (same as ajax_test_connection does)
            $plugin_settings['sap_password'] = shift8_gravitysap_decrypt_password($plugin_settings['sap_password']);
            
            // Initialize SAP service
            require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($plugin_settings);
            
            // Create Business Partner in SAP
            $result = $sap_service->create_business_partner($business_partner_data);
            
            if ($result && isset($result['CardCode'])) {
                return array(
                    'success' => true,
                    'message' => 'Card Code: ' . esc_html($result['CardCode'])
                );
            } else {
                return array(
                    'success' => false,
                    'message' => 'SAP service did not return a valid result'
                );
            }
            
        } catch (Exception $e) {
            shift8_gravitysap_debug_log('Test failed with exception', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Sanitize test values
     *
     * @since 1.0.0
     * @param array $test_values Raw test values
     * @return array Sanitized test values
     */
    private function sanitize_test_values($test_values) {
        if (!is_array($test_values)) {
            return array();
        }
        
        $sanitized = array();
        $allowed_fields = $this->get_allowed_sap_fields();
        
        foreach ($test_values as $field => $value) {
            if (in_array($field, $allowed_fields, true)) {
                if ($field === 'EmailAddress') {
                    $sanitized[$field] = sanitize_email($value);
                } else {
                    $sanitized[$field] = sanitize_text_field($value);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Map test values to Business Partner structure
     *
     * @since 1.0.0
     * @param array $settings    Form settings
     * @param array $test_values Test values
     * @return array Business Partner data structure
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
                    // Initialize with required fields for SAP Business One
                    // AddressName is REQUIRED for SAP to save the address properly
                    $business_partner['BPAddresses'] = array(array(
                        'AddressType' => 'bo_BillTo',
                        'AddressName' => 'Primary', // Required field
                        'RowNum' => 0
                    ));
                }
                
                $business_partner['BPAddresses'][0][$address_field] = $test_value;
            } else {
                $business_partner[$sap_field] = $test_value;
            }
        }
        
        return $business_partner;
    }

    /**
     * Test SAP numbering series configuration
     *
     * @since 1.0.0
     * @return array Test result
     */
    public function test_numbering_series() {
        shift8_gravitysap_debug_log('=== TESTING SAP NUMBERING SERIES ===');
        
        try {
            // Get plugin settings
            $plugin_settings = get_option('shift8_gravitysap_settings', array());
            
            if (empty($plugin_settings['sap_endpoint']) || empty($plugin_settings['sap_username']) || empty($plugin_settings['sap_password'])) {
                return array(
                    'success' => false,
                    'message' => 'SAP connection settings are incomplete. Please configure SAP settings first.'
                );
            }
            
            // Decrypt password for testing (same as ajax_test_connection does)
            $plugin_settings['sap_password'] = shift8_gravitysap_decrypt_password($plugin_settings['sap_password']);
            
            // Initialize SAP service
            require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($plugin_settings);
            
            // Test numbering series
            $result = $sap_service->test_numbering_series();
            
            shift8_gravitysap_debug_log('Numbering series test complete', $result);
            return $result;
            
        } catch (Exception $e) {
            shift8_gravitysap_debug_log('Numbering series test failed with exception', array(
                'error' => $e->getMessage()
            ));
            
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }

    /**
     * Plugin deactivation
     *
     * Cleans up scheduled events and temporary data.
     *
     * @since 1.0.0
     */
    public function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook('shift8_gravitysap_cleanup_logs');
        
        shift8_gravitysap_debug_log('Plugin deactivated');
    }

    /**
     * Validate SAP field limits for Gravity Forms
     *
     * This function is hooked into gform_validation to validate
     * form field values against SAP Business One field limits.
     *
     * @since 1.1.0
     * @param array $validation_result The current validation result
     * @return array The modified validation result
     */
    public function validate_sap_field_limits($validation_result) {
        shift8_gravitysap_debug_log('=== SAP FIELD VALIDATION STARTED ===');
        
        $form = $validation_result['form'];
        $form_id = rgar($form, 'id', 'unknown');
        shift8_gravitysap_debug_log('Validating form', array('form_id' => $form_id));
        
        $settings = rgar($form, 'sap_integration_settings', array());
        
        // Skip validation if SAP integration is not enabled
        if (empty($settings['enabled']) || $settings['enabled'] !== '1') {
            shift8_gravitysap_debug_log('SAP integration disabled for this form, skipping validation');
            return $validation_result;
        }
        
        $field_mapping = rgar($settings, 'field_mapping', array());
        if (empty($field_mapping)) {
            shift8_gravitysap_debug_log('No field mapping configured, skipping validation');
            return $validation_result;
        }

        shift8_gravitysap_debug_log('Field mapping found', array('mapping' => $field_mapping));

        $sap_field_limits = $this->get_sap_field_limits();
        $validation_errors = 0;

        foreach ($field_mapping as $sap_field => $field_id) {
            if (!isset($sap_field_limits[$sap_field])) {
                shift8_gravitysap_debug_log('No limits defined for SAP field', array('sap_field' => $sap_field));
                continue;
            }
            
            $field = RGFormsModel::get_field($form, $field_id);
            if (!$field) {
                shift8_gravitysap_debug_log('Could not find form field', array('field_id' => $field_id));
                continue;
            }
            
            // Get field label early for consistent use throughout validation
            $field_label = GFCommon::get_label($field);
            
            // Debug field type and input structure
            shift8_gravitysap_debug_log('Field details', array(
                'field_id' => $field_id,
                'field_type' => $field->type,
                'field_label' => $field_label
            ));
            
            $field_value = rgpost("input_{$field_id}");
            
            // For composite fields (name, address), try to get the value differently
            if (empty($field_value) && in_array($field->type, array('name', 'address'), true)) {
                // Try to get the complete value for name fields
                if ($field->type === 'name') {
                    $first = rgpost("input_{$field_id}.3");  // First name
                    $last = rgpost("input_{$field_id}.6");   // Last name
                    if (!empty($first) || !empty($last)) {
                        $field_value = trim($first . ' ' . $last);
                    }
                }
                shift8_gravitysap_debug_log('Composite field value retrieved', array(
                    'field_id' => $field_id,
                    'composite_value' => $field_value,
                    'first_name' => rgpost("input_{$field_id}.3"),
                    'last_name' => rgpost("input_{$field_id}.6")
                ));
            }
            
            // Allow theme functions to provide field values during validation
            // This is useful for fields that are populated by gform_pre_submission hooks
            if (empty($field_value)) {
                /**
                 * Filter: shift8_gravitysap_get_field_value_for_validation
                 * 
                 * Allows theme functions to provide field values during validation
                 * for fields that might be populated later in the form submission process.
                 * 
                 * @param string $field_value Current field value (empty)
                 * @param int    $field_id    Gravity Forms field ID
                 * @param object $field       Gravity Forms field object
                 * @param array  $form        Gravity Forms form array
                 * @param string $sap_field   SAP field name this is mapped to
                 */
                $field_value = apply_filters(
                    'shift8_gravitysap_get_field_value_for_validation',
                    $field_value,
                    $field_id,
                    $field,
                    $form,
                    $sap_field
                );
                
                if (!empty($field_value)) {
                    shift8_gravitysap_debug_log('Field value provided by theme filter', array(
                        'field_id' => $field_id,
                        'field_label' => $field_label,
                        'provided_value' => $field_value,
                        'sap_field' => $sap_field
                    ));
                }
            }
            
            // For hidden or calculated fields that are still empty after all attempts,
            // skip validation if they're likely to be populated by theme functions
            if (empty($field_value)) {
                if ($field->type === 'hidden' || (isset($field->visibility) && $field->visibility === 'hidden')) {
                    shift8_gravitysap_debug_log('Skipping validation for hidden field (likely populated by theme function)', array(
                        'field_id' => $field_id,
                        'field_type' => $field->type,
                        'field_label' => $field_label,
                        'sap_field' => $sap_field
                    ));
                    continue; // Skip validation for hidden fields that might be populated later
                }
            }
            
            // Debug all input_XX values for this field
            $all_inputs = array();
            foreach ($_POST as $key => $value) {
                if (strpos($key, "input_{$field_id}") === 0) {
                    $all_inputs[$key] = $value;
                }
            }
            
            $limits = $sap_field_limits[$sap_field];
            
            shift8_gravitysap_debug_log('Validating field', array(
                'sap_field' => $sap_field,
                'field_id' => $field_id,
                'field_label' => $field_label,
                'field_value' => $field_value,
                'field_type' => $field->type,
                'all_related_inputs' => $all_inputs,
                'limits' => $limits
            ));

            // Check required fields
            if ($limits['required'] && empty($field_value)) {
                $validation_result['is_valid'] = false;
                $field->failed_validation = true;
                $field->validation_message = sprintf(
                    esc_html__('%s is required for SAP Business Partner creation.', 'shift8-gravity-forms-sap-b1-integration'),
                    esc_html($field_label)
                );
                $validation_errors++;
                shift8_gravitysap_debug_log('Required field validation failed', array('field' => $field_label));
                continue;
            }

            // Skip validation if field is empty and not required
            if (empty($field_value)) {
                shift8_gravitysap_debug_log('Field is empty but not required, skipping');
                continue;
            }

            // Check field length limits
            $field_length = strlen($field_value);
            $max_length = $limits['max_length'];

            if ($field_length > $max_length) {
                $validation_result['is_valid'] = false;
                $field->failed_validation = true;
                
                $custom_message = isset($limits['validation_message']) ? $limits['validation_message'] : '';
                if ($custom_message) {
                    $field->validation_message = sprintf(
                        esc_html__('%s: %s (Current: %d chars, Max: %d)', 'shift8-gravity-forms-sap-b1-integration'),
                        esc_html($field_label),
                        esc_html($custom_message),
                        $field_length,
                        $max_length
                    );
                } else {
                    $field->validation_message = sprintf(
                        esc_html__('%s cannot exceed %d characters (currently %d characters).', 'shift8-gravity-forms-sap-b1-integration'),
                        esc_html($field_label),
                        $max_length,
                        $field_length
                    );
                }
                $validation_errors++;
                shift8_gravitysap_debug_log('Length validation failed', array(
                    'field' => $field_label,
                    'current_length' => $field_length,
                    'max_length' => $max_length
                ));
                continue;
            }

            // Check email format
            if (isset($limits['format']) && $limits['format'] === 'email' && !is_email($field_value)) {
                $validation_result['is_valid'] = false;
                $field->failed_validation = true;
                $field->validation_message = sprintf(
                    esc_html__('%s must be a valid email address.', 'shift8-gravity-forms-sap-b1-integration'),
                    esc_html($field_label)
                );
                $validation_errors++;
                shift8_gravitysap_debug_log('Email validation failed', array('field' => $field_label, 'value' => $field_value));
                continue;
            }

            // Check URL format
            if (isset($limits['format']) && $limits['format'] === 'url' && !filter_var($field_value, FILTER_VALIDATE_URL)) {
                $validation_result['is_valid'] = false;
                $field->failed_validation = true;
                $field->validation_message = sprintf(
                    esc_html__('%s must be a valid URL.', 'shift8-gravity-forms-sap-b1-integration'),
                    esc_html($field_label)
                );
                $validation_errors++;
                shift8_gravitysap_debug_log('URL validation failed', array('field' => $field_label, 'value' => $field_value));
                continue;
            }
            
            shift8_gravitysap_debug_log('Field validation passed', array('field' => $field_label));
        }

        shift8_gravitysap_debug_log('=== SAP FIELD VALIDATION COMPLETED ===', array(
            'total_errors' => $validation_errors,
            'is_valid' => $validation_result['is_valid']
        ));

        return $validation_result;
    }
}

// Initialize plugin
shift8_gravitysap_debug_log('Plugin file loaded, initializing...');
Shift8_GravitySAP::get_instance();

// Load WP-CLI command if in CLI context
if (defined('WP_CLI') && WP_CLI) {
    require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'cli-test-submission.php';
} 
