<?php
/**
 * Template for "My Mixes" tab on user account page
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
                <strong>' . __('Mix Deleted:', 'herbal-mix-creator2') . '</strong> ' . __('Your published mix has been removed from your list. An email notification has been sent to the administrator to remove it from the shop.', 'herbal-mix-creator2') . '
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
                <div id="all-mixes" class="tab-pane active">
                    <table class="woocommerce-orders-table">
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
                                        
                                        <a href="?action=buy_mix&mix_id=<?php echo esc_attr($mix->id); ?>" class="button button-small" title="<?php _e('Buy this mix', 'herbal-mix-creator2'); ?>"><?php _e('Buy', 'herbal-mix-creator2'); ?></a>
                                        
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
                
                <div id="published-mixes" class="tab-pane">
                    <table class="woocommerce-orders-table">
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
                                return $mix->status == 'published';
                            });
                            
                            if (empty($published_mixes)) : 
                            ?>
                                <tr>
                                    <td colspan="4" class="no-mixes-message">
                                        <p><?php _e('No published mixes yet.', 'herbal-mix-creator2'); ?></p>
                                        <p><small><?php _e('Publish your private mixes to share them with the community!', 'herbal-mix-creator2'); ?></small></p>
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
                                            <?php if ($mix->base_product_id) : ?>
                                                <br><small class="product-link">
                                                    <a href="<?php echo esc_url(get_permalink($mix->base_product_id)); ?>" target="_blank" title="<?php _e('View in shop', 'herbal-mix-creator2'); ?>">
                                                        <?php _e('View in Shop', 'herbal-mix-creator2'); ?> ↗
                                                    </a>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date_i18n(get_option('date_format'), strtotime($mix->created_at)); ?></td>
                                        <td><?php echo intval($mix->like_count); ?></td>
                                        <td class="mix-actions">
                                            <a href="#" class="view-mix button button-small" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('View this mix', 'herbal-mix-creator2'); ?>"><?php _e('View', 'herbal-mix-creator2'); ?></a>
                                            <a href="?action=buy_mix&mix_id=<?php echo esc_attr($mix->id); ?>" class="button button-small" title="<?php _e('Buy this mix', 'herbal-mix-creator2'); ?>"><?php _e('Buy', 'herbal-mix-creator2'); ?></a>
                                            <a href="#" class="delete-mix button button-small button-danger" data-mix-id="<?php echo esc_attr($mix->id); ?>" title="<?php _e('Delete this mix (will notify admin to remove from shop)', 'herbal-mix-creator2'); ?>"><?php _e('Delete', 'herbal-mix-creator2'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div id="private-mixes" class="tab-pane">
                    <table class="woocommerce-orders-table">
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
                                            <a href="?action=buy_mix&mix_id=<?php echo esc_attr($mix->id); ?>" class="button button-small" title="<?php _e('Buy this mix', 'herbal-mix-creator2'); ?>"><?php _e('Buy', 'herbal-mix-creator2'); ?></a>
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
        
        <!-- EDIT MODAL DIALOG -->
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
                    
                    <div class="mix-pricing">
                        <div class="price-item">
                            <span class="price-label"><?php _e('Current Price:', 'herbal-mix-creator2'); ?></span>
                            <span id="edit-mix-price-preview" class="price-value"></span>
                        </div>
                        <div class="price-item">
                            <span class="price-label"><?php _e('Price in Points:', 'herbal-mix-creator2'); ?></span>
                            <span id="edit-mix-points-price-preview" class="price-value"></span>
                        </div>
                        <div class="price-item">
                            <span class="price-label"><?php _e('Points Earned:', 'herbal-mix-creator2'); ?></span>
                            <span id="edit-mix-points-earned-preview" class="price-value"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Editable Fields -->
                <form id="edit-mix-form">
                    <input type="hidden" id="edit-mix-id" name="mix_id" value="">
                    
                    <div class="form-row">
                        <label for="edit-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> <span class="required">*</span></label>
                        <input type="text" id="edit-mix-name" name="mix_name" required>
                    </div>
                    
                    <div class="form-row">
                        <label for="edit-mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?></label>
                        <textarea id="edit-mix-description" name="mix_description" rows="4" placeholder="<?php _e('Describe your mix: flavor profile, usage suggestions, benefits, etc.', 'herbal-mix-creator2'); ?>"></textarea>
                        <p class="field-hint"><?php _e('This description will be visible when you publish your mix.', 'herbal-mix-creator2'); ?></p>
                    </div>
                    
                    <!-- Image Upload using Media Handler -->
                    <div class="form-row image-upload-row">
                        <label for="edit-mix-image"><?php _e('Mix Image', 'herbal-mix-creator2'); ?></label>
                        <div class="custom-file-upload">
                            <div id="edit-mix-image_preview" class="image-preview">
                                <div class="upload-prompt">
                                    <i class="upload-icon"></i>
                                    <span><?php _e('Click to upload a new image', 'herbal-mix-creator2'); ?></span>
                                </div>
                            </div>
                            
                            <!-- Progress bar -->
                            <div id="edit-mix-image_progress_container" class="upload-progress-container" style="display: none;">
                                <div class="upload-progress-bar-wrapper">
                                    <div id="edit-mix-image_progress_bar" class="upload-progress-bar"></div>
                                </div>
                                <div id="edit-mix-image_progress_text" class="upload-progress-text">0%</div>
                            </div>
                            
                            <!-- File input -->
                            <input type="file" id="edit-mix-image_file" name="edit_mix_image_file" accept="image/*" style="display:none;" class="herbal-mix-file-input" data-target="edit-mix-image">
                            
                            <!-- Hidden field for image URL -->
                            <input type="hidden" id="edit-mix-image" name="mix_image" class="herbal-mix-image-input">
                            
                            <!-- Buttons -->
                            <div class="image-upload-buttons">
                                <button type="button" id="edit-mix-image_select_btn" class="button herbal-mix-select-image-btn" data-target="edit-mix-image"><?php _e('Upload New Image', 'herbal-mix-creator2'); ?></button>
                                <button type="button" id="edit-mix-image_remove_btn" class="button button-secondary herbal-mix-remove-image-btn" data-target="edit-mix-image" style="display:none;"><?php _e('Remove', 'herbal-mix-creator2'); ?></button>
                            </div>
                            
                            <p class="field-hint"><?php _e('Upload a new image for your mix (recommended size: 800x800px)', 'herbal-mix-creator2'); ?></p>
                            <p id="edit-mix-image_error" class="error-message" style="display: none;"></p>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" id="edit-update-button" class="button button-primary"><?php _e('Save Changes', 'herbal-mix-creator2'); ?></button>
                        <button type="button" class="button cancel-modal edit-cancel"><?php _e('Cancel', 'herbal-mix-creator2'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PUBLISH MODAL DIALOG -->
        <div id="publish-mix-modal" class="modal-dialog" style="display:none;">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <h3><?php _e('Publish Your Mix', 'herbal-mix-creator2'); ?></h3>
                
                <div class="mix-summary">
                    <div class="mix-recipe-preview">
                        <h4><?php _e('Mix Recipe (not editable)', 'herbal-mix-creator2'); ?></h4>
                        <div id="mix-ingredients-preview" class="ingredients-list">
                            <!-- Ingredients will be dynamically inserted here by JS -->
                        </div>
                    </div>
                    
                    <div class="mix-pricing">
                        <div class="price-item">
                            <span class="price-label"><?php _e('Price:', 'herbal-mix-creator2'); ?></span>
                            <span id="mix-price-preview" class="price-value"></span>
                        </div>
                        <div class="price-item">
                            <span class="price-label"><?php _e('Price in Points:', 'herbal-mix-creator2'); ?></span>
                            <span id="mix-points-price-preview" class="price-value"></span>
                        </div>
                        <div class="price-item">
                            <span class="price-label"><?php _e('Points Earned:', 'herbal-mix-creator2'); ?></span>
                            <span id="mix-points-earned-preview" class="price-value"></span>
                        </div>
                    </div>
                </div>
                
                <form id="publish-mix-form">
                    <input type="hidden" id="publish-mix-id" name="mix_id" value="">
                    <input type="hidden" id="mix-recipe" name="mix_recipe" value="">
                    <input type="hidden" id="mix-price" name="mix_price" value="">
                    <input type="hidden" id="mix-points-price" name="mix_points_price" value="">
                    <input type="hidden" id="mix-points-earned" name="mix_points_earned" value="">
                    
                    <div class="form-row">
                        <label for="mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> <span class="required">*</span></label>
                        <input type="text" id="mix-name" name="mix_name" required>
                    </div>
                    
                    <div class="form-row">
                        <label for="mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?> <span class="required">*</span></label>
                        <textarea id="mix-description" name="mix_description" rows="4" required placeholder="<?php _e('Describe your mix: flavor profile, usage suggestions, benefits, etc.', 'herbal-mix-creator2'); ?>"></textarea>
                        <p class="field-hint"><?php _e('This description will be visible to everyone in the shop.', 'herbal-mix-creator2'); ?></p>
                    </div>
                    
                    <!-- Image upload for publish -->
                    <div class="form-row image-upload-row">
                        <label for="mix-image"><?php _e('Image', 'herbal-mix-creator2'); ?> <span class="required">*</span></label>
                        <div class="custom-file-upload">
                            <div id="mix-image_preview" class="image-preview">
                                <div class="upload-prompt">
                                    <i class="upload-icon"></i>
                                    <span><?php _e('Click to upload a new image', 'herbal-mix-creator2'); ?></span>
                                </div>
                            </div>
                            
                            <!-- Progress bar -->
                            <div id="mix-image_progress_container" class="upload-progress-container" style="display: none;">
                                <div class="upload-progress-bar-wrapper">
                                    <div id="mix-image_progress_bar" class="upload-progress-bar"></div>
                                </div>
                                <div id="mix-image_progress_text" class="upload-progress-text">0%</div>
                            </div>
                            
                            <!-- File input -->
                            <input type="file" id="mix-image_file" name="mix_image_file" accept="image/*" style="display:none;" class="herbal-mix-file-input" data-target="mix-image">
                            
                            <!-- Hidden field for image URL -->
                            <input type="hidden" id="mix-image" name="mix_image" required class="herbal-mix-image-input">
                            
                            <!-- Buttons -->
                            <div class="image-upload-buttons">
                                <button type="button" id="mix-image_select_btn" class="button herbal-mix-select-image-btn" data-target="mix-image"><?php _e('Upload New Image', 'herbal-mix-creator2'); ?></button>
                                <button type="button" id="mix-image_remove_btn" class="button button-secondary herbal-mix-remove-image-btn" data-target="mix-image" style="display:none;"><?php _e('Remove', 'herbal-mix-creator2'); ?></button>
                            </div>
                            
                            <p class="field-hint"><?php _e('Upload a new image for your mix (recommended size: 800x800px)', 'herbal-mix-creator2'); ?></p>
                            <p id="mix-image_error" class="error-message" style="display: none;"></p>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" id="publish-button" class="button button-primary" disabled><?php _e('Publish Mix', 'herbal-mix-creator2'); ?></button>
                        <button type="button" class="button cancel-modal"><?php _e('Cancel', 'herbal-mix-creator2'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- JavaScript dla obsługi zakładek -->
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
            
            // Sprawdź czy herbalProfileData istnieje
            if (typeof herbalProfileData === 'undefined') {
                console.error('herbalProfileData not defined, creating fallback');
                window.herbalProfileData = {
                    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                    getNonce: '<?php echo wp_create_nonce('get_mix_details'); ?>',
                    updateMixNonce: '<?php echo wp_create_nonce('update_mix_details'); ?>',
                    publishNonce: '<?php echo wp_create_nonce('publish_mix'); ?>',
                    recipeNonce: '<?php echo wp_create_nonce('get_recipe_pricing'); ?>',
                    uploadImageNonce: '<?php echo wp_create_nonce('upload_mix_image'); ?>',
                    deleteMixNonce: '<?php echo wp_create_nonce('delete_mix'); ?>',
                    deleteMixConfirm: '<?php _e('Are you sure you want to delete this mix? This action cannot be undone.', 'herbal-mix-creator2'); ?>'
                };
            }
            
            // ENHANCED DELETE MIX HANDLER z AJAX i przekierowaniem
            $(document).on('click', '.delete-mix', function(e) {
                e.preventDefault();
                console.log('Delete mix clicked');
                
                const mixId = $(this).data('mix-id');
                if (!mixId) {
                    alert('No mix ID found');
                    return;
                }
                
                // Pokaż dialog potwierdzenia
                if (!confirm(herbalProfileData.deleteMixConfirm || 'Are you sure you want to delete this mix? This action cannot be undone.')) {
                    return;
                }
                
                // Zablokuj przycisk i pokaż stan ładowania
                const $deleteBtn = $(this);
                const originalText = $deleteBtn.text();
                $deleteBtn.prop('disabled', true).text('<?php _e('Deleting...', 'herbal-mix-creator2'); ?>');
                
                // Wyślij żądanie AJAX
                $.ajax({
                    url: herbalProfileData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'delete_mix',
                        mix_id: mixId,
                        nonce: herbalProfileData.deleteMixNonce || herbalProfileData.getNonce
                    },
                    success: function(response) {
                        console.log('Delete response:', response);
                        
                        if (response.success) {
                            // Pokaż komunikat sukcesu
                            alert(response.data.message || '<?php _e('Mix deleted successfully.', 'herbal-mix-creator2'); ?>');
                            
                            // Przekieruj do listy mieszanek
                            if (response.data.redirect_url) {
                                window.location.href = response.data.redirect_url;
                            } else {
                                // Fallback - odśwież stronę
                                location.reload();
                            }
                        } else {
                            console.error('Delete error:', response.data);
                            alert('Error: ' + (response.data || 'Unknown error occurred'));
                            
                            // Przywróć przycisk
                            $deleteBtn.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error, xhr.responseText);
                        alert('<?php _e('Connection error. Please try again.', 'herbal-mix-creator2'); ?>');
                        
                        // Przywróć przycisk
                        $deleteBtn.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        </script>
        
    <?php endif; ?>
</div>

<style>
/* Dodatkowe style dla template'u My Mixes */
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

