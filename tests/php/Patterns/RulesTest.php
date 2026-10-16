<?php
/**
 * Pattern rule tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Patterns;

use Pergel\Patterns\Rules;
use PHPUnit\Framework\TestCase;

/**
 * Each rule on a small pattern file.
 */
final class RulesTest extends TestCase {

	/**
	 * A pattern file with the given header lines and markup.
	 *
	 * @param string $markup Block markup.
	 * @param string $extra  Extra header lines.
	 * @param string $slug   File slug.
	 */
	private static function file( string $markup, string $extra = " * Categories: services\n * Description: Three cards.\n", string $slug = 'cards' ): string {
		return "<?php\n/**\n * Title: Cards\n * Slug: pergel/{$slug}\n{$extra} *\n * @package Pergel\n */\n\ndeclare( strict_types=1 );\n\ndefined( 'ABSPATH' ) || exit;\n?>\n{$markup}";
	}

	private const PARAGRAPH = "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\"><!-- wp:paragraph -->\n<p><?php esc_html_e( 'Hello', 'pergel' ); ?></p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->";

	public function test_a_good_pattern_passes(): void {
		$this->assertSame( array(), Rules::check( 'cards.php', self::file( self::PARAGRAPH ) ) );
	}

	public function test_header_fields_are_required(): void {
		$errors = Rules::check( 'cards.php', self::file( self::PARAGRAPH, " * Categories: widgets\n", 'other' ) );

		$this->assertContains( 'Slug must be pergel/cards.', $errors );
		$this->assertContains( 'Missing Description.', $errors );
		$this->assertContains( 'Unknown category widgets.', $errors );
		$this->assertSame( array(), Rules::check( 'hidden-x.php', self::file( self::PARAGRAPH, " * Inserter: no\n", 'hidden-x' ) ) );
	}

	public function test_hard_coded_text_is_found(): void {
		$text = str_replace( "<?php esc_html_e( 'Hello', 'pergel' ); ?>", 'Hello', self::PARAGRAPH );
		$alt  = '<!-- wp:image --><figure class="wp-block-image"><img src="#" alt="A team"/></figure><!-- /wp:image -->';
		$attr = '<!-- wp:pergel/form {"submitLabel":"Send"} /-->';

		$this->assertContains( 'Hard-coded text: "Hello".', Rules::check( 'cards.php', self::file( $text ) ) );
		$this->assertContains( 'Hard-coded alt: "A team".', Rules::check( 'cards.php', self::file( $alt ) ) );
		$this->assertContains( 'Hard-coded block attribute submitLabel: "Send".', Rules::check( 'cards.php', self::file( $attr ) ) );
		$this->assertSame( array(), Rules::check( 'cards.php', self::file( '<!-- wp:group {"style":{"color":{"text":"var:preset|color|base"}}} --><div class="wp-block-group"></div><!-- /wp:group -->' ) ) );
	}

	public function test_translations_use_the_theme_domain(): void {
		$wrong = str_replace( "'pergel' )", "'default' )", self::PARAGRAPH );
		$none  = str_replace( ", 'pergel' )", ' )', self::PARAGRAPH );
		$x     = str_replace( "esc_html_e( 'Hello', 'pergel' )", "echo esc_html_x( 'Hello', 'greeting, short', 'pergel' )", self::PARAGRAPH );

		$this->assertContains( 'Translation without the pergel text domain.', Rules::check( 'cards.php', self::file( $wrong ) ) );
		$this->assertContains( 'Translation without the pergel text domain.', Rules::check( 'cards.php', self::file( $none ) ) );
		$this->assertSame( array( 'pergel' ), Rules::domains( self::file( $x ) ) );
	}

	public function test_only_core_and_pergel_blocks(): void {
		$errors = Rules::check( 'cards.php', self::file( '<!-- wp:group --><div class="wp-block-group"><!-- wp:html --><b>x</b><!-- /wp:html --><!-- wp:acme/slider /--></div><!-- /wp:group -->' ) );

		$this->assertContains( 'Block core/html is not allowed.', $errors );
		$this->assertContains( 'Block acme/slider is not a core or Pergel block.', $errors );
	}

	public function test_woocommerce_blocks_only_in_hidden_shop_patterns(): void {
		$price = '<!-- wp:group --><div class="wp-block-group"><!-- wp:woocommerce/product-price /--></div><!-- /wp:group -->';
		$error = 'Block woocommerce/product-price is not a core or Pergel block.';

		$this->assertSame(
			array(),
			Rules::check(
				'hidden-product-x.php',
				self::file(
					$price,
					' * Inserter: no
',
					'hidden-product-x'
				)
			)
		);
		$this->assertContains(
			$error,
			Rules::check(
				'hidden-x.php',
				self::file(
					$price,
					' * Inserter: no
',
					'hidden-x'
				)
			)
		);
		$this->assertContains(
			$error,
			Rules::check(
				'hidden-product-x.php',
				self::file(
					$price,
					' * Categories: services
 * Description: Prices.
',
					'hidden-product-x'
				)
			)
		);
	}

