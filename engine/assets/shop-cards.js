/**
 * Product card extras (Pergel\Woo\Cards): the sale countdown, the quick
 * view window, the colour options and the pictures that follow the
 * pointer.
 *
 * The countdown boxes come with the page; this updates them every second,
 * also in products drawn later ("Load more", filters). The quick view
 * button opens a native <dialog> and fills it from /pergel/v1/quick-view;
 * Escape and the close button close it, and focus goes back to the button.
 */

const node = document.getElementById(
	'wp-script-module-data-pergel/shop-cards'
);
let config = {};
try {
	config = JSON.parse( node ? node.textContent : '{}' ) || {};
} catch {
	config = {};
}
const texts = config.texts || {};

/* Countdown. */

const SECONDS = { d: 86400, h: 3600, m: 60, s: 1 };
const LIMITS = { d: Infinity, h: 24, m: 60, s: 60 };

const tick = () => {
	const now = Date.now() / 1000;
	for ( const box of document.querySelectorAll( '[data-pergel-until]' ) ) {
		const left = Math.max( 0, Math.floor( Number( box.dataset.pergelUntil ) - now ) );
		for ( const part of box.querySelectorAll( '[data-pergel-part]' ) ) {
			const key = part.dataset.pergelPart;
			const value = Math.floor( left / SECONDS[ key ] ) % LIMITS[ key ];
			part.textContent = String( value ).padStart( 2, '0' );
		}
		box.classList.toggle( 'is-over', left === 0 );
	}
};

if ( document.querySelector( '[data-pergel-until]' ) ) {
	tick();
	window.setInterval( tick, 1000 );
}

/* Quick view. */

let dialog;
let body;
let opener;

