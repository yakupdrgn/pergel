<?php
/**
 * The "Unimart" shop preset.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Labels;
use Pergel\Settings\Schema;
use Pergel\Settings\Screen;
use Pergel\Settings\Settings;

/**
 * One click that sets the shop's settings to a layout close to the Unimart
 * electronics shop: filters on the left, boxed cards with their details,
 * the product page with pictures on the left and tabs below, related
 * products in a carousel, the bars of S6. Colours and fonts stay the site's
 * own (its tokens); nothing is copied from Unimart but the arrangement.
 *
 * The shop layout tab offers it in a box. Its button shows every setting it
 * would change, from what it is now to what it becomes; applying it keeps
 * the old values, so "Undo" puts back each one that still has the preset's
 * value. Categories and products keep their own choices. Lists, whose pages
 * the preset cannot make, are turned on only where their page is set.
 */
final class Preset {

	public const NAME = 'unimart';

	/**
	 * What applying it kept for Undo: when, and the old values by group
	 * and key.
	 */
	public const UNDO = 'pergel_preset_undo';

	/**
	 * Admin-post actions, also the nonce actions.
	 */
	public const APPLY = 'pergel_preset_apply';

	public const REVERT = 'pergel_preset_undo';

	/**
	 * Query argument of the preview, and of the note after a change.
	 */
	public const PREVIEW = 'pergel-preset';

	public const DONE = 'pergel-preset-done';

