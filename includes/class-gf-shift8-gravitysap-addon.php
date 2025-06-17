<?php
/**
 * Gravity Forms SAP Business One Add-On
 *
 * @package Shift8\GravitySAP
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Framework should already be included by the main plugin

/**
 * Gravity Forms Shift8 SAP Add-On Class
 */
class GF_Shift8_GravitySAP_AddOn extends GFAddOn {

    /**
     * Version
     */
    protected $_version = SHIFT8_GRAVITYSAP_VERSION;
    
    /**
     * Minimum Gravity Forms version
     */
    protected $_min_gravityforms_version = '2.5';
    
    /**
     * Slug
     */
    protected $_slug = 'shift8-gravitysap';
    
    /**
     * Path
     */
    protected $_path = 'shift8-gravitysap/shift8-gravitysap.php';
    
    /**
     * Full path
     */
    protected $_full_path = __FILE__;
    
    /**
     * Title
     */
    protected $_title = 'Shift8 GravitySAP';
    
    /**
     * Short title
     */
    protected $_short_title = 'GravitySAP';

    /**
     * Capabilities
     */
    protected $_capabilities = array('gravityforms_shift8_gravitysap', 'gravityforms_shift8_gravitysap_uninstall');

    /**
     * Capabilities settings
     */
    protected $_capabilities_settings_page = 'gravityforms_shift8_gravitysap';

    /**
     * Capabilities form settings
     */
    protected $_capabilities_form_settings = 'gravityforms_shift8_gravitysap';

    /**
     * Capabilities uninstall
     */
    protected $_capabilities_uninstall = 'gravityforms_shift8_gravitysap_uninstall';

    /**
     * Enable form settings
     */
    protected $_enable_rg_autoupgrade = false;
    protected $_has_form_settings = true;
    protected $_supports_feed_order = false;

    /**
     * Singleton instance
     */
    private static $_instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Include the field map feature
     */
    public function minimum_requirements() {
        return array(
            'gravityforms' => array(
                'version' => $this->_min_gravityforms_version
            )
        );
    }

    /**
     * Plugin starting point. Will load appropriate files
     */
    public function init() {
        parent::init();
        
        shift8_gravitysap_debug_log('Add-on init() called');
        
        // Include SAP service class
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
        
        // Add form submission handler
        add_action('gform_after_submission', array($this, 'handle_form_submission'), 10, 2);
        
        // Add feed settings
        add_filter('gform_addon_feed_settings_fields', array($this, 'feed_settings_fields'), 10, 2);
        
        // Debug settings save - MORE COMPREHENSIVE HOOKS
        add_action('gform_before_save_form_settings', array($this, 'debug_before_save_settings'), 10, 2);
        add_action('gform_after_save_form_settings', array($this, 'debug_after_save_settings'), 10, 2);
        add_action('gform_pre_save_feed', array($this, 'debug_pre_save_feed'), 10, 2);
        add_action('gform_post_save_feed', array($this, 'debug_post_save_feed'), 10, 2);
        
        // Add form settings page debugging
        add_action('gform_form_settings_page_sap_integration', array($this, 'debug_form_settings_page'), 10);
        add_action('gform_form_settings', array($this, 'debug_form_settings'), 10, 2);
        
        // Add more debugging hooks
        add_action('init', array($this, 'debug_init'), 20);
        add_action('admin_init', array($this, 'debug_admin_init'), 20);
        add_action('wp_loaded', array($this, 'debug_wp_loaded'), 20);
        
        // Debug all $_POST and $_GET requests to admin pages
        if (is_admin()) {
            add_action('admin_head', array($this, 'debug_admin_request'), 1);
        }
        
        shift8_gravitysap_debug_log('Add-on initialized successfully');
    }

