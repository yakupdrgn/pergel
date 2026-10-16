/**
 * Delivery estimate in the editor: the server describes it. Plain
 * script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var be = wp.blockEditor;

	wp.blocks.registerBlockType( 'pergel/delivery-estimate', {
		edit: function () {
			return el( 'div', be.useBlockProps(), el( wp.components.Disabled, null, el( wp.serverSideRender, { block: 'pergel/delivery-estimate', skipBlockSupportAttributes: true } ) ) );
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
