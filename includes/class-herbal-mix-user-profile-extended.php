<?php
/**
 * Enhanced User Profile Extended Class - Complete Modal Implementation
 * File: includes/class-herbal-mix-user-profile-extended.php
 */

if (!defined('ABSPATH')) exit;

class HerbalMixUserProfileExtended {
    
    public function __construct() {
        // Initialize points for new users
        add_action('user_register', array($this, 'initialize_user_points'));
        
        // Use priority 15 (later) to run after HerbalProfileIntegration
        add_filter('woocommerce_account_menu_items', array($this, 'add_mix_menu_items'), 15);
        add_action('init', array($this, 'add_custom_endpoints'));
        
        // Register tab content handlers
        add_action('woocommerce_account_my-mixes_endpoint', array($this, 'render_my_mixes_tab'));
        add_action('woocommerce_account_favorite-mixes_endpoint', array($this, 'render_favorite_mixes_tab'));
        
        // Profile additional fields
        add_action('woocommerce_edit_account_form', array($this, 'add_avatar_to_account_form'));
        add_action('woocommerce_save_account_details', array($this, 'save_extra_account_details'));
        add_filter('get_avatar_url', array($this, 'custom_avatar_url'), 10, 3);
        
        // AJAX handlers for mixes
        add_action('wp_ajax_get_mix_details', array($this, 'ajax_get_mix_details'));
        add_action('wp_ajax_get_mix_recipe_and_pricing', array($this, 'ajax_get_mix_recipe_and_pricing'));
        add_action('wp_ajax_update_mix_details', array($this, 'ajax_update_mix_details'));
        add_action('wp_ajax_publish_mix', array($this, 'ajax_publish_mix'));
        add_action('wp_ajax_delete_mix', array($this, 'ajax_delete_mix'));
        add_action('wp_ajax_view_mix', array($this, 'ajax_view_mix'));
        add_action('wp_ajax_remove_favorite_mix', array($this, 'ajax_remove_favorite_mix'));
        add_action('wp_ajax_upload_mix_image', array($this, 'ajax_upload_mix_image'));
        add_action('wp_ajax_buy_mix', array($this, 'ajax_buy_mix')); // NEW: Buy functionality
        
        // Load profile assets when needed
        add_action('wp_enqueue_scripts', array($this, 'enqueue_profile_assets'));
    }
    
    /**
     * Initialize points for new users
     */
    public function initialize_user_points($user_id) {
        update_user_meta($user_id, 'reward_points', 0);
    }
    
