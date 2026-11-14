/**
 * Product listing extras (Pergel\Woo\Listing): list and grid buttons, the
 * products per page menu, and loading more products on the same page.
 *
 * The view is a data attribute on <html>, so WooCommerce redrawing the
 * products keeps it; an inline script in the head sets it from the
 * visitor's last choice before the page draws. "Load more" asks the
 * Interactivity router for this page with more products per page, so the
 * new products come with working buttons; when that is not possible it
 * follows its link to the next page.
 */

const STORE = 'pergel-shop-view';
const root = document.documentElement;

/* List and grid buttons. The view is "list", "grid" (the products per row
 * of the page) or "grid-N" (N per row on wide screens; shop-layout.css
 * applies it from 1024px, and narrower screens show one grid button). */

const setView = ( view ) => {
	root.dataset.pergelView = view;
	for ( const button of document.querySelectorAll(
		'.pergel-view-button'
	) ) {
		const own = button.dataset.pergelView;
		button.setAttribute(
			'aria-pressed',
			own === view || ( own === 'grid' && view.startsWith( 'grid-' ) )
				? 'true'
				: 'false'
		);
	}
};

// The view to start with: the visitor's last one while its button is
// there, else the page's. A plain grid presses the button of the number
// of products a row shows now.
const firstView = ( group ) => {
	const has = ( view ) =>
		!! group.querySelector(
			`.pergel-view-button[data-pergel-view="${ CSS.escape( view ) }"]`
		);
	let view = root.dataset.pergelView;
	if ( ! view || ! has( view ) ) {
		view = group.dataset.pergelDefault || 'grid';
	}
	if ( view === 'grid' ) {
		const grid = document.querySelector(
			'.pergel-shop-grid > .wc-block-product-template'
		);
		const count = grid
			? getComputedStyle( grid ).gridTemplateColumns.split( ' ' ).length
			: 0;
		if ( has( `grid-${ count }` ) ) {
			view = `grid-${ count }`;
		}
	}
	return view;
};

for ( const group of document.querySelectorAll( '.pergel-view-switch' ) ) {
	group.hidden = false;
	setView( firstView( group ) );
	group.addEventListener( 'click', ( event ) => {
		const button =
			event.target instanceof Element &&
			event.target.closest( '.pergel-view-button' );
		if ( ! button ) {
			return;
		}
		setView( button.dataset.pergelView );
		try {
			window.localStorage.setItem( STORE, button.dataset.pergelView );
		} catch {
			// Storage may be blocked; the view then lasts for this page.
		}
	} );
}

/* Products per page: the form sends itself, like the sorting menu. */

for ( const select of document.querySelectorAll( '.pergel-per-page select' ) ) {
	select.addEventListener( 'change', () => select.form.requestSubmit() );
}

/* Load more. */

let busy = false;
let status;

// Says what happens, outside the region the router redraws.
const say = ( text ) => {
	if ( ! status ) {
		status = document.createElement( 'div' );
		status.className = 'screen-reader-text';
		status.setAttribute( 'role', 'status' );
		document.body.append( status );
	}
	status.textContent = text;
};

// Products of the grid the button belongs to, found again after a redraw.
const items = ( region ) =>
	document.querySelectorAll(
		`[data-wp-router-region="${ CSS.escape( region ) }"] .wc-block-product-template > li`
	);

// The first product after `before`, once it is on the page; rejects after
// ten seconds.
const arrival = ( region, before ) =>
	new Promise( ( resolve, reject ) => {
		const done = () => {
			const item = items( region )[ before ];
			if ( item ) {
				watcher.disconnect();
				clearTimeout( timer );
				resolve( item );
			}
		};
		const watcher = new MutationObserver( done );
		const timer = setTimeout( () => {
			watcher.disconnect();
			reject( new Error( 'timeout' ) );
		}, 10000 );
		watcher.observe( document.body, { childList: true, subtree: true } );
	} );

// What loading more asks for: this page with `step` more products per
// page. `url` is empty when the router cannot load them in place.
const next = ( link ) => {
	const holder = link.closest( '[data-wp-router-region]' );
	const region = holder ? holder.dataset.wpRouterRegion : '';
	const step = Number( link.dataset.pergelStep );
	const before = region ? items( region ).length : 0;
	const wanted = before + step;
	if ( ! region || ! step || wanted > Number( link.dataset.pergelMost ) ) {
		return { region, before, url: '' };
	}
	const url = new URL( window.location.href );
	url.pathname = url.pathname.replace( /\/page\/\d+\/?$/, '/' );
	url.searchParams.delete( 'paged' );
	url.searchParams.set( 'per_page', String( wanted ) );
	return { region, before, url: url.toString() };
};

