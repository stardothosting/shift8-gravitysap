jQuery(document).ready(function($) {
    console.log('Shift8 GravitySAP admin script loaded');

    // Test connection button click handler
    $('#test-connection').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $result = $('#sap-connection-result');
        
        // Disable button and show loading state
        $button.prop('disabled', true);
        $result.removeClass('notice-success notice-error').addClass('notice-info').show().text('Testing connection...');
        
        // Get form values
        const data = {
            action: 'shift8_gravitysap_test_connection',
            nonce: shift8GravitySAP.nonce,
            endpoint: $('#sap_endpoint').val(),
            company_db: $('#sap_company_db').val(),
            username: $('#sap_username').val(),
            password: $('#sap_password').val()
        };
        
        // Make AJAX request
        $.post(shift8GravitySAP.ajaxurl, data, function(response) {
            if (response.success) {
                $result.removeClass('notice-info notice-error').addClass('notice-success')
                    .text('Connection successful!');
            } else {
                $result.removeClass('notice-info notice-success').addClass('notice-error')
                    .text('Connection failed: ' + response.data);
            }
        }).fail(function(xhr, status, error) {
            $result.removeClass('notice-info notice-success').addClass('notice-error')
                .text('Connection failed: ' + error);
        }).always(function() {
            $button.prop('disabled', false);
        });
    });

    // View logs
    $('#view-log').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $viewer = $('#log-viewer');
        var $textarea = $viewer.find('textarea');
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: shift8GravitySAP.ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_gravitysap_get_logs',
                nonce: shift8GravitySAP.nonce
            },
            success: function(response) {
                if (response.success) {
                    $textarea.val(response.data.logs.join('\n'));
                    $viewer.show();
                } else {
                    alert('Failed to load logs: ' + response.data.message);
                }
            },
            error: function() {
                alert('Failed to load logs: Server error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Clear logs
    $('#clear-log').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to clear the log file?')) {
            return;
        }
        
        var $button = $(this);
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: shift8GravitySAP.ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_gravitysap_clear_log',
                nonce: shift8GravitySAP.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#log-viewer textarea').val('');
                    alert('Log file cleared successfully');
                } else {
                    alert('Failed to clear log: ' + response.data.message);
                }
            },
            error: function() {
                alert('Failed to clear log: Server error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // Handle SAP field refresh
    $(document).on('click', '#refresh_sap_fields', function() {
        console.log('Refresh button clicked');
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $status = $button.next('.spinner').next('.refresh-status');
        var entityType = $('select[name="_gform_setting_sap_entity_type"]').val();

        console.log('Entity type:', entityType);

        if (!entityType) {
            $status.removeClass('success').addClass('error').text('Please select an entity type first');
            return;
        }

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $status.removeClass('success error').text('Refreshing fields...');

        $.ajax({
            url: shift8GravitySAP.ajaxurl,
            type: 'POST',
            data: {
                action: 'refresh_sap_fields',
                entity_type: entityType,
                nonce: shift8GravitySAP.nonce
            },
            success: function(response) {
                console.log('Refresh response:', response);
                if (response.success) {
                    $status.removeClass('error').addClass('success').text('Fields refreshed successfully');
                    // Reload the page to show updated fields
                    window.location.reload();
                } else {
                    $status.removeClass('success').addClass('error').text(response.data.message || 'Failed to refresh fields');
                }
            },
            error: function(xhr, status, error) {
                console.error('Refresh error:', {xhr: xhr, status: status, error: error});
                $status.removeClass('success').addClass('error').text('Failed to refresh fields');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });

    // Handle form submission
    $(document).on('submit', 'form#gform-settings', function(e) {
        console.log('Form submission started');
        var $form = $(this);
        var $submitButton = $form.find('input[type="submit"]');
        var $spinner = $submitButton.next('.spinner');
        
        console.log('Form data:', $form.serialize());
        console.log('Form action:', $form.attr('action'));
        console.log('Form method:', $form.attr('method'));
        
        $submitButton.prop('disabled', true);
        $spinner.addClass('is-active');

        // Let Gravity Forms handle the submission
        return true;
    });

    // Log when the form is loaded
    console.log('Form settings form found:', $('form#gform-settings').length > 0);
    console.log('Form fields:', $('form#gform-settings').find('input, select, textarea').length);
    console.log('Form action:', $('form#gform-settings').attr('action'));
    console.log('Form method:', $('form#gform-settings').attr('method'));
}); 