<?php
/**
 * Pattern fields read from and written into the theme's patterns.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use InvalidArgumentException;
use Pergel\Package\Fields;
use PHPUnit\Framework\TestCase;

/**
 * Field names come from the pattern's blocks; filled patterns keep the
 * markup the editor would save, so they open without a block recovery.
 */
final class PackageFieldsTest extends TestCase {

	/**
	 * Parsed blocks of a registered pattern.
	 *
	 * @param string $slug Pattern name.
	 * @return list<array<string, mixed>>
	 */
	private static function blocks( string $slug ): array {
		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( $slug );
		self::assertIsArray( $pattern, $slug );
		return parse_blocks( $pattern['content'] );
	}

	/**
	 * Fills a pattern; images under media/ are attachment 7, others stay as given.
	 *
	 * @param string               $slug   Pattern name.
	 * @param array<string, mixed> $values Field values.
	 * @param list<string>         $notes  Fields left as they were.
	 */
	private static function fill( string $slug, array $values, array &$notes = array() ): string {
		$media = static fn( string $src, ?string $alt ): array => str_starts_with( $src, 'media/' )
			? array(
				'id'  => 7,
				'url' => 'http://site.test/wp-content/uploads/' . basename( $src ),
				'alt' => $alt ?? 'Yüklenen görsel',
			)
			: array(
				'id'  => 0,
				'url' => $src,
				'alt' => $alt ?? '',
			);
		return serialize_blocks( Fields::fill( self::blocks( $slug ), $values, $media, $notes ) );
	}

	/**
	 * Names of the blocks in a tree, depth first.
	 *
	 * @param list<array<string, mixed>> $blocks Blocks.
	 * @return list<string>
	 */
	private static function names( array $blocks ): array {
		$names = array();
		foreach ( $blocks as $block ) {
			if ( null === $block['blockName'] ) {
				continue;
			}
			$names[] = $block['blockName'];
			$names   = array_merge( $names, self::names( $block['innerBlocks'] ) );
		}
		return $names;
	}

	public function test_schema_of_a_hero(): void {
		$schema = Fields::schema( self::blocks( 'pergel/hero-split' ) );

		$this->assertSame( array( 'eyebrow', 'heading', 'text', 'buttons', 'image' ), array_keys( $schema ) );
		$this->assertSame( array( 'text', 'text', 'text', 'buttons', 'image' ), array_column( $schema, 'type' ) );
		$this->assertCount( 2, $schema['buttons']['default'] );
		$this->assertSame( '#', $schema['buttons']['default'][0]['url'] );
		$this->assertSame( 'landscape.svg', $schema['image']['default'] );
	}

	public function test_items_need_alike_fields(): void {
		$pricing = Fields::schema( self::blocks( 'pergel/pricing-three' ) );
		$this->assertSame( 'items', $pricing['items']['type'] );
		$this->assertCount( 3, $pricing['items']['items'] );
		// The featured plan has its badge as one field more.
		$this->assertArrayHasKey( 'eyebrow', $pricing['items']['items'][1] );

		// A heading column beside a form column, or an image beside a quote, are not items.
		$this->assertArrayNotHasKey( 'items', Fields::schema( self::blocks( 'pergel/hero-form' ) ) );
		$this->assertArrayNotHasKey( 'items', Fields::schema( self::blocks( 'pergel/testimonial-with-image' ) ) );
		// The rows after a heading group are.
		$contact = Fields::schema( self::blocks( 'pergel/contact-inverse' ) );
		$this->assertSame( array( 'eyebrow', 'heading', 'text', 'items' ), array_keys( $contact ) );
	}

	public function test_without_values_every_pattern_is_unchanged(): void {
		$noop = static fn( string $src, ?string $alt ): array => array(
			'id'  => 0,
			'url' => $src,
			'alt' => $alt ?? '',
		);
		foreach ( \WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			if ( ! str_starts_with( $pattern['name'], 'pergel/' ) ) {
				continue;
			}
			$blocks = parse_blocks( $pattern['content'] );
			Fields::schema( $blocks );
			$this->assertSame( serialize_blocks( $blocks ), serialize_blocks( Fields::fill( $blocks, array(), $noop ) ), $pattern['name'] );
		}
	}

	public function test_fills_texts_buttons_and_image(): void {
		$notes = array();
		$html  = self::fill(
			'pergel/hero-split',
			array(
				'heading' => 'Topraktan **sofraya**',
				'text'    => 'Bal & zeytinyağı',
				'buttons' => array(
					array(
						'label' => 'Ürünler',
						'url'   => '/urunler/?a=1&b=2',
					),
				),
				'image'   => 'media/tarla.jpg',
			),
			$notes
		);

		$this->assertSame( array( 'eyebrow' ), $notes );
		$this->assertStringContainsString( '<h1 class="wp-block-heading">Topraktan <strong>sofraya</strong></h1>', $html );
		$this->assertStringContainsString( '<p class="has-muted-color has-text-color has-large-font-size">Bal &amp; zeytinyağı</p>', $html );
		$this->assertStringContainsString( '<a class="wp-block-button__link wp-element-button" href="/urunler/?a=1&amp;b=2">Ürünler</a>', $html );
		$this->assertSame( 1, substr_count( $html, '<!-- /wp:button -->' ) );
		$this->assertStringContainsString( '"id":7', $html );
		$this->assertStringContainsString( '<img src="http://site.test/wp-content/uploads/tarla.jpg" alt="Yüklenen görsel" style="border-radius:var(--wp--custom--radius--card);aspect-ratio:4/3;object-fit:cover" class="wp-image-7"/>', $html );
		// The eyebrow keeps the pattern's text.
		$this->assertStringContainsString( 'text-transform:uppercase">Mühendislik ve destek</p>', $html );
	}

