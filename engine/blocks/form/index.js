/**
 * Form block in the editor: the server draws the form (a preview that
 * cannot be typed in), the sidebar holds its settings. Plain script, no
 * build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;

	function toggle( props, name, label, help ) {
		return el( c.ToggleControl, {
			__nextHasNoMarginBottom: true,
			label: label,
			help: help,
			checked: !! props.attributes[ name ],
			onChange: function ( value ) {
				var change = {};
				change[ name ] = value;
				props.setAttributes( change );
			},
		} );
	}

	wp.blocks.registerBlockType( 'pergel/form', {
		edit: function ( props ) {
			var a = props.attributes;
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Form', 'pergel-engine' ) },
						el( c.SelectControl, {
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
							label: __( 'Kind', 'pergel-engine' ),
							value: a.kind,
							options: [
								{ value: 'contact', label: __( 'Contact', 'pergel-engine' ) },
								{ value: 'quote', label: __( 'Quote request', 'pergel-engine' ) },
							],
							onChange: function ( value ) {
								props.setAttributes( { kind: value } );
							},
						} ),
						toggle( props, 'phone', __( 'Phone field', 'pergel-engine' ) ),
						toggle( props, 'company', __( 'Company field', 'pergel-engine' ) ),
						toggle(
							props,
							'consent',
							__( 'Ask for consent', 'pergel-engine' ),
							__( 'A required checkbox that links to the privacy policy page (Settings → Privacy).', 'pergel-engine' )
						)
					),
					el(
						c.PanelBody,
						{ title: __( 'Texts', 'pergel-engine' ), initialOpen: false },
						el( c.TextControl, {
							__next40pxDefaultSize: true,
							__nextHasNoMarginBottom: true,
							label: __( 'Button text', 'pergel-engine' ),
							help: __( 'Leave empty for "Send message" or "Request a quote".', 'pergel-engine' ),
							value: a.submitLabel,
							onChange: function ( value ) {
								props.setAttributes( { submitLabel: value } );
							},
						} ),
						el( c.TextareaControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Thank-you text', 'pergel-engine' ),
							help: __( 'Shown after a message is sent. Leave empty for the standard text.', 'pergel-engine' ),
							value: a.successMessage,
							onChange: function ( value ) {
								props.setAttributes( { successMessage: value } );
							},
						} )
					)
				),
				el(
					'div',
					be.useBlockProps(),
					el( c.Disabled, null, el( wp.serverSideRender, { block: 'pergel/form', attributes: a, skipBlockSupportAttributes: true } ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
