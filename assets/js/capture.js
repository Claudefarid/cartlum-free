/**
 * Cartlum: saves the cart while the customer is still on the checkout page.
 *
 * Works on the classic checkout (#billing_phone ...) and the block checkout (#billing-phone,
 * #shipping-phone, #email ...). Sends only when a usable phone or email is present, waits until
 * typing pauses, and never sends the same details twice.
 */
( function () {
	'use strict';

	var cfg = window.cartlumCapture;
	if ( ! cfg || ! cfg.url ) {
		return;
	}

	var FIELDS = {
		phone: [ '#billing_phone', '#billing-phone', '#shipping-phone', '#shipping_phone' ],
		email: [ '#billing_email', '#email', '#billing-email' ],
		first_name: [ '#billing_first_name', '#billing-first_name', '#shipping-first_name' ],
		last_name: [ '#billing_last_name', '#billing-last_name', '#shipping-last_name' ]
	};
	var ALL = [].concat( FIELDS.phone, FIELDS.email, FIELDS.first_name, FIELDS.last_name ).join( ',' );

	var timer = null;
	var lastSent = '';
	var pending = null;

	function value( selectors ) {
		for ( var i = 0; i < selectors.length; i++ ) {
			var el = document.querySelector( selectors[ i ] );
			if ( el && el.value && el.value.trim() ) {
				return el.value.trim();
			}
		}
		return '';
	}

	// Bangla, Arabic-Indic and Devanagari digits count as digits: many customers type their number
	// on their phone's native keyboard.
	function asciiDigits( text ) {
		return text.replace( /[০-৯٠-٩۰-۹०-९]/g, function ( c ) {
			var code = c.charCodeAt( 0 );
			return String( code - ( code >= 0x09E6 ? 0x09E6 : code >= 0x0966 ? 0x0966 : code >= 0x06F0 ? 0x06F0 : 0x0660 ) );
		} );
	}

	function collect() {
		var data = {
			phone: asciiDigits( value( FIELDS.phone ) ),
			email: value( FIELDS.email ),
			first_name: value( FIELDS.first_name ),
			last_name: value( FIELDS.last_name ),
			block: document.querySelector( '.wc-block-checkout' ) ? '1' : ''
		};
		// A phone needs at least 8 digits, an email an @ and a dot after it. Anything less is
		// still being typed.
		var phoneOk = data.phone.replace( /\D/g, '' ).length >= 8;
		var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( data.email );
		if ( ! phoneOk ) {
			data.phone = '';
		}
		if ( ! emailOk ) {
			data.email = '';
		}
		return ( phoneOk || emailOk ) ? data : null;
	}

	function body( data ) {
		var params = new URLSearchParams();
		params.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( k ) {
			params.append( k, data[ k ] );
		} );
		return params;
	}

	function send() {
		timer = null;
		var data = collect();
		if ( ! data ) {
			return;
		}
		var key = JSON.stringify( data );
		if ( key === lastSent ) {
			return;
		}
		lastSent = key;
		pending = null;
		try {
			fetch( cfg.url, { method: 'POST', credentials: 'same-origin', keepalive: true, body: body( data ) } );
		} catch ( e ) {
			// Capture is best-effort; it must never get in the way of checking out.
		}
	}

	function schedule() {
		pending = true;
		if ( timer ) {
			clearTimeout( timer );
		}
		timer = setTimeout( send, 1200 );
	}

	function relevant( target ) {
		return target && target.matches && target.matches( ALL );
	}

	document.addEventListener( 'input', function ( e ) {
		if ( relevant( e.target ) ) {
			schedule();
		}
	}, true );

	document.addEventListener( 'change', function ( e ) {
		if ( relevant( e.target ) ) {
			schedule();
		}
	}, true );

	// Leaving the page mid-typing: send what we have.
	window.addEventListener( 'pagehide', function () {
		if ( pending ) {
			send();
		}
	} );

	// Returning customers and browser autofill: fields may already be filled when the page loads.
	setTimeout( send, 2500 );

	// The block checkout renders its fields with JavaScript, so add the privacy notice once the
	// phone field exists. The classic checkout prints it from PHP.
	if ( cfg.notice ) {
		var tries = 0;
		var noticeTimer = setInterval( function () {
			tries++;
			if ( document.querySelector( '.cartlum-notice' ) || tries > 40 ) {
				clearInterval( noticeTimer );
				return;
			}
			var phone = document.querySelector( '.wc-block-checkout #billing-phone, .wc-block-checkout #shipping-phone' );
			if ( ! phone ) {
				return;
			}
			var p = document.createElement( 'p' );
			p.className = 'cartlum-notice';
			p.style.cssText = 'font-size:12px;opacity:.75;margin:6px 0 0;';
			p.textContent = cfg.notice;
			var holder = phone.closest( '.wc-block-components-text-input' ) || phone.parentNode;
			holder.parentNode.insertBefore( p, holder.nextSibling );
			clearInterval( noticeTimer );
		}, 250 );
	}
}() );
