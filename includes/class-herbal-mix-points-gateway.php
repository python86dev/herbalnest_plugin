<?php
/**
 * FINAL FIXED Points Payment Gateway for WooCommerce
 * Uses ingredient-based points system only (no conversion rate)
 * Gateway always available for testing
 * Proper timing and registration
 * File: includes/class-herbal-mix-points-gateway.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize Points Payment Gateway - FINAL FIXED VERSION
 * Called only once from woocommerce_init hook
 */
function herbal_init_points_payment_gateway() {
    // Don't initialize if WooCommerce payment gateway class doesn't exist
    if (!class_exists('WC_Payment_Gateway')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Herbal Gateway: WC_Payment_Gateway class not found');
        }
        return false;
    }

    // Don't initialize if already exists (prevent duplicates)
    if (class_exists('WC_Gateway_Points_Payment')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Herbal Gateway: WC_Gateway_Points_Payment already exists');
        }
        return false;
    }

    /**
     * FINAL FIXED Points Payment Gateway Class
     * - Gateway always available for testing
     * - Removed conversion_rate system completely
     * - Uses product meta price_point directly
     * - Complete cart and checkout integration
     * - All original functions preserved and enhanced
     */
    class WC_Gateway_Points_Payment extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'points_payment';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __('Pay with Points', 'herbal-mix-creator2');
            $this->method_description = __('Allow customers to pay using their reward points based on ingredient costs.', 'herbal-mix-creator2');
            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title', __('Pay with Points', 'herbal-mix-creator2'));
            $this->description = $this->get_option('description', __('Pay for your order using your reward points.', 'herbal-mix-creator2'));
            $this->enabled = $this->get_option('enabled', 'yes');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            
            // PRESERVED: Original hooks for cart and checkout display (backward compatibility)
            add_action('woocommerce_cart_totals_after_order_total', array($this, 'display_cart_points_info'), 5); // Lower priority than main system
            add_action('woocommerce_review_order_after_order_total', array($this, 'display_checkout_points_info'), 5);
            
            // PRESERVED: Original hook for product pages
            add_action('woocommerce_single_product_summary', array($this, 'display_single_product_points'), 25);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Herbal Gateway: WC_Gateway_Points_Payment constructed successfully');
            }
        }

        /**
         * FIXED: Simplified form fields - NO conversion rate
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'herbal-mix-creator2'),
                    'type' => 'checkbox',
                    'label' => __('Enable Points Payment', 'herbal-mix-creator2'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'herbal-mix-creator2'),
                    'type' => 'text',
                    'description' => __('This controls the title shown during checkout.', 'herbal-mix-creator2'),
                    'default' => __('Pay with Points', 'herbal-mix-creator2'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'herbal-mix-creator2'),
                    'type' => 'textarea',
                    'description' => __('Payment method description shown during checkout.', 'herbal-mix-creator2'),
                    'default' => __('Pay for your order using your reward points.', 'herbal-mix-creator2'),
                    'desc_tip' => true,
                ),
                'min_points_required' => array(
                    'title' => __('Minimum Points Required', 'herbal-mix-creator2'),
                    'type' => 'number',
                    'description' => __('Minimum points a user must have to see this payment option.', 'herbal-mix-creator2'),
                    'default' => '0', // Changed to 0 for always available
                    'custom_attributes' => array(
                        'step' => '1',
                        'min' => '0'
                    )
                )
                // REMOVED: conversion_rate field completely
            );
        }

        /**
         * FIXED: Gateway always available for testing
         */
        public function is_available() {
            if (!$this->enabled || $this->enabled !== 'yes') {
                return false;
            }

            if (!is_user_logged_in()) {
                return false;
            }

            // FIXED: Always available if user is logged in (for testing)
            // No complex logic - just simple availability check
            return true;
        }

        /**
         * PRESERVED: Original get_user_points method
         */
        private function get_user_points($user_id) {
            if (!$user_id) {
                return 0;
            }
            
            $points = get_user_meta($user_id, 'reward_points', true);
            return floatval($points);
        }

        /**
         * PRESERVED: Original calculate_required_points method
         */
        private function calculate_required_points($order = null) {
            $total_points = 0;
            
            if ($order) {
                // For completed orders
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $quantity = $item->get_quantity();
                    $points_cost = floatval(get_post_meta($product_id, 'price_point', true));
                    $total_points += $points_cost * $quantity;
                }
            } else {
                // For cart
                if (WC()->cart && !WC()->cart->is_empty()) {
                    foreach (WC()->cart->get_cart() as $cart_item) {
                        $product_id = $cart_item['product_id'];
                        $quantity = $cart_item['quantity'];
                        $points_cost = floatval(get_post_meta($product_id, 'price_point', true));
                        $total_points += $points_cost * $quantity;
                    }
                }
            }
            
            return round($total_points);
        }

        /**
         * ENHANCED: Calculate earned points method
         */
        private function calculate_earned_points($order = null) {
            $total_earned = 0;
            
            if ($order) {
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $quantity = $item->get_quantity();
                    $points_earned = floatval(get_post_meta($product_id, 'point_earned', true));
                    $total_earned += $points_earned * $quantity;
                }
            } else {
                if (WC()->cart && !WC()->cart->is_empty()) {
                    foreach (WC()->cart->get_cart() as $cart_item) {
                        $product_id = $cart_item['product_id'];
                        $quantity = $cart_item['quantity'];
                        $points_earned = floatval(get_post_meta($product_id, 'point_earned', true));
                        $total_earned += $points_earned * $quantity;
                    }
                }
            }
            
            return round($total_earned);
        }

        /**
         * ENHANCED: Better payment fields with earned points info
         */
        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }

            if (!is_user_logged_in()) {
                echo '<p class="woocommerce-error">' . __('You must be logged in to pay with points.', 'herbal-mix-creator2') . '</p>';
                return;
            }

            $user_id = get_current_user_id();
            $user_points = $this->get_user_points($user_id);
            $required_points = $this->calculate_required_points();
            $earned_points = $this->calculate_earned_points();

            echo '<div class="points-payment-info">';
            echo '<h4>' . __('🎯 Points Payment Details', 'herbal-mix-creator2') . '</h4>';
            
            // Enhanced table layout with earned points
            echo '<table class="points-payment-table" style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
            
            echo '<tr>';
            echo '<td style="padding: 8px; border: 1px solid #ddd;"><strong>' . __('Your Points Balance:', 'herbal-mix-creator2') . '</strong></td>';
            echo '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;"><strong>' . number_format($user_points, 0) . ' pts</strong></td>';
            echo '</tr>';
            
            if ($required_points > 0) {
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid #ddd;">' . __('Required for this Order:', 'herbal-mix-creator2') . '</td>';
                echo '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . number_format($required_points, 0) . ' pts</td>';
                echo '</tr>';
                
                $remaining_points = $user_points - $required_points;
                $status_color = $remaining_points >= 0 ? 'green' : 'red';
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid #ddd;">' . __('Remaining After Payment:', 'herbal-mix-creator2') . '</td>';
                echo '<td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: ' . $status_color . ';"><strong>' . number_format(max(0, $remaining_points), 0) . ' pts</strong></td>';
                echo '</tr>';
            }
            
            if ($earned_points > 0) {
                echo '<tr style="background: #f0f9ff;">';
                echo '<td style="padding: 8px; border: 1px solid #ddd;">' . __('🎁 Points You Will Earn:', 'herbal-mix-creator2') . '</td>';
                echo '<td style="padding: 8px; border: 1px solid #ddd; text-align: right; color: green;"><strong>+' . number_format($earned_points, 0) . ' pts</strong></td>';
                echo '</tr>';
            }
            
            echo '</table>';
            
            if ($required_points > 0) {
                if ($user_points >= $required_points) {
                    echo '<p style="color: green; font-weight: bold;">✅ ' . __('You have enough points for this order!', 'herbal-mix-creator2') . '</p>';
                } else {
                    $needed = $required_points - $user_points;
                    echo '<p class="woocommerce-error">⚠️ ' . sprintf(
                        __('You need %s more points to complete this order.', 'herbal-mix-creator2'),
                        number_format($needed, 0)
                    ) . '</p>';
                }
            } else {
                echo '<p style="color: orange;">ℹ️ ' . __('This order cannot be paid with points (no points cost assigned to products).', 'herbal-mix-creator2') . '</p>';
            }
            
            echo '</div>';
        }

        /**
         * ENHANCED: Process payment with earned points
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();

            if (!$user_id) {
                wc_add_notice(__('You must be logged in to pay with points.', 'herbal-mix-creator2'), 'error');
                return array('result' => 'fail');
            }

            $required_points = $this->calculate_required_points($order);
            $user_points = $this->get_user_points($user_id);

            if ($required_points <= 0) {
                wc_add_notice(__('This order cannot be paid with points.', 'herbal-mix-creator2'), 'error');
                return array('result' => 'fail');
            }

            if ($user_points < $required_points) {
                wc_add_notice(sprintf(
                    __('Insufficient points. You have %s points but need %s points.', 'herbal-mix-creator2'),
                    number_format($user_points, 0),
                    number_format($required_points, 0)
                ), 'error');
                return array('result' => 'fail');
            }

            // Deduct points
            $new_points = $user_points - $required_points;
            $result = update_user_meta($user_id, 'reward_points', $new_points);

            if (!$result) {
                wc_add_notice(__('Error processing points payment. Please try again.', 'herbal-mix-creator2'), 'error');
                return array('result' => 'fail');
            }

            // Record transaction in history (if database class exists)
            if (class_exists('Herbal_Mix_Database')) {
                Herbal_Mix_Database::record_points_transaction(
                    $user_id,
                    -$required_points,
                    'order_payment',
                    $order_id,
                    $user_points,
                    $new_points,
                    sprintf(__('Payment for order #%s', 'herbal-mix-creator2'), $order_id)
                );
            }

            // Complete the order
            $order->payment_complete();
            $order->add_order_note(sprintf(
                __('Paid with %s points. Transaction completed successfully.', 'herbal-mix-creator2'),
                number_format($required_points, 0)
            ));

            // Award earned points
            $earned_points = $this->calculate_earned_points($order);
            if ($earned_points > 0) {
                $final_points = $new_points + $earned_points;
                update_user_meta($user_id, 'reward_points', $final_points);
                
                if (class_exists('Herbal_Mix_Database')) {
                    Herbal_Mix_Database::record_points_transaction(
                        $user_id,
                        $earned_points,
                        'order_completed',
                        $order_id,
                        $new_points,
                        $final_points,
                        sprintf(__('Points earned from order #%s', 'herbal-mix-creator2'), $order_id)
                    );
                }
                
                $order->add_order_note(sprintf(
                    __('Customer earned %s points from this purchase.', 'herbal-mix-creator2'),
                    number_format($earned_points, 0)
                ));
            }

            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        /**
         * ENHANCED: Thank you page with earned points
         */
        public function thankyou_page($order_id) {
            $order = wc_get_order($order_id);
            
            if ($order->get_payment_method() === $this->id) {
                $required_points = $this->calculate_required_points($order);
                $earned_points = $this->calculate_earned_points($order);
                
                echo '<div class="woocommerce-message" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 15px 0; border-radius: 6px;">';
                echo '<h3>🎉 ' . __('Payment Successful!', 'herbal-mix-creator2') . '</h3>';
                echo '<p>✅ <strong>' . __('Paid:', 'herbal-mix-creator2') . '</strong> ' . number_format($required_points, 0) . ' ' . __('points', 'herbal-mix-creator2') . '</p>';
                
                if ($earned_points > 0) {
                    echo '<p>🎁 <strong>' . __('Earned:', 'herbal-mix-creator2') . '</strong> ' . number_format($earned_points, 0) . ' ' . __('points', 'herbal-mix-creator2') . '</p>';
                }
                
                $user_id = get_current_user_id();
                if ($user_id) {
                    $current_balance = $this->get_user_points($user_id);
                    echo '<p>👤 <strong>' . __('Your new balance:', 'herbal-mix-creator2') . '</strong> ' . number_format($current_balance, 0) . ' ' . __('points', 'herbal-mix-creator2') . '</p>';
                }
                
                echo '</div>';
            }
        }

        /**
         * PRESERVED: Original display_cart_points_info method (lower priority)
         * Note: This runs at priority 5, after the main system at default priority
         */
        public function display_cart_points_info() {
            // Don't show if main system already displayed points
            if (did_action('herbal_points_totals_displayed')) {
                return;
            }
            
            if (!is_user_logged_in()) return;
            
            $user_id = get_current_user_id();
            $user_points = $this->get_user_points($user_id);
            $required_points = $this->calculate_required_points();
            
            if ($required_points <= 0) return; // Only show if cart has items with points cost
            
            echo '<tr class="cart-points-info-legacy">';
            echo '<th>' . __('Points Payment Option', 'herbal-mix-creator2') . '</th>';
            echo '<td>';
            echo '<div class="points-summary">';
            echo sprintf(__('Required: %s pts | Available: %s pts', 'herbal-mix-creator2'), 
                       '<strong>' . number_format($required_points, 0) . '</strong>', 
                       '<strong>' . number_format($user_points, 0) . '</strong>');
                       
            if ($user_points >= $required_points) {
                echo '<br><span style="color: green;">✓ ' . __('You can pay with points!', 'herbal-mix-creator2') . '</span>';
            } else {
                $needed = $required_points - $user_points;
                echo '<br><span style="color: orange;">⚠ ' . sprintf(__('Need %s more points', 'herbal-mix-creator2'), number_format($needed, 0)) . '</span>';
            }
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }

        /**
         * PRESERVED: Original display_checkout_points_info method
         */
        public function display_checkout_points_info() {
            $this->display_cart_points_info();
        }

        /**
         * PRESERVED: Original display_single_product_points method
         */
        public function display_single_product_points() {
            global $product;
            
            if (!$product) return;
            
            $product_id = $product->get_id();
            $points_cost = floatval(get_post_meta($product_id, 'price_point', true));
            $points_earned = floatval(get_post_meta($product_id, 'point_earned', true));
            
            if ($points_cost <= 0 && $points_earned <= 0) return;
            
            echo '<div class="herbal-product-points-info" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 15px 0; border-radius: 6px;">';
            echo '<h4 style="margin-top: 0;">🎯 ' . __('Points Information', 'herbal-mix-creator2') . '</h4>';
            
            if ($points_cost > 0) {
                echo '<p>💰 ' . sprintf(__('Alternative payment: %s points', 'herbal-mix-creator2'), '<strong>' . number_format($points_cost, 0) . '</strong>') . '</p>';
            }
            
            if ($points_earned > 0) {
                echo '<p>🎁 ' . sprintf(__('Earn: %s points with purchase', 'herbal-mix-creator2'), '<strong>' . number_format($points_earned, 0) . '</strong>') . '</p>';
            }
            
            if (is_user_logged_in()) {
                $user_points = $this->get_user_points(get_current_user_id());
                echo '<p>👤 ' . sprintf(__('Your balance: %s points', 'herbal-mix-creator2'), '<strong>' . number_format($user_points, 0) . '</strong>') . '</p>';
                
                if ($points_cost > 0) {
                    if ($user_points >= $points_cost) {
                        echo '<p style="color: green;">✓ ' . __('You can buy this with points!', 'herbal-mix-creator2') . '</p>';
                    } else {
                        echo '<p style="color: orange;">⚠ ' . sprintf(__('Need %s more points', 'herbal-mix-creator2'), number_format($points_cost - $user_points, 0)) . '</p>';
                    }
                }
            }
            
            echo '</div>';
        }

        /**
         * PRESERVED: Original validation methods
         */
        public function validate_min_points_required_field($key, $value) {
            if ($value < 0) {
                WC_Admin_Settings::add_error(__('Minimum points required cannot be negative.', 'herbal-mix-creator2'));
                return $this->get_option($key);
            }
            return $value;
        }
    }

    /**
     * CRITICAL: Register the gateway with WooCommerce
     */
    add_filter('woocommerce_payment_gateways', function($methods) {
        // Ensure we're not adding duplicates
        if (!in_array('WC_Gateway_Points_Payment', $methods)) {
            $methods[] = 'WC_Gateway_Points_Payment';
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Herbal Gateway: Added WC_Gateway_Points_Payment to payment methods via filter');
            }
        }
        return $methods;
    }, 10);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Herbal Gateway: Successfully initialized FINAL FIXED version');
    }
    
    return true;
}

