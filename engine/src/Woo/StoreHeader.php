<?php
/**
 * The store header.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Blocks\Header;
use Pergel\Settings\Settings;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Draws a shop header in place of the theme's header part when
 * shop.header_type is "store": a top bar, a row with the logo, a product
 * search with a category list, contact, account, the lists and the cart,
 * then a bar with the menu. The items of the top bar and of the row are
 * ticked and ordered in the settings; the look values become custom
 * properties on the wrapper.
 *
 * The part is swapped on pre_render_block, so a header saved in the Site
 * Editor is swapped too and the theme's header is not drawn first. The
 * part's own options (sticky, overlay) stay: the wrapper gets the classes
 * of Blocks\Header. The filter is added on wp, once the settings (and a
 * preview's values) and the page are known, and only where the header
 * shows.
 */
final class StoreHeader {

	use LookValues;

	public const STYLE = 'pergel-store-header';

	/**
	 * Default items of each row; every item of the field can be added.
	 */
	public const DEFAULTS = array(
		'top' => array( 'text', 'menu' ),
		'row' => array( 'logo', 'search', 'contact', 'account', 'lists', 'cart' ),
	);

	/**
	 * Line icons, drawn like the bar on phones.
	 */
	private const ICONS = array(
		'phone'   => '<path d="M5 4h3l1.5 4-2 1.2a11 11 0 0 0 5.3 5.3l1.2-2 4 1.5v3a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'email'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'address' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'account' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
	);

	/**
	 * Hooks the header in.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_assets' ) );
		add_action( 'wp', array( self::class, 'load' ) );
	}

	/**
	 * Registers the CSS; core inlines it, it is small. Hooked on init.
	 */
	public static function register_assets(): void {
		wp_register_style( self::STYLE, plugins_url( 'assets/store-header.css', FILE ), array(), VERSION );
		wp_style_add_data( self::STYLE, 'path', dirname( FILE ) . '/assets/store-header.css' );
	}

	/**
	 * Adds the swap where the store header shows. Hooked on wp.
	 */
	public static function load(): void {
		if ( self::applies() ) {
			add_filter( 'pre_render_block', array( self::class, 'swap' ), 10, 2 );
		}
	}

	/**
	 * Whether this page shows the store header: on every page, or on the
	 * shop pages (shop, categories, products, cart, checkout, account).
	 */
	public static function applies(): bool {
		if ( 'store' !== Settings::get( 'shop.header_type' ) ) {
			return false;
		}
		return 'site' === Settings::get( 'shop.header_on' ) || is_woocommerce() || is_cart() || is_checkout() || is_account_page();
	}

	/**
	 * Filter for pre_render_block: the store header instead of the header
	 * part.
	 *
	 * @param mixed $pre   Output so far; null renders the block.
	 * @param mixed $block Parsed block.
	 * @return mixed
	 */
	public static function swap( mixed $pre, mixed $block ): mixed {
		if ( null !== $pre || ! is_array( $block ) || 'core/template-part' !== ( $block['blockName'] ?? null ) ) {
			return $pre;
		}
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		if ( 'header' !== ( $attrs['slug'] ?? '' ) && 'header' !== ( $attrs['area'] ?? '' ) ) {
			return $pre;
		}
		return self::part( $attrs );
	}

	/**
	 * The header with the part's wrapper.
	 *
	 * @param array<string, mixed> $attrs Attributes of the header part.
	 */
	public static function part( array $attrs ): string {
		$tag     = is_string( $attrs['tagName'] ?? null ) && tag_escape( $attrs['tagName'] ) === $attrs['tagName'] ? $attrs['tagName'] : 'header';
		$options = Header::classes( $attrs );
		$classes = array_merge( array( 'wp-block-template-part', self::STYLE ), $options );
		if ( is_string( $attrs['className'] ?? null ) ) {
			$classes[] = $attrs['className'];
		}
		if ( array() !== $options ) {
			wp_enqueue_style( Header::STYLE );
			wp_enqueue_script_module( Header::SCRIPT );
		}
		wp_enqueue_style( self::STYLE );

		$rows = self::top() . self::row() . self::bar();
		if ( 'header' !== $tag ) {
			$rows = '<header class="pergel-store-header__rows">' . $rows . '</header>';
		}
		$style = self::style();
		return sprintf(
			'<%1$s class="%2$s"%3$s>%4$s</%1$s>',
			$tag,
			esc_attr( implode( ' ', $classes ) ),
			'' !== $style ? ' style="' . esc_attr( $style ) . '"' : '',
			$rows
		);
	}

