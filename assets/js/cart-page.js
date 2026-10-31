/**
 * Cart page: saves the mobile number typed on the cart page (the checkout then has it filled in),
 * and keeps the free-delivery bar of the block cart and checkout up to date. The classic cart and
 * checkout redraw the bar themselves.
 */
( function () {
	var cfg = window.cartlumCartPage;
	if ( ! cfg ) {
		return;
	}

	// Mobile number. The classic cart redraws the field when quantities change, so look it up each time.
	var saved = '';
	var sending = '';
	var pending = null;

	function save( input ) {
		var value = input.value.trim();
		if ( ! value || value === saved || value === input.defaultValue ) {
			return null;
		}
		if ( pending && value === sending ) {
			return pending;
		}
		var box = input.closest( '.cartlum-cart-phone' );
		var note = box.querySelector( '.cartlum-cart-phone__note' );
		var body = new FormData();
		body.append( 'nonce', cfg.nonce );
		body.append( 'phone', value );
		sending = value;
		pending = fetch( cfg.url, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( r ) {
				var ok = r && r.success;
				if ( ok ) {
					saved = value;
				}
				note.textContent = ok ? cfg.saved : ( r && r.data && r.data.message ) || cfg.error;
				box.classList.toggle( 'is-error', ! ok );
			} )
			.catch( function () {
				note.textContent = cfg.error;
				box.classList.add( 'is-error' );
			} )
			.then( function () {
				pending = null;
			} );
		return pending;
	}

	document.addEventListener( 'change', function ( e ) {
		if ( e.target && 'cartlum-cart-phone' === e.target.id ) {
			save( e.target );
		}
	} );

	// On the way to the checkout, wait for the number (up to 2 seconds) so the checkout has it.
	document.addEventListener( 'click', function ( e ) {
		var link = e.target.closest && e.target.closest( 'a.checkout-button, a.wc-block-cart__submit-button' );
		var input = document.getElementById( 'cartlum-cart-phone' );
		var wait = link && link.href && input ? save( input ) : null;
		if ( ! wait ) {
			return;
		}
		e.preventDefault();
		var go = function () {
			window.location.href = link.href;
		};
		Promise.race( [ wait, new Promise( function ( r ) {
			setTimeout( r, 2000 );
		} ) ] ).then( go, go );
	} );

	// Free-delivery bar on the block cart and checkout, from the cart WooCommerce's blocks keep
	// (its cart data has camelCase keys, its totals snake_case ones).
	var bar = document.querySelector( '.cartlum-free-bar[data-cartlum-block]' );
	var data = window.wp && window.wp.data;
	if ( ! bar || ! cfg.amount || ! data || ! data.select ) {
		return;
	}

	function money( value, t ) {
		var decimals = Number( t.currency_minor_unit ) || 0;
		var parts = value.toFixed( decimals ).split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, t.currency_thousand_separator || '' );
		return ( t.currency_prefix || '' ) + parts.join( t.currency_decimal_separator || '.' ) + ( t.currency_suffix || '' );
	}

	var last = '';
	function draw() {
		var store = data.select( 'wc/store/cart' );
		var cart = store && store.getCartData ? store.getCartData() : null;
		var t = store && store.getCartTotals ? store.getCartTotals() : null;
		if ( ! cart || ! t || undefined === t.total_items ) {
			return;
		}
		var unit = Math.pow( 10, Number( t.currency_minor_unit ) || 0 );
		var total = ( Number( t.total_items ) - Number( t.total_discount ) + ( cfg.inclTax ? Number( t.total_items_tax ) - Number( t.total_discount_tax ) : 0 ) ) / unit;
		var left = cfg.amount - total;
		var key = [ total, cart.itemsCount, cart.needsShipping ].join( '|' );
		if ( key === last ) {
			return;
		}
		last = key;
		if ( ! cart.itemsCount || ! cart.needsShipping ) {
			bar.hidden = true;
			return;
		}
		if ( ! bar.firstChild ) {
			bar.innerHTML = '<p class="cartlum-free-bar__text"></p><span class="cartlum-free-bar__track" aria-hidden="true"><span class="cartlum-free-bar__fill"></span></span>';
		}
		bar.hidden = false;
		bar.classList.toggle( 'is-done', left <= 0 );
		bar.querySelector( '.cartlum-free-bar__text' ).textContent = left > 0 ? cfg.left.replace( '%s', money( left, t ) ) : cfg.done;
		bar.querySelector( '.cartlum-free-bar__fill' ).style.width = Math.min( 100, Math.max( 0, ( total / cfg.amount ) * 100 ) ) + '%';
	}

	data.subscribe( draw );
	draw();
}() );