    /**
     * Debug init hook
     */
    public function debug_init() {
        if (isset($_GET['page']) && $_GET['page'] === 'gf_edit_forms' && isset($_GET['subview']) && $_GET['subview'] === 'sap_integration') {
            shift8_gravitysap_debug_log('Init hook - SAP settings page detected', array(
                'GET' => $_GET,
                'POST' => $_POST,
                'current_action' => current_action()
            ));
        }
    }

    /**
     * Debug admin init hook
     */
    public function debug_admin_init() {
        if (isset($_GET['page']) && $_GET['page'] === 'gf_edit_forms' && isset($_GET['subview']) && $_GET['subview'] === 'sap_integration') {
            shift8_gravitysap_debug_log('Admin init hook - SAP settings page detected', array(
                'GET' => $_GET,
                'POST' => $_POST,
                'doing_action' => doing_action()
            ));
        }
    }

    /**
     * Debug wp loaded hook
     */
    public function debug_wp_loaded() {
        if (isset($_GET['page']) && $_GET['page'] === 'gf_edit_forms' && isset($_GET['subview']) && $_GET['subview'] === 'sap_integration') {
            shift8_gravitysap_debug_log('WP loaded hook - SAP settings page detected', array(
                'GET' => $_GET,
                'POST' => $_POST
            ));
        }
    }

    /**
     * Debug admin request
     */
    public function debug_admin_request() {
        if (isset($_GET['page']) && $_GET['page'] === 'gf_edit_forms') {
            shift8_gravitysap_debug_log('Admin page request detected', array(
                'GET' => $_GET,
                'POST' => $_POST,
                'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
                'current_screen' => get_current_screen() ? get_current_screen()->id : 'unknown'
            ));
        }
    }

    /**
     * Debug before save settings
     */
    public function debug_before_save_settings($form, $is_new) {
        shift8_gravitysap_debug_log('Before save form settings', array(
            'form' => $form,
            'is_new' => $is_new,
            'POST' => $_POST,
            'REQUEST' => $_REQUEST
        ));
    }

    /**
     * Debug after save settings
     */
    public function debug_after_save_settings($form, $is_new) {
        shift8_gravitysap_debug_log('After save form settings', array(
            'form' => $form,
            'is_new' => $is_new,
            'POST' => $_POST,
            'REQUEST' => $_REQUEST
        ));
    }

    /**
     * Debug pre save feed
     */
    public function debug_pre_save_feed($feed, $form) {
        shift8_gravitysap_debug_log('Pre save feed', array(
            'feed' => $feed,
            'form' => $form,
            'POST' => $_POST,
            'REQUEST' => $_REQUEST
        ));
    }

    /**
     * Debug post save feed
     */
    public function debug_post_save_feed($feed, $form) {
        shift8_gravitysap_debug_log('Post save feed', array(
            'feed' => $feed,
            'form' => $form,
            'POST' => $_POST,
            'REQUEST' => $_REQUEST
        ));
    }

    /**
     * Debug form settings page
     */
    public function debug_form_settings_page() {
        shift8_gravitysap_debug_log('Form settings page loaded');
    }

    /**
     * Debug form settings
     */
    public function debug_form_settings($form, $subview) {
        shift8_gravitysap_debug_log('Form settings called with subview: ' . $subview);
    }

    /**
     * Override get_form_settings to add debugging
     */
    public function get_form_settings($form) {
        shift8_gravitysap_debug_log('get_form_settings called', array('form' => $form));
        return parent::get_form_settings($form);
    }

    /**
     * Override save_form_settings to add debugging
     */
    public function save_form_settings($form, $settings) {
        shift8_gravitysap_debug_log('save_form_settings called', array(
            'form' => $form,
            'settings' => $settings
        ));
        return parent::save_form_settings($form, $settings);
    }

    /**
     * Override save_settings to add debugging
     */
    public function save_settings($settings) {
        shift8_gravitysap_debug_log('Save settings called', array(
            'settings' => $settings,
            'POST' => $_POST,
            'REQUEST' => $_REQUEST
        ));
        
        return parent::save_settings($settings);
    }

