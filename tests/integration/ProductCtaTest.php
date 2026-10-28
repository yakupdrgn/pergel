<?php
/**
 * The Product enquiry block and quote requests about products.
 *
 * Needs WooCommerce; skipped on a site without it, where it checks that
 * the block is not registered.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Core\Visitor;
use Pergel\Forms\Form;
use Pergel\Forms\Inbox;
use Pergel\Forms\Submit;
use Pergel\Settings\Settings;
use Pergel\Woo\Modes;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\Quote;
use Pergel\Woo\QuoteProvider;
use Pergel\Woo\QuoteRequest;
use PHPUnit\Framework\TestCase;

/**
 * Products, messages and settings are removed or restored after each test.
 * No mail leaves the machine: the pre_wp_mail filter catches it.
 */
final class ProductCtaTest extends TestCase {

	/**
	 * The settings option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved;

	/**
	 * Products made by the test.
	 *
	 * @var list<int>
	 */
	private array $products = array();

	/**
	 * Visitor address of the test, new for every test.
	 */
	private string $ip = '';

	/**
	 * Providers the test registers.
	 *
	 * @var list<QuoteProvider>
	 */
	private array $providers = array();

	protected function setUp(): void {
		if ( ! Woo::active() ) {
			return;
		}
		$this->saved = get_option( Settings::OPTION, false );
		$this->ip    = '198.51.100.' . wp_rand( 1, 254 );
		wp_set_current_user( 0 );
		add_filter( 'pre_wp_mail', '__return_true' );
		add_filter( 'pergel/woo/quote_providers', array( $this, 'providers' ) );
		Form::flush();
		delete_transient( Visitor::key( 'form:site', HOUR_IN_SECONDS ) );
		// Contacts set up on the site would show up in every test.
		$this->settings(
			array(
				'whatsapp'    => '',
				'contact_url' => '',
			),
			array( 'phone' => '' )
		);
	}

