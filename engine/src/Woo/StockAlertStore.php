<?php
/**
 * Back-in-stock alert records.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * The addresses waiting for a product, in their own table: one row per
 * product (or variation) and address. A row is pending until its address
 * is confirmed, active while it waits, and sent once the email has gone
 * out. Pending rows are deleted after PENDING_DAYS, sent ones after
 * SENT_DAYS. The personal data export and erasure tools include the rows.
 *
 * @phpstan-type Row array{id: int, product_id: int, email: string, user_id: int, status: string, created: string, confirmed: string, sent: string}
 */
final class StockAlertStore {

	/**
	 * Table schema version; a higher one makes install() run dbDelta.
	 */
	public const SCHEMA = 1;

	/**
	 * Autoloaded option with the installed schema version.
	 */
	public const STATE = 'pergel_stock_alert_db';

	public const PENDING = 'pending';
	public const ACTIVE  = 'active';
	public const SENT    = 'sent';

	/**
	 * Days a row stays without its address confirmed, and after its email.
	 */
	public const PENDING_DAYS = 7;
	public const SENT_DAYS    = 30;

	/**
	 * Rows per privacy tool batch.
	 */
	private const BATCH = 50;

	/**
	 * Adds the hooks that work on the stored rows: the privacy tools, the
	 * admin list and the links in the emails. Woo\Module calls it once the
	 * table exists, so the rows stay listed and erasable, and an address
	 * can still be removed with its link, after the alerts are turned off.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( StockAlert::class, 'link_clicked' ), 5 );
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'erasers' ) );
		add_action( StockAlert::SEND_HOOK, array( StockAlert::class, 'run' ) );
		if ( is_admin() ) {
			add_action( 'admin_init', array( self::class, 'privacy_text' ) );
			StockAlertList::register();
		}
	}

	/**
	 * The table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pergel_stock_alerts';
	}

	/**
	 * Whether the table is installed.
	 */
	public static function installed(): bool {
		return self::SCHEMA === (int) get_option( self::STATE, 0 );
	}

	/**
	 * Creates or updates the table when its schema is older than SCHEMA.
	 * Cheap when there is nothing to do.
	 */
	public static function maintain(): void {
		if ( ! self::installed() ) {
			self::install();
		}
	}

	/**
	 * Creates the table.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		dbDelta(
			"CREATE TABLE $table (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL,
  email varchar(100) NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(10) NOT NULL,
  created datetime NOT NULL,
  confirmed datetime DEFAULT NULL,
  sent datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY product_email (product_id,email),
  KEY status_created (status,created),
  KEY email (email)
) {$wpdb->get_charset_collate()};"
		);
		update_option( self::STATE, self::SCHEMA, true );
	}

	/**
	 * Drops the table and the state. For uninstalling and tests.
	 */
	public static function uninstall(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.SchemaChange -- Own table, name from the prefix.
		delete_option( self::STATE );
	}

	/**
	 * One row by id.
	 *
	 * @param int $id Row id.
	 * @return array<string, mixed>|null
	 * @phpstan-return Row|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table; not cached, rows change on every request that reads them.
		return is_array( $row ) ? self::row( $row ) : null;
	}

	/**
	 * The row of a product and an address.
	 *
	 * @param int    $product Product or variation id.
	 * @param string $email   Address, lower case.
	 * @return array<string, mixed>|null
	 * @phpstan-return Row|null
	 */
	public static function find( int $product, string $email ): ?array {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE product_id = %d AND email = %s", $product, $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table.
		return is_array( $row ) ? self::row( $row ) : null;
	}

	/**
	 * Stores an address for a product, as a new row or by starting the
	 * product's row for that address over.
	 *
	 * @param int    $product Product or variation id.
	 * @param string $email   Address, lower case.
	 * @param int    $user    Customer's user id, 0 for a guest.
	 * @param string $status  PENDING or ACTIVE.
	 * @return array<string, mixed>|null The stored row.
	 * @phpstan-return Row|null
	 */
	public static function save( int $product, string $email, int $user, string $status ): ?array {
		global $wpdb;
		$now    = current_time( 'mysql', true );
		$fields = array(
			'user_id'   => $user,
			'status'    => $status,
			'created'   => $now,
			'confirmed' => self::ACTIVE === $status ? $now : null,
			'sent'      => null,
		);
		$row    = self::find( $product, $email );
		if ( null === $row ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
				self::table(),
				array(
					'product_id' => $product,
					'email'      => $email,
				) + $fields
			);
			return self::get( (int) $wpdb->insert_id );
		}
		$wpdb->update( self::table(), $fields, array( 'id' => $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
		return self::get( $row['id'] );
	}

	/**
	 * Marks a pending row as confirmed.
	 *
	 * @param int $id Row id.
	 */
	public static function confirm( int $id ): bool {
		global $wpdb;
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			self::table(),
			array(
				'status'    => self::ACTIVE,
				'confirmed' => current_time( 'mysql', true ),
			),
			array(
				'id'     => $id,
				'status' => self::PENDING,
			)
		);
	}

