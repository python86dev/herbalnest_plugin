<?php
/**
 * 🔧 HERBAL TEMPLATE HELPER FUNCTIONS
 * File: includes/herbal-template-helpers.php
 * 
 * Helper functions for cart and checkout templates
 * These functions provide data for enhanced points display
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get comprehensive cart points summary
 * Used by cart and checkout templates
 */
if (!function_exists('herbal_get_cart_points_summary')) {
    function herbal_get_cart_points_summary() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return array(
                'user_points' => 0,
                'total_cost' => 0,
                'total_earned' => 0,
                'products_with_points' => 0,
                'total_products' => 0,
                'can_pay' => false,
                'shortage' => 0
            );
        }
        
        $user_points = floatval(get_user_meta($user_id, 'reward_points', true)) ?: 0;
        
        $total_cost = 0;
        $total_earned = 0;
        $products_with_points = 0;
        $total_products = 0;
        
        if (WC()->cart && !WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                $quantity = $cart_item['quantity'];
                $total_products++;
                
                if ($product) {
                    $price_point = floatval(get_post_meta($product->get_id(), 'price_point', true));
                    $point_earned = floatval(get_post_meta($product->get_id(), 'point_earned', true));
                    
                    if ($price_point > 0 || $point_earned > 0) {
                        $products_with_points++;
                        $total_cost += $price_point * $quantity;
                        $total_earned += $point_earned * $quantity;
                    }
                }
            }
        }
        
        return array(
            'user_points' => $user_points,
            'total_cost' => $total_cost,
            'total_earned' => $total_earned,
            'products_with_points' => $products_with_points,
            'total_products' => $total_products,
            'can_pay' => $user_points >= $total_cost && $total_cost > 0,
            'shortage' => max(0, $total_cost - $user_points)
        );
    }
}

/**
 * Get individual product points display for cart/checkout
 * Shows points cost and earned for specific product
 */
if (!function_exists('herbal_get_product_points_display')) {
    function herbal_get_product_points_display($product, $quantity = 1) {
        if (!$product) return '';
        
        $price_point = floatval(get_post_meta($product->get_id(), 'price_point', true));
        $point_earned = floatval(get_post_meta($product->get_id(), 'point_earned', true));
        
        if ($price_point > 0 || $point_earned > 0) {
            $html = '<div class="product-points-info">';
            
            if ($price_point > 0) {
                $total_cost = $price_point * $quantity;
                $html .= '<span style="color: #dc3545; font-weight: 600; margin-right: 10px;">' . 
                         number_format($total_cost, 0) . ' pts</span>';
            }
            
            if ($point_earned > 0) {
                $total_earned = $point_earned * $quantity;
                $html .= '<span style="color: #28a745; font-weight: 600;">+' . 
                         number_format($total_earned, 0) . ' pts</span>';
            }
            
            $html .= '</div>';
            
            return $html;
        }
        
        return '';
    }
}

/**
 * Format points for display with proper plural handling
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
 * Check if current cart has any products with points
 * Used to determine if enhanced templates should be loaded
 */
if (!function_exists('herbal_cart_has_points_products')) {
    function herbal_cart_has_points_products() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return false;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($product) {
                $price_point = floatval(get_post_meta($product->get_id(), 'price_point', true));
                $point_earned = floatval(get_post_meta($product->get_id(), 'point_earned', true));
                
                if ($price_point > 0 || $point_earned > 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
}

/**
 * Get user's current points balance
 */
if (!function_exists('herbal_get_user_points')) {
    function herbal_get_user_points($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return 0;
        }
        
        return floatval(get_user_meta($user_id, 'reward_points', true)) ?: 0;
    }
}

/**
 * Check if points payment gateway is available and enabled
 */
if (!function_exists('herbal_is_points_payment_available')) {
    function herbal_is_points_payment_available() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check if gateway exists and is enabled
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        
        return isset($available_gateways['points_payment']) && 
               $available_gateways['points_payment']->enabled === 'yes';
    }
}

