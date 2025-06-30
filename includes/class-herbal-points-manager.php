<?php
/**
 * Updated Helper Functions for Points Management
 * This file can replace the old class-herbal-points-manager.php helper functions section
 * or be included separately to provide backward compatibility
 * 
 * UPDATED: Now uses Herbal_Mix_Database instead of HerbalPointsManager class
 */

if (!defined('ABSPATH')) {
    exit;
}

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
            if (class_exists('Herbal_Mix_Database')) {
                Herbal_Mix_Database::record_points_transaction(
                    $user_id,
                    $points,
                    $transaction_type,
                    $reference_id,
                    $current_points,
                    $new_points
                );
            }
            
            // Trigger action for other plugins/themes
            do_action('herbal_points_added', $user_id, $points, $new_points, $transaction_type);
        }
        
        return $success ? $new_points : false;
    }
}

/**
 * Subtract points from user (helper function)
 * UPDATED: Now uses Herbal_Mix_Database instead of HerbalPointsManager
 */
if (!function_exists('herbal_subtract_user_points')) {
    function herbal_subtract_user_points($user_id, $points, $transaction_type = 'manual', $reference_id = null) {
        if (!$user_id || $points <= 0) {
            return false;
        }
        
        $current_points = herbal_get_user_points($user_id);
        $new_points = max(0, $current_points - $points);
        
        // Update user meta
        $success = update_user_meta($user_id, 'reward_points', $new_points);
        
        if ($success) {
            // Record transaction in history using new database class
            if (class_exists('Herbal_Mix_Database')) {
                Herbal_Mix_Database::record_points_transaction(
                    $user_id,
                    -$points,
                    $transaction_type,
                    $reference_id,
                    $current_points,
                    $new_points
                );
            }
            
            // Trigger action for other plugins/themes
            do_action('herbal_points_subtracted', $user_id, $points, $new_points, $transaction_type);
        }
        
        return $success ? $new_points : false;
    }
}

/**
 * Check if user has enough points
 */
if (!function_exists('herbal_user_has_enough_points')) {
    function herbal_user_has_enough_points($user_id, $required_points) {
        $user_points = herbal_get_user_points($user_id);
        return $user_points >= $required_points;
    }
}

/**
 * Get user points history
 * UPDATED: Now uses Herbal_Mix_Database instead of HerbalPointsManager
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
 * Award points for completed orders
 * UPDATED: Now uses helper functions instead of HerbalPointsManager
 */
if (!function_exists('herbal_award_points_for_order')) {
    function herbal_award_points_for_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        // Check if points already awarded
        if (get_post_meta($order_id, '_herbal_points_awarded', true)) {
            return false;
        }
        
        $total_points = 0;
        
        // Calculate points for each product
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $points_earned = get_post_meta($product->get_id(), '_points_earned', true);
            if ($points_earned) {
                $quantity = $item->get_quantity();
                $total_points += floatval($points_earned) * $quantity;
            }
        }
        
        // Award points if any earned
        if ($total_points > 0) {
            $success = herbal_add_user_points($user_id, $total_points, 'order_completion', $order_id);
            
            if ($success) {
                // Mark as awarded
                update_post_meta($order_id, '_herbal_points_awarded', true);
                
                // Add order note
                $order->add_order_note(sprintf(
                    __('Customer earned %s reward points for this order.', 'herbal-mix-creator2'),
                    number_format($total_points, 0)
                ));
                
                return $total_points;
            }
        }
        
        return false;
    }
}

/**
 * Process points payment for an order
 * UPDATED: Now uses helper functions instead of HerbalPointsManager
 */
if (!function_exists('herbal_process_points_payment')) {
    function herbal_process_points_payment($order_id, $points_required) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        // Check if user has enough points
        if (!herbal_user_has_enough_points($user_id, $points_required)) {
            return false;
        }
        
        // Deduct points
        $success = herbal_subtract_user_points($user_id, $points_required, 'points_payment', $order_id);
        
        if ($success) {
            // Mark order as paid with points
            update_post_meta($order_id, '_paid_with_points', $points_required);
            
            return true;
        }
        
        return false;
    }
}

/**
 * Get points statistics for admin
 * UPDATED: Now uses direct database queries instead of HerbalPointsManager
 */
if (!function_exists('herbal_get_points_statistics')) {
    function herbal_get_points_statistics() {
        global $wpdb;
        
        // Total points in system
        $total_points = $wpdb->get_var("
            SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'reward_points'
        ") ?: 0;
        
        // Total users with points
        $total_users = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'reward_points' 
            AND CAST(meta_value AS DECIMAL(10,2)) > 0
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

/**
 * Convert currency to points using default conversion rate
 */
if (!function_exists('herbal_currency_to_points')) {
    function herbal_currency_to_points($currency_amount, $conversion_rate = 100) {
        return floatval($currency_amount) * floatval($conversion_rate);
    }
}

/**
 * Convert points to currency using default conversion rate
 */
if (!function_exists('herbal_points_to_currency')) {
    function herbal_points_to_currency($points, $conversion_rate = 100) {
        return floatval($points) / floatval($conversion_rate);
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
                return $atts['show_label'] 
                    ? sprintf(__('You have %s points', 'herbal-mix-creator2'), herbal_format_points($points, false))
                    : herbal_format_points($points, false);
                    
            case 'widget':
                ob_start();
                ?>
                <div class="herbal-points-widget">
                    <div class="points-value"><?php echo herbal_format_points($points, false); ?></div>
                    <?php if ($atts['show_label']): ?>
                    <div class="points-label"><?php _e('Points', 'herbal-mix-creator2'); ?></div>
                    <?php endif; ?>
                </div>
                <style>
                .herbal-points-widget {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px;
                    border-radius: 8px;
                    text-align: center;
                    display: inline-block;
                }
                .herbal-points-widget .points-value {
                    font-size: 24px;
                    font-weight: bold;
                    line-height: 1;
                }
                .herbal-points-widget .points-label {
                    font-size: 12px;
                    opacity: 0.9;
                    margin-top: 5px;
                }
                </style>
                <?php
                return ob_get_clean();
                
            default:
                return herbal_format_points($points, false);
        }
    }
}

// Register shortcode
add_shortcode('herbal_user_points', 'herbal_user_points_shortcode');