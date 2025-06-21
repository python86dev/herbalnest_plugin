<?php
/**
 * Plugin Name: Herbal Mix Creator
 * Description: Herbal mix creator with points system for UK market.
 * Version: 1.0
 * Author: Łukasz Łuczyński
 * Text Domain: herbal-mix-creator2
 * 
 * FIXED VERSION: Clean main plugin file with proper AJAX handler registration
 * - Removed duplicate AJAX actions
 * - Proper class initialization
 * - Fixed asset loading
 * - English frontend for UK market
 */

// Prevent direct file loading
if (!defined('ABSPATH')) {
    exit;
}

// === PLUGIN CONSTANTS ===
define('HERBAL_MIX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HERBAL_MIX_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HERBAL_MIX_VERSION', '1.0');

// === LOAD CORE CLASSES ===
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-database.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-media-handler.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-product-meta.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-reward-points.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-actions.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-admin-panel.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-creator.php';

// === INITIALIZE PLUGIN ===
add_action('plugins_loaded', 'herbal_mix_creator_init');

function herbal_mix_creator_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'herbal_mix_creator_woocommerce_notice');
        return;
    }
    
    // Initialize core classes
    if (class_exists('Herbal_Mix_Creator')) {
        new Herbal_Mix_Creator();
    }
    
    if (class_exists('Herbal_Mix_Product_Meta')) {
        new Herbal_Mix_Product_Meta();
    }
    
    if (class_exists('Herbal_Mix_Reward_Points')) {
        new Herbal_Mix_Reward_Points();
    }
    
    if (class_exists('Herbal_Mix_Actions')) {
        new Herbal_Mix_Actions();
    }
    
    if (class_exists('Herbal_Mix_Media_Handler')) {
        new Herbal_Mix_Media_Handler();
    }
    
    // Initialize admin panel
    if (is_admin() && class_exists('Herbal_Mix_Admin_Panel')) {
        new Herbal_Mix_Admin_Panel();
    }
    
    // Load points system classes
    herbal_load_points_system();
}

/**
 * Load points system after WooCommerce is ready
 */
function herbal_load_points_system() {
    require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-profile-integration.php';
    require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-points-admin.php';
    
    // Initialize profile integration
    if (class_exists('HerbalProfileIntegration')) {
        new HerbalProfileIntegration();
    }
    
    if (class_exists('Herbal_Points_Admin')) {
        new Herbal_Points_Admin();
    }
    
    // Load user profile extended - THIS IS IMPORTANT FOR EDIT/PUBLISH BUTTONS
    require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-user-profile-extended.php';
    if (class_exists('HerbalMixUserProfileExtended')) {
        new HerbalMixUserProfileExtended();
    }
    
    // Load payment gateway after WooCommerce is fully loaded
    add_action('woocommerce_loaded', function() {
        if (file_exists(HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-points-gateway.php')) {
            require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-points-gateway.php';
        }
    });
}

/**
 * WooCommerce required notice
 */
function herbal_mix_creator_woocommerce_notice() {
    echo '<div class="notice notice-error"><p>';
    echo '<strong>' . esc_html__('Herbal Mix Creator', 'herbal-mix-creator2') . '</strong>: ';
    echo esc_html__('WooCommerce is required for this plugin to work properly.', 'herbal-mix-creator2');
    echo '</p></div>';
}

/**
 * Check plugin requirements after activation
 */
function herbal_mix_creator_check_requirements() {
    if (!class_exists('WooCommerce')) {
        herbal_mix_creator_woocommerce_notice();
    }
    
    // Check if database tables exist after activation
    if (get_option('herbal_mix_creator_activated') && !herbal_is_points_system_ready()) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . esc_html__('Herbal Mix Creator', 'herbal-mix-creator2') . '</strong>: ';
        echo esc_html__('Database tables are missing. Please deactivate and reactivate the plugin.', 'herbal-mix-creator2');
        echo '</p></div>';
    }
}
add_action('admin_notices', 'herbal_mix_creator_check_requirements');

/**
 * Check if points system is ready
 */
