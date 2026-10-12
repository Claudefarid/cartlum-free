/**
 * Cartlum: "Order on WhatsApp" names what the customer actually chose. When the button is tapped,
 * the message is rebuilt with the selected colour/size, quantity and that variation's price.
 */
( function () {
	'use strict';

	function text( el ) {
		return el ? el.textContent.replace( /\s+/g, ' ' ).trim() : '';
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-cartlum-wa]' );
		if ( ! btn ) {
			return;
		}
		var form = document.querySelector( 'form.cart' );
		var options = [];
		var qty = '1';
		var price = btn.getAttribute( 'data-price' );

		if ( form ) {
			form.querySelectorAll( 'select[name^="attribute_"]' ).forEach( function ( select ) {
				if ( select.value ) {
					options.push( text( select.options[ select.selectedIndex ] ) );
				}
			} );
			// A colour picked with a shop's own swatches (a field named …colour… or …color…).
			form.querySelectorAll( 'input[type="hidden"], input[type="radio"]:checked, select:not([name^="attribute_"])' ).forEach( function ( field ) {
				if ( /colou?r/i.test( field.name || '' ) && field.value && options.indexOf( field.value ) === -1 ) {
					options.push( field.value );
				}
			} );
			var q = form.querySelector( 'input.qty' );
			if ( q && parseInt( q.value, 10 ) > 0 ) {
				qty = String( parseInt( q.value, 10 ) );
			}
			// The chosen variation's price, once WooCommerce shows it.
			var vprice = form.querySelector( '.woocommerce-variation-price .amount' );
			if ( vprice && text( vprice ) ) {
				price = text( vprice );
			}
		}

		var name = btn.getAttribute( 'data-name' ) + ( options.length ? ' (' + options.join( ', ' ) + ')' : '' );
		var message = btn.getAttribute( 'data-template' )
			.split( '{product}' ).join( name )
			.split( '{quantity}' ).join( qty )
			.split( '{price}' ).join( price )
			.split( '{link}' ).join( btn.getAttribute( 'data-link' ) );

		btn.href = 'https://wa.me/' + btn.getAttribute( 'data-cartlum-wa' ) + '?text=' + encodeURIComponent( message );
	} );
}() );
