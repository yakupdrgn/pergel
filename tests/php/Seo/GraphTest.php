<?php
/**
 * Schema graph tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Seo;

use Pergel\Seo\Graph;
use PHPUnit\Framework\TestCase;

/**
 * Covers the fallback graph, breadcrumbs, the Organization merge used with
 * SEO plugins, and description clipping.
 */
final class GraphTest extends TestCase {

	private const SITE = array(
		'url'         => 'https://example.com/',
		'name'        => 'Example',
		'description' => '',
		'language'    => 'tr',
		'search'      => 'https://example.com/?s={search_term_string}',
	);

	private const ORG = array(
		'@type' => 'Organization',
		'@id'   => 'https://example.com/#organization',
		'name'  => 'Example',
		'url'   => 'https://example.com/',
	);

	/**
	 * Page data with defaults.
	 *
	 * @param array<string, mixed> $page Overrides.
	 * @return array{type: string, url: string, name: string, description: string, image: array{url?: string, width?: int, height?: int, alt?: string}, crumbs: list<array{name: string, url: string}>, article: array{type?: string, headline?: string, published?: string, modified?: string, author?: string}, front: bool}
	 */
	private static function page( array $page = array() ): array {
		return $page + array(
			'type'        => 'WebPage',
			'url'         => 'https://example.com/about/',
			'name'        => 'About',
			'description' => '',
			'image'       => array(),
			'crumbs'      => array(),
			'article'     => array(),
			'front'       => false,
		);
	}

	/**
	 * Node types in graph order.
	 *
	 * @param list<array<string, mixed>> $graph Graph.
	 * @return list<mixed>
	 */
	private static function types( array $graph ): array {
		return array_map( static fn ( array $node ): mixed => $node['@type'], $graph );
	}

	public function test_page_graph_has_one_organization_and_website(): void {
		$graph = Graph::build( self::SITE, self::page(), self::ORG );

		$this->assertSame( array( 'Organization', 'WebSite', 'WebPage' ), self::types( $graph ) );
		$this->assertSame( self::ORG, $graph[0] );
		$this->assertSame( array( '@id' => 'https://example.com/#organization' ), $graph[1]['publisher'] );
		$this->assertSame( 'https://example.com/?s={search_term_string}', $graph[1]['potentialAction']['target']['urlTemplate'] );
		$this->assertArrayNotHasKey( 'description', $graph[1] );
		$this->assertSame( array( '@id' => 'https://example.com/#website' ), $graph[2]['isPartOf'] );
		$this->assertArrayNotHasKey( 'about', $graph[2] );
	}

	public function test_front_page_is_about_the_organization(): void {
		$graph = Graph::build( self::SITE, self::page( array( 'front' => true ) ), self::ORG );
		$this->assertSame( array( '@id' => 'https://example.com/#organization' ), $graph[2]['about'] );
	}

	public function test_organization_without_id_gets_one(): void {
		$graph = Graph::build( self::SITE, self::page(), array( 'name' => 'Example' ) );
		$this->assertSame(
			array(
				'@type' => 'Organization',
				'@id'   => 'https://example.com/#organization',
				'name'  => 'Example',
			),
			$graph[0]
		);
	}

	public function test_post_graph_has_article_image_and_breadcrumbs(): void {
		$graph = Graph::build(
			self::SITE,
			self::page(
				array(
					'url'     => 'https://example.com/news/hello/',
					'name'    => 'Hello',
					'image'   => array(
						'url'    => 'https://example.com/hello.jpg',
						'width'  => 1200,
						'height' => 630,
					),
					'crumbs'  => array(
						array(
							'name' => 'Home',
							'url'  => 'https://example.com/',
						),
						array(
							'name' => 'News',
							'url'  => 'https://example.com/news/',
						),
						array(
							'name' => 'Hello',
							'url'  => 'https://example.com/news/hello/',
						),
					),
					'article' => array(
						'type'      => 'BlogPosting',
						'headline'  => 'Hello',
						'published' => '2026-10-14T07:00:00+00:00',
						'modified'  => '2026-10-15T07:00:00+00:00',
						'author'    => '',
					),
				)
			),
			self::ORG
		);

		$this->assertSame( array( 'Organization', 'WebSite', 'WebPage', 'BlogPosting', 'BreadcrumbList' ), self::types( $graph ) );
		$this->assertSame( 'https://example.com/news/hello/#primaryimage', $graph[2]['primaryImageOfPage']['@id'] );
		$this->assertSame( array( '@id' => 'https://example.com/news/hello/#breadcrumb' ), $graph[2]['breadcrumb'] );
		$this->assertSame( array( '@id' => 'https://example.com/news/hello/#primaryimage' ), $graph[3]['image'] );
		$this->assertArrayNotHasKey( 'author', $graph[3] );
		$this->assertSame( 3, $graph[4]['itemListElement'][2]['position'] );
		$this->assertSame( 'https://example.com/news/', $graph[4]['itemListElement'][1]['item'] );
	}

