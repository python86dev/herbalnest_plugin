<?php
/**
 * Herbal Mix Product Meta - WooCommerce Product Fields Integration
 * CORRECTED VERSION - Only field names updated to match database
 * 
 * FIXED: Meta field names changed from:
 * - '_price_points' → 'price_point'
 * - '_points_earned' → 'point_earned'
 * - '_herbal_mix_id' → 'herbal_mix_id'
 * - '_custom_mix_data' → 'herbal_mix_data'
 * 
 * All other functionality preserved as original
 */

if (!defined('ABSPATH')) {
    exit;
}

class Herbal_Mix_Product_Meta {

    /**
     * Constructor
     */
    public function __construct() {
        // Add hooks for WooCommerce product meta
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_custom_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_custom_product_fields'));
        
        // Add custom tab for mix details
        add_filter('woocommerce_product_data_tabs', array($this, 'add_herbal_mix_product_data_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_herbal_mix_product_data_fields'));
        
        // Display meta in frontend
        add_action('woocommerce_single_product_summary', array($this, 'display_product_points_info'), 25);
        add_action('woocommerce_single_product_summary', array($this, 'display_herbal_mix_details'), 35);
        
        // Add to cart item data
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_custom_fields'), 10, 2);
        
        // Admin styling
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        global $post_type;
        if ('product' !== $post_type) {
            return;
        }

        wp_enqueue_style(
            'herbal-mix-admin',
            plugin_dir_url(__FILE__) . 'assets/css/herbal-mix-admin.css',
            array(),
            time()
        );
    }

    /**
     * Adds custom product fields to WooCommerce product edit page.
     * FIXED: Updated field names to match database columns
     */
    public function add_custom_product_fields() {
        echo '<div class="options_group">';

        // Field: Points Awarded on Purchase
        // FIXED: Use 'point_earned' instead of '_points_earned'
        woocommerce_wp_text_input( array(
            'id'                => 'point_earned',
            'label'             => __( 'Points Awarded on Purchase', 'herbal-mix-creator2' ),
            'desc_tip'          => 'true',
            'description'       => __( 'Enter the number of reward points granted when purchasing this product.', 'herbal-mix-creator2' ),
            'type'              => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0',
            ),
        ) );

