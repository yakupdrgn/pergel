<?php
/**
 * The SEO module without an SEO plugin, and its hand-over to one.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Core\Plugin;
use Pergel\Seo\Detect;
use Pergel\Seo\Head;
use Pergel\Seo\Integrations;
use Pergel\Seo\Meta;
use Pergel\Seo\Sitemap;
use Pergel\Settings\Screen;
use Pergel\Settings\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Creates posts, runs queries against them and reads the printed head.
 * Everything created is deleted again.
 */
final class SeoTest extends TestCase {

	/**
	 * Posts created by the test.
	 *
	 * @var list<int>
	 */
	private array $posts = array();

	/**
	 * The option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved;

	protected function setUp(): void {
		$this->saved = get_option( Settings::OPTION, false );
		if ( '' !== Detect::active() ) {
			$this->markTestSkipped( 'An SEO plugin is active on this site.' );
		}
	}

	protected function tearDown(): void {
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		remove_all_filters( 'pergel/seo/plugin' );
		$GLOBALS['wp_query']     = new \WP_Query();
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		Head::flush();
		Integrations::flush();
	}

	/**
	 * A published post.
	 *
	 * @param array<string, mixed> $args Post fields.
	 * @param array<string, mixed> $meta Meta values.
	 */
	private function post( array $args, array $meta = array() ): int {
		$id = wp_insert_post(
			$args + array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Pergel SEO test',
				'post_author' => 1,
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * Runs a main query, as WordPress does for a front-end request.
	 *
	 * @param array<string, mixed> $vars Query vars.
	 */
	private static function go( array $vars ): void {
		$GLOBALS['wp_query']     = new \WP_Query( $vars );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		Head::flush();
	}

	/**
	 * Output of a Head printer.
	 *
	 * @param string $method Head method name.
	 */
	private static function capture( string $method ): string {
		ob_start();
		Head::$method();
		return (string) ob_get_clean();
	}

	/**
	 * The printed JSON-LD graph.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function graph(): array {
		$html = self::capture( 'print_graph' );
		self::assertSame( 1, preg_match( '#<script type="application/ld\+json">(.+)</script>#', $html, $match ) );
		$data = json_decode( $match[1], true );
		self::assertIsArray( $data );
		return $data['@graph'];
	}

	public function test_module_runs_the_fallback(): void {
		$this->assertContains( 'seo', Plugin::loaded() );
		$this->assertNotFalse( has_action( 'wp_head', array( Head::class, 'print_graph' ) ) );
		$this->assertTrue( registered_meta_key_exists( 'post', Meta::DESCRIPTION, '' ) );
		$this->assertContains( 'page', Meta::post_types() );
		$this->assertNotContains( 'attachment', Meta::post_types() );
	}

	public function test_post_title_description_and_graph(): void {
		$id = $this->post(
			array( 'post_content' => '<!-- wp:paragraph --><p>Body text.</p><!-- /wp:paragraph -->' ),
			array(
				Meta::TITLE       => 'Custom search title',
				Meta::DESCRIPTION => 'Custom <b>search</b> description & more',
			)
		);
		self::go( array( 'p' => $id ) );

		$this->assertSame( 'Custom search title', Head::document_title( '' ) );
		$meta = self::capture( 'print_meta' );
		$this->assertStringContainsString( '<meta name="description" content="Custom search description &amp; more" />', $meta );
		$this->assertStringContainsString( '<meta property="og:type" content="article" />', $meta );
		$this->assertStringContainsString( '<meta property="og:title" content="Custom search title" />', $meta );
		$this->assertStringContainsString( '<meta name="twitter:card" content="summary" />', $meta );
		$this->assertSame( '', self::capture( 'print_canonical' ), 'Core prints the canonical link of single posts.' );

		$graph = self::graph();
		$types = array_column( $graph, '@type' );
		$this->assertSame( 1, count( array_keys( $types, 'Organization', true ) ) );
		$this->assertSame( 1, count( array_keys( $types, 'WebSite', true ) ) );
		$this->assertContains( 'BlogPosting', $types );
		$article = $graph[ array_search( 'BlogPosting', $types, true ) ];
		$this->assertSame( 'Pergel SEO test', $article['headline'] );
		$this->assertSame( 'Person', $article['author']['@type'] );
	}

	public function test_description_falls_back_to_the_content(): void {
		$id = $this->post( array( 'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Heading</h2><!-- /wp:heading --><!-- wp:paragraph --><p>First &amp; second.</p><!-- /wp:paragraph -->' ) );
		self::go( array( 'p' => $id ) );
		$this->assertSame( 'Heading First & second.', Head::page()['description'] );

		wp_update_post(
			array(
				'ID'           => $id,
				'post_excerpt' => 'The excerpt.',
			)
		);
		self::go( array( 'p' => $id ) );
		$this->assertSame( 'The excerpt.', Head::page()['description'] );
	}

	public function test_hidden_post_is_noindex_and_left_out_of_the_sitemap(): void {
		$hidden  = $this->post( array( 'post_title' => 'Pergel hidden' ), array( Meta::NOINDEX => true ) );
		$visible = $this->post( array( 'post_title' => 'Pergel visible' ) );
		self::go( array( 'p' => $hidden ) );

		$robots = Head::robots( array( 'max-image-preview' => 'large' ) );
		$this->assertTrue( $robots['noindex'] );
		$this->assertArrayNotHasKey( 'max-image-preview', $robots );

		$provider = wp_sitemaps_get_server()->registry->get_provider( 'posts' );
		$this->assertNotNull( $provider );
		$urls = array_column( $provider->get_url_list( 1, 'post' ), 'lastmod', 'loc' );
		$this->assertArrayNotHasKey( get_permalink( $hidden ), $urls );
		$this->assertArrayHasKey( get_permalink( $visible ), $urls );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\dT/', (string) $urls[ get_permalink( $visible ) ] );
	}

	public function test_author_sitemap_is_off_by_default(): void {
		$this->assertArrayNotHasKey( 'users', wp_sitemaps_get_server()->registry->get_providers() );
		Settings::update( array( 'seo' => array( 'sitemap_users' => true ) ) );
		$this->assertSame( 'provider', Sitemap::provider( 'provider', 'users' ) );
		$this->assertSame( 'provider', Sitemap::provider( 'provider', 'posts' ) );
	}

	public function test_archive_canonical_includes_the_page_number(): void {
		$term = get_term_by( 'slug', 'pergel-test', 'category' );
		if ( ! $term instanceof \WP_Term ) {
			$this->markTestSkipped( 'The pergel-test category is missing; run tools/fixtures/seed.php.' );
		}
		self::go( array( 'cat' => $term->term_id ) );
		$link = (string) get_term_link( $term );
		$this->assertSame( '<link rel="canonical" href="' . esc_url( $link ) . '" />' . "\n", self::capture( 'print_canonical' ) );
		$this->assertSame( 'CollectionPage', Head::page()['type'] );

		self::go(
			array(
				'cat'   => $term->term_id,
				'paged' => 2,
			)
		);
		$this->assertSame( trailingslashit( $link ) . 'page/2/', Head::page()['url'] );
	}

	public function test_default_share_image_and_twitter_handle(): void {
		$image         = wp_insert_attachment(
			array(
				'post_title'     => 'pergel-share.png',
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			)
		);
		$this->posts[] = $image;
		update_post_meta( $image, '_wp_attached_file', 'pergel-test/pergel-share.png' );
		wp_update_attachment_metadata(
			$image,
			array(
				'width'  => 1200,
				'height' => 630,
				'file'   => 'pergel-test/pergel-share.png',
			)
		);
		Settings::update(
			array(
				'seo' => array(
					'og_image'     => $image,
					'twitter_site' => 'ariumlab',
				),
			)
		);
		self::go( array( 'p' => $this->post( array() ) ) );

		$meta = self::capture( 'print_meta' );
		$this->assertStringContainsString( '<meta property="og:image" content="' . esc_attr( (string) wp_get_attachment_url( $image ) ) . '" />', $meta );
		$this->assertStringContainsString( '<meta property="og:image:width" content="1200" />', $meta );
		$this->assertStringContainsString( '<meta name="twitter:card" content="summary_large_image" />', $meta );
		$this->assertStringContainsString( '<meta name="twitter:site" content="@ariumlab" />', $meta );
	}

	public function test_no_output_on_404(): void {
		self::go( array( 'p' => PHP_INT_MAX ) );
		$GLOBALS['wp_query']->set_404();
		$this->assertSame( '', self::capture( 'print_meta' ) . self::capture( 'print_graph' ) . self::capture( 'print_canonical' ) );
	}

	public function test_plugin_organization_is_filled_from_the_brand(): void {
		Settings::update(
			array(
				'brand' => array(
					'phone'  => '+90 212 000 00 00',
					'social' => array( 'https://www.linkedin.com/company/example' ),
				),
			)
		);
		Integrations::flush();
		Integrations::register( 'yoast' );
		$node = apply_filters(
			'wpseo_schema_organization',
			array(
				'@type' => 'Organization',
				'@id'   => home_url( '/' ) . '#organization',
				'name'  => 'From Yoast',
			)
		);
		remove_filter( 'wpseo_schema_organization', array( Integrations::class, 'node' ) );

		$this->assertSame( 'From Yoast', $node['name'] );
		$this->assertSame( '+90 212 000 00 00', $node['telephone'] );
		$this->assertSame( array( 'https://www.linkedin.com/company/example' ), $node['sameAs'] );

		$json = Integrations::json( '{"@context":"https://schema.org","@type":"Organization","name":"From SEOPress"}' );
		$this->assertIsString( $json );
		$this->assertStringContainsString( '"telephone":"+90 212 000 00 00"', $json );
		$this->assertSame( 'not json', Integrations::json( 'not json' ) );
	}

	public function test_detection_filter_and_settings_notice(): void {
		add_filter( 'pergel/seo/plugin', static fn (): string => 'rank-math' );
		$this->assertSame( 'rank-math', Detect::active() );

		require_once ABSPATH . 'wp-admin/includes/admin.php';
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		wp_set_current_user( (int) $admins[0] );
		$GLOBALS['title'] = 'Pergel settings';
		$_GET['tab']      = 'seo';
		ob_start();
		Screen::render();
		$html = (string) ob_get_clean();
		unset( $_GET['tab'] );
		wp_set_current_user( 0 );

		$this->assertStringContainsString( 'Rank Math is active, so these settings are not used now.', $html );
		$this->assertStringContainsString( 'name="pergel_settings[seo][sitemap_users]"', $html );
	}
}
