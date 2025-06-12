<?php
/**
 * User Profile Points Template - Fixed Version Without Errors
 * File: templates/user-profile-points.php
 * For UK Market
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
if (!$user_id) {
    echo '<p>' . __('You must be logged in to view your points.', 'herbal-mix-creator2') . '</p>';
    return;
}

// Initialize Points Manager
$points_manager = new HerbalPointsManager();
$current_points = $points_manager->get_user_points($user_id);
$recent_history = $points_manager->get_points_history($user_id, 5); // Last 5 transactions

?>

<div class="herbal-points-dashboard">
    <!-- Main Points Widget -->
    <div class="points-summary-card">
        <div class="points-balance">
            <div class="points-value"><?php echo number_format($current_points, 0); ?></div>
            <div class="points-label"><?php _e('Available Points', 'herbal-mix-creator2'); ?></div>
        </div>
        <div class="points-actions">
            <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="herbal-button-primary">
                <?php _e('Shop with Points', 'herbal-mix-creator2'); ?>
            </a>
            <button id="load-points-history" class="herbal-button-secondary">
                <?php _e('View Points History', 'herbal-mix-creator2'); ?>
            </button>
        </div>
    </div>

    <!-- How to Earn Points Section -->
    <div class="points-earning-section">
        <h3><?php _e('How to Earn More Points', 'herbal-mix-creator2'); ?></h3>
        <ul class="points-earning-methods">
            <li>
                <div class="method-icon purchase-icon"></div>
                <div class="method-details">
                    <strong><?php _e('Make a purchase', 'herbal-mix-creator2'); ?></strong><br>
                    <span><?php _e('Earn points on every order', 'herbal-mix-creator2'); ?></span>
                </div>
            </li>
            <li>
                <div class="method-icon create-icon"></div>
                <div class="method-details">
                    <strong><?php _e('Create custom mixes', 'herbal-mix-creator2'); ?></strong><br>
                    <span><?php _e('Get points when others buy your creations', 'herbal-mix-creator2'); ?></span>
                </div>
            </li>
            <li>
                <div class="method-icon review-icon"></div>
                <div class="method-details">
                    <strong><?php _e('Write reviews', 'herbal-mix-creator2'); ?></strong><br>
                    <span><?php _e('Earn points for reviewing products', 'herbal-mix-creator2'); ?></span>
                </div>
            </li>
        </ul>
    </div>

    <!-- Points History Section -->
    <div class="points-history-section">
        <h3><?php _e('Points History', 'herbal-mix-creator2'); ?></h3>
        
        <?php if (empty($recent_history)): ?>
            <div class="no-history">
                <p><?php _e('No points transactions yet.', 'herbal-mix-creator2'); ?></p>
                <p><?php _e('Start earning points by:', 'herbal-mix-creator2'); ?></p>
                <ul>
                    <li><?php _e('🛒 Making purchases', 'herbal-mix-creator2'); ?></li>
                    <li><?php _e('💡 Creating popular mixes', 'herbal-mix-creator2'); ?></li>
                    <li><?php _e('⭐ Writing product reviews', 'herbal-mix-creator2'); ?></li>
                </ul>
            </div>
        <?php else: ?>
            <div class="recent-transactions">
                <p><?php _e('Click the button above to load your complete points history.', 'herbal-mix-creator2'); ?></p>
                
                <h4><?php _e('Recent Activity', 'herbal-mix-creator2'); ?></h4>
                <div class="mini-history">
                    <?php foreach ($recent_history as $transaction): ?>
                        <?php
                        // Simple inline formatting of transaction type
                        $formatted_type = ucfirst(str_replace('_', ' ', $transaction->transaction_type));
                        switch($transaction->transaction_type) {
                            case 'purchase':
                                $formatted_type = '🛒 Purchase';
                                break;
                            case 'order_payment':
                                $formatted_type = '💳 Order Payment';
                                break;
                            case 'mix_sale_commission':
                                $formatted_type = '💡 Mix Commission';
                                break;
                            case 'manual':
                                $formatted_type = '✋ Manual';
                                break;
                            case 'admin_adjustment':
                                $formatted_type = '⚙️ Admin Adjustment';
                                break;
                            case 'bonus':
                                $formatted_type = '🎁 Bonus';
                                break;
                            case 'refund':
                                $formatted_type = '↩️ Refund';
                                break;
                        }
                        ?>
                        <div class="transaction-item">
                            <div class="transaction-type">
                                <?php echo esc_html($formatted_type); ?>
                            </div>
                            <div class="transaction-points <?php echo $transaction->points_change >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo ($transaction->points_change >= 0 ? '+' : '') . number_format($transaction->points_change, 2); ?>
                            </div>
                            <div class="transaction-date">
                                <?php echo date('j M Y', strtotime($transaction->created_at)); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Container for full history (loaded via AJAX) -->
        <div id="points-history-container" style="display: none;">
            <!-- Points history will be loaded here via AJAX -->
        </div>
    </div>
</div>

<style>
/* Points Dashboard Styles */
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
    transform: translateY(-2px);
    color: white;
}