const build = () => {
	dialog = document.createElement( 'dialog' );
	dialog.className = 'pergel-quick-dialog';
	const close = document.createElement( 'button' );
	close.type = 'button';
	close.className = 'pergel-quick-dialog__close';
	close.setAttribute( 'aria-label', texts.close || 'Close' );
	close.innerHTML =
		'<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>';
	close.addEventListener( 'click', () => dialog.close() );
	body = document.createElement( 'div' );
	body.className = 'pergel-quick-dialog__body';
	dialog.append( close, body );
	// A click on the backdrop closes the window.
	dialog.addEventListener( 'click', ( event ) => {
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );
	dialog.addEventListener( 'close', () => {
		body.textContent = '';
		if ( opener && opener.isConnected ) {
			opener.focus();
		}
	} );
	// Pictures: a thumbnail shows its picture in the big one.
	dialog.addEventListener( 'click', ( event ) => {
		const thumb =
			event.target instanceof Element &&
			event.target.closest( '.pergel-quick__thumb' );
		const image = thumb && dialog.querySelector( '.pergel-quick__image' );
		if ( ! image ) {
			return;
		}
		image.src = thumb.dataset.pergelSrc;
		image.srcset = thumb.dataset.pergelSrcset || '';
		image.alt = thumb.dataset.pergelAlt || '';
		for ( const other of dialog.querySelectorAll( '.pergel-quick__thumb' ) ) {
			other.setAttribute( 'aria-pressed', other === thumb ? 'true' : 'false' );
		}
	} );
	document.body.append( dialog );
};

const show = async ( button ) => {
	if ( ! config.quick ) {
		return;
	}
	if ( ! dialog ) {
		build();
	}
	opener = button;
	const card = button.closest( 'li' );
	const page = card && card.querySelector( 'a[href]' );
	dialog.removeAttribute( 'aria-labelledby' );
	dialog.setAttribute( 'aria-label', texts.loading || '' );
	body.innerHTML = '';
	const status = document.createElement( 'p' );
	status.className = 'pergel-quick-dialog__status';
	status.setAttribute( 'role', 'status' );
	status.textContent = texts.loading || '';
	body.append( status );
	dialog.showModal();
	try {
		const url = new URL( config.quick, window.location.href );
		url.searchParams.set( 'id', button.dataset.pergelQuick );
		const response = await fetch( url, {
			headers: { Accept: 'application/json' },
		} );
		if ( ! response.ok ) {
			throw new Error( String( response.status ) );
		}
		const answer = await response.json();
		if ( ! dialog.open || opener !== button ) {
			return;
		}
		body.innerHTML = answer.html;
		dialog.removeAttribute( 'aria-label' );
		dialog.setAttribute( 'aria-labelledby', 'pergel-quick-title' );
		const title = body.querySelector( '#pergel-quick-title' );
		if ( title ) {
			title.tabIndex = -1;
			title.focus();
		}
	} catch {
		status.textContent = texts.failed || '';
		if ( page ) {
			const link = document.createElement( 'a' );
			link.href = page.href;
			link.textContent = page.textContent.trim() || page.href;
			body.append( link );
		}
	}
};

/* Colours: an option's picture on the card; pressed again, the product's. */

/* The card's own picture, kept before an option or a gallery picture replaces it. */
const own = ( image ) => {
	if ( ! image.dataset.pergelOwnSrc ) {
		image.dataset.pergelOwnSrc = image.currentSrc || image.src;
		image.dataset.pergelOwnSrcset = image.getAttribute( 'srcset' ) || '';
	}
};

const swatch = ( button ) => {
	const card = button.closest( 'li' );
	const box = card && card.querySelector( '.wc-block-components-product-image' );
	const image = box && box.querySelector( 'img:not(.pergel-second-picture)' );
	if ( ! image ) {
		return;
	}
	own( image );
	const pressed = 'true' === button.getAttribute( 'aria-pressed' );
	for ( const other of card.querySelectorAll( '.pergel-card-swatch[aria-pressed]' ) ) {
		other.setAttribute( 'aria-pressed', 'false' );
	}
	image.srcset = pressed ? image.dataset.pergelOwnSrcset : button.dataset.pergelSrcset || '';
	image.src = pressed ? image.dataset.pergelOwnSrc : button.dataset.pergelSrc;
	button.setAttribute( 'aria-pressed', pressed ? 'false' : 'true' );
	box.classList.toggle( 'has-pergel-swatch', ! pressed );
};

document.addEventListener( 'click', ( event ) => {
	const target = event.target instanceof Element ? event.target : null;
	const button = target && target.closest( '[data-pergel-quick]' );
	if ( button ) {
		event.preventDefault();
		show( button );
		return;
	}
	const option = target && target.closest( '.pergel-card-swatch[data-pergel-src]' );
	if ( option ) {
		event.preventDefault();
		swatch( option );
	}
} );

/*
 * Pictures that follow the pointer: the picture is split into as many
 * strips as the product has pictures, and the one under the pointer shows.
 * Only with a mouse; nothing loads before the pointer is over a card. The
 * gallery pictures load then, and the marks under them are drawn once.
 */

let active = null;
let parts = null;
let shown = 0;
let rect = null;

const turn = ( index ) => {
	const image = active.querySelector( 'img:not(.pergel-second-picture)' );
	if ( ! image || index === shown ) {
		return;
	}
	own( image );
	const [ src, srcset ] = index > 0
		? parts[ index - 1 ]
		: [ image.dataset.pergelOwnSrc, image.dataset.pergelOwnSrcset ];
	image.srcset = srcset || '';
	image.src = src;
	shown = index;
	const marks = active.querySelector( '.pergel-gallery-marks' );
	if ( marks ) {
		for ( const [ at, mark ] of [ ...marks.children ].entries() ) {
			mark.classList.toggle( 'is-on', at === shown );
		}
	}
};

const leave = () => {
	if ( active ) {
		if ( ! active.classList.contains( 'has-pergel-swatch' ) ) {
			turn( 0 );
		}
		active.classList.remove( 'is-pergel-browsing' );
	}
	active = null;
	parts = null;
	shown = 0;
};

const enter = ( target ) => {
	try {
		parts = JSON.parse( target.dataset.pergelGallery || '[]' );
	} catch {
		parts = [];
	}
	if ( ! Array.isArray( parts ) || ! parts.length ) {
		return;
	}
	active = target;
	shown = 0;
	rect = active.getBoundingClientRect();
	if ( ! active.querySelector( '.pergel-gallery-marks' ) ) {
		const image = active.querySelector( 'img:not(.pergel-second-picture)' );
		for ( const [ src, srcset ] of parts ) {
			const next = new Image();
			next.decoding = 'async';
			if ( image && image.sizes ) {
				next.sizes = image.sizes;
			}
			next.srcset = srcset || '';
			next.src = src;
		}
		const marks = document.createElement( 'span' );
		marks.className = 'pergel-gallery-marks';
		marks.setAttribute( 'aria-hidden', 'true' );
		for ( let at = 0; at <= parts.length; at++ ) {
			marks.append( document.createElement( 'span' ) );
		}
		marks.firstChild.classList.add( 'is-on' );
		active.append( marks );
	}
	active.classList.add( 'is-pergel-browsing' );
};

if ( window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches ) {
	document.addEventListener(
		'pointermove',
		( event ) => {
			if ( 'mouse' !== event.pointerType ) {
				return;
			}
			const target =
				event.target instanceof Element &&
				event.target.closest( '[data-pergel-gallery]' );
			if ( target !== active ) {
				leave();
				if ( target ) {
					enter( target );
				}
			}
			if ( ! active || active.classList.contains( 'has-pergel-swatch' ) ) {
				return;
			}
			const across = ( event.clientX - rect.left ) / ( rect.width || 1 );
			const index = Math.min( parts.length, Math.max( 0, Math.floor( across * ( parts.length + 1 ) ) ) );
			turn( index );
		},
		{ passive: true }
	);
	document.addEventListener( 'pointerout', ( event ) => {
		if ( active && ! ( event.relatedTarget instanceof Node && active.contains( event.relatedTarget ) ) ) {
			leave();
		}
	} );
	// The picture may have moved (scrolling, a layout change) since the pointer came in.
	window.addEventListener( 'scroll', () => {
		if ( active ) {
			rect = active.getBoundingClientRect();
		}
	}, { passive: true } );
}
