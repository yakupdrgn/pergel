/**
 * Slide in the editor: any blocks, a paragraph to start with. Plain
 * script, no build step.
 */
( function ( wp ) {
	'use strict';

	var __ = wp.i18n.__;

	window.pergelBlocks.container( 'pergel/carousel-slide', {
		template: [ [ 'core/paragraph', { placeholder: __( 'Slide content', 'pergel-engine' ) } ] ],
	} );
}( window.wp ) );
