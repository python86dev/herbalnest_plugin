<?php
/**
 * Plugin Name: Herbal Mix Creator
 * Description: Herbal mix creator with points system and premium product templates for UK market.
 * Version: 1.3
 * Author: Łukasz Łuczyński
 * Text Domain: herbal-mix-creator2
 * Domain Path: /languages
 * 
 * ENHANCED VERSION 1.3: Unified Points System
 * BEZPIECZNA WERSJA - wszystkie funkcje z warunkami if (!function_exists())
 */

// Prevent direct file loading
if (!defined('ABSPATH')) {
    exit;
}

// === PLUGIN CONSTANTS ===
if (!defined('HERBAL_MIX_PLUGIN_URL')) {
    define('HERBAL_MIX_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('HERBAL_MIX_PLUGIN_PATH')) {
    define('HERBAL_MIX_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('HERBAL_MIX_VERSION')) {
    define('HERBAL_MIX_VERSION', '1.3');
}

// === LOAD CORE CLASSES IN PROPER ORDER ===
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-database.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-points-manager.php'; // NOWY CENTRALNY MANAGER
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-media-handler.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-template-handler.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-product-meta.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-reward-points.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-actions.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-admin-panel.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-creator.php';

// === INITIALIZE PLUGIN ===
add_action('plugins_loaded', 'herbal_mix_creator_init');

if (!function_exists('herbal_mix_creator_init')) {
    function herbal_mix_creator_init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', 'herbal_mix_creator_woocommerce_notice');
            return;
        }
        
        // === INITIALIZE CORE CLASSES ===
        
        // 1. Media handler (no dependencies)
        if (class_exists('HerbalMixMediaHandler')) {
            new HerbalMixMediaHandler();
        }
        
        // 2. Template handler (NEW - initialize early for template overrides)
        if (class_exists('Herbal_Mix_Template_Handler')) {
            new Herbal_Mix_Template_Handler();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Herbal Mix Template Handler initialized');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ERROR: Herbal_Mix_Template_Handler class not found');
            }
        }
        
        // 3. NOWY: Inicjalizuj centralny manager punktów
        if (class_exists('Herbal_Points_Manager')) {
            Herbal_Points_Manager::init();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Herbal Plugin: Central Points Manager initialized');
            }
        }
        
        // 4. Core functionality
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
        
        // 5. Admin panel (only in admin)
        if (is_admin() && class_exists('Herbal_Mix_Admin_Panel')) {
            new Herbal_Mix_Admin_Panel();
        }
        
        // 6. Load points system and profile integration
        herbal_load_points_system();
    }
}

/**
 * Load points system after WooCommerce is ready
 */
if (!function_exists('herbal_load_points_system')) {
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
        
        // Load user profile extended
        require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-user-profile-extended.php';
        if (class_exists('HerbalMixUserProfileExtended')) {
            new HerbalMixUserProfileExtended();
        }
        
        // UPDATED: Initialize payment gateway using new points system
        add_action('woocommerce_loaded', function() {
            if (file_exists(HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-points-gateway.php')) {
                require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-points-gateway.php';
                
                // Initialize gateway with new points manager
                if (function_exists('herbal_init_points_payment_gateway')) {
                    herbal_init_points_payment_gateway();
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Herbal Plugin: Points payment gateway initialized with new manager');
                    }
                }
            }
        });
    }
}

/**
 * WooCommerce required notice
 */
if (!function_exists('herbal_mix_creator_woocommerce_notice')) {
    function herbal_mix_creator_woocommerce_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html__('Herbal Mix Creator', 'herbal-mix-creator2') . '</strong>: ';
        echo esc_html__('WooCommerce is required for this plugin to work properly.', 'herbal-mix-creator2');
        echo '</p></div>';
    }
}

/**
 * Check plugin requirements after activation
 */
if (!function_exists('herbal_mix_creator_check_requirements')) {
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
}

/**
 * Check if points system is ready
 */
if (!function_exists('herbal_is_points_system_ready')) {
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
}

// === PLUGIN ACTIVATION ===
register_activation_hook(__FILE__, 'herbal_plugin_activation');

