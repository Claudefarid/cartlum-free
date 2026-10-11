/**
 * Save-cart popup. Opens once when a shopper with items in the cart is about to leave (mouse
 * heading out of the window on computers, a quick scroll back up on phones) and/or right after
 * "Add to cart". After "No thanks" it stays away for 3 days; after a number is saved, for 30.
 */
( function () {
	var cfg = window.cartlumPopup;
	var box = document.getElementById( 'cartlum-popup' );
	if ( ! cfg || ! box ) {
		return;
	}
	var KEY = 'cartlumPopupUntil';
	var form = box.querySelector( '.clx-popup__form' );
	var done = box.querySelector( '.clx-popup__done' );
	var input = box.querySelector( '.clx-popup__input' );
	var error = box.querySelector( '.clx-popup__error' );
	var opened = false;
	var lastFocus = null;

	function store( k, v ) {
		try {
			if ( undefined === v ) {
				return window.localStorage.getItem( k );
			}
			window.localStorage.setItem( k, v );
		} catch ( e ) {}
		return null;
	}

	function snoozed() {
		return Number( store( KEY ) || 0 ) > Date.now();
	}

	function snooze( days ) {
		store( KEY, String( Date.now() + days * 864e5 ) );
	}

	function hasCart() {
		return /(?:^|;\s*)woocommerce_items_in_cart=1/.test( document.cookie );
	}

	function open() {
		if ( opened || snoozed() || ! hasCart() ) {
			return;
		}
		opened = true;
		lastFocus = document.activeElement;
		box.hidden = false;
		document.documentElement.classList.add( 'clx-popup-open' );
		setTimeout( function () {
			input.focus();
		}, 50 );
	}

	function close() {
		if ( box.hidden ) {
			return;
		}
		if ( ! form.hidden ) {
			snooze( 3 );
		}
		box.hidden = true;
		document.documentElement.classList.remove( 'clx-popup-open' );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	box.addEventListener( 'click', function ( e ) {
		if ( e.target.closest( '[data-cartlum-close]' ) ) {
			close();
		}
	} );
	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			close();
		}
	} );

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();
		var button = form.querySelector( '.clx-popup__button' );
		error.textContent = '';
		button.disabled = true;
		var body = new FormData( form );
		fetch( cfg.url, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( r ) {
				if ( r && r.success ) {
					snooze( 30 );
					form.hidden = true;
					done.hidden = false;
					done.querySelector( 'button' ).focus();
				} else {
					error.textContent = ( r && r.data && r.data.message ) || cfg.error;
					input.focus();
				}
			} )
			.catch( function () {
				error.textContent = cfg.error;
			} )
			.then( function () {
				button.disabled = false;
			} );
	} );

	var onAdd = 'exit' !== cfg.trigger;
	var onExit = 'add_to_cart' !== cfg.trigger;

	if ( onAdd ) {
		if ( cfg.justAdded ) {
			setTimeout( open, 800 );
		}
		// Shop pages that add to the cart without reloading: classic themes fire WooCommerce's
		// jQuery event, block themes a native one.
		var later = function () {
			setTimeout( open, 800 );
		};
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'added_to_cart', later );
		}
		document.body.addEventListener( 'wc-blocks_added_to_cart', later );
	}

	if ( onExit ) {
		// Give the shopper a moment on the page first.
		setTimeout( function () {
			document.addEventListener( 'mouseout', function ( e ) {
				if ( ! e.relatedTarget && e.clientY <= 0 ) {
					open();
				}
			} );
			var maxY = window.scrollY;
			var lastY = window.scrollY;
			var lastT = Date.now();
			window.addEventListener(
				'scroll',
				function () {
					var y = window.scrollY;
					var t = Date.now();
					maxY = Math.max( maxY, y );
					// Fast flick back up after reading down the page: often heading for the address bar.
					if ( maxY > 600 && lastY - y > 250 && t - lastT < 300 && y < 300 ) {
						open();
					}
					if ( t - lastT > 300 ) {
						lastY = y;
						lastT = t;
					}
				},
				{ passive: true }
			);
		}, 5000 );
	}
} )();
