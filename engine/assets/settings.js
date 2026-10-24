/**
 * Pergel settings screen: image fields use the media library; colour
 * fields show their colour and the picker only for "Custom colour"; item
 * lists move their rows with the arrows.
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

	document.querySelectorAll( '.pergel-color' ).forEach( function ( field ) {
		var pick = field.querySelector( 'select' );
		var hex = field.querySelector( '.pergel-color__hex' );
		var swatch = field.querySelector( '.pergel-color__swatch' );

		function show() {
			var custom = 'custom' === pick.value;
			var option = pick.options[ pick.selectedIndex ];
			hex.hidden = ! custom;
			// An empty background brings back the chequered "not set" look.
			swatch.style.background = custom ? hex.value : ( option && option.dataset.color ) || '';
		}

		pick.addEventListener( 'change', show );
		hex.addEventListener( 'input', show );
		show();
	} );

	// Item lists: the arrows move a row, the order field follows, and
	// "As in the template" turns the rows off.
	document.querySelectorAll( '.pergel-items' ).forEach( function ( field ) {
		var order = field.querySelector( 'input[type="hidden"]' );
		var template = field.querySelector( '.pergel-items__template' );
		var list = field.querySelector( '.pergel-items__list' );
		var live = field.querySelector( '[aria-live]' );

		function sync() {
			var rows = Array.prototype.slice.call( list.children );
			order.value = rows.map( function ( row ) {
				return row.dataset.key;
			} ).join( ',' );
			list.classList.toggle( 'is-off', template.checked );
			list.querySelectorAll( 'input, button' ).forEach( function ( control ) {
				control.disabled = template.checked || control.hasAttribute( 'data-locked' );
			} );
		}

		list.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-move]' );
			var row = button && button.closest( 'li' );
			var up = button && '-1' === button.dataset.move;
			var next = row && ( up ? row.previousElementSibling : row.nextElementSibling );
			if ( ! next ) {
				return;
			}
			list.insertBefore( up ? row : next, up ? next : row );
			button.focus();
			sync();
			live.textContent = live.dataset.moved
				.replace( '%1$s', row.querySelector( 'label' ).textContent.trim() )
				.replace( '%2$s', String( Array.prototype.indexOf.call( list.children, row ) + 1 ) )
				.replace( '%3$s', String( list.children.length ) );
		} );

		template.addEventListener( 'change', sync );
		sync();
	} );
}() );
