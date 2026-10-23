<?php
/**
 * Sections under the product: tabs, extra sections, questions, rating.
 *
 * Needs WooCommerce; skipped without it. Run against a shop site
 * (PERGEL_WP_DIR=…/pergel-shop).
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Integration;

use Pergel\Settings\Schema;
use Pergel\Settings\Settings;
use Pergel\Woo\Details;
use Pergel\Woo\DetailsFields;
use Pergel\Woo\Layout;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\QuestionEmail;
use Pergel\Woo\Questions;
use Pergel\Woo\QuestionsAdmin;
use PHPUnit\Framework\TestCase;

/**
 * Settings, products, users, patterns and the main query are put back
 * after each test.
 */
final class ProductDetailsTest extends TestCase {

	/**
	 * The option as it was before the test (false: not saved).
	 *
	 * @var mixed
	 */
	private mixed $saved = false;

	/**
	 * The main query before go_to() replaced it.
	 */
	private ?\WP_Query $query = null;

	/**
	 * Posts made for the test: products and patterns.
	 *
	 * @var list<int>
	 */
	private array $posts = array();

	/**
	 * Users made for the test.
	 *
	 * @var list<int>
	 */
	private array $users = array();

	/**
	 * Emails sent during the test: subject => recipient.
	 *
	 * @var array<string, string>
	 */
	private array $mails = array();

