/**
 * Cartlum phone-first checkout: labels inside the fields, the folding order summary on phones,
 * the discount code box, and error messages placed above the form.
 *
 * WooCommerce's own checkout script does the real work (totals, delivery charge, placing the
 * order); this only adds to it.
 */
( function ( $ ) {
	'use strict';

	if ( ! $ ) {
		return;
	}

	/* ---------- Order summary on phones (checkout and thank-you page) ---------- */

	$( document ).on( 'click', '.clx-summary__toggle', function () {
		var $summary = $( this ).closest( '.clx-summary' );
		var open = ! $summary.hasClass( 'is-open' );
		var $label = $( this ).find( '[data-show]' );
		$summary.toggleClass( 'is-open', open );
		$( this ).attr( 'aria-expanded', open ? 'true' : 'false' );
		$label.text( open ? $label.data( 'hide' ) : $label.data( 'show' ) );
	} );

	// The rest is for the checkout form only.
	if ( typeof wc_checkout_params === 'undefined' ) {
		return;
	}

	var $body = $( document.body );
	var FIELD = 'input.input-text, textarea, select';

	/* ---------- Labels inside the fields ---------- */

	// A label moves up once its field has focus or a value. Selects always have a value.
	function sync( row ) {
		var $row = $( row );
		var $field = $row.find( FIELD ).first();
		if ( ! $field.length ) {
			$row.addClass( 'clx-no-float' );
			return;
		}
		var filled = $field.is( 'select' ) || String( $field.val() || '' ).length > 0;
		var focused = $row.find( ':focus' ).length > 0;
		$row.toggleClass( 'clx-float', filled || focused );
	}

	function syncAll() {
		$( 'form.clx .form-row' ).each( function () {
			sync( this );
		} );
	}

	$( document ).on( 'input change focusin focusout', 'form.clx .form-row', function () {
		var row = this;
		setTimeout( function () {
			sync( row );
		}, 0 );
	} );

	syncAll();
	// Browser autofill fills fields without events.
	setTimeout( syncAll, 400 );
	setTimeout( syncAll, 1500 );
	$body.on( 'updated_checkout country_to_state_changed', syncAll );

	/* ---------- Discount code ---------- */

	// The box sits inside the summary, which WooCommerce rebuilds after every change, so the
	// typed code and the last message are kept here and put back.
	var coupon = { code: '', msg: '', ok: false };

	function restoreCoupon() {
		var $input = $( '#clx-coupon-code' );
		if ( ! $input.length ) {
			return;
		}
		$input.val( coupon.code );
		$( '.clx-coupon__apply' ).prop( 'disabled', ! $.trim( coupon.code ) );
		$( '.clx-coupon__msg' )
			.text( coupon.msg )
			.toggleClass( 'is-ok', coupon.ok && !! coupon.msg )
			.toggleClass( 'is-error', ! coupon.ok && !! coupon.msg );
		sync( $input.closest( '.form-row' )[ 0 ] );
	}

	$( document ).on( 'input', '#clx-coupon-code', function () {
		coupon.code = this.value;
		$( '.clx-coupon__apply' ).prop( 'disabled', ! $.trim( this.value ) );
	} );

	// Enter in the code box applies the code; it must not submit the order.
	$( document ).on( 'keydown', '#clx-coupon-code', function ( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault();
			$( '.clx-coupon__apply' ).trigger( 'click' );
		}
	} );

	$( document ).on( 'click', '.clx-coupon__apply', function () {
		var code = $.trim( $( '#clx-coupon-code' ).val() );
		var $btn = $( this );
		if ( ! code || $btn.attr( 'aria-busy' ) ) {
			return;
		}
		$btn.prop( 'disabled', true ).attr( 'aria-busy', 'true' );

		$.ajax( {
			type: 'POST',
			url: wc_checkout_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'apply_coupon' ),
			data: {
				security: wc_checkout_params.apply_coupon_nonce,
				coupon_code: code,
				billing_email: $( '#billing_email' ).val() || ''
			},
			dataType: 'html'
		} )
			.done( function ( html ) {
				var $msg = $( '<div>' ).html( html );
				coupon.ok = ! $msg.find( '.woocommerce-error, .is-error' ).length;
				coupon.msg = $.trim( $msg.text().replace( /\s+/g, ' ' ) );
				if ( coupon.ok ) {
					coupon.code = '';
				}
				$body.trigger( 'applied_coupon_in_checkout', [ code ] );
				$body.trigger( 'update_checkout', { update_shipping_method: false } );
			} )
			.fail( function () {
				coupon.ok = false;
				coupon.msg = ( window.cartlumCheckout && window.cartlumCheckout.couponError ) || 'The code could not be checked. Please try again.';
			} )
			.always( function () {
				$btn.removeAttr( 'aria-busy' );
				restoreCoupon();
			} );
	} );

	$body.on( 'updated_checkout', restoreCoupon );
	$body.on( 'removed_coupon_in_checkout', function () {
		coupon.msg = '';
		coupon.ok = false;
	} );

	/* ---------- Errors ---------- */

	// WooCommerce puts errors at the top of the form, above both columns; move them above the fields.
	$body.on( 'checkout_error', function () {
		var $notices = $( 'form.clx > .woocommerce-NoticeGroup-checkout' );
		var $target = $( 'form.clx .clx-col-main > .clx-inner' ).first();
		if ( ! $notices.length || ! $target.length ) {
			return;
		}
		$target.prepend( $notices );
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		$( 'html, body' ).stop().animate( { scrollTop: $notices.offset().top - 16 }, reduce ? 0 : 250 );
	} );

	/* ---------- Place order in reach on phones ---------- */

	// On a phone the real button sits far below the last field. While it is off screen, a bar at the
	// bottom shows the same label (with the total) and presses the real button.
	( function () {
		if ( ! window.matchMedia || ! document.querySelector( 'form.clx' ) ) {
			return;
		}
		var mq = window.matchMedia( '(max-width: 768px)' );
		var $bar = $( '<div class="clx-sticky-order" hidden><button type="button" class="clx-sticky-order__btn"></button></div>' ).appendTo( 'body' );
		var $btn = $bar.find( 'button' );
		function real() {
			return document.querySelector( 'form.clx #place_order' );
		}
		function sync() {
			var el = real();
			var show = false;
			if ( el && mq.matches ) {
				var r = el.getBoundingClientRect();
				show = r.top > window.innerHeight || r.bottom < 0;
				$btn.text( $.trim( $( el ).text() ) || $.trim( el.value || '' ) );
			}
			$bar.prop( 'hidden', ! show );
			$body.toggleClass( 'clx-has-sticky-order', show );
		}
		$( window ).on( 'scroll resize', sync );
		$body.on( 'updated_checkout checkout_error', sync );
		$btn.on( 'click', function () {
			var el = real();
			if ( el ) {
				$btn.prop( 'disabled', true );
				el.click();
				setTimeout( function () {
					$btn.prop( 'disabled', false );
				}, 3000 );
			}
		} );
		sync();
	}() );
}( window.jQuery ) );
