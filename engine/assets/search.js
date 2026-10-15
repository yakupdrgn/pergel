/**
 * Live search suggestions on the core Search block (Pergel\Search\Box).
 *
 * The field is an ARIA combobox: typing two or more characters asks
 * /pergel/v1/search (no cookies, so the answer can be cached) and fills
 * the list under the field; the arrow keys move through it, Enter opens
 * the chosen suggestion, Escape closes the list. Without a chosen
 * suggestion Enter sends the form to the search page as before.
 */

const DELAY = 150;
const MIN_LENGTH = 2;

const format = ( text, value ) => text.replace( /%[ds]/, String( value ) );

function element( tag, className, text ) {
	const node = document.createElement( tag );
	if ( className ) {
		node.className = className;
	}
	if ( text ) {
		node.textContent = text;
	}
	return node;
}

function setup( form ) {
	let config;
	try {
		config = JSON.parse( form.dataset.pergelSearch );
	} catch {
		return;
	}
	const input = form.querySelector( 'input[role="combobox"]' );
	const panel = form.querySelector( '.pergel-search__panel' );
	const list = form.querySelector( '.pergel-search__list' );
	const status = form.querySelector( '.pergel-search__status' );
	if ( ! input || ! panel || ! list || ! status ) {
		return;
	}
	const empty = element( 'p', 'pergel-search__empty' );
	empty.hidden = true;
	panel.append( empty );

	const answers = new Map();
	let timer = 0;
	let controller = null;
	let options = [];
	let active = -1;
	let shown = '';

	const open = ( isOpen ) => {
		panel.hidden = ! isOpen;
		input.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		if ( ! isOpen ) {
			select( -1 );
		}
	};

	const select = ( index ) => {
		options.forEach( ( option, i ) => option.setAttribute( 'aria-selected', i === index ? 'true' : 'false' ) );
		active = index;
		if ( index < 0 ) {
			input.removeAttribute( 'aria-activedescendant' );
			return;
		}
		input.setAttribute( 'aria-activedescendant', options[ index ].id );
		options[ index ].scrollIntoView( { block: 'nearest' } );
	};

	const option = ( href, className ) => {
		const link = element( 'a', 'pergel-search__option' + ( className ? ' ' + className : '' ) );
		link.href = href;
		link.id = list.id + '-' + options.length;
		link.setAttribute( 'role', 'option' );
		link.setAttribute( 'aria-selected', 'false' );
		link.tabIndex = -1;
		options.push( link );
		return link;
	};

	const group = ( label ) => {
		const node = element( 'div', 'pergel-search__group' );
		node.setAttribute( 'role', 'group' );
		const heading = element( 'div', 'pergel-search__label', label );
		heading.id = list.id + '-g' + list.childElementCount;
		heading.setAttribute( 'role', 'presentation' );
		node.setAttribute( 'aria-labelledby', heading.id );
		node.append( heading );
		list.append( node );
		return node;
	};

	const render = ( answer ) => {
		const { text } = config;
		list.replaceChildren();
		options = [];
		active = -1;
		shown = answer.query;

		if ( answer.terms.length ) {
			const node = group( text.terms );
			for ( const term of answer.terms ) {
				const link = option( term.url, 'is-term' );
				link.append( element( 'span', 'pergel-search__title', term.name ) );
				node.append( link );
			}
		}
		if ( answer.items.length ) {
			const node = group( text.results );
			for ( const item of answer.items ) {
				const link = option( item.url );
				if ( item.image ) {
					const image = element( 'img', 'pergel-search__image' );
					image.src = item.image.src;
					image.width = item.image.width;
					image.height = item.image.height;
					image.alt = '';
					image.loading = 'lazy';
					image.decoding = 'async';
					link.append( image );
				} else {
					link.append( element( 'span', 'pergel-search__image is-empty' ) );
				}
				const body = element( 'span', 'pergel-search__body' );
				body.append( element( 'span', 'pergel-search__title', item.title ) );
				if ( item.price ) {
					// Price markup from the server, filtered there to a few inline tags.
					const price = element( 'span', 'pergel-search__meta pergel-search__price' );
					price.innerHTML = item.price;
					body.append( price );
				} else if ( 'product' !== config.type && item.type ) {
					body.append( element( 'span', 'pergel-search__meta', item.type ) );
				}
				link.append( body );
				node.append( link );
			}
		}
		if ( answer.total > 0 ) {
			const more = option( answer.more, 'is-more' );
			more.textContent = 1 === answer.total ? text.allOne : format( text.all, answer.total );
			list.append( more );
		}
		// A listbox holds options only, so "nothing found" stands beside it.
		list.hidden = 0 === options.length;
		empty.hidden = 0 !== options.length;
		empty.textContent = format( text.none, answer.query );

		const count = options.length;
		status.textContent = 0 === count ? format( text.none, answer.query ) : ( 1 === count ? text.statusOne : format( text.status, count ) );
		open( true );
	};

	const ask = async ( query ) => {
		if ( answers.has( query ) ) {
			render( answers.get( query ) );
			return;
		}
		controller?.abort();
		controller = new AbortController();
		const url = new URL( config.endpoint, window.location.href );
		url.searchParams.set( 'q', query );
		if ( config.type ) {
			url.searchParams.set( 'type', config.type );
		}
		try {
			const response = await fetch( url, { signal: controller.signal, credentials: 'omit', headers: { Accept: 'application/json' } } );
			if ( ! response.ok ) {
				return;
			}
			const answer = await response.json();
			answers.set( query, answer );
			if ( input.value.trim() === query ) {
				render( answer );
			}
		} catch {
			// Aborted by the next keystroke, or offline: the form still works.
		}
	};

	input.addEventListener( 'input', () => {
		window.clearTimeout( timer );
		const query = input.value.trim();
		if ( query.length < MIN_LENGTH ) {
			controller?.abort();
			open( false );
			status.textContent = '';
			return;
		}
		timer = window.setTimeout( () => ask( query ), DELAY );
	} );

	input.addEventListener( 'focus', () => {
		if ( shown && shown === input.value.trim() && options.length ) {
			open( true );
		}
	} );

	input.addEventListener( 'keydown', ( event ) => {
		const isOpen = ! panel.hidden;
		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			if ( ! options.length ) {
				return;
			}
			event.preventDefault();
			if ( ! isOpen ) {
				open( true );
			}
			// Past either end the focus goes back to the text (-1), then wraps.
			let next = active + ( 'ArrowDown' === event.key ? 1 : -1 );
			if ( next >= options.length ) {
				next = -1;
			} else if ( next < -1 ) {
				next = options.length - 1;
			}
			select( next );
		} else if ( 'Enter' === event.key && isOpen && active >= 0 ) {
			event.preventDefault();
			window.location.assign( options[ active ].href );
		} else if ( 'Escape' === event.key && isOpen ) {
			// Only the list closes, not a menu or the hidden search field around it.
			event.preventDefault();
			event.stopPropagation();
			open( false );
		}
	} );

	form.addEventListener( 'focusout', ( event ) => {
		if ( ! event.relatedTarget || ! form.contains( event.relatedTarget ) ) {
			open( false );
		}
	} );

	document.addEventListener( 'pointerdown', ( event ) => {
		if ( ! panel.hidden && ! form.contains( event.target ) ) {
			open( false );
		}
	} );
}

document.querySelectorAll( 'form[data-pergel-search]' ).forEach( setup );
