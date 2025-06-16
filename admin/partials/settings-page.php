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
                            <label for="sap_endpoint"><?php esc_html_e('SAP Service Layer Endpoint', 'shift8-gravitysap'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="sap_endpoint" 
                                   name="shift8_gravitysap_settings[sap_endpoint]" 
                                   value="<?php echo esc_attr($settings['sap_endpoint']); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">
                                <?php esc_html_e('The URL of your SAP Service Layer endpoint (e.g., https://your-sap-server:50000/b1s/v1)', 'shift8-gravitysap'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sap_company_db"><?php esc_html_e('Company Database', 'shift8-gravitysap'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="sap_company_db" 
                                   name="shift8_gravitysap_settings[sap_company_db]" 
                                   value="<?php echo esc_attr($settings['sap_company_db']); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">
                                <?php esc_html_e('Your SAP Business One company database name', 'shift8-gravitysap'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sap_username"><?php esc_html_e('Username', 'shift8-gravitysap'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="sap_username" 
                                   name="shift8_gravitysap_settings[sap_username]" 
                                   value="<?php echo esc_attr($settings['sap_username']); ?>" 
                                   class="regular-text" 
                                   required>
                            <p class="description">
                                <?php esc_html_e('Your SAP Business One username', 'shift8-gravitysap'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sap_password"><?php echo esc_html__('Password', 'shift8-gravitysap'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="sap_password" name="shift8_gravitysap_settings[sap_password]" value="" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Your SAP Business One Service Layer password', 'shift8-gravitysap'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sap_debug"><?php echo esc_html__('Debug Logging', 'shift8-gravitysap'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="sap_debug" name="shift8_gravitysap_settings[sap_debug]" value="1" <?php checked(isset($settings['sap_debug']) ? $settings['sap_debug'] : '0', '1'); ?> />
                                <?php echo esc_html__('Enable debug logging', 'shift8-gravitysap'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Log detailed information about API requests and responses. Only enable this when troubleshooting issues.', 'shift8-gravitysap'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" id="test-connection" class="button button-secondary">
                        <?php esc_html_e('Test SAP Connection', 'shift8-gravitysap'); ?>
                    </button>
                    <span id="connection-result"></span>
                    <?php submit_button(); ?>
                </p>
            </form>
        </div>
    </div>
</div> 