	protected function setUp(): void {
		// Saved first: tearDown() runs after a skip too.
		$this->saved = get_option( Settings::OPTION, false );
		if ( ! Woo::active() ) {
			$this->markTestSkipped( 'WooCommerce is not active on this site.' );
		}
		Settings::update( array( 'shop' => Schema::defaults()['shop'] ) );
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		delete_option( Settings::OPTION );
		if ( false !== $this->saved ) {
			// Restored as stored, without running it through the schema again.
			remove_all_filters( 'sanitize_option_' . Settings::OPTION );
			add_option( Settings::OPTION, $this->saved, '', true );
			add_filter( 'sanitize_option_' . Settings::OPTION, array( \Pergel\Settings\Module::class, 'sanitize' ) );
		}
		Settings::flush();
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->users as $id ) {
			wp_delete_user( $id );
		}
		wp_set_current_user( 0 );
		remove_filter( 'render_block_data', array( Details::class, 'sections' ), 10 );
		remove_filter( 'render_block_woocommerce/product-details', array( Details::class, 'tabs' ), 10 );
		remove_filter( 'render_block_woocommerce/product-reviews', array( Details::class, 'rating' ), 10 );
		wp_dequeue_style( Layout::DETAILS_STYLE );
		wp_dequeue_script_module( Layout::DETAILS_SCRIPT );
		Layout::flush();
		if ( null !== $this->query ) {
			$GLOBALS['wp_the_query'] = $this->query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the query go_to() replaced.
		}
		wp_reset_query();
	}

	/**
	 * Keeps emails from leaving; notes them instead.
	 *
	 * @param mixed                $result Short-circuit value.
	 * @param array<string, mixed> $mail   Mail arguments.
	 */
	public function catch_mail( mixed $result, array $mail ): bool {
		$this->mails[ (string) $mail['subject'] ] = is_array( $mail['to'] ) ? implode( ',', $mail['to'] ) : (string) $mail['to'];
		return true;
	}

	/**
	 * One accordion section as the theme's details pattern writes it.
	 *
	 * @param string $title Title.
	 * @param string $inner Block markup of its content.
	 * @param bool   $open  Open by default.
	 */
	private static function section( string $title, string $inner, bool $open = false ): string {
		return '<!-- wp:accordion-item' . ( $open ? ' {"openByDefault":true}' : '' ) . ' --><div class="wp-block-accordion-item' . ( $open ? ' is-open' : '' ) . '">'
			. '<!-- wp:accordion-heading {"level":2} --><h2 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">' . $title . '</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h2><!-- /wp:accordion-heading -->'
			. "\n\n" . '<!-- wp:accordion-panel --><div role="region" class="wp-block-accordion-panel">' . $inner . '</div><!-- /wp:accordion-panel --></div><!-- /wp:accordion-item -->';
	}

	/**
	 * The theme's details block, shortened: description, specifications,
	 * reviews; each section also holds a paragraph, so none is empty.
	 */
	private static function template(): string {
		return '<!-- wp:woocommerce/product-details {"align":"wide"} --><div class="wp-block-woocommerce-product-details alignwide"><!-- wp:accordion {"headingLevel":2} --><div role="group" class="wp-block-accordion">'
			. self::section( 'Description', '<!-- wp:paragraph --><p>About it</p><!-- /wp:paragraph -->', true )
			. "\n\n" . self::section( 'Specifications', '<!-- wp:paragraph --><p>Table</p><!-- /wp:paragraph --><!-- wp:woocommerce/product-specifications /-->' )
			. "\n\n" . self::section( 'Reviews', '<!-- wp:paragraph --><p>What people say</p><!-- /wp:paragraph --><!-- wp:woocommerce/product-reviews --><div class="wp-block-woocommerce-product-reviews"></div><!-- /wp:woocommerce/product-reviews -->' )
			. '</div><!-- /wp:accordion --></div><!-- /wp:woocommerce/product-details -->';
	}

	/**
	 * Makes a product.
	 */
	private function product(): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Details product ' . wp_generate_password( 4, false ) );
		$product->set_status( 'publish' );
		$product->set_regular_price( '20' );
		$product->set_weight( '2' );
		$id            = $product->save();
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * Makes a published pattern.
	 *
	 * @param string $text Its paragraph.
	 */
	private function pattern( string $text ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_title'   => 'Details pattern',
				'post_content' => '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->',
			)
		);
		$this->assertIsInt( $id );
		$this->posts[] = $id;
		return $id;
	}

	/**
	 * Makes a user.
	 *
	 * @param string $role Role.
	 */
	private function user( string $role ): int {
		$id = wp_insert_user(
			array(
				'user_login'   => 'details' . strtolower( wp_generate_password( 6, false ) ),
				'user_email'   => 'details' . strtolower( wp_generate_password( 6, false ) ) . '@example.com',
				'user_pass'    => wp_generate_password(),
				'display_name' => 'Ayşe ' . $role,
				'role'         => $role,
			)
		);
		$this->assertIsInt( $id );
		$this->users[] = $id;
		return $id;
	}

	/**
	 * Shows a product page, as a page view would, and runs Layout's check.
	 *
	 * @param int $id Product id.
	 */
	private function go_to( int $id ): void {
		$this->query           ??= $GLOBALS['wp_the_query'];
		$GLOBALS['wp_the_query'] = new \WP_Query( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test page view; put back in tearDown.
			array(
				'p'         => $id,
				'post_type' => 'product',
			)
		);
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
		$GLOBALS['post']         = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
		Layout::flush();
		Layout::product_page();
	}

	/**
	 * Titles of the top level sections in rendered HTML.
	 *
	 * @param string $html Rendered details block.
	 * @return list<string>
	 */
	private static function titles( string $html ): array {
		return array_map( static fn ( array $section ): string => $section['title'], Details::headings( $html ) );
	}

	public function test_defaults_leave_the_sections_alone(): void {
		$id = $this->product();
		$this->go_to( $id );

		$this->assertFalse( has_filter( 'render_block_data', array( Details::class, 'sections' ) ) );
		$this->assertFalse( wp_style_is( Layout::DETAILS_STYLE ) );
		$html = do_blocks( self::template() );
		$this->assertSame( array( 'Description', 'Specifications', 'Reviews' ), self::titles( $html ) );
		$this->assertStringNotContainsString( 'pergel-details', $html );
	}

	public function test_a_product_with_its_own_tab_loads_them(): void {
		$id = $this->product();
		update_post_meta(
			$id,
			Layout::DETAILS_META,
			array(
				'tab_name' => 'Warranty',
				'tab'      => 'Two years.',
			)
		);
		$this->go_to( $id );

		$this->assertNotFalse( has_filter( 'render_block_data', array( Details::class, 'sections' ) ) );
		$html = do_blocks( self::template() );
		$this->assertSame( array( 'Description', 'Specifications', 'Warranty', 'Reviews' ), self::titles( $html ) );
		$this->assertStringContainsString( '<p>Two years.</p>', $html );
		$this->assertStringNotContainsString( 'pergel-details-tabs', $html, 'The template keeps its accordion.' );
	}

	public function test_sections_in_order_and_as_tabs(): void {
		$id      = $this->product();
		$pattern = $this->pattern( 'Shipped in a day.' );
		update_post_meta(
			$id,
			Layout::DETAILS_META,
			array(
				'features' => array( 'Light', 'Strong' ),
				'tab_name' => 'Warranty',
				'tab'      => 'Two years.',
			)
		);
		Settings::update(
			array(
				'shop' => array(
					'details_style'    => 'tabs',
					'details_phone'    => 'scroll',
					'details_features' => true,
					'details_title'    => 'Shipping',
					'details_tab'      => $pattern,
					'details_qa'       => true,
				),
			)
		);
		$this->go_to( $id );

		$html = do_blocks( self::template() );

		$this->assertSame( array( 'Description', __( 'Specifications', 'pergel-engine' ), __( 'Additional information', 'pergel-engine' ), 'Warranty', 'Shipping', __( 'Questions', 'pergel-engine' ), 'Reviews' ), self::titles( $html ) );
		$this->assertStringContainsString( '<ul class="pergel-features"><li>Light</li><li>Strong</li></ul>', $html );
		$this->assertStringContainsString( 'Shipped in a day.', $html );
		$this->assertStringContainsString( 'id="' . Questions::ANCHOR . '"', $html );
		$this->assertMatchesRegularExpression( '#<div [^>]*class="[^"]*pergel-details is-tabs is-phone-scroll#', $html );
		$this->assertSame( 7, substr_count( $html, 'role="tab"' ) );
		$this->assertMatchesRegularExpression( '#<button type="button" role="tab" class="pergel-details-tab" id="([^"]+)-tab" aria-controls="\1-panel" aria-selected="true" tabindex="0" data-pergel-toggle="\1">Description</button>#', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-selected="true"' ), 'One tab open.' );
		$this->assertStringContainsString( '&quot;autoclose&quot;: true', $html, 'One section open at a time.' );
		$this->assertTrue( wp_style_is( Layout::DETAILS_STYLE ) );
	}

	public function test_all_open(): void {
		$id = $this->product();
		Settings::update( array( 'shop' => array( 'details_style' => 'open' ) ) );
		$this->go_to( $id );

		$html = do_blocks( self::template() );

		$open = array_filter( Details::headings( $html ), static fn ( array $section ): bool => $section['open'] );
		$this->assertCount( 3, $open );
		$this->assertStringNotContainsString( 'pergel-details-tabs', $html );
	}

	public function test_a_category_chooses_tabs_for_its_products(): void {
		$id   = $this->product();
		$term = wp_insert_term( 'Details tabs ' . wp_generate_password( 4, false ), 'product_cat' );
		$this->assertIsArray( $term );
		wp_set_object_terms( $id, array( (int) $term['term_id'] ), 'product_cat' );
		update_term_meta( (int) $term['term_id'], Layout::TERM_META, array( 'shop.details_style' => 'tabs' ) );
		$this->go_to( $id );

		$html = do_blocks( self::template() );
		wp_delete_term( (int) $term['term_id'], 'product_cat' );

		$this->assertStringContainsString( 'pergel-details-tabs', $html );
	}

	public function test_headings_reads_the_top_level_only(): void {
		$nested = '<div class="wp-block-accordion">'
			. '<div class="wp-block-accordion-item"><h2><button class="wp-block-accordion-heading__toggle" id="a" aria-controls="a-panel" aria-expanded="true"><span class="wp-block-accordion-heading__toggle-title">One &amp; <em>two</em></span><span aria-hidden="true">+</span></button></h2>'
			. '<div id="a-panel"><div class="wp-block-accordion"><div class="wp-block-accordion-item"><h3><button class="wp-block-accordion-heading__toggle" id="b" aria-controls="b-panel" aria-expanded="false"><span class="wp-block-accordion-heading__toggle-title">Inner</span></button></h3><div id="b-panel"></div></div></div></div></div>'
			. '<div class="wp-block-accordion-item"><h2><button class="wp-block-accordion-heading__toggle" id="c" aria-controls="c-panel" aria-expanded="false"><span class="wp-block-accordion-heading__toggle-title">C++</span></button></h2><div id="c-panel"></div></div>'
			. '</div>';

		$this->assertSame(
			array(
				array(
					'id'    => 'a',
					'panel' => 'a-panel',
					'title' => 'One & two',
					'open'  => true,
				),
				array(
					'id'    => 'c',
					'panel' => 'c-panel',
					'title' => 'C++',
					'open'  => false,
				),
			),
			Details::headings( $nested )
		);
	}

	public function test_questions_are_held_answered_and_shown(): void {
		$id       = $this->product();
		$customer = $this->user( 'customer' );
		$manager  = $this->user( 'shop_manager' );
		Settings::update( array( 'shop' => array( 'details_qa' => true ) ) );
		wp_set_current_user( $customer );
		$nonce = wp_create_nonce( Questions::ACTION . '_' . $id );

		$this->assertSame( 'invalid', Questions::handle( $id, 'Short?', $nonce, $customer ) );
		$this->assertSame( 'expired', Questions::handle( $id, 'Does it come with a case?', 'bad', $customer ) );
		$this->assertSame( 'login', Questions::handle( $id, 'Does it come with a case?', $nonce, 0 ) );
		$this->assertSame( 'sent', Questions::handle( $id, 'Does it come with a <b>case</b>?', $nonce, $customer ) );

		$held = get_comments(
			array(
				'post_id' => $id,
				'type'    => Layout::QUESTION,
				'status'  => 'hold',
			)
		);
		$this->assertIsArray( $held );
		$this->assertCount( 1, $held );
		$question = $held[0];
		$this->assertInstanceOf( \WP_Comment::class, $question );
		$this->assertSame( 'Does it come with a case?', $question->comment_content );
		$this->assertSame( array(), Questions::answered( $id ), 'Not shown before the answer.' );
		$this->assertSame( 1, QuestionsAdmin::waiting() );

		$this->assertSame( 'empty', QuestionsAdmin::save( (int) $question->comment_ID, '  ', $manager ) );
		$this->assertSame( 'answered', QuestionsAdmin::save( (int) $question->comment_ID, 'Yes, a hard case.', $manager ) );
		$this->assertSame( 'updated', QuestionsAdmin::save( (int) $question->comment_ID, 'Yes, a hard case and a strap.', $manager ) );

		$pairs = Questions::answered( $id );
		$this->assertCount( 1, $pairs );
		$this->assertSame( 'Yes, a hard case and a strap.', $pairs[0][1]->comment_content );
		$this->assertSame( 0, QuestionsAdmin::waiting() );
		$product = wc_get_product( $id );
		$this->assertInstanceOf( \WC_Product::class, $product );
		[ $title, $html ] = Questions::section( $product );
		/* translators: %s: number of answered questions. */
		$this->assertSame( sprintf( __( 'Questions (%s)', 'pergel-engine' ), '1' ), $title );
		$this->assertStringContainsString( 'Does it come with a case?', $html );
		$this->assertStringContainsString( 'Ayşe customer', $html );
		$this->assertStringContainsString( 'name="question"', $html, 'The form for a logged-in customer.' );
		wp_set_current_user( 0 );
		[ , $guest ] = Questions::section( $product );
		$this->assertStringNotContainsString( 'name="question"', $guest );
		$this->assertStringContainsString( 'pergel-questions__login', $guest );

		$customer_email = get_userdata( $customer )->user_email ?? '';
		$sent           = array_keys( $this->mails, $customer_email, true );
		$this->assertCount( 1, $sent, 'One email to the customer, for the first answer.' );
		$this->assertStringContainsString( wp_strip_all_tags( $product->get_name() ), (string) $sent[0] );
	}

	public function test_questions_stay_out_of_reviews(): void {
		$id       = $this->product();
		$question = wp_insert_comment(
			array(
				'comment_post_ID'  => $id,
				'comment_type'     => Layout::QUESTION,
				'comment_approved' => 1,
				'comment_content'  => 'A question',
			)
		);
		$review   = wp_insert_comment(
			array(
				'comment_post_ID'  => $id,
				'comment_type'     => 'review',
				'comment_approved' => 1,
				'comment_content'  => 'A review',
			)
		);

		$all  = get_comments(
			array(
				'post_id' => $id,
				'fields'  => 'ids',
			)
		);
		$asks = get_comments(
			array(
				'post_id' => $id,
				'type'    => Layout::QUESTION,
				'fields'  => 'ids',
			)
		);

		$this->assertSame( array( $review ), array_map( 'intval', (array) $all ) );
		$this->assertSame( array( $question ), array_map( 'intval', (array) $asks ) );
	}

	public function test_rating_summary_above_the_reviews(): void {
		$id = $this->product();
		foreach ( array( 5, 5, 3 ) as $rating ) {
			$comment = wp_insert_comment(
				array(
					'comment_post_ID'  => $id,
					'comment_type'     => 'review',
					'comment_approved' => 1,
					'comment_content'  => 'Rated ' . $rating,
				)
			);
			update_comment_meta( (int) $comment, 'rating', $rating );
		}
		\WC_Comments::clear_transients( $id );
		Settings::update( array( 'shop' => array( 'details_rating' => true ) ) );
		$this->go_to( $id );

		$html = do_blocks( self::template() );

		$this->assertMatchesRegularExpression( '#<div class="pergel-rating-summary"><p class="pergel-rating-summary__average"><span class="pergel-rating-summary__number">4[.,]3</span>#', $html );
		/* translators: %s: number of reviews. */
		$this->assertStringContainsString( sprintf( _n( 'Based on %s review', 'Based on %s reviews', 3, 'pergel-engine' ), '3' ), $html );
		/* translators: %d: number of stars, 1 to 5. */
		$this->assertStringContainsString( sprintf( _n( '%d star', '%d stars', 5, 'pergel-engine' ), 5 ) . '</span><span class="pergel-rating-summary__bar" style="--pergel-share:67%"', $html );
		/* translators: %d: number of stars, 1 to 5. */
		$this->assertStringContainsString( sprintf( _n( '%d star', '%d stars', 1, 'pergel-engine' ), 1 ) . '</span><span class="pergel-rating-summary__bar" style="--pergel-share:0%"', $html );
	}

	public function test_own_values_are_cleaned(): void {
		$this->assertSame(
			array(
				'features' => array( 'One', 'Two' ),
				'tab_name' => 'Warranty',
				'tab'      => '<p>Two years</p>alert(1)',
			),
			DetailsFields::clean(
				array(
					'features' => "  One \n\n<b>Two</b>\r\n",
					'tab_name' => ' <i>Warranty</i> ',
					'tab'      => '<p>Two years</p><script>alert(1)</script>',
				)
			)
		);
		$this->assertSame( DetailsFields::FEATURES, count( DetailsFields::clean( array( 'features' => str_repeat( "x\n", 50 ) ) )['features'] ) );
		$this->assertSame(
			array(
				'features' => array(),
				'tab_name' => '',
				'tab'      => '',
			),
			DetailsFields::clean( 'nope' )
		);
	}

	public function test_email_preview_has_a_sample(): void {
		$email = new QuestionEmail();
		\Pergel\Woo\StockEmail::preview( $email );

		$this->assertStringContainsString( __( 'Sample product', 'pergel-engine' ), $email->get_content_plain() );
	}
}
