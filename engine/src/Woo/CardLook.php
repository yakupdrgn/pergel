<?php
/**
 * The "Card look" shop settings as CSS.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

/**
 * Turns the card look settings (edge, corners, space, colours, the name and
 * the button) into a few CSS rules for the product grids Cards marks with
 * .pergel-cards. Only the values that are set make rules, so an empty value
 * keeps the card style's or the theme's own look. The class is a doubled
 * .pergel-cards to beat the card styles and plain theme rules.
 *
 * Cards loads this class only while one of these settings is set.
 */
final class CardLook {

	/**
	 * The grid the rules are for.
	 */
	private const GRID = '.pergel-cards.pergel-cards';

	/**
	 * Set once the rules are on the page.
	 *
	 * @var bool
	 */
	private static bool $added = false;

	/**
	 * Adds the rules to the card stylesheet, once a page.
	 */
	public static function enqueue(): void {
		if ( self::$added ) {
			return;
		}
		self::$added = true;
		$css         = self::css();
		if ( '' !== $css ) {
			wp_add_inline_style( Layout::CARD_STYLE, $css );
		}
	}

	/**
	 * The rules for the values that are set.
	 */
	public static function css(): string {
		$grid  = self::GRID;
		$card  = "$grid .wc-block-product";
		$rules = array();

		$gap = self::px( 'card_gap' );
		if ( '' !== $gap ) {
			// WooCommerce sets the gap on the template with a layout class.
			$rules[] = "$grid .wc-block-product-template{gap:$gap!important}";
		}

		$box = array();
		$bg  = self::color( 'card_bg' );
		if ( '' !== $bg ) {
			$box[] = "background:$bg";
		}
		$edge  = self::px( 'card_border' );
		$color = self::color( 'card_border_color' );
		if ( '0px' === $edge ) {
			$box[] = 'border:0';
		} elseif ( '' !== $edge || '' !== $color ) {
			$box[] = 'border:' . ( '' === $edge ? '1px' : $edge ) . ' solid ' . ( '' === $color ? 'var(--wp--preset--color--line)' : $color );
		}
		$radius = self::px( 'card_radius' );
		if ( '' !== $radius ) {
			$box[] = "border-radius:$radius";
		}
		$padding = self::px( 'card_padding' );
		if ( '' !== $padding ) {
			$box[] = "padding:$padding";
		}
		if ( $box ) {
			$rules[] = "$card{box-sizing:border-box;" . implode( ';', $box ) . '}';
		}

		$rules = array_merge( $rules, self::hover( $card ) );

		$image = array();
		$fill  = self::color( 'card_image_bg' );
		if ( '' !== $fill ) {
			$image[] = "background:$fill";
		}
		$corner = self::px( 'card_image_radius' );
		if ( '' !== $corner ) {
			$image[] = "border-radius:$corner;overflow:hidden";
			$rules[] = "$card .wc-block-components-product-image img{border-radius:$corner}";
		}
		if ( $image ) {
			$rules[] = "$card .wc-block-components-product-image{" . implode( ';', $image ) . '}';
		}

		$align = Settings::get( 'shop.card_align' );
		if ( 'start' === $align || 'center' === $align ) {
			$flex    = 'start' === $align ? 'flex-start' : 'center';
			$rules[] = "$card{text-align:$align}";
			$rules[] = "$card :is(.pergel-card-meta,.pergel-card-pills,.wc-block-components-product-price,.wc-block-components-product-rating__container,.wp-block-pergel-product-actions){justify-content:$flex}";
			$rules[] = "$card .wc-block-components-product-button.wc-block-components-product-button{align-items:$flex;justify-content:$flex;text-align:$align}";
		}

		$title = array();
		$size  = self::px( 'card_title_size' );
		if ( '' !== $size ) {
			// Preset font size classes use !important.
			$title[] = "font-size:$size!important";
		}
		$weight = Settings::get( 'shop.card_title_weight' );
		if ( is_string( $weight ) && ctype_digit( $weight ) ) {
			$title[] = "font-weight:$weight";
		}
		if ( $title ) {
			$rules[] = "$card .wp-block-post-title{" . implode( ';', $title ) . '}';
		}

		$meta = self::color( 'card_meta_color' );
		if ( '' !== $meta ) {
			$rules[] = "$card .pergel-card-meta,$card .pergel-card-meta a{color:$meta}";
		}

		$rules = array_merge( $rules, self::button( $card ) );
		return implode( '', $rules );
	}