	/**
	 * The look values as custom properties; unset values are left out, so
	 * the CSS falls back to the theme's colours.
	 */
	private static function style(): string {
		$props = array(
			'--pergel-sh-top-bg'     => self::color( 'header_top_bg' ),
			'--pergel-sh-top-text'   => self::color( 'header_top_color' ),
			'--pergel-sh-bg'         => self::color( 'header_bg' ),
			'--pergel-sh-pad'        => self::px( 'header_padding' ),
			'--pergel-sh-radius'     => self::px( 'search_radius' ),
			'--pergel-sh-button'     => self::color( 'search_button' ),
			'--pergel-sh-bar-bg'     => self::color( 'header_bar_bg' ),
			'--pergel-sh-bar-text'   => self::color( 'header_bar_color' ),
			'--pergel-sh-bar-height' => self::px( 'header_bar_height' ),
		);
		$style = '';
		foreach ( array_filter( $props ) as $name => $value ) {
			$style .= $name . ':' . $value . ';';
		}
		return $style;
	}

	/**
	 * The items of a row, in order.
	 *
	 * @param string $key  Field key.
	 * @param string $kind top or row.
	 * @return list<string>
	 */
	public static function items( string $key, string $kind ): array {
		$value = Settings::get( "shop.$key" );
		if ( ! is_string( $value ) || '' === $value ) {
			return self::DEFAULTS[ $kind ];
		}
		return explode( ',', $value );
	}

	/**
	 * The top bar; none when it is off or shows nothing.
	 */
	private static function top(): string {
		if ( true !== Settings::get( 'shop.header_top' ) ) {
			return '';
		}
		$html = '';
		foreach ( self::items( 'header_top_items', 'top' ) as $item ) {
			$html .= self::top_item( $item );
		}
		return '' === $html ? '' : '<div class="pergel-store-header__top"><div class="pergel-store-header__wrap">' . $html . '</div></div>';
	}

	/**
	 * One item of the top bar.
	 *
	 * @param string $item Item key.
	 */
	private static function top_item( string $item ): string {
		switch ( $item ) {
			case 'text':
				$text = (string) Settings::get( 'shop.header_text' );
				$text = '' !== $text ? $text : (string) get_bloginfo( 'description' );
				return '' === $text ? '' : '<p class="pergel-store-header__text">' . esc_html( $text ) . '</p>';

			case 'email':
				$email = (string) Settings::get( 'brand.email' );
				return '' === $email ? '' : self::link( 'mailto:' . $email, 'email', esc_html( $email ) );

			case 'phone':
				$phone = (string) Settings::get( 'brand.phone' );
				return '' === $phone ? '' : self::link( self::tel( $phone ), 'phone', esc_html( $phone ) );

			case 'address':
				$address = self::address();
				return '' === $address ? '' : '<span class="pergel-store-header__link">' . self::icon( 'address' ) . esc_html( $address ) . '</span>';

			case 'menu':
				$menu = (int) Settings::get( 'shop.header_top_menu' );
				if ( $menu <= 0 ) {
					return '';
				}
				return '<div class="pergel-store-header__menu">' . self::navigation(
					array(
						'ref'         => $menu,
						'overlayMenu' => 'never',
						'ariaLabel'   => _x( 'Top bar', 'header menu label', 'pergel-engine' ),
					)
				) . '</div>';
		}
		return '';
	}

	/**
	 * The row with the logo, the search and the visitor's links. Without
	 * the menu bar the menu follows the logo.
	 */
	private static function row(): string {
		$items = self::items( 'header_items', 'row' );
		if ( true !== Settings::get( 'shop.header_bar' ) ) {
			$at = array_search( 'logo', $items, true );
			array_splice( $items, false === $at ? 0 : $at + 1, 0, array( 'menu' ) );
		}
		$html = '';
		foreach ( $items as $item ) {
			$html .= self::row_item( $item );
		}
		if ( '' === $html ) {
			return '';
		}
		// Without a search to fill the middle, the logo keeps to the left.
		$spread = in_array( 'search', $items, true ) ? '' : ' is-spread';
		return '<div class="pergel-store-header__main' . $spread . '"><div class="pergel-store-header__wrap">' . $html . '</div></div>';
	}

