/**
 * Enhanced Media Handler - Unified image upload handling
 * File: assets/js/media-handler.js
 * 
 * FEATURES:
 * - Drag & drop support
 * - Progress tracking
 * - Multiple upload types (mix, avatar, ingredient)
 * - Proper error handling
 * - Image preview with removal
 * - Security validation
 */
(function($) {
    'use strict';
    
    // Global configuration
    let mediaConfig = {
        initialized: false,
        uploading: false,
        defaultImage: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="%23ccc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>'
    };
    
    /**
     * Initialize media upload functionality
     */
    function initMediaHandler() {
        if (mediaConfig.initialized) {
            return;
        }
        
        // Check if herbalMediaData is available
        if (typeof herbalMediaData === 'undefined') {
            console.warn('Herbal Media Handler: Configuration not loaded');
            return;
        }
        
        console.log('Initializing Herbal Media Handler');
        
        // Cache DOM elements
        const $fileInputs = $('.herbal-mix-file-input');
        const $selectButtons = $('.herbal-mix-select-image-btn');
        const $removeButtons = $('.herbal-mix-remove-image-btn');
        const $imagePreviews = $('.image-preview');
        
        // Initialize upload areas
        initializeUploadAreas();
        
        // Bind events
        bindSelectButtonEvents();
        bindRemoveButtonEvents();
        bindFileInputEvents();
        bindPreviewClickEvents();
        bindDragDropEvents();
        
        mediaConfig.initialized = true;
        console.log('Media Handler initialized successfully');
    }
    
    /**
     * Initialize upload areas and set default states
     */
    function initializeUploadAreas() {
        $('.custom-file-upload').each(function() {
            const $container = $(this);
            const fieldName = $container.data('field');
            const uploadType = $container.data('upload-type') || 'mix_image';
            
            // Set data attributes for easy access
            $container.attr('data-upload-type', uploadType);
            
            // Initialize preview if no image is set
            const $preview = $container.find('.image-preview');
            const $hiddenInput = $container.find('input[type="hidden"]');
            
            if (!$hiddenInput.val() && !$preview.hasClass('has-image')) {
                $preview.css('background-image', `url(${mediaConfig.defaultImage})`);
            }
        });
    }
    
    /**
     * Bind select button click events
     */
    function bindSelectButtonEvents() {
        $(document).on('click', '.herbal-mix-select-image-btn', function(e) {
            e.preventDefault();
            
            if (mediaConfig.uploading) {
                return;
            }
            
            const targetId = $(this).data('target');
            const $fileInput = $(`#${targetId}_file`);
            
            console.log('Select button clicked for:', targetId);
            
            if ($fileInput.length) {
                $fileInput.trigger('click');
            } else {
                console.error('File input not found for target:', targetId);
            }
        });
    }
    
    /**
     * Bind remove button click events
     */
    function bindRemoveButtonEvents() {
        $(document).on('click', '.herbal-mix-remove-image-btn', function(e) {
            e.preventDefault();
            
            const targetId = $(this).data('target');
            removeImage(targetId);
        });
    }
    
    /**
     * Bind file input change events
     */
    function bindFileInputEvents() {
        $(document).on('change', '.herbal-mix-file-input', function(e) {
            const files = this.files;
            
            if (!files || files.length === 0) {
                return;
            }
            
            const file = files[0];
            const targetId = this.id.replace('_file', '');
            
            handleFileSelection(file, targetId);
        });
    }
    
    /**
     * Bind preview click events (for empty previews)
     */
    function bindPreviewClickEvents() {
        $(document).on('click', '.image-preview:not(.has-image)', function(e) {
            e.preventDefault();
            
            const targetId = $(this).attr('id').replace('_preview', '');
            const $selectBtn = $(`#${targetId}_select_btn`);
            
            if ($selectBtn.length) {
                $selectBtn.trigger('click');
            }
        });
    }
    
    /**
     * Bind drag and drop events
     */
    function bindDragDropEvents() {
        // Prevent default drag behaviors
        $(document).on('dragenter dragover dragleave drop', '.image-preview', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
        
        // Handle drag enter/over
        $(document).on('dragenter dragover', '.image-preview', function(e) {
            $(this).addClass('drag-over');
        });
        
        // Handle drag leave
        $(document).on('dragleave', '.image-preview', function(e) {
            // Only remove if actually leaving the element
            if (!$.contains(this, e.relatedTarget)) {
                $(this).removeClass('drag-over');
            }
        });
        
        // Handle drop
        $(document).on('drop', '.image-preview', function(e) {
            const $preview = $(this);
            $preview.removeClass('drag-over');
            
            const files = e.originalEvent.dataTransfer.files;
            
            if (files.length > 0) {
                const file = files[0];
                const targetId = $preview.attr('id').replace('_preview', '');
                
                handleFileSelection(file, targetId);
            }
        });
    }
    
    /**
     * Handle file selection (from input or drag&drop)
     */
    function handleFileSelection(file, targetId) {
        console.log('File selected:', file.name, 'for target:', targetId);
        
        // Validate file before upload
        const validationResult = validateFile(file);
        if (!validationResult.valid) {
            showError(targetId, validationResult.message);
            return;
        }
        
        // Show preview immediately
        showFilePreview(file, targetId);
        
        // Start upload
        uploadFile(file, targetId);
    }
    
    /**
     * Validate selected file
     */
    function validateFile(file) {
        // Check file type
        if (!herbalMediaData.allowedTypes.includes(file.type)) {
            return {
                valid: false,
                message: herbalMediaData.messages.wrongType
            };
        }
        
        // Check file size
        if (file.size > herbalMediaData.maxFileSize) {
            return {
                valid: false,
                message: `${herbalMediaData.messages.tooLarge} ${formatFileSize(herbalMediaData.maxFileSize)}`
            };
        }
        
        return { valid: true };
    }
    
    /**
     * Show file preview before upload
     */
    function showFilePreview(file, targetId) {
        const $preview = $(`#${targetId}_preview`);
        
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                $preview.css('background-image', `url(${e.target.result})`)
                    .addClass('has-image');
                
                // Hide upload prompt
                $preview.find('.upload-prompt').hide();
            };
            
            reader.readAsDataURL(file);
        }
    }
    
    /**
     * Upload file via AJAX
     */
    function uploadFile(file, targetId) {
        if (mediaConfig.uploading) {
            return;
        }
        
        mediaConfig.uploading = true;
        
        // Get upload type and determine action
        const $container = $(`#${targetId}`).closest('.custom-file-upload');
        const uploadType = $container.data('upload-type') || 'mix_image';
        
        let ajaxAction, nonceKey;
        
        switch (uploadType) {
            case 'avatar':
                ajaxAction = 'upload_avatar';
                nonceKey = 'uploadAvatarNonce';
                break;
            case 'ingredient':
                ajaxAction = 'upload_ingredient_image';
                nonceKey = 'uploadIngredientNonce';
                break;
            default:
                ajaxAction = 'upload_mix_image';
                nonceKey = 'uploadImageNonce';
                break;
        }
        
        // Prepare form data
        const formData = new FormData();
        formData.append('action', ajaxAction);
        formData.append('nonce', herbalMediaData[nonceKey]);
        formData.append(uploadType === 'avatar' ? 'avatar_image' : (uploadType === 'ingredient' ? 'ingredient_image' : 'mix_image'), file);
        
        // UI updates - show progress
        showUploadProgress(targetId);
        disableUploadButton(targetId);
        
        // Perform upload
        $.ajax({
            url: herbalMediaData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                
                // Track upload progress
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        updateUploadProgress(targetId, percentComplete);
                    }
                }, false);
                
                return xhr;
            },
            success: function(response) {
                handleUploadSuccess(response, targetId, file.name);
            },
            error: function(xhr, status, error) {
                handleUploadError(xhr, status, error, targetId);
            },
            complete: function() {
                mediaConfig.uploading = false;
                hideUploadProgress(targetId);
                enableUploadButton(targetId);
            }
        });
    }
    
    /**
     * Handle successful upload
     */
    function handleUploadSuccess(response, targetId, fileName) {
        console.log('Upload response for', targetId, ':', response);
        
        if (response.success && response.data) {
            // Update hidden input with image URL
            const $hiddenInput = $(`#${targetId}`);
            $hiddenInput.val(response.data.url);
            
            // Update preview with final image
            const $preview = $(`#${targetId}_preview`);
            $preview.css('background-image', `url(${response.data.url})`)
                .addClass('has-image');
            
            // Show remove button
            const $removeBtn = $(`#${targetId}_remove_btn`);
            $removeBtn.show();
            
            // Show success message
            showSuccess(targetId, response.data.message || herbalMediaData.messages.uploadSuccess);
            
            // Trigger change event for form validation
            $hiddenInput.trigger('change');
            
            console.log('Upload successful, URL:', response.data.url);
            
        } else {
            const errorMessage = response.data?.message || herbalMediaData.messages.uploadError;
            showError(targetId, errorMessage);
            console.error('Upload failed:', response);
        }
    }
    
    /**
     * Handle upload error
     */
    function handleUploadError(xhr, status, error, targetId) {
        console.error('Upload AJAX error:', status, error, xhr.responseText);
        
        let errorMessage = herbalMediaData.messages.connectionError;
        
        // Try to parse error response
        try {
            const response = JSON.parse(xhr.responseText);
            if (response.data && response.data.message) {
                errorMessage = response.data.message;
            }
        } catch (e) {
            // Use default error message
        }
        
        showError(targetId, errorMessage);
        
        // Reset preview to default state
        resetPreview(targetId);
    }
    
    /**
     * Remove image
     */
    function removeImage(targetId) {
        // Clear hidden input
        const $hiddenInput = $(`#${targetId}`);
        $hiddenInput.val('');
        
        // Reset preview
        resetPreview(targetId);
        
        // Hide remove button
        const $removeBtn = $(`#${targetId}_remove_btn`);
        $removeBtn.hide();
        
        // Clear file input
        const $fileInput = $(`#${targetId}_file`);
        $fileInput.val('');
        
        // Hide any error messages
        const $errorMsg = $(`#${targetId}_error`);
        $errorMsg.hide();
        
        // Trigger change event for form validation
        $hiddenInput.trigger('change');
        
        console.log('Image removed for:', targetId);
    }
    
    /**
     * Reset preview to default state
     */
    function resetPreview(targetId) {
        const $preview = $(`#${targetId}_preview`);
        
        $preview.css('background-image', `url(${mediaConfig.defaultImage})`)
            .removeClass('has-image');
        
        // Show upload prompt again
        $preview.find('.upload-prompt').show();
    }
    
    /**
     * Show upload progress
     */
    function showUploadProgress(targetId) {
        const $progressContainer = $(`#${targetId}_progress`);
        
        if ($progressContainer.length) {
            $progressContainer.show();
            updateUploadProgress(targetId, 0);
        }
    }
    
    /**
     * Update upload progress
     */
    function updateUploadProgress(targetId, percent) {
        const $progressBar = $(`#${targetId}_progress .upload-progress-bar`);
        const $progressText = $(`#${targetId}_progress .upload-progress-text`);
        
        $progressBar.css('width', percent + '%');
        $progressText.text(percent + '%');
    }
    
    /**
     * Hide upload progress
     */
    function hideUploadProgress(targetId) {
        const $progressContainer = $(`#${targetId}_progress`);
        
        setTimeout(function() {
            $progressContainer.fadeOut();
        }, 1000);
    }
    
    /**
     * Disable upload button during upload
     */
    function disableUploadButton(targetId) {
        const $selectBtn = $(`#${targetId}_select_btn`);
        const $removeBtn = $(`#${targetId}_remove_btn`);
        
        $selectBtn.prop('disabled', true)
            .addClass('uploading')
            .text(herbalMediaData.messages.uploading);
        
        $removeBtn.prop('disabled', true);
    }
    
    /**
     * Enable upload button after upload
     */
    function enableUploadButton(targetId) {
        const $selectBtn = $(`#${targetId}_select_btn`);
        const $removeBtn = $(`#${targetId}_remove_btn`);
        
        $selectBtn.prop('disabled', false)
            .removeClass('uploading')
            .text($selectBtn.data('original-text') || 'Upload Image');
        
        $removeBtn.prop('disabled', false);
        
        // Store original button text for future use
        if (!$selectBtn.data('original-text')) {
            $selectBtn.data('original-text', $selectBtn.text());
        }
    }
    
    /**
     * Show error message
     */
    function showError(targetId, message) {
        const $errorMsg = $(`#${targetId}_error`);
        
        if ($errorMsg.length) {
            $errorMsg.removeClass('success-message')
                .addClass('error-message')
                .text(message)
                .show();
        } else {
            // Fallback: create error message if it doesn't exist
            const $container = $(`#${targetId}`).closest('.custom-file-upload');
            $container.append(`<div id="${targetId}_error" class="error-message">${message}</div>`);
        }
        
        // Auto-hide error after 5 seconds
        setTimeout(function() {
            $(`#${targetId}_error`).fadeOut();
        }, 5000);
    }
    
    /**
     * Show success message
     */
    function showSuccess(targetId, message) {
        const $errorMsg = $(`#${targetId}_error`);
        
        if ($errorMsg.length) {
            $errorMsg.removeClass('error-message')
                .addClass('success-message')
                .text(message)
                .show();
        } else {
            // Fallback: create success message if it doesn't exist
            const $container = $(`#${targetId}`).closest('.custom-file-upload');
            $container.append(`<div id="${targetId}_error" class="success-message">${message}</div>`);
        }
        
        // Auto-hide success after 3 seconds
        setTimeout(function() {
            $(`#${targetId}_error`).fadeOut();
        }, 3000);
    }
    
    /**
     * Format file size for display
     */
    function formatFileSize(bytes) {
        if (bytes >= 1073741824) {
            return (bytes / 1073741824).toFixed(2) + ' GB';
        } else if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return bytes + ' B';
        }
    }
    
    /**
     * Get upload type from container
     */
    function getUploadType(targetId) {
        const $container = $(`#${targetId}`).closest('.custom-file-upload');
        return $container.data('upload-type') || 'mix_image';
    }
    
    /**
     * Check if upload is in progress
     */
    function isUploading() {
        return mediaConfig.uploading;
    }
    
    /**
     * Reset all upload forms
     */
    function resetAllUploads() {
        $('.custom-file-upload').each(function() {
            const $container = $(this);
            const fieldName = $container.data('field');
            const targetId = $container.find('input[type="hidden"]').attr('id');
            
            if (targetId) {
                removeImage(targetId);
            }
        });
    }
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        console.log('Document ready, initializing media handler');
        
        // Initialize after a short delay to ensure all elements are rendered
        setTimeout(function() {
            initMediaHandler();
        }, 100);
    });
    
    /**
     * Re-initialize when new content is added dynamically
     */
    $(document).on('DOMNodeInserted', '.custom-file-upload', function() {
        if (mediaConfig.initialized) {
            console.log('New upload area detected, re-initializing');
            setTimeout(function() {
                initializeUploadAreas();
            }, 50);
        }
    });
    
    /**
     * Handle modal/tab content loading
     */
    $(document).on('shown.bs.modal shown.bs.tab', function() {
        if (mediaConfig.initialized) {
            setTimeout(function() {
                initializeUploadAreas();
            }, 100);
        }
    });
    
    /**
     * Global API for external use
     */
    window.herbalMediaHandler = {
        init: initMediaHandler,
        upload: uploadFile,
        remove: removeImage,
        reset: resetAllUploads,
        isUploading: isUploading,
        formatFileSize: formatFileSize,
        validateFile: validateFile,
        
        // Event callbacks
        onUploadStart: function(callback) {
            $(document).on('herbal_upload_start', callback);
        },
        onUploadSuccess: function(callback) {
            $(document).on('herbal_upload_success', callback);
        },
        onUploadError: function(callback) {
            $(document).on('herbal_upload_error', callback);
        }
    };
    
    /**
     * Trigger custom events for better integration
     */
    function triggerUploadEvent(eventName, data) {
        $(document).trigger('herbal_' + eventName, data);
    }
    
    // Enhanced event triggering in upload functions
    function enhancedUploadFile(file, targetId) {
        triggerUploadEvent('upload_start', { file: file, targetId: targetId });
        uploadFile(file, targetId);
    }
    
    function enhancedHandleUploadSuccess(response, targetId, fileName) {
        handleUploadSuccess(response, targetId, fileName);
        triggerUploadEvent('upload_success', { response: response, targetId: targetId, fileName: fileName });
    }
    
    function enhancedHandleUploadError(xhr, status, error, targetId) {
        handleUploadError(xhr, status, error, targetId);
        triggerUploadEvent('upload_error', { xhr: xhr, status: status, error: error, targetId: targetId });
    }
    
    /**
     * Debug helpers
     */
    if (window.location.search.includes('herbal_debug=1')) {
        window.herbalMediaDebug = {
            config: mediaConfig,
            elements: function() {
                return {
                    fileInputs: $('.herbal-mix-file-input').length,
                    selectButtons: $('.herbal-mix-select-image-btn').length,
                    removeButtons: $('.herbal-mix-remove-image-btn').length,
                    previews: $('.image-preview').length,
                    containers: $('.custom-file-upload').length
                };
            },
            testUpload: function(targetId) {
                console.log('Testing upload for:', targetId);
                const $container = $(`#${targetId}`).closest('.custom-file-upload');
                console.log('Container data:', $container.data());
                console.log('Upload type:', getUploadType(targetId));
            }
        };
        
        console.log('Herbal Media Handler Debug Mode Enabled');
        console.log('Available commands: herbalMediaDebug.config, herbalMediaDebug.elements(), herbalMediaDebug.testUpload(targetId)');
    }
    
})(jQuery);

/**
 * CSS classes for styling (can be moved to separate CSS file)
 */
if (typeof document !== 'undefined') {
    const style = document.createElement('style');
    style.textContent = `
        .custom-file-upload {
            margin-bottom: 15px;
        }
        
        .image-preview.drag-over {
            border-color: #8AC249 !important;
            box-shadow: 0 0 10px rgba(138, 194, 73, 0.3) !important;
            transform: scale(1.02) !important;
        }
        
        .upload-progress-container {
            margin: 10px 0;
            padding: 10px;
            background: #f8f8f8;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        
        .upload-progress-bar-wrapper {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .upload-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #8AC249, #7AB93D);
            transition: width 0.3s ease;
            border-radius: 4px;
        }
        
        .upload-progress-text {
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        
        .button.uploading {
            background: #ccc !important;
            cursor: not-allowed !important;
            position: relative;
        }
        
        .button.uploading:after {
            content: '';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            border: 2px solid #666;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: herbal-spin 1s linear infinite;
        }
        
        @keyframes herbal-spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }
        
        .success-message {
            color: #28a745;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
        }
        
        .error-message {
            color: #721c24;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 13px;
        }
    `;
    
    document.head.appendChild(style);
}