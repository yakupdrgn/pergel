/**
 * Logo marquee in the editor: the logos (core images) in a still row and
 * the loop duration. Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	window.pergelBlocks.container( 'pergel/logo-marquee', {
		orientation: 'horizontal',
		template: [ [ 'core/image' ], [ 'core/image' ], [ 'core/image' ], [ 'core/image' ] ],
		controls: function ( props ) {
			return el(
				wp.blockEditor.InspectorControls,
				null,
				el(
					wp.components.PanelBody,
					{ title: __( 'Movement', 'pergel-engine' ) },
					el( wp.components.RangeControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Seconds for one loop', 'pergel-engine' ),
						help: __( 'Higher is slower. Visitors can pause the row; with reduced motion it stands still.', 'pergel-engine' ),
						min: 5,
						max: 120,
						value: props.attributes.duration,
						onChange: function ( value ) {
							props.setAttributes( { duration: 'number' === typeof value ? value : 30 } );
						},
					} )
				)
			);
		},
	} );
}( window.wp ) );