	public function test_items_are_cloned_and_removed(): void {
		$plan  = array(
			'heading' => 'Plan',
			'text'    => '1 ₺',
		);
		$plans = self::fill( 'pergel/pricing-three', array( 'items' => array( $plan, $plan, $plan, $plan ) ) );
		$this->assertSame( 4, substr_count( $plans, '<!-- wp:pergel/pricing-plan' ) );
		$this->assertSame( 4, substr_count( $plans, '<h3 class="wp-block-heading">Plan</h3>' ) );
		// The new plan is a copy of the first, not of the featured one.
		$this->assertSame( 1, substr_count( $plans, '"featured":true' ) );
		$this->assertSame( serialize_blocks( parse_blocks( $plans ) ), $plans );

		$stats = self::fill(
			'pergel/stats-row',
			array(
				'items' => array(
					array(
						'text'   => '12',
						'text_2' => 'Köy',
					),
				),
			)
		);
		$this->assertSame( 1, substr_count( $stats, '<!-- wp:pergel/counter' ) );
		$this->assertStringContainsString( '>12</p>', $stats );
		$this->assertStringNotContainsString( "<!-- /wp:pergel/counter -->\n\n</div>", $stats );

		$faq = self::fill(
			'pergel/faq-accordion',
			array(
				'items' => array(
					array(
						'summary' => 'Kargo ne kadar sürer?',
						'text'    => 'İki gün.',
					),
				),
			)
		);
		$this->assertSame( 1, substr_count( $faq, '<!-- wp:details' ) );
		$this->assertMatchesRegularExpression( '/<summary[^>]*>Kargo ne kadar sürer\?<\/summary>/', $faq );
	}

	public function test_separators_between_items_follow_them(): void {
		$faq = static fn( int $n ): string => self::fill( 'pergel/faq-accordion', array( 'items' => array_fill( 0, $n, array() ) ) );
		// Each question has a rule under it.
		$this->assertSame( 2, substr_count( $faq( 2 ), '<!-- wp:separator' ) );
		$this->assertSame( 8, substr_count( $faq( 8 ), '<!-- wp:separator' ) );
		$this->assertSame( 0, substr_count( $faq( 0 ), '<!-- wp:separator' ) );

		// Rules only between rows.
		$rows = static fn( int $n ): string => self::fill( 'pergel/contact-details-list', array( 'items' => array_fill( 0, $n, array() ) ) );
		$this->assertSame( 1, substr_count( $rows( 2 ), '<!-- wp:separator' ) );
		$this->assertSame( 6, substr_count( $rows( 7 ), '<!-- wp:separator' ) );
		$this->assertSame( serialize_blocks( parse_blocks( $rows( 7 ) ) ), $rows( 7 ) );
	}

	public function test_lists_cover_and_media_text(): void {
		$hero = self::fill(
			'pergel/hero-checklist',
			array( 'list' => array( 'Bir', 'İki', 'Üç', 'Dört' ) )
		);
		$this->assertSame( 4, substr_count( $hero, '<!-- wp:list-item' ) );
		$this->assertStringContainsString( '<li>Dört</li>', $hero );

		$cover = self::fill( 'pergel/hero-cover', array( 'background' => 'media/kapak.jpg' ) );
		$this->assertStringContainsString( '"url":"http://site.test/wp-content/uploads/kapak.jpg"', $cover );
		$this->assertStringContainsString( '"id":7', $cover );
		$this->assertStringContainsString( '<img class="wp-block-cover__image-background wp-image-7" alt="Yüklenen görsel" src="http://site.test/wp-content/uploads/kapak.jpg"', $cover );

		$media = self::fill(
			'pergel/cta-with-image',
			array(
				'image' => array(
					'src' => 'media/ekip.jpg',
					'alt' => 'Ekip',
				),
			)
		);
		$this->assertStringContainsString( '"mediaId":7', $media );
		$this->assertMatchesRegularExpression( '/<img src="http:\/\/site.test\/wp-content\/uploads\/ekip.jpg" alt="Ekip" class="wp-image-7 size-full"\/>/', $media );
	}

	public function test_null_removes_a_block(): void {
		$blocks = parse_blocks( self::fill( 'pergel/hero-split', array( 'eyebrow' => null, 'buttons' => null ) ) );
		$names  = self::names( $blocks );

		$this->assertNotContains( 'core/buttons', $names );
		$this->assertSame( 1, count( array_keys( $names, 'core/paragraph', true ) ) );
	}

	public function test_unknown_fields_and_wrong_values_name_the_field(): void {
		$cases = array(
			'"title" is not a field here; fields: eyebrow, heading, text, buttons, image' => array( 'title' => 'x' ),
			'buttons: a list of { label, url } is expected' => array( 'buttons' => 'x' ),
			'buttons[1]: a button is { label, url }'        => array( 'buttons' => array( array( 'label' => 'x' ) ) ),
			'heading: text is expected'                     => array( 'heading' => array( 'x' ) ),
			'text: the link "javascript:alert(1" uses javascript:' => array( 'text' => '[x](javascript:alert(1))' ),
		);
		foreach ( $cases as $message => $values ) {
			try {
				self::fill( 'pergel/hero-split', $values );
				$this->fail( "Expected: $message" );
			} catch ( InvalidArgumentException $e ) {
				$this->assertStringStartsWith( $message, $e->getMessage() );
			}
		}

		try {
			self::fill( 'pergel/stats-row', array( 'items' => array( array( 'title' => 'x' ) ) ) );
			$this->fail( 'Expected an error.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringStartsWith( 'items[1]."title" is not a field here; fields: text, text_2', $e->getMessage() );
		}
	}
}
