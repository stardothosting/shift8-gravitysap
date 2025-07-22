<?php
/**
 * Simplified PHPUnit bootstrap for basic plugin testing
 *
 * @package Shift8\GravitySAP\Tests
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

// Mock WordPress functions that our plugin uses
// (Option functions defined later with global storage)

if (!function_exists('current_time')) {
    function current_time($type) {
        return ($type === 'mysql') ? date('Y-m-d H:i:s') : time();
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Global array to track WordPress filters/actions
global $wp_filter;
$wp_filter = array();

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        global $wp_filter;
        if (!isset($wp_filter[$tag])) {
            $wp_filter[$tag] = (object) array('callbacks' => array());
        }
        if (!isset($wp_filter[$tag]->callbacks[$priority])) {
            $wp_filter[$tag]->callbacks[$priority] = array();
        }
        $wp_filter[$tag]->callbacks[$priority][] = array(
            'function' => $function_to_add,
            'accepted_args' => $accepted_args
        );
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return add_action($tag, $function_to_add, $priority, $accepted_args);
    }
}

// WordPress plugin functions
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $function) {
        // Mock function - just record that it was called
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $function) {
        // Mock function - just record that it was called
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false; // Default to false for unit tests
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax() {
        return false;
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        // Return a mock salt for testing
        return 'test_salt_' . $scheme . '_1234567890abcdef';
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false) {
        return true;
    }
}

if (!function_exists('get_admin_page_title')) {
    function get_admin_page_title() {
        return 'Test Admin Page';
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($option_group) {
        // Mock function
    }
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections($page) {
        // Mock function
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null) {
        echo '<input type="submit" name="' . esc_attr($name) . '" class="button-' . esc_attr($type) . '" value="' . esc_attr($text ? $text : 'Save Changes') . '" />';
    }
}

if (!function_exists('add_settings_section')) {
    function add_settings_section($id, $title, $callback, $page) {
        // Mock function
    }
}

if (!function_exists('add_settings_field')) {
    function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = array()) {
        // Mock function
    }
}

if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = array()) {
        // Mock function
    }
}

// Additional WordPress functions needed for comprehensive testing
if (!function_exists('add_option')) {
    function add_option($option, $value) {
        global $_test_options;
        if (!isset($_test_options[$option])) {
            $_test_options[$option] = $value;
        }
        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = array()) {
        return true; // Mock function
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return esc_html($text);
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs(intval($value));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('is_plugin_active')) {
    function is_plugin_active($plugin) {
        return false; // Mock function - return false for testing
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false) {
        return array(
            'path' => '/tmp/wp-uploads',
            'url' => 'http://example.com/wp-content/uploads',
            'subdir' => '',
            'basedir' => '/tmp/wp-uploads',
            'baseurl' => 'http://example.com/wp-content/uploads',
            'error' => false
        );
    }
}

if (!class_exists('WP_Filesystem_Base')) {
    class WP_Filesystem_Base {
        public function exists($file) {
            return file_exists($file);
        }
        
        public function is_writable($path) {
            return true; // Mock as writable for tests
        }
        
        public function get_contents($file) {
            return file_exists($file) ? file_get_contents($file) : '';
        }
        
        public function put_contents($file, $contents, $mode = false) {
            return true; // Mock successful write
        }
    }
}

if (!function_exists('WP_Filesystem')) {
    function WP_Filesystem($args = false, $context = false, $allow_relaxed_file_ownership = false) {
        global $wp_filesystem;
        $wp_filesystem = new WP_Filesystem_Base();
        return true;
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return true; // Mock successful directory creation
    }
}

// Define FS_CHMOD_FILE constant
if (!defined('FS_CHMOD_FILE')) {
    define('FS_CHMOD_FILE', 0644);
}

// WordPress HTTP API functions for SAP testing
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array()) {
        // Default mock response - can be overridden in tests
        return array(
            'response' => array('code' => 200),
            'body' => wp_json_encode(array('success' => true))
        );
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        // Default mock response - can be overridden in tests
        return array(
            'response' => array('code' => 200),
            'body' => wp_json_encode(array('success' => true))
        );
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return isset($response['response']['code']) ? $response['response']['code'] : 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = array();
        private $error_data = array();
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            
            if (isset($this->errors[$code])) {
                return $this->errors[$code][0];
            }
            
            return '';
        }
        
        public function get_error_code() {
            if (empty($this->errors)) {
                return '';
            }
            
            return array_keys($this->errors)[0];
        }
    }
}

// Initialize WordPress filesystem global
global $wp_filesystem;
$wp_filesystem = new WP_Filesystem_Base();

// Initialize static options array for our mock functions
global $_test_options;
$_test_options = array();

// Override the mock option functions to use global storage
function get_option($option, $default = false) {
    global $_test_options;
    return isset($_test_options[$option]) ? $_test_options[$option] : $default;
}

function update_option($option, $value) {
    global $_test_options;
    $_test_options[$option] = $value;
    return true;
}

function delete_option($option) {
    global $_test_options;
    unset($_test_options[$option]);
    return true;
}

// Add rgget and rgpost functions for Gravity Forms compatibility
if (!function_exists('rgget')) {
    function rgget($name, $array = null) {
        if ($array === null) {
            $array = $_GET;
        }
        return isset($array[$name]) ? $array[$name] : null;
    }
}

if (!function_exists('rgpost')) {
    function rgpost($name, $array = null) {
        if ($array === null) {
            $array = $_POST;
        }
        return isset($array[$name]) ? $array[$name] : null;
    }
}

if (!function_exists('rgar')) {
    function rgar($array, $name, $default = null) {
        return isset($array[$name]) ? $array[$name] : $default;
    }
}

// Mock wp_mkdir_p function
if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return true; // Mock always successful
    }
}

// Enhanced wp_create_nonce for testing
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test_nonce_' . md5($action . 'test_key');
    }
}

// Enhanced wp_verify_nonce for testing
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        $expected = wp_create_nonce($action);
        return $nonce === $expected;
    }
}

// Load the plugin
require dirname(__DIR__) . '/shift8-gravitysap.php'; 