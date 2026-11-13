/**
 * Filter pages (Pergel\Woo\FilterPages): when the router has drawn a
 * listing whose filters make a filter page, the address bar shows that
 * page's own address, keeping other parameters such as per_page.
 */

import { store, getElement } from '@wordpress/interactivity';

const FILTER =
	/^(?:filter_[a-z0-9_-]+|query_type_[a-z0-9_-]+|min_price|max_price|rating_filter)$/;

store( 'pergel/filter-pages', {
	callbacks: {
		address() {
			const { ref } = getElement();
			const own = ref && ref.dataset.pergelUrl;
			if ( ! own ) {
				return;
			}
			const here = new URL( window.location.href );
			const next = new URL( own, here );
			for ( const [ key, value ] of here.searchParams ) {
				if ( ! FILTER.test( key ) && key !== 'paged' ) {
					next.searchParams.append( key, value );
				}
			}
			if ( next.href !== here.href ) {
				window.history.replaceState(
					window.history.state,
					'',
					next.href
				);
			}
		},
	},
} );
