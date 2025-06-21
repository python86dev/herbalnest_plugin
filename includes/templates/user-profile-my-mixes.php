<?php
/**
 * FIXED Template for "My Mixes" tab on user account page
 * NO DUPLICATES + Working Price Loading
 * 
 * @package HerbalMixCreator2
 */

// Security check
if (!defined('ABSPATH')) exit;

// Sprawdź komunikaty o usunięciu mieszanki
$deletion_message = '';
if (isset($_GET['deleted'])) {
    switch ($_GET['deleted']) {
        case 'published':
            $deletion_message = '<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
                <a class="woocommerce-Button woocommerce-Button--alt button" href="' . esc_url(remove_query_arg('deleted')) . '">×</a>
                <strong>' . __('Mix Deleted:', 'herbal-mix-creator2') . '</strong> ' . __('Your published mix has been removed from your list.', 'herbal-mix-creator2') . '
            </div>';
            break;
        case 'success':
            $deletion_message = '<div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
                <a class="woocommerce-Button woocommerce-Button--alt button" href="' . esc_url(remove_query_arg('deleted')) . '">×</a>
                <strong>' . __('Mix Deleted:', 'herbal-mix-creator2') . '</strong> ' . __('Your mix has been successfully deleted.', 'herbal-mix-creator2') . '
            </div>';
            break;
    }
}
?>

