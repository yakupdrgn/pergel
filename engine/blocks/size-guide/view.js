/**
 * Size guide (Pergel\Woo\SizeGuide): the button opens the dialog with an
 * invoker command and the dialog closes itself on Esc and outside clicks.
 * This only stands in for browsers that do not know these yet.
 */
const commands = 'command' in HTMLButtonElement.prototype;
const closedBy = 'closedBy' in HTMLDialogElement.prototype;

document.querySelectorAll( '[data-pergel-size-guide]' ).forEach( ( block ) => {
	const dialog = block.querySelector( 'dialog' );
	if ( ! dialog ) {
		return;
	}
	if ( ! commands ) {
		block.querySelector( '.wp-block-pergel-size-guide__open' ).addEventListener( 'click', () => dialog.showModal() );
		block.querySelector( '.wp-block-pergel-size-guide__close' ).addEventListener( 'click', () => dialog.close() );
	}
	if ( ! closedBy ) {
		// The panel fills the dialog, so only the backdrop is the dialog itself.
		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );
	}
} );