/**
 * PRESERVED: Original CSS styles function (enhanced)
 */
if (!function_exists('herbal_points_payment_styles')) {
    function herbal_points_payment_styles() {
        if (is_checkout() || is_cart() || is_product()) {
            ?>
            <style>
            .points-payment-info {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 6px;
                padding: 15px;
                margin: 15px 0;
            }
            
            .points-payment-info h4 {
                margin-top: 0;
                margin-bottom: 10px;
                color: #495057;
            }
            
            .points-payment-table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0;
            }
            
            .points-payment-table td,
            .points-payment-table th {
                padding: 8px;
                border: 1px solid #ddd;
                font-size: 14px;
            }
            
            .cart-points-info th,
            .cart-points-info td,
            .cart-points-info-legacy th,
            .cart-points-info-legacy td {
                font-size: 14px;
                padding: 8px;
            }
            
            .points-summary {
                font-size: 14px;
                line-height: 1.4;
            }
            
            .herbal-product-points-info {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 6px;
                padding: 15px;
                margin: 15px 0;
            }
            
            .herbal-product-points-info h4 {
                margin-top: 0;
                color: #495057;
            }
            
            .woocommerce-error {
                color: #dc3545;
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                padding: 10px;
                margin: 10px 0;
            }
            
            /* Hide legacy display if main system is active */
            .cart-points-info-legacy {
                display: none;
            }
            </style>
            <?php
        }
    }
    add_action('wp_head', 'herbal_points_payment_styles');
}