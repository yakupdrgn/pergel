<?php
/**
 * The logo marquee block.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

/**
 * A row of logos that moves sideways. The row is printed twice so the loop
 * has no gap; the copy is hidden from screen readers and keyboards. A pause
 * button (WCAG 2.2.2) appears once the view script runs; reduced motion
 * shows the logos still.
 */
final class Marquee {

	/**
	 * Default seconds for one loop.
	 */
	public const DURATION = 30;

	/**
	 * Block markup.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Rendered inner blocks (images).
	 */
	public static function render( array $attributes, string $content ): string {
		if ( '' === trim( $content ) ) {
			return '';
		}
		$duration = is_int( $attributes['duration'] ?? null ) ? max( 5, min( 120, $attributes['duration'] ) ) : self::DURATION;
		$wrapper  = get_block_wrapper_attributes( array( 'style' => '--pergel-marquee-duration:' . $duration . 's' ) );
		return '<div ' . $wrapper . '>'
			. '<div class="wp-block-pergel-logo-marquee__track">'
			. '<div class="wp-block-pergel-logo-marquee__group">' . $content . '</div>'
			. '<div class="wp-block-pergel-logo-marquee__group" aria-hidden="true" inert>' . self::copy( $content ) . '</div>'
			. '</div>'
			. '<button type="button" class="wp-block-pergel-logo-marquee__toggle" aria-pressed="false" aria-label="' . esc_attr__( 'Pause the moving logos', 'pergel-engine' ) . '" hidden>' . esc_html__( 'Pause', 'pergel-engine' ) . '</button>'
			. '</div>';
	}

	/**
	 * The second row: the same markup without id attributes, which must
	 * stay unique on the page.
	 *
	 * @param string $content Rendered inner blocks.
	 */
	public static function copy( string $content ): string {
		$tags = new \WP_HTML_Tag_Processor( $content );
		while ( $tags->next_tag() ) {
			$tags->remove_attribute( 'id' );
		}
		return $tags->get_updated_html();
	}
}
