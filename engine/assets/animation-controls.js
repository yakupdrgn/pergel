/**
 * Scroll animation option on section and content blocks. The server
 * registers the same attribute, adds the classes and loads the CSS; the
 * editor shows blocks still, the animation plays on the published page.
 *
 * Plain script, no build step.
 */
( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.blockEditor || ! wp.compose || ! config ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var components = wp.components;
	var ATTRIBUTE = 'pergelAnimation';
	var EFFECTS = [ '', 'fade', 'rise', 'zoom', 'slide' ];
	var blocks = config.blocks || [];

	function takes( name ) {
		return blocks.indexOf( name ) !== -1;
	}

	wp.hooks.addFilter( 'blocks.registerBlockType', 'pergel/animation-attribute', function ( settings, name ) {
		if ( ! takes( name ) ) {
			return settings;
		}
		var attributes = {};
		attributes[ ATTRIBUTE ] = { type: 'string', 'enum': EFFECTS, 'default': '' };
		return Object.assign( {}, settings, {
			attributes: Object.assign( {}, settings.attributes, attributes ),
		} );
	} );

	function Controls( props ) {
		var value = props.attributes[ ATTRIBUTE ] || '';
		return el(
			wp.blockEditor.InspectorControls,
			null,
			el(
				components.PanelBody,
				{ title: __( 'Scroll animation', 'pergel-engine' ), initialOpen: '' !== value },
				el( components.SelectControl, {
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
					label: __( 'Effect', 'pergel-engine' ),
					help: __( 'Plays on the published page as the block scrolls into view. Visitors who prefer reduced motion see the block without it.', 'pergel-engine' ),
					value: value,
					options: [
						{ value: '', label: __( 'None', 'pergel-engine' ) },
						{ value: 'fade', label: __( 'Fade in', 'pergel-engine' ) },
						{ value: 'rise', label: __( 'Rise', 'pergel-engine' ) },
						{ value: 'zoom', label: __( 'Zoom in', 'pergel-engine' ) },
						{ value: 'slide', label: __( 'Slide in from the side', 'pergel-engine' ) },
					],
					onChange: function ( next ) {
						var update = {};
						update[ ATTRIBUTE ] = next;
						props.setAttributes( update );
					},
				} )
			)
		);
	}

	wp.hooks.addFilter(
		'editor.BlockEdit',
		'pergel/animation-controls',
		wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
			return function ( props ) {
				if ( ! takes( props.name ) || ! props.isSelected ) {
					return el( BlockEdit, props );
				}
				return el( wp.element.Fragment, null, el( BlockEdit, props ), el( Controls, props ) );
			};
		}, 'withPergelAnimationControls' )
	);
}( window.wp, window.pergelAnimation ) );
