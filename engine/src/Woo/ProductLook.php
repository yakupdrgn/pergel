<?php
/**
 * The "Product page look" shop settings as CSS.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

use const Pergel\VERSION;

/**
 * Turns the look settings of the top of the product page (the pictures,
 * the name, the price, the buying buttons and the quantity box) into CSS
 * rules. Only the values that are set make rules, so an empty value keeps
 * the template's look. The rules are printed in a style of their own.
 *
 * Layout loads this class only on product pages and while one of these
 * settings is set.
 */
final class ProductLook {

	use LookValues;

	/**
	 * Handle of the style the rules are printed in.
	 */
	public const STYLE = 'pergel-product-look';

	/**
	 * The buying form, written to beat WooCommerce's button rules (three
	 * classes).
	 */
	private const FORM = 'body .wc-block-add-to-cart-with-options.wc-block-add-to-cart-with-options';

	/**
	 * Set once the rules are on the page.
	 *
	 * @var bool
	 */
	private static bool $added = false;

	/**
	 * Prints the rules in the head, once a page.
	 */
	public static function enqueue(): void {
		if ( self::$added ) {
			return;
		}
		self::$added = true;
		$css         = self::css();
		if ( '' === $css ) {
			return;
		}
		wp_register_style( self::STYLE, false, array(), VERSION );
		wp_add_inline_style( self::STYLE, $css );
		wp_enqueue_style( self::STYLE );
	}

	/**
	 * The rules for the values that are set.
	 */
	public static function css(): string {
		return implode( '', array_merge( self::pictures(), self::summary(), self::buying() ) );
	}

	/**
	 * The large picture and the small ones beside it.
	 *
	 * @return list<string>
	 */
	private static function pictures(): array {
		$large = '.wc-block-product-gallery-large-image';
		$rules = array();
		$box   = array();
		$bg    = self::color( 'picture_bg' );
		if ( '' !== $bg ) {
			$box[] = "background:$bg";
		}
		$edge  = self::px( 'picture_edge' );
		$color = self::color( 'picture_edge_color' );
		if ( '0px' === $edge ) {
			$box[] = 'border:0';
		} elseif ( '' !== $edge || '' !== $color ) {
			$box[] = 'border:' . ( '' === $edge ? '1px' : $edge ) . ' solid ' . ( '' === $color ? 'var(--wp--preset--color--line)' : $color );
		}
		$radius = self::px( 'picture_radius' );
		if ( '' !== $radius ) {
			// The box rounds the picture; the template's own corners go.
			$box[]   = "border-radius:$radius;overflow:hidden";
			$rules[] = "$large :is(.wc-block-components-product-image,img){border-radius:0}";
		}
		if ( $box ) {
			array_unshift( $rules, "$large{box-sizing:border-box;" . implode( ';', $box ) . '}' );
		}

		$thumb  = '.wc-block-product-gallery-thumbnails__thumbnail';
		$corner = self::px( 'thumb_radius' );
		if ( '' !== $corner ) {
			$rules[] = "$thumb,$thumb img{border-radius:$corner}";
			$rules[] = "$thumb{overflow:hidden}";
		}
		$active = self::color( 'thumb_active' );
		if ( '' !== $active ) {
			$rules[] = "$thumb img.wc-block-product-gallery-thumbnails__thumbnail__image--is-active{outline:2px solid $active;outline-offset:-2px}";
		}
		return $rules;
	}

	/**
	 * The product name and the price.
	 *
	 * @return list<string>
	 */
	private static function summary(): array {
		$rules = array();
		$title = array();
		$size  = self::px( 'title_size' );
		if ( '' !== $size ) {
			// Preset font size classes use !important.
			$title[] = "font-size:$size!important";
		}
		$weight = self::weight( 'title_weight' );
		if ( '' !== $weight ) {
			$title[] = "font-weight:$weight";
		}
		if ( $title ) {
			$rules[] = 'h1.wp-block-post-title{' . implode( ';', $title ) . '}';
		}

		$price = array();
		$size  = self::px( 'price_size' );
		if ( '' !== $size ) {
			$price[] = "font-size:$size!important";
		}
		$weight = self::weight( 'price_weight' );
		if ( '' !== $weight ) {
			$price[] = "font-weight:$weight";
		}
		$color = self::color( 'price_color' );
		if ( '' !== $color ) {
			$price[] = "color:$color";
		}
		if ( $price ) {
			// The product's own price, not those of the grids on the page.
			$own     = '.wp-block-woocommerce-product-price:not([data-is-descendent-of-query-loop]) .wc-block-components-product-price';
			$rules[] = "$own{" . implode( ';', $price ) . '}';
			if ( '' !== $color ) {
				// The struck-out old price stays quiet.
				$rules[] = "$own del{color:var(--wp--preset--color--muted)}";
			}
		}
		return $rules;
	}

	/**
	 * The add to cart and buy now buttons and the quantity box.
	 *
	 * @return list<string>
	 */
	private static function buying(): array {
		$form    = self::FORM;
		$cart    = "$form :is(.wc-block-components-product-button__button,.single_add_to_cart_button)";
		$buttons = "$form :is(.wc-block-components-product-button__button,.single_add_to_cart_button,.pergel-buy-now)";
		$rules   = array();

		$color = self::color( 'buy_color' );
		$color = '' === $color ? 'var(--wp--preset--color--accent)' : $color;
		$look  = match ( Settings::get( 'shop.buy_look' ) ) {
			'filled'  => array( "background:$color;color:var(--wp--preset--color--on-accent);border:2px solid $color", 'filter:brightness(.92)' ),
			'outline' => array( "background:transparent;color:$color;border:2px solid $color", "background:$color;color:var(--wp--preset--color--on-accent)" ),
			default   => array(),
		};
		if ( $look ) {
			$rules[] = "$cart{{$look[0]}}";
			$rules[] = "$cart:hover{{$look[1]}}";
		}

		$own   = array();
		$shape = self::shape( 'buy_shape' );
		if ( '' !== $shape ) {
			$own[] = "border-radius:$shape";
		}
		$height = self::px( 'buy_height' );
		if ( '' !== $height ) {
			$own[]   = "box-sizing:border-box;min-height:$height;padding-block:0;display:inline-flex;align-items:center;justify-content:center";
			$rules[] = "$form .wc-block-add-to-cart-with-options__quantity-selector{box-sizing:border-box;height:$height}";
			$rules[] = "$form .wc-block-add-to-cart-with-options__quantity-selector .wc-block-components-quantity-selector{height:100%}";
		}
		if ( $own ) {
			array_unshift( $rules, "$buttons{" . implode( ';', $own ) . '}' );
		}

		$qty = self::shape( 'qty_shape' );
		if ( '' !== $qty ) {
			$box     = "$form .wc-block-add-to-cart-with-options__quantity-selector";
			$rules[] = "$box,$box .wc-block-components-quantity-selector{border-radius:$qty}";
			$rules[] = "$box .wc-block-components-quantity-selector__button--minus{border-radius:$qty 0 0 $qty}";
			$rules[] = "$box .wc-block-components-quantity-selector__button--plus{border-radius:0 $qty $qty 0}";
		}
		return $rules;
	}

	/**
	 * A shape setting as a corner radius, '' when not set.
	 *
	 * @param string $key Setting key in the shop group.
	 */
	private static function shape( string $key ): string {
		return match ( Settings::get( "shop.$key" ) ) {
			'pill'    => '999px',
			'rounded' => '8px',
			'square'  => '0',
			default   => '',
		};
	}
}
