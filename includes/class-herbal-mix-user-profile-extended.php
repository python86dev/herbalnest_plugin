<?php
/**
 * KOMPLETNY: Enhanced User Profile Extended Class
 * Plik: includes/class-herbal-mix-user-profile-extended.php
 * 
 * PEŁNA WERSJA z:
 * - Publikowaniem używającym szablonu "Custom User Mix" (ID: 57)
 * - Kupowaniem używającym szablonu "Custom Herbal Mix" (ID: 96) 
 * - Wszystkimi metodami AJAX (Edit, Publish, Delete, View, Buy)
 * - Zarządzaniem produktami WooCommerce
 * - Systemem punktów i nagród
 */

if (!defined('ABSPATH')) exit;

class HerbalMixUserProfileExtended {
    
    // ID szablonów produktów (z twojego screenshota)
    const PUBLIC_TEMPLATE_ID = 57;   // "Custom User Mix" - publiczne produkty po publikacji
    const PRIVATE_TEMPLATE_ID = 96;  // "Custom Herbal Mix" - prywatne produkty do kupowania
    
    public function __construct() {
        // Initialize points for new users
        add_action('user_register', array($this, 'initialize_user_points'));
        
        // Use priority 15 (later) to run after HerbalProfileIntegration
        add_filter('woocommerce_account_menu_items', array($this, 'add_mix_menu_items'), 15);
        add_action('init', array($this, 'add_custom_endpoints'));
        
        // Register tab content handlers
        add_action('woocommerce_account_my-mixes_endpoint', array($this, 'render_my_mixes_tab'));
        add_action('woocommerce_account_favorite-mixes_endpoint', array($this, 'render_favorite_mixes_tab'));
        
        // Profile additional fields
        add_action('woocommerce_edit_account_form', array($this, 'add_avatar_to_account_form'));
        add_action('woocommerce_save_account_details', array($this, 'save_extra_account_details'));
        add_filter('get_avatar_url', array($this, 'custom_avatar_url'), 10, 3);
        
        // AJAX handlers for mix management - WSZYSTKIE
        add_action('wp_ajax_get_mix_details', array($this, 'ajax_get_mix_details'));
        add_action('wp_ajax_get_mix_recipe_and_pricing', array($this, 'ajax_get_mix_recipe_and_pricing'));
        add_action('wp_ajax_update_mix_details', array($this, 'ajax_update_mix_details'));
        add_action('wp_ajax_publish_mix', array($this, 'ajax_publish_mix'));
        add_action('wp_ajax_delete_mix', array($this, 'ajax_delete_mix'));
        add_action('wp_ajax_view_mix', array($this, 'ajax_view_mix'));
        add_action('wp_ajax_remove_favorite_mix', array($this, 'ajax_remove_favorite_mix'));
        add_action('wp_ajax_buy_mix', array($this, 'ajax_buy_mix'));
        
        // Load profile assets when needed
        add_action('wp_enqueue_scripts', array($this, 'enqueue_profile_assets'));
    }
    
    /**
     * Initialize points for new users
     */
    public function initialize_user_points($user_id) {
        if (class_exists('Herbal_Mix_Reward_Points')) {
            Herbal_Mix_Reward_Points::add_points($user_id, 100, 'registration_bonus', 'Welcome bonus points');
        }
    }
    
