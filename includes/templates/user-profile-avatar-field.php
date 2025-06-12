<p class="woocommerce-form-row form-row">
    <label for="custom_avatar"><?php _e('Profile Picture', 'herbal-mix-creator2'); ?></label>
    
    <div class="avatar-upload-container">
        <?php if ($avatar_url) : ?>
            <div class="current-avatar">
                <img src="<?php echo esc_url($avatar_url); ?>" alt="<?php _e('Your avatar', 'herbal-mix-creator2'); ?>">
            </div>
        <?php else : ?>
            <div class="avatar-placeholder">
                <?php echo get_avatar($user_id, 96); ?>
            </div>
        <?php endif; ?>
        
        <input type="hidden" id="custom_avatar" name="custom_avatar" value="<?php echo esc_attr($avatar_url); ?>">
        <button type="button" class="button" id="upload-avatar-btn"><?php _e('Upload Profile Picture', 'herbal-mix-creator2'); ?></button>
        <?php if ($avatar_url) : ?>
            <button type="button" class="button button-secondary" id="remove-avatar-btn"><?php _e('Remove', 'herbal-mix-creator2'); ?></button>
        <?php endif; ?>
    </div>
</p>

<script>
jQuery(document).ready(function($) {
    $('#upload-avatar-btn').on('click', function(e) {
        e.preventDefault();
        
        const avatarUploader = wp.media({
            title: '<?php _e('Select or Upload Profile Picture', 'herbal-mix-creator2'); ?>',
            button: { text: '<?php _e('Use this image', 'herbal-mix-creator2'); ?>' },
            multiple: false
        });
        
        avatarUploader.on('select', function() {
            const attachment = avatarUploader.state().get('selection').first().toJSON();
            $('#custom_avatar').val(attachment.url);
            
            // Aktualizuj wyświetlany avatar
            $('.avatar-upload-container .current-avatar, .avatar-placeholder').remove();
            $('.avatar-upload-container').prepend('<div class="current-avatar"><img src="' + attachment.url + '" alt="<?php _e('Your avatar', 'herbal-mix-creator2'); ?>"></div>');
            
            // Dodaj przycisk usuwania, jeśli nie istnieje
            if ($('#remove-avatar-btn').length === 0) {
                $('.avatar-upload-container').append('<button type="button" class="button button-secondary" id="remove-avatar-btn"><?php _e('Remove', 'herbal-mix-creator2'); ?></button>');
                
                // Dodajemy event listener dla nowego przycisku
                $('#remove-avatar-btn').on('click', function() {
                    $('#custom_avatar').val('');
                    $('.current-avatar').remove();
                    $('.avatar-upload-container').prepend('<div class="avatar-placeholder"><?php echo get_avatar($user_id, 96); ?></div>');
                    $(this).remove();
                });
            }
        });
        
        avatarUploader.open();
    });
    
    $('#remove-avatar-btn').on('click', function() {
        $('#custom_avatar').val('');
        $('.current-avatar').remove();
        $('.avatar-upload-container').prepend('<div class="avatar-placeholder"><?php echo get_avatar($user_id, 96); ?></div>');
        $(this).remove();
    });
});
</script>