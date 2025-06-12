<?php
/**
 * Admin Panel for managing herbal ingredients and packaging in wp-admin.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HerbalMixAdminPanel {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
		add_action( 'admin_init', array( $this, 'handle_category_submission' ) );
	}

	public function register_menu() {
		add_menu_page(
			'Herbal Data',
			'Herbal Data',
			'manage_options',
			'herbal-data',
			'__return_null',
			'dashicons-leaf',
			56
		);

		add_submenu_page(
			'herbal-data',
			'Ingredients',
			'Ingredients',
			'manage_options',
			'herbal-ingredients',
			array( $this, 'render_ingredient_page' )
		);

		add_submenu_page(
			'herbal-data',
			'Packaging',
			'Packaging',
			'manage_options',
			'herbal-packaging',
			array( $this, 'render_packaging_page' )
		);

		add_submenu_page(
			'herbal-data',
			'Categories',
			'Categories',
			'manage_options',
			'herbal-categories',
			function() {
				include plugin_dir_path(__FILE__) . 'partials/admin-categories.php';
			}
		);
	}

	public function handle_form_submission() {
		global $wpdb;

		// INGREDIENT FORM
		if ( isset( $_POST['herbal_ingredient_nonce'] ) && wp_verify_nonce( $_POST['herbal_ingredient_nonce'], 'add_herbal_ingredient' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );

			$image_url = '';
			if ( ! empty( $_FILES['image_file']['name'] ) ) {
				$attachment_id = media_handle_upload( 'image_file', 0 );
				if ( ! is_wp_error( $attachment_id ) ) {
					$image_url = wp_get_attachment_url( $attachment_id );
				}
			}

			$table = $wpdb->prefix . 'herbal_ingredients';
			$data = array(
				'name'              => sanitize_text_field( $_POST['name'] ),
				'image_url'         => esc_url_raw( $image_url ),
				'category_id'       => intval( $_POST['category_id'] ),
				'storytelling'      => sanitize_textarea_field( $_POST['storytelling'] ),
				'description'       => sanitize_textarea_field( $_POST['description'] ),
				'do_not_mix_with'   => sanitize_textarea_field( $_POST['do_not_mix_with'] ),
				'contraindications' => sanitize_textarea_field( $_POST['contraindications'] ),
				'price_per_gram'    => floatval( $_POST['price_per_gram'] ),
				'price_points'      => intval( $_POST['price_points'] ),
				'points_earned'     => intval( $_POST['points_earned'] )
			);
			$wpdb->insert( $table, $data );
			wp_safe_redirect( admin_url( 'admin.php?page=herbal-ingredients&added=1' ) );
			exit;
		}

		// PACKAGING FORM
		if ( isset( $_POST['herbal_packaging_nonce'] ) && wp_verify_nonce( $_POST['herbal_packaging_nonce'], 'add_herbal_packaging' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );

			$image_url = '';
			if ( ! empty( $_FILES['image_file']['name'] ) ) {
				$attachment_id = media_handle_upload( 'image_file', 0 );
				if ( ! is_wp_error( $attachment_id ) ) {
					$image_url = wp_get_attachment_url( $attachment_id );
				}
			}

			$table = $wpdb->prefix . 'herbal_packaging';
			$data = array(
				'name'           => sanitize_text_field( $_POST['name'] ),
				'description'    => sanitize_textarea_field( $_POST['description'] ),
				'image_url'      => esc_url_raw( $image_url ),
				'grams'          => intval( $_POST['grams'] ),
				'herb_capacity'  => intval( $_POST['herb_capacity'] ),
				'price'          => floatval( $_POST['price'] ),
				'price_points'   => intval( $_POST['price_points'] ),
				'points_earned'  => intval( $_POST['points_earned'] ),
				'is_default'     => isset( $_POST['is_default'] ) ? 1 : 0,
				'available'      => isset( $_POST['available'] ) ? 1 : 0,
				'position'       => intval( $_POST['position'] )
			);
			$wpdb->insert( $table, $data );
			wp_safe_redirect( admin_url( 'admin.php?page=herbal-packaging&added=1' ) );
			exit;
		}
	}

	public function handle_category_submission() {
		if (
			isset($_POST['herbal_category_nonce']) &&
			wp_verify_nonce($_POST['herbal_category_nonce'], 'add_herbal_category')
		) {
			global $wpdb;
			$table = $wpdb->prefix . 'herbal_categories';
			$data = [
				'name' => sanitize_text_field($_POST['name'] ?? ''),
				'description' => sanitize_text_field($_POST['description'] ?? '')
			];
			$wpdb->insert($table, $data);
			wp_redirect(admin_url('admin.php?page=herbal-categories&added=1'));
			exit;
		}
	}

	public function render_ingredient_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'herbal_ingredients';
		$ingredients = $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC" );
		include plugin_dir_path(__FILE__) . 'partials/admin-ingredients.php';
	}

	public function render_packaging_page() {
		global $wpdb;
		$table = $wpdb->prefix . 'herbal_packaging';
		$packages = $wpdb->get_results( "SELECT * FROM $table ORDER BY position ASC, name ASC" );

		// DEBUG (tymczasowo)
		if ( current_user_can('manage_options') ) {
			echo '<pre style="background:#fff; padding:10px; border:1px solid #ccc;"><strong>DEBUG:</strong> ';
			print_r($packages);
			echo '</pre>';
		}

		include plugin_dir_path(__FILE__) . 'partials/admin-packaging.php';
	}
}

new HerbalMixAdminPanel();
