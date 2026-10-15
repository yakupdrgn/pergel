/**
 * Mega menu block in the editor: the menu item's text in place, and the
 * template part that holds the panel in the sidebar. The panel is edited
 * as a template part (Site Editor → Patterns → Mega menu). Plain script,
 * no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;
	var AREA = 'pergel-mega-menu';

	// The Navigation block lists the blocks it can hold; add this one.
	wp.hooks.addFilter( 'blocks.registerBlockType', 'pergel/mega-menu', function ( settings, name ) {
		if ( 'core/navigation' !== name || ! Array.isArray( settings.allowedBlocks ) || settings.allowedBlocks.indexOf( 'pergel/mega-menu' ) >= 0 ) {
			return settings;
		}
		return Object.assign( {}, settings, { allowedBlocks: settings.allowedBlocks.concat( [ 'pergel/mega-menu' ] ) } );
	} );

	wp.blocks.registerBlockType( 'pergel/mega-menu', {
		edit: function ( props ) {
			var a = props.attributes;
			var parts = wp.data.useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'wp_template_part', { per_page: -1 } );
			}, [] );
			var options = ( parts || [] )
				.filter( function ( part ) {
					return AREA === part.area;
				} )
				.map( function ( part ) {
					return { value: part.slug, label: ( part.title && part.title.rendered ) || part.slug };
				} );
			if ( a.menu && ! options.some( function ( option ) {
				return option.value === a.menu;
			} ) ) {
				options.unshift( { value: a.menu, label: a.menu } );
			}
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Mega menu', 'pergel-engine' ) },
						el( c.SelectControl, {
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
							label: __( 'Panel', 'pergel-engine' ),
							help: __( 'A template part in the Mega menu area. Edit it under Site Editor → Patterns.', 'pergel-engine' ),
							value: a.menu,
							options: options,
							onChange: function ( value ) {
								props.setAttributes( { menu: value } );
							},
						} )
					)
				),
				el(
					'li',
					be.useBlockProps( { className: 'wp-block-navigation-item has-child' } ),
					el(
						'span',
						{ className: 'wp-block-navigation-item__content' },
						el( be.RichText, {
							tagName: 'span',
							className: 'wp-block-navigation-item__label',
							identifier: 'label',
							allowedFormats: [],
							withoutInteractiveFormatting: true,
							placeholder: __( 'Menu', 'pergel-engine' ),
							'aria-label': __( 'Menu item text', 'pergel-engine' ),
							value: a.label,
							onChange: function ( value ) {
								props.setAttributes( { label: value } );
							},
						} ),
						el(
							'span',
							{ className: 'wp-block-pergel-mega-menu__icon', 'aria-hidden': true },
							el(
								'svg',
								{ viewBox: '0 0 12 12', width: 12, height: 12, focusable: false },
								el( 'path', { d: 'M1.5 4L6 8l4.5-4', fill: 'none', stroke: 'currentColor', strokeWidth: 1.5 } )
							)
						)
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
