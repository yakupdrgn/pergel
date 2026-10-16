/**
 * Wishlist or compare list in the editor: a note about what the page shows,
 * and the sidebar choice of list. Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;

	wp.blocks.registerBlockType( 'pergel/shop-list', {
		edit: function ( props ) {
			var compare = 'compare' === props.attributes.kind;
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'List', 'pergel-engine' ) },
						el( c.SelectControl, {
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							label: __( 'Show', 'pergel-engine' ),
							value: props.attributes.kind,
							options: [
								{ value: 'wishlist', label: __( 'Wishlist', 'pergel-engine' ) },
								{ value: 'compare', label: __( 'Products to compare', 'pergel-engine' ) },
							],
							onChange: function ( value ) {
								props.setAttributes( { kind: value } );
							},
						} )
					)
				),
				el(
					'div',
					be.useBlockProps(),
					el(
						c.Placeholder,
						{
							icon: compare ? 'list-view' : 'heart',
							label: compare ? __( 'Products to compare', 'pergel-engine' ) : __( 'Wishlist', 'pergel-engine' ),
							instructions: compare
								? __( 'Visitors see the products they chose to compare here, side by side.', 'pergel-engine' )
								: __( 'Visitors see the products they saved here. Logged-in customers keep their wishlist in their account.', 'pergel-engine' ),
						}
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
