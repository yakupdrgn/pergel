<?php
/**
 * Wishlist and compare.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Two product lists a visitor builds while browsing: a wishlist and up to
 * four products to compare. Each is turned on in the settings; both are
 * off by default.
 *
 * A page view never writes: a visitor's lists live in the browser
 * (localStorage), so cached pages stay the same for everyone. A logged-in
 * customer's wishlist is kept in their account (user meta) and changes only
 * when they press a button; a list saved in the browser before logging in
 * moves into the account then. The list pages draw their products through
 * GET pergel/v1/shop-list, rendered here, so prices follow the sales modes.
 *
 * The buttons (pergel/product-actions) are hooked after the product button
 * of product cards and after the add-to-cart form of the product page; the
 * header links (pergel/shop-links) after the menu of Pergel's headers, when
 * the list has a page. All three blocks share one small script module.
 */
final class Lists {

	public const KINDS = array( 'wishlist', 'compare' );

	/**
	 * Most products a list holds.
	 */
	public const LIMITS = array(
		'wishlist' => 100,
		'compare'  => 4,
	);

	public const SCRIPT = 'pergel/shop-lists';

	/**
	 * User meta with a customer's wishlist (product IDs, oldest first).
	 */
	public const META = '_pergel_wishlist';

	public const ACTIONS = 'pergel/product-actions';

	public const LINKS = 'pergel/shop-links';

	/**
	 * Adds the hooks. Woo\Module calls it when at least one list is on.
	 */
	public static function register(): void {
		if ( array() === self::enabled() ) {
			return;
		}
		add_action( 'init', array( self::class, 'register_assets' ) );
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_filter( 'hooked_block_types', array( self::class, 'hook' ), 21, 4 );
		add_filter( 'hooked_block_' . self::ACTIONS, array( self::class, 'hooked_actions' ), 10, 4 );
		add_filter( 'script_module_data_' . self::SCRIPT, array( self::class, 'data' ) );
	}

	/**
	 * The lists that are on.
	 *
	 * @return list<string>
	 */
	public static function enabled(): array {
		return array_values( array_filter( self::KINDS, static fn ( string $kind ): bool => true === Settings::get( "woo.{$kind}" ) ) );
	}