    /**
     * Override save_feed_settings to add debugging
     */
    public function save_feed_settings($feed_id, $form_id, $settings) {
        shift8_gravitysap_debug_log('Save feed settings called', array(
            'feed_id' => $feed_id,
            'form_id' => $form_id,
            'settings' => $settings,
            'POST' => $_POST,
            'REQUEST' => $_REQUEST
        ));
        
        return parent::save_feed_settings($feed_id, $form_id, $settings);
    }

    /**
     * Return the plugin's icon for the plugin/form settings menu
     */
    public function get_menu_icon() {
        return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>';
    }

    /**
     * Configures the settings which should be rendered on the add-on settings tab
     */
    public function plugin_settings_fields() {
        return array(
            array(
                'title'  => esc_html__('SAP Business One Settings', 'shift8-gravitysap'),
                'fields' => array(
                    array(
                        'name'              => 'sap_endpoint',
                        'label'             => esc_html__('Service Layer Endpoint', 'shift8-gravitysap'),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'feedback_callback' => array($this, 'is_valid_setting'),
                    ),
                    array(
                        'name'              => 'sap_company_db',
                        'label'             => esc_html__('Company Database', 'shift8-gravitysap'),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'feedback_callback' => array($this, 'is_valid_setting'),
                    ),
                    array(
                        'name'              => 'sap_username',
                        'label'             => esc_html__('Username', 'shift8-gravitysap'),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'feedback_callback' => array($this, 'is_valid_setting'),
                    ),
                    array(
                        'name'              => 'sap_password',
                        'label'             => esc_html__('Password', 'shift8-gravitysap'),
                        'type'              => 'password',
                        'class'             => 'medium',
                        'feedback_callback' => array($this, 'is_valid_setting'),
                    ),
                ),
            ),
        );
    }

