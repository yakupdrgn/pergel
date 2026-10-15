<?php
/**
 * Block extensions on a site.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Blocks\Animation;
use PHPUnit\Framework\TestCase;

/**
 * Pergel's scroll animation adds classes and loads its CSS only where it is
 * used. Hiding by device and sticky position are core's; the theme turns
 * them on and sets the device sizes they use.
 */
final class ExtensionsTest extends TestCase {

	public function test_section_and_content_blocks_take_the_animation(): void {
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( array( 'core/group', 'core/heading', 'core/image', 'pergel/counter', 'pergel/pricing-table' ) as $name ) {
			$type = $registry->get_registered( $name );
			$this->assertNotNull( $type, "{$name} is not registered." );
			$this->assertArrayHasKey( Animation::ATTRIBUTE, $type->attributes, "{$name} has no animation." );
		}
		foreach ( array( 'core/navigation-link', 'pergel/carousel-slide', 'pergel/mega-menu' ) as $name ) {
			$type = $registry->get_registered( $name );
			$this->assertNotNull( $type, "{$name} is not registered." );
			$this->assertArrayNotHasKey( Animation::ATTRIBUTE, $type->attributes, "{$name} should not animate." );
		}
	}

	public function test_animation_adds_classes_and_loads_the_css(): void {
		wp_dequeue_style( Animation::STYLE );
		$plain = do_blocks( '<!-- wp:paragraph --><p>Still</p><!-- /wp:paragraph -->' );

		$this->assertStringNotContainsString( 'pergel-animate', $plain );
		$this->assertFalse( wp_style_is( Animation::STYLE ) );

		$html = do_blocks( '<!-- wp:group {"pergelAnimation":"rise","layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:paragraph --><p>Moves</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );
		$tags = new \WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $tags->next_tag() );
		foreach ( array( 'wp-block-group', 'pergel-animate', 'pergel-animate--rise' ) as $class ) {
			$this->assertTrue( $tags->has_class( $class ), "Missing class {$class}." );
		}
		$this->assertTrue( $tags->next_tag( 'p' ) );
		$this->assertFalse( $tags->has_class( 'pergel-animate' ) );
		$this->assertTrue( wp_style_is( Animation::STYLE ) );
		$this->assertNotEmpty( wp_styles()->get_data( Animation::STYLE, 'path' ) );
		wp_dequeue_style( Animation::STYLE );
	}

	public function test_an_unknown_effect_is_ignored(): void {
		wp_dequeue_style( Animation::STYLE );
		$html = do_blocks( '<!-- wp:heading {"pergelAnimation":"spin"} --><h2 class="wp-block-heading">Title</h2><!-- /wp:heading -->' );

		$this->assertStringNotContainsString( 'pergel-animate', $html );
		$this->assertFalse( wp_style_is( Animation::STYLE ) );
	}

	public function test_the_css_keeps_content_visible_without_support(): void {
		$css = (string) file_get_contents( (string) wp_styles()->get_data( Animation::STYLE, 'path' ) );

		$this->assertMatchesRegularExpression( '/@media \(prefers-reduced-motion: no-preference\)\s*\{\s*@supports \(animation-timeline: view\(\)\)/', $css );
		$this->assertStringNotContainsString( 'opacity: 0', substr( $css, (int) strpos( $css, '@media' ) ) );
	}

	public function test_the_editor_gets_the_option_before_pergels_blocks(): void {
		$helper = wp_scripts()->query( 'pergel-blocks-editor' );

		$this->assertNotFalse( $helper );
		$this->assertContains( Animation::SCRIPT, $helper->deps );
		$this->assertNotFalse( wp_scripts()->query( Animation::SCRIPT ) );
	}

	public function test_hiding_by_device_uses_the_theme_sizes(): void {
		$this->assertSame(
			array(
				'mobile' => '600px',
				'tablet' => '1024px',
			),
			wp_get_global_settings( array( 'viewport' ) )
		);

		$html = do_blocks( '<!-- wp:group {"metadata":{"blockVisibility":{"viewport":{"mobile":false}}},"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:paragraph --><p>Wide screens</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );
		$tags = new \WP_HTML_Tag_Processor( $html );

		$this->assertTrue( $tags->next_tag() );
		$this->assertTrue( $tags->has_class( 'wp-block-hidden-mobile' ) );
		$this->assertStringContainsString( '@media (width <= 600px)', (string) wp_style_engine_get_stylesheet_from_context( 'block-supports', array( 'prettify' => false ) ) );
	}

	public function test_sticky_position_is_on(): void {
		$this->assertTrue( wp_get_global_settings( array( 'position', 'sticky' ) ) );

		$html = do_blocks( '<!-- wp:group {"style":{"position":{"type":"sticky","top":"0px"}},"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:paragraph --><p>Stays</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );

		$this->assertStringContainsString( 'is-position-sticky', $html );
	}
}
