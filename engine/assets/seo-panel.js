/**
 * Pergel SEO panel in the post editor: title, description and "hide from
 * search engines" for the current post.
 *
 * Plain script, no build step. The server loads it only while no SEO plugin
 * is active, and only on edit screens of viewable post types.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editor || ! wp.editor.PluginDocumentSettingPanel ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var components = wp.components;

	var TITLE = '_pergel_seo_title';
	var DESCRIPTION = '_pergel_seo_description';
	var NOINDEX = '_pergel_seo_noindex';

	function count( value, ideal ) {
		/* translators: 1: characters typed, 2: recommended maximum. */
		return sprintf( __( '%1$d of about %2$d characters.', 'pergel-engine' ), value.length, ideal );
	}

	function Panel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );
		var entity = useEntityProp( 'postType', postType, 'meta' );
		var meta = entity[ 0 ];
		var setMeta = entity[ 1 ];

		if ( ! meta || ! Object.prototype.hasOwnProperty.call( meta, TITLE ) ) {
			return null;
		}

		function update( key ) {
			return function ( value ) {
				var changes = {};
				changes[ key ] = value;
				setMeta( Object.assign( {}, meta, changes ) );
			};
		}

		var title = meta[ TITLE ] || '';
		var description = meta[ DESCRIPTION ] || '';

		return el(
			wp.editor.PluginDocumentSettingPanel,
			{ name: 'pergel-seo', title: __( 'SEO', 'pergel-engine' ), className: 'pergel-seo-panel' },
			el(
				'div',
				{ style: { display: 'grid', gap: '16px' } },
				el( components.TextControl, {
					__next40pxDefaultSize: true,
					__nextHasNoMarginBottom: true,
					label: __( 'Search title', 'pergel-engine' ),
					help: title ? count( title, 60 ) : __( 'Empty: the post title and the site title.', 'pergel-engine' ),
					value: title,
					maxLength: 70,
					onChange: update( TITLE ),
				} ),
				el( components.TextareaControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Search description', 'pergel-engine' ),
					help: description ? count( description, 155 ) : __( 'Empty: the excerpt or the start of the content.', 'pergel-engine' ),
					value: description,
					maxLength: 300,
					rows: 4,
					onChange: update( DESCRIPTION ),
				} ),
				el( components.ToggleControl, {
					__nextHasNoMarginBottom: true,
					label: __( 'Hide from search engines', 'pergel-engine' ),
					help: __( 'Adds noindex and leaves the page out of the sitemap.', 'pergel-engine' ),
					checked: !! meta[ NOINDEX ],
					onChange: update( NOINDEX ),
				} )
			)
		);
	}

	wp.plugins.registerPlugin( 'pergel-seo', { render: Panel } );
}( window.wp ) );