.dashboard-header h2 {
    margin: 0;
    color: #2c3e50;
}

.mixes-tabs {
    margin-top: 20px;
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

.tab-content {
    background: #fff;
    padding: 20px;
    border: 1px solid #e1e1e1;
    border-radius: 0 5px 5px 5px;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.woocommerce-orders-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.woocommerce-orders-table th,
.woocommerce-orders-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e1e1e1;
}

.woocommerce-orders-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    display: inline-block;
}

.status-published {
    background: #d4edda;
    color: #155724;
}

.status-private,
.status-not_public {
    background: #fff3cd;
    color: #856404;
}

.mix-actions {
    white-space: nowrap;
}

.mix-actions .button {
    margin-right: 5px;
    margin-bottom: 5px;
    padding: 6px 12px;
    font-size: 12px;
}

.button-danger {
    background: #dc3545;
    border-color: #dc3545;
    color: #fff;
}

.button-danger:hover {
    background: #c82333;
    border-color: #c82333;
}

.no-mixes-message {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.description {
    color: #666;
    font-style: italic;
}

.product-link a {
    color: #007cba;
    text-decoration: none;
}

.product-link a:hover {
    text-decoration: underline;
}

/* Modal Styles */
.modal-dialog {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
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

.mix-summary {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.mix-recipe-preview h4 {
    margin: 0 0 15px 0;
    color: #2c3e50;
}

.ingredients-list {
    margin-bottom: 15px;
}

.ingredient-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e1e1e1;
}

.ingredient-item:last-child {
    border-bottom: none;
}

.mix-pricing {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.price-item {
    display: flex;
    flex-direction: column;
    text-align: center;
    padding: 10px;
    background: #fff;
    border-radius: 5px;
    border: 1px solid #e1e1e1;
}

.price-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 5px;
}

.price-value {
    font-weight: 600;
    color: #2c3e50;
    font-size: 16px;
}

.form-row {
    margin-bottom: 20px;
}

.form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #2c3e50;
}

.required {
    color: #dc3545;
}

.form-row input[type="text"],
.form-row textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-row textarea {
    resize: vertical;
    min-height: 100px;
}

.field-hint {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
    margin-bottom: 0;
}

.form-actions {
    margin-top: 30px;
    text-align: right;
}

.form-actions .button {
    margin-left: 10px;
}

/* Image Upload Styles */
.image-upload-row {
    margin-bottom: 25px;
}

.custom-file-upload {
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    background: #fafafa;
    transition: all 0.3s ease;
}

.custom-file-upload:hover {
    border-color: #007cba;
    background: #f8f9fa;
}

.image-preview {
    margin-bottom: 15px;
    min-height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border-radius: 4px;
    position: relative;
    overflow: hidden;
}

.image-preview img {
    max-width: 100%;
    max-height: 200px;
    object-fit: cover;
}

.upload-prompt {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #666;
}

.upload-icon::before {
    content: "📷";
    font-size: 48px;
    margin-bottom: 10px;
}

.upload-progress-container {
    margin: 15px 0;
}

.upload-progress-bar-wrapper {
    width: 100%;
    height: 20px;
    background: #e1e1e1;
    border-radius: 10px;
    overflow: hidden;
}

.upload-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #007cba, #005a87);
    width: 0%;
    transition: width 0.3s ease;
}

.upload-progress-text {
    text-align: center;
    margin-top: 5px;
    font-size: 14px;
    color: #666;
}

.image-upload-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
}

.error-message {
    color: #dc3545;
    font-size: 14px;
    margin-top: 10px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .tab-navigation {
        flex-direction: column;
    }
    
    .tab-navigation li {
        margin-right: 0;
        margin-bottom: 2px;
    }
    
    .modal-content {
        width: 95%;
        padding: 20px;
        margin: 10px;
    }
    
    .mix-pricing {
        grid-template-columns: 1fr;
    }
    
    .woocommerce-orders-table {
        font-size: 14px;
    }
    
    .woocommerce-orders-table th,
    .woocommerce-orders-table td {
        padding: 8px;
    }
    
    .mix-actions {
        white-space: normal;
    }
    
    .mix-actions .button {
        display: block;
        width: 100%;
        margin-right: 0;
        margin-bottom: 5px;
    }
}

/* Print Styles */
@media print {
    .mix-actions,
    .dashboard-header .button,
    .tab-navigation,
    .modal-dialog {
        display: none !important;
    }
    
    .tab-pane {
        display: block !important;
    }
}
</style>