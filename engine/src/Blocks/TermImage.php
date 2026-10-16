<?php
/**
 * Term image block.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

use WP_Block;
use WP_Term;

/**
 * Draws the image of the term a Terms Query is showing. The image is the
 * attachment in the term's `thumbnail_id` meta, where WooCommerce keeps a
 * product category's image; other taxonomies can supply one through the
 * `pergel/term_image_id` filter. The image repeats the term name next to it,
 * so it has empty alternative text and its link is left out of the tab order.
 */
final class TermImage {

	/**
	 * Shapes the block offers.
	 */
	private const RATIOS = array( '1', '4/3', '3/4', '16/9' );

	/**
	 * The block's markup; empty outside a term.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param WP_Block             $block      Block with termId and taxonomy context.
	 */
	public static function render( array $attributes, WP_Block $block ): string {
		$term = get_term( (int) ( $block->context['termId'] ?? 0 ), (string) ( $block->context['taxonomy'] ?? '' ) );
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		$ratio = in_array( $attributes['aspectRatio'] ?? '', self::RATIOS, true ) ? (string) $attributes['aspectRatio'] : '4/3';
		$style = 'aspect-ratio:' . $ratio;

		/**
		 * Filters the attachment shown for a term.
		 *
		 * @param int     $image_id Attachment ID; 0 for none.
		 * @param WP_Term $term     The term.
		 */
		$image_id = (int) apply_filters( 'pergel/term_image_id', (int) get_term_meta( $term->term_id, 'thumbnail_id', true ), $term );
		$image    = $image_id > 0 ? wp_get_attachment_image(
			$image_id,
			'medium_large',
			false,
			array(
				'alt'   => '',
				'style' => $style,
				'sizes' => '(max-width: 600px) 100vw, 400px',
			)
		) : '';
		if ( '' === $image ) {
			$image = '<span class="wp-block-pergel-term-image__placeholder" style="' . esc_attr( $style ) . '"></span>';
		}

		$link = get_term_link( $term );
		if ( false !== ( $attributes['isLink'] ?? true ) && is_string( $link ) ) {
			$image = '<a href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">' . $image . '</a>';
		}
		return '<figure ' . get_block_wrapper_attributes() . '>' . $image . '</figure>';
	}
}
