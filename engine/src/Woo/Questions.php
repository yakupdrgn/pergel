<?php
/**
 * Questions and answers about a product.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Core\Visitor;

/**
 * Customers who are logged in ask a question about a product; it is a
 * comment of its own type on the product, held for moderation. The shop
 * answers it under Products → Questions (QuestionsAdmin): the answer is a
 * reply to it, and both show on the product page from then on. Layout
 * keeps the type out of every other comment list, so questions never mix
 * with reviews.
 *
 * The form posts to admin-post.php, so it works without JavaScript. Pages
 * for logged-in users are not served from a page cache, so the form can
 * carry a nonce; guests get a link to log in instead.
 */
final class Questions {

	public const ACTION = 'pergel_question';

	/**
	 * Id of the section, for links back to it.
	 */
	public const ANCHOR = 'pergel-questions';

	/**
	 * Query argument with the result after sending.
	 */
	public const RESULT = 'pergel-question';

	/**
	 * Shortest and longest question.
	 */
	public const MIN_LENGTH = 10;

	public const MAX_LENGTH = 500;

	/**
	 * Most questions from one customer in an hour.
	 */
	private const PER_HOUR = 5;

	/**
	 * Most answered questions shown.
	 */
	private const SHOWN = 20;

	/**
	 * The section's title and content for a product.
	 *
	 * @param \WC_Product $product Product.
	 * @return array{0: string, 1: string}
	 */
	public static function section( \WC_Product $product ): array {
		$questions = self::answered( $product->get_id() );
		$count     = count( $questions );
		$items     = '';
		foreach ( $questions as [ $question, $answer ] ) {
			$items .= sprintf(
				'<li class="pergel-question"><p class="pergel-question__text"><span class="pergel-question__mark" aria-hidden="true">%1$s</span><span class="screen-reader-text">%2$s </span>%3$s</p><p class="pergel-question__meta">%4$s</p><div class="pergel-question__answer"><span class="pergel-question__mark" aria-hidden="true">%5$s</span><span class="screen-reader-text">%6$s </span>%7$s</div></li>',
				esc_html_x( 'Q', 'question mark letter', 'pergel-engine' ),
				esc_html__( 'Question:', 'pergel-engine' ),
				esc_html( $question->comment_content ),
				/* translators: 1: name of the customer, 2: date. */
				esc_html( sprintf( __( 'Asked by %1$s on %2$s', 'pergel-engine' ), $question->comment_author, get_comment_date( '', $question ) ) ),
				esc_html_x( 'A', 'answer mark letter', 'pergel-engine' ),
				esc_html__( 'Answer:', 'pergel-engine' ),
				wpautop( esc_html( $answer->comment_content ) )
			);
		}
		$html  = '<div class="pergel-questions" id="' . esc_attr( self::ANCHOR ) . '">' . self::notice();
		$html .= '' === $items
			? '<p class="pergel-questions__none">' . esc_html__( 'No questions yet.', 'pergel-engine' ) . '</p>'
			: '<ul class="pergel-questions__list">' . $items . '</ul>';
		$html .= self::form( $product ) . '</div>';
		/* translators: %s: number of answered questions. */
		$title = $count > 0 ? sprintf( __( 'Questions (%s)', 'pergel-engine' ), number_format_i18n( $count ) ) : __( 'Questions', 'pergel-engine' );
		return array( $title, $html );
	}

	/**
	 * Published questions with their first published answer, newest first.
	 *
	 * @param int $product Product id.
	 * @return list<array{0: \WP_Comment, 1: \WP_Comment}>
	 */
	public static function answered( int $product ): array {
		$questions = get_comments(
			array(
				'post_id' => $product,
				'type'    => Layout::QUESTION,
				'status'  => 'approve',
				'parent'  => 0,
				'number'  => self::SHOWN,
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			)
		);
		$questions = is_array( $questions ) ? array_filter( $questions, static fn ( mixed $comment ): bool => $comment instanceof \WP_Comment ) : array();
		if ( array() === $questions ) {
			return array();
		}
		$answers = get_comments(
			array(
				'type'       => Layout::QUESTION,
				'status'     => 'approve',
				'parent__in' => array_map( static fn ( \WP_Comment $comment ): int => (int) $comment->comment_ID, $questions ),
				'orderby'    => 'comment_date_gmt',
				'order'      => 'ASC',
			)
		);
		$first   = array();
		foreach ( is_array( $answers ) ? $answers : array() as $answer ) {
			if ( $answer instanceof \WP_Comment ) {
				$first[ (int) $answer->comment_parent ] ??= $answer;
			}
		}
		$pairs = array();
		foreach ( $questions as $question ) {
			$answer = $first[ (int) $question->comment_ID ] ?? null;
			if ( null !== $answer ) {
				$pairs[] = array( $question, $answer );
			}
		}
		return $pairs;
	}

