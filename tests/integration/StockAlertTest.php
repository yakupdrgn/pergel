<?php
/**
 * Back-in-stock alerts: the form, its guards, the confirmation by email,
 * the emails when a product is back, the clean-up, the privacy tools and
 * the admin list.
 *
 * Needs WooCommerce for most tests; on a site without it, it checks that
 * the block is not registered. No mail leaves the machine: the
 * pre_wp_mail filter catches it.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Core\Visitor;
use Pergel\Forms\Submit;
use Pergel\Settings\Settings;
use Pergel\Woo\Modes;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\StockAlert;
use Pergel\Woo\StockAlertList;
use Pergel\Woo\StockAlertStore;
use PHPUnit\Framework\TestCase;

/**
 * Products, users, alerts, queued runs and settings are removed or
 * restored after each test.
 */
final class StockAlertTest extends TestCase {

	/**
	 * The settings option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved;

	/**
	 * Whether the alerts table was there before the test.
	 */
	private bool $installed = false;

	/**
	 * Products made by the test.
	 *
	 * @var list<int>
	 */
	private array $products = array();

	/**
	 * Users made by the test.
	 *
	 * @var list<int>
	 */
	private array $users = array();

	/**
	 * Mails caught by pre_wp_mail.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $mails = array();

	/**
	 * Visitor address of the test, new for every test.
	 */
	private string $ip = '';

	/**
	 * Email keys the test added to WooCommerce's mailer.
	 *
	 * @var list<string>
	 */
	private array $emails = array();

