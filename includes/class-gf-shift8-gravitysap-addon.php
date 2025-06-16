<?php
/**
 * Shift8 GravitySAP Add-On
 *
 * @package     Shift8GravitySAP
 * @author      Shift8
 * @copyright   2024 Shift8
 * @license     GPL-2.0+
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

GFForms::include_addon_framework();

/**
 * Gravity Forms Shift8 SAP Add-On Class
 */
class GF_Shift8_GravitySAP_AddOn extends GFAddOn {

    /**
     * Contains an instance of this class, if available.
     *
     * @var GF_Shift8_GravitySAP_AddOn
     */
    private static $_instance = null;

    /**
     * Defines the version of the Add-On.
     *
     * @var string
     */
    protected $_version = '1.0.0';
    
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
    protected $_short_title = 'SAP';

    /**
     * Capabilities
     */
    protected $_capabilities = array(
        'gravityforms_shift8_gravitysap',
        'gravityforms_shift8_gravitysap_uninstall',
        'gravityforms_shift8_gravitysap_form_settings'
    );

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
    protected $_enable_rg_autoupgrade = true;
    protected $_has_form_settings = true;
    protected $_supports_feed_order = false;

    /**
     * URL
     */
    protected $_url = 'https://www.shift8web.ca';

    /**
     * Get instance of this class.
     *
     * @return GF_Shift8_GravitySAP_AddOn
     */
    public static function get_instance() {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Plugin starting point. Will load appropriate files
     */
    public function init() {
        parent::init();
        
        // Validate required properties
        if (empty($this->_slug)) {
            error_log('[Shift8 GravitySAP] Error: Addon slug is not set');
            return;
        }
        
        if (empty($this->_path)) {
            error_log('[Shift8 GravitySAP] Error: Addon path is not set');
            return;
        }
        
        // Validate Gravity Forms version
        if (!$this->is_gravityforms_supported()) {
            error_log('[Shift8 GravitySAP] Error: Gravity Forms version not supported');
            return;
        }
        
        // Include SAP service class
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
        
        // Add form submission handler
        add_action('gform_after_submission', array($this, 'handle_form_submission'), 10, 2);
        
        // Add AJAX handlers
        add_action('wp_ajax_refresh_sap_fields', array($this, 'ajax_refresh_sap_fields'));
        
        // Add feed settings
        add_filter('gform_addon_navigation', array($this, 'add_feed_settings_menu'));
        
        // Add feed list columns
        add_filter('gform_addon_feed_settings_fields', array($this, 'feed_settings_fields'), 10, 2);
        
        // Add feed list actions
        add_filter('gform_addon_feed_actions', array($this, 'feed_list_actions'), 10, 2);
        
        // Add feed processing
        add_filter('gform_addon_feed_processing', array($this, 'process_feed'), 10, 3);
        
        // Log successful initialization
        error_log('[Shift8 GravitySAP] Addon initialized successfully');
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
     * Configures the settings which should be rendered on the Form Settings > SAP tab
     */
    public function form_settings_fields($form) {
        return array(
            array(
                'title'  => esc_html__('SAP Integration', 'shift8-gravitysap'),
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
                        'label'   => esc_html__('Business Partner Type', 'shift8-gravitysap'),
                        'type'    => 'select',
                        'choices' => array(
                            array(
                                'label' => esc_html__('Customer', 'shift8-gravitysap'),
                                'value' => 'Customer'
                            ),
                            array(
                                'label' => esc_html__('Vendor', 'shift8-gravitysap'),
                                'value' => 'Vendor'
                            ),
                            array(
                                'label' => esc_html__('Lead', 'shift8-gravitysap'),
                                'value' => 'Lead'
                            )
                        ),
                        'tooltip' => esc_html__('Select the type of Business Partner to create in SAP.', 'shift8-gravitysap'),
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
        // Get field mappings
        $mappings = $feed['meta']['mappedFields'] ?? array();
        if (empty($mappings)) {
            $this->log_debug('No field mappings found for form #' . $form['id']);
            return;
        }

        // Prepare data for SAP
        $sap_data = array();
        foreach ($mappings as $sap_field => $form_field_id) {
            if (!empty($form_field_id)) {
                $sap_data[$sap_field] = rgar($entry, $form_field_id);
            }
        }

        // Get SAP settings
        $sap_settings = get_option('shift8_gravitysap_settings');
        if (!$sap_settings) {
            $this->log_debug('SAP settings not found');
            return;
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

            // Add success note to entry
            GFAPI::add_note($entry['id'], 0, 'Shift8 SAP Integration', 
                sprintf('Successfully created %s in SAP Business One', $entity_type));

        } catch (Exception $e) {
            $this->log_debug('Error creating ' . $entity_type . ' in SAP', array(
                'form_id' => $form['id'],
                'entry_id' => $entry['id'],
                'error' => $e->getMessage()
            ));

            // Add error note to entry
            GFAPI::add_note($entry['id'], 0, 'Shift8 SAP Integration', 
                sprintf('Error creating %s in SAP Business One: %s', $entity_type, $e->getMessage()));
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
            $this->log_debug('Error getting SAP session', $e->getMessage());
            return false;
        }
    }

    /**
     * Log debug information
     */
    private function log_debug($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = '[Shift8 GravitySAP] ' . $message;
            if ($data !== null) {
                $log_message .= ' Data: ' . print_r($data, true);
            }
            error_log($log_message);
        }
    }

    /**
     * Plugin settings fields.
     *
     * @return array
     */
    public function plugin_settings_fields() {
        return array(
            array(
                'title'  => esc_html__('SAP B1 Integration Settings', 'shift8-gravitysap'),
                'fields' => array(
                    array(
                        'name'              => 'sap_endpoint',
                        'label'             => esc_html__('SAP Service Layer Endpoint URL', 'shift8-gravitysap'),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'feedback_callback' => array($this, 'is_valid_setting'),
                    ),
                    array(
                        'name'              => 'sap_company_db',
                        'label'             => esc_html__('SAP Company Database', 'shift8-gravitysap'),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'feedback_callback' => array($this, 'is_valid_setting'),
                    ),
                    array(
                        'name'              => 'sap_username',
                        'label'             => esc_html__('SAP Username', 'shift8-gravitysap'),
                        'type'              => 'text',
                        'class'             => 'medium',
                        'feedback_callback' => array($this, 'is_valid_setting'),
                    ),
                    array(
                        'name'              => 'sap_password',
                        'label'             => esc_html__('SAP Password', 'shift8-gravitysap'),
                        'type'              => 'password',
                        'class'             => 'medium',
                        'feedback_callback' => array($this, 'is_valid_setting'),
                    ),
                ),
            ),
        );
    }

    /**
     * Feed settings fields.
     *
     * @return array
     */
    public function feed_settings_fields() {
        $form = $this->get_current_form();
        return array(
            array(
                'title'  => esc_html__('SAP B1 Feed Settings', 'shift8-gravitysap'),
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
                        'label'   => esc_html__('Business Partner Type', 'shift8-gravitysap'),
                        'type'    => 'select',
                        'choices' => array(
                            array(
                                'label' => esc_html__('Customer', 'shift8-gravitysap'),
                                'value' => 'Customer'
                            ),
                            array(
                                'label' => esc_html__('Vendor', 'shift8-gravitysap'),
                                'value' => 'Vendor'
                            ),
                            array(
                                'label' => esc_html__('Lead', 'shift8-gravitysap'),
                                'value' => 'Lead'
                            )
                        ),
                        'tooltip' => esc_html__('Select the type of Business Partner to create in SAP.', 'shift8-gravitysap'),
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

    private function get_form_fields_as_choices($form, $types = array()) {
        $choices = array();
        if (!isset($form['fields']) || !is_array($form['fields'])) return $choices;
        foreach ($form['fields'] as $field) {
            if (!rgar($field, 'label')) continue;
            if (!empty($types) && !in_array($field->type, $types)) continue;
            $choices[] = array(
                'label' => $field->label,
                'value' => $field->id,
            );
        }
        return $choices;
    }

    /**
     * Add feed settings menu
     */
    public function add_feed_settings_menu($menus) {
        $menus[] = array(
            'name'       => $this->_slug,
            'label'      => $this->get_short_title(),
            'permission' => $this->_capabilities_form_settings,
            'query'      => array('fid' => '{id}'),
            'icon'       => $this->get_menu_icon()
        );
        return $menus;
    }

    /**
     * Feed list actions
     */
    public function feed_list_actions($feed, $column) {
        if ($column === 'feed_name') {
            $edit_url = add_query_arg(array(
                'page' => 'gf_edit_forms',
                'view' => 'settings',
                'subview' => $this->_slug,
                'fid' => $feed['form_id'],
                'id' => $feed['id']
            ), admin_url('admin.php'));
            
            echo '<a href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'shift8-gravitysap') . '</a>';
        }
    }

    /**
     * Check if Gravity Forms version is supported
     */
    private function is_gravityforms_supported() {
        if (class_exists('GFCommon')) {
            return version_compare(GFCommon::$version, $this->_min_gravityforms_version, '>=');
        }
        return false;
    }

    public function __construct() {
        error_log('[Shift8 GravitySAP] GF_Shift8_GravitySAP_AddOn constructor called');
        parent::__construct();
    }
} 