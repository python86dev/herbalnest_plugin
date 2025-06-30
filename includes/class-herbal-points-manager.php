<?php
/**
 * Herbal Mix Points Manager - COMPLETE COMPATIBLE VERSION
 * Fully integrates with existing wtyczka structure without conflicts
 * Includes ALL necessary functions and AJAX handlers
 * 
 * File: includes/class-herbal-mix-points-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Herbal_Mix_Points_Manager {
    
    private static $instance = null;
    private $points_displayed = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Use different priorities to avoid conflicts with existing code
        
        // Frontend hooks - AFTER existing hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 15); // After existing at 10
        
        // Points display hooks - DIFFERENT priority from existing
        add_action('woocommerce_cart_totals_after_order_total', array($this, 'display_cart_points'), 20); // After existing
        add_action('woocommerce_review_order_after_order_total', array($this, 'display_checkout_points'), 20); // After existing
        
        // Product page points display - DIFFERENT priority
        add_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 30); // After existing at 25
        
        // WooCommerce integration hooks
        add_action('woocommerce_add_to_cart', array($this, 'cart_updated'), 10, 6);
        add_action('woocommerce_cart_item_removed', array($this, 'cart_item_removed'));
        add_action('woocommerce_cart_item_restored', array($this, 'cart_item_restored'));
        
        // Order completion hooks (preserved)
        add_action('woocommerce_order_status_completed', array($this, 'award_points_on_order_completion'));
        add_action('woocommerce_payment_complete', array($this, 'award_points_on_payment'));
        
        // NEW AJAX actions with unique names to avoid conflicts
        add_action('wp_ajax_herbal_points_calculate_cart', array($this, 'ajax_calculate_cart_points'));
        add_action('wp_ajax_herbal_points_refresh_balance', array($this, 'ajax_refresh_user_points'));
        
        // MISSING: Add support for existing AJAX action from mix-creator.js
        add_action('wp_ajax_add_to_basket', array($this, 'ajax_add_to_basket_integration'));
        add_action('wp_ajax_nopriv_add_to_basket', array($this, 'ajax_add_to_basket_integration'));
        
        // Styles
        add_action('wp_head', array($this, 'add_points_styles'), 20); // After other styles
        
        // Initialize helper functions
        $this->init_helper_functions();
    }
    
    /**
     * MISSING: Initialize helper functions for backward compatibility
     */
    private function init_helper_functions() {
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
        
        if (!function_exists('herbal_add_user_points')) {
            function herbal_add_user_points($user_id, $points, $transaction_type = 'manual', $reference_id = null) {
                if (!$user_id || $points <= 0) {
                    return false;
                }
                
                $points_manager = Herbal_Mix_Points_Manager::get_instance();
                return $points_manager->add_points($user_id, $points, $transaction_type, $reference_id);
            }
        }
        
        if (!function_exists('herbal_subtract_user_points')) {
            function herbal_subtract_user_points($user_id, $points, $transaction_type = 'manual', $reference_id = null) {
                if (!$user_id || $points <= 0) {
                    return false;
                }
                
                $points_manager = Herbal_Mix_Points_Manager::get_instance();
                return $points_manager->subtract_points($user_id, $points, $transaction_type, $reference_id);
            }
        }
        
        if (!function_exists('herbal_user_has_enough_points')) {
            function herbal_user_has_enough_points($user_id, $required_points) {
                $user_points = herbal_get_user_points($user_id);
                return $user_points >= $required_points;
            }
        }
    }
    
    /**
     * MISSING: Integration with existing add_to_basket AJAX
     */
    public function ajax_add_to_basket_integration() {
        // This is called when mix-creator.js sends add_to_basket action
        // We need to calculate points and potentially update displays
        
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
            return; // Let existing handler deal with this
        }
        
        // Don't interfere with existing functionality, just prepare for points display
        // The actual add to basket is handled by existing code
        
        // Parse mix data to calculate points if needed
        if (isset($_POST['mix_data'])) {
            $mix_data = json_decode(stripslashes($_POST['mix_data']), true);
            
            if (is_array($mix_data)) {
                // Store calculated points in session for later display
                $total_cost = 0;
                $total_earned = 0;
                
                // Calculate from packaging
                if (isset($mix_data['packaging'])) {
                    $packaging = $mix_data['packaging'];
                    $total_cost += floatval($packaging['price_point'] ?? 0);
                    $total_earned += floatval($packaging['point_earned'] ?? 0);
                }
                
                // Calculate from ingredients
                if (isset($mix_data['ingredients']) && is_array($mix_data['ingredients'])) {
                    foreach ($mix_data['ingredients'] as $ingredient) {
                        $weight = floatval($ingredient['weight'] ?? 0);
                        $cost_per_gram = floatval($ingredient['price_point'] ?? 0);
                        $earned_per_gram = floatval($ingredient['point_earned'] ?? 0);
                        
                        $total_cost += $cost_per_gram * $weight;
                        $total_earned += $earned_per_gram * $weight;
                    }
                }
                
                // Store in session for cart display
                if (isset($_SESSION)) {
                    $_SESSION['herbal_last_added_points'] = array(
                        'cost' => $total_cost,
                        'earned' => $total_earned
                    );
                }
            }
        }
        
        // Don't send JSON response - let existing handler do that
        return;
    }
    
    /**
     * Cart updated hook
     */
    public function cart_updated($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        // Reset points display flag when cart changes
        $this->points_displayed = false;
    }
    
    /**
     * Cart item removed hook
     */
    public function cart_item_removed($cart_item_key) {
        $this->points_displayed = false;
    }
    
    /**
     * Cart item restored hook
     */
    public function cart_item_restored($cart_item_key) {
        $this->points_displayed = false;
    }
    
    /**
     * Enqueue assets only where needed - COMPATIBLE with existing
     */
    public function enqueue_assets() {
        if (!$this->should_load_assets()) {
            return;
        }
        
        // Only load our assets if not already loaded by existing system
        if (!wp_script_is('herbal-points-js', 'enqueued')) {
            
            // Check if herbal-points.css exists, otherwise skip
            $css_file = HERBAL_MIX_PLUGIN_PATH . 'assets/css/herbal-points.css';
            if (file_exists($css_file)) {
                wp_enqueue_style(
                    'herbal-points-css',
                    HERBAL_MIX_PLUGIN_URL . 'assets/css/herbal-points.css',
                    array(),
                    HERBAL_MIX_VERSION
                );
            }
            
            // Check if herbal-points.js exists, otherwise skip
            $js_file = HERBAL_MIX_PLUGIN_PATH . 'assets/js/herbal-points.js';
            if (file_exists($js_file)) {
                wp_enqueue_script(
                    'herbal-points-js',
                    HERBAL_MIX_PLUGIN_URL . 'assets/js/herbal-points.js',
                    array('jquery'),
                    HERBAL_MIX_VERSION,
                    true
                );
                
                // Use DIFFERENT object name to avoid conflicts with herbalMixData
                wp_localize_script('herbal-points-js', 'herbalPointsData', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('herbal_points_nonce'), // Different nonce name
                    'strings' => array(
                        'loading' => __('Loading...', 'herbal-mix-creator2'),
                        'error' => __('An error occurred.', 'herbal-mix-creator2'),
                        'insufficient_points' => __('Insufficient points for this purchase.', 'herbal-mix-creator2'),
                        'points_updated' => __('Points balance updated.', 'herbal-mix-creator2')
                    )
                ));
            }
        }
    }
    
    /**
     * Check if assets should be loaded - COMPATIBLE
     */
    private function should_load_assets() {
        return is_cart() || is_checkout() || is_product() || is_account_page();
    }
    
    /**
     * Display points information in cart - CHECKS for existing display
     */
    public function display_cart_points() {
        // Check if points were already displayed by existing code
        if ($this->points_displayed || did_action('herbal_points_cart_displayed')) {
            return;
        }
        
        $this->render_points_totals_row();
        $this->points_displayed = true;
        do_action('herbal_points_cart_displayed'); // Signal that we displayed
    }
    
    /**
     * Display points information in checkout - CHECKS for existing display
     */
    public function display_checkout_points() {
        // Check if points were already displayed by existing code
        if ($this->points_displayed || did_action('herbal_points_checkout_displayed')) {
            return;
        }
        
        $this->render_points_totals_row();
        $this->points_displayed = true;
        do_action('herbal_points_checkout_displayed'); // Signal that we displayed
    }
    
    /**
     * Unified points display for cart and checkout - ENHANCED
     */
    private function render_points_totals_row() {
        if (!is_user_logged_in() || !WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $points_data = $this->calculate_cart_points();
        
        // Only show if there are any points involved
        if ($points_data['cost'] <= 0 && $points_data['earned'] <= 0) {
            return;
        }

        $user_points = $this->get_user_points(get_current_user_id());
        
        echo '<tr class="herbal-points-total-row herbal-points-manager-display">';
        echo '<th class="herbal-points-header">';
        echo '<span class="points-icon">🎯</span> ';
        echo __('Points Summary', 'herbal-mix-creator2');
        echo '</th>';
        echo '<td class="herbal-points-content">';
        
        // Current balance
        echo '<div class="points-balance">';
        echo '<strong>' . __('Your balance:', 'herbal-mix-creator2') . '</strong> ';
        echo '<span class="balance-amount">' . number_format($user_points, 0) . ' pts</span>';
        if ($user_points > 0) {
            echo ' <button type="button" class="refresh-points" title="' . __('Refresh balance', 'herbal-mix-creator2') . '">🔄</button>';
        }
        echo '</div>';
        
        // Alternative cost
        if ($points_data['cost'] > 0) {
            $can_afford = $user_points >= $points_data['cost'];
            $status_class = $can_afford ? 'affordable' : 'insufficient';
            
            echo '<div class="points-cost ' . $status_class . '">';
            echo '<span class="cost-icon">💰</span> ';
            echo '<strong>' . __('Alternative cost:', 'herbal-mix-creator2') . '</strong> ';
            echo '<span class="cost-amount">' . number_format($points_data['cost'], 0) . ' pts</span>';
            
            if ($can_afford) {
                echo ' <span class="status-indicator available">✓ ' . __('Available', 'herbal-mix-creator2') . '</span>';
            } else {
                $needed = $points_data['cost'] - $user_points;
                echo ' <span class="status-indicator insufficient">⚠ ' . 
                     sprintf(__('Need %s more', 'herbal-mix-creator2'), number_format($needed, 0)) . '</span>';
            }
            echo '</div>';
        }
        
        // Points to be earned
        if ($points_data['earned'] > 0) {
            echo '<div class="points-earned">';
            echo '<span class="earn-icon">🎁</span> ';
            echo '<strong>' . __('You will earn:', 'herbal-mix-creator2') . '</strong> ';
            echo '<span class="earn-amount">' . number_format($points_data['earned'], 0) . ' pts</span>';
            echo '</div>';
        }
        
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Display points information on product page - COMPATIBLE
     */
    public function display_product_points() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        // Check if product points info was already displayed
        if (did_action('herbal_product_points_displayed')) {
            return;
        }
        
        $product_id = $product->get_id();
        $points_cost = $this->get_product_points_cost($product_id);
        $points_earned = $this->get_product_points_earned($product_id);
        
        if ($points_cost <= 0 && $points_earned <= 0) {
            return;
        }
        
        echo '<div class="herbal-product-points-info herbal-points-manager-display">';
        echo '<h4><span class="points-icon">🎯</span> ' . __('Points Information', 'herbal-mix-creator2') . '</h4>';
        
        if ($points_cost > 0) {
            echo '<div class="product-points-cost">';
            echo '<span class="cost-icon">💰</span> ';
            echo sprintf(__('Alternative payment: %s points', 'herbal-mix-creator2'), 
                        '<strong>' . number_format($points_cost, 0) . '</strong>');
            echo '</div>';
        }
        
        if ($points_earned > 0) {
            echo '<div class="product-points-earned">';
            echo '<span class="earn-icon">🎁</span> ';
            echo sprintf(__('Earn: %s points with purchase', 'herbal-mix-creator2'), 
                        '<strong>' . number_format($points_earned, 0) . '</strong>');
            echo '</div>';
        }
        
        if (is_user_logged_in()) {
            $user_points = $this->get_user_points(get_current_user_id());
            echo '<div class="product-user-balance">';
            echo '<span class="balance-icon">👤</span> ';
            echo sprintf(__('Your balance: %s points', 'herbal-mix-creator2'), 
                        '<strong>' . number_format($user_points, 0) . '</strong>');
            
            if ($points_cost > 0) {
                if ($user_points >= $points_cost) {
                    echo ' <span class="status-available">✓ ' . __('You can buy this with points!', 'herbal-mix-creator2') . '</span>';
                } else {
                    echo ' <span class="status-insufficient">⚠ ' . 
                         sprintf(__('Need %s more points', 'herbal-mix-creator2'), 
                                number_format($points_cost - $user_points, 0)) . '</span>';
                }
            }
            echo '</div>';
        }
        
        echo '</div>';
        
        do_action('herbal_product_points_displayed'); // Signal that we displayed
    }
    
    /**
     * AJAX: Calculate cart points - NEW action name
     */
    public function ajax_calculate_cart_points() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_points_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        $points_data = $this->calculate_cart_points();
        $user_points = 0;
        
        if (is_user_logged_in()) {
            $user_points = $this->get_user_points(get_current_user_id());
        }
        
        $response = array(
            'cost' => $points_data['cost'],
            'earned' => $points_data['earned'],
            'balance' => $user_points,
            'can_afford' => $user_points >= $points_data['cost'],
            'needed' => max(0, $points_data['cost'] - $user_points)
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX: Refresh user points - NEW action name
     */
    public function ajax_refresh_user_points() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_points_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        $user_points = $this->get_user_points(get_current_user_id());
        
        wp_send_json_success(array(
            'balance' => $user_points
        ));
    }
    
    /**
     * Calculate total points for current cart - USING EXISTING DB COLUMNS
     */
    public function calculate_cart_points() {
        $total_cost = 0;
        $total_earned = 0;
        
        if (!WC()->cart || WC()->cart->is_empty()) {
            return array('cost' => 0, 'earned' => 0);
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            
            // Use EXACT column names from existing database
            $points_cost = $this->get_product_points_cost($product_id);
            $points_earned = $this->get_product_points_earned($product_id);
            
            $total_cost += $points_cost * $quantity;
            $total_earned += $points_earned * $quantity;
        }
        
        return array(
            'cost' => $total_cost,
            'earned' => $total_earned
        );
    }
    
    /**
     * Calculate points for a specific order
     */
    public function calculate_order_points($order) {
        $total_cost = 0;
        $total_earned = 0;
        
        if (!$order) {
            return array('cost' => 0, 'earned' => 0);
        }
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            
            $points_cost = $this->get_product_points_cost($product_id);
            $points_earned = $this->get_product_points_earned($product_id);
            
            $total_cost += $points_cost * $quantity;
            $total_earned += $points_earned * $quantity;
        }
        
        return array(
            'cost' => $total_cost,
            'earned' => $total_earned
        );
    }
    
    /**
     * Get user's current points balance - USING EXISTING META KEY
     */
    public function get_user_points($user_id) {
        if (!$user_id) {
            return 0;
        }
        
        // Use the EXACT meta key from existing code
        $points = get_user_meta($user_id, 'reward_points', true);
        return floatval($points);
    }
    
    /**
     * Get product points cost - USING EXISTING DB COLUMN NAMES
     */
    public function get_product_points_cost($product_id) {
        // Use EXACT column name from database schema
        return floatval(get_post_meta($product_id, 'price_point', true));
    }
    
    /**
     * Get product points earned - USING EXISTING DB COLUMN NAMES  
     */
    public function get_product_points_earned($product_id) {
        // Use EXACT column name from database schema
        return floatval(get_post_meta($product_id, 'point_earned', true));
    }
    
    /**
     * Award points when order is completed
     */
    public function award_points_on_order_completion($order_id) {
        $this->process_order_points($order_id, 'order_completion');
    }
    
    /**
     * Award points when payment is complete
     */
    public function award_points_on_payment($order_id) {
        $this->process_order_points($order_id, 'payment_completion');
    }
    
    /**
     * Process points for order
     */
    private function process_order_points($order_id, $trigger) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_meta('_herbal_points_awarded')) {
            return;
        }
        
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }
        
        $points_data = $this->calculate_order_points($order);
        
        if ($points_data['earned'] > 0) {
            $this->add_points($user_id, $points_data['earned'], 'purchase', $order_id, 
                            sprintf(__('Points earned from order #%s', 'herbal-mix-creator2'), $order_id));
            
            $order->update_meta_data('_herbal_points_awarded', true);
            $order->save();
        }
    }
    
    /**
     * Add points to user account
     */
    public function add_points($user_id, $points, $type = 'manual', $reference_id = null, $notes = '') {
        if ($points <= 0) {
            return false;
        }
        
        $current_points = $this->get_user_points($user_id);
        $new_points = $current_points + $points;
        
        // Update user meta using EXISTING meta key
        update_user_meta($user_id, 'reward_points', $new_points);
        
        // Record transaction in EXISTING table
        $this->record_transaction($user_id, $points, $type, $reference_id, $current_points, $new_points, $notes);
        
        // Hook for other plugins/themes
        do_action('herbal_points_added', $user_id, $points, $new_points, $type);
        
        return true;
    }
    
    /**
     * Subtract points from user account
     */
    public function subtract_points($user_id, $points, $type = 'points_payment', $reference_id = null, $notes = '') {
        if ($points <= 0) {
            return false;
        }
        
        $current_points = $this->get_user_points($user_id);
        
        if ($current_points < $points) {
            return false;
        }
        
        $new_points = $current_points - $points;
        
        // Update user meta using EXISTING meta key
        update_user_meta($user_id, 'reward_points', $new_points);
        
        // Record transaction in EXISTING table
        $this->record_transaction($user_id, -$points, $type, $reference_id, $current_points, $new_points, $notes);
        
        // Hook for other plugins/themes
        do_action('herbal_points_subtracted', $user_id, $points, $new_points, $type);
        
        return true;
    }
    
    /**
     * Record points transaction in database - USING EXISTING TABLE
     */
    private function record_transaction($user_id, $points_change, $type, $reference_id, $points_before, $points_after, $notes) {
        global $wpdb;
        
        // Use EXISTING table name from database schema
        $wpdb->insert(
            $wpdb->prefix . 'herbal_points_history',
            array(
                'user_id' => $user_id,
                'points_change' => $points_change,
                'transaction_type' => $type,
                'reference_id' => $reference_id,
                'reference_type' => $this->get_reference_type($type),
                'points_before' => $points_before,
                'points_after' => $points_after,
                'notes' => $notes,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%f', '%s', '%d', '%s', '%f', '%f', '%s', '%s')
        );
    }
    
    /**
     * Get reference type based on transaction type
     */
    private function get_reference_type($type) {
        $types = array(
            'purchase' => 'order',
            'order_completion' => 'order',
            'payment_completion' => 'order',
            'points_payment' => 'order',
            'manual' => 'admin',
            'admin_adjustment' => 'admin'
        );
        
        return isset($types[$type]) ? $types[$type] : 'other';
    }
    
    /**
     * Add CSS styles for points display - COMPATIBLE
     */
    public function add_points_styles() {
        if (!$this->should_load_assets()) {
            return;
        }
        ?>
        <style>
        /* Only style our specific displays to avoid conflicts */
        .herbal-points-manager-display.herbal-points-total-row th,
        .herbal-points-manager-display.herbal-points-total-row td {
            border-top: 2px solid #ddd !important;
            padding: 15px 10px !important;
            font-size: 14px;
        }
        
        .herbal-points-manager-display .herbal-points-header {
            font-weight: 600 !important;
            color: #2c3e50;
        }
        
        .herbal-points-manager-display .herbal-points-content > div {
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .herbal-points-manager-display .herbal-points-content > div:last-child {
            margin-bottom: 0;
        }
        
        .herbal-points-manager-display .points-balance .balance-amount {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .herbal-points-manager-display .refresh-points {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            margin-left: 5px;
            opacity: 0.7;
        }
        
        .herbal-points-manager-display .refresh-points:hover {
            opacity: 1;
        }
        
        .herbal-points-manager-display .points-cost.affordable .cost-amount {
            color: #28a745;
        }
        
        .herbal-points-manager-display .points-cost.insufficient .cost-amount {
            color: #dc3545;
        }
        
        .herbal-points-manager-display .points-earned .earn-amount {
            color: #28a745;
            font-weight: 600;
        }
        
        .herbal-points-manager-display .status-indicator.available {
            color: #28a745;
            font-size: 12px;
        }
        
        .herbal-points-manager-display .status-indicator.insufficient {
            color: #dc3545;
            font-size: 12px;
        }
        
        .herbal-points-manager-display.herbal-product-points-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .herbal-points-manager-display.herbal-product-points-info h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 18px;
        }
        
        .herbal-points-manager-display.herbal-product-points-info > div {
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .herbal-points-manager-display .status-available {
            color: #28a745;
            font-weight: 600;
        }
        
        .herbal-points-manager-display .status-insufficient {
            color: #dc3545;
            font-weight: 600;
        }
        
        .points-icon, .cost-icon, .earn-icon, .balance-icon {
            margin-right: 5px;
        }
        </style>
        <?php
    }
}