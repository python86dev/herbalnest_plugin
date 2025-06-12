<?php
/**
 * Plugin Name: Herbal Mix Creator
 * Description: Herbal mix creator with points system for UK market.
 * Version: 1.0
 * Author: Łukasz Łuczyński
 * Text Domain: herbal-mix-creator2
 * 
 * FIXED VERSION: Restored all original functionality with corrected database field names
 * - Maintains all shortcodes, hooks, and original features
 * - Only changes meta field names to match database columns
 */

// Prevent direct file loading
if (!defined('ABSPATH')) {
    exit;
}

// === LOAD BASIC BACKEND CLASSES ===
require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-mix-database.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-mix-media-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-mix-product-meta.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-mix-reward-points.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-mix-actions.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-mix-admin-panel.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-mix-user-profile-extended.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-mix-creator.php';

// === POINTS SYSTEM - UPDATED TO USE NEW DATABASE CLASS ===
add_action('plugins_loaded', 'herbal_load_points_system');

function herbal_load_points_system() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . __('Herbal Mix Creator requires WooCommerce for the points system to function properly.', 'herbal-mix-creator2') . '</p></div>';
        });
        return;
    }
    
    // Load updated points system classes (now using Herbal_Mix_Database)
    require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-profile-integration.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-points-admin.php';
    
    // Load payment gateway after WooCommerce is fully loaded
    add_action('woocommerce_loaded', function() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-herbal-mix-points-gateway.php';
    });
}

// === PLUGIN ACTIVATION ===
register_activation_hook(__FILE__, 'herbal_plugin_activation');

function herbal_plugin_activation() {
    // Activate database tables using new unified database class
    if (class_exists('Herbal_Mix_Database')) {
        Herbal_Mix_Database::install();
    }
    
    // Save activation flag
    add_option('herbal_mix_creator_activated', true);
    add_option('herbal_flush_rewrite_rules_flag', true);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// === PLUGIN DEACTIVATION ===
register_deactivation_hook(__FILE__, 'herbal_plugin_deactivation');

function herbal_plugin_deactivation() {
    // Clear cache and rewrite rules
    flush_rewrite_rules();
}

// === SHORTCODE [herbal_mix_creator] ===
add_shortcode('herbal_mix_creator', 'render_herbal_mix_creator_shortcode');

function render_herbal_mix_creator_shortcode() {
    // Buffer output
    ob_start();
    include plugin_dir_path(__FILE__) . 'frontend-mix-form.php';
    return ob_get_clean();
}

// === REGISTER STYLES AND SCRIPTS ===
add_action('wp_enqueue_scripts', 'enqueue_herbal_mix_assets');

function enqueue_herbal_mix_assets() {
    global $post;
    
    // Load basic styles and scripts on pages with shortcode
    if (is_singular() && $post && has_shortcode($post->post_content, 'herbal_mix_creator')) {
        // Form CSS
        wp_enqueue_style(
            'herbal-mix-css',
            plugin_dir_url(__FILE__) . 'assets/css/mix-creator.css',
            [],
            filemtime(plugin_dir_path(__FILE__) . 'assets/css/mix-creator.css')
        );
        
        // Chart.js library
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            null,
            true
        );
        
        // Main creator script
        wp_enqueue_script(
            'herbal-mix-js',
            plugin_dir_url(__FILE__) . 'assets/js/mix-creator.js',
            ['jquery', 'chart-js'],
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/mix-creator.js'),
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('herbal-mix-js', 'herbalAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('herbal_mix_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'herbal-mix-creator2'),
                'error' => __('An error occurred. Please try again.', 'herbal-mix-creator2'),
                'success' => __('Success!', 'herbal-mix-creator2'),
                'selectPackaging' => __('Please select packaging first.', 'herbal-mix-creator2'),
                'addIngredients' => __('Please add at least one ingredient.', 'herbal-mix-creator2'),
                'maxWeight' => __('Total weight cannot exceed package capacity.', 'herbal-mix-creator2'),
                'minWeight' => __('Please add at least 1g of ingredients.', 'herbal-mix-creator2'),
                'mixName' => __('Please enter a name for your mix.', 'herbal-mix-creator2')
            ]
        ]);
    }
    
    // Load admin styles on product edit pages
    if (is_admin()) {
        global $pagenow, $post_type;
        if (($pagenow == 'post.php' || $pagenow == 'post-new.php') && $post_type == 'product') {
            wp_enqueue_style(
                'herbal-admin-css',
                plugin_dir_url(__FILE__) . 'assets/css/herbal-admin.css',
                [],
                filemtime(plugin_dir_path(__FILE__) . 'assets/css/herbal-admin.css')
            );
        }
    }
}