if (!function_exists('herbal_plugin_activation')) {
    function herbal_plugin_activation() {
        // Create database tables using unified database class
        if (class_exists('Herbal_Mix_Database')) {
            Herbal_Mix_Database::install();
        }
        
        // Save activation flag
        add_option('herbal_mix_creator_activated', true);
        add_option('herbal_flush_rewrite_rules_flag', true);
        
        // Set default template override setting
        add_option('herbal_enable_template_override', 1);
        
        // USUNIETE: conversion_rate setting - nie używamy już WooCommerce przelicznika
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// === PLUGIN DEACTIVATION ===
register_deactivation_hook(__FILE__, 'herbal_plugin_deactivation');

if (!function_exists('herbal_plugin_deactivation')) {
    function herbal_plugin_deactivation() {
        // Clear cache and rewrite rules
        flush_rewrite_rules();
        
        // Clear any scheduled events
        wp_clear_scheduled_hook('herbal_cleanup_temp_data');
    }
}

// === SHORTCODE [herbal_mix_creator] ===
add_shortcode('herbal_mix_creator', 'render_herbal_mix_creator_shortcode');

if (!function_exists('render_herbal_mix_creator_shortcode')) {
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
}

// === REGISTER STYLES AND SCRIPTS ===
add_action('wp_enqueue_scripts', 'enqueue_herbal_mix_assets');

if (!function_exists('enqueue_herbal_mix_assets')) {
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
            $css_file = HERBAL_MIX_PLUGIN_PATH . 'assets/css/mix-creator.css';
            if (file_exists($css_file)) {
                wp_enqueue_style(
                    'herbal-mix-css',
                    HERBAL_MIX_PLUGIN_URL . 'assets/css/mix-creator.css',
                    [],
                    filemtime($css_file)
                );
            }
            
            // Chart.js library for nutritional charts
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                '4.4.0',
                true
            );
            
            // Main creator JavaScript
            $js_file = HERBAL_MIX_PLUGIN_PATH . 'assets/js/mix-creator.js';
            if (file_exists($js_file)) {
                wp_enqueue_script(
                    'herbal-mix-js',
                    HERBAL_MIX_PLUGIN_URL . 'assets/js/mix-creator.js',
                    ['jquery', 'chart-js'],
                    filemtime($js_file),
                    true
                );
            }
            
            // Localize script for mix creator
            wp_localize_script('herbal-mix-js', 'herbalMixData', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('herbal_mix_nonce'),
                'default_herb_img' => HERBAL_MIX_PLUGIN_URL . 'assets/images/default-herb.jpg',
                'strings' => [
                    'loading' => esc_html__('Loading...', 'herbal-mix-creator2'),
                    'error' => esc_html__('An error occurred. Please try again.', 'herbal-mix-creator2'),
                    'success_save' => esc_html__('Mix saved successfully!', 'herbal-mix-creator2'),
                    'success_buy' => esc_html__('Redirecting to checkout...', 'herbal-mix-creator2'),
                    'confirm_delete' => esc_html__('Are you sure you want to delete this mix?', 'herbal-mix-creator2'),
                    'max_ingredients' => esc_html__('Maximum ingredients reached', 'herbal-mix-creator2'),
                    'select_packaging' => esc_html__('Please select packaging first', 'herbal-mix-creator2'),
                    'add_ingredients' => esc_html__('Please add at least one ingredient', 'herbal-mix-creator2'),
                    'login_required' => esc_html__('Please log in to save mixes', 'herbal-mix-creator2')
                ]
            ]);
        }
        
        // === ACCOUNT PAGE ASSETS ===
        if (function_exists('is_account_page') && is_account_page()) {
            
            // Account/Profile CSS
            $profile_css = HERBAL_MIX_PLUGIN_PATH . 'assets/css/profile.css';
            if (file_exists($profile_css)) {
                wp_enqueue_style(
                    'herbal-profile-css',
                    HERBAL_MIX_PLUGIN_URL . 'assets/css/profile.css',
                    [],
                    filemtime($profile_css)
                );
            }
            
            // Profile JavaScript
            $profile_js = HERBAL_MIX_PLUGIN_PATH . 'assets/js/profile.js';
            if (file_exists($profile_js)) {
                wp_enqueue_script(
                    'herbal-profile-js',
                    HERBAL_MIX_PLUGIN_URL . 'assets/js/profile.js',
                    ['jquery'],
                    filemtime($profile_js),
                    true
                );
            }
            
            // Localize profile script
            wp_localize_script('herbal-profile-js', 'herbalProfileData', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('herbal_profile_nonce'),
                'user_id' => get_current_user_id(),
                'strings' => [
                    'loading' => esc_html__('Loading...', 'herbal-mix-creator2'),
                    'error' => esc_html__('An error occurred', 'herbal-mix-creator2'),
                    'success' => esc_html__('Success!', 'herbal-mix-creator2'),
                    'confirm_delete' => esc_html__('Are you sure?', 'herbal-mix-creator2'),
                    'no_more_data' => esc_html__('No more data to load', 'herbal-mix-creator2')
                ]
            ]);
        }
    }
}