        // Field: Price in Points
        // FIXED: Use 'price_point' instead of '_price_points'
        woocommerce_wp_text_input( array(
            'id'                => 'price_point',
            'label'             => __( 'Price in Points', 'herbal-mix-creator2' ),
            'desc_tip'          => 'true',
            'description'       => __( 'Enter the product price expressed in reward points.', 'herbal-mix-creator2' ),
            'type'              => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0',
            ),
        ) );

        echo '</div>';
    }

    /**
     * Saves the custom product fields data.
     * FIXED: Updated to use correct meta field names
     *
     * @param int $post_id The product ID.
     */
    public function save_custom_product_fields( $post_id ) {
        // FIXED: Use correct field names matching database columns
        $points_earned = isset( $_POST['point_earned'] ) ? sanitize_text_field( $_POST['point_earned'] ) : '';
        update_post_meta( $post_id, 'point_earned', $points_earned );

        $price_points = isset( $_POST['price_point'] ) ? sanitize_text_field( $_POST['price_point'] ) : '';
        update_post_meta( $post_id, 'price_point', $price_points );
    }
    
    /**
     * Add a tab for herbal mix data in product data metabox
     *
     * @param array $tabs Existing product tabs
     * @return array Modified tabs array
     */
    public function add_herbal_mix_product_data_tab($tabs) {
        $tabs['herbal_mix'] = array(
            'label'    => __('Mix Details', 'herbal-mix-creator2'),
            'target'   => 'herbal_mix_product_data',
            'class'    => array('show_if_simple'),
            'priority' => 21
        );
        return $tabs;
    }
    
    /**
     * Add content to herbal mix tab
     * FIXED: Updated to use correct meta field names
     */
    public function add_herbal_mix_product_data_fields() {
        global $post;
        
        // Check if this is a mix - FIXED: Use correct field name
        $herbal_mix_id = get_post_meta($post->ID, 'herbal_mix_id', true);
        
        // Get mix data - FIXED: Use correct field name
        $mix_data_json = get_post_meta($post->ID, 'herbal_mix_data', true);
        $mix_data = !empty($mix_data_json) ? json_decode($mix_data_json, true) : array();
        
        // Get packaging ID
        $packaging_id = isset($mix_data['packaging_id']) ? $mix_data['packaging_id'] : 0;
        
        // Get packaging options from database
        global $wpdb;
        $packaging_table = $wpdb->prefix . 'herbal_packaging';
        $packaging_options = $wpdb->get_results("SELECT id, name, herb_capacity FROM $packaging_table WHERE available = 1 ORDER BY herb_capacity ASC", ARRAY_A);
        
        // Get ingredient options from database
        $ingredients_table = $wpdb->prefix . 'herbal_ingredients';
        $ingredients_options = $wpdb->get_results("SELECT id, name, price FROM $ingredients_table WHERE visible = 1 ORDER BY name ASC", ARRAY_A);
        
        // Get existing ingredients
        $ingredients = isset($mix_data['ingredients']) && is_array($mix_data['ingredients']) ? $mix_data['ingredients'] : array();
        
        ?>
        <div id="herbal_mix_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                // Mix ID field (read-only)
                woocommerce_wp_text_input( array(
                    'id'            => 'herbal_mix_id',
                    'label'         => __('Mix ID', 'herbal-mix-creator2'),
                    'desc_tip'      => 'true',
                    'description'   => __('ID of the herbal mix.', 'herbal-mix-creator2'),
                    'type'          => 'text',
                    'value'         => $herbal_mix_id,
                    'custom_attributes' => array('readonly' => 'readonly')
                ) );

                // Mix author field
                $mix_author = get_post_meta($post->ID, 'custom_mix_author', true);
                woocommerce_wp_text_input( array(
                    'id'            => 'custom_mix_author',
                    'label'         => __('Mix Creator', 'herbal-mix-creator2'),
                    'desc_tip'      => 'true',
                    'description'   => __('User who created this herbal mix.', 'herbal-mix-creator2'),
                    'type'          => 'text',
                    'value'         => $mix_author
                ) );

                // Packaging selection
                ?>
                <p class="form-field">
                    <label for="herbal_packaging_id"><?php _e('Package Type', 'herbal-mix-creator2'); ?></label>
                    <select id="herbal_packaging_id" name="herbal_packaging_id" class="select short">
                        <option value=""><?php _e('Select packaging...', 'herbal-mix-creator2'); ?></option>
                        <?php foreach ($packaging_options as $package): ?>
                            <option value="<?php echo esc_attr($package['id']); ?>" 
                                    <?php selected($packaging_id, $package['id']); ?>>
                                <?php echo esc_html($package['name'] . ' (' . $package['herb_capacity'] . 'g)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description"><?php _e('The packaging used for this herbal mix.', 'herbal-mix-creator2'); ?></span>
                </p>
                <?php
                ?>
            </div>

            <?php if (!empty($ingredients)): ?>
            <div class="options_group">
                <h3><?php _e('Mix Ingredients', 'herbal-mix-creator2'); ?></h3>
                <div class="herbal-ingredients-list">
                    <?php foreach ($ingredients as $ingredient): ?>
                        <div class="ingredient-item">
                            <strong><?php echo esc_html($ingredient['name']); ?></strong>
                            <?php if (isset($ingredient['weight'])): ?>
                                - <?php echo esc_html($ingredient['weight']); ?>g
                            <?php endif; ?>
                            <?php if (isset($ingredient['percentage'])): ?>
                                (<?php echo esc_html($ingredient['percentage']); ?>%)
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="options_group">
                <h3><?php _e('Pricing Information', 'herbal-mix-creator2'); ?></h3>
                
                <?php
                // FIXED: Display current points pricing using correct field names
                $current_price_point = get_post_meta($post->ID, 'price_point', true);
                $current_point_earned = get_post_meta($post->ID, 'point_earned', true);
                ?>
                
                <p class="form-field">
                    <label><?php _e('Current Points Price:', 'herbal-mix-creator2'); ?></label>
                    <span class="description">
                        <?php 
                        echo $current_price_point ? 
                            number_format($current_price_point, 0) . ' pts' : 
                            __('Not set', 'herbal-mix-creator2'); 
                        ?>
                    </span>
                </p>
                
                <p class="form-field">
                    <label><?php _e('Current Points Earned:', 'herbal-mix-creator2'); ?></label>
                    <span class="description">
                        <?php 
                        echo $current_point_earned ? 
                            number_format($current_point_earned, 0) . ' pts' : 
                            __('Not set', 'herbal-mix-creator2'); 
                        ?>
                    </span>
                </p>
            </div>

            <div class="options_group">
                <h3><?php _e('Mix Data (JSON)', 'herbal-mix-creator2'); ?></h3>
                <p class="form-field">
                    <label for="herbal_mix_data_display"><?php _e('Raw Mix Data', 'herbal-mix-creator2'); ?></label>
                    <textarea id="herbal_mix_data_display" 
                              rows="10" 
                              cols="50" 
                              readonly
                              style="width: 100%; font-family: monospace; font-size: 12px;">
<?php echo esc_textarea(json_encode($mix_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
                    </textarea>
                    <span class="description"><?php _e('Complete mix data stored in the database (read-only).', 'herbal-mix-creator2'); ?></span>
                </p>
            </div>
        </div>
        
        <style>
        .herbal-ingredients-list {
            background: #f9f9f9;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .ingredient-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .ingredient-item:last-child {
            border-bottom: none;
        }
        </style>
        <?php
    }
    
    /**
     * Display product points information on single product page
     * FIXED: Updated to use correct meta field names
     */
    public function display_product_points_info() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        // FIXED: Use correct meta field names matching database columns
        $points_cost = get_post_meta($product->get_id(), 'price_point', true);
        $points_earned = get_post_meta($product->get_id(), 'point_earned', true);
        
        if ($points_cost || $points_earned) {
            echo '<div class="herbal-product-points-info">';
            
            if ($points_cost) {
                echo '<p class="points-cost">';
                echo sprintf(
                    __('Alternative payment: %s pts', 'herbal-mix-creator2'),
                    number_format($points_cost, 0)
                );
                echo '</p>';
            }
            
            if ($points_earned) {
                echo '<p class="points-earned">';
                echo sprintf(
                    __('Earn %s reward points with this purchase', 'herbal-mix-creator2'),
                    number_format($points_earned, 0)
                );
                echo '</p>';
            }
            
            echo '</div>';
        }
    }
    
    /**
     * Display herbal mix details on single product page
     * FIXED: Updated to use correct meta field names
     */
    public function display_herbal_mix_details() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        
        // Check if this is a herbal mix - FIXED: Use correct meta field name
        $herbal_mix_id = get_post_meta($product_id, 'herbal_mix_id', true);
        if (!$herbal_mix_id) {
            return;
        }
        
        // Get author data - FIXED: Use correct meta field names
        $author_id = get_post_meta($product_id, 'custom_mix_user', true);
        $author_name = get_post_meta($product_id, 'custom_mix_author', true);
        
        if (empty($author_name) && $author_id) {
            $user = get_user_by('id', $author_id);
            if ($user) {
                $nickname = get_user_meta($author_id, 'nickname', true);
                $author_name = !empty($nickname) ? $nickname : $user->display_name;
            }
        }
        
        // Get mix data - FIXED: Use correct meta field name
        $mix_data_json = get_post_meta($product_id, 'herbal_mix_data', true);
        $mix_data = json_decode($mix_data_json, true);
        
        if (!$mix_data) {
            return;
        }
        
        echo '<div class="herbal-mix-details">';
        
        // Display author data
        if ($author_name) {
            echo '<div class="herbal-mix-author">';
            echo '<h4>' . __('Mix Creator', 'herbal-mix-creator2') . '</h4>';
            echo '<p>' . esc_html($author_name) . '</p>';
            echo '</div>';
        }
        
        // Display ingredients (without exact proportions for IP protection)
        if (isset($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
            echo '<div class="herbal-mix-ingredients">';
            echo '<h4>' . __('Ingredients', 'herbal-mix-creator2') . '</h4>';
            echo '<ul>';
            
            foreach ($mix_data['ingredients'] as $ingredient) {
                if (isset($ingredient['name'])) {
                    echo '<li>' . esc_html($ingredient['name']) . '</li>';
                }
            }
            
            echo '</ul>';
            echo '</div>';
        }
        
        // Display packaging information
        if (isset($mix_data['packaging']) && is_array($mix_data['packaging'])) {
            echo '<div class="herbal-mix-packaging">';
            echo '<h4>' . __('Package', 'herbal-mix-creator2') . '</h4>';
            echo '<p>' . esc_html($mix_data['packaging']['name']) . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Display custom fields in cart and checkout
     * FIXED: Updated to use correct meta field names
     */
    public function display_cart_item_custom_fields($item_data, $cart_item) {
        // Check for herbal mix data
        if (isset($cart_item['herbal_mix_data'])) {
            $mix_data = $cart_item['herbal_mix_data'];
            
            // Add mix creator info
            if (isset($mix_data['mix_name'])) {
                $item_data[] = array(
                    'key'   => __('Mix Name', 'herbal-mix-creator2'),
                    'value' => esc_html($mix_data['mix_name'])
                );
            }
            
            // Add ingredients
            if (isset($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
                $ingredients_names = array();
                foreach ($mix_data['ingredients'] as $ingredient) {
                    if (isset($ingredient['name'])) {
                        $ingredients_names[] = $ingredient['name'];
                    }
                }
                
                if (!empty($ingredients_names)) {
                    $item_data[] = array(
                        'key'   => __('Ingredients', 'herbal-mix-creator2'),
                        'value' => implode(', ', $ingredients_names)
                    );
                }
            }
            
            // Add packaging info
            if (isset($mix_data['packaging']['name'])) {
                $item_data[] = array(
                    'key'   => __('Package', 'herbal-mix-creator2'),
                    'value' => esc_html($mix_data['packaging']['name'])
                );
            }
        } else {
            // For regular products, check if they have points data
            $product = $cart_item['data'];
            if ($product && $product->get_id() > 0) {
                // FIXED: Use correct meta field names
                $points_cost = get_post_meta($product->get_id(), 'price_point', true);
                $points_earned = get_post_meta($product->get_id(), 'point_earned', true);
                
                if ($points_cost && $points_cost > 0) {
                    $item_data[] = array(
                        'key'   => __('Points Cost', 'herbal-mix-creator2'),
                        'value' => number_format($points_cost, 0) . ' pts'
                    );
                }
                
                if ($points_earned && $points_earned > 0) {
                    $item_data[] = array(
                        'key'   => __('Points Earned', 'herbal-mix-creator2'),
                        'value' => number_format($points_earned, 0) . ' pts'
                    );
                }
            }
        }
        
        return $item_data;
    }
}