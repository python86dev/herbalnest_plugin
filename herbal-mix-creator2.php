<?php
/**
 * Plugin Name: Herbal Mix Creator
 * Description: Herbal mix creator with points system and premium product templates for UK market.
 * Version: 1.1
 * Author: Łukasz Łuczyński
 * Text Domain: herbal-mix-creator2
 * Domain Path: /languages
 * 
 * ENHANCED VERSION: Added premium product template system
 * - Clean main plugin file without code duplication
 * - Premium WooCommerce template override for herbal products
 * - Proper class initialization order
 * - Fixed asset loading
 * - English frontend for UK market
 * - Centralized media handling
 * - Interactive ingredient modals with database integration
 */

// Prevent direct file loading
if (!defined('ABSPATH')) {
    exit;
}

// === PLUGIN CONSTANTS ===
define('HERBAL_MIX_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HERBAL_MIX_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HERBAL_MIX_VERSION', '1.1');

// === LOAD CORE CLASSES IN PROPER ORDER ===
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-database.php';
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-media-handler.php'; // Load media handler first
require_once HERBAL_MIX_PLUGIN_PATH . 'includes/class-herbal-mix-template-handler.php'; // NEW: Template system
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
    
    // 3. Core functionality
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
    
    // 4. Admin panel (only in admin)
    if (is_admin() && class_exists('Herbal_Mix_Admin_Panel')) {
        new Herbal_Mix_Admin_Panel();
    }
    
    // 5. Load points system and profile integration
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
    
    // Set default template override setting
    add_option('herbal_enable_template_override', 1);
    
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
            $admin_css = HERBAL_MIX_PLUGIN_PATH . 'assets/css/herbal-admin.css';
            if (file_exists($admin_css)) {
                wp_enqueue_style(
                    'herbal-admin-css',
                    HERBAL_MIX_PLUGIN_URL . 'assets/css/herbal-admin.css',
                    [],
                    filemtime($admin_css)
                );
            }
        }
        
        // Load admin panel assets on plugin pages
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'herbal-mix') !== false) {
            $admin_panel_css = HERBAL_MIX_PLUGIN_PATH . 'assets/css/admin-panel.css';
            if (file_exists($admin_panel_css)) {
                wp_enqueue_style(
                    'herbal-admin-panel-css',
                    HERBAL_MIX_PLUGIN_URL . 'assets/css/admin-panel.css',
                    [],
                    filemtime($admin_panel_css)
                );
            }
            
            $admin_panel_js = HERBAL_MIX_PLUGIN_PATH . 'assets/js/admin-panel.js';
            if (file_exists($admin_panel_js)) {
                wp_enqueue_script(
                    'herbal-admin-panel-js',
                    HERBAL_MIX_PLUGIN_URL . 'assets/js/admin-panel.js',
                    ['jquery'],
                    filemtime($admin_panel_js),
                    true
                );
            }
        }
    }
}

// === NEW: AJAX HANDLER FOR INGREDIENT DETAILS (Template System) ===
add_action('wp_ajax_get_ingredient_details', 'herbal_ajax_get_ingredient_details');
add_action('wp_ajax_nopriv_get_ingredient_details', 'herbal_ajax_get_ingredient_details');

function herbal_ajax_get_ingredient_details() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'herbal_template_nonce')) {
        wp_send_json_error(__('Invalid security token.', 'herbal-mix-creator2'));
    }
    
    $ingredient_id = intval($_POST['ingredient_id'] ?? 0);
    if (!$ingredient_id) {
        wp_send_json_error(__('Invalid ingredient ID.', 'herbal-mix-creator2'));
    }
    
    global $wpdb;
    
    $ingredient = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name, description, story, image_url 
         FROM {$wpdb->prefix}herbal_ingredients 
         WHERE id = %d AND visible = 1",
        $ingredient_id
    ));
    
    if (!$ingredient) {
        wp_send_json_error(__('Ingredient not found.', 'herbal-mix-creator2'));
    }
    
    $data = array(
        'id' => $ingredient->id,
        'name' => $ingredient->name,
        'description' => $ingredient->description ?: __('No description available.', 'herbal-mix-creator2'),
        'story' => $ingredient->story ?: '',
        'image_url' => $ingredient->image_url ?: ''
    );
    
    wp_send_json_success($data);
}

