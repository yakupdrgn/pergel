<?php
/**
 * Live search suggestions.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Search;

use Pergel\Core\Plugin;
use Pergel\Core\Visitor;
use Pergel\Settings\Settings;
use Pergel\Woo\Modes;

/**
 * GET /pergel/v1/search?q=…&type=product: the first results with a small
 * image and, for products sold to everyone, the price; the matching
 * categories; the number of results and the address of the search page.
 *
 * The answer is the same for every visitor (the view script sends no
 * cookies, so a "prices after login" product shows no price), which lets
 * a page cache or CDN keep it for five minutes. The query result itself is
 * cached until the index changes. With a persistent object cache, each
 * connection may ask 120 times a minute.
 *
 * @phpstan-type Item array{id: int, title: string, url: string, type: string, image: array{src: string, width: int, height: int}|null, price: string}
 * @phpstan-type Term array{id: int, name: string, url: string, taxonomy: string, count: int}
 * @phpstan-type Answer array{query: string, total: int, items: list<Item>, terms: list<Term>, more: string}
 */
final class Suggest {

	public const NAMESPACE = 'pergel/v1';
	public const ROUTE     = 'search';

	/**
	 * Longest query accepted, in characters.
	 */
	public const MAX_QUERY = 100;

	/**
	 * Shortest query answered, in characters; the view script asks from
	 * two on as well.
	 */
	public const MIN_QUERY = 2;

	/**
	 * Most categories suggested.
	 */
	public const MAX_TERMS = 4;

	/**
	 * Requests per connection per minute, with a persistent object cache.
	 */
	public const LIMIT = 120;

	/**
	 * Price markup allowed in an answer.
	 */
	private const PRICE_TAGS = array(
		'span' => array(
			'class'       => true,
			'aria-hidden' => true,
		),
		'del'  => array( 'aria-hidden' => true ),
		'ins'  => array( 'aria-hidden' => true ),
		'bdi'  => array(),
	);

	/**
	 * Registers the endpoint. Hooked on rest_api_init.
	 */
	public static function route(): void {
		register_rest_route(
			self::NAMESPACE,
			'/' . self::ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'rest' ),
				// Public: it shows what the search page shows. Results are capped, the rate is limited.
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'    => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => self::MAX_QUERY,
					),
					'type' => array(
						'type'    => 'string',
						'enum'    => array( '', 'product' ),
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * REST callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function rest( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! self::within_limit() ) {
			return new \WP_REST_Response( array( 'code' => 'limit' ), 429 );
		}
		$query    = $request->get_param( 'q' );
		$type     = $request->get_param( 'type' );
		$response = new \WP_REST_Response( self::answer( is_string( $query ) ? $query : '', 'product' === $type ? 'product' : '' ) );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}

	/**
	 * The suggestions for a query.
	 *
	 * @param string $query Visitor input.
	 * @param string $type  'product' for the product search, '' for all.
	 * @return array<string, mixed>
	 * @phpstan-return Answer
	 */
	public static function answer( string $query, string $type = '' ): array {
		$query  = trim( mb_substr( $query, 0, self::MAX_QUERY, 'UTF-8' ) );
		$terms  = Text::terms( $query );
		$types  = Query::types( '' === $type ? 'any' : $type );
		$answer = array(
			'query' => $query,
			'total' => 0,
			'items' => array(),
			'terms' => array(),
			'more'  => add_query_arg(
				array_filter(
					array(
						's'         => rawurlencode( $query ),
						'post_type' => $type,
					)
				),
				home_url( '/' )
			),
		);
		if ( mb_strlen( $query, 'UTF-8' ) < self::MIN_QUERY || array() === $terms || array() === $types || ! Index::ready() ) {
			return $answer;
		}

		$ids             = array_keys( Query::match( Index::KIND_POST, $terms, $types ) );
		$answer['total'] = count( $ids );
		$limit           = max( 1, (int) Settings::get( 'search.live_limit' ) );
		$shown           = array_slice( $ids, 0, $limit );
		_prime_post_caches( $shown );
		_prime_post_caches( array_filter( array_map( 'get_post_thumbnail_id', $shown ) ) );
		foreach ( $shown as $id ) {
			$item = self::item( $id );
			if ( null !== $item ) {
				$answer['items'][] = $item;
			}
		}

		$taxonomies = 'product' === $type ? array_intersect( Index::taxonomies(), array( 'product_cat' ) ) : Index::taxonomies();
		foreach ( array_keys( Query::match( Index::KIND_TERM, $terms, array(), 20 ) ) as $id ) {
			$term = get_term( $id );
			if ( ! $term instanceof \WP_Term || $term->count < 1 || ! in_array( $term->taxonomy, $taxonomies, true ) ) {
				continue;
			}
			$url = get_term_link( $term );
			if ( ! is_string( $url ) ) {
				continue;
			}
			$answer['terms'][] = array(
				'id'       => $term->term_id,
				'name'     => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'url'      => $url,
				'taxonomy' => $term->taxonomy,
				'count'    => $term->count,
			);
			if ( count( $answer['terms'] ) >= self::MAX_TERMS ) {
				break;
			}
		}
		return $answer;
	}

	/**
	 * One result.
	 *
	 * @param int $id Post id.
	 * @return array<string, mixed>|null
	 * @phpstan-return Item|null
	 */
	private static function item( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		$type  = get_post_type_object( $post->post_type );
		$image = null;
		$thumb = (int) get_post_thumbnail_id( $post );
		if ( $thumb > 0 ) {
			$src = wp_get_attachment_image_src( $thumb, 'thumbnail' );
			if ( is_array( $src ) ) {
				$image = array(
					'src'    => (string) $src[0],
					'width'  => (int) $src[1],
					'height' => (int) $src[2],
				);
			}
		}
		return array(
			'id'    => $id,
			'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
			'url'   => (string) get_permalink( $post ),
			'type'  => $type instanceof \WP_Post_Type ? (string) $type->labels->singular_name : '',
			'image' => $image,
			'price' => 'product' === $post->post_type ? self::price( $post ) : '',
		);
	}

	/**
	 * Price markup of a product sold to a visitor who is not logged in;
	 * '' where the sales mode hides it.
	 *
	 * @param \WP_Post $post Product post.
	 */
	private static function price( \WP_Post $post ): string {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$product = wc_get_product( $post );
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}
		// The sales modes are not enforced in this REST request; apply them as for a guest.
		if ( in_array( 'woo', Plugin::loaded(), true ) && Modes::SALE !== Modes::of( $product ) ) {
			return '';
		}
		return trim( wp_kses( $product->get_price_html(), self::PRICE_TAGS ) );
	}

	/**
	 * Counts this request against the connection's limit. Needs a
	 * persistent object cache; without one, counting would write to the
	 * database on every keystroke, so there is no limit.
	 */
	private static function within_limit(): bool {
		return ! wp_using_ext_object_cache() || Visitor::hit( 'search:' . Visitor::bucket( Visitor::ip() ), self::LIMIT, MINUTE_IN_SECONDS );
	}
}
