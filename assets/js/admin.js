/* Mailchimp Builder Admin JavaScript */

jQuery(document).ready(function($) {
    
    var $generateBtn = $('#generate-newsletter');
    var $sendBtn = $('#send-newsletter');
    var $preview = $('#newsletter-preview');
    var $message = $('#newsletter-message');
    var $subject = $('.newsletter-subject');
    var generatedContent = '';
    
    // Generate newsletter
    $generateBtn.on('click', function() {
        var $btn = $(this);
        
        // Show loading state
        $btn.addClass('loading').prop('disabled', true);
        $preview.html('<p>Genererer nyhedsbrev...</p>');
        $message.hide();
        
        // Make AJAX request
        $.ajax({
            url: mailchimp_builder.ajax_url,
            type: 'POST',
            data: {
                action: 'mailchimp_generate_newsletter',
                nonce: mailchimp_builder.nonce
            },
            success: function(response) {
                if (response.success) {
                    generatedContent = response.data.content;
                    
                    // Create iframe to display newsletter
                    var iframe = $('<iframe>')
                        .attr('srcdoc', generatedContent)
                        .css({
                            'width': '100%',
                            'min-height': '500px',
                            'border': 'none',
                            'background': 'white'
                        });
                    
                    $preview.html(iframe);
                    $subject.show();
                    $sendBtn.show();
                    
                    showMessage('success', 'Nyhedsbrev genereret succesfuldt!');
                } else {
                    showMessage('error', 'Fejl ved generering af nyhedsbrev: ' + (response.data.message || 'Ukendt fejl'));
                    $preview.html('');
                }
            },
            error: function(xhr, status, error) {
                showMessage('error', 'AJAX fejl: ' + error);
                $preview.html('');
            },
            complete: function() {
                $btn.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Send newsletter
    $sendBtn.on('click', function() {
        var $btn = $(this);
        var subject = $('#newsletter-subject').val().trim();
        
        if (!subject) {
            showMessage('error', 'Indtast venligst et emne for nyhedsbrevet.');
            $('#newsletter-subject').focus();
            return;
        }
        
        if (!generatedContent) {
            showMessage('error', 'Generer først et nyhedsbrev før afsendelse.');
            return;
        }
        
        // Confirm before sending
        if (!confirm('Er du sikker på at du vil sende nyhedsbrevet til alle abonnenter?')) {
            return;
        }
        
        // Show loading state
        $btn.addClass('loading').prop('disabled', true);
        $message.hide();
        
        // Make AJAX request
        $.ajax({
            url: mailchimp_builder.ajax_url,
            type: 'POST',
            data: {
                action: 'mailchimp_send_campaign',
                nonce: mailchimp_builder.nonce,
                content: generatedContent,
                subject: subject
            },
            success: function(response) {
                if (response.success) {
                    showMessage('success', response.data.message || 'Nyhedsbrev sendt succesfuldt!');
                    
                    // Reset form
                    generatedContent = '';
                    $preview.html('');
                    $subject.hide();
                    $sendBtn.hide();
                } else {
                    showMessage('error', 'Fejl ved afsendelse: ' + (response.data.message || 'Ukendt fejl'));
                }
            },
            error: function(xhr, status, error) {
                showMessage('error', 'AJAX fejl: ' + error);
            },
            complete: function() {
                $btn.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Helper function to show messages
    function showMessage(type, text) {
        $message
            .removeClass('notice-success notice-error')
            .addClass('notice notice-' + type)
            .html('<p>' + text + '</p>')
            .show();
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                $message.fadeOut();
            }, 5000);
        }
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $message.offset().top - 100
        }, 500);
    }
    
    // Preview newsletter in modal (optional enhancement)
    var $previewModal = null;
    
    function createPreviewModal() {
        if ($previewModal) {
            return $previewModal;
        }
        
        $previewModal = $('<div>')
            .addClass('newsletter-modal')
            .css({
                'position': 'fixed',
                'top': '0',
                'left': '0',
                'width': '100%',
                'height': '100%',
                'background': 'rgba(0,0,0,0.8)',
                'z-index': '9999',
                'display': 'none'
            })
            .appendTo('body');
        
        var $modalContent = $('<div>')
            .css({
                'position': 'absolute',
                'top': '5%',
                'left': '5%',
                'width': '90%',
                'height': '90%',
                'background': 'white',
                'border-radius': '4px',
                'overflow': 'hidden'
            })
            .appendTo($previewModal);
        
        var $modalHeader = $('<div>')
            .css({
                'padding': '15px 20px',
                'background': '#f1f1f1',
                'border-bottom': '1px solid #ddd',
                'display': 'flex',
                'justify-content': 'space-between',
                'align-items': 'center'
            })
            .appendTo($modalContent);
        
        $modalHeader.append('<h3 style="margin: 0;">Nyhedsbrev Forhåndsvisning</h3>');
        
        var $closeBtn = $('<button>')
            .text('×')
            .css({
                'background': 'none',
                'border': 'none',
                'font-size': '24px',
                'cursor': 'pointer',
                'padding': '0',
                'width': '30px',
                'height': '30px'
            })
            .on('click', function() {
                $previewModal.hide();
            })
            .appendTo($modalHeader);
        
        var $modalBody = $('<div>')
            .addClass('modal-body')
            .css({
                'padding': '20px',
                'height': 'calc(100% - 70px)',
                'overflow': 'auto'
            })
            .appendTo($modalContent);
        
        // Close modal when clicking outside
        $previewModal.on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        return $previewModal;
    }
    
    // Add preview button functionality (if needed later)
    $(document).on('click', '.preview-newsletter-modal', function() {
        if (generatedContent) {
            var $modal = createPreviewModal();
            var iframe = $('<iframe>')
                .attr('srcdoc', generatedContent)
                .css({
                    'width': '100%',
                    'height': '100%',
                    'border': 'none'
                });
            
            $modal.find('.modal-body').html(iframe);
            $modal.show();
        }
    });
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Escape key to close modal
        if (e.keyCode === 27 && $previewModal && $previewModal.is(':visible')) {
            $previewModal.hide();
        }
        
        // Ctrl/Cmd + Enter to generate newsletter
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
            if ($generateBtn.is(':visible') && !$generateBtn.prop('disabled')) {
                $generateBtn.click();
            }
        }
    });
    
    // Auto-save subject line to localStorage
    $('#newsletter-subject').on('input', function() {
        localStorage.setItem('mailchimp_newsletter_subject', $(this).val());
    });
    
    // Restore subject line from localStorage
    var savedSubject = localStorage.getItem('mailchimp_newsletter_subject');
    if (savedSubject) {
        $('#newsletter-subject').val(savedSubject);
    }
    
    // Form validation
    $('form').on('submit', function(e) {
        var apiKey = $('#mailchimp_api_key').val().trim();
        var listId = $('#mailchimp_list_id').val().trim();
        
        if (apiKey && !apiKey.match(/^[a-f0-9]{32}-[a-z]{2,4}\d+$/)) {
            alert('Mailchimp API nøglen ser ikke korrekt ud. Den skal være i formatet: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us1');
            $('#mailchimp_api_key').focus();
            e.preventDefault();
            return false;
        }
        
        if (listId && !listId.match(/^[a-f0-9]{10}$/)) {
            alert('Mailchimp Liste ID skal være 10 tegn langt og kun indeholde bogstaver og tal.');
            $('#mailchimp_list_id').focus();
            e.preventDefault();
            return false;
        }
    });
    
    // Show/hide events end date based on events checkbox
    function toggleEventsEndDate() {
        var $eventsCheckbox = $('input[name="mailchimp_builder_options[include_events]"]');
        var $eventsEndDateRow = $('#events_end_date').closest('tr');
        
        if ($eventsCheckbox.is(':checked')) {
            $eventsEndDateRow.show();
        } else {
            $eventsEndDateRow.hide();
        }
    }
    
    // Initial check
    toggleEventsEndDate();
    
    // On checkbox change
    $('input[name="mailchimp_builder_options[include_events]"]').on('change', toggleEventsEndDate);
    
    // Auto-set reasonable default date if empty
    $('#events_end_date').on('focus', function() {
        if (!$(this).val()) {
            var threeMonthsFromNow = new Date();
            threeMonthsFromNow.setMonth(threeMonthsFromNow.getMonth() + 3);
            var dateString = threeMonthsFromNow.toISOString().split('T')[0];
            $(this).val(dateString);
        }
    });
    
    // Header image upload functionality
    var mediaUploader;
    
    $('#upload-header-image').on('click', function(e) {
        e.preventDefault();
        
        // If the media uploader already exists, reopen it
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        // Create the media uploader
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Vælg Header Billede',
            button: {
                text: 'Vælg Billede'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        // When an image is selected
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            $('#header_image').val(attachment.id);
            $('.header-image-preview').html('<img src="' + attachment.url + '" alt="Header billede" style="max-width: 300px; height: auto; border: 1px solid #ddd;" />');
            $('#remove-header-image').show();
        });
        
        // Open the media uploader
        mediaUploader.open();
    });
    
    // Remove header image
    $('#remove-header-image').on('click', function(e) {
        e.preventDefault();
        
        $('#header_image').val('');
        $('.header-image-preview').html('');
        $(this).hide();
    });
    
});
