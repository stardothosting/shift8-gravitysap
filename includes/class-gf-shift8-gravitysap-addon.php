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
        
        // Include SAP service class
        require_once SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'includes/class-shift8-gravitysap-sap-service.php';
        
        add_action('gform_after_submission', array($this, 'handle_form_submission'), 10, 2);
        
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
    public function form_settings_fields($form) {
        return array(
            array(
                'title'  => esc_html__('SAP Business One Field Mapping', 'shift8-gravitysap'),
                'fields' => array(
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
                        'name'  => 'field_mappings',
                        'label' => esc_html__('Field Mappings', 'shift8-gravitysap'),
                        'type'  => 'field_mappings',
                    ),
                ),
            ),
        );
    }

    public function settings_field_mappings($field, $echo = true) {
        $form = GFAPI::get_form($this->get_current_form_id());
        $mappings = $this->get_form_setting('field_mappings') ?: array();
        
        $html = '<div class="field-mappings">';
        $html .= '<table class="widefat">';
        $html .= '<thead><tr>';
        $html .= '<th>' . esc_html__('Form Field', 'shift8-gravitysap') . '</th>';
        $html .= '<th>' . esc_html__('SAP Field', 'shift8-gravitysap') . '</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        
        foreach ($form['fields'] as $form_field) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($form_field->label) . '</td>';
            $html .= '<td>';
            $html .= '<input type="text" name="field_mappings[' . esc_attr($form_field->id) . ']" ';
            $html .= 'value="' . esc_attr($mappings[$form_field->id] ?? '') . '" class="medium" />';
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
        
        if ($echo) {
            echo $html;
        }
        return $html;
    }

    public function handle_form_submission($entry, $form) {
        // Get form settings
        $settings = $this->get_form_settings($form);
        if (!$settings) {
            $this->log_debug('No form settings found for form #' . $form['id']);
            return;
        }

        // Get field mappings
        $mappings = $settings['field_mappings'] ?? array();
        if (empty($mappings)) {
            $this->log_debug('No field mappings found for form #' . $form['id']);
            return;
        }

        // Prepare data for SAP
        $sap_data = array();
        foreach ($mappings as $form_field_id => $sap_field) {
            if (!empty($sap_field)) {
                $sap_data[$sap_field] = rgar($entry, $form_field_id);
            }
        }

        // Get SAP settings
        $sap_settings = get_option('shift8_gravitysap_settings');
        if (!$sap_settings) {
            $this->log_debug('SAP settings not found');
            return;
        }

        // Send data to SAP
        try {
            $endpoint = rtrim($sap_settings['sap_endpoint'], '/') . '/';
            $entity_type = $settings['sap_entity_type'];
            
            // First, get session
            $session = $this->get_sap_session($sap_settings);
            if (!$session) {
                throw new Exception('Failed to get SAP session');
            }

            // Create entity in SAP
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

    private function log_debug($message, $data = null) {
        if (class_exists('GFLogging')) {
            GFLogging::include_logger();
            GFLogging::log_message('shift8-gravitysap', $message, KLogger::DEBUG, $data);
        }
    }
}

// Initialize add-on
function gf_shift8_gravitysap() {
    return GF_Shift8_GravitySAP_AddOn::get_instance();
} 