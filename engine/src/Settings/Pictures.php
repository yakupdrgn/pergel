<?php
/**
 * Small drawings for choices shown as cards.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Settings;

/**
 * One inline SVG per choice of a field shown as cards (Labels "cards"). A
 * drawing is decoration: the card's text names the choice, so the SVG is
 * hidden from screen readers. Drawn in currentColor, so it follows the
 * admin colour scheme and the selected state.
 */
final class Pictures {

	private const WIDTH  = 64;
	private const HEIGHT = 40;

	/**
	 * The drawing of one choice; empty when the field has none.
	 *
	 * @param string $group  Schema group.
	 * @param string $key    Field key.
	 * @param string $option Choice.
	 */
	public static function get( string $group, string $key, string $option ): string {
		$shapes = match ( "$group.$key" ) {
			'shop.columns', 'shop.columns_tablet', 'shop.columns_phone', 'shop.view_columns' => self::grid( $option ),
			'shop.width' => self::width( $option ),
			'shop.filters', 'shop.drawer_side' => self::filters( $option ),
			'shop.filters_sticky' => self::sticky( $option ),
			'shop.view' => self::view( $option ),
			'shop.paging' => self::paging( $option ),
			'shop.category_strip' => self::strip( $option ),
			'shop.card_style' => self::card( $option ),
			'shop.badge_sale' => self::sale( $option ),
			'shop.product_layout' => self::product( $option ),
			'shop.product_thumbs' => self::thumbs( $option ),
			'shop.details_style' => self::details( $option ),
			'shop.related_style' => self::related( $option ),
			default      => '',
		};
		if ( '' === $shapes ) {
			return '';
		}
		return sprintf(
			'<svg class="pergel-choice__picture" viewBox="0 0 %1$d %2$d" width="%1$d" height="%2$d" aria-hidden="true" focusable="false">'
			. '<rect x="0.5" y="0.5" width="%3$d" height="%4$d" rx="3" fill="none" stroke="currentColor" opacity="0.4"/>%5$s</svg>',
			self::WIDTH,
			self::HEIGHT,
			self::WIDTH - 1,
			self::HEIGHT - 1,
			$shapes
		);
	}

	/**
	 * Product tiles in one row; "auto" draws tiles that shrink to the right,
	 * "template" dashed tiles.
	 *
	 * @param string $option Column count, "auto" or "template".
	 */
	private static function grid( string $option ): string {
		if ( 'template' === $option ) {
			return '<g fill="none" stroke="currentColor" stroke-dasharray="3 2">'
				. '<rect x="6.5" y="9.5" width="15" height="21" rx="2"/><rect x="24.5" y="9.5" width="15" height="21" rx="2"/><rect x="42.5" y="9.5" width="15" height="21" rx="2"/></g>';
		}
		if ( 'auto' === $option ) {
			return '<rect x="6" y="8" width="20" height="24" rx="2" fill="currentColor"/>'
				. '<rect x="30" y="11" width="15" height="18" rx="2" fill="currentColor" opacity="0.6"/>'
				. '<rect x="49" y="14" width="9" height="12" rx="2" fill="currentColor" opacity="0.3"/>';
		}
		$count = max( 1, (int) $option );
		$gap   = 3;
		$inner = self::WIDTH - 12;
		$tile  = ( $inner - $gap * ( $count - 1 ) ) / $count;
		$html  = '';
		for ( $i = 0; $i < $count; $i++ ) {
			$html .= sprintf(
				'<rect x="%1$s" y="9" width="%2$s" height="22" rx="2" fill="currentColor"/>',
				round( 6 + $i * ( $tile + $gap ), 2 ),
				round( $tile, 2 )
			);
		}
		return $html;
	}

	/**
	 * The page column inside the screen.
	 *
	 * @param string $option "wide" or "full".
	 */
	private static function width( string $option ): string {
		$side = 'full' === $option ? 4 : 14;
		return sprintf(
			'<rect x="%1$d" y="7" width="%2$d" height="5" rx="1" fill="currentColor" opacity="0.6"/>'
			. '<rect x="%1$d" y="15" width="%2$d" height="18" rx="2" fill="currentColor"/>',
			$side,
			self::WIDTH - 2 * $side
		);
	}

