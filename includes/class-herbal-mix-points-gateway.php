<?php
/**
 * Herbal Mix Points Payment Gateway - CLEANED VERSION
 * File: includes/class-herbal-mix-points-gateway.php
 * 
 * MAJOR CHANGES:
 * - REMOVED: conversion_rate settings and calculations
 * - REMOVED: calculate_required_points() method with currency conversion
 * - CHANGED: Now uses direct price_point values from product metadata
 * - PRESERVED: Direct points payment functionality
 * - PRESERVED: Points validation and transaction recording
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize Points Payment Gateway (only once)
 */
if (!function_exists('herbal_init_points_payment_gateway')) {
    function herbal_init_points_payment_gateway() {
        if (!class_exists('WC_Payment_Gateway') || class_exists('WC_Gateway_Points_Payment')) {
            return false;
        }

        class WC_Gateway_Points_Payment extends WC_Payment_Gateway {

            public function __construct() {
                $this->id = 'herbal_points_payment';
                $this->icon = '';
                $this->has_fields = true;
                $this->method_title = __('Points Payment', 'herbal-mix-creator2');
                $this->method_description = __('Allow customers to pay using their reward points.', 'herbal-mix-creator2');

                $this->supports = array('products');

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->enabled = $this->get_option('enabled');
                $this->min_points_required = $this->get_option('min_points_required', 100);

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_cart_totals_after_order_total', array($this, 'show_cart_points_info'));
                add_action('woocommerce_review_order_after_order_total', array($this, 'show_checkout_points_info'));
            }

            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'herbal-mix-creator2'),
                        'type' => 'checkbox',
                        'label' => __('Enable Points Payment', 'herbal-mix-creator2'),
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => __('Title', 'herbal-mix-creator2'),
                        'type' => 'text',
                        'description' => __('Payment method title shown during checkout.', 'herbal-mix-creator2'),
                        'default' => __('Pay with Points', 'herbal-mix-creator2'),
                        'desc_tip' => true,
                    ),
                    'description' => array(
                        'title' => __('Description', 'herbal-mix-creator2'),
                        'type' => 'textarea',
                        'description' => __('Payment method description shown during checkout.', 'herbal-mix-creator2'),
                        'default' => __('Pay for your order using your reward points based on product point prices.', 'herbal-mix-creator2'),
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
                    // REMOVED: conversion_rate field - no longer needed
                );
            }

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

                return $user_points >= $min_points;
            }

            private function get_user_points($user_id) {
                if (!$user_id) {
                    return 0;
                }
                
                $points = get_user_meta($user_id, 'reward_points', true);
                return floatval($points);
            }

            /**
             * Calculate required points based on product price_point metadata
             * CHANGED: No longer uses conversion rates - uses direct price_point values
             */
            private function calculate_required_points_from_products($order = null) {
                $total_points = 0;
                
                if ($order) {
                    // Calculate from order items
                    foreach ($order->get_items() as $item) {
                        $product = $item->get_product();
                        if ($product) {
                            $price_point = floatval(get_post_meta($product->get_id(), 'price_point', true));
                            if ($price_point > 0) {
                                $quantity = $item->get_quantity();
                                $total_points += $price_point * $quantity;
                            }
                        }
                    }
                } else {
                    // Calculate from cart
                    if (WC()->cart && !WC()->cart->is_empty()) {
                        foreach (WC()->cart->get_cart() as $cart_item) {
                            $product = $cart_item['data'];
                            if ($product) {
                                $price_point = floatval(get_post_meta($product->get_id(), 'price_point', true));
                                if ($price_point > 0) {
                                    $quantity = $cart_item['quantity'];
                                    $total_points += $price_point * $quantity;
                                }
                            }
                        }
                    }
                }
                
                return round($total_points);
            }

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
                $required_points = $this->calculate_required_points_from_products();

                echo '<div class="points-payment-info">';
                echo '<h4>' . __('Points Payment Details', 'herbal-mix-creator2') . '</h4>';
                echo '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
                
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid #ddd;"><strong>' . __('Your Points Balance:', 'herbal-mix-creator2') . '</strong></td>';
                echo '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;"><strong>' . number_format($user_points, 0) . ' pts</strong></td>';
                echo '</tr>';
                
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid #ddd;">' . __('Required for this Order:', 'herbal-mix-creator2') . '</td>';
                echo '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;">' . number_format($required_points, 0) . ' pts</td>';
                echo '</tr>';
                
                $remaining_points = $user_points - $required_points;
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid #ddd;">' . __('Remaining After Payment:', 'herbal-mix-creator2') . '</td>';
                echo '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;"><strong>' . number_format(max(0, $remaining_points), 0) . ' pts</strong></td>';
                echo '</tr>';
                
                echo '</table>';

                if ($user_points < $required_points) {
                    echo '<p class="woocommerce-error">' . 
                         sprintf(__('Insufficient points. You need %s more points to complete this order.', 'herbal-mix-creator2'), 
                                number_format($required_points - $user_points, 0)) . '</p>';
                }

                echo '</div>';
            }

            public function validate_fields() {
                if (!is_user_logged_in()) {
                    wc_add_notice(__('You must be logged in to pay with points.', 'herbal-mix-creator2'), 'error');
                    return false;
                }

                $user_id = get_current_user_id();
                $user_points = $this->get_user_points($user_id);
                $required_points = $this->calculate_required_points_from_products();

                if ($user_points < $required_points) {
                    wc_add_notice(
                        sprintf(__('Insufficient points. You have %s points but need %s points.', 'herbal-mix-creator2'),
                               number_format($user_points, 0),
                               number_format($required_points, 0)),
                        'error'
                    );
                    return false;
                }

                return true;
            }

            public function process_payment($order_id) {
                $order = wc_get_order($order_id);
                
                if (!$order) {
                    return array('result' => 'fail', 'redirect' => '');
                }

                $user_id = $order->get_user_id();
                if (!$user_id) {
                    wc_add_notice(__('User not found.', 'herbal-mix-creator2'), 'error');
                    return array('result' => 'fail', 'redirect' => '');
                }

                $required_points = $this->calculate_required_points_from_products($order);
                $success = $this->subtract_user_points($user_id, $required_points, 'order_payment', $order_id);

                if ($success) {
                    $order->payment_complete();
                    $order->add_order_note(
                        sprintf(__('Payment completed using %s points.', 'herbal-mix-creator2'), 
                               number_format($required_points, 0))
                    );

                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                } else {
                    wc_add_notice(__('Payment failed. Insufficient points.', 'herbal-mix-creator2'), 'error');
                    return array('result' => 'fail', 'redirect' => '');
                }
            }

            private function subtract_user_points($user_id, $points, $transaction_type = 'payment', $reference_id = null) {
                $current_points = $this->get_user_points($user_id);
                
                if ($current_points < $points) {
                    return false;
                }
                
                $new_points = $current_points - $points;
                $success = update_user_meta($user_id, 'reward_points', $new_points);
                
                if ($success && class_exists('Herbal_Mix_Database')) {
                    Herbal_Mix_Database::record_points_transaction(
                        $user_id,
                        -$points,
                        $transaction_type,
                        $reference_id,
                        $current_points,
                        $new_points,
                        sprintf(__('Payment for order #%s', 'herbal-mix-creator2'), $reference_id)
                    );
                }
                
                return $success ? $new_points : false;
            }

            /**
             * Show points info in cart
             */
            public function show_cart_points_info() {
                if (!is_user_logged_in() || $this->enabled !== 'yes') {
                    return;
                }

                $user_points = $this->get_user_points(get_current_user_id());
                $required_points = $this->calculate_required_points_from_products();

                if ($required_points > 0) {
                    echo '<tr class="cart-points-info">';
                    echo '<th>' . __('Points Required:', 'herbal-mix-creator2') . '</th>';
                    echo '<td><strong>' . number_format($required_points, 0) . ' pts</strong></td>';
                    echo '</tr>';
                    
                    echo '<tr class="cart-points-info">';
                    echo '<th>' . __('Your Points:', 'herbal-mix-creator2') . '</th>';
                    echo '<td><strong>' . number_format($user_points, 0) . ' pts</strong></td>';
                    echo '</tr>';
                }
            }

            /**
             * Show points info in checkout
             */
            public function show_checkout_points_info() {
                $this->show_cart_points_info();
            }

            // REMOVED: validate_conversion_rate_field() - no longer needed
        }
    }

    /**
     * Register the gateway with WooCommerce
     */
    add_filter('woocommerce_payment_gateways', function($methods) {
        $methods[] = 'WC_Gateway_Points_Payment';
        return $methods;
    });
    
    return true;
}

/**
 * Add CSS styles for points payment
 */
if (!function_exists('herbal_points_payment_styles')) {
    function herbal_points_payment_styles() {
        if (is_checkout() || is_cart()) {
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
            
            .cart-points-info th,
            .cart-points-info td {
                font-size: 14px;
                padding: 8px;
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