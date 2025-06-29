<?php
/**
 * Herbal Points Manager - CENTRALNY SYSTEM PUNKTÓW
 * 
 * Ten plik zastępuje wszystkie zduplikowane funkcje punktów
 * Używa wyłącznie logiki opartej na składnikach (ingredient-based)
 * Usuwa dependency na WooCommerce conversion rate
 * Zachowuje wszystkie funkcje backward compatibility
 * 
 * File: includes/class-herbal-points-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Herbal_Points_Manager {

    /**
     * Inicjalizacja - hooków i filtrów
     */
    public static function init() {
        // Hook do naliczania punktów po zakupie
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'award_points_for_completed_order'));
        add_action('woocommerce_order_status_processing', array(__CLASS__, 'award_points_for_completed_order'));
        
        // Hook do wyświetlania punktów w profilu użytkownika
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_points_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_get_user_points_balance', array(__CLASS__, 'ajax_get_user_points_balance'));
        add_action('wp_ajax_nopriv_get_user_points_balance', array(__CLASS__, 'ajax_get_user_points_balance'));
    }

    // ===== PODSTAWOWE OPERACJE PUNKTÓW =====

    /**
     * Pobierz saldo punktów użytkownika
     */
    public static function get_user_points($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return 0;
        }
        
        $points = get_user_meta($user_id, 'reward_points', true);
        return floatval($points);
    }

    /**
     * Dodaj punkty użytkownikowi
     */
    public static function add_points($user_id, $points, $transaction_type = 'manual', $reference_id = null, $notes = '') {
        if (!$user_id || $points <= 0) {
            return false;
        }
        
        $current_points = self::get_user_points($user_id);
        $new_points = $current_points + $points;
        
        // Aktualizuj user meta
        $success = update_user_meta($user_id, 'reward_points', $new_points);
        
        if ($success) {
            // Zapisz w historii
            self::record_transaction($user_id, $points, $transaction_type, $reference_id, $current_points, $new_points, $notes);
            
            // Trigger akcji dla innych pluginów
            do_action('herbal_points_added', $user_id, $points, $new_points, $transaction_type);
            
            return $new_points;
        }
        
        return false;
    }

    /**
     * Odejmij punkty od użytkownika
     */
    public static function subtract_points($user_id, $points, $transaction_type = 'purchase', $reference_id = null, $notes = '') {
        if (!$user_id || $points <= 0) {
            return false;
        }
        
        $current_points = self::get_user_points($user_id);
        
        // Sprawdź czy ma wystarczająco punktów
        if ($current_points < $points) {
            return new WP_Error('insufficient_points', __('User does not have enough points', 'herbal-mix-creator2'));
        }
        
        $new_points = $current_points - $points;
        
        // Aktualizuj user meta
        $success = update_user_meta($user_id, 'reward_points', $new_points);
        
        if ($success) {
            // Zapisz w historii (punkty ujemne)
            self::record_transaction($user_id, -$points, $transaction_type, $reference_id, $current_points, $new_points, $notes);
            
            // Trigger akcji dla innych pluginów
            do_action('herbal_points_subtracted', $user_id, $points, $new_points, $transaction_type);
            
            return $new_points;
        }
        
        return false;
    }

    /**
     * Sprawdź czy użytkownik ma wystarczająco punktów
     */
    public static function user_has_enough_points($user_id, $required_points) {
        $user_points = self::get_user_points($user_id);
        return $user_points >= $required_points;
    }

    // ===== OPERACJE NA PRODUKTACH =====

    /**
     * Pobierz punkty wymagane dla produktu (ingredient-based calculation)
     */
    public static function get_product_points_cost($product_id) {
        // Najpierw sprawdź czy są ustawione w meta
        $points_cost = get_post_meta($product_id, 'price_point', true);
        
        if ($points_cost && $points_cost > 0) {
            return floatval($points_cost);
        }
        
        // Jeśli nie ma w meta, oblicz z składników
        return self::calculate_product_points_from_ingredients($product_id);
    }

    /**
     * Pobierz punkty zarabiane za zakup produktu
     */
    public static function get_product_points_earned($product_id) {
        // Najpierw sprawdź czy są ustawione w meta
        $points_earned = get_post_meta($product_id, 'point_earned', true);
        
        if ($points_earned && $points_earned > 0) {
            return floatval($points_earned);
        }
        
        // Jeśli nie ma w meta, oblicz z składników
        return self::calculate_product_earned_from_ingredients($product_id);
    }

    /**
     * Oblicz punkty produktu na podstawie składników (Twoja obecna logika)
     */
    public static function calculate_product_points_from_ingredients($product_id) {
        $ingredients_json = get_post_meta($product_id, 'product_ingredients', true);
        
        if (!$ingredients_json) {
            return 0;
        }
        
        $ingredients = json_decode($ingredients_json, true);
        if (!is_array($ingredients)) {
            return 0;
        }
        
        $total_cost_points = 0;
        global $wpdb;
        
        foreach ($ingredients as $ingredient) {
            if (isset($ingredient['id']) && isset($ingredient['weight'])) {
                $weight = floatval($ingredient['weight']);
                
                // Pobierz dane składnika z bazy
                $ingredient_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT price_point FROM {$wpdb->prefix}herbal_ingredients WHERE id = %d",
                    $ingredient['id']
                ));
                
                if ($ingredient_data && $ingredient_data->price_point > 0) {
                    $total_cost_points += (floatval($ingredient_data->price_point) * $weight);
                }
            }
        }
        
        // Dodaj punkty za packaging jeśli jest
        $packaging_id = get_post_meta($product_id, 'packaging_id', true);
        if ($packaging_id) {
            $packaging_data = $wpdb->get_row($wpdb->prepare(
                "SELECT price_point FROM {$wpdb->prefix}herbal_packaging WHERE id = %d",
                $packaging_id
            ));
            
            if ($packaging_data && $packaging_data->price_point > 0) {
                $total_cost_points += floatval($packaging_data->price_point);
            }
        }
        
        return round($total_cost_points);
    }

    /**
     * Oblicz punkty zarabiane na podstawie składników
     */
    public static function calculate_product_earned_from_ingredients($product_id) {
        $ingredients_json = get_post_meta($product_id, 'product_ingredients', true);
        
        if (!$ingredients_json) {
            return 0;
        }
        
        $ingredients = json_decode($ingredients_json, true);
        if (!is_array($ingredients)) {
            return 0;
        }
        
        $total_earned_points = 0;
        global $wpdb;
        
        foreach ($ingredients as $ingredient) {
            if (isset($ingredient['id']) && isset($ingredient['weight'])) {
                $weight = floatval($ingredient['weight']);
                
                // Pobierz dane składnika z bazy
                $ingredient_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT point_earned FROM {$wpdb->prefix}herbal_ingredients WHERE id = %d",
                    $ingredient['id']
                ));
                
                if ($ingredient_data && $ingredient_data->point_earned > 0) {
                    $total_earned_points += (floatval($ingredient_data->point_earned) * $weight);
                }
            }
        }
        
        // Dodaj punkty za packaging jeśli jest
        $packaging_id = get_post_meta($product_id, 'packaging_id', true);
        if ($packaging_id) {
            $packaging_data = $wpdb->get_row($wpdb->prepare(
                "SELECT point_earned FROM {$wpdb->prefix}herbal_packaging WHERE id = %d",
                $packaging_id
            ));
            
            if ($packaging_data && $packaging_data->point_earned > 0) {
                $total_earned_points += floatval($packaging_data->point_earned);
            }
        }
        
        return round($total_earned_points);
    }

    // ===== HISTORIA TRANSAKCJI =====

    /**
     * Zapisz transakcję w historii
     */
    public static function record_transaction($user_id, $points_change, $transaction_type, $reference_id = null, $points_before = 0, $points_after = 0, $notes = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'herbal_points_history';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        if ($table_exists !== $table_name) {
            return false;
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'points_change' => $points_change,
                'transaction_type' => $transaction_type,
                'reference_id' => $reference_id,
                'reference_type' => $reference_id ? 'order' : null,
                'points_before' => $points_before,
                'points_after' => $points_after,
                'notes' => $notes,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%f', '%s', '%d', '%s', '%f', '%f', '%s', '%s')
        );
        
        return $result !== false;
    }

    /**
     * Pobierz historię punktów użytkownika
     */
    public static function get_user_points_history($user_id, $limit = 20, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'herbal_points_history';
        
        return $wpdb->get_results(
            $wpdb->prepare("
                SELECT * FROM {$table_name}
                WHERE user_id = %d
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d
            ", $user_id, $limit, $offset)
        );
    }

    // ===== OBSŁUGA ZAMÓWIEŃ =====

    /**
     * Nalicz punkty za ukończone zamówienie (hook na woocommerce_order_status_completed)
     */
    public static function award_points_for_completed_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        // Sprawdź czy punkty już zostały naliczone
        if (get_post_meta($order_id, '_herbal_points_awarded', true)) {
            return false;
        }
        
        $total_points = 0;
        
        // Oblicz punkty dla każdego produktu
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $points_earned = self::get_product_points_earned($product->get_id());
            if ($points_earned > 0) {
                $quantity = $item->get_quantity();
                $total_points += $points_earned * $quantity;
            }
        }
        
        // Przyznaj punkty jeśli są jakieś do przyznania
        if ($total_points > 0) {
            $success = self::add_points(
                $user_id, 
                $total_points, 
                'order_completion', 
                $order_id,
                sprintf(__('Points earned for order #%s', 'herbal-mix-creator2'), $order->get_order_number())
            );
            
            if ($success) {
                // Oznacz jako przyznane
                update_post_meta($order_id, '_herbal_points_awarded', true);
                
                // Dodaj notatkę do zamówienia
                $order->add_order_note(sprintf(
                    __('Customer earned %s reward points for this order.', 'herbal-mix-creator2'),
                    number_format($total_points, 0)
                ));
                
                return $total_points;
            }
        }
        
        return false;
    }

    /**
     * Przetwórz płatność punktami za zamówienie
     */
    public static function process_points_payment($order_id, $points_required) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        // Sprawdź czy użytkownik ma wystarczająco punktów
        if (!self::user_has_enough_points($user_id, $points_required)) {
            return new WP_Error('insufficient_points', __('Insufficient points balance', 'herbal-mix-creator2'));
        }
        
        // Odejmij punkty
        $result = self::subtract_points(
            $user_id, 
            $points_required, 
            'points_payment', 
            $order_id,
            sprintf(__('Payment for order #%s', 'herbal-mix-creator2'), $order->get_order_number())
        );
        
        if (!is_wp_error($result)) {
            // Oznacz zamówienie jako opłacone punktami
            update_post_meta($order_id, '_paid_with_points', $points_required);
            
            return true;
        }
        
        return $result;
    }

    // ===== STATYSTYKI =====

    /**
     * Pobierz statystyki punktów dla admina
     */
    public static function get_points_statistics() {
        global $wpdb;
        
        // Łączne punkty w systemie
        $total_points = $wpdb->get_var("
            SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'reward_points'
        ") ?: 0;
        
        // Liczba użytkowników z punktami
        $total_users = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'reward_points' 
            AND CAST(meta_value AS DECIMAL(10,2)) > 0
        ") ?: 0;
        
        // Średnia punktów na użytkownika
        $avg_points = $total_users > 0 ? ($total_points / $total_users) : 0;
        
        // Transakcje dzisiaj
        $today = date('Y-m-d');
        $transactions_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}herbal_points_history 
            WHERE DATE(created_at) = %s
        ", $today)) ?: 0;
        
        return array(
            'total_points' => $total_points,
            'total_users' => $total_users,
            'avg_points' => $avg_points,
            'transactions_today' => $transactions_today
        );
    }

    // ===== AJAX HANDLERS =====

    /**
     * AJAX: Pobierz saldo punktów użytkownika
     */
    public static function ajax_get_user_points_balance() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        $user_id = get_current_user_id();
        $points = self::get_user_points($user_id);
        
        wp_send_json_success(array(
            'points' => $points,
            'formatted' => number_format($points, 0)
        ));
    }

    // ===== UTILITIES =====

    /**
     * Enqueue scripts dla systemu punktów
     */
    public static function enqueue_points_scripts() {
        if (is_account_page() || is_checkout() || is_cart()) {
            wp_enqueue_script('jquery');
            
            // Dodaj inline script dla aktualizacji punktów
            wp_add_inline_script('jquery', '
                jQuery(document).ready(function($) {
                    // Funkcja do odświeżania salda punktów
                    function updatePointsBalance() {
                        $.post(ajaxurl || "' . admin_url('admin-ajax.php') . '", {
                            action: "get_user_points_balance"
                        }, function(response) {
                            if (response.success) {
                                $(".user-points-balance").text(response.data.formatted + " pts");
                            }
                        });
                    }
                    
                    // Odśwież na załadowaniu strony
                    updatePointsBalance();
                    
                    // Odśwież co 30 sekund
                    setInterval(updatePointsBalance, 30000);
                });
            ');
        }
    }

    /**
     * Formatuj punkty do wyświetlania
     */
    public static function format_points($points, $show_suffix = true) {
        $formatted = number_format($points, 0);
        
        if ($show_suffix) {
            $formatted .= ' ' . _n('point', 'points', $points, 'herbal-mix-creator2');
        }
        
        return $formatted;
    }
}

