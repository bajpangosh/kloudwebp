jQuery(document).ready(function($) {
    // Log container element
    const $log = $('#kloudwebp-log');
    
    // Add log message function
    function addLog(message) {
        const timestamp = new Date().toLocaleTimeString();
        $log.append(`[${timestamp}] ${message}\n`);
        $log.scrollTop($log[0].scrollHeight);
    }

    // Update post status in table
    function updatePostStatus(postId, status) {
        const $row = $(`#post-${postId}`);
        if ($row.length) {
            $.post(ajaxurl, {
                action: 'kloudwebp_get_conversion_status',
                nonce: kloudwebpAjax.nonce,
                post_id: postId
            }, function(response) {
                if (response.success) {
                    const data = response.data;
                    $row.find('.kloudwebp-status')
                        .removeClass()
                        .addClass(`kloudwebp-status ${data.class}`)
                        .text(data.label);
                }
            });
        }
    }

    // Single post conversion
    $('.kloudwebp-convert-single').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const postId = $button.data('post-id');
        
        // Disable button and show loading state
        $button.prop('disabled', true)
               .addClass('kloudwebp-loading')
               .text(kloudwebpAjax.strings.converting);
        
        addLog(`Starting conversion for post ID: ${postId}`);

        $.post(ajaxurl, {
            action: 'kloudwebp_convert_single',
            nonce: kloudwebpAjax.nonce,
            post_id: postId
        }, function(response) {
            if (response.success) {
                const data = response.data;
                addLog(`Conversion complete for post ID: ${postId}`);
                addLog(`Converted: ${data.converted}, Failed: ${data.failed}, Skipped: ${data.skipped}`);
                
                // Update status in table
                updatePostStatus(postId, data.status);
            } else {
                addLog(`Error converting post ID: ${postId}`);
            }
        }).fail(function() {
            addLog(`Failed to convert post ID: ${postId}`);
        }).always(function() {
            // Reset button state
            $button.prop('disabled', false)
                   .removeClass('kloudwebp-loading')
                   .text(kloudwebpAjax.strings.converted);
        });
    });

    // Bulk conversion
    $('#kloudwebp-bulk-convert').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        
        // Disable button and show loading state
        $button.prop('disabled', true)
               .addClass('kloudwebp-loading')
               .text(kloudwebpAjax.strings.converting);
        
        addLog('Starting bulk conversion...');

        // Get all posts that need conversion
        $.post(ajaxurl, {
            action: 'kloudwebp_convert_bulk',
            nonce: kloudwebpAjax.nonce
        }, function(response) {
            if (response.success) {
                const data = response.data;
                const total = data.total;
                const postIds = data.ids;
                
                addLog(`Found ${total} posts/pages to process`);
                
                // Process posts in batches to avoid timeout
                let processed = 0;
                const batchSize = 5;
                
                function processBatch() {
                    const batch = postIds.slice(processed, processed + batchSize);
                    if (batch.length === 0) {
                        addLog('Bulk conversion complete!');
                        $button.prop('disabled', false)
                               .removeClass('kloudwebp-loading')
                               .text('Bulk Convert All Images');
                        return;
                    }
                    
                    // Process each post in the batch
                    const promises = batch.map(postId => {
                        return $.post(ajaxurl, {
                            action: 'kloudwebp_convert_single',
                            nonce: kloudwebpAjax.nonce,
                            post_id: postId
                        }).then(function(response) {
                            if (response.success) {
                                const data = response.data;
                                addLog(`Processed post ID: ${postId} - Converted: ${data.converted}, Failed: ${data.failed}, Skipped: ${data.skipped}`);
                                updatePostStatus(postId, data.status);
                            } else {
                                addLog(`Error processing post ID: ${postId}`);
                            }
                        }).fail(function() {
                            addLog(`Failed to process post ID: ${postId}`);
                        });
                    });
                    
                    // Wait for all posts in batch to complete
                    $.when.apply($, promises).always(function() {
                        processed += batch.length;
                        const progress = Math.round((processed / total) * 100);
                        addLog(`Progress: ${progress}% (${processed}/${total})`);
                        $button.text(`Converting... ${progress}%`);
                        
                        // Process next batch
                        setTimeout(processBatch, 1000);
                    });
                }
                
                // Start processing
                processBatch();
            } else {
                addLog('Error starting bulk conversion');
                $button.prop('disabled', false)
                       .removeClass('kloudwebp-loading')
                       .text('Bulk Convert All Images');
            }
        }).fail(function() {
            addLog('Failed to start bulk conversion');
            $button.prop('disabled', false)
                   .removeClass('kloudwebp-loading')
                   .text('Bulk Convert All Images');
        });
    });
});
