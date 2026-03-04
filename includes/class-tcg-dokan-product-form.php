<?php
/**
 * Dokan vendor product form: card selector, condition/printing/language dropdowns, save hooks.
 */

defined( 'ABSPATH' ) || exit;

class TCG_Dokan_Product_Form {

	public function __construct() {
		// Render fields on new/edit product forms.
		add_action( 'dokan_new_product_after_product_tags', [ $this, 'render_card_selector' ] );
		add_action( 'dokan_product_edit_after_product_tags', [ $this, 'render_card_selector' ] );

		add_action( 'dokan_new_product_after_product_tags', [ $this, 'render_listing_fields' ] );
		add_action( 'dokan_product_edit_after_product_tags', [ $this, 'render_listing_fields' ] );

		// Save data.
		add_action( 'dokan_new_product_added', [ $this, 'save_fields' ], 10, 2 );
		add_action( 'dokan_product_updated', [ $this, 'save_fields' ], 10, 2 );

		// Enqueue assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Render the card search/selector UI.
	 */
	public function render_card_selector( $post_id = 0 ) {
		$linked_card = $post_id ? (int) get_post_meta( $post_id, '_linked_ygo_card', true ) : 0;
		$card_title  = $linked_card ? get_the_title( $linked_card ) : '';
		?>
		<div class="dokan-form-group tcg-card-selector">
			<label for="tcg-card-search" class="form-label">
				<?php esc_html_e( 'Carta vinculada', 'tcg-dokan' ); ?> <span class="required">*</span>
			</label>
			<input
				type="text"
				id="tcg-card-search"
				class="dokan-form-control"
				placeholder="<?php esc_attr_e( 'Buscar carta por nombre...', 'tcg-dokan' ); ?>"
				value="<?php echo esc_attr( $card_title ); ?>"
				<?php echo $linked_card ? 'readonly' : ''; ?>
				autocomplete="off"
			>
			<input type="hidden" name="_linked_ygo_card" id="tcg-linked-card-id" value="<?php echo esc_attr( $linked_card ); ?>">

			<?php if ( $linked_card ) : ?>
				<button type="button" id="tcg-change-card" class="dokan-btn dokan-btn-sm dokan-btn-default" style="margin-top:5px;">
					<?php esc_html_e( 'Cambiar carta', 'tcg-dokan' ); ?>
				</button>
			<?php endif; ?>

			<div id="tcg-card-preview" class="tcg-card-preview" style="<?php echo $linked_card ? '' : 'display:none;'; ?>">
				<!-- Filled by JS -->
			</div>
		</div>
		<?php
	}

	/**
	 * Render condition, printing, language dropdowns.
	 */
	public function render_listing_fields( $post_id = 0 ) {
		$taxonomies = [
			'ygo_condition' => __( 'Condición', 'tcg-dokan' ),
			'ygo_printing'  => __( 'Printing', 'tcg-dokan' ),
			'ygo_language'  => __( 'Idioma', 'tcg-dokan' ),
		];

		foreach ( $taxonomies as $tax => $label ) {
			$terms = get_terms( [
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'orderby'    => 'name',
			] );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			// Get current value.
			$current = [];
			if ( $post_id ) {
				$assigned = wp_get_post_terms( $post_id, $tax, [ 'fields' => 'ids' ] );
				if ( ! is_wp_error( $assigned ) ) {
					$current = $assigned;
				}
			}

			?>
			<div class="dokan-form-group">
				<label for="tcg-<?php echo esc_attr( $tax ); ?>" class="form-label">
					<?php echo esc_html( $label ); ?> <span class="required">*</span>
				</label>
				<select name="<?php echo esc_attr( $tax ); ?>" id="tcg-<?php echo esc_attr( $tax ); ?>" class="dokan-form-control">
					<option value=""><?php esc_html_e( '— Seleccionar —', 'tcg-dokan' ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->term_id ); ?>"
							<?php selected( in_array( $term->term_id, $current, true ) ); ?>>
							<?php echo esc_html( $term->name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php
		}
	}

	/**
	 * Save linked card meta and taxonomy terms.
	 */
	public function save_fields( $product_id, $data ) {
		// Save linked card.
		if ( isset( $_POST['_linked_ygo_card'] ) ) {
			$card_id = absint( $_POST['_linked_ygo_card'] );
			if ( $card_id && get_post_type( $card_id ) === 'ygo_card' ) {
				update_post_meta( $product_id, '_linked_ygo_card', $card_id );
			}
		}

		// Save taxonomy terms.
		$taxonomies = [ 'ygo_condition', 'ygo_printing', 'ygo_language' ];
		foreach ( $taxonomies as $tax ) {
			if ( isset( $_POST[ $tax ] ) && $_POST[ $tax ] !== '' ) {
				$term_id = absint( $_POST[ $tax ] );
				wp_set_object_terms( $product_id, $term_id, $tax );
			}
		}
	}

	/**
	 * Enqueue JS/CSS only on Dokan dashboard.
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-autocomplete' );

		wp_enqueue_script(
			'tcg-vendor-form',
			TCG_DOKAN_URL . 'assets/js/vendor-form.js',
			[ 'jquery', 'jquery-ui-autocomplete' ],
			TCG_DOKAN_VERSION,
			true
		);

		wp_localize_script( 'tcg-vendor-form', 'tcgDokan', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tcg_dokan_nonce' ),
			'i18n'    => [
				'searching'  => __( 'Buscando...', 'tcg-dokan' ),
				'noResults'  => __( 'No se encontraron cartas', 'tcg-dokan' ),
				'selectCard' => __( 'Selecciona una carta para continuar', 'tcg-dokan' ),
			],
		] );

		wp_enqueue_style(
			'tcg-vendor-form',
			TCG_DOKAN_URL . 'assets/css/vendor-form.css',
			[],
			TCG_DOKAN_VERSION
		);
	}
}
