<?php
/**
 * Handles actions for user-created herbal mixes: Buy, Publish, View, Delete.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HerbalMixActions {

    public function __construct() {
        add_action( 'init', array( $this, 'handle_mix_actions' ) );
        
        // Add display of author and ingredients on product page
        add_action('woocommerce_single_product_summary', array($this, 'display_herbal_mix_details'), 25);
    }

    public function handle_mix_actions() {
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
            wp_die( 'Mix not found or permission denied.' );
        }

        switch ( $action ) {
            case 'buy_mix':
                $this->create_virtual_product_and_add_to_cart( $mix );
                break;
            case 'publish_mix':
                $product_id = $this->create_public_product( $mix );
                if ($product_id) {
                    wp_redirect( get_permalink( $product_id ) );
                    exit;
                }
                wp_die( 'Failed to publish product.' );
                break;
            case 'delete_mix':
                // NOWE: Obsługa usuwania mieszanki przez GET (fallback)
                $this->handle_delete_mix_get( $mix, $user_id );
                break;
            case 'view_mix':
                $edit_url = add_query_arg( array( 'mix_id' => $mix_id ), site_url( '/edit-my-mix' ) );
                wp_redirect( $edit_url );
                exit;
        }
    }

    /**
     * NOWA METODA: Obsługuje usuwanie mieszanki przez GET request (fallback)
     */
    private function handle_delete_mix_get( $mix, $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Sprawdź status mieszanki
        if ($mix->status === 'published') {
            // Mieszanka publiczna - wyślij email do administratora
            $this->send_published_mix_deletion_notification_get( $mix, $user_id );
        }
        
        // Usuń mieszankę z bazy
        $wpdb->delete( $table, array( 'id' => $mix->id ) );
        
        // Przekieruj do strony My Mixes z komunikatem
        $redirect_url = $this->get_my_mixes_url_with_message( $mix->status );
        wp_redirect( $redirect_url );
        exit;
    }

    /**
     * NOWA METODA: Wysyła powiadomienie email (wersja dla GET request)
     */
    private function send_published_mix_deletion_notification_get( $mix, $user_id ) {
        // Pobierz informacje o użytkowniku
        $user = get_user_by('id', $user_id);
        $user_name = $user ? ($user->display_name ?: $user->user_login) : 'Unknown User';
        $user_email = $user ? $user->user_email : 'unknown@example.com';
        
        // Przygotuj dane mieszanki
        $mix_data = json_decode($mix->mix_data, true);
        
        // Email administratora
        $admin_email = get_option('admin_email');
        
        // Temat emaila
        $subject = sprintf(
            '[%s] Published Mix Deletion Request - %s',
            get_bloginfo('name'),
            $mix->mix_name
        );
        
        // Treść emaila
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
        
        // Nagłówki emaila
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', get_bloginfo('name'), get_option('admin_email')),
            sprintf('Reply-To: %s <%s>', $user_name, $user_email)
        );
        
        // Wyślij email
        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * NOWA METODA: Pobiera URL do My Mixes z komunikatem
     */
    private function get_my_mixes_url_with_message( $mix_status ) {
        $my_account_url = wc_get_page_permalink('myaccount');
        
        if ($my_account_url) {
            $url = trailingslashit($my_account_url) . 'my-mixes/';
            
            // Dodaj komunikat w zależności od statusu
            if ($mix_status === 'published') {
                $url = add_query_arg('deleted', 'published', $url);
            } else {
                $url = add_query_arg('deleted', 'success', $url);
            }
            
            return $url;
        }
        
        // Fallback
        return wc_get_page_permalink('myaccount') ?: home_url();
    }

    private function create_virtual_product_and_add_to_cart( $mix ) {
        $product_id = $this->generate_product_from_mix( $mix, true );
        if ( $product_id ) {
            WC()->cart->add_to_cart( $product_id );
            wp_redirect( wc_get_cart_url() );
            exit;
        }
        wp_die( 'Failed to create virtual product.' );
    }

    /**
     * Creates a public product from a user's mix
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
        
        // Set product properties - CHANGED FROM VIRTUAL TO PHYSICAL
        $product->set_virtual(false); // This is a physical product that requires shipping
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
        
        // Get packaging information
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
            
            if ($packaging) {
                // You can add additional fields to packaging table to store these values
                // For now we'll use default values based on capacity
                if (isset($packaging->herb_capacity)) {
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
        }
        
        // Set shipping parameters
        $product->set_weight($total_weight + $packaging_weight); // Weight in grams (sum of ingredients + packaging)
        $product->set_length($packaging_dimensions['length']);
        $product->set_width($packaging_dimensions['width']);
        $product->set_height($packaging_dimensions['height']);
        
        // Optionally set shipping class
        // $product->set_shipping_class_id(10); // Change to your shipping class ID if needed
        
        // Set SKU
        $product->set_sku('ECM-' . $mix->id);
        
        // Save product to get ID
        $product_id = $product->save();
        
        // Add product metadata
        update_post_meta($product_id, '_price_points', round($points_price, 2));
        update_post_meta($product_id, '_points_earned', $points_earned);
        update_post_meta($product_id, '_herbal_mix_id', $mix->id);
        update_post_meta($product_id, '_custom_mix_user', $mix->user_id);
        update_post_meta($product_id, '_custom_mix_author', $this->get_user_nickname($mix->user_id));
        
        // Save full mix data in metadata
        update_post_meta($product_id, '_custom_mix_data', $mix->mix_data);
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
        
        // Return product ID
        return $product_id;
    }

    /**
     * Calculate prices and points for a mix based on current data
     *
     * @param array $mix_data Mix data
     * @return array Prices and points: ['price', 'points_price', 'points_earned']
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
     * Get user's nickname
     *
     * @param int $user_id User ID
     * @return string Nickname or "Unknown User"
     */
    private function get_user_nickname($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return __('Unknown User', 'herbal-mix-creator2');
        }
        
        $nickname = get_user_meta($user_id, 'nickname', true);
        if (empty($nickname)) {
            // Fallback to display_name if nickname not set
            return $user->display_name;
        }
        
        return $nickname;
    }

    /**
     * Set product image from URL
     *
     * @param int $product_id Product ID
     * @param string $image_url Image URL
     * @return int|false Attachment ID or false on error
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

    // This function remains but uses your new `create_public_product` implementation instead
    private function generate_product_from_mix($mix, $is_virtual = true) {
        if ($is_virtual) {
            // For virtual products (adding to cart), we'll keep the original behavior
            $template_id = 82;
            $template_product = wc_get_product($template_id);
            if (!$template_product) return false;

            if (empty($mix->mix_data)) return false;
            $mix_data = json_decode($mix->mix_data, true);
            if (!is_array($mix_data)) return false;

            $total_price = 0;
            $total_points_price = 0;
            $total_earned_points = 0;

            global $wpdb;
            $ingredients_table = $wpdb->prefix . 'herbal_ingredients';

            foreach ($mix_data as $ingredient_id => $grams) {
                $ingredient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ingredients_table WHERE id = %d", intval($ingredient_id)));
                if ($ingredient) {
                    $total_price += floatval($ingredient->price) * $grams;
                    $total_points_price += floatval($ingredient->price_points) * $grams;
                    $total_earned_points += intval($ingredient->points_earned) * $grams;
                }
            }

            $new_product = new WC_Product_Simple();
            $new_product->set_name($mix->mix_name);
            $new_product->set_description($mix->mix_description ?? 'Custom herbal mix created by user.');
            $new_product->set_regular_price(round($total_price, 2));
            $new_product->set_virtual(true); // Keep virtual for cart products
            $new_product->set_catalog_visibility($is_virtual ? 'hidden' : 'visible');
            $new_product->set_status($is_virtual ? 'private' : 'publish');
            $new_product->save();

            $product_id = $new_product->get_id();
            update_post_meta($product_id, '_price_points', round($total_points_price, 2));
            update_post_meta($product_id, '_points_earned', $total_earned_points);
            update_post_meta($product_id, '_herbal_mix_id', $mix->id);
            update_post_meta($product_id, '_custom_mix_user', $mix->user_id);

            if (!empty($mix->mix_image)) {
                $image_id = attachment_url_to_postid(esc_url($mix->mix_image));
                if ($image_id) {
                    set_post_thumbnail($product_id, $image_id);
                }
            }

            return $product_id;
        } else {
            // For public products, use the new implementation
            return $this->create_public_product($mix);
        }
    }

    public function display_herbal_mix_details() {
        global $product;
        
        if (!$product) return;
        
        $product_id = $product->get_id();
        
        // Check if this is a mix
        $herbal_mix_id = get_post_meta($product_id, '_herbal_mix_id', true);
        if (!$herbal_mix_id) return;
        
        // Get author data
        $author_id = get_post_meta($product_id, '_custom_mix_user', true);
        $author_name = get_post_meta($product_id, '_custom_mix_author', true);
        
        if (empty($author_name) && $author_id) {
            $nickname = get_user_meta($author_id, 'nickname', true);
            $author_name = !empty($nickname) ? $nickname : get_user_by('id', $author_id)->display_name;
        }
        
        // Get mix data
        $mix_data_json = get_post_meta($product_id, '_custom_mix_data', true);
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