// Fetches the next products ahead of time, so they are ready when the
// visitor reaches the end of the list: the router keeps the page and
// navigate() draws it without asking the server again.
const prefetch = async ( link ) => {
	const { url } = next( link );
	if ( ! url ) {
		return;
	}
	try {
		const { actions } = await import( '@wordpress/interactivity-router' );
		Promise.resolve( actions.prefetch( url ) ).catch( () => {} );
	} catch {
		// Loading more falls back to the link.
	}
};

const more = async ( link, auto = false ) => {
	if ( busy ) {
		return;
	}
	const { region, before, url } = next( link );
	if ( ! url ) {
		// Scrolling never leaves the page by itself.
		if ( ! auto ) {
			window.location.assign( link.href );
		}
		return;
	}
	busy = true;
	root.classList.add( 'pergel-more-busy' );
	// The page stays where it is and the new products come in below. With
	// scroll anchoring the browser would keep a footer in view in place and
	// push the page down past the new products.
	document.body.style.overflowAnchor = 'none';
	say( link.dataset.pergelBusy || '' );
	let first;
	try {
		const { actions } = await import( '@wordpress/interactivity-router' );
		// The router draws the new page, but the promise of navigate() does
		// not settle when it is called outside a directive: wait for the
		// new products instead.
		const drawn = arrival( region, before );
		Promise.resolve( actions.navigate( url ) ).catch( () => {} );
		first = await drawn;
	} catch {
		window.location.assign( link.href );
		return;
	} finally {
		busy = false;
		root.classList.remove( 'pergel-more-busy' );
		// Anchoring comes back once the new products have been laid out.
		requestAnimationFrame( () =>
			requestAnimationFrame( () =>
				document.body.style.removeProperty( 'overflow-anchor' )
			)
		);
	}
	say( '' );
	// A press of the button moves focus to the first new product, so a
	// keyboard user goes on from there. Scrolling leaves focus where it is:
	// moved there, it would draw a focus ring around a product picture.
	const target = ! auto && first && first.querySelector( 'a' );
	if ( target ) {
		target.focus( { preventScroll: true } );
	}
	watch();
};

document.addEventListener( 'click', ( event ) => {
	const link =
		event.target instanceof Element &&
		event.target.closest( '[data-pergel-more]' );
	if (
		! link ||
		event.defaultPrevented ||
		event.button !== 0 ||
		event.metaKey ||
		event.ctrlKey ||
		event.shiftKey ||
		event.altKey
	) {
		return;
	}
	event.preventDefault();
	more( link );
} );

/* Load while scrolling: the button loads when it nears the screen, and
 * stays for keyboard users. A button already above the screen loads too: a
 * quick scroll during a load can end in a footer taller than the screen,
 * and the new button, drawn above it, would never come into view.
 *
 * Before the first scroll "near" is 400px, so a tall screen does not load
 * by itself. Once the visitor scrolls, the button loads a screen and a half
 * away, and the next products are fetched while it is still four screens
 * away, so the list grows without a wait at its end. */

let observer;
let ahead;
let scrolled = false;

// Calls `then` when the link is within `margin` below the screen, or above it.
const near = ( link, margin, then ) => {
	const seen = new IntersectionObserver(
		( entries ) => {
			if (
				entries.some(
					( entry ) =>
						entry.isIntersecting || entry.boundingClientRect.bottom < 0
				)
			) {
				then();
			}
		},
		{ rootMargin: `0px 0px ${ margin } 0px` }
	);
	seen.observe( link );
	return seen;
};

const watch = () => {
	const link = document.querySelector( '[data-pergel-more="scroll"]' );
	observer?.disconnect();
	ahead?.disconnect();
	if ( ! link || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	observer = near( link, scrolled ? '150%' : '400px', () =>
		more( link, true )
	);
	if ( scrolled ) {
		ahead = near( link, '400%', () => {
			ahead.disconnect();
			prefetch( link );
		} );
	}
};

watch();

if ( document.querySelector( '[data-pergel-more="scroll"]' ) ) {
	window.addEventListener(
		'scroll',
		() => {
			scrolled = true;
			watch();
		},
		{ once: true, passive: true }
	);
}
