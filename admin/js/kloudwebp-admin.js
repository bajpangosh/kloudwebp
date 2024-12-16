(function($) {
    'use strict';

    $(document).ready(function() {
        $('.kloudwebp-convert-post').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const postId = button.data('post-id');
            const row = button.closest('tr');
            
            // Disable button and show loading state
            button.prop('disabled', true).text(kloudwebpAjax.converting);

            $.ajax({
                url: kloudwebpAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kloudwebp_convert_single_post',
                    nonce: kloudwebpAjax.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        const results = response.data;
                        
                        // Update the status cells
                        row.find('.column-images').text(
                            results.success + ' / ' + 
                            (results.success + results.failed + results.skipped)
                        );
                        
                        // Update progress bar
                        const total = results.success + results.failed + results.skipped;
                        const percentage = total > 0 ? Math.round((results.success / total) * 100) : 0;
                        const progressBar = row.find('.conversion-progress');
                        progressBar.css('width', percentage + '%');
                        
                        // Update button text and status
                        button.text(kloudwebpAjax.success);
                        if (percentage === 100) {
                            button.remove(); // Remove button if all images converted
                        } else {
                            button.prop('disabled', false);
                        }
                    } else {
                        button.text(kloudwebpAjax.error).addClass('button-danger');
                        setTimeout(() => {
                            button.text('Convert').removeClass('button-danger').prop('disabled', false);
                        }, 3000);
                    }
                },
                error: function() {
                    button.text(kloudwebpAjax.error).addClass('button-danger');
                    setTimeout(() => {
                        button.text('Convert').removeClass('button-danger').prop('disabled', false);
                    }, 3000);
                }
            });
        });
    });
})(jQuery);