	/**
	 * Adds the admin-post handlers. Layout calls it in the admin.
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::APPLY, array( self::class, 'apply' ) );
		add_action( 'admin_post_' . self::REVERT, array( self::class, 'undo' ) );
	}

	/**
	 * The preset's values by group and key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function values(): array {
		$woo = array(
			'swatches'          => true,
			'sticky_cart'       => true,
			'bought_together'   => true,
			'bottom_bar'        => true,
			'shipping_mini'     => true,
			'shipping_cart'     => true,
			'shipping_product'  => true,
			'shipping_checkout' => true,
		);
		foreach ( array( 'wishlist', 'compare' ) as $kind ) {
			if ( '' !== Lists::page( $kind ) ) {
				$woo[ $kind ] = true;
			}
		}
		return array(
			'woo'  => $woo,
			'shop' => array(
				'columns'            => '3',
				'columns_tablet'     => '2',
				'columns_phone'      => '2',
				'per_page'           => 9,
				'per_page_menu'      => '9, 18, 36',
				'filters'            => 'left',
				'filters_fold'       => true,
				'filters_search'     => true,
				'filter_brand'       => true,
				'filter_rating'      => true,
				'view_switch'        => true,
				'category_strip'     => 'images',
				'card_style'         => 'boxed',
				'card_category'      => true,
				'card_rating'        => true,
				'card_discount'      => true,
				'card_hover'         => true,
				'card_countdown'     => true,
				'quick_view'         => true,
				'card_swatches'      => true,
				// The card look measured on the reference, in the site's colours.
				'card_gap'           => 24,
				'card_bg'            => 'base',
				'card_border'        => 2,
				'card_border_color'  => 'line',
				'card_radius'        => 12,
				'card_padding'       => 8,
				'card_hover_look'    => 'accent',
				'card_image_bg'      => 'surface',
				'card_image_radius'  => 8,
				'card_align'         => 'start',
				'card_title_size'    => 16,
				'card_title_weight'  => '500',
				'card_meta_color'    => 'accent',
				'card_button'        => 'outline',
				'card_button_shape'  => 'pill',
				'card_button_width'  => 'full',
				'card_button_height' => 38,
				'filter_bg'          => 'base',
				'filter_edge'        => 2,
				'filter_edge_color'  => 'line',
				'filter_radius'      => 12,
				'filter_padding'     => 12,
				'filter_head_size'   => 15,
				'filter_head_weight' => '700',
				'filter_text_size'   => 15,
				'filter_title'       => _x( 'Filter & Refine', 'filter box title of the Unimart layout', 'pergel-engine' ),
				'filter_icon'        => true,
				'filter_more'        => 'pill',
				'filter_input_size'  => 38,
				'filter_input_edge'  => 2,
				'filter_input_color' => 'line',
				'filter_counts'      => 'right',
				'filter_swatch'      => 18,
				'filter_star_size'   => 16,
				'control_radius'     => 6,
				'paging_shape'       => 'circle',
				'paging_size'        => 48,
				'paging_bg'          => 'surface',
				// The header as on the reference: a light top bar, the row
				// with the search, then the menu on an accent bar.
				'header_type'        => 'store',
				'header_top_bg'      => 'base',
				'header_top_color'   => 'contrast',
				'header_padding'     => 15,
				'search_radius'      => 6,
				'search_button'      => 'contrast',
				'header_bar_height'  => 60,
				'picture_bg'         => 'surface',
				'picture_radius'     => 8,
				'thumb_radius'       => 8,
				'thumb_active'       => 'accent',
				'title_size'         => 30,
				'title_weight'       => '700',
				'price_weight'       => '600',
				'price_color'        => 'accent',
				'buy_shape'          => 'pill',
				'buy_height'         => 50,
				'qty_shape'          => 'pill',
				'badge_new'          => 30,
				'badge_featured'     => true,
				'badge_sold_out'     => true,
				'product_layout'     => 'left',
				'product_thumbs'     => 'left',
				'product_sticky'     => true,
				'product_badges'     => true,
				'product_category'   => true,
				'product_brand'      => true,
				'product_discount'   => true,
				'product_stock'      => true,
				// The summary as on the reference: the short description under
				// the name, then rating and SKU, the price and buying; the
				// details list goes (the category is above the name).
				'summary_items'      => 'title,excerpt,rating,sku,price,buying,cta,also,facts,trust',
				'product_nav'        => true,
				'product_buy_now'    => true,
				'product_coupons'    => true,
				'product_share'      => true,
				'details_style'      => 'tabs',
				'details_phone'      => 'scroll',
				'details_features'   => true,
				'details_qa'         => true,
				'details_rating'     => true,
				'related_style'      => 'carousel',
				'related_count'      => 8,
				'related_compare'    => '4',
				'related_recent'     => 'show',
				// The sections as on the reference: folder tabs joined to a
				// white card on a light band, with recently viewed and
				// recommended products beside them.
				'details_side'       => 'both',
				'tab_look'           => 'folder',
				'tab_size'           => 14,
				'tab_weight'         => '500',
				'tab_active'         => 'accent',
				'tab_radius'         => 16,
				'tab_panel_bg'       => 'base',
				'tab_edge_color'     => 'line',
				'tab_padding'        => 32,
				'tab_area_bg'        => 'surface',
				// Boxes under the buying form: two similar products, the
				// categories, availability and type, and the payment line,
				// on a light background with 12 px corners.
				'also_count'         => 2,
				'facts_rows'         => 'category,stock,type',
				'box_bg'             => 'surface',
				'box_radius'         => 12,
				'box_padding'        => 16,
				// The related products in a white box with a light edge, the
				// sticky bar with the short description and an outlined pill.
				'related_bg'         => 'base',
				'related_edge'       => 'line',
				'related_radius'     => 16,
				'related_padding'    => 32,
				'sticky_bg'          => 'base',
				'sticky_padding'     => 16,
				'sticky_image'       => 84,
				'sticky_text'        => true,
				'sticky_button'      => 'outline',
				'sticky_shape'       => 'pill',
			),
		);
	}

	/**
	 * The settings the preset would change: group => key => [now, new].
	 *
	 * @return array<string, array<string, array{0: mixed, 1: mixed}>>
	 */
	public static function changes(): array {
		$all     = Settings::all();
		$changes = array();
		foreach ( self::values() as $group => $values ) {
			foreach ( $values as $key => $value ) {
				$now = $all[ $group ][ $key ] ?? null;
				if ( $now !== $value ) {
					$changes[ $group ][ $key ] = array( $now, $value );
				}
			}
		}
		return $changes;
	}

	/**
	 * Whether the shop layout tab shows the preview instead of the form.
	 */
	public static function previewing(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only shows the preview; applying it checks a nonce.
		return isset( $_GET[ self::PREVIEW ] ) && self::NAME === sanitize_key( wp_unslash( $_GET[ self::PREVIEW ] ) );
	}

