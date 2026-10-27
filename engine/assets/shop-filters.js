/**
 * Filter area extras (Pergel\Woo\Filters): filter groups that open and
 * close, a search box in long option lists, subcategories closed under an
 * arrow, and long lists cut to a few rows with a More button.
 *
 * The group buttons come with the page. WooCommerce redraws the filters
 * from the server after every choice, so which groups are closed, which
 * lists are opened and what was typed are kept here, by group heading, and
 * put back after each redraw. The search box and the More button are added
 * here, so they never show without the script. A search first opens
 * WooCommerce's "show more", so it looks through every option; More opens
 * it too.
 *
 * WordPress's router keeps the first page's markup as it was when the
 * page was ready, with our boxes in it, and puts it back when the filters
 * are cleared; our own boxes stay too. So only the boxes made here count,
 * and copies of them go.
 */

const LONG = 8;
const GROUP = [
	'price',
	'taxonomy',
	'status',
	'attribute',
	'rating',
]
	.map( ( name ) => `.wp-block-woocommerce-product-filter-${ name }` )
	.join( ',' );

const closed = new Set();
const opened = new Set();
const branches = new Set();
const typed = new Map();
const ours = new WeakSet();
const ITEM = '.wc-block-product-filter-checkbox-list__item';
const SHOW_ALL = '.wc-block-product-filter-checkbox-list__show-more-button';

const fold = ( text ) =>
	text
		.toLocaleLowerCase( 'tr' )
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.replace( /ı/g, 'i' )
		.trim();

const nameOf = ( group ) => {
	const heading = group.querySelector( '.wp-block-heading' );
	return heading ? heading.textContent.trim() : '';
};

const setOpen = ( group, open ) => {
	group.classList.toggle( 'is-pergel-folded', ! open );
	const button = group.querySelector( '.pergel-filter-toggle' );
	if ( button ) {
		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	}
};

const narrow = ( group, query ) => {
	const words = fold( query );
	if ( words ) {
		const more = group.querySelector( `${ SHOW_ALL }:not([hidden])` );
		if ( more ) {
			more.click();
		}
	}
	group.classList.toggle( 'is-pergel-searching', words !== '' );
	for ( const item of group.querySelectorAll(
		'.wc-block-product-filter-checkbox-list__item'
	) ) {
		const miss = words !== '' && ! fold( item.textContent ).includes( words );
		item.toggleAttribute( 'data-pergel-miss', miss );
	}
};

// The one box or button of ours in a group; copies go.
const own = ( group, selector ) => {
	let found = null;
	for ( const node of group.querySelectorAll( selector ) ) {
		if ( ours.has( node ) && ! found ) {
			found = node;
		} else {
			node.remove();
		}
	}
	return found;
};

const addSearch = ( group, label ) => {
	const list = group.querySelector( '.wc-block-product-filter-checkbox-list' );
	const existing = own( group, '.pergel-filter-search' );
	if ( existing ) {
		// The options were redrawn under the box: narrow them again.
		if ( existing.value ) {
			narrow( group, existing.value );
		}
		return;
	}
	if ( ! list ) {
		return;
	}
	const items = list.querySelectorAll(
		'.wc-block-product-filter-checkbox-list__item'
	).length;
	const more = list.querySelector(
		'.wc-block-product-filter-checkbox-list__show-more'
	);
	if ( items <= LONG && ! more ) {
		return;
	}
	const name = nameOf( group );
	const input = document.createElement( 'input' );
	input.type = 'search';
	input.className = 'pergel-filter-search';
	input.setAttribute( 'aria-label', name ? `${ label }: ${ name }` : label );
	input.placeholder = label;
	input.value = typed.get( name ) || '';
	ours.add( input );
	input.addEventListener( 'input', () => {
		typed.set( name, input.value );
		narrow( group, input.value );
	} );
	list.before( input );
	if ( input.value ) {
		narrow( group, input.value );
	}
};

const depthOf = ( item ) => parseInt( item.dataset.pergelDepth || '0', 10 );

// Subcategories start closed under their parent's arrow (Filters::depth
// gives each option its depth). WooCommerce draws only its first fifteen
// options, so the rest are asked for first: every top category shows. A
// ticked subcategory keeps its parents open.
const tree = ( group, label ) => {
	const rest = group.querySelector( `${ SHOW_ALL }:not([hidden])` );
	const items = [ ...group.querySelectorAll( ITEM ) ];
	if ( ! items.some( ( item ) => depthOf( item ) > 0 ) ) {
		return;
	}
	// The options it adds arrive as a redraw, which comes back here.
	if ( rest ) {
		rest.click();
	}
	const name = nameOf( group );
	const open = [];
	items.forEach( ( item, index ) => {
		const depth = depthOf( item );
		open.length = depth;
		item.toggleAttribute( 'data-pergel-folded', open.includes( false ) );
		let end = index + 1;
		while ( end < items.length && depthOf( items[ end ] ) > depth ) {
			end++;
		}
		let button = own( item, '.pergel-filter-branch' );
		if ( end === index + 1 ) {
			if ( button ) {
				button.remove();
			}
			open[ depth ] = true;
			return;
		}
		const value = item.querySelector( 'input' )?.value || '';
		const key = `${ name }|${ value }`;
		if ( items.slice( index + 1, end ).some( ( child ) => child.querySelector( 'input:checked' ) ) ) {
			branches.add( key );
		}
		open[ depth ] = branches.has( key );
		if ( ! button ) {
			button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'pergel-filter-branch';
			button.dataset.key = key;
			const text = item.querySelector( '.wc-block-product-filter-checkbox-list__text' );
			button.setAttribute( 'aria-label', `${ label }: ${ text ? text.textContent.trim() : value }` );
			ours.add( button );
			item.append( button );
		}
		button.setAttribute( 'aria-expanded', open[ depth ] ? 'true' : 'false' );
	} );
};

