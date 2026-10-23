<?php
/**
 * Shortcuts at the bottom of phone screens.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * A row of shortcuts fixed to the bottom of narrow screens: the shop, the
 * filters of a shop page, a product search, the wishlist, the account and
 * the cart, each with its own setting. Not on the checkout, where it would
 * only distract.
 *
 * The bar is printed in the footer. The filter button opens WooCommerce's
 * own filter panel, so it shows only where that panel is (the script finds
 * it). The search opens a dialog with a search block, which the live search
 * of the Search module serves too. The wishlist count is the one the list
 * script fills; the cart count follows the cart through assets/cart-sync.js.
 */
final class BottomBar {

	/**
	 * Handles of the style and the script module.
	 */
	public const ASSET = 'pergel-bottom-bar';

	public const SCRIPT = 'pergel/bottom-bar';

	/**
	 * Icons drawn for the engine, 24×24, stroked with the text colour.
	 */
	private const ICONS = array(
		'shop'     => '<path d="M5 8h14l-1.2 12H6.2L5 8Z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
		'filter'   => '<path d="M4 6h16M7 12h10M10 18h4"/>',
		'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/>',
		'wishlist' => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.1a4.3 4.3 0 0 1 7.5 2.7C19.5 15.4 12 20 12 20Z"/>',
		'account'  => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
		'cart'     => '<path d="M3 4h2l2.2 10.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L20.5 8H6"/><circle cx="9.5" cy="19.5" r="1.25"/><circle cx="17" cy="19.5" r="1.25"/>',
		'close'    => '<path d="m6 6 12 12M18 6 6 18"/>',
	);

