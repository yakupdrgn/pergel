/**
 * Header panel on the Template Part block that shows the header: sticky
 * mode and overlay. The server registers the same attributes and renders
 * the result; the editor canvas shows the header in place.
 *
 * Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.blockEditor || ! wp.compose ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var components = wp.components;
	var NAME = 'core/template-part';

	wp.hooks.addFilter( 'blocks.registerBlockType', 'pergel/header-attributes', function ( settings, name ) {
		if ( NAME !== name ) {
			return settings;
		}
		return Object.assign( {}, settings, {
			attributes: Object.assign( {}, settings.attributes, {
				pergelSticky: { type: 'string', 'enum': [ '', 'always', 'smart' ], 'default': '' },
				pergelOverlay: { type: 'boolean', 'default': false },
			} ),
		} );
	} );

	function useArea( attributes ) {
		return wp.data.useSelect(
			function ( select ) {
				if ( attributes.area ) {
					return attributes.area;
				}
				var core = select( 'core' );
				var theme = attributes.theme || ( core.getCurrentTheme() || {} ).stylesheet;
				if ( ! attributes.slug || ! theme ) {
					return null;
				}
				var part = core.getEditedEntityRecord( 'postType', 'wp_template_part', theme + '//' + attributes.slug );
				return part ? part.area : null;
			},
			[ attributes.area, attributes.slug, attributes.theme ]
		);
	}

	function Controls( props ) {
		var attributes = props.attributes;
		if ( 'header' !== useArea( attributes ) ) {
			return null;
		}
		return el(
			wp.blockEditor.InspectorControls,
			null,
			el(
				components.PanelBody,
				{ title: __( 'Header behaviour', 'pergel-engine' ) },
				el( components.SelectControl, {
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
					label: __( 'Sticky', 'pergel-engine' ),
					help: __( 'Keeps the header at the top of the screen while the page scrolls.', 'pergel-engine' ),
					value: attributes.pergelSticky || '',
					options: [
						{ value: '', label: __( 'Off', 'pergel-engine' ) },
						{ value: 'always', label: __( 'Always', 'pergel-engine' ) },
						{ value: 'smart', label: __( 'Hide while scrolling down', 'pergel-engine' ) },
					],
					onChange: function ( value ) {
						props.setAttributes( { pergelSticky: value } );
					},
				} ),
				el( 'div', { style: { height: '16px' } } ),
				el( components.ToggleControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Over the first section', 'pergel-engine' ),
					help: __( 'The header sits over the first section, without its background until the page scrolls. Use it on templates that open with a full-width image.', 'pergel-engine' ),
					checked: !! attributes.pergelOverlay,
					onChange: function ( value ) {
						props.setAttributes( { pergelOverlay: value } );
					},
				} )
			)
		);
	}

	wp.hooks.addFilter(
		'editor.BlockEdit',
		'pergel/header-controls',
		wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
			return function ( props ) {
				if ( NAME !== props.name ) {
					return el( BlockEdit, props );
				}
				return el( wp.element.Fragment, null, el( BlockEdit, props ), el( Controls, props ) );
			};
		}, 'withPergelHeaderControls' )
	);
}( window.wp ) );