	public function test_short_trails_are_not_breadcrumbs(): void {
		$home = array(
			'name' => 'Home',
			'url'  => 'https://example.com/',
		);
		$this->assertSame( array(), Graph::breadcrumbs( array( $home ), '#b' ) );
		$this->assertSame(
			array(),
			Graph::breadcrumbs(
				array(
					$home,
					array(
						'name' => '',
						'url'  => 'https://example.com/x/',
					),
				),
				'#b'
			)
		);
	}

	public function test_enrich_fills_only_empty_properties(): void {
		$plugin = array(
			'@type'  => 'Organization',
			'@id'    => 'https://example.com/#organization',
			'name'   => 'Plugin Name',
			'logo'   => array( '@id' => 'https://example.com/#logo' ),
			'email'  => '',
			'sameAs' => array( 'https://x.com/example' ),
		);
		$brand  = array(
			'@type'     => 'Organization',
			'@id'       => 'https://example.com/#organization',
			'name'      => 'Brand Name',
			'logo'      => array( 'url' => 'https://example.com/brand.png' ),
			'email'     => 'info@example.com',
			'telephone' => '+90 212 000 00 00',
			'sameAs'    => array( 'https://x.com/example', 'https://www.linkedin.com/company/example' ),
		);

		$this->assertSame(
			array(
				'@type'     => 'Organization',
				'@id'       => 'https://example.com/#organization',
				'name'      => 'Plugin Name',
				'logo'      => array( '@id' => 'https://example.com/#logo' ),
				'email'     => 'info@example.com',
				'sameAs'    => array( 'https://x.com/example', 'https://www.linkedin.com/company/example' ),
				'telephone' => '+90 212 000 00 00',
			),
			Graph::enrich( $plugin, $brand )
		);
	}

	public function test_enrich_leaves_other_nodes_alone(): void {
		$brand = array( 'telephone' => '+90 212 000 00 00' );
		$this->assertSame( array( '@type' => 'WebSite' ), Graph::enrich( array( '@type' => 'WebSite' ), $brand ) );
		$person = array(
			'@type' => array( 'Person', 'Organization' ),
			'@id'   => 'https://example.com/#person',
		);
		$this->assertSame( $person, Graph::enrich( $person, $brand ) );
		$this->assertSame( 'text', Graph::enrich( 'text', $brand ) );

		$local = Graph::enrich(
			array(
				'@type' => 'Dentist',
				'@id'   => 'https://example.com/#organization',
			),
			$brand
		);
		$this->assertSame( '+90 212 000 00 00', $local['telephone'] );
	}

	public function test_enrich_all_keeps_keys(): void {
		$graph = array(
			'publisher' => array( '@type' => 'Organization' ),
			'WebSite'   => array( '@type' => 'WebSite' ),
		);
		$out   = Graph::enrich_all( $graph, array( 'email' => 'info@example.com' ) );
		$this->assertSame( array( 'publisher', 'WebSite' ), array_keys( $out ) );
		$this->assertSame( 'info@example.com', $out['publisher']['email'] );
		$this->assertSame( null, Graph::enrich_all( null, array() ) );
	}

	public function test_clip(): void {
		$this->assertSame( 'Short text.', Graph::clip( "  Short\n text. " ) );
		$long = str_repeat( 'kelime ', 40 );
		$cut  = Graph::clip( $long );
		$this->assertLessThanOrEqual( 160, mb_strlen( $cut ) );
		$this->assertStringEndsWith( 'kelime…', $cut );
		$this->assertSame( 'Çok uzun bir başlık…', Graph::clip( 'Çok uzun bir başlık, gerçekten', 22 ) );
		$this->assertSame( 'abcdefghi…', Graph::clip( 'abcdefghijklmnop', 10 ) );
	}
}
