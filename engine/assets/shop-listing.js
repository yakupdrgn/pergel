/**
 * Product listing extras (Pergel\Woo\Listing): grid and list buttons, the
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

/* Grid and list buttons. */

const setView = ( view ) => {
	root.dataset.pergelView = view;
	for ( const button of document.querySelectorAll(
		'.pergel-view-button'
	) ) {
		button.setAttribute(
			'aria-pressed',
			button.dataset.pergelView === view ? 'true' : 'false'
		);
	}
};

for ( const group of document.querySelectorAll( '.pergel-view-switch' ) ) {
	group.hidden = false;
	setView( root.dataset.pergelView || group.dataset.pergelDefault || 'grid' );
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

const more = async ( link, auto = false ) => {
	if ( busy ) {
		return;
	}
	const holder = link.closest( '[data-wp-router-region]' );
	const region = holder ? holder.dataset.wpRouterRegion : '';
	const step = Number( link.dataset.pergelStep );
	const before = region ? items( region ).length : 0;
	const wanted = before + step;
	if ( ! region || ! step || wanted > Number( link.dataset.pergelMost ) ) {
		// Scrolling never leaves the page by itself.
		if ( ! auto ) {
			window.location.assign( link.href );
		}
		return;
	}
	busy = true;
	root.classList.add( 'pergel-more-busy' );
	say( link.dataset.pergelBusy || '' );
	const url = new URL( window.location.href );
	url.pathname = url.pathname.replace( /\/page\/\d+\/?$/, '/' );
	url.searchParams.delete( 'paged' );
	url.searchParams.set( 'per_page', String( wanted ) );
	let first;
	try {
		const { actions } = await import( '@wordpress/interactivity-router' );
		// The router draws the new page, but the promise of navigate() does
		// not settle when it is called outside a directive: wait for the
		// new products instead.
		const drawn = arrival( region, before );
		Promise.resolve( actions.navigate( url.toString() ) ).catch( () => {} );
		first = await drawn;
	} catch {
		window.location.assign( link.href );
		return;
	} finally {
		busy = false;
		root.classList.remove( 'pergel-more-busy' );
	}
	say( '' );
	const target = first && first.querySelector( 'a' );
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
 * stays for keyboard users. */

let observer;

const watch = () => {
	const link = document.querySelector( '[data-pergel-more="scroll"]' );
	if ( observer ) {
		observer.disconnect();
	}
	if ( ! link || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}
	observer = new IntersectionObserver(
		( entries ) => {
			if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
				more( link, true );
			}
		},
		{ rootMargin: '0px 0px 400px 0px' }
	);
	observer.observe( link );
};

watch();
