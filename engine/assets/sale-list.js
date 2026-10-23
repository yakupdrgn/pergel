/**
 * "Flash sale list": a Query Loop of the running and upcoming flash sales,
 * soonest first (Pergel\Woo\FlashSales orders every Query Loop of
 * campaigns on the site). The cards are ordinary blocks and can be changed.
 */
( function ( wp ) {
	'use strict';

	var __ = wp.i18n.__;
	var card = [
		'core/group',
		{
			tagName: 'article',
			style: {
				spacing: {
					blockGap: 'var:preset|spacing|30',
					padding: { top: 'var:preset|spacing|30', bottom: 'var:preset|spacing|40', left: 'var:preset|spacing|30', right: 'var:preset|spacing|30' },
				},
				border: { width: '1px', radius: 'var(--wp--custom--radius--card)' },
			},
			borderColor: 'line',
			layout: { type: 'flex', orientation: 'vertical', flexWrap: 'nowrap', justifyContent: 'stretch' },
		},
		[
			[ 'core/post-featured-image', { isLink: true, aspectRatio: '16/9', style: { border: { radius: 'var(--wp--custom--radius--card)' } } } ],
			[ 'pergel/sale-timer', { countdown: false, fontSize: 'small' } ],
			[ 'core/post-title', { level: 3, isLink: true, fontSize: 'large' } ],
			[ 'core/post-excerpt', { excerptLength: 24 } ],
			[ 'core/read-more', { content: __( 'See the products', 'pergel-engine' ) } ],
		],
	];

	wp.blocks.registerBlockVariation( 'core/query', {
		name: 'pergel/sale-list',
		title: __( 'Flash sale list', 'pergel-engine' ),
		description: __( 'The running and upcoming flash sales, soonest first; ended ones are left out.', 'pergel-engine' ),
		icon: 'megaphone',
		keywords: [ __( 'flash sale', 'pergel-engine' ), __( 'campaign', 'pergel-engine' ) ],
		category: 'pergel',
		isActive: [ 'namespace' ],
		scope: [ 'inserter' ],
		allowedControls: [],
		attributes: {
			namespace: 'pergel/sale-list',
			query: { perPage: 3, pages: 0, offset: 0, postType: 'pergel_sale', order: 'asc', orderBy: 'date', inherit: false },
		},
		innerBlocks: [
			[ 'core/post-template', { layout: { type: 'grid', minimumColumnWidth: '18rem' } }, [ card ] ],
			[ 'core/query-no-results', {}, [ [ 'core/paragraph', { content: __( 'There is no flash sale right now. Have a look again soon.', 'pergel-engine' ) } ] ] ],
		],
	} );
}( window.wp ) );
