<?php
/**
 * COMPLETE Clean Points Payment Gateway for WooCommerce
 * Delegates ALL points operations to Herbal_Mix_Points_Manager
 * NO display duplication - clean separation of concerns
 * Uses existing database column names consistently
 * 
 * File: includes/class-herbal-mix-points-gateway.php
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize Points Payment Gateway - CLEAN VERSION
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
     * COMPLETE Points Payment Gateway Class
     * Delegates to Points Manager for all operations
     */
    class WC_Gateway_Points_Payment extends WC_Payment_Gateway {
        
        private $points_manager;
        
        public function __construct() {
            $this->id = 'points_payment';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __('Pay with Points', 'herbal-mix-creator2');
            $this->method_description = __('Allow customers to pay using their reward points.', 'herbal-mix-creator2');
            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title', __('Pay with Points', 'herbal-mix-creator2'));
            $this->description = $this->get_option('description', __('Pay for your order using your reward points.', 'herbal-mix-creator2'));
            $this->enabled = $this->get_option('enabled', 'yes');

            // Get points manager instance for delegation
            if (class_exists('Herbal_Mix_Points_Manager')) {
                $this->points_manager = Herbal_Mix_Points_Manager::get_instance();
            }

            // WooCommerce hooks
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            
            // NO DISPLAY HOOKS - Points Manager handles all display!
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Herbal Gateway: WC_Gateway_Points_Payment constructed successfully');
            }
        }
        
        /**
         * Initialize form fields for admin settings
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
                    'default' => '0',
                    'custom_attributes' => array(
                        'step' => '1',
                        'min' => '0'
                    )
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

            if (!$this->points_manager) {
                return false; // Points Manager not available
            }

            // Check if user has minimum required points
            $min_points = $this->get_option('min_points_required', 0);
            $user_points = $this->points_manager->get_user_points(get_current_user_id());
            
            if ($user_points < $min_points) {
                return false;
            }
            
            // Check if cart has any items that can be paid with points
            $cart_points = $this->points_manager->calculate_cart_points();
            
            return $cart_points['cost'] > 0;
        }
        
        /**
         * Display payment fields during checkout
         */
        public function payment_fields() {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }

            if (!is_user_logged_in()) {
                echo '<p class="woocommerce-error">' . __('You must be logged in to pay with points.', 'herbal-mix-creator2') . '</p>';
                return;
            }

            if (!$this->points_manager) {
                echo '<p class="woocommerce-error">' . __('Points system not available.', 'herbal-mix-creator2') . '</p>';
                return;
            }

            $user_id = get_current_user_id();
            $user_points = $this->points_manager->get_user_points($user_id);
            $cart_points = $this->points_manager->calculate_cart_points();
            
            // Clean, minimal display to avoid conflicts with Points Manager
            echo '<div class="points-payment-gateway-info">';
            echo '<h4>' . __('Points Payment Summary', 'herbal-mix-creator2') . '</h4>';
            
            echo '<table class="points-payment-table">';
            echo '<tr>';
            echo '<td><strong>' . __('Your balance:', 'herbal-mix-creator2') . '</strong></td>';
            echo '<td>' . number_format($user_points, 0) . ' ' . __('points', 'herbal-mix-creator2') . '</td>';
            echo '</tr>';
            
            if ($cart_points['cost'] > 0) {
                echo '<tr>';
                echo '<td><strong>' . __('Required for this order:', 'herbal-mix-creator2') . '</strong></td>';
                echo '<td>' . number_format($cart_points['cost'], 0) . ' ' . __('points', 'herbal-mix-creator2') . '</td>';
                echo '</tr>';
                
                if ($user_points >= $cart_points['cost']) {
                    echo '<tr style="color: green;">';
                    echo '<td><strong>' . __('After payment:', 'herbal-mix-creator2') . '</strong></td>';
                    echo '<td>' . number_format($user_points - $cart_points['cost'], 0) . ' ' . __('points', 'herbal-mix-creator2') . '</td>';
                    echo '</tr>';
                } else {
                    echo '<tr style="color: red;">';
                    echo '<td><strong>' . __('Insufficient balance:', 'herbal-mix-creator2') . '</strong></td>';
                    echo '<td>' . sprintf(__('Need %s more points', 'herbal-mix-creator2'), 
                                       number_format($cart_points['cost'] - $user_points, 0)) . '</td>';
                    echo '</tr>';
                }
            }
            
            if ($cart_points['earned'] > 0) {
                echo '<tr style="color: green;">';
                echo '<td><strong>' . __('You will earn:', 'herbal-mix-creator2') . '</strong></td>';
                echo '<td>' . number_format($cart_points['earned'], 0) . ' ' . __('points', 'herbal-mix-creator2') . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            echo '</div>';
            
            // Inline styles to avoid external dependencies
            ?>
            <style>
            .points-payment-gateway-info {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 6px;
                padding: 15px;
                margin: 15px 0;
            }
            
            .points-payment-gateway-info h4 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #495057;
                font-size: 16px;
            }
            
            .points-payment-table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0;
            }
            
            .points-payment-table td {
                padding: 8px 12px;
                border: 1px solid #dee2e6;
                font-size: 14px;
                text-align: left;
            }
            
            .points-payment-table td:first-child {
                font-weight: 600;
                background: #f8f9fa;
                width: 50%;
            }
            
            .points-payment-table td:last-child {
                text-align: right;
            }
            </style>
            <?php
        }
        
        /**
         * Validate payment fields before processing
         */
        public function validate_fields() {
            if (!is_user_logged_in()) {
                wc_add_notice(__('You must be logged in to pay with points.', 'herbal-mix-creator2'), 'error');
                return false;
            }
            
            if (!$this->points_manager) {
                wc_add_notice(__('Points system not available.', 'herbal-mix-creator2'), 'error');
                return false;
            }
            
            $user_id = get_current_user_id();
            $user_points = $this->points_manager->get_user_points($user_id);
            $cart_points = $this->points_manager->calculate_cart_points();
            
            if ($cart_points['cost'] <= 0) {
                wc_add_notice(__('No items in cart can be paid with points.', 'herbal-mix-creator2'), 'error');
                return false;
            }
            
            if ($user_points < $cart_points['cost']) {
                wc_add_notice(
                    sprintf(__('Insufficient points. You need %s points but only have %s.', 'herbal-mix-creator2'), 
                           number_format($cart_points['cost'], 0), 
                           number_format($user_points, 0)), 
                    'error'
                );
                return false;
            }
            
            return true;
        }
        
        /**
         * Process payment - delegates to Points Manager
         */
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $user_id = $order->get_user_id();
            
            if (!$user_id) {
                return array(
                    'result' => 'failure',
                    'messages' => __('User not found.', 'herbal-mix-creator2')
                );
            }
            
            if (!$this->points_manager) {
                return array(
                    'result' => 'failure',
                    'messages' => __('Points system not available.', 'herbal-mix-creator2')
                );
            }
            
            $order_points = $this->points_manager->calculate_order_points($order);
            
            if ($order_points['cost'] <= 0) {
                return array(
                    'result' => 'failure',
                    'messages' => __('No points required for this order.', 'herbal-mix-creator2')
                );
            }
            
            // Subtract points for payment using Points Manager
            $payment_success = $this->points_manager->subtract_points(
                $user_id, 
                $order_points['cost'], 
                'points_payment', 
                $order_id,
                sprintf(__('Payment for order #%s', 'herbal-mix-creator2'), $order_id)
            );
            
            if (!$payment_success) {
                return array(
                    'result' => 'failure',
                    'messages' => __('Insufficient points for payment.', 'herbal-mix-creator2')
                );
            }
            
            // Award earned points using Points Manager
            if ($order_points['earned'] > 0) {
                $this->points_manager->add_points(
                    $user_id,
                    $order_points['earned'],
                    'purchase',
                    $order_id,
                    sprintf(__('Points earned from order #%s', 'herbal-mix-creator2'), $order_id)
                );
            }
            
            // Mark order as paid
            $order->payment_complete();
            $order->update_status('processing', __('Payment completed with points.', 'herbal-mix-creator2'));
            
            // Add order note
            $order->add_order_note(
                sprintf(__('Paid with %s points. %s points earned.', 'herbal-mix-creator2'), 
                       number_format($order_points['cost'], 0),
                       number_format($order_points['earned'], 0))
            );
            
            // Reduce stock
            wc_reduce_stock_levels($order_id);
            
            // Empty cart
            WC()->cart->empty_cart();
            
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        
        /**
         * Thank you page display
         */
        public function thankyou_page($order_id) {
            $order = wc_get_order($order_id);
            
            if ($order->get_payment_method() === $this->id && $this->points_manager) {
                $order_points = $this->points_manager->calculate_order_points($order);
                $user_id = $order->get_user_id();
                $current_balance = $this->points_manager->get_user_points($user_id);
                
                echo '<div class="woocommerce-message herbal-payment-success">';
                echo '<h3>🎉 ' . __('Payment Successful!', 'herbal-mix-creator2') . '</h3>';
                
                if ($order_points['cost'] > 0) {
                    echo '<p>✅ <strong>' . __('Paid:', 'herbal-mix-creator2') . '</strong> ' . 
                         number_format($order_points['cost'], 0) . ' ' . __('points', 'herbal-mix-creator2') . '</p>';
                }
                
                if ($order_points['earned'] > 0) {
                    echo '<p>🎁 <strong>' . __('Earned:', 'herbal-mix-creator2') . '</strong> ' . 
                         number_format($order_points['earned'], 0) . ' ' . __('points', 'herbal-mix-creator2') . '</p>';
                }
                
                echo '<p>👤 <strong>' . __('Your new balance:', 'herbal-mix-creator2') . '</strong> ' . 
                     number_format($current_balance, 0) . ' ' . __('points', 'herbal-mix-creator2') . '</p>';
                
                echo '</div>';
                
                // Inline styles for thank you page
                ?>
                <style>
                .herbal-payment-success {
                    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
                    border: 1px solid #c3e6cb !important;
                    color: #155724 !important;
                    padding: 20px !important;
                    margin: 20px 0 !important;
                    border-radius: 8px !important;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                
                .herbal-payment-success h3 {
                    margin-top: 0;
                    margin-bottom: 15px;
                    color: #155724 !important;
                }
                
                .herbal-payment-success p {
                    margin-bottom: 10px;
                    font-size: 14px;
                }
                
                .herbal-payment-success p:last-child {
                    margin-bottom: 0;
                }
                </style>
                <?php
            }
        }
        
        /**
         * Admin options page
         */
        public function admin_options() {
            echo '<h2>' . esc_html($this->get_method_title()) . '</h2>';
            echo '<p>' . esc_html($this->get_method_description()) . '</p>';
            
            // Show current status
            if ($this->points_manager) {
                echo '<div class="notice notice-success inline"><p>';
                echo '<strong>' . __('Status:', 'herbal-mix-creator2') . '</strong> ';
                echo __('Points system is active and ready.', 'herbal-mix-creator2');
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-warning inline"><p>';
                echo '<strong>' . __('Warning:', 'herbal-mix-creator2') . '</strong> ';
                echo __('Points Manager not found. Some features may not work properly.', 'herbal-mix-creator2');
                echo '</p></div>';
            }
            
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }
        
        /**
         * Get icon for gateway (optional)
         */
        public function get_icon() {
            $icon_html = '';
            $icon = $this->get_option('icon');
            
            if ($icon) {
                $icon_html = '<img src="' . esc_attr($icon) . '" alt="' . esc_attr($this->get_title()) . '" />';
            } else {
                // Default points icon
                $icon_html = '<span style="font-size: 16px;">🎯</span>';
            }
            
            return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
        }
    }

    return true;
}