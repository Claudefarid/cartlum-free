/**
 * Cartlum: SMS code for risky cash-on-delivery orders. When the checkout answers that the number
 * must be confirmed, show a code box under the order button, send the code, and place the order
 * again once the code is right. WooCommerce's own checkout script does the placing.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.cartlumCode;
	if ( ! $ || ! cfg ) {
		return;
	}

	var $body = $( document.body );
	var timer = null;
	var busy = false;

	function phone() {
		return $.trim( $( '#billing_phone' ).val() || '' );
	}

	function payment() {
		return $( 'input[name="payment_method"]:checked' ).val() || '';
	}

	function post( url, data ) {
		return $.ajax( {
			type: 'POST',
			url: url,
			data: $.extend( { nonce: cfg.nonce }, data ),
			dataType: 'json'
		} );
	}

	function box() {
		var $box = $( '.cartlum-otp' );
		if ( $box.length ) {
			return $box;
		}
		$box = $(
			'<div class="cartlum-otp" role="group">' +
				'<p class="cartlum-otp__title"></p>' +
				'<p class="cartlum-otp__msg" role="status" aria-live="polite"></p>' +
				'<label for="cartlum-otp-code"></label>' +
				'<input type="text" id="cartlum-otp-code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*">' +
				'<button type="button" class="cartlum-otp__go"></button>' +
				'<button type="button" class="cartlum-otp__resend" disabled></button>' +
			'</div>'
		);
		$box.find( '.cartlum-otp__title' ).text( cfg.title );
		$box.find( 'label' ).text( cfg.label );
		$box.find( '.cartlum-otp__go' ).text( cfg.confirm );
		// Under the order button, so it is where the customer is looking.
		var $anchor = $( '#place_order' ).closest( '.form-row, .place-order' );
		if ( $anchor.length ) {
			$anchor.after( $box );
		} else {
			$( 'form.checkout' ).append( $box );
		}
		return $box;
	}

	function say( text, isError ) {
		box().find( '.cartlum-otp__msg' ).text( text || '' ).toggleClass( 'is-error', !! isError );
	}

	function countdown() {
		var left = cfg.wait;
		var $btn = box().find( '.cartlum-otp__resend' );
		clearInterval( timer );
		$btn.prop( 'disabled', true ).text( cfg.resendIn.replace( '%d', left ) );
		timer = setInterval( function () {
			left--;
			if ( left <= 0 ) {
				clearInterval( timer );
				$btn.prop( 'disabled', false ).text( cfg.resend );
			} else {
				$btn.text( cfg.resendIn.replace( '%d', left ) );
			}
		}, 1000 );
	}

	function sendCode() {
		post( cfg.send, { phone: phone(), payment_method: payment() } )
			.done( function ( res ) {
				say( res && res.data && res.data.message, ! ( res && res.success ) );
				if ( res && res.success ) {
					countdown();
					box().find( 'input' ).val( '' ).trigger( 'focus' );
				}
			} )
			.fail( function () {
				say( cfg.network, true );
			} );
	}

	function verify() {
		var $box = box();
		var code = $.trim( $box.find( 'input' ).val() );
		if ( busy || code.length < 6 ) {
			return;
		}
		busy = true;
		$box.find( '.cartlum-otp__go' ).prop( 'disabled', true ).text( cfg.busy );
		post( cfg.verify, { phone: phone(), code: code } )
			.done( function ( res ) {
				if ( res && res.success ) {
					$box.remove();
					clearInterval( timer );
					$( '#place_order' ).show();
					$( 'form.checkout' ).trigger( 'submit' );
					return;
				}
				say( res && res.data && res.data.message, true );
				$box.find( 'input' ).trigger( 'select' );
			} )
			.fail( function () {
				say( cfg.network, true );
			} )
			.always( function () {
				busy = false;
				$box.find( '.cartlum-otp__go' ).prop( 'disabled', false ).text( cfg.confirm );
			} );
	}

	// The checkout answered: is it our "confirm your number" error?
	$body.on( 'checkout_error', function () {
		var $marker = $( '.cartlum-otp-required' );
		if ( ! $marker.length ) {
			return;
		}
		// The code box says it better than an error banner.
		$marker.closest( '.woocommerce-NoticeGroup, .woocommerce-error, .wc-block-components-notice-banner' ).remove();
		var $box = box();
		// One way forward: the code box's button places the order.
		$( '#place_order' ).hide();
		say( '' );
		sendCode();
		$( 'html, body' ).stop().animate( { scrollTop: $box.offset().top - 80 }, 200 );
	} );

	$( document ).on( 'click', '.cartlum-otp__go', verify );
	$( document ).on( 'click', '.cartlum-otp__resend', function () {
		if ( ! $( this ).prop( 'disabled' ) ) {
			sendCode();
		}
	} );
	$( document ).on( 'input', '#cartlum-otp-code', function () {
		this.value = this.value.replace( /[^0-9০-৯]/g, '' ).slice( 0, 6 );
		if ( 6 === this.value.length ) {
			verify();
		}
	} );
	$( document ).on( 'keydown', '#cartlum-otp-code', function ( e ) {
		// Enter confirms the code; it must not submit the order without it.
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			verify();
		}
	} );
	// A different number needs its own code.
	$( document ).on( 'change', '#billing_phone', function () {
		$( '.cartlum-otp' ).remove();
		$( '#place_order' ).show();
		clearInterval( timer );
	} );
}( window.jQuery ) );