	protected function tearDown(): void {
		if ( ! Woo::active() ) {
			return;
		}
		foreach ( $this->products as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->delete( true );
			}
		}
		foreach ( get_posts(
			array(
				'post_type'      => Inbox::TYPE,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_key'       => Inbox::META_EMAIL,
				'meta_value'     => 'grace@example.invalid',
			)
		) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			wp_delete_post( (int) $id, true );
		}
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		delete_transient( Visitor::key( 'form:' . $this->ip, Submit::WINDOW ) );
		remove_filter( 'pre_wp_mail', '__return_true' );
		remove_filter( 'pergel/woo/quote_providers', array( $this, 'providers' ) );
		wp_set_current_user( 0 );
		unset( $GLOBALS['post'], $_GET['pergel_form'] );
		Settings::flush();
		Modes::flush();
	}

	/**
	 * Filter for pergel/woo/quote_providers.
	 *
	 * @param mixed $providers Providers so far.
	 * @return array<mixed>
	 */
	public function providers( mixed $providers ): array {
		return array_merge( is_array( $providers ) ? $providers : array(), $this->providers );
	}

	/**
	 * Skips a test that needs WooCommerce on a site without it.
	 */
	private function needs_woocommerce(): void {
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
	}

	/**
	 * Saves shop settings.
	 *
	 * @param array<string, mixed> $woo   Shop settings.
	 * @param array<string, mixed> $brand Brand settings.
	 */
	private function settings( array $woo, array $brand = array() ): void {
		Settings::update(
			array_filter(
				array(
					'woo'   => $woo,
					'brand' => $brand,
				)
			)
		);
		Settings::flush();
		Modes::flush();
	}

	/**
	 * Makes a simple product.
	 *
	 * @param string $mode Own mode; '' follows the shop.
	 */
	private function simple( string $mode = '' ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Pergel test balance' );
		$product->set_status( 'publish' );
		$product->set_sku( 'PERGEL-TB-' . wp_rand( 1000, 9999 ) );
		$product->set_regular_price( '100' );
		if ( '' !== $mode ) {
			$product->update_meta_data( Modes::POST_META, $mode );
		}
		$product->save();
		$this->products[] = $product->get_id();
		Modes::flush();
		return $product;
	}

	/**
	 * Makes a variable product with two variations.
	 *
	 * @return array{0: \WC_Product_Variable, 1: list<int>}
	 */
	private function variable(): array {
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Volume' );
		$attribute->set_options( array( '10 ml', '20 ml' ) );
		$attribute->set_variation( true );
		$attribute->set_visible( true );
		$product = new \WC_Product_Variable();
		$product->set_name( 'Pergel test pipette' );
		$product->set_status( 'publish' );
		$product->set_attributes( array( $attribute ) );
		$product->save();
		$this->products[] = $product->get_id();
		$ids              = array();
		foreach ( array( '10 ml', '20 ml' ) as $i => $value ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $product->get_id() );
			$variation->set_attributes( array( 'volume' => $value ) );
			$variation->set_regular_price( '50' );
			$variation->set_sku( 'PERGEL-TP-' . $i . '-' . wp_rand( 1000, 9999 ) );
			$variation->set_status( 'publish' );
			$variation->save();
			$ids[] = $variation->get_id();
		}
		$product = wc_get_product( $product->get_id() );
		$this->assertInstanceOf( \WC_Product_Variable::class, $product );
		Modes::flush();
		return array( $product, $ids );
	}

	/**
	 * Renders the block for a product.
	 *
	 * @param int                  $id         Product id.
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	private function render( int $id, array $attributes = array() ): string {
		$block = new \WP_Block(
			array(
				'blockName'    => 'pergel/product-cta',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'postId'   => $id,
				'postType' => 'product',
			)
		);
		return $block->render();
	}

	/**
	 * The signed token in a rendered quote form.
	 *
	 * @param string $html Rendered block.
	 */
	private function token( string $html ): string {
		$this->assertSame( 1, preg_match( '/name="token" value="([^"]+)"/', $html, $match ) );
		$spec = Form::verify( html_entity_decode( $match[1] ) );
		$this->assertNotNull( $spec );
		// Signed again ten seconds earlier, so the form is not "too quick".
		return Form::sign( array( 'time' => time() - 10 ) + $spec );
	}

	/**
	 * Valid sender fields.
	 *
	 * @return array<string, string>
	 */
	private static function sender(): array {
		return array(
			'name'    => 'Grace Hopper',
			'email'   => 'grace@example.invalid',
			'phone'   => '+90 212 000 00 00',
			'company' => 'Navy Labs',
			'message' => 'Delivery to Istanbul, please.',
		);
	}

	public function test_without_woocommerce_the_block_is_not_registered(): void {
		if ( Woo::active() ) {
			$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( 'pergel/product-cta' ) );
			return;
		}
		$this->assertFalse( \WP_Block_Type_Registry::get_instance()->is_registered( 'pergel/product-cta' ) );
		$this->assertFalse( class_exists( Quote::class, false ) );
		$this->assertFalse( has_filter( 'pergel/forms/subject_fields' ) );
	}

	public function test_a_product_for_sale_shows_nothing_unless_asked(): void {
		$this->needs_woocommerce();
		$this->settings(
			array(
				'sales_mode'  => 'sale',
				'whatsapp'    => '+90 532 000 00 00',
				'contact_url' => 'https://example.invalid/contact/',
			)
		);
		$product = $this->simple();
		$this->assertSame( '', $this->render( $product->get_id() ) );
		$html = $this->render( $product->get_id(), array( 'onSale' => true ) );
		$this->assertStringContainsString( 'https://wa.me/905320000000?text=', $html );
		$this->assertStringNotContainsString( '<details', $html );
	}

	public function test_quote_mode_shows_the_form_and_the_contact_links(): void {
		$this->needs_woocommerce();
		$this->settings(
			array(
				'sales_mode'  => 'quote',
				'whatsapp'    => '+90 532 000 00 00',
				'contact_url' => 'https://example.invalid/contact/',
			),
			array( 'phone' => '+90 212 000 00 00' )
		);
		$product = $this->simple();
		$html    = $this->render( $product->get_id() );
		$this->assertStringContainsString( 'is-mode-quote', $html );
		$this->assertStringContainsString( '<details id="pergel-quote" class="wp-block-pergel-product-cta__quote" data-wp-init="callbacks.anchor">', $html );
		$this->assertStringContainsString( 'is-kind-quote', $html );
		$this->assertStringContainsString( 'Pergel test balance (', $html );
		$this->assertStringContainsString( 'name="quantity"', $html );
		$this->assertStringNotContainsString( 'name="variation"', $html );
		$this->assertStringContainsString( 'href="tel:+902120000000"', $html );
		$this->assertStringContainsString( 'href="https://example.invalid/contact/"', $html );
		$this->assertStringContainsString( rawurlencode( (string) get_permalink( $product->get_id() ) ), $html );
		$this->assertSame( 3, substr_count( $html, 'data-wp-on--click="actions.lead"' ) );

		$_GET['pergel_form'] = 'sent';
		$this->assertStringContainsString( '<details id="pergel-quote" class="wp-block-pergel-product-cta__quote" data-wp-init="callbacks.anchor" open>', $this->render( $product->get_id() ) );

		$html = $this->render(
			$product->get_id(),
			array(
				'quote'    => false,
				'whatsapp' => false,
			)
		);
		$this->assertStringNotContainsString( '<details', $html );
		$this->assertStringNotContainsString( 'wa.me', $html );
	}

	public function test_a_quote_plugin_page_replaces_the_form(): void {
		$this->needs_woocommerce();
		$this->settings( array( 'sales_mode' => 'quote' ) );
		$product = $this->simple();
		$page    = static fn (): string => 'https://example.invalid/request-a-quote/?plugin_add=PT-1';
		add_filter( 'pergel/woo/quote_url', $page );
		$html = $this->render( $product->get_id() );
		remove_filter( 'pergel/woo/quote_url', $page );
		$this->assertStringContainsString( '<p class="wp-block-pergel-product-cta__quote-link"><a class="wp-element-button" href="https://example.invalid/request-a-quote/?plugin_add=PT-1"', $html );
		$this->assertStringContainsString( 'quote_page', $html );
		$this->assertStringNotContainsString( '<details', $html );
		$this->assertStringNotContainsString( '<form', $html );

		// An address that is not a web page is ignored.
		$script = static fn (): string => 'javascript:alert(1)';
		add_filter( 'pergel/woo/quote_url', $script );
		$html = $this->render( $product->get_id() );
		remove_filter( 'pergel/woo/quote_url', $script );
		$this->assertStringContainsString( '<details id="pergel-quote"', $html );
	}

	public function test_catalog_mode_shows_contacts_only(): void {
		$this->needs_woocommerce();
		$this->settings( array( 'contact_url' => 'https://example.invalid/contact/' ) );
		$product = $this->simple( Modes::CATALOG );
		$html    = $this->render( $product->get_id() );
		$this->assertStringContainsString( 'is-mode-catalog', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringContainsString( 'https://example.invalid/contact/', $html );
		// Without any contact set up there is nothing to show.
		$this->settings( array( 'contact_url' => '' ) );
		$this->assertSame( '', $this->render( $product->get_id() ) );
	}

	public function test_login_mode_offers_to_log_in(): void {
		$this->needs_woocommerce();
		$product = $this->simple( Modes::LOGIN );
		$html    = $this->render( $product->get_id() );
		$this->assertStringContainsString( esc_url( wp_login_url( (string) get_permalink( $product->get_id() ) ) ), $html );
		$user = wp_insert_user(
			array(
				'user_login' => 'pergel-cta-' . wp_rand( 1000, 9999 ),
				'user_pass'  => wp_generate_password(),
				'role'       => 'customer',
			)
		);
		$this->assertIsInt( $user );
		wp_set_current_user( $user );
		$this->assertSame( '', $this->render( $product->get_id() ) );
		wp_set_current_user( 0 );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user );
	}

	public function test_a_variable_product_asks_for_its_option(): void {
		$this->needs_woocommerce();
		$this->settings( array( 'sales_mode' => 'quote' ) );
		[ $product, $variations ] = $this->variable();
		$html                     = $this->render( $product->get_id() );
		$this->assertStringContainsString( 'name="variation" required', $html );
		foreach ( $variations as $id ) {
			$this->assertStringContainsString( '<option value="' . $id . '">', $html );
		}
		$this->assertStringContainsString( '10 ml', $html );

		$token  = $this->token( $html );
		$result = Submit::handle(
			array(
				'token'     => $token,
				'variation' => '999999999',
				'quantity'  => '0',
			) + self::sender(),
			$this->ip
		);
		$this->assertSame( 'invalid', $result['code'] );
		$this->assertSame( array( 'variation', 'quantity' ), array_keys( $result['errors'] ) );

		$result = Submit::handle(
			array(
				'token'     => $token,
				'variation' => (string) $variations[1],
				'quantity'  => '12',
			) + self::sender(),
			$this->ip
		);
		$this->assertSame( 'sent', $result['code'] );
		$values = get_post_meta( $result['entry'], Inbox::META_FIELDS, true );
		$this->assertIsArray( $values );
		$this->assertSame( array( 'name', 'email', 'phone', 'company', 'product', 'variation', 'quantity', 'message' ), array_keys( $values ) );
		$this->assertSame( 'Pergel test pipette', $values['product'] );
		$this->assertStringContainsString( '20 ml', $values['variation'] );
		$this->assertStringContainsString( (string) wc_get_product( $variations[1] )?->get_sku(), $values['variation'] );
		$this->assertSame( '12', $values['quantity'] );
	}

	public function test_a_quote_request_reaches_the_providers(): void {
		$this->needs_woocommerce();
		$this->settings( array( 'sales_mode' => 'quote' ) );
		$product         = $this->simple();
		$good            = new class() implements QuoteProvider {
			/**
			 * Requests received.
			 *
			 * @var list<QuoteRequest>
			 */
			public array $requests = array();
			public function id(): string {
				return 'fake';
			}
			public function label(): string {
				return 'Fake CRM';
			}
			public function available(): bool {
				return true;
			}
			public function send( QuoteRequest $request ): bool {
				$this->requests[] = $request;
				return true;
			}
		};
		$broken          = new class() implements QuoteProvider {
			public function id(): string {
				return 'broken';
			}
			public function label(): string {
				return 'Broken ERP';
			}
			public function available(): bool {
				return true;
			}
			public function send( QuoteRequest $request ): bool {
				throw new \RuntimeException( 'Down' );
			}
		};
		$off             = new class() implements QuoteProvider {
			public function id(): string {
				return 'off';
			}
			public function label(): string {
				return 'Not set up';
			}
			public function available(): bool {
				return false;
			}
			public function send( QuoteRequest $request ): bool {
				return true;
			}
		};
		$this->providers = array( $good, $broken, $off );

		$token  = $this->token( $this->render( $product->get_id() ) );
		$result = Submit::handle(
			array(
				'token'    => $token,
				'quantity' => '3',
			) + self::sender(),
			$this->ip
		);
		$this->assertSame( 'sent', $result['code'] );

		$this->assertCount( 1, $good->requests );
		$request = $good->requests[0];
		$this->assertSame( $result['entry'], $request->entry );
		$this->assertSame( $product->get_id(), $request->product );
		$this->assertSame( 0, $request->variation );
		$this->assertSame( $product->get_sku(), $request->sku );
		$this->assertSame( 3, $request->quantity );
		$this->assertSame( 'Grace Hopper', $request->name );
		$this->assertSame( 'Navy Labs', $request->company );
		$this->assertSame( $product->get_id(), $request->page );

		$this->assertSame(
			array(
				'fake'   => array(
					'label' => 'Fake CRM',
					'sent'  => true,
				),
				'broken' => array(
					'label' => 'Broken ERP',
					'sent'  => false,
				),
			),
			get_post_meta( $result['entry'], Inbox::META_HANDOFF, true )
		);
	}

	public function test_a_contact_form_is_not_passed_on(): void {
		$this->needs_woocommerce();
		$product = $this->simple();
		$seen    = 0;
		$count   = static function ( mixed $providers ) use ( &$seen ): mixed {
			++$seen;
			return $providers;
		};
		add_filter( 'pergel/woo/quote_providers', $count );
		$spec   = array(
			'kind'     => 'contact',
			'optional' => array(),
			'consent'  => false,
			'time'     => time() - 10,
			'post'     => $product->get_id(),
			'subject'  => $product->get_id(),
			'id'       => 'pergel-form-1',
		);
		$result = Submit::handle(
			array(
				'token'    => Form::sign( $spec ),
				'quantity' => '1',
			) + self::sender(),
			$this->ip
		);
		remove_filter( 'pergel/woo/quote_providers', $count );
		$this->assertSame( 'sent', $result['code'] );
		$this->assertSame( 0, $seen );
		$this->assertSame( '', get_post_meta( $result['entry'], Inbox::META_HANDOFF, true ) );
	}
}
