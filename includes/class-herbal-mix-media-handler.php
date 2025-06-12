<?php
/**
 * Klasa obsługująca media (zdjęcia, obrazy) w Herbal Mix Creator
 * Umożliwia tylko przesyłanie nowych plików, bez dostępu do biblioteki mediów
 *
 * @package HerbalMixCreator2
 */

if (!defined('ABSPATH')) exit;

class HerbalMixMediaHandler {

    /**
     * Konstruktor - inicjalizuje hooki AJAX
     */
    public function __construct() {
        // Hooki AJAX do obsługi mediów
        add_action('wp_ajax_upload_mix_image', array($this, 'ajax_upload_image'));
        add_action('wp_ajax_upload_avatar', array($this, 'ajax_upload_avatar'));
        
        // Ładowanie skryptów i styli
        add_action('wp_enqueue_scripts', array($this, 'enqueue_media_scripts'));
    }
    
    /**
     * Dodaje skrypty i style dla przesyłania mediów w panelu frontendu
     */
    public function enqueue_media_scripts() {
        // Sprawdź czy jesteśmy na stronie konta lub edycji mieszanki
        if (is_account_page() || is_page('edit-my-mix') || is_page('herbal-mix-creator')) {
            wp_enqueue_script(
                'herbal-media-handler',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/media-handler.js',
                array('jquery'),
                filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/media-handler.js'),
                true
            );
            
            // Przekaż dane do skryptu
            wp_localize_script('herbal-media-handler', 'herbalMediaData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'uploadImageNonce' => wp_create_nonce('upload_image_nonce'),
                'uploadAvatarNonce' => wp_create_nonce('upload_avatar_nonce'),
                'maxFileSize' => $this->get_max_upload_size(),
                'allowedTypes' => array('image/jpeg', 'image/png', 'image/gif'),
                'messages' => array(
                    'tooLarge' => __('File is too large. Maximum size is', 'herbal-mix-creator2'),
                    'wrongType' => __('Invalid file type. Only JPEG, PNG and GIF images are allowed.', 'herbal-mix-creator2'),
                    'uploadSuccess' => __('Image uploaded successfully.', 'herbal-mix-creator2'),
                    'uploadError' => __('Error uploading image.', 'herbal-mix-creator2'),
                    'connectionError' => __('Connection error. Please try again.', 'herbal-mix-creator2')
                )
            ));
            
            // Style
            wp_enqueue_style(
                'herbal-media-styles',
                plugin_dir_url(dirname(__FILE__)) . 'assets/css/media-handler.css',
                array(),
                filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/media-handler.css')
            );
        }
    }
    
    /**
     * Obsługuje przesyłanie obrazu za pomocą AJAX
     */
    public function ajax_upload_image() {
        // Weryfikacja nonce
        if (!check_ajax_referer('upload_image_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed. Please refresh the page.');
            return;
        }
        
        // Sprawdź czy użytkownik jest zalogowany
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to upload images.');
            return;
        }
        
        // Wykonaj przesyłanie obrazu
        $result = $this->handle_image_upload('mix_image');
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Obsługuje przesyłanie avatara za pomocą AJAX
     */
    public function ajax_upload_avatar() {
        // Weryfikacja nonce
        if (!check_ajax_referer('upload_avatar_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed. Please refresh the page.');
            return;
        }
        
        // Tylko zalogowani użytkownicy mogą przesyłać avatary
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to upload avatar.');
            return;
        }
        
        // Przesyłanie avatara
        $result = $this->handle_image_upload('avatar_image');
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            // Aktualizuj avatar użytkownika w meta danych
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'custom_avatar', $result['url']);
            
            wp_send_json_success($result);
        }
    }
    
    /**
     * Uniwersalna funkcja do obsługi przesyłania obrazów
     * Obsługuje tylko nowe pliki - bez dostępu do biblioteki mediów
     *
     * @param string $file_key Klucz tablicy $_FILES dla przesłanego pliku
     * @return array|WP_Error Dane przesłanego obrazu lub obiekt błędu
     */
    private function handle_image_upload($file_key) {
        // Sprawdź, czy plik został przesłany
        if (empty($_FILES[$file_key]) || !isset($_FILES[$file_key]['tmp_name']) || empty($_FILES[$file_key]['tmp_name'])) {
            return new WP_Error('no_file', 'No file was uploaded or upload error occurred.');
        }
        
        $file = $_FILES[$file_key];
        
        // Sprawdź błędy przesyłania
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', $this->get_upload_error_message($file['error']));
        }
        
        // Sprawdź typ MIME pliku
        $file_info = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        if (!$file_info['type'] || !in_array($file_info['type'], $allowed_types)) {
            return new WP_Error('invalid_type', 'Invalid file type. Only JPEG, PNG and GIF images are allowed.');
        }
        
        // Sprawdź rozmiar pliku
        $max_size = $this->get_max_upload_size();
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File is too large. Maximum size is ' . size_format($max_size) . '.');
        }
        
        // Sprawdź czy plik to rzeczywiście obraz
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return new WP_Error('invalid_image', 'The uploaded file is not a valid image.');
        }
        
        // Ograniczenia rozmiaru obrazu (opcjonalne)
        if ($image_info[0] > 2000 || $image_info[1] > 2000) {
            return new WP_Error('image_too_large', 'Image dimensions are too large. Maximum size is 2000x2000 pixels.');
        }
        
        // Upewnij się, że wymagane klasy są załadowane
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Dodaj filtr dla dozwolonych typów MIME
        add_filter('upload_mimes', array($this, 'add_allowed_mimes'));
        
        // Ustaw unikalne nazwy plików
        add_filter('wp_unique_filename_callback', array($this, 'unique_filename_callback'));
        
        // Konfiguruj upload
        $upload_overrides = array(
            'test_form' => false,
            'unique_filename_callback' => array($this, 'unique_filename_callback')
        );
        
        // Obsłuż przesyłanie pliku
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        // Usuń filtry
        remove_filter('upload_mimes', array($this, 'add_allowed_mimes'));
        remove_filter('wp_unique_filename_callback', array($this, 'unique_filename_callback'));
        
        if (isset($uploaded_file['error'])) {
            return new WP_Error('upload_failed', $uploaded_file['error']);
        }
        
        // Utwórz attachment w bazie danych
        $attachment_id = $this->create_attachment($uploaded_file);
        
        if (is_wp_error($attachment_id)) {
            // Usuń plik jeśli nie udało się utworzyć attachment
            if (file_exists($uploaded_file['file'])) {
                unlink($uploaded_file['file']);
            }
            return $attachment_id;
        }
        
        // Zwróć dane załącznika
        return array(
            'id' => $attachment_id,
            'url' => $uploaded_file['url'],
            'type' => $file_info['type'],
            'filename' => basename($uploaded_file['file'])
        );
    }
    
    /**
     * Tworzy attachment w bazie danych WordPress
     */
    private function create_attachment($uploaded_file) {
        // Przygotuj dane załącznika
        $file_path = $uploaded_file['file'];
        $file_url = $uploaded_file['url'];
        $file_type = $uploaded_file['type'];
        
        // Przygotuj tytuł na podstawie nazwy pliku
        $attachment_title = sanitize_file_name(pathinfo($file_path, PATHINFO_FILENAME));
        
        // Dane załącznika
        $attachment_data = array(
            'guid' => $file_url,
            'post_mime_type' => $file_type,
            'post_title' => $attachment_title,
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Wstaw załącznik do bazy danych
        $attachment_id = wp_insert_attachment($attachment_data, $file_path);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Upewnij się, że potrzebne funkcje są załadowane
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        // Wygeneruj metadane załącznika (miniatury itp.)
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);
        
        // Dodaj dodatkowe meta dane
        update_post_meta($attachment_id, '_herbal_mix_upload', true);
        update_post_meta($attachment_id, '_herbal_mix_uploader', get_current_user_id());
        update_post_meta($attachment_id, '_herbal_mix_upload_date', current_time('mysql'));
        
        return $attachment_id;
    }
    
    /**
     * Callback dla unikalnych nazw plików
     */
    public function unique_filename_callback($filename, $ext, $dir) {
        // Dodaj timestamp i random string dla unikalności
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $timestamp = current_time('timestamp');
        $random = wp_generate_password(8, false);
        
        return sanitize_file_name($name . '_' . $timestamp . '_' . $random . $ext);
    }
    
    /**
     * Dodaje dozwolone typy MIME dla przesyłanych plików
     */
    public function add_allowed_mimes($mimes) {
        return array_merge($mimes, array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        ));
    }
    
    /**
     * Pobiera maksymalny dozwolony rozmiar przesyłanego pliku
     */
    public function get_max_upload_size() {
        // Ustaw domyślny maksymalny rozmiar na 5MB
        $max_size = 5 * 1024 * 1024;
        
        // Sprawdź limity serwera
        $upload_max = wp_convert_hr_to_bytes(ini_get('upload_max_filesize'));
        $post_max = wp_convert_hr_to_bytes(ini_get('post_max_size'));
        $wp_max = wp_max_upload_size();
        
        // Użyj najmniejszego limitu
        $server_max = min($upload_max, $post_max, $wp_max);
        
        if ($server_max && $server_max < $max_size) {
            $max_size = $server_max;
        }
        
        return $max_size;
    }
    
    /**
     * Zwraca komunikat o błędzie przesyłania pliku
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload.';
            default:
                return 'Unknown upload error.';
        }
    }
    
    /**
     * Generuje HTML dla interfejsu przesyłania obrazu (tylko nowe pliki)
     *
     * @param string $field_name Nazwa pola formularza
     * @param string $current_image_url URL obecnie ustawionego obrazu (opcjonalnie)
     * @param array $options Dodatkowe opcje dla interfejsu
     * @return string Kod HTML interfejsu
     */
    public static function render_image_upload_field($field_name, $current_image_url = '', $options = array()) {
        $defaults = array(
            'label' => __('Image', 'herbal-mix-creator2'),
            'required' => false,
            'upload_button_text' => __('Upload New Image', 'herbal-mix-creator2'),
            'remove_button_text' => __('Remove', 'herbal-mix-creator2'),
            'placeholder_text' => __('Click to upload a new image', 'herbal-mix-creator2'),
            'hint_text' => __('Upload a new image (recommended size: 800x800px)', 'herbal-mix-creator2'),
            'preview_size' => array('width' => 150, 'height' => 150),
            'css_class' => '',
            'accept' => 'image/*'
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Generuj unikalny ID na podstawie nazwy pola
        $field_id = sanitize_title($field_name);
        
        ob_start();
        ?>
        <div class="form-row image-upload-row <?php echo esc_attr($options['css_class']); ?>">
            <label for="<?php echo esc_attr($field_id); ?>">
                <?php echo esc_html($options['label']); ?>
                <?php if ($options['required']) : ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>
            <div class="custom-file-upload" data-field="<?php echo esc_attr($field_name); ?>">
                <div id="<?php echo esc_attr($field_id); ?>_preview" class="image-preview<?php echo $current_image_url ? ' has-image' : ''; ?>" 
                     style="width: <?php echo esc_attr($options['preview_size']['width']); ?>px; 
                            height: <?php echo esc_attr($options['preview_size']['height']); ?>px;
                            <?php echo $current_image_url ? 'background-image: url(' . esc_url($current_image_url) . ');' : ''; ?>">
                    <div class="upload-prompt">
                        <i class="upload-icon"></i>
                        <span><?php echo esc_html($options['placeholder_text']); ?></span>
                    </div>
                </div>
                
                <!-- Pasek postępu przesyłania -->
                <div id="<?php echo esc_attr($field_id); ?>_progress_container" class="upload-progress-container" style="display: none; margin-top: 10px;">
                    <div class="upload-progress-bar-wrapper">
                        <div id="<?php echo esc_attr($field_id); ?>_progress_bar" class="upload-progress-bar"></div>
                    </div>
                    <div id="<?php echo esc_attr($field_id); ?>_progress_text" class="upload-progress-text">0%</div>
                </div>
                
                <!-- Pole przesyłania pliku -->
                <input type="file" id="<?php echo esc_attr($field_id); ?>_file" name="<?php echo esc_attr($field_name); ?>_file" 
                       accept="<?php echo esc_attr($options['accept']); ?>" style="display:none;" 
                       class="herbal-mix-file-input" data-target="<?php echo esc_attr($field_id); ?>">
                
                <!-- Ukryte pole z URL obrazu -->
                <input type="hidden" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>" 
                       value="<?php echo esc_attr($current_image_url); ?>" 
                       class="herbal-mix-image-input" <?php echo $options['required'] ? 'required' : ''; ?>>
                
                <!-- Przyciski -->
                <div class="image-upload-buttons">
                    <button type="button" id="<?php echo esc_attr($field_id); ?>_select_btn" 
                            class="button herbal-mix-select-image-btn" data-target="<?php echo esc_attr($field_id); ?>">
                        <?php echo esc_html($options['upload_button_text']); ?>
                    </button>
                    <button type="button" id="<?php echo esc_attr($field_id); ?>_remove_btn" 
                            class="button button-secondary herbal-mix-remove-image-btn" 
                            data-target="<?php echo esc_attr($field_id); ?>" 
                            style="<?php echo $current_image_url ? '' : 'display:none;'; ?>">
                        <?php echo esc_html($options['remove_button_text']); ?>
                    </button>
                </div>
                
                <p class="field-hint"><?php echo esc_html($options['hint_text']); ?></p>
                <p id="<?php echo esc_attr($field_id); ?>_error" class="error-message" style="display: none;"></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Usuwa przesłane pliki użytkownika (funkcja pomocnicza)
     */
    public function cleanup_user_uploads($user_id) {
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_herbal_mix_uploader',
                    'value' => $user_id,
                    'compare' => '='
                )
            )
        ));
        
        foreach ($attachments as $attachment) {
            wp_delete_attachment($attachment->ID, true);
        }
    }
}

// Inicjalizacja klasy
$herbal_mix_media_handler = new HerbalMixMediaHandler();

/**
 * Funkcja pomocnicza do renderowania pola przesyłania obrazu
 */
function herbal_mix_image_upload_field($field_name, $current_image_url = '', $options = array()) {
    echo HerbalMixMediaHandler::render_image_upload_field($field_name, $current_image_url, $options);
}