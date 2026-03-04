<?php
/**
 * Frontend display: card stats table and reference prices on single product page.
 */

defined( 'ABSPATH' ) || exit;

class TCG_Dokan_Display {

	public function __construct() {
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_card_stats' ], 25 );
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_ref_prices' ], 35 );
	}

	/**
	 * Render card stats table on single product page.
	 */
	public function render_card_stats() {
		global $product;

		$card_id = (int) get_post_meta( $product->get_id(), '_linked_ygo_card', true );
		if ( ! $card_id ) {
			return;
		}

		$stats = $this->get_display_stats( $card_id );
		if ( empty( $stats ) ) {
			return;
		}

		echo '<div class="tcg-card-stats">';
		echo '<h4>' . esc_html__( 'Card Stats', 'tcg-dokan' ) . '</h4>';
		echo '<table class="tcg-stats-table">';

		foreach ( $stats as $label => $value ) {
			echo '<tr>';
			echo '<th>' . esc_html( $label ) . '</th>';
			echo '<td>' . esc_html( $value ) . '</td>';
			echo '</tr>';
		}

		echo '</table>';
		echo '</div>';
	}

	/**
	 * Render reference prices on single product page.
	 */
	public function render_ref_prices() {
		global $product;

		$card_id = (int) get_post_meta( $product->get_id(), '_linked_ygo_card', true );
		if ( ! $card_id ) {
			return;
		}

		$prices_json = get_post_meta( $card_id, '_ygo_ref_prices', true );
		$prices = $prices_json ? json_decode( $prices_json, true ) : [];

		if ( empty( $prices ) ) {
			return;
		}

		$labels = [
			'tcgplayer'  => 'TCGPlayer',
			'cardmarket' => 'Cardmarket',
			'ebay'       => 'eBay',
			'amazon'     => 'Amazon',
		];

		$has_values = false;
		foreach ( $labels as $key => $label ) {
			if ( ! empty( $prices[ $key ] ) && $prices[ $key ] !== '0' && $prices[ $key ] !== '0.00' ) {
				$has_values = true;
				break;
			}
		}

		if ( ! $has_values ) {
			return;
		}

		echo '<div class="tcg-ref-prices">';
		echo '<h4>' . esc_html__( 'Precios de Referencia', 'tcg-dokan' ) . '</h4>';
		echo '<table class="tcg-prices-table">';

		foreach ( $labels as $key => $label ) {
			if ( empty( $prices[ $key ] ) || $prices[ $key ] === '0' || $prices[ $key ] === '0.00' ) {
				continue;
			}
			echo '<tr>';
			echo '<th>' . esc_html( $label ) . '</th>';
			echo '<td>$' . esc_html( $prices[ $key ] ) . '</td>';
			echo '</tr>';
		}

		echo '</table>';
		echo '</div>';
	}

	/**
	 * Build associative array of displayable stats.
	 */
	private function get_display_stats( $card_id ) {
		$stats = [];

		$set_code = get_post_meta( $card_id, '_ygo_set_code', true );
		if ( $set_code ) {
			$stats[ __( 'Set Code', 'tcg-dokan' ) ] = $set_code;
		}

		$rarity = get_post_meta( $card_id, '_ygo_set_rarity', true );
		if ( $rarity ) {
			$stats[ __( 'Rarity', 'tcg-dokan' ) ] = $rarity;
		}

		$frame = get_post_meta( $card_id, '_ygo_frame_type', true );
		if ( $frame ) {
			$stats[ __( 'Card Type', 'tcg-dokan' ) ] = ucfirst( $frame );
		}

		$typeline = get_post_meta( $card_id, '_ygo_typeline', true );
		if ( $typeline ) {
			$stats[ __( 'Type', 'tcg-dokan' ) ] = $typeline;
		}

		// Attribute taxonomy.
		$attrs = wp_get_post_terms( $card_id, 'ygo_attribute', [ 'fields' => 'names' ] );
		if ( ! is_wp_error( $attrs ) && ! empty( $attrs ) ) {
			$stats[ __( 'Attribute', 'tcg-dokan' ) ] = implode( ', ', $attrs );
		}

		$level = get_post_meta( $card_id, '_ygo_level', true );
		if ( $level ) {
			$stats[ __( 'Level', 'tcg-dokan' ) ] = $level;
		}

		$rank = get_post_meta( $card_id, '_ygo_rank', true );
		if ( $rank ) {
			$stats[ __( 'Rank', 'tcg-dokan' ) ] = $rank;
		}

		$linkval = get_post_meta( $card_id, '_ygo_linkval', true );
		if ( $linkval ) {
			$stats[ __( 'Link', 'tcg-dokan' ) ] = $linkval;
		}

		$scale = get_post_meta( $card_id, '_ygo_scale', true );
		if ( $scale ) {
			$stats[ __( 'Pendulum Scale', 'tcg-dokan' ) ] = $scale;
		}

		$atk = get_post_meta( $card_id, '_ygo_atk', true );
		if ( $atk !== '' && $atk !== false ) {
			$stats[ __( 'ATK', 'tcg-dokan' ) ] = $atk;
		}

		$def = get_post_meta( $card_id, '_ygo_def', true );
		if ( $def !== '' && $def !== false ) {
			$stats[ __( 'DEF', 'tcg-dokan' ) ] = $def;
		}

		return $stats;
	}
}
