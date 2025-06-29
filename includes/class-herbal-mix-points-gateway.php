<?php
/**
 * Points Payment Gateway for WooCommerce - COMPLETE WORKING VERSION
 * Updated to use Herbal_Mix_Database instead of HerbalPointsManager
 * UK Market Version (English Language)
 * 
 * FIXES:
 * - Fixed all PHP syntax errors
 * - Complete class implementation
 * - Proper error handling
 * - Working initialization
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize Points Payment Gateway
 * This function is called from herbal-mix-creator2.php
 */
function herbal_init_points_payment_gateway() {
    // Double check if WooCommerce gateway class exists
    if (!class_exists('WC_Payment_Gateway')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Herbal Gateway: WC_Payment_Gateway class not found');
        }
        return false;
    }

    /**
     * Points Payment Gateway Class
     */
    if (!class_exists('WC_Gateway_Points_Payment')) {
        class WC_Gateway_Points_Payment extends WC_Payment_Gateway {

            /**
             * Constructor
             */
            public function __construct() {
                $this->id = 'points_payment';
                $this->icon = '';
                $this->has_fields = true;
                $this->method_title = __('Pay with Points', 'herbal-mix-creator2');
                $this->method_description = __('Allow customers to pay using their reward points.', 'herbal-mix-creator2');

                // Supported features
                $this->supports = array(
                    'products'
                );

                // Initialize settings
                $this->init_form_fields();
                $this->init_settings();

                // Gateway settings
                $this->title = $this->get_option('title', __('Pay with Points', 'herbal-mix-creator2'));
                $this->description = $this->get_option('description', __('Pay for your order using your reward points.', 'herbal-mix-creator2'));
                $this->enabled = $this->get_option('enabled', 'yes');

                // Hooks
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
                
                // Display points info in cart and checkout
                add_action('woocommerce_cart_totals_after_order_total', array($this, 'display_cart_points_info'));
                add_action('woocommerce_review_order_after_order_total', array($this, 'display_checkout_points_info'));
            }

            /**
             * Initialize gateway form fields
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
                    ),
                    'conversion_rate' => array(
                        'title' => __('Points Conversion Rate', 'herbal-mix-creator2'),
                        'type' => 'number',
                        'description' => __('How many points equal £1 (e.g., 100 points = £1).', 'herbal-mix-creator2'),
                        'default' => '100',
                        'custom_attributes' => array(
                            'step' => '1',
                            'min' => '1'
                        )
                    ),
                    'partial_payment' => array(
                        'title' => __('Partial Payment', 'herbal-mix-creator2'),
                        'type' => 'checkbox',
                        'label' => __('Allow partial payment with points', 'herbal-mix-creator2'),
                        'description' => __('Users can use points for part of the order and pay remainder with other methods.', 'herbal-mix-creator2'),
                        'default' => 'no'
                    )
                );
            }

            /**
             * Check if gateway is available for current cart
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

                // Check if user has minimum required points
                if ($user_points < $min_points) {
                    return false;
                }

                // Check if user has enough points for current cart
                $required_points = $this->calculate_required_points();
                return $user_points >= $required_points;
            }

            /**
             * Get user points using unified database access
             */
            private function get_user_points($user_id) {
                if (!$user_id) {
                    return 0;
                }
                
                $points = get_user_meta($user_id, 'reward_points', true);
                return floatval($points);
            }

            /**
             * Subtract points from user account
             */
            private function subtract_user_points($user_id, $points, $transaction_type = 'payment', $reference_id = null) {
                $current_points = $this->get_user_points($user_id);
                
                if ($current_points < $points) {
                    return false;
                }
                
                $new_points = $current_points - $points;
                $success = update_user_meta($user_id, 'reward_points', $new_points);
                
                if ($success && class_exists('Herbal_Mix_Database')) {
                    // Record transaction in history
                    Herbal_Mix_Database::record_points_transaction(
                        $user_id,
                        -$points, // Negative for deduction
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
             * Calculate required points for current cart/order
             */
            private function calculate_required_points($order = null) {
                $total_points = 0;
                $conversion_rate = floatval($this->get_option('conversion_rate', 100));
                
                if ($order) {
                    // For existing order
                    $total_points = $order->get_total() * $conversion_rate;
                } else {
                    // For current cart
                    if (WC()->cart && !WC()->cart->is_empty()) {
                        foreach (WC()->cart->get_cart() as $cart_item) {
                            $product = $cart_item['data'];
                            $quantity = $cart_item['quantity'];
                            
                            if (!$product) continue;
                            
                            // Check if product has specific points price
                            $points_price = get_post_meta($product->get_id(), '_price_points', true);
                            
                            if ($points_price && $points_price > 0) {
                                $total_points += floatval($points_price) * $quantity;
                            } else {
                                // Use conversion rate
                                $regular_price = $product->get_price();
                                $total_points += $regular_price * $conversion_rate * $quantity;
                            }
                        }
                        
                        // Add shipping and fees
                        if (WC()->cart->get_shipping_total()) {
                            $total_points += WC()->cart->get_shipping_total() * $conversion_rate;
                        }
                        
                        if (WC()->cart->get_fee_total()) {
                            $total_points += WC()->cart->get_fee_total() * $conversion_rate;
                        }
                        
                        if (WC()->cart->get_total_tax()) {
                            $total_points += WC()->cart->get_total_tax() * $conversion_rate;
                        }
                    }
                }
                
                return round($total_points);
            }

            /**
             * Display payment fields on checkout
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
                echo '<table class="herbal-points-table" style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
                
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
                    echo '<div class="woocommerce-error" style="margin: 10px 0;">';
                    echo '<strong>' . __('Insufficient Points', 'herbal-mix-creator2') . '</strong><br>';
                    echo sprintf(__('You need %s more points to complete this order.', 'herbal-mix-creator2'), 
                               number_format($required_points - $user_points, 0));
                    echo '</div>';
                }
                
                echo '</div>';
            }

            /**
             * Process the payment
             */
            public function process_payment($order_id) {
                $order = wc_get_order($order_id);
                $user_id = get_current_user_id();
                
                if (!$user_id) {
                    wc_add_notice(__('You must be logged in to pay with points.', 'herbal-mix-creator2'), 'error');
                    return array('result' => 'fail');
                }

                $user_points = $this->get_user_points($user_id);
                $required_points = $this->calculate_required_points($order);

                // Verify user has enough points
                if ($user_points < $required_points) {
                    wc_add_notice(__('Insufficient points for this order.', 'herbal-mix-creator2'), 'error');
                    return array('result' => 'fail');
                }

                // Deduct points from user account
                $success = $this->subtract_user_points($user_id, $required_points, 'points_payment', $order_id);
                
                if (!$success) {
                    wc_add_notice(__('Failed to process points payment. Please try again.', 'herbal-mix-creator2'), 'error');
                    return array('result' => 'fail');
                }

                // Mark order as processing/completed
                $order->payment_complete();
                $order->add_order_note(sprintf(
                    __('Paid with %s points. Transaction completed successfully.', 'herbal-mix-creator2'),
                    number_format($required_points, 0)
                ));

                // Reduce stock levels
                wc_reduce_stock_levels($order_id);

                // Empty cart
                WC()->cart->empty_cart();

                // Return success result
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            }

            /**
             * Thank you page content
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
             * Display points info in cart
             */
            public function display_cart_points_info() {
                if (!is_user_logged_in()) return;
                
                $user_id = get_current_user_id();
                $user_points = $this->get_user_points($user_id);
                $required_points = $this->calculate_required_points();
                
                echo '<tr class="cart-points-info">';
                echo '<th>' . __('Points Payment Option', 'herbal-mix-creator2') . '</th>';
                echo '<td>';
                echo sprintf(__('Required: %s pts', 'herbal-mix-creator2'), number_format($required_points, 0));
                echo '<br>';
                echo sprintf(__('You have: %s pts', 'herbal-mix-creator2'), number_format($user_points, 0));
                echo '</td>';
                echo '</tr>';
            }

            /**
             * Display points info in checkout
             */
            public function display_checkout_points_info() {
                $this->display_cart_points_info();
            }

            /**
             * Validate admin field: minimum points required
             */
            public function validate_min_points_required_field($key, $value) {
                if ($value < 0) {
                    WC_Admin_Settings::add_error(__('Minimum points required cannot be negative.', 'herbal-mix-creator2'));
                    return $this->get_option($key);
                }
                return $value;
            }

            /**
             * Validate admin field: conversion rate
             */
            public function validate_conversion_rate_field($key, $value) {
                if ($value <= 0) {
                    WC_Admin_Settings::add_error(__('Conversion rate must be greater than 0.', 'herbal-mix-creator2'));
                    return $this->get_option($key);
                }
                return $value;
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
    
    // Log successful initialization
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Herbal Gateway: Successfully initialized and registered');
    }
    
    return true;
}

/**
 * Add CSS styles for points payment
 */
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
        
        .points-payment-info table {
            border-collapse: collapse;
            margin: 10px 0;
            width: 100%;
        }
        
        .points-payment-info table td {
            padding: 8px;
            border: 1px solid #dee2e6;
        }
        
        .herbal-points-table th,
        .herbal-points-table td {
            padding: 8px !important;
            border: 1px solid #ddd !important;
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

/**
 * Debug function to check if gateway is properly loaded
 */
function herbal_debug_payment_gateway() {
    if (current_user_can('manage_options') && isset($_GET['debug_herbal_payment'])) {
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        echo '<pre>';
        echo "=== HERBAL PAYMENT GATEWAY DEBUG ===\n\n";
        
        echo "Available Payment Gateways:\n";
        foreach ($gateways as $id => $gateway) {
            echo "- {$id}: {$gateway->get_title()}\n";
        }
        
        if (isset($gateways['points_payment'])) {
            echo "\n✅ Points Payment Gateway is loaded!\n";
            $points_gateway = $gateways['points_payment'];
            echo "Enabled: " . ($points_gateway->enabled === 'yes' ? 'Yes' : 'No') . "\n";
            echo "Available: " . ($points_gateway->is_available() ? 'Yes' : 'No') . "\n";
            
            if (is_user_logged_in()) {
                $user_points = get_user_meta(get_current_user_id(), 'reward_points', true) ?: 0;
                echo "User Points: " . number_format($user_points, 0) . "\n";
                echo "Min Required: " . $points_gateway->get_option('min_points_required', 100) . "\n";
            }
        } else {
            echo "\n❌ Points Payment Gateway NOT found!\n";
        }
        
        echo "\nClass exists: " . (class_exists('WC_Gateway_Points_Payment') ? 'Yes' : 'No') . "\n";
        echo "Database class: " . (class_exists('Herbal_Mix_Database') ? 'Yes' : 'No') . "\n";
        
        echo '</pre>';
        exit;
    }
}
add_action('init', 'herbal_debug_payment_gateway');