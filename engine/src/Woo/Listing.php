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
 * - list and grid buttons beside the sorting menu, with a button for each
 *   products per row the visitor may pick on wide screens; their choice
 *   is kept in their browser;
 * - a products per page menu beside the sorting menu (a plain form, like
 *   WooCommerce's sorting), left out while products load on scrolling;
 * - a "Load more" button, or loading while scrolling, after the products.
 *   The script asks WordPress's Interactivity router for the same page with
 *   more products, so add-to-cart buttons keep working; the page numbers
 *   stay in the markup for search engines and without the script.
 *
 * Every default leaves the template as it is, like Layout.
 */
final class Listing {

	public const SCRIPT = Layout::LISTING_SCRIPT;

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
	 * Whether the short description follows the name, the card having no
	 * price to follow; set when the main grid starts, unset when it ends
	 * (the name block does not get the query to tell the main grid by).
	 *
	 * @var bool
	 */
	private static bool $after_title = false;

	/**
	 * Hooks for the products of the main grid: the short description and
	 * the "Load more" link. Layout calls it when the main grid of a listing
	 * page starts, so other pages never load this class.
	 *
	 * @param array $template The grid's Product Template block, parsed.
	 * @phpstan-param array<string, mixed> $template
	 */
	public static function prepare( array $template = array() ): void {
		// Cards without a price get the short description after the name.
		self::$after_title = array() !== $template && ! self::holds( $template, 'woocommerce/product-price' );
		foreach ( array(
			'woocommerce/product-price'    => array( 'summary', 3 ),
			'core/post-title'              => array( 'title_summary', 3 ),
			'woocommerce/product-template' => array( 'grid_done', 1 ),
			'core/query-pagination'        => array( 'more', 3 ),
		) as $name => [ $method, $args ] ) {
			if ( ! has_filter( "render_block_$name", array( self::class, $method ) ) ) {
				add_filter( "render_block_$name", array( self::class, $method ), 10, $args );
			}
		}
	}

	/**
	 * Whether a parsed block holds a block of this name.
	 *
	 * @param array  $block Parsed block.
	 * @param string $name  Block name.
	 * @phpstan-param array<string, mixed> $block
	 */
	private static function holds( array $block, string $name ): bool {
		foreach ( is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array() as $inner ) {
			if ( is_array( $inner ) && ( ( $inner['blockName'] ?? null ) === $name || self::holds( $inner, $name ) ) ) {
				return true;
			}
		}
		return false;
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
			// Block themes draw the template before the head.
			if ( ! has_action( 'wp_head', array( self::class, 'head' ) ) ) {
				add_action( 'wp_head', array( self::class, 'head' ), 1 );
			}
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
		// While products load as the visitor scrolls, a per page number means nothing to them.
		$menu = 'scroll' === Layout::get( 'shop.paging' ) ? array() : self::menu();
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
	 * Products per row the visitor can pick, from the setting.
	 *
	 * @return list<int>
	 */
	public static function columns(): array {
		$value = (string) Settings::get( 'shop.view_columns' );
		return '' === $value ? array() : array_map( 'intval', explode( ',', $value ) );
	}

	/**
	 * The list button, then the grid buttons: one per products per row
	 * chosen in the settings, for wide screens, and one plain grid button
	 * for narrower screens (or for all, with no number chosen). Hidden
	 * until the script shows them.
	 */
	private static function buttons(): string {
		$columns = self::columns();
		$wide    = Layout::get( 'shop.columns' );
		$view    = 'list' === Layout::get( 'shop.view' ) ? 'list' : 'grid';
		if ( 'grid' === $view && is_string( $wide ) && in_array( (int) $wide, $columns, true ) ) {
			$view = 'grid-' . $wide;
		}
		$options = array(
			'list' => array( __( 'List', 'pergel-engine' ), '<path d="M3 3h5v5H3zM10 4h7v1.5h-7zM10 6.5h5V8h-5zM3 12h5v5H3zM10 13h7v1.5h-7zM10 15.5h5V17h-5z"/>', '' ),
			'grid' => array( __( 'Grid', 'pergel-engine' ), '<path d="M3 3h6v6H3zM11 3h6v6h-6zM3 11h6v6H3zM11 11h6v6h-6z"/>', $columns ? ' pergel-view-button--narrow' : '' ),
		);
		foreach ( $columns as $count ) {
			$tile  = ( 16 - 1.5 * ( $count - 1 ) ) / $count;
			$shape = '';
			for ( $i = 0; $i < $count; $i++ ) {
				$x      = round( 2 + $i * ( $tile + 1.5 ), 2 );
				$shape .= sprintf( '<rect x="%1$s" y="3" width="%2$s" height="6.25" rx="0.75"/><rect x="%1$s" y="10.75" width="%2$s" height="6.25" rx="0.75"/>', $x, round( $tile, 2 ) );
			}
			$options[ "grid-$count" ] = array(
				/* translators: %d: number of products in a row, 2 to 6. */
				sprintf( __( '%d per row', 'pergel-engine' ), $count ),
				$shape,
				' pergel-view-button--wide',
			);
		}
		$buttons = '';
		foreach ( $options as $option => [ $label, $shape, $class ] ) {
			$buttons .= sprintf(
				'<button type="button" class="pergel-view-button%5$s" data-pergel-view="%1$s" aria-pressed="%2$s" title="%3$s"><svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" aria-hidden="true" focusable="false">%4$s</svg><span class="screen-reader-text">%3$s</span></button>',
				esc_attr( $option ),
				$option === $view || ( 'grid' === $option && str_starts_with( $view, 'grid-' ) ) ? 'true' : 'false',
				esc_attr( $label ),
				$shape,
				esc_attr( $class )
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
	 * The short description after the name, in cards without a price.
	 *
	 * @param string    $html     Rendered name.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function title_summary( string $html, array $block, \WP_Block $instance ): string {
		return self::$after_title && self::list_possible() ? $html . self::summary_block( $instance ) : $html;
	}

	/**
	 * Ends the short description after the name with the main grid.
	 *
	 * @param string $html Rendered Product Template.
	 */
	public static function grid_done( string $html ): string {
		self::$after_title = false;
		return $html;
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
		return $html . self::summary_block( $instance );
	}

	/**
	 * The short description of the product a block shows, for the list.
	 *
	 * @param \WP_Block $instance A block of the product's card.
	 */
	private static function summary_block( \WP_Block $instance ): string {
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
		return $summary->render();
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
		wp_print_inline_script_tag(
			sprintf(
				'try{var v=localStorage.getItem(%s);if(/^(grid|list|grid-[2-6])$/.test(v)){document.documentElement.dataset.pergelView=v}}catch(e){}',
				wp_json_encode( self::STORE )
			)
		);
	}

	/**
	 * Whether this is a page with a visitor's products per page: it stays
	 * out of search results, since it repeats the normal pages.
	 */
	public static function noindex(): bool {
		return self::asked() > 0 && Layout::listing();
	}
}
