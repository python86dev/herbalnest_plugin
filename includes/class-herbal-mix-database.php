<?php
/**
 * Herbal Mix Database - COMPLETE CORRECTED VERSION
 * Maintains all original functionality with consistent column naming
 * File: includes/class-herbal-mix-database.php
 * 
 * FIXED: All column names now consistently use `price_point` and `point_earned`
 */

if (!defined('ABSPATH')) {
    exit;
}

class Herbal_Mix_Database {

    /**
     * Creates all database tables during plugin activation
     * COMPLETE VERSION - maintains original names + adds new functionality
     */
    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // === ORIGINAL TABLES (NO CHANGES - KEEP ALL NAMES) ===
        
        // Table: herbal_packaging (ORIGINAL - do not change!)
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

        // Table: herbal_categories (ORIGINAL - do not change!)
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

        // Table: herbal_ingredients (ORIGINAL - do not change!)
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

        // Table: herbal_mixes (ORIGINAL - do not change!)
        $table4 = $wpdb->prefix . 'herbal_mixes';
        dbDelta("
            CREATE TABLE {$table4} (
                id INT NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                mix_name VARCHAR(255) NOT NULL,
                mix_description TEXT,
                mix_image TEXT,
                mix_data LONGTEXT NOT NULL,
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
        
        // === EXTENDED TABLES (ADDED FROM HerbalPointsManager) ===
        
        // Table: herbal_points_history (MAIN POINTS TABLE)
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

        // Table: herbal_comments (NEW - comments and reviews)
        $table6 = $wpdb->prefix . 'herbal_comments';
        dbDelta("
            CREATE TABLE {$table6} (
                id INT NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                parent_type ENUM('product', 'mix', 'blog_post', 'review') NOT NULL,
                parent_id BIGINT UNSIGNED NOT NULL,
                parent_comment_id INT DEFAULT NULL,
                content TEXT NOT NULL,
                likes_count INT DEFAULT 0,
                replies_count INT DEFAULT 0,
                status ENUM('approved', 'pending', 'spam', 'trash') DEFAULT 'approved',
                is_pinned TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY parent_type (parent_type, parent_id),
                KEY user_id (user_id),
                KEY status (status),
                KEY parent_comment_id (parent_comment_id),
                KEY created_at (created_at)
            ) {$charset_collate};
        ");

        // Table: herbal_reviews (NEW - detailed product reviews)
        $table7 = $wpdb->prefix . 'herbal_reviews';
        dbDelta("
            CREATE TABLE {$table7} (
                id INT NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                mix_id INT DEFAULT NULL,
                rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                pros TEXT,
                cons TEXT,
                helpful_yes INT DEFAULT 0,
                helpful_no INT DEFAULT 0,
                comments_count INT DEFAULT 0,
                verified_purchase TINYINT(1) DEFAULT 0,
                status ENUM('approved', 'pending', 'rejected', 'spam') DEFAULT 'approved',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY product_id (product_id),
                KEY user_id (user_id),
                KEY mix_id (mix_id),
                KEY rating (rating),
                KEY status (status),
                KEY verified_purchase (verified_purchase)
            ) {$charset_collate};
        ");

        // Table: herbal_notifications (NEW - notifications)
        $table8 = $wpdb->prefix . 'herbal_notifications';
        dbDelta("
            CREATE TABLE {$table8} (
                id INT NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                action_url TEXT DEFAULT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                read_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY type (type),
                KEY is_read (is_read),
                KEY created_at (created_at)
            ) {$charset_collate};
        ");

        // Insert sample data
        self::insert_sample_data();
    }

    /**
     * Insert sample data for testing
     */
    public static function insert_sample_data() {
        global $wpdb;

        // Check if categories already exist
        $existing_categories = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}herbal_categories");
        if ($existing_categories > 0) {
            return; // Data already exists
        }

        // Insert sample categories
        $categories = [
            ['name' => 'Relaxing', 'description' => 'Herbs that promote relaxation and calm', 'sort_order' => 1, 'visible' => 1],
            ['name' => 'Digestive', 'description' => 'Herbs that aid digestion', 'sort_order' => 2, 'visible' => 1],
            ['name' => 'Energising', 'description' => 'Herbs that boost energy and vitality', 'sort_order' => 3, 'visible' => 1],
            ['name' => 'Immune Support', 'description' => 'Herbs that support immune system', 'sort_order' => 4, 'visible' => 1]
        ];

        foreach ($categories as $category) {
            $wpdb->insert($wpdb->prefix . 'herbal_categories', $category);
        }

        // Insert sample packaging
        $packaging = [
            [
                'name' => '50g Glass Jar',
                'herb_capacity' => 50,
                'price' => 0.00,
                'price_point' => 0,
                'point_earned' => 10,
                'available' => 1
            ],
            [
                'name' => '100g Glass Jar',
                'herb_capacity' => 100,
                'price' => 2.20,
                'price_point' => 220,
                'point_earned' => 22,
                'available' => 1
            ],
            [
                'name' => '150g Glass Jar',
                'herb_capacity' => 150,
                'price' => 2.60,
                'price_point' => 260,
                'point_earned' => 26,
                'available' => 1
            ],
            [
                'name' => '200g Premium Jar',
                'herb_capacity' => 200,
                'price' => 3.20,
                'price_point' => 320,
                'point_earned' => 32,
                'available' => 1
            ]
        ];

        foreach ($packaging as $pack) {
            $wpdb->insert($wpdb->prefix . 'herbal_packaging', $pack);
        }

        // Insert sample ingredients
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

    // === ORIGINAL METHODS (PRESERVED FROM ORIGINAL CLASS) ===

    /**
     * Get all packaging available in database (ORIGINAL METHOD)
     * NOTE: uses price_point, point_earned (original names!)
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
     * Get single packaging by ID with validation (ORIGINAL METHOD)
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

        return $packaging;
    }

    /**
     * Get single ingredient by ID with validation (ORIGINAL METHOD)
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

        return $ingredient;
    }

    /**
     * Get all ingredients with categories (ORIGINAL METHOD)
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

        // Group by categories
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
            
            $categories[$cat_id]['ingredients'][] = $ingredient;
        }

        return array_values($categories);
    }

    /**
     * Get all categories (ORIGINAL METHOD)
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

    /**
     * Save new mix (ORIGINAL METHOD)
     */
    public static function save_mix($user_id, $mix_name, $mix_data, $mix_description = '', $status = 'favorite') {
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
                'mix_data' => wp_json_encode($mix_data),
                'status' => sanitize_text_field($status),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('save_failed', 'Failed to save mix: ' . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    /**
     * Get user mixes (ORIGINAL METHOD)
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
     * Delete user mix
     */
    public static function delete_mix($mix_id, $user_id = null) {
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

    /**
     * Get ingredients for frontend (with availability check)
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
        
        // Process results and add availability info
        foreach ($results as $ingredient) {
            $ingredient->price = (float) $ingredient->price;
            $ingredient->price_point = (float) $ingredient->price_point;
            $ingredient->point_earned = (float) $ingredient->point_earned;
            $ingredient->stock = (int) $ingredient->stock;
            
            // Simple availability check
            $ingredient->is_available = $ingredient->stock > 0;
            
            // If packaging capacity is specified, could add more complex availability logic here
            if ($packaging_capacity && $packaging_capacity < 50) {
                // Example: some ingredients might not be suitable for small packages
                // This is just an example - implement your own logic
            }
        }
        
        return $results;
    }

    // === NEW METHODS (ADDED FROM HerbalPointsManager) ===

    /**
     * Record points transaction in history
     */
    public static function record_points_transaction($user_id, $points_change, $transaction_type, $reference_id = null, $points_before = 0, $points_after = 0, $notes = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'herbal_points_history';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        if ($table_exists !== $table_name) {
            // Table doesn't exist, probably need to run install()
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
     * Get user points history
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

    /**
     * Get points statistics
     */
    public static function get_points_statistics() {
        global $wpdb;
        
        // Total points in system
        $total_points = $wpdb->get_var("
            SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'reward_points'
        ") ?: 0;
        
        // Total users with points
        $total_users = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'reward_points' 
            AND CAST(meta_value AS DECIMAL(10,2)) > 0
        ") ?: 0;
        
        // Average points per user
        $avg_points = $total_users > 0 ? ($total_points / $total_users) : 0;
        
        // Transactions today
        $today = date('Y-m-d');
        $transactions_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}herbal_points_history 
            WHERE DATE(created_at) = %s
        ", $today)) ?: 0;
        
        return array(
            'total_points' => $total_points,
            'total_users' => $total_users,
            'avg_points' => $avg_points,
            'transactions_today' => $transactions_today
        );
    }

    /**
     * Add points to user
     */
    public static function add_user_points($user_id, $points, $transaction_type = 'manual', $reference_id = null, $notes = '') {
        if (!$user_id || $points <= 0) {
            return false;
        }
        
        $current_points = (float) get_user_meta($user_id, 'reward_points', true) ?: 0;
        $new_points = $current_points + $points;
        
        // Update user meta
        update_user_meta($user_id, 'reward_points', $new_points);
        
        // Record transaction
        self::record_points_transaction(
            $user_id, 
            $points, 
            $transaction_type, 
            $reference_id, 
            $current_points, 
            $new_points, 
            $notes
        );
        
        return $new_points;
    }

    /**
     * Subtract points from user
     */
    public static function subtract_user_points($user_id, $points, $transaction_type = 'purchase', $reference_id = null, $notes = '') {
        if (!$user_id || $points <= 0) {
            return false;
        }
        
        $current_points = (float) get_user_meta($user_id, 'reward_points', true) ?: 0;
        
        if ($current_points < $points) {
            return new WP_Error('insufficient_points', 'User does not have enough points');
        }
        
        $new_points = $current_points - $points;
        
        // Update user meta
        update_user_meta($user_id, 'reward_points', $new_points);
        
        // Record transaction
        self::record_points_transaction(
            $user_id, 
            -$points, 
            $transaction_type, 
            $reference_id, 
            $current_points, 
            $new_points, 
            $notes
        );
        
        return $new_points;
    }

    /**
     * Get user current points balance
     */
    public static function get_user_points($user_id) {
        if (!$user_id) {
            return 0;
        }
        
        return (float) get_user_meta($user_id, 'reward_points', true) ?: 0;
    }

    /**
     * Validate mix data structure
     */
    public static function validate_mix_data($mix_data) {
        if (is_string($mix_data)) {
            $mix_data = json_decode($mix_data, true);
        }
        
        if (!is_array($mix_data)) {
            return false;
        }
        
        // Check required fields
        $required_fields = ['packaging', 'ingredients'];
        foreach ($required_fields as $field) {
            if (!isset($mix_data[$field])) {
                return false;
            }
        }
        
        // Validate packaging
        if (!isset($mix_data['packaging']['id']) || !is_numeric($mix_data['packaging']['id'])) {
            return false;
        }
        
        // Validate ingredients
        if (!is_array($mix_data['ingredients']) || empty($mix_data['ingredients'])) {
            return false;
        }
        
        foreach ($mix_data['ingredients'] as $ingredient) {
            if (!isset($ingredient['id']) || !isset($ingredient['weight']) || 
                !is_numeric($ingredient['id']) || !is_numeric($ingredient['weight'])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Calculate mix totals
     */
    public static function calculate_mix_totals($mix_data) {
        if (is_string($mix_data)) {
            $mix_data = json_decode($mix_data, true);
        }
        
        if (!self::validate_mix_data($mix_data)) {
            return new WP_Error('invalid_data', 'Invalid mix data');
        }
        
        $totals = [
            'total_weight' => 0,
            'total_price' => 0,
            'total_price_points' => 0,
            'total_points_earned' => 0
        ];
        
        // Add packaging costs
        if (isset($mix_data['packaging'])) {
            $packaging = $mix_data['packaging'];
            $totals['total_price'] += (float) ($packaging['price'] ?? 0);
            $totals['total_price_points'] += (float) ($packaging['price_point'] ?? 0);
            $totals['total_points_earned'] += (float) ($packaging['point_earned'] ?? 0);
        }
        
        // Add ingredient costs
        foreach ($mix_data['ingredients'] as $ingredient) {
            $weight = (float) $ingredient['weight'];
            $price = (float) ($ingredient['price'] ?? 0);
            $price_point = (float) ($ingredient['price_point'] ?? 0);
            $point_earned = (float) ($ingredient['point_earned'] ?? 0);
            
            $totals['total_weight'] += $weight;
            $totals['total_price'] += ($price * $weight);
            $totals['total_price_points'] += ($price_point * $weight);
            $totals['total_points_earned'] += ($point_earned * $weight);
        }
        
        return $totals;
    }

    /**
     * Create WooCommerce product from mix
     */
    public static function create_woocommerce_product_from_mix($mix_id, $user_id = null) {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_required', 'WooCommerce is required');
        }
        
        global $wpdb;
        
        $mix = $wpdb->get_row($wpdb->prepare("
            SELECT * 
              FROM {$wpdb->prefix}herbal_mixes 
             WHERE id = %d
        ", $mix_id));
        
        if (!$mix) {
            return new WP_Error('mix_not_found', 'Mix not found');
        }
        
        if ($user_id && $mix->user_id != $user_id) {
            return new WP_Error('permission_denied', 'Permission denied');
        }
        
        $mix_data = json_decode($mix->mix_data, true);
        if (!$mix_data) {
            return new WP_Error('invalid_mix_data', 'Invalid mix data');
        }
        
        $totals = self::calculate_mix_totals($mix_data);
        if (is_wp_error($totals)) {
            return $totals;
        }
        
        // Create WooCommerce product
        $product = new WC_Product_Simple();
        $product->set_name($mix->mix_name);
        $product->set_description($mix->mix_description ?: 'Custom herbal mix');
        $product->set_short_description('Weight: ' . $totals['total_weight'] . 'g');
        $product->set_price($totals['total_price']);
        $product->set_regular_price($totals['total_price']);
        $product->set_manage_stock(true);
        $product->set_stock_quantity(1);
        $product->set_sku('herbal-mix-' . $mix_id);
        $product->set_catalog_visibility('hidden'); // Hidden by default
        
        $product_id = $product->save();
        
        if (!$product_id) {
            return new WP_Error('product_creation_failed', 'Failed to create product');
        }
        
        // Add custom meta for points (using consistent names)
        update_post_meta($product_id, 'price_point', $totals['total_price_points']);
        update_post_meta($product_id, 'point_earned', $totals['total_points_earned']);
        update_post_meta($product_id, 'herbal_mix_id', $mix_id);
        update_post_meta($product_id, 'herbal_mix_data', $mix->mix_data);
        
        // Update mix record with product ID
        $wpdb->update(
            $wpdb->prefix . 'herbal_mixes',
            ['base_product_id' => $product_id],
            ['id' => $mix_id],
            ['%d'],
            ['%d']
        );
        
        return $product_id;
    }

    /**
     * Get mix by ID
     */
    public static function get_mix($mix_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * 
              FROM {$wpdb->prefix}herbal_mixes 
             WHERE id = %d
        ", $mix_id));
    }

    /**
     * Update mix
     */
    public static function update_mix($mix_id, $data, $user_id = null) {
        global $wpdb;
        
        $where = ['id' => $mix_id];
        $where_format = ['%d'];
        
        if ($user_id) {
            $where['user_id'] = $user_id;
            $where_format[] = '%d';
        }
        
        // Prepare data for update
        $update_data = [];
        $update_format = [];
        
        if (isset($data['mix_name'])) {
            $update_data['mix_name'] = sanitize_text_field($data['mix_name']);
            $update_format[] = '%s';
        }
        
        if (isset($data['mix_description'])) {
            $update_data['mix_description'] = sanitize_textarea_field($data['mix_description']);
            $update_format[] = '%s';
        }
        
        if (isset($data['mix_data'])) {
            $update_data['mix_data'] = is_array($data['mix_data']) ? wp_json_encode($data['mix_data']) : $data['mix_data'];
            $update_format[] = '%s';
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
            $update_format[] = '%s';
        }
        
        if (isset($data['mix_image'])) {
            $update_data['mix_image'] = esc_url_raw($data['mix_image']);
            $update_format[] = '%s';
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', 'No data to update');
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'herbal_mixes',
            $update_data,
            $where,
            $update_format,
            $where_format
        );
        
        return $result !== false;
    }

    /**
     * Search mixes
     */
    public static function search_mixes($search_term, $status = 'favorite', $limit = 20, $offset = 0) {
        global $wpdb;
        
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT m.*, u.display_name as author_name
              FROM {$wpdb->prefix}herbal_mixes m
         LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
             WHERE (m.mix_name LIKE %s OR m.mix_description LIKE %s)
               AND m.status = %s
          ORDER BY m.created_at DESC
             LIMIT %d OFFSET %d
        ", $search_term, $search_term, $status, $limit, $offset));
    }

    /**
     * Get popular mixes
     */
    public static function get_popular_mixes($limit = 10, $days = 30) {
        global $wpdb;
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT m.*, u.display_name as author_name,
                   COALESCE(m.like_count, 0) as total_likes
              FROM {$wpdb->prefix}herbal_mixes m
         LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
             WHERE m.status = 'favorite'
               AND m.created_at >= %s
          ORDER BY total_likes DESC, m.created_at DESC
             LIMIT %d
        ", $date_from, $limit));
    }

    /**
     * Clean old data (for maintenance)
     */
    public static function cleanup_old_data($days = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean old notifications
        $deleted_notifications = $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}herbal_notifications 
             WHERE is_read = 1 
               AND created_at < %s
        ", $cutoff_date));
        
        // Clean old points history (keep important transactions)
        $deleted_points = $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}herbal_points_history 
             WHERE transaction_type IN ('manual', 'admin_adjustment') 
               AND created_at < %s
        ", $cutoff_date));
        
        return [
            'notifications_deleted' => $deleted_notifications,
            'points_history_deleted' => $deleted_points
        ];
    }

    /**
     * Export user data (GDPR compliance)
     */
    public static function export_user_data($user_id) {
        global $wpdb;
        
        $data = [
            'user_id' => $user_id,
            'export_date' => current_time('mysql'),
            'points_balance' => get_user_meta($user_id, 'reward_points', true) ?: 0
        ];
        
        // Get user mixes
        $data['mixes'] = $wpdb->get_results($wpdb->prepare("
            SELECT mix_name, mix_description, created_at, status 
              FROM {$wpdb->prefix}herbal_mixes 
             WHERE user_id = %d
        ", $user_id));
        
        // Get points history
        $data['points_history'] = $wpdb->get_results($wpdb->prepare("
            SELECT points_change, transaction_type, notes, created_at 
              FROM {$wpdb->prefix}herbal_points_history 
             WHERE user_id = %d 
          ORDER BY created_at DESC
        ", $user_id));
        
        // Get reviews
        $data['reviews'] = $wpdb->get_results($wpdb->prepare("
            SELECT title, content, rating, created_at 
              FROM {$wpdb->prefix}herbal_reviews 
             WHERE user_id = %d
        ", $user_id));
        
        // Get comments
        $data['comments'] = $wpdb->get_results($wpdb->prepare("
            SELECT content, parent_type, created_at 
              FROM {$wpdb->prefix}herbal_comments 
             WHERE user_id = %d
        ", $user_id));
        
        return $data;
    }

    /**
     * Delete user data (GDPR compliance)
     */
    public static function delete_user_data($user_id, $anonymize = true) {
        global $wpdb;
        
        if (!$user_id) {
            return false;
        }
        
        $deleted = [];
        
        if ($anonymize) {
            // Anonymize instead of delete to maintain data integrity
            
            // Anonymize mixes
            $deleted['mixes'] = $wpdb->update(
                $wpdb->prefix . 'herbal_mixes',
                ['user_id' => 0],
                ['user_id' => $user_id],
                ['%d'],
                ['%d']
            );
            
            // Anonymize points history
            $deleted['points_history'] = $wpdb->update(
                $wpdb->prefix . 'herbal_points_history',
                ['user_id' => 0],
                ['user_id' => $user_id],
                ['%d'],
                ['%d']
            );
            
            // Anonymize reviews
            $deleted['reviews'] = $wpdb->update(
                $wpdb->prefix . 'herbal_reviews',
                ['user_id' => 0],
                ['user_id' => $user_id],
                ['%d'],
                ['%d']
            );
            
            // Anonymize comments
            $deleted['comments'] = $wpdb->update(
                $wpdb->prefix . 'herbal_comments',
                ['user_id' => 0],
                ['user_id' => $user_id],
                ['%d'],
                ['%d']
            );
            
        } else {
            // Complete deletion
            
            $deleted['mixes'] = $wpdb->delete($wpdb->prefix . 'herbal_mixes', ['user_id' => $user_id], ['%d']);
            $deleted['points_history'] = $wpdb->delete($wpdb->prefix . 'herbal_points_history', ['user_id' => $user_id], ['%d']);
            $deleted['reviews'] = $wpdb->delete($wpdb->prefix . 'herbal_reviews', ['user_id' => $user_id], ['%d']);
            $deleted['comments'] = $wpdb->delete($wpdb->prefix . 'herbal_comments', ['user_id' => $user_id], ['%d']);
        }
        
        // Always delete notifications
        $deleted['notifications'] = $wpdb->delete($wpdb->prefix . 'herbal_notifications', ['user_id' => $user_id], ['%d']);
        
        // Delete user meta
        delete_user_meta($user_id, 'reward_points');
        
        return $deleted;
    }

    /**
     * Get database table status (for admin diagnostics)
     */
    public static function get_table_status() {
        global $wpdb;
        
        $tables = [
            'herbal_packaging',
            'herbal_categories', 
            'herbal_ingredients',
            'herbal_mixes',
            'herbal_points_history',
            'herbal_comments',
            'herbal_reviews',
            'herbal_notifications'
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
     * Optimize database tables
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'herbal_packaging',
            $wpdb->prefix . 'herbal_categories', 
            $wpdb->prefix . 'herbal_ingredients',
            $wpdb->prefix . 'herbal_mixes',
            $wpdb->prefix . 'herbal_points_history',
            $wpdb->prefix . 'herbal_comments',
            $wpdb->prefix . 'herbal_reviews',
            $wpdb->prefix . 'herbal_notifications'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE {$table}");
            $results[$table] = $result !== false;
        }
        
        return $results;
    }

    /**
     * Backup essential data
     */
    public static function backup_essential_data() {
        global $wpdb;
        
        $backup = [
            'timestamp' => current_time('mysql'),
            'data' => []
        ];
        
        // Backup categories
        $backup['data']['categories'] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}herbal_categories", ARRAY_A);
        
        // Backup packaging
        $backup['data']['packaging'] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}herbal_packaging", ARRAY_A);
        
        // Backup ingredients
        $backup['data']['ingredients'] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}herbal_ingredients", ARRAY_A);
        
        return $backup;
    }

    /**
     * Restore essential data from backup
     */
    public static function restore_essential_data($backup_data) {
        global $wpdb;
        
        if (!is_array($backup_data) || !isset($backup_data['data'])) {
            return new WP_Error('invalid_backup', 'Invalid backup data format');
        }
        
        $restored = [];
        
        try {
            // Restore categories
            if (isset($backup_data['data']['categories'])) {
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}herbal_categories");
                foreach ($backup_data['data']['categories'] as $category) {
                    $wpdb->insert($wpdb->prefix . 'herbal_categories', $category);
                }
                $restored['categories'] = count($backup_data['data']['categories']);
            }
            
            // Restore packaging
            if (isset($backup_data['data']['packaging'])) {
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}herbal_packaging");
                foreach ($backup_data['data']['packaging'] as $package) {
                    $wpdb->insert($wpdb->prefix . 'herbal_packaging', $package);
                }
                $restored['packaging'] = count($backup_data['data']['packaging']);
            }
            
            // Restore ingredients
            if (isset($backup_data['data']['ingredients'])) {
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}herbal_ingredients");
                foreach ($backup_data['data']['ingredients'] as $ingredient) {
                    $wpdb->insert($wpdb->prefix . 'herbal_ingredients', $ingredient);
                }
                $restored['ingredients'] = count($backup_data['data']['ingredients']);
            }
            
            return $restored;
            
        } catch (Exception $e) {
            return new WP_Error('restore_failed', 'Failed to restore data: ' . $e->getMessage());
        }
    }
}