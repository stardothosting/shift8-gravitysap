<?php
/**
 * Settings page template for Shift8 GravitySAP
 *
 * @package Shift8\GravitySAP
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('shift8_gravitysap_settings', array());
$endpoint = isset($settings['sap_endpoint']) ? $settings['sap_endpoint'] : '';
$company_db = isset($settings['sap_company_db']) ? $settings['sap_company_db'] : '';
$username = isset($settings['sap_username']) ? $settings['sap_username'] : '';
$password = isset($settings['sap_password']) ? $settings['sap_password'] : '';
$enable_logging = isset($settings['enable_logging']) ? $settings['enable_logging'] : '0';
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (!class_exists('GFForms')): ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e('Gravity Forms Required', 'shift8-gravitysap'); ?></strong><br>
            <?php esc_html_e('This plugin requires Gravity Forms to be installed and activated for full functionality. You can configure SAP connection settings below, but form integration will not work until Gravity Forms is active.', 'shift8-gravitysap'); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="shift8-gravitysap-admin">
        <div class="shift8-gravitysap-main">
            <form method="post" action="options.php">
                <?php settings_fields('shift8_gravitysap_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sap_endpoint"><?php esc_html_e('SAP Endpoint', 'shift8-gravitysap'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="sap_endpoint" name="shift8_gravitysap_settings[sap_endpoint]" 
                                   value="<?php echo esc_attr($endpoint); ?>" class="regular-text" required>
                            <p class="description">
                                <?php esc_html_e('Enter the SAP Service Layer endpoint URL (e.g., https://your-sap-server:50000/b1s/v1/)', 'shift8-gravitysap'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sap_company_db"><?php esc_html_e('Company Database', 'shift8-gravitysap'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sap_company_db" name="shift8_gravitysap_settings[sap_company_db]" 
                                   value="<?php echo esc_attr($company_db); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sap_username"><?php esc_html_e('Username', 'shift8-gravitysap'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sap_username" name="shift8_gravitysap_settings[sap_username]" 
                                   value="<?php echo esc_attr($username); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sap_password"><?php esc_html_e('Password', 'shift8-gravitysap'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="sap_password" name="shift8_gravitysap_settings[sap_password]" 
                                   value="<?php echo esc_attr($password); ?>" class="regular-text" required>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="enable_logging"><?php esc_html_e('Debug Logging', 'shift8-gravitysap'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="enable_logging" name="shift8_gravitysap_settings[enable_logging]" 
                                       value="1" <?php checked($enable_logging, '1'); ?>>
                                <?php esc_html_e('Enable debug logging', 'shift8-gravitysap'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('When enabled, all SAP API interactions will be logged to the WordPress debug log.', 'shift8-gravitysap'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <div class="shift8-gravitysap-sidebar">
            <div class="postbox">
                <h3 class="hndle"><?php esc_html_e('Quick Start Guide', 'shift8-gravitysap'); ?></h3>
                <div class="inside">
                    <ol>
                        <li><?php esc_html_e('Configure your SAP connection settings above', 'shift8-gravitysap'); ?></li>
                        <li><?php esc_html_e('Test the connection to ensure it works', 'shift8-gravitysap'); ?></li>
                        <li><?php esc_html_e('Go to a Gravity Form and add a "SAP B1 Integration" feed', 'shift8-gravitysap'); ?></li>
                        <li><?php esc_html_e('Map form fields to SAP Business Partner fields', 'shift8-gravitysap'); ?></li>
                        <li><?php esc_html_e('Test form submission to create Business Partners in SAP', 'shift8-gravitysap'); ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <hr>

    <h2><?php esc_html_e('Test Connection', 'shift8-gravitysap'); ?></h2>
    <p><?php esc_html_e('Test your SAP connection settings before saving.', 'shift8-gravitysap'); ?></p>
    
    <div id="sap-connection-result" class="notice" style="display: none; margin: 10px 0;"></div>
    
    <button type="button" id="test-connection" class="button button-secondary">
        <?php esc_html_e('Test SAP Connection', 'shift8-gravitysap'); ?>
    </button>
</div>

<style>
.shift8-gravitysap-admin {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.shift8-gravitysap-sidebar .postbox {
    margin-bottom: 20px;
}

#sap-connection-result {
    margin-left: 10px;
    font-weight: bold;
}

#sap-connection-result.success {
    color: #46b450;
}

#sap-connection-result.error {
    color: #dc3232;
}
</style> 