	/**
	 * The card while the pointer is over it.
	 *
	 * @param string $card Card selector.
	 * @return list<string>
	 */
	private static function hover( string $card ): array {
		$shadow = 'box-shadow:0 .5rem 1.5rem rgb(0 0 0/.08)';
		$hover  = match ( Settings::get( 'shop.card_hover_look' ) ) {
			'none'   => 'box-shadow:none;transform:none',
			'shadow' => $shadow,
			'accent' => "$shadow;border-color:var(--wp--preset--color--accent)",
			'lift'   => "$shadow;transform:translateY(-4px)",
			default  => '',
		};
		if ( '' === $hover ) {
			return array();
		}
		return array(
			"$card{transition:box-shadow .2s,border-color .2s,transform .2s}",
			"$card:hover{{$hover}}",
			"@media (prefers-reduced-motion:reduce){{$card}{transition:none}{$card}:hover{transform:none}}",
		);
	}

	/**
	 * The card's button: WooCommerce's add to cart or read more, and
	 * Pergel's own card button.
	 *
	 * @param string $card Card selector.
	 * @return list<string>
	 */
	private static function button( string $card ): array {
		$link   = "$card .wc-block-components-product-button :is(.wp-block-button__link,.wc-block-components-product-button__button)";
		$button = "$link,$card .pergel-card-button";
		$rules  = array();
		$color  = self::color( 'card_button_color' );
		$color  = '' === $color ? 'var(--wp--preset--color--accent)' : $color;
		$look   = match ( Settings::get( 'shop.card_button' ) ) {
			'filled'  => array( "background:$color;color:var(--wp--preset--color--on-accent);border:2px solid $color", 'filter:brightness(.92)' ),
			'outline' => array( "background:transparent;color:$color;border:2px solid $color", "background:$color;color:var(--wp--preset--color--on-accent)" ),
			'text'    => array( "background:none;color:$color;border:0;padding-inline:0;min-height:0", 'text-decoration:underline' ),
			default   => array(),
		};
		$own = array();
		if ( $look ) {
			$own[]   = $look[0];
			$rules[] = "$link:hover,$card .pergel-card-button:hover{{$look[1]}}";
		}
		$shape = match ( Settings::get( 'shop.card_button_shape' ) ) {
			'pill'    => '999px',
			'rounded' => '8px',
			'square'  => '0',
			default   => '',
		};
		if ( '' !== $shape ) {
			$own[] = "border-radius:$shape";
		}
		$width = Settings::get( 'shop.card_button_width' );
		if ( 'full' === $width ) {
			$own[]   = 'width:100%;justify-content:center;text-align:center';
			$rules[] = "$card .wc-block-components-product-button{width:100%}";
		} elseif ( 'auto' === $width ) {
			$own[] = 'width:auto';
		}
		$height = self::px( 'card_button_height' );
		if ( '' !== $height ) {
			$own[] = "box-sizing:border-box;min-height:$height;padding-block:0;display:inline-flex;align-items:center;justify-content:center";
		}
		if ( $own ) {
			array_unshift( $rules, $button . '{' . implode( ';', $own ) . '}' );
		}
		return $rules;
	}

	/**
	 * A length setting as CSS, '' when not set.
	 *
	 * @param string $key Setting key in the shop group.
	 */
	private static function px( string $key ): string {
		$value = Settings::get( "shop.$key" );
		return is_int( $value ) ? $value . 'px' : '';
	}

	/**
	 * A colour setting as CSS: a palette colour as its preset variable,
	 * #rrggbb as it is, '' when not set.
	 *
	 * @param string $key Setting key in the shop group.
	 */
	private static function color( string $key ): string {
		$value = Settings::get( "shop.$key" );
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#[0-9a-f]{6}$/', $value ) ) {
			return $value;
		}
		return preg_match( '/^[a-z0-9][a-z0-9-]*$/', $value ) ? "var(--wp--preset--color--$value)" : '';
	}
}