	/**
	 * The box above the shop layout settings: the note after a change, the
	 * link to the preview and, after applying, the Undo button.
	 */
	public static function box(): string {
		$html = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks the note to show.
		$done = isset( $_GET[ self::DONE ] ) ? sanitize_key( wp_unslash( $_GET[ self::DONE ] ) ) : '';
		if ( 'applied' === $done || 'undone' === $done ) {
			$html .= '<div class="notice notice-success inline"><p>' . esc_html( 'applied' === $done ? __( 'The Unimart layout is on. Undo puts back the settings it changed.', 'pergel-engine' ) : __( 'The settings are back as they were.', 'pergel-engine' ) ) . '</p></div>';
		}
		$undo  = get_option( self::UNDO );
		$html .= '<div class="card pergel-preset" style="max-width:none">'
			. '<h2 class="title">' . esc_html__( 'Unimart layout', 'pergel-engine' ) . '</h2>'
			. '<p>' . esc_html__( 'Sets the shop in one go to a layout close to the Unimart electronics shop: filters on the left, boxed cards with their details, pictures on the left and tabs below on product pages, related products in a carousel, the bar on phones and the free shipping progress. Colours and fonts stay your site\'s. You see every change before it is made.', 'pergel-engine' ) . '</p>'
			. '<p><a class="button" href="' . esc_url( self::url( array( self::PREVIEW => self::NAME ) ) ) . '">' . esc_html__( 'Preview the changes', 'pergel-engine' ) . '</a>';
		if ( is_array( $undo ) && is_array( $undo['values'] ?? null ) ) {
			$html .= ' ' . self::form( self::REVERT, __( 'Undo the Unimart layout', 'pergel-engine' ), 'button' );
		}
		return $html . '</p></div>';
	}

	/**
	 * The preview: every setting the preset changes, now and after.
	 */
	public static function preview(): string {
		$changes = self::changes();
		$back    = '<a class="button" href="' . esc_url( self::url() ) . '">' . esc_html__( 'Back to the settings', 'pergel-engine' ) . '</a>';
		$html    = '<h2>' . esc_html__( 'Unimart layout: the changes', 'pergel-engine' ) . '</h2>';
		if ( array() === $changes ) {
			return $html . '<p>' . esc_html__( 'The settings already match the Unimart layout.', 'pergel-engine' ) . '</p><p>' . $back . '</p>';
		}
		$labels = Labels::fields();
		$rows   = '';
		foreach ( $changes as $group => $fields ) {
			foreach ( $fields as $key => [ $now, $new ] ) {
				$text  = $labels[ $group ][ $key ] ?? array();
				$rows .= '<tr><td>' . esc_html( Screen::page_title( $group, $key ) ) . '</td><th scope="row">' . esc_html( (string) ( $text['label'] ?? $key ) ) . '</th>'
					. '<td>' . esc_html( self::shown( $now, $text ) ) . '</td><td><strong>' . esc_html( self::shown( $new, $text ) ) . '</strong></td></tr>';
			}
		}
		$count = array_sum( array_map( 'count', $changes ) );
		return $html
			/* translators: %d: number of settings. */
			. '<p>' . esc_html( sprintf( _n( 'Applying it changes %d setting. Product categories and products keep their own choices.', 'Applying it changes %d settings. Product categories and products keep their own choices.', $count, 'pergel-engine' ), $count ) ) . '</p>'
			. '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Section', 'pergel-engine' ) . '</th><th scope="col">' . esc_html__( 'Setting', 'pergel-engine' ) . '</th><th scope="col">' . esc_html__( 'Now', 'pergel-engine' ) . '</th><th scope="col">' . esc_html__( 'After', 'pergel-engine' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
			. '<p>' . self::form( self::APPLY, __( 'Apply the Unimart layout', 'pergel-engine' ), 'button button-primary' ) . ' ' . $back . '</p>';
	}

