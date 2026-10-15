/**
 * Product enquiry block in the editor: the server draws the block for the
 * product in context, the sidebar chooses what it offers. Plain script, no
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

	wp.blocks.registerBlockType( 'pergel/product-cta', {
		edit: function ( props ) {
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Product enquiry', 'pergel-engine' ) },
						toggle(
							props,
							'quote',
							__( 'Quote form', 'pergel-engine' ),
							__( 'On products in quote mode. Requests are kept under Pergel → Form messages.', 'pergel-engine' )
						),
						toggle(
							props,
							'whatsapp',
							__( 'WhatsApp link', 'pergel-engine' ),
							__( 'Needs the WhatsApp number under Pergel → Settings → Shop.', 'pergel-engine' )
						),
						toggle( props, 'phone', __( 'Phone link', 'pergel-engine' ), __( 'The phone number of the brand.', 'pergel-engine' ) ),
						toggle(
							props,
							'contact',
							__( 'Contact page link', 'pergel-engine' ),
							__( 'Needs the contact page under Pergel → Settings → Shop.', 'pergel-engine' )
						),
						toggle(
							props,
							'onSale',
							__( 'Links on products for sale too', 'pergel-engine' ),
							__( 'Otherwise the block shows only where a product has no price or cart.', 'pergel-engine' )
						)
					)
				),
				el(
					'div',
					be.useBlockProps(),
					el( c.Disabled, null, el( wp.serverSideRender, { block: 'pergel/product-cta', attributes: props.attributes, skipBlockSupportAttributes: true, urlQueryArgs: { post_id: props.context.postId } } ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
