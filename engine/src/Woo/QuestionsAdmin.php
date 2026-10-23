<?php
/**
 * Products → Questions.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * The list of product questions (Questions) under Products, with the
 * number waiting in the menu. Answering a question publishes it with its
 * answer and emails the customer, the first time; a published question
 * can be hidden again, or deleted with its answer. Everyone who can
 * moderate comments can use it. Layout loads this class in the admin while
 * the setting is on.
 */
final class QuestionsAdmin {

	public const PAGE = 'pergel-questions';

	/**
	 * Admin-post actions.
	 */
	private const ANSWER = 'pergel_question_answer';

	private const CHANGE = 'pergel_question_change';

	/**
	 * Questions on one page of the list.
	 */
	private const PER_PAGE = 20;

	/**
	 * Adds the admin hooks.
	 */
	public static function register(): void {
		add_action( 'admin_post_' . Questions::ACTION, array( Questions::class, 'submit' ) );
		add_action( 'admin_post_nopriv_' . Questions::ACTION, array( Questions::class, 'submit' ) );
		add_action( 'admin_post_' . self::ANSWER, array( self::class, 'answer' ) );
		add_action( 'admin_post_' . self::CHANGE, array( self::class, 'change' ) );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_filter( 'woocommerce_email_classes', array( self::class, 'emails' ) );
		add_filter( 'woocommerce_prepare_email_for_preview', array( StockEmail::class, 'preview' ) );
	}

	/**
	 * Filter for woocommerce_email_classes: the question answered email.
	 *
	 * @param mixed $emails Email objects by class key.
	 * @return mixed
	 */
	public static function emails( mixed $emails ): mixed {
		if ( is_array( $emails ) ) {
			$emails[ QuestionEmail::KEY ] = new QuestionEmail();
		}
		return $emails;
	}

	/**
	 * Questions waiting for an answer.
	 */
	public static function waiting(): int {
		return (int) get_comments(
			array(
				'type'   => Layout::QUESTION,
				'status' => 'hold',
				'parent' => 0,
				'count'  => true,
			)
		);
	}