<div class="herbal-mixes-dashboard">
    <div class="dashboard-header">
        <h2><?php _e('My Herbal Mixes', 'herbal-mix-creator2'); ?></h2>
        <a href="<?php echo esc_url(get_permalink(get_page_by_path('herbal-mix-creator'))); ?>" class="button"><?php _e('Create New Mix', 'herbal-mix-creator2'); ?></a>
    </div>
    
    <?php 
    // Wyświetl komunikat o usunięciu jeśli istnieje
    if ($deletion_message) {
        echo $deletion_message;
    }
    ?>
    
    <?php if (empty($my_mixes)) : ?>
        <div class="woocommerce-message woocommerce-message--info">
            <p><?php _e('You haven\'t created any mixes yet.', 'herbal-mix-creator2'); ?></p>
            <a href="<?php echo esc_url(get_permalink(get_page_by_path('herbal-mix-creator'))); ?>" class="button"><?php _e('Create Your First Mix', 'herbal-mix-creator2'); ?></a>
        </div>
    <?php else : ?>
        <div class="mixes-tabs">
            <ul class="tab-navigation">
                <li class="active"><a href="#all-mixes"><?php _e('All Mixes', 'herbal-mix-creator2'); ?></a></li>
                <li><a href="#published-mixes"><?php _e('Published', 'herbal-mix-creator2'); ?></a></li>
                <li><a href="#private-mixes"><?php _e('Private', 'herbal-mix-creator2'); ?></a></li>
            </ul>
            
            <div class="tab-content">
                <!-- ALL MIXES TAB -->
                <div id="all-mixes" class="tab-pane active">
                    <table class="woocommerce-orders-table mixes-table">
                        <thead>
                            <tr>
                                <th><?php _e('Name', 'herbal-mix-creator2'); ?></th>
                                <th><?php _e('Created', 'herbal-mix-creator2'); ?></th>
                                <th><?php _e('Status', 'herbal-mix-creator2'); ?></th>
                                <th><?php _e('Likes', 'herbal-mix-creator2'); ?></th>
                                <th><?php _e('Actions', 'herbal-mix-creator2'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_mixes as $mix) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($mix->mix_name); ?></strong>
                                        <?php if (!empty($mix->mix_description)) : ?>
                                            <br><small class="description"><?php echo esc_html(wp_trim_words($mix->mix_description, 10, '...')); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date_i18n(get_option('date_format'), strtotime($mix->created_at)); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo esc_attr($mix->status); ?>">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $mix->status))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo intval($mix->like_count); ?></td>
                                    <td class="mix-actions">
                                        <?php if ($mix->status != 'published') : ?>
                                            <a href="#" class="edit-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Edit this mix', 'herbal-mix-creator2'); ?>"><?php _e('Edit', 'herbal-mix-creator2'); ?></a>
                                        <?php else : ?>
                                            <a href="#" class="view-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('View this mix', 'herbal-mix-creator2'); ?>"><?php _e('View', 'herbal-mix-creator2'); ?></a>
                                        <?php endif; ?>
                                        
                                        <a href="#" class="buy-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Buy this mix', 'herbal-mix-creator2'); ?>"><?php _e('Buy', 'herbal-mix-creator2'); ?></a>
                                        
                                        <?php if ($mix->status != 'published') : ?>
                                            <a href="#" class="publish-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Publish this mix to the community', 'herbal-mix-creator2'); ?>"><?php _e('Publish', 'herbal-mix-creator2'); ?></a>
                                        <?php endif; ?>
                                        
                                        <a href="#" class="delete-mix button button-small button-danger" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Delete this mix permanently', 'herbal-mix-creator2'); ?>"><?php _e('Delete', 'herbal-mix-creator2'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- PUBLISHED MIXES TAB -->
                <div id="published-mixes" class="tab-pane">
                    <table class="woocommerce-orders-table mixes-table">
                        <thead>
                            <tr>
                                <th><?php _e('Name', 'herbal-mix-creator2'); ?></th>
                                <th><?php _e('Created', 'herbal-mix-creator2'); ?></th>
                                <th><?php _e('Likes', 'herbal-mix-creator2'); ?></th>
                                <th><?php _e('Actions', 'herbal-mix-creator2'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $published_mixes = array_filter($my_mixes, function($mix) {
                                return $mix->status === 'published';
                            });
                            
                            if (empty($published_mixes)) : 
                            ?>
                                <tr>
                                    <td colspan="4" class="no-mixes-message">
                                        <p><?php _e('No published mixes found.', 'herbal-mix-creator2'); ?></p>
                                        <p><small><?php _e('Publish your mixes to share them with the community!', 'herbal-mix-creator2'); ?></small></p>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($published_mixes as $mix) : ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($mix->mix_name); ?></strong>
                                            <?php if (!empty($mix->mix_description)) : ?>
                                                <br><small class="description"><?php echo esc_html(wp_trim_words($mix->mix_description, 10, '...')); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date_i18n(get_option('date_format'), strtotime($mix->created_at)); ?></td>
                                        <td><?php echo intval($mix->like_count); ?></td>
                                        <td class="mix-actions">
                                            <a href="#" class="view-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('View this mix', 'herbal-mix-creator2'); ?>"><?php _e('View', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="buy-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Buy this mix', 'herbal-mix-creator2'); ?>"><?php _e('Buy', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="delete-mix button button-small button-danger" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Delete this mix permanently', 'herbal-mix-creator2'); ?>"><?php _e('Delete', 'herbal-mix-creator2'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- PRIVATE MIXES TAB -->
                <div id="private-mixes" class="tab-pane">
                    <table class="woocommerce-orders-table mixes-table">
                        <thead>
                            <tr>
                                <th><?php _e('Name', 'herbal-mix-creator2'); ?></th>
                                <th><?php _e('Created', 'herbal-mix-creator2'); ?></th>
                                <th><?php _e('Actions', 'herbal-mix-creator2'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $private_mixes = array_filter($my_mixes, function($mix) {
                                return $mix->status != 'published';
                            });
                            
                            if (empty($private_mixes)) : 
                            ?>
                                <tr>
                                    <td colspan="3" class="no-mixes-message">
                                        <p><?php _e('No private mixes found.', 'herbal-mix-creator2'); ?></p>
                                        <p><small><?php _e('Create new mixes or all your mixes are already published!', 'herbal-mix-creator2'); ?></small></p>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($private_mixes as $mix) : ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($mix->mix_name); ?></strong>
                                            <?php if (!empty($mix->mix_description)) : ?>
                                                <br><small class="description"><?php echo esc_html(wp_trim_words($mix->mix_description, 10, '...')); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date_i18n(get_option('date_format'), strtotime($mix->created_at)); ?></td>
                                        <td class="mix-actions">
                                            <a href="#" class="edit-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Edit this mix', 'herbal-mix-creator2'); ?>"><?php _e('Edit', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="buy-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Buy this mix', 'herbal-mix-creator2'); ?>"><?php _e('Buy', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="publish-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Publish this mix to the community', 'herbal-mix-creator2'); ?>"><?php _e('Publish', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="delete-mix button button-small button-danger" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Delete this mix permanently', 'herbal-mix-creator2'); ?>"><?php _e('Delete', 'herbal-mix-creator2'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- EDIT MODAL DIALOG - FIXED PRICING SECTION -->
<div id="edit-mix-modal" class="modal-dialog" style="display:none;">
    <div class="modal-content">
        <span class="close-modal edit-close">&times;</span>
        <h3><?php _e('Edit Your Mix', 'herbal-mix-creator2'); ?></h3>
        
        <!-- Recipe Preview Section (Read-only) -->
        <div class="mix-summary">
            <div class="mix-recipe-preview">
                <h4><?php _e('Mix Recipe (not editable)', 'herbal-mix-creator2'); ?></h4>
                <div id="edit-mix-ingredients-preview" class="ingredients-list">
                    <!-- Ingredients will be dynamically inserted here by JS -->
                </div>
            </div>
            
            <!-- FIXED: Pricing section with proper IDs -->
            <div class="mix-pricing">
                <div class="price-item">
                    <span class="price-label"><?php _e('Current Price:', 'herbal-mix-creator2'); ?></span>
                    <span class="price-value" id="edit-mix-price">£0.00</span>
                </div>
                <div class="price-item">
                    <span class="price-label"><?php _e('Price in Points:', 'herbal-mix-creator2'); ?></span>
                    <span class="price-value" id="edit-mix-points-price">0 pts</span>
                </div>
                <div class="price-item">
                    <span class="price-label"><?php _e('Points Earned:', 'herbal-mix-creator2'); ?></span>
                    <span class="price-value" id="edit-mix-points-earned">0 pts</span>
                </div>
            </div>
        </div>
        
        <!-- Edit Form -->
        <form id="edit-mix-form">
            <input type="hidden" id="edit-mix-id" name="mix_id" value="">
            
            <div class="form-group">
                <label for="edit-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> *</label>
                <input type="text" id="edit-mix-name" name="mix_name" required>
            </div>
            
            <div class="form-group">
                <label for="edit-mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?></label>
                <textarea id="edit-mix-description" name="mix_description" rows="4" placeholder="<?php _e('Describe your mix: flavor profile, usage suggestions, benefits, etc.', 'herbal-mix-creator2'); ?>"></textarea>
                <small class="form-text"><?php _e('This description will be visible when you publish your mix.', 'herbal-mix-creator2'); ?></small>
            </div>
            
            <div class="form-actions">
                <button type="submit" id="edit-update-button" class="button button-primary">
                    <?php _e('Update Mix', 'herbal-mix-creator2'); ?>
                </button>
                <button type="button" class="button button-secondary cancel-modal edit-cancel">
                    <?php _e('Cancel', 'herbal-mix-creator2'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- PUBLISH MODAL DIALOG - FIXED PRICING SECTION -->
<div id="publish-mix-modal" class="modal-dialog" style="display:none;">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3><?php _e('Publish Your Mix', 'herbal-mix-creator2'); ?></h3>
        
        <div class="mix-summary">
            <div class="mix-recipe-preview">
                <h4><?php _e('Mix Recipe (not editable)', 'herbal-mix-creator2'); ?></h4>
                <div id="publish-mix-ingredients-preview" class="ingredients-list">
                    <!-- Ingredients will be dynamically inserted here by JS -->
                </div>
            </div>
            
            <!-- FIXED: Pricing section with proper IDs -->
            <div class="mix-pricing">
                <div class="price-item">
                    <span class="price-label"><?php _e('Price:', 'herbal-mix-creator2'); ?></span>
                    <span class="price-value" id="publish-mix-price">£0.00</span>
                </div>
                <div class="price-item">
                    <span class="price-label"><?php _e('Price in Points:', 'herbal-mix-creator2'); ?></span>
                    <span class="price-value" id="publish-mix-points-price">0 pts</span>
                </div>
                <div class="price-item">
                    <span class="price-label"><?php _e('Points Earned:', 'herbal-mix-creator2'); ?></span>
                    <span class="price-value" id="publish-mix-points-earned">0 pts</span>
                </div>
            </div>
        </div>
        
        <!-- Publish Form -->
        <form id="publish-mix-form" enctype="multipart/form-data">
            <input type="hidden" id="publish-mix-id" name="mix_id" value="">
            
            <div class="form-group">
                <label for="publish-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> *</label>
                <input type="text" id="publish-mix-name" name="mix_name" required>
            </div>
            
            <div class="form-group">
                <label for="publish-mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?> *</label>
                <textarea id="publish-mix-description" name="mix_description" rows="4" required placeholder="<?php _e('Describe your mix: flavor profile, usage suggestions, benefits, etc.', 'herbal-mix-creator2'); ?>"></textarea>
            </div>
            
            <div class="form-group">
                <label for="publish-mix-image-input"><?php _e('Mix Image', 'herbal-mix-creator2'); ?> *</label>
                <input type="hidden" id="publish-mix-image" name="mix_image" value="">
                <input type="file" id="publish-mix-image-input" accept="image/*">
                <div class="image-preview">
                    <img id="publish-mix-image-preview" src="" alt="" style="display:none;">
                </div>
                <button type="button" id="publish-mix-image-remove" class="button" style="display:none;">
                    <?php _e('Remove Image', 'herbal-mix-creator2'); ?>
                </button>
                <div class="error-message" id="publish-image-error" style="display:none;"></div>
            </div>
            
            <div class="form-actions">
                <button type="submit" id="publish-button" class="button button-primary" disabled>
                    <?php _e('Publish Mix', 'herbal-mix-creator2'); ?>
                </button>
                <button type="button" class="button button-secondary cancel-modal">
                    <?php _e('Cancel', 'herbal-mix-creator2'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Template Specific Styles */
.herbal-mixes-dashboard {
    max-width: 100%;
    margin: 0 auto;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.tab-navigation {
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
    border-bottom: 2px solid #e1e1e1;
    display: flex;
    flex-wrap: wrap;
}

.tab-navigation li {
    margin-right: 5px;
}

.tab-navigation a {
    display: block;
    padding: 12px 20px;
    text-decoration: none;
    color: #666;
    border: 1px solid transparent;
    border-bottom: none;
    border-radius: 5px 5px 0 0;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.tab-navigation li.active a,
.tab-navigation a:hover {
    color: #2c3e50;
    background: #fff;
    border-color: #e1e1e1;
    border-bottom: 2px solid #fff;
    margin-bottom: -2px;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    text-transform: capitalize;
}

.status-published {
    background: #d4edda;
    color: #155724;
}

.status-favorite {
    background: #fff3cd;
    color: #856404;
}

.mix-actions .button {
    margin: 2px;
    font-size: 12px;
    padding: 5px 10px;
}

.mix-actions .button-danger {
    background: #dc3545;
    color: white;
    border-color: #dc3545;
}

.mix-actions .button-danger:hover {
    background: #c82333;
    border-color: #bd2130;
}

/* Modal Styles */
.modal-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 90%;
    overflow-y: auto;
    position: relative;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 24px;
    cursor: pointer;
    color: #999;
}

.close-modal:hover {
    color: #333;
}

.mix-pricing {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.price-item {
    text-align: center;
}

.price-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.price-value {
    display: block;
    font-size: 16px;
    font-weight: bold;
    color: #2c3e50;
}

.ingredients-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    background: white;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-actions {
    margin-top: 30px;
    text-align: right;
}

.form-actions .button {
    margin-left: 10px;
}

@media (max-width: 768px) {
    .mix-pricing {
        grid-template-columns: 1fr;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .tab-navigation {
        flex-direction: column;
    }
    
    .tab-navigation li {
        margin-right: 0;
        margin-bottom: 5px;
    }
}
</style>

<script type="text/javascript">
// FIXED: JavaScript initialization for tabs
jQuery(document).ready(function($) {
    console.log('My Mixes template loaded');
    
    // Tab navigation
    $('.tab-navigation a').on('click', function(e) {
        e.preventDefault();
        
        const target = $(this).attr('href');
        
        $('.tab-navigation li').removeClass('active');
        $(this).parent().addClass('active');
        
        $('.tab-pane').removeClass('active');
        $(target).addClass('active');
    });
    
    // Check if herbalProfileData exists - FALLBACK
    if (typeof herbalProfileData === 'undefined') {
        console.error('herbalProfileData not defined, creating fallback');
        window.herbalProfileData = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            getNonce: '<?php echo wp_create_nonce('get_mix_details'); ?>',
            updateMixNonce: '<?php echo wp_create_nonce('update_mix_details'); ?>',
            publishNonce: '<?php echo wp_create_nonce('publish_mix'); ?>',
            deleteNonce: '<?php echo wp_create_nonce('delete_mix'); ?>',
            strings: {
                error: 'An error occurred. Please try again.',
                confirmDelete: 'Are you sure you want to delete this mix? This action cannot be undone.',
                deleting: 'Deleting...',
                deleteSuccess: 'Mix deleted successfully.',
                connectionError: 'Connection error. Please try again.'
            }
        };
    }
});
</script>
