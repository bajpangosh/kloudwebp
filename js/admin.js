jQuery(document).ready(function($) {
    var conversionInProgress = false;
    var progressInterval;
    
    function processBatch(offset) {
        $.ajax({
            url: kloudwebpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'kloudwebp_process_batch',
                nonce: kloudwebpAjax.nonce,
                offset: offset
            },
            success: function(response) {
                if (response.success) {
                    var results = response.data;
                    updateStats(results);
                    
                    if (!results.done) {
                        // Process next batch
                        processBatch(results.next_offset);
                    } else {
                        // Conversion complete
                        clearInterval(progressInterval);
                        conversionInProgress = false;
                        $('#convert-images').prop('disabled', false);
                        logMessage('Conversion complete! Total space saved: ' + results.space_saved, 'success');
                    }
                } else {
                    logMessage('Error processing batch: ' + response.data.message, 'error');
                    clearInterval(progressInterval);
                    conversionInProgress = false;
                    $('#convert-images').prop('disabled', false);
                }
            },
            error: function() {
                logMessage('Error processing batch', 'error');
                clearInterval(progressInterval);
                conversionInProgress = false;
                $('#convert-images').prop('disabled', false);
            }
        });
    }
    
    function updateProgress() {
        $.ajax({
            url: kloudwebpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'kloudwebp_get_progress',
                nonce: kloudwebpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var progress = response.data;
                    var percentage = Math.round((progress.current / progress.total) * 100);
                    
                    $('.kloudwebp-progress-bar').css('width', percentage + '%');
                    $('.kloudwebp-status').html(progress.status);
                }
            }
        });
    }
    
    function logMessage(message, type) {
        var $log = $('#conversion-log');
        var timestamp = new Date().toLocaleTimeString();
        var className = type ? 'kloudwebp-log-entry ' + type : 'kloudwebp-log-entry';
        
        $log.append(
            $('<div/>')
                .addClass(className)
                .text('[' + timestamp + '] ' + message)
        );
        
        $log.scrollTop($log[0].scrollHeight);
    }
    
    function updateStats(results) {
        if (results.original_size) {
            $('#original-size').text(results.original_size);
        }
        if (results.converted_size) {
            $('#converted-size').text(results.converted_size);
        }
        if (results.space_saved) {
            $('#space-saved').text(results.space_saved);
        }
        
        // Refresh the stats cards
        $.ajax({
            url: kloudwebpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'kloudwebp_get_stats',
                nonce: kloudwebpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.kloudwebp-stat-card .number').each(function() {
                        var key = $(this).parent().find('h3').text().toLowerCase();
                        $(this).text(response.data[key]);
                    });
                }
            }
        });
    }
    
    $('#convert-images').on('click', function(e) {
        e.preventDefault();
        
        if (conversionInProgress) {
            return;
        }
        
        var $button = $(this);
        var $progress = $('#conversion-progress');
        var $log = $('#conversion-log');
        
        // Reset and show progress
        $('.kloudwebp-progress-bar').css('width', '0%');
        $progress.show();
        $log.empty().show();
        
        // Disable button and start conversion
        $button.prop('disabled', true);
        conversionInProgress = true;
        
        logMessage('Starting batch conversion...', 'info');
        
        // Start progress updates
        progressInterval = setInterval(updateProgress, 1000);
        
        // Start batch processing
        processBatch(0);
    });
    
    $('#cleanup-files').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        
        $button.prop('disabled', true);
        logMessage('Starting file cleanup...', 'info');
        
        $.ajax({
            url: kloudwebpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'kloudwebp_cleanup_files',
                nonce: kloudwebpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    logMessage(response.data.message, 'success');
                } else {
                    logMessage('Error cleaning up files', 'error');
                }
            },
            error: function() {
                logMessage('Error cleaning up files', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    $('#clear-cache').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: kloudwebpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'kloudwebp_clear_cache',
                nonce: kloudwebpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    logMessage('Cache cleared successfully', 'success');
                } else {
                    logMessage('Error clearing cache', 'error');
                }
            },
            error: function() {
                logMessage('Error clearing cache', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    $('#regenerate-thumbnails').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        
        $button.prop('disabled', true);
        logMessage('Starting thumbnail regeneration...', 'info');
        
        $.ajax({
            url: kloudwebpAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'kloudwebp_regenerate_thumbnails',
                nonce: kloudwebpAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    logMessage('Thumbnails regenerated successfully', 'success');
                } else {
                    logMessage('Error regenerating thumbnails', 'error');
                }
            },
            error: function() {
                logMessage('Error regenerating thumbnails', 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
});
