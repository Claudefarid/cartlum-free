/**
 * Cartlum option buttons. Each button sets WooCommerce's hidden select and lets WooCommerce do the
 * rest (price, stock, image, Add to cart). Buttons follow the select: the chosen one is pressed,
 * choices WooCommerce has no variation for are disabled, and "Clear" resets them.
 */
( function ( $ ) {
	'use strict';

	// Whether some in-stock variation fits this choice and the other options already chosen. Unknown
	// (true) when WooCommerce loads variations on demand for large products.
	function inStock( form, name, value ) {
		var variations = $( form ).data( 'product_variations' );
		if ( ! Array.isArray( variations ) ) {
			return true;
		}
		var chosen = {};
		$( form ).find( 'select[name^="attribute_"]' ).each( function () {
			chosen[ this.name ] = this.name === name ? value : this.value;
		} );
		return variations.some( function ( v ) {
			if ( ! v.is_in_stock ) {
				return false;
			}
			return Object.keys( chosen ).every( function ( key ) {
				var want = v.attributes[ key ];
				return ! chosen[ key ] || ! want || want === chosen[ key ];
			} );
		} );
	}

	function sync( form ) {
		$( form ).find( 'select[data-cartlum-swatches]' ).each( function () {
			var select = this;
			$( select ).nextAll( '.cartlum-swatches' ).first().find( '.cartlum-swatch' ).each( function () {
				var value = this.getAttribute( 'data-value' );
				var option = $( select ).find( 'option' ).filter( function () {
					return this.value === value;
				} );
				this.disabled = ! option.length || option.prop( 'disabled' ) || ! inStock( form, select.name, value );
				this.setAttribute( 'aria-pressed', select.value === value ? 'true' : 'false' );
			} );
		} );
	}

	$( document ).on( 'click', '.cartlum-swatch', function () {
		var select = $( this ).closest( '.cartlum-swatches' ).prevAll( 'select[data-cartlum-swatches]' ).first();
		var value = this.getAttribute( 'data-value' );
		// A second tap on the chosen option clears it.
		select.val( select.val() === value ? '' : value ).trigger( 'change' );
	} );

	$( document ).on( 'woocommerce_update_variation_values reset_data change', '.variations_form', function () {
		sync( this );
	} );

	$( function () {
		$( '.variations_form' ).each( function () {
			sync( this );
		} );
	} );
}( jQuery ) );
