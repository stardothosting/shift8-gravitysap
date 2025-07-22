<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="shift8-gravitysap-admin">
        <div class="shift8-gravitysap-main">
            <form method="post" action="options.php">
                <?php settings_fields('shift8_gravitysap_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sap_endpoint"><?php esc_html_e('SAP Service Layer Endpoint', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="sap_endpoint" 
                                   name="shift8_gravitysap_settings[sap_endpoint]" 
                                   value="<?php echo esc_attr($settings['sap_endpoint']); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">
                                <?php esc_html_e('The URL of your SAP Service Layer endpoint (e.g., https://your-sap-server:50000/b1s/v1)', 'shift8-gravity-forms-sap-b1-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sap_company_db"><?php esc_html_e('Company Database', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="sap_company_db" 
                                   name="shift8_gravitysap_settings[sap_company_db]" 
                                   value="<?php echo esc_attr($settings['sap_company_db']); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">
                                <?php esc_html_e('Your SAP Business One company database name', 'shift8-gravity-forms-sap-b1-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sap_username"><?php esc_html_e('Username', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="sap_username" 
                                   name="shift8_gravitysap_settings[sap_username]" 
                                   value="<?php echo esc_attr($settings['sap_username']); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">
                                <?php esc_html_e('Your SAP Business One username', 'shift8-gravity-forms-sap-b1-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sap_password"><?php echo esc_html__('Password', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="sap_password" name="shift8_gravitysap_settings[sap_password]" value="" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Your SAP Business One Service Layer password', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sap_debug"><?php echo esc_html__('Debug Logging', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="sap_debug" name="shift8_gravitysap_settings[sap_debug]" value="1" <?php checked(isset($settings['sap_debug']) ? $settings['sap_debug'] : '0', '1'); ?> />
                                <?php echo esc_html__('Enable debug logging', 'shift8-gravity-forms-sap-b1-integration'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Log detailed information about API requests and responses. Only enable this when troubleshooting issues.', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" id="test-connection" class="button button-secondary">
                        <?php esc_html_e('Test SAP Connection', 'shift8-gravity-forms-sap-b1-integration'); ?>
                    </button>
                    <span id="connection-result"></span>
                    <?php submit_button(); ?>
                </p>
            </form>
            
            <!-- Log Management Section -->
            <?php if (isset($settings['sap_debug']) && $settings['sap_debug'] === '1'): ?>
            <div class="shift8-gravitysap-logs" style="margin-top: 30px;">
                <h2><?php esc_html_e('Debug Log Management', 'shift8-gravity-forms-sap-b1-integration'); ?></h2>
                <div class="card">
                    <h3><?php esc_html_e('Log File Information', 'shift8-gravity-forms-sap-b1-integration'); ?></h3>
                    <div id="log-info">
                        <p><strong><?php esc_html_e('Log File:', 'shift8-gravity-forms-sap-b1-integration'); ?></strong> 
                           <code><?php echo esc_html(Shift8_GravitySAP_Admin::get_log_file_path()); ?></code></p>
                        <p><strong><?php esc_html_e('Size:', 'shift8-gravity-forms-sap-b1-integration'); ?></strong> 
                           <span id="log-size"><?php echo esc_html(Shift8_GravitySAP_Admin::get_log_file_size()); ?></span></p>
                    </div>
                    
                    <p>
                        <button type="button" id="view-logs" class="button">
                            <?php esc_html_e('View Recent Logs', 'shift8-gravity-forms-sap-b1-integration'); ?>
                        </button>
                        <button type="button" id="clear-logs" class="button">
                            <?php esc_html_e('Clear Log File', 'shift8-gravity-forms-sap-b1-integration'); ?>
                        </button>
                        <?php if (file_exists(Shift8_GravitySAP_Admin::get_log_file_path())): ?>
                        <a href="<?php echo esc_url(Shift8_GravitySAP_Admin::get_log_file_url()); ?>" 
                           class="button" download>
                            <?php esc_html_e('Download Log File', 'shift8-gravity-forms-sap-b1-integration'); ?>
                        </a>
                        <?php endif; ?>
                    </p>
                    
                    <div id="log-viewer" style="display: none; margin-top: 20px;">
                        <h4><?php esc_html_e('Recent Log Entries', 'shift8-gravity-forms-sap-b1-integration'); ?></h4>
                        <textarea id="log-content" readonly style="width: 100%; height: 300px; font-family: monospace; font-size: 12px;"></textarea>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div> 