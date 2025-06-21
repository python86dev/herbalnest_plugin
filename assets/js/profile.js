/**
 * COMPLETE profile.js - Full JavaScript for Herbal Mix Creator Profile
 * File: assets/js/profile.js
 * 
 * FIXED VERSION: Complete functionality for Edit/Publish/Delete/View mix buttons
 */

jQuery(document).ready(function($) {
    
    console.log('Herbal Profile JS loaded successfully');
    
    // === TAB NAVIGATION ===
    $('.tab-navigation a').on('click', function(e) {
        e.preventDefault();
        
        const target = $(this).attr('href');
        
        $('.tab-navigation li').removeClass('active');
        $(this).parent().addClass('active');
        
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');
    });
    
    // === FALLBACK DATA CHECK ===
    if (typeof herbalProfileData === 'undefined') {
        console.error('herbalProfileData not defined, creating fallback');
        window.herbalProfileData = {
            ajaxUrl: '/wp-admin/admin-ajax.php',
            getNonce: '',
            updateMixNonce: '',
            publishNonce: '',
            recipeNonce: '',
            uploadImageNonce: '',
            deleteNonce: '',
            deleteMixNonce: '',
            favoritesNonce: '',
            buyMixNonce: '',
            strings: {
                loading: 'Loading...',
                error: 'An error occurred. Please try again.',
                success: 'Success!',
                confirmDelete: 'Are you sure you want to delete this mix? This action cannot be undone.',
                deleting: 'Deleting...',
                deleteSuccess: 'Mix deleted successfully.',
                updating: 'Updating...',
                updateSuccess: 'Mix updated successfully.',
                publishing: 'Publishing...',
                publishSuccess: 'Mix published successfully.',
                connectionError: 'Connection error. Please try again.',
                noMixData: 'No mix data found.',
                noIngredients: 'No ingredients found in this mix.'
            }
        };
    }
    
    // === EDIT MIX FUNCTIONALITY ===
    $(document).on('click', '.edit-mix', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        
        if (!mixId) {
            alert('Mix ID not found');
            return;
        }
        
        $('#edit-mix-modal').show();
        $('#edit-mix-id').val(mixId);
        
        // Show loading state
        $('#edit-mix-ingredients-preview').html('<div class="loading">Loading ingredients...</div>');
        
        // Load mix details
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_details',
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                console.log('Mix details response:', response);
                
                if (response.success && response.data) {
                    var mix = response.data;
                    $('#edit-mix-name').val(mix.mix_name || '');
                    $('#edit-mix-description').val(mix.mix_description || '');
                    
                    // Load recipe and pricing data
                    loadMixRecipeAndPricing(mixId, 'edit');
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : herbalProfileData.strings.error;
                    alert(errorMsg);
                    $('#edit-mix-modal').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr.responseText);
                alert(herbalProfileData.strings.connectionError);
                $('#edit-mix-modal').hide();
            }
        });
    });
    
    // === PUBLISH MIX FUNCTIONALITY ===
    $(document).on('click', '.publish-mix', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        
        if (!mixId) {
            alert('Mix ID not found');
            return;
        }
        
        $('#publish-mix-modal').show();
        $('#publish-mix-id').val(mixId);
        
        // Show loading state
        $('#publish-mix-ingredients-preview, #mix-ingredients-preview').html('<div class="loading">Loading ingredients...</div>');
        
        // Load mix details
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_details',
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                console.log('Mix details response:', response);
                
                if (response.success && response.data) {
                    var mix = response.data;
                    $('#publish-mix-name').val(mix.mix_name || '');
                    $('#publish-mix-description').val(mix.mix_description || '');
                    
                    // Set image if exists
                    if (mix.mix_image) {
                        $('#publish-mix-image').val(mix.mix_image);
                        if ($('#publish-mix-image-preview').length) {
                            $('#publish-mix-image-preview').attr('src', mix.mix_image).show();
                        }
                        if ($('#publish-mix-image-remove').length) {
                            $('#publish-mix-image-remove').show();
                        }
                    }
                    
                    // Load recipe and pricing data
                    loadMixRecipeAndPricing(mixId, 'publish');
                    
                    // Validate form if function exists
                    if (typeof validatePublishForm === 'function') {
                        validatePublishForm();
                    }
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : herbalProfileData.strings.error;
                    alert(errorMsg);
                    $('#publish-mix-modal').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr.responseText);
                alert(herbalProfileData.strings.connectionError);
                $('#publish-mix-modal').hide();
            }
        });
    });
    
    // === DELETE MIX FUNCTIONALITY ===
    $(document).on('click', '.delete-mix', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        var button = $(this);
        
        if (!mixId) {
            alert('Mix ID not found');
            return;
        }
        
        if (!confirm(herbalProfileData.strings.confirmDelete)) {
            return;
        }
        
        var originalText = button.text();
        button.prop('disabled', true).text(herbalProfileData.strings.deleting);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_mix',
                mix_id: mixId,
                nonce: herbalProfileData.deleteNonce || herbalProfileData.deleteMixNonce
            },
            success: function(response) {
                if (response.success) {
                    alert(herbalProfileData.strings.deleteSuccess);
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if no mixes left
                        if ($('.mixes-table tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data && response.data.message ? response.data.message : herbalProfileData.strings.error);
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete AJAX Error:', xhr.responseText);
                alert(herbalProfileData.strings.connectionError);
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // === VIEW MIX FUNCTIONALITY ===
    $(document).on('click', '.view-mix', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        
        if (!mixId) {
            alert('Mix ID not found');
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
                        window.open(response.data.view_url, '_blank');
                    } else {
                        showViewModal(response.data);
                    }
                } else {
                    alert(response.data && response.data.message ? response.data.message : herbalProfileData.strings.error);
                }
            },
            error: function() {
                alert(herbalProfileData.strings.connectionError);
            }
        });
    });
    
    // === BUY MIX FUNCTIONALITY ===
    $(document).on('click', '.buy-mix', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        var button = $(this);
        
        if (!mixId) {
            alert('Mix ID not found');
            return;
        }
        
        var originalText = button.text();
        button.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'buy_mix',
                mix_id: mixId,
                nonce: herbalProfileData.buyMixNonce
            },
            success: function(response) {
                if (response.success && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    alert(response.data && response.data.message ? response.data.message : 'Failed to process purchase');
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(herbalProfileData.strings.connectionError);
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // === UPDATE MIX FORM SUBMISSION ===
    $(document).on('submit', '#edit-mix-form', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'update_mix_details');
        formData.append('nonce', herbalProfileData.updateMixNonce);
        
        var submitButton = $('#edit-update-button');
        var originalText = submitButton.text();
        submitButton.prop('disabled', true).text(herbalProfileData.strings.updating);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(herbalProfileData.strings.updateSuccess);
                    $('#edit-mix-modal').hide();
                    location.reload();
                } else {
                    alert(response.data && response.data.message ? response.data.message : herbalProfileData.strings.error);
                }
                submitButton.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert(herbalProfileData.strings.connectionError);
                submitButton.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // === PUBLISH MIX FORM SUBMISSION ===
    $(document).on('submit', '#publish-mix-form', function(e) {
        e.preventDefault();
        
        if (!validatePublishForm()) {
            return;
        }
        
        var formData = new FormData(this);
        formData.append('action', 'publish_mix');
        formData.append('nonce', herbalProfileData.publishNonce);
        
        var submitButton = $('#publish-button');
        var originalText = submitButton.text();
        submitButton.prop('disabled', true).text(herbalProfileData.strings.publishing);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(herbalProfileData.strings.publishSuccess);
                    $('#publish-mix-modal').hide();
                    location.reload();
                } else {
                    alert(response.data && response.data.message ? response.data.message : herbalProfileData.strings.error);
                }
                submitButton.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert(herbalProfileData.strings.connectionError);
                submitButton.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // === REMOVE FAVORITE MIX ===
    $(document).on('click', '.remove-favorite', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        var button = $(this);
        
        if (!mixId) {
            alert('Mix ID not found');
            return;
        }
        
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
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if no favorites left
                        if ($('.mixes-table tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data && response.data.message ? response.data.message : herbalProfileData.strings.error);
                }
            },
            error: function() {
                alert(herbalProfileData.strings.connectionError);
            }
        });
    });
    
    // === MODAL CLOSE HANDLERS ===
    $(document).on('click', '.close-modal, .cancel-modal', function(e) {
        e.preventDefault();
        $(this).closest('.modal-dialog').hide();
    });
    
    // Close modal when clicking outside
    $(document).on('click', '.modal-dialog', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // === HELPER FUNCTIONS ===
    
    /**
     * Load Mix Recipe and Pricing (FIXED VERSION)
     */
    function loadMixRecipeAndPricing(mixId, type) {
        if (!mixId) {
            console.error('No mix ID provided for recipe loading');
            return;
        }
        
        // Determine which elements to update based on type
        var ingredientsSelector, priceSelector, pointsPriceSelector, pointsEarnedSelector;
        
        switch(type) {
            case 'edit':
                ingredientsSelector = '#edit-mix-ingredients-preview';
                priceSelector = '#edit-mix-price';
                pointsPriceSelector = '#edit-mix-points-price';
                pointsEarnedSelector = '#edit-mix-points-earned';
                break;
            case 'publish':
                ingredientsSelector = '#publish-mix-ingredients-preview, #mix-ingredients-preview';
                priceSelector = '#publish-mix-price, #mix-price-preview';
                pointsPriceSelector = '#publish-mix-points-price, #mix-points-price-preview';
                pointsEarnedSelector = '#publish-mix-points-earned, #mix-points-earned-preview';
                break;
            case 'view':
                ingredientsSelector = '#view-mix-ingredients';
                priceSelector = '#view-mix-price';
                pointsPriceSelector = '#view-mix-points-price';
                pointsEarnedSelector = '#view-mix-points-earned';
                break;
            default:
                console.error('Unknown type for loadMixRecipeAndPricing:', type);
                return;
        }
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_recipe_and_pricing',
                mix_id: mixId,
                nonce: herbalProfileData.getNonce || herbalProfileData.recipeNonce
            },
            success: function(response) {
                console.log('Recipe and pricing response:', response);
                
                if (response.success && response.data) {
                    var data = response.data;
                    
                    // Update ingredients display
                    if (data.ingredients_html) {
                        $(ingredientsSelector).html(data.ingredients_html);
                    } else {
                        $(ingredientsSelector).html('<div class="no-ingredients">' + herbalProfileData.strings.noIngredients + '</div>');
                    }
                    
                    // Update pricing display
                    if (data.total_price !== undefined) {
                        $(priceSelector).text('£' + data.total_price);
                    }
                    
                    if (data.total_points !== undefined) {
                        $(pointsPriceSelector).text(data.total_points + ' pts');
                    }
                    
                    if (data.points_earned !== undefined) {
                        $(pointsEarnedSelector).text(data.points_earned + ' pts');
                    }
                    
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to load recipe data';
                    console.error('Recipe loading error:', errorMsg);
                    $(ingredientsSelector).html('<div class="error">Failed to load recipe: ' + errorMsg + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Recipe AJAX Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                $(ingredientsSelector).html('<div class="error">' + herbalProfileData.strings.connectionError + '</div>');
            }
        });
    }
    
    /**
     * Validate Publish Form
     */
    function validatePublishForm() {
        var nameValid = $('#publish-mix-name').val().trim() !== '';
        var descriptionValid = $('#publish-mix-description').val().trim() !== '';
        var imageValid = $('#publish-mix-image').val() !== '';
        
        var allValid = nameValid && descriptionValid && imageValid;
        $('#publish-button').prop('disabled', !allValid);
        
        // Show error messages
        if (!nameValid && $('#publish-mix-name').val() !== undefined) {
            $('#publish-mix-name').addClass('error');
        } else {
            $('#publish-mix-name').removeClass('error');
        }
        
        if (!descriptionValid && $('#publish-mix-description').val() !== undefined) {
            $('#publish-mix-description').addClass('error');
        } else {
            $('#publish-mix-description').removeClass('error');
        }
        
        if (!imageValid && $('#publish-mix-image').val() !== undefined) {
            $('#publish-image-error').text(herbalProfileData.strings.imageRequired || 'Please select an image for your mix.').show();
        } else {
            $('#publish-image-error').hide();
        }
        
        return allValid;
    }
    
    /**
     * Show View Modal
     */
    function showViewModal(mixData) {
        // Create view modal if it doesn't exist
        if ($('#view-mix-modal').length === 0) {
            var modalHtml = `
                <div id="view-mix-modal" class="modal-dialog">
                    <div class="modal-content">
                        <span class="close-modal">&times;</span>
                        <h3>${mixData.mix_name}</h3>
                        <div class="mix-author">Created by: ${mixData.author_name}</div>
                        <div class="mix-description">${mixData.mix_description || 'No description'}</div>
                        <div class="mix-recipe-preview">
                            <h4>Ingredients</h4>
                            <div id="view-mix-ingredients" class="ingredients-list"></div>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="button button-primary buy-mix" data-mix-id="${mixData.id}">Buy This Mix</button>
                            <button type="button" class="button button-secondary cancel-modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
        }
        
        $('#view-mix-modal').show();
        loadMixRecipeAndPricing(mixData.id, 'view');
    }
    
    // === FORM VALIDATION ON INPUT ===
    $(document).on('input', '#publish-mix-name, #publish-mix-description', function() {
        validatePublishForm();
    });
    
    $(document).on('input', '#edit-mix-name', function() {
        var hasValue = $(this).val().trim() !== '';
        $('#edit-update-button').prop('disabled', !hasValue);
    });
    
    // === IMAGE UPLOAD HANDLING ===
    $(document).on('change', '#publish-mix-image-input', function() {
        var file = this.files[0];
        if (file) {
            // Preview image
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#publish-mix-image-preview').attr('src', e.target.result).show();
                $('#publish-mix-image-remove').show();
            };
            reader.readAsDataURL(file);
            
            // Set value for validation
            $('#publish-mix-image').val('temp-value');
            validatePublishForm();
        }
    });
    
    $(document).on('click', '#publish-mix-image-remove', function() {
        $('#publish-mix-image-input').val('');
        $('#publish-mix-image').val('');
        $('#publish-mix-image-preview').hide();
        $(this).hide();
        validatePublishForm();
    });
    
    // Make functions available globally
    window.loadMixRecipeAndPricing = loadMixRecipeAndPricing;
    window.validatePublishForm = validatePublishForm;
    window.showViewModal = showViewModal;
    
});