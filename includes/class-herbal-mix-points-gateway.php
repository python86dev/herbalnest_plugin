<?php
/**
 * 🔧 WORKING HERBAL POINTS GATEWAY - Compatible Version
 * File: includes/class-herbal-mix-points-gateway.php
 * 
 * FIXES:
 * - Compatible with existing herbal_init_points_payment_gateway() call
 * - No dynamic properties (PHP 8.2+ compatible)
 * - Enhanced cart/checkout display
 * - Working gateway registration
 * - No conversion rate conflicts
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize Points Payment Gateway
 * COMPATIBLE with existing main plugin file calls
 */
function herbal_init_points_payment_gateway() {
    // Check if already loaded or WC not available
    if (class_exists('WC_Gateway_Points_Payment') || !class_exists('WC_Payment_Gateway')) {
        return false;
    }

    /**
     * Points Payment Gateway Class
     */
    class WC_Gateway_Points_Payment extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'points_payment';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __('Points Payment', 'herbal-mix-creator2');
            $this->method_description = __('Allow customers to pay using reward points.', 'herbal-mix-creator2');
            $this->supports = array('products');

            // Initialize settings
            $this->init_form_fields();
            $this->init_settings();

            // FIXED: No dynamic properties
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');

            // Register hooks
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            
            // Enhanced cart/checkout display
            add_action('woocommerce_cart_totals_after_order_total', array($this, 'display_cart_points_summary'));
            add_action('woocommerce_review_order_after_order_total', array($this, 'display_checkout_points_summary'));
            
            // Individual product points in cart
            add_filter('woocommerce_cart_item_price', array($this, 'add_points_to_cart_item'), 10, 3);
            
            // Add styles
            add_action('wp_head', array($this, 'add_points_styles'));
        }

        /**
         * Gateway settings
         */
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
                    'description' => __('Payment method title displayed during checkout.', 'herbal-mix-creator2'),
                    'default' => __('Pay with Points', 'herbal-mix-creator2'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'herbal-mix-creator2'),
                    'type' => 'textarea',
                    'description' => __('Payment method description.', 'herbal-mix-creator2'),
                    'default' => __('Use your reward points to pay for this order.', 'herbal-mix-creator2'),
                    'desc_tip' => true,
                ),
                'min_points_required' => array(
                    'title' => __('Minimum Points Required', 'herbal-mix-creator2'),
                    'type' => 'number',
                    'description' => __('Minimum points user must have to see this option.', 'herbal-mix-creator2'),
                    'default' => '100',
                    'custom_attributes' => array('step' => '1', 'min' => '0')
                )
            );
        }

        /**
         * Check if gateway is available
         */
        public function is_available() {
            if (!$this->enabled || $this->enabled !== 'yes') {
                return false;
            }

            if (!is_user_logged_in()) {
                return false;
            }

            $user_points = $this->get_user_points(get_current_user_id());
            $min_points = intval($this->get_option('min_points_required', 100));
            $required_points = $this->calculate_cart_points_required();

            return $user_points >= $min_points && $required_points > 0 && $user_points >= $required_points;
        }

        /**
         * Get user's current points
         */
        private function get_user_points($user_id) {
            if (!$user_id) return 0;
            return floatval(get_user_meta($user_id, 'reward_points', true)) ?: 0;
        }

        /**
         * Calculate total points required for cart
         */
        private function calculate_cart_points_required($order = null) {
            $total_points = 0;
            
            if ($order) {
                foreach ($order->get_items() as $item) {
                    $product = $item->get_product();
                    if ($product) {
                        $price_point = floatval(get_post_meta($product->get_id(), 'price_point', true));
                        if ($price_point > 0) {
                            $total_points += $price_point * $item->get_quantity();
                        }
                    }
                }
            } else {
                if (WC()->cart && !WC()->cart->is_empty()) {
                    foreach (WC()->cart->get_cart() as $cart_item) {
                        $product = $cart_item['data'];
                        if ($product) {
                            $price_point = floatval(get_post_meta($product->get_id(), 'price_point', true));
                            if ($price_point > 0) {
                                $total_points += $price_point * $cart_item['quantity'];
                            }
                        }
                    }
                }
            }
            
            return round($total_points);
        }

        /**
         * Get comprehensive cart points summary
         */
        private function get_cart_points_summary() {
            $user_id = get_current_user_id();
            $user_points = $this->get_user_points($user_id);
            
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

        /**
         * Display enhanced cart points summary
         */
        public function display_cart_points_summary() {
            if (!is_user_logged_in() || $this->enabled !== 'yes') {
                return;
            }
            
            $summary = $this->get_cart_points_summary();
            
            if ($summary['total_cost'] > 0) {
                echo '<tr class="herbal-points-summary-row">';
                echo '<td colspan="2">';
                echo $this->render_points_summary_html($summary, 'cart');
                echo '</td>';
                echo '</tr>';
            }
        }

        /**
         * Display enhanced checkout points summary
         */
        public function display_checkout_points_summary() {
            if (!is_user_logged_in() || $this->enabled !== 'yes') {
                return;
            }
            
            $summary = $this->get_cart_points_summary();
            
            if ($summary['total_cost'] > 0) {
                echo '<tr class="herbal-points-summary-row">';
                echo '<td colspan="2">';
                echo $this->render_points_summary_html($summary, 'checkout');
                echo '</td>';
                echo '</tr>';
            }
        }

        /**
         * Render points summary HTML
         */
        private function render_points_summary_html($summary, $context = 'cart') {
            $icon = $context === 'checkout' ? '💳' : '🛒';
            
            $html = '<div class="herbal-points-summary">';
            $html .= '<div class="points-header">';
            $html .= '<h4>' . $icon . ' ' . __('Points Summary', 'herbal-mix-creator2') . '</h4>';
            $html .= '</div>';
            
            $html .= '<div class="points-breakdown">';
            
            // Total cost
            $html .= '<div class="points-row">';
            $html .= '<span class="label">' . __('Total Points Required:', 'herbal-mix-creator2') . '</span>';
            $html .= '<span class="value cost">' . number_format($summary['total_cost'], 0) . ' pts</span>';
            $html .= '</div>';
            
            // User balance
            $html .= '<div class="points-row">';
            $html .= '<span class="label">' . __('Your Points Balance:', 'herbal-mix-creator2') . '</span>';
            $html .= '<span class="value balance">' . number_format($summary['user_points'], 0) . ' pts</span>';
            $html .= '</div>';
            
            // Earned points
            if ($summary['total_earned'] > 0) {
                $html .= '<div class="points-row">';
                $html .= '<span class="label">' . __('Points You\'ll Earn:', 'herbal-mix-creator2') . '</span>';
                $html .= '<span class="value earned">+' . number_format($summary['total_earned'], 0) . ' pts</span>';
                $html .= '</div>';
            }
            
            $html .= '</div>'; // End breakdown
            
            // Status
            $html .= '<div class="points-status">';
            if ($summary['can_pay']) {
                $remaining = $summary['user_points'] - $summary['total_cost'];
                $html .= '<div class="status-success">';
                $html .= '<span class="icon">✅</span>';
                $html .= '<span class="text">' . __('You can pay with points!', 'herbal-mix-creator2') . '</span>';
                $html .= '<div class="details">' . 
                         sprintf(__('Remaining: %s pts', 'herbal-mix-creator2'), number_format($remaining, 0)) . 
                         '</div>';
                $html .= '</div>';
            } else if ($summary['total_cost'] > 0) {
                $html .= '<div class="status-insufficient">';
                $html .= '<span class="icon">❌</span>';
                $html .= '<span class="text">' . __('Insufficient points', 'herbal-mix-creator2') . '</span>';
                $html .= '<div class="details">' . 
                         sprintf(__('Need %s more points', 'herbal-mix-creator2'), number_format($summary['shortage'], 0)) . 
                         '</div>';
                $html .= '</div>';
            }
            $html .= '</div>'; // End status
            
            $html .= '</div>'; // End summary
            
            return $html;
        }

        /**
         * Add points info to individual cart items
         */
        public function add_points_to_cart_item($price_html, $cart_item, $cart_item_key) {
            if (!is_user_logged_in() || $this->enabled !== 'yes') {
                return $price_html;
            }
            
            $product = $cart_item['data'];
            if (!$product) {
                return $price_html;
            }
            
            $price_point = floatval(get_post_meta($product->get_id(), 'price_point', true));
            $point_earned = floatval(get_post_meta($product->get_id(), 'point_earned', true));
            $quantity = $cart_item['quantity'];
            
            if ($price_point > 0 || $point_earned > 0) {
                $points_html = '<div class="item-points">';
                
                if ($price_point > 0) {
                    $total_cost = $price_point * $quantity;
                    $points_html .= '<span class="cost">' . number_format($total_cost, 0) . ' pts</span>';
                }
                
                if ($point_earned > 0) {
                    $total_earned = $point_earned * $quantity;
                    $points_html .= '<span class="earned">+' . number_format($total_earned, 0) . ' pts</span>';
                }
                
                $points_html .= '</div>';
                
                return $price_html . $points_html;
            }
            
            return $price_html;
        }

        /**
         * Payment fields display
         */
        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }

            if (!is_user_logged_in()) {
                echo '<p class="error">' . __('You must be logged in to pay with points.', 'herbal-mix-creator2') . '</p>';
                return;
            }

            $summary = $this->get_cart_points_summary();
            
            echo '<div class="points-payment-confirmation">';
            
            if ($summary['can_pay']) {
                echo '<div class="confirmation-success">';
                echo '<h4>✅ ' . __('Ready to pay with points!', 'herbal-mix-creator2') . '</h4>';
                echo '<p>' . sprintf(
                    __('This order requires %s points. You will have %s points remaining after payment.', 'herbal-mix-creator2'),
                    number_format($summary['total_cost'], 0),
                    number_format($summary['user_points'] - $summary['total_cost'], 0)
                ) . '</p>';
                echo '</div>';
            } else {
                echo '<div class="confirmation-error">';
                echo '<h4>❌ ' . __('Cannot pay with points', 'herbal-mix-creator2') . '</h4>';
                echo '<p>' . sprintf(
                    __('You need %s more points to complete this order.', 'herbal-mix-creator2'),
                    number_format($summary['shortage'], 0)
                ) . '</p>';
                echo '</div>';
            }
            
            echo '</div>';
        }

        /**
         * Validate payment fields
         */
        public function validate_fields() {
            if (!is_user_logged_in()) {
                wc_add_notice(__('You must be logged in to pay with points.', 'herbal-mix-creator2'), 'error');
                return false;
            }

            $summary = $this->get_cart_points_summary();

            if (!$summary['can_pay']) {
                wc_add_notice(
                    sprintf(__('Insufficient points. You need %s more points.', 'herbal-mix-creator2'),
                           number_format($summary['shortage'], 0)),
                    'error'
                );
                return false;
            }

            return true;
        }

        /**
         * Process payment
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return array('result' => 'fail');
            }

            $user_id = $order->get_user_id();
            if (!$user_id) {
                wc_add_notice(__('User not found.', 'herbal-mix-creator2'), 'error');
                return array('result' => 'fail');
            }

            $required_points = $this->calculate_cart_points_required($order);
            
            if ($this->deduct_user_points($user_id, $required_points, $order_id)) {
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
                return array('result' => 'fail');
            }
        }

        /**
         * Deduct points from user account
         */
        private function deduct_user_points($user_id, $points, $order_id) {
            $current_points = $this->get_user_points($user_id);
            
            if ($current_points < $points) {
                return false;
            }
            
            $new_points = $current_points - $points;
            $success = update_user_meta($user_id, 'reward_points', $new_points);
            
            // Record transaction in history
            if ($success && class_exists('Herbal_Mix_Database')) {
                Herbal_Mix_Database::record_points_transaction(
                    $user_id,
                    -$points,
                    'order_payment',
                    $order_id,
                    $current_points,
                    $new_points,
                    sprintf(__('Payment for order #%s', 'herbal-mix-creator2'), $order_id)
                );
            }
            
            return $success;
        }

        /**
         * Add comprehensive CSS styles
         */
        public function add_points_styles() {
            if (!is_cart() && !is_checkout()) {
                return;
            }
            ?>
            <style>
            /* Main Points Summary */
            .herbal-points-summary {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border: 2px solid #28a745;
                border-radius: 12px;
                padding: 20px;
                margin: 15px 0;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.1);
            }
            
            .herbal-points-summary .points-header h4 {
                margin: 0 0 15px 0;
                color: #28a745;
                font-size: 18px;
                font-weight: 600;
                text-align: center;
                border-bottom: 2px solid #28a745;
                padding-bottom: 8px;
            }
            
            .points-breakdown .points-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #dee2e6;
            }
            
            .points-breakdown .points-row:last-child {
                border-bottom: none;
            }
            
            .points-row .label {
                font-weight: 500;
                color: #495057;
            }
            
            .points-row .value {
                font-weight: 700;
                font-size: 16px;
            }
            
            .points-row .value.cost {
                color: #dc3545;
            }
            
            .points-row .value.balance {
                color: #17a2b8;
            }
            
            .points-row .value.earned {
                color: #28a745;
            }
            
            /* Status Messages */
            .points-status {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 2px solid #dee2e6;
            }
            
            .status-success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                padding: 12px;
                border-radius: 8px;
                text-align: center;
            }
            
            .status-insufficient {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
                padding: 12px;
                border-radius: 8px;
                text-align: center;
            }
            
            .status-success .icon,
            .status-insufficient .icon {
                font-size: 18px;
                margin-right: 8px;
            }
            
            .status-success .text,
            .status-insufficient .text {
                font-weight: 600;
                font-size: 16px;
            }
            
            .status-success .details,
            .status-insufficient .details {
                font-size: 14px;
                margin-top: 5px;
                opacity: 0.9;
            }
            
            /* Individual Item Points */
            .item-points {
                margin-top: 5px;
                font-size: 12px;
            }
            
            .item-points .cost {
                color: #dc3545;
                font-weight: 600;
                margin-right: 10px;
            }
            
            .item-points .earned {
                color: #28a745;
                font-weight: 600;
            }
            
            /* Payment Confirmation */
            .points-payment-confirmation {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 6px;
                padding: 15px;
                margin: 15px 0;
            }
            
            .confirmation-success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                padding: 15px;
                border-radius: 8px;
            }
            
            .confirmation-error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                color: #721c24;
                padding: 15px;
                border-radius: 8px;
            }
            
            /* Mobile Responsive */
            @media (max-width: 768px) {
                .herbal-points-summary {
                    padding: 15px;
                }
                
                .points-row {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 5px;
                }
                
                .points-row .value {
                    font-size: 18px;
                }
            }
            </style>
            <?php
        }
    }

    /**
     * Register gateway with WooCommerce
     */
    add_filter('woocommerce_payment_gateways', function($methods) {
        $methods[] = 'WC_Gateway_Points_Payment';
        return $methods;
    });

    return true;
}

/**
 * Clean up old gateway options (run once)
 */
if (current_user_can('manage_options') && isset($_GET['herbal_cleanup_old_gateway'])) {
    global $wpdb;
    
    // Delete old gateway settings
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_herbal_points_payment_%'");
    
    // Clear WooCommerce cache
    if (function_exists('wc_clear_notices')) {
        wc_clear_notices();
    }
    
    add_action('admin_notices', function() {
        echo '<div class="notice notice-success"><p>✅ Old gateway settings cleaned up successfully!</p></div>';
    });
}