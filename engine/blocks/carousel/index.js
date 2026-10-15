/**
 * Carousel block in the editor: the slides side by side in a row that
 * scrolls, and the carousel's settings. Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;

	window.pergelBlocks.container( 'pergel/carousel', {
		orientation: 'horizontal',
		template: [ [ 'pergel/carousel-slide' ], [ 'pergel/carousel-slide' ] ],
		className: function ( attributes ) {
			return attributes.perView > 1 ? 'has-several-per-view' : undefined;
		},
		style: function ( attributes ) {
			return { '--pergel-carousel-per-view': attributes.perView };
		},
		controls: function ( props ) {
			var a = props.attributes;
			return el(
				wp.blockEditor.InspectorControls,
				null,
				el(
					c.PanelBody,
					{ title: __( 'Carousel', 'pergel-engine' ) },
					el( c.TextControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Name for screen readers', 'pergel-engine' ),
						help: __( 'What the slides are, e.g. "Customer stories". Leave empty for "Carousel".', 'pergel-engine' ),
						value: a.label,
						onChange: function ( value ) {
							props.setAttributes( { label: value } );
						},
					} ),
					el( 'div', { style: { height: '16px' } } ),
					el( c.RangeControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Slides in view', 'pergel-engine' ),
						help: __( 'On phones a single slide is in view, with the edge of the next one.', 'pergel-engine' ),
						min: 1,
						max: 4,
						value: a.perView,
						onChange: function ( value ) {
							props.setAttributes( { perView: 'number' === typeof value ? value : 1 } );
						},
					} ),
					el( 'div', { style: { height: '16px' } } ),
					el( c.ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Show dots', 'pergel-engine' ),
						checked: !! a.dots,
						onChange: function ( value ) {
							props.setAttributes( { dots: value } );
						},
					} )
				)
			);
		},
	} );
}( window.wp ) );
