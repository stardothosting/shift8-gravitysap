<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('shift8_gravitysap_settings'); ?>
    
    <div class="shift8-gravitysap-admin">
        <div class="shift8-gravitysap-main">
            <form method="post" action="">
                <?php wp_nonce_field('shift8_gravitysap_settings', 'shift8_gravitysap_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sap_endpoint"><?php esc_html_e('SAP Service Layer Endpoint', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="sap_endpoint" 
                                   name="sap_endpoint" 
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
                                   name="sap_company_db" 
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
                                   name="sap_username" 
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
                            <input type="password" id="sap_password" name="sap_password" value="" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Your SAP Business One Service Layer password', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sap_ssl_verify"><?php echo esc_html__('SSL Certificate Verification', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="sap_ssl_verify" name="sap_ssl_verify" value="1" <?php checked(isset($settings['sap_ssl_verify']) ? $settings['sap_ssl_verify'] : '0', '1'); ?> />
                                <?php echo esc_html__('Verify SSL certificates (recommended for production)', 'shift8-gravity-forms-sap-b1-integration'); ?>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('⚠️ SECURITY: Leave this enabled for production. Only disable for development/testing with self-signed certificates.', 'shift8-gravity-forms-sap-b1-integration'); ?>
                                <br>
                                <?php echo esc_html__('If you see "SSL certificate problem" errors, your SAP server may be using a self-signed certificate.', 'shift8-gravity-forms-sap-b1-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sap_debug"><?php echo esc_html__('Debug Logging', 'shift8-gravity-forms-sap-b1-integration'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="sap_debug" name="sap_debug" value="1" <?php checked(isset($settings['sap_debug']) ? $settings['sap_debug'] : '0', '1'); ?> />
                                <?php echo esc_html__('Enable debug logging (requires WP_DEBUG)', 'shift8-gravity-forms-sap-b1-integration'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Log detailed information using WordPress error_log(). Requires WP_DEBUG to be enabled in wp-config.php.', 'shift8-gravity-forms-sap-b1-integration'); ?></p>
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
        </div>
    </div>
</div> 