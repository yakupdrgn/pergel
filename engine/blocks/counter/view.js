/**
 * Counter block: counts the number up from zero when the block scrolls into
 * view. The server prints the final number, so it is there without
 * JavaScript; with reduced motion nothing moves. The number keeps its
 * thousands separator, its decimals and the text around it ("250+", "%98").
 */
const NUMBER = /\d{1,3}(?:([.,   ])\d{3})+(?:[.,]\d{1,2})?|\d+(?:[.,]\d{1,2})?/;

const parse = ( token ) => {
	const decimals = token.match( /[.,](\d{1,2})$/ );
	const places = decimals ? decimals[ 1 ].length : 0;
	const whole = decimals ? token.slice( 0, -( places + 1 ) ) : token;
	const group = ( whole.match( /\D/ ) || [ '' ] )[ 0 ];
	return {
		value: Number( whole.replace( /\D/g, '' ) + ( places ? '.' + decimals[ 1 ] : '' ) ),
		places,
		point: places ? token.charAt( token.length - places - 1 ) : '',
		group,
	};
};

const format = ( n, { places, point, group } ) => {
	const [ whole, fraction ] = n.toFixed( places ).split( '.' );
	const grouped = group ? whole.replace( /\B(?=(\d{3})+(?!\d))/g, group ) : whole;
	return fraction ? grouped + point + fraction : grouped;
};

/** The text node with the number, the number and the text around it. */
const find = ( element ) => {
	const walker = document.createTreeWalker( element, NodeFilter.SHOW_TEXT );
	for ( let node = walker.nextNode(); node; node = walker.nextNode() ) {
		const match = node.data.match( NUMBER );
		if ( match ) {
			return {
				node,
				before: node.data.slice( 0, match.index ),
				after: node.data.slice( match.index + match[ 0 ].length ),
				number: parse( match[ 0 ] ),
			};
		}
	}
	return null;
};

const run = ( counter, duration ) => {
	const start = performance.now();
	const step = ( now ) => {
		const t = Math.min( 1, ( now - start ) / duration );
		const eased = 1 - Math.pow( 1 - t, 3 );
		counter.node.data = counter.before + format( counter.number.value * eased, counter.number ) + counter.after;
		if ( t < 1 ) {
			requestAnimationFrame( step );
		}
	};
	requestAnimationFrame( step );
};

if ( 'IntersectionObserver' in window && ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
	const counters = new Map();
	const observer = new IntersectionObserver(
		( entries ) => {
			for ( const entry of entries ) {
				if ( entry.isIntersecting ) {
					observer.unobserve( entry.target );
					run( counters.get( entry.target ), Number( entry.target.dataset.duration ) );
				}
			}
		},
		{ threshold: 0.5 }
	);
	for ( const block of document.querySelectorAll( '.wp-block-pergel-counter' ) ) {
		const first = block.firstElementChild;
		const counter = first && Number( block.dataset.duration ) > 0 ? find( first ) : null;
		if ( counter ) {
			counter.node.data = counter.before + format( 0, counter.number ) + counter.after;
			counters.set( block, counter );
			observer.observe( block );
		}
	}
}
