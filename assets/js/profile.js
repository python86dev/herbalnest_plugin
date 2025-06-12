/**
 * Enhanced Profile JavaScript for Herbal Mix Creator
 * Complete version with all original functions + recipe loading fixes
 */

// Prevent duplicate loading
if (typeof herbalProfileLoaded !== 'undefined') {
    console.warn('Profile.js already loaded, skipping...');
    return;
}
var herbalProfileLoaded = true;

jQuery(document).ready(function($) {
    
    // === GLOBAL VARIABLES ===
    let currentMixData = null;
    // FIXED: Prevent duplicate recipe loading by using flags
    let isLoadingRecipe = false;
    
    // === UTILITY FUNCTIONS ===
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function showError(message) {
        // Improved error display - handle object errors properly
        let errorMsg = message;
        if (typeof message === 'object') {
            if (message.message) {
                errorMsg = message.message;
            } else if (message.data) {
                errorMsg = message.data;
            } else {
                errorMsg = 'An unknown error occurred';
            }
        }
        alert(errorMsg);
        console.error('Error:', message);
    }
    
    function showLoading(element, show = true) {
        if (show) {
            element.addClass('loading');
        } else {
            element.removeClass('loading');
        }
    }
    
    // === EDIT MIX FUNCTIONALITY ===
    $(document).on("click", ".edit-mix", function(e) {
        e.preventDefault();
        var mixId = $(this).data("mix-id");
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        openEditModal(mixId);
    });
    
    function openEditModal(mixId) {
        $("#edit-mix-modal").show();
        $("#edit-mix-form").data("mix-id", mixId);
        $("#edit-mix-id").val(mixId);
        
        // Show loading state
        showLoading($("#edit-mix-modal .modal-content"));
        
        loadMixForEdit(mixId);
    }
    
    function loadMixForEdit(mixId) {
        console.log('Loading mix for edit, ID:', mixId);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: "GET",
            data: {
                action: "get_mix_details",
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                console.log('Mix details response:', response);
                
                if (response.success) {
                    var mix = response.data;
                    currentMixData = mix;
                    
                    // Fill form fields
                    $("#edit-mix-name").val(mix.mix_name || "");
                    $("#edit-mix-description").val(mix.mix_description || "");
                    
                    // Handle image display
                    if (mix.mix_image) {
                        setEditImagePreview(mix.mix_image);
                    } else {
                        clearEditImagePreview();
                    }
                    
                    // Load recipe and pricing data - FIXED FUNCTION CALL
                    fetchEditMixRecipeAndPricing(mixId);
                    
                } else {
                    showLoading($("#edit-mix-modal .modal-content"), false);
                    showError(response.data || 'Failed to load mix details');
                    $("#edit-mix-modal").hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading mix details:', status, error, xhr.responseText);
                showLoading($("#edit-mix-modal .modal-content"), false);
                showError('Connection error loading mix. Please try again.');
                $("#edit-mix-modal").hide();
            }
        });
    }
    
    // === ENHANCED RECIPE AND PRICING LOADING - FIXED to prevent duplicates ===
    function fetchEditMixRecipeAndPricing(mixId) {
        if (isLoadingRecipe) {
            console.log('Recipe loading already in progress, skipping...');
            return;
        }
        
        isLoadingRecipe = true;
        console.log('Fetching recipe and pricing for mix ID:', mixId);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: "GET",
            data: {
                action: "get_mix_recipe_and_pricing",
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                isLoadingRecipe = false; // Reset flag
                console.log('Recipe and pricing response:', response);
                showLoading($("#edit-mix-modal .modal-content"), false);
                
                if (response.success) {
                    var data = response.data;
                    
                    // Update ingredients preview
                    if (data.ingredients_html) {
                        $("#edit-mix-ingredients-preview").html(data.ingredients_html);
                    } else if (data.ingredients && Array.isArray(data.ingredients)) {
                        // Fallback: build ingredients HTML from array
                        var ingredientsHtml = '<ul class="ingredients-list">';
                        data.ingredients.forEach(function(ingredient) {
                            ingredientsHtml += `
                                <li class="ingredient-item">
                                    <span class="ingredient-name">${escapeHtml(ingredient.name || 'Unknown')}</span>
                                    <span class="ingredient-weight">${ingredient.weight || 0}g</span>
                                </li>
                            `;
                        });
                        ingredientsHtml += '</ul>';
                        $("#edit-mix-ingredients-preview").html(ingredientsHtml);
                    } else {
                        $("#edit-mix-ingredients-preview").html('<p>No ingredients found.</p>');
                    }
                    
                    // FIXED: Update pricing with consistent format
                    $("#edit-mix-price-preview").text('£' + (data.total_price || '0.00'));
                    $("#edit-mix-points-price-preview").text((data.total_points || '0') + ' pts');
                    $("#edit-mix-points-earned-preview").text((data.points_earned || '0') + ' pts');
                    
                } else {
                    showError(response.data || 'Failed to load recipe details');
                    $("#edit-mix-ingredients-preview").html('<p class="error-message">Error loading ingredients</p>');
                }
            },
            error: function(xhr, status, error) {
                isLoadingRecipe = false; // Reset flag
                console.error('AJAX error loading recipe:', status, error, xhr.responseText);
                showLoading($("#edit-mix-modal .modal-content"), false);
                showError('Connection error loading recipe. Please try again.');
                $("#edit-mix-ingredients-preview").html('<p class="error-message">Connection error loading ingredients</p>');
            }
        });
    }
    
    // === IMAGE HANDLING FUNCTIONS ===
    function setEditImagePreview(imageUrl) {
        if ($("#edit-mix-image-preview").length) {
            $("#edit-mix-image-preview").attr('src', imageUrl).show();
            $("#edit-mix-image_select_btn").text('Change Image');
            $("#edit-mix-image_remove_btn").show();
        }
    }
    
    function clearEditImagePreview() {
        if ($("#edit-mix-image-preview").length) {
            $("#edit-mix-image-preview").hide();
            $("#edit-mix-image_select_btn").text('Select Image');
            $("#edit-mix-image_remove_btn").hide();
        }
    }
    
    // === PUBLISH MIX FUNCTIONALITY ===
    $(document).on("click", ".publish-mix", function(e) {
        e.preventDefault();
        var mixId = $(this).data("mix-id");
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        openPublishModal(mixId);
    });
    
    function openPublishModal(mixId) {
        $("#publish-mix-modal").show();
        $("#publish-mix-form").data("mix-id", mixId);
        $("#mix-id").val(mixId);
        
        // Show loading state
        showLoading($("#publish-mix-modal .modal-content"));
        
        loadMixForPublish(mixId);
    }
    
    function loadMixForPublish(mixId) {
        console.log('Loading mix for publish, ID:', mixId);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: "GET",
            data: {
                action: "get_mix_details",
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                console.log('Publish mix details response:', response);
                
                if (response.success) {
                    var mix = response.data;
                    currentMixData = mix;
                    
                    // Fill form fields
                    $("#mix-name").val(mix.mix_name || "");
                    $("#mix-description").val(mix.mix_description || "");
                    
                    // Handle image display
                    if (mix.mix_image) {
                        setPublishImagePreview(mix.mix_image);
                    } else {
                        clearPublishImagePreview();
                    }
                    
                    // Load recipe and pricing data
                    fetchPublishMixRecipeAndPricing(mixId);
                    
                    // Validate form
                    validatePublishForm();
                    
                } else {
                    showLoading($("#publish-mix-modal .modal-content"), false);
                    showError(response.data || 'Failed to load mix for publishing');
                    $("#publish-mix-modal").hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading mix for publish:', status, error, xhr.responseText);
                showLoading($("#publish-mix-modal .modal-content"), false);
                showError('Connection error loading mix. Please try again.');
                $("#publish-mix-modal").hide();
            }
        });
    }
    
    function fetchPublishMixRecipeAndPricing(mixId) {
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: "GET",
            data: {
                action: "get_mix_recipe_and_pricing",
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                console.log('Publish recipe and pricing response:', response);
                showLoading($("#publish-mix-modal .modal-content"), false);
                
                if (response.success) {
                    var data = response.data;
                    
                    // Update ingredients preview
                    if (data.ingredients_html) {
                        $("#mix-ingredients-preview").html(data.ingredients_html);
                    } else if (data.ingredients && Array.isArray(data.ingredients)) {
                        var ingredientsHtml = '<ul class="ingredients-list">';
                        data.ingredients.forEach(function(ingredient) {
                            ingredientsHtml += `
                                <li class="ingredient-item">
                                    <span class="ingredient-name">${escapeHtml(ingredient.name || 'Unknown')}</span>
                                    <span class="ingredient-weight">${ingredient.weight || 0}g</span>
                                </li>
                            `;
                        });
                        ingredientsHtml += '</ul>';
                        $("#mix-ingredients-preview").html(ingredientsHtml);
                    } else {
                        $("#mix-ingredients-preview").html('<p>No ingredients found.</p>');
                    }
                    
                    // Update pricing
                    $("#mix-price-preview").text('£' + (data.total_price || "0.00"));
                    $("#mix-points-price-preview").text((data.total_points || "0") + " pts");
                    $("#mix-points-earned-preview").text((data.points_earned || "0") + " pts");
                    
                } else {
                    showError(response.data || 'Failed to load recipe for publishing');
                    $("#mix-ingredients-preview").html('<p class="error-message">Error loading ingredients</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading publish recipe:', status, error, xhr.responseText);
                showLoading($("#publish-mix-modal .modal-content"), false);
                showError('Connection error loading recipe. Please try again.');
                $("#mix-ingredients-preview").html('<p class="error-message">Connection error loading ingredients</p>');
            }
        });
    }
    
    function setPublishImagePreview(imageUrl) {
        if ($("#mix-image-preview").length) {
            $("#mix-image-preview").attr('src', imageUrl).show();
            $("#mix-image_select_btn").text('Change Image');
            $("#mix-image_remove_btn").show();
        }
    }
    
    function clearPublishImagePreview() {
        if ($("#mix-image-preview").length) {
            $("#mix-image-preview").hide();
            $("#mix-image_select_btn").text('Select Image');
            $("#mix-image_remove_btn").hide();
        }
    }
    
    // === FORM VALIDATION ===
    function validatePublishForm() {
        const nameValid = $("#mix-name").val().trim() !== '';
        const descriptionValid = $("#mix-description").val().trim() !== '';
        const imageValid = $("#mix-image").val() !== '';
        
        const allValid = nameValid && descriptionValid && imageValid;
        $("#publish-button, #publish-mix-btn").prop('disabled', !allValid);
        
        return allValid;
    }
    
    // Listen for form field changes
    $(document).on('input', '#mix-name, #mix-description', validatePublishForm);
    $(document).on('change', '#mix-image', validatePublishForm);
    $(document).on('input', '#edit-mix-name, #edit-mix-description', function() {
        // Basic validation for edit form
        const name = $('#edit-mix-name').val().trim();
        const hasChanges = name.length > 0;
        
        if ($('#edit-update-button').length) {
            $('#edit-update-button').prop('disabled', !hasChanges);
        }
    });
    
    // === PUBLISH FORM SUBMISSION - FIXED to use correct nonce ===
    $(document).on('submit', '#publish-mix-form', function(e) {
        e.preventDefault();
        
        if (!validatePublishForm()) {
            return false;
        }
        
        const submitBtn = $("#publish-button, #publish-mix-btn");
        const originalBtnText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Publishing...');
        
        const formData = new FormData(this);
        formData.append('action', 'publish_mix');
        formData.append('nonce', herbalProfileData.publishNonce); // FIXED: Use correct nonce
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                submitBtn.prop('disabled', false).text(originalBtnText);
                
                if (response.success) {
                    alert(herbalProfileData.strings.publishSuccess);
                    if (response.data && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    showError(response.data || 'Unknown error occurred');
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false).text(originalBtnText);
                console.error('AJAX Error:', status, error);
                showError('Connection error. Please try again.');
            }
        });
    });
    
    // === DELETE MIX FUNCTIONALITY ===
    $(document).on("click", ".delete-mix", function(e) {
        e.preventDefault();
        var mixId = $(this).data("mix-id");
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        if (!confirm(herbalProfileData.strings.confirmDelete || "Are you sure you want to delete this mix?")) {
            return;
        }
        
        deleteMix(mixId);
    });
    
    function deleteMix(mixId) {
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: "POST",
            data: {
                action: "delete_mix",
                mix_id: mixId,
                nonce: herbalProfileData.deleteNonce
            },
            success: function(response) {
                if (response.success) {
                    alert(herbalProfileData.strings.deleteSuccess || 'Mix deleted successfully!');
                    location.reload();
                } else {
                    showError(response.data || 'Failed to delete mix');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error deleting mix:', status, error, xhr.responseText);
                showError('Connection error deleting mix. Please try again.');
            }
        });
    }
    
    // === VIEW MIX FUNCTIONALITY ===
    $(document).on("click", ".view-mix", function(e) {
        e.preventDefault();
        var mixId = $(this).data("mix-id");
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        loadMixForView(mixId);
    });
    
    function loadMixForView(mixId) {
        $('#view-mix-modal').show();
        showLoading($('#view-mix-modal .modal-content'));
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: "GET",
            data: {
                action: "get_mix_details",
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                showLoading($('#view-mix-modal .modal-content'), false);
                
                if (response.success) {
                    var mix = response.data;
                    
                    // Fill view fields
                    $('#view-mix-name').text(mix.mix_name || 'Untitled Mix');
                    $('#view-mix-description').text(mix.mix_description || 'No description');
                    
                    if (mix.mix_image) {
                        $('#view-mix-image').attr('src', mix.mix_image).show();
                    } else {
                        $('#view-mix-image').hide();
                    }
                    
                    // Load recipe data for view
                    fetchViewMixRecipeAndPricing(mixId);
                    
                } else {
                    showError(response.data || 'Failed to load mix');
                    $('#view-mix-modal').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading mix for view:', status, error, xhr.responseText);
                showLoading($('#view-mix-modal .modal-content'), false);
                showError('Connection error loading mix. Please try again.');
                $('#view-mix-modal').hide();
            }
        });
    }
    
    function fetchViewMixRecipeAndPricing(mixId) {
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: "GET",
            data: {
                action: "get_mix_recipe_and_pricing",
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var data = response.data;
                    
                    // Update view ingredients
                    if (data.ingredients_html) {
                        $("#view-mix-ingredients").html(data.ingredients_html);
                    } else {
                        $("#view-mix-ingredients").html('<p>No ingredients found.</p>');
                    }
                    
                    // Update view pricing
                    $("#view-mix-price").text('£' + (data.total_price || "0.00"));
                    $("#view-mix-points-price").text((data.total_points || "0") + " pts");
                    $("#view-mix-points-earned").text((data.points_earned || "0") + " pts");
                } else {
                    $("#view-mix-ingredients").html('<p class="error-message">Error loading ingredients</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading view recipe:', status, error, xhr.responseText);
                $("#view-mix-ingredients").html('<p class="error-message">Connection error loading ingredients</p>');
            }
        });
    }
    
    // === MODAL CLOSE HANDLERS ===
    $(document).on("click", ".close-modal, .edit-close, .cancel-modal", function(e) {
        e.preventDefault();
        $(this).closest('.modal-dialog').hide();
        $('#publish-mix-modal').hide();
        $('#edit-mix-modal').hide();
        $('#view-mix-modal').hide();
    });
    
    // Close modal when clicking outside
    $(document).on("click", ".modal-dialog", function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // === BUY MIX FUNCTIONALITY ===
    $(document).on("click", ".buy-mix", function(e) {
        e.preventDefault();
        var mixId = $(this).data("mix-id");
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        button.prop('disabled', true).text('Adding to cart...');
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: "POST",
            data: {
                action: "buy_mix",
                mix_id: mixId,
                nonce: herbalProfileData.buyNonce
            },
            success: function(response) {
                button.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    alert('Mix added to cart successfully!');
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    }
                } else {
                    showError(response.data || 'Failed to add mix to cart');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error buying mix:', status, error, xhr.responseText);
                button.prop('disabled', false).text(originalText);
                showError('Connection error. Please try again.');
            }
        });
    });
    
    // === REMOVE FAVORITE FUNCTIONALITY ===
    $(document).on("click", ".remove-favorite", function(e) {
        e.preventDefault();
        var mixId = $(this).data("mix-id");
        
        if (!mixId) {
            showError(herbalProfileData.strings.error);
            return;
        }
        
        if (!confirm("Are you sure you want to remove this mix from favorites?")) {
            return;
        }
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: "POST",
            data: {
                action: "remove_favorite_mix",
                mix_id: mixId,
                nonce: herbalProfileData.favoritesNonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    showError(response.data || 'Failed to remove favorite');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error removing favorite:', status, error, xhr.responseText);
                showError('Connection error. Please try again.');
            }
        });
    });
    
    // === INITIALIZE ON PAGE LOAD ===
    if ($('#publish-mix-modal').length) {
        validatePublishForm();
    }
    
    // Debug: Check if elements exist on page load
    console.log('Edit buttons found:', $('.edit-mix').length);
    console.log('Publish buttons found:', $('.publish-mix').length);
    console.log('Edit modal found:', $('#edit-mix-modal').length);
    console.log('Publish modal found:', $('#publish-mix-modal').length);
    console.log('Profile.js enhanced with improved error handling loaded successfully');
});