// === TEMPLATE DIRECTORY CREATION ===
add_action('init', 'herbal_ensure_template_directories');

if (!function_exists('herbal_ensure_template_directories')) {
    function herbal_ensure_template_directories() {
        $template_dir = HERBAL_MIX_PLUGIN_PATH . 'includes/templates/';
        if (!is_dir($template_dir)) {
            wp_mkdir_p($template_dir);
        }
        
        $assets_css_dir = HERBAL_MIX_PLUGIN_PATH . 'assets/css/';
        if (!is_dir($assets_css_dir)) {
            wp_mkdir_p($assets_css_dir);
        }
        
        $assets_js_dir = HERBAL_MIX_PLUGIN_PATH . 'assets/js/';
        if (!is_dir($assets_js_dir)) {
            wp_mkdir_p($assets_js_dir);
        }
    }
}

// === PLUGIN UPDATE/MIGRATION ===
add_action('admin_init', 'herbal_check_plugin_update');

if (!function_exists('herbal_check_plugin_update')) {
    function herbal_check_plugin_update() {
        $current_version = get_option('herbal_mix_plugin_version', '1.0');
        
        if (version_compare($current_version, HERBAL_MIX_VERSION, '<')) {
            // Run any necessary migrations here
            
            // Update version
            update_option('herbal_mix_plugin_version', HERBAL_MIX_VERSION);
            
            // Set template override default for existing installations
            if (!get_option('herbal_enable_template_override')) {
                add_option('herbal_enable_template_override', 1);
            }
        }
    }
}

// === LANGUAGE LOADING ===
add_action('plugins_loaded', 'herbal_load_textdomain');