    /**
     * Add custom endpoints
     */
    public function add_custom_endpoints() {
        add_rewrite_endpoint('my-mixes', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('favorite-mixes', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Add mix-related menu items
     */
    public function add_mix_menu_items($menu_items) {
        $new_items = array();
        foreach ($menu_items as $key => $item) {
            $new_items[$key] = $item;
            if ($key === 'points-history') {
                $new_items['my-mixes'] = __('My Herbal Mixes', 'herbal-mix-creator2');
                $new_items['favorite-mixes'] = __('Favorite Mixes', 'herbal-mix-creator2');
            }
        }
        return $new_items;
    }
    
   /**
 * Enhanced enqueue assets with correct nonces
 */
public function enqueue_profile_assets() {
    if (is_account_page()) {
        // CSS
        wp_enqueue_style(
            'herbal-profile-css',
            plugin_dir_url(__FILE__) . '../assets/css/profile.css',
            array('woocommerce-general'),
            '1.0.2'
        );
        
        // JavaScript
        wp_enqueue_script(
            'herbal-profile-js',
            plugin_dir_url(__FILE__) . '../assets/js/profile.js',
            array('jquery'),
            '1.0.2',
            true
        );
        
        // CORRECTED: Localize script with proper nonces
        wp_localize_script('herbal-profile-js', 'herbalProfileData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'getNonce' => wp_create_nonce('get_mix_details'),
            'publishNonce' => wp_create_nonce('publish_mix'), // FIXED: Add missing nonce
            'updateNonce' => wp_create_nonce('update_mix_details'),
            'deleteNonce' => wp_create_nonce('delete_mix'),
            'buyNonce' => wp_create_nonce('buy_mix'),
            'favoritesNonce' => wp_create_nonce('manage_favorites'),
            'strings' => array(
                'loading' => __('Loading...', 'herbal-mix-creator2'),
                'error' => __('An error occurred. Please try again.', 'herbal-mix-creator2'),
                'confirmDelete' => __('Are you sure you want to delete this mix?', 'herbal-mix-creator2'),
                'confirmPublish' => __('Are you sure you want to publish this mix?', 'herbal-mix-creator2'),
                'publishSuccess' => __('Mix published successfully!', 'herbal-mix-creator2'),
                'deleteSuccess' => __('Mix deleted successfully!', 'herbal-mix-creator2'),
                'updateSuccess' => __('Mix updated successfully!', 'herbal-mix-creator2'),
                'buySuccess' => __('Mix added to cart successfully!', 'herbal-mix-creator2'),
                'imageRequired' => __('Please upload an image for your mix.', 'herbal-mix-creator2'),
                'nameRequired' => __('Please enter a name for your mix.', 'herbal-mix-creator2'),
                'descriptionRequired' => __('Please enter a description for your mix.', 'herbal-mix-creator2')
            )
        ));
        
        // REMOVE DUPLICATED INLINE SCRIPT - This was causing the double loading
        // wp_add_inline_script('jquery', $this->get_enhanced_modal_script());
    }
}
   
  
   
    
    /**
     * Get enhanced modal JavaScript
     */
    private function get_enhanced_modal_script() {
        return '
        jQuery(document).ready(function($) {
            
            // === GLOBAL VARIABLES ===
            let currentMixData = null;
            
            // === EDIT MIX FUNCTIONALITY ===
            $(document).on("click", ".edit-mix", function(e) {
                e.preventDefault();
                var mixId = $(this).data("mix-id");
                
                if (!mixId) {
                    alert(herbalProfileData.strings.error);
                    return;
                }
                
                openEditModal(mixId);
            });
            
            // === PUBLISH MIX FUNCTIONALITY ===
            $(document).on("click", ".publish-mix", function(e) {
                e.preventDefault();
                var mixId = $(this).data("mix-id");
                
                if (!mixId) {
                    alert(herbalProfileData.strings.error);
                    return;
                }
                
                openPublishModal(mixId);
            });
            
            // === BUY MIX FUNCTIONALITY ===
            $(document).on("click", ".buy-mix", function(e) {
                e.preventDefault();
                var mixId = $(this).data("mix-id");
                var button = $(this);
                
                if (!mixId) {
                    alert(herbalProfileData.strings.error);
                    return;
                }
                
                button.prop("disabled", true).text(herbalProfileData.strings.buying);
                
                $.ajax({
                    url: herbalProfileData.ajaxUrl,
                    type: "POST",
                    data: {
                        action: "buy_mix",
                        mix_id: mixId,
                        nonce: herbalProfileData.buyNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(herbalProfileData.strings.buySuccess);
                            window.location.href = herbalProfileData.cartUrl;
                        } else {
                            alert(response.data.message || herbalProfileData.strings.error);
                        }
                    },
                    error: function() {
                        alert(herbalProfileData.strings.error);
                    },
                    complete: function() {
                        button.prop("disabled", false).text("' . __('Buy', 'herbal-mix-creator2') . '");
                    }
                });
            });
            
            // === DELETE MIX FUNCTIONALITY ===
            $(document).on("click", ".delete-mix", function(e) {
                e.preventDefault();
                var mixId = $(this).data("mix-id");
                var button = $(this);
                
                if (!mixId) {
                    alert(herbalProfileData.strings.error);
                    return;
                }
                
                if (!confirm(herbalProfileData.strings.confirmDelete)) {
                    return;
                }
                
                button.prop("disabled", true).text(herbalProfileData.strings.deleting);
                
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
                            alert(herbalProfileData.strings.deleteSuccess);
                            button.closest("tr").fadeOut(300);
                        } else {
                            alert(response.data.message || herbalProfileData.strings.error);
                        }
                    },
                    error: function() {
                        alert(herbalProfileData.strings.error);
                    },
                    complete: function() {
                        button.prop("disabled", false).text("' . __('Delete', 'herbal-mix-creator2') . '");
                    }
                });
            });
            
            // === MODAL FUNCTIONS ===
            
            function openEditModal(mixId) {
                $("#edit-mix-modal").show();
                $("#edit-mix-form").data("mix-id", mixId);
                $("#edit-mix-id").val(mixId);
                
                // Show loading state
                $("#edit-mix-modal .modal-content").addClass("loading");
                
                loadMixForEdit(mixId);
            }
            
            function openPublishModal(mixId) {
                $("#publish-mix-modal").show();
                $("#publish-mix-form").data("mix-id", mixId);
                $("#mix-id").val(mixId);
                
                // Show loading state
                $("#publish-mix-modal .modal-content").addClass("loading");
                
                loadMixForPublish(mixId);
            }
            
            function loadMixForEdit(mixId) {
                $.ajax({
                    url: herbalProfileData.ajaxUrl,
                    type: "GET",
                    data: {
                        action: "get_mix_details",
                        mix_id: mixId,
                        nonce: herbalProfileData.getNonce
                    },
                    success: function(response) {
                        $("#edit-mix-modal .modal-content").removeClass("loading");
                        
                        if (response.success) {
                            var mix = response.data;
                            currentMixData = mix;
                            
                            // Fill form fields
                            $("#edit-mix-name").val(mix.mix_name || "");
                            $("#edit-mix-description").val(mix.mix_description || "");
                            
                            // Handle image display
                            if (mix.mix_image) {
                                setEditImagePreview(mix.mix_image);
                            }
                            
                            // Load recipe and pricing data
                            loadMixRecipeAndPricing(mixId, "edit");
                            
                        } else {
                            alert(response.data.message || herbalProfileData.strings.error);
                            $("#edit-mix-modal").hide();
                        }
                    },
                    error: function() {
                        $("#edit-mix-modal .modal-content").removeClass("loading");
                        alert(herbalProfileData.strings.error);
                        $("#edit-mix-modal").hide();
                    }
                });
            }
            
