<?php
/**
 * The search module: index upkeep, matching, the search page, suggestions
 * and the Search block.
 *
 * Builds the index of the site when it is not complete. Tests that need
 * WooCommerce are skipped without it.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Core\Plugin;
use Pergel\Search\Box;
use Pergel\Search\Index;
use Pergel\Search\Query;
use Pergel\Search\Suggest;
use Pergel\Search\Text;
use Pergel\Settings\Settings;
use Pergel\Woo\Modes;
use Pergel\Woo\Module as Woo;
use PHPUnit\Framework\TestCase;

/**
 * Posts and settings made by a test are removed after it.
 */
final class SearchTest extends TestCase {

	/**
	 * The option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved;

	/**
	 * Posts made by the test.
	 *
	 * @var list<int>
	 */
	private array $posts = array();

	/**
	 * Terms made by the test, with their taxonomies.
	 *
	 * @var array<int, string>
	 */
	private array $terms = array();

	public static function setUpBeforeClass(): void {
		if ( in_array( 'search', Plugin::loaded(), true ) && ! Index::ready() ) {
			self::build();
		}
	}

	/**
	 * Runs a full build now.
	 */
	private static function build(): void {
		if ( Index::SCHEMA !== Index::state()['db'] ) {
			Index::install();
		}
		Index::start();
		while ( ! Index::step() ) {
			// One batch at a time.
		}
		wp_clear_scheduled_hook( Index::BUILD_HOOK );
	}

