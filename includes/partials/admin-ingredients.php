<?php
// admin-ingredients.php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$table = $wpdb->prefix . 'herbal_ingredients';
$category_table = $wpdb->prefix . 'herbal_categories';

// Edytowany składnik (jeśli dotyczy)
$editing_ingredient = null;
if (isset($_GET['edit'])) {
    $editing_ingredient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($_GET['edit'])), ARRAY_A);
}

// Dodawanie lub aktualizacja składnika
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ingredient'])) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $data = array(
        'category_id'   => intval($_POST['category_id']),
        'name'          => sanitize_text_field($_POST['name']),
        'price'         => floatval($_POST['price']),
        'price_point'   => floatval($_POST['price_point']),
        'point_earned'  => floatval($_POST['point_earned']),
        'image_url'     => '',
        'description'   => sanitize_textarea_field($_POST['description']),
        'story'         => sanitize_textarea_field($_POST['story']),
        'visible'       => isset($_POST['visible']) ? 1 : 0,
        'sort_order'    => intval($_POST['sort_order']),
        'meta_data'     => sanitize_textarea_field($_POST['meta_data']),
    );

    if (!empty($_FILES['image_upload']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $upload = wp_handle_upload($_FILES['image_upload'], array('test_form' => false));
        if (!isset($upload['error']) && isset($upload['url'])) {
            $data['image_url'] = esc_url_raw($upload['url']);
        } else {
            echo '<div class="error"><p>Błąd przesyłania obrazka: ' . esc_html($upload['error']) . '</p></div>';
        }
    } elseif (!empty($_POST['existing_image_url'])) {
        $data['image_url'] = esc_url_raw($_POST['existing_image_url']);
    }

    if ($id > 0) {
        $wpdb->update($table, $data, array('id' => $id));
        echo '<div class="updated"><p>Składnik zaktualizowany.</p></div>';
    } else {
        $wpdb->insert($table, $data);
        echo '<div class="updated"><p>Składnik dodany.</p></div>';
    }
}

if (isset($_GET['delete'])) {
    $wpdb->delete($table, array('id' => intval($_GET['delete'])));
    echo '<div class="updated"><p>Składnik usunięty.</p></div>';
}

$raw_categories = $wpdb->get_results("SELECT * FROM $category_table ORDER BY sort_order ASC", ARRAY_A);
$categories = [];
foreach ($raw_categories as $cat) {
    $categories[$cat['id']] = $cat;
}

$ingredients = $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC", ARRAY_A);
?>

<div class="wrap">
    <h1>Składniki</h1>

    <h2>Dodaj / Edytuj składnik</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo esc_attr($editing_ingredient['id'] ?? 0); ?>">
        <input type="hidden" name="existing_image_url" value="<?php echo esc_url($editing_ingredient['image_url'] ?? ''); ?>">

        <table class="form-table">
            <tr>
                <th>Kategoria</th>
                <td>
                    <select name="category_id">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php selected((int)($editing_ingredient['category_id'] ?? 0), (int)$cat['id']); ?>><?php echo esc_html($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Nazwa</th>
                <td><input type="text" name="name" value="<?php echo esc_attr($editing_ingredient['name'] ?? ''); ?>" required></td>
            </tr>
            <tr>
                <th>Cena (£/g)</th>
                <td><input type="number" step="0.01" name="price" value="<?php echo esc_attr($editing_ingredient['price'] ?? ''); ?>"></td>
            </tr>
            <tr>
                <th>Cena (punktów/g)</th>
                <td><input type="number" step="0.01" name="price_point" value="<?php echo esc_attr($editing_ingredient['price_point'] ?? ''); ?>"></td>
            </tr>
            <tr>
                <th>Punkty za zakup (1g)</th>
                <td><input type="number" step="0.01" name="point_earned" value="<?php echo esc_attr($editing_ingredient['point_earned'] ?? ''); ?>"></td>
            </tr>
            <tr>
                <th>Obrazek</th>
                <td>
                    <input type="file" name="image_upload" accept="image/*">
                    <?php if (!empty($editing_ingredient['image_url'])): ?>
                        <div style="margin-top:10px;">
                            <img src="<?php echo esc_url($editing_ingredient['image_url']); ?>" alt="" style="max-width:150px;">
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Opis</th>
                <td><textarea name="description" rows="3"><?php echo esc_textarea($editing_ingredient['description'] ?? ''); ?></textarea></td>
            </tr>
            <tr>
                <th>Storytelling</th>
                <td><textarea name="story" rows="3"><?php echo esc_textarea($editing_ingredient['story'] ?? ''); ?></textarea></td>
            </tr>
            <tr>
                <th>Kolejność sortowania</th>
                <td><input type="number" name="sort_order" value="<?php echo esc_attr($editing_ingredient['sort_order'] ?? 0); ?>"></td>
            </tr>
            <tr>
                <th>Widoczny?</th>
                <td><label><input type="checkbox" name="visible" <?php checked($editing_ingredient['visible'] ?? 1, 1); ?>> Tak</label></td>
            </tr>
            <tr>
                <th>Dane dodatkowe (JSON)</th>
                <td><textarea name="meta_data" rows="3"><?php echo esc_textarea($editing_ingredient['meta_data'] ?? '{}'); ?></textarea></td>
            </tr>
        </table>
        <p><input type="submit" name="submit_ingredient" class="button-primary" value="Zapisz składnik"></p>
    </form>

    <hr>

    <h2>Lista składników</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Nazwa</th>
                <th>Kategoria</th>
                <th>Cena</th>
                <th>Punkty</th>
                <th>Widoczny</th>
                <th>Akcje</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ingredients as $ing): ?>
                <tr>
                    <td><?php echo esc_html($ing['name']); ?></td>
                    <td><?php echo esc_html($categories[$ing['category_id']]['name'] ?? '-'); ?></td>
                    <td>£<?php echo number_format($ing['price'], 2); ?></td>
                    <td><?php echo $ing['point_earned']; ?></td>
                    <td><?php echo $ing['visible'] ? '✅' : '❌'; ?></td>
                    <td>
                        <a href="?page=herbal_ingredients&edit=<?php echo $ing['id']; ?>">Edytuj</a> |
                        <a href="?page=herbal_ingredients&delete=<?php echo $ing['id']; ?>" onclick="return confirm('Na pewno usunąć?')">Usuń</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>