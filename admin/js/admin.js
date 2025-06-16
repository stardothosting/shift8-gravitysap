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
}); 