<?php
/**
 * Listings: TCGPlayer-style card page with vendor listings.
 *
 * Shortcodes:
 *   [tcg_buy_box]        — Buy box (best listing) or "no disponible"
 *   [tcg_other_vendors]  — Table of remaining vendor listings
 *   [tcg_card_listings]  — Both combined (buy box + table)
 */

defined( 'ABSPATH' ) || exit;

class TCG_Dokan_Listings {

	public function __construct() {
		add_shortcode( 'tcg_card_listings', [ $this, 'render_shortcode_combined' ] );
		add_shortcode( 'tcg_buy_box', [ $this, 'render_shortcode_buy_box' ] );
		add_shortcode( 'tcg_other_vendors', [ $this, 'render_shortcode_other_vendors' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/* ------------------------------------------------------------------
	 * Shortcode callbacks
	 * ---------------------------------------------------------------- */

	/**
	 * [tcg_card_listings] — Combined: buy box + other vendors table.
	 */
	public function render_shortcode_combined() {
		if ( ! is_singular( 'ygo_card' ) ) {
			return '';
		}

		$listings = $this->get_ranked_listings();

		ob_start();

		if ( empty( $listings ) ) {
			$this->render_out_of_stock();
		} elseif ( count( $listings ) === 1 ) {
			$this->render_buy_box( $listings[0] );
		} else {
			$this->render_buy_box( $listings[0] );
			$this->render_vendors_table( array_slice( $listings, 1 ) );
		}

		return ob_get_clean();
	}

	/**
	 * [tcg_buy_box] — Only the buy box (best listing or out-of-stock).
	 */
	public function render_shortcode_buy_box() {
		if ( ! is_singular( 'ygo_card' ) ) {
			return '';
		}

		$listings = $this->get_ranked_listings();

		ob_start();

		if ( empty( $listings ) ) {
			$this->render_out_of_stock();
		} else {
			$this->render_buy_box( $listings[0] );
		}

		return ob_get_clean();
	}

	/**
	 * [tcg_other_vendors] — Only the other vendors table (excludes the best listing).
	 */
	public function render_shortcode_other_vendors() {
		if ( ! is_singular( 'ygo_card' ) ) {
			return '';
		}

		$listings = $this->get_ranked_listings();

		ob_start();

		if ( count( $listings ) >= 2 ) {
			$this->render_vendors_table( array_slice( $listings, 1 ) );
		}

		return ob_get_clean();
	}

	/* ------------------------------------------------------------------
	 * Data helpers
	 * ---------------------------------------------------------------- */

	/**
	 * Get ranked listings for the current card. Cached per request.
	 */
	private function get_ranked_listings() {
		static $cache = [];

		$card_id = get_the_ID();
		if ( isset( $cache[ $card_id ] ) ) {
			return $cache[ $card_id ];
		}

		$listings = $this->get_vendor_listings( $card_id );
		$listings = $this->rank_listings( $listings );
		$cache[ $card_id ] = $listings;

		return $listings;
	}

	/**
	 * Query WooCommerce products linked to this card that are in stock.
	 */
	private function get_vendor_listings( $card_id ) {
		$query = new WP_Query( [
			'post_type'      => 'product',
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'meta_query'     => [
				[
					'key'   => '_linked_ygo_card',
					'value' => $card_id,
					'type'  => 'NUMERIC',
				],
			],
		] );

		$listings = [];

		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product || ! $product->is_in_stock() ) {
				continue;
			}

			$listing = $this->get_listing_data( $product );
			if ( $listing ) {
				$listings[] = $listing;
			}
		}

		wp_reset_postdata();

		return $listings;
	}

	/**
	 * Build listing data array from a WooCommerce product.
	 */
	private function get_listing_data( $product ) {
		$product_id = $product->get_id();
		$vendor_id  = (int) get_post_field( 'post_author', $product_id );

		// Vendor info via Dokan.
		$vendor_name = '';
		$vendor_url  = '';
		if ( function_exists( 'dokan' ) ) {
			$vendor      = dokan()->vendor->get( $vendor_id );
			$vendor_name = $vendor->get_shop_name();
			$vendor_url  = $vendor->get_shop_url();
		}

		// Taxonomy terms.
		$condition = $this->get_first_term( $product_id, 'ygo_condition' );
		$printing  = $this->get_first_term( $product_id, 'ygo_printing' );
		$language  = $this->get_first_term( $product_id, 'ygo_language' );

		return [
			'product_id'  => $product_id,
			'price'       => (float) $product->get_price(),
			'price_html'  => $product->get_price_html(),
			'stock_qty'   => $product->get_stock_quantity() ?: 0,
			'total_sales' => (int) get_post_meta( $product_id, 'total_sales', true ),
			'vendor_name' => $vendor_name,
			'vendor_url'  => $vendor_url,
			'condition'   => $condition,
			'printing'    => $printing,
			'language'    => $language,
		];
	}

	/**
	 * Get first taxonomy term name for a product.
	 */
	private function get_first_term( $product_id, $taxonomy ) {
		$terms = wp_get_post_terms( $product_id, $taxonomy, [ 'fields' => 'names' ] );
		return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0] : '';
	}

