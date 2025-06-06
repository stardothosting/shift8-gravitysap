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
    protected $_min_gravityforms_version = '2.4';
    
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
    protected $_full_path = SHIFT8_GRAVITYSAP_PLUGIN_FILE;
    
    /**
     * Title
     */
    protected $_title = 'Shift8 Gravity Forms SAP B1 Integration';
    
    /**
     * Short title
     */
    protected $_short_title = 'SAP B1 Integration';

    /**
     * Capabilities
     */
    protected $_capabilities = array('gravityforms_edit_forms');

    /**
     * Capabilities settings
     */
    protected $_capabilities_settings_page = 'gravityforms_edit_forms';

    /**
     * Capabilities form settings
     */
    protected $_capabilities_form_settings = 'gravityforms_edit_forms';

    /**
     * Capabilities uninstall
     */
    protected $_capabilities_uninstall = 'gravityforms_uninstall';

    /**
     * Enable form settings
     */
    protected $_enable_rg_autoupgrade = false;

    /**
     * Singleton instance
     */
    private static $_instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new GF_Shift8_GravitySAP_AddOn();
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
        
        // Include SAP service class
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
        
        add_action('gform_after_submission', array($this, 'process_feed'), 10, 2);
        
        // Debug: Log that the add-on is initialized
        if (function_exists('error_log')) {
            error_log('Shift8 GravitySAP Add-On initialized successfully');
        }
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
                        'label'   => esc_html__('SAP Service Layer Endpoint URL', 'shift8-gravitysap'),
                        'type'    => 'text',
                        'name'    => 'sap_endpoint',
                        'tooltip' => esc_html__('Enter your SAP Service Layer endpoint URL (e.g., https://sap.example.com:50000/b1s/v1/)', 'shift8-gravitysap'),
                        'class'   => 'medium',
                        'required' => true
                    ),
                    array(
                        'label'   => esc_html__('SAP Company Database', 'shift8-gravitysap'),
                        'type'    => 'text',
                        'name'    => 'sap_company_db',
                        'tooltip' => esc_html__('Enter your SAP Company Database identifier', 'shift8-gravitysap'),
                        'class'   => 'medium',
                        'required' => true
                    ),
                    array(
                        'label'   => esc_html__('SAP Username', 'shift8-gravitysap'),
                        'type'    => 'text',
                        'name'    => 'sap_username',
                        'tooltip' => esc_html__('Enter your SAP username', 'shift8-gravitysap'),
                        'class'   => 'medium',
                        'required' => true
                    ),
                    array(
                        'label'   => esc_html__('SAP Password', 'shift8-gravitysap'),
                        'type'    => 'password',
                        'name'    => 'sap_password',
                        'tooltip' => esc_html__('Enter your SAP password', 'shift8-gravitysap'),
                        'class'   => 'medium',
                        'required' => true
                    ),
                    array(
                        'label'   => esc_html__('Enable Logging', 'shift8-gravitysap'),
                        'type'    => 'checkbox',
                        'name'    => 'enable_logging',
                        'tooltip' => esc_html__('Enable logging to shift8-gravitysap.log file', 'shift8-gravitysap'),
                        'choices' => array(
                            array(
                                'label' => esc_html__('Enable error and activity logging', 'shift8-gravitysap'),
                                'name'  => 'enable_logging'
                            )
                        )
                    ),
                    array(
                        'type' => 'save',
                        'messages' => array(
                            'success' => esc_html__('SAP settings have been updated.', 'shift8-gravitysap')
                        )
                    )
                )
            )
        );
    }

    /**
     * Configures the settings which should be rendered on the Form Settings > SAP tab
     */
    public function form_settings_fields($form) {
        return array(
            array(
                'title'  => esc_html__('SAP Business Partner Feed', 'shift8-gravitysap'),
                'fields' => array(
                    array(
                        'label'   => esc_html__('Feed Name', 'shift8-gravitysap'),
                        'type'    => 'text',
                        'name'    => 'feedName',
                        'tooltip' => esc_html__('Enter a feed name to identify this particular feed', 'shift8-gravitysap'),
                        'class'   => 'medium',
                        'required' => true
                    ),
                    array(
                        'label'   => esc_html__('Business Partner Type', 'shift8-gravitysap'),
                        'type'    => 'select',
                        'name'    => 'card_type',
                        'tooltip' => esc_html__('Select the type of Business Partner', 'shift8-gravitysap'),
                        'choices' => array(
                            array('label' => esc_html__('Customer', 'shift8-gravitysap'), 'value' => 'cCustomer'),
                            array('label' => esc_html__('Vendor', 'shift8-gravitysap'), 'value' => 'cSupplier'),
                            array('label' => esc_html__('Lead', 'shift8-gravitysap'), 'value' => 'cLid')
                        ),
                        'required' => true
                    ),
                    array(
                        'name'      => 'mappedFields',
                        'label'     => esc_html__('Map Fields', 'shift8-gravitysap'),
                        'type'      => 'field_map',
                        'field_map' => $this->get_business_partner_fields(),
                        'tooltip'   => esc_html__('Map your form fields to the corresponding SAP Business Partner fields.', 'shift8-gravitysap')
                    ),
                    array(
                        'name'    => 'condition',
                        'label'   => esc_html__('Condition', 'shift8-gravitysap'),
                        'type'    => 'feed_condition',
                        'tooltip' => esc_html__('Process this feed if the following condition is met', 'shift8-gravitysap')
                    ),
                    array(
                        'type' => 'save',
                        'messages' => array(
                            'success' => esc_html__('Feed updated successfully.', 'shift8-gravitysap')
                        )
                    )
                )
            )
        );
    }

    /**
     * Define the markup for the field_map setting table header
     */
    public function field_map_table_header() {
        return array(
            'name'     => esc_html__('SAP Field', 'shift8-gravitysap'),
            'value'    => esc_html__('Form Field', 'shift8-gravitysap'),
            'required' => ''
        );
    }

    /**
     * Get Business Partner fields for mapping
     */
    public function get_business_partner_fields() {
        return array(
            array(
                'name'       => 'CardName',
                'label'      => esc_html__('Business Partner Name', 'shift8-gravitysap'),
                'required'   => true
            ),
            array(
                'name'       => 'EmailAddress',
                'label'      => esc_html__('Email Address', 'shift8-gravitysap'),
                'required'   => false
            ),
            array(
                'name'       => 'Phone1',
                'label'      => esc_html__('Phone Number', 'shift8-gravitysap'),
                'required'   => false
            ),
            array(
                'name'       => 'BlockSendingMarketingContent',
                'label'      => esc_html__('Block Marketing Content (Opt-out)', 'shift8-gravitysap'),
                'required'   => false
            ),
            array(
                'name'       => 'BPAddresses.Street',
                'label'      => esc_html__('Street Address', 'shift8-gravitysap'),
                'required'   => false
            ),
            array(
                'name'       => 'BPAddresses.City',
                'label'      => esc_html__('City', 'shift8-gravitysap'),
                'required'   => false
            ),
            array(
                'name'       => 'BPAddresses.Country',
                'label'      => esc_html__('Country', 'shift8-gravitysap'),
                'required'   => false
            ),
            array(
                'name'       => 'BPAddresses.State',
                'label'      => esc_html__('State/Province', 'shift8-gravitysap'),
                'required'   => false
            ),
            array(
                'name'       => 'BPAddresses.ZipCode',
                'label'      => esc_html__('Zip/Postal Code', 'shift8-gravitysap'),
                'required'   => false
            )
        );
    }

    /**
     * Process the feed when a form is submitted
     */
    public function process_feed($entry, $form) {
        $feeds = $this->get_feeds($form['id']);
        
        foreach ($feeds as $feed) {
            if (!$this->is_feed_condition_met($feed, $form, $entry)) {
                continue;
            }

            $this->process_sap_feed($feed, $entry, $form);
        }
    }

    /**
     * Process SAP feed
     */
    protected function process_sap_feed($feed, $entry, $form) {
        try {
            // Get plugin settings
            $settings = $this->get_plugin_settings();
            
            if (empty($settings['sap_endpoint']) || empty($settings['sap_username']) || empty($settings['sap_password'])) {
                throw new Exception('SAP connection settings are incomplete');
            }

            // Map form fields to SAP Business Partner data
            $business_partner_data = $this->map_entry_to_business_partner($feed, $entry, $form);
            
            // Initialize SAP service
            $sap_service = new Shift8_GravitySAP_SAP_Service($settings);
            
            // Create Business Partner in SAP
            $result = $sap_service->create_business_partner($business_partner_data);
            
            if ($result) {
                $this->log_info(sprintf('Successfully created Business Partner in SAP for entry ID: %s', $entry['id']));
                
                // Add entry note
                GFFormsModel::add_note($entry['id'], 0, 'Shift8 SAP Integration', 
                    sprintf('Business Partner successfully created in SAP B1. Card Code: %s', $result['CardCode']));
            }
            
        } catch (Exception $e) {
            $this->log_error(sprintf('Error processing SAP feed for entry ID %s: %s', $entry['id'], $e->getMessage()));
            
            // Add error note to entry
            GFFormsModel::add_note($entry['id'], 0, 'Shift8 SAP Integration', 
                sprintf('Error creating Business Partner in SAP B1: %s', $e->getMessage()));
        }
    }

    /**
     * Map entry data to Business Partner structure
     */
    protected function map_entry_to_business_partner($feed, $entry, $form) {
        $mapped_fields = rgar($feed['meta'], 'mappedFields');
        $card_type = rgar($feed['meta'], 'card_type', 'cCustomer');
        
        $business_partner = array(
            'CardType' => $card_type
        );

        // Map basic fields
        foreach ($mapped_fields as $field_name => $field_id) {
            if (empty($field_id)) {
                continue;
            }

            $field_value = rgar($entry, $field_id);
            
            if (empty($field_value)) {
                continue;
            }

            // Handle address fields specially
            if (strpos($field_name, 'BPAddresses.') === 0) {
                $address_field = str_replace('BPAddresses.', '', $field_name);
                
                if (!isset($business_partner['BPAddresses'])) {
                    $business_partner['BPAddresses'] = array(array(
                        'AddressType' => 'bo_BillTo'
                    ));
                }
                
                $business_partner['BPAddresses'][0][$address_field] = $field_value;
            } else {
                // Handle checkbox fields for opt-out
                if ($field_name === 'BlockSendingMarketingContent') {
                    $business_partner[$field_name] = $field_value === '1' ? 'tYES' : 'tNO';
                } else {
                    $business_partner[$field_name] = $field_value;
                }
            }
        }

        return $business_partner;
    }

    /**
     * Log info message
     */
    public function log_info($message) {
        if ($this->is_logging_enabled()) {
            Shift8_GravitySAP_Logger::log_info($message);
        }
    }

    /**
     * Log error message
     */
    public function log_error($message) {
        if ($this->is_logging_enabled()) {
            Shift8_GravitySAP_Logger::log_error($message);
        }
    }

    /**
     * Check if logging is enabled
     */
    protected function is_logging_enabled() {
        $settings = $this->get_plugin_settings();
        return !empty($settings['enable_logging']);
    }
}

// Initialize add-on
function gf_shift8_gravitysap() {
    return GF_Shift8_GravitySAP_AddOn::get_instance();
} 