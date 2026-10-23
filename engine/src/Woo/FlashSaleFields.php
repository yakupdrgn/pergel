<?php
/**
 * The flash sale editor box and list column.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * A box beside the campaign editor: start, end, discount, products and
 * categories, and how far the prices are; a column in the campaign list
 * with the state, the dates and the discount.
 */
final class FlashSaleFields {

	/**
	 * Nonce action and field.
	 */
	private const NONCE = 'pergel_sale_fields';

	/**
	 * Posted field names.
	 */
	private const FIELD = 'pergel_sale';

	/**
	 * Format of the date and time fields, in the site's time zone.
	 */
	private const FORMAT = 'Y-m-d\TH:i';

	/**
	 * Adds the admin hooks.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_' . FlashSales::TYPE, array( self::class, 'box' ) );
		add_action( 'save_post_' . FlashSales::TYPE, array( self::class, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ), 20 );
		add_filter( 'manage_' . FlashSales::TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . FlashSales::TYPE . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
	}

	/**
	 * WooCommerce's product and category pickers on the campaign editor.
	 */
	public static function assets(): void {
		$screen = get_current_screen();
		if ( null === $screen || FlashSales::TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}

	/**
	 * Adds the box.
	 */
	public static function box(): void {
		add_meta_box( 'pergel-sale', __( 'Campaign', 'pergel-engine' ), array( self::class, 'fields' ), FlashSales::TYPE, 'side', 'high' );
	}

	/**
	 * The fields.
	 *
	 * @param mixed $post Campaign being edited.
	 */
	public static function fields( mixed $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$sale = FlashSales::campaign( $post->ID );
		wp_nonce_field( self::NONCE, self::NONCE );
		echo self::status( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status().
		printf(
			'<p><label for="pergel-sale-start"><strong>%1$s</strong></label><br><input type="datetime-local" id="pergel-sale-start" name="%2$s[start]" value="%3$s"></p>',
			esc_html__( 'Starts', 'pergel-engine' ),
			esc_attr( self::FIELD ),
			esc_attr( $sale['start'] > 0 ? FlashSales::date( $sale['start'], self::FORMAT ) : '' )
		);
		printf(
			'<p><label for="pergel-sale-end"><strong>%1$s</strong></label><br><input type="datetime-local" id="pergel-sale-end" name="%2$s[end]" value="%3$s"></p>',
			esc_html__( 'Ends', 'pergel-engine' ),
			esc_attr( self::FIELD ),
			esc_attr( $sale['end'] > 0 ? FlashSales::date( $sale['end'], self::FORMAT ) : '' )
		);
		printf(
			'<p class="description">%s</p>',
			/* translators: %s: time zone name. */
			esc_html( sprintf( __( 'In the site\'s time zone (%s).', 'pergel-engine' ), wp_timezone_string() ) )
		);
		printf(
			'<p><label for="pergel-sale-discount"><strong>%1$s</strong></label><br><input type="number" id="pergel-sale-discount" name="%2$s[discount]" value="%3$s" min="1" max="90" step="1" class="small-text"> %%</p>',
			esc_html__( 'Discount', 'pergel-engine' ),
			esc_attr( self::FIELD ),
			esc_attr( $sale['discount'] > 0 ? (string) $sale['discount'] : '' )
		);
		$options = '';
		foreach ( $sale['products'] as $id ) {
			$product = wc_get_product( $id );
			if ( $product instanceof \WC_Product ) {
				$options .= sprintf( '<option value="%1$d" selected>%2$s</option>', $id, esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) );
			}
		}
		printf(
			'<p><label for="pergel-sale-products"><strong>%1$s</strong></label><br><select id="pergel-sale-products" class="wc-product-search" multiple style="width:100%%" name="%2$s[products][]" data-placeholder="%3$s" data-action="woocommerce_json_search_products">%4$s</select></p>',
			esc_html__( 'Products', 'pergel-engine' ),
			esc_attr( self::FIELD ),
			esc_attr__( 'Search for a product…', 'pergel-engine' ),
			$options // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		);
		$terms   = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		$options = '';
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$options .= sprintf( '<option value="%1$d"%2$s>%3$s</option>', $term->term_id, selected( in_array( $term->term_id, $sale['categories'], true ), true, false ), esc_html( $term->name ) );
			}
		}
		printf(
			'<p><label for="pergel-sale-categories"><strong>%1$s</strong></label><br><select id="pergel-sale-categories" class="wc-enhanced-select" multiple style="width:100%%" name="%2$s[categories][]" data-placeholder="%3$s">%4$s</select></p><p class="description">%5$s</p>',
			esc_html__( 'Categories', 'pergel-engine' ),
			esc_attr( self::FIELD ),
			esc_attr__( 'Choose categories…', 'pergel-engine' ),
			$options, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_html__( 'Every published product in these categories and their subcategories takes part, with the products chosen above. Products added to a category later join when the campaign is saved again.', 'pergel-engine' )
		);
	}

