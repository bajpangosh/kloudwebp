(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle individual post conversion
        $('.kloudwebp-convert-post').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const postId = button.data('post-id');
            const row = button.closest('tr');
            
            // Disable button and show loading state
            button.prop('disabled', true).text('Converting...');
            
            // Send AJAX request
            $.ajax({
                url: kloudwebpAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kloudwebp_convert_post',
                    post_id: postId,
                    nonce: kloudwebpAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update progress bar
                        const percentage = (response.data.converted / response.data.total) * 100;
                        row.find('.conversion-progress').css('width', percentage + '%');
                        
                        // Update images count
                        row.find('.column-images').text(response.data.converted + ' / ' + response.data.total);
                        
                        // Show success message
                        showNotice('success', response.data.message);
                        
                        // Remove convert button if all images are converted
                        if (response.data.converted === response.data.total) {
                            button.remove();
                        }
                    } else {
                        showNotice('error', response.data);
                        button.prop('disabled', false).text('Convert');
                    }
                },
                error: function() {
                    showNotice('error', 'An error occurred during conversion');
                    button.prop('disabled', false).text('Convert');
                }
            });
        });

        // Handle post type and status filters
        $('#post-type-filter, #conversion-status-filter').on('change', function() {
            const postType = $('#post-type-filter').val();
            const status = $('#conversion-status-filter').val();
            
            // Filter table rows based on selection
            $('.kloudwebp-posts-table tbody tr').each(function() {
                const row = $(this);
                const rowPostType = row.find('td:nth-child(2)').text().toLowerCase();
                const progressBar = row.find('.conversion-progress');
                const percentage = parseInt(progressBar.css('width')) / parseInt(progressBar.parent().css('width')) * 100;
                
                let showRow = true;
                
                // Filter by post type
                if (postType && rowPostType !== postType) {
                    showRow = false;
                }
                
                // Filter by conversion status
                if (status) {
                    switch(status) {
                        case 'unconverted':
                            if (percentage > 0) showRow = false;
                            break;
                        case 'partial':
                            if (percentage === 0 || percentage === 100) showRow = false;
                            break;
                        case 'converted':
                            if (percentage < 100) showRow = false;
                            break;
                    }
                }
                
                row.toggle(showRow);
            });
        });
    });

    // Helper function to show admin notices
    function showNotice(type, message) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Remove existing notices
        $('.notice').remove();
        
        // Add new notice at the top of the page
        $('.wrap > h1').after(notice);
        
        // Make the notice dismissible
        if (wp.notices && wp.notices.removeDismissible) {
            wp.notices.removeDismissible(notice);
        }
    }
})(jQuery);
