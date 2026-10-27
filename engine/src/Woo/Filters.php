<?php
/**
 * The filter area of the product listing pages.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Applies the "shop" filter settings to the theme's shop layout (the
 * `pergel-shop-layout` columns: a filter column and a product column) and
 * to WooCommerce's Product Filters block:
 *
 * - where the filters sit: left, right, behind a "Filter products" button
 *   (the block's own overlay, on every screen), or nowhere;
 * - whether the filter column stays in view or scrolls on its own;
 * - filter headings that open and close their group, a search box in
 *   long lists and long lists cut to a few rows with a More button (a small
 *   script module, loaded only when one is on);
 * - the filter box's own title, with an icon;
 * - the brand and rating filters, added to the theme's filter pattern,
 *   and the Availability filter, which can go;
 * - child categories set in under their parent in the category list;
 * - a filter with one option that every product has is left out.
 *
 * Every default leaves the template as it is, like Layout.
 */
final class Filters {

	public const SCRIPT = 'pergel/shop-filters';

	/**
	 * The icon before the filter box's title: three sliders.
	 */
	private const ICON = '<svg class="pergel-filters__icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>';

	/**
	 * Filter blocks whose first heading names their group.
	 */
	private const GROUPS = array(
		'woocommerce/product-filter-price',
		'woocommerce/product-filter-taxonomy',
		'woocommerce/product-filter-status',
		'woocommerce/product-filter-attribute',
		'woocommerce/product-filter-rating',
	);

	/**
	 * Adds the front-end hooks. Layout adds the groups() filter everywhere.
	 */
	public static function register(): void {
		add_filter( 'render_block_core/columns', array( self::class, 'columns' ), 10, 2 );
		add_filter( 'render_block_core/column', array( self::class, 'column' ), 10, 2 );
		add_filter( 'render_block_data', array( self::class, 'block_data' ) );
		add_filter( 'render_block_woocommerce/product-filters', array( self::class, 'filters' ) );
		foreach ( self::GROUPS as $block ) {
			add_filter( "render_block_$block", array( self::class, 'heading' ) );
		}
		foreach ( array( 'taxonomy', 'status', 'attribute' ) as $kind ) {
			add_filter( "render_block_woocommerce/product-filter-$kind", array( self::class, 'lonely' ), 5 );
		}
		add_filter( 'render_block_woocommerce/product-filter-status', array( self::class, 'stock' ) );
		add_filter( 'render_block_woocommerce/product-filter-checkbox-list', array( self::class, 'depth' ) );
		add_action( 'init', array( self::class, 'register_assets' ) );
	}