	/**
	 * Deletes a row.
	 *
	 * @param int $id Row id.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * Whether any address waits for a product.
	 *
	 * @param int $product Product or variation id.
	 */
	public static function waiting( int $product ): bool {
		global $wpdb;
		$table = self::table();
		return null !== $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE product_id = %d AND status = %s LIMIT 1", $product, self::ACTIVE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table.
	}

	/**
	 * The addresses waiting for a product, oldest first.
	 *
	 * @param int $product Product or variation id.
	 * @param int $limit   Most rows.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<Row>
	 */
	public static function due( int $product, int $limit ): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE product_id = %d AND status = %s ORDER BY id ASC LIMIT %d", $product, self::ACTIVE, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table.
		return array_map( array( self::class, 'row' ), is_array( $rows ) ? array_values( $rows ) : array() );
	}

	/**
	 * Marks a row as sent.
	 *
	 * @param int $id Row id.
	 */
	public static function mark_sent( int $id ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table.
			self::table(),
			array(
				'status' => self::SENT,
				'sent'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * Deletes pending rows older than PENDING_DAYS and sent rows older than
	 * SENT_DAYS.
	 *
	 * @return int Rows deleted.
	 */
	public static function purge(): int {
		global $wpdb;
		$table   = self::table();
		$pending = gmdate( 'Y-m-d H:i:s', time() - self::PENDING_DAYS * DAY_IN_SECONDS );
		$sent    = gmdate( 'Y-m-d H:i:s', time() - self::SENT_DAYS * DAY_IN_SECONDS );
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE (status = %s AND created < %s) OR (status = %s AND sent < %s)", self::PENDING, $pending, self::SENT, $sent ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table.
		return (int) $deleted;
	}

	/**
	 * Row count by status.
	 *
	 * @return array<string, int>
	 */
	public static function counts(): array {
		global $wpdb;
		$table  = self::table();
		$counts = array_fill_keys( array( self::ACTIVE, self::PENDING, self::SENT ), 0 );
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM $table GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table, no input.
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['n'];
			}
		}
		return $counts;
	}

	/**
	 * The products most addresses wait for.
	 *
	 * @param int $limit Most products.
	 * @return array<int, int> Address count by product id.
	 */
	public static function wanted( int $limit ): array {
		global $wpdb;
		$table  = self::table();
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, COUNT(*) AS n FROM $table WHERE status = %s GROUP BY product_id ORDER BY n DESC, product_id ASC LIMIT %d", self::ACTIVE, $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table.
		$wanted = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$wanted[ (int) $row['product_id'] ] = (int) $row['n'];
		}
		return $wanted;
	}

	/**
	 * A page of rows, newest first.
	 *
	 * @param string $status   A status, or '' for all.
	 * @param int    $page     Page number.
	 * @param int    $per_page Rows per page.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<Row>
	 */
	public static function rows( string $status, int $page, int $per_page ): array {
		global $wpdb;
		$table  = self::table();
		$offset = ( max( 1, $page ) - 1 ) * $per_page;
		$rows   = '' === $status
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table.
			: $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d", $status, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table.
		return array_map( array( self::class, 'row' ), is_array( $rows ) ? array_values( $rows ) : array() );
	}

	/**
	 * Suggested text for the privacy policy. Hooked on admin_init.
	 */
	public static function privacy_text(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				'Pergel',
				'<p>' . esc_html__( 'When you ask to be told that a sold-out product is back in stock, we keep your email address, the product and the time you asked. Unless you are logged in, we first email you a link to confirm the address; an address that is not confirmed is deleted after 7 days. When the product is back, we email you once and delete the address 30 days later. Every such email has a link to remove your address sooner.', 'pergel-engine' ) . '</p>'
			);
		}
	}

	/**
	 * Adds the alerts to the personal data export.
	 *
	 * @param mixed $exporters Registered exporters.
	 * @return mixed
	 */
	public static function exporters( mixed $exporters ): mixed {
		if ( is_array( $exporters ) ) {
			$exporters['pergel-stock-alerts'] = array(
				'exporter_friendly_name' => __( 'Back-in-stock alerts', 'pergel-engine' ),
				'callback'               => array( self::class, 'export' ),
			);
		}
		return $exporters;
	}

	/**
	 * Adds the alerts to the personal data erasure.
	 *
	 * @param mixed $erasers Registered erasers.
	 * @return mixed
	 */
	public static function erasers( mixed $erasers ): mixed {
		if ( is_array( $erasers ) ) {
			$erasers['pergel-stock-alerts'] = array(
				'eraser_friendly_name' => __( 'Back-in-stock alerts', 'pergel-engine' ),
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
		$rows = self::by_email( $email, $page );
		$data = array();
		foreach ( $rows as $row ) {
			$product = wc_get_product( $row['product_id'] );
			$data[]  = array(
				'group_id'    => 'pergel-stock-alerts',
				'group_label' => __( 'Back-in-stock alerts', 'pergel-engine' ),
				'item_id'     => 'pergel-stock-alert-' . $row['id'],
				'data'        => array(
					array(
						'name'  => __( 'Product', 'pergel-engine' ),
						'value' => $product instanceof \WC_Product ? $product->get_name() : '#' . $row['product_id'],
					),
					array(
						'name'  => __( 'Email address', 'pergel-engine' ),
						'value' => $row['email'],
					),
					array(
						'name'  => __( 'Status', 'pergel-engine' ),
						'value' => self::status_label( $row['status'] ),
					),
					array(
						'name'  => __( 'Asked on', 'pergel-engine' ),
						'value' => get_date_from_gmt( $row['created'] ),
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => count( $rows ) < self::BATCH,
		);
	}

	/**
	 * Eraser callback: deletes the address's rows. A row that cannot be
	 * deleted is reported as kept and ends the run.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Batch number (always the first, since rows go away).
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		unset( $page );
		$rows     = self::by_email( $email, 1 );
		$removed  = false;
		$retained = false;
		foreach ( $rows as $row ) {
			if ( self::delete( $row['id'] ) ) {
				$removed = true;
			} else {
				$retained = true;
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => array(),
			'done'           => $retained || count( $rows ) < self::BATCH,
		);
	}

	/**
	 * Name of a status.
	 *
	 * @param string $status Status.
	 */
	public static function status_label( string $status ): string {
		return match ( $status ) {
			self::PENDING => __( 'Awaiting confirmation', 'pergel-engine' ),
			self::SENT    => __( 'Emailed', 'pergel-engine' ),
			default       => __( 'Waiting', 'pergel-engine' ),
		};
	}

	/**
	 * Rows of an address, and of the account with that address (a customer
	 * who signed up and later changed the account's email).
	 *
	 * @param string $email Email address.
	 * @param int    $page  Batch number.
	 * @return list<array<string, mixed>>
	 * @phpstan-return list<Row>
	 */
	private static function by_email( string $email, int $page ): array {
		global $wpdb;
		$email = strtolower( trim( $email ) );
		if ( '' === $email || ! self::installed() ) {
			return array();
		}
		$user  = get_user_by( 'email', $email );
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE email = %s OR ( user_id > 0 AND user_id = %d ) ORDER BY id ASC LIMIT %d OFFSET %d", $email, $user instanceof \WP_User ? $user->ID : 0, self::BATCH, ( max( 1, $page ) - 1 ) * self::BATCH ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table.
		return array_map( array( self::class, 'row' ), is_array( $rows ) ? array_values( $rows ) : array() );
	}

	/**
	 * A database row with its types.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 * @phpstan-return Row
	 */
	private static function row( array $row ): array {
		return array(
			'id'         => (int) ( $row['id'] ?? 0 ),
			'product_id' => (int) ( $row['product_id'] ?? 0 ),
			'email'      => (string) ( $row['email'] ?? '' ),
			'user_id'    => (int) ( $row['user_id'] ?? 0 ),
			'status'     => (string) ( $row['status'] ?? '' ),
			'created'    => (string) ( $row['created'] ?? '' ),
			'confirmed'  => (string) ( $row['confirmed'] ?? '' ),
			'sent'       => (string) ( $row['sent'] ?? '' ),
		);
	}
}