/* Points Earning Section */
.points-earning-section {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.points-earning-section h3 {
    font-size: 24px;
    color: #2a6a3c;
    margin-bottom: 20px;
    border-bottom: 2px solid #8AC249;
    padding-bottom: 10px;
}

.points-earning-methods {
    list-style: none;
    padding: 0;
    margin: 0;
}

.points-earning-methods li {
    display: flex;
    align-items: center;
    padding: 20px 0;
    border-bottom: 1px solid #eee;
}

.points-earning-methods li:last-child {
    border-bottom: none;
}

.method-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #8AC249;
    margin-right: 20px;
    background-size: 24px 24px;
    background-repeat: no-repeat;
    background-position: center;
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

.method-details strong {
    display: block;
    font-size: 16px;
    color: #2a6a3c;
    margin-bottom: 5px;
}

.method-details span {
    color: #666;
    font-size: 14px;
}

/* Points History Section */
.points-history-section {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.points-history-section h3 {
    font-size: 24px;
    color: #2a6a3c;
    margin-bottom: 20px;
    border-bottom: 2px solid #8AC249;
    padding-bottom: 10px;
}

.recent-transactions {
    margin-bottom: 20px;
}

.mini-history {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-top: 15px;
}

.transaction-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #dee2e6;
}

.transaction-item:last-child {
    border-bottom: none;
}

.transaction-type {
    font-weight: 500;
    color: #495057;
    flex: 1;
}

.transaction-points {
    font-weight: bold;
    margin: 0 15px;
}

.transaction-points.positive {
    color: #28a745;
}

.transaction-points.negative {
    color: #dc3545;
}

.transaction-date {
    color: #6c757d;
    font-size: 0.9em;
}

/* Full History Table */
.points-history-table-wrapper {
    margin: 20px 0;
    overflow-x: auto;
}

.points-history-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.points-history-table th {
    background: #667eea;
    color: white;
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
}

.points-history-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

.points-history-table tr:last-child td {
    border-bottom: none;
}

.points-history-table tr:hover {
    background-color: #f8f9fa;
}

.points-change.positive {
    color: #28a745;
    font-weight: bold;
}

.points-change.negative {
    color: #dc3545;
    font-weight: bold;
}

.no-data {
    text-align: center;
    color: #666;
    font-style: italic;
}

.no-history {
    text-align: center;
    padding: 40px 20px;
    color: #666;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px dashed #dee2e6;
}

.no-history ul {
    list-style: none;
    padding: 0;
    margin-top: 15px;
    text-align: left;
    display: inline-block;
}

.no-history li {
    padding: 5px 0;
    font-size: 1.1em;
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
        margin-bottom: 10px;
        margin-right: 0;
    }
    
    .transaction-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .points-history-table-wrapper {
        font-size: 0.9em;
    }
    
    .points-history-table th,
    .points-history-table td {
        padding: 8px 6px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // AJAX loading of points history
    $('#load-points-history').on('click', function() {
        var button = $(this);
        var container = $('#points-history-container');
        
        // If history already loaded, just show/hide
        if (container.find('.points-history-table').length > 0) {
            container.toggle();
            button.text(container.is(':visible') ? 
                '<?php _e('Hide Points History', 'herbal-mix-creator2'); ?>' : 
                '<?php _e('View Points History', 'herbal-mix-creator2'); ?>'
            );
            return;
        }
        
        // Load history via AJAX
        button.prop('disabled', true).text('<?php _e('Loading...', 'herbal-mix-creator2'); ?>');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'get_points_history',
                user_id: <?php echo $user_id; ?>,
                nonce: '<?php echo wp_create_nonce('herbal_points_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var historyHTML = '<div class="points-history-table-wrapper">';
                    historyHTML += '<table class="points-history-table">';
                    historyHTML += '<thead><tr>';
                    historyHTML += '<th><?php _e('Date', 'herbal-mix-creator2'); ?></th>';
                    historyHTML += '<th><?php _e('Transaction', 'herbal-mix-creator2'); ?></th>';
                    historyHTML += '<th><?php _e('Points Change', 'herbal-mix-creator2'); ?></th>';
                    historyHTML += '<th><?php _e('Balance After', 'herbal-mix-creator2'); ?></th>';
                    historyHTML += '</tr></thead><tbody>';
                    
                    if (response.data && response.data.length > 0) {
                        response.data.forEach(function(transaction) {
                            var changeClass = transaction.points_change >= 0 ? 'positive' : 'negative';
                            var changePrefix = transaction.points_change >= 0 ? '+' : '';
                            var formattedDate = new Date(transaction.created_at).toLocaleDateString('en-GB');
                            
                            historyHTML += '<tr>';
                            historyHTML += '<td>' + formattedDate + '</td>';
                            historyHTML += '<td>' + transaction.transaction_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</td>';
                            historyHTML += '<td class="points-change ' + changeClass + '">' + 
                                         changePrefix + parseFloat(transaction.points_change).toFixed(2) + '</td>';
                            historyHTML += '<td>' + parseFloat(transaction.points_after).toFixed(2) + '</td>';
                            historyHTML += '</tr>';
                        });
                    } else {
                        historyHTML += '<tr><td colspan="4" class="no-data"><?php _e('No transactions found.', 'herbal-mix-creator2'); ?></td></tr>';
                    }
                    
                    historyHTML += '</tbody></table></div>';
                    
                    container.html(historyHTML).fadeIn();
                    button.text('<?php _e('Hide Points History', 'herbal-mix-creator2'); ?>');
                } else {
                    alert('<?php _e('Error loading points history.', 'herbal-mix-creator2'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Connection error. Please try again.', 'herbal-mix-creator2'); ?>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});
</script>