	/**
	 * Adds the hooks. Woo\Module calls it on the site while the setting is
	 * on.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'assets' ) );
		add_action( 'wp', array( self::class, 'page' ) );
	}

	/**
	 * Registers the style and the script module. Hooked on init.
	 */
	public static function assets(): void {
		wp_register_style( self::ASSET, plugins_url( 'assets/bottom-bar.css', FILE ), array(), VERSION );
		wp_style_add_data( self::ASSET, 'path', dirname( FILE ) . '/assets/bottom-bar.css' );
		wp_register_script_module( self::SCRIPT, plugins_url( 'assets/bottom-bar.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );
	}

	/**
	 * Adds the bar to this page, unless it is the checkout or the bar would
	 * be empty. Hooked on wp, when the page is known.
	 */
	public static function page(): void {
		$items = self::items();
		if ( is_checkout() || array() === $items ) {
			return;
		}
		wp_enqueue_style( self::ASSET );
		wp_enqueue_script_module( self::SCRIPT );
		if ( isset( $items['cart'] ) ) {
			wp_enqueue_script_module( Layout::CART_SCRIPT );
		}
		if ( isset( $items['wishlist'] ) ) {
			Lists::enqueue();
		}
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_action( 'wp_footer', array( self::class, 'render' ), 5 );
	}

	/**
	 * Marks the page, so the theme and the sticky add-to-cart bar leave
	 * room for the bar.
	 *
	 * @param array<int, string> $classes Body classes.
	 * @return array<int, string>
	 */
	public static function body_class( array $classes ): array {
		$classes[] = 'has-pergel-bottom-bar';
		return $classes;
	}

	/**
	 * The items that are on and have somewhere to go, in order, with their
	 * link ('' for a button).
	 *
	 * @return array<string, string>
	 */
	public static function items(): array {
		$items = array();
		foreach ( array( 'shop', 'filter', 'search', 'wishlist', 'account', 'cart' ) as $item ) {
			if ( true !== Settings::get( "woo.bar_$item" ) ) {
				continue;
			}
			$link = match ( $item ) {
				'shop'     => (string) wc_get_page_permalink( 'shop' ),
				'wishlist' => in_array( 'wishlist', Lists::linked(), true ) ? Lists::page( 'wishlist' ) : null,
				'account'  => (string) wc_get_page_permalink( 'myaccount' ),
				'cart'     => (string) wc_get_cart_url(),
				'filter'   => Layout::listing() ? '' : null,
				default    => '',
			};
			if ( null !== $link && ( '' !== $link || in_array( $item, array( 'filter', 'search' ), true ) ) ) {
				$items[ $item ] = $link;
			}
		}
		return $items;
	}

	/**
	 * Prints the bar and the search dialog. Hooked on wp_footer.
	 */
	public static function render(): void {
		echo self::markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in markup().
	}

	/**
	 * The bar and the search dialog.
	 */
	public static function markup(): string {
		$labels  = array(
			'shop'     => __( 'Shop', 'pergel-engine' ),
			'filter'   => __( 'Filter', 'pergel-engine' ),
			'search'   => __( 'Search', 'pergel-engine' ),
			'wishlist' => __( 'Wishlist', 'pergel-engine' ),
			'account'  => __( 'Account', 'pergel-engine' ),
			'cart'     => __( 'Cart', 'pergel-engine' ),
		);
		$current = array(
			'shop'     => is_shop(),
			'account'  => is_account_page(),
			'cart'     => is_cart(),
			'wishlist' => is_page() && (string) get_permalink() === Lists::page( 'wishlist' ),
		);
		$html    = '';
		$search  = false;
		foreach ( self::items() as $item => $link ) {
			$label = '<span class="pergel-bottom-bar__label">' . esc_html( $labels[ $item ] ) . '</span>';
			$count = '';
			if ( 'cart' === $item ) {
				$items = WC()->cart instanceof \WC_Cart ? WC()->cart->get_cart_contents_count() : 0;
				$count = '<span class="pergel-bottom-bar__count" data-pergel-cart-count' . ( $items > 0 ? '' : ' hidden' ) . '>' . esc_html( (string) $items ) . '</span><span class="pergel-sr"> ' . esc_html__( 'products', 'pergel-engine' ) . '</span>';
			} elseif ( 'wishlist' === $item ) {
				$count = '<span class="pergel-bottom-bar__count" data-pergel-count="wishlist" hidden></span>';
			}
			if ( 'filter' === $item ) {
				$html .= '<li hidden data-pergel-bar-filter><button type="button" class="pergel-bottom-bar__item">' . self::icon( $item ) . $label . '</button></li>';
			} elseif ( 'search' === $item ) {
				$search = true;
				$html  .= '<li><button type="button" class="pergel-bottom-bar__item" aria-haspopup="dialog" aria-controls="pergel-bottom-search" data-pergel-bar-search>' . self::icon( $item ) . $label . '</button></li>';
			} else {
				$html .= '<li><a class="pergel-bottom-bar__item" href="' . esc_url( $link ) . '"' . ( ! empty( $current[ $item ] ) ? ' aria-current="page"' : '' ) . '>' . self::icon( $item ) . $label . $count . '</a></li>';
			}
		}
		$dialog = $search ? self::search() : '';
		return '<nav class="pergel-bottom-bar" aria-label="' . esc_attr__( 'Shop shortcuts', 'pergel-engine' ) . '"><ul class="pergel-bottom-bar__list" role="list">' . $html . '</ul></nav>' . $dialog;
	}

	/**
	 * The search dialog: a search block that looks among the products.
	 */
	private static function search(): string {
		$form = render_block(
			array(
				'blockName'    => 'core/search',
				'attrs'        => array(
					'label'      => __( 'Search products', 'pergel-engine' ),
					'showLabel'  => false,
					'buttonText' => __( 'Search', 'pergel-engine' ),
					'query'      => array( 'post_type' => 'product' ),
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		return '<dialog id="pergel-bottom-search" class="pergel-bottom-search" aria-labelledby="pergel-bottom-search-title">'
			. '<div class="pergel-bottom-search__head"><h2 id="pergel-bottom-search-title" class="pergel-bottom-search__title">' . esc_html__( 'Search products', 'pergel-engine' ) . '</h2>'
			. '<button type="button" class="pergel-bottom-search__close" data-pergel-bar-close>' . self::icon( 'close' ) . '<span class="pergel-sr">' . esc_html__( 'Close', 'pergel-engine' ) . '</span></button></div>'
			. $form . '</dialog>';
	}

	/**
	 * An inline icon.
	 *
	 * @param string $name Icon name.
	 */
	private static function icon( string $name ): string {
		return '<svg class="pergel-bottom-bar__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . self::ICONS[ $name ] . '</svg>';
	}
}