	/**
	 * A value as the settings screen names it.
	 *
	 * @param mixed                $value Value.
	 * @param array<string, mixed> $text  Labels of the field.
	 */
	private static function shown( mixed $value, array $text ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'On', 'pergel-engine' ) : __( 'Off', 'pergel-engine' );
		}
		$choices = is_array( $text['choices'] ?? null ) ? $text['choices'] : array();
		if ( ( is_string( $value ) || is_int( $value ) ) && isset( $choices[ $value ] ) ) {
			return (string) $choices[ $value ];
		}
		if ( null === $value || '' === $value ) {
			return isset( $text['items'] ) ? __( 'As in the template', 'pergel-engine' ) : '—';
		}
		if ( isset( $text['items'] ) && is_string( $value ) ) {
			// An item list: the item names, in order.
			return implode( ' · ', array_map( static fn ( string $key ): string => (string) ( $choices[ $key ] ?? $key ), explode( ',', $value ) ) );
		}
		return is_scalar( $value ) ? (string) $value : '—';
	}

	/**
	 * A small form that posts to admin-post with a nonce.
	 *
	 * @param string $action Action, also the nonce action.
	 * @param string $label  Button text.
	 * @param string $classes Button classes.
	 */
	private static function form( string $action, string $label, string $classes ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'
			. '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">'
			. wp_nonce_field( $action, '_wpnonce', true, false )
			. '<button type="submit" class="' . esc_attr( $classes ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * POST admin-post.php?action=pergel_preset_apply: applies the preset and
	 * keeps the old values.
	 */
	public static function apply(): void {
		self::check( self::APPLY );
		self::run();
		self::back( 'applied' );
	}

	/**
	 * Applies the preset and keeps the old values for Undo.
	 */
	public static function run(): void {
		$changes = self::changes();
		if ( array() !== $changes ) {
			$old = array();
			$new = array();
			foreach ( $changes as $group => $fields ) {
				foreach ( $fields as $key => [ $now, $value ] ) {
					$old[ $group ][ $key ] = $now;
					$new[ $group ][ $key ] = $value;
				}
			}
			// A second apply keeps the values from before the first one.
			$kept = get_option( self::UNDO );
			if ( is_array( $kept ) && is_array( $kept['values'] ?? null ) ) {
				$old = array_replace_recursive( $old, $kept['values'] );
			}
			update_option(
				self::UNDO,
				array(
					'time'   => time(),
					'values' => $old,
				),
				false
			);
			Settings::update( $new );
		}
	}

	/**
	 * POST admin-post.php?action=pergel_preset_undo: puts back the old value
	 * of every setting that still has the preset's value.
	 */
	public static function undo(): void {
		self::check( self::REVERT );
		self::revert();
		self::back( 'undone' );
	}

	/**
	 * Puts back the old values and forgets them.
	 */
	public static function revert(): void {
		$kept = get_option( self::UNDO );
		if ( is_array( $kept ) && is_array( $kept['values'] ?? null ) ) {
			Settings::update( self::reverted( $kept['values'] ) );
		}
		delete_option( self::UNDO );
	}

	/**
	 * The old values of the settings that still have the preset's value;
	 * ones changed by hand since then stay as they are.
	 *
	 * @param array<mixed> $old Old values by group and key.
	 * @return array<string, array<string, mixed>>
	 */
	public static function reverted( array $old ): array {
		$all      = Settings::all();
		$defaults = Schema::defaults();
		$preset   = self::values();
		$back     = array();
		foreach ( $old as $group => $fields ) {
			if ( ! is_string( $group ) || ! is_array( $fields ) || ! isset( $defaults[ $group ] ) ) {
				continue;
			}
			foreach ( $fields as $key => $value ) {
				if ( is_string( $key ) && array_key_exists( $key, $defaults[ $group ] ) && ( $all[ $group ][ $key ] ?? null ) === ( $preset[ $group ][ $key ] ?? null ) ) {
					$back[ $group ][ $key ] = $value;
				}
			}
		}
		return $back;
	}

	/**
	 * Stops unless the request may change the settings.
	 *
	 * @param string $action Nonce action.
	 */
	private static function check( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'pergel-engine' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Goes back to the Ready-made layouts page with a note.
	 *
	 * @param string $done applied or undone.
	 */
	private static function back( string $done ): void {
		wp_safe_redirect( self::url( array( self::DONE => $done ) ) );
		exit;
	}

	/**
	 * The "Ready-made layouts" page of the Shop settings, with extra query
	 * arguments.
	 *
	 * @param array<string, string> $args Query arguments.
	 */
	private static function url( array $args = array() ): string {
		return Screen::url( 'presets', $args );
	}
}