	/**
	 * Address of a list's page, or ''.
	 *
	 * @param string $kind wishlist or compare.
	 */
	public static function page( string $kind ): string {
		$url = Settings::get( "woo.{$kind}_url" );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Registers the script module. Hooked on init.
	 */
	public static function register_assets(): void {
		wp_register_script_module( self::SCRIPT, plugins_url( 'assets/shop-lists.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );
	}

	/**
	 * Loads the script module on this page. Each block brings its own CSS.
	 */
	public static function enqueue(): void {
		wp_enqueue_script_module( self::SCRIPT );
	}

	/**
	 * Filter for hooked_block_types: the buttons after the product button
	 * and the add-to-cart form, the header links after the menu of a Pergel
	 * header. Never twice where the block is placed by hand.
	 *
	 * @param mixed $hooked   Block types hooked at this position.
	 * @param mixed $position Relative position.
	 * @param mixed $anchor   Anchor block type.
	 * @param mixed $context  Pattern data, template or post the blocks are in.
	 * @return mixed
	 */
	public static function hook( mixed $hooked, mixed $position, mixed $anchor, mixed $context ): mixed {
		if ( ! is_array( $hooked ) || 'after' !== $position ) {
			return $hooked;
		}
		$content = self::content( $context );
		// The add-to-cart form holds a product button of its own; the buttons go after the form.
		$in_form = $context instanceof \WP_Block_Template && str_ends_with( $context->slug, 'add-to-cart-with-options' );
		if ( ( 'woocommerce/add-to-cart-with-options' === $anchor || ( 'woocommerce/product-button' === $anchor && ! $in_form ) ) && ! str_contains( $content, 'wp:' . self::ACTIONS ) ) {
			$hooked[] = self::ACTIONS;
		}
		if ( 'core/navigation' === $anchor && self::is_header_pattern( $context ) && ! str_contains( $content, 'wp:' . self::LINKS ) && array() !== self::linked() ) {
			// Before the mini cart, which MiniCart hooks at the same place.
			$cart   = array_search( MiniCart::BLOCK, $hooked, true );
			$hooked = ! is_int( $cart ) ? array_merge( $hooked, array( self::LINKS ) ) : array_merge( array_slice( $hooked, 0, $cart ), array( self::LINKS ), array_slice( $hooked, $cart ) );
		}
		return $hooked;
	}

	/**
	 * Filter for hooked_block_pergel/product-actions: labelled buttons on
	 * the product page, icons on product cards.
	 *
	 * @param mixed $block    Parsed hooked block.
	 * @param mixed $type     Hooked block type.
	 * @param mixed $position Relative position.
	 * @param mixed $anchor   Parsed anchor block.
	 * @return mixed
	 */
	public static function hooked_actions( mixed $block, mixed $type, mixed $position, mixed $anchor ): mixed {
		if ( is_array( $block ) && is_array( $anchor ) && 'woocommerce/add-to-cart-with-options' === ( $anchor['blockName'] ?? null ) ) {
			$block['attrs']           = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$block['attrs']['labels'] = true;
		}
		return $block;
	}

	/**
	 * The lists that are on and have a page.
	 *
	 * @return list<string>
	 */
	public static function linked(): array {
		return array_values( array_filter( self::enabled(), static fn ( string $kind ): bool => '' !== self::page( $kind ) ) );
	}

	/**
	 * Filter for script_module_data_pergel/shop-lists: what the script
	 * needs. A visitor's data is the same for everyone, so cached pages
	 * stay correct; only a logged-in customer gets their list and a nonce.
	 *
	 * @param mixed $data Module data.
	 * @return array<string, mixed>
	 */
	public static function data( mixed $data ): array {
		$data = is_array( $data ) ? $data : array();
		$user = get_current_user_id();
		return array_merge(
			$data,
			array(
				'list'    => esc_url_raw( rest_url( 'pergel/v1/shop-list' ) ),
				'save'    => esc_url_raw( rest_url( 'pergel/v1/wishlist' ) ),
				'kinds'   => self::enabled(),
				'limits'  => self::LIMITS,
				'user'    => $user > 0,
				'nonce'   => $user > 0 ? wp_create_nonce( 'wp_rest' ) : '',
				'saved'   => $user > 0 ? self::saved( $user ) : array(),
				'texts'   => array(
					'added'   => __( 'Added to the wishlist.', 'pergel-engine' ),
					'removed' => __( 'Removed from the wishlist.', 'pergel-engine' ),
					/* translators: %d: most products that can be compared. */
					'full'    => sprintf( __( 'You can compare up to %d products. Remove one first.', 'pergel-engine' ), self::LIMITS['compare'] ),
					'compare' => __( 'Added to compare.', 'pergel-engine' ),
					'dropped' => __( 'Removed from compare.', 'pergel-engine' ),
					'failed'  => __( 'The list could not be updated. Try again.', 'pergel-engine' ),
				),
				'empties' => array_combine( self::KINDS, array_map( array( self::class, 'empty_text' ), self::KINDS ) ),
			)
		);
	}

	/**
	 * What an empty list says.
	 *
	 * @param string $kind wishlist or compare.
	 */
	public static function empty_text( string $kind ): string {
		return 'compare' === $kind
			? __( 'No products to compare yet. Press Compare on a product to add it here.', 'pergel-engine' )
			: __( 'Your wishlist is empty. Press the heart on a product to save it here.', 'pergel-engine' );
	}

	/**
	 * Adds the saved wishlist to the personal data export.
	 *
	 * @param mixed $exporters Registered exporters.
	 * @return mixed
	 */
	public static function exporters( mixed $exporters ): mixed {
		if ( is_array( $exporters ) ) {
			$exporters['pergel-wishlist'] = array(
				'exporter_friendly_name' => __( 'Wishlist', 'pergel-engine' ),
				'callback'               => array( self::class, 'export' ),
			);
		}
		return $exporters;
	}

	/**
	 * Adds the saved wishlist to the personal data erasure.
	 *
	 * @param mixed $erasers Registered erasers.
	 * @return mixed
	 */
	public static function erasers( mixed $erasers ): mixed {
		if ( is_array( $erasers ) ) {
			$erasers['pergel-wishlist'] = array(
				'eraser_friendly_name' => __( 'Wishlist', 'pergel-engine' ),
				'callback'             => array( self::class, 'erase' ),
			);
		}
		return $erasers;
	}

	/**
	 * Exporter callback: the products in the account's saved wishlist.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Batch number; the list fits in one.
	 * @return array{data: list<array<string, mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', $email );
		$ids  = $user instanceof \WP_User ? self::saved( $user->ID ) : array();
		if ( array() === $ids ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$names = array();
		foreach ( $ids as $id ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			$names[] = $product instanceof \WC_Product ? $product->get_name() : '#' . $id;
		}
		return array(
			'data' => array(
				array(
					'group_id'    => 'pergel-wishlist',
					'group_label' => __( 'Wishlist', 'pergel-engine' ),
					'item_id'     => 'pergel-wishlist-' . $user->ID,
					'data'        => array(
						array(
							'name'  => __( 'Products', 'pergel-engine' ),
							'value' => implode( ', ', $names ),
						),
					),
				),
			),
			'done' => true,
		);
	}

	/**
	 * Eraser callback: deletes the account's saved wishlist.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Batch number; the list goes in one.
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', $email );
		return array(
			'items_removed'  => $user instanceof \WP_User && '' !== get_user_meta( $user->ID, self::META, true ) && delete_user_meta( $user->ID, self::META ),
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * A customer's saved wishlist.
	 *
	 * @param int $user User ID.
	 * @return list<int>
	 */
	public static function saved( int $user ): array {
		return self::ids( get_user_meta( $user, self::META, true ), self::LIMITS['wishlist'] );
	}

	/**
	 * Distinct positive IDs, at most $limit, keeping the newest.
	 *
	 * @param mixed $ids   IDs as an array or a comma-separated string.
	 * @param int   $limit Most IDs.
	 * @return list<int>
	 */
	public static function ids( mixed $ids, int $limit ): array {
		if ( is_string( $ids ) ) {
			$ids = explode( ',', $ids );
		}
		$clean = array();
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			$id = is_numeric( $id ) ? (int) $id : 0;
			if ( $id > 0 ) {
				$clean[ $id ] = $id;
			}
		}
		return array_slice( array_values( $clean ), -$limit );
	}

	/**
	 * Registers the REST routes. Hooked on rest_api_init.
	 */
	public static function routes(): void {
		register_rest_route(
			'pergel/v1',
			'/shop-list',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'show' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'kind' => array(
						'type'     => 'string',
						'enum'     => self::enabled(),
						'required' => true,
					),
					'ids'  => array(
						'type'    => 'string',
						'default' => '',
						'pattern' => '^[0-9,]*$',
					),
				),
			)
		);
		if ( in_array( 'wishlist', self::enabled(), true ) ) {
			register_rest_route(
				'pergel/v1',
				'/wishlist',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'change' ),
					'permission_callback' => 'is_user_logged_in',
					'args'                => array(
						'action' => array(
							'type'     => 'string',
							'enum'     => array( 'add', 'remove', 'merge' ),
							'required' => true,
						),
						'ids'    => array(
							'type'     => 'array',
							'items'    => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'maxItems' => self::LIMITS['wishlist'],
							'required' => true,
						),
					),
				)
			);
		}
	}

	/**
	 * GET pergel/v1/shop-list: a list's markup for the products given, in
	 * their order, without the ones a visitor cannot see.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 */
	public static function show( \WP_REST_Request $request ): \WP_REST_Response {
		$kind     = (string) $request->get_param( 'kind' );
		$products = self::products( self::ids( $request->get_param( 'ids' ), self::LIMITS[ $kind ] ?? 0 ) );
		return new \WP_REST_Response(
			array(
				'ids'  => array_map( static fn ( \WC_Product $product ): int => $product->get_id(), $products ),
				'html' => ListView::render( $kind, $products ),
			)
		);
	}

	/**
	 * POST pergel/v1/wishlist: adds, removes or merges products in the
	 * customer's wishlist. A merge adds the list the browser kept before
	 * logging in.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 */
	public static function change( \WP_REST_Request $request ): \WP_REST_Response {
		$user    = get_current_user_id();
		$saved   = self::saved( $user );
		$ids     = array_map( static fn ( \WC_Product $product ): int => $product->get_id(), self::products( self::ids( $request->get_param( 'ids' ), self::LIMITS['wishlist'] ) ) );
		$changed = 'remove' === $request->get_param( 'action' )
			? array_values( array_diff( $saved, self::ids( $request->get_param( 'ids' ), self::LIMITS['wishlist'] ) ) )
			: self::ids( array_merge( $saved, $ids ), self::LIMITS['wishlist'] );
		if ( $changed !== $saved ) {
			update_user_meta( $user, self::META, $changed );
		}
		return new \WP_REST_Response( array( 'ids' => $changed ) );
	}

	/**
	 * The products a visitor may see, in the order given.
	 *
	 * @param array<int, int> $ids Product IDs.
	 * @phpstan-param list<int> $ids
	 * @return list<\WC_Product>
	 */
	public static function products( array $ids ): array {
		$products = array();
		foreach ( $ids as $id ) {
			$product = 'product' === get_post_type( $id ) ? wc_get_product( $id ) : null;
			if ( $product instanceof \WC_Product && 'publish' === $product->get_status() && $product->is_visible() ) {
				$products[] = $product;
			}
		}
		return $products;
	}

	/**
	 * The product a block is about: the post in its context, or the current
	 * post.
	 *
	 * @param \WP_Block|null $block Block instance.
	 */
	public static function product( ?\WP_Block $block ): ?\WC_Product {
		$id      = $block instanceof \WP_Block && is_int( $block->context['postId'] ?? null ) ? $block->context['postId'] : (int) get_the_ID();
		$product = $id > 0 && 'product' === get_post_type( $id ) ? wc_get_product( $id ) : null;
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Markup of the hook context: a pattern's content, a template's or a
	 * post's.
	 *
	 * @param mixed $context Hook context.
	 */
	private static function content( mixed $context ): string {
		if ( is_array( $context ) ) {
			return (string) ( $context['content'] ?? '' );
		}
		if ( $context instanceof \WP_Block_Template ) {
			return (string) $context->content;
		}
		return $context instanceof \WP_Post ? $context->post_content : '';
	}

	/**
	 * Whether the context is one of Pergel's header patterns.
	 *
	 * @param mixed $context Hook context.
	 */
	private static function is_header_pattern( mixed $context ): bool {
		return is_array( $context )
			&& str_starts_with( (string) ( $context['name'] ?? '' ), 'pergel/' )
			&& in_array( 'header', (array) ( $context['categories'] ?? array() ), true );
	}
}
