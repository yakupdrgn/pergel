/**
 * Form block: sends the form to the REST endpoint and shows the answer in
 * place, with an error under each field the server turned back. The
 * browser's own checks (required, email) run first. Without this script
 * the form posts to admin-post.php and the page comes back with the result.
 */
import { store, getContext, getElement, withSyncEvent } from '@wordpress/interactivity';

const { state } = store( 'pergel/form', {
	state: {
		/** The error of the field in context. */
		get error() {
			const context = getContext();
			return ( context.field && context.errors[ context.field ] ) || '';
		},
		get invalid() {
			return '' !== state.error;
		},
	},
	actions: {
		submit: withSyncEvent( function* ( event ) {
			event.preventDefault();
			const context = getContext();
			const { ref } = getElement();
			if ( context.sending ) {
				return;
			}
			context.sending = true;
			context.message = '';
			context.errors = {};
			let data = null;
			try {
				const response = yield fetch( state.endpoint, {
					method: 'POST',
					body: new FormData( ref ),
					credentials: 'omit',
					headers: { Accept: 'application/json' },
				} );
				data = yield response.json();
			} catch ( error ) {
				data = null;
			}
			context.sending = false;
			if ( ! data || 'string' !== typeof data.message ) {
				context.failed = true;
				context.message = state.offline;
				return;
			}
			context.failed = ! data.ok;
			context.errors = data.errors || {};
			context.message = data.ok && context.success ? context.success : data.message;
			if ( data.ok ) {
				ref.reset();
				if ( window.turnstile ) {
					window.turnstile.reset();
				}
				return;
			}
			const first = Object.keys( context.errors )[ 0 ];
			const field = first && ref.elements.namedItem( first );
			if ( field && field.focus ) {
				field.focus();
			}
		} ),
	},
} );
