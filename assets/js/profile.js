/**
 * KOMPLETNY: Profile JavaScript with Publish Modal
 * Plik: assets/js/profile.js
 * 
 * PEŁNA WERSJA z:
 * - Edit Mix functionality
 * - Publish Mix Modal (nowy)
 * - Delete, View, Buy, Remove Favorite
 * - Image upload handling
 * - Error handling i notifications
 * - Modal management
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('Herbal Mix Profile JS loaded - COMPLETE VERSION WITH PUBLISH MODAL');
    
    // Ensure we have the necessary data
    if (typeof herbalProfileData === 'undefined') {
        console.error('herbalProfileData not found');
        return;
    }
    
    const ajaxUrl = herbalProfileData.ajaxUrl;
    const nonce = herbalProfileData.nonce || herbalProfileData.getNonce;
    const currencySymbol = herbalProfileData.currencySymbol || '£';
    
    // === EDIT MIX FUNCTIONALITY ===
    
    // Handle Edit button click
    $(document).on('click', '.edit-mix', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const mixId = $button.data('mix-id');
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        console.log('Opening edit modal for mix:', mixId);
        
        setButtonLoading($button, true);
        
        // Load mix details
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_details',
                nonce: nonce,
                mix_id: mixId
            },
            success: function(response) {
                console.log('Mix details response:', response);
                
                if (response.success && response.data) {
                    populateEditForm(response.data);
                    loadRecipeData(mixId, 'edit');
                    $('#edit-mix-modal').show();
                } else {
                    showNotification('Error: ' + (response.data || 'Failed to load mix details'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Edit mix error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Edit Mix');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // === PUBLISH MIX MODAL FUNCTIONALITY ===
    
    // Handle publish form submission
$(document).on('submit', '#publish-mix-form', function(e) {
    e.preventDefault();
    
    console.log('=== PUBLISH FORM SUBMIT DEBUG ===');
    
    const mixName = $('#publish-mix-name').val().trim();
    const mixDescription = $('#publish-mix-description').val().trim();
    const mixImage = $('#publish-mix-image').val();
    const mixId = $('#publish-mix-id').val();
    const isConfirmed = $('#publish-confirm').is(':checked');
    
    console.log('Form data:', {
        mixId: mixId,
        mixName: mixName,
        mixDescription: mixDescription,
        mixImage: mixImage,
        isConfirmed: isConfirmed
    });
    
    // Walidacja formularza
    if (!mixName) {
        showNotification('Please enter a mix name.', 'error');
        console.log('Validation failed: no mix name');
        return;
    }
    
    if (!mixId) {
        showNotification('Invalid mix ID.', 'error');
        console.log('Validation failed: no mix ID');
        return;
    }
    
    if (!isConfirmed) {
        showNotification('Please confirm that you understand the publishing terms.', 'error');
        console.log('Validation failed: not confirmed');
        return;
    }
    
    const $button = $('#publish-button');
    
    // Przygotuj dane do wysłania
    const formData = {
        action: 'publish_mix',
        nonce: nonce, // Używaj podstawowego nonce, nie publishNonce
        mix_id: mixId,
        mix_name: mixName,
        mix_description: mixDescription,
        mix_image: mixImage
    };
    
    console.log('Sending AJAX data:', formData);
    console.log('AJAX URL:', ajaxUrl);
    
    setButtonLoading($button, true, 'Publishing...');
    
    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: formData,
        timeout: 30000, // 30 sekund timeout
        success: function(response) {
            console.log('=== PUBLISH SUCCESS RESPONSE ===');
            console.log('Raw response:', response);
            
            if (response && response.success) {
                showNotification(response.data.message || 'Mix published successfully! You earned 50 points.', 'success');
                $('#publish-mix-modal').hide();
                
                // Update the UI - zmień przycisk Publish na View
                const $row = $(`tr[data-mix-id="${mixId}"]`);
                if ($row.length) {
                    // Usuń przycisk Publish
                    $row.find('.show-publish-modal').remove();
                    
                    // Zaktualizuj status
                    $row.find('.status-badge')
                        .removeClass('status-favorite status-draft')
                        .addClass('status-published')
                        .text('Published');
                    
                    // Usuń przycisk Edit i dodaj View
                    $row.find('.edit-mix').remove();
                    $row.find('.mix-actions').prepend(`
                        <button type="button" class="button button-small view-mix" data-mix-id="${mixId}">
                            View
                        </button>
                    `);
                    
                    console.log('UI updated for published mix');
                }
                
                // Opcjonalnie: przekieruj do produktu
                if (response.data.product_url) {
                    setTimeout(() => {
                        if (confirm('Would you like to view your published product?')) {
                            window.open(response.data.product_url, '_blank');
                        }
                    }, 2000);
                }
                
            } else {
                const errorMessage = response.data || 'Failed to publish mix. Please try again.';
                showNotification('Error: ' + errorMessage, 'error');
                console.error('Publish failed:', errorMessage);
            }
        },
        error: function(xhr, status, error) {
            console.log('=== PUBLISH AJAX ERROR ===');
            console.log('Status:', status);
            console.log('Error:', error);
            console.log('Response Text:', xhr.responseText);
            console.log('Status Code:', xhr.status);
            
            let errorMessage = 'Failed to publish mix.';
            
            // Lepsze error handling
            if (xhr.status === 0) {
                errorMessage = 'Network error. Please check your connection.';
            } else if (xhr.status >= 500) {
                errorMessage = 'Server error. Please try again later.';
            } else if (xhr.status === 403) {
                errorMessage = 'Permission denied. Please refresh the page and try again.';
            } else if (xhr.responseText) {
                // Sprawdź czy to jest HTML error page
                if (xhr.responseText.includes('<html') || xhr.responseText.includes('<!DOCTYPE')) {
                    errorMessage = 'Server returned an error page. Please check the browser console for details.';
                    console.error('Server returned HTML instead of JSON:', xhr.responseText.substring(0, 500));
                } else {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.data) {
                            errorMessage = response.data;
                        }
                    } catch (e) {
                        console.error('Failed to parse error response:', e);
                        errorMessage = 'Unexpected server response.';
                    }
                }
            }
            
            showNotification('Publish error: ' + errorMessage, 'error');
        },
        complete: function() {
            setButtonLoading($button, false);
            console.log('=== PUBLISH REQUEST COMPLETE ===');
        }
    });
});

// DODAJ RÓWNIEŻ: Lepsze debugowanie dla ładowania modalu publikacji
$(document).on('click', '.show-publish-modal', function(e) {
    e.preventDefault();
    
    const $button = $(this);
    const mixId = $button.data('mix-id');
    
    console.log('=== OPENING PUBLISH MODAL ===');
    console.log('Mix ID:', mixId);
    
    if (!mixId) {
        showNotification('Invalid mix ID', 'error');
        return;
    }
    
    setButtonLoading($button, true);
    
    // Load mix details for publish
    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
            action: 'get_mix_details',
            nonce: nonce,
            mix_id: mixId
        },
        success: function(response) {
            console.log('Publish modal data response:', response);
            
            if (response.success && response.data) {
                populatePublishForm(response.data);
                loadRecipeData(mixId, 'publish');
                $('#publish-mix-modal').show();
            } else {
                showNotification('Error: ' + (response.data || 'Failed to load mix details'), 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Publish modal error:', error, xhr.responseText);
            handleAjaxError(xhr, status, error, 'Open Publish Modal');
        },
        complete: function() {
            setButtonLoading($button, false);
        }
    });
});

// NOWA FUNKCJA: Lepsze error handling
function handleAjaxError(xhr, status, error, context) {
    console.error(`=== ${context.toUpperCase()} ERROR ===`);
    console.error('Status:', status);
    console.error('Error:', error);
    console.error('Response:', xhr.responseText);
    
    let message = `${context} failed.`;
    
    if (xhr.status === 0) {
        message = 'Network connection error. Please check your internet connection.';
    } else if (xhr.status === 403) {
        message = 'Permission denied. Please refresh the page and try again.';
    } else if (xhr.status >= 500) {
        message = 'Server error. Please try again in a few moments.';
    } else if (xhr.responseText && xhr.responseText.includes('Fatal error')) {
        message = 'A server error occurred. Please contact the administrator.';
        console.error('PHP Fatal Error detected in response');
    }
    
    showNotification(message, 'error');
}

// POPRAWIONA funkcja: showNotification z lepszą widocznością
function showNotification(message, type = 'info') {
    console.log(`NOTIFICATION [${type.toUpperCase()}]: ${message}`);
    
    // Usuń poprzednie notyfikacje
    $('.herbal-notification').remove();
    
    // Utwórz nową notyfikację
    const $notification = $(`
        <div class="herbal-notification herbal-notification-${type}" style="
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'error' ? '#dc3545' : type === 'success' ? '#28a745' : '#007cba'};
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            z-index: 9999;
            max-width: 400px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            font-weight: bold;
        ">
            ${message}
            <button style="
                background: none;
                border: none;
                color: white;
                float: right;
                margin-left: 10px;
                cursor: pointer;
                font-size: 18px;
                line-height: 1;
            ">&times;</button>
        </div>
    `);
    
    // Dodaj do body
    $('body').append($notification);
    
    // Auto-hide po 5 sekundach
    setTimeout(() => {
        $notification.fadeOut(() => $notification.remove());
    }, 5000);
    
    // Pozwól na ręczne zamknięcie
    $notification.find('button').on('click', () => {
        $notification.fadeOut(() => $notification.remove());
    });
}
    
    // === DELETE MIX FUNCTIONALITY ===
    
    // Handle Delete button click
    $(document).on('click', '.delete-mix', function(e) {
        e.preventDefault();
        
        const mixId = $(this).data('mix-id');
        const $button = $(this);
        const $row = $button.closest('tr');
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        if (!confirm(herbalProfileData.strings?.confirmDelete || 'Are you sure you want to delete this mix?')) {
            return;
        }
        
        console.log('Deleting mix:', mixId);
        
        setButtonLoading($button, true, 'Deleting...');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_mix',
                nonce: herbalProfileData.deleteNonce || nonce,
                mix_id: mixId
            },
            success: function(response) {
                console.log('Delete mix response:', response);
                
                if (response.success) {
                    showNotification('Mix deleted successfully!', 'success');
                    
                    // Remove row with animation
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if table is empty and reload if needed
                        if ($('.herbal-mixes-table tbody tr:visible').length === 0) {
                            setTimeout(() => {
                                location.reload();
                            }, 500);
                        }
                    });
                } else {
                    showNotification('Error: ' + (response.data || 'Failed to delete mix'), 'error');
                    setButtonLoading($button, false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete mix error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Delete Mix');
                setButtonLoading($button, false);
            }
        });
    });
    
    // === VIEW MIX FUNCTIONALITY ===
    
    // Handle View button click
    $(document).on('click', '.view-mix', function(e) {
        e.preventDefault();
        
        const mixId = $(this).data('mix-id');
        const $button = $(this);
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        console.log('Viewing mix:', mixId);
        
        setButtonLoading($button, true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'view_mix',
                nonce: nonce,
                mix_id: mixId
            },
            success: function(response) {
                console.log('View mix response:', response);
                
                if (response.success && response.data) {
                    showViewModal(response.data);
                } else {
                    showNotification('Error: ' + (response.data || 'Failed to load mix details'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('View mix error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'View Mix');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // === BUY MIX FUNCTIONALITY ===
    
    // Handle Buy button click
    $(document).on('click', '.buy-mix', function(e) {
        e.preventDefault();
        
        const mixId = $(this).data('mix-id');
        const $button = $(this);
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        console.log('Buying mix:', mixId);
        
        setButtonLoading($button, true, 'Adding to cart...');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'buy_mix',
                nonce: herbalProfileData.buyMixNonce || nonce,
                mix_id: mixId
            },
            success: function(response) {
                console.log('Buy mix response:', response);
                
                if (response.success) {
                    showNotification('Added to cart successfully!', 'success');
                    
                    // Optional: redirect to cart
                    if (response.data && response.data.cart_url) {
                        if (confirm('Go to cart now?')) {
                            window.location.href = response.data.cart_url;
                        }
                    }
                } else {
                    showNotification('Error: ' + (response.data || 'Failed to add to cart'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Buy mix error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Buy Mix');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // === REMOVE FAVORITE FUNCTIONALITY ===
    
    // Handle Remove Favorite button click
    $(document).on('click', '.remove-favorite', function(e) {
        e.preventDefault();
        
        const mixId = $(this).data('mix-id');
        const $button = $(this);
        const $row = $button.closest('tr');
        
        if (!mixId) {
            showNotification('Invalid mix ID', 'error');
            return;
        }
        
        if (!confirm('Remove this mix from your favorites?')) {
            return;
        }
        
        console.log('Removing favorite:', mixId);
        
        setButtonLoading($button, true, 'Removing...');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'remove_favorite_mix',
                nonce: herbalProfileData.favoritesNonce || nonce,
                mix_id: mixId
            },
            success: function(response) {
                console.log('Remove favorite response:', response);
                
                if (response.success) {
                    showNotification('Removed from favorites', 'success');
                    
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        
                        if ($('.herbal-mixes-table tbody tr:visible').length === 0) {
                            setTimeout(() => {
                                location.reload();
                            }, 500);
                        }
                    });
                } else {
                    showNotification('Error: ' + (response.data || 'Failed to remove from favorites'), 'error');
                    setButtonLoading($button, false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Remove favorite error:', error, xhr.responseText);
                handleAjaxError(xhr, status, error, 'Remove Favorite');
                setButtonLoading($button, false);
            }
        });
    });
    
    // === FORM FUNCTIONS ===
    
    // Populate edit form with mix data
    function populateEditForm(mixData) {
        $('#edit-mix-id').val(mixData.id);
        $('#edit-mix-name').val(mixData.name || '');
        $('#edit-mix-description').val(mixData.description || '');
        
        // Handle image preview
        if (mixData.image) {
            $('#edit-mix-image').val(mixData.image);
            $('#edit-mix-image-preview').attr('src', mixData.image).show();
            $('#edit-mix-image-remove').show();
        } else {
            $('#edit-mix-image').val('');
            $('#edit-mix-image-preview').hide();
            $('#edit-mix-image-remove').hide();
        }
        
        // Enable/disable update button
        $('#edit-update-button').prop('disabled', !mixData.name);
    }
    
    // Populate publish form with mix data
    function populatePublishForm(mixData) {
        $('#publish-mix-id').val(mixData.id);
        $('#publish-mix-name').val(mixData.name || '');
        $('#publish-mix-description').val(mixData.description || '');
        
        // Handle image preview
        if (mixData.image) {
            $('#publish-mix-image').val(mixData.image);
            $('#publish-mix-image-preview').attr('src', mixData.image).show();
            $('#publish-mix-image-remove').show();
        } else {
            $('#publish-mix-image').val('');
            $('#publish-mix-image-preview').hide();
            $('#publish-mix-image-remove').hide();
        }
        
        // Reset checkbox and button
        $('#publish-confirm').prop('checked', false);
        $('#publish-button').prop('disabled', true);
    }
    
    // Load recipe data for modal (Edit or Publish)
    function loadRecipeData(mixId, modalType = 'edit') {
        console.log('Loading recipe data for mix:', mixId, 'Modal type:', modalType);
        
        const previewSelector = modalType === 'edit' ? '#edit-mix-ingredients-preview' : '#publish-mix-ingredients-preview';
        
        $(previewSelector).html('<div class="recipe-loading"><p>Loading recipe data...</p></div>');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_recipe_and_pricing',
                nonce: nonce,
                mix_id: mixId,
                action_type: modalType
            },
            success: function(response) {
                console.log('Recipe data response:', response);
                
                if (response.success && response.data && response.data.recipe) {
                    displayRecipePreview(response.data.recipe, modalType);
                    updatePricing(response.data.recipe, modalType);
                } else {
                    $(previewSelector).html('<div class="recipe-error"><p>Recipe data not available</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Recipe data error:', error, xhr.responseText);
                $(previewSelector).html('<div class="recipe-error"><p>Error loading recipe data</p></div>');
            }
        });
    }
    
    // Display recipe preview (dla Edit lub Publish)
    function displayRecipePreview(recipe, modalType = 'edit') {
        if (!recipe) {
            const previewSelector = modalType === 'edit' ? '#edit-mix-ingredients-preview' : '#publish-mix-ingredients-preview';
            $(previewSelector).html('<div class="recipe-error"><p>No recipe data available</p></div>');
            return;
        }
        
        let html = '<div class="recipe-preview">';
        
        // Packaging section
        if (recipe.packaging && recipe.packaging.name) {
            html += `
                <div class="packaging-section">
                    <h5 class="section-title">Packaging</h5>
                    <div class="packaging-item">
                        <div class="item-details">
                            <span class="item-name">${escapeHtml(recipe.packaging.name)}</span>
                            <span class="item-capacity">(${recipe.packaging.capacity}g capacity)</span>
                        </div>
                        <div class="item-pricing">
                            <span class="item-price">${currencySymbol}${recipe.packaging.price.toFixed(2)}</span>
                            <span class="item-points">${Math.round(recipe.packaging.points)} pts</span>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Ingredients section
        if (recipe.ingredients && recipe.ingredients.length > 0) {
            html += `
                <div class="ingredients-section">
                    <h5 class="section-title">Ingredients (${recipe.ingredients.length})</h5>
                    <div class="ingredients-list">
            `;
            
            recipe.ingredients.forEach(function(ingredient) {
                const imageUrl = ingredient.image || '';
                html += `
                    <div class="ingredient-item">
                        <div class="ingredient-info">
                            ${imageUrl ? `<img src="${imageUrl}" alt="${escapeHtml(ingredient.name)}" class="ingredient-image">` : ''}
                            <div class="ingredient-details">
                                <span class="ingredient-name">${escapeHtml(ingredient.name)}</span>
                                <span class="ingredient-weight">${ingredient.weight}g</span>
                            </div>
                        </div>
                        <div class="ingredient-pricing">
                            <span class="ingredient-price">${currencySymbol}${ingredient.total_price.toFixed(2)}</span>
                            <span class="ingredient-points">${Math.round(ingredient.total_points)} pts</span>
                        </div>
                    </div>
                `;
            });
            
            html += '</div></div>';
        }
        
        // Totals section
        if (recipe.total_weight !== undefined && recipe.total_price !== undefined) {
            html += `
                <div class="recipe-totals">
                    <div class="totals-row">
                        <span class="totals-label">Total Weight:</span>
                        <span class="totals-value">${recipe.total_weight}g</span>
                    </div>
                    <div class="totals-row">
                        <span class="totals-label">Total Price:</span>
                        <span class="totals-value">${currencySymbol}${recipe.total_price.toFixed(2)}</span>
                    </div>
                    <div class="totals-row">
                        <span class="totals-label">Total Points:</span>
                        <span class="totals-value">${Math.round(recipe.total_points)} pts</span>
                    </div>
                </div>
            `;
        }
        
        html += '</div>';
        
        const previewSelector = modalType === 'edit' ? '#edit-mix-ingredients-preview' : '#publish-mix-ingredients-preview';
        $(previewSelector).html(html);
    }
    
    // Update pricing section (dla Edit lub Publish)
    function updatePricing(recipe, modalType = 'edit') {
        const prefix = modalType === 'edit' ? 'edit' : 'publish';
        
        if (recipe.total_price !== undefined) {
            $(`#${prefix}-mix-price`).text(`${currencySymbol}${recipe.total_price.toFixed(2)}`);
        }
        
        if (recipe.total_points !== undefined) {
            $(`#${prefix}-mix-points-price`).text(`${Math.round(recipe.total_points)} pts`);
            
            if (modalType === 'edit') {
                const pointsEarned = Math.round(recipe.total_points * 0.1);
                $('#edit-mix-points-earned').text(`${pointsEarned} pts`);
            } else {
                // For publish - fixed 50 points reward
                $('#publish-mix-points-earned').text('50 pts');
            }
        }
    }
    
    // === FORM VALIDATION ===

// Enable/disable update button based on name input (POPRAWIONE)
$(document).on('input', '#edit-mix-name', function() {
    validateEditForm();
});

// Enable/disable publish button based on inputs (POPRAWIONE)
$(document).on('input change', '#publish-mix-name, #publish-confirm', function() {
    validatePublishForm();
});

// DODAJ: Walidacja po zmianie pola image (ukrytego)
$(document).on('change', '#edit-mix-image', function() {
    validateEditForm();
});

$(document).on('change', '#publish-mix-image', function() {
    validatePublishForm();
});



    // === FORM SUBMISSION ===
    
    // Handle edit form submission
    $(document).on('submit', '#edit-mix-form', function(e) {
        e.preventDefault();
        
        const mixName = $('#edit-mix-name').val().trim();
        if (!mixName) {
            showNotification('Please enter a mix name.', 'error');
            return;
        }
        
        const $button = $('#edit-update-button');
        
        const formData = {
            action: 'update_mix_details',
            nonce: nonce,
            mix_id: $('#edit-mix-id').val(),
            mix_name: mixName,
            mix_description: $('#edit-mix-description').val(),
            mix_image: $('#edit-mix-image').val()
        };
        
        setButtonLoading($button, true, 'Updating...');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotification('Mix updated successfully!', 'success');
                    $('#edit-mix-modal').hide();
                    location.reload();
                } else {
                    showNotification('Error: ' + (response.data || 'Failed to update mix.'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Update error:', error);
                handleAjaxError(xhr, status, error, 'Update Mix');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // Handle publish form submission
    $(document).on('submit', '#publish-mix-form', function(e) {
        e.preventDefault();
        
        const mixName = $('#publish-mix-name').val().trim();
        if (!mixName) {
            showNotification('Please enter a mix name.', 'error');
            return;
        }
        
        if (!$('#publish-confirm').is(':checked')) {
            showNotification('Please confirm that you understand the publishing terms.', 'error');
            return;
        }
        
        const $button = $('#publish-button');
        
        const formData = {
            action: 'publish_mix',
            nonce: herbalProfileData.publishNonce || nonce,
            mix_id: $('#publish-mix-id').val(),
            mix_name: mixName,
            mix_description: $('#publish-mix-description').val(),
            mix_image: $('#publish-mix-image').val()
        };
        
        setButtonLoading($button, true, 'Publishing...');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotification('Mix published successfully! You earned 50 points.', 'success');
                    $('#publish-mix-modal').hide();
                    
                    // Update the button in the table
                    const mixId = $('#publish-mix-id').val();
                    const $row = $(`tr[data-mix-id="${mixId}"]`);
                    $row.find('.show-publish-modal').remove();
                    $row.find('.status-badge').removeClass('status-favorite status-draft')
                        .addClass('status-published').text('Published');
                    
                    // Add View button and remove Edit button
                    $row.find('.edit-mix').remove();
                    $row.find('.mix-actions').prepend(`
                        <button type="button" class="button button-small view-mix" data-mix-id="${mixId}">
                            View
                        </button>
                    `);
                    
                } else {
                    showNotification('Error: ' + (response.data || 'Failed to publish mix.'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Publish error:', error);
                handleAjaxError(xhr, status, error, 'Publish Mix');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
  // === IMAGE UPLOAD FUNCTIONALITY ===

// Handle image upload for Edit
$(document).on('change', '#edit-mix-image-input', function() {
    const file = this.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            showNotification('Image file is too large. Please choose a file smaller than 5MB.', 'error');
            this.value = '';
            return;
        }
        
        // Show preview immediately
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#edit-mix-image-preview').attr('src', e.target.result).show();
            $('#edit-mix-image-remove').show();
        };
        reader.readAsDataURL(file);
        
        // NOWE: Upload file via AJAX
        uploadImageFile(file, 'edit');
    }
});

// Handle image upload for Publish
$(document).on('change', '#publish-mix-image-input', function() {
    const file = this.files[0];
    if (file) {
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            showNotification('Image file is too large. Please choose a file smaller than 5MB.', 'error');
            this.value = '';
            return;
        }
        
        // Show preview immediately
        const reader = new FileReader();
        reader.onload = function(e) {
            $('#publish-mix-image-preview').attr('src', e.target.result).show();
            $('#publish-mix-image-remove').show();
        };
        reader.readAsDataURL(file);
        
        // NOWE: Upload file via AJAX
        uploadImageFile(file, 'publish');
    }
});

// NOWA FUNKCJA: Upload image file via AJAX z blokadą przycisków
function uploadImageFile(file, modalType) {
    console.log(`Starting upload for ${modalType} modal:`, file.name);
    
    // NOWE: Zablokuj przyciski podczas uploadu
    lockButtonsDuringUpload(modalType, true);
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'upload_mix_image');
    formData.append('nonce', herbalProfileData.uploadImageNonce || nonce);
    formData.append('mix_image', file);
    
    // Show uploading state
    const $hiddenInput = $(`#${modalType}-mix-image`);
    $hiddenInput.val('uploading...');
    
    // AJAX upload
    $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            console.log(`Upload response for ${modalType}:`, response);
            
            if (response.success && response.data && response.data.url) {
                // Set the real URL
                $hiddenInput.val(response.data.url);
                
                // Update preview with final URL
                $(`#${modalType}-mix-image-preview`).attr('src', response.data.url);
                
                showNotification('Image uploaded successfully!', 'success');
                console.log(`Image uploaded successfully for ${modalType}:`, response.data.url);
                
            } else {
                const errorMsg = response.data || 'Failed to upload image.';
                console.error(`Upload failed for ${modalType}:`, errorMsg);
                
                // Reset on failure
                $hiddenInput.val('');
                $(`#${modalType}-mix-image-preview`).hide();
                $(`#${modalType}-mix-image-remove`).hide();
                
                showNotification('Error: ' + errorMsg, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error(`Upload AJAX error for ${modalType}:`, {xhr, status, error});
            
            // Reset on error
            $hiddenInput.val('');
            $(`#${modalType}-mix-image-preview`).hide();
            $(`#${modalType}-mix-image-remove`).hide();
            
            showNotification('Connection error. Please try again.', 'error');
        },
        complete: function() {
            // NOWE: Odblokuj przyciski po zakończeniu (sukces lub błąd)
            lockButtonsDuringUpload(modalType, false);
        }
    });
}

// NOWA FUNKCJA: Zarządzanie blokadą przycisków podczas uploadu
function lockButtonsDuringUpload(modalType, isUploading) {
    if (modalType === 'edit') {
        const $updateButton = $('#edit-update-button');
        const $fileInput = $('#edit-mix-image-input');
        
        if (isUploading) {
            // Zablokuj podczas uploadu
            $updateButton.prop('disabled', true);
            $fileInput.prop('disabled', true);
            
            // Pokaż status uploadu na przycisku
            $updateButton.data('original-text', $updateButton.text());
            $updateButton.html('<span style="opacity: 0.7;">📤 Uploading image...</span>');
            
        } else {
            // Odblokuj po zakończeniu
            $fileInput.prop('disabled', false);
            
            // Przywróć oryginalny tekst
            const originalText = $updateButton.data('original-text') || 'Update Mix';
            $updateButton.text(originalText);
            
            // Sprawdź czy formularz jest kompletny przed odblokowaniem
            validateEditForm();
        }
        
    } else if (modalType === 'publish') {
        const $publishButton = $('#publish-button');
        const $fileInput = $('#publish-mix-image-input');
        const $confirmCheckbox = $('#publish-confirm');
        
        if (isUploading) {
            // Zablokuj podczas uploadu
            $publishButton.prop('disabled', true);
            $fileInput.prop('disabled', true);
            $confirmCheckbox.prop('disabled', true);
            
            // Pokaż status uploadu na przycisku
            $publishButton.data('original-text', $publishButton.text());
            $publishButton.html('<span style="opacity: 0.7;">📤 Uploading image...</span>');
            
        } else {
            // Odblokuj po zakończeniu
            $fileInput.prop('disabled', false);
            $confirmCheckbox.prop('disabled', false);
            
            // Przywróć oryginalny tekst
            const originalText = $publishButton.data('original-text') || '🚀 Publish Mix to Community';
            $publishButton.text(originalText);
            
            // Sprawdź czy formularz jest kompletny przed odblokowaniem
            validatePublishForm();
        }
    }
}

// NOWA FUNKCJA: Walidacja formularza Edit
function validateEditForm() {
    const hasName = $('#edit-mix-name').val().trim() !== '';
    const isUploading = $('#edit-mix-image').val() === 'uploading...';
    
    $('#edit-update-button').prop('disabled', !hasName || isUploading);
}

// NOWA FUNKCJA: Walidacja formularza Publish
function validatePublishForm() {
    const hasName = $('#publish-mix-name').val().trim() !== '';
    const isConfirmed = $('#publish-confirm').is(':checked');
    const isUploading = $('#publish-mix-image').val() === 'uploading...';
    
    $('#publish-button').prop('disabled', !(hasName && isConfirmed) || isUploading);
}


// Remove image for Edit (unchanged)
$(document).on('click', '#edit-mix-image-remove', function() {
    $('#edit-mix-image').val('');
    $('#edit-mix-image-input').val('');
    $('#edit-mix-image-preview').hide();
    $(this).hide();
});

// Remove image for Publish (unchanged)
$(document).on('click', '#publish-mix-image-remove', function() {
    $('#publish-mix-image').val('');
    $('#publish-mix-image-input').val('');
    $('#publish-mix-image-preview').hide();
    $(this).hide();
});
    
    // === MODAL FUNCTIONALITY ===
    
    // Close modal functionality
    $(document).on('click', '.close-modal, .cancel-modal, .edit-close', function(e) {
        e.preventDefault();
        
        const $modal = $(this).closest('.modal-dialog');
        if ($modal.length) {
            $modal.hide();
        } else {
            $('.modal-dialog').hide();
        }
        
        resetForms();
    });
    
    // Close modal when clicking outside
    $(document).on('click', '.modal-dialog', function(e) {
        if (e.target === this) {
            $(this).hide();
            resetForms();
        }
    });
    
    // ESC key to close modal
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) {
            $('.modal-dialog').hide();
            resetForms();
        }
    });
    
    // Reset all forms
    function resetForms() {
        $('#edit-mix-form')[0]?.reset();
        $('#publish-mix-form')[0]?.reset();
        $('#edit-mix-ingredients-preview').empty();
        $('#publish-mix-ingredients-preview').empty();
        $('#edit-mix-image-preview, #publish-mix-image-preview').hide();
        $('#edit-mix-image-remove, #publish-mix-image-remove').hide();
        $('#edit-update-button, #publish-button').prop('disabled', true);
        
        // Reset pricing
        $('#edit-mix-price, #publish-mix-price').text('£0.00');
        $('#edit-mix-points-price, #publish-mix-points-price').text('0 pts');
        $('#edit-mix-points-earned').text('0 pts');
        $('#publish-mix-points-earned').text('50 pts');
        
        // Reset checkbox
        $('#publish-confirm').prop('checked', false);
    }
    
    // === VIEW MODAL FUNCTION ===
    
    // Show view modal with mix details
    function showViewModal(data) {
        const { mix, recipe } = data;
        
        // Create modal HTML
        const modalHtml = `
            <div id="view-mix-modal" class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>View Mix: ${escapeHtml(mix.name)}</h3>
                        <button type="button" class="modal-close close-modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="mix-info">
                            <p><strong>Author:</strong> ${escapeHtml(mix.author || 'Unknown')}</p>
                            <p><strong>Created:</strong> ${mix.created_at}</p>
                            ${mix.description ? `<p><strong>Description:</strong> ${escapeHtml(mix.description)}</p>` : ''}
                        </div>
                        <div class="mix-recipe">
                            <h4>Recipe Details</h4>
                            <div class="recipe-content">
                                ${recipe ? renderRecipeDetails(recipe) : '<p>Recipe details not available</p>'}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="button button-primary buy-mix" data-mix-id="${mix.id}">
                            Buy This Mix
                        </button>
                        <button type="button" class="button button-secondary close-modal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal and add new one
        $('#view-mix-modal').remove();
        $('body').append(modalHtml);
        $('#view-mix-modal').show();
    }
    
    // === UTILITY FUNCTIONS ===
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Set button loading state
    function setButtonLoading($button, isLoading, loadingText = 'Loading...') {
        if (isLoading) {
            $button.data('original-text', $button.text());
            $button.text(loadingText).prop('disabled', true);
        } else {
            const originalText = $button.data('original-text') || 'Button';
            $button.text(originalText).prop('disabled', false);
        }
    }
    
    // Show notification
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        $('.herbal-notification').remove();
        
        const notificationClass = `herbal-notification herbal-notification-${type}`;
        const notification = $(`<div class="${notificationClass}">${escapeHtml(message)}</div>`);
        
        // Add notification styles if not already present
        if (!$('#herbal-notification-styles').length) {
            $('head').append(`
                <style id="herbal-notification-styles">
                .herbal-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 6px;
                    color: #fff;
                    font-weight: 600;
                    z-index: 10000;
                    max-width: 300px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                }
                .herbal-notification-success { background: #27ae60; }
                .herbal-notification-error { background: #e74c3c; }
                .herbal-notification-info { background: #3498db; }
                .herbal-notification-warning { background: #f39c12; }
                </style>
            `);
        }
        
        $('body').append(notification);
        
        // Show with animation
        notification.fadeIn(300);
        
        // Auto hide after 5 seconds
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Handle AJAX errors
    function handleAjaxError(xhr, status, error, context = 'Action') {
        console.error(`${context} AJAX Error:`, {
            status: status,
            error: error,
            response: xhr.responseText
        });
        
        let message = `${context} failed. `;
        
        if (xhr.status === 403) {
            message += 'Permission denied.';
        } else if (xhr.status === 404) {
            message += 'Resource not found.';
        } else if (xhr.status === 500) {
            message += 'Server error.';
        } else {
            message += 'Please try again.';
        }
        
        showNotification(message, 'error');
    }
    
    // Render recipe details for view modal
    function renderRecipeDetails(recipe) {
        if (!recipe) {
            return '<p>Recipe details not available.</p>';
        }
        
        let html = '<div class="recipe-details">';
        
        // Packaging info
        if (recipe.packaging) {
            html += `
                <div class="packaging-info">
                    <h5>Packaging</h5>
                    <p><strong>${escapeHtml(recipe.packaging.name)}</strong> (${recipe.packaging.capacity}g capacity)</p>
                    <p>Price: ${currencySymbol}${recipe.packaging.price.toFixed(2)}</p>
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
                        <span class="ingredient-price">${currencySymbol}${ingredient.total_price.toFixed(2)}</span>
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
                    <p><strong>Total Price:</strong> ${currencySymbol}${recipe.total_price.toFixed(2)}</p>
                    ${recipe.total_points ? `<p><strong>Total Points:</strong> ${Math.round(recipe.total_points)} pts</p>` : ''}
                </div>
            `;
        }
        
        html += '</div>';
        return html;
    }
    
    console.log('Herbal Mix Profile JS initialization complete - ALL FEATURES LOADED');
});