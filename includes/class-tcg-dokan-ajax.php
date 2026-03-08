<?php
/**
 * AJAX handlers: card search autocomplete and card data retrieval.
 */

defined( 'ABSPATH' ) || exit;

class TCG_Dokan_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_tcg_search_ygo_cards', [ $this, 'search_cards' ] );
		add_action( 'wp_ajax_tcg_get_ygo_card_data', [ $this, 'get_card_data' ] );
		add_action( 'wp_ajax_tcg_add_to_cart', [ $this, 'add_to_cart' ] );
		add_action( 'wp_ajax_nopriv_tcg_add_to_cart', [ $this, 'add_to_cart' ] );
	}

	/**
	 * Search ygo_card posts by title only. Returns max 15 results.
	 */
	public function search_cards() {
		check_ajax_referer( 'tcg_dokan_nonce', 'nonce' );

		try {
			$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

			if ( strlen( $term ) < 3 ) {
				wp_send_json( [] );
			}

			global $wpdb;

			// FULLTEXT search on post_title for ygo_card posts only.
			// Append * for prefix matching (e.g. "bujin" matches "bujintei").
			$ft_term = $wpdb->esc_like( $term ) . '*';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$post_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ygo_card' AND post_status = 'publish' AND MATCH(post_title) AGAINST(%s IN BOOLEAN MODE) ORDER BY post_title ASC LIMIT 15",
				$ft_term
			) );

			// Fallback to LIKE if FULLTEXT returns nothing (e.g. partial mid-word match).
			if ( empty( $post_ids ) ) {
				$like = '%' . $wpdb->esc_like( $term ) . '%';
				$post_ids = $wpdb->get_col( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ygo_card' AND post_status = 'publish' AND post_title LIKE %s ORDER BY post_title ASC LIMIT 15",
					$like
				) );
			}

			$results = [];

			if ( ! empty( $post_ids ) ) {
				update_meta_cache( 'post', $post_ids );

				foreach ( $post_ids as $card_id ) {
					$card = get_post( $card_id );
					if ( ! $card ) {
						continue;
					}
					$set_code   = get_post_meta( $card_id, '_ygo_set_code', true );
					$set_rarity = get_post_meta( $card_id, '_ygo_set_rarity', true );
					$thumb      = get_the_post_thumbnail_url( $card_id, 'thumbnail' );

					$results[] = [
						'id'         => $card->ID,
						'label'      => $card->post_title . ( $set_code ? " [{$set_code}]" : '' ),
						'value'      => $card->post_title,
						'thumbnail'  => $thumb ?: '',
						'set_code'   => $set_code,
						'set_rarity' => $set_rarity,
					];
				}
			}

			wp_send_json( $results );
		} catch ( \Throwable $e ) {
			wp_send_json_error( [
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			] );
		}
	}

	/**
	 * Get full card data for a given ygo_card ID.
	 */
	public function get_card_data() {
		check_ajax_referer( 'tcg_dokan_nonce', 'nonce' );

		$card_id = isset( $_GET['card_id'] ) ? absint( $_GET['card_id'] ) : 0;

		if ( ! $card_id || get_post_type( $card_id ) !== 'ygo_card' ) {
			wp_send_json_error( 'Invalid card ID' );
		}

		$card = get_post( $card_id );

		$meta_keys = [
			'_ygo_card_id', '_ygo_frame_type', '_ygo_typeline',
			'_ygo_atk', '_ygo_def', '_ygo_level', '_ygo_rank',
			'_ygo_linkval', '_ygo_scale', '_ygo_set_code',
			'_ygo_set_rarity', '_ygo_set_rarity_code', '_ygo_set_price',
		];

		$meta = [];
		foreach ( $meta_keys as $key ) {
			$meta[ $key ] = get_post_meta( $card_id, $key, true );
		}

		// Decode reference prices.
		$prices_json = get_post_meta( $card_id, '_ygo_ref_prices', true );
		$meta['_ygo_ref_prices'] = $prices_json ? json_decode( $prices_json, true ) : [];

		// Taxonomies.
		$taxonomies = [];
		foreach ( [ 'ygo_set', 'ygo_card_type', 'ygo_attribute', 'ygo_race', 'ygo_archetype' ] as $tax ) {
			$terms = wp_get_post_terms( $card_id, $tax, [ 'fields' => 'names' ] );
			$taxonomies[ $tax ] = is_wp_error( $terms ) ? [] : $terms;
		}

		wp_send_json_success( [
			'id'          => $card->ID,
			'title'       => $card->post_title,
			'description' => $card->post_content,
			'thumbnail'   => get_the_post_thumbnail_url( $card_id, 'medium' ),
			'meta'        => $meta,
			'taxonomies'  => $taxonomies,
		] );
	}

	/**
	 * AJAX add-to-cart for listings (buy box and vendor table).
	 */
	public function add_to_cart() {
		check_ajax_referer( 'tcg_listings_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

		if ( ! $product_id || $quantity < 1 ) {
			wp_send_json_error( __( 'Datos inválidos.', 'tcg-dokan' ) );
		}

		// Validate product exists and is linked to a card.
		$card_id = (int) get_post_meta( $product_id, '_linked_ygo_card', true );
		if ( ! $card_id ) {
			wp_send_json_error( __( 'Producto no válido.', 'tcg-dokan' ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_in_stock() ) {
			wp_send_json_error( __( 'Producto sin stock.', 'tcg-dokan' ) );
		}

		// Validate stock quantity.
		$stock_qty = $product->get_stock_quantity();
		if ( $stock_qty !== null && $quantity > $stock_qty ) {
			wp_send_json_error(
				/* translators: %d: available stock */
				sprintf( __( 'Solo %d disponible(s).', 'tcg-dokan' ), $stock_qty )
			);
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );

		if ( ! $cart_item_key ) {
			wp_send_json_error( __( 'No se pudo agregar al carrito.', 'tcg-dokan' ) );
		}

		// Build mini-cart fragments.
		$fragments = apply_filters( 'woocommerce_add_to_cart_fragments', [] );

		wp_send_json_success( [
			'fragments'  => $fragments,
			'cart_hash'  => WC()->cart->get_cart_hash(),
			'cart_count' => WC()->cart->get_cart_contents_count(),
		] );
	}
}
