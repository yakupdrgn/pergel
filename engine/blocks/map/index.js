/**
 * Map block in the editor: the address, the zoom and an optional image of
 * the location (a core image). Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	window.pergelBlocks.container( 'pergel/map', {
		template: [ [ 'core/image' ] ],
		controls: function ( props ) {
			return el(
				wp.blockEditor.InspectorControls,
				null,
				el(
					wp.components.PanelBody,
					{ title: __( 'Location', 'pergel-engine' ) },
					el( wp.components.TextControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Address or place', 'pergel-engine' ),
						help: __( 'Searched on Google Maps. Leave empty to use the address from the brand settings.', 'pergel-engine' ),
						value: props.attributes.query,
						onChange: function ( value ) {
							props.setAttributes( { query: value } );
						},
					} ),
					el( 'div', { style: { height: '16px' } } ),
					el( wp.components.RangeControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Zoom', 'pergel-engine' ),
						min: 1,
						max: 21,
						value: props.attributes.zoom,
						onChange: function ( value ) {
							props.setAttributes( { zoom: 'number' === typeof value ? value : 14 } );
						},
					} )
				)
			);
		},
	} );
}( window.wp ) );