// === AJAX HANDLERS ===
add_action('wp_ajax_herbal_load_ingredients', 'herbal_ajax_load_ingredients');
add_action('wp_ajax_nopriv_herbal_load_ingredients', 'herbal_ajax_load_ingredients');

function herbal_ajax_load_ingredients() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $category_id = intval($_POST['category_id']);
    $search = sanitize_text_field($_POST['search']);
    
    $ingredients_table = $wpdb->prefix . 'herbal_ingredients';
    
    $sql = "SELECT id, name, price, price_point, point_earned, description, image_url 
            FROM {$ingredients_table} 
            WHERE visible = 1";
    
    $params = [];
    
    if ($category_id > 0) {
        $sql .= " AND category_id = %d";
        $params[] = $category_id;
    }
    
    if (!empty($search)) {
        $sql .= " AND name LIKE %s";
        $params[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    $sql .= " ORDER BY sort_order ASC, name ASC";
    
    if (!empty($params)) {
        $ingredients = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    } else {
        $ingredients = $wpdb->get_results($sql);
    }
    
    wp_send_json_success($ingredients);
}

add_action('wp_ajax_herbal_load_categories', 'herbal_ajax_load_categories');
add_action('wp_ajax_nopriv_herbal_load_categories', 'herbal_ajax_load_categories');

function herbal_ajax_load_categories() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $categories_table = $wpdb->prefix . 'herbal_categories';
    
    $categories = $wpdb->get_results(
        "SELECT id, name, description 
         FROM {$categories_table} 
         WHERE visible = 1 
         ORDER BY sort_order ASC, name ASC"
    );
    
    wp_send_json_success($categories);
}

add_action('wp_ajax_herbal_save_mix', 'herbal_ajax_save_mix');

function herbal_ajax_save_mix() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
        wp_die('Security check failed');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('You must be logged in to save a mix.', 'herbal-mix-creator2')]);
    }
    
    $user_id = get_current_user_id();
    $mix_name = sanitize_text_field($_POST['mix_name']);
    $mix_description = sanitize_textarea_field($_POST['mix_description']);
    $mix_data = $_POST['mix_data']; // Already JSON
    
    if (empty($mix_name)) {
        wp_send_json_error(['message' => __('Please enter a name for your mix.', 'herbal-mix-creator2')]);
    }
    
    // Save mix to database
    global $wpdb;
    $mixes_table = $wpdb->prefix . 'herbal_mixes';
    
    $result = $wpdb->insert(
        $mixes_table,
        [
            'user_id' => $user_id,
            'mix_name' => $mix_name,
            'mix_description' => $mix_description,
            'mix_data' => $mix_data,
            'created_at' => current_time('mysql'),
            'status' => 'favorite'
        ],
        ['%d', '%s', '%s', '%s', '%s', '%s']
    );
    
    if ($result === false) {
        wp_send_json_error(['message' => __('Failed to save mix. Please try again.', 'herbal-mix-creator2')]);
    }
    
    $mix_id = $wpdb->insert_id;
    wp_send_json_success([
        'message' => __('Mix saved successfully!', 'herbal-mix-creator2'),
        'mix_id' => $mix_id
    ]);
}

// === DISPLAY CART ITEM META ===
add_filter('woocommerce_get_item_data', 'herbal_display_cart_item_meta', 10, 2);

