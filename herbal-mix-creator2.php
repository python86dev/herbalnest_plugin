<?php
/**
 * Plugin Name: Herbal Mix Creator
 * Description: Herbal mix creator with points system and premium product templates for UK market.
 * Version: 1.3
 * Author: Łukasz Łuczyński
 * Text Domain: herbal-mix-creator2
 * Domain Path: /languages
 * 
 * FINAL FIXED VERSION 1.3: Proper gateway timing
 * - Fixed payment gateway registration timing
 * - Gateway always available for testing
 * - Single ingredient-based points system
 * - Clean code structure without conflicts
 * - English frontend for UK market
 */

// Prevent direct file loading
if (!defined('ABSPATH')) {
    exit;
}

// === PLUGIN CONSTANTS ===
define('HERBAL_MIX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HERBAL_MIX_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HERBAL_MIX_VERSION', '1.3');

// === LOAD CORE CLASSES IN PROPER ORDER ===
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-database.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-media-handler.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-template-handler.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-product-meta.php';
// REMOVED: class-herbal-mix-reward-points.php (duplicate system)
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-actions.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-admin-panel.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-creator.php';

// === FIXED: PAYMENT GATEWAY REGISTRATION WITH CORRECT TIMING ===
add_action('woocommerce_init', 'herbal_register_points_gateway_correct_timing');

function herbal_register_points_gateway_correct_timing() {
    // Prevent double loading
    if (class_exists('WC_Gateway_Points_Payment')) {
        return; // Gateway already loaded
    }
    
    // Load gateway file
    if (file_exists(HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-points-gateway.php')) {
        require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-points-gateway.php';
        
        // Initialize gateway
        if (function_exists('herbal_init_points_payment_gateway')) {
            herbal_init_points_payment_gateway();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Herbal Plugin: Payment gateway loaded on woocommerce_init (CORRECT TIMING)');
            }
        }
    }
}

// === FORCE GATEWAY REGISTRATION IN PAYMENT METHODS LIST ===
add_filter('woocommerce_payment_gateways', 'herbal_force_add_points_gateway', 99);

function herbal_force_add_points_gateway($methods) {
    // Check if gateway is already in the list
    if (!in_array('WC_Gateway_Points_Payment', $methods)) {
        $methods[] = 'WC_Gateway_Points_Payment';
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Herbal: FORCED points gateway into payment methods list');
        }
    }
    
    return $methods;
}

// === ALTERNATIVE REGISTRATION IF ABOVE DOESN'T WORK ===
add_action('woocommerce_payment_gateways_initialized', 'herbal_ensure_points_gateway_registered');

function herbal_ensure_points_gateway_registered($payment_gateways) {
    if (class_exists('WC_Gateway_Points_Payment')) {
        $gateway = new WC_Gateway_Points_Payment();
        $payment_gateways->gateways['points_payment'] = $gateway;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Herbal: Manually registered points gateway in gateways array');
        }
    }
}

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
    
    // 2. Template handler (initialize early for template overrides)
    if (class_exists('Herbal_Mix_Template_Handler')) {
        new Herbal_Mix_Template_Handler();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Herbal Mix Template Handler initialized');
        }
    }
    
    // 3. Core functionality
    if (class_exists('Herbal_Mix_Creator')) {
        new Herbal_Mix_Creator();
    }
    
    if (class_exists('Herbal_Mix_Product_Meta')) {
        new Herbal_Mix_Product_Meta();
    }
    
    // REMOVED: Herbal_Mix_Reward_Points (duplicate)
    // Points system now handled by Herbal_Mix_Database class only
    
    if (class_exists('Herbal_Mix_Actions')) {
        new Herbal_Mix_Actions();
    }
    
    // 4. Admin panel (only in admin)
    if (is_admin() && class_exists('Herbal_Mix_Admin_Panel')) {
        new Herbal_Mix_Admin_Panel();
    }
    
    // 5. Load points system and profile integration
    herbal_load_points_system();
}