// ===== BACKWARD COMPATIBILITY FUNCTIONS =====
// Te funkcje zapewniają kompatybilność wsteczną z kodem który już może używać starych nazw

/**
 * Get user points (helper function)
 * UPDATED: Now uses new Herbal_Points_Manager
 */
if (!function_exists('herbal_get_user_points')) {
    function herbal_get_user_points($user_id = null) {
        return Herbal_Points_Manager::get_user_points($user_id);
    }
}

/**
 * Add points to user (helper function)
 * UPDATED: Now uses new Herbal_Points_Manager
 */
if (!function_exists('herbal_add_user_points')) {
    function herbal_add_user_points($user_id, $points, $transaction_type = 'manual', $reference_id = null) {
        return Herbal_Points_Manager::add_points($user_id, $points, $transaction_type, $reference_id);
    }
}

/**
 * Subtract points from user (helper function)
 * UPDATED: Now uses new Herbal_Points_Manager
 */
if (!function_exists('herbal_subtract_user_points')) {
    function herbal_subtract_user_points($user_id, $points, $transaction_type = 'manual', $reference_id = null) {
        return Herbal_Points_Manager::subtract_points($user_id, $points, $transaction_type, $reference_id);
    }
}

/**
 * Check if user has enough points
 */
if (!function_exists('herbal_user_has_enough_points')) {
    function herbal_user_has_enough_points($user_id, $required_points) {
        return Herbal_Points_Manager::user_has_enough_points($user_id, $required_points);
    }
}

