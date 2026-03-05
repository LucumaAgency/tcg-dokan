/**
 * TCG Dokan — Listings: AJAX add-to-cart for buy box and vendors table.
 */
(function( $ ) {
	'use strict';

	$( document ).on( 'click', '.tcg-add-to-cart', function( e ) {
		e.preventDefault();

		var $btn       = $( this );
		var productId  = $btn.data( 'product-id' );
		var nonce      = $btn.data( 'nonce' );

		// Find qty input: in same buy box or same table row.
		var $context   = $btn.closest( '.tcg-buy-box, tr' );
		var qty        = parseInt( $context.find( '.tcg-qty-input' ).val(), 10 ) || 1;

		// Prevent double-click.
		if ( $btn.hasClass( 'tcg-loading' ) ) {
			return;
		}

		var originalText = $btn.text();
		$btn.addClass( 'tcg-loading' ).prop( 'disabled', true ).text( tcgListings.i18n.adding );

		$.post( tcgListings.ajaxurl, {
			action:     'tcg_add_to_cart',
			nonce:      nonce,
			product_id: productId,
			quantity:   qty
		}, function( res ) {
			if ( res.success ) {
				$btn.removeClass( 'tcg-loading' ).addClass( 'tcg-added' ).text( tcgListings.i18n.added );

				// Update WooCommerce mini-cart fragments.
				if ( res.data.fragments ) {
					$.each( res.data.fragments, function( key, val ) {
						$( key ).replaceWith( val );
					} );
				}

				// Update cart count badge if present.
				if ( res.data.cart_count !== undefined ) {
					$( '.cart-contents-count, .cart-count' ).text( res.data.cart_count );
				}

				// Trigger WooCommerce event.
				$( document.body ).trigger( 'added_to_cart', [ res.data.fragments, res.data.cart_hash ] );

				// Reset button after 2s.
				setTimeout( function() {
					// Determine default label based on context.
					var isTable = $btn.closest( 'tr' ).length > 0;
					var label   = isTable ? tcgListings.i18n.buy : tcgListings.i18n.add;
					$btn.removeClass( 'tcg-added' ).prop( 'disabled', false ).text( label );
				}, 2000 );
			} else {
				$btn.removeClass( 'tcg-loading' ).prop( 'disabled', false ).text( originalText );
				if ( res.data ) {
					alert( res.data );
				}
			}
		}).fail( function() {
			$btn.removeClass( 'tcg-loading' ).prop( 'disabled', false ).text( originalText );
			alert( tcgListings.i18n.error );
		});
	});
})( jQuery );
