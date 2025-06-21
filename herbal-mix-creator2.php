<?php
/**
 * Plugin Name: Herbal Mix Creator
 * Description: Herbal mix creator with points system for UK market.
 * Version: 1.0
 * Author: Łukasz Łuczyński
 * Text Domain: herbal-mix-creator2
 * 
 * FIXED VERSION: Clean main plugin file without code duplication
 * - Removed duplicate AJAX actions
 * - Proper class initialization order
 * - Fixed asset loading
 * - English frontend for UK market
 * - Centralized media handling
 */

// Prevent direct file loading
if (!defined('ABSPATH')) {
    exit;
}

// === PLUGIN CONSTANTS ===
define('HERBAL_MIX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HERBAL_MIX_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HERBAL_MIX_VERSION', '1.0');

// === LOAD CORE CLASSES IN PROPER ORDER ===
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-database.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-media-handler.php'; // Load media handler first
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
    
    // Initialize core classes in proper order
    // 1. Media handler (no dependencies)
    if (class_exists('HerbalMixMediaHandler')) {
        new HerbalMixMediaHandler();
    }
    
    // 2. Core functionality
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
    
    // 3. Admin panel (only in admin)
    if (is_admin() && class_exists('Herbal_Mix_Admin_Panel')) {
        new Herbal_Mix_Admin_Panel();
    }
    
    // 4. Load points system and profile integration
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
    
    // Load user profile extended - IMPORTANT FOR EDIT/PUBLISH BUTTONS
    // This loads AFTER media handler to avoid duplicate AJAX registrations
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
    $template_path = HERBAL_MIX_PLUGIN_PATH . 'includes/templates/frontend-mix-form.php';
    if (file_exists($template_path)) {
        include $template_path;
    } else {
        // Try fallback location for backward compatibility
        $fallback_path = HERBAL_MIX_PLUGIN_PATH . 'frontend-mix-form.php';
        if (file_exists($fallback_path)) {
            include $fallback_path;
        } else {
            echo '<div class="herbal-mix-error">';
            echo '<p>' . esc_html__('Herbal Mix Creator template not found.', 'herbal-mix-creator2') . '</p>';
            echo '</div>';
        }
    }
    
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
            
            // Localize admin script
            wp_localize_script('herbal-admin-panel-js', 'herbalAdminData', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('herbal_admin_nonce'),
                'strings' => [
                    'confirmDelete' => __('Are you sure you want to delete this item?', 'herbal-mix-creator2'),
                    'loading' => __('Loading...', 'herbal-mix-creator2'),
                    'error' => __('An error occurred.', 'herbal-mix-creator2'),
                    'success' => __('Success!', 'herbal-mix-creator2')
                ]
            ]);
        }
    }
}

// === UTILITY FUNCTIONS ===

/**
 * Get plugin version for cache busting
 */
function herbal_mix_get_version() {
    return HERBAL_MIX_VERSION;
}

/**
 * Check if user has required capabilities for admin functions
 */
function herbal_mix_user_can_manage() {
    return current_user_can('manage_options') || current_user_can('manage_woocommerce');
}

/**
 * Log plugin errors for debugging
 */
function herbal_mix_log_error($message, $context = array()) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Herbal Mix Creator: ' . $message . (!empty($context) ? ' Context: ' . print_r($context, true) : ''));
    }
}

/**
 * Get plugin directory URL with trailing slash
 */
function herbal_mix_get_plugin_url() {
    return HERBAL_MIX_PLUGIN_URL;
}

/**
 * Get plugin directory path with trailing slash
 */
function herbal_mix_get_plugin_path() {
    return HERBAL_MIX_PLUGIN_PATH;
}

// === CLEANUP ON UNINSTALL ===
register_uninstall_hook(__FILE__, 'herbal_mix_creator_uninstall');

function herbal_mix_creator_uninstall() {
    // Only run if user has permission
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    // Check if we should keep data
    $keep_data = get_option('herbal_mix_keep_data_on_uninstall', false);
    
    if (!$keep_data) {
        global $wpdb;
        
        // Remove custom tables
        $tables_to_remove = array(
            $wpdb->prefix . 'herbal_packaging',
            $wpdb->prefix . 'herbal_categories',
            $wpdb->prefix . 'herbal_ingredients',
            $wpdb->prefix . 'herbal_mixes',
            $wpdb->prefix . 'herbal_points_history'
        );
        
        foreach ($tables_to_remove as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        // Remove plugin options
        delete_option('herbal_mix_creator_activated');
        delete_option('herbal_flush_rewrite_rules_flag');
        delete_option('herbal_mix_keep_data_on_uninstall');
        
        // Remove user meta data
        delete_metadata('user', 0, 'herbal_points', '', true);
        delete_metadata('user', 0, 'custom_avatar', '', true);
        
        // Clear any cached data
        wp_cache_flush();
    }
}