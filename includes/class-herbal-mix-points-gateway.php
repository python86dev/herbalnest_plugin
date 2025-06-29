<?php
/**
 * FIXED Points Payment Gateway for WooCommerce
 * Uses ingredient-based points system only (no conversion rate)
 * File: includes/class-herbal-mix-points-gateway.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize Points Payment Gateway - FIXED VERSION
 */
function herbal_init_points_payment_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Herbal Gateway: WC_Payment_Gateway class not found');
        }
        return false;
    }

    /**
     * FIXED Points Payment Gateway Class
     * - Removed conversion_rate system
     * - Uses product meta price_point directly
     * - Proper cart and checkout integration
     */
    if (!class_exists('WC_Gateway_Points_Payment')) {
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
                
                // FIXED: Proper hooks for cart and checkout display
                add_action('woocommerce_cart_totals_after_order_total', array($this, 'display_cart_points_info'));
                add_action('woocommerce_review_order_after_order_total', array($this, 'display_checkout_points_info'));
                
                // Add points info to product pages
                add_action('woocommerce_single_product_summary', array($this, 'display_single_product_points'), 25);
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
                        'default' => '100',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min' => '0'
                        )
                    )
                    // REMOVED: conversion_rate field
                );
            }

            /**
             * FIXED: Availability check using actual product points
             */
            public function is_available() {
                if (!$this->enabled || $this->enabled !== 'yes') {
                    return false;
                }

                if (!is_user_logged_in()) {
                    return false;
                }

                $user_id = get_current_user_id();
                $user_points = $this->get_user_points($user_id);
                $min_points = intval($this->get_option('min_points_required', 100));
                
                // Check if user has minimum points
                if ($user_points < $min_points) {
                    return false;
                }

                // FIXED: Check if cart/order has any items with points cost
                $required_points = $this->calculate_required_points();
                
                // Only show if cart has items that can be paid with points AND user has enough
                return $required_points > 0 && $user_points >= $required_points;
            }

            /**
             * Get user points from meta
             */
            private function get_user_points($user_id) {
                if (!$user_id) {
                    return 0;
                }
                
                $points = get_user_meta($user_id, 'reward_points', true);
                return floatval($points);
            }

            /**
             * FIXED: Calculate required points from product meta, not conversion
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
             * FIXED: Payment fields with proper points display
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

                echo '<div class="points-payment-info">';
                echo '<h4>' . __('Points Payment Details', 'herbal-mix-creator2') . '</h4>';
                
                // FIXED: Better table layout
                echo '<table class="points-payment-table" style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
                
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid #ddd;"><strong>' . __('Your Points Balance:', 'herbal-mix-creator2') . '</strong></td>';
                echo '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;"><strong>' . number_format($user_points, 0) . ' pts</strong></td>';
                echo '</tr>';
                
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
                
                echo '</table>';
                
                if ($remaining_points < 0) {
                    echo '<p class="woocommerce-error">' . sprintf(
                        __('You need %s more points to complete this order.', 'herbal-mix-creator2'),
                        number_format(abs($remaining_points), 0)
                    ) . '</p>';
                }
                
                echo '</div>';
            }

            /**
             * FIXED: Process payment with proper points deduction
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

                if ($user_points < $required_points) {
                    wc_add_notice(sprintf(
                        __('Insufficient points. You have %s points but need %s points.', 'herbal-mix-creator2'),
                        number_format($user_points, 0),
                        number_format($required_points, 0)
                    ), 'error');
                    return array('result' => 'fail');
                }

                // Deduct points using database class
                if (class_exists('Herbal_Mix_Database')) {
                    $result = Herbal_Mix_Database::subtract_user_points(
                        $user_id,
                        $required_points,
                        'order_payment',
                        $order_id,
                        sprintf(__('Payment for order #%s', 'herbal-mix-creator2'), $order_id)
                    );
                } else {
                    // Fallback method
                    $new_points = $user_points - $required_points;
                    $result = update_user_meta($user_id, 'reward_points', $new_points);
                }

                if (!$result || is_wp_error($result)) {
                    wc_add_notice(__('Error processing points payment. Please try again.', 'herbal-mix-creator2'), 'error');
                    return array('result' => 'fail');
                }

                // Complete the order
                $order->payment_complete();
                $order->add_order_note(sprintf(
                    __('Paid with %s points. Transaction completed successfully.', 'herbal-mix-creator2'),
                    number_format($required_points, 0)
                ));

                // Award points for purchase (if products have point_earned meta)
                $this->award_purchase_points($order);

                wc_reduce_stock_levels($order_id);
                WC()->cart->empty_cart();

                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            }

            /**
             * NEW: Award points for purchase based on product meta
             */
            private function award_purchase_points($order) {
                $user_id = $order->get_user_id();
                if (!$user_id) return;

                $total_earned = 0;
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $quantity = $item->get_quantity();
                    $points_earned = floatval(get_post_meta($product_id, 'point_earned', true));
                    $total_earned += $points_earned * $quantity;
                }

                if ($total_earned > 0 && class_exists('Herbal_Mix_Database')) {
                    Herbal_Mix_Database::add_user_points(
                        $user_id,
                        $total_earned,
                        'order_completed',
                        $order->get_id(),
                        sprintf(__('Points earned from order #%s', 'herbal-mix-creator2'), $order->get_id())
                    );

                    $order->add_order_note(sprintf(
                        __('Customer earned %s points from this purchase.', 'herbal-mix-creator2'),
                        number_format($total_earned, 0)
                    ));
                }
            }

            /**
             * Thank you page
             */
            public function thankyou_page($order_id) {
                $order = wc_get_order($order_id);
                
                if ($order->get_payment_method() === $this->id) {
                    $required_points = $this->calculate_required_points($order);
                    
                    echo '<div class="woocommerce-message" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 15px 0; border-radius: 6px;">';
                    echo '<h3>' . __('Payment Successful!', 'herbal-mix-creator2') . '</h3>';
                    echo '<p>' . sprintf(
                        __('Your order has been paid using %s reward points.', 'herbal-mix-creator2'),
                        number_format($required_points, 0)
                    ) . '</p>';
                    echo '</div>';
                }
            }

            /**
             * FIXED: Display points info in cart
             */
            public function display_cart_points_info() {
                if (!is_user_logged_in()) return;
                
                $user_id = get_current_user_id();
                $user_points = $this->get_user_points($user_id);
                $required_points = $this->calculate_required_points();
                
                if ($required_points <= 0) return; // Only show if cart has items with points cost
                
                echo '<tr class="cart-points-info">';
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
             * Display points info in checkout (same as cart)
             */
            public function display_checkout_points_info() {
                $this->display_cart_points_info();
            }

            /**
             * NEW: Display points info on single product pages
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
        }
    }

    /**
     * Register the gateway with WooCommerce
     */
    add_filter('woocommerce_payment_gateways', function($methods) {
        $methods[] = 'WC_Gateway_Points_Payment';
        return $methods;
    });
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Herbal Gateway: Successfully initialized FIXED version');
    }
    
    return true;
}

/**
 * FIXED: Simplified CSS styles for points payment
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
            .cart-points-info td {
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
            </style>
            <?php
        }
    }
    add_action('wp_head', 'herbal_points_payment_styles');
}