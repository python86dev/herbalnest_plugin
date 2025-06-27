<?php
/**
 * Herbal Mix Template Handler
 * Handles WooCommerce template overrides for premium herbal tea products
 * 
 * File: includes/class-herbal-mix-template-handler.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Herbal_Mix_Template_Handler {

    public function __construct() {
        // Template overrides
        add_filter('woocommerce_locate_template', array($this, 'locate_plugin_template'), 10, 3);
        
        // Remove default WooCommerce hooks for custom products
        add_action('woocommerce_single_product_summary', array($this, 'maybe_override_product_hooks'), 1);
        
        // Enqueue premium template assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_template_assets'));
        
        // Add template data for JavaScript
        add_action('wp_footer', array($this, 'add_template_data'));
        
        // Create template directory if needed
        add_action('init', array($this, 'ensure_template_directory'));
    }

    /**
     * Locate plugin templates
     */
    public function locate_plugin_template($template, $template_name, $template_path) {
        // Only override single product template for herbal products
        if ($template_name === 'single-product.php') {
            // Check if current product has herbal mix data
            global $product;
            if ($product && $this->is_herbal_mix_product($product)) {
                $plugin_template = $this->get_plugin_template_path('single-product-herbal.php');
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Herbal Template: Attempting to load template: $plugin_template");
                    error_log("Template exists: " . (file_exists($plugin_template) ? 'YES' : 'NO'));
                }
                
                if (file_exists($plugin_template)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("Herbal Template: Loading custom template for product ID: " . $product->get_id());
                    }
                    return $plugin_template;
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG && $product) {
                    error_log("Herbal Template: Product ID " . $product->get_id() . " is NOT a herbal product - using default template");
                }
            }
        }
        
        return $template;
    }

    /**
     * Check if product is a herbal mix product
     */
    private function is_herbal_mix_product($product) {
        if (!$product) {
            return false;
        }
        
        // Check if template override is enabled in admin
        if (!get_option('herbal_enable_template_override', 1)) {
            return false;
        }
        
        $product_id = $product->get_id();
        
        // Check if product has herbal mix meta data
        $has_ingredients = get_post_meta($product_id, 'product_ingredients', true);
        $has_story = get_post_meta($product_id, 'mix_story', true);
        $has_creator = get_post_meta($product_id, 'mix_creator_name', true);
        $has_points = get_post_meta($product_id, 'price_point', true);
        
        $is_herbal = !empty($has_ingredients) || !empty($has_story) || !empty($has_creator) || !empty($has_points);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Herbal Template Debug - Product ID: $product_id");
            error_log("Has ingredients: " . (!empty($has_ingredients) ? 'YES' : 'NO'));
            error_log("Has story: " . (!empty($has_story) ? 'YES' : 'NO'));
            error_log("Has creator: " . (!empty($has_creator) ? 'YES' : 'NO'));
            error_log("Has points: " . (!empty($has_points) ? 'YES' : 'NO'));
            error_log("Is herbal product: " . ($is_herbal ? 'YES' : 'NO'));
        }
        
        return $is_herbal;
    }

    /**
     * Get plugin template path
     */
    private function get_plugin_template_path($template_name) {
        return HERBAL_MIX_PLUGIN_PATH . 'includes/templates/' . $template_name;
    }

    /**
     * Maybe override default WooCommerce hooks for herbal products
     */
    public function maybe_override_product_hooks() {
        global $product;
        
        if ($product && $this->is_herbal_mix_product($product)) {
            // Remove default WooCommerce product meta display
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);
            
            // Remove default product data tabs (Description, Reviews, etc.)
            remove_action('woocommerce_output_product_data_tabs', 'woocommerce_output_product_data_tabs', 25);
            
            // Remove existing herbal mix displays to avoid duplication
            if (class_exists('Herbal_Mix_Product_Meta')) {
                remove_action('woocommerce_single_product_summary', array('Herbal_Mix_Product_Meta', 'display_product_points_info'), 25);
                remove_action('woocommerce_single_product_summary', array('Herbal_Mix_Product_Meta', 'display_herbal_mix_details'), 35);
                
                // Try to remove instance methods as well
                $GLOBALS['herbal_product_meta_instance'] = new Herbal_Mix_Product_Meta();
                remove_action('woocommerce_single_product_summary', array($GLOBALS['herbal_product_meta_instance'], 'display_product_points_info'), 25);
                remove_action('woocommerce_single_product_summary', array($GLOBALS['herbal_product_meta_instance'], 'display_herbal_mix_details'), 35);
            }
        }
    }

    /**
     * Enqueue template assets
     */
    public function enqueue_template_assets() {
        global $product;
        
        if (is_product() && $product && $this->is_herbal_mix_product($product)) {
            // Google Fonts
            wp_enqueue_style(
                'herbal-template-fonts',
                'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap',
                array(),
                HERBAL_MIX_VERSION
            );
            
            // Template CSS
            $css_file = HERBAL_MIX_PLUGIN_PATH . 'assets/css/herbal-product-template.css';
            if (file_exists($css_file)) {
                wp_enqueue_style(
                    'herbal-product-template',
                    HERBAL_MIX_PLUGIN_URL . 'assets/css/herbal-product-template.css',
                    array(),
                    filemtime($css_file)
                );
            }
            
            // Template JavaScript
            $js_file = HERBAL_MIX_PLUGIN_PATH . 'assets/js/herbal-product-template.js';
            if (file_exists($js_file)) {
                wp_enqueue_script(
                    'herbal-product-template',
                    HERBAL_MIX_PLUGIN_URL . 'assets/js/herbal-product-template.js',
                    array('jquery'),
                    filemtime($js_file),
                    true
                );
            }
        }
    }

    /**
     * Add template data for JavaScript
     */
    public function add_template_data() {
        global $product;
        
        if (is_product() && $product && $this->is_herbal_mix_product($product)) {
            $product_id = $product->get_id();
            
            // Get ingredient details for modal
            $ingredient_details = array();
            $herbal_data = self::get_product_template_data($product_id);
            
            if (!empty($herbal_data['ingredients'])) {
                foreach ($herbal_data['ingredients'] as $ingredient) {
                    $ingredient_details[$ingredient['id']] = array(
                        'id' => $ingredient['id'],
                        'name' => $ingredient['name'],
                        'description' => $ingredient['description'],
                        'story' => $ingredient['story'],
                        'image_url' => $ingredient['image_url'],
                        'weight' => $ingredient['weight']
                    );
                }
            }
            
            $template_data = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('herbal_template_nonce'),
                'product_id' => $product_id,
                'ingredient_details' => $ingredient_details,
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'translations' => array(
                    'loading' => __('Loading...', 'herbal-mix-creator2'),
                    'error' => __('An error occurred', 'herbal-mix-creator2'),
                    'close' => __('Close', 'herbal-mix-creator2'),
                    'added_to_cart' => __('Added to Basket!', 'herbal-mix-creator2'),
                    'add_to_cart' => __('Add to Basket', 'herbal-mix-creator2'),
                    'description' => __('Description', 'herbal-mix-creator2'),
                    'story' => __('Traditional Uses & Story', 'herbal-mix-creator2'),
                    'no_description' => __('No description available.', 'herbal-mix-creator2'),
                )
            );
            
            wp_localize_script('herbal-product-template', 'herbalTemplateData', $template_data);
        }
    }

    /**
     * Get ingredient details from database
     */
    private function get_ingredient_details($ingredient_id) {
        global $wpdb;
        
        $ingredient = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, description, story, image_url 
             FROM {$wpdb->prefix}herbal_ingredients 
             WHERE id = %d AND visible = 1",
            $ingredient_id
        ));
        
        if ($ingredient) {
            return array(
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'description' => $ingredient->description ?: __('No description available.', 'herbal-mix-creator2'),
                'story' => $ingredient->story ?: '',
                'image_url' => $ingredient->image_url ?: ''
            );
        }
        
        return null;
    }

    /**
     * Get packaging details from database
     */
    public static function get_packaging_details($packaging_id) {
        global $wpdb;
        
        $packaging = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, herb_capacity, image_url, price, price_point, point_earned 
             FROM {$wpdb->prefix}herbal_packaging 
             WHERE id = %d AND available = 1",
            $packaging_id
        ));
        
        if ($packaging) {
            return array(
                'id' => $packaging->id,
                'name' => $packaging->name,
                'capacity' => $packaging->herb_capacity . 'g',
                'image_url' => $packaging->image_url ?: '',
                'price' => $packaging->price,
                'price_point' => $packaging->price_point,
                'point_earned' => $packaging->point_earned
            );
        }
        
        return null;
    }

    /**
     * Ensure template directory exists
     */
    public function ensure_template_directory() {
        $template_dir = HERBAL_MIX_PLUGIN_PATH . 'includes/templates/';
        if (!is_dir($template_dir)) {
            wp_mkdir_p($template_dir);
        }
    }

    /**
     * Get product meta data for template
     */
    public static function get_product_template_data($product_id) {
        $data = array(
            'price_point' => get_post_meta($product_id, 'price_point', true),
            'point_earned' => get_post_meta($product_id, 'point_earned', true),
            'mix_story' => get_post_meta($product_id, 'mix_story', true),
            'mix_creator_name' => get_post_meta($product_id, 'mix_creator_name', true),
            'packaging_id' => get_post_meta($product_id, 'packaging_id', true),
            'product_ingredients' => get_post_meta($product_id, 'product_ingredients', true),
        );
        
        // Parse ingredients JSON and get full details from database
        if ($data['product_ingredients']) {
            $ingredients_json = json_decode($data['product_ingredients'], true);
            if (is_array($ingredients_json)) {
                $data['ingredients'] = self::get_ingredients_with_details($ingredients_json);
            } else {
                $data['ingredients'] = array();
            }
        } else {
            $data['ingredients'] = array();
        }
        
        // Get packaging details
        if ($data['packaging_id']) {
            $data['packaging'] = self::get_packaging_details($data['packaging_id']);
        }
        
        return $data;
    }

    /**
     * Get ingredients with full details from database
     */
    private static function get_ingredients_with_details($ingredients_json) {
        global $wpdb;
        
        $ingredients_with_details = array();
        
        foreach ($ingredients_json as $ingredient) {
            $ingredient_id = intval($ingredient['id'] ?? 0);
            if (!$ingredient_id) {
                continue;
            }
            
            // Get full ingredient details from database
            $ingredient_details = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, description, story, image_url, price, price_point, point_earned 
                 FROM {$wpdb->prefix}herbal_ingredients 
                 WHERE id = %d AND visible = 1",
                $ingredient_id
            ));
            
            if ($ingredient_details) {
                $ingredients_with_details[] = array(
                    'id' => $ingredient_details->id,
                    'name' => $ingredient_details->name,
                    'weight' => floatval($ingredient['weight'] ?? 0),
                    'description' => $ingredient_details->description ?: __('No description available.', 'herbal-mix-creator2'),
                    'story' => $ingredient_details->story ?: '',
                    'image_url' => $ingredient_details->image_url ?: '',
                    'price_per_gram' => floatval($ingredient_details->price),
                    'points_per_gram' => floatval($ingredient_details->price_point),
                    'earned_per_gram' => floatval($ingredient_details->point_earned)
                );
            }
        }
        
        return $ingredients_with_details;
    }
}