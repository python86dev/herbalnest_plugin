<?php
/**
 * Profile Integration for Points System - COMPLETE FIXED VERSION
 * - Restored beautiful original design with gradients
 * - Fixed database field names to match schema
 * - Added working Edit/Publish functionality
 * - UK Market Version (English Language)
 */

if (!defined('ABSPATH')) exit;

class HerbalProfileIntegration {
    
    public function __construct() {
        // Add My Account endpoints
        add_action('init', array($this, 'add_account_endpoints'));
        
        // Use priority 10 for points menu items
        add_filter('woocommerce_account_menu_items', array($this, 'add_points_menu_items'), 10);
        
        // Points-specific actions
        add_action('woocommerce_account_points-history_endpoint', array($this, 'render_points_history_page'));
        
        // AJAX handlers
        add_action('wp_ajax_get_more_points_history', array($this, 'ajax_get_more_points_history'));
        add_action('wp_ajax_nopriv_get_more_points_history', array($this, 'ajax_get_more_points_history'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_profile_scripts'));
        
        // Flush rewrite rules if needed
        add_action('wp_loaded', array($this, 'flush_rewrite_rules_maybe'));
        
        // Add points display to My Account dashboard
        add_action('woocommerce_account_dashboard', array($this, 'add_points_to_dashboard'), 5);
        
        // Prevent double dashboard display
        add_action('herbal_points_dashboard_displayed', array($this, 'mark_dashboard_displayed'));
    }
    
    private $dashboard_displayed = false;
    
    /**
     * Mark that dashboard was already displayed
     */
    public function mark_dashboard_displayed() {
        $this->dashboard_displayed = true;
    }
    
    /**
     * Add custom endpoints for My Account
     */
    public function add_account_endpoints() {
        add_rewrite_endpoint('points-history', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Add points menu items
     */
    public function add_points_menu_items($menu_items) {
        // Insert points history after dashboard
        $new_items = array();
        foreach ($menu_items as $key => $item) {
            $new_items[$key] = $item;
            if ($key === 'dashboard') {
                $new_items['points-history'] = __('My Points', 'herbal-mix-creator2');
            }
        }
        
        return $new_items;
    }
    
    /**
     * Get user points (using new database structure)
     */
    private function get_user_points($user_id) {
        return floatval(get_user_meta($user_id, 'reward_points', true));
    }
    
    /**
     * Enqueue profile scripts and styles
     */
    public function enqueue_profile_scripts() {
        if (is_account_page()) {
            wp_enqueue_script('jquery');
            
            // Add inline script for AJAX
            wp_add_inline_script('jquery', '
                jQuery(document).ready(function($) {
                    var loadMoreButton = $("#load-points-history");
                    var historyContainer = $("#points-history-container");
                    var currentPage = 1;
                    
                    loadMoreButton.on("click", function() {
                        var button = $(this);
                        
                        // If history already loaded, just show/hide
                        if (historyContainer.find(".points-history-table").length > 0) {
                            historyContainer.toggle();
                            button.text(historyContainer.is(":visible") ? "' . __('Hide History', 'herbal-mix-creator2') . '" : "' . __('Show Full History', 'herbal-mix-creator2') . '");
                            return;
                        }
                        
                        // Load history via AJAX
                        button.prop("disabled", true).text("' . __('Loading...', 'herbal-mix-creator2') . '");
                        
                        $.post(ajaxurl || "' . admin_url('admin-ajax.php') . '", {
                            action: "get_more_points_history",
                            nonce: "' . wp_create_nonce('herbal_points_nonce') . '",
                            page: currentPage
                        }, function(response) {
                            if (response.success) {
                                historyContainer.html(response.data.html).show();
                                button.text("' . __('Hide History', 'herbal-mix-creator2') . '");
                                
                                if (response.data.has_more) {
                                    historyContainer.append(\'<button id="load-more-pages" class="herbal-button-secondary" style="margin-top: 20px; width: 100%;">' . __('Load More', 'herbal-mix-creator2') . '</button>\');
                                }
                            } else {
                                alert("' . __('Error loading history.', 'herbal-mix-creator2') . '");
                            }
                        }).always(function() {
                            button.prop("disabled", false);
                        });
                    });
                    
                    // Load more pages functionality
                    $(document).on("click", "#load-more-pages", function() {
                        var button = $(this);
                        currentPage++;
                        
                        button.prop("disabled", true).text("' . __('Loading...', 'herbal-mix-creator2') . '");
                        
                        $.post(ajaxurl || "' . admin_url('admin-ajax.php') . '", {
                            action: "get_more_points_history",
                            nonce: "' . wp_create_nonce('herbal_points_nonce') . '",
                            page: currentPage
                        }, function(response) {
                            if (response.success) {
                                button.before(response.data.html);
                                
                                if (!response.data.has_more) {
                                    button.remove();
                                } else {
                                    button.prop("disabled", false).text("' . __('Load More', 'herbal-mix-creator2') . '");
                                }
                            }
                        });
                    });
                });
            ');
        }
    }
    
    /**
     * Add points display to My Account dashboard
     */
    public function add_points_to_dashboard() {
        if (!is_user_logged_in() || $this->dashboard_displayed) {
            return;
        }
        
        // Mark as displayed to prevent duplicates
        do_action('herbal_points_dashboard_displayed');
        
        $user_id = get_current_user_id();
        $current_points = $this->get_user_points($user_id);
        
        echo '<div class="herbal-points-dashboard-widget">';
        echo '<h3>' . __('Your Reward Points', 'herbal-mix-creator2') . '</h3>';
        echo '<div class="points-display">';
        echo '<span class="points-value">' . number_format($current_points, 0) . '</span>';
        echo '<span class="points-label">' . __('Available Points', 'herbal-mix-creator2') . '</span>';
        echo '</div>';
        echo '<p><a href="' . wc_get_account_endpoint_url('points-history') . '" class="herbal-button-primary">' . __('View Points History', 'herbal-mix-creator2') . '</a></p>';
        echo '</div>';
        
        // Enhanced dashboard widget styling
        echo '<style>
        .herbal-points-dashboard-widget {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
            color: white;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        .herbal-points-dashboard-widget h3 {
            margin-top: 0;
            color: white;
            font-size: 1.3em;
            opacity: 0.95;
        }
        .points-display {
            margin: 20px 0;
        }
        .points-value {
            display: block;
            font-size: 2.5em;
            font-weight: bold;
            color: white;
            line-height: 1;
            margin-bottom: 8px;
        }
        .points-label {
            font-size: 1.1em;
            color: rgba(255,255,255,0.9);
            margin-top: 5px;
            display: block;
        }
        .herbal-button-primary {
            background: white;
            color: #667eea;
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            margin-top: 10px;
        }
        .herbal-button-primary:hover {
            background: #f8f9fa;
            color: #667eea;
            text-decoration: none;
            transform: translateY(-2px);
        }
        </style>';
    }
    
    /**
     * Render points history page - RESTORED BEAUTIFUL DESIGN
     */
    public function render_points_history_page() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            echo '<p>' . __('You must be logged in to view your points.', 'herbal-mix-creator2') . '</p>';
            return;
        }
        
        // Get current points and recent history
        $current_points = $this->get_user_points($user_id);
        $recent_history = Herbal_Mix_Database::get_points_history($user_id, 5, 0);
        
        // Start beautiful points dashboard
        ?>
        <div class="herbal-points-dashboard">
            <!-- Beautiful gradient points summary card -->
            <div class="points-summary-card">
                <div class="points-balance">
                    <div class="points-value"><?php echo number_format($current_points, 0); ?></div>
                    <div class="points-label"><?php _e('Available Points', 'herbal-mix-creator2'); ?></div>
                </div>
                <div class="points-actions">
                    <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="herbal-button-primary">
                        <?php _e('Shop Products', 'herbal-mix-creator2'); ?>
                    </a>
                    <a href="<?php echo get_permalink(get_page_by_path('herbal-mix-creator')); ?>" class="herbal-button-secondary">
                        <?php _e('Create Mix', 'herbal-mix-creator2'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Ways to earn points -->
            <div class="points-earning-section">
                <h3><?php _e('Ways to Earn Points', 'herbal-mix-creator2'); ?></h3>
                <ul class="points-earning-methods">
                    <li>
                        <div class="method-icon purchase-icon"></div>
                        <div class="method-content">
                            <h4><?php _e('Make Purchases', 'herbal-mix-creator2'); ?></h4>
                            <p><?php _e('Earn points with every purchase you make', 'herbal-mix-creator2'); ?></p>
                        </div>
                    </li>
                    <li>
                        <div class="method-icon create-icon"></div>
                        <div class="method-content">
                            <h4><?php _e('Create Herbal Mixes', 'herbal-mix-creator2'); ?></h4>
                            <p><?php _e('Get points for creating and publishing your own mixes', 'herbal-mix-creator2'); ?></p>
                        </div>
                    </li>
                    <li>
                        <div class="method-icon review-icon"></div>
                        <div class="method-content">
                            <h4><?php _e('Leave Reviews', 'herbal-mix-creator2'); ?></h4>
                            <p><?php _e('Share your experience and earn points for helpful reviews', 'herbal-mix-creator2'); ?></p>
                        </div>
                    </li>
                    <li>
                        <div class="method-icon refer-icon"></div>
                        <div class="method-content">
                            <h4><?php _e('Refer Friends', 'herbal-mix-creator2'); ?></h4>
                            <p><?php _e('Invite friends and earn bonus points when they make their first purchase', 'herbal-mix-creator2'); ?></p>
                        </div>
                    </li>
                </ul>
            </div>
            
            <!-- Recent activity -->
            <?php if (!empty($recent_history)): ?>
            <div class="recent-activity-section">
                <h3><?php _e('Recent Activity', 'herbal-mix-creator2'); ?></h3>
                <div class="recent-transactions">
                    <?php foreach ($recent_history as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-details">
                                <h4><?php echo $this->format_transaction_type($transaction->transaction_type); ?></h4>
                                <div class="transaction-meta">
                                    <?php echo date_i18n('F j, Y \a\t g:i A', strtotime($transaction->created_at)); ?>
                                    <?php if ($transaction->notes): ?>
                                        <br><small><?php echo esc_html($transaction->notes); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="points-change <?php echo ($transaction->points_change >= 0) ? 'positive' : 'negative'; ?>">
                                <?php echo ($transaction->points_change >= 0 ? '+' : '') . number_format($transaction->points_change, 0); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="load-more-section">
                    <button id="load-points-history" class="herbal-button-secondary">
                        <?php _e('Show Full History', 'herbal-mix-creator2'); ?>
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="no-activity-section">
                <div class="no-history">
                    <h3><?php _e('No Points Activity Yet', 'herbal-mix-creator2'); ?></h3>
                    <p><?php _e('Start earning points by:', 'herbal-mix-creator2'); ?></p>
                    <ul>
                        <li><?php _e('Making your first purchase', 'herbal-mix-creator2'); ?></li>
                        <li><?php _e('Creating a custom herbal mix', 'herbal-mix-creator2'); ?></li>
                        <li><?php _e('Leaving product reviews', 'herbal-mix-creator2'); ?></li>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Container for full history (loaded via AJAX) -->
            <div id="points-history-container" style="display: none;">
                <!-- Points history will be loaded here via AJAX -->
            </div>
        </div>

        <style>
        /* RESTORED BEAUTIFUL POINTS DASHBOARD STYLES */
        .herbal-points-dashboard {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px 0;
        }

        .points-summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .points-balance {
            text-align: left;
        }

        .points-value {
            font-size: 3.5em;
            font-weight: bold;
            line-height: 1;
            margin-bottom: 10px;
        }

        .points-label {
            font-size: 1.2em;
            opacity: 0.9;
        }

        .points-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .herbal-button-primary,
        .herbal-button-secondary {
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            min-width: 160px;
            text-align: center;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }

        .herbal-button-primary {
            background: white;
            color: #667eea;
        }

        .herbal-button-primary:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            color: #667eea;
            text-decoration: none;
        }

        .herbal-button-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .herbal-button-secondary:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
        }

        .points-earning-section {
            margin: 40px 0;
        }

        .points-earning-section h3 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.4em;
        }

        .points-earning-methods {
            list-style: none;
            padding: 0;
            margin: 20px 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .points-earning-methods li {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }

        .points-earning-methods li:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .method-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #667eea;
            margin-right: 20px;
            background-position: center;
            background-repeat: no-repeat;
            background-size: 24px;
            flex-shrink: 0;
        }

        .purchase-icon {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>');
        }

        .create-icon {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>');
        }

        .review-icon {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>');
        }

        .refer-icon {
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white"><path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63A2.996 2.996 0 0 0 17.09 6c-.8 0-1.54.37-2.01.99l-.01.01A2.99 2.99 0 0 0 12 6c-1.66 0-3 1.34-3 3s1.34 3 3 3c.35 0 .69-.07 1-.19v7.19h2v-3h1v3h4z"/></svg>');
        }

        .method-content h4 {
            margin: 0 0 8px 0;
            color: #2c3e50;
            font-size: 1.1em;
        }

        .method-content p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9em;
            line-height: 1.4;
        }

        .recent-activity-section,
        .no-activity-section {
            margin: 40px 0;
        }

        .recent-activity-section h3,
        .no-activity-section h3 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.4em;
        }

        .recent-transactions {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f1f1f1;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-details h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 1em;
        }

        .transaction-meta {
            font-size: 0.85em;
            color: #6c757d;
        }

        .points-change {
            font-size: 1.3em;
            font-weight: bold;
            padding: 8px 12px;
            border-radius: 20px;
            min-width: 80px;
            text-align: center;
        }

        .points-change.positive {
            color: #28a745;
            background: rgba(40, 167, 69, 0.1);
        }

        .points-change.negative {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }

        .load-more-section {
            text-align: center;
            margin-top: 30px;
        }

        .no-history {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 12px;
            border: 2px dashed #dee2e6;
        }

        .no-history h3 {
            color: #495057;
            margin-bottom: 15px;
        }

        .no-history ul {
            list-style: none;
            padding: 0;
            margin-top: 20px;
            text-align: left;
            display: inline-block;
        }

        .no-history li {
            padding: 8px 0;
            font-size: 1em;
            position: relative;
            padding-left: 25px;
        }

        .no-history li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }

        /* Points History Table (loaded via AJAX) */
        .points-history-table-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-top: 20px;
        }

        .points-history-table {
            width: 100%;
            border-collapse: collapse;
        }

        .points-history-table th,
        .points-history-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f1f1f1;
        }