if (!function_exists('herbal_load_textdomain')) {
    function herbal_load_textdomain() {
        load_plugin_textdomain(
            'herbal-mix-creator2',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
}

// === BACKWARD COMPATIBILITY FUNCTIONS ===
// Te funkcje zapewniają kompatybilność wsteczną z kodem który już może używać starych nazw

if (!function_exists('herbal_get_user_points')) {
    function herbal_get_user_points($user_id = null) {
        return Herbal_Points_Manager::get_user_points($user_id);
    }
}

if (!function_exists('herbal_add_user_points')) {
    function herbal_add_user_points($user_id, $points, $transaction_type = 'manual', $reference_id = null) {
        return Herbal_Points_Manager::add_points($user_id, $points, $transaction_type, $reference_id);
    }
}

if (!function_exists('herbal_subtract_user_points')) {
    function herbal_subtract_user_points($user_id, $points, $transaction_type = 'manual', $reference_id = null) {
        return Herbal_Points_Manager::subtract_points($user_id, $points, $transaction_type, $reference_id);
    }
}

if (!function_exists('herbal_user_has_enough_points')) {
    function herbal_user_has_enough_points($user_id, $required_points) {
        return Herbal_Points_Manager::user_has_enough_points($user_id, $required_points);
    }
}

// === DEBUG FUNCTIONS ===
if (defined('WP_DEBUG') && WP_DEBUG) {
    
    // Debug: Verify template assets loading
    if (!function_exists('herbal_debug_template_assets')) {
        add_action('wp_footer', 'herbal_debug_template_assets');
        
        function herbal_debug_template_assets() {
            if (is_product()) {
                global $product;
                if ($product && class_exists('Herbal_Mix_Template_Handler')) {
                    // Check if product is detected as herbal mix
                    $product_id = $product->get_id();
                    $has_ingredients = get_post_meta($product_id, 'product_ingredients', true);
                    $has_story = get_post_meta($product_id, 'mix_story', true);
                    $has_creator = get_post_meta($product_id, 'mix_creator_name', true);
                    $has_points = get_post_meta($product_id, 'price_point', true);
                    
                    $is_herbal = !empty($has_ingredients) || !empty($has_story) || !empty($has_creator) || !empty($has_points);
                    $template_enabled = get_option('herbal_enable_template_override', 1);
                    
                    echo '<!-- Herbal Template Debug -->';
                    echo '<script>console.log("Herbal Template Debug:", {';
                    echo 'isHerbalProduct: ' . ($is_herbal ? 'true' : 'false') . ',';
                    echo 'templateEnabled: ' . ($template_enabled ? 'true' : 'false') . ',';
                    echo 'hasIngredients: ' . (!empty($has_ingredients) ? 'true' : 'false') . ',';
                    echo 'hasStory: ' . (!empty($has_story) ? 'true' : 'false') . ',';
                    echo 'productId: ' . $product_id;
                    echo '});</script>';
                }
            }
        }
    }
    
    // Debug: Check payment gateway status
    if (!function_exists('herbal_debug_payment_gateway_status')) {
        add_action('wp_footer', 'herbal_debug_payment_gateway_status');
        
        function herbal_debug_payment_gateway_status() {
            if (is_checkout() && current_user_can('manage_options')) {
                $gateway_loaded = class_exists('WC_Gateway_Points_Payment');
                $wc_loaded = class_exists('WooCommerce');
                $function_exists = function_exists('herbal_init_points_payment_gateway');
                $points_manager_loaded = class_exists('Herbal_Points_Manager');
                
                echo '<!-- Herbal Payment Gateway Debug -->';
                echo '<script>console.log("Payment Gateway Debug:", {';
                echo 'gatewayClassExists: ' . ($gateway_loaded ? 'true' : 'false') . ',';
                echo 'wooCommerceLoaded: ' . ($wc_loaded ? 'true' : 'false') . ',';
                echo 'initFunctionExists: ' . ($function_exists ? 'true' : 'false') . ',';
                echo 'pointsManagerLoaded: ' . ($points_manager_loaded ? 'true' : 'false');
                echo '});</script>';
            }
        }
    }
}

// === FLUSH REWRITE RULES IF NEEDED ===
add_action('wp_loaded', 'herbal_flush_rewrite_rules_maybe');

if (!function_exists('herbal_flush_rewrite_rules_maybe')) {
    function herbal_flush_rewrite_rules_maybe() {
        if (get_option('herbal_flush_rewrite_rules_flag')) {
            flush_rewrite_rules();
            delete_option('herbal_flush_rewrite_rules_flag');
        }
    }
}

// === DEBUG ACCESS - dostęp przez URL dla administratorów ===
add_action('init', function() {
    if (current_user_can('manage_options') && isset($_GET['debug_herbal_points'])) {
        echo '<div style="background: white; padding: 20px; border: 2px solid #000; margin: 20px;">';
        echo '<h2>🔍 HERBAL POINTS SYSTEM DEBUG</h2>';
        
        echo '<h3>System Status:</h3>';
        echo '<p><strong>Points Manager loaded:</strong> ' . (class_exists('Herbal_Points_Manager') ? '✅ YES' : '❌ NO') . '</p>';
        echo '<p><strong>Database ready:</strong> ' . (herbal_is_points_system_ready() ? '✅ YES' : '❌ NO') . '</p>';
        echo '<p><strong>WooCommerce loaded:</strong> ' . (class_exists('WooCommerce') ? '✅ YES' : '❌ NO') . '</p>';
        echo '<p><strong>Payment gateway class:</strong> ' . (class_exists('WC_Gateway_Points_Payment') ? '✅ YES' : '❌ NO') . '</p>';
        
        if (class_exists('Herbal_Points_Manager')) {
            $stats = Herbal_Points_Manager::get_points_statistics();
            echo '<h3>Points Statistics:</h3>';
            echo '<ul>';
            echo '<li>Total points in system: ' . number_format($stats['total_points'], 0) . '</li>';
            echo '<li>Users with points: ' . $stats['total_users'] . '</li>';
            echo '<li>Average points per user: ' . number_format($stats['avg_points'], 2) . '</li>';
            echo '<li>Transactions today: ' . $stats['transactions_today'] . '</li>';
            echo '</ul>';
        }
        
        // Test backward compatibility functions
        echo '<h3>Backward Compatibility Test:</h3>';
        if (is_user_logged_in()) {
            $test_user_id = get_current_user_id();
            $user_points = herbal_get_user_points($test_user_id);
            echo '<p>Current user points: ' . number_format($user_points, 0) . '</p>';
            echo '<p>Function herbal_get_user_points() works: ✅</p>';
        } else {
            echo '<p>Log in to test user functions</p>';
        }
        
        echo '</div>';
        exit;
    }
});

// === DEVELOPMENT HELPERS ===
add_action('init', function() {
    if (current_user_can('manage_options') && isset($_GET['debug_herbal_gateway'])) {
        echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px; font-family: monospace;">';
        echo '<h2>🔍 HERBAL PAYMENT GATEWAY DEBUG</h2>';
        
        // Check if WooCommerce is loaded
        echo '<p><strong>WooCommerce loaded:</strong> ' . (class_exists('WooCommerce') ? '✅ Yes' : '❌ No') . '</p>';
        echo '<p><strong>WC_Payment_Gateway exists:</strong> ' . (class_exists('WC_Payment_Gateway') ? '✅ Yes' : '❌ No') . '</p>';
        echo '<p><strong>Gateway class exists:</strong> ' . (class_exists('WC_Gateway_Points_Payment') ? '✅ Yes' : '❌ No') . '</p>';
        echo '<p><strong>Gateway function exists:</strong> ' . (function_exists('herbal_init_points_payment_gateway') ? '✅ Yes' : '❌ No') . '</p>';
        echo '<p><strong>Points Manager exists:</strong> ' . (class_exists('Herbal_Points_Manager') ? '✅ Yes' : '❌ No') . '</p>';
        
        // Check available gateways
        if (function_exists('WC') && WC()->payment_gateways) {
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            echo '<h3>Available Payment Gateways:</h3><ul>';
            foreach ($gateways as $id => $gateway) {
                echo '<li><strong>' . $id . ':</strong> ' . $gateway->get_title();
                if ($id === 'points_payment') {
                    echo ' <span style="color: green;">✅ FOUND!</span>';
                    echo '<br>  - Enabled: ' . ($gateway->enabled === 'yes' ? 'Yes' : 'No');
                    echo '<br>  - Available: ' . ($gateway->is_available() ? 'Yes' : 'No');
                }
                echo '</li>';
            }
            echo '</ul>';
            
            if (!isset($gateways['points_payment'])) {
                echo '<p style="color: red;"><strong>❌ Points Payment Gateway NOT found in available gateways!</strong></p>';
            } else {
                echo '<p style="color: green;"><strong>✅ Points Payment Gateway is available!</strong></p>';
            }
        } else {
            echo '<p style="color: red;">❌ WooCommerce payment gateways not available</p>';
        }
        
        echo '</div>';
        exit;
    }
});

// === ROZWADŹKA/DEBUG WOOCOMMERCE LOADED ===
add_action('woocommerce_loaded', function() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('=== HERBAL DEBUG: woocommerce_loaded hook triggered ===');
        error_log('Points Manager class exists: ' . (class_exists('Herbal_Points_Manager') ? 'YES' : 'NO'));
        
        // Test podstawowych funkcji
        if (is_user_logged_in()) {
            $test_points = Herbal_Points_Manager::get_user_points();
            error_log('Current user points: ' . $test_points);
        }
    }
}, 10);

