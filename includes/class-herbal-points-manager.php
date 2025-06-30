<?php
/**
 * Herbal Mix Points Manager - CLEANED VERSION
 * File: includes/class-herbal-points-manager.php
 * 
 * MAJOR CHANGES:
 * - REMOVED: herbal_currency_to_points() function (causing conflicts)
 * - REMOVED: herbal_points_to_currency() function (causing conflicts)
 * - PRESERVED: All other points system functionality
 * - PRESERVED: Direct points usage and transaction history
 */

if (!defined('ABSPATH')) {
    exit;
}

class HerbalPointsManager {
    
    /**
     * Add points to user account with full transaction history
     */
    public static function add_points($user_id, $points, $transaction_type, $reference_id = null, $notes = null) {
        if (!$user_id || $points <= 0) {
            return false;
        }
        
        $current_points = floatval(get_user_meta($user_id, 'reward_points', true)) ?: 0;
        $new_points = $current_points + $points;
        
        $success = update_user_meta($user_id, 'reward_points', $new_points);
        
        if ($success && class_exists('Herbal_Mix_Database')) {
            Herbal_Mix_Database::record_points_transaction(
                $user_id, $points, $transaction_type, $reference_id, 
                $current_points, $new_points, $notes
            );
        }
        
        return $success;
    }
    
    /**
     * Subtract points from user account with validation
     */
    public static function subtract_points($user_id, $points, $transaction_type, $reference_id = null, $notes = null) {
        if (!$user_id || $points <= 0) {
            return false;
        }
        
        $current_points = floatval(get_user_meta($user_id, 'reward_points', true)) ?: 0;
        
        if ($current_points < $points) {
            return false; // Insufficient points
        }
        
        $new_points = $current_points - $points;
        
        $success = update_user_meta($user_id, 'reward_points', $new_points);
        
        if ($success && class_exists('Herbal_Mix_Database')) {
            Herbal_Mix_Database::record_points_transaction(
                $user_id, -$points, $transaction_type, $reference_id,
                $current_points, $new_points, $notes
            );
        }
        
        return $success;
    }
    
    /**
     * Get user's current points balance
     */
    public static function get_user_points($user_id) {
        if (!$user_id) {
            return 0;
        }
        
        return floatval(get_user_meta($user_id, 'reward_points', true)) ?: 0;
    }
    
    /**
     * Award points for WooCommerce order completion
     */
    public static function award_points_for_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return false;
        }
        
        $total_points = 0;
        
        // Calculate points based on point_earned metadata (NOT currency conversion)
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $point_earned = floatval(get_post_meta($product->get_id(), 'point_earned', true));
                if ($point_earned > 0) {
                    $quantity = $item->get_quantity();
                    $total_points += $point_earned * $quantity;
                }
            }
        }
        
        if ($total_points > 0) {
            return self::add_points(
                $user_id, 
                $total_points, 
                'purchase', 
                $order_id, 
                sprintf(__('Points earned from order #%s', 'herbal-mix-creator2'), $order->get_order_number())
            );
        }
        
        return false;
    }
    
    /**
     * Get points statistics for admin dashboard
     */
    public static function get_points_statistics() {
        global $wpdb;
        
        // Total points in system
        $total_points = $wpdb->get_var("
            SELECT SUM(meta_value) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'reward_points'
        ") ?: 0;
        
        // Total users with points
        $total_users = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'reward_points' AND meta_value > 0
        ") ?: 0;
        
        // Average points per user
        $avg_points = $total_users > 0 ? ($total_points / $total_users) : 0;
        
        // Transactions today
        $today = date('Y-m-d');
        $transactions_today = 0;
        
        if (class_exists('Herbal_Mix_Database')) {
            $transactions_today = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}herbal_points_history 
                WHERE DATE(created_at) = %s
            ", $today)) ?: 0;
        }
        
        return array(
            'total_points' => $total_points,
            'total_users' => $total_users,
            'avg_points' => $avg_points,
            'transactions_today' => $transactions_today
        );
    }
}

/**
 * Format points for display
 */
if (!function_exists('herbal_format_points')) {
    function herbal_format_points($points, $show_suffix = true) {
        $formatted = number_format($points, 0);
        
        if ($show_suffix) {
            $formatted .= ' ' . _n('point', 'points', $points, 'herbal-mix-creator2');
        }
        
        return $formatted;
    }
}

// ===== REMOVED FUNCTIONS (These were causing conflicts) =====
// - herbal_currency_to_points() - REMOVED
// - herbal_points_to_currency() - REMOVED

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
        
        $points = HerbalPointsManager::get_user_points($atts['user_id']);
        
        switch ($atts['format']) {
            case 'text':
                return sprintf(__('You have %s points', 'herbal-mix-creator2'), 
                             herbal_format_points($points, $atts['show_label']));
            
            case 'widget':
                return '<div class="herbal-points-widget">' . 
                       herbal_format_points($points, $atts['show_label']) . 
                       '</div>';
            
            default:
                return herbal_format_points($points, $atts['show_label']);
        }
    }
}
add_shortcode('user_points', 'herbal_user_points_shortcode');

/**
 * Hook into WooCommerce order completion to award points
 */
add_action('woocommerce_order_status_completed', array('HerbalPointsManager', 'award_points_for_order'));

/**
 * AJAX handler for admin points management
 */
add_action('wp_ajax_herbal_admin_add_points', 'herbal_ajax_admin_add_points');

function herbal_ajax_admin_add_points() {
    if (!wp_verify_nonce($_POST['nonce'], 'herbal_admin_points') || !current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    $user_id = intval($_POST['user_id']);
    $points = floatval($_POST['points']);
    $reason = sanitize_textarea_field($_POST['reason']);
    
    if (!$user_id || $points == 0) {
        wp_send_json_error('Invalid parameters');
    }
    
    $success = false;
    $transaction_type = $points > 0 ? 'admin_addition' : 'admin_subtraction';
    
    if ($points > 0) {
        $success = HerbalPointsManager::add_points($user_id, $points, $transaction_type, null, $reason);
    } else {
        $success = HerbalPointsManager::subtract_points($user_id, abs($points), $transaction_type, null, $reason);
    }
    
    if ($success) {
        $new_balance = HerbalPointsManager::get_user_points($user_id);
        wp_send_json_success(array(
            'new_balance' => $new_balance,
            'message' => sprintf(__('Successfully %s %s points. New balance: %s', 'herbal-mix-creator2'),
                               $points > 0 ? __('added', 'herbal-mix-creator2') : __('subtracted', 'herbal-mix-creator2'),
                               abs($points),
                               herbal_format_points($new_balance))
        ));
    } else {
        wp_send_json_error(__('Failed to update points', 'herbal-mix-creator2'));
    }
}