            function loadMixForPublish(mixId) {
                $.ajax({
                    url: herbalProfileData.ajaxUrl,
                    type: "GET",
                    data: {
                        action: "get_mix_details",
                        mix_id: mixId,
                        nonce: herbalProfileData.getNonce
                    },
                    success: function(response) {
                        $("#publish-mix-modal .modal-content").removeClass("loading");
                        
                        if (response.success) {
                            var mix = response.data;
                            currentMixData = mix;
                            
                            // Fill form fields
                            $("#mix-name").val(mix.mix_name || "");
                            $("#mix-description").val(mix.mix_description || "");
                            
                            // Handle image display
                            if (mix.mix_image) {
                                setPublishImagePreview(mix.mix_image);
                            }
                            
                            // Load recipe and pricing data
                            loadMixRecipeAndPricing(mixId, "publish");
                            
                            // Enable/disable publish button based on validation
                            validatePublishForm();
                            
                        } else {
                            alert(response.data.message || herbalProfileData.strings.error);
                            $("#publish-mix-modal").hide();
                        }
                    },
                    error: function() {
                        $("#publish-mix-modal .modal-content").removeClass("loading");
                        alert(herbalProfileData.strings.error);
                        $("#publish-mix-modal").hide();
                    }
                });
            }
            
            function loadMixRecipeAndPricing(mixId, type) {
                $.ajax({
                    url: herbalProfileData.ajaxUrl,
                    type: "GET",
                    data: {
                        action: "get_mix_recipe_and_pricing",
                        mix_id: mixId,
                        nonce: herbalProfileData.getNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            
                            if (type === "edit") {
                                $("#edit-mix-ingredients-preview").html(data.ingredients_html || "");
                                $("#edit-mix-price-preview").text("£" + (data.total_price || "0.00"));
                                $("#edit-mix-points-price-preview").text((data.total_points || "0") + " points");
                                $("#edit-mix-points-earned-preview").text((data.points_earned || "0") + " points");
                            } else if (type === "publish") {
                                $("#mix-ingredients-preview").html(data.ingredients_html || "");
                                $("#mix-price-preview").text("£" + (data.total_price || "0.00"));
                                $("#mix-points-price-preview").text((data.total_points || "0") + " points");
                                $("#mix-points-earned-preview").text((data.points_earned || "0") + " points");
                            }
                        }
                    },
                    error: function() {
                        console.log("Could not load recipe and pricing data");
                    }
                });
            }
            
            // === FORM VALIDATION ===
            
            function validatePublishForm() {
                const nameValid = $("#mix-name").val().trim() !== "";
                const descriptionValid = $("#mix-description").val().trim() !== "";
                const imageValid = $("#mix-image").val() !== "" || (currentMixData && currentMixData.mix_image);
                
                const allValid = nameValid && descriptionValid && imageValid;
                $("#publish-button").prop("disabled", !allValid);
                
                return allValid;
            }
            
            // Listen for form field changes
            $(document).on("input", "#mix-name, #mix-description", validatePublishForm);
            $(document).on("change", "#mix-image", validatePublishForm);
            
            // === FORM SUBMISSIONS ===
            
            // Handle edit form submission
            $(document).on("submit", "#edit-mix-form", function(e) {
                e.preventDefault();
                
                if (!$("#edit-mix-name").val().trim()) {
                    alert(herbalProfileData.strings.nameRequired);
                    $("#edit-mix-name").focus();
                    return false;
                }
                
                const submitBtn = $("#edit-update-button");
                const originalBtnText = submitBtn.text();
                submitBtn.prop("disabled", true).text(herbalProfileData.strings.updating);
                
                const formData = new FormData(this);
                formData.append("action", "update_mix_details");
                formData.append("nonce", herbalProfileData.updateMixNonce);
                
                $.ajax({
                    url: herbalProfileData.ajaxUrl,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        submitBtn.prop("disabled", false).text(originalBtnText);
                        
                        if (response.success) {
                            alert(herbalProfileData.strings.updateSuccess);
                            $("#edit-mix-modal").hide();
                            location.reload();
                        } else {
                            alert(response.data.message || herbalProfileData.strings.error);
                        }
                    },
                    error: function() {
                        submitBtn.prop("disabled", false).text(originalBtnText);
                        alert(herbalProfileData.strings.error);
                    }
                });
            });
            
            // Handle publish form submission
            $(document).on("submit", "#publish-mix-form", function(e) {
                e.preventDefault();
                
                if (!validatePublishForm()) {
                    if (!$("#mix-name").val().trim()) {
                        alert(herbalProfileData.strings.nameRequired);
                        $("#mix-name").focus();
                        return false;
                    }
                    
                    if (!$("#mix-description").val().trim()) {
                        alert(herbalProfileData.strings.descriptionRequired);
                        $("#mix-description").focus();
                        return false;
                    }
                    
                    if (!$("#mix-image").val() && (!currentMixData || !currentMixData.mix_image)) {
                        alert(herbalProfileData.strings.imageRequired);
                        return false;
                    }
                    
                    return false;
                }
                
                const submitBtn = $("#publish-button");
                const originalBtnText = submitBtn.text();
                submitBtn.prop("disabled", true).text(herbalProfileData.strings.publishing);
                
                const formData = new FormData(this);
                formData.append("action", "publish_mix");
                formData.append("nonce", herbalProfileData.publishNonce);
                
                $.ajax({
                    url: herbalProfileData.ajaxUrl,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        submitBtn.prop("disabled", false).text(originalBtnText);
                        
                        if (response.success) {
                            alert(herbalProfileData.strings.publishSuccess);
                            
                            if (response.data && response.data.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                $("#publish-mix-modal").hide();
                                window.location.reload();
                            }
                        } else {
                            alert(response.data.message || herbalProfileData.strings.error);
                        }
                    },
                    error: function() {
                        submitBtn.prop("disabled", false).text(originalBtnText);
                        alert(herbalProfileData.strings.error);
                    }
                });
            });
            
            // === MODAL CLOSE FUNCTIONS ===
            
            $(document).on("click", ".close-modal, .cancel-modal, .edit-close, .edit-cancel", function() {
                $("#publish-mix-modal, #edit-mix-modal").hide();
            });
            
            $(document).on("click", "#edit-mix-modal, #publish-mix-modal", function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
            
            // === IMAGE PREVIEW FUNCTIONS ===
            
            function setEditImagePreview(imageUrl) {
                // Set hidden field value
                $("#edit-mix-image").val(imageUrl);
                
                // Show preview if element exists
                if ($("#edit-mix-image-preview").length) {
                    $("#edit-mix-image-preview").attr("src", imageUrl).show();
                }
                
                // Update button states
                $("#edit-mix-image_select_btn").text("' . __('Change Image', 'herbal-mix-creator2') . '");
                $("#edit-mix-image_remove_btn").show();
            }
            
            function setPublishImagePreview(imageUrl) {
                // Set hidden field value
                $("#mix-image").val(imageUrl);
                
                // Show preview if element exists
                if ($("#mix-image-preview").length) {
                    $("#mix-image-preview").attr("src", imageUrl).show();
                }
                
                // Update button states
                $("#mix-image_select_btn").text("' . __('Change Image', 'herbal-mix-creator2') . '");
                $("#mix-image_remove_btn").show();
                
                // Validate form after setting image
                validatePublishForm();
            }
            
            // Initialize on page load
            if ($("#publish-mix-modal").length) {
                validatePublishForm();
            }
        });
        ';
    }
    
    /**
     * Get enhanced modal CSS styles
     */
    private function get_enhanced_modal_styles() {
        return '
        /* Enhanced Modal Styles */
        .modal-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .modal-content.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .modal-content.loading::after {
            content: "Loading...";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .close-modal, .edit-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }
        
        .close-modal:hover, .edit-close:hover {
            color: #333;
        }
        
        .modal-content h3 {
            margin: 0 0 20px 0;
            color: #2c3e50;
            font-size: 24px;
        }
        
        /* Mix Summary Styling */
        .mix-summary {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .mix-recipe-preview h4 {
            color: #495057;
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        
        .ingredients-list {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            margin: 0;
            min-height: 100px;
        }
        
        .ingredients-list ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .ingredients-list li {
            padding: 5px 0;
            border-bottom: 1px solid #f1f3f4;
            color: #495057;
        }
        
        .ingredients-list li:last-child {
            border-bottom: none;
        }
        
        /* Pricing Display */
        .mix-pricing {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .price-item {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .price-label {
            font-weight: 600;
            color: #495057;
        }
        
        .price-value {
            font-weight: 700;
            color: #2c3e50;
        }
        
        /* Form Styling */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.15s ease-in-out;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.25);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        /* Image Upload Styling */
        .image-upload-section {
            border: 2px dashed #dee2e6;
            border-radius: 6px;
            padding: 20px;
            text-align: center;
            transition: border-color 0.15s ease-in-out;
        }
        
        .image-upload-section:hover {
            border-color: #007cba;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .image-upload-buttons {
            margin-top: 15px;
        }
        
        .image-upload-buttons .button {
            margin: 0 5px;
        }
        
        .field-hint {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        /* Form Actions */
        .form-actions {
            margin-top: 30px;
            text-align: right;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
        }
        
        .form-actions .button {
            margin-left: 10px;
        }
        
        /* Mix Actions Buttons */
        .mix-actions .button.buy-mix {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        .mix-actions .button.buy-mix:hover {
            background: #218838;
            border-color: #1e7e34;
        }
        
        .mix-actions .button.buy-mix:disabled {
            background: #6c757d;
            border-color: #6c757d;
            cursor: not-allowed;
        }
        
        /* Error States */
        .error-message {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .form-group.error input,
        .form-group.error textarea {
            border-color: #dc3545;
        }
        
        /* Success States */
        .form-group.success input,
        .form-group.success textarea {
            border-color: #28a745;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                padding: 20px;
                margin: 20px auto;
            }
            
            .mix-pricing {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                text-align: center;
            }
            
            .form-actions .button {
                display: block;
                width: 100%;
                margin: 10px 0 0 0;
            }
        }
        ';
    }
    
    /**
     * Display My Mixes tab content with enhanced functionality
     */
    /**
 * POPRAWIONA FUNKCJA: render_my_mixes_tab()
 * Dodaj tę funkcję do klasy HerbalMixUserProfileExtended (zastąp istniejącą)
 */
public function render_my_mixes_tab() {
    // NAJPIERW pobierz dane
    $user_id = get_current_user_id();
    global $wpdb;
    
    $table = $wpdb->prefix . 'herbal_mixes';
    $my_mixes = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table 
        WHERE user_id = %d 
        ORDER BY created_at DESC
    ", $user_id));
    
    // POTEM sprawdź czy template istnieje i przekaż mu zmienną
    $template_path = plugin_dir_path(dirname(__FILE__)) . 'includes/templates/user-profile-my-mixes.php';
    if (file_exists($template_path)) {
        // ✅ POPRAWKA: ustaw zmienną PRZED include
        include($template_path);
        return;
    }
    
    // Enhanced fallback implementation (bez zmian)
    echo '<div class="herbal-my-mixes-container">';
    echo '<h3>' . __('My Herbal Mixes', 'herbal-mix-creator2') . '</h3>';
    
    if (empty($my_mixes)) {
        echo '<div class="no-mixes-message">';
        echo '<h3>' . __('No Custom Mixes Yet', 'herbal-mix-creator2') . '</h3>';
        echo '<p>' . __('You haven\'t created any custom herbal mixes yet.', 'herbal-mix-creator2') . '</p>';
        echo '<p>' . __('Start creating your personalized blends today!', 'herbal-mix-creator2') . '</p>';
        $create_mix_url = get_permalink(get_page_by_path('herbal-mix-creator')) ?: home_url('/herbal-mix-creator/');
        echo '<a href="' . esc_url($create_mix_url) . '" class="create-mix-button">' . __('Create Your First Mix', 'herbal-mix-creator2') . '</a>';
        echo '</div>';
    } else {
        echo '<div class="mixes-table-wrapper">';
        echo '<table class="mixes-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Mix Name', 'herbal-mix-creator2') . '</th>';
        echo '<th>' . __('Status', 'herbal-mix-creator2') . '</th>';
        echo '<th>' . __('Created', 'herbal-mix-creator2') . '</th>';
        echo '<th>' . __('Likes', 'herbal-mix-creator2') . '</th>';
        echo '<th>' . __('Actions', 'herbal-mix-creator2') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($my_mixes as $mix) {
            echo '<tr>';
            
            // Mix Name & Description
            echo '<td>';
            echo '<div class="mix-name">' . esc_html($mix->mix_name) . '</div>';
            if ($mix->mix_description) {
                echo '<div class="mix-description">' . esc_html(wp_trim_words($mix->mix_description, 15)) . '</div>';
            }
            echo '</td>';
            
            // Status
            echo '<td>';
            echo '<span class="mix-status ' . esc_attr($mix->status) . '">' . ucfirst($mix->status) . '</span>';
            echo '</td>';
            
            // Created Date
            echo '<td>' . date('j M Y', strtotime($mix->created_at)) . '</td>';
            
            // Likes (only show for published)
            echo '<td>';
            if ($mix->status === 'published') {
                echo intval($mix->like_count);
            } else {
                echo '-';
            }
            echo '</td>';
            
            // Enhanced Actions
            echo '<td>';
            echo '<div class="mix-actions">';
            echo '<button class="button view-mix" data-mix-id="' . $mix->id . '">' . __('View', 'herbal-mix-creator2') . '</button>';
            echo '<button class="button buy-mix" data-mix-id="' . $mix->id . '">' . __('Buy', 'herbal-mix-creator2') . '</button>';
            
            // Show Edit and Publish buttons only for non-published mixes
            if ($mix->status !== 'published') {
                echo '<button class="button edit-mix" data-mix-id="' . $mix->id . '">' . __('Edit', 'herbal-mix-creator2') . '</button>';
                echo '<button class="button publish-mix" data-mix-id="' . $mix->id . '">' . __('Publish', 'herbal-mix-creator2') . '</button>';
                echo '<button class="button delete-mix" data-mix-id="' . $mix->id . '">' . __('Delete', 'herbal-mix-creator2') . '</button>';
            }
            
            echo '</div>';
            echo '</td>';
            
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    echo '</div>';
}
    
    /**
     * Display Favorite Mixes tab content
     */
    public function render_favorite_mixes_tab() {
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'includes/templates/user-profile-favorite-mixes.php';
        if (file_exists($template_path)) {
            include($template_path);
            return;
        }
        
        // Fallback implementation with enhanced functionality
        $user_id = get_current_user_id();
        global $wpdb;
        
        $table = $wpdb->prefix . 'herbal_mixes';
        $favorite_mixes = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table 
            WHERE status = 'published' 
            AND JSON_CONTAINS(liked_by, %s)
            ORDER BY created_at DESC
        ", json_encode(strval($user_id))));
        
        echo '<div class="herbal-favorite-mixes-container">';
        echo '<h3>' . __('My Favorite Mixes', 'herbal-mix-creator2') . '</h3>';
        
        if (empty($favorite_mixes)) {
            echo '<div class="no-mixes-message">';
            echo '<h3>' . __('No Favorite Mixes Yet', 'herbal-mix-creator2') . '</h3>';
            echo '<p>' . __('You haven\'t liked any mixes yet.', 'herbal-mix-creator2') . '</p>';
            echo '<p>' . __('Browse the community mixes to find ones you like!', 'herbal-mix-creator2') . '</p>';
            echo '</div>';
        } else {
            echo '<div class="mixes-table-wrapper">';
            echo '<table class="mixes-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Mix Name', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Author', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Likes', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Actions', 'herbal-mix-creator2') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($favorite_mixes as $mix) {
                $author = get_userdata($mix->user_id);
                
                echo '<tr>';
                echo '<td>';
                echo '<div class="mix-name">' . esc_html($mix->mix_name) . '</div>';
                if ($mix->mix_description) {
                    echo '<div class="mix-description">' . esc_html(wp_trim_words($mix->mix_description, 15)) . '</div>';
                }
                echo '</td>';
                echo '<td>' . ($author ? $author->display_name : 'Unknown') . '</td>';
                echo '<td>' . intval($mix->like_count) . '</td>';
                echo '<td>';
                echo '<div class="mix-actions">';
                echo '<button class="button view-mix" data-mix-id="' . $mix->id . '">' . __('View', 'herbal-mix-creator2') . '</button>';
                echo '<button class="button buy-mix" data-mix-id="' . $mix->id . '">' . __('Buy', 'herbal-mix-creator2') . '</button>';
                echo '<button class="button remove-favorite" data-mix-id="' . $mix->id . '">' . __('Remove', 'herbal-mix-creator2') . '</button>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    // === ENHANCED AJAX HANDLERS ===
    
    /**
     * AJAX: Get mix details with enhanced data
     */
    public function ajax_get_mix_details() {
        if (!wp_verify_nonce($_GET['nonce'], 'get_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_GET['mix_id']);
        
        if (!$mix_id) {
            wp_send_json_error(array('message' => 'Invalid mix ID.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $mix_id
        ), ARRAY_A);
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found.'));
        }
        
        // Parse mix data
        $mix['mix_data_parsed'] = json_decode($mix['mix_data'], true);
        
        // Get author info
        $author = get_userdata($mix['user_id']);
        $mix['author_name'] = $author ? $author->display_name : 'Unknown';
        
        wp_send_json_success($mix);
    }
    
    /**
     * AJAX: Get mix recipe and pricing data with correct column names
     */
    public function ajax_get_mix_recipe_and_pricing() {
        if (!wp_verify_nonce($_GET['nonce'], 'get_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_GET['mix_id']);
        
        if (!$mix_id) {
            wp_send_json_error(array('message' => 'Invalid mix ID.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $mix_id
        ), ARRAY_A);
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found.'));
        }
        
        $mix_data = json_decode($mix['mix_data'], true);
        
        // Calculate pricing using CORRECT column names from database
        $total_price = 0;
        $total_points = 0;
        $points_earned = 0;
        $ingredients_html = '';
        
        if (isset($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
            $ingredients_table = $wpdb->prefix . 'herbal_ingredients';
            
            $ingredients_html = '<ul class="ingredients-list">';
            foreach ($mix_data['ingredients'] as $ingredient) {
                if (isset($ingredient['name'])) {
                    $weight = isset($ingredient['weight']) ? floatval($ingredient['weight']) : 0;
                    $ingredients_html .= '<li>' . esc_html($ingredient['name']);
                    if ($weight > 0) {
                        $ingredients_html .= ' - ' . $weight . 'g';
                    }
                    $ingredients_html .= '</li>';
                    
                    // Get ingredient pricing using CORRECT column names
                    if (isset($ingredient['id'])) {
                        $ingredient_data = $wpdb->get_row($wpdb->prepare(
                            "SELECT price, price_point, point_earned FROM $ingredients_table WHERE id = %d",
                            intval($ingredient['id'])
                        ));
                        
                        if ($ingredient_data && $weight > 0) {
                            $weight_ratio = $weight / 100;
                            $total_price += floatval($ingredient_data->price) * $weight_ratio;
                            $total_points += floatval($ingredient_data->price_point) * $weight_ratio;
                            $points_earned += floatval($ingredient_data->point_earned) * $weight_ratio;
                        }
                    }
                }
            }
            $ingredients_html .= '</ul>';
        }
        
        // Add packaging costs using CORRECT column names
        if (isset($mix_data['packaging_id'])) {
            $packaging_table = $wpdb->prefix . 'herbal_packaging';
            $packaging = $wpdb->get_row($wpdb->prepare(
                "SELECT price, price_point, point_earned FROM $packaging_table WHERE id = %d",
                intval($mix_data['packaging_id'])
            ));
            
            if ($packaging) {
                $total_price += floatval($packaging->price);
                $total_points += floatval($packaging->price_point);
                $points_earned += floatval($packaging->point_earned);
            }
        }
        
        wp_send_json_success(array(
            'ingredients_html' => $ingredients_html,
            'total_price' => number_format($total_price, 2),
            'total_points' => number_format($total_points, 0),
            'points_earned' => number_format($points_earned, 0)
        ));
    }
    
    /**
     * AJAX: Update mix details
     */
    public function ajax_update_mix_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'update_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $mix_name = sanitize_text_field($_POST['mix_name']);
        $mix_description = sanitize_textarea_field($_POST['mix_description']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id || empty($mix_name)) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Handle image upload if provided
        $update_data = array(
            'mix_name' => $mix_name,
            'mix_description' => $mix_description
        );
        
        if (isset($_FILES['mix_image']) && $_FILES['mix_image']['error'] == 0) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($_FILES['mix_image'], $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                $update_data['mix_image'] = $movefile['url'];
            }
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array(
                'id' => $mix_id,
                'user_id' => $user_id
            ),
            array_fill(0, count($update_data), '%s'),
            array('%d', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => 'Mix updated successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Database error.'));
        }
    }
    
    /**
     * AJAX: Publish mix
     */
    public function ajax_publish_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'publish_mix')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        // Get form data
        $mix_name = sanitize_text_field($_POST['mix_name']);
        $mix_description = sanitize_textarea_field($_POST['mix_description']);
        
        if (empty($mix_name) || empty($mix_description)) {
            wp_send_json_error(array('message' => 'Mix name and description are required for publishing.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Check if mix exists and belongs to user
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $mix_id, $user_id
        ), ARRAY_A);
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found or access denied.'));
        }
        
        if ($mix['status'] === 'published') {
            wp_send_json_error(array('message' => 'Mix is already published.'));
        }
        
        // Handle image upload if provided
        $mix_image = $mix['mix_image'];
        if (isset($_FILES['mix_image']) && $_FILES['mix_image']['error'] == 0) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($_FILES['mix_image'], $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                $mix_image = $movefile['url'];
            }
        }
        
        // Check if image is required and present
        if (empty($mix_image)) {
            wp_send_json_error(array('message' => 'An image is required for publishing.'));
        }
        
        // Update mix for publishing
        $result = $wpdb->update(
            $table,
            array(
                'status' => 'published',
                'mix_name' => $mix_name,
                'mix_description' => $mix_description,
                'mix_image' => $mix_image
            ),
            array('id' => $mix_id, 'user_id' => $user_id),
            array('%s', '%s', '%s', '%s'),
            array('%d', '%d')
        );
        
        if ($result !== false) {
            // Create WooCommerce product for the published mix
            if (class_exists('Herbal_Mix_Actions')) {
                $actions = new Herbal_Mix_Actions();
                if (method_exists($actions, 'create_public_product')) {
                    $updated_mix = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table WHERE id = %d",
                        $mix_id
                    ), ARRAY_A);
                    
                    $product_id = $actions->create_public_product((object)$updated_mix);
                    
                    if ($product_id) {
                        $wpdb->update(
                            $table,
                            array('base_product_id' => $product_id),
                            array('id' => $mix_id),
                            array('%d'),
                            array('%d')
                        );
                    }
                }
            }
            
            // Award points for publishing
            if (class_exists('Herbal_Mix_Database')) {
                Herbal_Mix_Database::add_user_points(
                    $user_id,
                    50,
                    'mix_published',
                    $mix_id,
                    sprintf(__('Points earned for publishing mix: %s', 'herbal-mix-creator2'), $mix_name)
                );
            }
            
            wp_send_json_success(array(
                'message' => __('Mix published successfully! You earned 50 reward points.', 'herbal-mix-creator2'),
                'redirect' => get_permalink($product_id ?? null)
            ));
        } else {
            wp_send_json_error(array('message' => 'Database error.'));
        }
    }
    
    /**
     * AJAX: Buy mix - creates virtual product and adds to cart
     */
    public function ajax_buy_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'buy_mix')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $mix_id
        ), ARRAY_A);
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found.'));
        }
        
        // Use existing actions class to create virtual product and add to cart
        if (class_exists('Herbal_Mix_Actions')) {
            $actions = new Herbal_Mix_Actions();
            
            // Create virtual product from mix
            $product_id = $actions->generate_product_from_mix((object)$mix, true);
            
            if ($product_id) {
                // Add to cart
                if (class_exists('WC') && WC()->cart) {
                    $cart_item_key = WC()->cart->add_to_cart($product_id, 1);
                    
                    if ($cart_item_key) {
                        wp_send_json_success(array(
                            'message' => __('Mix added to cart successfully!', 'herbal-mix-creator2'),
                            'cart_url' => wc_get_cart_url(),
                            'product_id' => $product_id
                        ));
                    } else {
                        wp_send_json_error(array('message' => 'Failed to add mix to cart.'));
                    }
                } else {
                    wp_send_json_error(array('message' => 'WooCommerce cart not available.'));
                }
            } else {
                wp_send_json_error(array('message' => 'Failed to create product from mix.'));
            }
        } else {
            wp_send_json_error(array('message' => 'Actions class not available.'));
        }
    }
    
    /**
     * AJAX: Delete mix
     */
    public function ajax_delete_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'delete_mix')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status, user_id FROM $table WHERE id = %d AND user_id = %d",
            $mix_id, $user_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found or access denied.'));
        }
        
        if ($mix->status === 'published') {
            wp_send_json_error(array('message' => 'Published mixes cannot be deleted.'));
        }
        
        $result = $wpdb->delete(
            $table,
            array('id' => $mix_id, 'user_id' => $user_id),
            array('%d', '%d')
        );
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Mix deleted successfully.', 'herbal-mix-creator2')
            ));
        } else {
            wp_send_json_error(array('message' => 'Database error.'));
        }
    }
    
    /**
     * AJAX: View mix
     */
    public function ajax_view_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'get_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        
        if (!$mix_id) {
            wp_send_json_error(array('message' => 'Invalid mix ID.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $mix_id
        ), ARRAY_A);
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found.'));
        }
        
        $author = get_userdata($mix['user_id']);
        $mix['author_name'] = $author ? $author->display_name : 'Unknown';
        
        // Check if mix has a product page
        if ($mix['base_product_id'] && $mix['status'] === 'published') {
            $product_url = get_permalink($mix['base_product_id']);
            if ($product_url) {
                wp_send_json_success(array(
                    'view_url' => $product_url,
                    'message' => 'Redirecting to product page.'
                ));
                return;
            }
        }
        
        wp_send_json_success(array(
            'mix_name' => $mix['mix_name'],
            'mix_description' => $mix['mix_description'],
            'status' => $mix['status'],
            'created_at' => date('F j, Y', strtotime($mix['created_at'])),
            'author_name' => $mix['author_name'],
            'like_count' => $mix['like_count'],
            'message' => 'Mix details loaded successfully.'
        ));
    }
    
    /**
     * AJAX: Remove favorite mix
     */
    public function ajax_remove_favorite_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'get_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT liked_by, like_count FROM $table WHERE id = %d",
            $mix_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found.'));
        }
        
        $liked_by = json_decode($mix->liked_by, true) ?: array();
        $user_key = array_search(strval($user_id), $liked_by);
        
        if ($user_key === false) {
            wp_send_json_error(array('message' => 'Mix is not in your favorites.'));
        }
        
        unset($liked_by[$user_key]);
        $liked_by = array_values($liked_by);
        
        $new_like_count = max(0, intval($mix->like_count) - 1);
        
        $result = $wpdb->update(
            $table,
            array(
                'liked_by' => json_encode($liked_by),
                'like_count' => $new_like_count
            ),
            array('id' => $mix_id),
            array('%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Removed from favorites.', 'herbal-mix-creator2')
            ));
        } else {
            wp_send_json_error(array('message' => 'Database error.'));
        }
    }
    
    /**
     * AJAX: Upload mix image
     */
    public function ajax_upload_mix_image() {
        if (!wp_verify_nonce($_POST['nonce'], 'upload_mix_image')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $uploaded_file = $_FILES['mix_image'];
        $upload_overrides = array('test_form' => false);
        
        $movefile = wp_handle_upload($uploaded_file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            $mix_id = intval($_POST['mix_id']);
            $user_id = get_current_user_id();
            
            if ($mix_id && $user_id) {
                global $wpdb;
                $table = $wpdb->prefix . 'herbal_mixes';
                
                $wpdb->update(
                    $table,
                    array('mix_image' => $movefile['url']),
                    array('id' => $mix_id, 'user_id' => $user_id),
                    array('%s'),
                    array('%d', '%d')
                );
            }
            
            wp_send_json_success(array(
                'image_url' => $movefile['url'],
                'message' => 'Image uploaded successfully!'
            ));
        } else {
            wp_send_json_error(array('message' => $movefile['error']));
        }
    }
    
    // === AVATAR AND PROFILE FIELDS (unchanged) ===
    
    public function add_avatar_to_account_form() {
        $user_id = get_current_user_id();
        $custom_avatar = get_user_meta($user_id, 'custom_avatar', true);
        
        ?>
        <fieldset>
            <legend><?php _e('Profile Picture', 'herbal-mix-creator2'); ?></legend>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="custom_avatar"><?php _e('Upload Avatar', 'herbal-mix-creator2'); ?></label>
                <input type="file" class="woocommerce-Input woocommerce-Input--file input-file" name="custom_avatar" id="custom_avatar" accept="image/*" />
                <?php if ($custom_avatar): ?>
                    <br><small><?php _e('Current avatar:', 'herbal-mix-creator2'); ?> <img src="<?php echo esc_url($custom_avatar); ?>" style="width: 32px; height: 32px; border-radius: 50%; vertical-align: middle;" /></small>
                <?php endif; ?>
            </p>
        </fieldset>
        <?php
    }
    
    public function save_extra_account_details($user_id) {
        if (isset($_FILES['custom_avatar']) && $_FILES['custom_avatar']['error'] == 0) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($_FILES['custom_avatar'], $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                update_user_meta($user_id, 'custom_avatar', $movefile['url']);
            }
        }
    }
    
    public function custom_avatar_url($url, $id_or_email, $args) {
        $user_id = 0;
        
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        
        if ($user_id) {
            $custom_avatar = get_user_meta($user_id, 'custom_avatar', true);
            if ($custom_avatar) {
                return $custom_avatar;
            }
        }
        
        return $url;
    }
}

// Initialize the class
new HerbalMixUserProfileExtended();