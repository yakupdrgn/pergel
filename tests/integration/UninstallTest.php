<?php
/**
 * Deleting the plugin: what Core\Uninstall removes and what it leaves.
 *
 * The test drops the plugin's tables of the test site and builds them
 * again afterwards, with the search index when it was complete.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Core\Uninstall;
use Pergel\Forms\Inbox;
use Pergel\Search\Index;
use Pergel\Seo\Meta;
use Pergel\Settings\Settings;
use Pergel\Starters\Shop;
use Pergel\Woo\Delivery;
use Pergel\Woo\Lists;
use Pergel\Woo\Modes;
use Pergel\Woo\StockAlert;
use Pergel\Woo\StockAlertStore;
use PHPUnit\Framework\TestCase;

/**
 * Options, tables and content made by the test are put back or removed after it.
 */
final class UninstallTest extends TestCase {

	private const OPTIONS = array( Settings::OPTION, Modes::INDEX, Shop::ATTRIBUTES_OPTION );

	/**
	 * Options as they were before the test (false: not saved).
	 *
	 * @var array<string, mixed>
	 */
	private array $saved = array();

	/**
	 * Whether the search index was complete before the test.
	 */
	private bool $indexed = false;

	/**
	 * Whether the stock alert table was there before the test.
	 */
	private bool $alerts = false;

	/**
	 * Posts, the term and the user made by the test.
	 *
	 * @var array{posts: list<int>, term: int, user: int}
	 */
	private array $made = array(
		'posts' => array(),
		'term'  => 0,
		'user'  => 0,
	);

	protected function setUp(): void {
		foreach ( self::OPTIONS as $option ) {
			$this->saved[ $option ] = get_option( $option, false );
		}
		$this->indexed = Index::ready();
		$this->alerts  = StockAlertStore::installed();
	}

	protected function tearDown(): void {
		foreach ( $this->made['posts'] as $post ) {
			wp_delete_post( $post, true );
		}
		if ( $this->made['term'] > 0 ) {
			wp_delete_term( $this->made['term'], 'category' );
		}
		if ( $this->made['user'] > 0 ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $this->made['user'] );
		}
		foreach ( $this->saved as $option => $value ) {
			if ( false === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}
		Settings::flush();
		if ( $this->alerts ) {
			StockAlertStore::install();
		}
		if ( $this->indexed ) {
			Index::install();
			Index::start();
			while ( ! Index::step() ) {
				// One batch at a time.
			}
			wp_clear_scheduled_hook( Index::BUILD_HOOK );
		}
	}

	public function test_deleting_the_plugin_removes_its_data_and_keeps_the_content(): void {
		global $wpdb;
		$page                = (int) wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Pergel uninstall page',
			)
		);
		$message             = (int) wp_insert_post(
			array(
				'post_type'   => Inbox::TYPE,
				'post_status' => 'private',
				'post_title'  => 'Pergel uninstall message',
			)
		);
		$this->made['posts'] = array( $page, $message );
		$this->made['term']  = (int) wp_insert_term( 'Pergel uninstall ' . wp_rand(), 'category' )['term_id'];
		$this->made['user']  = (int) wp_insert_user(
			array(
				'user_login' => 'pergel_uninstall_' . wp_rand(),
				'user_pass'  => wp_generate_password(),
				'user_email' => 'uninstall-' . wp_rand() . '@example.invalid',
			)
		);
		update_post_meta( $page, Meta::TITLE, 'A title' );
		update_post_meta( $page, Meta::NOINDEX, '1' );
		update_post_meta( $page, 'pergel_uninstall_other', 'stays' );
		update_post_meta( $message, Inbox::META_EMAIL, 'ada@example.invalid' );
		update_term_meta( $this->made['term'], Delivery::META, '2-4' );
		update_user_meta( $this->made['user'], Lists::META, '12,15' );
		update_option( 'woocommerce_' . StockAlert::EMAIL_BACK . '_settings', array( 'enabled' => 'yes' ) );
		set_transient( 'pergel_l_uninstall', 3, 600 );
		wp_schedule_single_event( time() + 600, StockAlert::SEND_HOOK, array( 7 ) );
		StockAlertStore::maintain();

		Uninstall::site();

		$this->assertFalse( get_option( Settings::OPTION, false ), 'Settings are removed (a registered setting reads its default otherwise).' );
		$this->assertFalse( get_option( 'woocommerce_' . StockAlert::EMAIL_BACK . '_settings' ) );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', StockAlertStore::table() ) ), 'The stock alert table is dropped.' );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Index::table() ) ), 'The search index is dropped.' );
		$this->assertFalse( get_option( Index::STATE ) );
		$this->assertFalse( wp_next_scheduled( StockAlert::SEND_HOOK, array( 7 ) ) );
		if ( ! wp_using_ext_object_cache() ) {
			$this->assertFalse( get_transient( 'pergel_l_uninstall' ), 'Limit counters are removed.' );
		}
		$this->assertNull( get_post( $message ), 'Form messages are deleted.' );
		$this->assertSame( '', get_post_meta( $message, Inbox::META_EMAIL, true ) );
		$this->assertSame( '', get_post_meta( $page, Meta::TITLE, true ) );
		$this->assertFalse( metadata_exists( 'post', $page, Meta::NOINDEX ), 'Registered meta reads its default, so the row is checked.' );
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $message ) ), 'Their meta goes with them.' );
		$this->assertSame( '', get_term_meta( $this->made['term'], Delivery::META, true ) );
		$this->assertSame( '', get_user_meta( $this->made['user'], Lists::META, true ) );

		$this->assertInstanceOf( \WP_Post::class, get_post( $page ), 'Pages stay.' );
		$this->assertSame( 'stays', get_post_meta( $page, 'pergel_uninstall_other', true ), 'Other meta stays.' );
		$this->assertInstanceOf( \WP_Term::class, get_term( $this->made['term'], 'category' ) );
	}
}
