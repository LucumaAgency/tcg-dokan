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

		// Hide unnecessary form sections via Dokan filters.
		add_filter( 'dokan_product_edit_after_title', [ $this, 'hide_form_sections_css' ], 99 );
		add_filter( 'dokan_new_product_form_after_title', [ $this, 'hide_form_sections_css' ], 99 );

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
	 * Inject CSS to hide unnecessary Dokan product form sections.
	 */
	public function hide_form_sections_css() {
		?>
		<style>
			/* Downloadable / Virtual checkboxes */
			.dokan-product-type-container { display: none !important; }

			/* Categoría */
			.dokan-form-group:has(.dokan-new-cat-ui-title),
			.dokan-form-group:has(.dokan-select-product-category-container),
			.dokan-form-group:has(label[for="chosen_product_cat"]) { display: none !important; }

			/* Product featured image (right side) */
			.content-half-part.featured-image { display: none !important; }

			/* Brand */
			.dokan-form-group:has(#product_brand),
			.dokan-form-group:has(.product_brand_search),
			.dokan-form-group:has(label[for="product_brand"]) { display: none !important; }

			/* Tags / Etiquetas */
			.dokan-form-group:has(#product_tag_edit),
			.dokan-form-group:has(.product_tag_search),
			.dokan-form-group:has(label[for="product_tag_edit"]) { display: none !important; }

			/* Short description / Descripción corta */
			.dokan-product-short-description { display: none !important; }

			/* Description / long description */
			.dokan-product-description,
			.dokan-product-description-field,
			.dokan-form-group:has(#post_content) { display: none !important; }

			/* Attributes and Variations */
			.dokan-product-attribute-wrapper,
			.dokan-product-variation-wrapper,
			.dokan-attribute-variation-options,
			#dokan-product-attribute,
			#dokan-product-variation { display: none !important; }

			/* RMA */
			.dokan-rma-wrapper,
			.dokan-product-rma,
			#dokan-product-rma { display: none !important; }
		</style>
		<?php
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
			'cards'   => $this->get_all_cards_for_js(),
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

	/**
	 * Fetch all published ygo_card posts for client-side search.
	 * Cached in a transient for 1 hour to avoid heavy queries on every page load.
	 */
	private function get_all_cards_for_js() {
		$cache_key = 'tcg_dokan_cards_js';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		// Lightweight query: only the fields needed for autocomplete.
		$rows = $wpdb->get_results(
			"SELECT p.ID, p.post_title,
				MAX(CASE WHEN pm1.meta_key = '_ygo_set_code' THEN pm1.meta_value END) AS set_code,
				MAX(CASE WHEN pm1.meta_key = '_ygo_set_rarity' THEN pm1.meta_value END) AS set_rarity
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key IN ('_ygo_set_code', '_ygo_set_rarity')
			WHERE p.post_type = 'ygo_card' AND p.post_status = 'publish'
			GROUP BY p.ID
			ORDER BY p.post_title ASC",
			ARRAY_A
		);

		$cards = [];
		foreach ( $rows as $row ) {
			$set_code = $row['set_code'] ?: '';
			$cards[]  = [
				'id'         => (int) $row['ID'],
				'label'      => $row['post_title'] . ( $set_code ? " [{$set_code}]" : '' ),
				'value'      => $row['post_title'],
				'set_code'   => $set_code,
				'set_rarity' => $row['set_rarity'] ?: '',
			];
		}

		// Cache for 1 hour.
		set_transient( $cache_key, $cards, HOUR_IN_SECONDS );

		return $cards;
	}
}
