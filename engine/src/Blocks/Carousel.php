<?php
/**
 * The carousel block.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

/**
 * A row of slides that scrolls sideways and snaps to each slide. Without
 * JavaScript it is a scrollable row (touch, trackpad, or the arrow keys once
 * the row has focus); the view script adds previous / next buttons and
 * dots. It never moves on its own. Marked up as the WAI-ARIA carousel
 * pattern: a labelled region of slides that each say "2 of 5".
 */
final class Carousel {

	public const MAX_PER_VIEW = 4;

	/**
	 * Block markup; empty without slides.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Rendered slides.
	 */
	public static function render( array $attributes, string $content ): string {
		$slides = self::label_slides( $content );
		if ( 0 === $slides['count'] ) {
			return '';
		}
		$count    = $slides['count'];
		$per_view = is_int( $attributes['perView'] ?? null ) ? max( 1, min( self::MAX_PER_VIEW, $attributes['perView'] ) ) : 1;
		$label    = is_string( $attributes['label'] ?? null ) && '' !== trim( $attributes['label'] ) ? trim( $attributes['label'] ) : __( 'Carousel', 'pergel-engine' );
		$id       = wp_unique_id( 'pergel-carousel-' );
		$track    = $id . '-track';
		$context  = array(
			'index'   => 0,
			'count'   => $count,
			'visible' => $per_view,
			'ready'   => false,
			'atStart' => true,
			'atEnd'   => $count <= $per_view,
			'dots'    => false !== ( $attributes['dots'] ?? true ),
		);

		$dots = '';
		for ( $i = 0; $i < $count; $i++ ) {
			$dots .= '<button type="button" class="wp-block-pergel-carousel__dot" aria-controls="' . esc_attr( $track ) . '" '
				. wp_interactivity_data_wp_context( array( 'slide' => $i ) )
				/* translators: %d: slide number. */
				. ' aria-label="' . esc_attr( sprintf( __( 'Go to slide %d', 'pergel-engine' ), $i + 1 ) ) . '"'
				. ' data-wp-on--click="actions.go" data-wp-bind--aria-current="state.current" data-wp-bind--hidden="state.dotHidden"></button>';
		}

		$wrapper = get_block_wrapper_attributes(
			array(
				'class'                => $per_view > 1 ? 'has-several-per-view' : '',
				'style'                => '--pergel-carousel-per-view:' . $per_view . ';',
				'aria-roledescription' => __( 'carousel', 'pergel-engine' ),
				'aria-label'           => $label,
			)
		);

		return '<section ' . $wrapper . ' data-wp-interactive="pergel/carousel" ' . wp_interactivity_data_wp_context( $context ) . ' data-wp-init="callbacks.init">'
			. '<div class="wp-block-pergel-carousel__track" id="' . esc_attr( $track ) . '" tabindex="0" data-wp-on-async--scroll="callbacks.update">' . $slides['html'] . '</div>'
			. '<div class="wp-block-pergel-carousel__nav" hidden data-wp-bind--hidden="!context.ready">'
			. self::button( 'prev', $track, __( 'Previous slide', 'pergel-engine' ), 'context.atStart' )
			. '<div class="wp-block-pergel-carousel__dots" hidden data-wp-bind--hidden="!state.showDots">' . $dots . '</div>'
			. self::button( 'next', $track, __( 'Next slide', 'pergel-engine' ), 'context.atEnd' )
			. '</div>'
			. '</section>';
	}

	/**
	 * Marks each slide of this carousel as a group that says which slide it
	 * is. Slides of a carousel inside a slide already have their label and
	 * are left alone.
	 *
	 * @param string $content Rendered slides.
	 * @return array{html: string, count: int}
	 */
	public static function label_slides( string $content ): array {
		$tags  = new \WP_HTML_Tag_Processor( $content );
		$found = 0;
		while ( $tags->next_tag( array( 'class_name' => 'wp-block-pergel-carousel-slide' ) ) ) {
			if ( null === $tags->get_attribute( 'aria-roledescription' ) ) {
				$tags->set_bookmark( 'slide-' . $found );
				++$found;
			}
		}
		$total = $found;
		for ( $i = 0; $i < $total; $i++ ) {
			$tags->seek( 'slide-' . $i );
			$tags->set_attribute( 'role', 'group' );
			$tags->set_attribute( 'aria-roledescription', __( 'slide', 'pergel-engine' ) );
			/* translators: 1: slide number, 2: number of slides. */
			$tags->set_attribute( 'aria-label', sprintf( __( '%1$d of %2$d', 'pergel-engine' ), $i + 1, $total ) );
			$tags->release_bookmark( 'slide-' . $i );
		}
		return array(
			'html'  => $tags->get_updated_html(),
			'count' => $total,
		);
	}

	/**
	 * A previous or next button.
	 *
	 * @param string $which    prev or next.
	 * @param string $track    Track id.
	 * @param string $label    Accessible name.
	 * @param string $disabled Context path that disables it.
	 */
	private static function button( string $which, string $track, string $label, string $disabled ): string {
		$path = 'prev' === $which ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7';
		return '<button type="button" class="wp-block-pergel-carousel__' . $which . '" aria-controls="' . esc_attr( $track ) . '" aria-label="' . esc_attr( $label ) . '"'
			. ' data-wp-on--click="actions.' . $which . '" data-wp-bind--aria-disabled="' . esc_attr( $disabled ) . '">'
			. '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="' . $path . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			. '</button>';
	}
}