	/**
	 * Sort listings: highest total_sales first, price ASC as tiebreaker.
	 */
	private function rank_listings( $listings ) {
		usort( $listings, function( $a, $b ) {
			if ( $a['total_sales'] !== $b['total_sales'] ) {
				return $b['total_sales'] - $a['total_sales'];
			}
			return $a['price'] <=> $b['price'];
		} );
		return $listings;
	}

	/**
	 * Render a quantity <select> dropdown with options 1..stock_qty.
	 */
	private function render_qty_select( $stock_qty, $css_class = 'tcg-qty-select' ) {
		$max = $stock_qty > 0 ? min( $stock_qty, 99 ) : 10;
		echo '<select class="' . esc_attr( $css_class ) . '">';
		for ( $i = 1; $i <= $max; $i++ ) {
			echo '<option value="' . esc_attr( $i ) . '">' . esc_html( $i ) . '</option>';
		}
		echo '</select>';
	}

	/* ------------------------------------------------------------------
	 * Render methods
	 * ---------------------------------------------------------------- */

	/**
	 * Render out-of-stock message.
	 */
	private function render_out_of_stock() {
		?>
		<div class="tcg-buy-box tcg-buy-box--empty">
			<p class="tcg-buy-box__unavailable">
				<?php esc_html_e( 'No disponible actualmente', 'tcg-dokan' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the Buy Box for the winning listing.
	 */
	private function render_buy_box( $listing ) {
		$nonce = wp_create_nonce( 'tcg_listings_nonce' );
		?>
		<div class="tcg-buy-box" data-product-id="<?php echo esc_attr( $listing['product_id'] ); ?>">
			<div class="tcg-buy-box__price">
				<?php echo $listing['price_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<div class="tcg-buy-box__badges">
				<?php if ( $listing['condition'] ) : ?>
					<span class="tcg-badge tcg-badge--condition"><?php echo esc_html( $listing['condition'] ); ?></span>
				<?php endif; ?>
				<?php if ( $listing['printing'] ) : ?>
					<span class="tcg-badge tcg-badge--printing"><?php echo esc_html( $listing['printing'] ); ?></span>
				<?php endif; ?>
				<?php if ( $listing['language'] ) : ?>
					<span class="tcg-badge tcg-badge--language"><?php echo esc_html( $listing['language'] ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( $listing['vendor_name'] ) : ?>
				<div class="tcg-buy-box__vendor">
					<?php esc_html_e( 'Vendido por', 'tcg-dokan' ); ?>
					<a href="<?php echo esc_url( $listing['vendor_url'] ); ?>">
						<?php echo esc_html( $listing['vendor_name'] ); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="tcg-buy-box__stock">
				<?php
				if ( $listing['stock_qty'] > 0 ) {
					/* translators: %d: stock quantity */
					printf( esc_html__( '%d disponible(s)', 'tcg-dokan' ), $listing['stock_qty'] );
				} else {
					esc_html_e( 'En stock', 'tcg-dokan' );
				}
				?>
			</div>

			<div class="tcg-buy-box__actions">
				<?php $this->render_qty_select( $listing['stock_qty'] ); ?>
				<button type="button" class="tcg-add-to-cart button alt"
					data-product-id="<?php echo esc_attr( $listing['product_id'] ); ?>"
					data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Agregar al carrito', 'tcg-dokan' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the vendors comparison table.
	 */
	private function render_vendors_table( $listings ) {
		if ( empty( $listings ) ) {
			return;
		}

		$nonce = wp_create_nonce( 'tcg_listings_nonce' );
		?>
		<div class="tcg-vendors-section">
			<h3 class="tcg-vendors-section__title">
				<?php
				/* translators: %d: number of other vendors */
				printf( esc_html__( 'Otros vendedores (%d)', 'tcg-dokan' ), count( $listings ) );
				?>
			</h3>

			<table class="tcg-vendors-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Vendedor', 'tcg-dokan' ); ?></th>
						<th><?php esc_html_e( 'Condición', 'tcg-dokan' ); ?></th>
						<th><?php esc_html_e( 'Printing', 'tcg-dokan' ); ?></th>
						<th><?php esc_html_e( 'Idioma', 'tcg-dokan' ); ?></th>
						<th><?php esc_html_e( 'Precio', 'tcg-dokan' ); ?></th>
						<th><?php esc_html_e( 'Stock', 'tcg-dokan' ); ?></th>
						<th><?php esc_html_e( 'Cantidad', 'tcg-dokan' ); ?></th>
						<th><?php esc_html_e( 'Comprar', 'tcg-dokan' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $listings as $listing ) : ?>
					<tr data-product-id="<?php echo esc_attr( $listing['product_id'] ); ?>">
						<td data-label="<?php esc_attr_e( 'Vendedor', 'tcg-dokan' ); ?>">
							<?php if ( $listing['vendor_url'] ) : ?>
								<a href="<?php echo esc_url( $listing['vendor_url'] ); ?>">
									<?php echo esc_html( $listing['vendor_name'] ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $listing['vendor_name'] ); ?>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Condición', 'tcg-dokan' ); ?>">
							<?php if ( $listing['condition'] ) : ?>
								<span class="tcg-badge tcg-badge--condition"><?php echo esc_html( $listing['condition'] ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Printing', 'tcg-dokan' ); ?>">
							<?php if ( $listing['printing'] ) : ?>
								<span class="tcg-badge tcg-badge--printing"><?php echo esc_html( $listing['printing'] ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Idioma', 'tcg-dokan' ); ?>">
							<?php if ( $listing['language'] ) : ?>
								<span class="tcg-badge tcg-badge--language"><?php echo esc_html( $listing['language'] ); ?></span>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Precio', 'tcg-dokan' ); ?>">
							<?php echo $listing['price_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Stock', 'tcg-dokan' ); ?>">
							<?php echo esc_html( $listing['stock_qty'] > 0 ? $listing['stock_qty'] : __( 'En stock', 'tcg-dokan' ) ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Cantidad', 'tcg-dokan' ); ?>">
							<?php $this->render_qty_select( $listing['stock_qty'], 'tcg-qty-select' ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Comprar', 'tcg-dokan' ); ?>">
							<button type="button" class="tcg-add-to-cart button alt"
								data-product-id="<?php echo esc_attr( $listing['product_id'] ); ?>"
								data-nonce="<?php echo esc_attr( $nonce ); ?>">
								<?php esc_html_e( 'Comprar', 'tcg-dokan' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Enqueue CSS/JS only on ygo_card singles.
	 */
	public function enqueue_assets() {
		if ( ! is_singular( 'ygo_card' ) ) {
			return;
		}

		wp_enqueue_style(
			'tcg-listings',
			TCG_DOKAN_URL . 'assets/css/listings.css',
			[],
			TCG_DOKAN_VERSION
		);

		wp_enqueue_script(
			'tcg-listings',
			TCG_DOKAN_URL . 'assets/js/listings.js',
			[ 'jquery' ],
			TCG_DOKAN_VERSION,
			true
		);

		wp_localize_script( 'tcg-listings', 'tcgListings', [
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'i18n'    => [
				'adding'  => __( 'Agregando…', 'tcg-dokan' ),
				'added'   => __( '¡Agregado!', 'tcg-dokan' ),
				'error'   => __( 'Error al agregar', 'tcg-dokan' ),
				'buy'     => __( 'Comprar', 'tcg-dokan' ),
				'add'     => __( 'Agregar al carrito', 'tcg-dokan' ),
			],
		] );
	}
}
