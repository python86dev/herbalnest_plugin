<?php
/**
 * Herbal Mix Database - KOMPLETNA POPRAWIONA WERSJA
 * 
 * ZACHOWANE: Wszystkie oryginalne funkcje niezwiązane z punktami
 * USUNIĘTE: Tylko zduplikowane metody punktów (przeniesione do Herbal_Points_Manager)
 * 
 * File: includes/class-herbal-mix-database.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Herbal_Mix_Database {

    /**
     * Install database tables - KOMPLETNA WERSJA
     */
    public static function install() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // === CORE HERBAL TABLES (ZACHOWANE ORYGINALNE NAZWY) ===
        
        // Table: herbal_packaging (ORYGINALNA - bez zmian!)
        $table1 = $wpdb->prefix . 'herbal_packaging';
        dbDelta("
            CREATE TABLE {$table1} (
                id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                herb_capacity INT NOT NULL,
                image_url TEXT DEFAULT NULL,
                price FLOAT DEFAULT 0,
                price_point FLOAT DEFAULT 0,
                point_earned FLOAT DEFAULT 0,
                available TINYINT(1) DEFAULT 1,
                PRIMARY KEY (id)
            ) {$charset_collate};
        ");

        // Table: herbal_categories (ORYGINALNA - bez zmian!)
        $table2 = $wpdb->prefix . 'herbal_categories';
        dbDelta("
            CREATE TABLE {$table2} (
                id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                sort_order INT DEFAULT 0,
                visible TINYINT(1) DEFAULT 1,
                PRIMARY KEY (id)
            ) {$charset_collate};
        ");

        // Table: herbal_ingredients (ORYGINALNA - bez zmian!)
        $table3 = $wpdb->prefix . 'herbal_ingredients';
        dbDelta("
            CREATE TABLE {$table3} (
                id INT NOT NULL AUTO_INCREMENT,
                category_id INT,
                name VARCHAR(255) NOT NULL,
                price FLOAT DEFAULT 0,
                price_point FLOAT DEFAULT 0,
                point_earned FLOAT DEFAULT 0,
                image_url TEXT DEFAULT NULL,
                description TEXT,
                story TEXT,
                visible TINYINT(1) DEFAULT 1,
                sort_order INT DEFAULT 0,
                meta_data LONGTEXT,
                stock INT DEFAULT 100,
                PRIMARY KEY (id),
                KEY category_id (category_id)
            ) {$charset_collate};
        ");

        // Table: herbal_mixes (ZAKTUALIZOWANA - dodane mix_story field)
        $table4 = $wpdb->prefix . 'herbal_mixes';
        dbDelta("
            CREATE TABLE {$table4} (
                id INT NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                mix_name VARCHAR(255) NOT NULL,
                mix_description TEXT,
                mix_story TEXT,
                mix_image TEXT,
                mix_data LONGTEXT NOT NULL COMMENT 'JSON cache of ingredients and packaging data',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(50) DEFAULT 'favorite',
                liked_by TEXT,
                like_count INT DEFAULT 1,
                base_product_id BIGINT DEFAULT NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY status (status)
            ) {$charset_collate};
        ");

        // Table: herbal_points_history (GŁÓWNA TABELA PUNKTÓW)
        $table5 = $wpdb->prefix . 'herbal_points_history';
        dbDelta("
            CREATE TABLE {$table5} (
                id INT NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                points_change DECIMAL(10,2) NOT NULL,
                transaction_type VARCHAR(50) NOT NULL,
                reference_id BIGINT UNSIGNED DEFAULT NULL,
                reference_type VARCHAR(50) DEFAULT NULL,
                points_before DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                points_after DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                notes TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY transaction_type (transaction_type),
                KEY created_at (created_at),
                KEY reference_id (reference_id),
                KEY reference_type (reference_type)
            ) {$charset_collate};
        ");

        // === ROZSZERZONE TABELE (na przyszłość) ===
        
        // Table: herbal_comments (na przyszłość)
        $table6 = $wpdb->prefix . 'herbal_comments';
        dbDelta("
            CREATE TABLE {$table6} (
                id INT NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                parent_type ENUM('product', 'post', 'review') NOT NULL,
                parent_id BIGINT UNSIGNED NOT NULL,
                parent_comment_id INT DEFAULT NULL,
                content TEXT NOT NULL,
                likes_count INT DEFAULT 0,
                replies_count INT DEFAULT 0,
                status ENUM('pending', 'approved', 'spam', 'trash') DEFAULT 'approved',
                is_pinned TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY parent (parent_type, parent_id),
                KEY user_id (user_id),
                KEY status (status),
                KEY parent_comment_id (parent_comment_id)
            ) {$charset_collate};
        ");

        // Dodaj przykładowe dane jeśli tabele są puste
        self::add_sample_data();
    }

    /**
     * Dodaj przykładowe dane do pustych tabel
     */
    private static function add_sample_data() {
        global $wpdb;
        
        // Dodaj przykładowe kategorie jeśli nie ma żadnych
        $categories_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}herbal_categories");
        if ($categories_count == 0) {
            $categories = [
                ['name' => 'Relaxing Herbs', 'description' => 'Herbs for relaxation and calm', 'sort_order' => 1, 'visible' => 1],
                ['name' => 'Digestive Herbs', 'description' => 'Herbs that support digestion', 'sort_order' => 2, 'visible' => 1],
                ['name' => 'Energy Herbs', 'description' => 'Herbs for energy and vitality', 'sort_order' => 3, 'visible' => 1]
            ];
            
            foreach ($categories as $category) {
                $wpdb->insert($wpdb->prefix . 'herbal_categories', $category);
            }
        }
        
        // Dodaj przykładowe opakowania jeśli nie ma żadnych
        $packaging_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}herbal_packaging");
        if ($packaging_count == 0) {
            $packagings = [
                [
                    'name' => '150g Glass Jar',
                    'herb_capacity' => 150,
                    'price' => 2.60,
                    'price_point' => 260,
                    'point_earned' => 26,
                    'available' => 1
                ],
                [
                    'name' => '100g Glass Jar', 
                    'herb_capacity' => 100,
                    'price' => 2.00,
                    'price_point' => 200,
                    'point_earned' => 20,
                    'available' => 1
                ],
                [
                    'name' => '50g Glass Jar',
                    'herb_capacity' => 50,
                    'price' => 1.50,
                    'price_point' => 150,
                    'point_earned' => 15,
                    'available' => 1
                ]
            ];
            
            foreach ($packagings as $pack) {
                $wpdb->insert($wpdb->prefix . 'herbal_packaging', $pack);
            }
        }

        // Dodaj przykładowe składniki jeśli nie ma żadnych
        $ingredients_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}herbal_ingredients");
        if ($ingredients_count == 0) {
            $ingredients = [
                [
                    'category_id' => 1,
                    'name' => 'Chamomile',
                    'price' => 0.15,
                    'price_point' => 15,
                    'point_earned' => 2,
                    'description' => 'A gentle herb with calming properties',
                    'visible' => 1,
                    'sort_order' => 1,
                    'stock' => 100
                ],
                [
                    'category_id' => 1,
                    'name' => 'Lavender',
                    'price' => 0.20,
                    'price_point' => 20,
                    'point_earned' => 3,
                    'description' => 'Aromatic herb with relaxing properties',
                    'visible' => 1,
                    'sort_order' => 2,
                    'stock' => 100
                ],
                [
                    'category_id' => 2,
                    'name' => 'Peppermint',
                    'price' => 0.18,
                    'price_point' => 18,
                    'point_earned' => 2,
                    'description' => 'Refreshing herb with cooling properties',
                    'visible' => 1,
                    'sort_order' => 1,
                    'stock' => 100
                ],
                [
                    'category_id' => 2,
                    'name' => 'Ginger',
                    'price' => 0.25,
                    'price_point' => 25,
                    'point_earned' => 3,
                    'description' => 'Warming herb that aids digestion',
                    'visible' => 1,
                    'sort_order' => 2,
                    'stock' => 100
                ],
                [
                    'category_id' => 3,
                    'name' => 'Ginseng',
                    'price' => 0.40,
                    'price_point' => 40,
                    'point_earned' => 5,
                    'description' => 'Energising herb for vitality and focus',
                    'visible' => 1,
                    'sort_order' => 1,
                    'stock' => 100
                ]
            ];

            foreach ($ingredients as $ingredient) {
                $wpdb->insert($wpdb->prefix . 'herbal_ingredients', $ingredient);
            }
        }
    }

    // === ORYGINALNE METODY PACKAGING ===

    /**
     * Pobierz wszystkie dostępne opakowania (ORYGINALNA METODA)
     */
    public static function get_packagings() {
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_packaging';

        $results = $wpdb->get_results("
            SELECT id, name, herb_capacity, image_url, price, price_point, point_earned
              FROM {$table}
             WHERE available = 1
          ORDER BY herb_capacity ASC
        ");

        foreach ($results as $row) {
            $row->herb_capacity = (int) $row->herb_capacity;
            $row->price = (float) $row->price;
            $row->price_point = (float) $row->price_point;
            $row->point_earned = (float) $row->point_earned;
        }

        return $results;
    }

    /**
     * Pobierz pojedyncze opakowanie z walidacją (ORYGINALNA METODA)
     */
    public static function get_packaging($packaging_id) {
        global $wpdb;

        if (!is_numeric($packaging_id)) {
            return new WP_Error('invalid_id', 'Invalid packaging ID');
        }

        $packaging = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * 
                   FROM {$wpdb->prefix}herbal_packaging 
                  WHERE id = %d 
                    AND available = 1",
                $packaging_id
            )
        );

        if (!$packaging) {
            return new WP_Error('not_found', 'Packaging not found or not available');
        }

        // Konwertuj typy danych
        $packaging->herb_capacity = (int) $packaging->herb_capacity;
        $packaging->price = (float) $packaging->price;
        $packaging->price_point = (float) $packaging->price_point;
        $packaging->point_earned = (float) $packaging->point_earned;
        $packaging->available = (bool) $packaging->available;

        return $packaging;
    }

    // === ORYGINALNE METODY INGREDIENTS ===

    /**
     * Pobierz pojedynczy składnik z walidacją (ORYGINALNA METODA)
     */
    public static function get_ingredient($ingredient_id) {
        global $wpdb;

        if (!is_numeric($ingredient_id)) {
            return new WP_Error('invalid_id', 'Invalid ingredient ID');
        }

        $ingredient = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * 
                   FROM {$wpdb->prefix}herbal_ingredients 
                  WHERE id = %d 
                    AND visible = 1",
                $ingredient_id
            )
        );

        if (!$ingredient) {
            return new WP_Error('not_found', 'Ingredient not found or not available');
        }

        // Konwertuj typy danych
        $ingredient->price = (float) $ingredient->price;
        $ingredient->price_point = (float) $ingredient->price_point;
        $ingredient->point_earned = (float) $ingredient->point_earned;
        $ingredient->visible = (bool) $ingredient->visible;
        $ingredient->sort_order = (int) $ingredient->sort_order;
        $ingredient->stock = (int) $ingredient->stock;

        return $ingredient;
    }

    /**
     * Pobierz wszystkie składniki pogrupowane według kategorii (ORYGINALNA METODA)
     */
    public static function get_ingredients_by_category() {
        global $wpdb;

        $ingredients_table = $wpdb->prefix . 'herbal_ingredients';
        $categories_table = $wpdb->prefix . 'herbal_categories';

        $results = $wpdb->get_results("
            SELECT i.id, i.name, i.price, i.price_point, i.point_earned, i.image_url, 
                   i.description, i.story, i.visible, i.sort_order, i.meta_data, i.stock,
                   c.name as category_name, c.id as category_id
              FROM {$ingredients_table} i
         LEFT JOIN {$categories_table} c ON i.category_id = c.id
             WHERE i.visible = 1 AND c.visible = 1
          ORDER BY c.sort_order, i.sort_order, i.name
        ");

        // Grupuj według kategorii
        $categories = array();
        foreach ($results as $ingredient) {
            $cat_id = $ingredient->category_id ?: 0;
            $cat_name = $ingredient->category_name ?: 'Uncategorized';
            
            if (!isset($categories[$cat_id])) {
                $categories[$cat_id] = array(
                    'id' => $cat_id,
                    'name' => $cat_name,
                    'ingredients' => array()
                );
            }
            
            // Konwertuj typy danych dla składnika
            $ingredient->price = (float) $ingredient->price;
            $ingredient->price_point = (float) $ingredient->price_point;
            $ingredient->point_earned = (float) $ingredient->point_earned;
            $ingredient->stock = (int) $ingredient->stock;
            
            $categories[$cat_id]['ingredients'][] = $ingredient;
        }

        return array_values($categories);
    }

    /**
     * Pobierz składniki z sprawdzeniem dostępności (ORYGINALNA METODA)
     */
    public static function get_ingredients($category_id = null, $packaging_capacity = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'herbal_ingredients';
        
        $sql = "SELECT * FROM {$table} WHERE visible = 1";
        $params = [];
        
        if ($category_id) {
            $sql .= " AND category_id = %d";
            $params[] = $category_id;
        }
        
        $sql .= " ORDER BY sort_order ASC, name ASC";
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        $results = $wpdb->get_results($sql);
        
        // Przetwórz wyniki i dodaj informacje o dostępności
        foreach ($results as $ingredient) {
            $ingredient->price = (float) $ingredient->price;
            $ingredient->price_point = (float) $ingredient->price_point;
            $ingredient->point_earned = (float) $ingredient->point_earned;
            $ingredient->stock = (int) $ingredient->stock;
            
            // Proste sprawdzenie dostępności
            $ingredient->is_available = $ingredient->stock > 0;
            
            // Jeśli określona jest pojemność opakowania, można dodać bardziej złożoną logikę dostępności
            if ($packaging_capacity && $packaging_capacity < 50) {
                // Przykład: niektóre składniki mogą nie być odpowiednie dla małych opakowań
                // To tylko przykład - zaimplementuj własną logikę
            }
        }
        
        return $results;
    }

    // === ORYGINALNE METODY CATEGORIES ===

    /**
     * Pobierz wszystkie kategorie (ORYGINALNA METODA)
     */
    public static function get_categories() {
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_categories';

        return $wpdb->get_results("
            SELECT * FROM {$table} 
            WHERE visible = 1 
            ORDER BY sort_order, name
        ");
    }

    // === ORYGINALNE METODY MIXES ===

    /**
     * Zapisz nową mieszankę (ZAKTUALIZOWANA - dodany parametr mix_story)
     */
    public static function save_mix($user_id, $mix_name, $mix_data, $mix_description = '', $mix_story = '', $mix_image = '', $status = 'favorite') {
        global $wpdb;

        if (!$user_id || !$mix_name || !$mix_data) {
            return new WP_Error('missing_data', 'Missing required data');
        }

        $table = $wpdb->prefix . 'herbal_mixes';
        
        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'mix_name' => sanitize_text_field($mix_name),
                'mix_description' => sanitize_textarea_field($mix_description),
                'mix_story' => sanitize_textarea_field($mix_story),
                'mix_data' => wp_json_encode($mix_data),
                'status' => sanitize_text_field($status),
                'created_at' => current_time('mysql'),
                'mix_image' => esc_url_raw($mix_image),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('save_failed', 'Failed to save mix: ' . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    /**
     * Zaktualizuj historię mieszanki (NOWA FUNKCJA)
     */
    public static function update_mix_story($mix_id, $mix_story) {
        global $wpdb;
        
        if (!$mix_id) {
            return new WP_Error('missing_id', 'Missing mix ID');
        }
        
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $result = $wpdb->update(
            $table,
            array('mix_story' => sanitize_textarea_field($mix_story)),
            array('id' => intval($mix_id)),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update mix story: ' . $wpdb->last_error);
        }
        
        return $result;
    }

    /**
     * Pobierz mieszankę z historią (NOWA FUNKCJA)
     */
    public static function get_mix_with_story($mix_id) {
        global $wpdb;
        
        if (!$mix_id) {
            return new WP_Error('missing_id', 'Missing mix ID');
        }
        
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                intval($mix_id)
            )
        );
        
        if (!$mix) {
            return new WP_Error('not_found', 'Mix not found');
        }
        
        return $mix;
    }

    /**
     * Pobierz mieszanki użytkownika (ORYGINALNA METODA)
     */
    public static function get_user_mixes($user_id, $status = null, $limit = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';

        $where = $wpdb->prepare("WHERE user_id = %d", $user_id);
        
        if ($status) {
            $where .= $wpdb->prepare(" AND status = %s", $status);
        }
        
        $limit_clause = $limit ? $wpdb->prepare(" LIMIT %d", $limit) : '';

        return $wpdb->get_results("
            SELECT * FROM {$table} 
            {$where}
            ORDER BY created_at DESC
            {$limit_clause}
        ");
    }

    /**
     * Pobierz mieszankę według ID (ORYGINALNA METODA)
     */
    public static function get_mix_by_id($mix_id, $user_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $sql = "SELECT * FROM {$table} WHERE id = %d";
        $params = [$mix_id];
        
        if ($user_id) {
            $sql .= " AND user_id = %d";
            $params[] = $user_id;
        }
        
        return $wpdb->get_row($wpdb->prepare($sql, $params));
    }

    /**
     * Zaktualizuj mieszankę użytkownika (ORYGINALNA METODA)
     */
    public static function update_user_mix($mix_id, $user_id, $mix_data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $result = $wpdb->update(
            $table,
            array(
                'mix_name' => sanitize_text_field($mix_data['mix_name']),
                'mix_description' => sanitize_textarea_field($mix_data['mix_description']),
                'mix_story' => sanitize_textarea_field($mix_data['mix_story']),
                'mix_image' => esc_url_raw($mix_data['mix_image']),
                'mix_data' => wp_json_encode($mix_data)
            ),
            array('id' => $mix_id, 'user_id' => $user_id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d', '%d')
        );
        
        return $result !== false;
    }

    /**
     * Usuń mieszankę użytkownika (ORYGINALNA METODA)
     */
    public static function delete_user_mix($mix_id, $user_id = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $where = ['id' => $mix_id];
        $where_format = ['%d'];
        
        if ($user_id) {
            $where['user_id'] = $user_id;
            $where_format[] = '%d';
        }

        $result = $wpdb->delete($table, $where, $where_format);
        return $result !== false;
    }

    // === METODY HISTORII PUNKTÓW (ZACHOWANE - używane przez Herbal_Points_Manager) ===

    /**
     * Zapisz transakcję punktów w historii
     * UWAGA: Ta metoda zostaje - jest używana przez Herbal_Points_Manager
     */
    public static function record_points_transaction($user_id, $points_change, $transaction_type, $reference_id = null, $points_before = 0, $points_after = 0, $notes = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'herbal_points_history';
        
        // Sprawdź czy tabela istnieje
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        if ($table_exists !== $table_name) {
            return false;
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'points_change' => $points_change,
                'transaction_type' => $transaction_type,
                'reference_id' => $reference_id,
                'reference_type' => $reference_id ? 'order' : null,
                'points_before' => $points_before,
                'points_after' => $points_after,
                'notes' => $notes,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%f', '%s', '%d', '%s', '%f', '%f', '%s', '%s')
        );
        
        return $result !== false;
    }

    /**
     * Pobierz historię punktów użytkownika
     * UWAGA: Ta metoda zostaje - jest używana przez Herbal_Points_Manager
     */
    public static function get_points_history($user_id, $limit = 20, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'herbal_points_history';
        
        return $wpdb->get_results(
            $wpdb->prepare("
                SELECT * FROM {$table_name}
                WHERE user_id = %d
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d
            ", $user_id, $limit, $offset)
        );
    }

    // === USUNIĘTE DUPLIKACJE PUNKTÓW ===
    // 
    // USUNIĘTE METODY (przeniesione do Herbal_Points_Manager):
    // - get_points_statistics() 
    // - add_user_points()
    // - subtract_user_points() 
    // - get_user_points()
    // 
    // Te metody były zduplikowane i powodowały konflikty
    // Teraz wszystkie operacje punktów przechodzą przez Herbal_Points_Manager

    // === ADMIN/DIAGNOSTIC OPERATIONS (ORYGINALNE) ===

    /**
     * Pobierz status tabel bazy danych (dla diagnostyki admina)
     */
    public static function get_table_status() {
        global $wpdb;
        
        $tables = [
            'herbal_packaging',
            'herbal_categories', 
            'herbal_ingredients',
            'herbal_mixes',
            'herbal_points_history',
            'herbal_comments'  // na przyszłość
        ];
        
        $status = [];
        
        foreach ($tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'");
            $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$full_table_name}") : 0;
            
            $status[$table] = [
                'exists' => $exists === $full_table_name,
                'count' => (int) $count
            ];
        }
        
        return $status;
    }

    /**
     * Optymalizuj tabele bazy danych
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'herbal_packaging',
            $wpdb->prefix . 'herbal_categories', 
            $wpdb->prefix . 'herbal_ingredients',
            $wpdb->prefix . 'herbal_mixes',
            $wpdb->prefix . 'herbal_points_history',
            $wpdb->prefix . 'herbal_comments'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if ($table_exists === $table) {
                $result = $wpdb->query("OPTIMIZE TABLE {$table}");
                $results[$table] = $result !== false;
            }
        }
        
        return $results;
    }

    /**
     * Oczyść dane użytkownika (zgodność z GDPR)
     */
    public static function cleanup_user_data($user_id, $anonymize = true) {
        global $wpdb;
        
        $deleted = [];
        
        if ($anonymize) {
            // Anonimizuj zamiast usuwać
            $deleted['mixes'] = $wpdb->update(
                $wpdb->prefix . 'herbal_mixes',
                ['user_id' => 0],
                ['user_id' => $user_id],
                ['%d'],
                ['%d']
            );
            
            $deleted['points_history'] = $wpdb->update(
                $wpdb->prefix . 'herbal_points_history',
                ['user_id' => 0],
                ['user_id' => $user_id],
                ['%d'],
                ['%d']
            );
            
            $deleted['comments'] = $wpdb->update(
                $wpdb->prefix . 'herbal_comments',
                ['user_id' => 0],
                ['user_id' => $user_id],
                ['%d'],
                ['%d']
            );
            
        } else {
            // Kompletne usunięcie
            $deleted['mixes'] = $wpdb->delete($wpdb->prefix . 'herbal_mixes', ['user_id' => $user_id], ['%d']);
            $deleted['points_history'] = $wpdb->delete($wpdb->prefix . 'herbal_points_history', ['user_id' => $user_id], ['%d']);
            $deleted['comments'] = $wpdb->delete($wpdb->prefix . 'herbal_comments', ['user_id' => $user_id], ['%d']);
        }
        
        // Usuń user meta
        delete_user_meta($user_id, 'reward_points');
        
        return $deleted;
    }

    // === ORYGINALNE METODY POMOCNICZE ===

    /**
     * Waliduj dane mieszanki przed zapisem
     */
    public static function validate_mix_data($mix_data) {
        $errors = [];
        
        if (empty($mix_data['mix_name'])) {
            $errors[] = 'Mix name is required';
        }
        
        if (empty($mix_data['ingredients']) || !is_array($mix_data['ingredients'])) {
            $errors[] = 'At least one ingredient is required';
        }
        
        if (empty($mix_data['packaging_id'])) {
            $errors[] = 'Packaging selection is required';
        }
        
        return empty($errors) ? true : $errors;
    }

    /**
     * Formatuj dane mieszanki dla wyświetlania
     */
    public static function format_mix_for_display($mix) {
        if (!$mix) {
            return null;
        }
        
        // Dekoduj dane JSON
        if (is_string($mix->mix_data)) {
            $mix->mix_data = json_decode($mix->mix_data, true);
        }
        
        // Formatuj daty
        if ($mix->created_at) {
            $mix->created_at_formatted = date_i18n('F j, Y', strtotime($mix->created_at));
        }
        
        return $mix;
    }

    /**
     * Pobierz popularne składniki
     */
    public static function get_popular_ingredients($limit = 10) {
        global $wpdb;
        
        // Prosta implementacja - można rozbudować o statystyki użycia
        return $wpdb->get_results(
            $wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}herbal_ingredients 
                WHERE visible = 1 
                ORDER BY sort_order ASC, name ASC 
                LIMIT %d
            ", $limit)
        );
    }

    /**
     * Sprawdź czy użytkownik może edytować mieszankę
     */
    public static function can_user_edit_mix($user_id, $mix_id) {
        global $wpdb;
        
        $mix = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}herbal_mixes WHERE id = %d",
                $mix_id
            )
        );
        
        if (!$mix) {
            return false;
        }
        
        // Użytkownik może edytować własne mieszanki lub admin może edytować wszystkie
        return ($mix->user_id == $user_id) || current_user_can('manage_options');
    }
}