// === NEW: ADMIN SETTINGS FOR TEMPLATE OVERRIDE ===
add_action('admin_init', 'herbal_register_template_settings');

function herbal_register_template_settings() {
    // Only register if we're on a relevant admin page
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'herbal-mix') === false) {
        return;
    }
    
    register_setting(
        'herbal_mix_settings',
        'herbal_enable_template_override',
        array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        )
    );
    
    add_settings_section(
        'herbal_template_section',
        __('Product Template Settings', 'herbal-mix-creator2'),
        'herbal_template_section_callback',
        'herbal_mix_settings'
    );
    
    add_settings_field(
        'herbal_enable_template_override',
        __('Enable Premium Template', 'herbal-mix-creator2'),
        'herbal_template_override_callback',
        'herbal_mix_settings',
        'herbal_template_section'
    );
}

function herbal_template_section_callback() {
    echo '<p>' . esc_html__('Configure how herbal products are displayed in your store.', 'herbal-mix-creator2') . '</p>';
}

function herbal_template_override_callback() {
    $value = get_option('herbal_enable_template_override', 1);
    echo '<label>';
    echo '<input type="checkbox" name="herbal_enable_template_override" value="1" ' . checked(1, $value, false) . ' />';
    echo ' ' . esc_html__('Use premium herbal product template', 'herbal-mix-creator2');
    echo '</label>';
    echo '<p class="description">' . esc_html__('When enabled, herbal products will use a premium, spacious template design with interactive ingredient information and enhanced display of mix stories and creator details.', 'herbal-mix-creator2') . '</p>';
}

// === NEW: TEMPLATE DIRECTORY CREATION ===
add_action('init', 'herbal_ensure_template_directories');

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

// === PLUGIN UPDATE/MIGRATION ===
add_action('admin_init', 'herbal_check_plugin_update');

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

// === LANGUAGE LOADING ===
add_action('plugins_loaded', 'herbal_load_textdomain');

function herbal_load_textdomain() {
    load_plugin_textdomain(
        'herbal-mix-creator2',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

// === DEBUG FUNCTIONS (Remove in production) ===
if (defined('WP_DEBUG') && WP_DEBUG) {
    
    // Debug: Verify template assets loading
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
                echo 'hasCreator: ' . (!empty($has_creator) ? 'true' : 'false') . ',';
                echo 'hasPoints: ' . (!empty($has_points) ? 'true' : 'false');
                echo '});</script>';
            }
        }
    }
    
    // Debug: Check template files exist
    add_action('admin_notices', 'herbal_debug_template_files');
    
    function herbal_debug_template_files() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $template_file = HERBAL_MIX_PLUGIN_PATH . 'includes/templates/single-product-herbal.php';
        $css_file = HERBAL_MIX_PLUGIN_PATH . 'assets/css/herbal-product-template.css';
        $js_file = HERBAL_MIX_PLUGIN_PATH . 'assets/js/herbal-product-template.js';
        
        $missing_files = [];
        if (!file_exists($template_file)) $missing_files[] = 'Template file';
        if (!file_exists($css_file)) $missing_files[] = 'CSS file';
        if (!file_exists($js_file)) $missing_files[] = 'JavaScript file';
        
        if (!empty($missing_files)) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Herbal Mix Creator:</strong> Missing template files: ' . implode(', ', $missing_files);
            echo '. Please ensure all template files are uploaded correctly.';
            echo '</p></div>';
        }
    }
}

// === END OF FILE ===