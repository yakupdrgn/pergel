/**
 * Running flash sale in the editor: the server draws it, the sidebar picks
 * the campaign, the number of products and the title. Plain script, no
 * build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var c = wp.components;
	var be = wp.blockEditor;

	wp.blocks.registerBlockType( 'pergel/sale-current', {
		edit: function ( props ) {
			var sales = wp.data.useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'pergel_sale', { per_page: 50, status: 'publish', _fields: 'id,title' } );
			}, [] );
			var options = [ { value: 0, label: __( 'The one that ends soonest', 'pergel-engine' ) } ];
			( sales || [] ).forEach( function ( sale ) {
				options.push( { value: sale.id, label: sale.title && sale.title.rendered ? wp.htmlEntities.decodeEntities( sale.title.rendered ) : '#' + sale.id } );
			} );
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Campaign', 'pergel-engine' ) },
						el( c.SelectControl, {
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							label: __( 'Campaign', 'pergel-engine' ),
							help: __( 'A chosen campaign shows only while it runs.', 'pergel-engine' ),
							value: props.attributes.sale,
							options: options,
							onChange: function ( value ) {
								props.setAttributes( { sale: parseInt( value, 10 ) || 0 } );
							},
						} ),
						el( c.TextControl, {
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							label: __( 'Title', 'pergel-engine' ),
							placeholder: __( 'The campaign name', 'pergel-engine' ),
							value: props.attributes.title,
							onChange: function ( value ) {
								props.setAttributes( { title: value } );
							},
						} ),
						el( c.RangeControl, {
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							label: __( 'Products', 'pergel-engine' ),
							min: 1,
							max: 12,
							value: props.attributes.count,
							onChange: function ( value ) {
								props.setAttributes( { count: value || 4 } );
							},
						} )
					)
				),
				el(
					'div',
					be.useBlockProps(),
					el(
						c.Disabled,
						null,
						el( wp.serverSideRender, {
							block: 'pergel/sale-current',
							attributes: props.attributes,
							skipBlockSupportAttributes: true,
							EmptyResponsePlaceholder: function () {
								return el( c.Placeholder, { icon: 'megaphone', label: __( 'Running flash sale', 'pergel-engine' ), instructions: __( 'No flash sale runs now. The block shows the campaign that ends soonest while one runs.', 'pergel-engine' ) } );
							},
						} )
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
