/**
 * Logo marquee: shows the pause button and switches the row between moving
 * and paused (WCAG 2.2.2). Hover and keyboard focus also pause it, in CSS.
 */
for ( const block of document.querySelectorAll( '.wp-block-pergel-logo-marquee' ) ) {
	const toggle = block.querySelector( '.wp-block-pergel-logo-marquee__toggle' );
	if ( toggle ) {
		toggle.hidden = false;
		toggle.addEventListener( 'click', () => {
			const paused = 'true' !== toggle.getAttribute( 'aria-pressed' );
			toggle.setAttribute( 'aria-pressed', String( paused ) );
			block.classList.toggle( 'is-paused', paused );
		} );
	}
}
