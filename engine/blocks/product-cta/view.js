/**
 * Product enquiry block: reports a click on a contact link to Google
 * Analytics as a generate_lead event, when the site has gtag or a Tag
 * Manager data layer. The quote form reports its own.
 */
import { store, getContext } from '@wordpress/interactivity';

store( 'pergel/product-cta', {
	actions: {
		lead() {
			const source = getContext().source;
			if ( 'function' === typeof window.gtag ) {
				window.gtag( 'event', 'generate_lead', { lead_source: source } );
			} else if ( Array.isArray( window.dataLayer ) ) {
				window.dataLayer.push( { event: 'generate_lead', lead_source: source } );
			}
		},
	},
} );
