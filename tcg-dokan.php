<?php
/**
 * Plugin Name: TCG Dokan
 * Description: Marketplace de cartas YGO con Dokan — vendors crean productos WooCommerce vinculados al catálogo de cartas.
 * Version:     1.7.5
 * Author:      TCG Dev
 * Requires Plugins: woocommerce, dokan-lite
 * Text Domain: tcg-dokan
 */

defined( 'ABSPATH' ) || exit;

define( 'TCG_DOKAN_VERSION', '1.7.5' );
define( 'TCG_DOKAN_PATH', plugin_dir_path( __FILE__ ) );
define( 'TCG_DOKAN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check plugin dependencies (WooCommerce + Dokan) — runs on plugins_loaded.
 */
function tcg_dokan_check_plugins() {
	$missing = [];

	if ( ! class_exists( 'WooCommerce' ) ) {
		$missing[] = 'WooCommerce';
	}

	if ( ! class_exists( 'WeDevs_Dokan' ) ) {
		$missing[] = 'Dokan';
	}

	if ( $missing ) {
		add_action( 'admin_notices', function() use ( $missing ) {
			$list = implode( ', ', $missing );
			echo '<div class="notice notice-error"><p>';
			echo '<strong>TCG Dokan:</strong> requiere los siguientes plugins activos: ' . esc_html( $list );
			echo '</p></div>';
		} );
		return;
	}

	// Plugins OK — load classes and defer CPT check to init.
	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-setup.php';
	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-ajax.php';
	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-product-form.php';
	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-product-sync.php';
	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-display.php';
	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-listings.php';

	add_action( 'init', 'tcg_dokan_boot', 25 );

	// Ensure DB index exists (runs once, skips if already created).
	add_action( 'admin_init', 'tcg_dokan_maybe_add_title_index' );
}
add_action( 'plugins_loaded', 'tcg_dokan_check_plugins', 20 );

/**
 * Boot the plugin after init — ygo_card CPT is registered by then.
 */
function tcg_dokan_boot() {
	if ( ! post_type_exists( 'ygo_card' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo '<strong>TCG Dokan:</strong> requiere el CPT ygo_card (tcg-theme activo).';
			echo '</p></div>';
		} );
		return;
	}

	new TCG_Dokan_Setup();
	new TCG_Dokan_Ajax();
	new TCG_Dokan_Product_Form();
	new TCG_Dokan_Product_Sync();
	new TCG_Dokan_Display();
	new TCG_Dokan_Listings();
}

/**
 * Activation hook — seed taxonomy terms.
 */
function tcg_dokan_activate() {
	// Ensure taxonomies are registered before seeding.
	if ( ! taxonomy_exists( 'ygo_condition' ) ) {
		// Theme may not have loaded yet; register temporarily.
		register_taxonomy( 'ygo_condition', 'product', [ 'hierarchical' => true ] );
		register_taxonomy( 'ygo_printing', 'product', [ 'hierarchical' => true ] );
		register_taxonomy( 'ygo_language', 'product', [ 'hierarchical' => true ] );
	}

	$terms = [
		'ygo_condition' => [ 'Near Mint', 'Lightly Played', 'Moderately Played', 'Heavily Played', 'Damaged' ],
		'ygo_printing'  => [ '1st Edition', 'Unlimited', 'Limited' ],
		'ygo_language'  => [ 'English', 'Spanish', 'Japanese', 'Portuguese' ],
	];

	foreach ( $terms as $taxonomy => $names ) {
		foreach ( $names as $name ) {
			if ( ! term_exists( $name, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy );
			}
		}
	}

	// Add DB index for fast card title search.
	tcg_dokan_maybe_add_title_index();
}
register_activation_hook( __FILE__, 'tcg_dokan_activate' );

/**
 * Add a FULLTEXT index on post_title for fast card name search.
 * Also removes the old B-tree index if present.
 * Only runs once; safe to call multiple times.
 */
function tcg_dokan_maybe_add_title_index() {
	global $wpdb;

	$ft_index  = 'tcg_ft_post_title';
	$old_index = 'tcg_type_status_title';

	// Remove old B-tree index if it exists.
	$has_old = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = %s AND table_name = %s AND index_name = %s",
		DB_NAME,
		$wpdb->posts,
		$old_index
	) );
	if ( $has_old ) {
		$wpdb->query( "ALTER TABLE {$wpdb->posts} DROP INDEX {$old_index}" ); // phpcs:ignore
	}

	// Add FULLTEXT index if not present.
	$has_ft = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS WHERE table_schema = %s AND table_name = %s AND index_name = %s",
		DB_NAME,
		$wpdb->posts,
		$ft_index
	) );

	if ( ! $has_ft ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "ALTER TABLE {$wpdb->posts} ADD FULLTEXT INDEX {$ft_index} (post_title)" );
	}
}
