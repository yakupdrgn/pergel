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
		return implode( '', array_merge( self::filters(), self::toolbar(), self::paging(), self::strip() ) );
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

		if ( 'tab' === Settings::get( 'shop.filter_title_look' ) ) {
			// On the box's top edge like a folder tab, in the box's own look;
			// WooCommerce's panel wrappers stay in the box on wide screens.
			$column  = '.pergel-shop-layout:not(.pergel-filters-drawer) .pergel-shop-layout__filters .wc-block-product-filters';
			$edge    = self::px( 'filter_edge' );
			$edge    = '' === $edge ? '1px' : $edge;
			$color   = self::color( 'filter_edge_color' );
			$color   = '' === $color ? 'var(--wp--preset--color--line)' : $color;
			$bg      = self::color( 'filter_bg' );
			$bg      = '' === $bg ? 'var(--wp--preset--color--base)' : $bg;
			$radius  = self::px( 'filter_radius' );
			$radius  = '' === $radius ? '12px' : $radius;
			$rules[] = "@media (min-width:1025px){{$column}{position:relative;margin-top:calc(1.5rem + 18px + $edge)!important;border-top-left-radius:0}{$column} :is(.wc-block-product-filters__overlay,.wc-block-product-filters__overlay-wrapper,.wc-block-product-filters__overlay-dialog,.wc-block-product-filters__overlay-content){position:static}{$column} .pergel-filters__title{position:absolute;left:calc(-1 * $edge);bottom:100%;margin:0!important;padding:10px 24px 8px 16px;font-size:1rem!important;font-weight:700;line-height:1.5rem;background:$bg;border:$edge solid $color;border-bottom:0;border-radius:$radius $radius 0 0}}";
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

		// The search box of long lists (Filters' script); its corners are
		// the toolbar menus'.
		$input = array();
		$size  = self::px( 'filter_input_size' );
		if ( '' !== $size ) {
			$input[] = "height:$size;min-height:$size;padding-block:0";
		}
		$edge  = self::px( 'filter_input_edge' );
		$color = self::color( 'filter_input_color' );
		if ( '0px' === $edge ) {
			$input[] = 'border:0';
		} elseif ( '' !== $edge || '' !== $color ) {
			$input[] = 'border:' . ( '' === $edge ? '1px' : $edge ) . ' solid ' . ( '' === $color ? 'var(--wp--preset--color--muted)' : $color );
		}
		$radius = self::px( 'control_radius' );
		if ( '' !== $radius ) {
			$input[] = "border-radius:$radius";
		}
		if ( $input ) {
			$rules[] = "$filters .pergel-filter-search{" . implode( ';', $input ) . '}';
		}

		$list = "$filters .wc-block-product-filter-checkbox-list";
		if ( 'right' === Settings::get( 'shop.filter_counts' ) ) {
			$rules[] = "{$list}__label{display:flex}{$list}__text-wrapper{flex:1 1 auto}{$list}__count{margin-inline-start:auto}";
		}
		$swatch = self::px( 'filter_swatch' );
		if ( '' !== $swatch ) {
			$rules[] = "{$list}__color-swatch{width:$swatch;height:$swatch}";
		}
		$star = self::px( 'filter_star_size' );
		if ( '' !== $star ) {
			$rules[] = "{$list}__stars,{$list}__stars-svg{height:$star}";
		}
		$star_color = self::color( 'filter_star_color' );
		if ( '' !== $star_color ) {
			$rules[] = "{$list}__stars{color:$star_color}";
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
		$controls = "$products :is(.wc-block-catalog-sorting select,.pergel-per-page select,.pergel-view-button,.wp-block-search__inside-wrapper)";
		$radius   = self::px( 'control_radius' );
		if ( '' !== $radius ) {
			$rules[] = "$controls{border-radius:$radius}";
		}
		$height = self::px( 'control_height' );
		if ( '' !== $height ) {
			$rules[] = "$products :is(.wc-block-catalog-sorting select,.pergel-per-page select,.wp-block-search__inside-wrapper){box-sizing:border-box;height:$height;min-height:0}$products .pergel-view-button{box-sizing:border-box;width:$height;height:$height;min-width:0;min-height:0}$products .wp-block-search__input{height:100%;min-height:0;padding-block:0}";
		}
		$edge  = self::px( 'control_edge' );
		$color = self::color( 'control_edge_color' );
		if ( '0px' === $edge ) {
			$rules[] = "$controls{border:0}";
		} elseif ( '' !== $edge || '' !== $color ) {
			$rules[] = "$controls{border:" . ( '' === $edge ? '1px' : $edge ) . ' solid ' . ( '' === $color ? 'var(--wp--preset--color--line)' : $color ) . '}';
			// The search box's edge is its wrapper's.
			$rules[] = "$products .wp-block-search__inside-wrapper .wp-block-search__input{border:0}";
		}
		return $rules;
	}

	/**
	 * The category strip above the products: how many share the row, and
	 * square pictures for the "Round pictures" style.
	 *
	 * @return list<string>
	 */
	private static function strip(): array {
		$strip = '.pergel-category-strip';
		$rules = array();
		$count = Settings::get( 'shop.strip_count' );
		$count = is_string( $count ) && ctype_digit( $count ) ? (int) $count : 0;
		if ( $count > 0 ) {
			// Fewer on tablets and phones, where the next one peeks in.
			$tiles   = static fn ( float $n ): string => "$strip>ul{gap:16px;scroll-snap-type:x mandatory}$strip>ul>.pergel-category-card{flex:0 0 calc((100% - " . ( ceil( $n ) - 1 ) . "*16px)/$n);min-width:0;scroll-snap-align:start;transform:none}";
			$rules[] = $tiles( (float) $count );
			$rules[] = '@media (max-width:1100px){' . $tiles( (float) min( $count, 6 ) ) . '}';
			$rules[] = '@media (max-width:781px){' . $tiles( min( (float) $count, 3.4 ) ) . '}';
		}
		if ( 'square' === Settings::get( 'shop.strip_picture' ) ) {
			$bg      = self::color( 'card_image_bg' );
			$radius  = self::px( 'card_image_radius' );
			$rules[] = "$strip.is-images .pergel-category-card__link{display:flex;flex-direction:column;gap:12px;width:100%;margin:0}$strip.is-images .pergel-category-card__image{width:100%;height:auto;aspect-ratio:1;object-fit:contain;border-radius:" . ( '' === $radius ? '8px' : $radius ) . ';background:' . ( '' === $bg ? 'var(--wp--preset--color--surface)' : $bg ) . '}';
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
