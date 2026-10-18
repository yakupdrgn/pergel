/**
 * Map block: the "Show the map" link swaps the image for the embedded map,
 * so nothing loads from the map provider before the visitor asks for it.
 */

/**
 * The embed address of a block, if it is the map provider's. Markup that
 * imitates the block (data attributes pass the post content filters)
 * cannot load another address into the frame.
 *
 * @param {HTMLElement} block Block element.
 * @return {string} Address, or '' when it is not a map embed.
 */
const embed = ( block ) => {
	try {
		const url = new URL( block.dataset.src || '' );
		return 'https:' === url.protocol && 'www.google.com' === url.hostname && url.pathname.startsWith( '/maps' ) ? url.href : '';
	} catch {
		return '';
	}
};

for ( const block of document.querySelectorAll( '.wp-block-pergel-map[data-src]' ) ) {
	const link = block.querySelector( '.wp-block-pergel-map__load' );
	const src = embed( block );
	if ( ! link || ! src ) {
		continue;
	}
	link.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		const frame = document.createElement( 'iframe' );
		frame.src = src;
		frame.title = block.dataset.title || '';
		frame.referrerPolicy = 'no-referrer-when-downgrade';
		frame.allowFullscreen = true;
		frame.className = 'wp-block-pergel-map__embed';
		link.closest( '.wp-block-pergel-map__frame' ).append( frame );
		block.classList.add( 'is-loaded' );
		frame.focus();
	} );
}
