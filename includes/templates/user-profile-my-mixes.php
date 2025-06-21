<?php
/**
 * SIMPLIFIED Template for "My Mixes" tab - NO CSS duplication
 * Uses existing styles from assets/css/profile.css
 * 
 * @package HerbalMixCreator2
 */

// Security check
if (!defined('ABSPATH')) exit;

// Get deletion messages
$deletion_message = '';
if (isset($_GET['deleted'])) {
    switch ($_GET['deleted']) {
        case 'published':
            $deletion_message = '<div class="woocommerce-message woocommerce-message--info">
                <a class="woocommerce-Button woocommerce-Button--alt button" href="' . esc_url(remove_query_arg('deleted')) . '">×</a>
                <strong>' . __('Mix Deleted:', 'herbal-mix-creator2') . '</strong> ' . __('Your published mix has been removed.', 'herbal-mix-creator2') . '
            </div>';
            break;
        case 'success':
            $deletion_message = '<div class="woocommerce-message woocommerce-message--info">
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
    
    <?php if ($deletion_message) echo $deletion_message; ?>
    
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
                                            <a href="#" class="edit-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Edit', 'herbal-mix-creator2'); ?></a>
                                        <?php else : ?>
                                            <a href="#" class="view-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('View', 'herbal-mix-creator2'); ?></a>
                                        <?php endif; ?>
                                        
                                        <a href="#" class="buy-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Buy', 'herbal-mix-creator2'); ?></a>
                                        
                                        <?php if ($mix->status != 'published') : ?>
                                            <a href="#" class="publish-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Publish', 'herbal-mix-creator2'); ?></a>
                                        <?php endif; ?>
                                        
                                        <a href="#" class="delete-mix button button-small button-danger" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Delete', 'herbal-mix-creator2'); ?></a>
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
                                            <a href="#" class="view-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('View', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="buy-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Buy', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="delete-mix button button-small button-danger" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Delete', 'herbal-mix-creator2'); ?></a>
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
                                            <a href="#" class="edit-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Edit', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="buy-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Buy', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="publish-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Publish', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="delete-mix button button-small button-danger" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Delete', 'herbal-mix-creator2'); ?></a>
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

<!-- EDIT MODAL - Uses existing CSS from profile.css -->
<div id="edit-mix-modal" class="modal-dialog" style="display:none;">
    <div class="modal-content">
        <span class="close-modal edit-close">&times;</span>
        <h3><?php _e('Edit Your Mix', 'herbal-mix-creator2'); ?></h3>
        
        <!-- Recipe Preview Section -->
        <div class="mix-summary">
            <div class="mix-recipe-preview">
                <h4><?php _e('Mix Recipe (not editable)', 'herbal-mix-creator2'); ?></h4>
                <div id="edit-mix-ingredients-preview" class="ingredients-list">
                    <!-- Ingredients loaded by JavaScript -->
                </div>
            </div>
            
            <!-- Pricing section with CORRECT IDs -->
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
        <form id="edit-mix-form" enctype="multipart/form-data">
            <input type="hidden" id="edit-mix-id" name="mix_id" value="">
            
            <div class="form-group">
                <label for="edit-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> *</label>
                <input type="text" id="edit-mix-name" name="mix_name" required>
            </div>
            
            <div class="form-group">
                <label for="edit-mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?></label>
                <textarea id="edit-mix-description" name="mix_description" rows="4" placeholder="<?php _e('Describe your mix: flavor profile, usage suggestions, benefits, etc.', 'herbal-mix-creator2'); ?>"></textarea>
            </div>
            
            <!-- Image Upload Section -->
            <div class="form-group">
                <label for="edit-mix-image-input"><?php _e('Mix Image', 'herbal-mix-creator2'); ?></label>
                <input type="hidden" id="edit-mix-image" name="mix_image" value="">
                <input type="file" id="edit-mix-image-input" accept="image/*">
                <div class="image-preview">
                    <img id="edit-mix-image-preview" src="" alt="" style="display:none; max-width: 200px; height: auto; margin-top: 10px;">
                </div>
                <button type="button" id="edit-mix-image-remove" class="button" style="display:none; margin-top: 10px;">
                    <?php _e('Remove Image', 'herbal-mix-creator2'); ?>
                </button>
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

<!-- PUBLISH MODAL - Uses existing CSS from profile.css -->
<div id="publish-mix-modal" class="modal-dialog" style="display:none;">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3><?php _e('Publish Your Mix', 'herbal-mix-creator2'); ?></h3>
        
        <div class="mix-summary">
            <div class="mix-recipe-preview">
                <h4><?php _e('Mix Recipe (not editable)', 'herbal-mix-creator2'); ?></h4>
                <div id="publish-mix-ingredients-preview" class="ingredients-list">
                    <!-- Ingredients loaded by JavaScript -->
                </div>
            </div>
            
            <!-- Pricing section with CORRECT IDs -->
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
                <input type="file" id="publish-mix-image-input" accept="image/*" required>
                <div class="image-preview">
                    <img id="publish-mix-image-preview" src="" alt="" style="display:none; max-width: 200px; height: auto; margin-top: 10px;">
                </div>
                <button type="button" id="publish-mix-image-remove" class="button" style="display:none; margin-top: 10px;">
                    <?php _e('Remove Image', 'herbal-mix-creator2'); ?>
                </button>
                <div class="error-message" id="publish-image-error" style="display:none; color: #dc3545; font-size: 12px; margin-top: 5px;"></div>
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

<script type="text/javascript">
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
    
    // Image upload handlers for Edit modal
    $(document).on('change', '#edit-mix-image-input', function() {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#edit-mix-image-preview').attr('src', e.target.result).show();
                $('#edit-mix-image-remove').show();
            };
            reader.readAsDataURL(file);
            $('#edit-mix-image').val('temp-value');
        }
    });
    
    $(document).on('click', '#edit-mix-image-remove', function() {
        $('#edit-mix-image-input').val('');
        $('#edit-mix-image').val('');
        $('#edit-mix-image-preview').hide();
        $(this).hide();
    });
    
    // Image upload handlers for Publish modal
    $(document).on('change', '#publish-mix-image-input', function() {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#publish-mix-image-preview').attr('src', e.target.result).show();
                $('#publish-mix-image-remove').show();
            };
            reader.readAsDataURL(file);