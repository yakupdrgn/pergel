<?php
/**
 * The "Filter look" and "Toolbar and page numbers look" shop settings as CSS.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * Turns the look settings of the listing pages (the filter box, the toolbar
 * above the products and the page numbers) into CSS rules. Only the values
 * that are set make rules, so an empty value keeps the template's look.
 * The rules go after the layout CSS, which only loads on listing pages.
 *
 * Layout loads this class only on listing pages and while one of these
 * settings is set.
 */
final class ShopLook {

	use LookValues;

	/**
	 * The filter block, also inside the phone panel.
	 */
	private const FILTERS = '.pergel-shop-layout .wc-block-product-filters';

	/**
	 * The products column.
	 */
	private const PRODUCTS = '.pergel-shop-layout .pergel-shop-layout__products';

	/**
	 * Set once the rules are on the page.
	 *
	 * @var bool
	 */
	private static bool $added = false;

	/**
	 * Adds the rules after the layout CSS, once a page.
	 */
	public static function enqueue(): void {
		if ( self::$added ) {
			return;
		}
		self::$added = true;
		$css         = self::css();
		if ( '' !== $css ) {
			Layout::style();
			wp_add_inline_style( Layout::STYLE, $css );
		}
	}

	/**
	 * The rules for the values that are set.
	 */
	public static function css(): string {
		return implode( '', array_merge( self::filters(), self::toolbar(), self::paging() ) );
	}

	/**
	 * The filter box and its contents.
	 *
	 * @return list<string>
	 */
	private static function filters(): array {
		$filters = self::FILTERS;
		$rules   = array();

		// The box only where WooCommerce shows the filters beside the
		// products (wider than 1024px); its panel keeps its own look. The
		// block is a flex box: its content may not grow past the box.
		$box = self::box( 'filter' );
		if ( $box ) {
			$column  = '.pergel-shop-layout:not(.pergel-filters-drawer) .pergel-shop-layout__filters .wc-block-product-filters';
			$rules[] = "@media (min-width:1025px){{$column}{" . implode( ';', $box ) . "}{$column} .wc-block-product-filters__overlay{min-width:0;flex:1 1 auto}}";
		}

		$head = array();
		$size = self::px( 'filter_head_size' );
		if ( '' !== $size ) {
			// Preset font size classes use !important.
			$head[] = "font-size:$size!important";
		}
		$weight = self::weight( 'filter_head_weight' );
		if ( '' !== $weight ) {
			$head[] = "font-weight:$weight";
		}
		if ( $head ) {
			$rules[] = "$filters .wp-block-heading:not(.pergel-filters__title){" . implode( ';', $head ) . '}';
		}

		$group = '[class*="wp-block-woocommerce-product-filter-"]';
		$next  = "$filters $group:not(.wc-block-product-filter--hidden)+$group";
		$line  = Settings::get( 'shop.filter_divider' );
		if ( 'line' === $line ) {
			$rules[] = "$next{border-top:1px solid var(--wp--preset--color--line);padding-top:var(--wc-product-filter-block-spacing,1.5rem)}";
		} elseif ( 'none' === $line ) {
			$rules[] = "$next{border-top:0}";
		}

		$text = self::px( 'filter_text_size' );
		if ( '' !== $text ) {
			$rules[] = "$filters :is(.wc-block-product-filter-checkbox-list__label,.wc-block-product-filter-chips__item,.pergel-filter-search){font-size:$text}";
		}

		$accent = self::color( 'filter_accent' );
		if ( '' !== $accent ) {
			$rules[] = "$filters{--wc-product-filter-price-slider:$accent;--wc-product-filter-removable-chips-border:$accent}";
			// The theme colours a ticked box with a longer selector.
			$rules[] = "$filters input[type=checkbox].wc-block-product-filter-checkbox-list__input:checked{--wc-product-filter-checkbox-list-option-element:$accent;--wc-product-filter-checkbox-list-option-element-border:$accent}";
		}
		return $rules;
	}

	/**
	 * The row with the result count and the sorting, and its menus.
	 *
	 * @return list<string>
	 */
	private static function toolbar(): array {
		$products = self::PRODUCTS;
		$rules    = array();
		$box      = self::box( 'toolbar' );
		if ( $box ) {
			$rules[] = "$products>.wp-block-group:has(.wp-block-woocommerce-catalog-sorting,.wp-block-woocommerce-product-results-count){" . implode( ';', $box ) . '}';
		}
		$radius = self::px( 'control_radius' );
		if ( '' !== $radius ) {
			$rules[] = "$products :is(.wc-block-catalog-sorting select,.pergel-per-page select,.pergel-view-button){border-radius:$radius}";
		}
		return $rules;
	}

	/**
	 * The page numbers under the products.
	 *
	 * @return list<string>
	 */
	private static function paging(): array {
		$numbers = self::PRODUCTS . ' .wp-block-query-pagination-numbers .page-numbers';
		$shape   = match ( Settings::get( 'shop.paging_shape' ) ) {
			'square'  => '0',
			'rounded' => '8px',
			'circle'  => '999px',
			default   => '',
		};
		$size    = self::px( 'paging_size' );
		$bg      = self::color( 'paging_bg' );
		$current = self::color( 'paging_current' );
		if ( '' === $shape && '' === $size && '' === $bg && '' === $current ) {
			return array();
		}

		$own = array();
		if ( '' !== $shape || '' !== $size ) {
			// A box of its own, so the shape shows; 2.5em without a size.
			$box   = '' === $size ? '2.5em' : $size;
			$own[] = "display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;min-width:$box;height:$box;padding:0 .25em;text-decoration:none";
		}
		if ( '' !== $shape ) {
			$own[] = "border-radius:$shape";
		}
		if ( '' !== $bg ) {
			$own[] = "background:$bg";
		}
		$rules = array();
		if ( $own ) {
			$rules[] = "$numbers:not(.dots){" . implode( ';', $own ) . '}';
		}
		// The current page stands out from a number with a box.
		$fill    = '' === $current ? 'var(--wp--preset--color--accent)' : $current;
		$rules[] = "$numbers.current{background:$fill;color:var(--wp--preset--color--on-accent)}";
		return $rules;
	}

	/**
	 * Background, edge, corners and inner space of a box.
	 *
	 * @param string $prefix Setting key prefix: filter or toolbar.
	 * @return list<string>
	 */
	private static function box( string $prefix ): array {
		$box = array();
		$bg  = self::color( "{$prefix}_bg" );
		if ( '' !== $bg ) {
			$box[] = "background:$bg";
		}
		$edge  = self::px( "{$prefix}_edge" );
		$color = self::color( "{$prefix}_edge_color" );
		if ( '0px' === $edge ) {
			$box[] = 'border:0';
		} elseif ( '' !== $edge || '' !== $color ) {
			$box[] = 'border:' . ( '' === $edge ? '1px' : $edge ) . ' solid ' . ( '' === $color ? 'var(--wp--preset--color--line)' : $color );
		}
		$radius = self::px( "{$prefix}_radius" );
		if ( '' !== $radius ) {
			$box[] = "border-radius:$radius";
		}
		$padding = self::px( "{$prefix}_padding" );
		if ( '' !== $padding ) {
			$box[] = "padding:$padding";
		}
		if ( $box ) {
			array_unshift( $box, 'box-sizing:border-box' );
		}
		return $box;
	}
}
