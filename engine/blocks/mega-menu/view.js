/**
 * Mega menu block: the item's button opens and closes the panel. Escape
 * closes it (only it, not the mobile overlay menu) and puts the focus back
 * on the button; a click outside or
 * moving the focus out of the item closes it too. Without this script the
 * panel opens while the pointer or the focus is in the item (CSS).
 */
import { store, getContext, getElement, withSyncEvent } from '@wordpress/interactivity';

const toggleOf = ( element ) => element.querySelector( ':scope > .wp-block-pergel-mega-menu__toggle' );

store( 'pergel/mega-menu', {
	actions: {
		toggle() {
			const context = getContext();
			context.open = ! context.open;
		},
		keydown: withSyncEvent( ( event ) => {
			const context = getContext();
			if ( 'Escape' !== event.key || ! context.open ) {
				return;
			}
			// Only the panel closes, not the mobile overlay menu around it.
			event.preventDefault();
			event.stopPropagation();
			context.open = false;
			toggleOf( getElement().ref ).focus();
		} ),
		focusout( event ) {
			const context = getContext();
			const { ref } = getElement();
			if ( context.open && event.relatedTarget && ! ref.contains( event.relatedTarget ) ) {
				context.open = false;
			}
		},
		outside( event ) {
			const context = getContext();
			if ( context.open && ! getElement().ref.contains( event.target ) ) {
				context.open = false;
			}
		},
	},
	callbacks: {
		init() {
			getContext().ready = true;
		},
	},
} );
