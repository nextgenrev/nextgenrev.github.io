/**
 * AI Review Generator Pro - Admin JavaScript
 *
 * @package AIReviewGeneratorPro
 * @since   8.0.0
 */

(function ($) {
    'use strict';

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function () {
        initProviderToggle();
        initGenerateButton();
        initDashboardLogoUpload();
    });

    /**
     * Handle provider selection toggle
     */
    function initProviderToggle() {
        $('#ai-provider-select').on('change', function () {
            var provider = $(this).val();
            $('.provider-section').removeClass('active');
            $('#' + provider + '-settings').addClass('active');
        });
    }

    /**
     * Handle generate button click
     */
    function initGenerateButton() {
        $('#airg-generate-btn').on('click', function (e) {
            e.preventDefault();

            var productName = $('#airg-product-name').val().trim();
            var affiliateLink = $('#airg-affiliate-link').val().trim();
            var category = $('#airg-category').val();

            // Validation
            if (!productName) {
                alert('Please enter a product name');
                return;
            }

            if (!affiliateLink) {
                alert('Please enter an affiliate link');
                return;
            }

            showGenerationProgress('preparing', 'Preparing generation...');

            // Create a short-lived server-side job. The expensive operations
            // are then executed one per request so hosting gateway limits do
            // not terminate the entire article-generation flow.
            $.ajax({
                url: airg_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'airg_start_generation',
                    nonce: airg_ajax.nonce,
                    product_name: productName,
                    affiliate_link: affiliateLink,
                    category: category,
                    logo_url: $('#airg-logo-url').val() || '',
                    push_github: $('#airg-push-github').is(':checked') ? 1 : 0
                },
                timeout: 20000,
                success: function (response) {
                    if (!response.success || !response.data || !response.data.job_id) {
                        stopGenerationWithError(getGenerationMessage(response, 'Could not start generation.'));
                        return;
                    }

                    runGenerationStep(response.data.job_id, 0, 0);
                },
                error: function (xhr, status) {
                    stopGenerationWithError(getGenerationRequestError(xhr, status, 'preparing'));
                }
            });
        });
    }

    var generationSteps = ['scrape', 'research', 'content', 'post', 'image', 'github'];
    var generationMessages = {
        preparing: 'Preparing generation...',
        scrape: 'Reading the product page...',
        research: 'Researching pricing and user feedback...',
        content: 'Writing and formatting the review...',
        post: 'Publishing the review...',
        image: 'Creating the featured image...',
        github: 'Pushing to GitHub Pages...'
    };

    /**
     * Run one resumable generation stage.
     */
    function runGenerationStep(jobId, stepIndex, retryCount) {
        var stepsToRun = $('#airg-push-github').is(':checked') ? ['scrape', 'research', 'content', 'post', 'image', 'github'] : ['scrape', 'research', 'content', 'post', 'image'];
        var step = stepsToRun[stepIndex];
        
        if (!step) {
            stopGenerationWithError('Generation stopped before completion. Please try again.');
            return;
        }

        if (step === 'github') {
            $('#l-step-github').show();
        }

        showGenerationProgress(step, generationMessages[step]);

        $.ajax({
            url: airg_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'airg_generation_step',
                nonce: airg_ajax.nonce,
                job_id: jobId,
                step: step
            },
            timeout: 70000,
            success: function (response) {
                if (!response.success) {
                    stopGenerationWithError(getGenerationMessage(response, 'The ' + step + ' step failed.'));
                    return;
                }

                if (response.data && response.data.done && response.data.edit_url) {
                    window.location.href = response.data.edit_url;
                    return;
                }

                runGenerationStep(jobId, stepIndex + 1, 0);
            },
            error: function (xhr, status) {
                var retryable = status === 'timeout'
                    || xhr.status === 0
                    || xhr.status === 502
                    || xhr.status === 503
                    || xhr.status === 504;

                if (retryable && retryCount < 1) {
                    showGenerationProgress(step, 'Connection interrupted. Retrying this step...');
                    window.setTimeout(function () {
                        runGenerationStep(jobId, stepIndex, retryCount + 1);
                    }, 2000);
                    return;
                }

                stopGenerationWithError(getGenerationRequestError(xhr, status, step));
            }
        });
    }

    /**
     * Update the full-screen progress indicator.
     */
    function showGenerationProgress(step, message) {
        var currentIndex = generationSteps.indexOf(step);
        $('#airg-loader').css('display', 'flex');
        $('#airg-loader .loader-sub').text(message || generationMessages[step] || 'Working...');
        $('#airg-loader .l-step').each(function (index) {
            $(this)
                .toggleClass('active', index === currentIndex)
                .toggleClass('done', currentIndex >= 0 && index < currentIndex);
        });
    }

    /**
     * Stop progress and display one useful error message.
     */
    function stopGenerationWithError(message) {
        $('#airg-loader').hide();
        alert('Error: ' + message);
    }

    /**
     * Read a message from a normal WordPress JSON response.
     */
    function getGenerationMessage(response, fallback) {
        if (!response || typeof response.data === 'undefined') {
            return fallback;
        }

        if (typeof response.data === 'string') {
            return response.data;
        }

        return response.data.message || fallback;
    }

    /**
     * Turn HTTP and gateway failures into actionable messages.
     */
    function getGenerationRequestError(xhr, status, step) {
        var jsonMessage = getGenerationMessage(xhr.responseJSON, '');
        if (jsonMessage) {
            return jsonMessage;
        }

        if (status === 'timeout') {
            return 'The ' + step + ' step exceeded 70 seconds. Try again or choose a faster AI model.';
        }

        if (xhr.status === 502 || xhr.status === 503 || xhr.status === 504) {
            return 'The hosting gateway stopped the ' + step + ' step (HTTP ' + xhr.status + '). The plugin retried it once. Check the server error log or ask the host to raise its proxy/PHP-FPM request timeout.';
        }

        return 'Request failed (HTTP ' + (xhr.status || 0) + ') during the ' + step + ' step. Please try again.';
    }

    /**
     * Handle dashboard logo upload button
     */
    function initDashboardLogoUpload() {
        var $uploadButton = $('#airg-dashboard-upload-logo');
        var $fileInput = $('#airg-dashboard-logo-file');
        var $status = $('#airg-dashboard-logo-status');

        if (!$uploadButton.length || !$fileInput.length) {
            return;
        }

        // The label's `for` attribute opens the native file picker without
        // JavaScript. Keyboard support is added explicitly for accessibility.
        $uploadButton.on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $fileInput.val('');
                $fileInput[0].click();
            }
        });

        $fileInput.on('change', function () {
            var file = this.files && this.files[0];
            if (!file) {
                return;
            }

            if (typeof window.airg_ajax === 'undefined' || !airg_ajax.ajax_url || !airg_ajax.upload_nonce) {
                $status.text('The uploader could not initialize. Reload this page and try again.').css('color', '#b32d2e').show();
                $fileInput.val('');
                return;
            }

            if (!file.type || file.type.indexOf('image/') !== 0) {
                $status.text('Please select a supported image file.').css('color', '#b32d2e').show();
                $fileInput.val('');
                return;
            }

            if (airg_ajax.max_upload_size && file.size > Number(airg_ajax.max_upload_size)) {
                $status.text('This file is too large. Maximum size: ' + airg_ajax.max_upload_size_label + '.').css('color', '#b32d2e').show();
                $fileInput.val('');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'airg_upload_logo');
            formData.append('nonce', airg_ajax.upload_nonce);
            formData.append('logo', file);

            $uploadButton.attr('aria-disabled', 'true').css({ pointerEvents: 'none', opacity: '0.65' });
            $status.text('Uploading logo...').css('color', '#646970').show();

            $.ajax({
                url: airg_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (!response.success || !response.data || !response.data.url) {
                        var message = getUploadErrorMessage(response, 'Unknown upload error.');
                        $status.text('Logo upload failed: ' + message).css('color', '#b32d2e').show();
                        return;
                    }

                    var imageUrl = response.data.url;
                    var $preview = $('#airg-dashboard-logo-preview').empty();
                    $('<img>', {
                        src: imageUrl,
                        alt: 'Tool logo'
                    }).css({
                        maxWidth: '120px',
                        height: 'auto',
                        border: '1px solid #ddd',
                        borderRadius: '4px',
                        padding: '2px',
                        marginTop: '4px'
                    }).appendTo($preview);
                    $preview.append(' ').append(
                        $('<button>', {
                            type: 'button',
                            id: 'airg-dashboard-remove-logo',
                            class: 'button button-link',
                            text: 'Remove'
                        }).css({ color: '#a00', fontSize: '11px' })
                    );

                    $('#airg-logo-url').val(imageUrl);
                    $uploadButton.find('.airg-upload-logo-label').text('Change');
                    $status.text('Logo uploaded successfully.').css('color', '#008a20').show();
                },
                error: function (xhr) {
                    var message = getUploadErrorMessage(xhr.responseJSON, 'Request failed (' + xhr.status + ').');
                    $status.text('Logo upload failed: ' + message).css('color', '#b32d2e').show();
                },
                complete: function () {
                    $uploadButton.removeAttr('aria-disabled').css({ pointerEvents: '', opacity: '' });
                    $fileInput.val('');
                }
            });
        });

        // Remove logo
        $(document).on('click', '#airg-dashboard-remove-logo', function (e) {
            e.preventDefault();
            $('#airg-logo-url').val('');
            $('#airg-dashboard-logo-preview').html('');
            $uploadButton.find('.airg-upload-logo-label').text('Upload');
            $status.hide().text('');
        });
    }

    /**
     * Extract a readable message from a WordPress AJAX response.
     */
    function getUploadErrorMessage(response, fallback) {
        if (!response || typeof response.data === 'undefined') {
            return fallback;
        }

        if (typeof response.data === 'string') {
            return response.data;
        }

        if (response.data && response.data.message) {
            return response.data.message;
        }

        return fallback;
    }

})(jQuery);