	/**
	 * The filter column (outlined) beside the product tiles, or the panel
	 * button above them.
	 *
	 * @param string $option Filter area or panel side.
	 */
	private static function filters( string $option ): string {
		$tiles  = static fn ( int $x, int $width ): string => sprintf(
			'<rect x="%1$d" y="9" width="%2$d" height="10" rx="1.5" fill="currentColor"/><rect x="%1$d" y="22" width="%2$d" height="10" rx="1.5" fill="currentColor"/>',
			$x,
			$width
		);
		$column = static fn ( int $x ): string => sprintf( '<rect x="%d.5" y="9.5" width="13" height="22" rx="1.5" fill="none" stroke="currentColor"/>', $x );
		return match ( $option ) {
			'left'     => $column( 5 ) . $tiles( 22, 37 ),
			'right'    => $tiles( 5, 37 ) . $column( 45 ),
			'drawer'   => '<rect x="5.5" y="5.5" width="14" height="5" rx="1.5" fill="none" stroke="currentColor"/><rect x="5" y="14" width="54" height="8" rx="1.5" fill="currentColor"/><rect x="5" y="25" width="54" height="8" rx="1.5" fill="currentColor"/>',
			'none'     => $tiles( 5, 54 ),
			'template' => '<g stroke-dasharray="3 2">' . $column( 5 ) . '</g><g opacity="0.5">' . $tiles( 22, 37 ) . '</g>',
			default    => '',
		};
	}

	/**
	 * The filter column against the page while scrolling.
	 *
	 * @param string $option "off", "sticky" or "scroll".
	 */
	private static function sticky( string $option ): string {
		$page = '<rect x="24" y="5" width="35" height="30" rx="1.5" fill="currentColor" opacity="0.35"/>';
		return $page . match ( $option ) {
			'sticky' => '<rect x="5.5" y="5.5" width="14" height="20" rx="1.5" fill="none" stroke="currentColor"/><path d="M12.5 29v6" stroke="currentColor"/>',
			'scroll' => '<rect x="5.5" y="5.5" width="14" height="29" rx="1.5" fill="none" stroke="currentColor"/><rect x="16" y="9" width="2" height="9" rx="1" fill="currentColor"/>',
			default  => '<rect x="5.5" y="12.5" width="14" height="22" rx="1.5" fill="none" stroke="currentColor"/><path d="M12.5 4v6" stroke="currentColor"/>',
		};
	}

	/**
	 * Tiles in a row, or rows with a picture beside lines of text.
	 *
	 * @param string $option "grid" or "list".
	 */
	private static function view( string $option ): string {
		if ( 'list' !== $option ) {
			return self::grid( '3' );
		}
		$rows = '';
		foreach ( array( 6, 22 ) as $y ) {
			$rows .= sprintf(
				'<rect x="6" y="%1$d" width="14" height="12" rx="1.5" fill="currentColor"/><rect x="24" y="%2$d" width="30" height="3" rx="1" fill="currentColor"/><rect x="24" y="%3$d" width="22" height="3" rx="1" fill="currentColor" opacity="0.6"/>',
				$y,
				$y + 2,
				$y + 7
			);
		}
		return $rows;
	}

