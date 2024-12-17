(function($) {
    'use strict';

    $(document).ready(function() {
        // Function to show admin notices
        function showNotice(type, message) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = $(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`);
            
            // Remove existing notices
            $('.notice').remove();
            
            // Add new notice at the top of the page
            $('.wrap > h1').after(notice);
            
            // Make notice dismissible
            if (wp.notices && wp.notices.removeDismissible) {
                wp.notices.removeDismissible(notice);
            }
        }

        // Handle individual post conversion
        $('.kloudwebp-convert-post').on('click', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const row = button.closest('tr');
            const postId = button.data('post-id');
            
            // Disable button and show loading state
            button.prop('disabled', true)
                  .addClass('updating-message')
                  .text(kloudwebpAjax.converting);
            
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
                        row.find('.progress-text').text(percentage + '%');
                        
                        // Update images count
                        row.find('.column-images').text(
                            response.data.converted + ' / ' + response.data.total
                        );
                        
                        // Show success message
                        showNotice('success', response.data.message);
                        
                        // Update button state
                        if (response.data.converted === response.data.total) {
                            button.slideUp(300, function() {
                                $(this).remove();
                            });
                        } else {
                            button.removeClass('updating-message')
                                  .text(kloudwebpAjax.convert)
                                  .prop('disabled', false);
                        }

                        // Update stats cards if available
                        updateStatsCards();
                    } else {
                        showNotice('error', response.data || 'Error converting images');
                        button.removeClass('updating-message')
                              .text(kloudwebpAjax.convert)
                              .prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'Server error: ' + error);
                    button.removeClass('updating-message')
                          .text(kloudwebpAjax.convert)
                          .prop('disabled', false);
                }
            });
        });

        // Handle bulk conversion form submission
        $('.kloudwebp-bulk-convert').on('submit', function(e) {
            const form = $(this);
            const submitButton = form.find('button[type="submit"]');
            
            // Show loading state
            submitButton.addClass('updating-message')
                       .prop('disabled', true);
        });

        // Handle post type and status filters
        $('#post-type-filter, #conversion-status-filter').on('change', function() {
            const postType = $('#post-type-filter').val();
            const status = $('#conversion-status-filter').val();
            
            $('.kloudwebp-posts-table tbody tr').each(function() {
                const row = $(this);
                const rowPostType = row.find('td:nth-child(2)').text().toLowerCase();
                const progressBar = row.find('.conversion-progress');
                const percentage = parseInt(progressBar.width() / progressBar.parent().width() * 100);
                
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

            // Show message if no rows visible
            const visibleRows = $('.kloudwebp-posts-table tbody tr:visible').length;
            const noResults = $('.no-results-message');
            
            if (visibleRows === 0) {
                if (noResults.length === 0) {
                    $('.kloudwebp-posts-table').after(
                        '<p class="no-results-message">' + 
                        'No posts found matching the selected filters.' +
                        '</p>'
                    );
                }
            } else {
                noResults.remove();
            }
        });

        // Function to update stats cards
        function updateStatsCards() {
            $.ajax({
                url: kloudwebpAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kloudwebp_get_stats',
                    nonce: kloudwebpAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.stats-card').each(function() {
                            const card = $(this);
                            const statType = card.data('stat-type');
                            if (response.data[statType]) {
                                card.find('.stat-value').text(response.data[statType]);
                            }
                        });
                    }
                }
            });
        }

        // Handle post conversion button clicks
        $('.convert-post-button').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var post_id = button.data('post-id');
            var originalText = button.text();

            // Disable button and show loading state
            button.prop('disabled', true).text(kloudwebpAjax.converting);

            $.ajax({
                url: kloudwebpAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kloudwebp_convert_post',
                    post_id: post_id,
                    nonce: kloudwebpAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        button.removeClass('button-primary')
                              .addClass('button-disabled')
                              .text(kloudwebpAjax.success);
                        updateStats();
                    } else {
                        button.text(kloudwebpAjax.error);
                        console.error('Conversion failed:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    button.text(kloudwebpAjax.error);
                    console.error('AJAX error:', error);
                },
                complete: function() {
                    setTimeout(function() {
                        button.prop('disabled', false).text(originalText);
                    }, 2000);
                }
            });
        });

        function updateStats() {
            $.ajax({
                url: kloudwebpAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kloudwebp_get_stats',
                    nonce: kloudwebpAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#total-images').text(response.data.total_images);
                        $('#converted-images').text(response.data.converted_images);
                        $('#space-saved').text(response.data.space_saved);
                    }
                }
            });
        }

        // Initialize tooltips if using Bootstrap
        if (typeof $().tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }
    });
})(jQuery);
