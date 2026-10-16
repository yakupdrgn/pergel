<?php
/**
 * Size guide.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Settings;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * A "Size guide" button above the add-to-cart form that opens the guide in
 * a dialog.
 *
 * The guide is a synced pattern (Appearance → Editor → Patterns), so a
 * table, images and text are edited with the block editor and one guide
 * serves many products. A product category chooses its guide, which its
 * subcategories and products follow; a product can choose another guide
 * or none. The closest category wins; between categories as close, the
 * lower id.
 *
 * The dialog is a <dialog> opened by an invoker command (commandfor), so it
 * needs no script in current browsers: Esc and a click outside close it,
 * and focus returns to the button. The small script stands in for older
 * browsers. The block (pergel/size-guide) is hooked before the add-to-cart
 * form; on a product without a guide it draws nothing and loads nothing.
 */
final class SizeGuide {

	public const BLOCK = 'pergel/size-guide';

	/**
	 * Post meta of a product and term meta of a product category: a
	 * pattern id, or (only on a product) "none".
	 */
	public const META = 'pergel_size_guide';

	/**
	 * Handles of the style and the script module.
	 */
	private const ASSET = 'pergel-size-guide';

	/**
	 * Adds the hooks. Woo\Module calls it when the setting is on.
	 */
	public static function register(): void {
		add_filter( 'hooked_block_types', array( self::class, 'hook' ), 22, 4 );
		add_action( 'init', array( self::class, 'meta' ) );
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'product_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_product' ) );
		add_action( 'product_cat_add_form_fields', array( self::class, 'category_add' ) );
		add_action( 'product_cat_edit_form_fields', array( self::class, 'category_edit' ) );
		add_action( 'created_product_cat', array( self::class, 'save_category' ) );
		add_action( 'edited_product_cat', array( self::class, 'save_category' ) );
	}

	/**
	 * Filter for hooked_block_types: the button before the add-to-cart
	 * form, so it sits right above the options; not where it is placed by
	 * hand.
	 *
	 * @param mixed $hooked   Block types hooked at this position.
	 * @param mixed $position Relative position.
	 * @param mixed $anchor   Anchor block type.
	 * @param mixed $context  Pattern data, template or post the blocks are in.
	 * @return mixed
	 */
	public static function hook( mixed $hooked, mixed $position, mixed $anchor, mixed $context ): mixed {
		if ( ! is_array( $hooked ) || 'before' !== $position || 'woocommerce/add-to-cart-with-options' !== $anchor ) {
			return $hooked;
		}
		if ( ! str_contains( self::content( $context ), 'wp:' . self::BLOCK ) ) {
			$hooked[] = self::BLOCK;
		}
		return $hooked;
	}

	/**
	 * The button and the dialog. The style and the script load only here.
	 *
	 * @param \WP_Block|null $block Block instance.
	 */
	public static function render( ?\WP_Block $block = null ): string {
		if ( wp_is_serving_rest_request() ) {
			return '<div ' . get_block_wrapper_attributes() . '><p>' . esc_html__( 'Shown on product pages that have a size guide: a button that opens the guide.', 'pergel-engine' ) . '</p></div>';
		}
		if ( true !== Settings::get( 'woo.size_guide' ) ) {
			return '';
		}
		$id      = $block instanceof \WP_Block && is_int( $block->context['postId'] ?? null ) ? $block->context['postId'] : (int) get_the_ID();
		$product = $id > 0 && 'product' === get_post_type( $id ) ? wc_get_product( $id ) : null;
		$guide   = $product instanceof \WC_Product ? self::guide( $product ) : null;
		if ( ! $guide instanceof \WP_Post ) {
			return '';
		}
		$content = render_block(
			array(
				'blockName'    => 'core/block',
				'attrs'        => array( 'ref' => $guide->ID ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		if ( '' === trim( $content ) ) {
			return '';
		}
		wp_enqueue_style( self::ASSET, plugins_url( 'blocks/size-guide/style.css', FILE ), array(), VERSION );
		wp_enqueue_script_module( self::ASSET, plugins_url( 'blocks/size-guide/view.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );

		$dialog = 'pergel-size-guide-' . $product->get_id();
		$title  = '' !== trim( $guide->post_title ) ? $guide->post_title : __( 'Size guide', 'pergel-engine' );
		$icon   = '<svg class="wp-block-pergel-size-guide__icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M2 8.5A1.5 1.5 0 0 1 3.5 7h17A1.5 1.5 0 0 1 22 8.5v7a1.5 1.5 0 0 1-1.5 1.5h-17A1.5 1.5 0 0 1 2 15.5v-7Zm1.5 0v7h17v-7H18v3h-1.5v-3h-2v2H13v-2h-2v3H9.5v-3h-2v2H6v-2H3.5Z"/></svg>';
		$close  = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="m12 10.94 5.47-5.47 1.06 1.06L13.06 12l5.47 5.47-1.06 1.06L12 13.06l-5.47 5.47-1.06-1.06L10.94 12 5.47 6.53l1.06-1.06L12 10.94Z"/></svg>';

		return '<div ' . get_block_wrapper_attributes( array( 'data-pergel-size-guide' => '' ) ) . '>'
			. '<button type="button" class="wp-block-pergel-size-guide__open" commandfor="' . esc_attr( $dialog ) . '" command="show-modal" aria-haspopup="dialog">'
			. $icon . '<span>' . esc_html__( 'Size guide', 'pergel-engine' ) . '</span></button>'
			. '<dialog id="' . esc_attr( $dialog ) . '" class="wp-block-pergel-size-guide__dialog" aria-labelledby="' . esc_attr( $dialog ) . '-title" closedby="any">'
			. '<div class="wp-block-pergel-size-guide__panel">'
			. '<div class="wp-block-pergel-size-guide__header">'
			. '<h2 id="' . esc_attr( $dialog ) . '-title" class="wp-block-pergel-size-guide__title">' . esc_html( $title ) . '</h2>'
			. '<button type="button" class="wp-block-pergel-size-guide__close" commandfor="' . esc_attr( $dialog ) . '" command="close" aria-label="' . esc_attr__( 'Close', 'pergel-engine' ) . '">' . $close . '</button>'
			. '</div>'
			. '<div class="wp-block-pergel-size-guide__content">' . $content . '</div>'
			. '</div></dialog></div>';
	}

	/**
	 * The guide a product shows: its own choice, else its closest
	 * category's.
	 *
	 * @param \WC_Product $product Product (a variation counts as its parent).
	 */
	public static function guide( \WC_Product $product ): ?\WP_Post {
		$id  = $product->get_parent_id() > 0 ? $product->get_parent_id() : $product->get_id();
		$own = self::sanitize( get_post_meta( $id, self::META, true ), true );
		if ( 'none' === $own ) {
			return null;
		}
		if ( '' !== $own ) {
			$guide = self::pattern( (int) $own );
			if ( null !== $guide ) {
				return $guide;
			}
		}
		$best  = null;
		$terms = wc_get_product_term_ids( $id, 'product_cat' );
		sort( $terms );
		foreach ( $terms as $term ) {
			foreach ( array_merge( array( $term ), get_ancestors( $term, 'product_cat', 'taxonomy' ) ) as $distance => $category ) {
				if ( null !== $best && $distance >= $best[0] ) {
					break;
				}
				$guide = self::pattern( (int) self::sanitize( get_term_meta( (int) $category, self::META, true ), false ) );
				if ( null !== $guide ) {
					$best = array( $distance, $guide );
					break;
				}
			}
		}
		return null !== $best ? $best[1] : null;
	}

	/**
	 * A published synced pattern.
	 *
	 * @param int $id Pattern id.
	 */
	private static function pattern( int $id ): ?\WP_Post {
		$post = $id > 0 ? get_post( $id ) : null;
		return $post instanceof \WP_Post && 'wp_block' === $post->post_type && 'publish' === $post->post_status && '' === $post->post_password ? $post : null;
	}

	/**
	 * A stored choice: a pattern id, "none" (products only) or ''.
	 *
	 * @param mixed $value   Stored or posted value.
	 * @param bool  $product Whether "none" is allowed.
	 */
	public static function sanitize( mixed $value, bool $product = false ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );
		if ( 'none' === $value ) {
			return $product ? 'none' : '';
		}
		return ctype_digit( $value ) && (int) $value > 0 ? (string) (int) $value : '';
	}

	/**
	 * Registers the meta keys with their sanitize callback.
	 */
	public static function meta(): void {
		$args = array(
			'type'         => 'string',
			'single'       => true,
			'default'      => '',
			'show_in_rest' => false,
		);
		register_post_meta(
			'product',
			self::META,
			$args + array(
				'sanitize_callback' => static fn( mixed $value ): string => self::sanitize( $value, true ),
				'auth_callback'     => static fn(): bool => current_user_can( 'edit_products' ),
			)
		);
		register_term_meta(
			'product_cat',
			self::META,
			$args + array(
				'sanitize_callback' => static fn( mixed $value ): string => self::sanitize( $value ),
				'auth_callback'     => static fn(): bool => current_user_can( 'manage_product_terms' ),
			)
		);
	}

	/**
	 * Published synced patterns, by title.
	 *
	 * @return array<int, string> Pattern titles by id.
	 */
	private static function patterns(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'wp_block',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'has_password'   => false,
			)
		);
		$list  = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				/* translators: %d: pattern id. */
				$list[ $post->ID ] = '' !== trim( $post->post_title ) ? $post->post_title : sprintf( __( 'Pattern %d', 'pergel-engine' ), $post->ID );
			}
		}
		return $list;
	}

	/**
	 * The select box in the product data.
	 */
	public static function product_field(): void {
		global $product_object;
		$value = $product_object instanceof \WC_Product ? self::sanitize( $product_object->get_meta( self::META ), true ) : '';

		echo '<div class="options_group">';
		woocommerce_wp_select(
			array(
				'id'          => self::META,
				'label'       => __( 'Size guide', 'pergel-engine' ),
				'options'     => array(
					''     => __( 'Same as its categories', 'pergel-engine' ),
					'none' => __( 'No size guide', 'pergel-engine' ),
				) + self::patterns(),
				'value'       => $value,
				'desc_tip'    => true,
				'description' => __( 'A synced pattern (Appearance → Editor → Patterns) shown in a window from a button above the add-to-cart button.', 'pergel-engine' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Saves the product's choice. WooCommerce has checked the nonce and the
	 * capability.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save_product( \WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by WooCommerce before this action.
		$value = self::sanitize( isset( $_POST[ self::META ] ) ? sanitize_key( wp_unslash( $_POST[ self::META ] ) ) : '', true );
		if ( '' === $value ) {
			$product->delete_meta_data( self::META );
		} else {
			$product->update_meta_data( self::META, $value );
		}
	}

	/**
	 * The select box in the "Add new category" form.
	 */
	public static function category_add(): void {
		printf(
			'<div class="form-field"><label for="%1$s">%2$s</label>%3$s<p>%4$s</p></div>',
			esc_attr( self::META ),
			esc_html__( 'Size guide', 'pergel-engine' ),
			self::select( '' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in select().
			esc_html( self::category_help() )
		);
	}

	/**
	 * The select box in the category edit form.
	 *
	 * @param mixed $term Category being edited.
	 */
	public static function category_edit( mixed $term ): void {
		$value = $term instanceof \WP_Term ? self::sanitize( get_term_meta( $term->term_id, self::META, true ) ) : '';
		printf(
			'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td>%3$s<p class="description">%4$s</p></td></tr>',
			esc_attr( self::META ),
			esc_html__( 'Size guide', 'pergel-engine' ),
			self::select( $value ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in select().
			esc_html( self::category_help() )
		);
	}

	/**
	 * Help text of the category field.
	 */
	private static function category_help(): string {
		return __( 'A synced pattern (Appearance → Editor → Patterns) for the products of this category and its subcategories, unless a subcategory or a product chooses its own.', 'pergel-engine' );
	}

	/**
	 * Select box HTML for the category forms.
	 *
	 * @param string $value Current pattern id; '' for none.
	 */
	private static function select( string $value ): string {
		$html = sprintf( '<select id="%1$s" name="%1$s">', esc_attr( self::META ) );
		foreach ( array( '' => __( 'None', 'pergel-engine' ) ) + self::patterns() as $id => $label ) {
			$html .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $id ), selected( $value, (string) $id, false ), esc_html( $label ) );
		}
		return $html . '</select>';
	}

	/**
	 * Saves a category's guide. WordPress has checked the form nonce.
	 *
	 * @param int $term_id Category id.
	 */
	public static function save_category( int $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by WordPress before the term is saved.
		if ( ! isset( $_POST[ self::META ] ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- As above.
		$value = self::sanitize( sanitize_key( wp_unslash( $_POST[ self::META ] ) ) );
		if ( '' === $value ) {
			delete_term_meta( $term_id, self::META );
		} else {
			update_term_meta( $term_id, self::META, $value );
		}
	}

	/**
	 * Markup of the hook context: a pattern's content, a template's or a
	 * post's.
	 *
	 * @param mixed $context Hook context.
	 */
	private static function content( mixed $context ): string {
		if ( is_array( $context ) ) {
			return (string) ( $context['content'] ?? '' );
		}
		if ( $context instanceof \WP_Block_Template ) {
			return (string) $context->content;
		}
		return $context instanceof \WP_Post ? $context->post_content : '';
	}
}
