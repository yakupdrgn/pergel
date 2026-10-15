/**
 * Table of contents in the editor: the server's list for the saved content
 * and the depth setting. Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;

	wp.blocks.registerBlockType( 'pergel/table-of-contents', {
		edit: function ( props ) {
			var postId = props.context.postId;
			return el(
				'div',
				be.useBlockProps(),
				el(
					be.InspectorControls,
					null,
					el(
						wp.components.PanelBody,
						{ title: __( 'Headings', 'pergel-engine' ) },
						el( wp.components.SelectControl, {
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
							label: __( 'Headings to list', 'pergel-engine' ),
							help: __( 'The list follows the saved content.', 'pergel-engine' ),
							value: String( props.attributes.maxLevel ),
							options: [
								{ value: '2', label: __( 'Level 2', 'pergel-engine' ) },
								{ value: '3', label: __( 'Levels 2 and 3', 'pergel-engine' ) },
								{ value: '4', label: __( 'Levels 2 to 4', 'pergel-engine' ) },
							],
							onChange: function ( value ) {
								props.setAttributes( { maxLevel: parseInt( value, 10 ) } );
							},
						} )
					)
				),
				el( wp.serverSideRender, {
					block: 'pergel/table-of-contents',
					attributes: props.attributes,
					urlQueryArgs: postId ? { post_id: postId } : {},
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
