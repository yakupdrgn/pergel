<?php
/**
 * Fills a development site with fake products and posts for the scale test.
 *
 * Usage: wp eval-file tools/fixtures/scale.php products=5000 posts=10000
 *        wp eval-file tools/fixtures/scale.php remove
 *
 * Products are simple products in twenty categories (pergel-scale-cat-1 …
 * 20), with prices, SKUs, sales and out-of-stock items spread by their
 * number, so every run gives the same catalog. Posts go in one category
 * (pergel-scale). Numbers count the whole set: running it again with a
 * bigger number only adds the missing items. Everything it makes carries
 * the _pergel_scale meta, which "remove" deletes by. Development and CI only;
 * it needs WooCommerce for products.
 *
 * Runs through eval(), so it cannot declare strict_types.
 *
 * @package Pergel
 */

namespace Pergel\Dev;

defined( 'ABSPATH' ) || exit;

const SCALE_META       = '_pergel_scale';
const SCALE_CATEGORIES = 20;

/**
 * Number of items of a type the generator has made.
 *
 * @param string $type Post type.
 */
function scale_count( string $type ): int {
	global $wpdb;
	return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A count on a development site.
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = %s",
			SCALE_META,
			$type
		)
	);
}

/**
 * Term id of a category, made when missing.
 *
 * @param string $taxonomy Taxonomy.
 * @param string $slug     Term slug.
 * @param string $name     Term name.
 */
function scale_term( string $taxonomy, string $slug, string $name ): int {
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( $term instanceof \WP_Term ) {
		return $term->term_id;
	}
	$made = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
	if ( is_wp_error( $made ) ) {
		\WP_CLI::error( $made );
	}
	update_term_meta( (int) $made['term_id'], SCALE_META, 1 );
	return (int) $made['term_id'];
}

/**
 * Runs $make for the numbers from $from to $to, reporting progress and
 * keeping memory flat.
 *
 * @param string   $what Label for the progress lines.
 * @param int      $from First number.
 * @param int      $to   Last number.
 * @param callable $make Makes item $n.
 */
function scale_loop( string $what, int $from, int $to, callable $make ): void {
	$start = microtime( true );
	for ( $n = $from; $n <= $to; $n++ ) {
		$make( $n );
		if ( 0 === $n % 500 || $n === $to ) {
			// The runtime object cache keeps every post it has seen.
			wp_cache_flush_runtime();
			\WP_CLI::log( sprintf( '%s %d/%d, %.0f s', $what, $n, $to, microtime( true ) - $start ) );
		}
	}
}

/**
 * Adds products until there are $total.
 *
 * @param int $total Products wanted.
 */
function scale_products( int $total ): void {
	if ( ! class_exists( 'WooCommerce', false ) ) {
		\WP_CLI::error( 'WooCommerce is not active.' );
	}
	$have = scale_count( 'product' );
	if ( $have >= $total ) {
		\WP_CLI::log( "Products: $have already." );
		return;
	}
	$categories = array();
	for ( $c = 1; $c <= SCALE_CATEGORIES; $c++ ) {
		$categories[] = scale_term( 'product_cat', "pergel-scale-cat-$c", "Scale category $c" );
	}
	$words = array( 'Linen', 'Oak', 'Copper', 'Wool', 'Stone', 'Cedar', 'Clay', 'Brass', 'Cotton', 'Glass', 'Walnut', 'Felt' );
	$items = array( 'Lamp', 'Mug', 'Chair', 'Scarf', 'Bowl', 'Tray', 'Vase', 'Basket', 'Shelf', 'Cushion', 'Notebook', 'Bag' );
	scale_loop(
		'Products',
		$have + 1,
		$total,
		static function ( int $n ) use ( $categories, $words, $items ): void {
			$product = new \WC_Product_Simple();
			$product->set_name( sprintf( '%s %s %d', $words[ $n % 12 ], $items[ intdiv( $n, 12 ) % 12 ], $n ) );
			$product->set_slug( "pergel-scale-$n" );
			$product->set_sku( "PSCALE-$n" );
			$product->set_status( 'publish' );
			$product->set_short_description( 'A product made by the scale test generator.' );
			$price = (string) ( 50 + ( $n * 37 ) % 1950 );
			$product->set_regular_price( $price );
			if ( 0 === $n % 7 ) {
				$product->set_sale_price( (string) round( (int) $price * 0.8 ) );
			}
			if ( 0 === $n % 11 ) {
				$product->set_stock_status( 'outofstock' );
			}
			$product->set_category_ids( array( $categories[ $n % SCALE_CATEGORIES ] ) );
			$product->add_meta_data( SCALE_META, '1', true );
			$product->save();
		}
	);
}

