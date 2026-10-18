<?php
/**
 * Stored form messages.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Forms;

use Pergel\Settings\Screen;

/**
 * A hidden post type under the Pergel menu keeps every message. Only
 * administrators see it; nobody can create or edit an entry by hand, only
 * read and delete it. The messages take part in the personal data export
 * and erasure tools by the sender's email address.
 */
final class Inbox {

	public const TYPE = 'pergel_submission';

	public const META_KIND   = '_pergel_kind';
	public const META_FIELDS = '_pergel_fields';
	public const META_EMAIL  = '_pergel_email';
	public const META_PAGE   = '_pergel_page';
	public const META_MAILED = '_pergel_mailed';

	/**
	 * Where the message was passed on: label and result by service id.
	 */
	public const META_HANDOFF = '_pergel_handoff';

	/**
	 * Entries per export or erasure batch.
	 */
	private const BATCH = 50;

	/**
	 * Registers the post type. Hooked on init.
	 */
	public static function register_type(): void {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Form messages', 'pergel-engine' ),
					'singular_name'      => __( 'Form message', 'pergel-engine' ),
					'menu_name'          => __( 'Form messages', 'pergel-engine' ),
					'all_items'          => __( 'Form messages', 'pergel-engine' ),
					'edit_item'          => __( 'Form message', 'pergel-engine' ),
					'view_item'          => __( 'View message', 'pergel-engine' ),
					'search_items'       => __( 'Search messages', 'pergel-engine' ),
					'not_found'          => __( 'No messages yet.', 'pergel-engine' ),
					'not_found_in_trash' => __( 'No messages in the trash.', 'pergel-engine' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => Screen::SLUG,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'delete_with_user'    => false,
				'supports'            => false,
				'map_meta_cap'        => false,
				'capabilities'        => array(
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'delete_posts'       => 'manage_options',
					'read_private_posts' => 'manage_options',
					'publish_posts'      => 'do_not_allow',
					'create_posts'       => 'do_not_allow',
				),
			)
		);
	}

	/**
	 * Keeps a message.
	 *
	 * @param string                $kind   contact or quote.
	 * @param array<string, string> $values Clean field values.
	 * @param int                   $page   Page the form was on.
	 * @return int Entry id, 0 when it could not be stored.
	 */
	public static function store( string $kind, array $values, int $page ): int {
		if ( ! post_type_exists( self::TYPE ) ) {
			self::register_type();
		}
		$id = wp_insert_post(
			array(
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_title'  => $values['name'] ?? '',
				'post_author' => 0,
				'meta_input'  => array(
					self::META_KIND   => $kind,
					self::META_FIELDS => $values,
					self::META_EMAIL  => strtolower( $values['email'] ?? '' ),
					self::META_PAGE   => $page,
				),
			),
			true
		);
		return is_int( $id ) ? $id : 0;
	}

	/**
	 * Admin hooks.
	 */
	public static function register_admin(): void {
		add_filter( 'manage_' . self::TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . self::TYPE . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( self::class, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . self::TYPE, array( self::class, 'bulk_actions' ) );
		add_action( 'add_meta_boxes_' . self::TYPE, array( self::class, 'meta_boxes' ) );
		add_action( 'admin_init', array( self::class, 'privacy_text' ) );
	}

	/**
	 * List columns.
	 *
	 * @param mixed $columns Core columns.
	 * @return mixed
	 */
	public static function columns( mixed $columns ): mixed {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		return array(
			'cb'     => $columns['cb'] ?? '',
			'title'  => __( 'Name', 'pergel-engine' ),
			'email'  => __( 'Email', 'pergel-engine' ),
			'kind'   => __( 'Form', 'pergel-engine' ),
			'page'   => __( 'Page', 'pergel-engine' ),
			'mailed' => __( 'Email sent', 'pergel-engine' ),
			'date'   => __( 'Received', 'pergel-engine' ),
		);
	}

	/**
	 * A cell of the list.
	 *
	 * @param mixed $column  Column key.
	 * @param mixed $post_id Entry id.
	 */
	public static function column( mixed $column, mixed $post_id ): void {
		$post_id = (int) $post_id;
		switch ( $column ) {
			case 'email':
				$email = (string) get_post_meta( $post_id, self::META_EMAIL, true );
				echo '' === $email ? '' : '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
				break;
			case 'kind':
				echo esc_html( self::kind_label( (string) get_post_meta( $post_id, self::META_KIND, true ) ) );
				break;
			case 'page':
				echo self::page_link( (int) get_post_meta( $post_id, self::META_PAGE, true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in page_link().
				break;
			case 'mailed':
				echo esc_html( self::mailed_label( $post_id ) );
				break;
		}
	}

	/**
	 * Messages are read, not edited: no Quick Edit, and "Edit" says "View".
	 *
	 * @param mixed $actions Row actions.
	 * @param mixed $post    Row post.
	 * @return mixed
	 */
	public static function row_actions( mixed $actions, mixed $post ): mixed {
		if ( ! is_array( $actions ) || ! $post instanceof \WP_Post || self::TYPE !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] );
		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = '<a href="' . esc_url( admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) ) . '">' . esc_html__( 'View', 'pergel-engine' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * No bulk edit.
	 *
	 * @param mixed $actions Bulk actions.
	 * @return mixed
	 */
	public static function bulk_actions( mixed $actions ): mixed {
		if ( is_array( $actions ) ) {
			unset( $actions['edit'] );
		}
		return $actions;
	}

	/**
	 * The message screen: the message, read-only, without the publish box.
	 */
	public static function meta_boxes(): void {
		remove_meta_box( 'submitdiv', self::TYPE, 'side' );
		remove_meta_box( 'slugdiv', self::TYPE, 'normal' );
		add_meta_box( 'pergel-message', __( 'Message', 'pergel-engine' ), array( self::class, 'message_box' ), self::TYPE, 'normal', 'high' );
	}

	/**
	 * Prints a stored message.
	 *
	 * @param \WP_Post $post Entry.
	 */
	public static function message_box( \WP_Post $post ): void {
		$kind   = (string) get_post_meta( $post->ID, self::META_KIND, true );
		$values = get_post_meta( $post->ID, self::META_FIELDS, true );
		$labels = Form::labels( '' === $kind ? 'contact' : $kind );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( is_array( $values ) ? $values : array() as $field => $value ) {
			if ( ! is_string( $field ) || ! is_string( $value ) ) {
				continue;
			}
			if ( 'consent' === $field ) {
				$value = sprintf(
					/* translators: %s: date and time. */
					__( 'Given on %s', 'pergel-engine' ),
					wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), (int) strtotime( $value ) )
				);
			}
			$cell = 'email' === $field && is_email( $value )
				? '<a href="' . esc_url( 'mailto:' . $value ) . '">' . esc_html( $value ) . '</a>'
				: nl2br( esc_html( $value ) );
			echo '<tr><th scope="row">' . esc_html( $labels[ $field ] ?? $field ) . '</th><td>' . $cell . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		}
		$rows    = array(
			__( 'Form', 'pergel-engine' )       => esc_html( self::kind_label( $kind ) ),
			__( 'Page', 'pergel-engine' )       => self::page_link( (int) get_post_meta( $post->ID, self::META_PAGE, true ) ),
			__( 'Received', 'pergel-engine' )   => esc_html( (string) get_the_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $post ) ),
			__( 'Email sent', 'pergel-engine' ) => esc_html( self::mailed_label( $post->ID ) ),
		);
		$handoff = self::handoff_label( $post->ID );
		if ( '' !== $handoff ) {
			$rows[ __( 'Passed on to', 'pergel-engine' ) ] = esc_html( $handoff );
		}
		foreach ( $rows as $label => $cell ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $cell . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		}
		echo '</tbody></table>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=' . self::TYPE ) ) . '">' . esc_html__( 'Back to the messages', 'pergel-engine' ) . '</a></p>';
	}

	/**
	 * Suggested text for the privacy policy. Hooked on admin_init.
	 */
	public static function privacy_text(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				'Pergel',
				'<p>' . esc_html__( 'When you send a message with a form on this site, we keep your name, email address, the message and any phone number or company name you enter, so we can answer you. The message is also emailed to us. Your IP address is used only for a few minutes to stop spam and is not stored with the message. If the form shows a Cloudflare Turnstile check, Cloudflare receives your IP address and browser details to tell people from bots.', 'pergel-engine' ) . '</p>'
			);
		}
	}

	/**
	 * Adds the messages to the personal data export.
	 *
	 * @param mixed $exporters Registered exporters.
	 * @return mixed
	 */
	public static function exporters( mixed $exporters ): mixed {
		if ( is_array( $exporters ) ) {
			$exporters['pergel-forms'] = array(
				'exporter_friendly_name' => __( 'Form messages', 'pergel-engine' ),
				'callback'               => array( self::class, 'export' ),
			);
		}
		return $exporters;
	}

	/**
	 * Adds the messages to the personal data erasure.
	 *
	 * @param mixed $erasers Registered erasers.
	 * @return mixed
	 */
	public static function erasers( mixed $erasers ): mixed {
		if ( is_array( $erasers ) ) {
			$erasers['pergel-forms'] = array(
				'eraser_friendly_name' => __( 'Form messages', 'pergel-engine' ),
				'callback'             => array( self::class, 'erase' ),
			);
		}
		return $erasers;
	}

	/**
	 * Exporter callback.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Batch number.
	 * @return array{data: list<array<string, mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		$entries = self::by_email( $email, $page );
		$data    = array();
		foreach ( $entries as $id ) {
			$values = get_post_meta( $id, self::META_FIELDS, true );
			$kind   = (string) get_post_meta( $id, self::META_KIND, true );
			$labels = Form::labels( '' === $kind ? 'contact' : $kind );
			$items  = array(
				array(
					'name'  => __( 'Received', 'pergel-engine' ),
					'value' => (string) get_post_field( 'post_date', $id ),
				),
			);
			foreach ( is_array( $values ) ? $values : array() as $field => $value ) {
				if ( is_string( $field ) && is_string( $value ) ) {
					$items[] = array(
						'name'  => $labels[ $field ] ?? $field,
						'value' => $value,
					);
				}
			}
			$data[] = array(
				'group_id'    => 'pergel-forms',
				'group_label' => __( 'Form messages', 'pergel-engine' ),
				'item_id'     => 'pergel-form-message-' . $id,
				'data'        => $items,
			);
		}
		return array(
			'data' => $data,
			'done' => count( $entries ) < self::BATCH,
		);
	}

	/**
	 * Eraser callback: deletes the sender's messages. A message that cannot
	 * be deleted is reported as kept and ends the run, so the eraser does
	 * not ask for the same batch again and again.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Batch number (always the first, since entries go away).
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		unset( $page );
		$entries  = self::by_email( $email, 1 );
		$removed  = false;
		$retained = false;
		foreach ( $entries as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$removed = true;
			} else {
				$retained = true;
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $retained ? array( __( 'Some form messages could not be deleted; delete them under Messages.', 'pergel-engine' ) ) : array(),
			'done'           => $retained || count( $entries ) < self::BATCH,
		);
	}

	/**
	 * Entry ids from an email address, the trashed ones included.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Batch number.
	 * @return list<int>
	 */
	private static function by_email( string $email, int $page ): array {
		$email = strtolower( trim( $email ) );
		if ( '' === $email ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => array( 'private', 'trash' ),
				'fields'         => 'ids',
				'posts_per_page' => self::BATCH,
				'paged'          => max( 1, $page ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_key'       => self::META_EMAIL, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin privacy tools only.
				'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admin privacy tools only.
			)
		);
		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * Name of a kind of form.
	 *
	 * @param string $kind contact or quote.
	 */
	private static function kind_label( string $kind ): string {
		return 'quote' === $kind ? __( 'Quote request', 'pergel-engine' ) : __( 'Contact', 'pergel-engine' );
	}

	/**
	 * Whether the email went out.
	 *
	 * @param int $post_id Entry id.
	 */
	private static function mailed_label( int $post_id ): string {
		return '1' === (string) get_post_meta( $post_id, self::META_MAILED, true ) ? __( 'Yes', 'pergel-engine' ) : __( 'No', 'pergel-engine' );
	}

	/**
	 * Services the message was passed on to, with their results.
	 *
	 * @param int $post_id Entry id.
	 */
	private static function handoff_label( int $post_id ): string {
		$handoff = get_post_meta( $post_id, self::META_HANDOFF, true );
		$parts   = array();
		foreach ( is_array( $handoff ) ? $handoff : array() as $service ) {
			if ( is_array( $service ) && is_string( $service['label'] ?? null ) ) {
				$parts[] = sprintf(
					/* translators: 1: service name, 2: Sent or Failed. */
					__( '%1$s: %2$s', 'pergel-engine' ),
					$service['label'],
					! empty( $service['sent'] ) ? __( 'Sent', 'pergel-engine' ) : __( 'Failed', 'pergel-engine' )
				);
			}
		}
		return implode( '; ', $parts );
	}

	/**
	 * Link to the page the form was on, escaped.
	 *
	 * @param int $page Page id.
	 */
	private static function page_link( int $page ): string {
		$url = $page > 0 ? get_permalink( $page ) : false;
		return is_string( $url ) ? '<a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $page ) ) . '</a>' : '—';
	}
}
