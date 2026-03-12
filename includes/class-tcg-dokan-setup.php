<?php
/**
 * Setup: taxonomy registration backup, meta registration, Dokan product type filter.
 */

defined( 'ABSPATH' ) || exit;

class TCG_Dokan_Setup {

	public function __construct() {
		add_action( 'init', [ $this, 'ensure_taxonomies_on_product' ], 20 );
		add_action( 'init', [ $this, 'register_product_meta' ], 20 );
		add_filter( 'dokan_product_types', [ $this, 'limit_product_types' ] );
	}

	/**
	 * Backup: ensure YGO taxonomies are registered for 'product'.
	 * The theme handles primary registration; this catches edge cases.
	 */
	public function ensure_taxonomies_on_product() {
		$taxonomies = [ 'ygo_set', 'ygo_rarity', 'ygo_condition', 'ygo_printing', 'ygo_language' ];

		foreach ( $taxonomies as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				register_taxonomy_for_object_type( $tax, 'product' );
			}
		}
	}

	/**
	 * Register _linked_ygo_card meta on product.
	 */
	public function register_product_meta() {
		register_post_meta( 'product', '_linked_ygo_card', [
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => function() {
				return current_user_can( 'edit_products' );
			},
		] );
	}

	/**
	 * Only allow Simple Product in Dokan vendor dashboard.
	 */
	public function limit_product_types( $types ) {
		return [
			'simple' => __( 'Simple Product', 'tcg-dokan' ),
		];
	}
}