	/**
	 * The page under Products, with the number waiting.
	 */
	public static function menu(): void {
		$waiting = self::waiting();
		$bubble  = $waiting > 0 ? sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%2$s</span></span>', $waiting, number_format_i18n( $waiting ) ) : '';
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'Questions', 'pergel-engine' ),
			esc_html__( 'Questions', 'pergel-engine' ) . $bubble,
			'moderate_comments',
			self::PAGE,
			array( self::class, 'page' )
		);
	}

	/**
	 * The list.
	 */
	public static function page(): void {
		if ( ! current_user_can( 'moderate_comments' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filters and paging of a list; nothing changes.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'hold';
		$status = in_array( $status, array( 'hold', 'approve', 'all' ), true ) ? $status : 'hold';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$done   = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$query     = array(
			'type'    => Layout::QUESTION,
			'status'  => $status,
			'parent'  => 0,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
		);
		$total     = (int) get_comments( $query + array( 'count' => true ) );
		$questions = get_comments(
			$query + array(
				'number' => self::PER_PAGE,
				'offset' => ( $paged - 1 ) * self::PER_PAGE,
			)
		);
		$questions = is_array( $questions ) ? array_values( array_filter( $questions, static fn ( mixed $comment ): bool => $comment instanceof \WP_Comment ) ) : array();
		$answers   = self::answers( $questions );

		echo '<div class="wrap"><h1>' . esc_html__( 'Questions', 'pergel-engine' ) . '</h1>';
		$notes = array(
			'answered' => __( 'The answer is published and the customer was emailed.', 'pergel-engine' ),
			'updated'  => __( 'The answer is updated.', 'pergel-engine' ),
			'hidden'   => __( 'The question is hidden from the product page.', 'pergel-engine' ),
			'deleted'  => __( 'The question is in the trash.', 'pergel-engine' ),
			'empty'    => __( 'Write an answer first.', 'pergel-engine' ),
		);
		if ( isset( $notes[ $done ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', 'empty' === $done ? 'warning' : 'success', esc_html( $notes[ $done ] ) );
		}
		echo '<ul class="subsubsub">';
		$views = array(
			'hold'    => __( 'Waiting', 'pergel-engine' ),
			'approve' => __( 'Answered', 'pergel-engine' ),
			'all'     => __( 'All', 'pergel-engine' ),
		);
		$links = array();
		foreach ( $views as $key => $label ) {
			$links[] = sprintf(
				'<li><a href="%1$s"%2$s>%3$s</a></li>',
				esc_url( self::url( array( 'status' => $key ) ) ),
				$key === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo implode( ' | ', $links ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th scope="col" style="width:35%">' . esc_html__( 'Question', 'pergel-engine' ) . '</th><th scope="col" style="width:20%">' . esc_html__( 'Product', 'pergel-engine' ) . '</th><th scope="col">' . esc_html__( 'Answer', 'pergel-engine' ) . '</th></tr></thead><tbody>';
		if ( array() === $questions ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No questions here.', 'pergel-engine' ) . '</td></tr>';
		}
		foreach ( $questions as $question ) {
			self::row( $question, $answers[ (int) $question->comment_ID ] ?? null );
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages > 1 ) {
			echo '<div class="tablenav bottom"><div class="tablenav-pages">';
			echo wp_kses_post(
				(string) paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%', self::url( array( 'status' => $status ) ) ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			);
			echo '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * One question: its text and asker, the product, and the answer form.
	 *
	 * @param \WP_Comment      $question The question.
	 * @param \WP_Comment|null $answer   Its answer, if any.
	 */
	private static function row( \WP_Comment $question, ?\WP_Comment $answer ): void {
		$id      = (int) $question->comment_ID;
		$product = (int) $question->comment_post_ID;
		$field   = 'pergel-answer-' . $id;
		$actions = array();
		if ( '1' === $question->comment_approved ) {
			$actions[] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::change_url( $id, 'hide' ) ), esc_html__( 'Hide', 'pergel-engine' ) );
		}
		$actions[] = sprintf( '<a class="submitdelete" href="%1$s">%2$s</a>', esc_url( self::change_url( $id, 'delete' ) ), esc_html__( 'Delete', 'pergel-engine' ) );
		printf(
			'<tr><td><p>%1$s</p><p class="description">%2$s</p><div class="row-actions visible">%3$s</div></td><td><a href="%4$s">%5$s</a>%6$s</td><td>',
			esc_html( $question->comment_content ),
			/* translators: 1: customer name, 2: email address, 3: date. */
			esc_html( sprintf( __( '%1$s (%2$s), %3$s', 'pergel-engine' ), $question->comment_author, $question->comment_author_email, get_comment_date( '', $question ) ) ),
			implode( ' | ', $actions ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_url( (string) get_permalink( $product ) ),
			esc_html( get_the_title( $product ) ),
			'1' === $question->comment_approved ? '<p><span class="dashicons dashicons-yes" aria-hidden="true"></span> ' . esc_html__( 'On the product page', 'pergel-engine' ) . '</p>' : ''
		);
		printf(
			'<form method="post" action="%1$s"><label class="screen-reader-text" for="%2$s">%3$s</label><textarea class="widefat" rows="3" id="%2$s" name="answer">%4$s</textarea><input type="hidden" name="action" value="%5$s"><input type="hidden" name="question" value="%6$d">%7$s<p><button type="submit" class="button button-primary">%8$s</button></p></form></td></tr>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $field ),
			esc_html__( 'Answer', 'pergel-engine' ),
			null === $answer ? '' : esc_textarea( $answer->comment_content ),
			esc_attr( self::ANSWER ),
			(int) $id,
			wp_nonce_field( self::ANSWER . '_' . $id, '_wpnonce', true, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress's own hidden fields.
			null === $answer ? esc_html__( 'Answer and publish', 'pergel-engine' ) : esc_html__( 'Update the answer', 'pergel-engine' )
		);
	}

	/**
	 * The first answer of each question, of any status.
	 *
	 * @param array $questions Questions.
	 * @phpstan-param list<\WP_Comment> $questions
	 * @return array<int, \WP_Comment> By question id.
	 */
	private static function answers( array $questions ): array {
		if ( array() === $questions ) {
			return array();
		}
		$answers = get_comments(
			array(
				'type'       => Layout::QUESTION,
				'status'     => 'all',
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
		return $first;
	}

	/**
	 * A question, if the id is one.
	 *
	 * @param int $id Comment id.
	 */
	private static function question( int $id ): ?\WP_Comment {
		$comment = $id > 0 ? get_comment( $id ) : null;
		return $comment instanceof \WP_Comment && Layout::QUESTION === $comment->comment_type && 0 === (int) $comment->comment_parent ? $comment : null;
	}

	/**
	 * The answer form sent. Hooked on admin_post.
	 */
	public static function answer(): void {
		$id = isset( $_POST['question'] ) ? absint( $_POST['question'] ) : 0;
		check_admin_referer( self::ANSWER . '_' . $id );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- save() cleans the text.
		$done = self::save( $id, isset( $_POST['answer'] ) ? wp_unslash( $_POST['answer'] ) : '', get_current_user_id() );
		wp_safe_redirect( self::url( array( 'done' => $done ) ) );
		exit;
	}

	/**
	 * Stores an answer and publishes the question; emails the customer the
	 * first time.
	 *
	 * @param int   $id   Question id.
	 * @param mixed $text Untrusted answer, unslashed.
	 * @param int   $user The answering user.
	 * @return string Result code.
	 */
	public static function save( int $id, mixed $text, int $user ): string {
		$question = self::question( $id );
		$account  = get_userdata( $user );
		if ( null === $question || ! $account instanceof \WP_User || ! user_can( $account, 'moderate_comments' ) ) {
			wp_die( esc_html__( 'You cannot answer this question.', 'pergel-engine' ), '', array( 'response' => 403 ) );
		}
		$text = is_string( $text ) ? trim( sanitize_textarea_field( $text ) ) : '';
		if ( '' === $text ) {
			return 'empty';
		}
		$answer = self::answers( array( $question ) )[ $id ] ?? null;
		if ( null !== $answer ) {
			wp_update_comment(
				array(
					'comment_ID'       => (int) $answer->comment_ID,
					'comment_content'  => $text,
					'comment_approved' => 1,
				)
			);
		} else {
			wp_insert_comment(
				array(
					'comment_post_ID'      => (int) $question->comment_post_ID,
					'comment_parent'       => $id,
					'comment_type'         => Layout::QUESTION,
					'comment_content'      => $text,
					'comment_approved'     => 1,
					'user_id'              => $account->ID,
					'comment_author'       => $account->display_name,
					'comment_author_email' => $account->user_email,
				)
			);
		}
		wp_set_comment_status( $id, 'approve' );
		if ( null !== $answer ) {
			return 'updated';
		}
		$stored  = self::answers( array( $question ) )[ $id ] ?? null;
		$product = wc_get_product( (int) $question->comment_post_ID );
		// WooCommerce's copy carries the settings; it is missing when the
		// mailer was set up before the filter that adds it.
		$email = WC()->mailer()->get_emails()[ QuestionEmail::KEY ] ?? null;
		$email = $email instanceof QuestionEmail ? $email : new QuestionEmail();
		if ( null !== $stored && $product instanceof \WC_Product ) {
			$email->answered( $question, $stored, $product );
		}
		return 'answered';
	}

	/**
	 * Hide or delete, from a row link. Hooked on admin_post.
	 */
	public static function change(): void {
		$id   = isset( $_GET['question'] ) ? absint( $_GET['question'] ) : 0;
		$what = isset( $_GET['what'] ) ? sanitize_key( wp_unslash( $_GET['what'] ) ) : '';
		check_admin_referer( self::CHANGE . '_' . $what . '_' . $id );
		$question = self::question( $id );
		if ( null === $question || ! current_user_can( 'moderate_comments' ) ) {
			wp_die( esc_html__( 'You cannot change this question.', 'pergel-engine' ), '', array( 'response' => 403 ) );
		}
		$done = '';
		if ( 'hide' === $what ) {
			wp_set_comment_status( $id, 'hold' );
			$done = 'hidden';
		} elseif ( 'delete' === $what ) {
			foreach ( self::answers( array( $question ) ) as $answer ) {
				wp_trash_comment( (int) $answer->comment_ID );
			}
			wp_trash_comment( $id );
			$done = 'deleted';
		}
		wp_safe_redirect( self::url( array( 'done' => $done ) ) );
		exit;
	}

	/**
	 * Address of the list.
	 *
	 * @param array<string, string> $args Query arguments.
	 */
	private static function url( array $args = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => 'product',
					'page'      => self::PAGE,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Signed link for hide or delete.
	 *
	 * @param int    $id   Question id.
	 * @param string $what "hide" or "delete".
	 */
	private static function change_url( int $id, string $what ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => self::CHANGE,
					'question' => $id,
					'what'     => $what,
				),
				admin_url( 'admin-post.php' )
			),
			self::CHANGE . '_' . $what . '_' . $id
		);
	}
}
