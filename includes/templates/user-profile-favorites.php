<div class="herbal-favorites-dashboard">
    <h2><?php _e('Your Favorite Mixes', 'herbal-mix-creator2'); ?></h2>
    
    <?php if (empty($favorites)) : ?>
        <div class="woocommerce-message woocommerce-message--info">
            <p><?php _e('You don\'t have any favorite mixes yet.', 'herbal-mix-creator2'); ?></p>
            <a href="<?php echo esc_url(get_permalink(get_page_by_path('herbal-mix-creator'))); ?>" class="button"><?php _e('Create Your First Mix', 'herbal-mix-creator2'); ?></a>
        </div>
    <?php else : ?>
        <div class="favorites-grid">
            <?php foreach ($favorites as $mix) : 
                $mix_data = json_decode($mix->mix_data, true);
                $mix_author = get_user_by('id', $mix->user_id);
                ?>
                <div class="favorite-mix-card">
                    <?php if (!empty($mix->mix_image)) : ?>
                        <img src="<?php echo esc_url($mix->mix_image); ?>" class="mix-thumbnail" alt="<?php echo esc_attr($mix->mix_name); ?>">
                    <?php else : ?>
                        <div class="mix-thumbnail-placeholder"><?php echo esc_html(substr($mix->mix_name, 0, 1)); ?></div>
                    <?php endif; ?>
                    
                    <h3 class="mix-name"><?php echo esc_html($mix->mix_name); ?></h3>
                    
                    <div class="mix-meta">
                        <span class="mix-author"><?php _e('by', 'herbal-mix-creator2'); ?> <?php echo esc_html($mix_author ? $mix_author->display_name : __('Unknown', 'herbal-mix-creator2')); ?></span>
                        <span class="mix-date"><?php echo date_i18n(get_option('date_format'), strtotime($mix->created_at)); ?></span>
                    </div>
                    
                    <?php if (!empty($mix->mix_description)) : ?>
                        <div class="mix-description">
                            <?php echo wp_trim_words(esc_html($mix->mix_description), 20, '...'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mix-actions">
                        <a href="?action=buy_mix&mix_id=<?php echo esc_attr($mix->id); ?>" class="button buy-mix-btn"><?php _e('Buy', 'herbal-mix-creator2'); ?></a>
                        <a href="#" class="button button-secondary remove-favorite" data-mix-id="<?php echo esc_attr($mix->id); ?>"><?php _e('Remove', 'herbal-mix-creator2'); ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>