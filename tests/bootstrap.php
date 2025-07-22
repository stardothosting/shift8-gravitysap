<?php
/**
 * Brain/Monkey PHPUnit bootstrap for Shift8 Integration for Gravity Forms and SAP Business One
 *
 * @package Shift8\GravitySAP\Tests
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Initialize Brain/Monkey
Brain\Monkey\setUp();

use Brain\Monkey\Functions;

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

if (!defined('FS_CHMOD_FILE')) {
    define('FS_CHMOD_FILE', 0644);
}

// Global test options storage
global $_test_options;
$_test_options = array();

// Mock essential WordPress functions that are called during plugin load
Functions\when('get_option')->alias(function($option, $default = false) {
    global $_test_options;
    
    // Default empty settings for SAP debug to prevent destructor issues
    if ($option === 'shift8_gravitysap_settings') {
        return isset($_test_options[$option]) ? $_test_options[$option] : array('sap_debug' => '0');
    }
    
    return isset($_test_options[$option]) ? $_test_options[$option] : $default;
});

// Define a testing flag to prevent destructor issues
define('SHIFT8_GRAVITYSAP_TESTING', true);

// Define these functions if they don't exist yet
if (!function_exists('plugin_dir_path')) {
    Functions\when('plugin_dir_path')->justReturn(dirname(__DIR__) . '/');
}
if (!function_exists('plugin_dir_url')) {
    Functions\when('plugin_dir_url')->justReturn('http://example.com/wp-content/plugins/shift8-gravitysap/');
}
if (!function_exists('plugin_basename')) {
    Functions\when('plugin_basename')->justReturn('shift8-gravitysap/shift8-gravitysap.php');
}
Functions\when('current_time')->justReturn(date('Y-m-d H:i:s'));
Functions\when('wp_json_encode')->alias('json_encode');
Functions\when('sanitize_text_field')->alias(function($str) {
    return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
});

// Mock add_action and add_filter to prevent errors during plugin load
Functions\when('add_action')->justReturn(true);
Functions\when('add_filter')->justReturn(true);
Functions\when('register_activation_hook')->justReturn(true);
Functions\when('register_deactivation_hook')->justReturn(true);

// Mock WordPress HTTP API functions
Functions\when('wp_remote_request')->justReturn(array(
    'response' => array('code' => 200),
    'body' => '{"@odata.context":"test","value":[]}'
));
Functions\when('wp_remote_post')->justReturn(array(
    'response' => array('code' => 200),
    'body' => '{"@odata.context":"test","value":[]}'
));
Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
Functions\when('wp_remote_retrieve_body')->justReturn('{"@odata.context":"test","value":[]}');
Functions\when('wp_remote_retrieve_headers')->justReturn(array());
Functions\when('is_wp_error')->justReturn(false);

// Mock additional essential functions
Functions\when('rgar')->alias(function($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
});
Functions\when('wp_salt')->justReturn('test_salt_auth_1234567890abcdef');
Functions\when('is_admin')->justReturn(false);
Functions\when('wp_clear_scheduled_hook')->justReturn(true);
Functions\when('esc_html__')->alias(function($text, $domain) { return $text; });
Functions\when('esc_html_e')->alias(function($text, $domain) { echo $text; });
Functions\when('wp_verify_nonce')->justReturn(true);
Functions\when('add_option')->justReturn(true);
Functions\when('update_option')->justReturn(true);
Functions\when('wp_upload_dir')->justReturn(array('basedir' => '/tmp/uploads'));
Functions\when('wp_mkdir_p')->justReturn(true);
Functions\when('is_dir')->justReturn(true);
Functions\when('WP_Filesystem')->justReturn(true);
Functions\when('wp_unslash')->alias(function($value) {
    return is_string($value) ? stripslashes($value) : $value;
});
Functions\when('wp_send_json_success')->alias(function($data) {
    echo json_encode(array('success' => true, 'data' => $data));
    exit;
});
Functions\when('wp_send_json_error')->alias(function($data) {
    echo json_encode(array('success' => false, 'data' => $data));
    exit;
});
Functions\when('check_admin_referer')->justReturn(true);
Functions\when('settings_fields')->alias(function($group) { 
    echo '<input type="hidden" name="option_page" value="' . esc_attr($group) . '" />';
});
Functions\when('get_admin_page_title')->justReturn('Test Page Title');
Functions\when('wp_die')->alias(function($message) {
    throw new \Exception($message);
});

// Mock WordPress admin functions
Functions\when('add_settings_error')->justReturn(true);

// Mock global filesystem
global $wp_filesystem;
$wp_filesystem = new class {
    public function is_writable($path) { return true; }
    public function exists($file) { return false; }
    public function put_contents($file, $content) { return true; }
};

// Mock WP_Error class for tests
if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = array();
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code] = array($message);
            }
        }
        
        public function get_error_message() {
            $codes = array_keys($this->errors);
            if (empty($codes)) return '';
            return $this->errors[$codes[0]][0];
        }
    }
}

// Load the plugin
require dirname(__DIR__) . '/shift8-gravitysap.php'; 