/**
 * Adds posts until there are $total.
 *
 * @param int $total Posts wanted.
 */
function scale_posts( int $total ): void {
	$have = scale_count( 'post' );
	if ( $have >= $total ) {
		\WP_CLI::log( "Posts: $have already." );
		return;
	}
	$category  = scale_term( 'category', 'pergel-scale', 'Scale' );
	$paragraph = '<!-- wp:paragraph --><p>A post made by the scale test generator. It is long enough to stand in for an excerpt and carries the number of the post, %d, so search has something to find.</p><!-- /wp:paragraph -->';
	scale_loop(
		'Posts',
		$have + 1,
		$total,
		static function ( int $n ) use ( $category, $paragraph ): void {
			$id = wp_insert_post(
				array(
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_title'    => "Scale post $n",
					'post_name'     => "pergel-scale-post-$n",
					'post_content'  => sprintf( $paragraph, $n ),
					// One post an hour back from a fixed day, so archives span years.
					'post_date'     => gmdate( 'Y-m-d H:i:s', 1767225600 - $n * 3600 ),
					'post_category' => array( $category ),
					'meta_input'    => array( SCALE_META => 1 ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				\WP_CLI::error( $id );
			}
		}
	);
}

/**
 * Deletes everything the generator made.
 */
function scale_remove(): void {
	global $wpdb;
	$ids = array_map(
		'intval',
		$wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", SCALE_META ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Ids on a development site.
	);
	scale_loop(
		'Removed',
		1,
		max( 1, count( $ids ) ),
		static function ( int $n ) use ( $ids ): void {
			if ( isset( $ids[ $n - 1 ] ) ) {
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( $ids[ $n - 1 ] ) : null;
				if ( $product ) {
					$product->delete( true );
				} else {
					wp_delete_post( $ids[ $n - 1 ], true );
				}
			}
		}
	);
	foreach ( array( 'product_cat', 'category' ) as $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'meta_key'   => SCALE_META, // phpcs:ignore WordPress.DB.SlowDBQuery -- A handful of terms.
				'fields'     => 'ids',
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			wp_delete_term( (int) $term, $taxonomy );
		}
	}
}

/**
 * Reads products=N posts=N or "remove" from the eval-file arguments.
 *
 * @param array<int, string> $argv Arguments after the file name.
 */
function scale( array $argv ): void {
	if ( in_array( 'remove', $argv, true ) ) {
		scale_remove();
		\WP_CLI::success( 'Scale content removed.' );
		return;
	}
	$want = array(
		'products' => 0,
		'posts'    => 0,
	);
	foreach ( $argv as $arg ) {
		[ $key, $value ] = array_pad( explode( '=', $arg, 2 ), 2, '' );
		if ( ! isset( $want[ $key ] ) || ! ctype_digit( $value ) ) {
			\WP_CLI::error( "Unknown argument: $arg (expected products=N, posts=N or remove)" );
		}
		$want[ $key ] = (int) $value;
	}
	wp_defer_term_counting( true );
	if ( $want['products'] > 0 ) {
		scale_products( $want['products'] );
	}
	if ( $want['posts'] > 0 ) {
		scale_posts( $want['posts'] );
	}
	wp_defer_term_counting( false );
	\WP_CLI::success( sprintf( 'Scale content: %d products, %d posts.', scale_count( 'product' ), scale_count( 'post' ) ) );
}

scale( $args ?? array() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- $args holds the eval-file arguments.
