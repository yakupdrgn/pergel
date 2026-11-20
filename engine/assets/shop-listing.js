/**
 * Product listing extras (Pergel\Woo\Listing): list and grid buttons, the
 * products per page menu, loading more products on the same page, and the
 * loading state while the filters, sorting or page numbers draw the list
 * again.
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

/* Products per page: the list is drawn again in place, like the filters and
 * the sorting menu (the form sends itself when the router cannot), and the
 * number is kept for the visitor's next lists (see own() below). */

const PER_PAGE = 'pergel-shop-per-page';
const PER_PAGE_ARG = 'per_page';

for ( const select of document.querySelectorAll( '.pergel-per-page select' ) ) {
	select.addEventListener( 'change', () => {
		try {
			if (
				! select.value ||
				select.value === select.form.dataset.pergelDefault
			) {
				window.localStorage.removeItem( PER_PAGE );
			} else {
				window.localStorage.setItem( PER_PAGE, select.value );
			}
		} catch {
			// Storage may be blocked; the number then lasts for this list.
		}
		perPage( select ).catch( () => select.form.requestSubmit() );
	} );
}

/* Sorting from a /page/N/ address (the one loading more leaves): the
 * sorting menu keeps the address and asks for page 1 with "paged=1", so
 * the list would say /page/N/?paged=1. Back to the list's own address
 * first, before the menu reads it. */

document.addEventListener(
	'change',
	( event ) => {
		if (
			! ( event.target instanceof HTMLSelectElement ) ||
			! event.target.closest( '.wc-block-catalog-sorting' )
		) {
			return;
		}
		const url = new URL( window.location.href );
		const path = url.pathname.replace( /\/page\/\d+\/?$/, '/' );
		if ( path !== url.pathname ) {
			url.pathname = path;
			window.history.replaceState( window.history.state, '', url );
		}
		// The sorting menu's own handler stops the event: the list shows it
		// loads from here.
		if ( ! busy ) {
			loading();
		}
	},
	true
);

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