// A long list shows its first rows; the rest wait behind the More button.
// A ticked option further down opens it.
const cut = ( group, filters ) => {
	const list = group.querySelector( '.wc-block-product-filter-checkbox-list' );
	if ( ! list ) {
		return;
	}
	const rows = parseInt( filters.dataset.pergelRows, 10 ) || 5;
	const items = [ ...list.querySelectorAll( ITEM ) ].filter(
		( item ) => ! item.hasAttribute( 'data-pergel-folded' )
	);
	list.querySelectorAll( `${ ITEM }[data-pergel-folded]` ).forEach( ( item ) =>
		item.removeAttribute( 'data-pergel-beyond' )
	);
	const rest = list.querySelector( SHOW_ALL );
	let button = own( group, '.pergel-filter-more' );
	if ( items.length <= rows + 1 && ! rest ) {
		items.forEach( ( item ) => item.removeAttribute( 'data-pergel-beyond' ) );
		if ( button ) {
			button.remove();
		}
		return;
	}
	const name = nameOf( group );
	if ( items.slice( rows ).some( ( item ) => item.querySelector( 'input:checked' ) ) ) {
		opened.add( name );
	}
	const open = opened.has( name );
	items.forEach( ( item, index ) =>
		item.toggleAttribute( 'data-pergel-beyond', ! open && index >= rows )
	);
	if ( ! button ) {
		button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'pergel-filter-more';
		ours.add( button );
		list.after( button );
	}
	const text = open
		? filters.dataset.pergelLessLabel || ''
		: filters.dataset.pergelMoreLabel || '';
	// Only real changes: the observer below would see a new text as a redraw.
	if ( button.textContent !== text ) {
		button.textContent = text;
	}
	button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
};

const enhance = ( filters ) => {
	const features = ( filters.dataset.pergelFilters || '' ).split( ' ' );
	const label = filters.dataset.pergelSearchLabel || '';
	for ( const group of filters.querySelectorAll( GROUP ) ) {
		if ( features.includes( 'fold' ) ) {
			setOpen( group, ! closed.has( nameOf( group ) ) );
		}
		if ( features.includes( 'search' ) ) {
			addSearch( group, label );
		}
		if ( features.includes( 'tree' ) ) {
			tree( group, filters.dataset.pergelBranchLabel || '' );
		}
		if ( features.includes( 'more' ) ) {
			cut( group, filters );
		}
	}
};

for ( const filters of document.querySelectorAll( '[data-pergel-filters]' ) ) {
	enhance( filters );
	new MutationObserver( () => enhance( filters ) ).observe( filters, {
		childList: true,
		subtree: true,
	} );
}

document.addEventListener( 'click', ( event ) => {
	const branch =
		event.target instanceof Element &&
		event.target.closest( '[data-pergel-filters] .pergel-filter-branch' );
	if ( branch ) {
		const group = branch.closest( GROUP );
		const filters = branch.closest( '[data-pergel-filters]' );
		if ( ! group || ! filters ) {
			return;
		}
		const key = branch.dataset.key || '';
		if ( branches.has( key ) ) {
			branches.delete( key );
		} else {
			branches.add( key );
		}
		tree( group, filters.dataset.pergelBranchLabel || '' );
		if ( filters.dataset.pergelFilters.split( ' ' ).includes( 'more' ) ) {
			cut( group, filters );
		}
		return;
	}
	const more =
		event.target instanceof Element &&
		event.target.closest( '[data-pergel-filters] .pergel-filter-more' );
	if ( more ) {
		const group = more.closest( GROUP );
		const filters = more.closest( '[data-pergel-filters]' );
		if ( ! group || ! filters ) {
			return;
		}
		const name = nameOf( group );
		if ( opened.has( name ) ) {
			opened.delete( name );
		} else {
			opened.add( name );
			// WooCommerce draws the options past its first fifteen on request.
			const rest = group.querySelector( `${ SHOW_ALL }:not([hidden])` );
			if ( rest ) {
				rest.click();
			}
		}
		cut( group, filters );
		return;
	}
	const button =
		event.target instanceof Element &&
		event.target.closest( '[data-pergel-filters] .pergel-filter-toggle' );
	if ( ! button ) {
		return;
	}
	const group = button.closest( GROUP );
	if ( ! group ) {
		return;
	}
	const open = button.getAttribute( 'aria-expanded' ) !== 'true';
	const name = nameOf( group );
	if ( open ) {
		closed.delete( name );
	} else {
		closed.add( name );
	}
	setOpen( group, open );
} );
