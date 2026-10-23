/**
 * Recently viewed products in the editor: the server describes it, the
 * sidebar sets the number and the title. Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;

	wp.blocks.registerBlockType( 'pergel/recently-viewed', {
		edit: function ( props ) {
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Products', 'pergel-engine' ) },
						el( c.TextControl, {
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							label: __( 'Title', 'pergel-engine' ),
							placeholder: __( 'Recently viewed', 'pergel-engine' ),
							value: props.attributes.title,
							onChange: function ( value ) {
								props.setAttributes( { title: value } );
							},
						} ),
						el( c.RangeControl, {
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							label: __( 'Most products', 'pergel-engine' ),
							min: 1,
							max: 12,
							value: props.attributes.count,
							onChange: function ( value ) {
								props.setAttributes( { count: value || 4 } );
							},
						} )
					)
				),
				el( 'div', be.useBlockProps(), el( c.Disabled, null, el( wp.serverSideRender, { block: 'pergel/recently-viewed', attributes: props.attributes, skipBlockSupportAttributes: true } ) ) )
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
