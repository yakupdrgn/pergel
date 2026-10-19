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

	// Own fields: the server cleans them and leaves out one without a label.
	function ownFields( props ) {
		var fields = props.attributes.fields || [];

		function change( index, part ) {
			props.setAttributes( {
				fields: fields.map( function ( field, at ) {
					return at === index ? Object.assign( {}, field, part ) : field;
				} ),
			} );
		}

		function move( index, by ) {
			var next = fields.slice();
			var field = next.splice( index, 1 )[ 0 ];
			next.splice( index + by, 0, field );
			props.setAttributes( { fields: next } );
		}

		return el(
			c.PanelBody,
			{ title: __( 'Extra fields', 'pergel-engine' ), initialOpen: fields.length > 0 },
			el( 'p', { className: 'components-base-control__help' }, __( 'Shown before the message field, in this order.', 'pergel-engine' ) ),
			fields.map( function ( field, index ) {
				return el(
					'fieldset',
					{ key: index, style: { borderTop: '1px solid #ddd', margin: '0 0 16px', paddingTop: '12px' } },
					el( c.TextControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Label', 'pergel-engine' ),
						value: field.label || '',
						onChange: function ( value ) {
							change( index, { label: value } );
						},
					} ),
					el( c.SelectControl, {
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						label: __( 'Type', 'pergel-engine' ),
						value: field.type || 'text',
						options: [
							{ value: 'text', label: __( 'Short text', 'pergel-engine' ) },
							{ value: 'textarea', label: __( 'Long text', 'pergel-engine' ) },
							{ value: 'tel', label: __( 'Phone', 'pergel-engine' ) },
							{ value: 'select', label: __( 'List', 'pergel-engine' ) },
						],
						onChange: function ( value ) {
							change( index, { type: value } );
						},
					} ),
					'select' === field.type
						? el( c.TextareaControl, {
							__nextHasNoMarginBottom: true,
							label: __( 'Choices', 'pergel-engine' ),
							help: __( 'One per line.', 'pergel-engine' ),
							value: ( field.options || [] ).join( '\n' ),
							onChange: function ( value ) {
								change( index, { options: value.split( '\n' ) } );
							},
						} )
						: null,
					el( c.ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Required', 'pergel-engine' ),
						checked: !! field.required,
						onChange: function ( value ) {
							change( index, { required: value } );
						},
					} ),
					el(
						c.Flex,
						{ justify: 'flex-start' },
						el( c.Button, {
							variant: 'secondary',
							size: 'small',
							disabled: 0 === index,
							onClick: function () {
								move( index, -1 );
							},
						}, __( 'Move up', 'pergel-engine' ) ),
						el( c.Button, {
							variant: 'secondary',
							size: 'small',
							disabled: index === fields.length - 1,
							onClick: function () {
								move( index, 1 );
							},
						}, __( 'Move down', 'pergel-engine' ) ),
						el( c.Button, {
							variant: 'tertiary',
							size: 'small',
							isDestructive: true,
							onClick: function () {
								props.setAttributes( {
									fields: fields.filter( function ( unused, at ) {
										return at !== index;
									} ),
								} );
							},
						}, __( 'Remove', 'pergel-engine' ) )
					)
				);
			} ),
			el( c.Button, {
				variant: 'secondary',
				disabled: fields.length >= 10,
				onClick: function () {
					props.setAttributes( { fields: fields.concat( [ { label: '', type: 'text', required: false } ] ) } );
				},
			}, __( 'Add field', 'pergel-engine' ) )
		);
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
					ownFields( props ),
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
