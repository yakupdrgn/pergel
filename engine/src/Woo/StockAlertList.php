<?php
/**
 * Back-in-stock alerts in the admin area.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * WooCommerce → Stock alerts: how many addresses wait, the products most
 * of them wait for, and every alert, newest first, with a link to delete
 * it.
 */
final class StockAlertList {

	public const PAGE   = 'pergel-stock-alerts';
	public const DELETE = 'pergel_stock_alert_delete';

	/**
	 * Alerts per page.
	 */
	private const PER_PAGE = 50;

	/**
	 * Adds the hooks. StockAlertStore calls it in the admin area.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 60 );
		add_action( 'admin_post_' . self::DELETE, array( self::class, 'delete' ) );
	}

	/**
	 * The page under the WooCommerce menu.
	 */
	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Back-in-stock alerts', 'pergel-engine' ),
			__( 'Stock alerts', 'pergel-engine' ),
			'manage_woocommerce',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	/**
	 * Deletes one alert, then goes back to the list. Hooked on admin_post.
	 */
	public static function delete(): void {
		$id = isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( self::DELETE . '_' . $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'pergel-engine' ), '', array( 'response' => 403 ) );
		}
		$deleted = $id > 0 && StockAlertStore::delete( $id );
		wp_safe_redirect( add_query_arg( 'deleted', $deleted ? '1' : '0', admin_url( 'admin.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * The page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filters and paging of a read-only list.
		$status  = isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$page    = isset( $_GET['paged'] ) && is_numeric( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$deleted = isset( $_GET['deleted'] ) && '1' === $_GET['deleted'];
		// phpcs:enable
		$counts = StockAlertStore::counts();
		$status = isset( $counts[ $status ] ) ? $status : '';
		$total  = '' === $status ? array_sum( $counts ) : $counts[ $status ];
		$base   = admin_url( 'admin.php?page=' . self::PAGE );

		echo '<div class="wrap"><h1>' . esc_html__( 'Back-in-stock alerts', 'pergel-engine' ) . '</h1>';
		if ( $deleted ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'The alert has been deleted.', 'pergel-engine' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Addresses waiting for a sold-out product or option. When it is back in stock, each gets one email; addresses that are not confirmed are deleted after 7 days, emailed ones after 30 days.', 'pergel-engine' ) . '</p>';

		$links = array( '<a href="' . esc_url( $base ) . '"' . ( '' === $status ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html__( 'All', 'pergel-engine' ) . ' <span class="count">(' . esc_html( number_format_i18n( array_sum( $counts ) ) ) . ')</span></a>' );
		foreach ( $counts as $key => $count ) {
			$links[] = '<a href="' . esc_url( add_query_arg( 'status', $key, $base ) ) . '"' . ( $key === $status ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( StockAlertStore::status_label( $key ) ) . ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span></a>';
		}
		echo '<ul class="subsubsub"><li>' . implode( ' | </li><li>', $links ) . '</li></ul><br class="clear">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.

		$wanted = StockAlertStore::wanted( 10 );
		if ( array() !== $wanted && '' === $status && 1 === $page ) {
			echo '<h2>' . esc_html__( 'Most wanted', 'pergel-engine' ) . '</h2>';
			echo '<table class="widefat striped" style="max-width:40rem"><thead><tr><th scope="col">' . esc_html__( 'Product', 'pergel-engine' ) . '</th><th scope="col">' . esc_html__( 'Waiting', 'pergel-engine' ) . '</th></tr></thead><tbody>';
			foreach ( $wanted as $product => $count ) {
				echo '<tr><td>' . self::product_link( $product ) . '</td><td>' . esc_html( number_format_i18n( $count ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in product_link().
			}
			echo '</tbody></table><h2>' . esc_html__( 'All alerts', 'pergel-engine' ) . '</h2>';
		}

		$rows = StockAlertStore::rows( $status, $page, self::PER_PAGE );
		echo '<table class="widefat striped"><thead><tr>'
			. '<th scope="col">' . esc_html__( 'Product', 'pergel-engine' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Email address', 'pergel-engine' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Status', 'pergel-engine' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Asked on', 'pergel-engine' ) . '</th>'
			. '<th scope="col">' . esc_html__( 'Emailed on', 'pergel-engine' ) . '</th>'
			. '<th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'pergel-engine' ) . '</span></th>'
			. '</tr></thead><tbody>';
		if ( array() === $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No alerts yet.', 'pergel-engine' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$args   = array(
				'action' => self::DELETE,
				'id'     => $row['id'],
			);
			$delete = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::DELETE . '_' . $row['id'] );
			echo '<tr>'
				. '<td>' . self::product_link( $row['product_id'] ) . '</td>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in product_link().
				. '<td>' . esc_html( $row['email'] ) . ( $row['user_id'] > 0 ? ' <span class="description">(' . esc_html__( 'customer', 'pergel-engine' ) . ')</span>' : '' ) . '</td>'
				. '<td>' . esc_html( StockAlertStore::status_label( $row['status'] ) ) . '</td>'
				. '<td>' . esc_html( self::date( $row['created'] ) ) . '</td>'
				. '<td>' . esc_html( self::date( $row['sent'] ) ) . '</td>'
				. '<td><a href="' . esc_url( $delete ) . '" class="submitdelete">' . esc_html__( 'Delete', 'pergel-engine' ) . '</a></td>'
				. '</tr>';
		}
		echo '</tbody></table>';

		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages > 1 ) {
			$paging = paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%', '' === $status ? $base : add_query_arg( 'status', $status, $base ) ),
					'format'  => '',
					'current' => $page,
					'total'   => $pages,
				)
			);
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( (string) $paging ) . '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * A product's name with a link to edit it, escaped.
	 *
	 * @param int $id Product or variation id.
	 */
	private static function product_link( int $id ): string {
		$product = wc_get_product( $id );
		if ( ! $product instanceof \WC_Product ) {
			/* translators: %d: product id. */
			return esc_html( sprintf( __( 'Deleted product #%d', 'pergel-engine' ), $id ) );
		}
		$edit  = get_edit_post_link( $product->is_type( 'variation' ) ? $product->get_parent_id() : $id );
		$name  = esc_html( wp_strip_all_tags( $product->get_name() ) );
		$stock = $product->is_in_stock() ? '' : ' <span class="description">(' . esc_html__( 'out of stock', 'pergel-engine' ) . ')</span>';
		return ( is_string( $edit ) ? '<a href="' . esc_url( $edit ) . '">' . $name . '</a>' : $name ) . $stock;
	}

	/**
	 * A stored time in the site's time zone and formats, or a dash.
	 *
	 * @param string $gmt Time in UTC, as stored.
	 */
	private static function date( string $gmt ): string {
		if ( '' === $gmt ) {
			return '—';
		}
		$time = strtotime( $gmt . ' UTC' );
		return false === $time ? '—' : (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time );
	}
}