function herbal_display_cart_item_meta($item_data, $cart_item) {
    // Only show for herbal mix products
    if (isset($cart_item['herbal_mix_data'])) {
        $mix_data = $cart_item['herbal_mix_data'];
        
        // Add ingredients information
        if (isset($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
            $ingredients_names = array();
            foreach ($mix_data['ingredients'] as $ingredient) {
                $ingredients_names[] = $ingredient['name'];
            }
            if (!empty($ingredients_names)) {
                $item_data[] = array(
                    'key'   => __('Ingredients', 'herbal-mix-creator2'),
                    'value' => implode(', ', $ingredients_names)
                );
            }
        }
        
        // Add packaging information
        if (isset($mix_data['packaging']['name'])) {
            $item_data[] = array(
                'key'   => __('Package Size', 'herbal-mix-creator2'),
                'value' => $mix_data['packaging']['name']
            );
        }
        
        // Add total weight
        if (isset($mix_data['total_weight'])) {
            $item_data[] = array(
                'key'   => __('Total Weight', 'herbal-mix-creator2'),
                'value' => $mix_data['total_weight'] . 'g'
            );
        }
        
        // Add points information
        if (isset($mix_data['total_points'])) {
            $item_data[] = array(
                'key'   => __('Points Cost', 'herbal-mix-creator2'),
                'value' => number_format($mix_data['total_points'], 0) . ' pts'
            );
        }
    }
    
    return $item_data;
}

// === INITIALIZATION ===
add_action('init', 'herbal_init_plugin');

function herbal_init_plugin() {
    // Initialize main classes
    if (class_exists('Herbal_Mix_Database')) {
        // Database class is static, no initialization needed
    }
    
    if (class_exists('Herbal_Mix_Product_Meta')) {
        new Herbal_Mix_Product_Meta();
    }
    
    if (class_exists('Herbal_Mix_Admin_Panel')) {
        new Herbal_Mix_Admin_Panel();
    }
    
    if (class_exists('HerbalMixMediaHandler')) {
        new HerbalMixMediaHandler();
    }
    
    if (class_exists('Herbal_Mix_Creator')) {
        new Herbal_Mix_Creator();
    }
    
    // Initialize points system classes if WooCommerce is available
    if (class_exists('WooCommerce')) {
        if (class_exists('HerbalProfileIntegration')) {
            // HerbalProfileIntegration initializes itself
        }
        
        if (class_exists('HerbalPointsAdmin')) {
            // HerbalPointsAdmin initializes itself
        }
    }
}

// === HELPER FUNCTIONS FOR POINTS (UPDATED TO USE NEW DATABASE) ===

/**
 * Get user points (helper function)
 * UPDATED: Now uses direct user meta access instead of HerbalPointsManager
 */
if (!function_exists('herbal_get_user_points')) {
    function herbal_get_user_points($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return 0;
        }
        
        $points = get_user_meta($user_id, 'reward_points', true);
        return floatval($points);
    }
}

/**
 * Add points to user (helper function)
 * UPDATED: Now uses Herbal_Mix_Database instead of HerbalPointsManager
 */
if (!function_exists('herbal_add_user_points')) {
    function herbal_add_user_points($user_id, $points, $transaction_type = 'manual', $reference_id = null) {
        if (!$user_id || $points <= 0) {
            return false;
        }
        
        $current_points = herbal_get_user_points($user_id);
        $new_points = $current_points + $points;
        
        // Update user meta
        $success = update_user_meta($user_id, 'reward_points', $new_points);
        
        if ($success) {
            // Record transaction in history using new database class
            Herbal_Mix_Database::record_points_transaction(
                $user_id,
                $points,
                $transaction_type,
                $reference_id,
                $current_points,
                $new_points
            );
            
            // Trigger action for other plugins/themes
            do_action('herbal_points_added', $user_id, $points, $new_points, $transaction_type);
        }
        
        return $success ? $new_points : false;
    }
}

/**
 * Subtract points from user
 * UPDATED: Now uses Herbal_Mix_Database instead of HerbalPointsManager
 */
if (!function_exists('herbal_subtract_user_points')) {
    function herbal_subtract_user_points($user_id, $points, $transaction_type = 'purchase', $reference_id = null) {
        if (!$user_id || $points <= 0) {
            return false;
        }
        
        $current_points = herbal_get_user_points($user_id);
        
        if ($current_points < $points) {
            return new WP_Error('insufficient_points', __('Insufficient points available.', 'herbal-mix-creator2'));
        }
        
        $new_points = $current_points - $points;
        
        // Update user meta
        $success = update_user_meta($user_id, 'reward_points', $new_points);
        
        if ($success) {
            // Record transaction in history using new database class
            Herbal_Mix_Database::record_points_transaction(
                $user_id,
                -$points,
                $transaction_type,
                $reference_id,
                $current_points,
                $new_points
            );
            
            // Trigger action for other plugins/themes
            do_action('herbal_points_subtracted', $user_id, $points, $new_points, $transaction_type);
        }
        
        return $success ? $new_points : false;
    }
}

/**
 * Get user points history
 */
if (!function_exists('herbal_get_user_points_history')) {
    function herbal_get_user_points_history($user_id, $limit = 20, $offset = 0) {
        if (!class_exists('Herbal_Mix_Database')) {
            return array();
        }
        
        return Herbal_Mix_Database::get_points_history($user_id, $limit, $offset);
    }
}

/**
 * Format points for display
 */
if (!function_exists('herbal_format_points')) {
    function herbal_format_points($points, $show_suffix = true) {
        $formatted = number_format($points, 0);
        
        if ($show_suffix) {
            $formatted .= ' ' . __('pts', 'herbal-mix-creator2');
        }
        
        return $formatted;
    }
}

/**
 * Auto-set points for products that don't have them
 * FIXED: Updated to use correct meta field names
 */
