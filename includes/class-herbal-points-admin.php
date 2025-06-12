<?php
/**
 * Admin Panel for Points Management
 * Updated to use Herbal_Mix_Database instead of HerbalPointsManager
 * Adds Points Management page under Users menu in wp-admin
 * UK Market Version (English Language)
 */

if (!defined('ABSPATH')) exit;

class HerbalPointsAdmin {
    
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers for admin
        add_action('wp_ajax_admin_adjust_user_points', array($this, 'ajax_adjust_user_points'));
        add_action('wp_ajax_admin_get_user_points', array($this, 'ajax_get_user_points'));
        add_action('wp_ajax_get_points_statistics', array($this, 'ajax_get_points_statistics'));
        add_action('wp_ajax_export_users_points', array($this, 'ajax_export_users_points'));
        add_action('wp_ajax_get_user_points_history_admin', array($this, 'ajax_get_user_points_history'));
        add_action('wp_ajax_bulk_add_points', array($this, 'ajax_bulk_add_points'));
        
        // Add points column to users list
        add_filter('manage_users_columns', array($this, 'add_points_column'));
        add_filter('manage_users_custom_column', array($this, 'show_points_column'), 10, 3);
        add_filter('manage_users_sortable_columns', array($this, 'make_points_column_sortable'));
        add_action('pre_get_users', array($this, 'sort_users_by_points'));
        
        // Admin enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu under Users
     */
    public function add_admin_menu() {
        add_users_page(
            __('Points Management', 'herbal-mix-creator2'),
            __('Points Management', 'herbal-mix-creator2'),
            'manage_options',
            'herbal-points-management',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'users_page_herbal-points-management') {
            return;
        }
        
        wp_enqueue_script(
            'herbal-admin-points',
            plugin_dir_url(__FILE__) . '../assets/js/admin-points.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('herbal-admin-points', 'herbal_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('herbal_admin_points')
        ));
        
