/**
 * Back-in-stock alert (Pergel\Woo\StockAlert): sends the form without
 * leaving the page, and for a product with options shows it while a
 * sold-out option is chosen.
 */
const block = document.querySelector( '[data-pergel-stock]' );

if ( block ) {
	let config = null;
	try {
		config = JSON.parse( block.dataset.pergelStock );
	} catch {
		// The form still works without the script.
	}
	const form = block.querySelector( 'form' );
	if ( config && form ) {
		const status = form.querySelector( '.wp-block-pergel-stock-alert__status' );
		const product = form.querySelector( 'input[name="product"]' );
		const button = form.querySelector( 'button[type="submit"]' );
		const notice = block.querySelector( '.wp-block-pergel-stock-alert__notice' );

		const showErrors = ( errors = {} ) => {
			[ 'email', 'consent' ].forEach( ( name ) => {
				const input = form.querySelector( `[name="${ name }"]` );
				const slot = form.querySelector( `#${ input.id }-error` );
				const text = errors[ name ] || '';
				slot.textContent = text;
				slot.hidden = '' === text;
				if ( text ) {
					input.setAttribute( 'aria-invalid', 'true' );
				} else {
					input.removeAttribute( 'aria-invalid' );
				}
			} );
		};
		const say = ( text, failed ) => {
			status.textContent = text;
			status.classList.toggle( 'is-error', failed );
		};
		const reset = () => {
			form.classList.remove( 'is-done' );
			showErrors();
			say( '', false );
		};

		form.addEventListener( 'submit', async ( event ) => {
			event.preventDefault();
			if ( 'true' === button.getAttribute( 'aria-disabled' ) ) {
				return;
			}
			button.setAttribute( 'aria-disabled', 'true' );
			const headers = { Accept: 'application/json' };
			if ( config.nonce ) {
				headers[ 'X-WP-Nonce' ] = config.nonce;
			}
			try {
				const response = await window.fetch( config.endpoint, { method: 'POST', body: new window.FormData( form ), headers, credentials: 'same-origin' } );
				const data = await response.json();
				showErrors( data.errors || {} );
				say( data.message || config.offline, ! data.ok );
				if ( data.ok ) {
					form.classList.add( 'is-done' );
				} else {
					const invalid = form.querySelector( '[aria-invalid="true"]' );
					if ( invalid ) {
						invalid.focus();
					}
				}
			} catch {
				say( config.offline, true );
			} finally {
				button.removeAttribute( 'aria-disabled' );
			}
		} );

		// A product with options: the form for the chosen option, while it is sold out.
		if ( config.variations.length ) {
			const cart = [ ...document.querySelectorAll( 'form.wp-block-woocommerce-add-to-cart-with-options' ) ]
				.filter( ( f ) => f.compareDocumentPosition( block ) & Node.DOCUMENT_POSITION_FOLLOWING )
				.pop();
			const chosen = () => {
				if ( ! cart ) {
					return [];
				}
				const radios = [ ...cart.querySelectorAll( '[role="radio"][aria-checked="true"]' ) ].map( ( r ) => r.value );
				const selects = [ ...cart.querySelectorAll( 'select' ) ].map( ( s ) => s.value ).filter( Boolean );
				return [ ...radios, ...selects ];
			};
			const update = () => {
				const values = chosen();
				// The most specific match, so an "Any" option does not hide a closer one.
				const match = values.length
					? config.variations
						.filter( ( v ) => v.values.every( ( value ) => values.includes( value ) ) )
						.sort( ( a, b ) => b.values.length - a.values.length )[ 0 ]
					: null;
				if ( match && product.value !== String( match.id ) ) {
					product.value = String( match.id );
					reset();
				}
				// A result from an email link stays in view.
				block.hidden = ! match && ! notice;
				form.hidden = ! match;
			};
			if ( cart ) {
				new window.MutationObserver( update ).observe( cart, { attributes: true, attributeFilter: [ 'aria-checked', 'class' ], subtree: true } );
				cart.addEventListener( 'change', update );
			}
			update();
		}
	}
}
