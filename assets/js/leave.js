/**
 * Plugins page: "Why are you leaving Cartlum?" when Cartlum's Deactivate is clicked. Optional:
 * Skip deactivates at once and sends nothing; Send posts the chosen reason, then deactivates.
 */
( function () {
	'use strict';

	var s = window.cartlumLeave || {};
	var link = document.querySelector( 'tr[data-plugin="' + s.plugin + '"] .deactivate a' );
	if ( ! link || ! window.HTMLDialogElement ) {
		return;
	}

	function el( tag, attrs, text ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			node.setAttribute( k, attrs[ k ] );
		} );
		if ( text ) {
			node.textContent = text;
		}
		return node;
	}

	var dialog = el( 'dialog', { 'class': 'cartlum-leave', 'aria-labelledby': 'cartlum-leave-title' } );
	dialog.style.cssText = 'max-width:480px;width:calc(100% - 32px);border:1px solid #c3c4c7;border-radius:8px;padding:20px 24px';
	var form = el( 'form', { method: 'dialog' } );
	form.appendChild( el( 'h2', { id: 'cartlum-leave-title', style: 'margin-top:0' }, s.title ) );
	var list = el( 'fieldset', { style: 'border:0;margin:0;padding:0' } );
	Object.keys( s.reasons || {} ).forEach( function ( key ) {
		var label = el( 'label', { style: 'display:block;padding:6px 0' } );
		var input = el( 'input', { type: 'radio', name: 'reason', value: key } );
		label.appendChild( input );
		label.appendChild( document.createTextNode( ' ' + s.reasons[ key ] ) );
		list.appendChild( label );
	} );
	form.appendChild( list );
	var text = el( 'textarea', { name: 'text', rows: '3', maxlength: '500', style: 'width:100%;margin-top:8px', 'aria-label': s.more, placeholder: s.more } );
	form.appendChild( text );
	form.appendChild( el( 'p', { 'class': 'description' }, s.note ) );
	var buttons = el( 'p', { style: 'display:flex;gap:8px;justify-content:flex-end;margin-bottom:0' } );
	var skip = el( 'button', { type: 'button', 'class': 'button' }, s.skip );
	var send = el( 'button', { type: 'submit', 'class': 'button button-primary' }, s.send );
	buttons.appendChild( skip );
	buttons.appendChild( send );
	form.appendChild( buttons );
	dialog.appendChild( form );
	document.body.appendChild( dialog );

	function leave() {
		window.location.href = link.href;
	}
	link.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		dialog.showModal();
	} );
	skip.addEventListener( 'click', leave );
	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		var chosen = form.querySelector( 'input[name="reason"]:checked' );
		if ( ! chosen && ! text.value.trim() ) {
			leave();
			return;
		}
		send.disabled = true;
		skip.disabled = true;
		var body = new FormData();
		body.append( 'action', 'cartlum_leave_reason' );
		body.append( 'nonce', s.nonce );
		body.append( 'reason', chosen ? chosen.value : '' );
		body.append( 'text', text.value );
		var done = false;
		var go = function () {
			if ( ! done ) {
				done = true;
				leave();
			}
		};
		setTimeout( go, 6000 );
		fetch( s.ajax, { method: 'POST', body: body, credentials: 'same-origin' } ).then( go, go );
	} );
}() );
