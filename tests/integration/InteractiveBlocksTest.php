<?php
/**
 * The carousel and mega menu blocks on a site.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Blocks\MegaMenu;
use PHPUnit\Framework\TestCase;

/**
 * The carousel marks up its slides as the WAI-ARIA carousel pattern and
 * keeps its buttons hidden until the view script runs; the mega menu is a
 * Navigation item that opens a template part of its own area.
 */
final class InteractiveBlocksTest extends TestCase {

	public function test_carousel_labels_each_slide_and_hides_the_buttons_without_script(): void {
		$html = do_blocks( self::carousel( 3, '{"label":"Customer stories","perView":2}' ) );
		$tags = new \WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $tags->next_tag( 'section' ) );
		$this->assertTrue( $tags->has_class( 'wp-block-pergel-carousel' ) );
		$this->assertTrue( $tags->has_class( 'has-several-per-view' ) );
		$this->assertSame( 'carousel', $tags->get_attribute( 'aria-roledescription' ) );
		$this->assertSame( 'Customer stories', $tags->get_attribute( 'aria-label' ) );
		$this->assertStringContainsString( '--pergel-carousel-per-view:2', (string) $tags->get_attribute( 'style' ) );

		$this->assertTrue( $tags->next_tag( array( 'class_name' => 'wp-block-pergel-carousel__track' ) ) );
		$this->assertSame( '0', $tags->get_attribute( 'tabindex' ) );

		$labels = array();
		while ( $tags->next_tag( array( 'class_name' => 'wp-block-pergel-carousel-slide' ) ) ) {
			$this->assertSame( 'group', $tags->get_attribute( 'role' ) );
			$this->assertSame( 'slide', $tags->get_attribute( 'aria-roledescription' ) );
			$labels[] = $tags->get_attribute( 'aria-label' );
		}
		$this->assertSame( array( '1 of 3', '2 of 3', '3 of 3' ), $labels );

		$nav = new \WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $nav->next_tag( array( 'class_name' => 'wp-block-pergel-carousel__nav' ) ) );
		$this->assertTrue( $nav->get_attribute( 'hidden' ) );
		$this->assertSame( 3, substr_count( $html, 'wp-block-pergel-carousel__dot"' ) );
	}

	public function test_carousel_clamps_slides_in_view_and_names_itself(): void {
		$tags = new \WP_HTML_Tag_Processor( do_blocks( self::carousel( 2, '{"perView":9}' ) ) );

		$this->assertTrue( $tags->next_tag( 'section' ) );
		$this->assertStringContainsString( '--pergel-carousel-per-view:4', (string) $tags->get_attribute( 'style' ) );
		$this->assertSame( 'Carousel', $tags->get_attribute( 'aria-label' ) );
	}

	public function test_carousel_prints_nothing_without_slides(): void {
		$this->assertSame( '', trim( do_blocks( '<!-- wp:pergel/carousel --><!-- /wp:pergel/carousel -->' ) ) );
	}

	public function test_nested_carousel_slides_keep_their_own_numbers(): void {
		$inner = self::carousel( 2 );
		$outer = '<!-- wp:pergel/carousel --><!-- wp:pergel/carousel-slide -->' . $inner
			. '<!-- /wp:pergel/carousel-slide --><!-- /wp:pergel/carousel -->';
		$html  = do_blocks( $outer );

		$this->assertSame( 1, substr_count( $html, 'aria-label="1 of 1"' ) );
		$this->assertSame( 1, substr_count( $html, 'aria-label="1 of 2"' ) );
		$this->assertSame( 1, substr_count( $html, 'aria-label="2 of 2"' ) );
	}

	public function test_navigation_can_hold_the_mega_menu(): void {
		$navigation = \WP_Block_Type_Registry::get_instance()->get_registered( 'core/navigation' );

		$this->assertNotNull( $navigation );
		$this->assertContains( 'pergel/mega-menu', (array) $navigation->allowed_blocks );
		$this->assertContains( MegaMenu::AREA, array_column( get_allowed_block_template_part_areas(), 'area' ) );
	}

	public function test_mega_menu_opens_its_template_part(): void {
		$html = do_blocks( '<!-- wp:pergel/mega-menu {"label":"Services"} /-->' );
		$tags = new \WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $tags->next_tag( 'li' ) );
		$this->assertTrue( $tags->has_class( 'wp-block-navigation-item' ) );
		$this->assertTrue( $tags->next_tag( 'button' ) );
		$this->assertSame( 'false', $tags->get_attribute( 'aria-expanded' ) );
		$panel = (string) $tags->get_attribute( 'aria-controls' );
		$this->assertTrue( $tags->next_tag( array( 'class_name' => 'wp-block-pergel-mega-menu__panel' ) ) );
		$this->assertSame( $panel, $tags->get_attribute( 'id' ) );
		$this->assertStringContainsString( '>Services</span>', $html );
		$this->assertStringContainsString( 'wp-block-template-part', $html );
		$this->assertStringContainsString( 'wp-block-page-list', $html );
	}

	public function test_mega_menu_prints_nothing_for_an_empty_part(): void {
		$patterns = \WP_Block_Patterns_Registry::get_instance();
		$pattern  = $patterns->get_registered( 'pergel/hidden-mega-menu' );
		$this->assertIsArray( $pattern );
		$patterns->unregister( 'pergel/hidden-mega-menu' );
		try {
			$this->assertSame( '', trim( do_blocks( '<!-- wp:pergel/mega-menu {"label":"Services"} /-->' ) ) );
		} finally {
			$patterns->register( 'pergel/hidden-mega-menu', $pattern );
		}
	}

	public function test_mega_menu_prints_nothing_for_a_missing_part(): void {
		$this->assertSame( '', trim( do_blocks( '<!-- wp:pergel/mega-menu {"menu":"no-such-part"} /-->' ) ) );
		$this->assertSame( '', trim( do_blocks( '<!-- wp:pergel/mega-menu {"menu":""} /-->' ) ) );
	}

	public function test_mega_menu_is_a_list_item_of_the_navigation(): void {
		$html = do_blocks( '<!-- wp:navigation {"overlayMenu":"never"} --><!-- wp:pergel/mega-menu {"label":"Services"} /--><!-- /wp:navigation -->' );

		$this->assertMatchesRegularExpression( '#<ul[^>]*wp-block-navigation__container[^>]*><li[^>]*wp-block-pergel-mega-menu#', $html );
		$this->assertStringNotContainsString( '<li class="wp-block-navigation-item"><li', $html );
	}

	/**
	 * A carousel with a number of slides.
	 *
	 * @param int    $slides     Number of slides.
	 * @param string $attributes Block attributes (JSON) or empty.
	 */
	private static function carousel( int $slides, string $attributes = '' ): string {
		$html = '<!-- wp:pergel/carousel' . ( '' === $attributes ? '' : ' ' . $attributes ) . ' -->';
		for ( $i = 1; $i <= $slides; $i++ ) {
			$html .= '<!-- wp:pergel/carousel-slide --><!-- wp:paragraph --><p>Slide ' . $i . '</p><!-- /wp:paragraph --><!-- /wp:pergel/carousel-slide -->';
		}
		return $html . '<!-- /wp:pergel/carousel -->';
	}
}