	protected function setUp(): void {
		$this->saved = get_option( Settings::OPTION, false );
		$this->ip    = '198.51.100.' . wp_rand( 1, 254 );
		wp_set_current_user( 0 );
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );
		delete_transient( Visitor::key( 'form:site', HOUR_IN_SECONDS ) );
		foreach ( array( 'ada', 'other', 'grace' ) as $name ) {
			delete_transient( Visitor::key( 'stock-mail:' . $name . '@example.invalid', DAY_IN_SECONDS ) );
		}
		if ( Woo::active() ) {
			$this->installed = StockAlertStore::installed();
			StockAlertStore::maintain();
			// The emails join the mailer only when the setting was on as the site loaded.
			$mailer = WC()->mailer();
			foreach ( StockAlert::emails( array() ) as $key => $email ) {
				if ( ! isset( $mailer->emails[ $key ] ) ) {
					$mailer->emails[ $key ] = $email;
					$this->emails[]         = $key;
				}
			}
		}
	}

	protected function tearDown(): void {
		global $wpdb;
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ) );
		remove_action( 'woocommerce_product_set_stock_status', array( StockAlert::class, 'stock_status' ) );
		remove_action( 'woocommerce_variation_set_stock_status', array( StockAlert::class, 'stock_status' ) );
		delete_transient( Visitor::key( 'form:' . $this->ip, Submit::WINDOW ) );
		unset( $_GET['pergel_stock'], $_GET['pergel_stock_id'], $_GET['pergel_stock_key'], $_SERVER['REQUEST_METHOD'] );
		remove_all_filters( 'wp_die_handler' );
		unset( $_GET['pergel_stock_alert'] );
		if ( Woo::active() ) {
			foreach ( $this->emails as $key ) {
				unset( WC()->mailer()->emails[ $key ] );
			}
			foreach ( $this->products as $id ) {
				as_unschedule_all_actions( StockAlert::SEND_HOOK, array( $id ), 'pergel' );
				$product = wc_get_product( $id );
				if ( $product ) {
					foreach ( $product->get_children() as $child ) {
						as_unschedule_all_actions( StockAlert::SEND_HOOK, array( $child ), 'pergel' );
					}
					$product->delete( true );
				}
			}
			if ( $this->installed ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . StockAlertStore::table() . ' WHERE email LIKE %s', '%@example.invalid' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			} else {
				StockAlertStore::uninstall();
			}
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		wp_set_current_user( 0 );
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		if ( Woo::active() ) {
			Modes::flush();
		}
	}

	/**
	 * Short-circuits wp_mail().
	 *
	 * @param mixed                $result Null.
	 * @param array<string, mixed> $atts   Mail.
	 */
	public function catch_mail( mixed $result, array $atts ): bool {
		unset( $result );
		$this->mails[] = $atts;
		return true;
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
	 * Turns the alerts on, or off.
	 *
	 * @param bool $on Setting.
	 */
	private function turn( bool $on = true ): void {
		Settings::update( array( 'woo' => array( 'stock_alert' => $on ) ) );
		Settings::flush();
		Modes::flush();
	}

	/**
	 * Saves a product made by the test.
	 *
	 * @param \WC_Product $product Product.
	 */
	private function keep( \WC_Product $product ): \WC_Product {
		$product->set_name( 'Pergel stock alert test ' . ( count( $this->products ) + 1 ) );
		$product->set_status( 'publish' );
		$product->save();
		$this->products[] = $product->get_id();
		return $product;
	}

	/**
	 * A simple product with a price.
	 *
	 * @param bool $in_stock Stock status.
	 */
	private function simple( bool $in_stock = false ): \WC_Product {
		$product = new \WC_Product_Simple();
		$product->set_regular_price( '100' );
		$product->set_stock_status( $in_stock ? 'instock' : 'outofstock' );
		return $this->keep( $product );
	}

	/**
	 * A product with one option per colour.
	 *
	 * @param array<string, bool> $stock Whether each colour is in stock.
	 */
	private function variable( array $stock ): \WC_Product_Variable {
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Colour' );
		$attribute->set_options( array_keys( $stock ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product = new \WC_Product_Variable();
		$product->set_attributes( array( $attribute ) );
		$this->keep( $product );
		foreach ( $stock as $colour => $in_stock ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $product->get_id() );
			$variation->set_attributes( array( 'colour' => $colour ) );
			$variation->set_regular_price( '10' );
			$variation->set_stock_status( $in_stock ? 'instock' : 'outofstock' );
			$variation->save();
		}
		\WC_Product_Variable::sync( $product->get_id() );
		$fresh = wc_get_product( $product->get_id() );
		$this->assertInstanceOf( \WC_Product_Variable::class, $fresh );
		return $fresh;
	}

	/**
	 * The option of a product for a colour.
	 *
	 * @param \WC_Product $product Product with options.
	 * @param string      $colour  Colour.
	 */
	private function option( \WC_Product $product, string $colour ): \WC_Product_Variation {
		foreach ( $product->get_children() as $id ) {
			$variation = wc_get_product( $id );
			if ( $variation instanceof \WC_Product_Variation && $colour === $variation->get_attribute( 'colour' ) ) {
				return $variation;
			}
		}
		$this->fail( "No option for $colour." );
	}

	/**
	 * The block for a product.
	 *
	 * @param int $id Product ID.
	 */
	private function block( int $id ): string {
		$block = new \WP_Block(
			array(
				'blockName' => StockAlert::BLOCK,
				'attrs'     => array(),
			),
			array(
				'postId'   => $id,
				'postType' => 'product',
			)
		);
		return StockAlert::render( $block );
	}

	/**
	 * The settings the script reads from the block's markup.
	 *
	 * @param string $html Block markup.
	 * @return array<string, mixed>
	 */
	private function config( string $html ): array {
		$this->assertSame( 1, preg_match( '/data-pergel-stock="([^"]*)"/', $html, $m ) );
		return (array) json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true );
	}

	/**
	 * A filled-in form for a product's page.
	 *
	 * @param int                  $page    Product of the page.
	 * @param int                  $product Product or option the alert is for.
	 * @param array<string, mixed> $fields  Fields over the defaults.
	 * @return array<string, mixed>
	 */
	private function form( int $page, int $product, array $fields = array() ): array {
		return $fields + array(
			'token'        => StockAlert::sign( $page, time() - 10 ),
			'product'      => (string) $product,
			'email'        => 'Ada@Example.invalid',
			'consent'      => '1',
			Submit::TRAP   => '',
		);
	}

	/**
	 * The key in a link.
	 *
	 * @param string $link Link from an email.
	 */
	private static function key( string $link ): string {
		parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );
		return is_string( $query['pergel_stock_key'] ?? null ) ? $query['pergel_stock_key'] : '';
	}

	public function test_block_needs_woocommerce(): void {
		$this->assertSame( Woo::active(), \WP_Block_Type_Registry::get_instance()->is_registered( StockAlert::BLOCK ) );
	}

	public function test_form_follows_the_add_to_cart_form_once(): void {
		$this->needs_woocommerce();
		$template          = new \WP_Block_Template();
		$template->slug    = 'single-product';
		$template->content = '<!-- wp:woocommerce/add-to-cart-with-options /-->';

		$this->assertSame( array( StockAlert::BLOCK ), StockAlert::hook( array(), 'after', 'woocommerce/add-to-cart-with-options', $template ) );
		$this->assertSame( array(), StockAlert::hook( array(), 'before', 'woocommerce/add-to-cart-with-options', $template ) );

		$template->content .= '<!-- wp:pergel/stock-alert /-->';
		$this->assertSame( array(), StockAlert::hook( array(), 'after', 'woocommerce/add-to-cart-with-options', $template ) );
	}

	public function test_sold_out_product_shows_the_form(): void {
		$this->needs_woocommerce();
		$product = $this->simple();
		$this->turn( false );
		$this->assertSame( '', $this->block( $product->get_id() ), 'Nothing while off.' );

		$this->turn();
		$html = $this->block( $product->get_id() );
		$this->assertMatchesRegularExpression( '/<div [^>]*id="pergel-stock-alert"/', $html );
		$this->assertDoesNotMatchRegularExpression( '/<div id="pergel-stock-alert"[^>]* hidden/', $html );
		$this->assertStringContainsString( '<form class="wp-block-pergel-stock-alert__form" action="' . admin_url( 'admin-post.php' ) . '" method="post" aria-labelledby="pergel-stock-alert-title">', $html );
		$this->assertStringContainsString( 'name="product" value="' . $product->get_id() . '"', $html );
		$this->assertStringContainsString( 'name="email" value=""', $html );
		$this->assertStringContainsString( 'name="consent" value="1" required', $html );
		$this->assertStringContainsString( 'name="website" tabindex="-1"', $html );
		$this->assertSame( 1, preg_match( '/name="token" value="([^"]+)"/', $html, $token ) );
		$this->assertSame( $product->get_id(), StockAlert::verify( $token[1] )['product'] ?? null );
		$config = $this->config( $html );
		$this->assertSame( array(), $config['variations'] );
		$this->assertArrayNotHasKey( 'nonce', $config, 'Guest pages may be cached.' );
		$this->assertStringEndsWith( '/pergel/v1/stock-alert', (string) $config['endpoint'] );

		$this->assertSame( '', $this->block( $this->simple( true )->get_id() ), 'In stock: nothing to wait for.' );

		$user          = (int) wp_insert_user(
			array(
				'user_login' => 'pergel_stock_' . wp_rand(),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'grace@example.invalid',
				'role'       => 'customer',
			)
		);
		$this->users[] = $user;
		wp_set_current_user( $user );
		$html = $this->block( $product->get_id() );
		$this->assertStringContainsString( 'name="email" value="grace@example.invalid"', $html );
		$this->assertArrayHasKey( 'nonce', $this->config( $html ) );
	}

	public function test_product_with_some_options_sold_out_waits_for_the_choice(): void {
		$this->needs_woocommerce();
		$this->turn();
		$product = $this->variable(
			array(
				'Red'  => true,
				'Blue' => false,
			)
		);
		$html    = $this->block( $product->get_id() );
		$this->assertMatchesRegularExpression( '/<div id="pergel-stock-alert"[^>]* hidden/', $html );
		$this->assertStringContainsString( 'method="post" hidden', $html );
		$this->assertSame(
			array(
				array(
					'id'     => $this->option( $product, 'Blue' )->get_id(),
					'values' => array( 'Blue' ),
				),
			),
			$this->config( $html )['variations']
		);

		$all  = $this->variable(
			array(
				'Red'  => false,
				'Blue' => false,
			)
		);
		$html = $this->block( $all->get_id() );
		$this->assertDoesNotMatchRegularExpression( '/<div id="pergel-stock-alert"[^>]* hidden/', $html, 'Every option sold out: an alert for the product.' );
		$this->assertStringContainsString( 'name="product" value="' . $all->get_id() . '"', $html );

		$this->assertSame(
			'',
			$this->block(
				$this->variable(
					array(
						'Red'  => true,
						'Blue' => true,
					)
				)->get_id()
			)
		);
	}

	public function test_result_from_a_link_is_shown_even_without_a_form(): void {
		$this->needs_woocommerce();
		$this->turn();
		$_GET['pergel_stock_alert'] = 'confirmed';
		$html                       = $this->block( $this->simple( true )->get_id() );
		$this->assertStringContainsString( 'id="pergel-stock-alert"', $html );
		$this->assertStringContainsString( 'role="status">' . esc_html( StockAlert::message( 'confirmed' ) ) . '</p>', $html );

		$_GET['pergel_stock_alert'] = '<script>';
		$this->assertSame( '', $this->block( $this->simple( true )->get_id() ) );
	}

	public function test_form_is_guarded(): void {
		$this->needs_woocommerce();
		$this->turn();
		$product = $this->simple();
		$id      = $product->get_id();

		$this->assertSame( 'confirm', StockAlert::handle( $this->form( $id, $id, array( Submit::TRAP => 'http://spam.example' ) ), $this->ip, 0 )['code'], 'A bot is told it worked.' );
		$this->assertSame( 'expired', StockAlert::handle( $this->form( $id, $id, array( 'token' => $id . '.' . time() . '.' . str_repeat( '0', 64 ) ) ), $this->ip, 0 )['code'] );
		$this->assertSame( 'wait', StockAlert::handle( $this->form( $id, $id, array( 'token' => StockAlert::sign( $id ) ) ), $this->ip, 0 )['code'] );
		$this->assertSame( 'unavailable', StockAlert::handle( $this->form( $id, $this->simple()->get_id() ), $this->ip, 0 )['code'], 'Only the page\'s product.' );
		$in_stock = $this->simple( true );
		$this->assertSame( 'unavailable', StockAlert::handle( $this->form( $in_stock->get_id(), $in_stock->get_id() ), $this->ip, 0 )['code'] );

		$result = StockAlert::handle( $this->form( $id, $id, array( 'email' => 'not-an-address', 'consent' => '' ) ), $this->ip, 0 );
		$this->assertSame( 'invalid', $result['code'] );
		$this->assertSame( array( 'email', 'consent' ), array_keys( $result['errors'] ) );
		$this->assertSame( 'invalid', StockAlert::handle( $this->form( $id, $id, array( 'email' => str_repeat( 'a', 90 ) . '@example.invalid' ) ), $this->ip, 0 )['code'] );

		set_transient( Visitor::key( 'form:' . $this->ip, Submit::WINDOW ), Submit::LIMIT, 60 );
		$this->assertSame( 'limit', StockAlert::handle( $this->form( $id, $id ), $this->ip, 0 )['code'] );

		$this->assertSame( array(), $this->mails );
		$this->assertNull( StockAlertStore::find( $id, 'ada@example.invalid' ) );
	}

	public function test_guest_confirms_by_email_and_can_remove_the_address(): void {
		$this->needs_woocommerce();
		$this->turn();
		$product = $this->simple();
		$id      = $product->get_id();

		$this->assertSame( 'confirm', StockAlert::handle( $this->form( $id, $id ), $this->ip, 0 )['code'] );
		$row = StockAlertStore::find( $id, 'ada@example.invalid' );
		$this->assertNotNull( $row );
		$this->assertSame( StockAlertStore::PENDING, $row['status'] );
		$this->assertCount( 1, $this->mails );
		$this->assertSame( 'ada@example.invalid', $this->mails[0]['to'] );
		$this->assertStringContainsString( $product->get_name(), (string) $this->mails[0]['subject'] );
		$link = StockAlert::link( 'confirm', $row );
		$key  = self::key( $link );
		$this->assertSame( 32, strlen( $key ) );
		$this->assertStringContainsString( 'pergel_stock_key=' . $key, (string) $this->mails[0]['message'] );

		$this->assertSame( 'confirm', StockAlert::handle( $this->form( $id, $id ), $this->ip, 0 )['code'] );
		$this->assertCount( 1, $this->mails, 'One confirmation email an hour.' );

		$this->assertNull( StockAlert::use_link( 'confirm', $row['id'], str_repeat( 'a', 32 ) )['product'] );
		$this->assertNull( StockAlert::use_link( 'remove', $row['id'], $key )['product'], 'A key works for its own action only.' );
		$used = StockAlert::use_link( 'confirm', $row['id'], $key );
		$this->assertSame( 'confirmed', $used['code'] );
		$this->assertSame( $id, $used['product']?->get_id() );
		$this->assertSame( StockAlertStore::ACTIVE, StockAlertStore::get( $row['id'] )['status'] ?? null );
		$this->assertSame( 'confirmed', StockAlert::use_link( 'confirm', $row['id'], $key )['code'], 'A second click is harmless.' );

		$this->assertSame( 'confirm', StockAlert::handle( $this->form( $id, $id ), $this->ip, 0 )['code'], 'A stranger cannot tell the address is signed up.' );
		$this->assertCount( 1, $this->mails );

		$remove = self::key( StockAlert::link( 'remove', $row ) );
		$this->assertSame( 'removed', StockAlert::use_link( 'remove', $row['id'], $remove )['code'] );
		$this->assertNull( StockAlertStore::get( $row['id'] ) );
		$this->assertSame( 'link', StockAlert::use_link( 'remove', $row['id'], $remove )['code'] );
	}

	public function test_old_confirmation_link_stops_working_when_the_alert_starts_over(): void {
		$this->needs_woocommerce();
		$product = $this->simple();
		$row     = StockAlertStore::save( $product->get_id(), 'ada@example.invalid', 0, StockAlertStore::PENDING );
		$this->assertNotNull( $row );
		global $wpdb;
		$wpdb->update( StockAlertStore::table(), array( 'created' => gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ) ), array( 'id' => $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = StockAlertStore::get( $row['id'] );
		$this->assertNotNull( $row );
		$old = self::key( StockAlert::link( 'confirm', $row ) );
		$this->assertSame( 'confirmed', StockAlert::use_link( 'confirm', $row['id'], $old )['code'], 'The key works before the alert starts over.' );
		$wpdb->update( StockAlertStore::table(), array( 'status' => StockAlertStore::PENDING ), array( 'id' => $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->turn();
		$this->assertSame( 'confirm', StockAlert::handle( $this->form( $product->get_id(), $product->get_id() ), $this->ip, 0 )['code'] );
		$this->assertCount( 1, $this->mails, 'After an hour the email goes again.' );
		$this->assertSame( 'link', StockAlert::use_link( 'confirm', $row['id'], $old )['code'] );
	}

	public function test_an_address_gets_a_few_confirmation_emails_a_day(): void {
		$this->needs_woocommerce();
		$this->turn();
		for ( $i = 0; $i <= StockAlert::MAILS_PER_DAY; $i++ ) {
			$id = $this->simple()->get_id();
			$this->assertSame( 'confirm', StockAlert::handle( $this->form( $id, $id ), $this->ip, 0 )['code'] );
		}

		$this->assertCount( StockAlert::MAILS_PER_DAY, $this->mails, 'The answer is the same, the email stops.' );
		$this->assertNull( StockAlertStore::find( $id, 'ada@example.invalid' ) );
	}

	public function test_products_not_for_sale_take_no_alerts(): void {
		$this->needs_woocommerce();
		$this->turn();
		$product = $this->simple();
		$id      = $product->get_id();
		$row     = StockAlertStore::save( $id, 'grace@example.invalid', 0, StockAlertStore::ACTIVE );
		$this->assertNotNull( $row );
		update_post_meta( $id, Modes::POST_META, Modes::CATALOG );
		Modes::flush();

		$this->assertSame( 'unavailable', StockAlert::handle( $this->form( $id, $id ), $this->ip, 0 )['code'], 'The sales modes are not applied in admin-post.php; the alert asks for the mode.' );
		$product->set_stock_status( 'instock' );
		$product->save();
		$this->assertSame( 0, StockAlert::send( $id ) );
		$this->assertSame( array(), $this->mails );
	}

	public function test_an_email_link_opens_a_page_with_a_button(): void {
		$this->needs_woocommerce();
		$product = $this->simple();
		$row     = StockAlertStore::save( $product->get_id(), 'ada@example.invalid', 0, StockAlertStore::PENDING );
		$this->assertNotNull( $row );
		$link = StockAlert::link( 'confirm', $row );
		parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );
		$_GET                      = array_merge( $_GET, $query );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		add_filter(
			'wp_die_handler',
			static fn (): \Closure => static function ( mixed $message, mixed $title, mixed $args ): void {
				throw new \RuntimeException( (string) $args['response'] . ' ' . $message );
			}
		);
		try {
			StockAlert::link_clicked();
			$this->fail( 'The page should be shown.' );
		} catch ( \RuntimeException $page ) {
			$this->assertStringStartsWith( '200 ', $page->getMessage() );
			$this->assertStringContainsString( '<form method="post" action="' . esc_url( $link ) . '">', $page->getMessage() );
			$this->assertStringContainsString( esc_html( $product->get_name() ), $page->getMessage() );
		}
		$this->assertSame( StockAlertStore::PENDING, StockAlertStore::get( $row['id'] )['status'] ?? null, 'Opening the link confirms nothing.' );

		$_GET['pergel_stock_key'] = str_repeat( 'a', 32 );
		try {
			StockAlert::link_clicked();
			$this->fail( 'A wrong key should be turned back.' );
		} catch ( \RuntimeException $page ) {
			$this->assertStringStartsWith( '410 ', $page->getMessage() );
		}
	}

	public function test_customer_with_the_account_address_is_signed_up_at_once(): void {
		$this->needs_woocommerce();
		$this->turn();
		$user          = (int) wp_insert_user(
			array(
				'user_login' => 'pergel_stock_' . wp_rand(),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'ada@example.invalid',
				'role'       => 'customer',
			)
		);
		$this->users[] = $user;
		$product       = $this->variable(
			array(
				'Red'  => true,
				'Blue' => false,
			)
		);
		$blue          = $this->option( $product, 'Blue' )->get_id();

		$this->assertSame( 'subscribed', StockAlert::handle( $this->form( $product->get_id(), $blue ), $this->ip, $user )['code'] );
		$row = StockAlertStore::find( $blue, 'ada@example.invalid' );
		$this->assertSame( StockAlertStore::ACTIVE, $row['status'] ?? null );
		$this->assertSame( $user, $row['user_id'] ?? null );
		$this->assertSame( array(), $this->mails );

		$this->assertSame( 'confirm', StockAlert::handle( $this->form( $product->get_id(), $blue, array( 'email' => 'other@example.invalid' ) ), $this->ip, $user )['code'], 'Another address is confirmed by email.' );
		$this->assertCount( 1, $this->mails );
		$this->assertSame( 'unavailable', StockAlert::handle( $this->form( $product->get_id(), $this->option( $product, 'Red' )->get_id() ), $this->ip, $user )['code'] );
	}

	public function test_back_in_stock_sends_one_email_per_address(): void {
		$this->needs_woocommerce();
		$this->turn();
		add_action( 'woocommerce_product_set_stock_status', array( StockAlert::class, 'stock_status' ), 10, 2 );
		$product = $this->simple();
		$id      = $product->get_id();
		$ada     = StockAlertStore::save( $id, 'ada@example.invalid', 0, StockAlertStore::ACTIVE );
		StockAlertStore::save( $id, 'grace@example.invalid', 0, StockAlertStore::ACTIVE );
		StockAlertStore::save( $id, 'pending@example.invalid', 0, StockAlertStore::PENDING );
		$this->assertNotNull( $ada );

		$this->assertSame( 0, StockAlert::send( $id ), 'Still sold out.' );
		$this->assertFalse( as_has_scheduled_action( StockAlert::SEND_HOOK, array( $id ), 'pergel' ) );

		$product->set_stock_status( 'instock' );
		$product->save();
		$this->assertTrue( as_has_scheduled_action( StockAlert::SEND_HOOK, array( $id ), 'pergel' ) );

		$this->assertSame( 2, StockAlert::send( $id ) );
		$this->assertSame( array( 'ada@example.invalid', 'grace@example.invalid' ), array_column( $this->mails, 'to' ) );
		$this->assertStringContainsString( 'pergel_stock_key=' . self::key( StockAlert::link( 'remove', $ada ) ), (string) $this->mails[0]['message'] );
		$this->assertStringContainsString( (string) wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH ), (string) $this->mails[0]['message'] );
		$this->assertSame( StockAlertStore::SENT, StockAlertStore::get( $ada['id'] )['status'] ?? null );
		$this->assertSame( StockAlertStore::PENDING, StockAlertStore::find( $id, 'pending@example.invalid' )['status'] ?? null );

		$this->assertSame( 0, StockAlert::send( $id ), 'Once per address.' );
		$this->assertCount( 2, $this->mails );

		$this->turn( false );
		StockAlertStore::save( $id, 'late@example.invalid', 0, StockAlertStore::ACTIVE );
		$this->assertSame( 0, StockAlert::send( $id ), 'Nothing goes while the alerts are off.' );
	}

	public function test_option_back_in_stock_queues_its_own_and_the_product_alerts(): void {
		$this->needs_woocommerce();
		$this->turn();
		add_action( 'woocommerce_product_set_stock_status', array( StockAlert::class, 'stock_status' ), 10, 2 );
		add_action( 'woocommerce_variation_set_stock_status', array( StockAlert::class, 'stock_status' ), 10, 2 );
		$product = $this->variable(
			array(
				'Red'  => false,
				'Blue' => false,
			)
		);
		$blue    = $this->option( $product, 'Blue' );
		StockAlertStore::save( $product->get_id(), 'ada@example.invalid', 0, StockAlertStore::ACTIVE );
		StockAlertStore::save( $blue->get_id(), 'grace@example.invalid', 0, StockAlertStore::ACTIVE );

		$blue->set_stock_status( 'instock' );
		$blue->save();

		$this->assertTrue( as_has_scheduled_action( StockAlert::SEND_HOOK, array( $blue->get_id() ), 'pergel' ) );
		$this->assertTrue( as_has_scheduled_action( StockAlert::SEND_HOOK, array( $product->get_id() ), 'pergel' ), 'The product is back once one option is.' );
		$this->assertSame( 1, StockAlert::send( $blue->get_id() ) );
		// WooCommerce brings the product back at the end of the request.
		\WC_Product_Variable::sync( $product->get_id() );
		$this->assertSame( 1, StockAlert::send( $product->get_id() ) );
	}

	public function test_old_rows_are_deleted(): void {
		$this->needs_woocommerce();
		global $wpdb;
		$id     = $this->simple()->get_id();
		$old    = StockAlertStore::save( $id, 'old-pending@example.invalid', 0, StockAlertStore::PENDING );
		$fresh  = StockAlertStore::save( $id, 'new-pending@example.invalid', 0, StockAlertStore::PENDING );
		$sent   = StockAlertStore::save( $id, 'old-sent@example.invalid', 0, StockAlertStore::ACTIVE );
		$active = StockAlertStore::save( $id, 'old-active@example.invalid', 0, StockAlertStore::ACTIVE );
		$this->assertNotNull( $old );
		$this->assertNotNull( $fresh );
		$this->assertNotNull( $sent );
		$this->assertNotNull( $active );
		$long_ago = gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS );
		$table    = StockAlertStore::table();
		$wpdb->update( $table, array( 'created' => $long_ago ), array( 'id' => $old['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, array( 'created' => $long_ago ), array( 'id' => $active['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		StockAlertStore::mark_sent( $sent['id'] );
		$wpdb->update( $table, array( 'sent' => $long_ago ), array( 'id' => $sent['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->assertGreaterThanOrEqual( 2, StockAlertStore::purge() );
		$this->assertNull( StockAlertStore::get( $old['id'] ) );
		$this->assertNull( StockAlertStore::get( $sent['id'] ) );
		$this->assertNotNull( StockAlertStore::get( $fresh['id'] ) );
		$this->assertNotNull( StockAlertStore::get( $active['id'] ), 'An address that waits is kept.' );
	}

	public function test_privacy_tools_export_and_erase_an_address(): void {
		$this->needs_woocommerce();
		$product = $this->simple();
		StockAlertStore::save( $product->get_id(), 'ada@example.invalid', 0, StockAlertStore::ACTIVE );
		StockAlertStore::save( $product->get_id(), 'grace@example.invalid', 0, StockAlertStore::ACTIVE );

		$this->assertArrayHasKey( 'pergel-stock-alerts', StockAlertStore::exporters( array() ) );
		$this->assertArrayHasKey( 'pergel-stock-alerts', StockAlertStore::erasers( array() ) );
		$export = StockAlertStore::export( 'Ada@Example.invalid' );
		$this->assertTrue( $export['done'] );
		$this->assertCount( 1, $export['data'] );
		$this->assertSame( $product->get_name(), $export['data'][0]['data'][0]['value'] );

		$erased = StockAlertStore::erase( 'ada@example.invalid' );
		$this->assertTrue( $erased['items_removed'] );
		$this->assertNull( StockAlertStore::find( $product->get_id(), 'ada@example.invalid' ) );
		$this->assertNotNull( StockAlertStore::find( $product->get_id(), 'grace@example.invalid' ) );
		$this->assertFalse( StockAlertStore::erase( 'ada@example.invalid' )['items_removed'] );

		$user          = (int) wp_insert_user(
			array(
				'user_login' => 'pergel_stock_' . wp_rand(),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'other@example.invalid',
				'role'       => 'customer',
			)
		);
		$this->users[] = $user;
		StockAlertStore::save( $product->get_id(), 'old-address@example.invalid', $user, StockAlertStore::ACTIVE );
		$this->assertCount( 1, StockAlertStore::export( 'other@example.invalid' )['data'], 'The account\'s alerts under an earlier address count.' );
		$this->assertTrue( StockAlertStore::erase( 'other@example.invalid' )['items_removed'] );
		$this->assertNull( StockAlertStore::find( $product->get_id(), 'old-address@example.invalid' ) );
	}

	public function test_admin_list_shows_the_alerts_to_shop_managers(): void {
		$this->needs_woocommerce();
		$product = $this->simple();
		StockAlertStore::save( $product->get_id(), 'ada@example.invalid', 0, StockAlertStore::ACTIVE );
		StockAlertStore::save( $product->get_id(), 'pending@example.invalid', 0, StockAlertStore::PENDING );

		ob_start();
		StockAlertList::render();
		$this->assertSame( '', ob_get_clean(), 'Guests see nothing.' );

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => 'ID',
			)
		);
		wp_set_current_user( (int) $admins[0] );
		ob_start();
		StockAlertList::render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'ada@example.invalid', $html );
		$this->assertStringContainsString( 'pending@example.invalid', $html );
		$this->assertStringContainsString( esc_html( $product->get_name() ), $html );
		$this->assertStringContainsString( esc_html( StockAlertStore::status_label( StockAlertStore::PENDING ) ), $html );
		$this->assertStringContainsString( 'action=' . StockAlertList::DELETE, $html );
		$this->assertStringContainsString( esc_html__( 'Most wanted', 'pergel-engine' ), $html );
	}
}
