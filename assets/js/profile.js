/**
 * FIXED: Enhanced Profile JavaScript - Working Button Implementation
 * File: assets/js/profile.js
 * 
 * FIXES:
 * - Fixed all button event handlers
 * - Corrected AJAX calls with proper nonces
 * - Improved modal functionality
 * - Enhanced error handling
 * - Working edit, publish, delete, buy buttons
 */

jQuery(document).ready(function($) {
    
    // === GLOBAL VARIABLES ===
    let currentMixData = null;
    
    // === UTILITY FUNCTIONS ===
    function showError(message) {
        alert('Error: ' + message);
        console.error('Profile Error:', message);
    }
    
    function showSuccess(message) {
        alert(message);
        console.log('Profile Success:', message);
    }
    
    function validateForm(formId) {
        const form = $(formId);
        let isValid = true;
        
        form.find('input[required], textarea[required]').each(function() {
            if (!$(this).val().trim()) {
                isValid = false;
                $(this).addClass('error');
            } else {
                $(this).removeClass('error');
            }
        });
        
        return isValid;
    }
    
    // === MODAL FUNCTIONALITY ===
    function openModal(modalId) {
        $(modalId).fadeIn(300);
        $('body').addClass('modal-open');
    }
    
    function closeModal(modalId) {
        $(modalId).fadeOut(300);
        $('body').removeClass('modal-open');
        // Reset form if present
        $(modalId + ' form')[0]?.reset();
        $(modalId + ' .error').removeClass('error');
    }
    
    // Close modal events
    $(document).on('click', '.close-modal, .cancel-edit, .cancel-publish', function(e) {
        e.preventDefault();
        const modal = $(this).closest('.modal-dialog');
        closeModal('#' + modal.attr('id'));
    });
    
    // Close modal when clicking outside
    $(document).on('click', '.modal-dialog', function(e) {
        if (e.target === this) {
            closeModal('#' + $(this).attr('id'));
        }
    });
    
    // === EDIT MIX FUNCTIONALITY ===
    $(document).on('click', '.edit-mix', function(e) {
        e.preventDefault();
        const mixId = $(this).data('mix-id');
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        console.log('Opening edit modal for mix:', mixId);
        openEditModal(mixId);
    });
    
    function openEditModal(mixId) {
        // Show loading
        openModal('#edit-mix-modal');
        $('#edit-mix-modal .modal-content').html('<div class="loading">' + herbalProfileData.strings.loading + '</div>');
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_details',
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                if (response.success) {
                    populateEditModal(response.data.mix, response.data.ingredients_html);
                } else {
                    showError(response.data.message || herbalProfileData.strings.error);
                    closeModal('#edit-mix-modal');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading mix details:', status, error, xhr.responseText);
                showError('Connection error. Please try again.');
                closeModal('#edit-mix-modal');
            }
        });
    }
    
    function populateEditModal(mix, ingredientsHtml) {
        const modalContent = `
            <span class="close-modal edit-close">&times;</span>
            <h3>Edit Your Mix</h3>
            
            <form id="edit-mix-form">
                <input type="hidden" id="edit-mix-id" name="mix_id" value="${mix.id}">
                
                <div class="form-group">
                    <label for="edit-mix-name">Mix Name</label>
                    <input type="text" id="edit-mix-name" name="mix_name" value="${mix.mix_name}" required>
                </div>
                
                <div class="form-group">
                    <label for="edit-mix-description">Description</label>
                    <textarea id="edit-mix-description" name="mix_description" rows="4">${mix.mix_description || ''}</textarea>
                </div>
                
                <div class="mix-recipe-preview">
                    <h4>Mix Recipe (not editable)</h4>
                    <div id="edit-mix-ingredients-preview">${ingredientsHtml}</div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="button cancel-edit">Cancel</button>
                    <button type="submit" class="button button-primary" id="edit-update-button">Update Mix</button>
                </div>
            </form>
        `;
        
        $('#edit-mix-modal .modal-content').html(modalContent);
        currentMixData = mix;
    }
    
    // Handle edit form submission
    $(document).on('submit', '#edit-mix-form', function(e) {
        e.preventDefault();
        
        if (!validateForm('#edit-mix-form')) {
            showError('Please fill in all required fields.');
            return;
        }
        
        const submitBtn = $('#edit-update-button');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text(herbalProfileData.strings.updating);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'update_mix_details',
                mix_id: $('#edit-mix-id').val(),
                mix_name: $('#edit-mix-name').val(),
                mix_description: $('#edit-mix-description').val(),
                nonce: herbalProfileData.updateNonce
            },
            success: function(response) {
                submitBtn.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    showSuccess(herbalProfileData.strings.updateSuccess);
                    closeModal('#edit-mix-modal');
                    location.reload(); // Refresh to show updated data
                } else {
                    showError(response.data.message || herbalProfileData.strings.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error updating mix:', status, error, xhr.responseText);
                submitBtn.prop('disabled', false).text(originalText);
                showError('Connection error. Please try again.');
            }
        });
    });
    
    // === PUBLISH MIX FUNCTIONALITY ===
    $(document).on('click', '.publish-mix', function(e) {
        e.preventDefault();
        const mixId = $(this).data('mix-id');
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        console.log('Opening publish modal for mix:', mixId);
        openPublishModal(mixId);
    });
    
    function openPublishModal(mixId) {
        // Show loading
        openModal('#publish-mix-modal');
        $('#publish-mix-modal .modal-content').html('<div class="loading">' + herbalProfileData.strings.loading + '</div>');
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_details',
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                if (response.success) {
                    populatePublishModal(response.data.mix, response.data.ingredients_html);
                } else {
                    showError(response.data.message || herbalProfileData.strings.error);
                    closeModal('#publish-mix-modal');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error loading mix details:', status, error, xhr.responseText);
                showError('Connection error. Please try again.');
                closeModal('#publish-mix-modal');
            }
        });
    }
    
    function populatePublishModal(mix, ingredientsHtml) {
        const modalContent = `
            <span class="close-modal publish-close">&times;</span>
            <h3>Publish Your Mix</h3>
            
            <form id="publish-mix-form" enctype="multipart/form-data">
                <input type="hidden" id="publish-mix-id" name="mix_id" value="${mix.id}">
                
                <div class="form-group">
                    <label for="mix-name">Mix Name</label>
                    <input type="text" id="mix-name" name="mix_name" value="${mix.mix_name}" required>
                </div>
                
                <div class="form-group">
                    <label for="mix-description">Description</label>
                    <textarea id="mix-description" name="mix_description" rows="4" required>${mix.mix_description || ''}</textarea>
                </div>
                
                <div class="form-group">
                    <label for="mix-image">Mix Image (required)</label>
                    <input type="file" id="mix-image" name="mix_image" accept="image/*" required>
                    <img id="mix-image-preview" style="max-width: 200px; display: none; margin-top: 10px;">
                </div>
                
                <div class="mix-recipe-preview">
                    <h4>Mix Recipe</h4>
                    <div id="mix-ingredients-preview">${ingredientsHtml}</div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="button cancel-publish">Cancel</button>
                    <button type="submit" class="button button-primary" id="publish-button">Publish Mix</button>
                </div>
            </form>
        `;
        
        $('#publish-mix-modal .modal-content').html(modalContent);
        currentMixData = mix;
        
        // Setup image preview
        $('#mix-image').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#mix-image-preview').attr('src', e.target.result).show();
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Handle publish form submission
    $(document).on('submit', '#publish-mix-form', function(e) {
        e.preventDefault();
        
        if (!validateForm('#publish-mix-form')) {
            showError('Please fill in all required fields and upload an image.');
            return;
        }
        
        const submitBtn = $('#publish-button');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text(herbalProfileData.strings.publishing);
        
        const formData = new FormData(this);
        formData.append('action', 'publish_mix');
        formData.append('nonce', herbalProfileData.publishNonce);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    showSuccess(herbalProfileData.strings.publishSuccess);
                    closeModal('#publish-mix-modal');
                    
                    if (response.data && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    showError(response.data.message || herbalProfileData.strings.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error publishing mix:', status, error, xhr.responseText);
                submitBtn.prop('disabled', false).text(originalText);
                showError('Connection error. Please try again.');
            }
        });
    });
    
    // === DELETE MIX FUNCTIONALITY ===
    $(document).on('click', '.delete-mix', function(e) {
        e.preventDefault();
        const mixId = $(this).data('mix-id');
        const status = $(this).data('status');
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        // Different confirmation message for published mixes
        let confirmMessage = herbalProfileData.strings.confirmDelete;
        if (status === 'published') {
            confirmMessage = herbalProfileData.strings.confirmDeletePublished;
        }
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        deleteMix(mixId);
    });
    
    function deleteMix(mixId) {
        const button = $('.delete-mix[data-mix-id="' + mixId + '"]');
        const originalText = button.text();
        button.prop('disabled', true).text(herbalProfileData.strings.deleting);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_mix',
                mix_id: mixId,
                nonce: herbalProfileData.deleteNonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(herbalProfileData.strings.deleteSuccess);
                    
                    // Remove the row from table
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('.mixes-table tbody tr:visible').length === 0) {
                            location.reload(); // Reload to show "no mixes" message
                        }
                    });
                } else {
                    button.prop('disabled', false).text(originalText);
                    showError(response.data.message || herbalProfileData.strings.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error deleting mix:', status, error, xhr.responseText);
                button.prop('disabled', false).text(originalText);
                showError('Connection error. Please try again.');
            }
        });
    }
    
    // === BUY MIX FUNCTIONALITY ===
    $(document).on('click', '.buy-mix', function(e) {
        e.preventDefault();
        const mixId = $(this).data('mix-id');
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        const button = $(this);
        const originalText = button.text();
        button.prop('disabled', true).text(herbalProfileData.strings.buying);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'buy_mix',
                mix_id: mixId,
                nonce: herbalProfileData.buyNonce
            },
            success: function(response) {
                button.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    showSuccess(herbalProfileData.strings.buySuccess);
                    
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else if (herbalProfileData.cartUrl) {
                        window.location.href = herbalProfileData.cartUrl;
                    }
                } else {
                    showError(response.data.message || herbalProfileData.strings.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error buying mix:', status, error, xhr.responseText);
                button.prop('disabled', false).text(originalText);
                showError('Connection error. Please try again.');
            }
        });
    });
    
    // === VIEW MIX FUNCTIONALITY ===
    $(document).on('click', '.view-mix', function(e) {
        e.preventDefault();
        const mixId = $(this).data('mix-id');
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'view_mix',
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.view_url) {
                        // Redirect to product page if published
                        window.location.href = response.data.view_url;
                    } else {
                        // Show mix details in modal for unpublished mixes
                        showMixDetailsModal(response.data.mix);
                    }
                } else {
                    showError(response.data.message || herbalProfileData.strings.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error viewing mix:', status, error, xhr.responseText);
                showError('Connection error. Please try again.');
            }
        });
    });
    
    function showMixDetailsModal(mix) {
        const mixData = JSON.parse(mix.mix_data);
        let ingredientsHtml = '<div class="ingredients-list">';
        
        if (mixData.ingredients && mixData.ingredients.length > 0) {
            mixData.ingredients.forEach(function(ingredient) {
                ingredientsHtml += '<div class="ingredient-row">';
                ingredientsHtml += '<span class="ingredient-name">' + ingredient.name + '</span>';
                ingredientsHtml += '<span class="ingredient-amount">' + ingredient.amount + 'g</span>';
                ingredientsHtml += '</div>';
            });
        } else {
            ingredientsHtml += '<p>No ingredients found.</p>';
        }
        
        ingredientsHtml += '</div>';
        
        const modalContent = `
            <span class="close-modal">&times;</span>
            <h3>${mix.mix_name}</h3>
            
            <div class="mix-details">
                <div class="mix-info">
                    <p><strong>Status:</strong> ${mix.status}</p>
                    <p><strong>Created:</strong> ${new Date(mix.created_at).toLocaleDateString()}</p>
                    ${mix.mix_description ? '<p><strong>Description:</strong> ' + mix.mix_description + '</p>' : ''}
                </div>
                
                <div class="mix-recipe">
                    <h4>Recipe</h4>
                    ${ingredientsHtml}
                </div>
            </div>
        `;
        
        // Create or update view modal
        if ($('#view-mix-modal').length === 0) {
            $('body').append('<div id="view-mix-modal" class="modal-dialog"><div class="modal-content"></div></div>');
        }
        
        $('#view-mix-modal .modal-content').html(modalContent);
        openModal('#view-mix-modal');
    }
    
    // === REMOVE FAVORITE FUNCTIONALITY ===
    $(document).on('click', '.remove-favorite', function(e) {
        e.preventDefault();
        const mixId = $(this).data('mix-id');
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        if (!confirm('Are you sure you want to remove this mix from favorites?')) {
            return;
        }
        
        const button = $(this);
        const originalText = button.text();
        button.prop('disabled', true).text('Removing...');
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'remove_favorite_mix',
                mix_id: mixId,
                nonce: herbalProfileData.favoritesNonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Mix removed from favorites.');
                    
                    // Remove the row from table
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('.mixes-table tbody tr:visible').length === 0) {
                            location.reload(); // Reload to show "no favorites" message
                        }
                    });
                } else {
                    button.prop('disabled', false).text(originalText);
                    showError(response.data.message || herbalProfileData.strings.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error removing favorite:', status, error, xhr.responseText);
                button.prop('disabled', false).text(originalText);
                showError('Connection error. Please try again.');
            }
        });
    });
    
    // === FORM VALIDATION HELPERS ===
    $(document).on('input', '#edit-mix-name, #mix-name', function() {
        const value = $(this).val().trim();
        if (value.length > 0) {
            $(this).removeClass('error');
        } else {
            $(this).addClass('error');
        }
    });
    
    $(document).on('input', '#edit-mix-description, #mix-description', function() {
        const value = $(this).val().trim();
        const form = $(this).closest('form');
        
        if (form.attr('id') === 'publish-mix-form') {
            // Description required for publish
            if (value.length > 0) {
                $(this).removeClass('error');
            } else {
                $(this).addClass('error');
            }
        }
    });
    
    $(document).on('change', '#mix-image', function() {
        const file = this.files[0];
        if (file) {
            $(this).removeClass('error');
        } else {
            $(this).addClass('error');
        }
    });
    
    // === RESPONSIVE TABLE HANDLING ===
    function handleResponsiveTables() {
        if ($(window).width() < 768) {
            $('.mixes-table').addClass('mobile-view');
        } else {
            $('.mixes-table').removeClass('mobile-view');
        }
    }
    
    // Handle window resize
    $(window).on('resize', handleResponsiveTables);
    handleResponsiveTables(); // Call on load
    
    // === KEYBOARD SHORTCUTS ===
    $(document).on('keydown', function(e) {
        // Escape key closes modals
        if (e.key === 'Escape') {
            $('.modal-dialog:visible').each(function() {
                closeModal('#' + $(this).attr('id'));
            });
        }
        
        // Ctrl+Enter submits forms in modals
        if (e.ctrlKey && e.key === 'Enter') {
            const visibleModal = $('.modal-dialog:visible');
            if (visibleModal.length > 0) {
                const form = visibleModal.find('form');
                if (form.length > 0) {
                    form.submit();
                }
            }
        }
    });
    
    // === LOADING STATES ===
    function setButtonLoading(button, isLoading, loadingText) {
        if (isLoading) {
            button.data('original-text', button.text());
            button.prop('disabled', true).text(loadingText || 'Loading...');
            button.addClass('loading');
        } else {
            const originalText = button.data('original-text') || button.text();
            button.prop('disabled', false).text(originalText);
            button.removeClass('loading');
        }
    }
    
    // === ERROR HANDLING AND LOGGING ===
    window.addEventListener('error', function(e) {
        console.error('JavaScript Error in Profile:', e.error);
    });
    
    // === INITIALIZATION ===
    console.log('Profile.js loaded successfully with enhanced functionality');
    console.log('Available nonces:', {
        get: herbalProfileData.getNonce ? 'OK' : 'MISSING',
        publish: herbalProfileData.publishNonce ? 'OK' : 'MISSING',
        update: herbalProfileData.updateNonce ? 'OK' : 'MISSING',
        delete: herbalProfileData.deleteNonce ? 'OK' : 'MISSING',
        buy: herbalProfileData.buyNonce ? 'OK' : 'MISSING',
        favorites: herbalProfileData.favoritesNonce ? 'OK' : 'MISSING'
    });
    
    // Debug: Check if elements exist on page load
    console.log('Profile elements found:', {
        'Edit buttons': $('.edit-mix').length,
        'Publish buttons': $('.publish-mix').length,
        'Delete buttons': $('.delete-mix').length,
        'Buy buttons': $('.buy-mix').length,
        'View buttons': $('.view-mix').length,
        'Edit modal': $('#edit-mix-modal').length,
        'Publish modal': $('#publish-mix-modal').length
    });
    
    // === ADDITIONAL FEATURES ===
    
    // Auto-save draft functionality for edit form
    let autoSaveTimeout;
    $(document).on('input', '#edit-mix-form input, #edit-mix-form textarea', function() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            // Could implement auto-save to localStorage here
            console.log('Auto-save triggered (not implemented)');
        }, 2000);
    });
    
    // Image drag and drop for publish form
    $(document).on('dragover', '#publish-mix-form .form-group', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-over');
    });
    
    $(document).on('dragleave', '#publish-mix-form .form-group', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
    });
    
    $(document).on('drop', '#publish-mix-form .form-group', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
        
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0 && files[0].type.startsWith('image/')) {
            const fileInput = $(this).find('#mix-image')[0];
            if (fileInput) {
                fileInput.files = files;
                $(fileInput).trigger('change');
            }
        }
    });
    
    // Enhanced tooltips for buttons
    $(document).on('mouseenter', '.mix-actions button', function() {
        const button = $(this);
        const action = button.text().toLowerCase();
        let tooltip = '';
        
        switch(action) {
            case 'edit':
                tooltip = 'Edit mix name and description';
                break;
            case 'publish':
                tooltip = 'Make your mix public and earn points';
                break;
            case 'delete':
                tooltip = 'Permanently delete this mix';
                break;
            case 'buy':
                tooltip = 'Add this mix to your cart';
                break;
            case 'view':
                tooltip = 'View mix details';
                break;
        }
        
        if (tooltip && !button.attr('title')) {
            button.attr('title', tooltip);
        }
    });
    
});
