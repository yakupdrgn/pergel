/**
 * Pricing plan in the editor: name, price, description, features and a
 * button (core blocks), and the highlight switch. Plain script, no build
 * step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	window.pergelBlocks.container( 'pergel/pricing-plan', {
		className: function ( attributes ) {
			return attributes.featured ? 'is-featured' : undefined;
		},
		template: [
			[ 'core/heading', { level: 3, placeholder: __( 'Plan name', 'pergel-engine' ) } ],
			[ 'core/paragraph', { fontSize: 'x-large', placeholder: __( 'Price', 'pergel-engine' ) } ],
			[ 'core/paragraph', { placeholder: __( 'Who the plan is for', 'pergel-engine' ) } ],
			[ 'core/list', {}, [ [ 'core/list-item', { placeholder: __( 'What is included', 'pergel-engine' ) } ] ] ],
			[ 'core/buttons', {}, [ [ 'core/button', { placeholder: __( 'Button text', 'pergel-engine' ) } ] ] ],
		],
		controls: function ( props ) {
			return el(
				wp.blockEditor.InspectorControls,
				null,
				el(
					wp.components.PanelBody,
					{ title: __( 'Plan', 'pergel-engine' ) },
					el( wp.components.ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Highlight this plan', 'pergel-engine' ),
						help: __( 'Gives the plan an accent border. Say why in its text too, for example "Most popular".', 'pergel-engine' ),
						checked: !! props.attributes.featured,
						onChange: function ( value ) {
							props.setAttributes( { featured: value } );
						},
					} )
				)
			);
		},
	} );
}( window.wp ) );