    /**
     * Configures the settings which should be rendered on the Form Settings > SAP tab
     */
    public function feed_settings_fields() {
        $this->log_debug('Feed settings fields called');
        
        return array(
            array(
                'title'  => esc_html__('SAP Business One Feed Settings', 'shift8-gravitysap'),
                'fields' => array(
                    array(
                        'name'    => 'feed_name',
                        'label'   => esc_html__('Feed Name', 'shift8-gravitysap'),
                        'type'    => 'text',
                        'class'   => 'medium',
                        'tooltip' => esc_html__('Enter a feed name to uniquely identify this setup.', 'shift8-gravitysap'),
                    ),
                    array(
                        'name'    => 'sap_entity_type',
                        'label'   => esc_html__('SAP Entity Type', 'shift8-gravitysap'),
                        'type'    => 'select',
                        'choices' => array(
                            array(
                                'label' => esc_html__('Business Partner', 'shift8-gravitysap'),
                                'value' => 'BusinessPartner',
                            ),
                            array(
                                'label' => esc_html__('Sales Order', 'shift8-gravitysap'),
                                'value' => 'Orders',
                            ),
                        ),
                    ),
                    array(
                        'name'    => 'mappedFields',
                        'label'   => esc_html__('Field Mappings', 'shift8-gravitysap'),
                        'type'    => 'field_map',
                        'tooltip' => esc_html__('Map form fields to SAP Business Partner fields.', 'shift8-gravitysap'),
                        'field_map' => array(
                            array(
                                'name'     => 'CardName',
                                'label'    => esc_html__('Card Name', 'shift8-gravitysap'),
                                'required' => true
                            ),
                            array(
                                'name'     => 'EmailAddress',
                                'label'    => esc_html__('Email', 'shift8-gravitysap'),
                                'required' => false
                            ),
                            array(
                                'name'     => 'Phone1',
                                'label'    => esc_html__('Phone', 'shift8-gravitysap'),
                                'required' => false
                            ),
                            array(
                                'name'     => 'BPAddresses.Street',
                                'label'    => esc_html__('Street Address', 'shift8-gravitysap'),
                                'required' => false
                            ),
                            array(
                                'name'     => 'BPAddresses.City',
                                'label'    => esc_html__('City', 'shift8-gravitysap'),
                                'required' => false
                            ),
                            array(
                                'name'     => 'BPAddresses.Country',
                                'label'    => esc_html__('Country', 'shift8-gravitysap'),
                                'required' => false
                            ),
                            array(
                                'name'     => 'BPAddresses.State',
                                'label'    => esc_html__('State/Province', 'shift8-gravitysap'),
                                'required' => false
                            ),
                            array(
                                'name'     => 'BPAddresses.ZipCode',
                                'label'    => esc_html__('Zip/Postal Code', 'shift8-gravitysap'),
                                'required' => false
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * Process the feed.
     *
     * @param array $feed  The feed object to be processed.
     * @param array $entry The entry object currently being processed.
     * @param array $form  The form object currently being processed.
     */
    public function process_feed($feed, $entry, $form) {
        $this->log_debug('Processing feed', array(
            'feed' => $feed,
            'entry' => $entry,
            'form' => $form
        ));

        // Get field mappings
        $mappings = $feed['meta']['mappedFields'] ?? array();
        if (empty($mappings)) {
            $this->log_debug('No field mappings found for form #' . $form['id']);
            return;
        }

        // Get SAP settings
        $sap_settings = get_option('shift8_gravitysap_settings');
        if (!$sap_settings) {
            $this->log_debug('SAP settings not found');
            return;
        }

        // Prepare data for SAP
        $sap_data = array();
        foreach ($mappings as $sap_field => $form_field_id) {
            if (!empty($form_field_id)) {
                $sap_data[$sap_field] = rgar($entry, $form_field_id);
            }
        }

        try {
            // Get session
            $session = $this->get_sap_session($sap_settings);
            if (!$session) {
                throw new Exception('Failed to get SAP session');
            }

            // Create entity in SAP
            $endpoint = rtrim($sap_settings['sap_endpoint'], '/') . '/';
            $entity_type = $feed['meta']['sap_entity_type'];
            
            $response = wp_remote_post($endpoint . $entity_type, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Cookie' => 'B1SESSION=' . $session
                ),
                'body' => json_encode($sap_data),
                'timeout' => 30,
                'sslverify' => false
            ));

            if (is_wp_error($response)) {
                throw new Exception('SAP API Error: ' . $response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 201) {
                $body = wp_remote_retrieve_body($response);
                throw new Exception('SAP API Error: HTTP ' . $response_code . ' - ' . $body);
            }

            $this->log_debug('Successfully created ' . $entity_type . ' in SAP', array(
                'form_id' => $form['id'],
                'entry_id' => $entry['id'],
                'sap_data' => $sap_data
            ));

        } catch (Exception $e) {
            $this->log_debug('Error creating ' . $entity_type . ' in SAP', array(
                'form_id' => $form['id'],
                'entry_id' => $entry['id'],
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Get SAP session
     */
    private function get_sap_session($settings) {
        try {
            $endpoint = rtrim($settings['sap_endpoint'], '/') . '/';
            
            $login_data = array(
                'CompanyDB' => $settings['sap_company_db'],
                'UserName' => $settings['sap_username'],
                'Password' => $settings['sap_password']
            );

            $response = wp_remote_post($endpoint . 'Login', array(
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($login_data),
                'timeout' => 30,
                'sslverify' => false
            ));

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new Exception('Login failed: HTTP ' . $response_code);
            }

            $cookies = wp_remote_retrieve_cookies($response);
            foreach ($cookies as $cookie) {
                if ($cookie->name === 'B1SESSION') {
                    return $cookie->value;
                }
            }

            throw new Exception('Session cookie not found');

        } catch (Exception $e) {
            $this->log_debug('Error getting SAP session', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Log debug information
     */
    private function log_debug($message, $data = null) {
        shift8_gravitysap_debug_log($message, $data);
    }

    /**
     * Form settings fields for SAP Integration tab
     */
    public function form_settings_fields($form) {
        shift8_gravitysap_debug_log('form_settings_fields called - ADDON FRAMEWORK', array(
            'form_id' => $form['id'],
            'POST' => $_POST,
            'GET' => $_GET
        ));
        
        return array(
            array(
                'title'  => esc_html__('SAP Business One Integration', 'shift8-gravitysap'),
                'fields' => array(
                    array(
                        'name'    => 'sap_enabled',
                        'label'   => esc_html__('Enable SAP Integration', 'shift8-gravitysap'),
                        'type'    => 'checkbox',
                        'tooltip' => esc_html__('Enable this form to send data to SAP Business One', 'shift8-gravitysap'),
                        'choices' => array(
                            array(
                                'label' => esc_html__('Send form submissions to SAP Business One', 'shift8-gravitysap'),
                                'name'  => 'sap_enabled',
                                'value' => '1'
                            )
                        )
                    ),
                    array(
                        'name'    => 'sap_feed_name',
                        'label'   => esc_html__('Feed Name', 'shift8-gravitysap'),
                        'type'    => 'text',
                        'class'   => 'medium',
                        'tooltip' => esc_html__('Enter a name to identify this SAP integration', 'shift8-gravitysap')
                    ),
                    array(
                        'name'    => 'sap_card_type',
                        'label'   => esc_html__('Business Partner Type', 'shift8-gravitysap'),
                        'type'    => 'select',
                        'tooltip' => esc_html__('Select the type of business partner to create in SAP', 'shift8-gravitysap'),
                        'choices' => array(
                            array(
                                'label' => esc_html__('Customer', 'shift8-gravitysap'),
                                'value' => 'cCustomer'
                            ),
                            array(
                                'label' => esc_html__('Vendor', 'shift8-gravitysap'),
                                'value' => 'cSupplier'
                            ),
                            array(
                                'label' => esc_html__('Lead', 'shift8-gravitysap'),
                                'value' => 'cLid'
                            )
                        )
                    ),
                    array(
                        'name'    => 'sap_field_mapping',
                        'label'   => esc_html__('Field Mapping', 'shift8-gravitysap'),
                        'type'    => 'field_map',
                        'field_map' => $this->get_field_map_choices($form),
                        'tooltip' => esc_html__('Map your form fields to SAP Business Partner fields', 'shift8-gravitysap')
                    )
                )
            )
        );
    }

    /**
     * Get field mapping choices for SAP Business Partner fields
     */
    protected function get_field_map_choices($form) {
        return array(
            array(
                'name'     => 'CardName',
                'label'    => esc_html__('Business Partner Name', 'shift8-gravitysap'),
                'required' => true
            ),
            array(
                'name'  => 'EmailAddress',
                'label' => esc_html__('Email Address', 'shift8-gravitysap')
            ),
            array(
                'name'  => 'Phone1',
                'label' => esc_html__('Phone Number', 'shift8-gravitysap')
            ),
            array(
                'name'  => 'BPAddresses_Street',
                'label' => esc_html__('Street Address', 'shift8-gravitysap')
            ),
            array(
                'name'  => 'BPAddresses_City',
                'label' => esc_html__('City', 'shift8-gravitysap')
            ),
            array(
                'name'  => 'BPAddresses_State',
                'label' => esc_html__('State/Province', 'shift8-gravitysap')
            ),
            array(
                'name'  => 'BPAddresses_ZipCode',
                'label' => esc_html__('Zip/Postal Code', 'shift8-gravitysap')
            )
        );
    }
}

// Initialize add-on
function gf_shift8_gravitysap() {
    return GF_Shift8_GravitySAP_AddOn::get_instance();
} 