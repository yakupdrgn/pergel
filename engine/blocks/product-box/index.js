/**
 * Product summary box in the editor: the server draws it for the product
 * being edited, the sidebar picks what it shows. Plain script, no build
 * step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;

	wp.blocks.registerBlockType( 'pergel/product-box', {
		edit: function ( props ) {
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Box', 'pergel-engine' ) },
						el( c.SelectControl, {
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							label: __( 'Shows', 'pergel-engine' ),
							value: props.attributes.box,
							options: [
								{ value: 'also', label: __( 'You may also like', 'pergel-engine' ) },
								{ value: 'facts', label: __( 'Product facts', 'pergel-engine' ) },
								{ value: 'trust', label: __( 'Safe payment', 'pergel-engine' ) },
							],
							onChange: function ( value ) {
								props.setAttributes( { box: value } );
							},
						} )
					)
				),
				el(
					'div',
					be.useBlockProps(),
					el(
						c.Disabled,
						null,
						el( wp.serverSideRender, {
							block: 'pergel/product-box',
							attributes: props.attributes,
							urlQueryArgs: props.context.postId ? { post_id: props.context.postId } : {},
							skipBlockSupportAttributes: true,
							EmptyResponsePlaceholder: function () {
								return el( c.Placeholder, { icon: 'index-card', label: __( 'Product summary box', 'pergel-engine' ), instructions: __( 'Shows on product pages, for the product on screen.', 'pergel-engine' ) } );
							},
						} )
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
