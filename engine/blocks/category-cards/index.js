/**
 * Category cards in the editor: the server draws them; the sidebar picks
 * the style, the parent category and how many. Plain script, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var be = wp.blockEditor;
	var c = wp.components;
	var __ = wp.i18n.__;

	wp.blocks.registerBlockType( 'pergel/category-cards', {
		edit: function ( props ) {
			var a = props.attributes;
			var terms = wp.data.useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'taxonomy', 'product_cat', { per_page: 100, hide_empty: true, _fields: 'id,name,parent' } );
			}, [] );
			var parents = [ { value: '0', label: __( 'Main categories', 'pergel-engine' ) } ].concat(
				( terms || [] ).map( function ( term ) {
					return { value: String( term.id ), label: term.name };
				} )
			);
			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Cards', 'pergel-engine' ) },
						el( c.SelectControl, {
							label: __( 'Style', 'pergel-engine' ),
							value: a.cardStyle,
							options: [
								{ value: 'images', label: __( 'Round pictures', 'pergel-engine' ) },
								{ value: 'boxes', label: __( 'Boxes', 'pergel-engine' ) },
								{ value: 'list', label: __( 'Boxes with subcategories', 'pergel-engine' ) },
								{ value: 'names', label: __( 'Names only', 'pergel-engine' ) },
							],
							onChange: function ( value ) {
								props.setAttributes( { cardStyle: value } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( c.SelectControl, {
							label: __( 'Categories', 'pergel-engine' ),
							help: __( 'The main categories, or the subcategories of one category.', 'pergel-engine' ),
							value: String( a.parent ),
							options: parents,
							onChange: function ( value ) {
								props.setAttributes( { parent: parseInt( value, 10 ) || 0 } );
							},
							__nextHasNoMarginBottom: true,
						} ),
						el( c.RangeControl, {
							label: __( 'How many', 'pergel-engine' ),
							value: a.number,
							min: 1,
							max: 24,
							onChange: function ( value ) {
								props.setAttributes( { number: value || 8 } );
							},
							__nextHasNoMarginBottom: true,
						} )
					)
				),
				el( 'div', be.useBlockProps(), el( c.Disabled, null, el( wp.serverSideRender, { block: 'pergel/category-cards', attributes: a, skipBlockSupportAttributes: true } ) ) )
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
