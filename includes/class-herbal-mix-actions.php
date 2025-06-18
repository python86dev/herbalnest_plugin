<?php
/**
 * Handles actions for user-created herbal mixes: Buy, Publish, View, Delete.
 * File: includes/class-herbal-mix-actions.php
 * 
 * UPDATED: Integrated with existing profile.js and user profile extended system
 * Uses AJAX handlers from HerbalMixUserProfileExtended class instead of GET requests
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HerbalMixActions {

    public function __construct() {
        // Legacy GET request handler for backwards compatibility
        add_action( 'init', array( $this, 'handle_legacy_mix_actions' ) );
        
        // Add display of author and ingredients on product page
        add_action('woocommerce_single_product_summary', array($this, 'display_herbal_mix_details'), 25);
        
        // Integration with existing AJAX system from user profile extended
        add_action('wp_ajax_herbal_mix_action_redirect', array($this, 'ajax_redirect_handler'));
        add_action('wp_ajax_nopriv_herbal_mix_action_redirect', array($this, 'ajax_redirect_handler'));
    }

    /**
     * Legacy GET request handler for backwards compatibility
     * Now redirects to proper AJAX endpoints or My Account page
     */
    public function handle_legacy_mix_actions() {
        if ( ! is_user_logged_in() || ! isset( $_GET['action'], $_GET['mix_id'] ) ) {
            return;
        }

        $action  = sanitize_text_field( $_GET['action'] );
        $mix_id  = intval( $_GET['mix_id'] );
        $user_id = get_current_user_id();

        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        $mix   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d AND user_id = %d", $mix_id, $user_id ) );

        if ( ! $mix ) {
            wp_die( __('Mix not found or permission denied.', 'herbal-mix-creator2') );
        }

        switch ( $action ) {
            case 'buy_mix':
                // Redirect to My Account with buy action
                $redirect_url = $this->get_my_account_url_with_action('buy', $mix_id);
                wp_redirect( $redirect_url );
                exit;
                
            case 'publish_mix':
                // Redirect to My Account with publish action  
                $redirect_url = $this->get_my_account_url_with_action('publish', $mix_id);
                wp_redirect( $redirect_url );
                exit;
                
            case 'delete_mix':
                // Handle deletion immediately for GET requests (backwards compatibility)
                $this->handle_delete_mix_legacy( $mix, $user_id );
                break;
                
            case 'view_mix':
                // Check if published and has product page
                if ($mix->status === 'published' && $mix->base_product_id) {
                    $product_url = get_permalink($mix->base_product_id);
                    if ($product_url) {
                        wp_redirect($product_url);
                        exit;
                    }
                }
                
                // Otherwise redirect to My Account
                $redirect_url = $this->get_my_account_url();
                wp_redirect( $redirect_url );
                exit;
        }
    }

    /**
     * AJAX redirect handler for frontend integration
     */
    public function ajax_redirect_handler() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_actions')) {
            wp_send_json_error('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['action_type']);
        $mix_id = intval($_POST['mix_id']);
        
        switch ($action) {
            case 'buy':
                // This should be handled by the existing ajax_buy_mix in user profile extended
                wp_send_json_success(array(
                    'redirect' => 'ajax',
                    'ajax_action' => 'buy_mix',
                    'mix_id' => $mix_id
                ));
                break;
                
            case 'publish':
                // This should be handled by the existing ajax_publish_mix in user profile extended
                wp_send_json_success(array(
                    'redirect' => 'ajax', 
                    'ajax_action' => 'publish_mix',
                    'mix_id' => $mix_id
                ));
                break;
                
            default:
                wp_send_json_error('Invalid action');
        }
    }

    /**
     * Legacy delete handler for GET requests
     */
    private function handle_delete_mix_legacy( $mix, $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Check if mix is published
        if ($mix->status === 'published') {
            // Send notification email to admin
            $this->send_published_mix_deletion_notification( $mix, $user_id );
        }
        
        // Delete mix from database
        $wpdb->delete( $table, array( 'id' => $mix->id ) );
        
        // Redirect to My Account with success message
        $redirect_url = $this->get_my_account_url_with_message( $mix->status );
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * Send email notification for published mix deletion
     */
    private function send_published_mix_deletion_notification( $mix, $user_id ) {
        // Get user information
        $user = get_user_by('id', $user_id);
        $user_name = $user ? ($user->display_name ?: $user->user_login) : 'Unknown User';
        $user_email = $user ? $user->user_email : 'unknown@example.com';
        
        // Get admin email
        $admin_email = get_option('admin_email');
        
        // Email subject
        $subject = sprintf(
            '[%s] Published Mix Deletion Request - %s',
            get_bloginfo('name'),
            $mix->mix_name
        );
        
        // Email content
        $message = sprintf(
            "Hello Administrator,\n\n" .
            "A user has deleted a published herbal mix from their profile. " .
            "Please remove the corresponding product from the shop if it exists.\n\n" .
            "Mix Details:\n" .
            "- Mix Name: %s\n" .
            "- Mix ID: %d\n" .
            "- Created: %s\n" .
            "- Status: %s\n" .
            "- Product ID: %s\n\n" .
            "User Details:\n" .
            "- User Name: %s\n" .
            "- User ID: %d\n" .
            "- User Email: %s\n\n" .
            "Mix Description:\n%s\n\n" .
            "This is an automated notification from %s.\n" .
            "Timestamp: %s",
            esc_html($mix->mix_name),
            $mix->id,
            $mix->created_at,
            $mix->status,
            $mix->base_product_id ? $mix->base_product_id : 'Not set',
            esc_html($user_name),
            $user_id,
            $user_email,
            $mix->mix_description ? esc_html($mix->mix_description) : 'No description provided',
            get_bloginfo('name'),
            current_time('mysql')
        );
        
        // Email headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', get_bloginfo('name'), get_option('admin_email')),
            sprintf('Reply-To: %s <%s>', $user_name, $user_email)
        );
        
        // Send email
        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * Get My Account URL with action parameters
     */
    private function get_my_account_url_with_action($action, $mix_id) {
        $my_account_url = wc_get_page_permalink('myaccount');
        
        if ($my_account_url) {
            $url = trailingslashit($my_account_url) . 'my-mixes/';
            $url = add_query_arg(array(
                'action' => $action,
                'mix_id' => $mix_id
            ), $url);
            
            return $url;
        }
        
        return wc_get_page_permalink('myaccount') ?: home_url();
    }

    /**
     * Get My Account URL with message
     */
    private function get_my_account_url_with_message( $mix_status ) {
        $my_account_url = wc_get_page_permalink('myaccount');
        
        if ($my_account_url) {
            $url = trailingslashit($my_account_url) . 'my-mixes/';
            
            // Add message based on status
            if ($mix_status === 'published') {
                $url = add_query_arg('deleted', 'published', $url);
            } else {
                $url = add_query_arg('deleted', 'success', $url);
            }
            
            return $url;
        }
        
        return wc_get_page_permalink('myaccount') ?: home_url();
    }

    /**
     * Get My Account URL
     */
    private function get_my_account_url() {
        $my_account_url = wc_get_page_permalink('myaccount');
        
        if ($my_account_url) {
            return trailingslashit($my_account_url) . 'my-mixes/';
        }
        
        return wc_get_page_permalink('myaccount') ?: home_url();
    }

    /**
     * Creates a public product from a user's mix
     * Used by the AJAX publish handler in HerbalMixUserProfileExtended
     * 
     * @param object $mix Mix data from database
     * @param float $price Optional product price (if not provided, will be calculated)
     * @param float $points_price Optional price in points (if not provided, will be calculated)
     * @param float $points_earned Optional points to earn (if not provided, will be calculated)
     * @return int|false ID of created product or false on error
     */
    public function create_public_product($mix, $price = 0, $points_price = 0, $points_earned = 0) {
        if (empty($mix) || empty($mix->mix_data)) {
            return false;
        }
        
        // Decode mix data
        $mix_data = json_decode($mix->mix_data, true);
        if (!is_array($mix_data)) {
            return false;
        }
        
        // If prices not provided, calculate from current data
        if ($price <= 0 || $points_price <= 0 || $points_earned <= 0) {
            $calculated_prices = $this->calculate_mix_pricing($mix_data);
            
            if ($price <= 0) {
                $price = $calculated_prices['price'];
            }
            
            if ($points_price <= 0) {
                $points_price = $calculated_prices['points_price'];
            }
            
            if ($points_earned <= 0) {
                $points_earned = $calculated_prices['points_earned'];
            }
        }
        
        // Create new product
        $product = new WC_Product_Simple();
        
        // Set basic product info
        $product->set_name($mix->mix_name);
        $product->set_description($mix->mix_description);
        $product->set_short_description(sprintf(
            __('Custom herbal mix created by %s', 'herbal-mix-creator2'),
            $this->get_user_nickname($mix->user_id)
        ));
        
        // Set price
        $product->set_regular_price(round($price, 2));
        
        // Set as physical product (requires shipping)
        $product->set_virtual(false);
        $product->set_catalog_visibility('visible');
        $product->set_status('publish');
        
        // Calculate total weight from ingredients
        $total_weight = 0;
        if (isset($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
            foreach ($mix_data['ingredients'] as $ingredient) {
                if (isset($ingredient['weight'])) {
                    $total_weight += floatval($ingredient['weight']);
                }
            }
        }
        
        // Get packaging information and set shipping parameters
        $packaging_weight = 20; // Default packaging weight in grams
        $packaging_dimensions = array(
            'length' => 10, // Default dimensions in cm
            'width'  => 10,
            'height' => 5
        );
        
        if (isset($mix_data['packaging_id'])) {
            global $wpdb;
            $packaging_table = $wpdb->prefix . 'herbal_packaging';
            $packaging = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $packaging_table WHERE id = %d",
                intval($mix_data['packaging_id'])
            ));
            
            if ($packaging && isset($packaging->herb_capacity)) {
                $capacity = intval($packaging->herb_capacity);
                
                // Adjust packaging size based on capacity
                if ($capacity <= 50) {
                    $packaging_weight = 20;
                    $packaging_dimensions = array('length' => 8, 'width' => 8, 'height' => 4);
                } else if ($capacity <= 100) {
                    $packaging_weight = 30;
                    $packaging_dimensions = array('length' => 10, 'width' => 10, 'height' => 5);
                } else {
                    $packaging_weight = 40;
                    $packaging_dimensions = array('length' => 12, 'width' => 12, 'height' => 6);
                }
            }
        }
        
        // Set shipping parameters
        $product->set_weight($total_weight + $packaging_weight); // Weight in grams
        $product->set_length($packaging_dimensions['length']);
        $product->set_width($packaging_dimensions['width']);
        $product->set_height($packaging_dimensions['height']);
        
        // Set SKU
        $product->set_sku('ECM-' . $mix->id);
        
        // Save product to get ID
        $product_id = $product->save();
        
        if (!$product_id) {
            return false;
        }
        
        // Add product metadata using correct field names
        update_post_meta($product_id, 'price_point', round($points_price, 2));
        update_post_meta($product_id, 'point_earned', $points_earned);
        update_post_meta($product_id, 'herbal_mix_id', $mix->id);
        update_post_meta($product_id, '_custom_mix_user', $mix->user_id);
        update_post_meta($product_id, '_custom_mix_author', $this->get_user_nickname($mix->user_id));
        
        // Save full mix data in metadata
        update_post_meta($product_id, 'herbal_mix_data', $mix->mix_data);
        update_post_meta($product_id, '_custom_mix_created', current_time('mysql'));
        
        // Add ingredients as separate metadata for easier access
        if (isset($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
            update_post_meta($product_id, '_custom_mix_ingredients', json_encode($mix_data['ingredients']));
        }
        
        // Add to Custom Mix category
        $term = get_term_by('slug', 'custom-mix', 'product_cat');
        if ($term) {
            wp_set_post_terms($product_id, array($term->term_id), 'product_cat');
        } else {
            // If category doesn't exist, create it
            $term = wp_insert_term('Custom Mix', 'product_cat', array(
                'slug' => 'custom-mix',
                'description' => __('User created herbal mixes', 'herbal-mix-creator2')
            ));
            
            if (!is_wp_error($term)) {
                wp_set_post_terms($product_id, array($term['term_id']), 'product_cat');
            }
        }
        
        // Set product image
        if (!empty($mix->mix_image)) {
            $image_id = attachment_url_to_postid(esc_url($mix->mix_image));
            if ($image_id) {
                set_post_thumbnail($product_id, $image_id);
            } else {
                // If attachment not found, try to create it
                $this->set_image_from_url($product_id, $mix->mix_image);
            }
        }
        
        // Set stock status (in stock by default)
        update_post_meta($product_id, '_stock_status', 'instock');
        update_post_meta($product_id, '_manage_stock', 'no');
        
        return $product_id;
    }

    /**
     * Calculate prices and points for a mix based on current data
     */
    private function calculate_mix_pricing($mix_data) {
        $total_price = 0;
        $total_points_price = 0;
        $total_points_earned = 0;
        
        global $wpdb;
        
        // Process packaging
        if (!empty($mix_data['packaging_id'])) {
            $packaging_table = $wpdb->prefix . 'herbal_packaging';
            $packaging = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $packaging_table WHERE id = %d",
                intval($mix_data['packaging_id'])
            ));
            
            if ($packaging) {
                $total_price += floatval($packaging->price);
                $total_points_price += floatval($packaging->price_point);
                $total_points_earned += floatval($packaging->point_earned);
            }
        }
        
        // Process ingredients
        $ingredients_table = $wpdb->prefix . 'herbal_ingredients';
        
        if (!empty($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
            foreach ($mix_data['ingredients'] as $ingredient_data) {
                if (isset($ingredient_data['id']) && isset($ingredient_data['weight'])) {
                    $ingredient_id = intval($ingredient_data['id']);
                    $weight = floatval($ingredient_data['weight']);
                    
                    $ingredient = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $ingredients_table WHERE id = %d",
                        $ingredient_id
                    ));
                    
                    if ($ingredient) {
                        $total_price += floatval($ingredient->price) * $weight;
                        $total_points_price += floatval($ingredient->price_point) * $weight;
                        $total_points_earned += floatval($ingredient->point_earned) * $weight;
                    }
                }
            }
        }
        
        return [
            'price' => round($total_price, 2),
            'points_price' => round($total_points_price),
            'points_earned' => round($total_points_earned)
        ];
    }

    /**
     * Generate product from mix (for both virtual and physical products)
     * Used by the existing AJAX buy handler in HerbalMixUserProfileExtended
     */
    public function generate_product_from_mix($mix, $is_virtual = true) {
        if (empty($mix->mix_data)) {
            return false;
        }
        
        $mix_data = json_decode($mix->mix_data, true);
        if (!is_array($mix_data)) {
            return false;
        }
        
        // Calculate totals from current ingredient prices
        $pricing = $this->calculate_mix_pricing($mix_data);
        
        // Create product
        $product = new WC_Product_Simple();
        
        // Set product data
        $product->set_name($mix->mix_name);
        $product->set_description($mix->mix_description ?: 'Custom herbal mix');
        $product->set_short_description(sprintf(
            __('Custom herbal mix created by %s', 'herbal-mix-creator2'),
            $this->get_user_nickname($mix->user_id)
        ));
        
        // Set price
        $product->set_regular_price($pricing['price']);
        
        // Set virtual/physical status
        $product->set_virtual($is_virtual);
        $product->set_downloadable(false);
        
        // Set visibility and status
        $product->set_catalog_visibility($is_virtual ? 'hidden' : 'visible');
        $product->set_status($is_virtual ? 'private' : 'publish');
        
        // Set stock status
        $product->set_manage_stock(false);
        $product->set_stock_status('instock');
        
        // Save product to get ID
        $product_id = $product->save();
        
        if (!$product_id) {
            return false;
        }
        
        // Add metadata using correct field names
        update_post_meta($product_id, 'price_point', $pricing['points_price']);
        update_post_meta($product_id, 'point_earned', $pricing['points_earned']);
        update_post_meta($product_id, 'herbal_mix_id', $mix->id);
        update_post_meta($product_id, '_custom_mix_user', $mix->user_id);
        update_post_meta($product_id, '_custom_mix_author', $this->get_user_nickname($mix->user_id));
        update_post_meta($product_id, 'herbal_mix_data', $mix->mix_data);
        update_post_meta($product_id, '_custom_mix_created', current_time('mysql'));
        
        // Handle product image
        if (!empty($mix->mix_image)) {
            $image_id = attachment_url_to_postid(esc_url($mix->mix_image));
            if ($image_id) {
                set_post_thumbnail($product_id, $image_id);
            } else {
                $this->set_image_from_url($product_id, $mix->mix_image);
            }
        }
        
        // Add to Custom Mix category for public products
        if (!$is_virtual) {
            $term = get_term_by('slug', 'custom-mix', 'product_cat');
            if ($term) {
                wp_set_post_terms($product_id, array($term->term_id), 'product_cat');
            }
        }
        
        return $product_id;
    }

    /**
     * Get user's nickname
     */
    private function get_user_nickname($user_id) {
        $nickname = get_user_meta($user_id, 'nickname', true);
        if (empty($nickname)) {
            $user = get_user_by('id', $user_id);
            $nickname = $user ? $user->display_name : __('Anonymous', 'herbal-mix-creator2');
        }
        return $nickname;
    }

    /**
     * Set product image from URL
     */
    private function set_image_from_url($product_id, $image_url) {
        // Get image data
        $image_data = file_get_contents($image_url);
        if (!$image_data) {
            return false;
        }
        
        // Get filename from URL
        $filename = basename($image_url);
        
        // Create unique filename
        $unique_filename = wp_unique_filename(wp_upload_dir()['path'], $filename);
        $upload_file = wp_upload_bits($unique_filename, null, $image_data);
        
        if ($upload_file['error']) {
            return false;
        }
        
        // Prepare attachment data
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Insert attachment to database
        $attachment_id = wp_insert_attachment($attachment, $upload_file['file'], $product_id);
        if (!$attachment_id) {
            return false;
        }
        
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // Set product featured image
        set_post_thumbnail($product_id, $attachment_id);
        
        return $attachment_id;
    }

    /**
     * Display herbal mix details on product page
     */
    public function display_herbal_mix_details() {
        global $product;
        
        if (!$product) return;
        
        $product_id = $product->get_id();
        
        // Check if this is a mix
        $herbal_mix_id = get_post_meta($product_id, 'herbal_mix_id', true);
        if (!$herbal_mix_id) return;
        
        // Get author data
        $author_id = get_post_meta($product_id, '_custom_mix_user', true);
        $author_name = get_post_meta($product_id, '_custom_mix_author', true);
        
        if (empty($author_name) && $author_id) {
            $nickname = get_user_meta($author_id, 'nickname', true);
            $author_name = !empty($nickname) ? $nickname : get_user_by('id', $author_id)->display_name;
        }
        
        // Get mix data
        $mix_data_json = get_post_meta($product_id, 'herbal_mix_data', true);
        $mix_data = json_decode($mix_data_json, true);
        
        // Display author data
        if ($author_name) {
            echo '<div class="herbal-mix-author">';
            echo '<h4>' . __('Mix Creator', 'herbal-mix-creator2') . '</h4>';
            echo '<p>' . esc_html($author_name) . '</p>';
            echo '</div>';
        }
        
        // Display ingredients (without proportions)
        if (isset($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
            echo '<div class="herbal-mix-ingredients">';
            echo '<h4>' . __('Ingredients', 'herbal-mix-creator2') . '</h4>';
            echo '<ul>';
            
            global $wpdb;
            $ingredients_table = $wpdb->prefix . 'herbal_ingredients';
            
            foreach ($mix_data['ingredients'] as $ingredient) {
                if (isset($ingredient['id'])) {
                    // Get ingredient name
                    $ingredient_name = $wpdb->get_var($wpdb->prepare(
                        "SELECT name FROM $ingredients_table WHERE id = %d",
                        $ingredient['id']
                    ));
                    
                    if ($ingredient_name) {
                        echo '<li>' . esc_html($ingredient_name) . '</li>';
                    } elseif (isset($ingredient['name'])) {
                        // Use name from mix data if ingredient doesn't exist in database
                        echo '<li>' . esc_html($ingredient['name']) . '</li>';
                    }
                }
            }
            
            echo '</ul>';
            echo '</div>';
        }
        
        // Add styles
        echo '<style>
            .herbal-mix-author, .herbal-mix-ingredients {
                margin-bottom: 20px;
                padding: 15px;
                background-color: #f9f9f9;
                border-radius: 5px;
            }
            .herbal-mix-author h4, .herbal-mix-ingredients h4 {
                margin-top: 0;
                margin-bottom: 10px;
                color: #2a6a3c;
            }
            .herbal-mix-ingredients ul {
                margin: 0;
                padding-left: 20px;
            }
            .herbal-mix-ingredients li {
                margin-bottom: 5px;
            }
        </style>';
    }
}

// Initialize class
new HerbalMixActions();
