<?php
// admin-packaging.php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'herbal_packaging';

// Dodanie opakowania
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_packaging'])) {
    $data = array(
        'name'           => sanitize_text_field($_POST['name']),
        'herb_capacity'  => intval($_POST['herb_capacity']),
        'price'          => floatval($_POST['price']),
        'price_point'    => floatval($_POST['price_points']),
        'point_earned'   => floatval($_POST['points_earned']),
        'available'      => isset($_POST['is_available']) ? 1 : 0,
        'image_url'      => ''
    );

    // Upload obrazka
    if (!empty($_FILES['image']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload = wp_handle_upload($_FILES['image'], array('test_form' => false));
        if (!isset($upload['error']) && isset($upload['url'])) {
            $data['image_url'] = esc_url_raw($upload['url']);
        }
    }

    $wpdb->insert($table, $data);
}

$packaging = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC", ARRAY_A);
?>

<div class="wrap">
    <h1>Herbal Packaging</h1>

    <h2>Add New Packaging</h2>
    <form method="post" enctype="multipart/form-data">
        <table class="form-table">
            <tr><th>Name</th><td><input type="text" name="name" required></td></tr>
            <tr><th>Upload Image</th><td><input type="file" name="image"></td></tr>
            <tr><th>Herb Capacity (grams)</th><td><input type="number" name="herb_capacity" step="1"></td></tr>
            <tr><th>Price (£)</th><td><input type="number" name="price" step="0.01"></td></tr>
            <tr><th>Price in Points</th><td><input type="number" name="price_points" step="0.01"></td></tr>
            <tr><th>Points Earned</th><td><input type="number" name="points_earned" step="0.01"></td></tr>
            <tr><th>Available</th><td><input type="checkbox" name="is_available" checked></td></tr>
        </table>
        <p><input type="submit" name="add_packaging" class="button-primary" value="Add Packaging"></p>
    </form>

    <h2>All Packaging Types</h2>
    <?php if (empty($packaging)): ?>
        <p>No packaging types found.</p>
    <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Capacity</th>
                    <th>Price</th>
                    <th>Available</th>
                    <th>Image</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($packaging as $pack): ?>
                    <tr>
                        <td><?php echo esc_html($pack['name']); ?></td>
                        <td><?php echo esc_html($pack['herb_capacity']); ?>g</td>
                        <td>£<?php echo number_format($pack['price'], 2); ?></td>
                        <td><?php echo $pack['available'] ? '✅' : '❌'; ?></td>
                        <td>
                            <?php if (!empty($pack['image_url'])): ?>
                                <img src="<?php echo esc_url($pack['image_url']); ?>" style="max-width:60px;">
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
