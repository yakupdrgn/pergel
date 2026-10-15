/**
 * Counter block in the editor: a number and a label (core paragraphs) and
 * the count duration. Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	window.pergelBlocks.container( 'pergel/counter', {
		templateLock: 'all',
		template: [
			[ 'core/paragraph', { fontSize: 'xx-large', placeholder: __( 'Number, e.g. 250+', 'pergel-engine' ) } ],
			[ 'core/paragraph', { placeholder: __( 'What it counts', 'pergel-engine' ) } ],
		],
		controls: function ( props ) {
			return el(
				wp.blockEditor.InspectorControls,
				null,
				el(
					wp.components.PanelBody,
					{ title: __( 'Counter', 'pergel-engine' ) },
					el( wp.components.RangeControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Count duration (milliseconds)', 'pergel-engine' ),
						help: __( 'The number counts up from zero when it scrolls into view. 0 shows it at once.', 'pergel-engine' ),
						min: 0,
						max: 5000,
						step: 100,
						value: props.attributes.duration,
						onChange: function ( value ) {
							props.setAttributes( { duration: 'number' === typeof value ? value : 1500 } );
						},
					} )
				)
			);
		},
	} );
}( window.wp ) );