	/**
	 * What follows the product tiles: page numbers, a button, or more tiles.
	 *
	 * @param string $option "template", "more" or "scroll".
	 */
	private static function paging( string $option ): string {
		$tiles = '<rect x="6" y="5" width="16" height="12" rx="1.5" fill="currentColor"/><rect x="24" y="5" width="16" height="12" rx="1.5" fill="currentColor"/><rect x="42" y="5" width="16" height="12" rx="1.5" fill="currentColor"/>';
		return $tiles . match ( $option ) {
			'more'   => '<rect x="20.5" y="24.5" width="23" height="8" rx="4" fill="none" stroke="currentColor"/>',
			'scroll' => '<g opacity="0.45"><rect x="6" y="21" width="16" height="12" rx="1.5" fill="currentColor"/><rect x="24" y="21" width="16" height="12" rx="1.5" fill="currentColor"/><rect x="42" y="21" width="16" height="12" rx="1.5" fill="currentColor"/></g>',
			default  => '<rect x="19" y="26" width="6" height="6" rx="1" fill="currentColor"/><rect x="29.5" y="26.5" width="5" height="5" rx="1" fill="none" stroke="currentColor"/><rect x="39.5" y="26.5" width="5" height="5" rx="1" fill="none" stroke="currentColor"/>',
		};
	}

	/**
	 * A row of category links above the products.
	 *
	 * @param string $option "none", "images" or "names".
	 */
	private static function strip( string $option ): string {
		$products = '<rect x="6" y="24" width="52" height="10" rx="1.5" fill="currentColor" opacity="0.35"/>';
		if ( 'none' === $option ) {
			return $products;
		}
		$row = '';
		for ( $i = 0; $i < 4; $i++ ) {
			$x    = 6 + $i * 14;
			$row .= match ( $option ) {
				'images' => sprintf( '<circle cx="%1$d" cy="10" r="5" fill="currentColor"/><rect x="%2$d" y="17" width="10" height="2" rx="1" fill="currentColor" opacity="0.6"/>', $x + 5, $x ),
				'boxes'  => sprintf( '<rect x="%1$d.5" y="4.5" width="11" height="15" rx="2" fill="none" stroke="currentColor"/><rect x="%2$d" y="7" width="7" height="6" rx="1" fill="currentColor"/>', $x, $x + 2 ),
				'list'   => sprintf( '<rect x="%1$d.5" y="4.5" width="11" height="15" rx="2" fill="none" stroke="currentColor"/><rect x="%2$d" y="7" width="7" height="1.5" rx="0.75" fill="currentColor"/><rect x="%2$d" y="10.5" width="5" height="1.5" rx="0.75" fill="currentColor" opacity="0.6"/><rect x="%2$d" y="13.5" width="6" height="1.5" rx="0.75" fill="currentColor" opacity="0.6"/>', $x, $x + 2 ),
				default  => sprintf( '<rect x="%d.5" y="9.5" width="11" height="6" rx="3" fill="none" stroke="currentColor"/>', $x ),
			};
		}
		return $row . $products;
	}

	/**
	 * One product card in each style.
	 *
	 * @param string $option Card style.
	 */
	private static function card( string $option ): string {
		// A name line and a shorter price line; centred on the plain card.
		$lines = static function ( int $x, int $width ) use ( $option ): string {
			$short = (int) round( $width * 0.6 );
			return sprintf(
				'<rect x="%1$d" y="25" width="%2$d" height="2.5" rx="1" fill="currentColor"/><rect x="%3$d" y="30" width="%4$d" height="2.5" rx="1" fill="currentColor" opacity="0.6"/>',
				$x,
				$width,
				'plain' === $option ? $x + intdiv( $width - $short, 2 ) : $x,
				$short
			);
		};
		return match ( $option ) {
			'template' => '<g fill="none" stroke="currentColor" stroke-dasharray="3 2"><rect x="21.5" y="5.5" width="21" height="16" rx="2"/><rect x="21.5" y="24.5" width="21" height="9" rx="1"/></g>',
			'standard' => '<rect x="20" y="5" width="24" height="17" rx="2" fill="currentColor" opacity="0.35"/><rect x="20" y="25" width="10" height="2" rx="1" fill="currentColor" opacity="0.8"/><rect x="20" y="29" width="22" height="2.5" rx="1" fill="currentColor"/><rect x="20" y="33.5" width="13" height="2.5" rx="1" fill="currentColor" opacity="0.6"/>',
			'boxed'    => '<rect x="18.5" y="3.5" width="27" height="33" rx="2.5" fill="none" stroke="currentColor"/><rect x="21" y="6" width="22" height="15" rx="1.5" fill="currentColor" opacity="0.35"/>' . $lines( 21, 22 ),
			'overlay'  => '<rect x="20" y="5" width="24" height="17" rx="2" fill="currentColor" opacity="0.35"/><circle cx="40" cy="9" r="2.5" fill="currentColor"/><circle cx="40" cy="15.5" r="2.5" fill="currentColor"/>' . $lines( 20, 24 ),
			'plain'    => '<rect x="20" y="5" width="24" height="17" rx="2" fill="currentColor" opacity="0.35"/>' . $lines( 20, 24 ),
			default    => '',
		};
	}

