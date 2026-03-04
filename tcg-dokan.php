<?php
/**
 * Plugin Name: TCG Dokan
 * Description: Marketplace de cartas YGO con Dokan — vendors crean productos WooCommerce vinculados al catálogo de cartas.
 * Version:     1.0.0
 * Author:      TCG Dev
 * Requires Plugins: woocommerce, dokan-lite
 * Text Domain: tcg-dokan
 */

defined( 'ABSPATH' ) || exit;

define( 'TCG_DOKAN_VERSION', '1.0.0' );
define( 'TCG_DOKAN_PATH', plugin_dir_path( __FILE__ ) );
define( 'TCG_DOKAN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check dependencies before loading.
 */
function tcg_dokan_check_dependencies() {
	$missing = [];

	if ( ! class_exists( 'WooCommerce' ) ) {
		$missing[] = 'WooCommerce';
	}

	if ( ! class_exists( 'WeDevs_Dokan' ) ) {
		$missing[] = 'Dokan';
	}

	if ( ! post_type_exists( 'ygo_card' ) ) {
		$missing[] = 'YGO Card CPT (tcg-theme)';
	}

	if ( $missing ) {
		add_action( 'admin_notices', function() use ( $missing ) {
			$list = implode( ', ', $missing );
			echo '<div class="notice notice-error"><p>';
			echo '<strong>TCG Dokan:</strong> requiere los siguientes componentes activos: ' . esc_html( $list );
			echo '</p></div>';
		} );
		return false;
	}

	return true;
}

/**
 * Boot the plugin.
 */
function tcg_dokan_init() {
	if ( ! tcg_dokan_check_dependencies() ) {
		return;
	}

	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-setup.php';
	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-ajax.php';
	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-product-form.php';
	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-product-sync.php';
	require_once TCG_DOKAN_PATH . 'includes/class-tcg-dokan-display.php';

	new TCG_Dokan_Setup();
	new TCG_Dokan_Ajax();
	new TCG_Dokan_Product_Form();
	new TCG_Dokan_Product_Sync();
	new TCG_Dokan_Display();
}
add_action( 'plugins_loaded', 'tcg_dokan_init', 20 );

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
}
register_activation_hook( __FILE__, 'tcg_dokan_activate' );