	/**
	 * The form for a logged-in customer, else a link to log in.
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function form( \WC_Product $product ): string {
		$back = (string) get_permalink( $product->get_id() );
		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<p class="pergel-questions__login"><a href="%1$s">%2$s</a></p>',
				esc_url( wp_login_url( $back . '#' . self::ANCHOR ) ),
				esc_html__( 'Log in to ask a question', 'pergel-engine' )
			);
		}
		return sprintf(
			'<form class="pergel-questions__form" method="post" action="%1$s"><p><label for="pergel-question-text">%2$s</label><textarea id="pergel-question-text" name="question" rows="3" minlength="%3$d" maxlength="%4$d" required aria-describedby="pergel-question-hint"></textarea></p><p class="pergel-questions__hint" id="pergel-question-hint">%5$s</p><input type="hidden" name="action" value="%6$s"><input type="hidden" name="product" value="%7$d">%8$s<p><button type="submit" class="wp-element-button">%9$s</button></p></form>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_html__( 'Your question', 'pergel-engine' ),
			(int) self::MIN_LENGTH,
			(int) self::MAX_LENGTH,
			esc_html__( 'It shows here once the shop answers it, and you get the answer by email.', 'pergel-engine' ),
			esc_attr( self::ACTION ),
			(int) $product->get_id(),
			wp_nonce_field( self::ACTION . '_' . $product->get_id(), '_wpnonce', false, false ),
			esc_html__( 'Send the question', 'pergel-engine' )
		);
	}

	/**
	 * The result code after sending, from the redirect back; empty otherwise.
	 */
	private static function result(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks a fixed message.
		return isset( $_GET[ self::RESULT ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT ] ) ) : '';
	}

	/**
	 * Whether the page shows the result of sending a question.
	 */
	public static function answering(): bool {
		return '' !== self::result();
	}

	/**
	 * The result of sending, after the redirect back.
	 */
	private static function notice(): string {
		$code = self::result();
		if ( '' === $code ) {
			return '';
		}
		return sprintf( '<p class="pergel-questions__notice%1$s" role="status">%2$s</p>', 'sent' === $code ? ' is-sent' : '', esc_html( self::message( $code ) ) );
	}

	/**
	 * Text for a result code.
	 *
	 * @param string $code Result code.
	 */
	public static function message( string $code ): string {
		return match ( $code ) {
			'sent'      => __( 'Thank you. Your question shows here once the shop answers it.', 'pergel-engine' ),
			/* translators: 1: shortest, 2: longest question in characters. */
			'invalid'   => sprintf( __( 'Write a question of %1$d to %2$d characters.', 'pergel-engine' ), self::MIN_LENGTH, self::MAX_LENGTH ),
			'limit'     => __( 'You have sent several questions already. Try again in an hour.', 'pergel-engine' ),
			'duplicate' => __( 'You have already sent this question.', 'pergel-engine' ),
			'login'     => __( 'Log in to ask a question.', 'pergel-engine' ),
			default     => __( 'The question could not be sent. Reload the page and try again.', 'pergel-engine' ),
		};
	}

	/**
	 * The form sent: stores the question and goes back to the product with
	 * the result. Hooked on admin_post (logged in) and admin_post_nopriv.
	 */
	public static function submit(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- handle() checks the nonce.
		$product = isset( $_POST['product'] ) ? absint( $_POST['product'] ) : 0;
		$back    = $product > 0 ? get_permalink( $product ) : false;
		$back    = is_string( $back ) ? $back : home_url( '/' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- handle() checks the nonce and cleans the text.
		$code = self::handle( $product, isset( $_POST['question'] ) ? wp_unslash( $_POST['question'] ) : '', isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', get_current_user_id() );
		wp_safe_redirect( add_query_arg( self::RESULT, $code, $back ) . '#' . self::ANCHOR, 303 );
		exit;
	}

	/**
	 * Checks and stores one question.
	 *
	 * @param int    $product  Product id.
	 * @param mixed  $question Untrusted text, unslashed.
	 * @param string $nonce    Nonce of the form.
	 * @param int    $user     Logged-in user.
	 * @return string Result code.
	 */
	public static function handle( int $product, mixed $question, string $nonce, int $user ): string {
		$account = $user > 0 ? get_userdata( $user ) : false;
		if ( ! $account instanceof \WP_User ) {
			return 'login';
		}
		if ( false === wp_verify_nonce( $nonce, self::ACTION . '_' . $product ) ) {
			return 'expired';
		}
		$item = wc_get_product( $product );
		if ( ! $item instanceof \WC_Product || 'publish' !== $item->get_status() || ! $item->is_visible() ) {
			return 'expired';
		}
		$text   = is_string( $question ) ? trim( sanitize_textarea_field( $question ) ) : '';
		$length = mb_strlen( $text );
		if ( $length < self::MIN_LENGTH || $length > self::MAX_LENGTH ) {
			return 'invalid';
		}
		if ( ! Visitor::hit( 'question:' . $account->ID, self::PER_HOUR, HOUR_IN_SECONDS ) ) {
			return 'limit';
		}
		$hold = static fn ( mixed $approved, mixed $data ): mixed => is_array( $data ) && Layout::QUESTION === ( $data['comment_type'] ?? '' ) && in_array( $approved, array( 1, '1' ), true ) ? 0 : $approved;
		add_filter( 'pre_comment_approved', $hold, 99, 2 );
		$id   = wp_new_comment(
			array(
				'comment_post_ID'      => $product,
				'comment_content'      => $text,
				'comment_type'         => Layout::QUESTION,
				'comment_parent'       => 0,
				'user_id'              => $account->ID,
				'comment_author'       => $account->display_name,
				'comment_author_email' => $account->user_email,
				'comment_author_url'   => '',
				// As wp_new_comment() would take it, but also without a web request.
				'comment_author_IP'    => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
			true
		);
		remove_filter( 'pre_comment_approved', $hold, 99 );
		if ( $id instanceof \WP_Error ) {
			return 'comment_duplicate' === $id->get_error_code() ? 'duplicate' : 'error';
		}
		return 'sent';
	}
}
