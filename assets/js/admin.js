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
	/* ---------- App: toast ---------- */

	var toastEl = document.querySelector( '[data-cartlum-toast]' );
	var toastTimer;
	function toast( text, isError ) {
		if ( ! toastEl ) {
			return;
		}
		toastEl.textContent = text;
		toastEl.classList.toggle( 'is-error', !! isError );
		toastEl.hidden = false;
		clearTimeout( toastTimer );
		toastTimer = setTimeout( function () {
			toastEl.hidden = true;
		}, isError ? 6000 : 2600 );
	}

	/* ---------- App: notices ---------- */

	// Cartlum's own message after an action becomes a toast; other plugins' notices fold into one
	// "Notices (n)" line so the screen stays calm (still one click away).
	var noticeBox = document.querySelector( '[data-cartlum-notices]' );
	if ( noticeBox ) {
		var flash = document.querySelector( '.cl-flash' );
		if ( flash ) {
			toast( ( flash.querySelector( 'p' ) || flash ).textContent.trim(), 'error' === flash.getAttribute( 'data-cartlum-flash' ) );
			flash.remove();
		}
		var others = Array.prototype.slice.call( document.querySelectorAll( '#wpbody-content > .notice, #wpbody-content > .error, #wpbody-content > .updated, #wpbody-content > .update-nag' ) );
		if ( others.length ) {
			var details = document.createElement( 'details' );
			var summary = document.createElement( 'summary' );
			summary.textContent = ( i18n.notices || 'Notices' ) + ' (' + others.length + ')';
			details.appendChild( summary );
			others.forEach( function ( n ) {
				details.appendChild( n );
			} );
			noticeBox.appendChild( details );
			noticeBox.hidden = false;
		}
	}

	/* ---------- Settings: unsaved changes, Save bar, saving in place ---------- */

	var form = document.querySelector( '[data-cartlum-settings]' );
	if ( form ) {
		var bar = form.querySelector( '[data-cartlum-savebar]' );
		var dirty = false;
		var setDirty = function ( on ) {
			dirty = on;
			if ( bar ) {
				bar.classList.toggle( 'is-dirty', on );
			}
		};
		form.addEventListener( 'input', function () {
			setDirty( true );
		} );
		form.addEventListener( 'change', function () {
			setDirty( true );
		} );
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = i18n.leave || '';
			}
		} );
		form.addEventListener( 'submit', function ( e ) {
			// Buttons with their own target (tests inside the form) post normally.
			if ( e.submitter && e.submitter.hasAttribute( 'formaction' ) ) {
				dirty = false;
				return;
			}
			if ( ! window.fetch || ! window.FormData || ! i18n.ajax ) {
				dirty = false;
				return;
			}
			e.preventDefault();
			var btn = form.querySelector( '[data-cartlum-savebar] [type="submit"]' );
			var label = btn ? btn.value : '';
			if ( btn ) {
				btn.disabled = true;
				btn.value = i18n.saving || 'Saving…';
			}
			fetch( i18n.ajax, { method: 'POST', body: new FormData( form ), credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( res ) {
					if ( ! res || ! res.data ) {
						throw new Error( 'bad' );
					}
					if ( ! res.success ) {
						toast( res.data.message || i18n.failed, true );
						return;
					}
					setDirty( false );
					if ( res.data.reload ) {
						window.location.href = res.data.reload;
						return;
					}
					form.querySelectorAll( 'input[type="password"]' ).forEach( function ( p ) {
						if ( p.value ) {
							p.value = '';
							p.placeholder = i18n.saved || '';
						}
					} );
					toast( res.data.message, 'error' === res.data.type );
				} )
				.catch( function () {
					toast( i18n.failed || 'Could not save.', true );
				} )
				.then( function () {
					if ( btn ) {
						btn.disabled = false;
						btn.value = label;
					}
				} );
		} );
	}

	/* ---------- Jump to a setting (#cl-row-key) ---------- */

	function showRow( id ) {
		var row = document.getElementById( id );
		if ( ! row ) {
			return;
		}
		var more = row.closest( 'details' );
		if ( more ) {
			more.open = true;
		}
		row.scrollIntoView( { block: 'center' } );
		row.classList.add( 'cl-row--flash' );
		var input = row.querySelector( 'input, select, textarea' );
		if ( input ) {
			input.focus( { preventScroll: true } );
		}
	}
	if ( /^#cl-row-[a-z0-9_]+$/.test( window.location.hash ) ) {
		showRow( window.location.hash.slice( 1 ) );
	}

	/* ---------- Tabs open instantly: fetch the next page on hover ---------- */

	var prefetched = {};
	document.addEventListener( 'mouseover', function ( e ) {
		var a = e.target.closest && e.target.closest( '.cl-nav a, .cl-rail a' );
		if ( ! a || prefetched[ a.href ] || a.getAttribute( 'aria-current' ) ) {
			return;
		}
		prefetched[ a.href ] = true;
		var link = document.createElement( 'link' );
		link.rel = 'prefetch';
		link.href = a.href;
		document.head.appendChild( link );
	} );

	/* ---------- Today: cards load after the page shows ---------- */

	var today = document.querySelector( '[data-cartlum-today]' );
	function loadToday( fresh ) {
		var data = new FormData();
		data.append( 'action', 'cartlum_today' );
		data.append( 'nonce', today.getAttribute( 'data-nonce' ) );
		if ( fresh ) {
			data.append( 'fresh', '1' );
		}
		fetch( i18n.ajax, { method: 'POST', body: data, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				if ( res && res.success ) {
					today.innerHTML = res.data.html;
				}
			} );
	}
	if ( today && window.fetch ) {
		loadToday( false );
		today.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-cartlum-today-refresh]' ) ) {
				loadToday( true );
			}
		} );
	}

	/* ---------- Find a setting (Ctrl+K) ---------- */

	var pal = document.querySelector( '[data-cartlum-palette]' );
	if ( pal ) {
		var palIn = pal.querySelector( '[data-cartlum-palette-input]' );
		var palList = pal.querySelector( '[data-cartlum-palette-list]' );
		var index = null;
		var hits = [];
		var sel = 0;
		var esc = function ( t ) {
			var d = document.createElement( 'div' );
			d.textContent = t;
			return d.innerHTML;
		};
		var draw = function () {
			if ( ! hits.length ) {
				palList.innerHTML = '<li class="cl-palette__none">' + esc( index ? ( i18n.noMatch || '' ) : '…' ) + '</li>';
				return;
			}
			palList.innerHTML = hits.map( function ( h, i ) {
				return '<li role="option" data-i="' + i + '" aria-selected="' + ( i === sel ) + '">' + esc( h[ 0 ] ) + '<small>' + esc( h[ 1 ] ) + '</small></li>';
			} ).join( '' );
		};
		var filter = function () {
			var q = palIn.value.trim().toLowerCase();
			var list = index || [];
			hits = q ? list.filter( function ( h ) {
				return ( h[ 0 ] + ' ' + h[ 1 ] + ' ' + ( h[ 3 ] || '' ) ).toLowerCase().indexOf( q ) !== -1;
			} ) : list.filter( function ( h ) {
				return 3 === h.length && h[ 2 ].indexOf( '&tab=' ) === -1;
			} );
			hits = hits.slice( 0, 9 );
			sel = 0;
			draw();
		};
		var open = function () {
			pal.hidden = false;
			palIn.value = '';
			palIn.focus();
			if ( index ) {
				filter();
				return;
			}
			draw();
			var data = new FormData();
			data.append( 'action', 'cartlum_search_index' );
			data.append( 'nonce', i18n.nonce );
			fetch( i18n.ajax, { method: 'POST', body: data, credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( res ) {
					index = res && res.success ? res.data : [];
					filter();
				} );
		};
		var close = function () {
			pal.hidden = true;
		};
		var pick = function ( i ) {
			var h = hits[ i ];
			if ( ! h ) {
				return;
			}
			close();
			var here = window.location.href.split( '#' )[ 0 ];
			var to = h[ 2 ].split( '#' );
			if ( to[ 0 ].replace( /&amp;/g, '&' ) === here && to[ 1 ] ) {
				showRow( to[ 1 ] );
				return;
			}
			window.location.href = h[ 2 ];
		};
		document.querySelectorAll( '[data-cartlum-search]' ).forEach( function ( b ) {
			b.addEventListener( 'click', open );
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && 'k' === e.key.toLowerCase() ) {
				e.preventDefault();
				open();
			} else if ( 'Escape' === e.key && ! pal.hidden ) {
				close();
			}
		} );
		palIn.addEventListener( 'input', filter );
		palIn.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowDown' === e.key ) {
				sel = Math.min( sel + 1, hits.length - 1 );
				draw();
				e.preventDefault();
			} else if ( 'ArrowUp' === e.key ) {
				sel = Math.max( sel - 1, 0 );
				draw();
				e.preventDefault();
			} else if ( 'Enter' === e.key ) {
				pick( sel );
				e.preventDefault();
			}
		} );
		palList.addEventListener( 'click', function ( e ) {
			var li = e.target.closest( 'li[data-i]' );
			if ( li ) {
				pick( +li.getAttribute( 'data-i' ) );
			}
		} );
		pal.addEventListener( 'click', function ( e ) {
			if ( e.target === pal ) {
				close();
			}
		} );
	}
}() );
