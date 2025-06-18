jQuery(document).ready(function($) {
    
    // === EDIT MIX FUNCTIONALITY ===
    $(document).on('click', '.edit-mix', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        
        if (!mixId) {
            alert(herbalProfileData.strings.error);
            return;
        }
        
        $('#edit-mix-modal').show();
        $('#edit-mix-id').val(mixId);
        
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
                if (response.success) {
                    var mix = response.data;
                    $('#edit-mix-name').val(mix.mix_name);
                    $('#edit-mix-description').val(mix.mix_description);
                    
                    // Load recipe and pricing
                    loadMixRecipeAndPricing(mixId, 'edit');
                } else {
                    alert(response.data.message || herbalProfileData.strings.error);
                    $('#edit-mix-modal').hide();
                }
            },
            error: function() {
                alert(herbalProfileData.strings.error);
                $('#edit-mix-modal').hide();
            }
        });
    });
    
    // === PUBLISH MIX FUNCTIONALITY ===
    $(document).on('click', '.publish-mix', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        
        if (!mixId) {
            alert(herbalProfileData.strings.error);
            return;
        }
        
        $('#publish-mix-modal').show();
        $('#publish-mix-id').val(mixId);
        
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
                if (response.success) {
                    var mix = response.data;
                    $('#publish-mix-name').val(mix.mix_name);
                    $('#publish-mix-description').val(mix.mix_description);
                    
                    // Set image if exists
                    if (mix.mix_image) {
                        $('#publish-mix-image').val(mix.mix_image);
                        $('#publish-mix-image-preview').attr('src', mix.mix_image).show();
                        $('#publish-mix-image-remove').show();
                    }
                    
                    // Load recipe and pricing
                    loadMixRecipeAndPricing(mixId, 'publish');
                    
                    // Validate form
                    validatePublishForm();
                } else {
                    alert(response.data.message || herbalProfileData.strings.error);
                    $('#publish-mix-modal').hide();
                }
            },
            error: function() {
                alert(herbalProfileData.strings.error);
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
            alert(herbalProfileData.strings.error);
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
                nonce: herbalProfileData.deleteNonce
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
                    alert(response.data.message || herbalProfileData.strings.error);
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(herbalProfileData.strings.error);
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // === BUY MIX FUNCTIONALITY ===
    $(document).on('click', '.buy-mix', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        var button = $(this);
        
        if (!mixId) {
            alert(herbalProfileData.strings.error);
            return;
        }
        
        var originalText = button.text();
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
                    alert(herbalProfileData.strings.buySuccess);
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    }
                } else {
                    alert(response.data.message || herbalProfileData.strings.error);
                }
            },
            error: function() {
                button.prop('disabled', false).text(originalText);
                alert(herbalProfileData.strings.error);
            }
        });
    });
    
    // === VIEW MIX FUNCTIONALITY ===
    $(document).on('click', '.view-mix', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        
        if (!mixId) {
            alert(herbalProfileData.strings.error);
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
                        window.location.href = response.data.view_url;
                    } else {
                        // Show mix details in modal
                        showViewModal(response.data);
                    }
                } else {
                    alert(response.data.message || herbalProfileData.strings.error);
                }
            },
            error: function() {
                alert(herbalProfileData.strings.error);
            }
        });
    });
    
    // === EDIT FORM SUBMISSION ===
    $(document).on('submit', '#edit-mix-form', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = $('#edit-update-button');
        var originalText = submitBtn.text();
        
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
                if (response.success) {
                    alert(herbalProfileData.strings.updateSuccess);
                    $('#edit-mix-modal').hide();
                    location.reload();
                } else {
                    alert(response.data.message || herbalProfileData.strings.error);
                }
                submitBtn.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert(herbalProfileData.strings.error);
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // === PUBLISH FORM SUBMISSION ===
    $(document).on('submit', '#publish-mix-form', function(e) {
        e.preventDefault();
        
        if (!validatePublishForm()) {
            return false;
        }
        
        var form = $(this);
        var submitBtn = $('#publish-button');
        var originalText = submitBtn.text();
        
        submitBtn.prop('disabled', true).text(herbalProfileData.strings.publishing);
        
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'publish_mix',
                mix_id: $('#publish-mix-id').val(),
                mix_name: $('#publish-mix-name').val(),
                mix_description: $('#publish-mix-description').val(),
                mix_image: $('#publish-mix-image').val(),
                nonce: herbalProfileData.publishNonce
            },
            success: function(response) {
                if (response.success) {
                    alert(herbalProfileData.strings.publishSuccess);
                    if (response.data && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    alert(response.data.message || herbalProfileData.strings.error);
                    submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(herbalProfileData.strings.error);
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // === IMAGE UPLOAD FUNCTIONALITY ===
    $(document).on('click', '#publish-mix-image-select', function(e) {
        e.preventDefault();
        
        var fileInput = $('<input type="file" accept="image/*">');
        fileInput.click();
        
        fileInput.on('change', function() {
            var file = this.files[0];
            if (!file) return;
            
            // Validate file type
            if (!file.type.match(/^image\/(jpeg|jpg|png|gif)$/)) {
                alert('Invalid file type. Only JPEG, PNG and GIF are allowed.');
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File too large. Maximum size is 5MB.');
                return;
            }
            
            // Upload file
            var formData = new FormData();
            formData.append('mix_image', file);
            formData.append('action', 'upload_mix_image');
            formData.append('nonce', herbalProfileData.uploadNonce);
            
            $('#publish-image-error').hide();
            $('#publish-mix-image-select').prop('disabled', true).text('Uploading...');
            
            $.ajax({
                url: herbalProfileData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#publish-mix-image').val(response.data.url);
                        $('#publish-mix-image-preview').attr('src', response.data.url).show();
                        $('#publish-mix-image-remove').show();
                        $('#publish-mix-image-select').text('Change Image');
                        validatePublishForm();
                    } else {
                        $('#publish-image-error').text(response.data.message).show();
                    }
                    $('#publish-mix-image-select').prop('disabled', false);
                },
                error: function() {
                    $('#publish-image-error').text('Upload failed. Please try again.').show();
                    $('#publish-mix-image-select').prop('disabled', false).text('Select Image');
                }
            });
        });
    });
    
    // Remove image
    $(document).on('click', '#publish-mix-image-remove', function(e) {
        e.preventDefault();
        $('#publish-mix-image').val('');
        $('#publish-mix-image-preview').hide();
        $('#publish-mix-image-remove').hide();
        $('#publish-mix-image-select').text('Select Image');
        validatePublishForm();
    });
    
    // === REMOVE FAVORITE FUNCTIONALITY ===
    $(document).on('click', '.remove-favorite', function(e) {
        e.preventDefault();
        var mixId = $(this).data('mix-id');
        var button = $(this);
        
        if (!mixId) {
            alert(herbalProfileData.strings.error);
            return;
        }
        
        if (!confirm('Are you sure you want to remove this mix from favorites?')) {
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
                    alert(response.data.message || herbalProfileData.strings.error);
                }
            },
            error: function() {
                alert(herbalProfileData.strings.error);
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
    
    function loadMixRecipeAndPricing(mixId, type) {
        $.ajax({
            url: herbalProfileData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_mix_recipe_and_pricing',
                mix_id: mixId,
                nonce: herbalProfileData.getNonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    var data = response.data;
                    var prefix = type === 'edit' ? '#edit-' : '#publish-';
                    
                    // Update ingredients
                    $(prefix + 'mix-ingredients-preview').html(data.ingredients_html);
                    
                    // Update pricing
                    $(prefix + 'mix-price').text('£' + data.total_price);
                    $(prefix + 'mix-points-price').text(data.total_points + ' pts');
                    $(prefix + 'mix-points-earned').text(data.points_earned + ' pts');
                }
            }
        });
    }
    
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
            $('#publish-image-error').text(herbalProfileData.strings.imageRequired).show();
        } else {
            $('#publish-image-error').hide();
        }
        
        return allValid;
    }
    
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
    
    // === INITIALIZE ===
    console.log('Herbal Profile JS loaded successfully');
});
