/**
 * Pergel settings screen: image fields use the media library.
 *
 * Plain script, no build step. Loaded only on the Pergel settings page,
 * after wp_enqueue_media().
 */
( function () {
	'use strict';

	document.querySelectorAll( '.pergel-image' ).forEach( function ( field ) {
		var input = field.querySelector( 'input[type="hidden"]' );
		var preview = field.querySelector( '.pergel-image__preview' );
		var remove = field.querySelector( '.pergel-image__remove' );
		var frame;

		field.querySelector( '.pergel-image__choose' ).addEventListener( 'click', function () {
			if ( ! frame ) {
				frame = window.wp.media( {
					title: field.dataset.title,
					button: { text: field.dataset.button },
					// data-types: the only image types the field takes, e.g. no SVG for share images.
					library: { type: field.dataset.types ? field.dataset.types.split( ',' ) : 'image' },
					multiple: false,
				} );
				frame.on( 'select', function () {
					var image = frame.state().get( 'selection' ).first().toJSON();
					var size = ( image.sizes && ( image.sizes.medium || image.sizes.full ) ) || image;
					var img = document.createElement( 'img' );
					img.src = size.url;
					img.alt = '';
					preview.replaceChildren( img );
					input.value = String( image.id );
					remove.hidden = false;
				} );
			}
			frame.open();
		} );

		remove.addEventListener( 'click', function () {
			input.value = '0';
			preview.replaceChildren();
			remove.hidden = true;
			field.querySelector( '.pergel-image__choose' ).focus();
		} );
	} );
}() );
