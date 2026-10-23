/**
 * The bar at the bottom of phones (Pergel\Woo\BottomBar): the search
 * dialog, and the filter button, which opens WooCommerce's own filter panel
 * and shows only where there is one.
 */

const dialog = document.getElementById( 'pergel-bottom-search' );

for ( const button of document.querySelectorAll( '[data-pergel-bar-search]' ) ) {
	button.addEventListener( 'click', () => {
		if ( ! dialog?.showModal ) {
			return;
		}
		dialog.showModal();
		dialog.querySelector( 'input[type="search"]' )?.focus();
	} );
}

dialog?.querySelector( '[data-pergel-bar-close]' )?.addEventListener( 'click', () => dialog.close() );
// A click on the backdrop closes it too.
dialog?.addEventListener( 'click', ( event ) => {
	if ( event.target === dialog ) {
		dialog.close();
	}
} );

const filter = document.querySelector( '[data-pergel-bar-filter]' );
const opener = document.querySelector( '.wc-block-product-filters__open-overlay' );
if ( filter && opener ) {
	// Only while the panel's own button shows, that is, on phones.
	const seen = () => {
		filter.hidden = opener.offsetParent === null;
	};
	seen();
	window.matchMedia( '(max-width: 599.98px)' ).addEventListener( 'change', seen );
	filter.querySelector( 'button' )?.addEventListener( 'click', () => opener.click() );
}