	protected function setUp(): void {
		if ( ! in_array( 'search', Plugin::loaded(), true ) ) {
			$this->markTestSkipped( 'The search module is off on this site.' );
		}
		$this->saved = get_option( Settings::OPTION, false );
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->terms as $id => $taxonomy ) {
			wp_delete_term( $id, $taxonomy );
		}
		Index::flush();
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		if ( class_exists( Modes::class, false ) ) {
			Modes::flush();
		}
		wp_reset_query();
	}

	/**
	 * Makes a published post and writes it to the index.
	 *
	 * @param string $title   Title.
	 * @param string $content Content.
	 * @param string $type    Post type.
	 */
	private function post( string $title, string $content = '', string $type = 'post' ): int {
		$id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => $type,
			)
		);
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		Index::flush();
		return $id;
	}

	/**
	 * Ids matching a query, best first.
	 *
	 * @param string $query Query.
	 * @param string $type  Post type or 'any'.
	 * @return list<int>
	 */
	private function find( string $query, string $type = 'any' ): array {
		return array_keys( Query::match( Index::KIND_POST, Text::terms( $query ), Query::types( $type ) ) );
	}

	public function test_index_is_complete(): void {
		$this->assertTrue( Index::ready() );
		$this->assertContains( 'post', Index::post_types() );
		$this->assertContains( 'page', Index::post_types() );
		$this->assertNotContains( 'attachment', Index::post_types() );
	}

	public function test_turkish_letters_and_prefixes_match(): void {
		$id = $this->post( 'Işıklı Zümrüt Pergelsandık', 'Ahşap kapaklı.' );
		foreach ( array( 'ışıklı', 'ISIKLI', 'isik', 'zumrut pergelsan', 'Pergelsandık ahşap', 'AHSAP' ) as $query ) {
			$this->assertContains( $id, $this->find( $query ), $query );
		}
		$this->assertNotContains( $id, $this->find( 'sandik' ), 'Only the start of a word matches.' );
	}

	public function test_every_term_must_match(): void {
		$both = $this->post( 'Pergelkuzey Pergelgüney' );
		$one  = $this->post( 'Pergelkuzey Pergelbatı' );
		$this->assertSame( array( $both ), $this->find( 'pergelkuzey pergelguney' ) );
		$this->assertEqualsCanonicalizing( array( $both, $one ), $this->find( 'pergelkuzey' ) );
	}

	public function test_common_terms_give_the_same_results(): void {
		$both    = $this->post( 'Pergelnadir Pergelortak' );
		$content = $this->post( 'Pergelnadir', 'Pergelortak geçiyor.' );
		$this->post( 'Pergelortak bir' );
		$this->post( 'Pergelortak iki' );
		$full = array(
			$this->find( 'pergelortak pergelnadir' ),
			$this->find( 'pergelnadir pergelortak' ),
			$this->find( 'pergelortak pergelyok' ),
		);
		$this->assertSame( array( $both, $content ), $full[0] );

		// Two rows or fewer: pergelnadir stays rare, pergelortak (four rows) is common.
		$rows = static fn (): int => 2;
		add_filter( 'pergel/search/common_rows', $rows );
		wp_cache_set_last_changed( Index::CACHE_GROUP );
		$capped = array(
			$this->find( 'pergelortak pergelnadir' ),
			$this->find( 'pergelnadir pergelortak' ),
			$this->find( 'pergelortak pergelyok' ),
		);
		$this->assertStringContainsString( 'c.object_id = r.object_id', $GLOBALS['wpdb']->last_query, 'The common term was looked up on the found posts.' );
		$this->assertCount( 4, $this->find( 'pergelortak' ), 'A common term alone still finds every post.' );
		remove_filter( 'pergel/search/common_rows', $rows );
		$this->assertSame( $full, $capped );
		$this->assertSame( array(), $capped[2] );
	}

	public function test_title_ranks_above_content(): void {
		$content = $this->post( 'Başka bir yazı', 'Bu yazıda pergelkartal geçiyor.' );
		$title   = $this->post( 'Pergelkartal üzerine' );
		$this->assertSame( array( $title, $content ), $this->find( 'pergelkartal' ) );
	}

	public function test_post_type_limits_results(): void {
		$post = $this->post( 'Pergelbaykuş yazısı' );
		$page = $this->post( 'Pergelbaykuş sayfası', '', 'page' );
		$this->assertSame( array( $page ), $this->find( 'pergelbaykus', 'page' ) );
		$this->assertEqualsCanonicalizing( array( $post, $page ), $this->find( 'pergelbaykus' ) );
	}

	public function test_unpublished_posts_leave_the_index(): void {
		$id = $this->post( 'Pergelserçe' );
		$this->assertSame( array( $id ), $this->find( 'pergelserce' ) );

		wp_trash_post( $id );
		Index::flush();
		$this->assertSame( array(), $this->find( 'pergelserce' ) );

		wp_untrash_post( $id );
		wp_publish_post( $id );
		Index::flush();
		$this->assertSame( array( $id ), $this->find( 'pergelserce' ) );

		wp_update_post(
			array(
				'ID'            => $id,
				'post_password' => 'secret',
			)
		);
		Index::flush();
		$this->assertSame( array(), $this->find( 'pergelserce' ), 'Password-protected posts are not searched.' );
	}

	public function test_categories_are_indexed_and_their_posts_follow_a_rename(): void {
		$term = wp_insert_term( 'Pergelçınar', 'category' );
		$this->assertIsArray( $term );
		$this->terms[ (int) $term['term_id'] ] = 'category';
		$id                                    = $this->post( 'Ağaçlar' );
		wp_set_post_categories( $id, array( (int) $term['term_id'] ) );
		Index::flush();

		$this->assertSame( array( (int) $term['term_id'] ), array_keys( Query::match( Index::KIND_TERM, Text::terms( 'pergelcinar' ) ) ) );
		$this->assertSame( array( $id ), $this->find( 'pergelcinar' ), 'A post is found by its category.' );

		wp_update_term( (int) $term['term_id'], 'category', array( 'name' => 'Pergelmeşe' ) );
		Index::flush();
		$this->assertNotFalse( wp_next_scheduled( Index::TERM_HOOK, array( (int) $term['term_taxonomy_id'], 0 ) ) );
		Index::term_posts( (int) $term['term_taxonomy_id'], 0 );
		wp_clear_scheduled_hook( Index::TERM_HOOK, array( (int) $term['term_taxonomy_id'], 0 ) );
		$this->assertSame( array( $id ), $this->find( 'pergelmese' ) );
		$this->assertSame( array(), $this->find( 'pergelcinar' ) );
	}

	public function test_search_query_uses_the_index(): void {
		$content = $this->post( 'Bir yazı', 'Pergelleylek burada.' );
		$title   = $this->post( 'Pergelleylek' );

		$query = new \WP_Query(
			array(
				's'         => 'PERGELLEYLEK',
				'post_type' => 'post',
				'fields'    => 'ids',
			)
		);
		$this->assertTrue( $query->get( 'pergel_search' ) );
		$this->assertSame( array( $title, $content ), array_map( 'intval', $query->posts ) );
		$this->assertStringNotContainsString( 'LIKE', $query->request );

		$none = new \WP_Query(
			array(
				's'      => 'pergelyokboyle',
				'fields' => 'ids',
			)
		);
		$this->assertSame( array(), $none->posts );
	}

	public function test_sorting_chosen_by_the_visitor_is_kept(): void {
		$older = $this->post( 'Pergelturna eski' );
		$newer = $this->post( 'Pergelturna yeni', 'pergelturna pergelturna' );
		wp_update_post(
			array(
				'ID'        => $older,
				'post_date' => '2020-01-01 10:00:00',
			)
		);
		Index::flush();
		$query = new \WP_Query(
			array(
				's'       => 'pergelturna',
				'orderby' => 'title',
				'order'   => 'DESC',
				'fields'  => 'ids',
			)
		);
		$this->assertSame( array( $newer, $older ), array_map( 'intval', $query->posts ) );
	}

	public function test_suggestions_answer_shape(): void {
		$id   = $this->post( 'Pergelpelikan rehberi' );
		$term = wp_insert_term( 'Pergelpelikanlar', 'category' );
		$this->assertIsArray( $term );
		$this->terms[ (int) $term['term_id'] ] = 'category';
		wp_set_post_categories( $id, array( (int) $term['term_id'] ) );
		Index::flush();

		$request = new \WP_REST_Request( 'GET', '/' . Suggest::NAMESPACE . '/' . Suggest::ROUTE );
		$request->set_query_params( array( 'q' => 'pergelpelikan' ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'public, max-age=300', $response->get_headers()['Cache-Control'] ?? '' );

		$answer = $response->get_data();
		$this->assertIsArray( $answer );
		$this->assertSame( 'pergelpelikan', $answer['query'] );
		$this->assertSame( 1, $answer['total'] );
		$this->assertSame( $id, $answer['items'][0]['id'] );
		$this->assertSame( 'Pergelpelikan rehberi', $answer['items'][0]['title'] );
		$this->assertSame( get_permalink( $id ), $answer['items'][0]['url'] );
		$this->assertNull( $answer['items'][0]['image'] );
		$this->assertSame( '', $answer['items'][0]['price'] );
		$this->assertSame( 'Pergelpelikanlar', $answer['terms'][0]['name'] );
		$this->assertStringContainsString( 's=pergelpelikan', $answer['more'] );

		$long = new \WP_REST_Request( 'GET', '/' . Suggest::NAMESPACE . '/' . Suggest::ROUTE );
		$long->set_query_params( array( 'q' => str_repeat( 'a', Suggest::MAX_QUERY + 1 ) ) );
		$this->assertSame( 400, rest_do_request( $long )->get_status() );
	}

	public function test_suggestions_follow_the_limit_setting(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			$this->post( 'Pergelflamingo ' . $i );
		}
		Settings::update( array( 'search' => array( 'live_limit' => 3 ) ) );
		Settings::flush();
		$this->assertSame( array(), Suggest::answer( 'p' )['items'], 'One letter is not a query.' );
		$answer = Suggest::answer( 'pergelflamingo' );
		$this->assertSame( 5, $answer['total'] );
		$this->assertCount( 3, $answer['items'] );
	}

	public function test_search_block_becomes_a_combobox(): void {
		$html = do_blocks( '<!-- wp:search {"label":"Ara","buttonText":"Ara"} /-->' );
		$this->assertStringContainsString( 'data-pergel-search=', $html );
		$this->assertStringContainsString( 'role="combobox"', $html );
		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertStringContainsString( 'role="listbox"', $html );
		$this->assertStringContainsString( 'role="status"', $html );
		$this->assertMatchesRegularExpression( '/aria-controls="(pergel-search-\d+)".*id="\1"/s', $html );
		$this->assertSame( '', Box::config( '' )['type'] );

		Settings::update( array( 'search' => array( 'live' => false ) ) );
		Settings::flush();
		$plain = do_blocks( '<!-- wp:search {"label":"Ara","buttonText":"Ara"} /-->' );
		$this->assertStringNotContainsString( 'data-pergel-search', $plain );
		$this->assertStringNotContainsString( 'combobox', $plain );
	}

	public function test_switching_the_module_on_rebuilds(): void {
		Settings::update( array( 'modules' => array( 'search' => false ) ) );
		Settings::update( array( 'modules' => array( 'search' => true ) ) );
		$this->assertFalse( Index::ready() );
		$this->assertSame( 'posts', Index::state()['phase'] );
		self::build();
		$this->assertTrue( Index::ready() );
	}

	public function test_products_are_found_by_sku_and_price_follows_the_mode(): void {
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		$product = new \WC_Product_Simple();
		$product->set_name( 'Pergelkaplan lamba' );
		$product->set_status( 'publish' );
		$product->set_sku( 'PGK-77/B' );
		$product->set_regular_price( '250' );
		$product->save();
		$this->posts[] = $product->get_id();
		Index::flush();

		foreach ( array( 'pergelkaplan', 'pgk', 'PGK-77', 'pgk77b' ) as $query ) {
			$this->assertSame( array( $product->get_id() ), $this->find( $query, 'product' ), $query );
		}

		Settings::update( array( 'woo' => array( 'sales_mode' => Modes::SALE ) ) );
		Settings::flush();
		Modes::flush();
		$answer = Suggest::answer( 'pergelkaplan', 'product' );
		$this->assertSame( $product->get_id(), $answer['items'][0]['id'] );
		$this->assertStringContainsString( '250', $answer['items'][0]['price'] );

		Settings::update( array( 'woo' => array( 'sales_mode' => Modes::QUOTE ) ) );
		Settings::flush();
		Modes::flush();
		$this->assertSame( '', Suggest::answer( 'pergelkaplan', 'product' )['items'][0]['price'] );

		$product->set_catalog_visibility( 'hidden' );
		$product->save();
		Index::flush();
		$this->assertSame( array(), $this->find( 'pergelkaplan', 'product' ) );
	}

	public function test_product_search_block_suggests_products(): void {
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		$html = do_blocks( '<!-- wp:search {"label":"Ara","buttonText":"Ara","query":{"post_type":"product"}} /-->' );
		$this->assertStringContainsString( '&quot;type&quot;:&quot;product&quot;', $html );
	}
}
