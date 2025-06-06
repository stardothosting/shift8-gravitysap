<?php
/**
 * Logging functionality for Shift8 GravitySAP
 *
 * @package Shift8\GravitySAP
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class for Shift8 GravitySAP plugin
 */
class Shift8_GravitySAP_Logger {

    /**
     * Log levels
     */
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_INFO = 'INFO';
    const LEVEL_DEBUG = 'DEBUG';

    /**
     * Log file path
     */
    private static $log_file = null;

    /**
     * Get log file path
     */
    private static function get_log_file() {
        if (self::$log_file === null) {
            self::$log_file = SHIFT8_GRAVITYSAP_PLUGIN_DIR . 'shift8-gravitysap.log';
        }
        return self::$log_file;
    }

    /**
     * Write log entry
     */
    private static function write_log($level, $message) {
        // Check if logging is enabled
        if (!self::is_logging_enabled()) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = sprintf("[%s] %s: %s" . PHP_EOL, $timestamp, $level, $message);

        // Append to log file
        file_put_contents(self::get_log_file(), $log_entry, FILE_APPEND | LOCK_EX);

        // Rotate log file if it gets too large (5MB)
        self::rotate_log_if_needed();
    }

    /**
     * Log error message
     */
    public static function log_error($message) {
        self::write_log(self::LEVEL_ERROR, $message);
        
        // Also log to WordPress error log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Shift8 GravitySAP Error: ' . $message);
        }
    }

    /**
     * Log warning message
     */
    public static function log_warning($message) {
        self::write_log(self::LEVEL_WARNING, $message);
    }

    /**
     * Log info message
     */
    public static function log_info($message) {
        self::write_log(self::LEVEL_INFO, $message);
    }

    /**
     * Log debug message
     */
    public static function log_debug($message) {
        // Only log debug messages if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::write_log(self::LEVEL_DEBUG, $message);
        }
    }

    /**
     * Check if logging is enabled
     */
    private static function is_logging_enabled() {
        $settings = get_option('shift8_gravitysap_settings', array());
        return !empty($settings['enable_logging']);
    }

    /**
     * Rotate log file if it exceeds size limit
     */
    private static function rotate_log_if_needed() {
        $log_file = self::get_log_file();
        
        if (file_exists($log_file) && filesize($log_file) > 5242880) { // 5MB
            $backup_file = $log_file . '.' . date('Y-m-d-H-i-s') . '.bak';
            rename($log_file, $backup_file);
            
            // Keep only the 5 most recent backup files
            self::cleanup_old_backups();
        }
    }

    /**
     * Clean up old backup files
     */
    private static function cleanup_old_backups() {
        $log_dir = dirname(self::get_log_file());
        $backup_files = glob($log_dir . '/shift8-gravitysap.log.*.bak');
        
        if (count($backup_files) > 5) {
            // Sort by modification time (oldest first)
            usort($backup_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files, keeping only the 5 most recent
            $files_to_remove = array_slice($backup_files, 0, count($backup_files) - 5);
            foreach ($files_to_remove as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Get log entries
     */
    public static function get_log_entries($limit = 100) {
        $log_file = self::get_log_file();
        
        if (!file_exists($log_file)) {
            return array();
        }

        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Return the last N lines
        return array_slice($lines, -$limit);
    }

    /**
     * Clear log file
     */
    public static function clear_log() {
        $log_file = self::get_log_file();
        
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            self::log_info('Log file cleared');
        }
    }

    /**
     * Get log file size
     */
    public static function get_log_file_size() {
        $log_file = self::get_log_file();
        
        if (file_exists($log_file)) {
            return filesize($log_file);
        }
        
        return 0;
    }

    /**
     * Format file size for display
     */
    public static function format_file_size($bytes) {
        if ($bytes == 0) {
            return '0 B';
        }

        $units = array('B', 'KB', 'MB', 'GB');
        $i = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    /**
     * Check if log file exists and is writable
     */
    public static function is_log_writable() {
        $log_file = self::get_log_file();
        $log_dir = dirname($log_file);
        
        // Check if directory is writable
        if (!is_writable($log_dir)) {
            return false;
        }
        
        // If file exists, check if it's writable
        if (file_exists($log_file) && !is_writable($log_file)) {
            return false;
        }
        
        return true;
    }
} 