	/**
	 * Registers the script module. Hooked on init.
	 */
	public static function register_assets(): void {
		wp_register_script_module( self::SCRIPT, plugins_url( 'assets/shop-filters.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );
	}

	/**
	 * Filter for pergel/shop/filters: the extra filters the theme's filter
	 * pattern adds.
	 *
	 * @param mixed $groups Filter names.
	 * @return list<string>
	 */
	public static function groups( mixed $groups ): array {
		$groups = array_values( array_filter( (array) $groups, 'is_string' ) );
		if ( true === Settings::get( 'shop.filter_brand' ) ) {
			$groups[] = 'brand';
		}
		if ( true === Settings::get( 'shop.filter_rating' ) ) {
			$groups[] = 'rating';
		}
		return $groups;
	}

	/**
	 * Whether a parsed block carries a class.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $name  Class name.
	 */
	private static function has_class( array $block, string $name ): bool {
		$classes = $block['attrs']['className'] ?? '';
		return is_string( $classes ) && in_array( $name, explode( ' ', $classes ), true );
	}

	/**
	 * The shop layout's position and scrolling classes.
	 *
	 * @param string               $html  Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public static function columns( string $html, array $block ): string {
		if ( ! self::has_class( $block, 'pergel-shop-layout' ) || ! Layout::listing() ) {
			return $html;
		}
		$classes = array();
		$place   = Layout::get( 'shop.filters' );
		if ( in_array( $place, array( 'left', 'right', 'drawer' ), true ) ) {
			$classes[] = "pergel-filters-$place";
		}
		$sticky = Settings::get( 'shop.filters_sticky' );
		if ( 'drawer' !== $place && in_array( $sticky, array( 'sticky', 'scroll' ), true ) ) {
			$classes[] = "pergel-filters-$sticky";
		}
		if ( ! $classes ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		Layout::style();
		foreach ( $classes as $class ) {
			$tags->add_class( $class );
		}
		return $tags->get_updated_html();
	}

	/**
	 * Drops the filter column when the page shows no filters.
	 *
	 * @param string               $html  Rendered block.
	 * @param array<string, mixed> $block Parsed block.
	 */
	public static function column( string $html, array $block ): string {
		if ( self::has_class( $block, 'pergel-shop-layout__filters' ) && Layout::listing() && 'none' === Layout::get( 'shop.filters' ) ) {
			return '';
		}
		return $html;
	}

	/**
	 * The Product Filters block's overlay: on every screen for the panel,
	 * and from the chosen side. The block has no context for its children,
	 * so its attributes can still change before it renders.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function block_data( array $block ): array {
		if ( 'woocommerce/product-filters' !== ( $block['blockName'] ?? '' ) || ! Layout::listing() ) {
			return $block;
		}
		if ( 'drawer' === Layout::get( 'shop.filters' ) ) {
			$block['attrs']['overlayMode'] = 'always';
		}
		if ( 'right' === Layout::get( 'shop.drawer_side' ) ) {
			$block['attrs']['overlayPosition'] = 'right';
		}
		return $block;
	}

	/**
	 * Puts the filter box's own title in, and marks the filters for the
	 * script and loads it, when a filter group can fold, a long list gets a
	 * search box or long lists show a few rows.
	 *
	 * @param string $html Rendered block.
	 */
	public static function filters( string $html ): string {
		if ( ! Layout::listing() ) {
			return $html;
		}
		$html     = self::title( $html );
		$features = array_keys(
			array_filter(
				array(
					'fold'   => true === Settings::get( 'shop.filters_fold' ),
					'search' => true === Settings::get( 'shop.filters_search' ),
					'more'   => 'pill' === Settings::get( 'shop.filter_more' ),
				)
			)
		);
		if ( ! $features ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		$tags->set_attribute( 'data-pergel-filters', implode( ' ', $features ) );
		$tags->set_attribute( 'data-pergel-search-label', __( 'Search the options', 'pergel-engine' ) );
		if ( in_array( 'more', $features, true ) ) {
			$rows = Settings::get( 'shop.filter_rows' );
			$tags->set_attribute( 'data-pergel-rows', (string) ( is_int( $rows ) ? $rows : 5 ) );
			$tags->set_attribute( 'data-pergel-more-label', _x( 'More', 'filter list', 'pergel-engine' ) );
			$tags->set_attribute( 'data-pergel-less-label', _x( 'Less', 'filter list', 'pergel-engine' ) );
		}
		wp_enqueue_script_module( self::SCRIPT );
		Layout::style();
		return $tags->get_updated_html();
	}

	/**
	 * The filter box's own title and icon, in the title heading of the
	 * theme's filter pattern (class pergel-filters__title).
	 *
	 * @param string $html Rendered block.
	 */
	private static function title( string $html ): string {
		$text = Settings::get( 'shop.filter_title' );
		$text = is_string( $text ) ? trim( $text ) : '';
		$icon = true === Settings::get( 'shop.filter_icon' );
		if ( '' === $text && ! $icon ) {
			return $html;
		}
		if ( $icon ) {
			Layout::style();
		}
		return (string) preg_replace_callback(
			'#(<h([1-6])\b[^>]*\bpergel-filters__title\b[^>]*>)(.*?)(</h\2>)#s',
			static fn ( array $parts ): string => $parts[1] . ( $icon ? self::ICON : '' ) . ( '' === $text ? $parts[3] : esc_html( $text ) ) . $parts[4],
			$html,
			1
		);
	}

	/**
	 * Drops a filter that filters nothing: one option, not ticked, that
	 * every product of the page has, such as the only brand of a store.
	 *
	 * @param string $html Rendered block.
	 */
	public static function lonely( string $html ): string {
		global $wp_query;
		if ( ! Layout::listing() || ! $wp_query instanceof \WP_Query ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		$context = $tags->get_attribute( 'data-wp-context' );
		$context = is_string( $context ) ? json_decode( $context, true ) : null;
		$items   = is_array( $context ) && isset( $context['items'] ) && is_array( $context['items'] ) ? $context['items'] : array();
		if ( 1 !== count( $items ) || ! is_array( $items[0] ) ) {
			return $html;
		}
		$item = $items[0];
		return empty( $item['selected'] ) && isset( $item['count'] ) && (int) $item['count'] === (int) $wp_query->found_posts ? '' : $html;
	}

	/**
	 * Drops the Availability filter when shop.filter_stock is off.
	 *
	 * @param string $html Rendered block.
	 */
	public static function stock( string $html ): string {
		return false === Settings::get( 'shop.filter_stock' ) && Layout::listing() ? '' : $html;
	}

	/**
	 * Marks each option of a category list with its depth, so a child
	 * category sits under its parent. WooCommerce sends the depth with the
	 * options but draws them all alike. A binding, not a plain attribute:
	 * WooCommerce draws the options again from the same data after each
	 * choice, and the binding fills those in too.
	 *
	 * @param string $html Rendered block.
	 */
	public static function depth( string $html ): string {
		if ( ! Layout::listing() || ! str_contains( $html, 'depth' ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( array( 'class_name' => 'wc-block-product-filter-checkbox-list__item' ) ) ) {
			$tags->set_attribute( 'data-wp-bind--data-pergel-depth', 'context.item.depth' );
		}
		Layout::style();
		return $tags->get_updated_html();
	}

	/**
	 * Turns a filter group's heading text into a button that opens and
	 * closes the group. Done here, in the server markup, so it survives
	 * WooCommerce redrawing the filters after each choice; the script only
	 * listens for clicks. Without the script the button does nothing and
	 * every group stays open.
	 *
	 * @param string $html Rendered block.
	 */
	public static function heading( string $html ): string {
		if ( true !== Settings::get( 'shop.filters_fold' ) || ! Layout::listing() ) {
			return $html;
		}
		return (string) preg_replace(
			'#(<h3\b[^>]*>)(.*?)(</h3>)#s',
			'$1<button type="button" class="pergel-filter-toggle" aria-expanded="true">$2</button>$3',
			$html,
			1
		);
	}
}
