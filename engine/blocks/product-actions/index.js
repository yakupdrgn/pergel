/**
 * Wishlist and compare buttons in the editor: the server draws them for the
 * product in context, the sidebar chooses icons or labelled buttons. Plain
 * script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;

	wp.blocks.registerBlockType( 'pergel/product-actions', {
		edit: function ( props ) {
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Buttons', 'pergel-engine' ) },
						el( c.ToggleControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Show labels', 'pergel-engine' ),
							help: __( 'Words next to the icons, as on the product page. Product cards show icons only.', 'pergel-engine' ),
							checked: !! props.attributes.labels,
							onChange: function ( value ) {
								props.setAttributes( { labels: value } );
							},
						} )
					)
				),
				el(
					'div',
					be.useBlockProps(),
					el( c.Disabled, null, el( wp.serverSideRender, { block: 'pergel/product-actions', attributes: props.attributes, skipBlockSupportAttributes: true, urlQueryArgs: { post_id: props.context.postId } } ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
