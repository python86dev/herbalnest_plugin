<?php
/**
 * Admin Categories Page Template
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset($_GET['added']) ) {
	echo '<div class="notice notice-success"><p>Category added successfully.</p></div>';
}
if ( isset($_GET['deleted']) ) {
	echo '<div class="notice notice-success"><p>Category deleted successfully.</p></div>';
}
if ( isset($_GET['updated']) ) {
	echo '<div class="notice notice-success"><p>Category updated successfully.</p></div>';
}

// Handle deletion
if ( isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) ) {
	global $wpdb;
	$wpdb->delete($wpdb->prefix . 'herbal_categories', ['id' => intval($_GET['id'])]);
	wp_redirect(admin_url('admin.php?page=herbal-categories&deleted=1'));
	exit;
}

// Handle update
if ( isset($_POST['edit_category']) && isset($_POST['category_id']) ) {
	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'herbal_categories',
		[
			'name' => sanitize_text_field($_POST['edit_name']),
			'description' => sanitize_text_field($_POST['edit_description'])
		],
		['id' => intval($_POST['category_id'])]
	);
	wp_redirect(admin_url('admin.php?page=herbal-categories&updated=1'));
	exit;
}

?>
<div class="wrap">
	<h1>Herbal Categories</h1>

	<h2>Add New Category</h2>
	<form method="post">
		<?php wp_nonce_field('add_herbal_category', 'herbal_category_nonce'); ?>
		<table class="form-table">
			<tr><th><label for="name">Category Name</label></th>
				<td><input name="name" type="text" class="regular-text" required></td>
			</tr>
			<tr><th><label for="description">Description</label></th>
				<td><textarea name="description" class="large-text" rows="3"></textarea></td>
			</tr>
		</table>
		<p><input type="submit" class="button button-primary" value="Add Category"></p>
	</form>

	<hr>
	<h2>All Categories</h2>
	<?php
	global $wpdb;
	$table = $wpdb->prefix . 'herbal_categories';
	$categories = $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
	if ( $categories ) {
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>Name</th><th>Description</th><th>Actions</th></tr></thead><tbody>';
		foreach ( $categories as $cat ) {
			echo '<tr>';
			echo '<td>' . esc_html($cat->name) . '</td>';
			echo '<td>' . esc_html($cat->description) . '</td>';
			echo '<td>
				<a href="' . esc_url(admin_url('admin.php?page=herbal-categories&action=edit&id=' . $cat->id)) . '" class="button">Edit</a>
				<a href="' . esc_url(admin_url('admin.php?page=herbal-categories&action=delete&id=' . $cat->id)) . '" class="button delete-category" onclick="return confirm(\'Are you sure?\');">Delete</a>
			</td>';
			echo '</tr>';

			if ( isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit' && intval($_GET['id']) === $cat->id ) {
				echo '<tr class="inline-edit-row"><td colspan="3">';
				echo '<form method="post">
					<input type="hidden" name="category_id" value="' . intval($cat->id) . '">
					<table class="form-table">
						<tr><th><label>Edit Name</label></th><td><input type="text" name="edit_name" value="' . esc_attr($cat->name) . '" class="regular-text" required></td></tr>
						<tr><th><label>Edit Description</label></th><td><textarea name="edit_description" class="large-text" rows="3">' . esc_textarea($cat->description) . '</textarea></td></tr>
					</table>
					<p><input type="submit" name="edit_category" class="button button-primary" value="Save Changes"></p>
				</form>';
				echo '</td></tr>';
			}
		}
		echo '</tbody></table>';
	} else {
		echo '<p>No categories found.</p>';
	}
	?>
</div>
