<?php
/**
 * Herbal Mix Product Meta - WooCommerce Product Fields Integration
 * COMPLETE FIXED VERSION with all required fields and database synchronization
 * 
 * File: includes/class-herbal-mix-product-meta.php
 * 
 * FEATURES:
 * - Points system (price_point, point_earned)
 * - Mix creator fields (mix_creator_id, mix_creator_name)
 * - Mix story field (mix_story) - synced with database
 * - Herbal mix data (herbal_mix_id, packaging_id)
 * - Dynamic ingredient management (NO JSON interface)
 * - Database synchronization with herbal_mixes table
 * - AJAX handlers for ingredient management
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
        add_filter('woocommerce_product_data_tabs', array($this, 'add_herbal_mix_product_data_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_herbal_mix_product_data_fields'));
        
        add_filter('woocommerce_product_data_tabs', array($this, 'add_herbal_mix_story_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_herbal_mix_story_fields'));
        
        add_filter('woocommerce_product_data_tabs', array($this, 'add_herbal_ingredients_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'add_herbal_ingredients_fields'));
        
        // Display meta in frontend
        add_action('woocommerce_single_product_summary', array($this, 'display_product_points_info'), 25);
        add_action('woocommerce_single_product_summary', array($this, 'display_herbal_mix_details'), 35);
        
        // Add to cart item data
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_custom_fields'), 10, 2);
        
        // Admin styling
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        
        // AJAX HANDLERS
        add_action('wp_ajax_sync_mix_story', array($this, 'ajax_sync_mix_story'));
        add_action('wp_ajax_add_mix_ingredient', array($this, 'ajax_add_mix_ingredient'));
        add_action('wp_ajax_update_mix_ingredient', array($this, 'ajax_update_mix_ingredient'));
        add_action('wp_ajax_remove_mix_ingredient', array($this, 'ajax_remove_mix_ingredient'));
        add_action('wp_ajax_refresh_ingredients_interface', array($this, 'ajax_refresh_ingredients_interface'));
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

        // Add inline CSS for admin styling
        wp_add_inline_style('woocommerce_admin_styles', '
            .herbal-ingredients-list {
                background: #f9f9f9;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                max-height: 400px;
                overflow-y: auto;
            }
            .ingredient-item {
                padding: 8px 0;
                border-bottom: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .ingredient-item:last-child {
                border-bottom: none;
            }
            .ingredient-name {
                font-weight: bold;
                flex: 1;
            }
            .ingredient-details {
                font-size: 12px;
                color: #666;
                margin-left: 10px;
            }
            .sync-button {
                background: #0073aa;
                color: white;
                border: none;
                padding: 5px 10px;
                border-radius: 3px;
                cursor: pointer;
                margin-left: 10px;
            }
            .sync-button:hover {
                background: #005a87;
            }
            .herbal-meta-section {
                margin-bottom: 20px;
                padding: 15px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .ingredients-list table {
                margin: 0;
            }
            .ingredients-list th,
            .ingredients-list td {
                padding: 10px;
                border-bottom: 1px solid #ddd;
            }
            .totals-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                margin-top: 15px;
            }
            @media (max-width: 768px) {
                .totals-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        ');
    }

    /**
     * Add custom product fields to WooCommerce product edit page
     * POINTS SYSTEM FIELDS
     */
    public function add_custom_product_fields() {
        echo '<div class="options_group">';
        echo '<h3>' . __('Herbal Mix Points System', 'herbal-mix-creator2') . '</h3>';

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
        echo '<h3>' . __('Mix Creator Information', 'herbal-mix-creator2') . '</h3>';

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
    }

    /**
     * Add tab for herbal mix data
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
     */
    public function add_herbal_mix_product_data_fields() {
        global $post;
        
        echo '<div id="herbal_mix_product_data" class="panel woocommerce_options_panel">';
        echo '<div class="herbal-meta-section">';
        echo '<h3>' . __('Mix Database Connection', 'herbal-mix-creator2') . '</h3>';
        
        // Herbal Mix ID link
        woocommerce_wp_text_input( array(
            'id'          => 'herbal_mix_id',
            'label'       => __( 'Herbal Mix Database ID', 'herbal-mix-creator2' ),
            'desc_tip'    => 'true',
            'description' => __( 'ID from herbal_mixes database table. Links this product to saved mix data.', 'herbal-mix-creator2' ),
            'type'        => 'number',
            'custom_attributes' => array(
                'min' => '0',
            ),
        ) );
        
        // Packaging ID
        woocommerce_wp_select( array(
            'id'      => 'packaging_id',
            'label'   => __( 'Selected Packaging', 'herbal-mix-creator2' ),
            'options' => $this->get_packaging_options(),
            'desc_tip' => 'true',
            'description' => __( 'The packaging used for this herbal mix.', 'herbal-mix-creator2' ),
        ) );
        
        // Display current packaging info if selected
        $packaging_id = get_post_meta($post->ID, 'packaging_id', true);
        if ($packaging_id) {
            $this->display_packaging_info($packaging_id);
        }
        
        echo '</div>';
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
        global $post;
        
        echo '<div id="herbal_story_product_data" class="panel woocommerce_options_panel">';
        echo '<div class="herbal-meta-section">';
        
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
        
        // Sync button for story
        $herbal_mix_id = get_post_meta($post->ID, 'herbal_mix_id', true);
        if ($herbal_mix_id) {
            echo '<p class="form-field">';
            echo '<button type="button" id="sync-story-btn" class="sync-button" data-mix-id="' . esc_attr($herbal_mix_id) . '">';
            echo __('Sync Story with Database', 'herbal-mix-creator2');
            echo '</button>';
            echo '<span class="description" style="margin-left: 10px;">';
            echo __('Click to synchronize this story with the herbal_mixes database table.', 'herbal-mix-creator2');
            echo '</span>';
            echo '</p>';
            
            // Add JavaScript for sync functionality
            echo '<script>
            jQuery(document).ready(function($) {
                $("#sync-story-btn").click(function() {
                    var mixId = $(this).data("mix-id");
                    var story = $("#mix_story").val();
                    
                    $.ajax({
                        url: ajaxurl,
                        method: "POST",
                        data: {
                            action: "sync_mix_story",
                            mix_id: mixId,
                            mix_story: story,
                            nonce: "' . wp_create_nonce('sync_mix_story') . '"
                        },
                        success: function(response) {
                            if (response.success) {
                                alert("' . __('Story synchronized successfully!', 'herbal-mix-creator2') . '");
                            } else {
                                alert("' . __('Error synchronizing story:', 'herbal-mix-creator2') . ' " + response.data);
                            }
                        },
                        error: function() {
                            alert("' . __('AJAX error occurred', 'herbal-mix-creator2') . '");
                        }
                    });
                });
            });
            </script>';
        }
        
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
     * Add content to ingredients tab
     * COMPLETELY REWRITTEN - Dynamic ingredient interface instead of JSON
     */
    public function add_herbal_ingredients_fields() {
        global $post;
        
        echo '<div id="herbal_ingredients_product_data" class="panel woocommerce_options_panel">';
        echo '<div class="herbal-meta-section">';
        echo '<h3>' . __('Mix Ingredients Management', 'herbal-mix-creator2') . '</h3>';
        
        // Get mix data
        $herbal_mix_id = get_post_meta($post->ID, 'herbal_mix_id', true);
        
        if (!$herbal_mix_id) {
            echo '<div class="notice notice-warning inline">';
            echo '<p>' . __('Please save the product with a valid Herbal Mix ID first to manage ingredients.', 'herbal-mix-creator2') . '</p>';
            echo '</div>';
        } else {
            // Display current ingredients
            echo '<div id="ingredients-container">';
            $this->display_ingredients_interface($herbal_mix_id);
            echo '</div>';
            
            // Add ingredient form
            echo '<div id="add-ingredient-form" style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 5px;">';
            echo '<h4>' . __('Add New Ingredient', 'herbal-mix-creator2') . '</h4>';
            
            echo '<table class="form-table">';
            echo '<tr>';
            echo '<td style="width: 40%;">';
            echo '<select id="new-ingredient-select" style="width: 100%;">';
            echo '<option value="">' . __('Select ingredient...', 'herbal-mix-creator2') . '</option>';
            $this->output_ingredients_options();
            echo '</select>';
            echo '</td>';
            echo '<td style="width: 30%;">';
            echo '<input type="number" id="new-ingredient-weight" placeholder="' . __('Weight (g)', 'herbal-mix-creator2') . '" step="0.1" min="0" style="width: 100%;">';
            echo '</td>';
            echo '<td style="width: 30%;">';
            echo '<button type="button" id="add-ingredient-btn" class="button button-primary" style="width: 100%;">' . __('Add Ingredient', 'herbal-mix-creator2') . '</button>';
            echo '</td>';
            echo '</tr>';
            echo '</table>';
            echo '</div>';
            
            // Totals summary
            echo '<div id="mix-totals" style="margin-top: 20px; padding: 15px; background: #e8f5e8; border-radius: 5px;">';
            echo '<h4>' . __('Mix Totals', 'herbal-mix-creator2') . '</h4>';
            echo '<div id="totals-content">';
            $this->display_mix_totals($herbal_mix_id);
            echo '</div>';
            echo '</div>';
        }
        
        // Add JavaScript
        $this->add_ingredients_javascript();
        
        echo '</div>';
        echo '</div>';
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
        
        // Story and mix data fields
        $mix_story = isset( $_POST['mix_story'] ) ? sanitize_textarea_field( $_POST['mix_story'] ) : '';
        update_post_meta( $post_id, 'mix_story', $mix_story );

        $herbal_mix_id = isset( $_POST['herbal_mix_id'] ) ? intval( $_POST['herbal_mix_id'] ) : '';
        update_post_meta( $post_id, 'herbal_mix_id', $herbal_mix_id );

        $packaging_id = isset( $_POST['packaging_id'] ) ? intval( $_POST['packaging_id'] ) : '';
        update_post_meta( $post_id, 'packaging_id', $packaging_id );
        
        // Auto-sync story to database if herbal_mix_id is set
        if ($herbal_mix_id && !empty($mix_story)) {
            $this->sync_story_to_database($herbal_mix_id, $mix_story);
        }
        
        // Auto-generate cache if herbal_mix_id is set
        if ($herbal_mix_id && class_exists('Herbal_Mix_Database')) {
            Herbal_Mix_Database::regenerate_mix_cache($herbal_mix_id);
        }
    }

    /**
     * Display ingredients interface
     * NEW METHOD for dynamic ingredient management
     */
    private function display_ingredients_interface($herbal_mix_id) {
        if (!class_exists('Herbal_Mix_Database')) {
            echo '<p>' . __('Database class not loaded.', 'herbal-mix-creator2') . '</p>';
            return;
        }
        
        $ingredients = Herbal_Mix_Database::get_mix_ingredients($herbal_mix_id);
        
        if (empty($ingredients)) {
            echo '<div class="no-ingredients" style="padding: 20px; text-align: center; color: #666;">';
            echo '<p>' . __('No ingredients added yet. Use the form below to add ingredients.', 'herbal-mix-creator2') . '</p>';
            echo '</div>';
            return;
        }
        
        echo '<div class="ingredients-list">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width: 40%;">' . __('Ingredient', 'herbal-mix-creator2') . '</th>';
        echo '<th style="width: 15%;">' . __('Weight (g)', 'herbal-mix-creator2') . '</th>';
        echo '<th style="width: 15%;">' . __('Price', 'herbal-mix-creator2') . '</th>';
        echo '<th style="width: 15%;">' . __('Points', 'herbal-mix-creator2') . '</th>';
        echo '<th style="width: 15%;">' . __('Actions', 'herbal-mix-creator2') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="ingredients-tbody">';
        
        foreach ($ingredients as $ingredient) {
            $this->display_ingredient_row($ingredient);
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    /**
     * Display single ingredient row
     * NEW METHOD
     */
    private function display_ingredient_row($ingredient) {
        $weight = floatval($ingredient->weight_grams);
        $price_total = floatval($ingredient->price) * $weight;
        $points_total = floatval($ingredient->price_point) * $weight;
        
        echo '<tr data-ingredient-id="' . esc_attr($ingredient->ingredient_id) . '">';
        
        // Ingredient name with image
        echo '<td>';
        if ($ingredient->image_url) {
            echo '<img src="' . esc_url($ingredient->image_url) . '" alt="" style="width: 30px; height: 30px; margin-right: 10px; vertical-align: middle;">';
        }
        echo '<strong>' . esc_html($ingredient->name) . '</strong>';
        if ($ingredient->description) {
            echo '<br><small style="color: #666;">' . esc_html(wp_trim_words($ingredient->description, 10)) . '</small>';
        }
        echo '</td>';
        
        // Weight (editable)
        echo '<td>';
        echo '<input type="number" class="ingredient-weight" value="' . esc_attr($weight) . '" step="0.1" min="0" style="width: 80px;" data-ingredient-id="' . esc_attr($ingredient->ingredient_id) . '" data-price="' . esc_attr($ingredient->price) . '" data-points="' . esc_attr($ingredient->price_point) . '">';
        echo '</td>';
        
        // Price
        echo '<td>';
        echo '<span class="price-display">£' . number_format($price_total, 2) . '</span>';
        echo '<br><small>' . number_format($ingredient->price, 3) . '/g</small>';
        echo '</td>';
        
        // Points
        echo '<td>';
        echo '<span class="points-display">' . number_format($points_total, 0) . ' pts</span>';
        echo '<br><small>' . number_format($ingredient->price_point, 1) . '/g</small>';
        echo '</td>';
        
        // Actions
        echo '<td>';
        echo '<button type="button" class="button update-ingredient" data-ingredient-id="' . esc_attr($ingredient->ingredient_id) . '">' . __('Update', 'herbal-mix-creator2') . '</button><br>';
        echo '<button type="button" class="button button-link-delete remove-ingredient" data-ingredient-id="' . esc_attr($ingredient->ingredient_id) . '" style="margin-top: 5px;">' . __('Remove', 'herbal-mix-creator2') . '</button>';
        echo '</td>';
        
        echo '</tr>';
    }

    /**
     * Output ingredients options for select
     * NEW METHOD
     */
    private function output_ingredients_options() {
        if (!class_exists('Herbal_Mix_Database')) {
            return;
        }
        
        $categories = Herbal_Mix_Database::get_ingredients_by_category();
        
        foreach ($categories as $category) {
            if (!empty($category['ingredients'])) {
                echo '<optgroup label="' . esc_attr($category['name']) . '">';
                foreach ($category['ingredients'] as $ingredient) {
                    echo '<option value="' . esc_attr($ingredient->id) . '" ';
                    echo 'data-price="' . esc_attr($ingredient->price) . '" ';
                    echo 'data-points="' . esc_attr($ingredient->price_point) . '">';
                    echo esc_html($ingredient->name) . ' (£' . number_format($ingredient->price, 3) . '/g)';
                    echo '</option>';
                }
                echo '</optgroup>';
            }
        }
    }

    /**
     * Display mix totals
     * NEW METHOD
     */
    private function display_mix_totals($herbal_mix_id) {
        if (!class_exists('Herbal_Mix_Database')) {
            echo '<p>' . __('Cannot calculate totals - database class not loaded.', 'herbal-mix-creator2') . '</p>';
            return;
        }
        
        $ingredients = Herbal_Mix_Database::get_mix_ingredients($herbal_mix_id);
        
        $total_weight = 0;
        $total_price = 0;
        $total_points = 0;
        $total_earned = 0;
        
        foreach ($ingredients as $ingredient) {
            $weight = floatval($ingredient->weight_grams);
            $total_weight += $weight;
            $total_price += floatval($ingredient->price) * $weight;
            $total_points += floatval($ingredient->price_point) * $weight;
            $total_earned += floatval($ingredient->point_earned) * $weight;
        }
        
        echo '<div class="totals-grid">';
        
        echo '<div style="text-align: center;">';
        echo '<div style="font-size: 24px; font-weight: bold; color: #2c3e50;">' . number_format($total_weight, 1) . 'g</div>';
        echo '<div style="color: #666;">' . __('Total Weight', 'herbal-mix-creator2') . '</div>';
        echo '</div>';
        
        echo '<div style="text-align: center;">';
        echo '<div style="font-size: 24px; font-weight: bold; color: #27ae60;">£' . number_format($total_price, 2) . '</div>';
        echo '<div style="color: #666;">' . __('Total Cost', 'herbal-mix-creator2') . '</div>';
        echo '</div>';
        
        echo '<div style="text-align: center;">';
        echo '<div style="font-size: 24px; font-weight: bold; color: #3498db;">' . number_format($total_points, 0) . ' pts</div>';
        echo '<div style="color: #666;">' . __('Points Cost', 'herbal-mix-creator2') . '</div>';
        echo '</div>';
        
        echo '<div style="text-align: center;">';
        echo '<div style="font-size: 24px; font-weight: bold; color: #e74c3c;">' . number_format($total_earned, 0) . ' pts</div>';
        echo '<div style="color: #666;">' . __('Points Earned', 'herbal-mix-creator2') . '</div>';
        echo '</div>';
        
        echo '</div>';
        
        if (count($ingredients) > 0) {
            echo '<p style="margin-top: 15px; color: #666; font-style: italic;">';
            echo sprintf(__('Mix contains %d ingredients', 'herbal-mix-creator2'), count($ingredients));
            echo '</p>';
        }
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
     * Display packaging information
     */
    private function display_packaging_info($packaging_id) {
        global $wpdb;
        
        $packaging = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}herbal_packaging WHERE id = %d",
            $packaging_id
        ));
        
        if ($packaging) {
            echo '<div class="packaging-info" style="background: #f0f8ff; padding: 10px; margin: 10px 0; border-radius: 4px;">';
            echo '<h4>' . __('Current Packaging Details:', 'herbal-mix-creator2') . '</h4>';
            echo '<p><strong>' . __('Name:', 'herbal-mix-creator2') . '</strong> ' . esc_html($packaging->name) . '</p>';
            echo '<p><strong>' . __('Capacity:', 'herbal-mix-creator2') . '</strong> ' . esc_html($packaging->herb_capacity) . 'g</p>';
            echo '<p><strong>' . __('Price:', 'herbal-mix-creator2') . '</strong> £' . number_format($packaging->price, 2) . '</p>';
            echo '<p><strong>' . __('Points Cost:', 'herbal-mix-creator2') . '</strong> ' . number_format($packaging->price_point, 0) . ' pts</p>';
            echo '<p><strong>' . __('Points Earned:', 'herbal-mix-creator2') . '</strong> ' . number_format($packaging->point_earned, 0) . ' pts</p>';
            echo '</div>';
        }
    }

    /**
     * Add JavaScript for ingredients management
     * NEW METHOD
     */
    private function add_ingredients_javascript() {
        global $post;
        $herbal_mix_id = get_post_meta($post->ID, 'herbal_mix_id', true);
        
        if (!$herbal_mix_id) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var mixId = <?php echo intval($herbal_mix_id); ?>;
            
            // Add ingredient
            $('#add-ingredient-btn').click(function() {
                var ingredientId = $('#new-ingredient-select').val();
                var weight = $('#new-ingredient-weight').val();
                
                if (!ingredientId || !weight || weight <= 0) {
                    alert('<?php echo esc_js(__('Please select an ingredient and enter weight', 'herbal-mix-creator2')); ?>');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'add_mix_ingredient',
                        mix_id: mixId,
                        ingredient_id: ingredientId,
                        weight: weight,
                        nonce: '<?php echo wp_create_nonce('mix_ingredients_nonce'); ?>'
                    },
                    beforeSend: function() {
                        $('#add-ingredient-btn').prop('disabled', true).text('<?php echo esc_js(__('Adding...', 'herbal-mix-creator2')); ?>');
                    },
                    success: function(response) {
                        if (response.success) {
                            refreshIngredientsInterface();
                            $('#new-ingredient-select').val('');
                            $('#new-ingredient-weight').val('');
                            alert('<?php echo esc_js(__('Ingredient added successfully!', 'herbal-mix-creator2')); ?>');
                        } else {
                            alert('<?php echo esc_js(__('Error:', 'herbal-mix-creator2')); ?> ' + response.data);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('AJAX error occurred', 'herbal-mix-creator2')); ?>');
                    },
                    complete: function() {
                        $('#add-ingredient-btn').prop('disabled', false).text('<?php echo esc_js(__('Add Ingredient', 'herbal-mix-creator2')); ?>');
                    }
                });
            });
            
            // Update ingredient weight
            $(document).on('click', '.update-ingredient', function() {
                var ingredientId = $(this).data('ingredient-id');
                var weight = $(this).closest('tr').find('.ingredient-weight').val();
                
                if (!weight || weight < 0) {
                    alert('<?php echo esc_js(__('Please enter valid weight', 'herbal-mix-creator2')); ?>');
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'update_mix_ingredient',
                        mix_id: mixId,
                        ingredient_id: ingredientId,
                        weight: weight,
                        nonce: '<?php echo wp_create_nonce('mix_ingredients_nonce'); ?>'
                    },
                    beforeSend: function() {
                        $(this).prop('disabled', true);
                    }.bind(this),
                    success: function(response) {
                        if (response.success) {
                            refreshIngredientsInterface();
                            alert('<?php echo esc_js(__('Ingredient updated successfully!', 'herbal-mix-creator2')); ?>');
                        } else {
                            alert('<?php echo esc_js(__('Error:', 'herbal-mix-creator2')); ?> ' + response.data);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('AJAX error occurred', 'herbal-mix-creator2')); ?>');
                    },
                    complete: function() {
                        $(this).prop('disabled', false);
                    }.bind(this)
                });
            });
            
            // Remove ingredient
            $(document).on('click', '.remove-ingredient', function() {
                if (!confirm('<?php echo esc_js(__('Are you sure you want to remove this ingredient?', 'herbal-mix-creator2')); ?>')) {
                    return;
                }
                
                var ingredientId = $(this).data('ingredient-id');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'remove_mix_ingredient',
                        mix_id: mixId,
                        ingredient_id: ingredientId,
                        nonce: '<?php echo wp_create_nonce('mix_ingredients_nonce'); ?>'
                    },
                    beforeSend: function() {
                        $(this).prop('disabled', true);
                    }.bind(this),
                    success: function(response) {
                        if (response.success) {
                            refreshIngredientsInterface();
                            alert('<?php echo esc_js(__('Ingredient removed successfully!', 'herbal-mix-creator2')); ?>');
                        } else {
                            alert('<?php echo esc_js(__('Error:', 'herbal-mix-creator2')); ?> ' + response.data);
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('AJAX error occurred', 'herbal-mix-creator2')); ?>');
                    },
                    complete: function() {
                        $(this).prop('disabled', false);
                    }.bind(this)
                });
            });
            
            // Refresh interface
            function refreshIngredientsInterface() {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'refresh_ingredients_interface',
                        mix_id: mixId,
                        nonce: '<?php echo wp_create_nonce('mix_ingredients_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#ingredients-container').html(response.data.ingredients_html);
                            $('#totals-content').html(response.data.totals_html);
                        }
                    }
                });
            }
            
            // Auto-update price/points when weight changes
            $(document).on('input', '.ingredient-weight', function() {
                var row = $(this).closest('tr');
                var weight = parseFloat($(this).val()) || 0;
                var pricePerGram = parseFloat($(this).data('price')) || 0;
                var pointsPerGram = parseFloat($(this).data('points')) || 0;
                
                row.find('.price-display').text('£' + (weight * pricePerGram).toFixed(2));
                row.find('.points-display').text((weight * pointsPerGram).toFixed(0) + ' pts');
            });
        });
        </script>
        <?php
    }

    /**
     * Sync story to database
     */
    private function sync_story_to_database($herbal_mix_id, $mix_story) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'herbal_mixes',
            array('mix_story' => sanitize_textarea_field($mix_story)),
            array('id' => intval($herbal_mix_id)),
            array('%s'),
            array('%d')
        );
        
        return $result !== false;
    }

    /**
     * AJAX handler for syncing story
     */
    public function ajax_sync_mix_story() {
        if (!wp_verify_nonce($_POST['nonce'], 'sync_mix_story')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $mix_id = intval($_POST['mix_id']);
        $mix_story = sanitize_textarea_field($_POST['mix_story']);
        
        if ($this->sync_story_to_database($mix_id, $mix_story)) {
            wp_send_json_success('Story synchronized successfully');
        } else {
            wp_send_json_error('Failed to sync story to database');
        }
    }

    /**
     * AJAX handler for adding ingredient to mix
     * NEW METHOD
     */
    public function ajax_add_mix_ingredient() {
        if (!wp_verify_nonce($_POST['nonce'], 'mix_ingredients_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $mix_id = intval($_POST['mix_id']);
        $ingredient_id = intval($_POST['ingredient_id']);
        $weight = floatval($_POST['weight']);
        
        if (!$mix_id || !$ingredient_id || $weight <= 0) {
            wp_send_json_error('Invalid data provided');
            return;
        }
        
        if (!class_exists('Herbal_Mix_Database')) {
            wp_send_json_error('Database class not available');
            return;
        }
        
        $result = Herbal_Mix_Database::add_mix_ingredient($mix_id, $ingredient_id, $weight);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success('Ingredient added successfully');
    }

    /**
     * AJAX handler for updating ingredient weight
     * NEW METHOD
     */
    public function ajax_update_mix_ingredient() {
        if (!wp_verify_nonce($_POST['nonce'], 'mix_ingredients_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $mix_id = intval($_POST['mix_id']);
        $ingredient_id = intval($_POST['ingredient_id']);
        $weight = floatval($_POST['weight']);
        
        if (!$mix_id || !$ingredient_id) {
            wp_send_json_error('Invalid data provided');
            return;
        }
        
        if ($weight <= 0) {
            wp_send_json_error('Weight must be greater than 0');
            return;
        }
        
        if (!class_exists('Herbal_Mix_Database')) {
            wp_send_json_error('Database class not available');
            return;
        }
        
        $result = Herbal_Mix_Database::add_mix_ingredient($mix_id, $ingredient_id, $weight);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success('Ingredient updated successfully');
    }

    /**
     * AJAX handler for removing ingredient from mix
     * NEW METHOD
     */
    public function ajax_remove_mix_ingredient() {
        if (!wp_verify_nonce($_POST['nonce'], 'mix_ingredients_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $mix_id = intval($_POST['mix_id']);
        $ingredient_id = intval($_POST['ingredient_id']);
        
        if (!$mix_id || !$ingredient_id) {
            wp_send_json_error('Invalid data provided');
            return;
        }
        
        if (!class_exists('Herbal_Mix_Database')) {
            wp_send_json_error('Database class not available');
            return;
        }
        
        $result = Herbal_Mix_Database::remove_mix_ingredient($mix_id, $ingredient_id);
        
        if (!$result) {
            wp_send_json_error('Failed to remove ingredient');
            return;
        }
        
        wp_send_json_success('Ingredient removed successfully');
    }

    /**
     * AJAX handler for refreshing ingredients interface
     * NEW METHOD
     */
    public function ajax_refresh_ingredients_interface() {
        if (!wp_verify_nonce($_POST['nonce'], 'mix_ingredients_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $mix_id = intval($_POST['mix_id']);
        
        if (!$mix_id) {
            wp_send_json_error('Invalid mix ID');
            return;
        }
        
        // Generate ingredients HTML
        ob_start();
        $this->display_ingredients_interface($mix_id);
        $ingredients_html = ob_get_clean();
        
        // Generate totals HTML
        ob_start();
        $this->display_mix_totals($mix_id);
        $totals_html = ob_get_clean();
        
        wp_send_json_success(array(
            'ingredients_html' => $ingredients_html,
            'totals_html' => $totals_html
        ));
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
            echo '<div class="herbal-product-points-info" style="background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px;">';
            
            if ($points_cost) {
                echo '<p class="points-cost" style="margin: 0 0 5px 0;">';
                echo '<strong>' . sprintf(
                    __('Alternative payment: %s points', 'herbal-mix-creator2'),
                    number_format($points_cost, 0)
                ) . '</strong>';
                echo '</p>';
            }
            
            if ($points_earned) {
                echo '<p class="points-earned" style="margin: 0;">';
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
     */
    public function display_herbal_mix_details() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        
        // Check if this is a herbal mix
        $herbal_mix_id = get_post_meta($product_id, 'herbal_mix_id', true);
        if (!$herbal_mix_id) {
            return;
        }
        
        // Get mix data
        $mix_story = get_post_meta($product_id, 'mix_story', true);
        $creator_name = get_post_meta($product_id, 'mix_creator_name', true);
        
        echo '<div class="herbal-mix-frontend-details" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 8px;">';
        
        // Mix creator
        if ($creator_name) {
            echo '<div class="mix-creator" style="margin-bottom: 15px;">';
            echo '<h4 style="margin: 0 0 5px 0;">' . __('Created by:', 'herbal-mix-creator2') . '</h4>';
            echo '<p style="margin: 0; font-weight: bold; color: #2c3e50;">' . esc_html($creator_name) . '</p>';
            echo '</div>';
        }
        
        // Mix story
        if ($mix_story) {
            echo '<div class="mix-story" style="margin-bottom: 15px;">';
            echo '<h4 style="margin: 0 0 10px 0;">' . __('Mix Story:', 'herbal-mix-creator2') . '</h4>';
            echo '<div style="line-height: 1.6; color: #34495e;">' . wp_kses_post(wpautop($mix_story)) . '</div>';
            echo '</div>';
        }
        
        // Ingredients from database
        if (class_exists('Herbal_Mix_Database')) {
            $ingredients = Herbal_Mix_Database::get_mix_ingredients($herbal_mix_id);
            if (!empty($ingredients)) {
                echo '<div class="mix-ingredients">';
                echo '<h4 style="margin: 0 0 10px 0;">' . __('Ingredients:', 'herbal-mix-creator2') . '</h4>';
                echo '<ul style="margin: 0; padding-left: 20px;">';
                
                foreach ($ingredients as $ingredient) {
                    echo '<li style="margin: 5px 0;">';
                    echo '<strong>' . esc_html($ingredient->name) . '</strong> - ' . esc_html($ingredient->weight_grams) . 'g';
                    echo '</li>';
                }
                
                echo '</ul>';
                echo '</div>';
            }
        }
        
        echo '</div>';
    }

    /**
     * Display custom fields in cart and checkout
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