/**
 * Timeline item in the editor: a date, a title and a text (core blocks).
 * Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var __ = wp.i18n.__;

	window.pergelBlocks.container( 'pergel/timeline-item', {
		tagName: 'li',
		template: [
			[ 'core/paragraph', { fontSize: 'small', placeholder: __( 'Date', 'pergel-engine' ) } ],
			[ 'core/heading', { level: 3, placeholder: __( 'Milestone', 'pergel-engine' ) } ],
			[ 'core/paragraph', { placeholder: __( 'What happened', 'pergel-engine' ) } ],
		],
	} );
}( window.wp ) );
