/**
 * Wishlist and compare links in the editor: the server draws them. Plain
 * script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var be = wp.blockEditor;

	wp.blocks.registerBlockType( 'pergel/shop-links', {
		edit: function () {
			return el( 'div', be.useBlockProps(), el( wp.components.Disabled, null, el( wp.serverSideRender, { block: 'pergel/shop-links', skipBlockSupportAttributes: true } ) ) );
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