    /**
     * Add custom endpoints for mix management
     */
    public function add_custom_endpoints() {
        add_rewrite_endpoint('my-mixes', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('favorite-mixes', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Add mix menu items to account menu
     */
    public function add_mix_menu_items($items) {
        $new_items = array();
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            if ($key === 'dashboard') {
                $new_items['my-mixes'] = __('My Mixes', 'herbal-mix-creator2');
                $new_items['favorite-mixes'] = __('Favorite Mixes', 'herbal-mix-creator2');
            }
        }
        return $new_items;
    }
    
    /**
     * AJAX - Get mix recipe and pricing details
     */
    public function ajax_get_mix_recipe_and_pricing() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $mix_id = intval($_POST['mix_id']);
        $action_type = sanitize_text_field($_POST['action_type']); // 'edit', 'publish', or 'view'
        
        if (!$mix_id) {
            wp_send_json_error('Invalid mix ID.');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // For 'edit' or 'publish' - check user ownership, for 'view' - check if published
        if ($action_type === 'edit' || $action_type === 'publish') {
            $user_id = get_current_user_id();
            $mix = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $table 
                WHERE id = %d AND user_id = %d
            ", $mix_id, $user_id));
        } else {
            $mix = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $table 
                WHERE id = %d AND status = 'published'
            ", $mix_id));
        }
        
        if (!$mix) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        // Parse mix data
        $mix_data = json_decode($mix->mix_data, true);
        if (!$mix_data) {
            wp_send_json_error('Invalid mix data.');
        }
        
        // Get detailed recipe information
        $recipe_details = $this->build_recipe_details_fixed($mix_data);
        
        if (!$recipe_details) {
            wp_send_json_error('Unable to load recipe details.');
        }
        
        wp_send_json_success(array(
            'mix' => array(
                'id' => $mix->id,
                'name' => $mix->mix_name,
                'description' => $mix->mix_description,
                'image' => $mix->mix_image,
                'status' => $mix->status
            ),
            'recipe' => $recipe_details
        ));
    }
    
    /**
     * Build recipe details from mix data - obsługuje wszystkie struktury danych
     */
    private function build_recipe_details_fixed($mix_data) {
        if (empty($mix_data)) {
            return null;
        }
        
        global $wpdb;
        
        $details = array(
            'packaging' => array(),
            'ingredients' => array(),
            'total_weight' => 0,
            'total_price' => 0,
            'total_points' => 0
        );
        
        // Handle different packaging data structures
        $packaging_id = null;
        if (isset($mix_data['packaging']['id'])) {
            $packaging_id = intval($mix_data['packaging']['id']);
        } elseif (isset($mix_data['packaging_id'])) {
            $packaging_id = intval($mix_data['packaging_id']);
        } elseif (isset($mix_data['packaging']) && is_numeric($mix_data['packaging'])) {
            $packaging_id = intval($mix_data['packaging']);
        }
        
        // Get packaging details using correct column names from database
        if ($packaging_id > 0) {
            $packaging = $wpdb->get_row($wpdb->prepare("
                SELECT id, name, herb_capacity, image_url, price, price_point, point_earned
                FROM {$wpdb->prefix}herbal_packaging 
                WHERE id = %d AND available = 1
            ", $packaging_id));
            
            if ($packaging) {
                $details['packaging'] = array(
                    'id' => $packaging->id,
                    'name' => $packaging->name,
                    'capacity' => intval($packaging->herb_capacity),
                    'price' => floatval($packaging->price),
                    'points' => floatval($packaging->price_point),
                    'points_earned' => floatval($packaging->point_earned),
                    'image' => $packaging->image_url
                );
                $details['total_price'] += floatval($packaging->price);
                $details['total_points'] += floatval($packaging->price_point);
            }
        }
        
        // Handle ingredients with proper database queries
        if (isset($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
            foreach ($mix_data['ingredients'] as $ingredient_data) {
                $ingredient_id = 0;
                $weight = 0;
                
                // Handle different ingredient data structures
                if (isset($ingredient_data['id'])) {
                    $ingredient_id = intval($ingredient_data['id']);
                }
                if (isset($ingredient_data['weight'])) {
                    $weight = floatval($ingredient_data['weight']);
                }
                
                if ($ingredient_id > 0 && $weight > 0) {
                    // Get ingredient details using correct column names
                    $ingredient = $wpdb->get_row($wpdb->prepare("
                        SELECT id, name, price, price_point, point_earned, image_url, description
                        FROM {$wpdb->prefix}herbal_ingredients 
                        WHERE id = %d AND visible = 1
                    ", $ingredient_id));
                    
                    if ($ingredient) {
                        $price_per_gram = floatval($ingredient->price);
                        $points_per_gram = floatval($ingredient->price_point);
                        $points_earned_per_gram = floatval($ingredient->point_earned);
                        
                        $ingredient_total_price = $weight * $price_per_gram;
                        $ingredient_total_points = $weight * $points_per_gram;
                        $ingredient_points_earned = $weight * $points_earned_per_gram;
                        
                        $details['ingredients'][] = array(
                            'id' => $ingredient->id,
                            'name' => $ingredient->name,
                            'weight' => $weight,
                            'price_per_gram' => $price_per_gram,
                            'total_price' => $ingredient_total_price,
                            'points_per_gram' => $points_per_gram,
                            'total_points' => $ingredient_total_points,
                            'points_earned' => $ingredient_points_earned,
                            'image' => $ingredient->image_url,
                            'description' => $ingredient->description
                        );
                        
                        $details['total_weight'] += $weight;
                        $details['total_price'] += $ingredient_total_price;
                        $details['total_points'] += $ingredient_total_points;
                    }
                }
            }
        }
        
        // Format all totals
        $details['total_weight'] = round($details['total_weight'], 1);
        $details['total_price'] = round($details['total_price'], 2);
        $details['total_points'] = round($details['total_points']);
        
        return $details;
    }
    
    /**
     * AJAX: Get mix details (basic info only)
     */
    public function ajax_get_mix_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE id = %d AND user_id = %d
        ", $mix_id, $user_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        wp_send_json_success(array(
            'id' => $mix->id,
            'name' => $mix->mix_name,
            'description' => $mix->mix_description,
            'image' => $mix->mix_image,
            'status' => $mix->status,
            'created_at' => $mix->created_at
        ));
    }
    
    /**
     * AJAX: Update mix details (name, description, image)
     */
    public function ajax_update_mix_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $mix_id = intval($_POST['mix_id']);
        $mix_name = sanitize_text_field($_POST['mix_name']);
        $mix_description = isset($_POST['mix_description']) ? sanitize_textarea_field($_POST['mix_description']) : '';
        $mix_image = isset($_POST['mix_image']) ? sanitize_text_field($_POST['mix_image']) : '';
        $user_id = get_current_user_id();
        
        if (empty($mix_name)) {
            wp_send_json_error('Mix name is required.');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Verify ownership
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT id FROM $table 
            WHERE id = %d AND user_id = %d
        ", $mix_id, $user_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        // Update mix
        $result = $wpdb->update(
            $table,
            array(
                'mix_name' => $mix_name,
                'mix_description' => $mix_description,
                'mix_image' => $mix_image
            ),
            array('id' => $mix_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update mix: ' . $wpdb->last_error);
        }
        
        wp_send_json_success('Mix updated successfully!');
    }
    
    /**
     * AJAX: Publikowanie mieszanki używając szablonu "Custom User Mix" (ID: 57)
     */
    /**
 * NAPRAWIONA FUNKCJA: ajax_publish_mix z poprawnym error handling
 * Zastąp tę funkcję w includes/class-herbal-mix-user-profile-extended.php
 */
public function ajax_publish_mix() {
    // Wzmocnione debugowanie
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('=== PUBLISH MIX DEBUG START ===');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('User ID: ' . get_current_user_id());
    }
    
    try {
        // 1. Sprawdź nonce - obsłuż różne nazwy
        $nonce_verified = false;
        if (isset($_POST['nonce'])) {
            if (wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
                $nonce_verified = true;
            } elseif (wp_verify_nonce($_POST['nonce'], 'herbal_mix_publish_nonce')) {
                $nonce_verified = true;
            }
        }
        
        if (!$nonce_verified) {
            wp_send_json_error('Security check failed. Please refresh the page.');
            return;
        }
        
        // 2. Sprawdź czy użytkownik jest zalogowany
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to publish mixes.');
            return;
        }
        
        // 3. Pobierz i zwaliduj dane
        $mix_id = intval($_POST['mix_id'] ?? 0);
        if ($mix_id <= 0) {
            wp_send_json_error('Invalid mix ID provided.');
            return;
        }
        
        $mix_name = isset($_POST['mix_name']) ? sanitize_text_field($_POST['mix_name']) : '';
        $mix_description = isset($_POST['mix_description']) ? sanitize_textarea_field($_POST['mix_description']) : '';
        $mix_image = isset($_POST['mix_image']) ? esc_url_raw($_POST['mix_image']) : '';
        $user_id = get_current_user_id();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Validated data - Mix ID: ' . $mix_id . ', Name: ' . $mix_name . ', User ID: ' . $user_id);
        }
        
        // 4. Pobierz mieszankę z bazy danych
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE id = %d AND user_id = %d
        ", $mix_id, $user_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or you do not have permission to publish it.');
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Mix found: ' . $mix->mix_name . ', Status: ' . $mix->status);
        }
        
        // 5. Sprawdź czy mieszanka już została opublikowana
        if ($mix->status === 'published') {
            wp_send_json_error('This mix has already been published.');
            return;
        }
        
        // 6. Walidacja wymaganych danych
        if (empty($mix->mix_name) && empty($mix_name)) {
            wp_send_json_error('Mix must have a name to be published.');
            return;
        }
        
        if (empty($mix->mix_data)) {
            wp_send_json_error('Mix must have ingredients to be published.');
            return;
        }
        
        // 7. Parsuj dane mieszanki
        $mix_data = json_decode($mix->mix_data, true);
        if (!$mix_data || !is_array($mix_data)) {
            wp_send_json_error('Invalid mix data format. Please recreate your mix.');
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Mix data parsed successfully');
        }
        
        // 8. NAJPIERW - zaktualizuj dane mieszanki w bazie danych
        $final_name = !empty($mix_name) ? $mix_name : $mix->mix_name;
        $final_description = !empty($mix_description) ? $mix_description : $mix->mix_description;
        $final_image = !empty($mix_image) ? $mix_image : $mix->mix_image;
        
        // Zaktualizuj bazę danych PRZED tworzeniem produktu
        $update_data = array(
            'mix_name' => $final_name,
            'mix_description' => $final_description,
            'mix_image' => $final_image
        );
        
        $update_result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $mix_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($update_result === false) {
            wp_send_json_error('Failed to update mix data: ' . $wpdb->last_error);
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Mix data updated in database');
        }
        
        // 9. Oblicz szczegóły receptury
        $recipe_details = $this->calculate_recipe_for_publish($mix_data);
        if (is_wp_error($recipe_details)) {
            wp_send_json_error('Unable to calculate mix pricing: ' . $recipe_details->get_error_message());
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Recipe calculated - Price: ' . $recipe_details['total_price'] . ', Points: ' . $recipe_details['total_price_points']);
        }
        
        // 10. Sprawdź czy szablon istnieje
        $template = get_post(self::PUBLIC_TEMPLATE_ID);
        if (!$template || $template->post_type !== 'product') {
            wp_send_json_error('Product template not found (ID: ' . self::PUBLIC_TEMPLATE_ID . '). Please contact administrator.');
            return;
        }
        
        // 11. Utwórz produkt WooCommerce
        $product_id = $this->create_woocommerce_product_for_publish($mix, $recipe_details, $final_name, $final_description, $final_image, $user_id);
        
        if (is_wp_error($product_id)) {
            wp_send_json_error('Failed to create WooCommerce product: ' . $product_id->get_error_message());
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Product created with ID: ' . $product_id);
        }
        
        // 12. Zaktualizuj status mieszanki na 'published'
        $status_update = $wpdb->update(
            $table,
            array(
                'status' => 'published',
                'base_product_id' => $product_id
            ),
            array('id' => $mix_id),
            array('%s', '%d'),
            array('%d')
        );
        
        if ($status_update === false) {
            // Usuń produkt jeśli aktualizacja statusu nie powiodła się
            wp_delete_post($product_id, true);
            wp_send_json_error('Failed to update mix status: ' . $wpdb->last_error);
            return;
        }
        
        // 13. Przyznaj punkty za publikację
        if (class_exists('Herbal_Mix_Reward_Points')) {
            Herbal_Mix_Reward_Points::add_points($user_id, 50, 'mix_published', 'Published mix: ' . $final_name);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('=== PUBLISH MIX SUCCESS ===');
        }
        
        // 14. Zwróć sukces
        wp_send_json_success([
            'message' => 'Mix published successfully! You earned 50 points.',
            'product_id' => $product_id,
            'product_url' => get_permalink($product_id),
            'points_earned' => 50
        ]);
        
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('PUBLISH MIX EXCEPTION: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }
        wp_send_json_error('Unexpected error occurred: ' . $e->getMessage());
    }
}

/**
 * NOWA FUNKCJA: Oblicz recepturę dla publikowania
 */
private function calculate_recipe_for_publish($mix_data) {
    global $wpdb;
    
    try {
        $recipe = [
            'total_price' => 0,
            'total_price_points' => 0,
            'total_points_earned' => 0,
            'total_weight' => 0,
            'ingredients' => [],
            'packaging' => null
        ];
        
        // Sprawdź strukturę danych opakowania
        $packaging_id = null;
        if (isset($mix_data['packaging']['id'])) {
            $packaging_id = intval($mix_data['packaging']['id']);
        } elseif (isset($mix_data['packaging_id'])) {
            $packaging_id = intval($mix_data['packaging_id']);
        }
        
        // Pobierz dane opakowania
        if ($packaging_id > 0) {
            $packaging = $wpdb->get_row($wpdb->prepare("
                SELECT id, name, herb_capacity, price, price_point, point_earned, image_url
                FROM {$wpdb->prefix}herbal_packaging 
                WHERE id = %d AND available = 1
            ", $packaging_id));
            
            if ($packaging) {
                $recipe['total_price'] += (float) $packaging->price;
                $recipe['total_price_points'] += (float) $packaging->price_point;
                $recipe['total_points_earned'] += (float) $packaging->point_earned;
                $recipe['packaging'] = [
                    'id' => $packaging->id,
                    'name' => $packaging->name,
                    'capacity' => $packaging->herb_capacity,
                    'price' => $packaging->price,
                    'image_url' => $packaging->image_url
                ];
            }
        }
        
        // Pobierz dane składników
        if (isset($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
            foreach ($mix_data['ingredients'] as $ingredient_data) {
                $ingredient_id = intval($ingredient_data['id'] ?? 0);
                $weight = (float) ($ingredient_data['weight'] ?? 0);
                
                if ($ingredient_id > 0 && $weight > 0) {
                    $ingredient = $wpdb->get_row($wpdb->prepare("
                        SELECT id, name, price, price_point, point_earned, description, image_url
                        FROM {$wpdb->prefix}herbal_ingredients 
                        WHERE id = %d AND visible = 1
                    ", $ingredient_id));
                    
                    if ($ingredient) {
                        $ingredient_price = (float) $ingredient->price * $weight;
                        $ingredient_points_price = (float) $ingredient->price_point * $weight;
                        $ingredient_points_earned = (float) $ingredient->point_earned * $weight;
                        
                        $recipe['total_price'] += $ingredient_price;
                        $recipe['total_price_points'] += $ingredient_points_price;
                        $recipe['total_points_earned'] += $ingredient_points_earned;
                        $recipe['total_weight'] += $weight;
                        
                        $recipe['ingredients'][] = [
                            'id' => $ingredient->id,
                            'name' => $ingredient->name,
                            'weight' => $weight,
                            'price' => $ingredient_price,
                            'price_points' => $ingredient_points_price,
                            'points_earned' => $ingredient_points_earned,
                            'description' => $ingredient->description,
                            'image_url' => $ingredient->image_url
                        ];
                    }
                }
            }
        }
        
        // Zaokrąglij wszystkie ceny
        $recipe['total_price'] = round($recipe['total_price'], 2);
        $recipe['total_price_points'] = round($recipe['total_price_points'], 2);
        $recipe['total_points_earned'] = round($recipe['total_points_earned'], 2);
        
        return $recipe;
        
    } catch (Exception $e) {
        return new WP_Error('recipe_calculation_failed', 'Failed to calculate recipe: ' . $e->getMessage());
    }
}

/**
 * NOWA FUNKCJA: Utwórz produkt WooCommerce dla publikowania
 */
private function create_woocommerce_product_for_publish($mix, $recipe_details, $final_name, $final_description, $final_image, $user_id) {
    try {
        // Utwórz podstawowy produkt
        $product_data = [
            'post_title' => $final_name,
            'post_content' => $this->generate_product_content_for_publish($final_description, $recipe_details, $user_id),
            'post_excerpt' => wp_trim_words($final_description, 20, '...'),
            'post_status' => 'publish',
            'post_type' => 'product',
            'post_author' => get_current_user_id()
        ];
        
        $product_id = wp_insert_post($product_data);
        
        if (is_wp_error($product_id)) {
            return $product_id;
        }
        
        // Ustaw typ produktu WooCommerce
        wp_set_object_terms($product_id, 'simple', 'product_type');
        
        // === USTAW WSZYSTKIE WYMAGANE METADANE ===
        
        // 1. Nazwa mieszanki
        update_post_meta($product_id, 'mix_name', $final_name);
        
        // 2. Recepta mieszanki
        update_post_meta($product_id, 'product_ingredients', wp_json_encode($recipe_details['ingredients']));
        
        // 3. Autor mieszanki
        $author_data = $this->get_user_display_data($user_id);
        update_post_meta($product_id, 'mix_creator_name', $author_data['name']);
        
        // 4. ID autora
        update_post_meta($product_id, 'mix_creator_id', $user_id);
        
        // 5. Cena mieszanki
        update_post_meta($product_id, '_price', $recipe_details['total_price']);
        update_post_meta($product_id, '_regular_price', $recipe_details['total_price']);
        
        // 6. Cena w punktach (używaj prawidłowej nazwy kolumny)
        update_post_meta($product_id, 'price_point', $recipe_details['total_price_points']);
        
        // 7. Zdobyte punkty za zakup (używaj prawidłowej nazwy kolumny)
        update_post_meta($product_id, 'point_earned', $recipe_details['total_points_earned']);
        
        // 8. Opis mieszanki
        update_post_meta($product_id, 'mix_description', $final_description);
        
        // 9. Recepta mieszanki (pełne dane)
        update_post_meta($product_id, 'herbal_mix_data', $mix->mix_data);
        
        // 10. Opakowanie
        if (!empty($recipe_details['packaging'])) {
            update_post_meta($product_id, 'packaging_id', $recipe_details['packaging']['id']);
            update_post_meta($product_id, 'packaging_name', $recipe_details['packaging']['name']);
        }
        
        // === DODATKOWE METADANE ===
        update_post_meta($product_id, 'herbal_mix_id', $mix->id);
        update_post_meta($product_id, '_custom_mix_user', $user_id);
        update_post_meta($product_id, '_custom_mix_author', $author_data['name']);
        update_post_meta($product_id, '_custom_mix_created', current_time('mysql'));
        update_post_meta($product_id, 'total_weight', $recipe_details['total_weight']);
        
        // === USTAWIENIA WOOCOMMERCE ===
        update_post_meta($product_id, '_stock_status', 'instock');
        update_post_meta($product_id, '_manage_stock', 'no');
        update_post_meta($product_id, '_virtual', 'no');
        update_post_meta($product_id, '_downloadable', 'no');
        update_post_meta($product_id, '_sale_price', '');
        
        // 11. Zdjęcie mieszanki
        if (!empty($final_image)) {
            $this->set_product_featured_image($product_id, $final_image);
        }
        
        // 12. Przypisanie kategorii "Custom Mix"
        $this->assign_product_to_custom_mix_category($product_id);
        
        // Wyczyść cache WooCommerce
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
        
        return $product_id;
        
    } catch (Exception $e) {
        return new WP_Error('product_creation_failed', 'Failed to create product: ' . $e->getMessage());
    }
}

/**
 * FUNKCJA POMOCNICZA: Pobierz dane użytkownika
 */
private function get_user_display_data($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return ['name' => __('Unknown Author', 'herbal-mix-creator2'), 'id' => 0];
    }
    
    $nickname = get_user_meta($user_id, 'nickname', true);
    $display_name = !empty($nickname) ? $nickname : $user->display_name;
    
    return [
        'name' => $display_name,
        'id' => $user_id,
        'email' => $user->user_email
    ];
}

/**
 * FUNKCJA POMOCNICZA: Generuj treść produktu
 */
private function generate_product_content_for_publish($description, $recipe_details, $user_id) {
    $author_data = $this->get_user_display_data($user_id);
    
    $content = '<div class="custom-mix-product-content">';
    
    if (!empty($description)) {
        $content .= '<div class="mix-description">' . wp_kses_post($description) . '</div>';
    }
    
    $content .= '<div class="mix-details">';
    $content .= '<h4>' . __('Mix Details', 'herbal-mix-creator2') . '</h4>';
    $content .= '<ul>';
    $content .= '<li><strong>' . __('Created by:', 'herbal-mix-creator2') . '</strong> ' . esc_html($author_data['name']) . '</li>';
    $content .= '<li><strong>' . __('Total Weight:', 'herbal-mix-creator2') . '</strong> ' . $recipe_details['total_weight'] . 'g</li>';
    $content .= '<li><strong>' . __('Number of Ingredients:', 'herbal-mix-creator2') . '</strong> ' . count($recipe_details['ingredients']) . '</li>';
    
    if (!empty($recipe_details['packaging']['name'])) {
        $content .= '<li><strong>' . __('Packaging:', 'herbal-mix-creator2') . '</strong> ' . esc_html($recipe_details['packaging']['name']) . '</li>';
    }
    
    $content .= '</ul>';
    $content .= '</div>';
    
    $content .= '<div class="ingredients-list">';
    $content .= '<h4>' . __('Ingredients', 'herbal-mix-creator2') . '</h4>';
    $content .= '<ul>';
    foreach ($recipe_details['ingredients'] as $ingredient) {
        $content .= '<li>' . esc_html($ingredient['name']) . ' - ' . $ingredient['weight'] . 'g</li>';
    }
    $content .= '</ul>';
    $content .= '</div>';
    
    $content .= '</div>';
    
    return $content;
}

/**
 * FUNKCJA POMOCNICZA: Ustaw zdjęcie produktu
 */
private function set_product_featured_image($product_id, $image_url) {
    // Sprawdź czy obraz już istnieje w bibliotece mediów
    $attachment_id = attachment_url_to_postid($image_url);
    
    if ($attachment_id) {
        set_post_thumbnail($product_id, $attachment_id);
        return true;
    }
    
    // Jeśli nie ma, spróbuj utworzyć nowy attachment
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        return false;
    }
    
    $file_array = [
        'name' => basename($image_url),
        'tmp_name' => $tmp
    ];
    
    $attachment_id = media_handle_sideload($file_array, $product_id);
    
    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        return false;
    }
    
    set_post_thumbnail($product_id, $attachment_id);
    return true;
}

/**
 * FUNKCJA POMOCNICZA: Przypisz kategorię Custom Mix
 */
private function assign_product_to_custom_mix_category($product_id) {
    // Sprawdź czy kategoria istnieje
    $term = get_term_by('slug', 'custom-mix', 'product_cat');
    
    if (!$term) {
        // Utwórz kategorię jeśli nie istnieje
        $term_data = wp_insert_term(
            'Custom Mix',
            'product_cat',
            [
                'slug' => 'custom-mix',
                'description' => __('User-created herbal mixes', 'herbal-mix-creator2')
            ]
        );
        
        if (!is_wp_error($term_data)) {
            $term_id = $term_data['term_id'];
        } else {
            return false;
        }
    } else {
        $term_id = $term->term_id;
    }
    
    // Przypisz kategorię do produktu
    wp_set_post_terms($product_id, [$term_id], 'product_cat');
    return true;
}
    
    /**
     * AJAX: Buy mix - tworzy prywatny produkt używając szablonu "Custom Herbal Mix" (ID: 96)
     */
    public function ajax_buy_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to buy mixes.');
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Get mix (must be published or owned by user)
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE id = %d AND (status = 'published' OR user_id = %d)
        ", $mix_id, $user_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or not available for purchase.');
        }
        
        // Parse mix data and calculate pricing
        $mix_data = json_decode($mix->mix_data, true);
        if (!$mix_data) {
            wp_send_json_error('Invalid mix data.');
        }
        
        $recipe_details = $this->build_recipe_details_fixed($mix_data);
        if (!$recipe_details) {
            wp_send_json_error('Unable to calculate mix pricing.');
        }
        
        // Create or get existing PRIVATE product for this mix and user
        $product_id = $this->create_product_from_template(
            self::PRIVATE_TEMPLATE_ID,
            $mix->mix_name,
            $mix->mix_description,
            $recipe_details,
            $mix_data,
            $mix->mix_image,
            $user_id,
            'private' // Type: private product (not visible to others)
        );
        
        if (is_wp_error($product_id)) {
            wp_send_json_error('Failed to create product: ' . $product_id->get_error_message());
        }
        
        // Add to cart
        if (function_exists('WC')) {
            $cart_item_key = WC()->cart->add_to_cart($product_id, 1);
            
            if ($cart_item_key) {
                wp_send_json_success(array(
                    'message' => 'Mix added to cart successfully!',
                    'cart_url' => wc_get_cart_url(),
                    'product_id' => $product_id
                ));
            } else {
                wp_send_json_error('Failed to add mix to cart.');
            }
        } else {
            wp_send_json_error('WooCommerce is not available.');
        }
    }
    
    /**
 * NAPRAWIONA FUNKCJA: create_product_from_template dla publikowania mieszanek
 * Ta funkcja zastępuje istniejącą w class-herbal-mix-user-profile-extended.php
 * TYLKO dla przypadku publikowania (type = 'public', template ID 57)
 */
private function create_product_from_template($template_id, $mix_name, $mix_description, $recipe_details, $mix_data, $mix_image = '', $author_id = 0, $type = 'public') {
    // Sprawdź czy szablon istnieje
    $template = get_post($template_id);
    if (!$template || $template->post_type !== 'product') {
        return new WP_Error('template_not_found', 'Product template not found (ID: ' . $template_id . ')');
    }
    
    // Dla prywatnych produktów - użyj istniejącą logikę (nie zmieniamy)
    if ($type === 'private') {
        $existing_product = get_posts(array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_herbal_mix_id',
                    'value' => intval($_POST['mix_id']),
                    'compare' => '='
                ),
                array(
                    'key' => '_herbal_mix_buyer_id',
                    'value' => $author_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));
        
        if (!empty($existing_product)) {
            return $existing_product[0]->ID;
        }
    }
    
    // === NOWA LOGIKA DLA PUBLIKOWANIA (type = 'public') ===
    if ($type === 'public') {
        // Utwórz produkt bazując na szablonie ID 57
        $product_data = array(
            'post_title' => $mix_name,
            'post_content' => $this->generate_product_description_for_publish($mix_description, $recipe_details, $author_id),
            'post_excerpt' => wp_trim_words($mix_description, 20, '...'),
            'post_status' => 'publish',
            'post_type' => 'product',
            'post_author' => get_current_user_id()
        );
        
        $product_id = wp_insert_post($product_data);
        
        if (is_wp_error($product_id)) {
            return $product_id;
        }
        
        // Skopiuj podstawowe meta dane z szablonu (ale nie mix-specific)
        $this->copy_template_meta_for_publish($template_id, $product_id);
        
        // Ustaw typ produktu WooCommerce
        wp_set_object_terms($product_id, 'simple', 'product_type');
        
        // === PRZENIEŚ WSZYSTKIE WYMAGANE DANE Z MIESZANKI ===
        
        // 1. Nazwa mieszanki
        update_post_meta($product_id, 'mix_name', $mix_name);
        
        // 2. Recepta mieszanki (jako JSON)
        update_post_meta($product_id, 'product_ingredients', wp_json_encode($recipe_details['ingredients']));
        
        // 3. Autor mieszanki
        $author_data = $this->get_author_info($author_id);
        update_post_meta($product_id, 'mix_creator_name', $author_data['name']);
        
        // 4. ID autora
        update_post_meta($product_id, 'mix_creator_id', $author_id);
        
        // 5. Cena mieszanki
        update_post_meta($product_id, '_price', $recipe_details['total_price']);
        update_post_meta($product_id, '_regular_price', $recipe_details['total_price']);
        
        // 6. Cena w punktach (użyj prawidłowej nazwy kolumny)
        update_post_meta($product_id, 'price_point', $recipe_details['total_points']);
        
        // 7. Zdobyte punkty za zakup (użyj prawidłowej nazwy kolumny)
        update_post_meta($product_id, 'point_earned', $recipe_details['total_points'] * 0.1); // 10% punktów za zakup
        
        // 8. Zdjęcie mieszanki
        if (!empty($mix_image)) {
            $this->set_product_image_for_publish($product_id, $mix_image);
        }
        
        // 9. Opis mieszanki
        update_post_meta($product_id, 'mix_description', $mix_description);
        
        // 10. Recepta mieszanki (pełne dane)
        update_post_meta($product_id, 'herbal_mix_data', wp_json_encode($mix_data));
        
        // 11. Przypisanie kategorii "Custom Mix"
        $this->assign_custom_mix_category_for_publish($product_id);
        
        // 12. Opakowanie
        if (!empty($recipe_details['packaging']['id'])) {
            update_post_meta($product_id, 'packaging_id', $recipe_details['packaging']['id']);
            update_post_meta($product_id, 'packaging_name', $recipe_details['packaging']['name']);
        }
        
        // === DODATKOWE METADANE ===
        update_post_meta($product_id, 'herbal_mix_id', intval($_POST['mix_id']));
        update_post_meta($product_id, '_custom_mix_user', $author_id);
        update_post_meta($product_id, '_custom_mix_author', $author_data['name']);
        update_post_meta($product_id, '_custom_mix_created', current_time('mysql'));
        update_post_meta($product_id, 'total_weight', $recipe_details['total_weight']);
        
        // Ustaw stan magazynowy
        update_post_meta($product_id, '_stock_status', 'instock');
        update_post_meta($product_id, '_manage_stock', 'no');
        update_post_meta($product_id, '_virtual', 'no');
        update_post_meta($product_id, '_downloadable', 'no');
        
        // Wyczyść cache WooCommerce
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients($product_id);
        }
        
        return $product_id;
    }
    
    // Istniejąca logika dla innych typów (bez zmian)
    $product_data = array(
        'post_title' => $mix_name,
        'post_content' => $this->generate_product_description($mix_description, $recipe_details, $author_id, $type),
        'post_status' => $type === 'private' ? 'private' : 'publish',
        'post_type' => 'product',
        'post_author' => get_current_user_id()
    );
    
    $product_id = wp_insert_post($product_data);
    
    if (is_wp_error($product_id)) {
        return $product_id;
    }
    
    // Skopiuj meta dane z szablonu
    $this->copy_product_meta_from_template($template_id, $product_id);
    
    // Ustaw ceny bazując na recepturze
    update_post_meta($product_id, '_price', $recipe_details['total_price']);
    update_post_meta($product_id, '_regular_price', $recipe_details['total_price']);
    
    // Ustaw meta dane mieszanki
    update_post_meta($product_id, '_herbal_mix_id', intval($_POST['mix_id']));
    update_post_meta($product_id, '_herbal_mix_data', wp_json_encode($mix_data));
    update_post_meta($product_id, '_herbal_mix_recipe', wp_json_encode($recipe_details));
    update_post_meta($product_id, '_herbal_mix_author_id', $author_id);
    update_post_meta($product_id, '_herbal_mix_type', $type);
    
    // Dla prywatnych produktów - ustaw nabywcę
    if ($type === 'private') {
        update_post_meta($product_id, '_herbal_mix_buyer_id', $author_id);
    }
    
    // Ustaw obrazek produktu jeśli dostępny
    if (!empty($mix_image)) {
        $this->set_product_image_from_url($product_id, $mix_image);
    }
    
    // Ustaw kategorie produktu
    if ($type === 'public') {
        wp_set_object_terms($product_id, 'herbal-mixes', 'product_cat');
    } else {
        wp_set_object_terms($product_id, 'private-herbal-mixes', 'product_cat');
    }
    
    return $product_id;
}

/**
 * NOWE FUNKCJE POMOCNICZE dla publikowania
 */

/**
 * Skopiuj meta dane z szablonu dla publikowania
 */
private function copy_template_meta_for_publish($template_id, $product_id) {
    $template_meta = get_post_meta($template_id);
    
    // Metadane które NIE KOPIUJEMY (bo ustawiamy własne)
    $excluded_meta = array(
        '_edit_last', '_edit_lock', '_wp_old_slug', '_wp_old_date', '_thumbnail_id',
        'mix_name', 'mix_description', 'mix_creator_name', 'mix_creator_id',
        'price_point', 'point_earned', 'product_ingredients', 'herbal_mix_data',
        'packaging_id', 'packaging_name', '_price', '_regular_price', '_sale_price',
        '_herbal_mix_id', '_custom_mix_user', '_custom_mix_author'
    );
    
    foreach ($template_meta as $key => $values) {
        if (!in_array($key, $excluded_meta) && !empty($values[0])) {
            update_post_meta($product_id, $key, $values[0]);
        }
    }
}

/**
 * Generuj opis produktu dla publikowania
 */
private function generate_product_description_for_publish($mix_description, $recipe_details, $author_id) {
    $author_data = $this->get_author_info($author_id);
    
    $description = '<div class="custom-mix-description">';
    
    if (!empty($mix_description)) {
        $description .= '<div class="mix-story">' . wp_kses_post($mix_description) . '</div>';
    }
    
    $description .= '<div class="mix-details">';
    $description .= '<h4>' . __('Mix Details', 'herbal-mix-creator2') . '</h4>';
    $description .= '<ul>';
    $description .= '<li><strong>' . __('Created by:', 'herbal-mix-creator2') . '</strong> ' . esc_html($author_data['name']) . '</li>';
    $description .= '<li><strong>' . __('Total Weight:', 'herbal-mix-creator2') . '</strong> ' . $recipe_details['total_weight'] . 'g</li>';
    $description .= '<li><strong>' . __('Ingredients Count:', 'herbal-mix-creator2') . '</strong> ' . count($recipe_details['ingredients']) . '</li>';
    
    if (!empty($recipe_details['packaging']['name'])) {
        $description .= '<li><strong>' . __('Packaging:', 'herbal-mix-creator2') . '</strong> ' . esc_html($recipe_details['packaging']['name']) . '</li>';
    }
    
    $description .= '</ul>';
    $description .= '</div>';
    
    $description .= '<div class="ingredients-list">';
    $description .= '<h4>' . __('Ingredients', 'herbal-mix-creator2') . '</h4>';
    $description .= '<ul>';
    foreach ($recipe_details['ingredients'] as $ingredient) {
        $description .= '<li>' . esc_html($ingredient['name']) . ' - ' . $ingredient['weight'] . 'g</li>';
    }
    $description .= '</ul>';
    $description .= '</div>';
    
    return $description;
}

/**
 * Pobierz informacje o autorze
 */
private function get_author_info($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return ['name' => __('Unknown Author', 'herbal-mix-creator2'), 'id' => 0];
    }
    
