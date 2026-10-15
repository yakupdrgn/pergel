/**
 * Timeline block in the editor: a list of timeline items. Plain script, no
 * build step.
 */
( function () {
	'use strict';

	window.pergelBlocks.container( 'pergel/timeline', {
		tagName: 'ol',
		template: [ [ 'pergel/timeline-item' ], [ 'pergel/timeline-item' ], [ 'pergel/timeline-item' ] ],
	} );
}() );
