<?php
/**
 * View, products per page and paging of the product listing pages.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * The "View and paging" shop settings:
 *
 * - products as a grid or a list (the list adds the short description);
 * - grid and list buttons beside the sorting menu, the visitor's choice
 *   kept in their browser;
 * - a products per page menu beside the sorting menu (a plain form, like
 *   WooCommerce's sorting);
 * - a "Load more" button, or loading while scrolling, after the products.
 *   The script asks WordPress's Interactivity router for the same page with
 *   more products, so add-to-cart buttons keep working; the page numbers
 *   stay in the markup for search engines and without the script.
 *
 * Every default leaves the template as it is, like Layout.
 */
final class Listing {

	public const SCRIPT = 'pergel/shop-listing';

	/**
	 * Query argument of the products per page.
	 */
	public const ARG = 'per_page';

	/**
	 * Highest products per page a visitor may ask for.
	 */
	public const MOST = 96;

	/**
	 * Browser storage key of the visitor's view.
	 */
	private const STORE = 'pergel-shop-view';

	/**
	 * Products per page before a visitor's choice; set while WooCommerce
	 * prepares the main query.
	 *
	 * @var int
	 */
	private static int $step = 0;

	/**
	 * Whether the head needs the script that applies the visitor's view.
	 *
	 * @var bool
	 */
	private static bool $head = false;

	/**
	 * Adds the front-end hooks; Layout calls it.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_assets' ) );
		add_filter( 'render_block_woocommerce/catalog-sorting', array( self::class, 'toolbar' ) );
		add_filter( 'render_block_woocommerce/product-price', array( self::class, 'summary' ), 10, 3 );
		add_filter( 'render_block_core/query-pagination', array( self::class, 'more' ), 10, 3 );
		add_action( 'wp_head', array( self::class, 'head' ), 1 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
	}

	/**
	 * Registers the script module. Hooked on init.
	 */
	public static function register_assets(): void {
		wp_register_script_module(
			self::SCRIPT,
			plugins_url( 'assets/shop-listing.js', FILE ),
			array(
				array(
					'id'     => '@wordpress/interactivity-router',
					'import' => 'dynamic',
				),
			),
			VERSION,
			array( 'fetchpriority' => 'low' )
		);
	}

	/**
	 * Numbers of the products per page menu, from the setting: whole
	 * numbers from 1 to 96, at most six, smallest first.
	 *
	 * @return list<int>
	 */
	public static function menu(): array {
		$numbers = array();
		$parts   = preg_split( '/[\s,;]+/', (string) Settings::get( 'shop.per_page_menu' ) );
		foreach ( false === $parts ? array() : $parts as $part ) {
			$number = ctype_digit( $part ) ? (int) $part : 0;
			if ( $number >= 1 && $number <= self::MOST ) {
				$numbers[ $number ] = $number;
			}
		}
		ksort( $numbers );
		return array_slice( array_values( $numbers ), 0, 6 );
	}

	/**
	 * Whether the next products load on the same page.
	 */
	public static function loads_more(): bool {
		return in_array( Layout::get( 'shop.paging' ), array( 'more', 'scroll' ), true );
	}

	/**
	 * Whether this page can show the list: as its view, or by the buttons.
	 */
	public static function list_possible(): bool {
		return 'list' === Layout::get( 'shop.view' ) || true === Settings::get( 'shop.view_switch' );
	}

	/**
	 * Products per page: a visitor's choice from the menu, or the number
	 * the "Load more" script asks for. Layout calls it after its own number.
	 *
	 * @param int $per_page Products per page before the visitor's choice.
	 */
	public static function per_page( int $per_page ): int {
		self::$step = $per_page;
		$asked      = self::asked();
		if ( $asked > 0 && ( in_array( $asked, self::menu(), true ) || self::loads_more() ) ) {
			return $asked;
		}
		return $per_page;
	}