if (!function_exists('herbal_auto_set_product_points')) {
    function herbal_auto_set_product_points() {
        // Get all published products without points pricing
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'price_point',  // FIXED: Use correct field name
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => 'point_earned',  // FIXED: Use correct field name
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $products = get_posts($args);
        
        foreach ($products as $product_post) {
            $product_id = $product_post->ID;
            $product = wc_get_product($product_id);
            
            if (!$product) continue;
            
            $price = $product->get_price();
            
            if ($price > 0) {
                // Set default conversion: £1 = 100 points
                $points_price = $price * 100;
                $points_earned = $price * 10; // Earn 10% back in points
                
                // FIXED: Use correct meta field names
                update_post_meta($product_id, 'price_point', $points_price);
                update_post_meta($product_id, 'point_earned', $points_earned);
            }
        }
    }
}

// === ADMIN NOTICES ===
add_action('admin_notices', 'herbal_admin_notices');

function herbal_admin_notices() {
    // Check for WooCommerce dependency
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . __('Herbal Mix Creator', 'herbal-mix-creator2') . '</strong>: ';
        echo __('WooCommerce is required for this plugin to work properly.', 'herbal-mix-creator2');
        echo '</p></div>';
    }
    
    // Check if database tables exist after activation
    if (get_option('herbal_mix_creator_activated') && function_exists('herbal_is_points_system_ready') && !herbal_is_points_system_ready()) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . __('Herbal Mix Creator', 'herbal-mix-creator2') . '</strong>: ';
        echo __('Database tables are missing. Please deactivate and reactivate the plugin.', 'herbal-mix-creator2');
        echo '</p></div>';
    }
}

// === CSS STYLES FOR POINTS DISPLAY ===
add_action('wp_head', 'herbal_add_points_styles');

function herbal_add_points_styles() {
    if (is_product() || is_cart() || is_checkout() || is_account_page()) {
        ?>
        <style>
        .herbal-product-points {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .herbal-product-points p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .herbal-product-points .points-amount {
            font-weight: bold;
            color: #28a745;
        }
        
        .points-price {
            color: #6f42c1;
        }
        
        .points-earned {
            color: #28a745;
        }
        
        .herbal-points-dashboard-widget {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .herbal-points-dashboard-widget h3 {
            margin-top: 0;
            color: #495057;
        }
        
        .points-display {
            margin: 15px 0;
        }
        
        .points-value {
            display: block;
            font-size: 32px;
            font-weight: bold;
            color: #28a745;
            line-height: 1;
        }
        
        .points-label {
            font-size: 14px;
            color: #6c757d;
            margin-top: 5px;
            display: block;
        }
        </style>
        <?php
    }
}

// === CLEANUP ON UNINSTALL ===
register_uninstall_hook(__FILE__, 'herbal_plugin_uninstall');

function herbal_plugin_uninstall() {
    // Remove plugin options
    delete_option('herbal_mix_creator_activated');
    delete_option('herbal_flush_rewrite_rules_flag');
    
    // Note: We don't delete user data or database tables on uninstall
    // This preserves user points and mixes in case of accidental uninstall
}

// === DEBUG FUNCTIONS (for development) ===
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', 'herbal_debug_info');
    add_action('admin_footer', 'herbal_debug_info');
}

function herbal_debug_info() {
    if (current_user_can('manage_options') && isset($_GET['herbal_debug'])) {
        echo '<div style="position: fixed; bottom: 0; right: 0; background: #fff; border: 1px solid #ccc; padding: 10px; z-index: 9999; max-width: 300px; font-size: 12px;">';
        echo '<h4>Herbal Mix Creator Debug Info</h4>';
        echo '<p><strong>Plugin Version:</strong> 1.0</p>';
        echo '<p><strong>WooCommerce:</strong> ' . (class_exists('WooCommerce') ? 'Active' : 'Inactive') . '</p>';
        echo '<p><strong>Database Tables:</strong> ' . (function_exists('herbal_is_points_system_ready') ? (herbal_is_points_system_ready() ? 'OK' : 'Missing') : 'Unknown') . '</p>';
        echo '</div>';
    }
}

/**
 * Check if points system is properly set up
 */
function herbal_is_points_system_ready() {
    global $wpdb;
    
    $tables = [
        $wpdb->prefix . 'herbal_packaging',
        $wpdb->prefix . 'herbal_categories', 
        $wpdb->prefix . 'herbal_ingredients',
        $wpdb->prefix . 'herbal_mixes',
        $wpdb->prefix . 'herbal_points_history'
    ];
    
    foreach ($tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return false;
        }
    }
    
    return true;
}