	/**
	 * A product page: the picture, the text lines and, for three columns,
	 * a column of small products.
	 *
	 * @param string $option Layout.
	 */
	private static function product( string $option ): string {
		$text    = static fn ( int $x, int $width ): string => sprintf(
			'<rect x="%1$d" y="8" width="%2$d" height="3" rx="1" fill="currentColor"/><rect x="%1$d" y="14" width="%3$d" height="2.5" rx="1" fill="currentColor" opacity="0.6"/><rect x="%1$d" y="20" width="%2$d" height="5" rx="1.5" fill="currentColor" opacity="0.8"/>',
			$x,
			$width,
			(int) round( $width * 0.6 )
		);
		$picture = static fn ( int $x, int $width ): string => sprintf( '<rect x="%1$d" y="6" width="%2$d" height="28" rx="2" fill="currentColor" opacity="0.35"/>', $x, $width );
		return match ( $option ) {
			'template' => '<g fill="none" stroke="currentColor" stroke-dasharray="3 2"><rect x="6.5" y="6.5" width="26" height="27" rx="2"/><rect x="36.5" y="6.5" width="21" height="18" rx="1"/></g>',
			'left'     => $picture( 6, 28 ) . $text( 38, 20 ),
			'right'    => $text( 6, 20 ) . $picture( 30, 28 ),
			'three'    => $picture( 5, 22 ) . $text( 30, 14 ) . '<g fill="currentColor" opacity="0.5"><rect x="48" y="6" width="11" height="7" rx="1"/><rect x="48" y="16" width="11" height="7" rx="1"/><rect x="48" y="26" width="11" height="7" rx="1"/></g>',
			default    => '',
		};
	}

	/**
	 * The product pictures: one big picture and where the small ones go.
	 *
	 * @param string $option Small pictures.
	 */
	private static function thumbs( string $option ): string {
		$small = static function ( array $boxes ): string {
			$html = '';
			foreach ( $boxes as [ $x, $y ] ) {
				$html .= sprintf( '<rect x="%1$d" y="%2$d" width="7" height="7" rx="1" fill="currentColor" opacity="0.6"/>', $x, $y );
			}
			return $html;
		};
		return match ( $option ) {
			'template' => '<g fill="none" stroke="currentColor" stroke-dasharray="3 2"><rect x="18.5" y="5.5" width="27" height="29" rx="2"/></g>',
			'left'     => $small( array( array( 14, 6 ), array( 14, 15 ), array( 14, 24 ) ) ) . '<rect x="24" y="6" width="28" height="28" rx="2" fill="currentColor" opacity="0.35"/>',
			'bottom'   => '<rect x="19" y="4" width="26" height="22" rx="2" fill="currentColor" opacity="0.35"/>' . $small( array( array( 19, 29 ), array( 28.5, 29 ), array( 38, 29 ) ) ),
			'none'     => '<rect x="18" y="5" width="28" height="30" rx="2" fill="currentColor" opacity="0.35"/>',
			'grid'     => '<g fill="currentColor" opacity="0.35"><rect x="19" y="3" width="26" height="16" rx="2"/><rect x="19" y="21" width="12.5" height="8" rx="1.5"/><rect x="32.5" y="21" width="12.5" height="8" rx="1.5"/><rect x="19" y="31" width="12.5" height="6" rx="1.5"/><rect x="32.5" y="31" width="12.5" height="6" rx="1.5"/></g>',
			default    => '',
		};
	}