    $nickname = get_user_meta($user_id, 'nickname', true);
    $display_name = !empty($nickname) ? $nickname : $user->display_name;
    
    return [
        'name' => $display_name,
        'id' => $user_id,
        'email' => $user->user_email
    ];
}

/**
 * Ustaw zdjęcie produktu dla publikowania
 */
private function set_product_image_for_publish($product_id, $image_url) {
    // Sprawdź czy zdjęcie już istnieje w bibliotece mediów
    $attachment_id = attachment_url_to_postid($image_url);
    
    if ($attachment_id) {
        set_post_thumbnail($product_id, $attachment_id);
        return $attachment_id;
    }
    
    // Jeśli nie ma, stwórz nowe załączenie
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        return false;
    }
    
    $file_array = [
        'name' => basename($image_url),
        'tmp_name' => $tmp
    ];
    
    $attachment_id = media_handle_sideload($file_array, $product_id);
    
    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        return false;
    }
    
    set_post_thumbnail($product_id, $attachment_id);
    return $attachment_id;
}

/**
 * Przypisz kategorię "Custom Mix" dla publikowania
 */
private function assign_custom_mix_category_for_publish($product_id) {
    // Sprawdź czy kategoria "Custom Mix" istnieje
    $term = get_term_by('slug', 'custom-mix', 'product_cat');
    
    if (!$term) {
        // Stwórz kategorię jeśli nie istnieje
        $term_data = wp_insert_term(
            'Custom Mix',
            'product_cat',
            [
                'slug' => 'custom-mix',
                'description' => __('User-created herbal mixes', 'herbal-mix-creator2')
            ]
        );
        
        if (!is_wp_error($term_data)) {
            $term_id = $term_data['term_id'];
        } else {
            return false;
        }
    } else {
        $term_id = $term->term_id;
    }
    
    // Przypisz kategorię do produktu
    wp_set_post_terms($product_id, [$term_id], 'product_cat');
    return true;
}
    
    /**
     * Kopiuje meta dane z szablonu produktu
     */
    private function copy_product_meta_from_template($template_id, $product_id) {
        $template_meta = get_post_meta($template_id);
        
        $excluded_meta = array(
            '_edit_last', '_edit_lock', '_wp_old_slug', '_wp_old_date',
            '_herbal_mix_id', '_herbal_mix_data', '_herbal_mix_recipe',
            '_price', '_regular_price', '_sale_price'
        );
        
        foreach ($template_meta as $key => $values) {
            if (!in_array($key, $excluded_meta) && !empty($values[0])) {
                update_post_meta($product_id, $key, $values[0]);
            }
        }
    }
    
    /**
     * Generuje opis produktu bazując na recepturze
     */
    private function generate_product_description($mix_description, $recipe_details, $author_id, $type = 'public') {
        $description = '';
        
        if (!empty($mix_description)) {
            $description .= '<p>' . esc_html($mix_description) . '</p>';
        }
        
        $description .= '<h3>Recipe Details</h3>';
        
        // Packaging info
        if (!empty($recipe_details['packaging'])) {
            $packaging = $recipe_details['packaging'];
            $description .= '<p><strong>Packaging:</strong> ' . esc_html($packaging['name']) . ' (' . $packaging['capacity'] . 'g capacity)</p>';
        }
        
        // Ingredients
        if (!empty($recipe_details['ingredients'])) {
            $description .= '<h4>Ingredients:</h4><ul>';
            foreach ($recipe_details['ingredients'] as $ingredient) {
                $description .= '<li>' . esc_html($ingredient['name']) . ' - ' . $ingredient['weight'] . 'g</li>';
            }
            $description .= '</ul>';
        }
        
        // Totals
        $description .= '<p><strong>Total Weight:</strong> ' . $recipe_details['total_weight'] . 'g</p>';
        $description .= '<p><strong>Total Price:</strong> £' . number_format($recipe_details['total_price'], 2) . '</p>';
        
        // Author info for public products
        if ($type === 'public' && $author_id) {
            $author = get_userdata($author_id);
            if ($author) {
                $description .= '<p><strong>Created by:</strong> ' . esc_html($author->display_name) . '</p>';
            }
        }
        
        return $description;
    }
    
    /**
     * Ustawia obrazek produktu z URL
     */
    private function set_product_image_from_url($product_id, $image_url) {
        $attachment_id = attachment_url_to_postid($image_url);
        if ($attachment_id) {
            set_post_thumbnail($product_id, $attachment_id);
        }
    }
    
    /**
     * AJAX: Delete mix
     */
    public function ajax_delete_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Get mix to check if there's a related product
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE id = %d AND user_id = %d
        ", $mix_id, $user_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        // Delete related product if exists
        if (!empty($mix->product_id)) {
            wp_delete_post($mix->product_id, true);
        }
        
        // Delete mix from database
        $result = $wpdb->delete(
            $table,
            array('id' => $mix_id, 'user_id' => $user_id),
            array('%d', '%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to delete mix.');
        }
        
        if ($result === 0) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        wp_send_json_success('Mix deleted successfully!');
    }
    
    /**
     * AJAX: View mix (for published mixes)
     */
    public function ajax_view_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        $mix_id = intval($_POST['mix_id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Get published mix with author info
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT m.*, u.display_name as author_name 
            FROM $table m
            JOIN {$wpdb->users} u ON m.user_id = u.ID
            WHERE m.id = %d AND m.status = 'published'
        ", $mix_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or not published.');
        }
        
        // Parse mix data and get recipe details
        $mix_data = json_decode($mix->mix_data, true);
        $recipe_details = null;
        
        if ($mix_data) {
            $recipe_details = $this->build_recipe_details_fixed($mix_data);
        }
        
        wp_send_json_success(array(
            'mix' => array(
                'id' => $mix->id,
                'name' => $mix->mix_name,
                'description' => $mix->mix_description,
                'image' => $mix->mix_image,
                'author' => $mix->author_name,
                'created_at' => $mix->created_at
            ),
            'recipe' => $recipe_details
        ));
    }
    
    /**
     * AJAX: Remove from favorites
     */
    public function ajax_remove_favorite_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
            wp_send_json_error('Security check failed.');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in.');
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Remove from favorites (delete if it's a favorite, or change status if it's owned)
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table 
            WHERE id = %d AND user_id = %d
        ", $mix_id, $user_id));
        
        if (!$mix) {
            wp_send_json_error('Mix not found or access denied.');
        }
        
        if ($mix->status === 'favorite') {
            // Delete favorite mix
            $result = $wpdb->delete(
                $table,
                array('id' => $mix_id, 'user_id' => $user_id),
                array('%d', '%d')
            );
        } else {
            wp_send_json_error('This mix is not in your favorites.');
        }
        
        if ($result === false) {
            wp_send_json_error('Failed to remove from favorites.');
        }
        
        wp_send_json_success('Removed from favorites successfully!');
    }
    
    /**
     * Render My Mixes tab content
     */
    public function render_my_mixes_tab() {
        $template_path = HERBAL_MIX_PLUGIN_PATH . 'includes/templates/user-profile-my-mixes.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="herbal-error">';
            echo '<p>' . esc_html__('Template not found.', 'herbal-mix-creator2') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render Favorite Mixes tab content
     */
    public function render_favorite_mixes_tab() {
        $user_id = get_current_user_id();
        
        if (class_exists('Herbal_Mix_Database')) {
            $mixes = Herbal_Mix_Database::get_user_mixes($user_id, 'favorite');
        } else {
            $mixes = array();
        }
        
        echo '<div class="herbal-favorite-mixes">';
        echo '<h3>' . esc_html__('My Favorite Mixes', 'herbal-mix-creator2') . '</h3>';
        
        if (empty($mixes)) {
            echo '<p>' . esc_html__('You have no favorite mixes yet.', 'herbal-mix-creator2') . '</p>';
        } else {
            echo '<div class="mixes-grid">';
            foreach ($mixes as $mix) {
                echo '<div class="mix-card">';
                echo '<h4>' . esc_html($mix->mix_name) . '</h4>';
                echo '<p>' . esc_html(wp_trim_words($mix->mix_description, 20)) . '</p>';
                echo '<div class="mix-actions">';
                echo '<button type="button" class="button view-mix" data-mix-id="' . esc_attr($mix->id) . '">' . esc_html__('View', 'herbal-mix-creator2') . '</button>';
                echo '<button type="button" class="button buy-mix" data-mix-id="' . esc_attr($mix->id) . '">' . esc_html__('Buy', 'herbal-mix-creator2') . '</button>';
                echo '<button type="button" class="button remove-favorite" data-mix-id="' . esc_attr($mix->id) . '">' . esc_html__('Remove', 'herbal-mix-creator2') . '</button>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Enqueue profile assets
     */
    public function enqueue_profile_assets() {
        if (function_exists('is_account_page') && is_account_page()) {
            wp_enqueue_style(
                'herbal-profile-css',
                HERBAL_MIX_PLUGIN_URL . 'assets/css/profile.css',
                [],
                filemtime(HERBAL_MIX_PLUGIN_PATH . 'assets/css/profile.css')
            );
            
            wp_enqueue_script(
                'herbal-profile-js',
                HERBAL_MIX_PLUGIN_URL . 'assets/js/profile.js',
                ['jquery'],
                filemtime(HERBAL_MIX_PLUGIN_PATH . 'assets/js/profile.js'),
                true
            );
            
            wp_localize_script('herbal-profile-js', 'herbalProfileData', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('herbal_mix_nonce'),
    'uploadImageNonce' => wp_create_nonce('upload_image_nonce'), // DODAJ TĘ LINIĘ
    'currencySymbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '£',
    'strings' => [
        'loading' => __('Loading...', 'herbal-mix-creator2'),
        'error' => __('An error occurred.', 'herbal-mix-creator2'),
        'success' => __('Success!', 'herbal-mix-creator2'),
        'confirmDelete' => __('Are you sure you want to delete this mix?', 'herbal-mix-creator2')
    ]
]);
        }
    }
    
    /**
     * Add avatar field to account form
     */
    public function add_avatar_to_account_form() {
        $user_id = get_current_user_id();
        $custom_avatar = get_user_meta($user_id, 'herbal_custom_avatar', true);
        ?>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="herbal_custom_avatar"><?php esc_html_e('Custom Avatar URL', 'herbal-mix-creator2'); ?></label>
            <input type="url" class="woocommerce-Input woocommerce-Input--text input-text" name="herbal_custom_avatar" id="herbal_custom_avatar" value="<?php echo esc_attr($custom_avatar); ?>" />
        </p>
        <?php
    }
    
    /**
     * Save extra account details
     */
    public function save_extra_account_details($user_id) {
        if (isset($_POST['herbal_custom_avatar'])) {
            update_user_meta($user_id, 'herbal_custom_avatar', sanitize_url($_POST['herbal_custom_avatar']));
        }
    }
    
    /**
     * Custom avatar URL
     */
    public function custom_avatar_url($url, $id_or_email, $args) {
        $user_id = 0;
        if (is_numeric($id_or_email)) {
            $user_id = $id_or_email;
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        
        if ($user_id) {
            $custom_avatar = get_user_meta($user_id, 'herbal_custom_avatar', true);
            if ($custom_avatar) {
                return $custom_avatar;
            }
        }
        
        return $url;
    }
}