/**
 * Get user points history
 * UPDATED: Now uses new Herbal_Points_Manager
 */
if (!function_exists('herbal_get_user_points_history')) {
    function herbal_get_user_points_history($user_id, $limit = 20, $offset = 0) {
        return Herbal_Points_Manager::get_user_points_history($user_id, $limit, $offset);
    }
}

/**
 * Award points for completed orders
 * UPDATED: Now uses new Herbal_Points_Manager
 */
if (!function_exists('herbal_award_points_for_order')) {
    function herbal_award_points_for_order($order_id) {
        return Herbal_Points_Manager::award_points_for_completed_order($order_id);
    }
}

/**
 * Process points payment for an order
 * UPDATED: Now uses new Herbal_Points_Manager
 */
if (!function_exists('herbal_process_points_payment')) {
    function herbal_process_points_payment($order_id, $points_required) {
        return Herbal_Points_Manager::process_points_payment($order_id, $points_required);
    }
}

/**
 * Get points statistics for admin
 * UPDATED: Now uses new Herbal_Points_Manager
 */
if (!function_exists('herbal_get_points_statistics')) {
    function herbal_get_points_statistics() {
        return Herbal_Points_Manager::get_points_statistics();
    }
}

/**
 * Format points for display
 */
if (!function_exists('herbal_format_points')) {
    function herbal_format_points($points, $show_suffix = true) {
        return Herbal_Points_Manager::format_points($points, $show_suffix);
    }
}