	/**
	 * The sections under the product: closed sections, tabs over one open
	 * section, or every section open.
	 *
	 * @param string $option "template", "tabs" or "open".
	 */
	private static function details( string $option ): string {
		$lines = static fn ( int $y ): string => sprintf( '<rect x="10" y="%1$d" width="44" height="2" rx="1" fill="currentColor" opacity="0.45"/><rect x="10" y="%2$d" width="30" height="2" rx="1" fill="currentColor" opacity="0.45"/>', $y, $y + 4 );
		return match ( $option ) {
			'template' => '<g fill="none" stroke="currentColor" stroke-dasharray="3 2"><rect x="8.5" y="6.5" width="47" height="7" rx="1.5"/><rect x="8.5" y="16.5" width="47" height="7" rx="1.5"/><rect x="8.5" y="26.5" width="47" height="7" rx="1.5"/></g>',
			'tabs'     => '<rect x="8" y="6" width="14" height="6" rx="1.5" fill="currentColor"/><rect x="25" y="6" width="14" height="6" rx="1.5" fill="currentColor" opacity="0.35"/><rect x="42" y="6" width="14" height="6" rx="1.5" fill="currentColor" opacity="0.35"/>' . $lines( 18 ) . $lines( 27 ),
			'open'     => '<rect x="8" y="5" width="22" height="3" rx="1" fill="currentColor"/>' . $lines( 10 ) . '<rect x="8" y="21" width="22" height="3" rx="1" fill="currentColor"/>' . $lines( 26 ),
			default    => '',
		};
	}

	/**
	 * Related products under the product.
	 *
	 * @param string $option "template", "grid" or "carousel".
	 */
	private static function related( string $option ): string {
		$card = static fn ( int $x, int $y, int $w ): string => sprintf( '<rect x="%1$d" y="%2$d" width="%3$d" height="%3$d" rx="1.5" fill="currentColor" opacity="0.35"/><rect x="%1$d" y="%4$d" width="%5$d" height="2" rx="1" fill="currentColor" opacity="0.6"/>', $x, $y, $w, $y + $w + 2, (int) round( $w * 0.7 ) );
		return match ( $option ) {
			'template' => '<g fill="none" stroke="currentColor" stroke-dasharray="3 2"><rect x="8.5" y="10.5" width="10" height="16" rx="1.5"/><rect x="21.5" y="10.5" width="10" height="16" rx="1.5"/><rect x="34.5" y="10.5" width="10" height="16" rx="1.5"/><rect x="47.5" y="10.5" width="8" height="16" rx="1.5"/></g>',
			'grid'     => $card( 8, 4, 10 ) . $card( 21, 4, 10 ) . $card( 34, 4, 10 ) . $card( 47, 4, 10 ) . $card( 8, 21, 10 ) . $card( 21, 21, 10 ) . $card( 34, 21, 10 ) . $card( 47, 21, 10 ),
			'carousel' => $card( 4, 11, 13 ) . $card( 20, 11, 13 ) . $card( 36, 11, 13 ) . '<rect x="52" y="11" width="10" height="13" rx="1.5" fill="currentColor" opacity="0.2"/><path d="M58 15l2.5 2.5L58 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>',
			default    => '',
		};
	}

	/**
	 * The sale badge on a product picture.
	 *
	 * @param string $option "template", "percent" or "none".
	 */
	private static function sale( string $option ): string {
		$picture = '<rect x="14" y="5" width="36" height="30" rx="2" fill="currentColor" opacity="0.35"/>';
		return $picture . match ( $option ) {
			'template' => '<rect x="17" y="8" width="13" height="6" rx="1.5" fill="currentColor"/>',
			'percent'  => '<rect x="17" y="8" width="13" height="6" rx="1.5" fill="currentColor"/><text x="23.5" y="12.8" font-size="5" font-family="sans-serif" text-anchor="middle" fill="#fff">-15%</text>',
			default    => '',
		};
	}
}
