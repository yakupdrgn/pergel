<?php
/**
 * The mega menu block.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

/**
 * A menu item of the Navigation block that opens a wide panel. The panel
 * is a template part of its own area ("Mega menu"), so it is built and
 * edited with blocks like the header. A click opens it; Escape, a click
 * outside or moving focus out closes it. Without JavaScript it opens while
 * the pointer or the focus is in it. In the mobile overlay menu it opens
 * in place, under its item.
 */
final class MegaMenu {

	public const AREA = 'pergel-mega-menu';

	public const DEFAULT_PART = 'mega-menu';

	/**
	 * Hooks the block into the Navigation block and adds the area.
	 */
	public static function register(): void {
		add_filter( 'default_wp_template_part_areas', array( self::class, 'area' ) );
		add_filter( 'register_block_type_args', array( self::class, 'allow' ), 10, 2 );
	}

	/**
	 * Adds the Mega menu area for template parts.
	 *
	 * @param mixed $areas Areas from core and other plugins.
	 * @return mixed
	 */
	public static function area( mixed $areas ): mixed {
		if ( ! is_array( $areas ) || in_array( self::AREA, array_column( $areas, 'area' ), true ) ) {
			return $areas;
		}
		$areas[] = array(
			'area'        => self::AREA,
			'label'       => __( 'Mega menu', 'pergel-engine' ),
			'description' => __( 'The wide panel that a Mega menu item of the navigation opens.', 'pergel-engine' ),
			'icon'        => 'layout',
			'area_tag'    => 'div',
		);
		return $areas;
	}

	/**
	 * Lets the Navigation block hold the mega menu block.
	 *
	 * @param mixed $args Block type arguments.
	 * @param mixed $name Block name.
	 * @return mixed
	 */
	public static function allow( mixed $args, mixed $name ): mixed {
		if ( 'core/navigation' !== $name || ! is_array( $args ) || ! is_array( $args['allowed_blocks'] ?? null ) ) {
			return $args;
		}
		if ( ! in_array( 'pergel/mega-menu', $args['allowed_blocks'], true ) ) {
			$args['allowed_blocks'][] = 'pergel/mega-menu';
		}
		return $args;
	}

	/**
	 * Block markup; empty when the template part does not exist or shows
	 * nothing (no text and no media), so the menu never opens an empty box.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public static function render( array $attributes ): string {
		$slug = sanitize_title( is_string( $attributes['menu'] ?? null ) ? $attributes['menu'] : '' );
		if ( '' === $slug || null === get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' ) ) {
			return '';
		}
		$panel = render_block(
			array(
				'blockName'    => 'core/template-part',
				'attrs'        => array(
					'slug'    => $slug,
					'theme'   => get_stylesheet(),
					'tagName' => 'div',
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		if ( '' === trim( wp_strip_all_tags( $panel ) ) && ! preg_match( '/<(?:img|svg|video|iframe|picture)\b/i', $panel ) ) {
			return '';
		}
		$label = is_string( $attributes['label'] ?? null ) && '' !== trim( $attributes['label'] ) ? trim( $attributes['label'] ) : __( 'Menu', 'pergel-engine' );
		$id    = wp_unique_id( 'pergel-mega-menu-' );

		$wrapper = get_block_wrapper_attributes( array( 'class' => 'wp-block-navigation-item has-child' ) );

		return '<li ' . $wrapper . ' data-wp-interactive="pergel/mega-menu" ' . wp_interactivity_data_wp_context(
			array(
				'open'  => false,
				'ready' => false,
			)
		) . ' data-wp-init="callbacks.init" data-wp-class--is-open="context.open" data-wp-class--is-ready="context.ready"'
			. ' data-wp-on--keydown="actions.keydown" data-wp-on--focusout="actions.focusout" data-wp-on-document--click="actions.outside">'
			. '<button type="button" class="wp-block-navigation-item__content wp-block-pergel-mega-menu__toggle" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '"'
			. ' data-wp-bind--aria-expanded="context.open" data-wp-on--click="actions.toggle">'
			. '<span class="wp-block-navigation-item__label">' . esc_html( $label ) . '</span>'
			. '<span class="wp-block-pergel-mega-menu__icon" aria-hidden="true"><svg viewBox="0 0 12 12" width="12" height="12" focusable="false"><path d="M1.5 4L6 8l4.5-4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg></span>'
			. '</button>'
			. '<div class="wp-block-pergel-mega-menu__panel" id="' . esc_attr( $id ) . '">' . $panel . '</div>'
			. '</li>';
	}
}
