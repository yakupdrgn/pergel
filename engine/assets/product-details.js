/**
 * Tabs over the sections under the product (Pergel\Woo\Details).
 *
 * The sections stay the accordion's: WordPress opens and closes them, one
 * at a time (autoclose). A tab presses the heading button of its section,
 * which is hidden while the row of tabs shows; the tabs follow what is
 * open, so a link to a section or the browser's find opens the right tab.
 * While the row shows, each section is the tab panel of its tab.
 *
 * The column beside the sections has two plain tabs: recently viewed and
 * recommended products. Recently viewed waits hidden until its list has
 * products (assets/recently-viewed.js fills it), then opens first unless
 * the visitor has chosen a tab.
 */

// The theme's tablet breakpoint; product-details.css uses the same.
const wide = window.matchMedia( '(min-width: 600px)' );

for ( const box of document.querySelectorAll( '.pergel-details.is-tabs' ) ) {
	const tabs = [
		...box.querySelectorAll( '.pergel-details-tabs [role="tab"]' ),
	];
	const toggle = ( tab ) =>
		document.getElementById( tab.dataset.pergelToggle );
	const isOpen = ( tab ) =>
		toggle( tab )?.getAttribute( 'aria-expanded' ) === 'true';
	const shown = () =>
		box.classList.contains( 'is-phone-scroll' ) || wide.matches;

	const select = ( tab ) => {
		if ( ! isOpen( tab ) ) {
			toggle( tab )?.click();
		}
	};

	const sync = () => {
		for ( const tab of tabs ) {
			const open = isOpen( tab );
			tab.setAttribute( 'aria-selected', open ? 'true' : 'false' );
			tab.tabIndex = open ? 0 : -1;
		}
		if ( ! tabs.some( isOpen ) ) {
			tabs[ 0 ].tabIndex = 0;
		}
	};

	const roles = () => {
		const on = shown();
		for ( const tab of tabs ) {
			const panel = document.getElementById(
				tab.getAttribute( 'aria-controls' )
			);
			if ( ! panel ) {
				continue;
			}
			panel.setAttribute( 'role', on ? 'tabpanel' : 'region' );
			panel.setAttribute(
				'aria-labelledby',
				on ? tab.id : tab.dataset.pergelToggle
			);
		}
		// Going back to tabs with every section closed: open the first.
		if ( on && ! tabs.some( isOpen ) ) {
			select( tabs[ 0 ] );
		}
	};

	for ( const tab of tabs ) {
		tab.addEventListener( 'click', () => select( tab ) );
		const button = toggle( tab );
		if ( button ) {
			new window.MutationObserver( sync ).observe( button, {
				attributes: true,
				attributeFilter: [ 'aria-expanded' ],
			} );
		}
	}

	box.querySelector( '.pergel-details-tabs' )?.addEventListener(
		'keydown',
		( event ) => {
			const index = tabs.indexOf( event.target );
			if ( index < 0 ) {
				return;
			}
			const last = tabs.length - 1;
			const next = {
				ArrowRight: index === last ? 0 : index + 1,
				ArrowLeft: index === 0 ? last : index - 1,
				Home: 0,
				End: last,
			}[ event.key ];
			if ( next === undefined ) {
				return;
			}
			event.preventDefault();
			tabs[ next ].focus();
			tabs[ next ].scrollIntoView( { block: 'nearest', inline: 'nearest' } );
			select( tabs[ next ] );
		}
	);

	wide.addEventListener( 'change', roles );
	roles();
	sync();
}

for ( const list of document.querySelectorAll( '.pergel-side-tabs' ) ) {
	const tabs = [ ...list.querySelectorAll( '[role="tab"]' ) ];
	const panel = ( tab ) =>
		document.getElementById( tab.getAttribute( 'aria-controls' ) );
	let chosen = false;

	const select = ( tab ) => {
		for ( const other of tabs ) {
			const on = other === tab;
			other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			other.tabIndex = on ? 0 : -1;
			const box = panel( other );
			if ( box ) {
				box.hidden = ! on;
			}
		}
	};

	list.addEventListener( 'click', ( event ) => {
		const tab = event.target.closest( '[role="tab"]' );
		if ( tab ) {
			chosen = true;
			select( tab );
		}
	} );

	list.addEventListener( 'keydown', ( event ) => {
		const shown = tabs.filter( ( tab ) => ! tab.hidden );
		const index = shown.indexOf( event.target );
		if ( index < 0 ) {
			return;
		}
		const last = shown.length - 1;
		const next = {
			ArrowRight: index === last ? 0 : index + 1,
			ArrowLeft: index === 0 ? last : index - 1,
			Home: 0,
			End: last,
		}[ event.key ];
		if ( next === undefined ) {
			return;
		}
		event.preventDefault();
		chosen = true;
		shown[ next ].focus();
		select( shown[ next ] );
	} );

	const recent = tabs.find( ( tab ) => tab.hidden );
	const section =
		recent && panel( recent )?.querySelector( '[data-pergel-recent]' );
	if ( section ) {
		const observer = new window.MutationObserver( () => reveal() );
		const reveal = () => {
			if ( section.hidden ) {
				return;
			}
			observer.disconnect();
			recent.hidden = false;
			if ( ! chosen ) {
				select( recent );
			}
		};
		observer.observe( section, {
			attributes: true,
			attributeFilter: [ 'hidden' ],
		} );
		reveal();
	}
}
