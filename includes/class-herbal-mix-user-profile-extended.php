<?php
/**
 * Enhanced User Profile Extended Class - Fixed Button Functionality
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
        add_action('wp_ajax_buy_mix', array($this, 'ajax_buy_mix'));
        
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
    // Only load on account pages
    if (!is_account_page()) {
        return;
    }
    
    // Enqueue profile CSS
    wp_enqueue_style(
        'herbal-profile-css',
        plugin_dir_url(dirname(__FILE__)) . 'assets/css/profile.css',
        array(),
        filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/profile.css')
    );
    
    // Enqueue profile JavaScript
    wp_enqueue_script(
        'herbal-profile-js',
        plugin_dir_url(dirname(__FILE__)) . 'assets/js/profile.js',
        array('jquery'),
        filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/profile.js'),
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
            'deleting' => __('Deleting...', 'herbal-mix-creator2'),
            'deleteSuccess' => __('Mix deleted successfully.', 'herbal-mix-creator2'),
            'updating' => __('Updating...', 'herbal-mix-creator2'),
            'updateSuccess' => __('Mix updated successfully.', 'herbal-mix-creator2'),
            'publishing' => __('Publishing...', 'herbal-mix-creator2'),
            'publishSuccess' => __('Mix published successfully.', 'herbal-mix-creator2'),
            'imageRequired' => __('Please select an image for your mix.', 'herbal-mix-creator2'),
            'nameRequired' => __('Please enter a name for your mix.', 'herbal-mix-creator2'),
            'descriptionRequired' => __('Please enter a description for your mix.', 'herbal-mix-creator2'),
            'connectionError' => __('Connection error. Please check your internet connection and try again.', 'herbal-mix-creator2'),
            'invalidResponse' => __('Invalid server response. Please try again.', 'herbal-mix-creator2'),
            'noMixData' => __('No mix data found.', 'herbal-mix-creator2'),
            'noIngredients' => __('No ingredients found in this mix.', 'herbal-mix-creator2')
        )
    ));
    
    // Add inline CSS for loading states
    wp_add_inline_style('herbal-profile-css', '
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
            font-style: italic;
        }
        
        .error {
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .ingredient-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .ingredient-item:last-child {
            border-bottom: none;
        }
        
        .ingredient-name {
            font-weight: 500;
        }
        
        .ingredient-weight {
            color: #666;
        }
        
        .no-ingredients {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 20px;
        }
        
        .modal-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90%;
            overflow-y: auto;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .close-modal:hover {
            color: #333;
        }
    ');
}
    
    /**
     * Get modal styles
     */
    private function get_modal_styles() {
        return '
        /* Modal Dialog Styles */
        .modal-dialog {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 50px auto;
            padding: 30px;
            border: 1px solid #888;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-content.loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
            margin: -10px -10px 0 0;
        }
        
        .close-modal:hover,
        .close-modal:focus {
            color: #000;
            text-decoration: none;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #7eb246;
            outline: none;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        /* Mix Summary Styles */
        .mix-summary {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .mix-recipe-preview {
            margin-bottom: 20px;
        }
        
        .mix-recipe-preview h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .ingredients-list {
            background: white;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .ingredient-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .ingredient-item:last-child {
            border-bottom: none;
        }
        
        .ingredient-name {
            font-weight: 500;
            color: #555;
        }
        
        .ingredient-weight {
            color: #7eb246;
            font-weight: bold;
        }
        
        /* Mix Pricing Grid */
        .mix-pricing {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .price-item {
            background: white;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
            text-align: center;
        }
        
        .price-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .price-value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-top: 5px;
        }
        
        /* Image Upload Styles */
        .image-upload-section {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .image-upload-section h4 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .image-preview {
            margin: 15px 0;
        }
        
        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            border: 2px solid #ddd;
        }
        
        .image-buttons {
            display: flex;
            gap: 10px;
        }
        
        /* Form Actions */
        .form-actions {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .form-actions .button {
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .form-actions .button-primary {
            background: #7eb246;
            color: white;
            border: 1px solid #7eb246;
        }
        
        .form-actions .button-primary:hover {
            background: #6ca03c;
            border-color: #6ca03c;
        }
        
        .form-actions .button-primary:disabled {
            background: #ccc;
            border-color: #ccc;
            cursor: not-allowed;
        }
        
        .form-actions .button-secondary {
            background: white;
            color: #666;
            border: 1px solid #ddd;
        }
        
        .form-actions .button-secondary:hover {
            background: #f5f5f5;
            border-color: #999;
        }
        
        .cancel-modal {
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
        
        // Check if template exists
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'includes/templates/user-profile-my-mixes.php';
        if (file_exists($template_path)) {
            include($template_path);
            return;
        }
        
        // Enhanced fallback implementation
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
        
        // Add modals at the end
        $this->render_edit_modal();
        $this->render_publish_modal();
    }
    
    /**
     * Render Edit Modal
     */
    private function render_edit_modal() {
        ?>
        <div id="edit-mix-modal" class="modal-dialog">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <h3><?php _e('Edit Your Mix', 'herbal-mix-creator2'); ?></h3>
                
                <form id="edit-mix-form" method="post">
                    <input type="hidden" id="edit-mix-id" name="mix_id" value="">
                    
                    <div class="mix-summary">
                        <div class="mix-recipe-preview">
                            <h4><?php _e('Mix Recipe (not editable)', 'herbal-mix-creator2'); ?></h4>
                            <div id="edit-mix-ingredients-preview" class="ingredients-list">
                                <!-- Ingredients will be loaded here -->
                            </div>
                        </div>
                        
                        <div class="mix-pricing">
                            <div class="price-item">
                                <span class="price-label"><?php _e('Current Price', 'herbal-mix-creator2'); ?></span>
                                <span class="price-value" id="edit-mix-price">£0.00</span>
                            </div>
                            <div class="price-item">
                                <span class="price-label"><?php _e('Points Price', 'herbal-mix-creator2'); ?></span>
                                <span class="price-value" id="edit-mix-points-price">0 pts</span>
                            </div>
                            <div class="price-item">
                                <span class="price-label"><?php _e('Points Earned', 'herbal-mix-creator2'); ?></span>
                                <span class="price-value" id="edit-mix-points-earned">0 pts</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> *</label>
                        <input type="text" id="edit-mix-name" name="mix_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?></label>
                        <textarea id="edit-mix-description" name="mix_description" rows="4"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" id="edit-update-button" class="button button-primary">
                            <?php _e('Update Mix', 'herbal-mix-creator2'); ?>
                        </button>
                        <button type="button" class="button button-secondary cancel-modal">
                            <?php _e('Cancel', 'herbal-mix-creator2'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Publish Modal
     */
    private function render_publish_modal() {
        ?>
        <div id="publish-mix-modal" class="modal-dialog">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <h3><?php _e('Publish Your Mix', 'herbal-mix-creator2'); ?></h3>
                
                <form id="publish-mix-form" method="post" enctype="multipart/form-data">
                    <input type="hidden" id="publish-mix-id" name="mix_id" value="">
                    
                    <div class="mix-summary">
                        <div class="mix-recipe-preview">
                            <h4><?php _e('Mix Recipe', 'herbal-mix-creator2'); ?></h4>
                            <div id="publish-mix-ingredients-preview" class="ingredients-list">
                                <!-- Ingredients will be loaded here -->
                            </div>
                        </div>
                        
                        <div class="mix-pricing">
                            <div class="price-item">
                                <span class="price-label"><?php _e('Price', 'herbal-mix-creator2'); ?></span>
                                <span class="price-value" id="publish-mix-price">£0.00</span>
                            </div>
                            <div class="price-item">
                                <span class="price-label"><?php _e('Points Price', 'herbal-mix-creator2'); ?></span>
                                <span class="price-value" id="publish-mix-points-price">0 pts</span>
                            </div>
                            <div class="price-item">
                                <span class="price-label"><?php _e('Points Earned', 'herbal-mix-creator2'); ?></span>
                                <span class="price-value" id="publish-mix-points-earned">0 pts</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="publish-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> *</label>
                        <input type="text" id="publish-mix-name" name="mix_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="publish-mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?> *</label>
                        <textarea id="publish-mix-description" name="mix_description" rows="4" required></textarea>
                    </div>
                    
                    <div class="image-upload-section">
                        <h4><?php _e('Product Image', 'herbal-mix-creator2'); ?> *</h4>
                        <input type="hidden" id="publish-mix-image" name="mix_image" value="">
                        <div class="image-preview">
                            <img id="publish-mix-image-preview" src="" alt="" style="display:none;">
                        </div>
                        <div class="image-buttons">
                            <button type="button" id="publish-mix-image-select" class="button">
                                <?php _e('Select Image', 'herbal-mix-creator2'); ?>
                            </button>
                            <button type="button" id="publish-mix-image-remove" class="button" style="display:none;">
                                <?php _e('Remove Image', 'herbal-mix-creator2'); ?>
                            </button>
                        </div>
                        <div class="error-message" id="publish-image-error" style="display:none;"></div>
                    </div>
                    
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
        <?php
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
                $author_name = $author ? $author->display_name : __('Unknown', 'herbal-mix-creator2');
                
                echo '<tr>';
                echo '<td>';
                echo '<div class="mix-name">' . esc_html($mix->mix_name) . '</div>';
                if ($mix->mix_description) {
                    echo '<div class="mix-description">' . esc_html(wp_trim_words($mix->mix_description, 15)) . '</div>';
                }
                echo '</td>';
                echo '<td>' . esc_html($author_name) . '</td>';
                echo '<td>' . intval($mix->like_count) . '</td>';
                echo '<td>';
                echo '<div class="mix-actions">';
                echo '<button class="button view-mix" data-mix-id="' . $mix->id . '">' . __('View', 'herbal-mix-creator2') . '</button>';
                echo '<button class="button buy-mix" data-mix-id="' . $mix->id . '">' . __('Buy', 'herbal-mix-creator2') . '</button>';
                echo '<button class="button remove-favorite" data-mix-id="' . $mix->id . '">' . __('Unlike', 'herbal-mix-creator2') . '</button>';
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
     * Add avatar field to account form
     */
    public function add_avatar_to_account_form() {
        $user_id = get_current_user_id();
        $avatar_url = get_user_meta($user_id, 'custom_avatar', true);
        ?>
        <p class="woocommerce-form-row form-row">
            <label for="custom_avatar"><?php _e('Profile Picture', 'herbal-mix-creator2'); ?></label>
            <input type="hidden" name="custom_avatar" id="custom_avatar" value="<?php echo esc_url($avatar_url); ?>">
            <button type="button" class="button" id="upload_avatar_button"><?php _e('Upload Avatar', 'herbal-mix-creator2'); ?></button>
            <?php if ($avatar_url): ?>
                <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" style="max-width: 100px; display: block; margin-top: 10px;">
            <?php endif; ?>
        </p>
        <?php
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
     * Custom avatar URL
     */
    public function custom_avatar_url($url, $id_or_email, $args) {
        $user_id = 0;
        
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_object($id_or_email)) {
            $user_id = $id_or_email->user_id;
        } else {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        
        if ($user_id > 0) {
            $custom_avatar = get_user_meta($user_id, 'custom_avatar', true);
            if ($custom_avatar) {
                return $custom_avatar;
            }
        }
        
        return $url;
    }
    
    /**
 * AJAX: Get mix details (FIXED VERSION)
 */
public function ajax_get_mix_details() {
    // Verify nonce - check multiple possible nonce names
    $nonce_verified = false;
    $nonces_to_check = ['get_mix_details', 'herbal_mix_nonce', 'getNonce'];
    
    foreach ($nonces_to_check as $nonce_name) {
        if (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], $nonce_name)) {
            $nonce_verified = true;
            break;
        }
    }
    
    if (!$nonce_verified) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }
    
    $mix_id = intval($_REQUEST['mix_id']);
    $user_id = get_current_user_id();
    
    if (!$user_id || !$mix_id) {
        wp_send_json_error(array('message' => 'Invalid request parameters.'));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'herbal_mixes';
    
    // Get mix data with ownership check
    $mix = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND user_id = %d",
        $mix_id,
        $user_id
    ), ARRAY_A);
    
    if (!$mix) {
        wp_send_json_error(array('message' => 'Mix not found or access denied.'));
    }
    
    // Decode mix data safely
    $mix_data = json_decode($mix['mix_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(array('message' => 'Invalid mix data format.'));
    }
    
    $mix['mix_data_decoded'] = $mix_data;
    
    wp_send_json_success($mix);
}
    
   /**
 * AJAX: Get mix recipe and pricing (FIXED VERSION)
 */
public function ajax_get_mix_recipe_and_pricing() {
    // Verify nonce - check multiple possible nonce names
    $nonce_verified = false;
    $nonces_to_check = ['get_mix_details', 'get_recipe_pricing', 'herbal_mix_nonce'];
    
    foreach ($nonces_to_check as $nonce_name) {
        if (isset($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], $nonce_name)) {
            $nonce_verified = true;
            break;
        }
    }
    
    if (!$nonce_verified) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }
    
    $mix_id = intval($_REQUEST['mix_id']);
    
    if (!$mix_id) {
        wp_send_json_error(array('message' => 'Invalid mix ID.'));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'herbal_mixes';
    
    $mix = $wpdb->get_row($wpdb->prepare(
        "SELECT mix_data FROM $table WHERE id = %d",
        $mix_id
    ));
    
    if (!$mix) {
        wp_send_json_error(array('message' => 'Mix not found.'));
    }
    
    $mix_data = json_decode($mix->mix_data, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($mix_data)) {
        wp_send_json_error(array('message' => 'Invalid mix data format.'));
    }
    
    // Initialize totals
    $total_price = 0;
    $total_points = 0;
    $points_earned = 0;
    $ingredients_html = '';
    
    // Get packaging info (FIXED: use correct column names)
    if (!empty($mix_data['packaging_id'])) {
        $packaging = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}herbal_packaging WHERE id = %d",
            intval($mix_data['packaging_id'])
        ));
        
        if ($packaging) {
            $total_price += floatval($packaging->price);
            $total_points += floatval($packaging->price_point); // FIXED: correct column name
            $points_earned += floatval($packaging->point_earned); // FIXED: correct column name
        }
    }
    
    // Get ingredients info (FIXED: handle both old and new data structures)
    if (!empty($mix_data['ingredients'])) {
        // Handle different data structures
        $ingredients_data = $mix_data['ingredients'];
        
        // If it's an object/associative array (old format): ingredient_id => weight
        if (is_array($ingredients_data) && !isset($ingredients_data[0])) {
            foreach ($ingredients_data as $ingredient_id => $weight) {
                $this->process_ingredient_data($wpdb, $ingredient_id, $weight, $total_price, $total_points, $points_earned, $ingredients_html);
            }
        }
        // If it's an indexed array (new format): [{'id': x, 'weight': y}, ...]
        elseif (is_array($ingredients_data) && isset($ingredients_data[0])) {
            foreach ($ingredients_data as $ingredient_data) {
                if (isset($ingredient_data['id']) && isset($ingredient_data['weight'])) {
                    $this->process_ingredient_data($wpdb, $ingredient_data['id'], $ingredient_data['weight'], $total_price, $total_points, $points_earned, $ingredients_html);
                }
            }
        }
    }
    
    wp_send_json_success(array(
        'ingredients_html' => $ingredients_html,
        'total_price' => number_format($total_price, 2),
        'total_points' => round($total_points),
        'points_earned' => round($points_earned)
    ));
}
    
    /**
 * Helper method to process ingredient data (FIXED: use correct column names)
 */
private function process_ingredient_data($wpdb, $ingredient_id, $weight, &$total_price, &$total_points, &$points_earned, &$ingredients_html) {
    $ingredient = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}herbal_ingredients WHERE id = %d",
        intval($ingredient_id)
    ));
    
    if ($ingredient) {
        $weight = floatval($weight);
        $total_price += (floatval($ingredient->price) * $weight);
        $total_points += (floatval($ingredient->price_point) * $weight); // FIXED: correct column name
        $points_earned += (floatval($ingredient->point_earned) * $weight); // FIXED: correct column name
        
        $ingredients_html .= '<div class="ingredient-item">';
        $ingredients_html .= '<span class="ingredient-name">' . esc_html($ingredient->name) . '</span>';
        $ingredients_html .= '<span class="ingredient-weight">' . $weight . 'g</span>';
        $ingredients_html .= '</div>';
    }
}
    
    /**
     * AJAX: Update mix details
     */
    public function ajax_update_mix_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'update_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        $mix_name = sanitize_text_field($_POST['mix_name']);
        $mix_description = sanitize_textarea_field($_POST['mix_description']);
        
        if (!$user_id || !$mix_id || empty($mix_name)) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Verify ownership
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, status FROM $table WHERE id = %d",
            $mix_id
        ));
        
        if (!$mix || $mix->user_id != $user_id) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        if ($mix->status === 'published') {
            wp_send_json_error(array('message' => 'Cannot edit published mixes.'));
        }
        
        // Update mix
        $result = $wpdb->update(
            $table,
            array(
                'mix_name' => $mix_name,
                'mix_description' => $mix_description
            ),
            array('id' => $mix_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Mix updated successfully.', 'herbal-mix-creator2')
            ));
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
        $mix_name = sanitize_text_field($_POST['mix_name']);
        $mix_description = sanitize_textarea_field($_POST['mix_description']);
        $mix_image = sanitize_url($_POST['mix_image']);
        
        if (!$user_id || !$mix_id || empty($mix_name) || empty($mix_description) || empty($mix_image)) {
            wp_send_json_error(array('message' => 'All fields are required.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Get mix data
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $mix_id, $user_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found or access denied.'));
        }
        
        if ($mix->status === 'published') {
            wp_send_json_error(array('message' => 'Mix is already published.'));
        }
        
        // Update mix details first
        $wpdb->update(
            $table,
            array(
                'mix_name' => $mix_name,
                'mix_description' => $mix_description,
                'mix_image' => $mix_image
            ),
            array('id' => $mix_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        // Create public product using Herbal_Mix_Actions
        if (class_exists('Herbal_Mix_Actions')) {
            $actions = new Herbal_Mix_Actions();
            
            // Reload mix with updated data
            $mix = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $mix_id
            ));
            
            $product_id = $actions->create_public_product($mix);
            
            if ($product_id) {
                // Update mix status to published
                $wpdb->update(
                    $table,
                    array(
                        'status' => 'published',
                        'base_product_id' => $product_id
                    ),
                    array('id' => $mix_id),
                    array('%s', '%d'),
                    array('%d')
                );
                
                // Give user points for publishing
                $current_points = get_user_meta($user_id, 'reward_points', true) ?: 0;
                $new_points = $current_points + 50;
                update_user_meta($user_id, 'reward_points', $new_points);
                
                // Record points transaction
                if (class_exists('Herbal_Mix_Database')) {
                    Herbal_Mix_Database::record_points_transaction(
                        $user_id, 50, 'mix_published', $mix_id, $current_points, $new_points,
                        'Points for publishing mix: ' . $mix_name
                    );
                }
                
                wp_send_json_success(array(
                    'message' => __('Mix published successfully! You earned 50 reward points.', 'herbal-mix-creator2'),
                    'redirect' => get_permalink($product_id)
                ));
            } else {
                wp_send_json_error(array('message' => 'Failed to create product.'));
            }
        } else {
            wp_send_json_error(array('message' => 'Actions class not available.'));
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
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found.'));
        }
        
        // Use existing actions class to create virtual product and add to cart
        if (class_exists('Herbal_Mix_Actions')) {
            $actions = new Herbal_Mix_Actions();
            
            // Create virtual product from mix
            $product_id = $actions->generate_product_from_mix($mix, true);
            
            if ($product_id) {
                // Add to cart
                if (class_exists('WC') && WC()->cart) {
                    $cart_item_key = WC()->cart->add_to_cart($product_id, 1);
                    
                    if ($cart_item_key) {
                        wp_send_json_success(array(
                            'message' => __('Mix added to cart successfully!', 'herbal-mix-creator2'),
                            'cart_url' => wc_get_cart_url(),
                            'redirect_url' => wc_get_cart_url()
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
            // Send notification email to admin
            $admin_email = get_option('admin_email');
            $user = get_userdata($user_id);
            $subject = 'Published Mix Deletion Request';
            $message = sprintf(
                "User %s (ID: %d) wants to delete a published mix (ID: %d).\n\nPlease review this request.",
                $user->display_name,
                $user_id,
                $mix_id
            );
            
            wp_mail($admin_email, $subject, $message);
            
            wp_send_json_error(array(
                'message' => __('Published mixes cannot be deleted directly. An admin has been notified of your request.', 'herbal-mix-creator2')
            ));
        }
        
        // Delete non-published mix
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
            }
        }
        
        // Return mix data for modal display
        $mix['mix_data_decoded'] = json_decode($mix['mix_data'], true);
        wp_send_json_success($mix);
    }
    
    /**
     * AJAX: Remove favorite mix
     */
    public function ajax_remove_favorite_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'manage_favorites')) {
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
        $user_id_str = strval($user_id);
        
        if (in_array($user_id_str, $liked_by)) {
            $liked_by = array_diff($liked_by, array($user_id_str));
            $like_count = max(0, $mix->like_count - 1);
            
            $result = $wpdb->update(
                $table,
                array(
                    'liked_by' => json_encode(array_values($liked_by)),
                    'like_count' => $like_count
                ),
                array('id' => $mix_id),
                array('%s', '%d'),
                array('%d')
            );
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => __('Mix removed from favorites.', 'herbal-mix-creator2')
                ));
            }
        }
        
        wp_send_json_error(array('message' => 'Mix not in favorites.'));
    }
    
    /**
     * AJAX: Upload mix image
     */
    public function ajax_upload_mix_image() {
        if (!wp_verify_nonce($_POST['nonce'], 'upload_mix_image')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        if (!isset($_FILES['mix_image'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'));
        }
        
        $file = $_FILES['mix_image'];
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(array('message' => 'Invalid file type. Only JPEG, PNG and GIF are allowed.'));
        }
        
        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'File too large. Maximum size is 5MB.'));
        }
        
        // Handle upload
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Create attachment
            $attachment = array(
                'post_mime_type' => $movefile['type'],
                'post_title' => sanitize_file_name($file['name']),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attach_id = wp_insert_attachment($attachment, $movefile['file']);
            
            if ($attach_id) {
                $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
                
                wp_send_json_success(array(
                    'url' => $movefile['url'],
                    'attachment_id' => $attach_id,
                    'message' => __('Image uploaded successfully.', 'herbal-mix-creator2')
                ));
            }
        }
        
        wp_send_json_error(array('message' => 'Upload failed.'));
    }
}

// Initialize the class
new HerbalMixUserProfileExtended();