/**
 * FIXED: Load points system (payment gateway loaded separately with correct timing)
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
    
    // Load user profile extended
    require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-user-profile-extended.php';
    if (class_exists('HerbalMixUserProfileExtended')) {
        new HerbalMixUserProfileExtended();
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Herbal Plugin: Points system components loaded successfully');
    }
}

// === POINTS DISPLAY IN CART/CHECKOUT TOTALS (NEW FEATURE) ===
add_action('woocommerce_cart_totals_after_order_total', 'herbal_display_points_in_cart_totals');
add_action('woocommerce_review_order_after_order_total', 'herbal_display_points_in_checkout_totals');

function herbal_display_points_in_cart_totals() {
    herbal_display_points_totals_row();
}

function herbal_display_points_in_checkout_totals() {
    herbal_display_points_totals_row();
}

function herbal_display_points_totals_row() {
    if (!is_user_logged_in() || !WC()->cart || WC()->cart->is_empty()) {
        return;
    }

    $total_points_cost = 0;
    $total_points_earned = 0;
    
    // Calculate total points for entire cart
    foreach (WC()->cart->get_cart() as $cart_item) {
        $product_id = $cart_item['product_id'];
        $quantity = $cart_item['quantity'];
        
        $points_cost = floatval(get_post_meta($product_id, 'price_point', true));
        $points_earned = floatval(get_post_meta($product_id, 'point_earned', true));
        
        $total_points_cost += $points_cost * $quantity;
        $total_points_earned += $points_earned * $quantity;
    }

    // Only show if there are any points
    if ($total_points_cost <= 0 && $total_points_earned <= 0) {
        return;
    }

    $user_id = get_current_user_id();
    $user_points = floatval(get_user_meta($user_id, 'reward_points', true));

    // Display in totals section (exactly where you wanted)
    echo '<tr class="herbal-points-total-row">';
    echo '<th style="border-top: 2px solid #ddd; padding-top: 10px;">🎯 Points Summary</th>';
    echo '<td style="border-top: 2px solid #ddd; padding-top: 10px; text-align: right;">';
    
    if ($total_points_cost > 0) {
        $can_afford = $user_points >= $total_points_cost;
        $color = $can_afford ? 'green' : 'orange';
        echo '<div style="margin-bottom: 5px;">💰 <strong>Alternative cost:</strong> <span style="color: ' . $color . ';">' . number_format($total_points_cost, 0) . ' pts</span></div>';
    }
    
    if ($total_points_earned > 0) {
        echo '<div style="margin-bottom: 5px;">🎁 <strong>You will earn:</strong> <span style="color: green;">' . number_format($total_points_earned, 0) . ' pts</span></div>';
    }
    
    echo '<div style="font-size: 12px; color: #666;">👤 Your balance: ' . number_format($user_points, 0) . ' pts</div>';
    
    if ($total_points_cost > 0 && $user_points >= $total_points_cost) {
        echo '<div style="margin-top: 5px; color: green; font-weight: bold;">✅ You can pay with points!</div>';
    }
    
    echo '</td>';
    echo '</tr>';
}

/**
 * FIXED: Add points earning on order completion
 * Uses ingredient-based system only
 */
add_action('woocommerce_order_status_completed', 'herbal_award_points_on_order_complete', 10, 1);

function herbal_award_points_on_order_complete($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_user_id();
    if (!$user_id) return;

    // Check if points already awarded
    $points_awarded = get_post_meta($order_id, '_herbal_points_awarded', true);
    if ($points_awarded) return;

    $total_points = 0;
    
    // Calculate points from product meta (ingredient-based)
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();
        
        // Use point_earned meta field (without underscore)
        $points_earned = floatval(get_post_meta($product_id, 'point_earned', true));
        $total_points += $points_earned * $quantity;
    }

    if ($total_points > 0 && class_exists('Herbal_Mix_Database')) {
        // Award points using unified database system
        $result = Herbal_Mix_Database::add_user_points(
            $user_id,
            $total_points,
            'order_completed',
            $order_id,
            sprintf(__('Points earned from order #%s', 'herbal-mix-creator2'), $order_id)
        );
        
        if ($result !== false) {
            // Mark as awarded to prevent duplicates
            update_post_meta($order_id, '_herbal_points_awarded', current_time('mysql'));
            
            // Add order note
            $order->add_order_note(sprintf(
                __('Customer earned %s reward points from this purchase.', 'herbal-mix-creator2'),
                number_format($total_points, 0)
            ));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Herbal: Awarded {$total_points} points to user {$user_id} for order {$order_id}");
            }
        }
    }
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
    
    // FIXED: Migration for existing users - update meta field names
    herbal_migrate_points_meta_fields();
    
    // Save activation flag
    add_option('herbal_mix_creator_activated', true);
    add_option('herbal_flush_rewrite_rules_flag', true);
    
    // Set default template override setting
    add_option('herbal_enable_template_override', 1);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * FIXED: Migrate old meta field names to new ones
 * Convert _points_earned to point_earned (remove underscore)
 */
