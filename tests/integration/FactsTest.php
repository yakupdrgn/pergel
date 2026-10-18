<?php
/**
 * Questions and specifications in the structured data.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Seo\Facts;
use Pergel\Seo\Faq;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\Properties;
use PHPUnit\Framework\TestCase;

/**
 * Reads the FAQ and Specifications patterns back as schema, and adds the
 * nodes to a graph. Everything created is deleted again.
 */
final class FactsTest extends TestCase {

	private const FAQ = '<!-- wp:group {"className":"pergel-faq"} --><div class="wp-block-group pergel-faq">'
		. '<!-- wp:details --><details class="wp-block-details"><summary>Is it <em>safe</em>?</summary><!-- wp:paragraph --><p>Yes &amp; tested.</p><!-- /wp:paragraph -->'
		. '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --></ul><!-- /wp:list --></details><!-- /wp:details -->'
		. '<!-- wp:details --><details class="wp-block-details"><summary>No answer</summary></details><!-- /wp:details -->'
		. '</div><!-- /wp:group -->'
		. '<!-- wp:details --><details class="wp-block-details"><summary>Outside</summary><!-- wp:paragraph --><p>Not a question of the FAQ.</p><!-- /wp:paragraph --></details><!-- /wp:details -->';

	private const SPECS = '<!-- wp:table {"className":"is-style-pergel-specs"} --><figure class="wp-block-table is-style-pergel-specs"><table>'
		. '<thead><tr><th>Property</th><th>Value</th></tr></thead>'
		. '<tbody><tr><td><strong>Material</strong></td><td>PP &amp; PE</td></tr><tr><td>Volume</td><td></td></tr><tr><td>Size</td><td>10 mm</td></tr></tbody></table></figure><!-- /wp:table -->'
		. '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>Plain</td><td>table</td></tr></tbody></table></figure><!-- /wp:table -->';

	/**
	 * Posts created by the test.
	 *
	 * @var list<int>
	 */
	private array $posts = array();

	/**
	 * The main query before go_to() replaced it.
	 */
	private ?\WP_Query $query = null;

	protected function tearDown(): void {
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		if ( null !== $this->query ) {
			$GLOBALS['wp_the_query'] = $this->query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the query go_to() replaced.
		}
		wp_reset_query();
		Faq::flush();
		if ( class_exists( Properties::class ) ) {
			Properties::flush();
		}
	}

	/**
	 * Content of a registered pattern.
	 *
	 * @param string $name Pattern name.
	 */
	private function pattern( string $name ): string {
		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( $name );
		$this->assertIsArray( $pattern, $name );
		return (string) $pattern['content'];
	}

	/**
	 * Runs the main query for the given query vars, as a page view would.
	 *
	 * @param array<string, mixed> $vars Query vars.
	 */
	private function go_to( array $vars ): void {
		$this->query           ??= $GLOBALS['wp_the_query'];
		$GLOBALS['wp_the_query'] = new \WP_Query( $vars ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test page view; put back in tearDown.
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
	}

	public function test_questions_are_the_details_inside_an_faq(): void {
		$this->assertSame(
			array(
				array(
					'question' => 'Is it safe?',
					'answer'   => 'Yes & tested. One',
				),
			),
			Facts::questions( parse_blocks( self::FAQ ) )
		);
		$this->assertSame( array(), Facts::questions( parse_blocks( '<!-- wp:paragraph --><p>pergel-faq</p><!-- /wp:paragraph -->' ) ) );

		$card = '<!-- wp:group {"className":"pergel-faq"} --><div class="wp-block-group pergel-faq">'
			. '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading --><h2 class="wp-block-heading">Questions</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph --></div><!-- /wp:group -->'
			. '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Card?</h3><!-- /wp:heading --><!-- wp:paragraph --><p>Answer.</p><!-- /wp:paragraph --></div><!-- /wp:group -->'
			. '</div><!-- /wp:group -->';
		$this->assertSame(
			array(
				array(
					'question' => 'Card?',
					'answer'   => 'Answer.',
				),
			),
			Facts::questions( parse_blocks( $card ) )
		);

		$this->assertCount( 3, Facts::questions( parse_blocks( $this->pattern( 'pergel/product-faq' ) ) ) );
		$this->assertCount( 6, Facts::questions( parse_blocks( $this->pattern( 'pergel/faq-cards' ) ) ) );
		foreach ( array( 'accordion', 'cards', 'inverse', 'split-intro', 'two-columns', 'with-contact' ) as $name ) {
			$this->assertNotEmpty( Facts::questions( parse_blocks( $this->pattern( 'pergel/faq-' . $name ) ) ), $name );
		}
	}

	public function test_properties_are_the_two_cell_rows_of_specifications(): void {
		$this->assertSame(
			array(
				array(
					'name'  => 'Material',
					'value' => 'PP & PE',
				),
				array(
					'name'  => 'Size',
					'value' => '10 mm',
				),
			),
			Facts::properties( parse_blocks( self::SPECS ) )
		);
		$this->assertCount( 5, Facts::properties( parse_blocks( $this->pattern( 'pergel/product-specs' ) ) ) );
		// Three columns: not name and value.
		$this->assertSame( array(), Facts::properties( parse_blocks( $this->pattern( 'pergel/product-compatibility' ) ) ) );
		$this->assertSame( array(), Facts::properties( parse_blocks( $this->pattern( 'pergel/product-ordering' ) ) ) );
	}

	public function test_nodes(): void {
		$questions = array(
			array(
				'question' => 'Q?',
				'answer'   => 'A.',
			),
		);
		$this->assertSame(
			array(
				'@type'      => 'FAQPage',
				'@id'        => 'https://example.com/a/#faq',
				'url'        => 'https://example.com/a/',
				'mainEntity' => array(
					array(
						'@type'          => 'Question',
						'name'           => 'Q?',
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => 'A.',
						),
					),
				),
				'inLanguage' => 'tr',
			),
			Facts::faq_page( $questions, 'https://example.com/a/', 'tr' )
		);
		$this->assertSame( array(), Facts::faq_page( array(), 'https://example.com/a/', 'tr' ) );

		$values = Facts::property_values(
			array(
				array(
					'name'  => 'Size',
					'value' => '10 mm',
				),
			)
		);
		$this->assertSame(
			array(
				array(
					'@type' => 'PropertyValue',
					'name'  => 'Size',
					'value' => '10 mm',
				),
			),
			$values
		);
		$own   = array(
			'@type'              => 'Product',
			'additionalProperty' => array( 'own' ),
		);
		$graph = array(
			array( '@type' => 'WebPage' ),
			array( '@type' => array( 'Product', 'Thing' ) ),
			$own,
		);
		$graph = Facts::add_properties( $graph, $values );
		$this->assertArrayNotHasKey( 'additionalProperty', $graph[0] );
		$this->assertSame( $values, $graph[1]['additionalProperty'] );
		$this->assertSame( $own, $graph[2] );
		$this->assertSame( $values, Facts::add_properties( array( 'product' => array( '@type' => 'Product' ) ), $values )['product']['additionalProperty'] );
		$this->assertSame( 'text', Facts::add_properties( 'text', $values ) );
	}