function herbal_is_points_system_ready() {
    global $wpdb;
    
    $required_tables = [
        $wpdb->prefix . 'herbal_packaging',
        $wpdb->prefix . 'herbal_categories', 
        $wpdb->prefix . 'herbal_ingredients',
        $wpdb->prefix . 'herbal_mixes',
        $wpdb->prefix . 'herbal_points_history'
    ];
    
    foreach ($required_tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return false;
        }
    }
    
    return true;
}

// === PLUGIN ACTIVATION ===
register_activation_hook(__FILE__, 'herbal_plugin_activation');

function herbal_plugin_activation() {
    // Create database tables using unified database class
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
    
    // Clear any scheduled events
    wp_clear_scheduled_hook('herbal_cleanup_temp_data');
}

// === SHORTCODE [herbal_mix_creator] ===
add_shortcode('herbal_mix_creator', 'render_herbal_mix_creator_shortcode');

function render_herbal_mix_creator_shortcode($atts = []) {
    // Parse shortcode attributes
    $atts = shortcode_atts([
        'show_user_mixes' => 'true',
        'max_ingredients' => '10',
        'theme' => 'default'
    ], $atts, 'herbal_mix_creator');
    
    // Buffer output
    ob_start();
    
    // Check if user is logged in for certain features
    if (!is_user_logged_in() && $atts['show_user_mixes'] === 'true') {
        echo '<div class="herbal-login-notice">';
        echo '<p>' . esc_html__('Please log in to create and save your custom herbal mixes.', 'herbal-mix-creator2') . '</p>';
        echo '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="button">' . esc_html__('Log In', 'herbal-mix-creator2') . '</a>';
        echo '</div>';
    }
    
    // Include the frontend form
    include HERBAL_MIX_PLUGIN_PATH . 'frontend-mix-form.php';
    
    return ob_get_clean();
}

// === REGISTER STYLES AND SCRIPTS ===
add_action('wp_enqueue_scripts', 'enqueue_herbal_mix_assets');

function enqueue_herbal_mix_assets() {
    global $post;
    
    // Load assets only where needed
    $load_assets = false;
    
    // Check for shortcode
    if (is_singular() && $post && has_shortcode($post->post_content, 'herbal_mix_creator')) {
        $load_assets = true;
    }
    
    // Check for account pages (for profile functionality)
    if (function_exists('is_account_page') && is_account_page()) {
        $load_assets = true;
    }
    
    if (!$load_assets) {
        return;
    }
    
    // === FRONTEND MIX CREATOR ASSETS ===
    if (is_singular() && $post && has_shortcode($post->post_content, 'herbal_mix_creator')) {
        
        // Main creator CSS
        wp_enqueue_style(
            'herbal-mix-css',
            HERBAL_MIX_PLUGIN_URL . 'assets/css/mix-creator.css',
            [],
            filemtime(HERBAL_MIX_PLUGIN_PATH . 'assets/css/mix-creator.css')
        );
        
        // Chart.js library for nutritional charts
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            '4.4.0',
            true
        );
        
        // Main creator JavaScript
        wp_enqueue_script(
            'herbal-mix-js',
            HERBAL_MIX_PLUGIN_URL . 'assets/js/mix-creator.js',
            ['jquery', 'chart-js'],
            filemtime(HERBAL_MIX_PLUGIN_PATH . 'assets/js/mix-creator.js'),
            true
        );
        
        // Localize script for mix creator
        wp_localize_script('herbal-mix-js', 'herbalMixData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('herbal_mix_nonce'),
            'default_herb_img' => HERBAL_MIX_PLUGIN_URL . 'assets/img/default-herb.png',
            'empty_state_img' => HERBAL_MIX_PLUGIN_URL . 'assets/img/empty-state.png',
            'points_icon' => HERBAL_MIX_PLUGIN_URL . 'assets/img/points-icon.png',
            'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '£',
            'user_id' => get_current_user_id(),
            'user_name' => wp_get_current_user()->display_name,
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
    
    // === ADMIN ASSETS ===
    if (is_admin()) {
        global $pagenow, $post_type;
        
        // Load admin styles on product edit pages
        if (($pagenow === 'post.php' || $pagenow === 'post-new.php') && $post_type === 'product') {
            wp_enqueue_style(
                'herbal-admin-css',
                HERBAL_MIX_PLUGIN_URL . 'assets/css/herbal-admin.css',
                [],
                filemtime(HERBAL_MIX_PLUGIN_PATH . 'assets/css/herbal-admin.css')
            );
        }
        
        // Load admin panel assets on plugin pages
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'herbal-mix') !== false) {
            wp_enqueue_style(
                'herbal-admin-panel-css',
                HERBAL_MIX_PLUGIN_URL . 'assets/css/admin-panel.css',
                [],
                filemtime(HERBAL_MIX_PLUGIN_PATH . 'assets/css/admin-panel.css')
            );
            
            wp_enqueue_script(
                'herbal-admin-panel-js',
                HERBAL_MIX_PLUGIN_URL . 'assets/js/admin-panel.js',
                ['jquery'],
                filemtime(HERBAL_MIX_PLUGIN_PATH . 'assets/js/admin-panel.js'),
                true
            );
        }
    }
}