// Once the products of the grid are no longer `before` in number; rejects
// after ten seconds.
const recount = ( region, before ) =>
	new Promise( ( resolve, reject ) => {
		const watcher = new MutationObserver( () => {
			if ( items( region ).length !== before ) {
				watcher.disconnect();
				clearTimeout( timer );
				resolve();
			}
		} );
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

// The address the list shows once `count` products are on it: the page the
// last of them is on, /page/N/ as the page numbers link it (page 1 is the
// list's own address). Reloading it shows that page; the products per page
// in the address that loaded them never show.
const address = ( count, step ) => {
	const url = new URL( window.location.href );
	const page = Math.ceil( count / step );
	url.pathname = url.pathname.replace( /\/page\/\d+\/?$/, '/' );
	if ( page > 1 ) {
		url.pathname = url.pathname.replace( /\/?$/, `/page/${ page }/` );
	}
	url.searchParams.delete( 'paged' );
	url.searchParams.delete( 'per_page' );
	return url.toString();
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

// Draws `url` (the list with more products per page) in place of the
// region and waits for product number `before` + 1. The address then shows
// the page reached, and the history entry keeps how many products are on
// the list, so coming back to it draws them all again (see back()).
const draw = async ( region, before, url, step ) => {
	const { actions } = await import( '@wordpress/interactivity-router' );
	// The router draws the new page, but the promise of navigate() does
	// not settle when it is called outside a directive: wait for the
	// new products instead.
	// Loading more is not a new step back: it replaces the entry.
	const drawn = arrival( region, before );
	Promise.resolve( actions.navigate( url, { replace: true } ) ).catch(
		() => {}
	);
	const first = await drawn;
	const count = items( region ).length;
	window.history.replaceState(
		{
			...( window.history.state || {} ),
			pergelMore: { region, count, step },
		},
		'',
		address( count, step )
	);
	// The new button's own link goes on from that page too, for a new
	// tab or a page without the script.
	const after = document.querySelector(
		`[data-wp-router-region="${ CSS.escape( region ) }"] [data-pergel-more]`
	);
	if ( after ) {
		after.href = address( count + step, step );
	}
	return first;
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
		first = await draw(
			region,
			before,
			url,
			Number( link.dataset.pergelStep )
		);
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

/* Coming back to a list that loaded more. Its address is the page reached
 * (/page/3/), which on its own shows that page only; the history entry
 * remembers how many products were on the list and where the page was
 * scrolled. Back from a product (when the browser does not keep the page
 * in memory) or a reload of the entry draws them all again and scrolls
 * there. An inline script in the head hides the products until then
 * (pergel-redraw); shop-layout.css shows them after a few seconds
 * whatever happens. A link opened anew has no entry and shows the page. */

// Where the page is scrolled, kept in the entry once scrolling rests and
// when a link is followed (a reload does not keep what pagehide writes).
// Not while the list is being drawn again: the browser's own scroll on a
// short page would overwrite the place to go back to.
const keep = () => {
	const saved = window.history.state?.pergelMore;
	if ( saved && ! root.classList.contains( 'pergel-redraw' ) ) {
		window.history.replaceState(
			{
				...window.history.state,
				pergelMore: { ...saved, y: Math.round( window.scrollY ) },
			},
			''
		);
	}
};

let resting;
window.addEventListener(
	'scroll',
	() => {
		clearTimeout( resting );
		resting = setTimeout( keep, 250 );
	},
	{ passive: true }
);
window.addEventListener( 'pagehide', keep );
document.addEventListener(
	'click',
	( event ) => {
		if ( event.target instanceof Element && event.target.closest( 'a[href]' ) ) {
			keep();
		}
	},
	true
);

const back = async () => {
	const saved = window.history.state?.pergelMore;
	const entry = performance.getEntriesByType?.( 'navigation' )[ 0 ];
	const holder =
		saved &&
		document.querySelector(
			`[data-wp-router-region="${ CSS.escape( String( saved.region ) ) }"]`
		);
	const before = holder ? items( saved.region ).length : 0;
	if (
		! holder ||
		! entry ||
		entry.type === 'navigate' ||
		! ( saved.count > before ) ||
		! ( saved.step > 0 )
	) {
		return false;
	}
	const url = new URL( address( 0, saved.step ) );
	url.searchParams.set( 'per_page', String( saved.count ) );
	busy = true;
	document.body.style.overflowAnchor = 'none';
	try {
		await draw( saved.region, before, url.toString(), saved.step );
		if ( Number.isFinite( saved.y ) ) {
			window.scrollTo( 0, saved.y );
		}
	} catch {
		// The page reached stays, with its page numbers.
	} finally {
		busy = false;
		root.classList.remove( 'pergel-redraw' );
		root.style.removeProperty( '--pergel-back-height' );
		document.body.style.removeProperty( 'overflow-anchor' );
	}
	return true;
};

/* The products per page a visitor picked from the menu, on their next
 * lists: a list reached without a number of its own (a category from the
 * menu, a new visit) is drawn again with it. Only its first page, and not
 * when all its products are already on it. The inline script in the head
 * hides the products until then (Listing::remembered()). */

const own = async () => {
	const form = document.querySelector( '.pergel-per-page' );
	const select = form && form.querySelector( 'select' );
	const holder = document
		.querySelector( '.wc-block-product-template' )
		?.closest( '[data-wp-router-region]' );
	let wanted = 0;
	try {
		wanted = Number( window.localStorage.getItem( PER_PAGE ) );
	} catch {
		return;
	}
	const url = new URL( window.location.href );
	const region = holder ? holder.dataset.wpRouterRegion : '';
	const before = region ? items( region ).length : 0;
	const menu = select
		? [ ...select.options ].map( ( option ) => Number( option.value ) )
		: [];
	const more = !! holder?.querySelector(
		'[data-pergel-more], .wp-block-query-pagination-next'
	);
	if (
		! region ||
		! menu.includes( wanted ) ||
		wanted === Number( form.dataset.pergelDefault ) ||
		( wanted > before && ! more ) ||
		url.searchParams.has( PER_PAGE_ARG ) ||
		url.searchParams.has( 'paged' ) ||
		/\/page\/\d+\/?$/.test( url.pathname )
	) {
		return;
	}
	url.searchParams.set( PER_PAGE_ARG, String( wanted ) );
	busy = true;
	try {
		const { actions } = await import( '@wordpress/interactivity-router' );
		const drawn = recount( region, before );
		Promise.resolve(
			actions.navigate( url.toString(), { replace: true } )
		).catch( () => {} );
		await drawn;
		select.value = String( wanted );
		keepPerPage( String( wanted ) );
	} catch {
		// The list stays as the server drew it.
	} finally {
		busy = false;
	}
};

// The sorting menus, outside the redrawn region, send the number along.
const keepPerPage = ( value ) => {
	for ( const sorting of document.querySelectorAll( '.woocommerce-ordering' ) ) {
		let field = sorting.querySelector( `input[name="${ PER_PAGE_ARG }"]` );
		if ( ! value ) {
			field?.remove();
			continue;
		}
		if ( ! field ) {
			field = document.createElement( 'input' );
			field.type = 'hidden';
			field.name = PER_PAGE_ARG;
			sorting.append( field );
		}
		field.value = value;
	}
};

// Once the region holding `holder` changes (products, numbers, the load more
// button); rejects after ten seconds.
const redrawn = ( holder ) =>
	new Promise( ( resolve, reject ) => {
		const watcher = new MutationObserver( () => {
			watcher.disconnect();
			clearTimeout( timer );
			resolve();
		} );
		const timer = setTimeout( () => {
			watcher.disconnect();
			reject( new Error( 'timeout' ) );
		}, 10000 );
		watcher.observe( holder, {
			childList: true,
			subtree: true,
			attributes: true,
			characterData: true,
		} );
	} );

/* Loading: while the filters, the sorting menu, the products per page or a
 * page number draw the list again, the region of the products says so:
 * aria-busy and the pergel-loading class, with --pergel-loading-y (the
 * middle of the part of the products on the screen, from their top) and
 * --pergel-loading-label for themes. shop-layout.css dims the products and
 * turns a ring there. It ends once the products change or the address does
 * (the router changes it after drawing, also when the products stay the
 * same), or after ten seconds. */

const DATA = ( () => {
	try {
		return (
			JSON.parse(
				document.getElementById(
					'wp-script-module-data-pergel/shop-listing'
				)?.textContent || '{}'
			) || {}
		);
	} catch {
		return {};
	}
} )();

const productRegion = () =>
	document
		.querySelector( '.wc-block-product-template' )
		?.closest( '[data-wp-router-region]' );

let stopLoading;

const loading = () => {
	const holder = productRegion();
	const grid = holder?.querySelector( '.wc-block-product-template' );
	if ( ! grid ) {
		return;
	}
	// A second change while the first loads waits for its own list.
	stopLoading?.( false );
	const from = window.location.href;
	const box = grid.getBoundingClientRect();
	const top = Math.max( box.top, 0 );
	const bottom = Math.min( box.bottom, window.innerHeight );
	const middle =
		bottom > top ? ( top + bottom ) / 2 - box.top : box.height / 2;
	const y = Math.min( Math.max( middle, 48 ), Math.max( box.height - 48, 48 ) );
	holder.style.setProperty( '--pergel-loading-y', `${ Math.round( y ) }px` );
	if ( DATA.loading ) {
		holder.style.setProperty(
			'--pergel-loading-label',
			JSON.stringify( String( DATA.loading ) )
		);
	}
	holder.classList.add( 'pergel-loading' );
	holder.setAttribute( 'aria-busy', 'true' );
	say( DATA.loading || '' );
	const moved = () => {
		if ( window.location.href !== from ) {
			stop();
		}
	};
	const watcher = new MutationObserver( () => stop() );
	const timer = setTimeout( () => stop(), 10000 );
	const stop = ( done = true ) => {
		watcher.disconnect();
		clearTimeout( timer );
		window.navigation?.removeEventListener( 'currententrychange', moved );
		window.removeEventListener( 'popstate', moved );
		stopLoading = null;
		if ( done ) {
			holder.classList.remove( 'pergel-loading' );
			holder.removeAttribute( 'aria-busy' );
			say( '' );
		}
	};
	stopLoading = stop;
	watcher.observe( holder, {
		childList: true,
		subtree: true,
		characterData: true,
	} );
	window.navigation?.addEventListener( 'currententrychange', moved );
	window.addEventListener( 'popstate', moved );
};

// Draws the list again with the products per page `select` says: its first
// page, filters and sorting kept. Rejects when the router cannot, and the
// form then sends itself.
const perPage = async ( select ) => {
	const holder = document
		.querySelector( '.wc-block-product-template' )
		?.closest( '[data-wp-router-region]' );
	if ( ! holder || busy ) {
		throw new Error( 'no region' );
	}
	const value =
		select.value === select.form.dataset.pergelDefault ? '' : select.value;
	const url = new URL( window.location.href );
	url.pathname = url.pathname.replace( /\/page\/\d+\/?$/, '/' );
	url.searchParams.delete( 'paged' );
	if ( value ) {
		url.searchParams.set( PER_PAGE_ARG, value );
	} else {
		url.searchParams.delete( PER_PAGE_ARG );
	}
	busy = true;
	loading();
	try {
		const { actions } = await import( '@wordpress/interactivity-router' );
		const drawn = redrawn( holder );
		Promise.resolve( actions.navigate( url.toString() ) ).catch( () => {} );
		await drawn;
		keepPerPage( value );
		say( select.selectedOptions[ 0 ]?.textContent.trim() || '' );
	} finally {
		busy = false;
	}
};

/* After a filter changes the list while its head is above the screen (a
 * filter low in a long sidebar, or a list of one product that leaves the
 * visitor at the footer), the head of the list (how many products, the
 * sorting menu) comes back into view. A filter tried with the head in view
 * moves nothing. Themes with a sticky header set scroll-margin-top on
 * .wc-block-product-results-count. */

// What counts as a filter change: options ticked, the price, a chip
// removed, "Clear filters". Opening a group or typing in its search does not.
const FILTER_CHANGE =
	'input[type="checkbox"], input[type="radio"], select, .wc-block-product-filter-price-slider input';
const FILTER_CLICK =
	'.wc-block-product-filter-removable-chips__remove, .wp-block-woocommerce-product-filter-clear-button :is(a, button)';

let lifting = false;

const lift = () => {
	const holder = productRegion();
	if ( lifting || ! holder ) {
		return;
	}
	lifting = true;
	redrawn( holder )
		.then(
			() =>
				new Promise( ( resolve ) => {
					// After the browser lays the new list out.
					window.requestAnimationFrame( () => {
						const head =
							document.querySelector( '.wc-block-product-results-count' ) ||
							document.querySelector( '.wc-block-catalog-sorting' ) ||
							holder;
						if ( head.getBoundingClientRect().top < 0 ) {
							head.scrollIntoView( {
								block: 'start',
								behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' )
									.matches
									? 'auto'
									: 'smooth',
							} );
						}
						resolve();
					} );
				} )
		)
		.catch( () => {} )
		.finally( () => {
			lifting = false;
		} );
};

const filtered = ( event ) => {
	if (
		busy ||
		! ( event.target instanceof Element ) ||
		! event.target.closest(
			'change' === event.type ? FILTER_CHANGE : FILTER_CLICK
		)
	) {
		return;
	}
	loading();
	lift();
};

for ( const filters of document.querySelectorAll(
	'.wp-block-woocommerce-product-filters'
) ) {
	filters.addEventListener( 'change', filtered );
	filters.addEventListener( 'click', filtered );
}

// The page numbers of the list (the sorting menu shows it loads from its
// capture listener above).
document.addEventListener( 'click', ( event ) => {
	const link =
		event.target instanceof Element &&
		event.target.closest( '.wp-block-query-pagination a[href]' );
	if (
		! link ||
		busy ||
		event.button !== 0 ||
		event.metaKey ||
		event.ctrlKey ||
		event.shiftKey ||
		event.altKey ||
		link.closest( '[data-wp-router-region]' ) !== productRegion()
	) {
		return;
	}
	loading();
} );

back()
	.then( ( drawn ) => drawn || own() )
	.finally( () => {
		root.classList.remove( 'pergel-redraw' );
		watch();
	} );

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