	public function test_shop_sections_may_hold_woocommerce_blocks(): void {
		$price = '<!-- wp:group --><div class="wp-block-group"><!-- wp:woocommerce/product-price /--></div><!-- /wp:group -->';
		$shop  = " * Categories: pergel-shop\n * Description: Prices.\n";
		$rule  = 'Shop sections are named pergel/shop-* and filed under pergel-shop, and only they are.';

		$this->assertSame( array(), Rules::check( 'shop-x.php', self::file( $price, $shop, 'shop-x' ) ) );
		$this->assertContains( $rule, Rules::check( 'shop-x.php', self::file( self::PARAGRAPH, " * Categories: services\n * Description: Prices.\n", 'shop-x' ) ) );
		$this->assertContains( $rule, Rules::check( 'cards.php', self::file( self::PARAGRAPH, $shop ) ) );
		$this->assertContains( 'Block woocommerce/product-price is not a core or Pergel block.', Rules::check( 'cards.php', self::file( $price ) ) );
	}

	public function test_h1_only_in_heroes_and_pages(): void {
		$h1 = '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading"><?php esc_html_e( "Hi", "pergel" ); ?></h1><!-- /wp:heading -->';
		$h1 = '<!-- wp:group --><div class="wp-block-group">' . $h1 . '</div><!-- /wp:group -->';

		$this->assertContains( 'An H1 belongs only in hero (banner) and full-page patterns.', Rules::check( 'cards.php', self::file( $h1 ) ) );
		$this->assertSame( array(), Rules::check( 'cards.php', self::file( $h1, " * Categories: banner\n * Description: Hero.\n" ) ) );
		$this->assertContains( 'More than one H1.', Rules::check( 'cards.php', self::file( str_replace( '</div>', '<h1>x</h1></div>', $h1 ), " * Categories: banner\n * Description: Hero.\n" ) ) );
	}

	public function test_one_root_block_or_a_list_of_patterns(): void {
		$two   = self::PARAGRAPH . "\n\n" . self::PARAGRAPH;
		$pages = "<!-- wp:pattern {\"slug\":\"pergel/hero\"} /-->\n\n<!-- wp:pattern {\"slug\":\"pergel/cards\"} /-->";

		$this->assertSame( array( 'core/group', 'core/group' ), Rules::roots( $two ) );
		$this->assertContains( 'One root block per section pattern (found 2); full pages list pattern blocks.', Rules::check( 'cards.php', self::file( $two ) ) );
		$this->assertSame( array(), Rules::check( 'cards.php', self::file( $pages, " * Categories: pergel-pages\n * Description: Home.\n" ) ) );
	}

	public function test_no_external_urls_or_assets(): void {
		$img    = '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.com/a.jpg" alt=""/></figure><!-- /wp:image -->';
		$cover  = '<!-- wp:cover {"url":"https:\/\/example.com\/a.jpg"} --><div class="wp-block-cover"></div><!-- /wp:cover -->';
		$noalt  = '<!-- wp:image --><figure class="wp-block-image"><img src="#"/></figure><!-- /wp:image -->';
		$script = '<!-- wp:group --><div class="wp-block-group"><script>1</script></div><!-- /wp:group -->';

		$this->assertContains( 'External URL; use theme files (get_theme_file_uri) or "#".', Rules::check( 'cards.php', self::file( $img ) ) );
		$this->assertContains( 'External URL; use theme files (get_theme_file_uri) or "#".', Rules::check( 'cards.php', self::file( $cover ) ) );
		$this->assertContains( 'Image without alt.', Rules::check( 'cards.php', self::file( $noalt ) ) );
		$this->assertContains( 'No <script> in patterns.', Rules::check( 'cards.php', self::file( $script ) ) );
	}

	public function test_preset_references_must_exist_in_the_theme(): void {
		$presets = Rules::presets(
			array(
				'settings' => array(
					'color'      => array( 'palette' => array( array( 'slug' => 'base' ), array( 'slug' => 'line' ) ) ),
					'typography' => array( 'fontSizes' => array( array( 'slug' => 'large' ) ) ),
					'spacing'    => array( 'spacingSizes' => array( array( 'slug' => '40' ) ) ),
				),
			)
		);
		$good    = '<!-- wp:group {"backgroundColor":"base","fontSize":"large","style":{"border":{"color":"var:preset|color|line"},"typography":{"fontSize":"3rem"},"spacing":{"padding":{"top":"var:preset|spacing|40"}}}} --><div class="wp-block-group" style="border-color:var(--wp--preset--color--line)"></div><!-- /wp:group -->';
		$bad     = '<!-- wp:group {"textColor":"border","fontSize":"huge","style":{"spacing":{"padding":{"top":"var:preset|spacing|90"}}}} --><div class="wp-block-group" style="border-color:var(--wp--preset--color--border)"></div><!-- /wp:group -->';

		$this->assertSame( array( 'base', 'line' ), $presets['color'] );
		$this->assertSame( array(), Rules::unknown_presets( self::file( $good ), $presets ) );
		$this->assertSame(
			array( 'Unknown spacing preset 90.', 'Unknown color preset border.', 'Unknown font-size preset huge.' ),
			Rules::unknown_presets( self::file( $bad ), $presets )
		);
	}
}