	/**
	 * One item of the row.
	 *
	 * @param string $item Item key.
	 */
	private static function row_item( string $item ): string {
		switch ( $item ) {
			case 'logo':
				$logo = has_custom_logo() ? '<!-- wp:site-logo /-->' : '<!-- wp:site-title {"level":0} /-->';
				return '<div class="pergel-store-header__logo">' . do_blocks( $logo ) . '</div>';

			case 'search':
				return self::search();

			case 'contact':
				$phone = (string) Settings::get( 'brand.phone' );
				if ( '' === $phone ) {
					return '';
				}
				$label = (string) Settings::get( 'shop.header_call' );
				return self::item( self::tel( $phone ), 'phone', '' !== $label ? $label : __( 'Call us', 'pergel-engine' ), $phone, 'is-contact' );

			case 'account':
				$url = (string) wc_get_page_permalink( 'myaccount' );
				if ( '' === $url ) {
					return '';
				}
				$user = wp_get_current_user();
				return $user->exists()
					? self::item( $url, 'account', $user->display_name, __( 'My account', 'pergel-engine' ), 'is-account' )
					: self::item( $url, 'account', __( 'Log in or sign up', 'pergel-engine' ), __( 'Account', 'pergel-engine' ), 'is-account' );

			case 'lists':
				return self::registered( Lists::LINKS ) && array() !== Lists::linked() ? do_blocks( '<!-- wp:' . Lists::LINKS . ' /-->' ) : '';

			case 'cart':
				if ( ! self::registered( MiniCart::BLOCK ) || ! MiniCart::fits() ) {
					return '';
				}
				return '<div class="pergel-store-header__cart">' . do_blocks( get_comment_delimited_block_content( MiniCart::BLOCK, array( 'hasHiddenPrice' => false ), '' ) ) . '</div>';

			case 'button':
				// The enquiry page of the product pages.
				$url = (string) Settings::get( 'woo.contact_url' );
				if ( '' === $url ) {
					return '';
				}
				$text = (string) Settings::get( 'shop.header_button' );
				return sprintf(
					'<a class="wp-element-button pergel-store-header__button" href="%1$s">%2$s</a>',
					esc_url( $url ),
					esc_html( '' !== $text ? $text : __( 'Contact us', 'pergel-engine' ) )
				);

			case 'menu':
				return '<div class="pergel-store-header__menu">' . self::navigation( self::menu() ) . '</div>';
		}
		return '';
	}

	/**
	 * The bar with the menu and a link on the right; none when it is off.
	 */
	private static function bar(): string {
		if ( true !== Settings::get( 'shop.header_bar' ) ) {
			return '';
		}
		$text = (string) Settings::get( 'shop.header_bar_text' );
		$url  = (string) Settings::get( 'shop.header_bar_url' );
		$link = '' !== $text && '' !== $url ? '<a class="pergel-store-header__bar-link" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>' : '';
		return '<div class="pergel-store-header__bar"><div class="pergel-store-header__wrap"><div class="pergel-store-header__menu">'
			. self::navigation( self::menu() ) . '</div>' . $link . '</div></div>';
	}

	/**
	 * Attributes of the main menu; without a chosen menu the Navigation
	 * block shows the site's menu, as in the theme's headers.
	 *
	 * @return array<string, mixed>
	 */
	private static function menu(): array {
		$attrs = array(
			'overlayMenu'            => 'mobile',
			'overlayBackgroundColor' => 'base',
			'overlayTextColor'       => 'contrast',
			'ariaLabel'              => _x( 'Primary', 'header menu label', 'pergel-engine' ),
		);
		$menu  = (int) Settings::get( 'shop.header_menu' );
		if ( $menu > 0 ) {
			$attrs['ref'] = $menu;
		}
		return $attrs;
	}

	/**
	 * A Navigation block.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 */
	private static function navigation( array $attrs ): string {
		return do_blocks( get_comment_delimited_block_content( 'core/navigation', $attrs, '' ) );
	}