/**
 * Render points summary section for templates
 * Reusable component for cart and checkout
 */
if (!function_exists('herbal_render_points_summary')) {
    function herbal_render_points_summary($context = 'cart', $summary = null) {
        if (!$summary) {
            $summary = herbal_get_cart_points_summary();
        }
        
        if ($summary['total_cost'] <= 0) {
            return '';
        }
        
        $icon = $context === 'checkout' ? '💳' : '🛒';
        $title = $context === 'checkout' ? 
                 __('Points Payment Available', 'herbal-mix-creator2') : 
                 __('Points Summary', 'herbal-mix-creator2');
        
        $css_class = 'herbal-points-summary';
        if ($context === 'checkout') {
            $css_class .= ' checkout-style';
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($css_class); ?>">
            <div class="points-header">
                <h4><?php echo $icon . ' ' . esc_html($title); ?></h4>
            </div>
            
            <div class="points-breakdown">
                <div class="points-row">
                    <span class="label"><?php esc_html_e('Total Points Required:', 'herbal-mix-creator2'); ?></span>
                    <span class="value cost"><?php echo number_format($summary['total_cost'], 0); ?> pts</span>
                </div>
                
                <div class="points-row">
                    <span class="label"><?php esc_html_e('Your Points Balance:', 'herbal-mix-creator2'); ?></span>
                    <span class="value balance"><?php echo number_format($summary['user_points'], 0); ?> pts</span>
                </div>
                
                <?php if ($summary['total_earned'] > 0): ?>
                <div class="points-row">
                    <span class="label"><?php esc_html_e('Points You\'ll Earn:', 'herbal-mix-creator2'); ?></span>
                    <span class="value earned">+<?php echo number_format($summary['total_earned'], 0); ?> pts</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="points-status">
                <?php if ($summary['can_pay']): ?>
                    <?php $remaining = $summary['user_points'] - $summary['total_cost']; ?>
                    <div class="status-success">
                        <span class="icon">✅</span>
                        <span class="text">
                            <?php 
                            echo $context === 'checkout' ? 
                                 esc_html__('Points Payment Available Below!', 'herbal-mix-creator2') :
                                 esc_html__('You can pay with points!', 'herbal-mix-creator2');
                            ?>
                        </span>
                        <div class="details">
                            <?php 
                            echo sprintf(
                                $context === 'checkout' ? 
                                    esc_html__('You\'ll have %s points remaining', 'herbal-mix-creator2') :
                                    esc_html__('Remaining: %s pts', 'herbal-mix-creator2'),
                                number_format($remaining, 0)
                            );
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="status-insufficient">
                        <span class="icon">❌</span>
                        <span class="text">
                            <?php 
                            echo $context === 'checkout' ? 
                                 esc_html__('Insufficient Points for Full Payment', 'herbal-mix-creator2') :
                                 esc_html__('Insufficient points', 'herbal-mix-creator2');
                            ?>
                        </span>
                        <div class="details">
                            <?php echo sprintf(esc_html__('Need %s more points', 'herbal-mix-creator2'), number_format($summary['shortage'], 0)); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * Debug function to display points data
 * For troubleshooting templates
 */
if (!function_exists('herbal_debug_points_data')) {
    function herbal_debug_points_data() {
        if (!current_user_can('manage_options') || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $summary = herbal_get_cart_points_summary();
        
        echo '<!-- Herbal Points Debug Data -->';
        echo '<script>console.log("Herbal Points Summary:", ' . wp_json_encode($summary) . ');</script>';
        
        if (!empty($_GET['herbal_points_debug'])) {
            echo '<div style="position: fixed; top: 10px; right: 10px; background: white; border: 2px solid #000; padding: 15px; z-index: 9999; max-width: 300px;">';
            echo '<h4>🔍 Points Debug</h4>';
            echo '<pre>' . print_r($summary, true) . '</pre>';
            echo '</div>';
        }
    }
}

// Auto-load debug info on cart/checkout pages
if ((is_cart() || is_checkout()) && defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', 'herbal_debug_points_data');
}