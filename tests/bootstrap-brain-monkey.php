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
    return isset($_test_options[$option]) ? $_test_options[$option] : $default;
});

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

// Mock additional essential functions
Functions\when('rgar')->alias(function($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
});
Functions\when('wp_salt')->justReturn('test_salt_auth_1234567890abcdef');
Functions\when('is_admin')->justReturn(false);
Functions\when('wp_clear_scheduled_hook')->justReturn(true);
Functions\when('esc_html__')->alias(function($text, $domain) { return $text; });
Functions\when('wp_verify_nonce')->justReturn(true);
Functions\when('add_option')->justReturn(true);
Functions\when('wp_upload_dir')->justReturn(array('basedir' => '/tmp/uploads'));
Functions\when('wp_mkdir_p')->justReturn(true);
Functions\when('is_dir')->justReturn(true);
Functions\when('WP_Filesystem')->justReturn(true);

// Mock global filesystem
global $wp_filesystem;
$wp_filesystem = new class {
    public function is_writable($path) { return true; }
    public function exists($file) { return false; }
    public function put_contents($file, $content) { return true; }
};

// Load the plugin
require dirname(__DIR__) . '/shift8-gravitysap.php'; 