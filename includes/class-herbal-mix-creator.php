<?php
/**
 *  WTYCZKA: Herbal Mix Creator – klasa backendu formularza mieszanek
 *  Plik  : includes/class-herbal-mix-creator.php
 *  Autor : (c) 2025 Herbal Nest
 */

// Zapobiegamy bezpośredniemu wykonaniu pliku
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Herbal_Mix_Creator {

    /* --------------------------------------------------------------------- */
    /*  KONSTRUKTOR – rejestrujemy shortcode, hooki AJAX i assets            */
    /* --------------------------------------------------------------------- */
    public function __construct() {
        /* Shortcode do front‑endowego formularza */
        add_shortcode( 'herbal_mix_creator', [ $this, 'render_form' ] );

        /* Assets tylko na stronach, gdzie użyty jest shortcode */
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        /* ------ AJAX – opakowania / kategorie / składniki  ------ */
        add_action( 'wp_ajax_load_herbal_packaging',        [ $this, 'ajax_load_packaging' ] );
        add_action( 'wp_ajax_nopriv_load_herbal_packaging', [ $this, 'ajax_load_packaging' ] );

        add_action( 'wp_ajax_get_herbal_categories',        [ $this, 'ajax_get_categories' ] );
        add_action( 'wp_ajax_nopriv_get_herbal_categories', [ $this, 'ajax_get_categories' ] );

        add_action( 'wp_ajax_load_herbal_ingredients',      [ $this, 'ajax_get_ingredients' ] );
        add_action( 'wp_ajax_nopriv_load_herbal_ingredients',[ $this, 'ajax_get_ingredients' ] );

        /* ------ AJAX – zapis mieszanki / buy now  ------ */
        add_action( 'wp_ajax_save_mix',                     [ $this, 'ajax_save_mix' ] );
        add_action( 'wp_ajax_nopriv_save_mix',              [ $this, 'ajax_save_mix' ] );
    }

    /* --------------------------------------------------------------------- */
    /*  SHORTCODE                                                            */
    /* --------------------------------------------------------------------- */
    public function render_form() {
        ob_start();
        include plugin_dir_path( __FILE__ ) . '../frontend-mix-form.php';
        return ob_get_clean();
    }

    /* --------------------------------------------------------------------- */
    /*  ASSETS                                                               */
    /* --------------------------------------------------------------------- */
    public function enqueue_assets() {
        global $post;
        if ( ! isset( $post->post_content ) || strpos( $post->post_content, '[herbal_mix_creator]' ) === false ) {
            return; // brak shortcode na stronie → nie ładujemy niczego
        }

        /* -------- CSS -------- */
        $css_rel  = '../assets/css/mix-creator.css';
        $css_file = plugin_dir_path( __FILE__ ) . $css_rel;
        wp_enqueue_style( 'herbal-mix-css', plugin_dir_url( __FILE__ ) . $css_rel, [], filemtime( $css_file ) );

        /* -------- Chart.js -------- */
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true );

        /* -------- Główny skrypt -------- */
        $js_rel  = '../assets/js/mix-creator.js';
        $js_file = plugin_dir_path( __FILE__ ) . $js_rel;
        wp_enqueue_script( 'herbal-mix-js', plugin_dir_url( __FILE__ ) . $js_rel, [ 'jquery', 'chart-js' ], filemtime( $js_file ), true );

        /* -------- Dane przekazywane do JS -------- */
        wp_localize_script( 'herbal-mix-js', 'herbalMixData', [
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'herbal_mix_nonce' ),
            'default_herb_img' => plugin_dir_url( __FILE__ ) . '../assets/img/default-herb.png',
            'empty_state_img'  => plugin_dir_url( __FILE__ ) . '../assets/img/empty-state.png',
            'points_icon'      => plugin_dir_url( __FILE__ ) . '../assets/img/points-icon.png',
            'currency_symbol'  => get_woocommerce_currency_symbol(),
            'user_id'          => get_current_user_id(),
            'user_name'        => $this->get_user_display_name(),
        ] );
    }

    /* --------------------------------------------------------------------- */
    /*  AJAX: OPAKOWANIA                                                     */
    /* --------------------------------------------------------------------- */
    public function ajax_load_packaging() {
        $this->verify_nonce();

        global $wpdb;
        $rows = $wpdb->get_results( "SELECT *
                                       FROM {$wpdb->prefix}herbal_packaging
                                      WHERE available = 1
                                      ORDER BY herb_capacity ASC" );

        if ( $wpdb->last_error ) {
            wp_send_json_error( 'Database error: ' . $wpdb->last_error );
        }

        // upewnij się, że pola liczbowe są liczbami (frontend oczekuje floatów)
        foreach ( $rows as $row ) {
            $row->price        = (float) $row->price;
            $row->price_point  = (float) $row->price_point;
            $row->point_earned = (float) $row->point_earned;
            $row->herb_capacity= (int)   $row->herb_capacity;
        }

        wp_send_json_success( $rows );
    }

    /* --------------------------------------------------------------------- */
    /*  AJAX: KATEGORIE                                                      */
    /* --------------------------------------------------------------------- */
    public function ajax_get_categories() {
        $this->verify_nonce();

        global $wpdb;
        $cats = $wpdb->get_results( "SELECT id, name, description
                                       FROM {$wpdb->prefix}herbal_categories
                                      WHERE visible = 1
                                      ORDER BY sort_order ASC, name ASC" );
        wp_send_json_success( $cats );
    }

    /* --------------------------------------------------------------------- */
    /*  AJAX: SKŁADNIKI                                                      */
    /* --------------------------------------------------------------------- */
    public function ajax_get_ingredients() {
        $this->verify_nonce();

        $category_id        = isset( $_GET['category_id'] )        ? intval( $_GET['category_id'] )        : 0;
        $packaging_capacity = isset( $_GET['packaging_capacity'] ) ? intval( $_GET['packaging_capacity'] ) : 0;

        global $wpdb;
        $sql  = "SELECT * FROM {$wpdb->prefix}herbal_ingredients WHERE visible = 1";
        $args = [];
        if ( $category_id > 0 ) {
            $sql  .= ' AND category_id = %d';
            $args[] = $category_id;
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';
        $sql  = $args ? $wpdb->prepare( $sql, $args ) : $sql;
        $rows = $wpdb->get_results( $sql );

        foreach ( $rows as $row ) {
            $row->price        = (float) $row->price;
            $row->price_point  = (float) $row->price_point;
            $row->point_earned = (float) $row->point_earned;
            $row->stock        = (int)   $row->stock;
            $row->is_available = $this->check_ingredient_availability( $row, $packaging_capacity );
        }

        wp_send_json_success( $rows );
    }

    /* --------------------------------------------------------------------- */
    /*  AJAX: ZAPIS MIESZANKI (ulubione / buy now)                           */
    /* --------------------------------------------------------------------- */
    public function ajax_save_mix() {
        try {
            // Weryfikacja nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'herbal_mix_nonce')) {
                wp_send_json_error('Invalid security token. Please refresh the page.');
                return;
            }

            // 1. Sprawdź czy przesłano dane
            if (empty($_POST['mix_data'])) {
                wp_send_json_error('No mix data provided.');
                return;
            }

            // 2. Zdekoduj dane JSON
            $raw_data = json_decode(stripslashes($_POST['mix_data']), true);
            if (!$raw_data) {
                $json_error = json_last_error_msg();
                wp_send_json_error('JSON decode error: ' . $json_error . ' Data: ' . substr($_POST['mix_data'], 0, 100) . '...');
                return;
            }

            // 3. Sprawdź czy użytkownik jest zalogowany
            $user_id = get_current_user_id();
            if (!$user_id && $raw_data['action'] === 'save_favorite') {
                wp_send_json_error('You must be logged in to save mixes to favorites.');
                return;
            }

            // 4. Sprawdź składniki
            if (empty($raw_data['ingredients']) || !is_array($raw_data['ingredients'])) {
                wp_send_json_error('No ingredients found in the mix.');
                return;
            }

            // 5. Sprawdź opakowanie
            if (empty($raw_data['packaging']) || empty($raw_data['packaging']['id'])) {
                wp_send_json_error('No packaging selected for the mix.');
                return;
            }

            // 6. Przygotuj nazwę mieszanki
            $mix_name = isset($_POST['mix_name']) && !empty($_POST['mix_name']) 
                      ? sanitize_text_field($_POST['mix_name']) 
                      : 'Custom Mix';
            
            // 7. Przygotowanie uproszczonej struktury danych
            $mix_data_simplified = [
                'packaging_id' => intval($raw_data['packaging']['id']),
                'ingredients' => []
            ];

            foreach ($raw_data['ingredients'] as $ingredient) {
                $mix_data_simplified['ingredients'][] = [
                    'id' => intval($ingredient['id']),
                    'weight' => floatval($ingredient['weight'])
                ];
            }

            // 8. Przygotuj pole liked_by
            $liked_by = [$user_id];
            $json_liked_by = json_encode($liked_by);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('Error encoding liked_by field: ' . json_last_error_msg());
                return;
            }

            // 9. Sprawdź tabelę w bazie danych
            global $wpdb;
            $table_name = $wpdb->prefix . 'herbal_mixes';
            
            // 10. Sprawdź czy tabela istnieje
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if ($table_exists !== $table_name) {
                wp_send_json_error("Database table '$table_name' does not exist. Please reactivate the plugin.");
                return;
            }
            
            // 11. Sprawdź strukturę tabeli
            $column_check = $wpdb->query("SHOW COLUMNS FROM $table_name LIKE 'liked_by'");
            if ($column_check === 0) {
                wp_send_json_error("Column 'liked_by' does not exist in table '$table_name'. Please reactivate the plugin to update the database schema.");
                return;
            }

            // 12. Serializacja danych do zapisu
            $mix_data_json = json_encode($mix_data_simplified);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error('Error encoding mix data: ' . json_last_error_msg());
                return;
            }

            // 13. Zapisz do bazy danych
            $data_to_insert = [
                'user_id'        => $user_id,
                'mix_name'       => $mix_name,
                'mix_data'       => $mix_data_json,
                'created_at'     => current_time('mysql'),
                'status'         => 'favorite',
                'liked_by'       => $json_liked_by,
                'like_count'     => 1
            ];
            
            // Wyświetl zapytanie które zostanie wykonane
            $data_formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%d'];
            
            $insert_sql = "INSERT INTO $table_name (";
            $insert_sql .= implode(', ', array_keys($data_to_insert));
            $insert_sql .= ") VALUES (";
            foreach ($data_formats as $i => $format) {
                $key = array_keys($data_to_insert)[$i];
                $insert_sql .= $format === '%s' ? "'" . esc_sql($data_to_insert[$key]) . "'" : $data_to_insert[$key];
                if ($i < count($data_formats) - 1) $insert_sql .= ", ";
            }
            $insert_sql .= ")";
            
            // Zamiast wpdb->insert, ręcznie wykonujemy zapytanie dla lepszej diagnostyki
            $insert_result = $wpdb->query($insert_sql);
            
            if ($insert_result === false) {
                wp_send_json_error('Database insert error: ' . $wpdb->last_error . '. Query: ' . $insert_sql);
                return;
            }

            $mix_id = $wpdb->insert_id;
            if (!$mix_id) {
                wp_send_json_error('Failed to get insert ID after database insert.');
                return;
            }

            // Sukces!
            wp_send_json_success([
                'mix_id' => $mix_id,
                'message' => 'Mix saved successfully.'
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('Unexpected error: ' . $e->getMessage());
        }
    }

    /* --------------------------------------------------------------------- */
    /*  HELPERS                                                              */
    /* --------------------------------------------------------------------- */
    private function get_user_display_name() {
        $u = wp_get_current_user();
        return $u->ID ? $u->display_name : 'Guest';
    }

    private function verify_nonce() {
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], 'herbal_mix_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
    }

    private function check_ingredient_availability($item, $capacity) {
        if ($capacity <= 0) {
            return false;
        }
        $min = max(1, ceil($capacity * 0.1));
        return ($item->stock >= $min);
    }
}

// Instantiation
new Herbal_Mix_Creator();