	/**
	 * Where the campaign stands, above the fields.
	 *
	 * @param int $id Campaign.
	 */
	private static function status( int $id ): string {
		$state   = FlashSales::state( $id );
		$applied = count( FlashSalePrices::applied( $id ) );
		$texts   = array(
			'unset'    => __( 'Set the start, the end and the discount, and choose products or categories.', 'pergel-engine' ),
			'upcoming' => __( 'Not started yet. The prices change when it starts.', 'pergel-engine' ),
			/* translators: %d: number of products and variations. */
			'running'  => sprintf( _n( 'Running: the campaign price is on %d product.', 'Running: the campaign price is on %d products.', $applied, 'pergel-engine' ), $applied ),
			'ended'    => __( 'Ended. The products have their own prices again.', 'pergel-engine' ),
		);
		$html    = '<p><strong>' . esc_html( $texts[ $state ] ?? '' ) . '</strong></p>';
		if ( 'running' === $state && 'publish' !== get_post_status( $id ) ) {
			$html = '<p><strong>' . esc_html__( 'The campaign runs once it is published.', 'pergel-engine' ) . '</strong></p>';
		}
		$unsold = 'unset' === $state ? 0 : count( FlashSales::chosen( $id ) ) - count( FlashSales::products( $id ) );
		if ( $unsold > 0 ) {
			$html .= sprintf(
				'<div class="notice notice-info inline"><p>%s</p></div>',
				/* translators: %d: number of products. */
				esc_html( sprintf( _n( '%d product has no price under its sales mode (quote or catalog), so the campaign leaves it out.', '%d products have no price under their sales mode (quote or catalog), so the campaign leaves them out.', $unsold, 'pergel-engine' ), $unsold ) )
			);
		}
		$taken = array_keys( array_filter( FlashSalePrices::skipped( $id ), static fn ( string $reason ): bool => 'taken' === $reason ) );
		if ( $taken ) {
			$names = array();
			foreach ( array_slice( $taken, 0, 5 ) as $product ) {
				$names[] = get_the_title( $product );
			}
			$html .= sprintf(
				'<div class="notice notice-warning inline"><p>%1$s %2$s</p></div>',
				/* translators: %d: number of products. */
				esc_html( sprintf( _n( '%d product is in another running campaign and keeps that price:', '%d products are in another running campaign and keep that price:', count( $taken ), 'pergel-engine' ), count( $taken ) ) ),
				esc_html( implode( ', ', $names ) . ( count( $taken ) > 5 ? ', …' : '' ) )
			);
		}
		return $html;
	}

	/**
	 * Saves the fields and brings the prices in step.
	 *
	 * @param int $post_id Campaign.
	 */
	public static function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE ], $_POST[ self::FIELD ] ) || ! is_array( $_POST[ self::FIELD ] ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is checked in values().
		$values = self::values( wp_unslash( $_POST[ self::FIELD ] ) );
		foreach ( $values as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
		FlashSalePrices::schedule( $post_id );
	}

	/**
	 * Clean meta values from the posted fields.
	 *
	 * @param array<mixed> $posted Posted fields.
	 * @return array<string, int|list<int>>
	 */
	public static function values( array $posted ): array {
		return array(
			FlashSales::START      => self::time( $posted['start'] ?? '' ),
			FlashSales::END        => self::time( $posted['end'] ?? '' ),
			FlashSales::DISCOUNT   => max( 0, min( 90, absint( $posted['discount'] ?? 0 ) ) ),
			FlashSales::PRODUCTS   => FlashSales::ids( $posted['products'] ?? array() ),
			FlashSales::CATEGORIES => FlashSales::ids( $posted['categories'] ?? array() ),
		);
	}

	/**
	 * Unix time of a date and time field, 0 when empty or wrong.
	 *
	 * @param mixed $value Field value, in the site's time zone.
	 */
	private static function time( mixed $value ): int {
		if ( ! is_string( $value ) || '' === $value ) {
			return 0;
		}
		$date = \DateTimeImmutable::createFromFormat( self::FORMAT, $value, wp_timezone() );
		return false === $date ? 0 : $date->getTimestamp();
	}

	/**
	 * Adds the campaign column after the title.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$list = array();
		foreach ( $columns as $key => $label ) {
			$list[ $key ] = $label;
			if ( 'title' === $key ) {
				$list['pergel_sale'] = __( 'Campaign', 'pergel-engine' );
			}
		}
		return $list;
	}

	/**
	 * The campaign column: state, dates and discount.
	 *
	 * @param string $column Column.
	 * @param int    $id     Campaign.
	 */
	public static function column( string $column, int $id ): void {
		if ( 'pergel_sale' !== $column ) {
			return;
		}
		$names = array(
			'unset'    => __( 'Not set up', 'pergel-engine' ),
			'upcoming' => __( 'Upcoming', 'pergel-engine' ),
			'running'  => __( 'Running', 'pergel-engine' ),
			'ended'    => __( 'Ended', 'pergel-engine' ),
		);
		$state = FlashSales::state( $id );
		$sale  = FlashSales::campaign( $id );
		echo '<strong>' . esc_html( $names[ $state ] ) . '</strong>';
		if ( 'unset' !== $state ) {
			printf(
				'<br>%1$s – %2$s<br>%3$s',
				esc_html( FlashSales::date( $sale['start'] ) ),
				esc_html( FlashSales::date( $sale['end'] ) ),
				/* translators: %d: discount in percent. */
				esc_html( sprintf( __( '%d%% off', 'pergel-engine' ), $sale['discount'] ) )
			);
		}
	}
}
