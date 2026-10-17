/**
 * Term image in the editor: a tile in the chosen shape, since the image
 * belongs to each term the template repeats. Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var be = wp.blockEditor;
	var c = wp.components;
	var __ = wp.i18n.__;

	wp.blocks.registerBlockType( 'pergel/term-image', {
		edit: function ( props ) {
			var a = props.attributes;
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Settings', 'pergel-engine' ) },
						el( c.SelectControl, {
							label: __( 'Aspect ratio', 'pergel-engine' ),
							value: a.aspectRatio,
							options: [
								{ value: '1', label: __( 'Square', 'pergel-engine' ) },
								{ value: '4/3', label: __( 'Landscape', 'pergel-engine' ) },
								{ value: '3/4', label: __( 'Portrait', 'pergel-engine' ) },
								{ value: '16/9', label: __( 'Wide', 'pergel-engine' ) },
							],
							onChange: function ( value ) {
								props.setAttributes( { aspectRatio: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( c.ToggleControl, {
							label: __( 'Link to the category', 'pergel-engine' ),
							checked: a.isLink,
							onChange: function ( value ) {
								props.setAttributes( { isLink: value } );
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el(
					'figure',
					be.useBlockProps(),
					el( 'span', { className: 'wp-block-pergel-term-image__placeholder', style: { aspectRatio: a.aspectRatio } } )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
