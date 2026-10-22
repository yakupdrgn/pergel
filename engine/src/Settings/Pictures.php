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
			'shop.columns', 'shop.columns_tablet', 'shop.columns_phone' => self::grid( $option ),
			'shop.width' => self::width( $option ),
			'shop.filters', 'shop.drawer_side' => self::filters( $option ),
			'shop.filters_sticky' => self::sticky( $option ),
			'shop.view' => self::view( $option ),
			'shop.paging' => self::paging( $option ),
			'shop.category_strip' => self::strip( $option ),
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
			$row .= 'images' === $option
				? sprintf( '<circle cx="%1$d" cy="10" r="5" fill="currentColor"/><rect x="%2$d" y="17" width="10" height="2" rx="1" fill="currentColor" opacity="0.6"/>', $x + 5, $x )
				: sprintf( '<rect x="%d.5" y="9.5" width="11" height="6" rx="3" fill="none" stroke="currentColor"/>', $x );
		}
		return $row . $products;
	}
}