	public function test_a_page_with_an_faq_adds_a_faq_page_to_the_graph(): void {
		$id            = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Pergel FAQ test',
				'post_content' => self::FAQ,
			)
		);
		$this->posts[] = $id;
		$this->go_to( array( 'page_id' => $id ) );

		$list = Faq::graph( array( array( '@type' => 'WebPage' ) ) );
		$this->assertIsArray( $list );
		$this->assertCount( 2, $list );
		$this->assertSame( 'FAQPage', $list[1]['@type'] );
		$this->assertSame( wp_get_canonical_url( $id ) . '#faq', $list[1]['@id'] );
		$this->assertSame( 'Is it safe?', $list[1]['mainEntity'][0]['name'] );

		$map = Faq::graph( array( 'WebPage' => array( '@type' => 'WebPage' ) ) );
		$this->assertIsArray( $map );
		$this->assertSame( 'FAQPage', $map['pergel-faq']['@type'] );

		// A plugin's own FAQ wins (Yoast types the WebPage itself).
		$own = array( array( '@type' => array( 'WebPage', 'FAQPage' ) ) );
		$this->assertSame( $own, Faq::graph( $own ) );

		ob_start();
		Faq::print_script();
		$script = (string) ob_get_clean();
		$this->assertStringContainsString( 'class="pergel-faq-schema"', $script );
		$this->assertStringContainsString( '"@context":"https://schema.org","@type":"FAQPage"', $script );

		// Behind a password, the answers are not given away.
		wp_update_post(
			array(
				'ID'            => $id,
				'post_password' => 'secret',
			)
		);
		Faq::flush();
		$this->go_to( array( 'page_id' => $id ) );
		$this->assertSame( array(), Faq::node() );
	}

	public function test_product_specifications_become_additional_properties(): void {
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		$id            = (int) wp_insert_post(
			array(
				'post_type'    => 'product',
				'post_status'  => 'publish',
				'post_title'   => 'Pergel specs test',
				'post_content' => self::SPECS,
			)
		);
		$this->posts[] = $id;
		$product       = wc_get_product( $id );
		$this->assertInstanceOf( \WC_Product::class, $product );

		$markup = Properties::structured_data( array( '@type' => 'Product' ), $product );
		$this->assertIsArray( $markup );
		$this->assertSame( array( 'Material', 'Size' ), array_column( $markup['additionalProperty'], 'name' ) );
		$this->assertSame( 30, has_filter( 'woocommerce_structured_data_product', array( Properties::class, 'structured_data' ) ) );

		$this->go_to( array( 'p' => $id, 'post_type' => 'product' ) );
		$graph = Properties::graph( array( array( '@type' => 'Product' ) ) );
		$this->assertIsArray( $graph );
		$this->assertSame( 'PP & PE', $graph[0]['additionalProperty'][0]['value'] );
	}
}
