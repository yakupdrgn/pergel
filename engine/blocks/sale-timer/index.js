/**
 * Flash sale dates in the editor: a sample of what the page shows, since
 * the dates come from the campaign; the sidebar turns the countdown on or
 * off. Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;

	wp.blocks.registerBlockType( 'pergel/sale-timer', {
		edit: function ( props ) {
			var parts = [
				el( 'span', { key: 'discount', className: 'wp-block-pergel-sale-timer__discount' }, wp.i18n.sprintf( /* translators: %d: discount in percent. */ __( '%d%% off', 'pergel-engine' ), 20 ) ),
				el( 'span', { key: 'dates', className: 'wp-block-pergel-sale-timer__dates' }, __( 'Start – end of the campaign', 'pergel-engine' ) ),
			];
			if ( props.attributes.countdown ) {
				parts.push(
					el(
						'span',
						{ key: 'count', className: 'wp-block-pergel-sale-timer__count' },
						el( 'span', { className: 'wp-block-pergel-sale-timer__label' }, __( 'Ends in', 'pergel-engine' ) ),
						el( 'span', { className: 'pergel-countdown' }, el( 'span', { className: 'pergel-countdown__parts' }, [ 'd', 'h', 'm', 's' ].map( function ( key ) {
							return el( 'span', { key: key, className: 'pergel-countdown__part' }, el( 'b', null, '00' ) );
						} ) ) )
					)
				);
			}
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Countdown', 'pergel-engine' ) },
						el( c.ToggleControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Show the countdown', 'pergel-engine' ),
							help: __( 'To the start before the campaign, to the end while it runs.', 'pergel-engine' ),
							checked: props.attributes.countdown,
							onChange: function ( value ) {
								props.setAttributes( { countdown: value } );
							},
						} )
					)
				),
				el( 'div', be.useBlockProps(), parts )
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
