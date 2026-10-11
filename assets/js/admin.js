/* Cartlum admin: tabs, insert chips, SMS length, copy, busy buttons, gateway fields. */
( function () {
	'use strict';

	var i18n = window.cartlumAdmin || {};

	/* ---------- Tabs (single cart) ---------- */

	function selectTab( tab ) {
		var name = tab.getAttribute( 'data-cartlum-tab' );
		document.querySelectorAll( '[data-cartlum-tab]' ).forEach( function ( b ) {
			var on = b === tab;
			b.classList.toggle( 'is-active', on );
			b.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			b.tabIndex = on ? 0 : -1;
		} );
		document.querySelectorAll( '[data-cartlum-pane]' ).forEach( function ( p ) {
			p.classList.toggle( 'is-active', p.getAttribute( 'data-cartlum-pane' ) === name );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var tab = e.target.closest( '[data-cartlum-tab]' );
		if ( tab ) {
			selectTab( tab );
		}
	} );

	// Arrow keys move between tabs, as in any tab list.
	document.addEventListener( 'keydown', function ( e ) {
		var tab = e.target.closest && e.target.closest( '[data-cartlum-tab]' );
		if ( ! tab || ( 'ArrowRight' !== e.key && 'ArrowLeft' !== e.key ) ) {
			return;
		}
		var tabs = Array.prototype.slice.call( document.querySelectorAll( '[data-cartlum-tab]' ) );
		var next = tabs[ ( tabs.indexOf( tab ) + ( 'ArrowRight' === e.key ? 1 : tabs.length - 1 ) ) % tabs.length ];
		selectTab( next );
		next.focus();
		e.preventDefault();
	} );

	document.querySelectorAll( '[data-cartlum-tab]' ).forEach( function ( b ) {
		b.tabIndex = b.classList.contains( 'is-active' ) ? 0 : -1;
	} );

	/* ---------- Insert chips ---------- */

	// The chip goes into the text box the user last worked in; failing that, the first text box in
	// the same form (cart screen) or on the page (settings).
	var lastField = null;
	document.addEventListener( 'focusin', function ( e ) {
		if ( e.target.matches && e.target.matches( 'textarea, input[type="text"]' ) && ! e.target.readOnly ) {
			lastField = e.target;
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		var chip = e.target.closest( '[data-cartlum-insert]' );
		if ( ! chip ) {
			return;
		}
		var scope = chip.closest( 'form' ) || document;
		var field = lastField && scope.contains( lastField ) ? lastField : scope.querySelector( 'textarea' );
		if ( ! field ) {
			return;
		}
		var tag = chip.getAttribute( 'data-cartlum-insert' );
		var start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
		var end = typeof field.selectionEnd === 'number' ? field.selectionEnd : start;
		var before = field.value.slice( 0, start );
		var spacer = before && ! /\s$/.test( before ) ? ' ' : '';
		field.value = before + spacer + tag + field.value.slice( end );
		var caret = start + spacer.length + tag.length;
		field.focus();
		field.setSelectionRange( caret, caret );
		field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	} );

	/* ---------- SMS length ---------- */

	// 160 characters per SMS in GSM-7, 70 once any character falls outside it (Bangla, emoji ...).
	// Placeholders count as typed, so the sent message can be longer; this is a guide.
	var GSM = /^[\u000A\u000D -~£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¤¡ÄÖÑÜ§¿äöñüà]*$/;
	document.querySelectorAll( '[data-cartlum-count]' ).forEach( function ( area ) {
		var out = area.closest( 'form' ).querySelector( '[data-cartlum-counter]' );
		if ( ! out ) {
			return;
		}
		var update = function () {
			var text = area.value;
			var unicode = ! GSM.test( text );
			var single = unicode ? 70 : 160;
			var multi = unicode ? 67 : 153;
			var parts = text.length <= single ? 1 : Math.ceil( text.length / multi );
			out.textContent = text.length + ' ' + ( i18n.chars || 'characters' ) + ' · ' + parts + ' SMS' + ( unicode ? ' (' + ( i18n.unicode || 'unicode' ) + ')' : '' );
		};
		area.addEventListener( 'input', update );
		update();
	} );

	/* ---------- Copy ---------- */

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-cartlum-copy]' );
		if ( ! btn ) {
			return;
		}
		var input = btn.parentNode.querySelector( 'input' );
		var done = function () {
			var label = btn.textContent;
			btn.textContent = i18n.copied || 'Copied';
			setTimeout( function () {
				btn.textContent = label;
			}, 1500 );
		};
		input.select();
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( input.value ).then( done );
		} else {
			document.execCommand( 'copy' );
			done();
		}
	} );

	/* ---------- Busy buttons ---------- */

	// Sending can take a few seconds while the SMS gateway answers. Lock the button so a second
	// tap cannot send the same message twice.
	document.querySelectorAll( 'form[data-cartlum-busy]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function () {
			var btn = form.querySelector( 'button:not([type="button"])' );
			if ( ! btn || btn.disabled ) {
				return;
			}
			btn.dataset.label = btn.textContent;
			btn.textContent = i18n.sending || 'Sending…';
			btn.setAttribute( 'aria-busy', 'true' );
			// Disable after the submit has been dispatched, or the browser drops the click.
			setTimeout( function () {
				btn.disabled = true;
			}, 0 );
		} );
	} );

	/* ---------- Settings: only the chosen gateway's fields ---------- */

	var select = document.querySelector( '[data-cartlum-gateway-select]' );
	if ( select ) {
		var toggle = function () {
			document.querySelectorAll( '[data-cartlum-gateway]' ).forEach( function ( row ) {
				row.style.display = row.getAttribute( 'data-cartlum-gateway' ) === select.value ? '' : 'none';
			} );
		};
		select.addEventListener( 'change', toggle );
		toggle();
	}
}() );