function herbal_migrate_points_meta_fields() {
    global $wpdb;
    
    // Migrate product meta fields from wrong names to correct ones
    $products_with_old_meta = $wpdb->get_results("
        SELECT post_id, meta_value 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = '_points_earned'
    ");
    
    foreach ($products_with_old_meta as $product_meta) {
        $product_id = $product_meta->post_id;
        $points_earned = $product_meta->meta_value;
        
        // Update to correct meta key (without underscore)
        update_post_meta($product_id, 'point_earned', $points_earned);
        
        // Remove old meta key
        delete_post_meta($product_id, '_points_earned');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Herbal Migration: Updated product {$product_id} meta from _points_earned to point_earned");
        }
    }
    
    // Log migration completion
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Herbal Migration: Completed meta field migration for " . count($products_with_old_meta) . " products");
    }
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
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'user_id' => get_current_user_id(),
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
}

// === LANGUAGE LOADING ===
add_action('plugins_loaded', 'herbal_load_textdomain');

function herbal_load_textdomain() {
    load_plugin_textdomain(
        'herbal-mix-creator2',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

// === CSS STYLES FOR POINTS DISPLAY ===
add_action('wp_head', 'herbal_points_totals_styles');

function herbal_points_totals_styles() {
    if (is_cart() || is_checkout()) {
        ?>
        <style>
        .herbal-points-total-row th,
        .herbal-points-total-row td {
            border-top: 2px solid #ddd !important;
            padding-top: 10px !important;
            font-size: 14px;
        }
        
        .herbal-points-total-row td > div {
            line-height: 1.4;
        }
        
        .herbal-points-total-row th {
            font-weight: 600;
        }
        
        /* Ensure proper spacing */
        .herbal-points-total-row td {
            vertical-align: top;
        }
        </style>
        <?php
    }
}

// === ENHANCED DEBUG FOR GATEWAY TIMING ===
if (defined('WP_DEBUG') && WP_DEBUG) {
    
    // Debug: Check timing and status
    add_action('woocommerce_init', function() {
        error_log('=== HERBAL TIMING DEBUG ===');
        error_log('WooCommerce initialized, checking gateway status...');
        error_log('WC_Gateway_Points_Payment class exists: ' . (class_exists('WC_Gateway_Points_Payment') ? 'YES' : 'NO'));
        
        // Check if WooCommerce payment gateways are available
        if (WC() && WC()->payment_gateways) {
            error_log('WC payment_gateways object exists: YES');
            
            // Try to get the list of gateways
            $all_gateways = WC()->payment_gateways->payment_gateways;
            if (is_array($all_gateways)) {
                error_log('All gateway IDs: ' . implode(', ', array_keys($all_gateways)));
                error_log('Points gateway in array: ' . (isset($all_gateways['points_payment']) ? 'YES' : 'NO'));
            } else {
                error_log('Payment gateways array is not ready yet');
            }
        } else {
            error_log('WC payment_gateways object NOT ready');
        }
    }, 999); // Late priority to see final state
    
    // Debug: Final check after everything loads
    add_action('wp_loaded', function() {
        if (is_admin() && current_user_can('manage_options')) {
            if (WC() && WC()->payment_gateways) {
                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                $points_available = isset($available_gateways['points_payment']);
                
                error_log('=== FINAL GATEWAY STATUS ===');
                error_log('Available gateways after wp_loaded: ' . implode(', ', array_keys($available_gateways)));
                error_log('Points gateway available: ' . ($points_available ? 'YES' : 'NO'));
                
                if ($points_available) {
                    $pg = $available_gateways['points_payment'];
                    error_log('Points gateway enabled: ' . $pg->enabled);
                    error_log('Points gateway is_available(): ' . ($pg->is_available() ? 'YES' : 'NO'));
                }
            }
        }
    });
}

// === FLUSH REWRITE RULES IF NEEDED ===
add_action('wp_loaded', 'herbal_flush_rewrite_rules_maybe');

function herbal_flush_rewrite_rules_maybe() {
    if (get_option('herbal_flush_rewrite_rules_flag')) {
        flush_rewrite_rules();
        delete_option('herbal_flush_rewrite_rules_flag');
    }
}