// === FILE LOADING DEBUG (sprawdź w przypadku problemów) ===
add_action('init', function() {
    if (current_user_can('manage_options') && isset($_GET['debug_file_loading'])) {
        echo '<div style="background: white; padding: 20px; border: 2px solid #000; margin: 20px;">';
        echo '<h2>🔍 FILE LOADING DEBUG</h2>';
        
        $gateway_file = HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-points-gateway.php';
        $points_manager_file = HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-points-manager.php';
        
        echo '<h3>Critical Files Status:</h3>';
        echo '<p><strong>Points Manager file:</strong> ' . ($points_manager_file) . '</p>';
        echo '<p><strong>File exists:</strong> ' . (file_exists($points_manager_file) ? '✅ YES' : '❌ NO') . '</p>';
        echo '<p><strong>File readable:</strong> ' . (is_readable($points_manager_file) ? '✅ YES' : '❌ NO') . '</p>';
        echo '<p><strong>Class loaded:</strong> ' . (class_exists('Herbal_Points_Manager') ? '✅ YES' : '❌ NO') . '</p>';
        
        echo '<hr>';
        echo '<p><strong>Gateway file path:</strong> ' . $gateway_file . '</p>';
        echo '<p><strong>Gateway file exists:</strong> ' . (file_exists($gateway_file) ? '✅ YES' : '❌ NO') . '</p>';
        echo '<p><strong>Gateway class loaded:</strong> ' . (class_exists('WC_Gateway_Points_Payment') ? '✅ YES' : '❌ NO') . '</p>';
        
        if (file_exists($points_manager_file)) {
            echo '<h3>Points Manager Methods Available:</h3>';
            if (class_exists('Herbal_Points_Manager')) {
                $methods = get_class_methods('Herbal_Points_Manager');
                echo '<ul>';
                foreach (['get_user_points', 'add_points', 'subtract_points', 'get_product_points_cost'] as $key_method) {
                    $exists = in_array($key_method, $methods);
                    echo '<li>' . $key_method . ': ' . ($exists ? '✅' : '❌') . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p style="color: red;">Class not loaded</p>';
            }
        }
        
        echo '</div>';
        exit;
    }
});