/**
 * Carousel block: the previous / next buttons and the dots scroll the row
 * to a slide; scrolling by hand (touch, trackpad, arrow keys) moves the
 * dots along. Without this script the row still scrolls and snaps.
 */
import { store, getContext, getElement, withScope } from '@wordpress/interactivity';

const reduced = () => window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

const trackOf = ( element ) =>
	element.closest( '.wp-block-pergel-carousel' ).querySelector( ':scope > .wp-block-pergel-carousel__track' );

/** Reads the position of the row into the context. */
const measure = ( context, track ) => {
	const first = track.firstElementChild;
	if ( ! first ) {
		return;
	}
	const gap = parseFloat( getComputedStyle( track ).columnGap ) || 0;
	const step = first.getBoundingClientRect().width + gap;
	const left = Math.abs( track.scrollLeft );
	context.visible = Math.max( 1, Math.round( ( track.clientWidth + gap ) / step ) );
	context.index = Math.min( Math.round( left / step ), Math.max( 0, context.count - context.visible ) );
	context.atStart = left <= 1;
	context.atEnd = left + track.clientWidth >= track.scrollWidth - 1;
};

/** Scrolls the row so that slide i is the first one in view. */
const go = ( element, i ) => {
	const track = trackOf( element );
	const slide = track.children[ Math.max( 0, Math.min( i, track.children.length - 1 ) ) ];
	if ( slide ) {
		slide.scrollIntoView( { behavior: reduced() ? 'auto' : 'smooth', block: 'nearest', inline: 'start' } );
	}
};

const { state } = store( 'pergel/carousel', {
	state: {
		get scrollable() {
			const context = getContext();
			return context.count > context.visible;
		},
		get showNav() {
			return getContext().ready && state.scrollable;
		},
		get showDots() {
			return getContext().dots && state.scrollable;
		},
		/** The dot of the first slide in view. */
		get current() {
			const context = getContext();
			return context.slide === context.index;
		},
		/** Dots of slides that can never be first in view. */
		get dotHidden() {
			const context = getContext();
			return context.slide > context.count - context.visible;
		},
	},
	actions: {
		prev() {
			const context = getContext();
			go( getElement().ref, context.index - 1 );
		},
		next() {
			const context = getContext();
			go( getElement().ref, context.atEnd ? context.index : context.index + 1 );
		},
		go() {
			go( getElement().ref, getContext().slide );
		},
	},
	callbacks: {
		init() {
			const context = getContext();
			const track = trackOf( getElement().ref );
			measure( context, track );
			context.ready = true;
			const observer = new ResizeObserver( withScope( () => measure( context, track ) ) );
			observer.observe( track );
			return () => observer.disconnect();
		},
		update() {
			measure( getContext(), getElement().ref );
		},
	},
} );
