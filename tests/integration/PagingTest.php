<?php
/**
 * Listing pages past the last one go to the last page.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Seo\Paging;
use PHPUnit\Framework\TestCase;

/**
 * Creates a category with three posts, runs its main query for a page
 * number and reads where Paging would send it. Everything created is
 * deleted again.
 */
final class PagingTest extends TestCase {

	/**
	 * Posts created by the test.
	 *
	 * @var list<int>
	 */
	private array $posts = array();

	/**
	 * Categories created by the test.
	 *
	 * @var list<int>
	 */
	private array $terms = array();

	/**
	 * The main query before go_to() replaced it.
	 */
	private ?\WP_Query $query = null;

	/**
	 * The request address before the test.
	 */
	private string $uri = '/';

	protected function setUp(): void {
		$this->uri = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
	}

	protected function tearDown(): void {
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->terms as $id ) {
			wp_delete_term( $id, 'category' );
		}
		remove_all_filters( Paging::FILTER );
		$_SERVER['REQUEST_URI'] = $this->uri;
		if ( null !== $this->query ) {
			$GLOBALS['wp_the_query'] = $this->query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the query go_to() replaced.
		}
		wp_reset_query();
	}

	/**
	 * A category with the given number of published posts.
	 *
	 * @param int $posts Number of posts.
	 */
	private function category( int $posts ): \WP_Term {
		$made = wp_insert_term( 'Pergel paging ' . wp_generate_password( 6, false ), 'category' );
		$this->assertIsArray( $made );
		$this->terms[] = (int) $made['term_id'];
		for ( $i = 0; $i < $posts; $i++ ) {
			$id = wp_insert_post(
				array(
					'post_title'    => "Pergel paging $i",
					'post_status'   => 'publish',
					'post_author'   => 1,
					'post_category' => array( (int) $made['term_id'] ),
				)
			);
			$this->assertIsInt( $id );
			$this->posts[] = $id;
		}
		$term = get_term( (int) $made['term_id'], 'category' );
		$this->assertInstanceOf( \WP_Term::class, $term );
		return $term;
	}

	/**
	 * Runs the category's main query for a page, 404 when it shows no
	 * posts (as WP::handle_404 does for a page past the last one).
	 *
	 * @param \WP_Term $term  Category.
	 * @param int      $paged Page number.
	 * @param int      $size  Posts per page.
	 */
	private function go_to( \WP_Term $term, int $paged, int $size = 2 ): void {
		$base                   = (string) wp_parse_url( (string) get_term_link( $term ), PHP_URL_PATH );
		$_SERVER['REQUEST_URI'] = $GLOBALS['wp_rewrite']->using_permalinks()
			? user_trailingslashit( rtrim( $base, '/' ) . "/page/$paged" )
			: (string) add_query_arg(
				array(
					'cat'   => $term->term_id,
					'paged' => $paged,
				),
				'/'
			);
		$this->query           ??= $GLOBALS['wp_the_query'];
		$query                   = new \WP_Query(
			array(
				'cat'            => $term->term_id,
				'paged'          => $paged,
				'posts_per_page' => $size,
			)
		);
		if ( ! $query->posts ) {
			$query->set_404();
		}
		$GLOBALS['wp_the_query'] = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test page view; put back in tearDown.
		$GLOBALS['wp_query']     = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
	}

	/**
	 * The address of a page of the category, as Paging should build it.
	 *
	 * @param \WP_Term $term  Category.
	 * @param int      $paged Page number.
	 */
	private function page( \WP_Term $term, int $paged ): string {
		if ( ! $GLOBALS['wp_rewrite']->using_permalinks() ) {
			$url = home_url( '/?cat=' . $term->term_id );
			return $paged > 1 ? add_query_arg( 'paged', $paged, $url ) : $url;
		}
		$url = (string) get_term_link( $term );
		return $paged > 1 ? user_trailingslashit( trailingslashit( $url ) . "page/$paged" ) : $url;
	}

	public function test_page_past_the_last_goes_to_the_last_page(): void {
		$term = $this->category( 3 );
		$this->go_to( $term, 5 );
		$this->assertTrue( is_404() );
		$this->assertSame( $this->page( $term, 2 ), Paging::target() );
	}

	public function test_listing_on_one_page_goes_to_its_first_page(): void {
		$term = $this->category( 3 );
		$this->go_to( $term, 2, 3 );
		$this->assertSame( $this->page( $term, 1 ), Paging::target() );
	}

	public function test_page_that_exists_is_left_alone(): void {
		$term = $this->category( 3 );
		$this->go_to( $term, 2 );
		$this->assertFalse( is_404() );
		$this->assertSame( '', Paging::target() );
	}

	public function test_listing_without_posts_keeps_the_404(): void {
		$term = $this->category( 0 );
		$this->go_to( $term, 2 );
		$this->assertTrue( is_404() );
		$this->assertSame( '', Paging::target() );
	}

	public function test_filter_can_keep_the_404(): void {
		$term = $this->category( 3 );
		$this->go_to( $term, 4 );
		add_filter(
			Paging::FILTER,
			function ( string $to, int $paged, int $last ): string {
				$this->assertSame( array( 4, 2 ), array( $paged, $last ) );
				return '';
			},
			10,
			3
		);
		$this->assertSame( '', Paging::target() );
	}
}
