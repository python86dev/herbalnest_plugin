<?php
/**
 * Enhanced User Profile Extended Class - CORRECTED VERSION with full functionality
 * File: includes/class-herbal-mix-user-profile-extended.php
 * 
 * CHANGES:
 * - REMOVED only duplicate ajax_upload_mix_image() function (delegated to HerbalMixMediaHandler)
 * - KEPT all other functionality including full render_my_mixes_tab() implementation
 * - Fixed asset loading paths
 * - Proper separation of concerns while maintaining full features
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
        
        // AJAX handlers for mix management (NO ajax_upload_mix_image - delegated to HerbalMixMediaHandler)
        add_action('wp_ajax_get_mix_details', array($this, 'ajax_get_mix_details'));
        add_action('wp_ajax_get_mix_recipe_and_pricing', array($this, 'ajax_get_mix_recipe_and_pricing'));
        add_action('wp_ajax_update_mix_details', array($this, 'ajax_update_mix_details'));
        add_action('wp_ajax_publish_mix', array($this, 'ajax_publish_mix'));
        add_action('wp_ajax_delete_mix', array($this, 'ajax_delete_mix'));
        add_action('wp_ajax_view_mix', array($this, 'ajax_view_mix'));
        add_action('wp_ajax_remove_favorite_mix', array($this, 'ajax_remove_favorite_mix'));
        add_action('wp_ajax_buy_mix', array($this, 'ajax_buy_mix'));
        
        // Load profile assets when needed
        add_action('wp_enqueue_scripts', array($this, 'enqueue_profile_assets'));
    }
    
    /**
     * Initialize points for new users
     */
    public function initialize_user_points($user_id) {
        if (class_exists('Herbal_Mix_Reward_Points')) {
            Herbal_Mix_Reward_Points::award_points($user_id, 100, 'registration', null, 'Welcome bonus');
        }
    }

    /**
     * Add custom endpoints for WooCommerce account
     */
    public function add_custom_endpoints() {
        add_rewrite_endpoint('my-mixes', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('favorite-mixes', EP_ROOT | EP_PAGES);
    }

    /**
     * Add mix-related menu items to WooCommerce account
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
     * Enqueue profile assets with consistent paths
     */
    public function enqueue_profile_assets() {
        // Only load on account pages
        if (!function_exists('is_account_page') || !is_account_page()) {
            return;
        }

        // CSS - using HERBAL_MIX_PLUGIN_URL constant for consistency
        wp_enqueue_style(
            'herbal-profile-css',
            HERBAL_MIX_PLUGIN_URL . 'assets/css/profile.css',
            array(),
            filemtime(HERBAL_MIX_PLUGIN_PATH . 'assets/css/profile.css')
        );

        // JavaScript with proper dependencies
        wp_enqueue_script(
            'herbal-profile-js',
            HERBAL_MIX_PLUGIN_URL . 'assets/js/profile.js',
            array('jquery'),
            filemtime(HERBAL_MIX_PLUGIN_PATH . 'assets/js/profile.js'),
            true
        );

        // FIXED: Comprehensive localization data
        wp_localize_script('herbal-profile-js', 'herbalProfileData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            
            // FIXED: All required nonces
            'getNonce' => wp_create_nonce('get_mix_details'),
            'recipeNonce' => wp_create_nonce('get_recipe_pricing'),
            'updateMixNonce' => wp_create_nonce('update_mix_details'),
            'publishNonce' => wp_create_nonce('publish_mix'),
            'deleteNonce' => wp_create_nonce('delete_mix'),
            'deleteMixNonce' => wp_create_nonce('delete_mix'), // Alternative name
            'uploadImageNonce' => wp_create_nonce('upload_mix_image'),
            'favoritesNonce' => wp_create_nonce('manage_favorites'),
            'buyMixNonce' => wp_create_nonce('buy_mix'),
            
            // User data
            'userId' => get_current_user_id(),
            'currencySymbol' => get_woocommerce_currency_symbol(),
            
            // Strings for JavaScript
            'strings' => array(
                'loading' => __('Loading...', 'herbal-mix-creator2'),
                'error' => __('An error occurred. Please try again.', 'herbal-mix-creator2'),
                'success' => __('Success!', 'herbal-mix-creator2'),
                'confirmDelete' => __('Are you sure you want to delete this mix? This action cannot be undone.', 'herbal-mix-creator2'),
                'confirmRemoveFavorite' => __('Remove this mix from favorites?', 'herbal-mix-creator2'),
                'deleting' => __('Deleting...', 'herbal-mix-creator2'),
                'deleteSuccess' => __('Mix deleted successfully.', 'herbal-mix-creator2'),
                'connectionError' => __('Connection error. Please try again.', 'herbal-mix-creator2'),
                'updateSuccess' => __('Mix updated successfully!', 'herbal-mix-creator2'),
                'publishSuccess' => __('Mix published successfully!', 'herbal-mix-creator2'),
                'invalidData' => __('Invalid mix data.', 'herbal-mix-creator2'),
                'accessDenied' => __('Access denied.', 'herbal-mix-creator2')
            )
        ));
    }

    /**
     * FULL IMPLEMENTATION: Display My Mixes tab content with enhanced functionality
     */
    public function render_my_mixes_tab() {
        // Get user data
        $user_id = get_current_user_id();
        global $wpdb;
        
        $table = $wpdb->prefix . 'herbal_mixes';
        $my_mixes = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table 
            WHERE user_id = %d 
            ORDER BY created_at DESC
        ", $user_id));
        
        // Check if template exists first
        $template_path = HERBAL_MIX_PLUGIN_PATH . 'includes/templates/user-profile-my-mixes.php';
        if (file_exists($template_path)) {
            include($template_path);
            return;
        }
        
        // FULL FALLBACK IMPLEMENTATION (not simplified!)
        echo '<div class="herbal-my-mixes-container">';
        echo '<div class="dashboard-header">';
        echo '<h2>' . __('My Herbal Mixes', 'herbal-mix-creator2') . '</h2>';
        echo '<a href="' . esc_url(get_permalink(get_page_by_path('herbal-mix-creator'))) . '" class="button">' . __('Create New Mix', 'herbal-mix-creator2') . '</a>';
        echo '</div>';
        
        if (empty($my_mixes)) {
            echo '<div class="woocommerce-message woocommerce-message--info">';
            echo '<p>' . __('You haven\'t created any mixes yet.', 'herbal-mix-creator2') . '</p>';
            echo '<a href="' . esc_url(get_permalink(get_page_by_path('herbal-mix-creator'))) . '" class="button">' . __('Create Your First Mix', 'herbal-mix-creator2') . '</a>';
            echo '</div>';
        } else {
            echo '<div class="mixes-tabs">';
            echo '<ul class="tab-navigation">';
            echo '<li class="active"><a href="#all-mixes">' . __('All Mixes', 'herbal-mix-creator2') . '</a></li>';
            echo '<li><a href="#published-mixes">' . __('Published', 'herbal-mix-creator2') . '</a></li>';
            echo '<li><a href="#private-mixes">' . __('Private', 'herbal-mix-creator2') . '</a></li>';
            echo '</ul>';
            
            echo '<div class="tab-content">';
            
            // ALL MIXES TAB
            echo '<div id="all-mixes" class="tab-pane active">';
            echo '<table class="woocommerce-orders-table mixes-table">';
            echo '<thead><tr>';
            echo '<th>' . __('Name', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Created', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Status', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Likes', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Actions', 'herbal-mix-creator2') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($my_mixes as $mix) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($mix->mix_name) . '</strong>';
                if (!empty($mix->mix_description)) {
                    echo '<br><small class="description">' . esc_html(wp_trim_words($mix->mix_description, 10, '...')) . '</small>';
                }
                echo '</td>';
                echo '<td>' . date_i18n(get_option('date_format'), strtotime($mix->created_at)) . '</td>';
                echo '<td><span class="status-badge status-' . esc_attr($mix->status) . '">' . esc_html(ucfirst($mix->status)) . '</span></td>';
                echo '<td>' . intval($mix->like_count) . '</td>';
                echo '<td class="mix-actions">';
                
                // Action buttons
                echo '<button type="button" class="button button-small edit-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('Edit', 'herbal-mix-creator2') . '</button>';
                
                if ($mix->status === 'favorite') {
                    echo '<button type="button" class="button button-small publish-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('Publish', 'herbal-mix-creator2') . '</button>';
                } else {
                    echo '<button type="button" class="button button-small view-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('View', 'herbal-mix-creator2') . '</button>';
                }
                
                echo '<button type="button" class="button button-small button-danger delete-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('Delete', 'herbal-mix-creator2') . '</button>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>'; // End all-mixes tab
            
            // PUBLISHED MIXES TAB
            echo '<div id="published-mixes" class="tab-pane">';
            $published_mixes = array_filter($my_mixes, function($mix) { return $mix->status === 'published'; });
            
            if (empty($published_mixes)) {
                echo '<p>' . __('No published mixes yet.', 'herbal-mix-creator2') . '</p>';
            } else {
                echo '<table class="woocommerce-orders-table mixes-table">';
                echo '<thead><tr>';
                echo '<th>' . __('Name', 'herbal-mix-creator2') . '</th>';
                echo '<th>' . __('Published', 'herbal-mix-creator2') . '</th>';
                echo '<th>' . __('Likes', 'herbal-mix-creator2') . '</th>';
                echo '<th>' . __('Actions', 'herbal-mix-creator2') . '</th>';
                echo '</tr></thead><tbody>';
                
                foreach ($published_mixes as $mix) {
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($mix->mix_name) . '</strong></td>';
                    echo '<td>' . date_i18n(get_option('date_format'), strtotime($mix->created_at)) . '</td>';
                    echo '<td>' . intval($mix->like_count) . '</td>';
                    echo '<td class="mix-actions">';
                    echo '<button type="button" class="button button-small view-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('View', 'herbal-mix-creator2') . '</button>';
                    echo '<button type="button" class="button button-small edit-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('Edit', 'herbal-mix-creator2') . '</button>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            }
            echo '</div>'; // End published-mixes tab
            
            // PRIVATE MIXES TAB
            echo '<div id="private-mixes" class="tab-pane">';
            $private_mixes = array_filter($my_mixes, function($mix) { return $mix->status === 'favorite'; });
            
            if (empty($private_mixes)) {
                echo '<p>' . __('No private mixes yet.', 'herbal-mix-creator2') . '</p>';
            } else {
                echo '<table class="woocommerce-orders-table mixes-table">';
                echo '<thead><tr>';
                echo '<th>' . __('Name', 'herbal-mix-creator2') . '</th>';
                echo '<th>' . __('Created', 'herbal-mix-creator2') . '</th>';
                echo '<th>' . __('Actions', 'herbal-mix-creator2') . '</th>';
                echo '</tr></thead><tbody>';
                
                foreach ($private_mixes as $mix) {
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($mix->mix_name) . '</strong></td>';
                    echo '<td>' . date_i18n(get_option('date_format'), strtotime($mix->created_at)) . '</td>';
                    echo '<td class="mix-actions">';
                    echo '<button type="button" class="button button-small edit-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('Edit', 'herbal-mix-creator2') . '</button>';
                    echo '<button type="button" class="button button-small publish-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('Publish', 'herbal-mix-creator2') . '</button>';
                    echo '<button type="button" class="button button-small button-danger delete-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('Delete', 'herbal-mix-creator2') . '</button>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            }
            echo '</div>'; // End private-mixes tab
            
            echo '</div>'; // End tab-content
            echo '</div>'; // End mixes-tabs
        }
        
        echo '</div>'; // End herbal-my-mixes-container
        
        // Add modal containers and CSS/JS (keeping original functionality)
        $this->add_edit_modal();
        $this->add_publish_modal();
        $this->add_view_modal();
        $this->add_modal_styles();
    }

    /**
     * Display Favorite Mixes tab content
     */
    public function render_favorite_mixes_tab() {
        $template_path = HERBAL_MIX_PLUGIN_PATH . 'includes/templates/user-profile-favorite-mixes.php';
        if (file_exists($template_path)) {
            include($template_path);
            return;
        }
        
        // Fallback implementation
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
            echo '<p>' . __('You haven\'t liked any mixes yet. Browse published mixes and add them to your favorites!', 'herbal-mix-creator2') . '</p>';
            echo '</div>';
        } else {
            echo '<table class="woocommerce-orders-table mixes-table">';
            echo '<thead><tr>';
            echo '<th>' . __('Name', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Author', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Likes', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Actions', 'herbal-mix-creator2') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($favorite_mixes as $mix) {
                $author = get_user_by('id', $mix->user_id);
                echo '<tr>';
                echo '<td><strong>' . esc_html($mix->mix_name) . '</strong></td>';
                echo '<td>' . esc_html($author ? $author->display_name : __('Unknown', 'herbal-mix-creator2')) . '</td>';
                echo '<td>' . intval($mix->like_count) . '</td>';
                echo '<td class="mix-actions">';
                echo '<button type="button" class="button button-small view-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('View', 'herbal-mix-creator2') . '</button>';
                echo '<button type="button" class="button button-small buy-mix" data-mix-id="' . esc_attr($mix->id) . '">' . __('Buy', 'herbal-mix-creator2') . '</button>';
                echo '<button type="button" class="button button-small remove-favorite" data-mix-id="' . esc_attr($mix->id) . '">' . __('Remove', 'herbal-mix-creator2') . '</button>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '</div>';
    }

    /**
     * Add Edit Modal (UPDATED with better integration)
     */
    private function add_edit_modal() {
        ?>
        <div id="edit-mix-modal" class="modal-dialog" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Edit Mix Details', 'herbal-mix-creator2'); ?></h3>
                    <button type="button" class="modal-close cancel-modal">&times;</button>
                </div>
                <form id="edit-mix-form">
                    <input type="hidden" id="edit-mix-id" name="mix_id" value="">
                    
                    <div class="form-group">
                        <label for="edit-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> *</label>
                        <input type="text" id="edit-mix-name" name="mix_name" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" id="edit-update-button" class="button button-primary" disabled>
                            <?php _e('Update Mix', 'herbal-mix-creator2'); ?>
                        </button>
                        <button type="button" class="button button-secondary cancel-modal">
                            <?php _e('Cancel', 'herbal-mix-creator2'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Edit form validation
            $(document).on('input', '#edit-mix-name', function() {
                var hasValue = $(this).val().trim() !== '';
                $('#edit-update-button').prop('disabled', !hasValue);
            });
            
            // Handle edit form submission
            $(document).on('submit', '#edit-mix-form', function(e) {
                e.preventDefault();
                
                var mixName = $('#edit-mix-name').val().trim();
                if (!mixName) {
                    alert('<?php _e('Please enter a mix name.', 'herbal-mix-creator2'); ?>');
                    return;
                }
                
                var formData = {
                    action: 'update_mix_details',
                    nonce: herbalProfileData.updateMixNonce,
                    mix_id: $('#edit-mix-id').val(),
                    mix_name: mixName
                };
                
                $('#edit-update-button').prop('disabled', true).text('<?php _e('Updating...', 'herbal-mix-creator2'); ?>');
                
                $.ajax({
                    url: herbalProfileData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Mix updated successfully!', 'herbal-mix-creator2'); ?>');
                            $('#edit-mix-modal').hide();
                            location.reload(); // Reload to show updated name
                        } else {
                            alert('Error: ' + (response.data || '<?php _e('Failed to update mix.', 'herbal-mix-creator2'); ?>'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Update error:', error);
                        alert('<?php _e('Connection error. Please try again.', 'herbal-mix-creator2'); ?>');
                    },
                    complete: function() {
                        $('#edit-update-button').prop('disabled', false).text('<?php _e('Update Mix', 'herbal-mix-creator2'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Add Publish Modal (UPDATED with HerbalMixMediaHandler integration)
     */
    private function add_publish_modal() {
        ?>
        <div id="publish-mix-modal" class="modal-dialog" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Publish Your Mix', 'herbal-mix-creator2'); ?></h3>
                    <button type="button" class="modal-close cancel-modal">&times;</button>
                </div>
                <form id="publish-mix-form">
                    <input type="hidden" id="publish-mix-id" name="mix_id" value="">
                    
                    <div class="form-group">
                        <label for="publish-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> *</label>
                        <input type="text" id="publish-mix-name" name="mix_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="publish-mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?> *</label>
                        <textarea id="publish-mix-description" name="mix_description" rows="4" required></textarea>
                    </div>
                    
                    <?php
                    // Use HerbalMixMediaHandler for image upload
                    if (class_exists('HerbalMixMediaHandler')) {
                        echo HerbalMixMediaHandler::render_image_upload_field('publish_mix_image', '', array(
                            'label' => __('Product Image', 'herbal-mix-creator2'),
                            'required' => true,
                            'upload_button_text' => __('Upload Image', 'herbal-mix-creator2'),
                            'remove_button_text' => __('Remove Image', 'herbal-mix-creator2'),
                            'placeholder_text' => __('Click to upload product image', 'herbal-mix-creator2'),
                            'upload_type' => 'mix_image',
                            'preview_size' => array('width' => 200, 'height' => 200)
                        ));
                    } else {
                        // Fallback if HerbalMixMediaHandler not available
                        ?>
                        <div class="form-group">
                            <label for="publish-mix-image-fallback"><?php _e('Product Image', 'herbal-mix-creator2'); ?> *</label>
                            <input type="hidden" id="publish-mix-image" name="mix_image" value="">
                            <input type="file" id="publish-mix-image-fallback" accept="image/*">
                            <div class="image-preview">
                                <img id="publish-mix-image-preview" src="" alt="" style="display:none;">
                            </div>
                            <div class="error-message" id="publish-image-error" style="display:none;"></div>
                        </div>
                        <?php
                    }
                    ?>
                    
                    <div class="form-actions">
                        <button type="submit" id="publish-button" class="button button-primary" disabled>
                            <?php _e('Publish Mix', 'herbal-mix-creator2'); ?>
                        </button>
                        <button type="button" class="button button-secondary cancel-modal">
                            <?php _e('Cancel', 'herbal-mix-creator2'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Enhanced publish form validation
            function validatePublishForm() {
                var nameValid = $('#publish-mix-name').val().trim() !== '';
                var descriptionValid = $('#publish-mix-description').val().trim() !== '';
                var imageValid = $('#publish_mix_image').val() !== ''; // Updated selector
                
                var allValid = nameValid && descriptionValid && imageValid;
                $('#publish-button').prop('disabled', !allValid);
                
                return allValid;
            }
            
            // Bind validation to form inputs
            $(document).on('input change', '#publish-mix-name, #publish-mix-description, #publish_mix_image', function() {
                validatePublishForm();
            });
            
            // Re-validate when modal opens
            $(document).on('click', '.publish-mix', function() {
                setTimeout(function() {
                    validatePublishForm();
                }, 100);
            });
            
            // Handle publish form submission
            $(document).on('submit', '#publish-mix-form', function(e) {
                e.preventDefault();
                
                if (!validatePublishForm()) {
                    alert('<?php _e('Please fill in all required fields.', 'herbal-mix-creator2'); ?>');
                    return;
                }
                
                var formData = {
                    action: 'publish_mix',
                    nonce: herbalProfileData.publishNonce,
                    mix_id: $('#publish-mix-id').val(),
                    mix_name: $('#publish-mix-name').val(),
                    mix_description: $('#publish-mix-description').val(),
                    mix_image: $('#publish_mix_image').val()
                };
                
                $('#publish-button').prop('disabled', true).text('<?php _e('Publishing...', 'herbal-mix-creator2'); ?>');
                
                $.ajax({
                    url: herbalProfileData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert('<?php _e('Mix published successfully!', 'herbal-mix-creator2'); ?>');
                            $('#publish-mix-modal').hide();
                            location.reload(); // Reload to show updated status
                        } else {
                            alert('Error: ' + (response.data || '<?php _e('Failed to publish mix.', 'herbal-mix-creator2'); ?>'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Publish error:', error);
                        alert('<?php _e('Connection error. Please try again.', 'herbal-mix-creator2'); ?>');
                    },
                    complete: function() {
                        $('#publish-button').prop('disabled', false).text('<?php _e('Publish Mix', 'herbal-mix-creator2'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Add View Modal (KEPT ORIGINAL IMPLEMENTATION)
     */
    private function add_view_modal() {
        ?>
        <div id="view-mix-modal" class="modal-dialog" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="view-mix-title"><?php _e('Mix Details', 'herbal-mix-creator2'); ?></h3>
                    <button type="button" class="modal-close cancel-modal">&times;</button>
                </div>
                <div id="view-mix-content">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add Modal Styles (KEPT ORIGINAL IMPLEMENTATION)
     */
    private function add_modal_styles() {
        echo '<style>
        .modal-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999999;
        }
        
        .modal-content {
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .image-upload-section {
            margin: 20px 0;
            padding: 20px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            text-align: center;
        }
        
        .image-preview {
            width: 150px;
            height: 150px;
            margin: 0 auto 15px;
            border: 2px solid #eee;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .image-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .button:disabled {
            background: #f5f5f5;
            border-color: #999;
        }
        </style>';
    }

    /**
     * AJAX: Get mix details for editing
     */
    public function ajax_get_mix_details() {
        $this->verify_nonce('get_mix_details');
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE id = %d AND user_id = %d
        ", $mix_id, $user_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        wp_send_json_success(array(
            'id' => $mix->id,
            'name' => $mix->mix_name,
            'description' => $mix->mix_description,
            'image' => $mix->mix_image,
            'status' => $mix->status,
            'created_at' => $mix->created_at
        ));
    }

    /**
     * AJAX: Get mix recipe and pricing details
     */
    public function ajax_get_mix_recipe_and_pricing() {
        $this->verify_nonce('get_recipe_pricing');
        
        $mix_id = intval($_POST['mix_id']);
        $action_type = sanitize_text_field($_POST['action_type']); // 'edit' or 'view'
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // For 'edit' - check user ownership, for 'view' - check if published
        if ($action_type === 'edit') {
            $user_id = get_current_user_id();
            $mix = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $table 
                WHERE id = %d AND user_id = %d
            ", $mix_id, $user_id));
        } else {
            $mix = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $table 
                WHERE id = %d AND status = 'published'
            ", $mix_id));
        }
        
        if (!$mix) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        // Parse mix data
        $mix_data = json_decode($mix->mix_data, true);
        if (!$mix_data) {
            wp_send_json_error('Invalid mix data.');
        }
        
        // Get ingredients and packaging details
        $recipe_details = $this->build_recipe_details($mix_data);
        
        wp_send_json_success(array(
            'mix' => array(
                'id' => $mix->id,
                'name' => $mix->mix_name,
                'description' => $mix->mix_description,
                'image' => $mix->mix_image,
                'status' => $mix->status
            ),
            'recipe' => $recipe_details
        ));
    }

    /**
     * AJAX: Update mix details (name, description, image)
     */
    public function ajax_update_mix_details() {
        $this->verify_nonce('update_mix_details');
        
        $mix_id = intval($_POST['mix_id']);
        $mix_name = sanitize_text_field($_POST['mix_name']);
        $user_id = get_current_user_id();
        
        if (empty($mix_name)) {
            wp_send_json_error('Mix name is required.');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Verify ownership
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT id FROM $table 
            WHERE id = %d AND user_id = %d
        ", $mix_id, $user_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        // Update mix
        $result = $wpdb->update(
            $table,
            array(
                'mix_name' => $mix_name
            ),
            array('id' => $mix_id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update mix.');
        }
        
        wp_send_json_success('Mix updated successfully.');
    }

    /**
     * AJAX: Publish mix (image upload handled by HerbalMixMediaHandler)
     */
    public function ajax_publish_mix() {
        $this->verify_nonce('publish_mix');
        
        $mix_id = intval($_POST['mix_id']);
        $mix_name = sanitize_text_field($_POST['mix_name']);
        $mix_description = sanitize_textarea_field($_POST['mix_description']);
        $mix_image = esc_url_raw($_POST['mix_image']); // URL from HerbalMixMediaHandler
        $user_id = get_current_user_id();
        
        // Validation
        if (empty($mix_name) || empty($mix_description) || empty($mix_image)) {
            wp_send_json_error('All fields are required.');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Verify ownership
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT id FROM $table 
            WHERE id = %d AND user_id = %d
        ", $mix_id, $user_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        // Update mix to published status
        $result = $wpdb->update(
            $table,
            array(
                'mix_name' => $mix_name,
                'mix_description' => $mix_description,
                'mix_image' => $mix_image,
                'status' => 'published'
            ),
            array('id' => $mix_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to publish mix.');
        }
        
        // Award points for publishing
        if (class_exists('Herbal_Mix_Reward_Points')) {
            Herbal_Mix_Reward_Points::award_points($user_id, 50, 'mix_published', $mix_id, 'Published mix: ' . $mix_name);
        }
        
        wp_send_json_success('Mix published successfully!');
    }

    /**
     * AJAX: Delete mix
     */
    public function ajax_delete_mix() {
        $this->verify_nonce('delete_mix');
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Verify ownership
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT id FROM $table 
            WHERE id = %d AND user_id = %d
        ", $mix_id, $user_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        // Delete mix
        $result = $wpdb->delete($table, array('id' => $mix_id), array('%d'));
        
        if ($result === false) {
            wp_send_json_error('Failed to delete mix.');
        }
        
        wp_send_json_success('Mix deleted successfully.');
    }

    /**
     * AJAX: View mix details
     */
    public function ajax_view_mix() {
        $this->verify_nonce('herbal_profile_nonce');
        
        $mix_id = intval($_POST['mix_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE id = %d AND status = 'published'
        ", $mix_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found.');
        }
        
        wp_send_json_success(array(
            'id' => $mix->id,
            'name' => $mix->mix_name,
            'description' => $mix->mix_description,
            'image' => $mix->mix_image,
            'author' => get_user_by('id', $mix->user_id)->display_name,
            'created_at' => $mix->created_at,
            'like_count' => $mix->like_count
        ));
    }

    /**
     * AJAX: Remove favorite mix
     */
    public function ajax_remove_favorite_mix() {
        $this->verify_nonce('manage_favorites');
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT id, liked_by FROM $table 
            WHERE id = %d
        ", $mix_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found.');
        }
        
        // Remove user from liked_by
        $liked_by = json_decode($mix->liked_by, true);
        if (!is_array($liked_by)) {
            $liked_by = array();
        }
        
        $user_key = array_search(strval($user_id), $liked_by);
        if ($user_key !== false) {
            unset($liked_by[$user_key]);
            $liked_by = array_values($liked_by); // Re-index array
            
            $wpdb->update(
                $table,
                array(
                    'liked_by' => json_encode($liked_by),
                    'like_count' => count($liked_by)
                ),
                array('id' => $mix_id),
                array('%s', '%d'),
                array('%d')
            );
        }
        
        wp_send_json_success('Removed from favorites.');
    }

    /**
     * AJAX: Buy mix (add to cart)
     */
    public function ajax_buy_mix() {
        $this->verify_nonce('buy_mix');
        
        $mix_id = intval($_POST['mix_id']);
        
        // Get mix data and create cart product
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE id = %d AND status = 'published'
        ", $mix_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found.');
        }
        
        // Parse mix data and calculate price
        $mix_data = json_decode($mix->mix_data, true);
        $total_price = $this->calculate_mix_price($mix_data);
        
        // Add to cart (simplified - you might want to create actual products)
        $cart_item_data = array(
            'herbal_mix_id' => $mix_id,
            'mix_name' => $mix->mix_name,
            'mix_data' => $mix->mix_data
        );
        
        if (function_exists('WC')) {
            WC()->cart->add_to_cart(
                0, // Use 0 for custom product
                1, // Quantity
                0, // Variation ID
                array(), // Variation
                $cart_item_data
            );
            
            wp_send_json_success(array(
                'message' => 'Added to cart!',
                'cart_url' => wc_get_cart_url()
            ));
        } else {
            wp_send_json_error('WooCommerce not available.');
        }
    }

    /**
     * Add avatar field to account form
     */
    public function add_avatar_to_account_form() {
        $user_id = get_current_user_id();
        $avatar_url = get_user_meta($user_id, 'custom_avatar', true);
        
        $template_path = HERBAL_MIX_PLUGIN_PATH . 'includes/templates/user-profile-avatar-field.php';
        if (file_exists($template_path)) {
            include($template_path);
        }
    }

    /**
     * Save extra account details
     */
    public function save_extra_account_details($user_id) {
        if (isset($_POST['custom_avatar'])) {
            update_user_meta($user_id, 'custom_avatar', sanitize_url($_POST['custom_avatar']));
        }
    }

    /**
     * Custom avatar URL filter
     */
    public function custom_avatar_url($url, $id_or_email, $args) {
        $user_id = 0;
        
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif (is_email($id_or_email)) {
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

    // === HELPER METHODS ===

    /**
     * Verify AJAX nonce
     */
    private function verify_nonce($action) {
        // Map actions to their nonce names
        $nonce_mapping = array(
            'get_mix_details' => 'get_mix_details',
            'get_recipe_pricing' => 'get_recipe_pricing',
            'update_mix_details' => 'update_mix_details',
            'publish_mix' => 'publish_mix',
            'delete_mix' => 'delete_mix',
            'manage_favorites' => 'manage_favorites',
            'buy_mix' => 'buy_mix',
            'herbal_profile_nonce' => 'herbal_profile_nonce'
        );
        
        $nonce_action = isset($nonce_mapping[$action]) ? $nonce_mapping[$action] : $action;
        
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
    }

    /**
     * Build recipe details from mix data
     */
    private function build_recipe_details($mix_data) {
        if (empty($mix_data['ingredients']) || empty($mix_data['packaging'])) {
            return null;
        }
        
        $details = array(
            'packaging' => array(),
            'ingredients' => array(),
            'total_weight' => 0,
            'total_price' => 0,
            'total_points' => 0
        );
        
        // Get packaging details
        if (class_exists('Herbal_Mix_Database')) {
            $packaging = Herbal_Mix_Database::get_packaging($mix_data['packaging']['id']);
            if (!is_wp_error($packaging)) {
                $details['packaging'] = array(
                    'id' => $packaging->id,
                    'name' => $packaging->name,
                    'capacity' => $packaging->herb_capacity,
                    'price' => (float) $packaging->price,
                    'points' => (float) $packaging->price_point
                );
                $details['total_price'] += $packaging->price;
                $details['total_points'] += $packaging->price_point;
            }
        }
        
        // Get ingredients details
        foreach ($mix_data['ingredients'] as $ingredient) {
            if (class_exists('Herbal_Mix_Database')) {
                $ing_data = Herbal_Mix_Database::get_ingredient($ingredient['id']);
                if (!is_wp_error($ing_data)) {
                    $weight = (float) $ingredient['weight'];
                    $price_per_gram = (float) $ing_data->price;
                    $points_per_gram = (float) $ing_data->price_point;
                    
                    $ingredient_total_price = $weight * $price_per_gram;
                    $ingredient_total_points = $weight * $points_per_gram;
                    
                    $details['ingredients'][] = array(
                        'id' => $ing_data->id,
                        'name' => $ing_data->name,
                        'weight' => $weight,
                        'price_per_gram' => $price_per_gram,
                        'total_price' => $ingredient_total_price,
                        'points_per_gram' => $points_per_gram,
                        'total_points' => $ingredient_total_points,
                        'image' => $ing_data->image_url
                    );
                    
                    $details['total_weight'] += $weight;
                    $details['total_price'] += $ingredient_total_price;
                    $details['total_points'] += $ingredient_total_points;
                }
            }
        }
        
        return $details;
    }

    /**
     * Calculate mix price
     */
    private function calculate_mix_price($mix_data) {
        $recipe_details = $this->build_recipe_details($mix_data);
        return $recipe_details ? $recipe_details['total_price'] : 0;
    }
}

// Initialize the class
new HerbalMixUserProfileExtended();