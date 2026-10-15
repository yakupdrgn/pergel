/**
 * Map block: the "Show the map" link swaps the image for the embedded map,
 * so nothing loads from the map provider before the visitor asks for it.
 */
for ( const block of document.querySelectorAll( '.wp-block-pergel-map[data-src]' ) ) {
	const link = block.querySelector( '.wp-block-pergel-map__load' );
	if ( ! link ) {
		continue;
	}
	link.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		const frame = document.createElement( 'iframe' );
		frame.src = block.dataset.src;
		frame.title = block.dataset.title || '';
		frame.referrerPolicy = 'no-referrer-when-downgrade';
		frame.allowFullscreen = true;
		frame.className = 'wp-block-pergel-map__embed';
		link.closest( '.wp-block-pergel-map__frame' ).append( frame );
		block.classList.add( 'is-loaded' );
		frame.focus();
	} );
}
