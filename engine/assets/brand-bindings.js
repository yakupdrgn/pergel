/**
 * Pergel Brand binding source in the editors: current values and the
 * field list for the block's Attributes panel.
 *
 * Plain script, no build step. The server registers the source and fills
 * window.pergelBrand; the front end never loads this file.
 */
( function ( blocks, brand ) {
	'use strict';

	if ( ! blocks || ! blocks.registerBlockBindingsSource || ! brand ) {
		return;
	}

	function lookup( args, attribute ) {
		var key = args && args.key;
		if ( 'logo_inverse' === key ) {
			if ( ! brand.logo.id ) {
				return undefined;
			}
			if ( 'id' === attribute ) {
				return brand.logo.id;
			}
			return 'alt' === attribute || 'title' === attribute ? brand.values.name : brand.logo.url;
		}
		if ( 'social' === key ) {
			return brand.social[ args.network ];
		}
		return brand.values[ key ];
	}

	blocks.registerBlockBindingsSource( {
		name: 'pergel/brand',
		getValues: function ( options ) {
			var values = {};
			Object.keys( options.bindings ).forEach( function ( attribute ) {
				var value = lookup( options.bindings[ attribute ].args, attribute );
				// Empty values keep the text the block was saved with, as on the front end.
				if ( value ) {
					values[ attribute ] = value;
				}
			} );
			return values;
		},
		getFieldsList: function () {
			return brand.fields;
		},
		canUserEditValue: function () {
			return false;
		},
	} );
}( window.wp && window.wp.blocks, window.pergelBrand ) );
