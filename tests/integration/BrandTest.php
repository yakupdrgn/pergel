<?php
/**
 * The pergel/brand binding source against real blocks.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Brand\Brand;
use Pergel\Brand\Module;
use Pergel\Core\Plugin;
use Pergel\Settings\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Saves brand details, renders bound blocks and puts everything back.
 */
final class BrandTest extends TestCase {

	/**
	 * The option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved;

	/**
	 * The Site Logo option before the test.
	 *
	 * @var mixed
	 */
	private mixed $site_logo;

	/**
	 * Attachments created by the test.
	 *
	 * @var list<int>
	 */
	private array $attachments = array();

	protected function setUp(): void {
		$this->saved     = get_option( Settings::OPTION, false );
		$this->site_logo = get_option( 'site_logo', false );
	}

	protected function tearDown(): void {
		foreach ( $this->attachments as $id ) {
			wp_delete_post( $id, true );
		}
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		if ( false === $this->site_logo ) {
			delete_option( 'site_logo' );
		} else {
			update_option( 'site_logo', $this->site_logo );
		}
		Settings::flush();
	}

	/**
	 * A block markup with one binding.
	 *
	 * @param string               $block     Block name without "core/".
	 * @param string               $attribute Bound attribute.
	 * @param array<string, mixed> $args      Binding arguments.
	 * @param string               $html      Saved block HTML.
	 */
	private static function render( string $block, string $attribute, array $args, string $html ): string {
		$attrs = array(
			'metadata' => array(
				'bindings' => array(
					$attribute => array(
						'source' => Module::SOURCE,
						'args'   => $args,
					),
				),
			),
		);
		return trim( do_blocks( "<!-- wp:$block " . wp_json_encode( $attrs ) . " -->$html<!-- /wp:$block -->" ) );
	}

