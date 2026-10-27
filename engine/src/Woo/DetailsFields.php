<?php
/**
 * A product's own feature list and extra tab.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * A box under the product editor: a feature list, one per line, and a tab
 * with a title and content of its own. Details shows them under the
 * product: the tab always, the list when the feature list setting is on.
 * Stored together in one post meta, only when something was written.
 */
final class DetailsFields {

	/**
	 * Most lines in the feature list.
	 */
	public const FEATURES = 30;

	/**
	 * Longest feature line and tab title.
	 */
	private const LINE = 200;

	private const NAME_LENGTH = 40;

	/**
	 * Longest tab content.
	 */
	private const TAB_LENGTH = 20000;

	/**
	 * Form field name, and nonce action and field.
	 */
	private const NAME = 'pergel_details';

	private const NONCE = 'pergel_details_nonce';

	/**
	 * Adds the admin hooks; Layout calls it.
	 */
	public static function register(): void {
		add_action( 'add_meta_boxes_product', array( self::class, 'box' ) );
		add_action( 'save_post_product', array( self::class, 'save' ) );
	}

	/**
	 * Cleans stored or posted values.
	 *
	 * @param mixed $value Raw values.
	 * @return array{features: list<string>, tab_name: string, tab: string}
	 */
	public static function clean( mixed $value ): array {
		$value    = is_array( $value ) ? $value : array();
		$features = $value['features'] ?? array();
		$features = is_string( $features ) ? preg_split( '/\R/', $features ) : $features;
		$lines    = array();
		foreach ( is_array( $features ) ? $features : array() as $line ) {
			$line = is_string( $line ) ? mb_substr( trim( sanitize_text_field( $line ) ), 0, self::LINE ) : '';
			if ( '' !== $line && count( $lines ) < self::FEATURES ) {
				$lines[] = $line;
			}
		}
		$name = is_string( $value['tab_name'] ?? null ) ? mb_substr( trim( sanitize_text_field( $value['tab_name'] ) ), 0, self::NAME_LENGTH ) : '';
		$tab  = is_string( $value['tab'] ?? null ) ? trim( wp_kses_post( mb_substr( $value['tab'], 0, self::TAB_LENGTH ) ) ) : '';
		return array(
			'features' => $lines,
			'tab_name' => $name,
			'tab'      => $tab,
		);
	}

	/**
	 * Adds the box under the product editor.
	 */
	public static function box(): void {
		add_meta_box( 'pergel-product-details', __( 'Product page sections', 'pergel-engine' ), array( self::class, 'fields' ), 'product', 'normal', 'low' );
	}

	/**
	 * The fields in the box.
	 *
	 * @param \WP_Post $post Product being edited.
	 */
	public static function fields( \WP_Post $post ): void {
		$own = self::clean( get_post_meta( $post->ID, Layout::DETAILS_META, true ) );
		wp_nonce_field( self::NONCE, self::NONCE );
		printf(
			'<p><label for="pergel-details-features"><strong>%1$s</strong></label></p><textarea class="widefat" id="pergel-details-features" name="%2$s[features]" rows="5" aria-describedby="pergel-details-features-help">%3$s</textarea><p class="description" id="pergel-details-features-help">%4$s</p>',
			esc_html__( 'Feature list', 'pergel-engine' ),
			esc_attr( self::NAME ),
			esc_textarea( implode( "\n", $own['features'] ) ),
			esc_html__( 'One feature on each line. Shown as "Specifications" under the product when the feature list is on in Pergel → Settings → Shop.', 'pergel-engine' )
		);
		printf(
			'<p><label for="pergel-details-tab-name"><strong>%1$s</strong></label></p><input type="text" class="widefat" id="pergel-details-tab-name" name="%2$s[tab_name]" value="%3$s" maxlength="%4$d" aria-describedby="pergel-details-tab-help"><p class="description" id="pergel-details-tab-help">%5$s</p>',
			esc_html__( 'Own tab: title', 'pergel-engine' ),
			esc_attr( self::NAME ),
			esc_attr( $own['tab_name'] ),
			(int) self::NAME_LENGTH,
			esc_html__( 'A section of this product only, before the questions and reviews, e.g. Warranty. Shown when it has both a title and content.', 'pergel-engine' )
		);
		echo '<p><strong>' . esc_html__( 'Own tab: content', 'pergel-engine' ) . '</strong></p>';
		wp_editor(
			$own['tab'],
			'pergel-details-tab',
			array(
				'textarea_name' => self::NAME . '[tab]',
				'textarea_rows' => 8,
				'media_buttons' => true,
				'teeny'         => true,
			)
		);
	}

	/**
	 * Saves the box.
	 *
	 * @param int $post_id Product id.
	 */
	public static function save( int $post_id ): void {
		if ( ! isset( $_POST[ self::NONCE ], $_POST[ self::NAME ] ) || ! is_array( $_POST[ self::NAME ] ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- clean() sanitizes every value.
		$own = self::clean( wp_unslash( $_POST[ self::NAME ] ) );
		if ( array() === $own['features'] && '' === $own['tab_name'] && '' === $own['tab'] ) {
			delete_post_meta( $post_id, Layout::DETAILS_META );
		} else {
			update_post_meta( $post_id, Layout::DETAILS_META, $own );
		}
	}
}