// === BASIC AJAX HANDLERS (for frontend form only) ===
// NOTE: Profile AJAX handlers are registered in HerbalMixUserProfileExtended class

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
    
    $where_conditions = ['visible = 1'];
    $values = [];
    
    if ($category_id > 0) {
        $where_conditions[] = 'category_id = %d';
        $values[] = $category_id;
    }
    
    if (!empty($search)) {
        $where_conditions[] = 'name LIKE %s';
        $values[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "SELECT id, name, price, price_point, point_earned, image_url, description 
              FROM $ingredients_table 
              WHERE $where_clause 
              ORDER BY sort_order ASC, name ASC 
              LIMIT 50";
    
    if (!empty($values)) {
        $query = $wpdb->prepare($query, $values);
    }
    
    $ingredients = $wpdb->get_results($query);
    
    if ($wpdb->last_error) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    }
    
    wp_send_json_success($ingredients);
}

// === STYLES FOR POINTS DISPLAY ===
add_action('wp_head', 'herbal_add_points_styles');

function herbal_add_points_styles() {
    if (is_product() || is_cart() || is_checkout() || (function_exists('is_account_page') && is_account_page())) {
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
        
        .herbal-login-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .herbal-login-notice p {
            margin: 0 0 15px 0;
            color: #856404;
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
    // Admin can manually delete tables if needed
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
        echo '<p><strong>Plugin Version:</strong> ' . HERBAL_MIX_VERSION . '</p>';
        echo '<p><strong>WooCommerce:</strong> ' . (class_exists('WooCommerce') ? 'Active' : 'Inactive') . '</p>';
        echo '<p><strong>User Points:</strong> ' . (get_current_user_id() ? get_user_meta(get_current_user_id(), 'reward_points', true) : 'Not logged in') . '</p>';
        echo '<p><strong>Database Ready:</strong> ' . (herbal_is_points_system_ready() ? 'Yes' : 'No') . '</p>';
        echo '</div>';
    }
}

// === AUTOMATIC CLEANUP TASK ===
add_action('wp', 'herbal_schedule_cleanup');

function herbal_schedule_cleanup() {
    if (!wp_next_scheduled('herbal_cleanup_temp_data')) {
        wp_schedule_event(time(), 'daily', 'herbal_cleanup_temp_data');
    }
}

add_action('herbal_cleanup_temp_data', 'herbal_cleanup_temp_data_callback');

function herbal_cleanup_temp_data_callback() {
    global $wpdb;
    
    // Clean up old temporary mix data (older than 30 days)
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}herbal_mixes 
         WHERE status = 'temporary' 
         AND created_at < %s",
        date('Y-m-d H:i:s', strtotime('-30 days'))
    ));
    
    // Clean up orphaned images (if any tracking is implemented)
    do_action('herbal_cleanup_orphaned_images');
}