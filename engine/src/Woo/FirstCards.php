<?php
/**
 * The pictures of the first products on a listing page load at once.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * WooCommerce gives every product picture loading="lazy", so the first
 * card, often the largest thing on the first screen, waits for the layout
 * before it loads. The first row of the main grid on phones loads at once
 * instead, with fetchpriority="high": the pictures in a row are the same
 * size, so any of them can be the largest paint. Without lazy loading the
 * browser cannot work out "sizes=auto", so the width comes from the
 * products per row.
 */
final class FirstCards {

	/**
	 * Pictures still to load at once on this page.
	 *
	 * @var int
	 */
	private static int $left = 0;

	/**
	 * Whether this page's first pictures ask for high priority.
	 *
	 * @var bool
	 */
	private static bool $high = false;

	/**
	 * The "sizes" value for this page's grid.
	 *
	 * @var string
	 */
	private static string $sizes = '';

	/**
	 * Whether this page's main grid was counted already.
	 *
	 * @var bool
	 */
	private static bool $armed = false;

	/**
	 * Called by Layout::context() when the main grid of a listing page
	 * starts, with its products per row on wide screens.
	 *
	 * @param int $columns Products per row on wide screens.
	 */
	public static function arm( int $columns ): void {
		if ( self::$armed ) {
			return;
		}
		self::$armed = true;
		$columns     = max( 1, $columns );
		$list        = Listing::list_possible();
		$counts      = array();
		foreach ( array(
			'phone'  => 'shop.columns_phone',
			'tablet' => 'shop.columns_tablet',
		) as $screen => $path ) {
			$value             = Layout::get( $path );
			$counts[ $screen ] = is_string( $value ) && ctype_digit( $value ) && (int) $value > 0
				? (int) $value
				// WooCommerce's grid keeps cards at least 150px wide.
				: min( $columns, 2 );
		}
		$wide = $columns;
		foreach ( Listing::columns() as $choice ) {
			$wide = $choice > 0 ? min( $wide, $choice ) : $wide;
		}
		if ( $list ) {
			// In the list the picture takes 45% of the row.
			$counts = array_map( fn ( int $count ): int => min( $count, 2 ), $counts );
			$wide   = min( $wide, 2 );
		}
		self::$left  = 'list' === Layout::get( 'shop.view' ) ? 1 : $counts['phone'];
		self::$sizes = self::sizes( $counts['phone'], $counts['tablet'], $wide );
	}

	/**
	 * The "sizes" value: a share of the screen on phones and tablets, a
	 * share of the wide page width on larger screens.
	 *
	 * @param int $phone  Products per row on phones.
	 * @param int $tablet Products per row on tablets.
	 * @param int $wide   Products per row on wide screens.
	 */
	public static function sizes( int $phone, int $tablet, int $wide ): string {
		$parts = array(
			'(max-width: 599px) ' . self::share( '100vw', $phone ),
			'(max-width: 1023px) ' . self::share( '100vw', $tablet ),
		);
		$page  = 'full' === Layout::get( 'shop.width' ) ? '' : (string) ( wp_get_global_settings( array( 'layout', 'wideSize' ) ) ?? '' );
		if ( 1 === preg_match( '/^\d+(?:\.\d+)?px$/', $page ) ) {
			$parts[] = '(min-width: ' . $page . ') ' . self::share( $page, $wide );
		}
		$parts[] = self::share( '100vw', $wide );
		return implode( ', ', $parts );
	}

	/**
	 * A width split between the products in a row.
	 *
	 * @param string $width CSS width.
	 * @param int    $count Products per row.
	 */
	private static function share( string $width, int $count ): string {
		return $count <= 1 ? $width : sprintf( 'calc(%s / %d)', $width, $count );
	}

	/**
	 * Loads the first product pictures at once (wp_get_attachment_image_attributes).
	 * WooCommerce marks its product pictures with data-testid="product-image".
	 *
	 * @param array<string, mixed> $attr Picture attributes.
	 * @return array<string, mixed>
	 */
	public static function attributes( array $attr ): array {
		if ( self::$left <= 0 || 'product-image' !== ( $attr['data-testid'] ?? '' ) ) {
			return $attr;
		}
		--self::$left;
		$attr['loading'] = 'eager';
		if ( isset( $attr['srcset'] ) ) {
			$attr['sizes'] = self::$sizes;
		}
		// Not when a picture higher up the page asked first.
		if ( wp_high_priority_element_flag() ) {
			self::$high = true;
			wp_high_priority_element_flag( false );
		}
		if ( self::$high ) {
			$attr['fetchpriority'] = 'high';
		}
		return $attr;
	}

	/**
	 * Stops after the main grid (render_block_woocommerce/product-collection),
	 * so pictures further down the page keep loading lazily.
	 *
	 * @param string $html Rendered block.
	 */
	public static function done( string $html ): string {
		if ( self::$armed ) {
			self::$left = 0;
		}
		return $html;
	}

	/**
	 * Forgets the page; tests call it.
	 */
	public static function flush(): void {
		self::$left  = 0;
		self::$high  = false;
		self::$sizes = '';
		self::$armed = false;
	}
}