	/**
	 * A fake image attachment; no file is written.
	 *
	 * @param string $name File name under uploads.
	 */
	private function attachment( string $name ): int {
		$id = wp_insert_attachment(
			array(
				'post_title'     => $name,
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			)
		);
		update_post_meta( $id, '_wp_attached_file', "pergel-test/$name" );
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => 300,
				'height' => 90,
				'file'   => "pergel-test/$name",
			)
		);
		$this->attachments[] = $id;
		return $id;
	}

	public function test_module_and_source_are_registered(): void {
		$this->assertContains( 'brand', Plugin::loaded() );
		$source = get_block_bindings_source( Module::SOURCE );
		$this->assertNotNull( $source );
		$this->assertSame( 'Pergel Brand', $source->label );
	}

	public function test_text_is_escaped_in_paragraphs_and_headings(): void {
		Settings::update( array( 'brand' => array( 'name' => 'Ariumlab & Co "Lab"' ) ) );

		$this->assertSame( '<p class="wp-block-paragraph">Ariumlab &amp; Co &quot;Lab&quot;</p>', self::render( 'paragraph', 'content', array( 'key' => 'name' ), '<p>Fallback</p>' ) );
		$this->assertSame(
			'<h2 class="wp-block-heading">Ariumlab &amp; Co &quot;Lab&quot;</h2>',
			self::render( 'heading', 'content', array( 'key' => 'name' ), '<h2 class="wp-block-heading">Fallback</h2>' )
		);
	}

	public function test_empty_value_keeps_the_saved_text(): void {
		Settings::update( array( 'brand' => array( 'phone' => '' ) ) );
		$this->assertSame( '<p class="wp-block-paragraph">+90 000</p>', self::render( 'paragraph', 'content', array( 'key' => 'phone' ), '<p>+90 000</p>' ) );
		$this->assertSame( '<p class="wp-block-paragraph">Saved</p>', self::render( 'paragraph', 'content', array( 'key' => 'nope' ), '<p>Saved</p>' ) );
		$this->assertSame( '<p class="wp-block-paragraph">Saved</p>', self::render( 'paragraph', 'content', array(), '<p>Saved</p>' ) );
	}

	public function test_name_falls_back_to_the_site_title(): void {
		Settings::update( array( 'brand' => array( 'name' => '' ) ) );
		$title = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
		$this->assertSame( $title, Brand::value( 'name' ) );
		$this->assertSame( esc_html( $title ), Module::bound_value( array( 'key' => 'name' ), new \WP_Block( array( 'blockName' => 'core/paragraph' ) ), 'content' ) );
	}

	public function test_derived_values(): void {
		Settings::update(
			array(
				'brand' => array(
					'name'        => 'Example',
					'email'       => 'info@example.com',
					'phone'       => '+90 (212) 000 00 00',
					'street'      => 'Örnek Sok. 1',
					'postal_code' => '34000',
					'locality'    => 'Kadıköy',
				),
			)
		);

		$this->assertSame( 'mailto:info@example.com', Brand::value( 'email_url' ) );
		$this->assertSame( 'tel:+902120000000', Brand::value( 'phone_url' ) );
		$this->assertSame( 'Örnek Sok. 1, 34000 Kadıköy', Brand::value( 'address' ) );
		$this->assertSame( home_url( '/' ), Brand::value( 'url' ) );
		$this->assertStringContainsString( wp_date( 'Y' ) . ' Example', (string) Brand::value( 'copyright' ) );
	}

	public function test_button_link_and_text(): void {
		Settings::update(
			array(
				'brand' => array(
					'email' => 'info@example.com',
					'phone' => '+90 212 000 00 00',
				),
			)
		);
		$html = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Call</a></div>';

		$this->assertStringContainsString( 'href="mailto:info@example.com"', self::render( 'button', 'url', array( 'key' => 'email_url' ), $html ) );
		$this->assertStringContainsString( '>+90 212 000 00 00</a>', self::render( 'button', 'text', array( 'key' => 'phone' ), $html ) );
	}

	public function test_social_profile_by_network(): void {
		Settings::update( array( 'brand' => array( 'social' => array( 'https://www.linkedin.com/company/example', 'https://x.com/example' ) ) ) );
		$html = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">X</a></div>';

		$this->assertStringContainsString(
			'href="https://x.com/example"',
			self::render(
				'button',
				'url',
				array(
					'key'     => 'social',
					'network' => 'x',
				),
				$html
			)
		);
		$this->assertStringContainsString(
			'href="#"',
			self::render(
				'button',
				'url',
				array(
					'key'     => 'social',
					'network' => 'instagram',
				),
				$html
			)
		);
	}

	public function test_image_logo_for_dark_backgrounds(): void {
		$id = $this->attachment( 'pergel-test-logo.png' );
		Settings::update(
			array(
				'brand' => array(
					'name'         => 'Example',
					'logo_inverse' => $id,
				),
			)
		);
		$attrs = wp_json_encode(
			array(
				'metadata' => array(
					'bindings' => array(
						'id'  => array(
							'source' => Module::SOURCE,
							'args'   => array( 'key' => 'logo_inverse' ),
						),
						'url' => array(
							'source' => Module::SOURCE,
							'args'   => array( 'key' => 'logo_inverse' ),
						),
						'alt' => array(
							'source' => Module::SOURCE,
							'args'   => array( 'key' => 'logo_inverse' ),
						),
					),
				),
			)
		);
		$html  = do_blocks( "<!-- wp:image $attrs --><figure class=\"wp-block-image\"><img src=\"placeholder.png\" alt=\"\"/></figure><!-- /wp:image -->" );

		$this->assertStringContainsString( 'src="' . wp_get_attachment_url( $id ) . '"', $html );
		$this->assertStringContainsString( 'alt="Example"', $html );
	}

	public function test_organization_uses_the_site_logo(): void {
		$id = $this->attachment( 'pergel-test-site-logo.png' );
		update_option( 'site_logo', $id );
		Settings::update(
			array(
				'brand' => array(
					'name'  => 'Example',
					'email' => 'info@example.com',
				),
			)
		);

		$org = Brand::organization();
		$this->assertSame( 'Example', $org['name'] );
		$this->assertSame( home_url( '/' ) . '#organization', $org['@id'] );
		$this->assertSame(
			array(
				'@type'  => 'ImageObject',
				'url'    => wp_get_attachment_url( $id ),
				'width'  => 300,
				'height' => 90,
			),
			$org['logo']
		);
	}

	public function test_editor_data_lists_fields_and_values(): void {
		Settings::update(
			array(
				'brand' => array(
					'name'   => 'Example',
					'social' => array( 'https://www.linkedin.com/company/example', 'https://example.com/blog' ),
				),
			)
		);
		$data = Brand::editor_data();

		$this->assertSame( 'Example', $data['values']['name'] );
		$this->assertSame( array( 'linkedin' => 'https://www.linkedin.com/company/example' ), $data['social'] );
		$keys = array_map( static fn ( array $item ): string => $item['type'] . ':' . implode( ',', $item['args'] ), $data['fields'] );
		$this->assertContains( 'string:name', $keys );
		$this->assertContains( 'string:phone_url', $keys );
		$this->assertContains( 'number:logo_inverse', $keys );
		$this->assertContains( 'string:social,linkedin', $keys );
		$this->assertNotContains( 'string:social', $keys );
		foreach ( $data['fields'] as $item ) {
			$this->assertNotSame( '', $item['label'] );
		}
	}

	public function test_editor_script_is_enqueued_with_data(): void {
		Module::enqueue_editor();
		$this->assertTrue( wp_script_is( 'pergel-brand-bindings', 'enqueued' ) );
		$before = wp_scripts()->get_inline_script_data( 'pergel-brand-bindings', 'before' );
		$this->assertStringStartsWith( 'window.pergelBrand = {"fields":', $before );
		wp_dequeue_script( 'pergel-brand-bindings' );
	}
}
