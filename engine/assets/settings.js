/**
 * Pergel settings screen: image fields use the media library; colour
 * fields show their colour and the picker only for "Custom colour"; item
 * lists move their rows with the arrows; the preview panel shows the shop
 * with the unsaved values.
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

	// Live preview: the form's values go to the preview route a moment
	// after each change; the page shown reloads at the same scroll place.
	var panel = document.getElementById( 'pergel-preview' );
	var open = document.querySelector( '.pergel-preview__open' );
	var form = document.querySelector( '.pergel-settings__page form' );
	if ( panel && open && form ) {
		var frame = panel.querySelector( 'iframe' );
		var box = panel.querySelector( '.pergel-preview__frame' );
		var choose = panel.querySelector( 'select' );
		var state = panel.querySelector( '.pergel-preview__state' );
		var urls = null;
		var timer = 0;

		// The form's fields as { group: { key: value } }, as a save sends them.
		var values = function () {
			var data = {};
			new window.FormData( form ).forEach( function ( value, name ) {
				var match = /^pergel_settings\[(.+)\]$/.exec( name );
				if ( ! match ) {
					return;
				}
				var path = match[ 1 ].split( '][' );
				var list = '' === path[ path.length - 1 ];
				if ( list ) {
					path.pop();
				}
				var at = data;
				for ( var i = 0; i < path.length - 1; i++ ) {
					if ( 'object' !== typeof at[ path[ i ] ] || null === at[ path[ i ] ] ) {
						at[ path[ i ] ] = {};
					}
					at = at[ path[ i ] ];
				}
				var key = path[ path.length - 1 ];
				if ( list ) {
					at[ key ] = Array.isArray( at[ key ] ) ? at[ key ] : [];
					at[ key ].push( value );
				} else {
					// A box's hidden "0" comes first; the box overrides it.
					at[ key ] = value;
				}
			} );
			return data;
		};

		var fit = function () {
			var scale = box.clientWidth / 1280;
			frame.style.transform = 'scale(' + scale + ')';
			frame.style.height = ( box.clientHeight / scale ) + 'px';
		};

		var show = function () {
			if ( ! urls ) {
				return;
			}
			var url = urls[ choose.value ];
			var top = 0;
			try {
				top = frame.contentWindow.scrollY;
			} catch ( error ) {}
			frame.onload = function () {
				try {
					frame.contentWindow.scrollTo( 0, top );
				} catch ( error ) {}
			};
			frame.src = url;
		};

		var send = function () {
			state.textContent = panel.dataset.busy;
			window.fetch( panel.dataset.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': panel.dataset.nonce },
				body: JSON.stringify( { settings: values() } ),
			} ).then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( String( response.status ) );
				}
				return response.json();
			} ).then( function ( data ) {
				urls = data;
				show();
				state.textContent = panel.dataset.done;
			} ).catch( function () {
				state.textContent = panel.dataset.failed;
			} );
		};

		var later = function () {
			if ( panel.hidden ) {
				return;
			}
			window.clearTimeout( timer );
			timer = window.setTimeout( send, 600 );
		};

		var toggle = function ( on ) {
			panel.hidden = ! on;
			open.setAttribute( 'aria-expanded', on ? 'true' : 'false' );
			document.body.classList.toggle( 'pergel-previewing', on );
			if ( on ) {
				fit();
				send();
			} else {
				open.focus();
			}
		};

		open.addEventListener( 'click', function () {
			toggle( panel.hidden );
		} );
		panel.querySelector( '.pergel-preview__close' ).addEventListener( 'click', function () {
			toggle( false );
		} );
		panel.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				toggle( false );
			}
		} );
		choose.addEventListener( 'change', show );
		form.addEventListener( 'change', later );
		form.addEventListener( 'input', later );
		// The arrows of an item list change the order without an input event.
		form.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-move]' ) ) {
				later();
			}
		} );
		if ( window.ResizeObserver ) {
			new window.ResizeObserver( fit ).observe( box );
		}
	}
}() );
