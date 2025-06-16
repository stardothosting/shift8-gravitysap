jQuery(document).ready(function($) {
    'use strict';

    // Test SAP connection
    $('#test-connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $result = $('#connection-result');
        
        $button.prop('disabled', true).text('Testing...');
        $result.removeClass('success error').text('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_gravitysap_test_connection',
                nonce: shift8_gravitysap.nonce,
                sap_endpoint: $('#sap_endpoint').val(),
                sap_company_db: $('#sap_company_db').val(),
                sap_username: $('#sap_username').val(),
                sap_password: $('#sap_password').val()
            },
            success: function(response) {
                console.log('Raw response:', response); // Debug log
                if (response.success && response.data.success) {
                    $result.addClass('success').text('✓ ' + response.data.message);
                } else {
                    var errorMessage = '✗ ' + (response.data.message || 'Connection failed');
                    if (response.data.details) {
                        errorMessage += '\n\nDetails:\n' +
                            'Endpoint: ' + response.data.details.endpoint + '\n' +
                            'Company DB: ' + response.data.details.company_db + '\n' +
                            'Username: ' + response.data.details.username;
                    }
                    $result.addClass('error').text(errorMessage);
                }
            },
            error: function(xhr, status, error) {
                console.log('XHR:', xhr); // Debug log
                console.log('Status:', status); // Debug log
                console.log('Error:', error); // Debug log
                
                var errorMessage = '✗ Request failed\n\n';
                errorMessage += 'Status: ' + status + '\n';
                errorMessage += 'Error: ' + error + '\n';
                
                if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        errorMessage += '\nResponse: ' + JSON.stringify(response, null, 2);
                    } catch (e) {
                        errorMessage += '\nResponse: ' + xhr.responseText;
                    }
                }
                
                $result.addClass('error').text(errorMessage);
            },
            complete: function() {
                $button.prop('disabled', false).text('Test SAP Connection');
            }
        });
    });

    // View log entries
    $('#view-log').on('click', function(e) {
        e.preventDefault();
        
        var $viewer = $('#log-viewer');
        var $textarea = $viewer.find('textarea');
        
        if ($viewer.is(':visible')) {
            $viewer.hide();
            $(this).text('View Recent Logs');
            return;
        }
        
        $viewer.show();
        $(this).text('Hide Logs');
        
        // Load log entries
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_gravitysap_get_logs'
            },
            success: function(response) {
                if (response.success && response.data.logs) {
                    $textarea.val(response.data.logs.join('\n'));
                } else {
                    $textarea.val('No log entries found or unable to load logs.');
                }
            },
            error: function() {
                $textarea.val('Error loading log entries.');
            }
        });
    });

    // Clear log
    $('#clear-log').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to clear the log file? This action cannot be undone.')) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('Clearing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_gravitysap_clear_log'
            },
            success: function(response) {
                if (response.success) {
                    alert('Log cleared successfully');
                    // Clear the viewer if it's open
                    $('#log-viewer textarea').val('');
                } else {
                    alert('Failed to clear log');
                }
            },
            error: function() {
                alert('Request failed');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Form validation
    $('form').on('submit', function(e) {
        var isValid = true;
        var $form = $(this);
        
        // Check required fields
        $form.find('[required]').each(function() {
            var $field = $(this);
            if (!$field.val().trim()) {
                $field.addClass('error');
                isValid = false;
            } else {
                $field.removeClass('error');
            }
        });
        
        // Validate URL format for endpoint
        var endpoint = $('#sap_endpoint').val();
        if (endpoint && !isValidUrl(endpoint)) {
            $('#sap_endpoint').addClass('error');
            alert('Please enter a valid SAP Service Layer endpoint URL');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields correctly');
        }
    });
    
    // Remove error class on input
    $('input').on('input', function() {
        $(this).removeClass('error');
    });
    
    // Helper function to validate URL
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
});

// Add some CSS for better UX
jQuery(document).ready(function($) {
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .shift8-gravitysap-admin {
                display: grid;
                grid-template-columns: 2fr 1fr;
                gap: 20px;
                margin-top: 20px;
            }
            
            .shift8-gravitysap-sidebar .postbox {
                margin-bottom: 20px;
            }
            
            .shift8-status-good {
                color: #46b450;
                font-weight: bold;
            }
            
            .shift8-status-error {
                color: #dc3232;
                font-weight: bold;
            }
            
            #connection-result {
                margin-left: 10px;
                font-weight: bold;
            }
            
            #connection-result.success {
                color: #46b450;
            }
            
            #connection-result.error {
                color: #dc3232;
            }
            
            input.error {
                border-color: #dc3232 !important;
                box-shadow: 0 0 2px rgba(220, 50, 50, 0.3) !important;
            }
            
            @media (max-width: 768px) {
                .shift8-gravitysap-admin {
                    grid-template-columns: 1fr;
                }
            }
        `)
        .appendTo('head');
}); 