	/**
	 * The products per page in the address, or 0.
	 */
	private static function asked(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A view choice, like WooCommerce's orderby.
		$value = isset( $_GET[ self::ARG ] ) && is_string( $_GET[ self::ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::ARG ] ) ) : '';
		$value = ctype_digit( $value ) ? (int) $value : 0;
		return $value >= 1 && $value <= self::MOST ? $value : 0;
	}

	/**
	 * Classes on the main product grid, and its data for the script. Layout
	 * calls it while the grid renders.
	 *
	 * @param \WP_HTML_Tag_Processor $tags Grid markup, at its first tag.
	 * @return bool Whether the grid changed.
	 */
	public static function grid( \WP_HTML_Tag_Processor $tags ): bool {
		$list   = 'list' === Layout::get( 'shop.view' );
		$switch = true === Settings::get( 'shop.view_switch' );
		if ( ! $list && ! $switch ) {
			return false;
		}
		$tags->add_class( 'pergel-shop-grid' );
		if ( $list ) {
			$tags->add_class( 'pergel-view-list' );
		}
		if ( $switch ) {
			self::$head = true;
			wp_enqueue_script_module( self::SCRIPT );
		}
		return true;
	}

	/**
	 * The grid and list buttons and the products per page menu, beside the
	 * sorting menu.
	 *
	 * @param string $html Rendered sorting menu.
	 */
	public static function toolbar( string $html ): string {
		if ( '' === $html || ! Layout::listing() ) {
			return $html;
		}
		$parts = array();
		$menu  = self::menu();
		if ( $menu ) {
			$parts[] = self::menu_form( $menu );
		}
		if ( true === Settings::get( 'shop.view_switch' ) ) {
			$parts[] = self::buttons();
		}
		if ( ! $parts ) {
			return $html;
		}
		Layout::style();
		wp_enqueue_script_module( self::SCRIPT );
		return '<div class="pergel-shop-toolbar">' . $html . implode( '', $parts ) . '</div>';
	}

	/**
	 * The products per page form. It keeps the other query arguments
	 * (sorting, filters) and goes back to the first page.
	 *
	 * @param array<int, int> $menu Numbers.
	 * @phpstan-param list<int> $menu
	 */
	private static function menu_form( array $menu ): string {
		$current = self::asked();
		if ( ! in_array( $current, $menu, true ) ) {
			$current = in_array( self::$step, $menu, true ) ? self::$step : 0;
		}
		$options = '';
		if ( 0 === $current ) {
			$options .= sprintf( '<option value="" selected>%s</option>', esc_html( (string) self::$step ) );
		}
		foreach ( $menu as $number ) {
			$options .= sprintf( '<option value="%1$d"%2$s>%1$d</option>', $number, selected( $current, $number, false ) );
		}
		$keep = function_exists( 'wc_query_string_form_fields' )
			? (string) wc_query_string_form_fields( null, array( self::ARG, 'paged', 'product-page', 'submit' ), '', true )
			: '';
		$url  = explode( '?', (string) get_pagenum_link( 1, false ), 2 )[0];
		return sprintf(
			'<form class="pergel-per-page" method="get" action="%1$s"><label for="pergel-per-page">%2$s</label><select id="pergel-per-page" name="%3$s">%4$s</select>%5$s<noscript><button type="submit">%6$s</button></noscript></form>',
			esc_url( $url ),
			esc_html__( 'Show', 'pergel-engine' ),
			esc_attr( self::ARG ),
			$options,
			$keep,
			esc_html__( 'Apply', 'pergel-engine' )
		);
	}

	/**
	 * The grid and list buttons; hidden until the script shows them.
	 */
	private static function buttons(): string {
		$view    = 'list' === Layout::get( 'shop.view' ) ? 'list' : 'grid';
		$buttons = '';
		foreach ( array(
			'grid' => array( __( 'Grid', 'pergel-engine' ), '<path d="M3 3h6v6H3zM11 3h6v6h-6zM3 11h6v6H3zM11 11h6v6h-6z"/>' ),
			'list' => array( __( 'List', 'pergel-engine' ), '<path d="M3 3h5v5H3zM10 4h7v1.5h-7zM10 6.5h5V8h-5zM3 12h5v5H3zM10 13h7v1.5h-7zM10 15.5h5V17h-5z"/>' ),
		) as $option => [ $label, $shape ] ) {
			$buttons .= sprintf(
				'<button type="button" class="pergel-view-button" data-pergel-view="%1$s" aria-pressed="%2$s" title="%3$s"><svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false">%4$s</svg><span class="screen-reader-text">%3$s</span></button>',
				esc_attr( $option ),
				$option === $view ? 'true' : 'false',
				esc_attr( $label ),
				$shape
			);
		}
		return sprintf(
			'<div class="pergel-view-switch" role="group" aria-label="%1$s" data-pergel-default="%2$s" hidden>%3$s</div>',
			esc_attr__( 'View', 'pergel-engine' ),
			esc_attr( $view ),
			$buttons
		);
	}

	/**
	 * The short description after the price, in grids that can show the
	 * list; the grid view hides it.
	 *
	 * @param string    $html     Rendered price.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function summary( string $html, array $block, \WP_Block $instance ): string {
		if ( ! self::main_grid( $instance ) || ! self::list_possible() ) {
			return $html;
		}
		$summary = new \WP_Block(
			array(
				'blockName'    => 'woocommerce/product-summary',
				'attrs'        => array(
					'isDescendentOfQueryLoop' => true,
					'showDescriptionIfEmpty'  => true,
					'summaryLength'           => 30,
					'className'               => 'pergel-list-summary',
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			$instance->context
		);
		return $html . $summary->render();
	}

	/**
	 * Whether a block sits in the main product grid of a listing page.
	 *
	 * @param \WP_Block $instance Block instance.
	 */
	private static function main_grid( \WP_Block $instance ): bool {
		$query = $instance->context['query'] ?? null;
		return is_array( $query ) && true === ( $query['inherit'] ?? null ) && Layout::listing();
	}

	/**
	 * The "Load more" link after the page numbers: a real link to the next
	 * page, which the script turns into loading on the same page.
	 *
	 * @param string    $html     Rendered page numbers.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function more( string $html, array $block, \WP_Block $instance ): string {
		if ( ! self::main_grid( $instance ) || ! self::loads_more() ) {
			return $html;
		}
		global $wp_query;
		$pages = $wp_query instanceof \WP_Query ? (int) $wp_query->max_num_pages : 0;
		$next  = get_next_posts_page_link( $pages );
		if ( ! is_string( $next ) || '' === $next ) {
			return $html;
		}
		Layout::style();
		wp_enqueue_script_module( self::SCRIPT );
		// Only the first page grows; later pages follow the link.
		$step = max( 1, (int) get_query_var( 'paged' ) ) > 1 ? 0 : max( 1, self::$step );
		return $html . sprintf(
			'<div class="pergel-load-more"><a class="pergel-load-more__button wp-element-button" href="%1$s" data-pergel-more="%2$s" data-pergel-step="%3$d" data-pergel-most="%4$d" data-pergel-busy="%5$s">%6$s</a></div>',
			esc_url( $next ),
			esc_attr( (string) Layout::get( 'shop.paging' ) ),
			$step,
			self::MOST,
			esc_attr__( 'Loading products…', 'pergel-engine' ),
			esc_html__( 'Load more products', 'pergel-engine' )
		);
	}

	/**
	 * Applies the visitor's view before the page draws, so the grid does
	 * not jump. Only on pages with the buttons.
	 */
	public static function head(): void {
		if ( ! self::$head ) {
			return;
		}
		wp_print_inline_script_tag(
			sprintf(
				'try{var v=localStorage.getItem(%s);if(v==="grid"||v==="list"){document.documentElement.dataset.pergelView=v}}catch(e){}',
				wp_json_encode( self::STORE )
			)
		);
	}

	/**
	 * Pages with a visitor's products per page stay out of search results;
	 * they repeat the normal pages.
	 *
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( self::asked() > 0 && Layout::listing() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
}
