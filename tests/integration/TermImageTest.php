<?php
/**
 * Term image block: the term's image, its link and the empty tile.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WP_Block;

/**
 * Uses a post category, which works on any site; the term, its image and
 * the filter are removed after each test.
 */
final class TermImageTest extends TestCase {

	/**
	 * The test category.
	 *
	 * @var int
	 */
	private int $term = 0;

	/**
	 * The test image.
	 *
	 * @var int
	 */
	private int $image = 0;

	protected function setUp(): void {
		$made       = wp_insert_term( 'Pergel term image test', 'category', array( 'slug' => 'pergel-term-image-test' ) );
		$this->term = is_array( $made ) ? (int) $made['term_id'] : 0;
		$this->assertGreaterThan( 0, $this->term );
	}

	protected function tearDown(): void {
		remove_all_filters( 'pergel/term_image_id' );
		if ( $this->image ) {
			wp_delete_attachment( $this->image, true );
		}
		wp_delete_term( $this->term, 'category' );
	}

	public function test_a_term_without_an_image_gets_an_even_tile(): void {
		$html = $this->render( array( 'aspectRatio' => '1' ) );

		$this->assertStringContainsString( '<span class="wp-block-pergel-term-image__placeholder" style="aspect-ratio:1"></span>', $html );
		$this->assertStringContainsString( 'href="' . esc_url( (string) get_term_link( $this->term, 'category' ) ) . '" tabindex="-1" aria-hidden="true"', $html );
		$this->assertStringStartsWith( '<figure class="wp-block-pergel-term-image"', $html );
	}

	public function test_the_image_comes_from_term_meta_or_the_filter(): void {
		$this->image = $this->attachment();
		update_term_meta( $this->term, 'thumbnail_id', $this->image );

		$html = $this->render( array( 'isLink' => false ) );
		$this->assertStringContainsString( 'alt=""', $html );
		$this->assertStringContainsString( 'aspect-ratio:4/3', $html );
		$this->assertStringContainsString( (string) wp_get_attachment_url( $this->image ), $html );
		$this->assertStringNotContainsString( '<a ', $html );

		add_filter( 'pergel/term_image_id', '__return_zero' );
		$this->assertStringContainsString( '__placeholder', $this->render( array( 'aspectRatio' => 'bogus' ) ) );
		$this->assertStringContainsString( 'aspect-ratio:4/3', $this->render( array( 'aspectRatio' => 'bogus' ) ) );
	}

	public function test_nothing_outside_a_term(): void {
		$block = new WP_Block(
			array(
				'blockName'    => 'pergel/term-image',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$this->assertSame( '', $block->render() );
	}

	/**
	 * The block rendered for the test category.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 */
	private function render( array $attrs ): string {
		$block = new WP_Block(
			array(
				'blockName'    => 'pergel/term-image',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'termId'   => $this->term,
				'taxonomy' => 'category',
			)
		);
		return $block->render();
	}

	/**
	 * A small image in the media library.
	 */
	private function attachment(): int {
		$uploads = wp_upload_dir();
		$file    = $uploads['path'] . '/pergel-term-image-test.png';
		$image   = imagecreatetruecolor( 8, 6 );
		imagepng( $image, $file );
		$id = wp_insert_attachment(
			array(
				'post_title'     => 'Pergel term image test',
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			),
			$file
		);
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		return $id;
	}
}
