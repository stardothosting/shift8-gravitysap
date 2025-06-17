jQuery(document).ready(function($) {
    // Test SAP connection
    $('#test-connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $result = $('#connection-result');
        
        $button.prop('disabled', true);
        $result.html('').removeClass('success error');
        
        $.ajax({
            url: shift8GravitySAP.ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_gravitysap_test_connection',
                nonce: shift8GravitySAP.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('Connection successful!').addClass('success');
                } else {
                    $result.html('Connection failed: ' + response.data.message).addClass('error');
                }
            },
            error: function(xhr, status, error) {
                $result.html('Connection failed: Server error - ' + error).addClass('error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    // View logs
    $('#view-logs').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $viewer = $('#log-viewer');
        var $content = $('#log-content');
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: shift8GravitySAP.ajaxurl,
            type: 'POST',
            data: {
                action: 'shift8_gravitysap_get_custom_logs',
                nonce: shift8GravitySAP.nonce
            },
            success: function(response) {
                if (response.success) {
                    $content.val(response.data.logs.join('\n'));
                    $('#log-size').text(response.data.log_size);
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
    $('#clear-logs').on('click', function(e) {
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
                action: 'shift8_gravitysap_clear_custom_log',
                nonce: shift8GravitySAP.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#log-content').val('');
                    $('#log-viewer').hide();
                    $('#log-size').text('0 B');
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
}); 