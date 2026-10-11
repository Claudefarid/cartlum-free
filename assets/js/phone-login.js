/**
 * Cartlum phone login: number -> "Send code" -> 6-digit code -> logged in.
 */
( function () {
	'use strict';

	var cfg = window.cartlumLogin;
	var form = document.querySelector( '.clx-login__form' );
	if ( ! cfg || ! form ) {
		return;
	}

	var phone = form.querySelector( '#clx-login-phone' );
	var code = form.querySelector( '#clx-login-code' );
	var codeField = form.querySelector( '[data-field="code"]' );
	var submit = form.querySelector( '.clx-login__submit' );
	var msg = form.querySelector( '.clx-login__msg' );
	var links = form.querySelector( '.clx-login__links' );
	var resend = form.querySelector( '[data-resend]' );
	var change = form.querySelector( '[data-change]' );
	var timer = null;
	var busy = false;

	function say( text, isError ) {
		msg.textContent = text || '';
		msg.classList.toggle( 'is-error', !! isError );
	}

	function post( url, data ) {
		var body = new URLSearchParams();
		body.append( 'nonce', cfg.nonce );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );
		return fetch( url, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) {
			return r.json();
		} );
	}

	function setBusy( on, label ) {
		busy = on;
		submit.disabled = on;
		submit.setAttribute( 'aria-busy', on ? 'true' : 'false' );
		submit.textContent = on ? cfg.busy : label;
	}

	function countdown() {
		var left = cfg.wait;
		clearInterval( timer );
		resend.disabled = true;
		resend.textContent = cfg.resendIn.replace( '%d', left );
		timer = setInterval( function () {
			left--;
			if ( left <= 0 ) {
				clearInterval( timer );
				resend.disabled = false;
				resend.textContent = cfg.resend;
			} else {
				resend.textContent = cfg.resendIn.replace( '%d', left );
			}
		}, 1000 );
	}

	function toCodeStep() {
		form.setAttribute( 'data-step', 'code' );
		phone.readOnly = true;
		codeField.hidden = false;
		links.hidden = false;
		code.value = '';
		code.focus();
		countdown();
	}

	function toPhoneStep() {
		form.setAttribute( 'data-step', 'phone' );
		phone.readOnly = false;
		codeField.hidden = true;
		links.hidden = true;
		clearInterval( timer );
		submit.textContent = cfg.sendText;
		say( '' );
		phone.focus();
	}

	function sendCode( label ) {
		setBusy( true, label );
		post( cfg.send, { phone: phone.value } )
			.then( function ( res ) {
				say( res.data && res.data.message, ! res.success );
				if ( res.success ) {
					toCodeStep();
				}
			} )
			.catch( function () {
				say( cfg.network, true );
			} )
			.then( function () {
				setBusy( false, 'code' === form.getAttribute( 'data-step' ) ? cfg.login : cfg.sendText );
			} );
	}

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		if ( busy ) {
			return;
		}
		if ( 'phone' === form.getAttribute( 'data-step' ) ) {
			sendCode( cfg.sendText );
			return;
		}
		setBusy( true, cfg.login );
		post( cfg.verify, { phone: phone.value, code: code.value } )
			.then( function ( res ) {
				if ( res.success && res.data && res.data.redirect ) {
					window.location.href = res.data.redirect;
					return;
				}
				say( res.data && res.data.message, true );
				setBusy( false, cfg.login );
				code.select();
			} )
			.catch( function () {
				say( cfg.network, true );
				setBusy( false, cfg.login );
			} );
	} );

	// Six digits typed or pasted (or filled in from the SMS): log in straight away.
	code.addEventListener( 'input', function () {
		code.value = code.value.replace( /[^0-9০-৯]/g, '' ).slice( 0, 6 );
		if ( 6 === code.value.length && ! busy ) {
			form.requestSubmit ? form.requestSubmit() : submit.click();
		}
	} );

	resend.addEventListener( 'click', function () {
		if ( ! resend.disabled && ! busy ) {
			sendCode( cfg.login );
		}
	} );

	change.addEventListener( 'click', toPhoneStep );
}() );
