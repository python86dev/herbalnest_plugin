<?php
/**
 * Herbal Mix Product Meta - Simple Version
 * WooCommerce Product Fields with ingredients as meta fields
 * 
 * File: includes/class-herbal-mix-product-meta.php
 * 
 * SIMPLE APPROACH:
 * - Ingredients stored as product meta fields (JSON)
 * - No connection to herbal_mixes table for products
 * - Simple interface like packaging selection
 * - Auto-save on product publish
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
        
        // Add custom tabs for mix details
        add_filter('woocommerce_product_data_tabs', array($this, 'add_herbal_mix_story_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_herbal_mix_story_fields'));
        
        add_filter('woocommerce_product_data_tabs', array($this, 'add_herbal_ingredients_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_herbal_ingredients_fields'));
        
        // Display meta in frontend
        add_action('woocommerce_single_product_summary', array($this, 'display_product_points_info'), 25);
        add_action('woocommerce_single_product_summary', array($this, 'display_herbal_mix_details'), 35);
        
        // Add to cart item data
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_custom_fields'), 10, 2);
        
        // Admin styling and scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_assets($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        global $post_type;
        if ('product' !== $post_type) {
            return;
        }

        // Enqueue jQuery first
        wp_enqueue_script('jquery');

        // Add admin CSS
        $css = '
            .herbal-meta-section {
                margin-bottom: 20px;
                padding: 20px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 6px;
            }
            .ingredients-container {
                margin-bottom: 15px;
            }
            .ingredient-row {
                display: flex;
                align-items: center;
                margin-bottom: 12px;
                padding: 12px;
                background: #f8f9fa;
                border: 1px solid #e1e5e9;
                border-radius: 6px;
                gap: 12px;
            }
            .ingredient-row:hover {
                background: #e9ecef;
            }
            .ingredient-select {
                flex: 2;
                min-width: 200px;
                height: 36px;
            }
            .ingredient-weight {
                width: 120px;
                height: 36px;
                text-align: center;
                font-weight: 500;
            }
            .ingredient-remove {
                color: #dc3545;
                cursor: pointer;
                text-decoration: none;
                padding: 8px 12px;
                background: #fff;
                border: 1px solid #dc3545;
                border-radius: 4px;
                font-weight: 500;
                transition: all 0.2s;
                min-width: 80px;
                text-align: center;
            }
            .ingredient-remove:hover {
                background: #dc3545;
                color: #fff;
                text-decoration: none;
            }
            .add-ingredient-btn {
                background: #0073aa;
                color: #fff;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 500;
                margin-top: 15px;
            }
            .add-ingredient-btn:hover {
                background: #005a87;
                color: #fff;
            }
            .ingredients-total {
                background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
                border: 1px solid #c3e6cb;
                padding: 20px;
                border-radius: 8px;
                margin-top: 20px;
            }
            .ingredients-total h4 {
                margin: 0 0 15px 0;
                color: #155724;
                font-size: 18px;
                text-align: center;
            }
            .totals-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 15px;
                margin-top: 15px;
            }
            .total-item {
                text-align: center;
                background: rgba(255, 255, 255, 0.8);
                padding: 15px 10px;
                border-radius: 6px;
                border: 1px solid rgba(21, 87, 36, 0.1);
            }
            .total-value {
                font-size: 22px;
                font-weight: bold;
                color: #155724;
                line-height: 1.2;
                margin-bottom: 5px;
            }
            .total-label {
                font-size: 12px;
                color: #6c757d;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                font-weight: 500;
            }
            .ingredient-row-number {
                background: #0073aa;
                color: #fff;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: bold;
                flex-shrink: 0;
            }
            @media (max-width: 782px) {
                .ingredient-row {
                    flex-direction: column;
                    gap: 8px;
                }
                .ingredient-select, .ingredient-weight {
                    width: 100%;
                }
                .totals-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        ';
        wp_add_inline_style('woocommerce_admin_styles', $css);
    }

    /**
     * Add custom product fields to WooCommerce product edit page
     */
    public function add_custom_product_fields() {
        echo '<div class="options_group">';
        echo '<h3>🎯 ' . esc_html__('Herbal Mix Points System', 'herbal-mix-creator2') . '</h3>';

        // Field: Points Awarded on Purchase
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
        
        echo '<div class="options_group">';
        echo '<h3>👤 ' . esc_html__('Mix Creator Information', 'herbal-mix-creator2') . '</h3>';

        // Field: Mix Creator ID
        woocommerce_wp_text_input( array(
            'id'                => 'mix_creator_id',
            'label'             => __( 'Mix Creator ID', 'herbal-mix-creator2' ),
            'desc_tip'          => 'true',
            'description'       => __( 'WordPress User ID who created this herbal mix.', 'herbal-mix-creator2' ),
            'type'              => 'number',
            'custom_attributes' => array(
                'min'  => '0',
            ),
        ) );

        // Field: Mix Creator Name
        woocommerce_wp_text_input( array(
            'id'          => 'mix_creator_name',
            'label'       => __( 'Mix Creator Name', 'herbal-mix-creator2' ),
            'desc_tip'    => 'true',
            'description' => __( 'Display name of the user who created this mix.', 'herbal-mix-creator2' ),
        ) );

        echo '</div>';
        
        echo '<div class="options_group">';
        echo '<h3>📦 ' . esc_html__('Packaging Selection', 'herbal-mix-creator2') . '</h3>';

        // Field: Packaging ID (like before)
        woocommerce_wp_select( array(
            'id'      => 'packaging_id',
            'label'   => __( 'Selected Packaging', 'herbal-mix-creator2' ),
            'options' => $this->get_packaging_options(),
            'desc_tip' => 'true',
            'description' => __( 'The packaging used for this herbal mix.', 'herbal-mix-creator2' ),
        ) );

        echo '</div>';
    }

    /**
     * Add tab for mix story
     */
    public function add_herbal_mix_story_tab($tabs) {
        $tabs['herbal_story'] = array(
            'label'    => __('Mix Story & History', 'herbal-mix-creator2'),
            'target'   => 'herbal_story_product_data',
            'class'    => array('show_if_simple'),
            'priority' => 22
        );
        return $tabs;
    }

    /**
     * Add content to mix story tab
     */
    public function add_herbal_mix_story_fields() {
        echo '<div id="herbal_story_product_data" class="panel woocommerce_options_panel">';
        echo '<div class="herbal-meta-section">';
        echo '<h3>📖 ' . esc_html__('Mix Story & History', 'herbal-mix-creator2') . '</h3>';
        echo '<p style="color: #6c757d; margin-bottom: 20px;">' . esc_html__('Share the story behind this herbal mix - its origins, benefits, inspiration, or traditional uses.', 'herbal-mix-creator2') . '</p>';
        
        // Mix Story field
        woocommerce_wp_textarea_input( array(
            'id'          => 'mix_story',
            'label'       => __( 'Mix Story/History', 'herbal-mix-creator2' ),
            'placeholder' => __( 'Enter the background story of this herbal mix...', 'herbal-mix-creator2' ),
            'desc_tip'    => 'true',
            'description' => __( 'Tell the story behind this herbal mix - its origins, benefits, or inspiration.', 'herbal-mix-creator2' ),
            'rows'        => 8,
            'style'       => 'width: 100%;',
        ) );
        
        echo '<div style="margin-top: 15px; padding: 12px; background: #e7f3ff; border-left: 4px solid #2196f3; border-radius: 4px;">';
        echo '<strong>💡 ' . esc_html__('Tip:', 'herbal-mix-creator2') . '</strong> ';
        echo esc_html__('A compelling story helps customers connect with your product and understand its value. Consider mentioning traditional uses, health benefits, or the inspiration behind the blend.', 'herbal-mix-creator2');
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Add tab for ingredients management
     */
    public function add_herbal_ingredients_tab($tabs) {
        $tabs['herbal_ingredients'] = array(
            'label'    => __('Mix Ingredients', 'herbal-mix-creator2'),
            'target'   => 'herbal_ingredients_product_data',
            'class'    => array('show_if_simple'),
            'priority' => 23
        );
        return $tabs;
    }

    /**
     * Add content to ingredients tab - SIMPLE VERSION
     */
    public function add_herbal_ingredients_fields() {
        global $post;
        
        echo '<div id="herbal_ingredients_product_data" class="panel woocommerce_options_panel">';
        echo '<div class="herbal-meta-section">';
        echo '<h3>🌿 ' . esc_html__('Mix Ingredients', 'herbal-mix-creator2') . '</h3>';
        echo '<p style="color: #6c757d; margin-bottom: 20px;">' . esc_html__('Select ingredients and specify the weight in grams for each component of this herbal mix.', 'herbal-mix-creator2') . '</p>';
        
        // Get current ingredients
        $current_ingredients = get_post_meta($post->ID, 'product_ingredients', true);
        if (!$current_ingredients) {
            $current_ingredients = array(array('id' => '', 'weight' => ''));
        } else {
            $current_ingredients = json_decode($current_ingredients, true);
        }
        
        // Make sure we have at least one empty row
        if (empty($current_ingredients)) {
            $current_ingredients = array(array('id' => '', 'weight' => ''));
        }
        
        echo '<div style="margin-bottom: 20px;">';
        echo '<h4 style="margin: 0 0 15px 0; color: #155724;">📋 ' . esc_html__('Ingredient List', 'herbal-mix-creator2') . '</h4>';
        
        echo '<div class="ingredients-container">';
        
        foreach ($current_ingredients as $index => $ingredient) {
            $this->render_ingredient_row($ingredient, $index);
        }
        
        echo '</div>';
        
        echo '<button type="button" class="button add-ingredient-btn">➕ ' . esc_html__('Add Another Ingredient', 'herbal-mix-creator2') . '</button>';
        echo '</div>';
        
        // Display totals
        $this->display_ingredients_totals($current_ingredients);
        
        // Add JavaScript directly here
        $this->add_ingredients_javascript();
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render single ingredient row
     */
    private function render_ingredient_row($ingredient, $index) {
        $ingredient_id = isset($ingredient['id']) ? $ingredient['id'] : '';
        $weight = isset($ingredient['weight']) ? $ingredient['weight'] : '';
        
        echo '<div class="ingredient-row">';
        
        // Row number
        echo '<div class="ingredient-row-number">' . ($index + 1) . '</div>';
        
        // Ingredient dropdown
        echo '<select name="product_ingredients[' . $index . '][id]" class="ingredient-select">';
        echo '<option value="">' . esc_html__('Select ingredient...', 'herbal-mix-creator2') . '</option>';
        $this->output_ingredients_options($ingredient_id);
        echo '</select>';
        
        // Weight input with unit
        echo '<div style="display: flex; align-items: center; gap: 5px;">';
        echo '<input type="number" name="product_ingredients[' . $index . '][weight]" value="' . esc_attr($weight) . '" placeholder="0" step="0.1" min="0" class="ingredient-weight">';
        echo '<span style="font-weight: 500; color: #6c757d;">g</span>';
        echo '</div>';
        
        // Remove button
        echo '<a href="#" class="ingredient-remove">' . esc_html__('Remove', 'herbal-mix-creator2') . '</a>';
        
        echo '</div>';
    }

    /**
     * Output ingredients options for select
     */
    private function output_ingredients_options($selected_id = '') {
        if (!class_exists('Herbal_Mix_Database')) {
            echo '<option value="">Database class not loaded</option>';
            return;
        }
        
        $categories = Herbal_Mix_Database::get_ingredients_by_category();
        
        if (empty($categories)) {
            echo '<option value="">No ingredients found</option>';
            return;
        }
        
        foreach ($categories as $category) {
            if (!empty($category['ingredients'])) {
                echo '<optgroup label="' . esc_attr($category['name']) . '">';
                foreach ($category['ingredients'] as $ingredient) {
                    $selected = ($ingredient->id == $selected_id) ? 'selected' : '';
                    echo '<option value="' . esc_attr($ingredient->id) . '" ' . $selected;
                    echo ' data-price="' . esc_attr($ingredient->price) . '"';
                    echo ' data-points="' . esc_attr($ingredient->price_point) . '"';
                    echo ' data-earned="' . esc_attr($ingredient->point_earned) . '">';
                    echo esc_html($ingredient->name) . ' (£' . number_format($ingredient->price, 3) . '/g)';
                    echo '</option>';
                }
                echo '</optgroup>';
            }
        }
    }

    /**
     * Display ingredients totals
     */
    private function display_ingredients_totals($ingredients) {
        echo '<div class="ingredients-total">';
        echo '<h4>🧮 ' . esc_html__('Mix Totals Summary', 'herbal-mix-creator2') . '</h4>';
        
        echo '<div class="totals-grid">';
        
        echo '<div class="total-item">';
        echo '<div class="total-value total-weight">0g</div>';
        echo '<div class="total-label">⚖️ ' . esc_html__('Total Weight', 'herbal-mix-creator2') . '</div>';
        echo '</div>';
        
        echo '<div class="total-item">';
        echo '<div class="total-value total-price">£0.00</div>';
        echo '<div class="total-label">💷 ' . esc_html__('Total Cost', 'herbal-mix-creator2') . '</div>';
        echo '</div>';
        
        echo '<div class="total-item">';
        echo '<div class="total-value total-points">0 pts</div>';
        echo '<div class="total-label">🎯 ' . esc_html__('Points Cost', 'herbal-mix-creator2') . '</div>';
        echo '</div>';
        
        echo '<div class="total-item">';
        echo '<div class="total-value total-earned">0 pts</div>';
        echo '<div class="total-label">🎁 ' . esc_html__('Points Earned', 'herbal-mix-creator2') . '</div>';
        echo '</div>';
        
        echo '</div>';
        
        echo '<div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.9); border-radius: 4px; font-size: 12px; color: #6c757d; text-align: center;">';
        echo '📝 ' . esc_html__('Values update automatically as you add ingredients and adjust weights', 'herbal-mix-creator2');
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Add JavaScript for ingredients management
     */
    private function add_ingredients_javascript() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('Herbal Mix JavaScript loaded');
            
            // Update name attributes and row numbers for new rows
            function updateRowIndexes() {
                $('.ingredient-row').each(function(index) {
                    $(this).find('select').attr('name', 'product_ingredients[' + index + '][id]');
                    $(this).find('input').attr('name', 'product_ingredients[' + index + '][weight]');
                    $(this).find('.ingredient-row-number').text(index + 1);
                });
            }
            
            // Add new ingredient row
            $(document).on('click', '.add-ingredient-btn', function(e) {
                e.preventDefault();
                console.log('Add ingredient clicked');
                
                var container = $('.ingredients-container');
                var firstRow = $('.ingredient-row:first');
                var newRow = firstRow.clone();
                
                // Clear values
                newRow.find('select').val('');
                newRow.find('input').val('');
                
                // Append to container
                container.append(newRow);
                
                // Update indexes and numbers
                updateRowIndexes();
                
                // Update totals
                updateTotals();
                
                // Focus on new select
                newRow.find('select').focus();
            });
            
            // Remove ingredient row
            $(document).on('click', '.ingredient-remove', function(e) {
                e.preventDefault();
                console.log('Remove ingredient clicked');
                
                if ($('.ingredient-row').length > 1) {
                    $(this).closest('.ingredient-row').remove();
                    updateRowIndexes();
                    updateTotals();
                } else {
                    // Don't remove last row, just clear it
                    var row = $(this).closest('.ingredient-row');
                    row.find('select').val('');
                    row.find('input').val('');
                    updateTotals();
                }
            });
            
            // Update totals when ingredient or weight changes
            $(document).on('change input', '.ingredient-select, .ingredient-weight', function() {
                console.log('Input changed');
                updateTotals();
            });
            
            // Update totals function
            function updateTotals() {
                console.log('Updating totals...');
                
                var totalWeight = 0;
                var totalPrice = 0;
                var totalPoints = 0;
                var totalEarned = 0;
                
                $('.ingredient-row').each(function() {
                    var select = $(this).find('.ingredient-select');
                    var weight = parseFloat($(this).find('.ingredient-weight').val()) || 0;
                    
                    if (select.val() && weight > 0) {
                        var selectedOption = select.find('option:selected');
                        var price = parseFloat(selectedOption.attr('data-price')) || 0;
                        var points = parseFloat(selectedOption.attr('data-points')) || 0;
                        var earned = parseFloat(selectedOption.attr('data-earned')) || 0;
                        
                        console.log('Ingredient:', selectedOption.text(), 'Weight:', weight, 'Price:', price, 'Points:', points, 'Earned:', earned);
                        
                        totalWeight += weight;
                        totalPrice += (price * weight);
                        totalPoints += (points * weight);
                        totalEarned += (earned * weight);
                    }
                });
                
                console.log('Totals - Weight:', totalWeight, 'Price:', totalPrice, 'Points:', totalPoints, 'Earned:', totalEarned);
                
                // Update display with better formatting
                $('.total-weight').text(totalWeight.toFixed(1) + 'g');
                $('.total-price').text('£' + totalPrice.toFixed(2));
                $('.total-points').text(Math.round(totalPoints).toLocaleString() + ' pts');
                $('.total-earned').text(Math.round(totalEarned).toLocaleString() + ' pts');
                
                // Add visual feedback for totals
                if (totalWeight > 0) {
                    $('.ingredients-total').addClass('has-content');
                } else {
                    $('.ingredients-total').removeClass('has-content');
                }
            }
            
            // Initial setup
            setTimeout(function() {
                updateRowIndexes();
                updateTotals();
            }, 100);
        });
        </script>
        <style>
        .ingredients-total.has-content {
            animation: pulse-green 0.3s ease-in-out;
        }
        @keyframes pulse-green {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        </style>
        <?php
    }

    /**
     * Save all custom product fields
     */
    public function save_custom_product_fields( $post_id ) {
        // Points system fields
        $points_earned = isset( $_POST['point_earned'] ) ? sanitize_text_field( $_POST['point_earned'] ) : '';
        update_post_meta( $post_id, 'point_earned', $points_earned );

        $price_points = isset( $_POST['price_point'] ) ? sanitize_text_field( $_POST['price_point'] ) : '';
        update_post_meta( $post_id, 'price_point', $price_points );
        
        // Mix creator fields
        $creator_id = isset( $_POST['mix_creator_id'] ) ? intval( $_POST['mix_creator_id'] ) : '';
        update_post_meta( $post_id, 'mix_creator_id', $creator_id );
        
        $creator_name = isset( $_POST['mix_creator_name'] ) ? sanitize_text_field( $_POST['mix_creator_name'] ) : '';
        update_post_meta( $post_id, 'mix_creator_name', $creator_name );
        
        // Story field
        $mix_story = isset( $_POST['mix_story'] ) ? sanitize_textarea_field( $_POST['mix_story'] ) : '';
        update_post_meta( $post_id, 'mix_story', $mix_story );

        // Packaging ID
        $packaging_id = isset( $_POST['packaging_id'] ) ? intval( $_POST['packaging_id'] ) : '';
        update_post_meta( $post_id, 'packaging_id', $packaging_id );
        
        // Save ingredients - SIMPLE AS JSON
        if (isset($_POST['product_ingredients']) && is_array($_POST['product_ingredients'])) {
            $ingredients = array();
            
            foreach ($_POST['product_ingredients'] as $ingredient_data) {
                $ingredient_id = intval($ingredient_data['id']);
                $weight = floatval($ingredient_data['weight']);
                
                // Only save if both ID and weight are provided
                if ($ingredient_id > 0 && $weight > 0) {
                    // Get ingredient details for storage
                    $ingredient_details = $this->get_ingredient_details($ingredient_id);
                    if ($ingredient_details) {
                        $ingredients[] = array(
                            'id' => $ingredient_id,
                            'name' => $ingredient_details->name,
                            'weight' => $weight,
                            'price_per_gram' => floatval($ingredient_details->price),
                            'points_per_gram' => floatval($ingredient_details->price_point),
                            'earned_per_gram' => floatval($ingredient_details->point_earned)
                        );
                    }
                }
            }
            
            // Save as JSON in meta field
            update_post_meta($post_id, 'product_ingredients', wp_json_encode($ingredients));
        } else {
            // Remove ingredients if none provided
            delete_post_meta($post_id, 'product_ingredients');
        }
    }

    /**
     * Get ingredient details from database
     */
    private function get_ingredient_details($ingredient_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, price, price_point, point_earned FROM {$wpdb->prefix}herbal_ingredients WHERE id = %d",
            $ingredient_id
        ));
    }

    /**
     * Get packaging options for select field
     */
    private function get_packaging_options() {
        global $wpdb;
        $options = array('' => __('Select packaging...', 'herbal-mix-creator2'));
        
        $packaging = $wpdb->get_results(
            "SELECT id, name, herb_capacity, price, price_point, point_earned 
             FROM {$wpdb->prefix}herbal_packaging 
             WHERE available = 1 
             ORDER BY herb_capacity ASC"
        );
        
        foreach ($packaging as $pack) {
            $options[$pack->id] = sprintf(
                '%s (%dg) - £%.2f / %d pts',
                $pack->name,
                $pack->herb_capacity,
                $pack->price,
                $pack->price_point
            );
        }
        
        return $options;
    }

    /**
     * Display product points information on single product page
     */
    public function display_product_points_info() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $points_cost = get_post_meta($product->get_id(), 'price_point', true);
        $points_earned = get_post_meta($product->get_id(), 'point_earned', true);
        
        if ($points_cost || $points_earned) {
            echo '<div class="herbal-product-points-info" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 20px; margin: 20px 0; border-radius: 12px; border: 1px solid #2196f3; text-align: center;">';
            
            echo '<h4 style="margin: 0 0 15px 0; color: #1565c0; font-size: 18px;">🎯 ' . esc_html__('Reward Points', 'herbal-mix-creator2') . '</h4>';
            
            if ($points_cost) {
                echo '<div style="background: rgba(255,255,255,0.8); padding: 12px; margin: 8px 0; border-radius: 8px; border-left: 4px solid #2196f3;">';
                echo '<strong style="color: #1565c0; font-size: 16px;">🎯 ' . sprintf(
                    esc_html__('Alternative payment: %s points', 'herbal-mix-creator2'),
                    number_format($points_cost, 0)
                ) . '</strong>';
                echo '</div>';
            }
            
            if ($points_earned) {
                echo '<div style="background: rgba(255,255,255,0.8); padding: 12px; margin: 8px 0; border-radius: 8px; border-left: 4px solid #4caf50;">';
                echo '<span style="color: #2e7d32; font-size: 16px;">🎁 ' . sprintf(
                    esc_html__('Earn %s reward points with this purchase', 'herbal-mix-creator2'),
                    number_format($points_earned, 0)
                ) . '</span>';
                echo '</div>';
            }
            
            echo '</div>';
        }
    }

    /**
     * Display herbal mix details on single product page
     */
    public function display_herbal_mix_details() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        
        // Get mix data
        $mix_story = get_post_meta($product_id, 'mix_story', true);
        $creator_name = get_post_meta($product_id, 'mix_creator_name', true);
        $ingredients_json = get_post_meta($product_id, 'product_ingredients', true);
        
        // Only display if we have data
        if (!$mix_story && !$creator_name && !$ingredients_json) {
            return;
        }
        
        echo '<div class="herbal-mix-frontend-details" style="margin: 25px 0; padding: 25px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; border: 1px solid #dee2e6;">';
        
        // Mix creator
        if ($creator_name) {
            echo '<div class="mix-creator" style="margin-bottom: 20px; text-align: center;">';
            echo '<h4 style="margin: 0 0 8px 0; color: #495057; font-size: 16px;">👤 ' . esc_html__('Created by:', 'herbal-mix-creator2') . '</h4>';
            echo '<p style="margin: 0; font-weight: bold; color: #2c3e50; font-size: 18px;">' . esc_html($creator_name) . '</p>';
            echo '</div>';
        }
        
        // Ingredients from meta field
        if ($ingredients_json) {
            $ingredients = json_decode($ingredients_json, true);
            if (!empty($ingredients)) {
                echo '<div class="mix-ingredients" style="margin-bottom: 20px;">';
                echo '<h4 style="margin: 0 0 15px 0; color: #495057; font-size: 16px; text-align: center;">🌿 ' . esc_html__('Ingredients:', 'herbal-mix-creator2') . '</h4>';
                echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">';
                
                $total_weight = 0;
                foreach ($ingredients as $ingredient) {
                    $weight = floatval($ingredient['weight']);
                    $total_weight += $weight;
                    
                    echo '<div style="background: rgba(255,255,255,0.8); padding: 12px; border-radius: 6px; text-align: center; border: 1px solid rgba(0,0,0,0.1);">';
                    echo '<strong style="color: #2c3e50;">' . esc_html($ingredient['name']) . '</strong>';
                    echo '<br><span style="color: #6c757d; font-size: 14px;">' . esc_html($weight) . 'g</span>';
                    echo '</div>';
                }
                
                echo '</div>';
                echo '<div style="text-align: center; margin-top: 15px; padding: 10px; background: rgba(40, 167, 69, 0.1); border-radius: 6px;">';
                echo '<strong style="color: #28a745;">⚖️ ' . sprintf(esc_html__('Total Weight: %sg', 'herbal-mix-creator2'), number_format($total_weight, 1)) . '</strong>';
                echo '</div>';
                echo '</div>';
            }
        }
        
        // Mix story
        if ($mix_story) {
            echo '<div class="mix-story">';
            echo '<h4 style="margin: 0 0 15px 0; color: #495057; font-size: 16px; text-align: center;">📖 ' . esc_html__('Mix Story:', 'herbal-mix-creator2') . '</h4>';
            echo '<div style="line-height: 1.7; color: #495057; background: rgba(255,255,255,0.8); padding: 20px; border-radius: 8px; border-left: 4px solid #28a745;">' . wp_kses_post(wpautop($mix_story)) . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Display custom fields in cart and checkout
     */
    public function display_cart_item_custom_fields($item_data, $cart_item) {
        $product = $cart_item['data'];
        if ($product && $product->get_id() > 0) {
            $points_cost = get_post_meta($product->get_id(), 'price_point', true);
            $points_earned = get_post_meta($product->get_id(), 'point_earned', true);
            
            if ($points_cost && $points_cost > 0) {
                $item_data[] = array(
                    'key'   => '🎯 ' . __('Points Cost', 'herbal-mix-creator2'),
                    'value' => number_format($points_cost, 0) . ' pts'
                );
            }
            
            if ($points_earned && $points_earned > 0) {
                $item_data[] = array(
                    'key'   => '🎁 ' . __('Points Earned', 'herbal-mix-creator2'),
                    'value' => number_format($points_earned, 0) . ' pts'
                );
            }
            
            // Show ingredients in cart
            $ingredients_json = get_post_meta($product->get_id(), 'product_ingredients', true);
            if ($ingredients_json) {
                $ingredients = json_decode($ingredients_json, true);
                if (!empty($ingredients)) {
                    $ingredients_names = array();
                    $total_weight = 0;
                    foreach ($ingredients as $ingredient) {
                        $ingredients_names[] = $ingredient['name'] . ' (' . $ingredient['weight'] . 'g)';
                        $total_weight += floatval($ingredient['weight']);
                    }
                    
                    $item_data[] = array(
                        'key'   => '🌿 ' . __('Ingredients', 'herbal-mix-creator2'),
                        'value' => implode(', ', $ingredients_names)
                    );
                    
                    $item_data[] = array(
                        'key'   => '⚖️ ' . __('Total Weight', 'herbal-mix-creator2'),
                        'value' => number_format($total_weight, 1) . 'g'
                    );
                }
            }
            
            // Show mix creator
            $creator_name = get_post_meta($product->get_id(), 'mix_creator_name', true);
            if ($creator_name) {
                $item_data[] = array(
                    'key'   => '👤 ' . __('Created by', 'herbal-mix-creator2'),
                    'value' => esc_html($creator_name)
                );
            }
        }
        
        return $item_data;
    }
}