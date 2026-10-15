/**
 * Header part options on the front end: hides a "smart" sticky header while
 * the page scrolls down and shows it again on the way up, marks a scrolled
 * page for the overlay header, and keeps --pergel-header-height current for
 * anchor links. Loaded only on pages whose header uses one of the options.
 */
const root = document.documentElement;

for ( const header of document.querySelectorAll( '.pergel-header' ) ) {
	const smart = header.classList.contains( 'is-smart' );
	let last = window.scrollY;
	let queued = false;

	const update = () => {
		queued = false;
		const y = Math.max( 0, window.scrollY );
		header.classList.toggle( 'is-scrolled', y > 0 );
		if ( smart ) {
			const busy = header.contains( document.activeElement ) || root.classList.contains( 'has-modal-open' );
			if ( y > last && y > header.offsetHeight && ! busy ) {
				header.classList.add( 'is-hidden' );
			} else if ( y < last || busy ) {
				header.classList.remove( 'is-hidden' );
			}
		}
		last = y;
	};

	window.addEventListener(
		'scroll',
		() => {
			if ( ! queued ) {
				queued = true;
				window.requestAnimationFrame( update );
			}
		},
		{ passive: true }
	);
	header.addEventListener( 'focusin', () => header.classList.remove( 'is-hidden' ) );
	new window.ResizeObserver( () => root.style.setProperty( '--pergel-header-height', `${ header.offsetHeight }px` ) ).observe( header );
	update();
}