        // Admin styles
        wp_enqueue_style(
            'herbal-admin-points-style',
            plugin_dir_url(__FILE__) . '../assets/css/admin-points.css',
            array(),
            '1.0.0'
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        global $wpdb;
        
        // Get statistics
        $stats = $this->get_points_statistics();
        
        // Get users for dropdown
        $users = get_users(array(
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 500 // Limit for performance
        ));
        
        ?>
        <div class="wrap">
            <h1><?php _e('Points Management', 'herbal-mix-creator2'); ?></h1>
            
            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total_points'], 0); ?></div>
                    <div class="stat-label"><?php _e('Total Points in System', 'herbal-mix-creator2'); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total_users'], 0); ?></div>
                    <div class="stat-label"><?php _e('Users with Points', 'herbal-mix-creator2'); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['avg_points'], 1); ?></div>
                    <div class="stat-label"><?php _e('Average Points per User', 'herbal-mix-creator2'); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['transactions_today'], 0); ?></div>
                    <div class="stat-label"><?php _e('Transactions Today', 'herbal-mix-creator2'); ?></div>
                </div>
            </div>
            
            <!-- Points Adjustment Form -->
            <div class="adjustment-form-container">
                <h2><?php _e('Adjust User Points', 'herbal-mix-creator2'); ?></h2>
                
                <form id="points-adjustment-form" class="points-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="selected-user"><?php _e('Select User', 'herbal-mix-creator2'); ?></label>
                            </th>
                            <td>
                                <select id="selected-user" name="user_id" required>
                                    <option value=""><?php _e('Choose a user...', 'herbal-mix-creator2'); ?></option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>">
                                            <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="current-points-display" style="margin-top: 10px; font-weight: bold; display: none;">
                                    <?php _e('Current Points:', 'herbal-mix-creator2'); ?> <span id="current-points-value">0</span>
                                </div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="adjustment-type"><?php _e('Adjustment Type', 'herbal-mix-creator2'); ?></label>
                            </th>
                            <td>
                                <select id="adjustment-type" name="adjustment_type" required>
                                    <option value="add"><?php _e('Add Points', 'herbal-mix-creator2'); ?></option>
                                    <option value="subtract"><?php _e('Subtract Points', 'herbal-mix-creator2'); ?></option>
                                    <option value="set"><?php _e('Set Points To', 'herbal-mix-creator2'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="points-amount"><?php _e('Points Amount', 'herbal-mix-creator2'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="points-amount" name="points_amount" min="0" step="1" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="adjustment-reason"><?php _e('Reason (Optional)', 'herbal-mix-creator2'); ?></label>
                            </th>
                            <td>
                                <textarea id="adjustment-reason" name="reason" rows="3" cols="50" 
                                         placeholder="<?php _e('Enter reason for this adjustment...', 'herbal-mix-creator2'); ?>"></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Adjust Points', 'herbal-mix-creator2'); ?>">
                        <span id="adjustment-loading" style="display: none; margin-left: 10px;">
                            <?php _e('Processing...', 'herbal-mix-creator2'); ?>
                        </span>
                    </p>
                </form>
                
                <div id="adjustment-result" style="display: none; margin-top: 20px;"></div>
            </div>
            
            <!-- Bulk Operations -->
            <div class="bulk-operations-container">
                <h2><?php _e('Bulk Operations', 'herbal-mix-creator2'); ?></h2>
                
                <form id="bulk-points-form" class="points-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="bulk-user-ids"><?php _e('User IDs', 'herbal-mix-creator2'); ?></label>
                            </th>
                            <td>
                                <textarea id="bulk-user-ids" name="user_ids" rows="4" cols="50" 
                                         placeholder="<?php _e('Enter user IDs separated by commas (e.g., 1,2,3,4)', 'herbal-mix-creator2'); ?>"></textarea>
                                <p class="description"><?php _e('Enter user IDs separated by commas', 'herbal-mix-creator2'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="bulk-points"><?php _e('Points to Add', 'herbal-mix-creator2'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="bulk-points" name="points" min="1" step="1" required>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="bulk-reason"><?php _e('Reason', 'herbal-mix-creator2'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="bulk-reason" name="reason" class="regular-text" 
                                       placeholder="<?php _e('Reason for bulk adjustment', 'herbal-mix-creator2'); ?>">
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" class="button-secondary" value="<?php _e('Bulk Add Points', 'herbal-mix-creator2'); ?>">
                    </p>
                </form>
            </div>
            
            <!-- Export Section -->
            <div class="export-section">
                <h2><?php _e('Export Data', 'herbal-mix-creator2'); ?></h2>
                <p><?php _e('Export all user points data to CSV format.', 'herbal-mix-creator2'); ?></p>
                <button id="export-points-csv" class="button">
                    <?php _e('Export Points Data (CSV)', 'herbal-mix-creator2'); ?>
                </button>
            </div>
            
            <!-- Recent Transactions -->
            <div class="recent-transactions">
                <h2><?php _e('Recent Point Transactions', 'herbal-mix-creator2'); ?></h2>
                <div id="recent-transactions-list">
                    <?php echo $this->render_recent_transactions(); ?>
                </div>
            </div>
        </div>
        
        <!-- Include admin JavaScript -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // User selection change handler
            $('#selected-user').on('change', function() {
                var userId = $(this).val();
                if (userId) {
                    // Get current points for selected user
                    $.ajax({
                        url: herbal_admin_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'admin_get_user_points',
                            user_id: userId,
                            nonce: herbal_admin_ajax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#current-points-value').text(parseFloat(response.data.points).toFixed(0));
                                $('#current-points-display').show();
                            }
                        }
                    });
                } else {
                    $('#current-points-display').hide();
                }
            });
            
            // Points adjustment form submission
            $('#points-adjustment-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = {
                    action: 'admin_adjust_user_points',
                    user_id: $('#selected-user').val(),
                    adjustment_type: $('#adjustment-type').val(),
                    points_amount: $('#points-amount').val(),
                    reason: $('#adjustment-reason').val(),
                    nonce: herbal_admin_ajax.nonce
                };
                
                $('#adjustment-loading').show();
                $('#adjustment-result').hide();
                
                $.ajax({
                    url: herbal_admin_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        $('#adjustment-loading').hide();
                        
                        if (response.success) {
                            $('#adjustment-result')
                                .removeClass('notice-error')
                                .addClass('notice notice-success')
                                .html('<p>' + response.data.message + '</p>')
                                .show();
                            
                            // Update current points display
                            $('#current-points-value').text(parseFloat(response.data.new_points).toFixed(0));
                            
                            // Reset form
                            $('#points-amount').val('');
                            $('#adjustment-reason').val('');
                        } else {
                            $('#adjustment-result')
                                .removeClass('notice-success')
                                .addClass('notice notice-error')
                                .html('<p>' + response.data + '</p>')
                                .show();
                        }
                    },
                    error: function() {
                        $('#adjustment-loading').hide();
                        $('#adjustment-result')
                            .removeClass('notice-success')
                            .addClass('notice notice-error')
                            .html('<p><?php _e('An error occurred. Please try again.', 'herbal-mix-creator2'); ?></p>')
                            .show();
                    }
                });
            });
            
            // Bulk points form submission
            $('#bulk-points-form').on('submit', function(e) {
                e.preventDefault();
                
                var userIds = $('#bulk-user-ids').val().split(',').map(function(id) {
                    return parseInt(id.trim());
                }).filter(function(id) {
                    return !isNaN(id) && id > 0;
                });
                
                if (userIds.length === 0) {
                    alert('<?php _e('Please enter valid user IDs.', 'herbal-mix-creator2'); ?>');
                    return;
                }
                
                var formData = {
                    action: 'bulk_add_points',
                    user_ids: userIds,
                    points: $('#bulk-points').val(),
                    reason: $('#bulk-reason').val(),
                    nonce: herbal_admin_ajax.nonce
                };
                
                $.ajax({
                    url: herbal_admin_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            $('#bulk-points-form')[0].reset();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            });
            
            // Export CSV
            $('#export-points-csv').on('click', function() {
                $.ajax({
                    url: herbal_admin_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'export_users_points',
                        nonce: herbal_admin_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Create and download CSV file
                            var blob = new Blob([response.data.csv], { type: 'text/csv' });
                            var url = window.URL.createObjectURL(blob);
                            var a = document.createElement('a');
                            a.href = url;
                            a.download = 'herbal-points-export-' + new Date().toISOString().slice(0, 10) + '.csv';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);
                        } else {
                            alert('Export failed: ' + response.data);
                        }
                    }
                });
            });
        });
        </script>
        
        <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
            line-height: 1;
        }
        
        .stat-label {
            margin-top: 5px;
            color: #666;
            font-size: 0.9em;
        }
        
        .adjustment-form-container,
        .bulk-operations-container,
        .export-section,
        .recent-transactions {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .points-form table {
            background: transparent;
        }
        
        #current-points-display {
            background: #f0f0f1;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #0073aa;
        }
        
        .transaction-item {
            border-bottom: 1px solid #ddd;
            padding: 10px 0;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-meta {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        
        .points-positive {
            color: #46b450;
        }
        
        .points-negative {
            color: #dc3232;
        }
        </style>
        <?php
    }
    
    /**
     * Get points statistics
     * UPDATED: Now uses direct database queries instead of HerbalPointsManager
     */
    private function get_points_statistics() {
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
        $transactions_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}herbal_points_history 
            WHERE DATE(created_at) = %s
        ", $today)) ?: 0;
        
        return array(
            'total_points' => $total_points,
            'total_users' => $total_users,
            'avg_points' => $avg_points,
            'transactions_today' => $transactions_today
        );
    }
    
    /**
     * Render recent transactions
     */
    private function render_recent_transactions() {
        global $wpdb;
        
        $transactions = $wpdb->get_results("
            SELECT h.*, u.display_name 
            FROM {$wpdb->prefix}herbal_points_history h
            LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
            ORDER BY h.created_at DESC 
            LIMIT 10
        ");
        
        if (empty($transactions)) {
            return '<p>' . __('No recent transactions found.', 'herbal-mix-creator2') . '</p>';
        }
        
        $html = '<div class="transactions-list">';
        
        foreach ($transactions as $transaction) {
            $points_change = floatval($transaction->points_change);
            $is_positive = $points_change > 0;
            $formatted_change = ($is_positive ? '+' : '') . number_format($points_change, 0);
            $change_class = $is_positive ? 'points-positive' : 'points-negative';
            
            $html .= '<div class="transaction-item">';
            $html .= '<div class="transaction-main">';
            $html .= '<strong>' . esc_html($transaction->display_name ?: 'Unknown User') . '</strong> ';
            $html .= '<span class="' . $change_class . '">' . $formatted_change . ' points</span>';
            $html .= '</div>';
            $html .= '<div class="transaction-meta">';
            $html .= ucfirst(str_replace('_', ' ', $transaction->transaction_type)) . ' • ';
            $html .= date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at));
            if (!empty($transaction->notes)) {
                $html .= ' • ' . esc_html($transaction->notes);
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Format transaction type for display
     */
    private function format_transaction_type($type) {
        $types = array(
            'purchase' => __('Purchase Reward', 'herbal-mix-creator2'),
            'order_completion' => __('Order Completed', 'herbal-mix-creator2'),
            'points_payment' => __('Points Payment', 'herbal-mix-creator2'),
            'admin_adjustment' => __('Admin Adjustment', 'herbal-mix-creator2'),
            'manual' => __('Manual Adjustment', 'herbal-mix-creator2'),
            'refund' => __('Refund', 'herbal-mix-creator2'),
            'bulk_admin_adjustment' => __('Bulk Admin Adjustment', 'herbal-mix-creator2')
        );
        
        return isset($types[$type]) ? $types[$type] : ucfirst(str_replace('_', ' ', $type));
    }
    
    /**
     * AJAX: Adjust user points
     * UPDATED: Now uses Herbal_Mix_Database instead of HerbalPointsManager
     */
    public function ajax_adjust_user_points() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_admin_points') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $user_id = intval($_POST['user_id']);
        $adjustment_type = sanitize_text_field($_POST['adjustment_type']);
        $points_amount = floatval($_POST['points_amount']);
        $reason = sanitize_textarea_field($_POST['reason']);
        
        if (!$user_id || $points_amount < 0) {
            wp_send_json_error('Invalid parameters');
        }
        
        $current_points = floatval(get_user_meta($user_id, 'reward_points', true)) ?: 0;
        $new_points = $current_points;
        $transaction_type = 'admin_adjustment';
        
        switch ($adjustment_type) {
            case 'add':
                $new_points = $current_points + $points_amount;
                update_user_meta($user_id, 'reward_points', $new_points);
                Herbal_Mix_Database::record_points_transaction(
                    $user_id, $points_amount, $transaction_type, null, $current_points, $new_points, $reason
                );
                break;
                
            case 'subtract':
                $new_points = max(0, $current_points - $points_amount);
                update_user_meta($user_id, 'reward_points', $new_points);
                Herbal_Mix_Database::record_points_transaction(
                    $user_id, -$points_amount, $transaction_type, null, $current_points, $new_points, $reason
                );
                break;
                
            case 'set':
                $difference = $points_amount - $current_points;
                $new_points = $points_amount;
                update_user_meta($user_id, 'reward_points', $new_points);
                Herbal_Mix_Database::record_points_transaction(
                    $user_id, $difference, $transaction_type, null, $current_points, $new_points, $reason
                );
                break;
                
            default:
                wp_send_json_error('Invalid adjustment type');
        }
        
        wp_send_json_success(array(
            'old_points' => $current_points,
            'new_points' => $new_points,
            'message' => sprintf(__('User points updated from %s to %s', 'herbal-mix-creator2'), 
                               number_format($current_points, 2), number_format($new_points, 2))
        ));
    }
    
    /**
     * AJAX: Get user points
     */
    public function ajax_get_user_points() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_admin_points') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $user_id = intval($_POST['user_id']);
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }
        
        $points = floatval(get_user_meta($user_id, 'reward_points', true)) ?: 0;
        
        wp_send_json_success(array('points' => $points));
    }
    
    /**
     * AJAX: Get points statistics
     */
    public function ajax_get_points_statistics() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_admin_points') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $stats = $this->get_points_statistics();
        
        ob_start();
        ?>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['total_points'], 0); ?></div>
            <div class="stat-label"><?php _e('Total Points in System', 'herbal-mix-creator2'); ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['total_users'], 0); ?></div>
            <div class="stat-label"><?php _e('Users with Points', 'herbal-mix-creator2'); ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['avg_points'], 1); ?></div>
            <div class="stat-label"><?php _e('Average Points per User', 'herbal-mix-creator2'); ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($stats['transactions_today'], 0); ?></div>
            <div class="stat-label"><?php _e('Transactions Today', 'herbal-mix-creator2'); ?></div>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Export users points
     */
    public function ajax_export_users_points() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_admin_points') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $users = get_users(array(
            'meta_key' => 'reward_points',
            'fields' => array('ID', 'display_name', 'user_email')
        ));
        
        $csv = "User ID,Username,Email,Points\n";
        
        foreach ($users as $user) {
            $points = get_user_meta($user->ID, 'reward_points', true) ?: 0;
            $csv .= sprintf('"%s","%s","%s","%s"' . "\n",
                $user->ID,
                str_replace('"', '""', $user->display_name),
                str_replace('"', '""', $user->user_email),
                number_format($points, 2)
            );
        }
        
        wp_send_json_success(array('csv' => $csv));
    }
    
    /**
     * AJAX: Get user points history for admin
     */
    public function ajax_get_user_points_history() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_admin_points') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $user_id = intval($_POST['user_id']);
        $limit = intval($_POST['limit']) ?: 50;
        $offset = intval($_POST['offset']) ?: 0;
        
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }
        
        $history = Herbal_Mix_Database::get_points_history($user_id, $limit, $offset);
        
        wp_send_json_success($history);
    }
    
    /**
     * AJAX: Bulk add points to multiple users
     * UPDATED: Now uses Herbal_Mix_Database instead of HerbalPointsManager
     */
    public function ajax_bulk_add_points() {
        if (!wp_verify_nonce($_POST['nonce'], 'herbal_admin_points') || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $user_ids = array_map('intval', $_POST['user_ids']);
        $points = floatval($_POST['points']);
        $reason = sanitize_textarea_field($_POST['reason']);
        
        if (empty($user_ids) || $points <= 0) {
            wp_send_json_error('Invalid parameters');
        }
        
        $success_count = 0;
        
        foreach ($user_ids as $user_id) {
            if ($user_id > 0) {
                $current_points = floatval(get_user_meta($user_id, 'reward_points', true)) ?: 0;
                $new_points = $current_points + $points;
                
                if (update_user_meta($user_id, 'reward_points', $new_points)) {
                    Herbal_Mix_Database::record_points_transaction(
                        $user_id, $points, 'bulk_admin_adjustment', null, $current_points, $new_points, $reason
                    );
                    $success_count++;
                }
            }
        }
        
        wp_send_json_success(array(
            'success_count' => $success_count,
            'total_count' => count($user_ids),
            'message' => sprintf(__('Added %s points to %d users', 'herbal-mix-creator2'), 
                               number_format($points, 2), $success_count)
        ));
    }
    
    /**
     * Add points column to users list
     */
    public function add_points_column($columns) {
        $columns['herbal_points'] = __('Points', 'herbal-mix-creator2');
        return $columns;
    }
    
    /**
     * Show points in column
     */
    public function show_points_column($value, $column_name, $user_id) {
        if ($column_name == 'herbal_points') {
            $points = get_user_meta($user_id, 'reward_points', true) ?: 0;
            return number_format($points, 2);
        }
        return $value;
    }
    
    /**
     * Make points column sortable
     */
    public function make_points_column_sortable($columns) {
        $columns['herbal_points'] = 'herbal_points';
        return $columns;
    }
    
    /**
     * Sort users by points
     */
    public function sort_users_by_points($query) {
        if (!is_admin()) {
            return;
        }
        
        $orderby = $query->get('orderby');
        if ($orderby == 'herbal_points') {
            $query->set('meta_key', 'reward_points');
            $query->set('orderby', 'meta_value_num');
        }
    }
}

// Initialize admin functionality
if (is_admin()) {
    new HerbalPointsAdmin();
}