/**
 * Check if points system is properly configured
 */
if (!function_exists('herbal_is_points_system_ready')) {
    function herbal_is_points_system_ready() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        // Check if database class exists
        if (!class_exists('Herbal_Mix_Database')) {
            return false;
        }
        
        // Check if points history table exists
        global $wpdb;
        $table_name = $wpdb->prefix . 'herbal_points_history';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        
        return $table_exists === $table_name;
    }
}

/**
 * Shortcode to display user points
 */
if (!function_exists('herbal_user_points_shortcode')) {
    function herbal_user_points_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'format' => 'number', // 'number', 'text', 'widget'
            'show_label' => true
        ), $atts);
        
        if (!$atts['user_id']) {
            return __('You must be logged in.', 'herbal-mix-creator2');
        }
        
        $points = herbal_get_user_points($atts['user_id']);
        
        switch ($atts['format']) {
            case 'text':
                return sprintf(__('You have %s reward points', 'herbal-mix-creator2'), number_format($points, 0));
            
            case 'widget':
                return '<div class="herbal-points-widget"><span class="points-number">' . number_format($points, 0) . '</span><span class="points-label">' . __('points', 'herbal-mix-creator2') . '</span></div>';
            
            case 'number':
            default:
                return number_format($points, 0);
        }
    }
}
add_shortcode('herbal_user_points', 'herbal_user_points_shortcode');