	/**
	 * The product search, with the top-level categories in front of the
	 * field when the setting is on. The form sends product_cat, which the
	 * product search honours as a taxonomy query.
	 */
	private static function search(): string {
		$html = do_blocks(
			get_comment_delimited_block_content(
				'core/search',
				array(
					'label'          => _x( 'Search', 'search form label', 'pergel-engine' ),
					'showLabel'      => false,
					'placeholder'    => __( 'Search products…', 'pergel-engine' ),
					'buttonText'     => _x( 'Search', 'search button text', 'pergel-engine' ),
					'buttonPosition' => 'button-outside',
					'buttonUseIcon'  => true,
					'query'          => array( 'post_type' => 'product' ),
					'className'      => 'pergel-store-header__search',
				),
				''
			)
		);
		if ( true !== Settings::get( 'shop.header_categories' ) ) {
			return $html;
		}
		$select = self::categories();
		if ( '' === $select ) {
			return $html;
		}
		return (string) preg_replace( '/<div[^>]*\bwp-block-search__inside-wrapper\b[^>]*>/', '$0' . str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $select ), $html, 1 );
	}

	/**
	 * The category list of the search: the top-level product categories
	 * with products, without the default one.
	 */
	private static function categories(): string {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => true,
				'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
			)
		);
		if ( ! is_array( $terms ) || array() === $terms ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only, to show the searched category.
		$current = is_search() && isset( $_GET['product_cat'] ) ? sanitize_title( wp_unslash( (string) $_GET['product_cat'] ) ) : '';
		$options = '<option value="">' . esc_html__( 'All categories', 'pergel-engine' ) . '</option>';
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$options .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $term->slug ), selected( $current, $term->slug, false ), esc_html( $term->name ) );
			}
		}
		return '<select class="pergel-store-header__categories" name="product_cat" aria-label="' . esc_attr__( 'Category to search', 'pergel-engine' ) . '">' . $options . '</select>';
	}

	/**
	 * The brand address on one line: place, region and country.
	 */
	private static function address(): string {
		$parts   = array( (string) Settings::get( 'brand.locality' ), (string) Settings::get( 'brand.region' ) );
		$country = (string) Settings::get( 'brand.country' );
		if ( '' !== $country ) {
			$names   = WC()->countries instanceof \WC_Countries ? WC()->countries->get_countries() : array();
			$parts[] = (string) ( $names[ $country ] ?? $country );
		}
		return implode( ', ', array_filter( $parts, static fn ( string $part ): bool => '' !== $part ) );
	}

	/**
	 * A top bar link with an icon.
	 *
	 * @param string $url  Address.
	 * @param string $icon Icon key.
	 * @param string $html Escaped text.
	 */
	private static function link( string $url, string $icon, string $html ): string {
		return '<a class="pergel-store-header__link" href="' . esc_url( $url, array( 'mailto', 'tel', 'http', 'https' ) ) . '">' . self::icon( $icon ) . $html . '</a>';
	}

	/**
	 * A row link: an icon in a circle, a small line and a strong line.
	 *
	 * @param string $url   Address.
	 * @param string $icon  Icon key.
	 * @param string $small Small line.
	 * @param string $big   Strong line.
	 * @param string $kind  Extra class.
	 */
	private static function item( string $url, string $icon, string $small, string $big, string $kind ): string {
		return sprintf(
			'<a class="pergel-store-header__item %1$s" href="%2$s">%3$s<span class="pergel-store-header__label"><small>%4$s</small> <strong>%5$s</strong></span></a>',
			esc_attr( $kind ),
			esc_url( $url, array( 'tel', 'http', 'https' ) ),
			self::icon( $icon ),
			esc_html( $small ),
			esc_html( $big )
		);
	}

	/**
	 * Whether a block type is registered.
	 *
	 * @param string $name Block name.
	 */
	private static function registered( string $name ): bool {
		return \WP_Block_Type_Registry::get_instance()->is_registered( $name );
	}

	/**
	 * A tel: address: the digits and a leading plus.
	 *
	 * @param string $phone Phone number as written.
	 */
	private static function tel( string $phone ): string {
		return 'tel:' . preg_replace( '/[^0-9+]/', '', $phone );
	}

	/**
	 * An icon.
	 *
	 * @param string $name Icon key.
	 */
	private static function icon( string $name ): string {
		return '<svg class="pergel-store-header__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . self::ICONS[ $name ] . '</svg>';
	}
}
