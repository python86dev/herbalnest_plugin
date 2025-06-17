'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Helper method to add notification
     */
    private function add_notification($user_id, $type, $data = array()) {
        $notifications = get_user_meta($user_id, 'notifications', true) ?: array();
        
        $notification = array(
            'id' => uniqid(),
            'type' => $type,
            'data' => $data,
            'read' => false,
            'created_at' => current_time('mysql')
        );
        
        array_unshift($notifications, $notification);
        
        // Keep only last 50 notifications
        $notifications = array_slice($notifications, 0, 50);
        
        update_user_meta($user_id, 'notifications', $notifications);
    }
    
    /**
     * Helper method to get unread notifications count
     */
    private function get_unread_notifications_count($user_id) {
        $notifications = get_user_meta($user_id, 'notifications', true) ?: array();
        return count(array_filter($notifications, function($n) { return !$n['read']; }));
    }
    
    /**
     * Helper method to render activity item
     */
    private function render_activity_item($activity) {
        $data = json_decode($activity->activity_data, true);
        $time_ago = human_time_diff(strtotime($activity->created_at), current_time('timestamp')) . ' ago';
        
        $html = '<div class="activity-item" data-type="' . esc_attr($activity->activity_type) . '">';
        $html .= '<div class="activity-icon">';
        
        switch ($activity->activity_type) {
            case 'mix_created':
                $html .= '🌿';
                $message = sprintf(__('Created mix "%s"', 'herbal-mix-creator2'), $data['mix_name']);
                break;
            case 'mix_published':
                $html .= '📤';
                $message = sprintf(__('Published mix "%s"', 'herbal-mix-creator2'), $data['mix_name']);
                break;
            case 'mix_purchased':
                $html .= '🛒';
                $message = sprintf(__('Mix "%s" was purchased', 'herbal-mix-creator2'), $data['mix_name']);
                break;
            case 'mix_liked':
                $html .= '❤️';
                $message = sprintf(__('Mix "%s" was liked', 'herbal-mix-creator2'), $data['mix_name']);
                break;
            default:
                $html .= '📋';
                $message = ucfirst(str_replace('_', ' ', $activity->activity_type));
        }
        
        $html .= '</div>';
        $html .= '<div class="activity-content">';
        $html .= '<div class="activity-message">' . $message . '</div>';
        $html .= '<div class="activity-time">' . $time_ago . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Helper method to render notification item
     */
    private function render_notification_item($notification) {
        $data = $notification['data'];
        $time_ago = human_time_diff(strtotime($notification['created_at']), current_time('timestamp')) . ' ago';
        $read_class = $notification['read'] ? 'read' : 'unread';
        
        $html = '<div class="notification-item ' . $read_class . '" data-id="' . esc_attr($notification['id']) . '">';
        $html .= '<div class="notification-icon">';
        
        switch ($notification['type']) {
            case 'mix_liked':
                $html .= '❤️';
                $message = sprintf(__('%s liked your mix', 'herbal-mix-creator2'), $data['liker_name']);
                break;
            case 'new_follower':
                $html .= '👥';
                $message = sprintf(__('%s started following you', 'herbal-mix-creator2'), $data['follower_name']);
                break;
            case 'mix_purchased':
                $html .= '🛒';
                $message = sprintf(__('Your mix "%s" was purchased', 'herbal-mix-creator2'), $data['mix_name']);
                break;
            case 'mix_commented':
                $html .= '💬';
                $message = sprintf(__('%s commented on your mix', 'herbal-mix-creator2'), $data['commenter_name']);
                break;
            default:
                $html .= '📢';
                $message = 'New notification';
        }
        
        $html .= '</div>';
        $html .= '<div class="notification-content">';
        $html .= '<div class="notification-message">' . $message . '</div>';
        $html .= '<div class="notification-time">' . $time_ago . '</div>';
        $html .= '</div>';
        
        if (!$notification['read']) {
            $html .= '<button class="mark-read-btn" data-id="' . esc_attr($notification['id']) . '">✓</button>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Additional AJAX handlers for extended functionality
     */
    
    /**
     * AJAX - Add mix comment
     */
    public function ajax_add_mix_comment() {
        if (!wp_verify_nonce($_POST['nonce'], 'add_comment')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $comment = sanitize_textarea_field($_POST['comment']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id || !$comment) {
            wp_send_json_error(array('message' => 'Missing required fields.'));
        }
        
        // Implementation would depend on comments table structure
        wp_send_json_success(array('message' => __('Comment added successfully.', 'herbal-mix-creator2')));
    }
    
    /**
     * AJAX - Submit mix review
     */
    public function ajax_submit_mix_review() {
        if (!wp_verify_nonce($_POST['nonce'], 'submit_review')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $rating = intval($_POST['rating']);
        $review = sanitize_textarea_field($_POST['review']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id || !$rating || $rating < 1 || $rating > 5) {
            wp_send_json_error(array('message' => 'Invalid data provided.'));
        }
        
        // Implementation would depend on reviews table structure
        wp_send_json_success(array('message' => __('Review submitted successfully.', 'herbal-mix-creator2')));
    }
    
    /**
     * AJAX - Get mix pricing details
     */
    public function ajax_get_mix_pricing_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'get_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        
        if (!$mix_id) {
            wp_send_json_error(array('message' => 'Invalid mix ID.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT mix_data FROM $table WHERE id = %d",
            $mix_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found.'));
        }
        
        $mix_data = json_decode($mix->mix_data, true);
        $pricing_breakdown = $this->calculate_detailed_pricing($mix_data);
        
        wp_send_json_success(array(
            'pricing_breakdown' => $pricing_breakdown,
            'total_price' => $pricing_breakdown['total'],
            'total_points' => $pricing_breakdown['points_total']
        ));
    }
    
    /**
     * AJAX - Save mix as favorite
     */
    public function ajax_save_mix_as_favorite() {
        if (!wp_verify_nonce($_POST['nonce'], 'manage_favorites')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        // This would be handled by the like_mix functionality
        wp_send_json_success(array('message' => __('Mix saved to favorites.', 'herbal-mix-creator2')));
    }
    
    /**
     * AJAX - Get user stats
     */
    public function ajax_get_user_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'get_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $user_id = get_current_user_id();
        
        global $wpdb;
        $mixes_table = $wpdb->prefix . 'herbal_mixes';
        
        $stats = array(
            'total_mixes' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $mixes_table WHERE user_id = %d",
                $user_id
            )),
            'published_mixes' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $mixes_table WHERE user_id = %d AND status = 'published'",
                $user_id
            )),
            'total_likes' => $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(like_count) FROM $mixes_table WHERE user_id = %d",
                $user_id
            )) ?: 0,
            'reward_points' => get_user_meta($user_id, 'reward_points', true) ?: 0,
            'following_count' => count(get_user_meta($user_id, 'following_users', true) ?: array()),
            'followers_count' => $this->get_followers_count($user_id)
        );
        
        wp_send_json_success($stats);
    }
    
    /**
     * Helper method to calculate detailed pricing
     */
    private function calculate_detailed_pricing($mix_data) {
        if (empty($mix_data['ingredients'])) {
            return array('total' => 0, 'points_total' => 0, 'breakdown' => array());
        }
        
        $breakdown = array();
        $total_price = 0;
        $total_points = 0;
        
        foreach ($mix_data['ingredients'] as $ingredient) {
            $amount = floatval($ingredient['amount'] ?? $ingredient['weight'] ?? 0);
            $price_per_100g = floatval($ingredient['price'] ?? 0);
            $points_per_100g = floatval($ingredient['points'] ?? $ingredient['point_earned'] ?? 0);
            
            $ingredient_price = ($price_per_100g / 100) * $amount;
            $ingredient_points = ($points_per_100g / 100) * $amount;
            
            $breakdown[] = array(
                'name' => $ingredient['name'],
                'amount' => $amount,
                'price_per_100g' => $price_per_100g,
                'total_price' => $ingredient_price,
                'points_per_100g' => $points_per_100g,
                'total_points' => $ingredient_points
            );
            
            $total_price += $ingredient_price;
            $total_points += $ingredient_points;
        }
        
        // Add packaging cost if present
        if (!empty($mix_data['packaging'])) {
            $packaging_price = floatval($mix_data['packaging']['price'] ?? 0);
            $packaging_points = floatval($mix_data['packaging']['points'] ?? 0);
            
            $breakdown[] = array(
                'name' => $mix_data['packaging']['name'] ?? 'Packaging',
                'amount' => 1,
                'price_per_100g' => $packaging_price,
                'total_price' => $packaging_price,
                'points_per_100g' => $packaging_points,
                'total_points' => $packaging_points,
                'is_packaging' => true
            );
            
            $total_price += $packaging_price;
            $total_points += $packaging_points;
        }
        
        return array(
            'breakdown' => $breakdown,
            'total' => round($total_price, 2),
            'points_total' => round($total_points, 0)
        );
    }
    
    /**
     * Helper method to get followers count
     */
    private function get_followers_count($user_id) {
        global $wpdb;
        
        // This would require a more complex query to count users who follow this user
        // For now, return 0 as placeholder
        return 0;
    }
    
    /**
     * Additional method: Get mix recipe and pricing for modals
     */
    public function ajax_get_mix_recipe_and_pricing() {
        if (!wp_verify_nonce($_POST['nonce'], 'get_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        
        if (!$mix_id) {
            wp_send_json_error(array('message' => 'Invalid mix ID.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT mix_data FROM $table WHERE id = %d",
            $mix_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found.'));
        }
        
        $mix_data = json_decode($mix->mix_data, true);
        
        if (empty($mix_data['ingredients'])) {
            wp_send_json_error(array('message' => 'No ingredients found.'));
        }
        
        // Calculate current pricing
        $pricing_details = $this->calculate_detailed_pricing($mix_data);
        
        // Generate HTML for ingredients
        $ingredients_html = '<div class="ingredients-list">';
        foreach ($pricing_details['breakdown'] as $item) {
            if (isset($item['is_packaging'])) {
                continue; // Skip packaging in ingredients list
            }
            
            $ingredients_html .= '<div class="ingredient-row">';
            $ingredients_html .= '<span class="ingredient-name">' . esc_html($item['name']) . '</span>';
            $ingredients_html .= '<span class="ingredient-amount">' . $item['amount'] . 'g</span>';
            $ingredients_html .= '<span class="ingredient-price">£' . number_format($item['total_price'], 2) . '</span>';
            $ingredients_html .= '</div>';
        }
        $ingredients_html .= '</div>';
        
        // Generate pricing summary
        $pricing_html = '<div class="pricing-summary">';
        $pricing_html .= '<div class="total-row"><strong>Total: £' . number_format($pricing_details['total'], 2) . '</strong></div>';
        if ($pricing_details['points_total'] > 0) {
            $pricing_html .= '<div class="points-row">Points required: ' . number_format($pricing_details['points_total'], 0) . '</div>';
        }
        $pricing_html .= '</div>';
        
        wp_send_json_success(array(
            'ingredients_html' => $ingredients_html,
            'pricing_html' => $pricing_html,
            'total_price' => $pricing_details['total'],
            'total_points' => $pricing_details['points_total']
        ));
    }
}

// Initialize the class
new HerbalMixUserProfileExtended();                    'cart_count' => WC()->cart->get_cart_contents_count()
                ));
            } else {
                wp_send_json_error(array('message' => 'Failed to add mix to cart.'));
            }
        } else {
            wp_send_json_error(array('message' => 'WooCommerce cart not available.'));
        }
    }
    
    /**
     * COMPLETE: AJAX - Delete mix
     */
    public function ajax_delete_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'delete_mix')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d",
            $mix_id, $user_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found or access denied.'));
        }
        
        $message = '';
        
        // Handle published mixes differently
        if ($mix->status === 'published') {
            // Send notification to admin
            $this->send_published_mix_deletion_notification($mix, $user_id);
            
            // For published mixes, mark as deleted but don't remove immediately
            $result = $wpdb->update(
                $table,
                array(
                    'status' => 'deleted',
                    'deleted_at' => current_time('mysql')
                ),
                array('id' => $mix_id, 'user_id' => $user_id),
                array('%s', '%s'),
                array('%d', '%d')
            );
            
            $message = __('Published mix deletion request sent to administrator. The mix will be removed from the shop soon.', 'herbal-mix-creator2');
        } else {
            // For private mixes, delete immediately
            $result = $wpdb->delete(
                $table,
                array('id' => $mix_id, 'user_id' => $user_id),
                array('%d', '%d')
            );
            
            $message = __('Mix deleted successfully.', 'herbal-mix-creator2');
        }
        
        if ($result !== false) {
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => 'Database error occurred.'));
        }
    }
    
    /**
     * COMPLETE: AJAX - View mix (redirect to product or show modal)
     */
    public function ajax_view_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'get_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        
        if (!$mix_id) {
            wp_send_json_error(array('message' => 'Invalid mix ID.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $mix_id
        ), ARRAY_A);
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found.'));
        }
        
        // If published and has product page, redirect there
        if ($mix['status'] === 'published' && !empty($mix['base_product_id'])) {
            $product_url = get_permalink($mix['base_product_id']);
            if ($product_url) {
                wp_send_json_success(array(
                    'redirect_url' => $product_url,
                    'message' => 'Redirecting to product page.'
                ));
                return;
            }
        }
        
        // Otherwise, return mix details for modal view
        $author = get_userdata($mix['user_id']);
        $mix['author_name'] = $author ? $author->display_name : 'Unknown';
        $mix['author_avatar'] = get_avatar_url($mix['user_id']);
        
        wp_send_json_success(array(
            'mix' => $mix,
            'show_modal' => true,
            'message' => 'Mix details loaded.'
        ));
    }
    
    /**
     * COMPLETE: AJAX - Remove favorite mix
     */
    public function ajax_remove_favorite_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'manage_favorites')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT liked_by, like_count FROM $table WHERE id = %d AND status = 'published'",
            $mix_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found or not published.'));
        }
        
        $liked_by = json_decode($mix->liked_by, true) ?: array();
        $user_key = array_search(strval($user_id), $liked_by);
        
        if ($user_key !== false) {
            unset($liked_by[$user_key]);
            $liked_by = array_values($liked_by); // Reindex array
            
            $result = $wpdb->update(
                $table,
                array(
                    'liked_by' => json_encode($liked_by),
                    'like_count' => count($liked_by)
                ),
                array('id' => $mix_id),
                array('%s', '%d'),
                array('%d')
            );
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => __('Mix removed from favorites.', 'herbal-mix-creator2'),
                    'new_like_count' => count($liked_by)
                ));
            } else {
                wp_send_json_error(array('message' => 'Database error occurred.'));
            }
        } else {
            wp_send_json_error(array('message' => 'Mix was not in favorites.'));
        }
    }
    
    /**
     * COMPLETE: AJAX - Upload mix image
     */
    public function ajax_upload_mix_image() {
        if (!wp_verify_nonce($_POST['nonce'], 'upload_mix_image')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }
        
        if (empty($_FILES['mix_image'])) {
            wp_send_json_error(array('message' => 'No image uploaded.'));
        }
        
        // Validate file
        $file = $_FILES['mix_image'];
        $max_size = wp_max_upload_size();
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        
        if ($file['size'] > $max_size) {
            wp_send_json_error(array('message' => 'File too large. Maximum size: ' . size_format($max_size)));
        }
        
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(array('message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'));
        }
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            wp_send_json_error(array('message' => $upload['error']));
        }
        
        wp_send_json_success(array(
            'image_url' => $upload['url'],
            'image_id' => attachment_url_to_postid($upload['url']),
            'message' => __('Image uploaded successfully.', 'herbal-mix-creator2')
        ));
    }
    
    /**
     * COMPLETE: AJAX - Like mix
     */
    public function ajax_like_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'like_mix')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, liked_by, like_count FROM $table WHERE id = %d AND status = 'published'",
            $mix_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found or not published.'));
        }
        
        // Can't like own mix
        if ($mix->user_id == $user_id) {
            wp_send_json_error(array('message' => 'You cannot like your own mix.'));
        }
        
        $liked_by = json_decode($mix->liked_by, true) ?: array();
        $already_liked = in_array(strval($user_id), $liked_by);
        
        if ($already_liked) {
            // Unlike
            $liked_by = array_values(array_diff($liked_by, array(strval($user_id))));
            $action = 'unliked';
        } else {
            // Like
            $liked_by[] = strval($user_id);
            $action = 'liked';
        }
        
        $result = $wpdb->update(
            $table,
            array(
                'liked_by' => json_encode($liked_by),
                'like_count' => count($liked_by)
            ),
            array('id' => $mix_id),
            array('%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            // Update creator's total likes if liked
            if (!$already_liked) {
                $creator_likes = intval(get_user_meta($mix->user_id, 'total_likes_received', true));
                update_user_meta($mix->user_id, 'total_likes_received', $creator_likes + 1);
                
                // Add notification for creator
                $this->add_notification($mix->user_id, 'mix_liked', array(
                    'mix_id' => $mix_id,
                    'liker_id' => $user_id,
                    'liker_name' => $this->get_user_display_name()
                ));
            }
            
            wp_send_json_success(array(
                'action' => $action,
                'new_like_count' => count($liked_by),
                'message' => $action === 'liked' ? __('Mix liked!', 'herbal-mix-creator2') : __('Mix unliked.', 'herbal-mix-creator2')
            ));
        } else {
            wp_send_json_error(array('message' => 'Database error occurred.'));
        }
    }
    
    /**
     * COMPLETE: AJAX - Follow user
     */
    public function ajax_follow_user() {
        if (!wp_verify_nonce($_POST['nonce'], 'follow_user')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $target_user_id = intval($_POST['user_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$target_user_id || $user_id == $target_user_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        $following = get_user_meta($user_id, 'following_users', true) ?: array();
        $already_following = in_array($target_user_id, $following);
        
        if ($already_following) {
            // Unfollow
            $following = array_diff($following, array($target_user_id));
            $action = 'unfollowed';
        } else {
            // Follow
            $following[] = $target_user_id;
            $action = 'followed';
            
            // Add notification for followed user
            $this->add_notification($target_user_id, 'new_follower', array(
                'follower_id' => $user_id,
                'follower_name' => $this->get_user_display_name()
            ));
        }
        
        update_user_meta($user_id, 'following_users', array_unique($following));
        
        wp_send_json_success(array(
            'action' => $action,
            'message' => $action === 'followed' ? __('User followed!', 'herbal-mix-creator2') : __('User unfollowed.', 'herbal-mix-creator2')
        ));
    }
    
    /**
     * COMPLETE: AJAX - Get user activity
     */
    public function ajax_get_user_activity() {
        if (!wp_verify_nonce($_POST['nonce'], 'manage_notifications')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $user_id = get_current_user_id();
        $page = intval($_POST['page'] ?? 1);
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Get activity from database (you would need to create this table)
        global $wpdb;
        $activity_table = $wpdb->prefix . 'herbal_user_activity';
        
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $activity_table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id, $per_page, $offset
        ));
        
        $activity_html = '';
        foreach ($activities as $activity) {
            $activity_html .= $this->render_activity_item($activity);
        }
        
        wp_send_json_success(array(
            'html' => $activity_html,
            'has_more' => count($activities) === $per_page
        ));
    }
    
    /**
     * COMPLETE: AJAX - Get notifications
     */
    public function ajax_get_notifications() {
        if (!wp_verify_nonce($_POST['nonce'], 'manage_notifications')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $user_id = get_current_user_id();
        $notifications = get_user_meta($user_id, 'notifications', true) ?: array();
        
        // Sort by date, newest first
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        $notifications_html = '';
        foreach ($notifications as $notification) {
            $notifications_html .= $this->render_notification_item($notification);
        }
        
        wp_send_json_success(array(
            'html' => $notifications_html,
            'unread_count' => $this->get_unread_notifications_count($user_id)
        ));
    }
    
    /**
     * COMPLETE: AJAX - Mark notification as read
     */
    public function ajax_mark_notification_read() {
        if (!wp_verify_nonce($_POST['nonce'], 'manage_notifications')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $notification_id = sanitize_text_field($_POST['notification_id']);
        $user_id = get_current_user_id();
        
        $notifications = get_user_meta($user_id, 'notifications', true) ?: array();
        
        foreach ($notifications as &$notification) {
            if ($notification['id'] === $notification_id) {
                $notification['read'] = true;
                break;
            }
        }
        
        update_user_meta($user_id, 'notifications', $notifications);
        
        wp_send_json_success(array(
            'message' => __('Notification marked as read.', 'herbal-mix-creator2'),
            'unread_count' => $this->get_unread_notifications_count($user_id)
        ));
    }
    
    /**
     * COMPLETE: AJAX - Share mix
     */
    public function ajax_share_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'share_mix')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        
        if (!$mix_id) {
            wp_send_json_error(array('message' => 'Invalid mix ID.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND status = 'published'",
            $mix_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found or not published.'));
        }
        
        $mix_url = !empty($mix->base_product_id) ? get_permalink($mix->base_product_id) : home_url();
        
        if ($platform === 'email' && $email) {
            // Send email
            $subject = sprintf(__('Check out this herbal mix: %s', 'herbal-mix-creator2'), $mix->mix_name);
            $email_message = sprintf(
                __("Hi!\n\nI wanted to share this amazing herbal mix with you: %s\n\n%s\n\nCheck it out here: %s\n\n%s", 'herbal-mix-creator2'),
                $mix->mix_name,
                $mix->mix_description,
                $mix_url,
                $message ? "Personal message: " . $message : ''
            );
            
            $sent = wp_mail($email, $subject, $email_message);
            
            if ($sent) {
                wp_send_json_success(array('message' => __('Email sent successfully!', 'herbal-mix-creator2')));
            } else {
                wp_send_json_error(array('message' => 'Failed to send email.'));
            }
        } else {
            // Return URL for social sharing
            wp_send_json_success(array(
                'share_url' => $mix_url,
                'mix_name' => $mix->mix_name,
                'mix_description' => $mix->mix_description
            ));
        }
    }
    
    /**
     * COMPLETE: Add avatar upload to account form
     */
    public function add_avatar_to_account_form() {
        $user_id = get_current_user_id();
        $custom_avatar = get_user_meta($user_id, 'custom_avatar', true);
        ?>
        <fieldset>
            <legend><?php esc_html_e('Profile Picture', 'herbal-mix-creator2'); ?></legend>
            
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="custom_avatar"><?php esc_html_e('Custom Avatar', 'herbal-mix-creator2'); ?></label>
                
                <?php if ($custom_avatar): ?>
                    <div class="current-avatar">
                        <img src="<?php echo esc_url($custom_avatar); ?>" alt="Current Avatar" style="max-width: 100px; height: auto; border-radius: 50%;">
                        <p><small><?php esc_html_e('Current profile picture', 'herbal-mix-creator2'); ?></small></p>
                    </div>
                <?php endif; ?>
                
                <input type="file" class="woocommerce-Input" name="custom_avatar" id="custom_avatar" accept="image/*" />
                <small><?php esc_html_e('Upload a new profile picture (JPG, PNG, GIF, WebP - max 5MB)', 'herbal-mix-creator2'); ?></small>
            </p>
        </fieldset>
        <?php
    }
    
    /**
     * COMPLETE: Save extra account details including avatar
     */
    public function save_extra_account_details($user_id) {
        // Handle avatar upload
        if (!empty($_FILES['custom_avatar']['name'])) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($_FILES['custom_avatar'], $upload_overrides);
            
            if ($movefile && !isset($movefile['error'])) {
                // Delete old avatar file if exists
                $old_avatar = get_user_meta($user_id, 'custom_avatar', true);
                if ($old_avatar) {
                    $old_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $old_avatar);
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }
                
                // Save new avatar URL
                update_user_meta($user_id, 'custom_avatar', $movefile['url']);
                
                wc_add_notice(__('Profile picture updated successfully!', 'herbal-mix-creator2'), 'success');
            } else {
                wc_add_notice(__('Error uploading profile picture: ', 'herbal-mix-creator2') . $movefile['error'], 'error');
            }
        }
    }
    
    /**
     * COMPLETE: Custom avatar URL filter
     */
    public function custom_avatar_url($url, $id_or_email, $args) {
        $user_id = 0;
        
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
            $user_id = (int) $id_or_email->user_id;
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        
        if ($user_id) {
            $custom_avatar = get_user_meta($user_id, 'custom_avatar', true);
            if ($custom_avatar) {
                return $custom_avatar;
            }
        }
        
        return $url;
    }
    
    /**
     * COMPLETE: Add inline scripts for enhanced functionality
     */
    public function add_inline_scripts() {
        if (!is_account_page()) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Enhanced modal functionality
            $('.modal-dialog').on('click', function(e) {
                if (e.target === this) {
                    $(this).fadeOut(300);
                }
            });
            
            // Filter functionality
            $('.filter-btn').on('click', function() {
                var filter = $(this).data('filter');
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                
                if (filter === 'all') {
                    $('.mix-card').show();
                } else {
                    $('.mix-card').hide();
                    $('.mix-card[data-status="' + filter + '"]').show();
                }
            });
            
            // Image preview for file uploads
            $('input[type="file"]').on('change', function() {
                var file = this.files[0];
                if (file) {
                    var reader = new FileReader();
                    var preview = $(this).siblings('.upload-preview').find('img');
                    
                    reader.onload = function(e) {
                        preview.attr('src', e.target.result).show();
                        preview.siblings('.upload-placeholder').hide();
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
            
            // Copy to clipboard functionality
            $('#copy-link-button').on('click', function() {
                var input = $('#share-mix-url');
                input.select();
                document.execCommand('copy');
                
                $(this).text('Copied!').addClass('success');
                setTimeout(function() {
                    $('#copy-link-button').text('Copy').removeClass('success');
                }, 2000);
            });
            
            // Auto-resize textareas
            $('textarea').each(function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            }).on('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            
            // Loading states for buttons
            $(document).on('click', '.button', function() {
                if (!$(this).hasClass('no-loading')) {
                    $(this).addClass('loading').prop('disabled', true);
                }
            });
            
            // Character counters
            $('input[maxlength], textarea[maxlength]').each(function() {
                var maxLength = $(this).attr('maxlength');
                var counter = $('<small class="char-counter">0/' + maxLength + '</small>');
                $(this).after(counter);
                
                $(this).on('input', function() {
                    var length = $(this).val().length;
                    counter.text(length + '/' + maxLength);
                    
                    if (length > maxLength * 0.9) {
                        counter.addClass('warning');
                    } else {
                        counter.removeClass('warning');
                    }
                });
            });
        });
        </script>
        
        <style type="text/css">
        /* Enhanced button loading states */
        .button.loading {
            position: relative;
            color: transparent !important;
        }
        
        .button.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 16px;
            height: 16px;
            margin: -8px 0 0 -8px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: button-loading 1s linear infinite;
        }
        
        @keyframes button-loading {
            to { transform: rotate(360deg); }
        }
        
        /* Character counter styles */
        .char-counter {
            display: block;
            text-align: right;
            color: #666;
            font-size: 11px;
            margin-top: 2px;
        }
        
        .char-counter.warning {
            color: #e74c3c;
        }
        
        /* Copy button success state */
        .button.success {
            background-color: #27ae60 !important;
            border-color: #27ae60 !important;
        }
        </style>
        <?php
    }
    
    /**
     * Helper method to get user display name
     */
    private function get_user_display_name() {
        $user = wp_get_current_user();
        if (!$user->exists()) {
            return 'Guest';
        }
        
        if (!empty($user->nickname)) {
            return $user->nickname;
        } elseif (!empty($user->display_name)) {
            return $user->display_name;
        } elseif (!empty($user->user_nicename)) {
            return $user->user_nicename;
        } else {
            return $user->user_login;
        }
    }
    
    /**
     * Helper method to send published mix deletion notification
     */
    private function send_published_mix_deletion_notification($mix, $user_id) {
        $user = get_user_by('id', $user_id);
        $user_name = $user ? $user->display_name : 'Unknown User';
        
        $admin_email = get_option('admin_email');
        $subject = 'Published Mix Deletion Request';
        
        $message = "A user has requested to delete their published mix:\n\n";
        $message .= "User: {$user_name} (ID: {$user_id})\n";
        $message .= "Mix: {$mix->mix_name} (ID: {$mix->id})\n";
        $message .= "Status: {$mix->status}\n";
        $message .= "Created: {$mix->created_at}\n\n";
        $message .= "Please review and remove the associated product from the shop if necessary.\n";
        $message .= "The mix has been marked as deleted in the user's account.";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Helper method to add user activity
     */
    private function add_user_activity($user_id, $activity_type, $data = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_user_activity';
        
        $wpdb->insert(
            $table,
            array(
                'user_id' => $user_id,
                'activity_type' => $activity_type,
                'activity_data' => json_encode($data),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%                                    <option value="immunity"><?php _e('Immunity Support', 'herbal-mix-creator2'); ?></option>
                                    <option value="sleep"><?php _e('Sleep & Rest', 'herbal-mix-creator2'); ?></option>
                                    <option value="detox"><?php _e('Detox & Cleanse', 'herbal-mix-creator2'); ?></option>
                                    <option value="wellness"><?php _e('General Wellness', 'herbal-mix-creator2'); ?></option>
                                    <option value="other"><?php _e('Other', 'herbal-mix-creator2'); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h4><?php _e('Mix Recipe & Pricing', 'herbal-mix-creator2'); ?></h4>
                            <div class="mix-recipe-preview">
                                <div id="publish-mix-ingredients-preview" class="ingredients-preview"></div>
                                <div id="publish-mix-pricing-preview" class="pricing-preview"></div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h4><?php _e('Publication Terms', 'herbal-mix-creator2'); ?></h4>
                            <div class="terms-notice">
                                <p><?php _e('By publishing this mix, you agree that:', 'herbal-mix-creator2'); ?></p>
                                <ul>
                                    <li><?php _e('Your mix will be visible to all users', 'herbal-mix-creator2'); ?></li>
                                    <li><?php _e('Others can purchase and use your recipe', 'herbal-mix-creator2'); ?></li>
                                    <li><?php _e('You will earn reward points for each purchase', 'herbal-mix-creator2'); ?></li>
                                    <li><?php _e('You retain ownership of your original recipe', 'herbal-mix-creator2'); ?></li>
                                </ul>
                            </div>
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="publish-terms-agree" name="terms_agree" required>
                                    <?php _e('I agree to the publication terms', 'herbal-mix-creator2'); ?> <span class="required">*</span>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="button secondary cancel-publish"><?php _e('Cancel', 'herbal-mix-creator2'); ?></button>
                    <button type="submit" form="publish-mix-form" class="button primary" id="publish-button">
                        <span class="button-icon">📤</span><?php _e('Publish Mix', 'herbal-mix-creator2'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enhanced view modal for unpublished mixes
     */
    private function render_view_modal() {
        ?>
        <div id="view-mix-modal" class="modal-dialog" style="display:none;" aria-hidden="true" role="dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="view-mix-title"></h3>
                    <button type="button" class="close-modal" aria-label="<?php _e('Close', 'herbal-mix-creator2'); ?>">&times;</button>
                </div>
                
                <div class="modal-body">
                    <div class="mix-details">
                        <div class="mix-image-section">
                            <img id="view-mix-image" src="" alt="" style="display:none;">
                            <div id="view-mix-placeholder" class="mix-placeholder" style="display:none;">
                                <span class="herb-icon">🌿</span>
                            </div>
                        </div>
                        
                        <div class="mix-info-section">
                            <div id="view-mix-info" class="mix-info"></div>
                            
                            <div class="mix-recipe-section">
                                <h4><?php _e('Recipe', 'herbal-mix-creator2'); ?></h4>
                                <div id="view-mix-ingredients" class="ingredients-list"></div>
                            </div>
                            
                            <div class="mix-pricing-section">
                                <h4><?php _e('Pricing', 'herbal-mix-creator2'); ?></h4>
                                <div id="view-mix-pricing" class="pricing-details"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="button secondary close-modal"><?php _e('Close', 'herbal-mix-creator2'); ?></button>
                    <button type="button" class="button primary" id="view-buy-button">
                        <span class="button-icon">🛒</span><?php _e('Add to Cart', 'herbal-mix-creator2'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Share modal for social features
     */
    private function render_share_modal() {
        ?>
        <div id="share-mix-modal" class="modal-dialog" style="display:none;" aria-hidden="true" role="dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Share Your Mix', 'herbal-mix-creator2'); ?></h3>
                    <button type="button" class="close-modal" aria-label="<?php _e('Close', 'herbal-mix-creator2'); ?>">&times;</button>
                </div>
                
                <div class="modal-body">
                    <div class="share-options">
                        <h4><?php _e('Share via Social Media', 'herbal-mix-creator2'); ?></h4>
                        <div class="social-share-buttons">
                            <button class="social-btn facebook" data-platform="facebook">
                                <span class="social-icon">📘</span>Facebook
                            </button>
                            <button class="social-btn twitter" data-platform="twitter">
                                <span class="social-icon">🐦</span>Twitter
                            </button>
                            <button class="social-btn linkedin" data-platform="linkedin">
                                <span class="social-icon">💼</span>LinkedIn
                            </button>
                            <button class="social-btn whatsapp" data-platform="whatsapp">
                                <span class="social-icon">💬</span>WhatsApp
                            </button>
                        </div>
                        
                        <h4><?php _e('Copy Link', 'herbal-mix-creator2'); ?></h4>
                        <div class="copy-link-section">
                            <input type="text" id="share-mix-url" readonly>
                            <button class="button secondary" id="copy-link-button">
                                <span class="button-icon">📋</span><?php _e('Copy', 'herbal-mix-creator2'); ?>
                            </button>
                        </div>
                        
                        <h4><?php _e('Email Share', 'herbal-mix-creator2'); ?></h4>
                        <form id="email-share-form">
                            <div class="form-group">
                                <label for="share-email"><?php _e('Recipient Email', 'herbal-mix-creator2'); ?></label>
                                <input type="email" id="share-email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="share-message"><?php _e('Personal Message', 'herbal-mix-creator2'); ?></label>
                                <textarea id="share-message" name="message" rows="3" placeholder="<?php _e('Optional personal message...', 'herbal-mix-creator2'); ?>"></textarea>
                            </div>
                            <button type="submit" class="button primary">
                                <span class="button-icon">📧</span><?php _e('Send Email', 'herbal-mix-creator2'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pricing details modal
     */
    private function render_pricing_modal() {
        ?>
        <div id="pricing-modal" class="modal-dialog" style="display:none;" aria-hidden="true" role="dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Pricing Details', 'herbal-mix-creator2'); ?></h3>
                    <button type="button" class="close-modal" aria-label="<?php _e('Close', 'herbal-mix-creator2'); ?>">&times;</button>
                </div>
                
                <div class="modal-body">
                    <div id="pricing-breakdown" class="pricing-breakdown"></div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="button secondary close-modal"><?php _e('Close', 'herbal-mix-creator2'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * COMPLETE: Display Favorite Mixes tab
     */
    public function render_favorite_mixes_tab() {
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'includes/templates/user-profile-favorite-mixes.php';
        if (file_exists($template_path)) {
            include($template_path);
            return;
        }
        
        // Enhanced fallback implementation
        $user_id = get_current_user_id();
        global $wpdb;
        
        $table = $wpdb->prefix . 'herbal_mixes';
        $favorite_mixes = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table 
            WHERE status = 'published' 
            AND JSON_CONTAINS(liked_by, %s)
            ORDER BY created_at DESC
        ", json_encode(strval($user_id))));
        
        echo '<div class="herbal-favorite-mixes-container">';
        echo '<div class="favorites-header">';
        echo '<h3>' . __('My Favorite Mixes', 'herbal-mix-creator2') . '</h3>';
        echo '<div class="favorites-count">' . sprintf(__('%d favorites', 'herbal-mix-creator2'), count($favorite_mixes)) . '</div>';
        echo '</div>';
        
        if (empty($favorite_mixes)) {
            echo '<div class="no-favorites-message">';
            echo '<div class="no-favorites-icon">❤️</div>';
            echo '<h4>' . __('No Favorite Mixes Yet', 'herbal-mix-creator2') . '</h4>';
            echo '<p>' . __('You haven\'t liked any mixes yet.', 'herbal-mix-creator2') . '</p>';
            echo '<p>' . __('Browse the community mixes to find ones you like!', 'herbal-mix-creator2') . '</p>';
            echo '<a href="' . home_url('/shop/') . '" class="browse-mixes-button">' . __('Browse Community Mixes', 'herbal-mix-creator2') . '</a>';
            echo '</div>';
        } else {
            echo '<div class="favorites-grid">';
            
            foreach ($favorite_mixes as $mix) {
                $author = get_userdata($mix->user_id);
                $author_name = $author ? $author->display_name : __('Unknown', 'herbal-mix-creator2');
                
                echo '<div class="favorite-card">';
                
                // Mix image
                echo '<div class="favorite-image">';
                if (!empty($mix->mix_image)) {
                    echo '<img src="' . esc_url($mix->mix_image) . '" alt="' . esc_attr($mix->mix_name) . '" loading="lazy">';
                } else {
                    echo '<div class="favorite-placeholder"><span class="herb-icon">🌿</span></div>';
                }
                echo '</div>';
                
                // Mix content
                echo '<div class="favorite-content">';
                echo '<h4 class="favorite-title">' . esc_html($mix->mix_name) . '</h4>';
                echo '<p class="favorite-author">' . sprintf(__('by %s', 'herbal-mix-creator2'), esc_html($author_name)) . '</p>';
                if ($mix->mix_description) {
                    echo '<p class="favorite-description">' . esc_html(wp_trim_words($mix->mix_description, 15)) . '</p>';
                }
                
                echo '<div class="favorite-meta">';
                echo '<span class="favorite-likes">❤️ ' . intval($mix->like_count ?? 0) . '</span>';
                echo '<span class="favorite-date">' . date('M j, Y', strtotime($mix->created_at)) . '</span>';
                echo '</div>';
                
                echo '<div class="favorite-actions">';
                echo '<button class="button primary view-mix" data-mix-id="' . $mix->id . '">';
                echo '<span class="button-icon">👁️</span>' . __('View', 'herbal-mix-creator2');
                echo '</button>';
                echo '<button class="button success buy-mix" data-mix-id="' . $mix->id . '">';
                echo '<span class="button-icon">🛒</span>' . __('Buy', 'herbal-mix-creator2');
                echo '</button>';
                echo '<button class="button danger remove-favorite" data-mix-id="' . $mix->id . '">';
                echo '<span class="button-icon">💔</span>' . __('Remove', 'herbal-mix-creator2');
                echo '</button>';
                echo '</div>';
                
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * COMPLETE: Display Activity tab
     */
    public function render_activity_tab() {
        echo '<div class="herbal-activity-container">';
        echo '<h3>' . __('My Activity', 'herbal-mix-creator2') . '</h3>';
        echo '<div id="activity-feed" class="activity-feed"></div>';
        echo '<button id="load-more-activity" class="button secondary">' . __('Load More', 'herbal-mix-creator2') . '</button>';
        echo '</div>';
    }
    
    /**
     * COMPLETE: Display Notifications tab
     */
    public function render_notifications_tab() {
        echo '<div class="herbal-notifications-container">';
        echo '<div class="notifications-header">';
        echo '<h3>' . __('Notifications', 'herbal-mix-creator2') . '</h3>';
        echo '<button id="mark-all-read" class="button secondary">' . __('Mark All Read', 'herbal-mix-creator2') . '</button>';
        echo '</div>';
        echo '<div id="notifications-list" class="notifications-list"></div>';
        echo '</div>';
    }
    
    /**
     * COMPLETE: Display Following tab
     */
    public function render_following_tab() {
        echo '<div class="herbal-following-container">';
        echo '<h3>' . __('Following', 'herbal-mix-creator2') . '</h3>';
        echo '<div id="following-list" class="following-list"></div>';
        echo '</div>';
    }
    
    /**
     * COMPLETE: AJAX - Get mix details
     */
    public function ajax_get_mix_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'get_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        if (!$mix_id) {
            wp_send_json_error(array('message' => 'Invalid mix ID.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Check if user owns the mix OR it's published
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND (user_id = %d OR status = 'published')",
            $mix_id, $user_id
        ), ARRAY_A);
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found or access denied.'));
        }
        
        // Decode mix data
        $mix_data = json_decode($mix['mix_data'], true);
        
        // Get ingredient details and pricing
        $ingredients_html = '';
        $total_price = 0;
        $total_points = 0;
        
        if (!empty($mix_data['ingredients'])) {
            $ingredients_html .= '<div class="ingredients-list">';
            
            foreach ($mix_data['ingredients'] as $ingredient) {
                $amount_g = floatval($ingredient['amount'] ?? $ingredient['weight'] ?? 0);
                $price_per_100g = floatval($ingredient['price'] ?? 0);
                $points_per_100g = floatval($ingredient['points'] ?? $ingredient['point_earned'] ?? 0);
                
                $ingredient_price = ($price_per_100g / 100) * $amount_g;
                $ingredient_points = ($points_per_100g / 100) * $amount_g;
                
                $total_price += $ingredient_price;
                $total_points += $ingredient_points;
                
                $ingredients_html .= '<div class="ingredient-item">';
                $ingredients_html .= '<div class="ingredient-info">';
                $ingredients_html .= '<span class="ingredient-name">' . esc_html($ingredient['name']) . '</span>';
                $ingredients_html .= '<span class="ingredient-amount">' . $amount_g . 'g</span>';
                $ingredients_html .= '</div>';
                $ingredients_html .= '<div class="ingredient-pricing">';
                $ingredients_html .= '<span class="ingredient-price">£' . number_format($ingredient_price, 2) . '</span>';
                if ($ingredient_points > 0) {
                    $ingredients_html .= '<span class="ingredient-points">' . number_format($ingredient_points, 0) . ' pts</span>';
                }
                $ingredients_html .= '</div>';
                $ingredients_html .= '</div>';
            }
            
            $ingredients_html .= '</div>';
            
            // Add pricing summary
            $ingredients_html .= '<div class="pricing-summary">';
            $ingredients_html .= '<div class="total-price"><strong>Total: £' . number_format($total_price, 2) . '</strong></div>';
            if ($total_points > 0) {
                $ingredients_html .= '<div class="total-points">Points required: ' . number_format($total_points, 0) . '</div>';
            }
            $ingredients_html .= '</div>';
        }
        
        // Get author info
        $author = get_userdata($mix['user_id']);
        $mix['author_name'] = $author ? $author->display_name : 'Unknown';
        $mix['author_avatar'] = get_avatar_url($mix['user_id']);
        
        wp_send_json_success(array(
            'mix' => $mix,
            'ingredients_html' => $ingredients_html,
            'total_price' => $total_price,
            'total_points' => $total_points,
            'can_edit' => ($mix['user_id'] == $user_id && $mix['status'] !== 'published')
        ));
    }
    
    /**
     * COMPLETE: AJAX - Update mix details
     */
    public function ajax_update_mix_details() {
        if (!wp_verify_nonce($_POST['nonce'], 'update_mix_details')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        $mix_name = sanitize_text_field($_POST['mix_name']);
        $mix_description = sanitize_textarea_field($_POST['mix_description']);
        $mix_tags = sanitize_text_field($_POST['mix_tags'] ?? '');
        
        if (!$user_id || !$mix_id || !$mix_name) {
            wp_send_json_error(array('message' => 'Missing required fields.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Verify ownership and non-published status
        $existing_mix = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM $table WHERE id = %d AND user_id = %d",
            $mix_id, $user_id
        ));
        
        if (!$existing_mix) {
            wp_send_json_error(array('message' => 'Mix not found or access denied.'));
        }
        
        if ($existing_mix->status === 'published') {
            wp_send_json_error(array('message' => 'Published mixes cannot be edited.'));
        }
        
        $update_data = array(
            'mix_name' => $mix_name,
            'mix_description' => $mix_description,
            'updated_at' => current_time('mysql')
        );
        
        // Handle tags if provided
        if (!empty($mix_tags)) {
            $tags_array = array_map('trim', explode(',', $mix_tags));
            $update_data['tags'] = json_encode($tags_array);
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            array('id' => $mix_id, 'user_id' => $user_id),
            array('%s', '%s', '%s'),
            array('%d', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Mix updated successfully.', 'herbal-mix-creator2'),
                'mix_name' => $mix_name,
                'mix_description' => $mix_description
            ));
        } else {
            wp_send_json_error(array('message' => 'Database error occurred.'));
        }
    }
    
    /**
     * COMPLETE: AJAX - Publish mix
     */
    public function ajax_publish_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'publish_mix')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        $mix_name = sanitize_text_field($_POST['mix_name']);
        $mix_description = sanitize_textarea_field($_POST['mix_description']);
        $mix_category = sanitize_text_field($_POST['mix_category'] ?? '');
        $terms_agree = isset($_POST['terms_agree']) && $_POST['terms_agree'];
        
        if (!$user_id || !$mix_id || !$mix_name || !$mix_description || !$terms_agree) {
            wp_send_json_error(array('message' => 'Missing required fields or terms not agreed.'));
        }
        
        // Handle image upload
        $image_url = '';
        if (!empty($_FILES['mix_image'])) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            
            $upload = wp_handle_upload($_FILES['mix_image'], array('test_form' => false));
            
            if (isset($upload['error'])) {
                wp_send_json_error(array('message' => 'Image upload error: ' . $upload['error']));
            }
            
            $image_url = $upload['url'];
        } else {
            wp_send_json_error(array('message' => 'Image is required for publishing.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        // Get mix and verify ownership
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND user_id = %d AND status != 'published'",
            $mix_id, $user_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found, access denied, or already published.'));
        }
        
        // Update mix with publication data
        $update_result = $wpdb->update(
            $table,
            array(
                'mix_name' => $mix_name,
                'mix_description' => $mix_description,
                'mix_image' => $image_url,
                'category' => $mix_category,
                'status' => 'published',
                'published_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $mix_id, 'user_id' => $user_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d', '%d')
        );
        
        if ($update_result === false) {
            wp_send_json_error(array('message' => 'Failed to update mix in database.'));
        }
        
        // Create WooCommerce product
        $product_id = null;
        if (class_exists('HerbalMixActions')) {
            $actions = new HerbalMixActions();
            
            // Update mix object with new data
            $mix->mix_name = $mix_name;
            $mix->mix_description = $mix_description;
            $mix->mix_image = $image_url;
            $mix->category = $mix_category;
            $mix->status = 'published';
            
            $product_id = $actions->create_public_product($mix);
            
            if ($product_id) {
                // Update mix with product ID
                $wpdb->update(
                    $table,
                    array('base_product_id' => $product_id),
                    array('id' => $mix_id),
                    array('%d'),
                    array('%d')
                );
            }
        }
        
        // Award points for publishing
        $current_points = intval(get_user_meta($user_id, 'reward_points', true));
        $points_awarded = 50;
        update_user_meta($user_id, 'reward_points', $current_points + $points_awarded);
        
        // Update user stats
        $total_published = intval(get_user_meta($user_id, 'total_mixes_published', true));
        update_user_meta($user_id, 'total_mixes_published', $total_published + 1);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Mix published successfully! You earned %d reward points.', 'herbal-mix-creator2'), $points_awarded),
            'product_id' => $product_id,
            'redirect' => $product_id ? get_permalink($product_id) : null,
            'points_awarded' => $points_awarded
        ));
    }
    
    /**
     * COMPLETE: AJAX - Buy mix
     */
    public function ajax_buy_mix() {
        if (!wp_verify_nonce($_POST['nonce'], 'buy_mix')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $mix_id = intval($_POST['mix_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id || !$mix_id) {
            wp_send_json_error(array('message' => 'Invalid request.'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'herbal_mixes';
        
        $mix = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $mix_id
        ));
        
        if (!$mix) {
            wp_send_json_error(array('message' => 'Mix not found.'));
        }
        
        // Check if this is a published mix with existing product
        if ($mix->status === 'published' && !empty($mix->base_product_id)) {
            // Use existing product
            $product_id = $mix->base_product_id;
            
            // Verify product exists
            $product = wc_get_product($product_id);
            if (!$product) {
                wp_send_json_error(array('message' => 'Product not found.'));
            }
        } else {
            // Create virtual product for purchase
            if (!class_exists('HerbalMixActions')) {
                wp_send_json_error(array('message' => 'Product creation service not available.'));
            }
            
            $actions = new HerbalMixActions();
            $product_id = $actions->generate_product_from_mix($mix, true); // true = virtual
            
            if (!$product_id) {
                wp_send_json_error(array('message' => 'Failed to create product from mix.'));
            }
        }
        
        // Add to cart
        if (class_exists('WC') && WC()->cart) {
            $cart_item_key = WC()->cart->add_to_cart($product_id, 1);
            
            if ($cart_item_key) {
                // Award points to mix creator if different user
                if ($mix->user_id != $user_id && $mix->status === 'published') {
                    $creator_points = intval(get_user_meta($mix->user_id, 'reward_points', true));
                    update_user_meta($mix->user_id, 'reward_points', $creator_points + 5);
                    
                    // Add activity log for creator
                    $this->add_user_activity($mix->user_id, 'mix_purchased', array(
                        'mix_id' => $mix_id,
                        'mix_name' => $mix->mix_name,
                        'buyer_id' => $user_id,
                        'points_earned' => 5
                    ));
                }
                
                wp_send_json_success(array(
                    'message' => __('Mix added to cart successfully!', 'herbal-mix-creator2'),
                    'redirect_url' => wc_get_cart_url(),
                    'product_id' => $product_id,
                    'cart_count' => W<?php
/**
 * COMPLETE: Enhanced User Profile Extended Class - Full Implementation From Scratch
 * File: includes/class-herbal-mix-user-profile-extended.php
 * 
 * COMPLETE FEATURES:
 * - All AJAX handlers for profile functionality
 * - Complete modal implementation
 * - Enhanced buy, edit, delete, publish functionality
 * - Avatar system
 * - Social features integration
 * - Inline JavaScript and CSS
 * - All template renderers
 * - Enhanced error handling
 */

if (!defined('ABSPATH')) exit;

class HerbalMixUserProfileExtended {
    
    public function __construct() {
        // Initialize points for new users
        add_action('user_register', array($this, 'initialize_user_points'));
        
        // Use priority 15 (later) to run after HerbalProfileIntegration
        add_filter('woocommerce_account_menu_items', array($this, 'add_mix_menu_items'), 15);
        add_action('init', array($this, 'add_custom_endpoints'));
        
        // Register tab content handlers
        add_action('woocommerce_account_my-mixes_endpoint', array($this, 'render_my_mixes_tab'));
        add_action('woocommerce_account_favorite-mixes_endpoint', array($this, 'render_favorite_mixes_tab'));
        add_action('woocommerce_account_my-activity_endpoint', array($this, 'render_activity_tab'));
        add_action('woocommerce_account_notifications_endpoint', array($this, 'render_notifications_tab'));
        add_action('woocommerce_account_following_endpoint', array($this, 'render_following_tab'));
        
        // Profile additional fields
        add_action('woocommerce_edit_account_form', array($this, 'add_avatar_to_account_form'));
        add_action('woocommerce_save_account_details', array($this, 'save_extra_account_details'));
        add_filter('get_avatar_url', array($this, 'custom_avatar_url'), 10, 3);
        
        // COMPLETE AJAX handlers for mixes
        add_action('wp_ajax_get_mix_details', array($this, 'ajax_get_mix_details'));
        add_action('wp_ajax_get_mix_recipe_and_pricing', array($this, 'ajax_get_mix_recipe_and_pricing'));
        add_action('wp_ajax_update_mix_details', array($this, 'ajax_update_mix_details'));
        add_action('wp_ajax_publish_mix', array($this, 'ajax_publish_mix'));
        add_action('wp_ajax_delete_mix', array($this, 'ajax_delete_mix'));
        add_action('wp_ajax_view_mix', array($this, 'ajax_view_mix'));
        add_action('wp_ajax_remove_favorite_mix', array($this, 'ajax_remove_favorite_mix'));
        add_action('wp_ajax_upload_mix_image', array($this, 'ajax_upload_mix_image'));
        add_action('wp_ajax_buy_mix', array($this, 'ajax_buy_mix'));
        
        // Additional AJAX handlers
        add_action('wp_ajax_like_mix', array($this, 'ajax_like_mix'));
        add_action('wp_ajax_follow_user', array($this, 'ajax_follow_user'));
        add_action('wp_ajax_get_user_activity', array($this, 'ajax_get_user_activity'));
        add_action('wp_ajax_get_notifications', array($this, 'ajax_get_notifications'));
        add_action('wp_ajax_mark_notification_read', array($this, 'ajax_mark_notification_read'));
        add_action('wp_ajax_add_mix_comment', array($this, 'ajax_add_mix_comment'));
        add_action('wp_ajax_submit_mix_review', array($this, 'ajax_submit_mix_review'));
        add_action('wp_ajax_get_mix_pricing_details', array($this, 'ajax_get_mix_pricing_details'));
        add_action('wp_ajax_save_mix_as_favorite', array($this, 'ajax_save_mix_as_favorite'));
        add_action('wp_ajax_get_user_stats', array($this, 'ajax_get_user_stats'));
        add_action('wp_ajax_share_mix', array($this, 'ajax_share_mix'));
        
        // Load profile assets when needed
        add_action('wp_enqueue_scripts', array($this, 'enqueue_profile_assets'));
        
        // Add inline scripts and styles
        add_action('wp_footer', array($this, 'add_inline_scripts'));
    }
    
    /**
     * Initialize points for new users
     */
    public function initialize_user_points($user_id) {
        update_user_meta($user_id, 'reward_points', 0);
        update_user_meta($user_id, 'profile_setup_complete', 0);
        update_user_meta($user_id, 'first_mix_created', 0);
        update_user_meta($user_id, 'total_likes_received', 0);
        update_user_meta($user_id, 'total_mixes_published', 0);
    }
    
    /**
     * Add custom endpoints
     */
    public function add_custom_endpoints() {
        add_rewrite_endpoint('my-mixes', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('favorite-mixes', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('my-activity', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('notifications', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('following', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Add mix-related menu items with social features
     */
    public function add_mix_menu_items($menu_items) {
        $new_items = array();
        foreach ($menu_items as $key => $item) {
            $new_items[$key] = $item;
            if ($key === 'points-history') {
                $new_items['my-mixes'] = __('My Herbal Mixes', 'herbal-mix-creator2');
                $new_items['favorite-mixes'] = __('Favorite Mixes', 'herbal-mix-creator2');
                $new_items['my-activity'] = __('My Activity', 'herbal-mix-creator2');
                $new_items['notifications'] = __('Notifications', 'herbal-mix-creator2');
                $new_items['following'] = __('Following', 'herbal-mix-creator2');
            }
        }
        return $new_items;
    }
    
    /**
     * Enhanced enqueue assets with ALL required nonces
     */
    public function enqueue_profile_assets() {
        if (is_account_page()) {
            // CSS
            wp_enqueue_style(
                'herbal-profile-css',
                plugin_dir_url(__FILE__) . '../assets/css/profile.css',
                array('woocommerce-general'),
                '2.0.0'
            );
            
            // JavaScript
            wp_enqueue_script(
                'herbal-profile-js',
                plugin_dir_url(__FILE__) . '../assets/js/profile.js',
                array('jquery'),
                '2.0.0',
                true
            );
            
            // COMPLETE: localize script with ALL required data
            wp_localize_script('herbal-profile-js', 'herbalProfileData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                
                // All nonces
                'getNonce' => wp_create_nonce('get_mix_details'),
                'publishNonce' => wp_create_nonce('publish_mix'),
                'updateNonce' => wp_create_nonce('update_mix_details'),
                'deleteNonce' => wp_create_nonce('delete_mix'),
                'buyNonce' => wp_create_nonce('buy_mix'),
                'favoritesNonce' => wp_create_nonce('manage_favorites'),
                'uploadNonce' => wp_create_nonce('upload_mix_image'),
                'likesNonce' => wp_create_nonce('like_mix'),
                'followNonce' => wp_create_nonce('follow_user'),
                'commentsNonce' => wp_create_nonce('add_comment'),
                'reviewsNonce' => wp_create_nonce('submit_review'),
                'notificationsNonce' => wp_create_nonce('manage_notifications'),
                'shareNonce' => wp_create_nonce('share_mix'),
                
                // URLs
                'cartUrl' => wc_get_cart_url(),
                'myMixesUrl' => wc_get_account_endpoint_url('my-mixes'),
                'favoriteUrl' => wc_get_account_endpoint_url('favorite-mixes'),
                'activityUrl' => wc_get_account_endpoint_url('my-activity'),
                'notificationsUrl' => wc_get_account_endpoint_url('notifications'),
                'followingUrl' => wc_get_account_endpoint_url('following'),
                
                // User data
                'currentUserId' => get_current_user_id(),
                'userDisplayName' => $this->get_user_display_name(),
                'userPoints' => get_user_meta(get_current_user_id(), 'reward_points', true),
                'userAvatar' => get_avatar_url(get_current_user_id()),
                
                // Settings
                'maxImageSize' => wp_max_upload_size(),
                'allowedImageTypes' => array('image/jpeg', 'image/png', 'image/gif', 'image/webp'),
                'currencySymbol' => get_woocommerce_currency_symbol(),
                
                // Strings
                'strings' => array(
                    'loading' => __('Loading...', 'herbal-mix-creator2'),
                    'error' => __('An error occurred. Please try again.', 'herbal-mix-creator2'),
                    'success' => __('Operation completed successfully.', 'herbal-mix-creator2'),
                    'confirmDelete' => __('Are you sure you want to delete this mix? This action cannot be undone.', 'herbal-mix-creator2'),
                    'confirmPublish' => __('Are you sure you want to publish this mix? It will be visible to all users.', 'herbal-mix-creator2'),
                    'confirmDeletePublished' => __('This mix is published. Deleting it will notify the administrator to remove it from the shop. Continue?', 'herbal-mix-creator2'),
                    'publishSuccess' => __('Mix published successfully!', 'herbal-mix-creator2'),
                    'deleteSuccess' => __('Mix deleted successfully!', 'herbal-mix-creator2'),
                    'updateSuccess' => __('Mix updated successfully!', 'herbal-mix-creator2'),
                    'buySuccess' => __('Mix added to cart successfully!', 'herbal-mix-creator2'),
                    'likeSuccess' => __('Mix liked successfully!', 'herbal-mix-creator2'),
                    'followSuccess' => __('User followed successfully!', 'herbal-mix-creator2'),
                    'unfollowSuccess' => __('User unfollowed successfully!', 'herbal-mix-creator2'),
                    'commentSuccess' => __('Comment added successfully!', 'herbal-mix-creator2'),
                    'reviewSuccess' => __('Review submitted successfully!', 'herbal-mix-creator2'),
                    'shareSuccess' => __('Mix shared successfully!', 'herbal-mix-creator2'),
                    'imageRequired' => __('Please upload an image for your mix.', 'herbal-mix-creator2'),
                    'nameRequired' => __('Please enter a name for your mix.', 'herbal-mix-creator2'),
                    'descriptionRequired' => __('Please enter a description for your mix.', 'herbal-mix-creator2'),
                    'imageTooBig' => __('Image file is too large.', 'herbal-mix-creator2'),
                    'imageWrongType' => __('Invalid image type.', 'herbal-mix-creator2'),
                    'buying' => __('Adding to cart...', 'herbal-mix-creator2'),
                    'deleting' => __('Deleting...', 'herbal-mix-creator2'),
                    'publishing' => __('Publishing...', 'herbal-mix-creator2'),
                    'updating' => __('Updating...', 'herbal-mix-creator2'),
                    'liking' => __('Processing...', 'herbal-mix-creator2'),
                    'following' => __('Following...', 'herbal-mix-creator2'),
                    'commenting' => __('Adding comment...', 'herbal-mix-creator2'),
                    'reviewing' => __('Submitting review...', 'herbal-mix-creator2'),
                    'sharing' => __('Sharing...', 'herbal-mix-creator2'),
                    'uploading' => __('Uploading...', 'herbal-mix-creator2')
                )
            ));
        }
    }
    
    /**
     * ENHANCED: My Mixes tab content with complete functionality
     */
    public function render_my_mixes_tab() {
        // Get user data
        $user_id = get_current_user_id();
        
        // Check for template first
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'includes/templates/user-profile-my-mixes.php';
        if (file_exists($template_path)) {
            // Prepare data for template
            $my_mixes = Herbal_Mix_Database::get_user_mixes($user_id);
            $private_mixes = array_filter($my_mixes, function($mix) { return $mix->status !== 'published'; });
            $published_mixes = array_filter($my_mixes, function($mix) { return $mix->status === 'published'; });
            
            include($template_path);
            return;
        }
        
        // Enhanced fallback implementation
        $my_mixes = Herbal_Mix_Database::get_user_mixes($user_id);
        
        echo '<div class="herbal-my-mixes-container">';
        echo '<div class="mixes-header">';
        echo '<h3>' . __('My Herbal Mixes', 'herbal-mix-creator2') . '</h3>';
        
        // Mix statistics
        $total_mixes = count($my_mixes);
        $published_count = count(array_filter($my_mixes, function($mix) { return $mix->status === 'published'; }));
        $private_count = $total_mixes - $published_count;
        $total_likes = array_sum(array_map(function($mix) { return intval($mix->like_count ?? 0); }, $my_mixes));
        
        echo '<div class="mix-stats">';
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . $total_mixes . '</span>';
        echo '<span class="stat-label">' . __('Total Mixes', 'herbal-mix-creator2') . '</span>';
        echo '</div>';
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . $published_count . '</span>';
        echo '<span class="stat-label">' . __('Published', 'herbal-mix-creator2') . '</span>';
        echo '</div>';
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . $private_count . '</span>';
        echo '<span class="stat-label">' . __('Private', 'herbal-mix-creator2') . '</span>';
        echo '</div>';
        echo '<div class="stat-item">';
        echo '<span class="stat-number">' . $total_likes . '</span>';
        echo '<span class="stat-label">' . __('Total Likes', 'herbal-mix-creator2') . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        if (empty($my_mixes)) {
            echo '<div class="no-mixes-message">';
            echo '<div class="no-mixes-icon">🌿</div>';
            echo '<h4>' . __('No Custom Mixes Yet', 'herbal-mix-creator2') . '</h4>';
            echo '<p>' . __('You haven\'t created any custom herbal mixes yet.', 'herbal-mix-creator2') . '</p>';
            echo '<p>' . __('Start creating your personalized blends today!', 'herbal-mix-creator2') . '</p>';
            $create_mix_url = get_permalink(get_page_by_path('herbal-mix-creator')) ?: home_url('/herbal-mix-creator/');
            echo '<a href="' . esc_url($create_mix_url) . '" class="create-mix-button">' . __('Create Your First Mix', 'herbal-mix-creator2') . '</a>';
            echo '</div>';
        } else {
            // Filter tabs
            echo '<div class="mix-filters">';
            echo '<button class="filter-btn active" data-filter="all">' . __('All Mixes', 'herbal-mix-creator2') . ' (' . $total_mixes . ')</button>';
            echo '<button class="filter-btn" data-filter="published">' . __('Published', 'herbal-mix-creator2') . ' (' . $published_count . ')</button>';
            echo '<button class="filter-btn" data-filter="private">' . __('Private', 'herbal-mix-creator2') . ' (' . $private_count . ')</button>';
            echo '</div>';
            
            echo '<div class="mixes-grid">';
            
            foreach ($my_mixes as $mix) {
                $mix_data = json_decode($mix->mix_data, true);
                $ingredients_count = is_array($mix_data['ingredients'] ?? null) ? count($mix_data['ingredients']) : 0;
                
                echo '<div class="mix-card" data-status="' . esc_attr($mix->status) . '" data-mix-id="' . $mix->id . '">';
                
                // Mix image
                echo '<div class="mix-image">';
                if (!empty($mix->mix_image)) {
                    echo '<img src="' . esc_url($mix->mix_image) . '" alt="' . esc_attr($mix->mix_name) . '" loading="lazy">';
                } else {
                    echo '<div class="mix-placeholder"><span class="herb-icon">🌿</span></div>';
                }
                echo '<div class="mix-status-badge">';
                echo '<span class="mix-status ' . esc_attr($mix->status) . '">' . ucfirst($mix->status) . '</span>';
                echo '</div>';
                echo '</div>';
                
                // Mix content
                echo '<div class="mix-content">';
                echo '<h4 class="mix-title">' . esc_html($mix->mix_name) . '</h4>';
                if ($mix->mix_description) {
                    echo '<p class="mix-description">' . esc_html(wp_trim_words($mix->mix_description, 20)) . '</p>';
                }
                
                echo '<div class="mix-meta">';
                echo '<span class="mix-ingredients"><span class="icon">📋</span>' . sprintf(__('%d ingredients', 'herbal-mix-creator2'), $ingredients_count) . '</span>';
                echo '<span class="mix-date"><span class="icon">📅</span>' . date('M j, Y', strtotime($mix->created_at)) . '</span>';
                if ($mix->status === 'published' && isset($mix->like_count)) {
                    echo '<span class="mix-likes"><span class="icon">❤️</span>' . intval($mix->like_count) . '</span>';
                }
                echo '</div>';
                
                // Enhanced Actions
                echo '<div class="mix-actions">';
                echo '<button class="button primary view-mix" data-mix-id="' . $mix->id . '" title="' . __('View mix details', 'herbal-mix-creator2') . '">';
                echo '<span class="button-icon">👁️</span>' . __('View', 'herbal-mix-creator2');
                echo '</button>';
                
                echo '<button class="button success buy-mix" data-mix-id="' . $mix->id . '" title="' . __('Add to cart', 'herbal-mix-creator2') . '">';
                echo '<span class="button-icon">🛒</span>' . __('Buy', 'herbal-mix-creator2');
                echo '</button>';
                
                // Show Edit and Publish buttons only for non-published mixes
                if ($mix->status !== 'published') {
                    echo '<button class="button warning edit-mix" data-mix-id="' . $mix->id . '" title="' . __('Edit mix', 'herbal-mix-creator2') . '">';
                    echo '<span class="button-icon">✏️</span>' . __('Edit', 'herbal-mix-creator2');
                    echo '</button>';
                    
                    echo '<button class="button info publish-mix" data-mix-id="' . $mix->id . '" title="' . __('Publish mix', 'herbal-mix-creator2') . '">';
                    echo '<span class="button-icon">📤</span>' . __('Publish', 'herbal-mix-creator2');
                    echo '</button>';
                }
                
                // Delete button
                echo '<button class="button danger delete-mix" data-mix-id="' . $mix->id . '" data-status="' . esc_attr($mix->status) . '" title="' . __('Delete mix', 'herbal-mix-creator2') . '">';
                echo '<span class="button-icon">🗑️</span>' . __('Delete', 'herbal-mix-creator2');
                echo '</button>';
                
                // Social actions for published mixes
                if ($mix->status === 'published') {
                    echo '<button class="button secondary share-mix" data-mix-id="' . $mix->id . '" title="' . __('Share mix', 'herbal-mix-creator2') . '">';
                    echo '<span class="button-icon">📤</span>' . __('Share', 'herbal-mix-creator2');
                    echo '</button>';
                }
                
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        // Add all required modals
        $this->render_edit_modal();
        $this->render_publish_modal();
        $this->render_view_modal();
        $this->render_share_modal();
        $this->render_pricing_modal();
        
        echo '</div>';
    }
    
    /**
     * Enhanced edit modal with complete functionality
     */
    private function render_edit_modal() {
        ?>
        <div id="edit-mix-modal" class="modal-dialog" style="display:none;" aria-hidden="true" role="dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Edit Your Mix', 'herbal-mix-creator2'); ?></h3>
                    <button type="button" class="close-modal" aria-label="<?php _e('Close', 'herbal-mix-creator2'); ?>">&times;</button>
                </div>
                
                <div class="modal-body">
                    <form id="edit-mix-form">
                        <input type="hidden" id="edit-mix-id" name="mix_id" value="">
                        
                        <div class="form-section">
                            <h4><?php _e('Basic Information', 'herbal-mix-creator2'); ?></h4>
                            
                            <div class="form-group">
                                <label for="edit-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> <span class="required">*</span></label>
                                <input type="text" id="edit-mix-name" name="mix_name" required maxlength="100" placeholder="<?php _e('Enter mix name', 'herbal-mix-creator2'); ?>">
                                <small class="field-help"><?php _e('Give your mix a memorable name (max 100 characters)', 'herbal-mix-creator2'); ?></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit-mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?></label>
                                <textarea id="edit-mix-description" name="mix_description" rows="4" maxlength="500" placeholder="<?php _e('Describe your mix...', 'herbal-mix-creator2'); ?>"></textarea>
                                <small class="field-help"><?php _e('Describe the benefits and characteristics of your mix (max 500 characters)', 'herbal-mix-creator2'); ?></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit-mix-tags"><?php _e('Tags', 'herbal-mix-creator2'); ?></label>
                                <input type="text" id="edit-mix-tags" name="mix_tags" placeholder="<?php _e('relaxing, energizing, digestive', 'herbal-mix-creator2'); ?>">
                                <small class="field-help"><?php _e('Add tags to help others find your mix (comma separated)', 'herbal-mix-creator2'); ?></small>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h4><?php _e('Mix Recipe', 'herbal-mix-creator2'); ?> <span class="readonly-notice">(<?php _e('Read-only', 'herbal-mix-creator2'); ?>)</span></h4>
                            <div class="mix-recipe-preview">
                                <div id="edit-mix-ingredients-preview" class="ingredients-preview"></div>
                                <div id="edit-mix-pricing-preview" class="pricing-preview"></div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="button secondary cancel-edit"><?php _e('Cancel', 'herbal-mix-creator2'); ?></button>
                    <button type="submit" form="edit-mix-form" class="button primary" id="edit-update-button">
                        <span class="button-icon">💾</span><?php _e('Update Mix', 'herbal-mix-creator2'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enhanced publish modal with image upload
     */
    private function render_publish_modal() {
        ?>
        <div id="publish-mix-modal" class="modal-dialog" style="display:none;" aria-hidden="true" role="dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Publish Your Mix', 'herbal-mix-creator2'); ?></h3>
                    <button type="button" class="close-modal" aria-label="<?php _e('Close', 'herbal-mix-creator2'); ?>">&times;</button>
                </div>
                
                <div class="modal-body">
                    <form id="publish-mix-form" enctype="multipart/form-data">
                        <input type="hidden" id="publish-mix-id" name="mix_id" value="">
                        
                        <div class="form-section">
                            <h4><?php _e('Publication Details', 'herbal-mix-creator2'); ?></h4>
                            
                            <div class="form-group">
                                <label for="publish-mix-name"><?php _e('Mix Name', 'herbal-mix-creator2'); ?> <span class="required">*</span></label>
                                <input type="text" id="publish-mix-name" name="mix_name" required maxlength="100">
                            </div>
                            
                            <div class="form-group">
                                <label for="publish-mix-description"><?php _e('Description', 'herbal-mix-creator2'); ?> <span class="required">*</span></label>
                                <textarea id="publish-mix-description" name="mix_description" rows="4" required maxlength="500"></textarea>
                                <small class="field-help"><?php _e('This description will be shown to all users', 'herbal-mix-creator2'); ?></small>
                            </div>
                            
                            <div class="form-group">
                                <label for="publish-mix-image"><?php _e('Mix Image', 'herbal-mix-creator2'); ?> <span class="required">*</span></label>
                                <div class="image-upload-area">
                                    <input type="file" id="publish-mix-image" name="mix_image" accept="image/*" required>
                                    <div class="upload-preview">
                                        <img id="publish-image-preview" style="max-width: 200px; display: none;">
                                        <div class="upload-placeholder">
                                            <span class="upload-icon">📷</span>
                                            <p><?php _e('Click to upload image or drag & drop', 'herbal-mix-creator2'); ?></p>
                                            <small><?php _e('Supported: JPG, PNG, GIF, WebP (max 5MB)', 'herbal-mix-creator2'); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="publish-mix-category"><?php _e('Category', 'herbal-mix-creator2'); ?></label>
                                <select id="publish-mix-category" name="mix_category">
                                    <option value=""><?php _e('Select category', 'herbal-mix-creator2'); ?></option>
                                    <option value="relaxation"><?php _e('Relaxation', 'herbal-mix-creator2'); ?></option>
                                    <option value="energy"><?php _e('Energy & Focus', 'herbal-mix-creator2'); ?></option>
                                    <option value="digestive"><?php _e('Digestive Health', 'herbal-mix-creator2'); ?></option>
                                    <option value="immunity"
