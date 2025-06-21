<?php
/**
 * COMPLETE: Enhanced User Profile Extended Class - ALL METHODS INCLUDED
 * File: includes/class-herbal-mix-user-profile-extended.php
 * 
 * FULL IMPLEMENTATION - Original ~800+ lines with fixes
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
        // Set initial points (e.g., welcome bonus)
        $initial_points = 100; // Welcome bonus
        update_user_meta($user_id, 'reward_points', $initial_points);
        
        // Record the initial transaction
        if (class_exists('Herbal_Mix_Database')) {
            Herbal_Mix_Database::record_points_transaction(
                $user_id, 
                $initial_points, 
                'welcome_bonus', 
                null, 
                0, 
                $initial_points, 
                'Welcome bonus for new user'
            );
        }
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
     * FIXED: Display My Mixes tab content - ONLY uses template, NO fallback
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
        
        // FIXED: Only use template - NO fallback implementation
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'includes/templates/user-profile-my-mixes.php';
        if (file_exists($template_path)) {
            include($template_path);
        } else {
            // Simple error message if template missing
            echo '<div class="herbal-error">';
            echo '<p>' . __('Template file missing. Please check plugin installation.', 'herbal-mix-creator2') . '</p>';
            echo '</div>';
        }
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
        
        // Simple fallback for favorite mixes only
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
            echo '<p>' . __('You haven\'t liked any community mixes yet.', 'herbal-mix-creator2') . '</p>';
            echo '<p>' . __('Explore our community mixes to find your favorites!', 'herbal-mix-creator2') . '</p>';
            echo '</div>';
        } else {
            echo '<table class="mixes-table">';
            echo '<thead><tr>';
            echo '<th>' . __('Mix Name', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Author', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Likes', 'herbal-mix-creator2') . '</th>';
            echo '<th>' . __('Actions', 'herbal-mix-creator2') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($favorite_mixes as $mix) {
                $author = get_userdata($mix->user_id);
                echo '<tr>';
                echo '<td>' . esc_html($mix->mix_name) . '</td>';
                echo '<td>' . ($author ? esc_html($author->display_name) : 'Unknown') . '</td>';
                echo '<td>' . intval($mix->like_count) . '</td>';
                echo '<td>';
                echo '<button class="button view-mix" data-mix-id="' . $mix->id . '">' . __('View', 'herbal-mix-creator2') . '</button>';
                echo '<button class="button buy-mix" data-mix-id="' . $mix->id . '">' . __('Buy', 'herbal-mix-creator2') . '</button>';
                echo '<button class="button remove-favorite" data-mix-id="' . $mix->id . '">' . __('Remove', 'herbal-mix-creator2') . '</button>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        echo '</div>';
    }
    
    /**
     * Add avatar field to account form
     */
    public function add_avatar_to_account_form() {
        $user_id = get_current_user_id();
        $custom_avatar = get_user_meta($user_id, 'custom_avatar', true);
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="custom_avatar"><?php esc_html_e('Profile Picture', 'herbal-mix-creator2'); ?></label>
            <input type="file" class="woocommerce-Input woocommerce-Input--file input-file" name="custom_avatar" id="custom_avatar" accept="image/*" />
            <?php if ($custom_avatar): ?>
                <div class="current-avatar">
                    <img src="<?php echo esc_url($custom_avatar); ?>" alt="Current avatar" style="max-width: 100px; height: auto; margin-top: 10px;">
                    <label>
                        <input type="checkbox" name="remove_avatar" value="1"> <?php esc_html_e('Remove current picture', 'herbal-mix-creator2'); ?>
                    </label>
                </div>
            <?php endif; ?>
        </p>
        <?php
    }
    
    /**
     * Save extra account details
     */
    public function save_extra_account_details($user_id) {
        // Handle avatar upload
        if (isset($_POST['remove_avatar']) && $_POST['remove_avatar']) {
            delete_user_meta($user_id, 'custom_avatar');
        } elseif (isset($_FILES['custom_avatar']) && $_FILES['custom_avatar']['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = $_FILES['custom_avatar'];
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($uploaded_file['type'], $allowed_types)) {
                $upload = wp_handle_upload($uploaded_file, ['test_form' => false]);
                
                if (!isset($upload['error'])) {
                    update_user_meta($user_id, 'custom_avatar', $upload['url']);
                }
            }
        }
    }
    
    /**
     * Use custom avatar if available
     */
    public function custom_avatar_url($url, $id_or_email, $args) {
        $user_id = null;
        
        if (is_numeric($id_or_email)) {
            $user_id = $id_or_email;
        } elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) {
            $user_id = $id_or_email->user_id;
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
    
    /**
     * Enhanced enqueue assets with correct nonces
     */
    public function enqueue_profile_assets() {
        // Only load on account pages
        if (!is_account_page()) {
            return;
        }
        
        // Enqueue profile CSS (uses existing profile.css)
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
            'currencySymbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '£',
            
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
    }
    
    // === AJAX HANDLERS ===
    
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
        
        // Handle image upload if provided
        $update_data = array(
            'mix_name' => $mix_name,
            'mix_description' => $mix_description
        );
        
        if (isset($_FILES['mix_image']) && $_FILES['mix_image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = $_FILES['mix_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (in_array($uploaded_file['type'], $allowed_types)) {
                $upload = wp_handle_upload($uploaded_file, ['test_form' => false]);
                if (!isset($upload['error'])) {
                    $update_data['mix_image'] = $upload['url'];
                }
            }
        }
        
        // Update mix
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $mix_id),
            array_fill(0, count($update_data), '%s'),
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
        
        if (!$user_id || !$mix_id || empty($mix_name) || empty($mix_description)) {
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
        
        // Handle required image upload
        $mix_image = $mix->mix_image; // Use existing image if any
        
        if (isset($_FILES['mix_image']) && $_FILES['mix_image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = $_FILES['mix_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (in_array($uploaded_file['type'], $allowed_types)) {
                $upload = wp_handle_upload($uploaded_file, ['test_form' => false]);
                if (!isset($upload['error'])) {
                    $mix_image = $upload['url'];
                }
            }
        }
        
        if (empty($mix_image)) {
            wp_send_json_error(array('message' => 'An image is required to publish your mix.'));
        }
        
        // Update to published status
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
        
        if ($result !== false) {
            // Award points for publishing
            $current_points = floatval(get_user_meta($user_id, 'reward_points', true)) ?: 0;
            $publish_bonus = 50; // Points for publishing
            $new_points = $current_points + $publish_bonus;
            
            update_user_meta($user_id, 'reward_points', $new_points);
            
            if (class_exists('Herbal_Mix_Database')) {
                Herbal_Mix_Database::record_points_transaction(
                    $user_id, $publish_bonus, 'mix_published', $mix_id, $current_points, $new_points, 'Published mix: ' . $mix_name
                );
            }
            
            wp_send_json_success(array('message' => 'Mix published successfully! You earned ' . $publish_bonus . ' points.'));
        } else {
            wp_send_json_error(array('message' => 'Database error.'));
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
            $liked_by = array_filter($liked_by, function($id) use ($user_id_str) {
                return $id !== $user_id_str;
            });
            
            $new_like_count = max(0, intval($mix->like_count) - 1);
            
            $wpdb->update(
                $table,
                array(
                    'liked_by' => json_encode(array_values($liked_by)),
                    'like_count' => $new_like_count
                ),
                array('id' => $mix_id),
                array('%s', '%d'),
                array('%d')
            );
        }
        
        wp_send_json_success(array('message' => 'Mix removed from favorites.'));
    }
    
    /**
     * AJAX: Upload mix image
     */
    public function ajax_upload_mix_image() {
        if (!wp_verify_nonce($_POST['nonce'], 'upload_mix_image')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'No file uploaded or upload error.'));
        }
        
        $uploaded_file = $_FILES['image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($uploaded_file['type'], $allowed_types)) {
            wp_send_json_error(array('message' => 'Invalid file type. Only JPEG, PNG and GIF images are allowed.'));
        }
        
        // Check file size (max 5MB)
        if ($uploaded_file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'File too large. Maximum size is 5MB.'));
        }
        
        // Handle upload
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($uploaded_file, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Create attachment
            $attachment = array(
                'post_mime_type' => $movefile['type'],
                'post_title' => sanitize_file_name($uploaded_file['name']),
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
    
    /**
     * AJAX: Buy mix
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
        
        // Use Herbal_Mix_Actions to generate product and get cart URL
        if (class_exists('Herbal_Mix_Actions')) {
            $actions = new Herbal_Mix_Actions();
            $product_id = $actions->generate_product_from_mix($mix, true); // true = virtual product
            
            if ($product_id) {
                // Add to cart and redirect
                if (function_exists('WC')) {
                    WC()->cart->add_to_cart($product_id, 1);
                    wp_send_json_success(array(
                        'redirect_url' => wc_get_cart_url(),
                        'message' => 'Mix added to cart.'
                    ));
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
}

// Initialize the class
new HerbalMixUserProfileExtended();