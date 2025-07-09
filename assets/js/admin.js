/* Mailchimp Builder Admin JavaScript */

jQuery(document).ready(function($) {
    
    console.log('Mailchimp Builder Admin JS loaded');
    console.log('jQuery version:', $.fn.jquery);
    console.log('wp object available:', typeof wp !== 'undefined');
    console.log('wp.media available:', typeof wp !== 'undefined' && typeof wp.media !== 'undefined');
    
    var $generateBtn = $('#generate-newsletter');
    var $sendBtn = $('#send-newsletter');
    var $preview = $('#newsletter-preview');
    var $message = $('#newsletter-message');
    var $subject = $('.newsletter-subject');
    var generatedContent = '';
    
    // Test email functionality
    var listMembersLoaded = false;
    
    function loadListMembers() {
        if (listMembersLoaded) return;
        
        $.ajax({
            url: mailchimp_builder.ajax_url,
            type: 'POST',
            data: {
                action: 'mailchimp_get_list_members',
                nonce: mailchimp_builder.nonce
            },
            success: function(response) {
                if (response.success && response.data.members) {
                    var $select = $('#test-email-select');
                    $select.empty().append('<option value="">Vælg en email adresse...</option>');
                    
                    $.each(response.data.members, function(index, member) {
                        $select.append(
                            $('<option></option>')
                                .attr('value', member.email)
                                .text(member.name + ' (' + member.email + ')')
                        );
                    });
                    
                    listMembersLoaded = true;
                    $('.test-email-section').show();
                } else {
                    $('#test-email-select').html('<option value="">Kunne ikke indlæse medlemmer</option>');
                }
            },
            error: function() {
                $('#test-email-select').html('<option value="">Fejl ved indlæsning</option>');
            }
        });
    }
    
    function showTestMessage(type, message) {
        var $message = $('#test-email-message');
        $message.removeClass('notice-success notice-error')
                .addClass('notice-' + type)
                .html('<p>' + message + '</p>')
                .show();
    }
    
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
                    
                    // Load test email members and show test section
                    loadListMembers();
                    
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
    
    console.log('Looking for upload button:', $('#upload-header-image').length);
    
    // Use event delegation to handle dynamically added elements
    $(document).on('click', '#upload-header-image', function(e) {
        e.preventDefault();
        console.log('Upload button clicked');
        
        // Check if wp.media is available
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            console.error('WordPress media uploader is not available');
            alert('WordPress media uploader er ikke tilgængelig. Prøv at genindlæse siden.');
            return;
        }
        
        console.log('wp.media is available');
        
        // If the media uploader already exists, reopen it
        if (mediaUploader) {
            console.log('Reopening existing media uploader');
            mediaUploader.open();
            return;
        }
        
        console.log('Creating new media uploader');
        
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
            console.log('Image selected');
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            console.log('Attachment:', attachment);
            
            $('#header_image').val(attachment.id);
            $('.header-image-preview').html('<img src="' + attachment.url + '" alt="Header billede" style="max-width: 300px; height: auto; border: 1px solid #ddd;" />');
            $('#remove-header-image').show();
        });
        
        // Open the media uploader
        console.log('Opening media uploader');
        mediaUploader.open();
    });
    
    // Remove header image
    $(document).on('click', '#remove-header-image', function(e) {
        e.preventDefault();
        console.log('Remove image clicked');
        
        $('#header_image').val('');
        $('.header-image-preview').html('');
        $(this).hide();
    });
    
    // Sponsor search functionality
    var searchTimeout;
    var sponsorIndex = $('#selected-sponsors .sponsor-item').length;
    
    $('#sponsor-search').on('input', function() {
        var searchTerm = $(this).val().trim();
        
        clearTimeout(searchTimeout);
        
        if (searchTerm.length < 2) {
            $('#sponsor-search-results').hide().empty();
            return;
        }
        
        searchTimeout = setTimeout(function() {
            searchSponsors(searchTerm);
        }, 300);
    });
    
    function searchSponsors(searchTerm) {
        $.ajax({
            url: mailchimp_builder.ajax_url,
            type: 'POST',
            data: {
                action: 'mailchimp_search_sponsors',
                nonce: mailchimp_builder.nonce,
                search: searchTerm
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    var resultsHtml = '';
                    $.each(response.data, function(index, item) {
                        // Check if already selected
                        if ($('.sponsor-item[data-id="' + item.id + '"]').length === 0) {
                            resultsHtml += '<div class="sponsor-search-result" data-id="' + item.id + '" data-type="' + item.type + '">';
                            resultsHtml += '<span class="sponsor-result-title">' + item.title + '</span>';
                            resultsHtml += '<span class="sponsor-result-type">(' + (item.type === 'butiksside' ? 'Butik' : 'Erhverv') + ')</span>';
                            resultsHtml += '</div>';
                        }
                    });
                    
                    $('#sponsor-search-results').html(resultsHtml).show();
                } else {
                    $('#sponsor-search-results').html('<div class="sponsor-search-result">Ingen resultater fundet</div>').show();
                }
            },
            error: function() {
                $('#sponsor-search-results').html('<div class="sponsor-search-result">Fejl ved søgning</div>').show();
            }
        });
    }
    
    // Add sponsor when clicking on search result
    $(document).on('click', '.sponsor-search-result', function() {
        var id = $(this).data('id');
        var type = $(this).data('type');
        var title = $(this).find('.sponsor-result-title').text();
        
        if (id && type) {
            addSponsor(id, type, title);
            $('#sponsor-search').val('');
            $('#sponsor-search-results').hide().empty();
        }
    });
    
    function addSponsor(id, type, title) {
        var sponsorHtml = '<div class="sponsor-item" data-id="' + id + '" data-type="' + type + '">';
        sponsorHtml += '<input type="hidden" name="mailchimp_builder_options[sponsors][' + sponsorIndex + '][id]" value="' + id + '" />';
        sponsorHtml += '<input type="hidden" name="mailchimp_builder_options[sponsors][' + sponsorIndex + '][type]" value="' + type + '" />';
        sponsorHtml += '<span class="sponsor-title">' + title + '</span>';
        sponsorHtml += '<span class="sponsor-type">(' + (type === 'butiksside' ? 'Butik' : 'Erhverv') + ')</span>';
        sponsorHtml += '<button type="button" class="remove-sponsor button-link-delete">×</button>';
        sponsorHtml += '</div>';
        
        $('#selected-sponsors').append(sponsorHtml);
        sponsorIndex++;
    }
    
    // Remove sponsor
    $(document).on('click', '.remove-sponsor', function(e) {
        e.preventDefault();
        $(this).closest('.sponsor-item').remove();
        // Reindex remaining sponsors
        reindexSponsors();
    });
    
    function reindexSponsors() {
        $('#selected-sponsors .sponsor-item').each(function(index) {
            $(this).find('input[type="hidden"]').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/\[\d+\]/, '[' + index + ']');
                    $(this).attr('name', name);
                }
            });
        });
        sponsorIndex = $('#selected-sponsors .sponsor-item').length;
    }
    
    // Hide search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.sponsor-search-container').length) {
            $('#sponsor-search-results').hide();
        }
    });
    
    // Send test email
    $('#send-test-email').on('click', function() {
        var $btn = $(this);
        var $message = $('#test-email-message');
        var testEmail = $('#test-email-select').val() || $('#custom-test-email').val();
        var subject = $('#newsletter-subject').val();
        
        if (!testEmail) {
            showTestMessage('error', 'Vælg venligst en email adresse eller indtast en.');
            return;
        }
        
        if (!generatedContent) {
            showTestMessage('error', 'Generer først et nyhedsbrev før test-afsendelse.');
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
                action: 'mailchimp_send_test_email',
                nonce: mailchimp_builder.nonce,
                test_email: testEmail,
                subject: subject
            },
            success: function(response) {
                if (response.success) {
                    showTestMessage('success', response.data.message);
                } else {
                    showTestMessage('error', response.data.message);
                }
            },
            error: function() {
                showTestMessage('error', 'Der opstod en fejl under afsendelse af test email.');
            },
            complete: function() {
                $btn.removeClass('loading').prop('disabled', false);
            }
        });
    });
    
    // Post Selection functionality
    var postSearchTimeout;
    var selectedPosts = [];
    
    // Initialize selected posts from existing data
    $('#selected-posts-list .selected-item').each(function() {
        var id = $(this).data('id');
        if (id) {
            selectedPosts.push(id);
        }
    });
    
    // Post search functionality
    $('#post-search-input').on('input', function() {
        var query = $(this).val();
        var $results = $('#post-search-results');
        
        clearTimeout(postSearchTimeout);
        
        if (query.length < 2) {
            $results.hide();
            return;
        }
        
        postSearchTimeout = setTimeout(function() {
            $.ajax({
                url: mailchimp_builder.ajax_url,
                type: 'POST',
                data: {
                    action: 'mailchimp_search_posts',
                    nonce: mailchimp_builder.nonce,
                    search: query
                },
                success: function(response) {
                    if (response.success) {
                        $results.empty();
                        
                        if (response.data.length > 0) {
                            $.each(response.data, function(index, post) {
                                if (selectedPosts.indexOf(post.id) === -1) {
                                    var $item = $('<div class="search-result-item" data-id="' + post.id + '">')
                                        .append('<span class="item-title">' + post.title + '</span>')
                                        .append('<span class="item-date">' + post.date + '</span>');
                                    
                                    $results.append($item);
                                }
                            });
                            $results.show();
                        } else {
                            $results.html('<div class="search-result-item">Ingen indlæg fundet</div>').show();
                        }
                    }
                },
                error: function() {
                    $results.html('<div class="search-result-item">Fejl ved søgning</div>').show();
                }
            });
        }, 300);
    });
    
    // Add post on click
    $(document).on('click', '.search-result-item', function() {
        var $item = $(this);
        var postId = $item.data('id');
        
        if (!postId || selectedPosts.indexOf(postId) !== -1) {
            return;
        }
        
        var title = $item.find('.item-title').text();
        var date = $item.find('.item-date').text();
        
        addSelectedPost(postId, title, date);
        $item.remove();
        
        if ($('#post-search-results').children().length === 0) {
            $('#post-search-results').hide();
        }
    });
    
    // Remove post
    $(document).on('click', '.remove-item', function() {
        var $item = $(this).closest('.selected-item');
        var postId = $item.data('id');
        
        selectedPosts = selectedPosts.filter(function(id) {
            return id != postId;
        });
        
        $item.remove();
        updatePostIndices();
    });
    
    // Hide search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.post-search-container').length) {
            $('#post-search-results').hide();
        }
    });
    
    // Clear search input when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#post-search-input').length) {
            $('#post-search-input').val('');
        }
    });
    
    // Make post list sortable
    if ($.fn.sortable) {
        $('#selected-posts-list').sortable({
            handle: '.drag-handle',
            placeholder: 'ui-sortable-placeholder',
            helper: 'clone',
            update: function(event, ui) {
                updatePostIndices();
            }
        });
    }
    
    function addSelectedPost(postId, title, date) {
        selectedPosts.push(postId);
        var index = selectedPosts.length - 1;
        
        var $item = $('<div class="selected-item" data-id="' + postId + '">')
            .append('<span class="drag-handle">≡</span>')
            .append('<span class="item-title">' + title + '</span>')
            .append('<span class="item-date">(' + date + ')</span>')
            .append('<button type="button" class="remove-item" data-id="' + postId + '">×</button>')
            .append('<input type="hidden" name="mailchimp_builder_options[selected_posts][' + index + '][id]" value="' + postId + '" />');
        
        $('#selected-posts-list').append($item);
    }
    
    function updatePostIndices() {
        $('#selected-posts-list .selected-item').each(function(index) {
            $(this).find('input[type="hidden"]').attr('name', 'mailchimp_builder_options[selected_posts][' + index + '][id]');
        });
    }
    
    // Toggle recurring events category field
    $('#group_recurring_events').on('change', function() {
        var $categoryRow = $('#recurring-category-row');
        if ($(this).is(':checked')) {
            $categoryRow.show();
        } else {
            $categoryRow.hide();
        }
    });
    
    // ...existing code...
});
