<?php
/**
 * KOMPLETNY: User Profile My Mixes Template z modalem Publish
 * Plik: includes/templates/user-profile-my-mixes.php
 * 
 * PEŁNA WERSJA z:
 * - Modal Edit (istniejący)
 * - Modal Publish (nowy z ostrzeżeniem)
 * - Obsługa przycisków Edit/Publish/Delete/View/Buy
 * - Zgodność z bazą danych i resztą projektu
 */

if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();

// Pobierz mieszanki użytkownika używając klasy bazy danych
if (class_exists('Herbal_Mix_Database')) {
    $mixes = Herbal_Mix_Database::get_user_mixes($user_id);
} else {
    $mixes = array();
}
?>

<div class="herbal-my-mixes">
    <div class="mixes-header">
        <h3><?php _e('My Custom Mixes', 'herbal-mix-creator2'); ?></h3>
        <p class="mixes-description">
            <?php _e('Manage your saved herbal mixes. You can edit details, publish to the community, or delete mixes you no longer need.', 'herbal-mix-creator2'); ?>
        </p>
    </div>

    <?php if (empty($mixes)): ?>
        <div class="no-mixes-message">
            <div class="empty-state">
                <h4><?php _e('No Mixes Yet', 'herbal-mix-creator2'); ?></h4>
                <p><?php _e('You haven\'t created any custom mixes yet. Start creating your first herbal blend!', 'herbal-mix-creator2'); ?></p>
                <?php 
                $creator_page = get_posts(array(
                    'post_type' => 'page',
                    'meta_query' => array(
                        array(
                            'key' => '_wp_page_template',
                            'value' => 'page-herbal-mix-creator.php',
                            'compare' => 'LIKE'
                        )
                    ),
                    'posts_per_page' => 1
                ));
                if (!empty($creator_page) || get_option('herbal_mix_creator_page_id')):
                    $page_url = !empty($creator_page) ? get_permalink($creator_page[0]->ID) : get_permalink(get_option('herbal_mix_creator_page_id'));
                ?>
                    <a href="<?php echo esc_url($page_url); ?>" class="button button-primary">
                        <?php _e('Create Your First Mix', 'herbal-mix-creator2'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="mixes-content">
            <div class="mixes-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo count($mixes); ?></span>
                    <span class="stat-label"><?php _e('Total Mixes', 'herbal-mix-creator2'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo count(array_filter($mixes, function($mix) { return $mix->status === 'published'; })); ?></span>
                    <span class="stat-label"><?php _e('Published', 'herbal-mix-creator2'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo count(array_filter($mixes, function($mix) { return $mix->status === 'favorite'; })); ?></span>
                    <span class="stat-label"><?php _e('Favorites', 'herbal-mix-creator2'); ?></span>
                </div>
            </div>

            <div class="mixes-table-container">
                <table class="herbal-mixes-table">
                    <thead>
                        <tr>
                            <th><?php _e('Mix Name', 'herbal-mix-creator2'); ?></th>
                            <th><?php _e('Description', 'herbal-mix-creator2'); ?></th>
                            <th><?php _e('Status', 'herbal-mix-creator2'); ?></th>
                            <th><?php _e('Created', 'herbal-mix-creator2'); ?></th>
                            <th><?php _e('Actions', 'herbal-mix-creator2'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mixes as $mix): ?>
                            <tr data-mix-id="<?php echo esc_attr($mix->id); ?>">
                                <td class="mix-name">
                                    <strong><?php echo esc_html($mix->mix_name); ?></strong>
                                    <?php if ($mix->mix_image): ?>
                                        <img src="<?php echo esc_url($mix->mix_image); ?>" alt="<?php echo esc_attr($mix->mix_name); ?>" class="mix-thumbnail">
                                    <?php endif; ?>
                                </td>
                                <td class="mix-description">
                                    <?php echo esc_html(wp_trim_words($mix->mix_description, 15)); ?>
                                </td>
                                <td class="mix-status">
                                    <span class="status-badge status-<?php echo esc_attr($mix->status); ?>">
                                        <?php 
                                        switch($mix->status) {
                                            case 'published':
                                                _e('Published', 'herbal-mix-creator2');
                                                break;
                                            case 'favorite':
                                                _e('Favorite', 'herbal-mix-creator2');
                                                break;
                                            default:
                                                _e('Draft', 'herbal-mix-creator2');
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="mix-date">
                                    <?php echo esc_html(mysql2date('M j, Y', $mix->created_at)); ?>
                                </td>
                                <td class="mix-actions">
                                    <?php if ($mix->status !== 'published'): ?>
                                        <button type="button" class="button button-small edit-mix" data-mix-id="<?php echo esc_attr($mix->id); ?>">
                                            <?php _e('Edit', 'herbal-mix-creator2'); ?>
                                        </button>
                                        
                                        <button type="button" class="button button-small show-publish-modal" data-mix-id="<?php echo esc_attr($mix->id); ?>">
                                            <?php _e('Publish', 'herbal-mix-creator2'); ?>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="button button-small view-mix" data-mix-id="<?php echo esc_attr($mix->id); ?>">
                                            <?php _e('View', 'herbal-mix-creator2'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="button button-small buy-mix" data-mix-id="<?php echo esc_attr($mix->id); ?>">
                                        <?php _e('Buy', 'herbal-mix-creator2'); ?>
                                    </button>
                                    
                                    <button type="button" class="button button-small button-danger delete-mix" data-mix-id="<?php echo esc_attr($mix->id); ?>">
                                        <?php _e('Delete', 'herbal-mix-creator2'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL EDIT - Istniejący -->
<div id="edit-mix-modal" class="modal-dialog" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Edit Mix Details', 'herbal-mix-creator2'); ?></h3>
            <button type="button" class="modal-close close-modal">&times;</button>
        </div>
        
        <div class="mix-summary">
            <div class="mix-recipe-preview">
                <h4><?php _e('Mix Recipe', 'herbal-mix-creator2'); ?></h4>
                <div id="edit-mix-ingredients-preview" class="ingredients-list">
                    <!-- Recipe data loaded by JavaScript -->
                </div>
            </div>
            
            <div class="mix-pricing">
                <div class="price-item">
                    <span class="price-label"><?php _e('Total Price:', 'herbal-mix-creator2'); ?></span>
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
        
        <form id="edit-mix-form">
            <input type="hidden" id="edit-mix-id" name="mix_id" value="">
            
            <div class="form-group">
                <label for="edit-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> *</label>
                <input type="text" id="edit-mix-name" name="mix_name" required>
            </div>
            
            <div class="form-group">
                <label for="edit-mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?></label>
                <textarea id="edit-mix-description" name="mix_description" rows="4" 
                         placeholder="<?php _e('Describe your mix: flavor profile, usage suggestions, benefits, etc.', 'herbal-mix-creator2'); ?>"></textarea>
            </div>
            
            <div class="form-group">
                <label for="edit-mix-image-input"><?php _e('Mix Image', 'herbal-mix-creator2'); ?></label>
                <input type="hidden" id="edit-mix-image" name="mix_image" value="">
                <input type="file" id="edit-mix-image-input" accept="image/*">
                <div class="image-preview">
                    <img id="edit-mix-image-preview" src="" alt="" style="display:none;">
                </div>
                <button type="button" id="edit-mix-image-remove" class="button" style="display:none;">
                    <?php _e('Remove Image', 'herbal-mix-creator2'); ?>
                </button>
            </div>
            
            <div class="form-actions">
                <button type="submit" id="edit-update-button" class="button button-primary" disabled>
                    <?php _e('Update Mix', 'herbal-mix-creator2'); ?>
                </button>
                <button type="button" class="button button-secondary cancel-modal">
                    <?php _e('Cancel', 'herbal-mix-creator2'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PUBLISH - Nowy z ostrzeżeniem -->
<div id="publish-mix-modal" class="modal-dialog" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Publish Mix to Community', 'herbal-mix-creator2'); ?></h3>
            <button type="button" class="modal-close close-modal">&times;</button>
        </div>
        
        <!-- OSTRZEŻENIE O PUBLIKACJI -->
        <div class="publish-warning">
            <div class="warning-box">
                <h4><?php _e('⚠️ Important Notice', 'herbal-mix-creator2'); ?></h4>
                <p><?php _e('After publishing your mix:', 'herbal-mix-creator2'); ?></p>
                <ul>
                    <li><?php _e('Your mix will be visible to all community members', 'herbal-mix-creator2'); ?></li>
                    <li><?php _e('Other users will be able to purchase your mix', 'herbal-mix-creator2'); ?></li>
                    <li><strong><?php _e('You will NOT be able to edit the recipe after publishing', 'herbal-mix-creator2'); ?></strong></li>
                    <li><?php _e('You can only update the name, description, and image', 'herbal-mix-creator2'); ?></li>
                    <li><?php _e('A product will be created in the store using Custom User Mix template', 'herbal-mix-creator2'); ?></li>
                </ul>
                <p><strong><?php _e('Make sure your mix details are correct before proceeding!', 'herbal-mix-creator2'); ?></strong></p>
            </div>
        </div>
        
        <!-- PODGLĄD RECEPTURY -->
        <div class="mix-summary">
            <div class="mix-recipe-preview">
                <h4><?php _e('Mix Recipe (Final Version)', 'herbal-mix-creator2'); ?></h4>
                <div id="publish-mix-ingredients-preview" class="ingredients-list">
                    <!-- Recipe data loaded by JavaScript -->
                </div>
            </div>
            
            <div class="mix-pricing">
                <div class="price-item">
                    <span class="price-label"><?php _e('Product Price:', 'herbal-mix-creator2'); ?></span>
                    <span class="price-value" id="publish-mix-price">£0.00</span>
                </div>
                <div class="price-item">
                    <span class="price-label"><?php _e('Price in Points:', 'herbal-mix-creator2'); ?></span>
                    <span class="price-value" id="publish-mix-points-price">0 pts</span>
                </div>
                <div class="price-item">
                    <span class="price-label"><?php _e('You will earn:', 'herbal-mix-creator2'); ?></span>
                    <span class="price-value" id="publish-mix-points-earned">50 pts</span>
                </div>
            </div>
        </div>
        
        <!-- FORMULARZ PUBLIKACJI -->
        <form id="publish-mix-form">
            <input type="hidden" id="publish-mix-id" name="mix_id" value="">
            
            <div class="form-group">
                <label for="publish-mix-name"><?php _e('Product Name', 'herbal-mix-creator2'); ?> *</label>
                <input type="text" id="publish-mix-name" name="mix_name" required>
                <small><?php _e('This name will appear in the community store', 'herbal-mix-creator2'); ?></small>
            </div>
            
            <div class="form-group">
                <label for="publish-mix-description"><?php _e('Product Description', 'herbal-mix-creator2'); ?></label>
                <textarea id="publish-mix-description" name="mix_description" rows="5" 
                         placeholder="<?php _e('Describe your mix for potential customers: taste, benefits, usage instructions, etc.', 'herbal-mix-creator2'); ?>"></textarea>
                <small><?php _e('A good description helps customers understand your mix better', 'herbal-mix-creator2'); ?></small>
            </div>
            
            <div class="form-group">
                <label for="publish-mix-image-input"><?php _e('Product Image', 'herbal-mix-creator2'); ?></label>
                <input type="hidden" id="publish-mix-image" name="mix_image" value="">
                <input type="file" id="publish-mix-image-input" accept="image/*">
                <div class="image-preview">
                    <img id="publish-mix-image-preview" src="" alt="" style="display:none;">
                </div>
                <button type="button" id="publish-mix-image-remove" class="button" style="display:none;">
                    <?php _e('Remove Image', 'herbal-mix-creator2'); ?>
                </button>
                <small><?php _e('Upload an attractive image to showcase your mix (optional)', 'herbal-mix-creator2'); ?></small>
            </div>
            
            <!-- CHECKBOX POTWIERDZENIA -->
            <div class="form-group">
                <label class="checkbox-container">
                    <input type="checkbox" id="publish-confirm" required>
                    <span class="checkmark"></span>
                    <?php _e('I understand that I cannot change the recipe after publishing', 'herbal-mix-creator2'); ?>
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" id="publish-button" class="button button-primary publish-button" disabled>
                    <?php _e('🚀 Publish Mix to Community', 'herbal-mix-creator2'); ?>
                </button>
                <button type="button" class="button button-secondary cancel-modal">
                    <?php _e('Cancel', 'herbal-mix-creator2'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Style dla ostrzeżenia publikacji */
.publish-warning {
    padding: 20px 30px;
    background: #fff3cd;
    border-bottom: 1px solid #ffeaa7;
}

.warning-box {
    background: #fff;
    border: 2px solid #f39c12;
    border-radius: 8px;
    padding: 20px;
}

.warning-box h4 {
    margin: 0 0 15px 0;
    color: #d68910;
    font-size: 1.1em;
}

.warning-box ul {
    margin: 10px 0;
    padding-left: 20px;
}

.warning-box li {
    margin-bottom: 8px;
    color: #856404;
}

.warning-box strong {
    color: #d68910;
}

/* Style dla checkbox */
.checkbox-container {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
    font-size: 14px;
    line-height: 1.4;
}

.checkbox-container input[type="checkbox"] {
    width: auto;
    margin: 0;
}

/* Dodatkowe style dla form-group small */
.form-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-style: italic;
    font-size: 0.9em;
}

/* Style dla przyciska publikacji */
.publish-button {
    background: #27ae60 !important;
    border-color: #27ae60 !important;
    font-weight: 600;
}

.publish-button:hover:not(:disabled) {
    background: #219a52 !important;
    border-color: #219a52 !important;
}

.publish-button:disabled {
    background: #bdc3c7 !important;
    border-color: #bdc3c7 !important;
    cursor: not-allowed;
}

/* Pozostałe style */
.mixes-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
}

.mixes-header h3 {
    margin: 0 0 10px 0;
    color: #2c3e50;
    font-size: 1.8em;
}

.mixes-description {
    color: #666;
    margin: 0;
    font-size: 1.1em;
}

.no-mixes-message {
    text-align: center;
    padding: 60px 20px;
}

.empty-state h4 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 1.4em;
}

.empty-state p {
    color: #666;
    margin-bottom: 25px;
    font-size: 1.1em;
}

.mixes-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.stat-item {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    flex: 1;
    min-width: 120px;
    border-left: 4px solid #3498db;
}

.stat-number {
    display: block;
    font-size: 2em;
    font-weight: bold;
    color: #2c3e50;
}

.stat-label {
    color: #666;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mixes-table-container {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.mix-thumbnail {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    margin-left: 10px;
    border: 2px solid #ddd;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-published {
    background: #d4edda;
    color: #155724;
}

.status-favorite {
    background: #fff3cd;
    color: #856404;
}

.status-draft {
    background: #f8d7da;
    color: #721c24;
}

.button-danger {
    background: #e74c3c;
    color: #fff;
}

.button-danger:hover {
    background: #c0392b;
}

@media (max-width: 768px) {
    .mixes-stats {
        flex-direction: column;
    }
    
    .stat-item {
        text-align: left;
    }
    
    .herbal-mixes-table th:nth-child(2),
    .herbal-mixes-table td:nth-child(2),
    .herbal-mixes-table th:nth-child(4),
    .herbal-mixes-table td:nth-child(4) {
        display: none;
    }
}
</style>