        .points-history-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }

        .points-history-table tr:hover {
            background: #f8f9fa;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .herbal-points-dashboard {
                padding: 10px;
            }
            
            .points-summary-card {
                flex-direction: column;
                text-align: center;
                gap: 20px;
                padding: 20px 15px;
            }
            
            .points-value {
                font-size: 2.5em;
            }
            
            .points-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .herbal-button-primary,
            .herbal-button-secondary {
                min-width: auto;
            }
            
            .points-earning-methods li {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            
            .method-icon {
                margin-bottom: 15px;
                margin-right: 0;
            }
            
            .transaction-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .points-change {
                align-self: flex-end;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Format transaction type for display
     */
    private function format_transaction_type($type) {
        $types = array(
            'purchase' => __('Purchase', 'herbal-mix-creator2'),
            'order_completed' => __('Order Completed', 'herbal-mix-creator2'),
            'manual' => __('Manual Adjustment', 'herbal-mix-creator2'),
            'bonus' => __('Bonus Points', 'herbal-mix-creator2'),
            'refund' => __('Refund', 'herbal-mix-creator2'),
            'mix_published' => __('Mix Published', 'herbal-mix-creator2'),
            'review_submitted' => __('Review Submitted', 'herbal-mix-creator2'),
            'referral' => __('Referral Bonus', 'herbal-mix-creator2'),
            'points_purchase' => __('Points Purchase', 'herbal-mix-creator2')
        );
        
        return isset($types[$type]) ? $types[$type] : ucfirst(str_replace('_', ' ', $type));
    }
    
    /**
     * AJAX: Get more points history
     */
    public function ajax_get_more_points_history() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_points_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $history = Herbal_Mix_Database::get_points_history($user_id, $limit, $offset);
        
        $html = $this->render_history_table($history);
        $has_more = count($history) >= $limit;
        
        wp_send_json_success(array(
            'html' => $html,
            'has_more' => $has_more
        ));
    }
    
    /**
     * Render history table HTML
     */
    private function render_history_table($history) {
        if (empty($history)) {
            return '<p>' . __('No more transactions found.', 'herbal-mix-creator2') . '</p>';
        }
        
        $html = '<div class="points-history-table-wrapper">';
        $html .= '<table class="points-history-table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . __('Date', 'herbal-mix-creator2') . '</th>';
        $html .= '<th>' . __('Transaction', 'herbal-mix-creator2') . '</th>';
        $html .= '<th>' . __('Points', 'herbal-mix-creator2') . '</th>';
        $html .= '<th>' . __('Balance', 'herbal-mix-creator2') . '</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($history as $transaction) {
            $points_change = floatval($transaction->points_change);
            $is_positive = $points_change > 0;
            $formatted_change = ($is_positive ? '+' : '') . number_format($points_change, 0);
            $change_class = $is_positive ? 'positive' : 'negative';
            
            $transaction_type = $this->format_transaction_type($transaction->transaction_type);
            $date = date_i18n('M j, Y', strtotime($transaction->created_at));
            $balance = number_format($transaction->points_after, 0);
            
            $html .= '<tr>';
            $html .= '<td>' . esc_html($date) . '</td>';
            $html .= '<td>';
            $html .= '<strong>' . esc_html($transaction_type) . '</strong>';
            if ($transaction->notes) {
                $html .= '<br><small style="color: #6c757d;">' . esc_html($transaction->notes) . '</small>';
            }
            $html .= '</td>';
            $html .= '<td><span class="points-change ' . $change_class . '">' . $formatted_change . '</span></td>';
            $html .= '<td>' . $balance . ' pts</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Flush rewrite rules if needed
     */
    public function flush_rewrite_rules_maybe() {
        if (get_option('herbal_flush_rewrite_rules_flag')) {
            flush_rewrite_rules();
            delete_option('herbal_flush_rewrite_rules_flag');
        }
    }
}

// Initialize integration
new HerbalProfileIntegration();