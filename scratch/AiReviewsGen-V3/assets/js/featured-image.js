/**
 * Featured Image Generator - Meta Box JavaScript
 *
 * Handles native logo file selection, WordPress Media Library upload,
 * and AJAX-based featured image generation.
 *
 * @package AIReviewGeneratorPro
 * @since   8.1.0
 */
(function($) {
    'use strict';

    /**
     * Initialize logo upload button
     */
    function initLogoUpload() {
        var $uploadButton = $('#airg-upload-logo-btn');
        var $fileInput = $('#airg-tool-logo-file');
        var $status = $('#airg-logo-upload-status');

        $uploadButton.on('click', function(e) {
            e.preventDefault();
            $fileInput.val('').trigger('click');
        });

        $fileInput.on('change', function() {
            var file = this.files && this.files[0];
            if (!file) {
                return;
            }

            if (!file.type || file.type.indexOf('image/') !== 0) {
                alert('Please select an image file.');
                $fileInput.val('');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'airg_upload_logo');
            formData.append('nonce', airg_featured_image.upload_nonce);
            formData.append('logo', file);

            $uploadButton.prop('disabled', true);
            $status.text('Uploading logo...').show();

            $.ajax({
                url: airg_featured_image.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (!response.success || !response.data || !response.data.url) {
                        var message = response.data && response.data.message ? response.data.message : response.data;
                        alert('Logo upload failed: ' + (message || 'Unknown error'));
                        return;
                    }

                    var imageUrl = response.data.url;
                    var $preview = $('#airg-logo-preview').empty();
                    $('<img>', {
                        src: imageUrl,
                        alt: 'Tool logo'
                    }).css({
                        maxWidth: '100%',
                        height: 'auto',
                        border: '1px solid #ddd',
                        borderRadius: '4px',
                        padding: '4px'
                    }).appendTo($preview);

                    $('#airg-tool-logo').val(imageUrl);
                    $uploadButton.find('.airg-upload-logo-label').text('Change Logo');
                    $status.text('Logo uploaded successfully.').show();

                    if ($('#airg-remove-logo-btn').length === 0) {
                        $('<button>', {
                            type: 'button',
                            class: 'button',
                            id: 'airg-remove-logo-btn',
                            text: 'Remove Logo'
                        }).css({
                            width: '100%',
                            marginBottom: '12px',
                            color: '#a00'
                        }).insertAfter($status);
                    }
                },
                error: function(xhr) {
                    var response = xhr.responseJSON;
                    var message = response && response.data ? response.data : 'Request failed (' + xhr.status + ').';
                    alert('Logo upload failed: ' + message);
                },
                complete: function() {
                    $uploadButton.prop('disabled', false);
                    $fileInput.val('');
                }
            });
        });
    }

    /**
     * Initialize logo remove button
     */
    function initRemoveLogo() {
        $(document).on('click', '#airg-remove-logo-btn', function(e) {
            e.preventDefault();
            $('#airg-tool-logo').val('');
            $('#airg-logo-preview').html('');
            $('#airg-upload-logo-btn').find('.airg-upload-logo-label').text('Upload Logo');
            $('#airg-logo-upload-status').hide().text('');
            $(this).remove();
        });
    }

    /**
     * Initialize featured image generation button
     */
    function initGenerateButton() {
        $('#airg-generate-featured-image-btn').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $status = $('#airg-image-generation-status');
            var postId = $btn.data('post-id');

            if (!postId) {
                alert('Please save the post first before generating a featured image.');
                return;
            }

            // Disable button and show status
            $btn.prop('disabled', true).css('opacity', '0.6');
            $status.show();

            var logoColor = $('input[name="airg_logo_color"]:checked').val() || 'black';

            $.ajax({
                url: airg_featured_image.ajax_url,
                type: 'POST',
                data: {
                    action: 'airg_generate_featured_image',
                    nonce: airg_featured_image.nonce,
                    post_id: postId,
                    logo_color: logoColor
                },
                timeout: 120000,
                success: function(response) {
                    $btn.prop('disabled', false).css('opacity', '1');
                    $status.hide();

                    if (response.success) {
                        alert(response.data.message);
                        // Reload the page to show the new featured image
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).css('opacity', '1');
                    $status.hide();
                    alert('Request failed: ' + error + ' (Status: ' + status + ')');
                }
            });
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initLogoUpload();
        initRemoveLogo();
        initGenerateButton();
    });

})(jQuery);
