/**
 * Pricing table in the editor: pricing plans side by side. Plain script, no
 * build step.
 */
( function () {
	'use strict';

	window.pergelBlocks.container( 'pergel/pricing-table', {
		orientation: 'horizontal',
		template: [ [ 'pergel/pricing-plan' ], [ 'pergel/pricing-plan', { featured: true } ], [ 'pergel/pricing-plan' ] ],
	} );
}() );
