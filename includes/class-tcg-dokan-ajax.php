<?php
/**
 * AJAX handlers: card search autocomplete and card data retrieval.
 */

defined( 'ABSPATH' ) || exit;

class TCG_Dokan_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_tcg_search_ygo_cards', [ $this, 'search_cards' ] );
		add_action( 'wp_ajax_tcg_get_ygo_card_data', [ $this, 'get_card_data' ] );
	}

	/**
	 * Search ygo_card posts by title. Returns max 15 results.
	 */
	public function search_cards() {
		check_ajax_referer( 'tcg_dokan_nonce', 'nonce' );

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( strlen( $term ) < 2 ) {
			wp_send_json( [] );
		}

		$query = new WP_Query( [
			'post_type'      => 'ygo_card',
			'posts_per_page' => 15,
			's'              => $term,
			'post_status'    => 'publish',
			'orderby'        => 'relevance',
		] );

		$results = [];

		foreach ( $query->posts as $card ) {
			$set_code   = get_post_meta( $card->ID, '_ygo_set_code', true );
			$set_rarity = get_post_meta( $card->ID, '_ygo_set_rarity', true );
			$thumb      = get_the_post_thumbnail_url( $card->ID, 'thumbnail' );

			$results[] = [
				'id'         => $card->ID,
				'label'      => $card->post_title . ( $set_code ? " [{$set_code}]" : '' ),
				'value'      => $card->post_title,
				'thumbnail'  => $thumb ?: '',
				'set_code'   => $set_code,
				'set_rarity' => $set_rarity,
			];
		}

		wp_send_json( $results );
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
}
