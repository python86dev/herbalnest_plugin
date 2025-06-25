/**
 * Enhanced Profile.js - COMPLETE integration with HerbalMixMediaHandler
 * File: assets/js/profile.js
 * 
 * COMPLETE FUNCTIONALITY:
 * - Full integration with HerbalMixMediaHandler for image uploads
 * - Edit/Publish/View/Delete mix functionality
 * - Form validation with visual feedback
 * - Modal management and accessibility
 * - Error handling and user notifications
 * - Debug mode and helper functions
 */
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('Profile.js loaded - Enhanced version with full functionality');
    
    // === CONFIGURATION CHECK ===
    if (typeof herbalProfileData === 'undefined') {
        console.error('herbalProfileData not defined - creating fallback configuration');
        window.herbalProfileData = {
            ajaxUrl: ajaxurl || '/wp-admin/admin-ajax.php',
            getNonce: 'fallback_nonce',
            recipeNonce: 'fallback_nonce',
            updateMixNonce: 'fallback_nonce',
            publishNonce: 'fallback_nonce',
            deleteNonce: 'fallback_nonce',
            deleteMixNonce: 'fallback_nonce',
            uploadImageNonce: 'fallback_nonce',
            favoritesNonce: 'fallback_nonce',
            buyMixNonce: 'fallback_nonce',
            userId: 0,
            currencySymbol: '£',
            strings: {
                loading: 'Loading...',
                error: 'An error occurred. Please try again.',
                success: 'Success!',
                confirmDelete: 'Are you sure you want to delete this mix? This action cannot be undone.',
                confirmRemoveFavorite: 'Remove this mix from favorites?',
                deleting: 'Deleting...',
                deleteSuccess: 'Mix deleted successfully.',
                connectionError: 'Connection error. Please try again.',
                updateSuccess: 'Mix updated successfully!',
                publishSuccess: 'Mix published successfully!',
                invalidData: 'Invalid mix data.',
                accessDenied: 'Access denied.',
                imageRequired: 'Please select an image for your mix.'
            }
        };
    }
    
    console.log('Profile configuration loaded:', herbalProfileData);
    
    // === GLOBAL VARIABLES ===
    let isFormSubmitting = false;
    let currentMixData = null;
    
    // === TAB NAVIGATION ===
    $('.tab-navigation a').on('click', function(e) {
        e.preventDefault();
        
        const target = $(this).attr('href');
        
        $('.tab-navigation li').removeClass('active');
        $(this).parent().addClass('active');
        
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');
        
        console.log('Tab switched to:', target);
    });
    
    // === MODAL MANAGEMENT ===
    
    // Close modal handlers
    $(document).on('click', '.modal-close, .cancel-modal', function(e) {
        e.preventDefault();
        $(this).closest('.modal-dialog').hide();
        resetForms();
    });
    
    // Close modal on background click
    $(document).on('click', '.modal-dialog', function(e) {
        if (e.target === this) {
            $(this).hide();
            resetForms();
        }
    });
    
    // ESC key closes modals
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('.modal-dialog:visible').length > 0) {
            $('.modal-dialog:visible').hide();
            resetForms();
        }
    });
    
    // === EDIT MIX FUNCTIONALITY ===
    $(document).on('click', '.edit-mix', function(e) {
        e.preventDefault();
        
        const mixId = $(this).data('mix-id');
        console.log('Edit mix clicked, ID:', mixId);
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        // Show loading state
        const $button = $(this);
        setButtonLoading($button, true, 'Loading...');
        
        // Load mix details
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_details',
                nonce: herbalProfileData.getNonce,
                mix_id: mixId
            },
            success: function(response) {
                console.log('Edit mix response:', response);
                
                if (response.success && response.data) {
                    currentMixData = response.data;
                    
                    // Populate edit form
                    $('#edit-mix-id').val(response.data.id);
                    $('#edit-mix-name').val(response.data.name);
                    
                    // Show modal
                    $('#edit-mix-modal').show();
                    
                    // Focus on name field
                    setTimeout(() => {
                        $('#edit-mix-name').focus().select();
                    }, 100);
                    
                    // Validate form
                    validateEditForm();
                } else {
                    showNotification('Error: ' + (response.data || herbalProfileData.strings.error), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Edit mix AJAX error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Edit Mix');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // === PUBLISH MIX FUNCTIONALITY ===
    $(document).on('click', '.publish-mix', function(e) {
        e.preventDefault();
        
        const mixId = $(this).data('mix-id');
        console.log('Publish mix clicked, ID:', mixId);
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        const $button = $(this);
        setButtonLoading($button, true, 'Loading...');
        
        // Load mix details
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_details',
                nonce: herbalProfileData.getNonce,
                mix_id: mixId
            },
            success: function(response) {
                console.log('Publish mix response:', response);
                
                if (response.success && response.data) {
                    currentMixData = response.data;
                    
                    // Populate publish form
                    $('#publish-mix-id').val(response.data.id);
                    $('#publish-mix-name').val(response.data.name);
                    $('#publish-mix-description').val(response.data.description || '');
                    
                    // Clear and reset image field
                    $('#publish_mix_image').val('');
                    resetImagePreview('publish_mix_image');
                    
                    // Show modal
                    $('#publish-mix-modal').show();
                    
                    // Initialize media handler for this modal
                    if (typeof window.herbalMediaHandler !== 'undefined') {
                        window.herbalMediaHandler.init();
                    }
                    
                    // Focus on appropriate field
                    setTimeout(() => {
                        if (!$('#publish-mix-name').val()) {
                            $('#publish-mix-name').focus();
                        } else if (!$('#publish-mix-description').val()) {
                            $('#publish-mix-description').focus();
                        } else {
                            $('#publish_mix_image_select_btn').focus();
                        }
                    }, 200);
                    
                    // Validate form
                    validatePublishForm();
                } else {
                    showNotification('Error: ' + (response.data || herbalProfileData.strings.error), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Publish mix AJAX error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Publish Mix');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // === VIEW MIX FUNCTIONALITY ===
    $(document).on('click', '.view-mix', function(e) {
        e.preventDefault();
        
        const mixId = $(this).data('mix-id');
        console.log('View mix clicked, ID:', mixId);
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        const $button = $(this);
        setButtonLoading($button, true, 'Loading...');
        
        // Load mix details and recipe
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_recipe_and_pricing',
                nonce: herbalProfileData.recipeNonce || herbalProfileData.getNonce,
                mix_id: mixId,
                action_type: 'view'
            },
            success: function(response) {
                console.log('View mix response:', response);
                
                if (response.success && response.data) {
                    showViewModal(response.data);
                } else {
                    showNotification('Error: ' + (response.data || herbalProfileData.strings.error), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('View mix AJAX error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'View Mix');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // === DELETE MIX FUNCTIONALITY ===
    $(document).on('click', '.delete-mix', function(e) {
        e.preventDefault();
        
        const mixId = $(this).data('mix-id');
        const $button = $(this);
        const $row = $button.closest('tr');
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        if (!confirm(herbalProfileData.strings.confirmDelete)) {
            return;
        }
        
        console.log('Delete mix clicked, ID:', mixId);
        
        setButtonLoading($button, true, herbalProfileData.strings.deleting);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_mix',
                nonce: herbalProfileData.deleteNonce || herbalProfileData.deleteMixNonce,
                mix_id: mixId
            },
            success: function(response) {
                console.log('Delete mix response:', response);
                
                if (response.success) {
                    showNotification(herbalProfileData.strings.deleteSuccess, 'success');
                    
                    // Remove the row with animation
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('.mixes-table tbody tr:visible').length === 0) {
                            setTimeout(() => {
                                location.reload();
                            }, 500);
                        }
                    });
                } else {
                    showNotification('Error: ' + (response.data || herbalProfileData.strings.error), 'error');
                    setButtonLoading($button, false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete mix AJAX error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Delete Mix');
                setButtonLoading($button, false);
            }
        });
    });
    
    // === REMOVE FAVORITE FUNCTIONALITY ===
    $(document).on('click', '.remove-favorite', function(e) {
        e.preventDefault();
        
        const mixId = $(this).data('mix-id');
        const $button = $(this);
        const $row = $button.closest('tr');
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        if (!confirm(herbalProfileData.strings.confirmRemoveFavorite)) {
            return;
        }
        
        console.log('Remove favorite clicked, ID:', mixId);
        
        setButtonLoading($button, true, 'Removing...');
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'remove_favorite_mix',
                nonce: herbalProfileData.favoritesNonce || herbalProfileData.getNonce,
                mix_id: mixId
            },
            success: function(response) {
                console.log('Remove favorite response:', response);
                
                if (response.success) {
                    showNotification('Removed from favorites', 'success');
                    
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        
                        if ($('.mixes-table tbody tr:visible').length === 0) {
                            setTimeout(() => {
                                location.reload();
                            }, 500);
                        }
                    });
                } else {
                    showNotification('Error: ' + (response.data || herbalProfileData.strings.error), 'error');
                    setButtonLoading($button, false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Remove favorite AJAX error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Remove Favorite');
                setButtonLoading($button, false);
            }
        });
    });
    
    // === BUY MIX FUNCTIONALITY ===
    $(document).on('click', '.buy-mix', function(e) {
        e.preventDefault();
        
        const mixId = $(this).data('mix-id');
        const $button = $(this);
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        console.log('Buy mix clicked, ID:', mixId);
        
        setButtonLoading($button, true, 'Adding to cart...');
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'buy_mix',
                nonce: herbalProfileData.buyMixNonce || herbalProfileData.getNonce,
                mix_id: mixId
            },
            success: function(response) {
                console.log('Buy mix response:', response);
                
                if (response.success) {
                    showNotification('Added to cart!', 'success');
                    
                    if (response.data && response.data.cart_url) {
                        if (confirm('Go to cart now?')) {
                            window.location.href = response.data.cart_url;
                            return;
                        }
                    }
                } else {
                    showNotification('Error: ' + (response.data || herbalProfileData.strings.error), 'error');
                }
                
                setButtonLoading($button, false);
            },
            error: function(xhr, status, error) {
                console.error('Buy mix AJAX error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Buy Mix');
                setButtonLoading($button, false);
            }
        });
    });
    
    // === FORM SUBMISSION HANDLERS ===
    
    // Edit form submission
    $(document).on('submit', '#edit-mix-form', function(e) {
        e.preventDefault();
        
        if (isFormSubmitting) return;
        
        const mixName = $('#edit-mix-name').val().trim();
        if (!mixName) {
            showNotification('Please enter a mix name.', 'error');
            $('#edit-mix-name').focus();
            return;
        }
        
        isFormSubmitting = true;
        const $submitButton = $('#edit-update-button');
        setButtonLoading($submitButton, true, 'Updating...');
        
        const formData = {
            action: 'update_mix_details',
            nonce: herbalProfileData.updateMixNonce,
            mix_id: $('#edit-mix-id').val(),
            mix_name: mixName
        };
        
        console.log('Submitting edit form:', formData);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Edit form response:', response);
                
                if (response.success) {
                    showNotification(herbalProfileData.strings.updateSuccess, 'success');
                    $('#edit-mix-modal').hide();
                    
                    // Update the name in the table
                    $('.edit-mix[data-mix-id="' + $('#edit-mix-id').val() + '"]')
                        .closest('tr').find('td:first strong').text(mixName);
                    
                    resetForms();
                } else {
                    showNotification('Error: ' + (response.data || herbalProfileData.strings.error), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Edit form AJAX error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Update Mix');
            },
            complete: function() {
                isFormSubmitting = false;
                setButtonLoading($submitButton, false);
            }
        });
    });
    
    // Publish form submission
    $(document).on('submit', '#publish-mix-form', function(e) {
        e.preventDefault();
        
        if (isFormSubmitting) return;
        
        if (!validatePublishForm()) {
            showNotification('Please fill in all required fields.', 'error');
            return;
        }
        
        isFormSubmitting = true;
        const $submitButton = $('#publish-button');
        setButtonLoading($submitButton, true, 'Publishing...');
        
        const formData = {
            action: 'publish_mix',
            nonce: herbalProfileData.publishNonce,
            mix_id: $('#publish-mix-id').val(),
            mix_name: $('#publish-mix-name').val().trim(),
            mix_description: $('#publish-mix-description').val().trim(),
            mix_image: $('#publish_mix_image').val()
        };
        
        console.log('Submitting publish form:', formData);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Publish form response:', response);
                
                if (response.success) {
                    showNotification(herbalProfileData.strings.publishSuccess, 'success');
                    $('#publish-mix-modal').hide();
                    resetForms();
                    
                    // Reload to show updated status
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification('Error: ' + (response.data || herbalProfileData.strings.error), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Publish form AJAX error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Publish Mix');
            },
            complete: function() {
                isFormSubmitting = false;
                setButtonLoading($submitButton, false);
            }
        });
    });
    
    // === FORM VALIDATION ===
    
    function validateEditForm() {
        const nameValid = $('#edit-mix-name').val().trim() !== '';
        $('#edit-update-button').prop('disabled', !nameValid);
        
        toggleFieldError('#edit-mix-name', !nameValid && $('#edit-mix-name').val() !== '');
        
        return nameValid;
    }
    
    function validatePublishForm() {
        const nameValid = $('#publish-mix-name').val().trim() !== '';
        const descriptionValid = $('#publish-mix-description').val().trim() !== '';
        const imageValid = $('#publish_mix_image').val() !== '';
        
        const allValid = nameValid && descriptionValid && imageValid;
        $('#publish-button').prop('disabled', !allValid);
        
        // Visual feedback
        toggleFieldError('#publish-mix-name', !nameValid && $('#publish-mix-name').val() !== '');
        toggleFieldError('#publish-mix-description', !descriptionValid && $('#publish-mix-description').val() !== '');
        
        // Image error message
        const $imageError = $('#publish_mix_image_error');
        if (!imageValid && $imageError.length) {
            $imageError.text(herbalProfileData.strings.imageRequired).show();
        } else if ($imageError.length) {
            $imageError.hide();
        }
        
        return allValid;
    }
    
    function toggleFieldError(selector, hasError) {
        const $field = $(selector);
        if (hasError) {
            $field.addClass('error');
        } else {
            $field.removeClass('error');
        }
    }
    
    // === FORM INPUT HANDLERS ===
    
    // Real-time validation
    $(document).on('input', '#edit-mix-name', function() {
        validateEditForm();
    });
    
    $(document).on('input change', '#publish-mix-name, #publish-mix-description, #publish_mix_image', function() {
        setTimeout(validatePublishForm, 50);
    });
    
    // === VIEW MODAL FUNCTION ===
    
    function showViewModal(data) {
        console.log('Showing view modal with data:', data);
        
        if (!data || !data.mix) {
            showNotification('Invalid mix data', 'error');
            return;
        }
        
        const modalHtml = `
            <div id="view-mix-modal" class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${escapeHtml(data.mix.name)}</h3>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="view-mix-content">
                        <div class="mix-info">
                            ${data.mix.image ? `<div class="mix-image"><img src="${escapeHtml(data.mix.image)}" alt="${escapeHtml(data.mix.name)}" style="max-width: 200px; height: auto; border-radius: 8px; margin-bottom: 15px;"></div>` : ''}
                            <p><strong>Description:</strong> ${escapeHtml(data.mix.description || 'No description provided')}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${escapeHtml(data.mix.status)}">${escapeHtml(data.mix.status)}</span></p>
                        </div>
                        
                        <div class="mix-recipe">
                            <h4>Recipe & Pricing</h4>
                            ${renderRecipeDetails(data.recipe)}
                        </div>
                        
                        <div class="form-actions">
                            ${data.mix.status === 'published' ? `<button type="button" class="button button-primary buy-mix" data-mix-id="${data.mix.id}">Buy This Mix</button>` : ''}
                            <button type="button" class="button button-secondary cancel-modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal and add new one
        $('#view-mix-modal').remove();
        $('body').append(modalHtml);
        $('#view-mix-modal').show();
        
        // Focus on close button
        setTimeout(() => {
            $('#view-mix-modal .cancel-modal').focus();
        }, 100);
    }
    
    // === HELPER FUNCTIONS ===
    
    function renderRecipeDetails(recipe) {
        if (!recipe) {
            return '<p class="no-recipe">Recipe details not available.</p>';
        }
        
        let html = '<div class="recipe-details">';
        
        // Packaging info
        if (recipe.packaging) {
            html += `
                <div class="packaging-info">
                    <h5>Packaging</h5>
                    <p><strong>${escapeHtml(recipe.packaging.name)}</strong> (${recipe.packaging.capacity}g capacity)</p>
                    <p>Price: ${herbalProfileData.currencySymbol}${recipe.packaging.price.toFixed(2)}</p>
                </div>
            `;
        }
        
        // Ingredients list
        if (recipe.ingredients && recipe.ingredients.length > 0) {
            html += '<div class="ingredients-info"><h5>Ingredients</h5><ul class="ingredients-list">';
            recipe.ingredients.forEach(function(ingredient) {
                html += `
                    <li class="ingredient-item">
                        <span class="ingredient-name">${escapeHtml(ingredient.name)}</span>
                        <span class="ingredient-weight">${ingredient.weight}g</span>
                        <span class="ingredient-price">${herbalProfileData.currencySymbol}${ingredient.total_price.toFixed(2)}</span>
                    </li>
                `;
            });
            html += '</ul></div>';
        }
        
        // Totals
        if (recipe.total_weight !== undefined && recipe.total_price !== undefined) {
            html += `
                <div class="recipe-totals">
                    <p><strong>Total Weight:</strong> ${recipe.total_weight}g</p>
                    <p><strong>Total Price:</strong> ${herbalProfileData.currencySymbol}${recipe.total_price.toFixed(2)}</p>
                    ${recipe.total_points ? `<p><strong>Points Price:</strong> ${recipe.total_points} points</p>` : ''}
                </div>
            `;
        }
        
        html += '</div>';
        return html;
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function setButtonLoading($button, isLoading, loadingText) {
        if (isLoading) {
            $button.data('original-text', $button.text());
            $button.prop('disabled', true).text(loadingText || herbalProfileData.strings.loading);
            $button.addClass('loading');
        } else {
            $button.prop('disabled', false).text($button.data('original-text') || 'Submit');
            $button.removeClass('loading');
        }
    }
    
    function resetForms() {
        // Reset edit form
        $('#edit-mix-form')[0]?.reset();
        $('#edit-mix-id').val('');
        
        // Reset publish form
        $('#publish-mix-form')[0]?.reset();
        $('#publish-mix-id').val('');
        $('#publish_mix_image').val('');
        resetImagePreview('publish_mix_image');
        
        // Clear validation states
        $('.error').removeClass('error');
        $('.error-message').hide();
        
        // Reset buttons
        $('#edit-update-button').prop('disabled', true);
        $('#publish-button').prop('disabled', true);
        
        isFormSubmitting = false;
        currentMixData = null;
    }
    
    function resetImagePreview(fieldId) {
        const $preview = $(`#${fieldId}_preview`);
        const $removeBtn = $(`#${fieldId}_remove_btn`);
        
        if ($preview.length) {
            $preview.css('background-image', '').removeClass('has-image');
            $preview.find('.upload-prompt').show();
        }
        
        if ($removeBtn.length) {
            $removeBtn.hide();
        }
    }
    
    function showNotification(message, type = 'info') {
        const className = type === 'error' ? 'error-message' : (type === 'success' ? 'success-message' : 'info-message');
        const $notification = $(`<div class="${className} temporary-notification">${escapeHtml(message)}</div>`);
        
        // Position and style
        $notification.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: '999999',
            padding: '15px 20px',
            borderRadius: '6px',
            maxWidth: '350px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            backgroundColor: type === 'error' ? '#f8d7da' : (type === 'success' ? '#d4edda' : '#d1ecf1'),
            color: type === 'error' ? '#721c24' : (type === 'success' ? '#155724' : '#0c5460'),
            border: `1px solid ${type === 'error' ? '#f5c6cb' : (type === 'success' ? '#c3e6cb' : '#bee5eb')}`,
            fontSize: '14px',
            lineHeight: '1.4'
        });
        
        $('body').append($notification);
        
        // Auto remove
        setTimeout(() => {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, type === 'error' ? 5000 : 3000);
        
        console.log(`Notification (${type}):`, message);
    }
    
    function handleAjaxError(xhr, status, error, context) {
        console.error(`AJAX Error in ${context}:`, {
            status: status,
            error: error,
            response: xhr.responseText
        });
        
        let errorMessage = herbalProfileData.strings.connectionError;
        
        // Try to parse server error
        try {
            const response = JSON.parse(xhr.responseText);
            if (response.data && typeof response.data === 'string') {
                errorMessage = response.data;
            } else if (response.message) {
                errorMessage = response.message;
            }
        } catch (e) {
            // Use default message
        }
        
        showNotification(errorMessage, 'error');
    }
    
    // === MEDIA HANDLER INTEGRATION ===
    
    // Listen for media handler events
    $(document).on('herbal_upload_success', function(e, data) {
        console.log('Media upload success event:', data);
        
        if (data.targetId === 'publish_mix_image') {
            setTimeout(validatePublishForm, 100);
            showNotification('Image uploaded successfully!', 'success');
        }
    });
    
    $(document).on('herbal_upload_error', function(e, data) {
        console.error('Media upload error event:', data);
        showNotification('Image upload failed. Please try again.', 'error');
    });
    
    // === ACCESSIBILITY ENHANCEMENTS ===
    
    // Focus management for modals
    $(document).on('show', '.modal-dialog', function() {
        const $modal = $(this);
        const $firstInput = $modal.find('input, textarea, select, button').filter(':visible').first();
        
        setTimeout(() => {
            $firstInput.focus();
        }, 100);
    });
    
    // Trap focus within modals
    $(document).on('keydown', '.modal-dialog', function(e) {
        if (e.keyCode === 9) { // Tab key
            const $modal = $(this);
            const $focusableElements = $modal.find('input, textarea, select, button, a').filter(':visible');
            const $firstElement = $focusableElements.first();
            const $lastElement = $focusableElements.last();
            
            if (e.shiftKey) {
                if (document.activeElement === $firstElement[0]) {
                    e.preventDefault();
                    $lastElement.focus();
                }
            } else {
                if (document.activeElement === $lastElement[0]) {
                    e.preventDefault();
                    $firstElement.focus();
                }
            }
        }
    });
    
    // === INITIALIZATION AND CHECKS ===
    
    // Check for required elements
    const elementsCheck = {
        mixesTable: $('.mixes-table').length,
        tabNavigation: $('.tab-navigation').length,
        editButtons: $('.edit-mix').length,
        publishButtons: $('.publish-mix').length,
        deleteButtons: $('.delete-mix').length,
        viewButtons: $('.view-mix').length
    };
    
    console.log('Profile elements check:', elementsCheck);
    
    if (elementsCheck.mixesTable > 0) {
        console.log('Mixes table found, profile functionality fully initialized');
    }
    
    if (elementsCheck.tabNavigation > 0) {
        console.log('Tab navigation found, tab functionality initialized');
        
        // Set initial active tab if none is set
        if ($('.tab-navigation li.active').length === 0) {
            $('.tab-navigation li:first').addClass('active');
            $('.tab-pane:first').addClass('active');
        }
    }
    
    // Initialize media handler integration
    if (typeof window.herbalMediaHandler !== 'undefined') {
        console.log('HerbalMediaHandler integration initialized');
        
        // Enhanced callbacks
        window.herbalMediaHandler.onUploadSuccess(function(data) {
            console.log('Media upload success callback triggered:', data);
            $(document).trigger('herbal_upload_success', data);
        });
        
        window.herbalMediaHandler.onUploadError(function(data) {
            console.log('Media upload error callback triggered:', data);
            $(document).trigger('herbal_upload_error', data);
        });
        
        // Initialize on page load
        setTimeout(() => {
            window.herbalMediaHandler.init();
        }, 500);
    } else {
        console.warn('HerbalMediaHandler not found - image uploads may not work properly');
        console.log('Available global objects:', Object.keys(window).filter(key => key.includes('herbal')));
    }
    
    // === GLOBAL API FOR EXTERNAL ACCESS ===
    
    window.herbalProfile = {
        // Form validation
        validateEditForm: validateEditForm,
        validatePublishForm: validatePublishForm,
        
        // Modal management
        showViewModal: showViewModal,
        resetForms: resetForms,
        
        // UI helpers
        showNotification: showNotification,
        setButtonLoading: setButtonLoading,
        handleAjaxError: handleAjaxError,
        
        // Data management
        getCurrentMixData: () => currentMixData,
        refreshPage: () => location.reload(),
        
        // Utilities
        escapeHtml: escapeHtml,
        renderRecipeDetails: renderRecipeDetails
    };
    
    // === EVENT SYSTEM ===
    
    // Custom events for better integration
    const profileEvents = {
        mixUpdated: 'herbal:mix:updated',
        mixPublished: 'herbal:mix:published',
        mixDeleted: 'herbal:mix:deleted',
        favoriteRemoved: 'herbal:favorite:removed',
        modalOpened: 'herbal:modal:opened',
        modalClosed: 'herbal:modal:closed'
    };
    
    // Trigger events for better integration with other scripts
    $(document).on('click', '.edit-mix', function() {
        $(document).trigger(profileEvents.modalOpened, { type: 'edit', mixId: $(this).data('mix-id') });
    });
    
    $(document).on('click', '.publish-mix', function() {
        $(document).trigger(profileEvents.modalOpened, { type: 'publish', mixId: $(this).data('mix-id') });
    });
    
    $(document).on('click', '.view-mix', function() {
        $(document).trigger(profileEvents.modalOpened, { type: 'view', mixId: $(this).data('mix-id') });
    });
    
    $(document).on('click', '.modal-close, .cancel-modal', function() {
        $(document).trigger(profileEvents.modalClosed, { modal: $(this).closest('.modal-dialog').attr('id') });
    });
    
    // === PERFORMANCE OPTIMIZATIONS ===
    
    // Debounce validation functions
    const debouncedValidateEdit = debounce(validateEditForm, 300);
    const debouncedValidatePublish = debounce(validatePublishForm, 300);
    
    // Replace direct validation calls with debounced versions
    $(document).off('input', '#edit-mix-name').on('input', '#edit-mix-name', debouncedValidateEdit);
    $(document).off('input change', '#publish-mix-name, #publish-mix-description').on('input change', '#publish-mix-name, #publish-mix-description', debouncedValidatePublish);
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // === DEBUG MODE ===
    
    if (window.location.search.includes('herbal_debug=1') || window.location.hash.includes('debug')) {
        console.log('🐛 Herbal Profile Debug Mode Enabled');
        
        window.herbalProfileDebug = {
            // Configuration
            config: herbalProfileData,
            currentMix: () => currentMixData,
            isSubmitting: () => isFormSubmitting,
            
            // Element counts
            elements: elementsCheck,
            
            // Test functions
            testModal: function(type, mixId = 1) {
                console.log(`Testing ${type} modal with mix ID ${mixId}`);
                switch(type) {
                    case 'edit':
                        $('#edit-mix-modal').show();
                        $('#edit-mix-id').val(mixId);
                        $('#edit-mix-name').val('Test Mix Name');
                        validateEditForm();
                        break;
                    case 'publish':
                        $('#publish-mix-modal').show();
                        $('#publish-mix-id').val(mixId);
                        $('#publish-mix-name').val('Test Mix Name');
                        $('#publish-mix-description').val('Test description');
                        validatePublishForm();
                        break;
                    case 'view':
                        showViewModal({
                            mix: { id: mixId, name: 'Test Mix', description: 'Test description', status: 'published' },
                            recipe: { total_weight: 100, total_price: 25.50, ingredients: [] }
                        });
                        break;
                }
            },
            
            testNotification: function(message = 'Test notification', type = 'info') {
                showNotification(message, type);
            },
            
            testAjax: function() {
                console.log('Testing AJAX configuration...');
                $.ajax({
                    url: herbalProfileData.ajaxUrl,
                    type: 'POST',
                    data: { action: 'test' },
                    success: (response) => console.log('AJAX test success:', response),
                    error: (xhr, status, error) => console.log('AJAX test error:', status, error)
                });
            },
            
            // Validation tests
            testValidation: function() {
                console.log('Testing form validation...');
                console.log('Edit form valid:', validateEditForm());
                console.log('Publish form valid:', validatePublishForm());
            },
            
            // Media handler test
            testMediaHandler: function() {
                if (window.herbalMediaHandler) {
                    console.log('Media handler available:', typeof window.herbalMediaHandler);
                    console.log('Media handler methods:', Object.keys(window.herbalMediaHandler));
                } else {
                    console.log('Media handler not available');
                }
            },
            
            // Event listeners count
            getEventListeners: function() {
                return {
                    editButtons: $('.edit-mix').length,
                    publishButtons: $('.publish-mix').length,
                    deleteButtons: $('.delete-mix').length,
                    viewButtons: $('.view-mix').length,
                    modals: $('.modal-dialog').length
                };
            }
        };
        
        console.log('🔧 Debug commands available:');
        console.log('- herbalProfileDebug.testModal("edit"|"publish"|"view", mixId)');
        console.log('- herbalProfileDebug.testNotification(message, type)');
        console.log('- herbalProfileDebug.testAjax()');
        console.log('- herbalProfileDebug.testValidation()');
        console.log('- herbalProfileDebug.testMediaHandler()');
        console.log('- herbalProfileDebug.getEventListeners()');
        console.log('📊 Current state:', window.herbalProfileDebug.elements);
    }
    
    // === FINAL INITIALIZATION ===
    
    // Run initial validation on any pre-filled forms
    setTimeout(() => {
        if ($('#edit-mix-name').val()) {
            validateEditForm();
        }
        if ($('#publish-mix-name').val() || $('#publish-mix-description').val()) {
            validatePublishForm();
        }
    }, 100);
    
    // Log successful initialization
    console.log('✅ Profile.js initialization complete');
    console.log('📊 Elements found:', elementsCheck);
    console.log('🔧 Global API available as window.herbalProfile');
    console.log('📝 Current configuration:', {
        ajaxUrl: herbalProfileData.ajaxUrl,
        userId: herbalProfileData.userId,
        currency: herbalProfileData.currencySymbol,
        noncesAvailable: Object.keys(herbalProfileData).filter(key => key.includes('Nonce')).length
    });
    
    // Signal that profile is ready
    $(document).trigger('herbal:profile:ready');
    
});