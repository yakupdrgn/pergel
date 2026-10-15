/**
 * Editor side of the engine blocks that hold core blocks. The server
 * renders these blocks (render.php wraps the saved inner blocks), so the
 * editor only places the inner blocks and the block's own settings.
 *
 * Plain script, no build step. Each block's index.js calls
 * window.pergelBlocks.container() with its options.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.blockEditor ) {
		return;
	}

	var el = wp.element.createElement;
	var be = wp.blockEditor;

	/**
	 * Registers the editor side of a block whose content is inner blocks.
	 *
	 * @param {string} name    Block name, registered on the server.
	 * @param {Object} options tagName, template, templateLock, orientation,
	 *                         className( attributes ) and controls( props ).
	 */
	function container( name, options ) {
		wp.blocks.registerBlockType( name, {
			edit: function ( props ) {
				var blockProps = be.useBlockProps( {
					className: options.className ? options.className( props.attributes ) : undefined,
				} );
				var innerProps = be.useInnerBlocksProps( blockProps, {
					template: options.template,
					templateLock: options.templateLock,
					orientation: options.orientation,
				} );
				return el(
					wp.element.Fragment,
					null,
					options.controls ? options.controls( props ) : null,
					el( options.tagName || 'div', innerProps )
				);
			},
			save: function () {
				return el( be.InnerBlocks.Content );
			},
		} );
	}

	window.pergelBlocks = { container: container };
}( window.wp ) );
