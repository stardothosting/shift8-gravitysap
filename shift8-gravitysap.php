<?php
/**
 * Plugin Name: Shift8 Integration for Gravity Forms and SAP Business One
 * Plugin URI: https://github.com/stardothosting/shift8-gravitysap
 * Description: Integrates Gravity Forms with SAP Business One, automatically creating Business Partners from form submissions.
 * Version: 1.1.1
 * Author: Shift8 Web
 * Author URI: https://shift8web.ca
 * Text Domain: shift8-gravity-forms-sap-b1-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
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
define('SHIFT8_GRAVITYSAP_VERSION', '1.1.1');
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
 * Checks if debug logging is enabled before logging. Automatically
 * sanitizes sensitive data to prevent credential exposure.
 *
 * @since 1.0.0
 * @param string $message The log message
 * @param mixed  $data    Optional data to include in the log
 */
function shift8_gravitysap_debug_log($message, $data = null) {
    // Check if debug logging is enabled
    $settings = get_option('shift8_gravitysap_settings', array());
    
    // Debug the debug setting itself (only if WP_DEBUG is enabled) - but sanitize sensitive data
    if ($message === 'Debug setting check' && defined('WP_DEBUG') && WP_DEBUG) {
        $sanitized_settings = shift8_gravitysap_sanitize_log_data($settings);
        // Debug output removed for WordPress.org compliance
    }
    
    if (!isset($settings['sap_debug']) || $settings['sap_debug'] !== '1') {
        return;
    }
    
    // Sanitize sensitive data from logs
    if ($data !== null) {
        $data = shift8_gravitysap_sanitize_log_data($data);
    }

    // Format the log message
    $timestamp = current_time('Y-m-d H:i:s');
    $log_message = '[' . $timestamp . '] [Shift8 GravitySAP] ' . sanitize_text_field($message);
    if ($data !== null) {
        $log_message .= ' - Data: ' . wp_json_encode($data);
    }
    $log_message .= PHP_EOL;

    // Get WordPress uploads directory
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/shift8-gravitysap-debug.log';

    // Ensure the uploads directory exists and is writable
    if (!is_dir($upload_dir['basedir'])) {
        wp_mkdir_p($upload_dir['basedir']);
    }

    // Write to custom log file using WordPress file system
    global $wp_filesystem;
    if (empty($wp_filesystem)) {
        require_once ABSPATH . '/wp-admin/includes/file.php';
        WP_Filesystem();
    }
    
    if ($wp_filesystem && $wp_filesystem->is_writable($upload_dir['basedir'])) {
        if ($wp_filesystem->exists($log_file)) {
            $existing_content = $wp_filesystem->get_contents($log_file);
            $wp_filesystem->put_contents($log_file, $existing_content . $log_message, FS_CHMOD_FILE);
        } else {
            $wp_filesystem->put_contents($log_file, $log_message, FS_CHMOD_FILE);
        }
    } else {
        // Fallback to system error log if custom log file isn't writable (only if WP_DEBUG is enabled)
        // Debug output removed for WordPress.org compliance
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
        // Verify user capabilities
        if (!current_user_can('manage_options')) {
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
                GFCommon::add_message(esc_html__('Test successful! Business Partner created in SAP: ', 'shift8-gravity-forms-sap-b1-integration') . esc_html($test_result['message']));
            } else {
                GFCommon::add_error_message(esc_html__('Test failed: ', 'shift8-gravity-forms-sap-b1-integration') . esc_html($test_result['message']));
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
                    <th scope="row"><?php esc_html_e('Field Mapping', 'shift8-gravity-forms-sap-b1-integration'); ?></th>
                    <td>
                        <?php $this->render_field_mapping_table($form, $settings); ?>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="gform-settings-save" value="<?php esc_attr_e('Update Settings', 'shift8-gravity-forms-sap-b1-integration'); ?>" class="button-primary" />
            </p>
        </form>
        
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
            'BPAddresses.Street' => esc_html__('Street Address', 'shift8-gravity-forms-sap-b1-integration'),
            'BPAddresses.City' => esc_html__('City', 'shift8-gravity-forms-sap-b1-integration'),
            'BPAddresses.State' => esc_html__('State/Province', 'shift8-gravity-forms-sap-b1-integration'),
            'BPAddresses.ZipCode' => esc_html__('Zip/Postal Code', 'shift8-gravity-forms-sap-b1-integration'),
            'BPAddresses.Country' => esc_html__('Country', 'shift8-gravity-forms-sap-b1-integration'),
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
        );
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
                                    if (in_array($field->type, array('text', 'email', 'phone', 'address', 'name', 'hidden', 'website'), true)) {
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
     * Render test sections (numbering series and integration tests)
     *
     * @since 1.0.0
     * @param int   $form_id  Form ID
     * @param array $settings Current settings
     */
    private function render_test_sections($form_id, $settings) {
        ?>
        <hr style="margin: 30px 0;" />
        
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
            'field_mapping' => array()
        );
        
        // Validate and sanitize field mapping
        $field_mapping = rgpost('sap_field_mapping');
        if (is_array($field_mapping)) {
            $allowed_sap_fields = $this->get_allowed_sap_fields();
            
            foreach ($field_mapping as $sap_field => $field_id) {
                if (in_array($sap_field, $allowed_sap_fields, true) && is_numeric($field_id) && $field_id > 0) {
                    $settings['field_mapping'][$sap_field] = absint($field_id);
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
        
        if (empty($settings['enabled']) || $settings['enabled'] !== '1') {
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
            
            // Decrypt password for production use (same as ajax_test_connection does)
            $plugin_settings['sap_password'] = shift8_gravitysap_decrypt_password($plugin_settings['sap_password']);
            
            // Initialize SAP service
            require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
            $sap_service = new Shift8_GravitySAP_SAP_Service($plugin_settings);
            
            // Create Business Partner in SAP
            $result = $sap_service->create_business_partner($business_partner_data);
            
            if ($result && isset($result['CardCode'])) {
                // Add success note to entry
                GFFormsModel::add_note(
                    $entry['id'], 
                    0, 
                    'Shift8 SAP Integration', 
                    sprintf(
                        /* translators: %s: SAP Business Partner Card Code */
                        esc_html__('Business Partner successfully created in SAP B1. Card Code: %s', 'shift8-gravity-forms-sap-b1-integration'), 
                        esc_html($result['CardCode'])
                    )
                );
            }
            
        } catch (Exception $e) {
            // Add error note to entry
            GFFormsModel::add_note(
                $entry['id'], 
                0, 
                'Shift8 SAP Integration', 
                sprintf(
                    /* translators: %s: Error message */
                    esc_html__('Error creating Business Partner in SAP B1: %s', 'shift8-gravity-forms-sap-b1-integration'), 
                    esc_html($e->getMessage())
                )
            );
            
            shift8_gravitysap_debug_log('Form submission processing failed', array(
                'entry_id' => $entry['id'],
                'form_id' => $form['id'],
                'error' => $e->getMessage()
            ));
        }
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
        
        $business_partner = array('CardType' => $card_type);

        foreach ($field_mapping as $sap_field => $field_id) {
            if (empty($field_id)) {
                continue;
            }

            $field_value = rgar($entry, $field_id);
            
            if (empty($field_value)) {
                continue;
            }

            // Sanitize field value
            $field_value = sanitize_text_field($field_value);

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