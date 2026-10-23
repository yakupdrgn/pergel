<?php
/**
 * The Pergel settings screen.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Settings;

use Pergel\Core\Svg;
use Pergel\Seo\Detect;
use Pergel\Woo\Layout;
use Pergel\Woo\Module as Woo;
use Pergel\Woo\Preset;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * One admin page, one tab per schema group, plain PHP form posted to
 * options.php. Fields are generated from Schema; Labels only adds text.
 * Loads in wp-admin only.
 *
 * @phpstan-import-type Field from Schema
 */
final class Screen {

	public const SLUG = 'pergel';

	/**
	 * Adds the admin hooks.
	 */
	public static function register(): void {
		// Before the post type submenus (priority 10), so Settings stays the first item.
		add_action( 'admin_menu', array( self::class, 'menu' ), 9 );
		add_action( 'pergel/settings/invalid', array( self::class, 'report' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FILE ), array( self::class, 'action_links' ) );
	}

	/**
	 * Hooked on admin_menu.
	 */
	public static function menu(): void {
		$hook = add_menu_page(
			__( 'Pergel settings', 'pergel-engine' ),
			'Pergel',
			'manage_options',
			self::SLUG,
			array( self::class, 'render' ),
			self::icon(),
			61
		);
		add_submenu_page( self::SLUG, __( 'Pergel settings', 'pergel-engine' ), __( 'Settings', 'pergel-engine' ), 'manage_options', self::SLUG, array( self::class, 'render' ) );
		add_action( "load-$hook", array( self::class, 'load' ) );
	}

	/**
	 * Runs only when the settings page is opened.
	 */
	public static function load(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Media library for image fields, plus a few lines of CSS. No build step.
	 */
	public static function enqueue(): void {
		wp_enqueue_media();
		wp_enqueue_script(
			'pergel-settings',
			plugins_url( 'assets/settings.js', FILE ),
			array(),
			VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_add_inline_style(
			'common',
			'.pergel-settings .form-table input.small-text{text-transform:uppercase}'
			. '.pergel-settings__error{color:#b32d2e;margin:4px 0 0}'
			. '.pergel-image__preview{margin-bottom:8px}'
			. '.pergel-image__preview:empty,.pergel-image__remove[hidden]{display:none}'
			. '.pergel-image__preview img{display:block;max-width:240px;height:auto;padding:12px;background:#1d2327;border-radius:4px}'
			. '.pergel-choices{display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:0;border:0}'
			// Beats core's ".form-table td fieldset label" (inline-block, margins with !important).
			. '.form-table td fieldset.pergel-choices label.pergel-choice{position:relative;display:flex;flex-direction:column;align-items:center;gap:6px;min-width:76px;margin:0!important;padding:10px 8px 8px;border:1px solid #c3c4c7;border-radius:4px;background:#fff;color:#50575e;cursor:pointer}'
			. '.pergel-choice input{position:absolute;inset:0;width:100%;height:100%;margin:0;opacity:0;cursor:pointer}'
			. '.form-table td fieldset.pergel-choices label.pergel-choice:has(input:checked){border-color:var(--wp-admin-theme-color,#2271b1);box-shadow:0 0 0 1px var(--wp-admin-theme-color,#2271b1);color:var(--wp-admin-theme-color,#2271b1)}'
			. '.form-table td fieldset.pergel-choices label.pergel-choice:has(input:focus-visible){outline:2px solid var(--wp-admin-theme-color,#2271b1);outline-offset:2px}'
		);
	}

	/**
	 * Adds a "Settings" link to the plugin row.
	 *
	 * @param array<string, string> $links Action links.
	 * @return array<string, string>
	 */
	public static function action_links( array $links ): array {
		return array(
			'settings' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
				esc_html__( 'Settings', 'pergel-engine' )
			),
		) + $links;
	}

	/**
	 * Turns schema problems into settings errors. options.php keeps them
	 * across its redirect, and render() shows them at the top and next to
	 * each field.
	 *
	 * @param list<array{field: string, code: string}> $problems Field paths and error codes.
	 */
	public static function report( array $problems ): void {
		if ( ! function_exists( 'add_settings_error' ) ) {
			return;
		}
		foreach ( $problems as $problem ) {
			[ $group, $key ] = array_pad( explode( '.', $problem['field'], 2 ), 2, '' );
			$label           = Labels::fields()[ $group ][ $key ]['label'] ?? $problem['field'];
			add_settings_error(
				Settings::OPTION,
				'' === $problem['field'] ? 'pergel' : $problem['field'],
				/* translators: 1: field label, 2: what is wrong. */
				sprintf( __( '%1$s was not saved. %2$s', 'pergel-engine' ), $label, Labels::problem( $problem['code'] ) )
			);
		}
	}

	/**
	 * Renders the page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$groups = array_filter( Schema::groups(), array( self::class, 'shown' ), ARRAY_FILTER_USE_KEY );
		$tabs   = Labels::tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch, read only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( ! isset( $groups[ $tab ] ) ) {
			$tab = (string) array_key_first( $groups );
		}

		echo '<div class="wrap pergel-settings">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		settings_errors();

		$errors = array();
		foreach ( get_settings_errors( Settings::OPTION ) as $error ) {
			$errors[ $error['code'] ] = $error['message'];
		}

		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Settings sections', 'pergel-engine' ) . '">';
		foreach ( array_keys( $groups ) as $group ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( add_query_arg( 'tab', $group, admin_url( 'admin.php?page=' . self::SLUG ) ) ),
				$group === $tab ? ' nav-tab-active' : '',
				$group === $tab ? ' aria-current="page"' : '',
				esc_html( $tabs[ $group ]['title'] ?? ucfirst( $group ) )
			);
		}
		echo '</nav>';

		if ( 'shop' === $tab && Preset::previewing() ) {
			echo Preset::preview(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Preset.
			echo '</div>';
			return;
		}
		if ( 'shop' === $tab ) {
			echo Preset::box(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Preset.
		}

		echo '<form method="post" action="options.php">';
		settings_fields( 'pergel' );
		if ( isset( $tabs[ $tab ]['description'] ) ) {
			echo '<p>' . esc_html( $tabs[ $tab ]['description'] ) . '</p>';
		}
		$seo_plugin = 'seo' === $tab ? Detect::active() : '';
		if ( '' !== $seo_plugin ) {
			printf(
				'<div class="notice notice-info inline"><p>%s</p></div>',
				/* translators: %s: SEO plugin name. */
				esc_html( sprintf( __( '%s is active, so these settings are not used now.', 'pergel-engine' ), Detect::name( $seo_plugin ) ) )
			);
		}
		$lost = 'shop' === $tab ? Layout::templates_without_grid() : array();
		if ( $lost ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				/* translators: %s: template names, comma separated. */
				esc_html( sprintf( __( 'These templates were changed in the Site Editor and no longer hold the theme\'s product grid, so the settings below do not reach them: %s. Reset the template in the Site Editor to use them again.', 'pergel-engine' ), implode( ', ', $lost ) ) )
			);
		}

		$values = Settings::all()[ $tab ];
		foreach ( self::sections( $tab ) as $section ) {
			echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $section['fields'] as $key ) {
				self::row( $tab, $key, $groups[ $tab ][ $key ], $values[ $key ], $errors[ "$tab.$key" ] ?? '' );
			}
			echo '</tbody></table>';
		}

		submit_button();
		echo '</form></div>';
	}

	/**
	 * Sections of a tab. Every schema field appears exactly once: fields
	 * that Labels does not place go to a final "Other" section.
	 *
	 * @param string $group Schema group.
	 * @return list<array{title: string, fields: list<string>}>
	 */
	public static function sections( string $group ): array {
		$fields   = array_values( array_filter( array_keys( Schema::groups()[ $group ] ?? array() ), static fn ( string $key ): bool => self::shown( $group, $key ) ) );
		$sections = array();
		$placed   = array();
		foreach ( Labels::sections()[ $group ] ?? array() as $section ) {
			$keys = array_values( array_intersect( $section['fields'], $fields ) );
			if ( $keys ) {
				$sections[] = array(
					'title'  => $section['title'],
					'fields' => $keys,
				);
				$placed     = array_merge( $placed, $keys );
			}
		}
		$rest = array_values( array_diff( $fields, $placed ) );
		if ( $rest ) {
			$sections[] = array(
				'title'  => __( 'Other', 'pergel-engine' ),
				'fields' => $rest,
			);
		}
		return $sections;
	}

	/**
	 * Whether a tab, or a field of it, is shown. The shop tabs and the shop
	 * module switch need WooCommerce; hidden fields keep their saved values.
	 *
	 * @param string $group Schema group.
	 * @param string $key   Field key; empty for the whole tab.
	 */
	public static function shown( string $group, string $key = '' ): bool {
		$woo = in_array( $group, array( 'woo', 'shop' ), true ) || ( 'modules' === $group && 'woo' === $key );
		return ! $woo || Woo::active();
	}

	/**
	 * One form-table row.
	 *
	 * @param string               $group Schema group.
	 * @param string               $key   Field key.
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Current value.
	 * @param string               $error Error message from the last save, if any.
	 * @phpstan-param Field $field
	 */
	private static function row( string $group, string $key, array $field, mixed $value, string $error ): void {
		$text  = Labels::fields()[ $group ][ $key ] ?? array();
		$id    = "pergel-$group-$key";
		$name  = Settings::OPTION . "[$group][$key]";
		$label = $text['label'] ?? $key;

		$svg = 'image' === $field['type'] && ( $field['svg'] ?? true );
		if ( '' === $error && ! $svg && 'image' === $field['type'] && Svg::is( (int) $value ) ) {
			$error = Labels::problem( 'svg' );
		}
		$svg_hint = $svg && isset( $text['svg'] ) && ! Svg::uploads();

		$describedby = array();
		if ( '' !== $error ) {
			$describedby[] = "$id-error";
		}
		if ( isset( $text['help'] ) ) {
			$describedby[] = "$id-help";
		}
		if ( $svg_hint ) {
			$describedby[] = "$id-svg";
		}
		$cards = 'enum' === $field['type'] && ! empty( $text['cards'] );
		$aria  = ( $describedby ? ' aria-describedby="' . esc_attr( implode( ' ', $describedby ) ) . '"' : '' )
			. ( '' !== $error && 'image' !== $field['type'] && ! $cards ? ' aria-invalid="true"' : '' );

		// An image field has no input to label; its buttons carry the
		// description. Cards are radio buttons named by their fieldset.
		$th = 'image' === $field['type'] || $cards ? esc_html( $label ) : '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<tr><th scope="row">' . $th . '</th><td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		if ( $cards ) {
			self::cards( $group, $key, $field, $name, $value, $aria, $text );
		} else {
			self::control( $field, $id, $name, $value, $aria, $text );
		}
		if ( '' !== $error ) {
			echo '<p class="pergel-settings__error" id="' . esc_attr( "$id-error" ) . '">' . esc_html( $error ) . '</p>';
		}
		if ( isset( $text['help'] ) ) {
			echo '<p class="description" id="' . esc_attr( "$id-help" ) . '">' . esc_html( $text['help'] ) . '</p>';
		}
		if ( $svg_hint ) {
			$link = current_user_can( 'install_plugins' )
				? ' <a href="' . esc_url( admin_url( 'plugin-install.php?s=safe-svg&tab=search&type=term' ) ) . '">' . esc_html__( 'Install Safe SVG', 'pergel-engine' ) . '</a>'
				: '';
			echo '<p class="description" id="' . esc_attr( "$id-svg" ) . '">' . esc_html( (string) $text['svg'] ) . $link . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $link is escaped above.
		}
		echo '</td></tr>';
	}

	/**
	 * An enum field as a row of picture cards, one radio button each.
	 *
	 * @param string               $group Schema group.
	 * @param string               $key   Field key.
	 * @param array<string, mixed> $field Field definition.
	 * @param string               $name  Form name.
	 * @param mixed                $value Current value.
	 * @param string               $aria  Escaped aria attributes.
	 * @param array<string, mixed> $text  Labels for the field.
	 * @phpstan-param Field $field
	 */
	private static function cards( string $group, string $key, array $field, string $name, mixed $value, string $aria, array $text ): void {
		$choices = is_array( $text['choices'] ?? null ) ? $text['choices'] : array();
		printf(
			'<fieldset class="pergel-choices"%1$s><legend class="screen-reader-text">%2$s</legend>',
			$aria, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row().
			esc_html( (string) ( $text['label'] ?? $key ) )
		);
		foreach ( $field['options'] ?? array() as $option ) {
			printf(
				'<label class="pergel-choice"><input type="radio" name="%1$s" value="%2$s"%3$s>%4$s<span>%5$s</span></label>',
				esc_attr( $name ),
				esc_attr( $option ),
				checked( $value, $option, false ),
				Pictures::get( $group, $key, $option ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed SVG built from numbers.
				esc_html( (string) ( $choices[ $option ] ?? $option ) )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * The input for one field type.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param string               $id    Element id.
	 * @param string               $name  Form name.
	 * @param mixed                $value Current value.
	 * @param string               $aria  Escaped aria attributes.
	 * @param array<string, mixed> $text  Labels for the field.
	 * @phpstan-param Field $field
	 */
	private static function control( array $field, string $id, string $name, mixed $value, string $aria, array $text ): void {
		$attrs = sprintf( 'id="%s" name="%s"%s', esc_attr( $id ), esc_attr( $name ), $aria );

		switch ( $field['type'] ) {
			case 'bool':
				// The hidden input sends "0" when the box is unchecked.
				printf(
					'<input type="hidden" name="%1$s" value="0"><input type="checkbox" %2$s value="1"%3$s>',
					esc_attr( $name ),
					$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
					checked( true === $value, true, false )
				);
				return;

			case 'textarea':
			case 'urls':
			case 'dates':
				printf(
					'<textarea %1$s class="large-text" rows="4">%2$s</textarea>',
					$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
					esc_textarea( is_array( $value ) ? implode( "\n", $value ) : (string) $value )
				);
				return;

			case 'enum':
				$choices = is_array( $text['choices'] ?? null ) ? $text['choices'] : array();
				echo '<select ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				foreach ( $field['options'] ?? array() as $option ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $option ),
						selected( $value, $option, false ),
						esc_html( (string) ( $choices[ $option ] ?? $option ) )
					);
				}
				echo '</select>';
				return;

			case 'pattern':
				echo '<select ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				foreach ( self::patterns( (int) $value ) as $id => $title ) {
					printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $id, selected( (int) $value, $id, false ), esc_html( $title ) );
				}
				echo '</select>';
				return;

			case 'int':
				printf(
					'<input type="number" %1$s class="small-text" value="%2$d"%3$s%4$s>',
					$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
					(int) $value,
					isset( $field['min'] ) ? ' min="' . (int) $field['min'] . '"' : '',
					isset( $field['max'] ) ? ' max="' . (int) $field['max'] . '"' : ''
				);
				return;

			case 'image':
				$image = (int) $value > 0 ? wp_get_attachment_image( (int) $value, 'medium', false, array( 'alt' => '' ) ) : '';
				printf(
					'<div class="pergel-image" data-title="%1$s" data-button="%2$s"%11$s>'
					. '<div class="pergel-image__preview">%3$s</div>'
					. '<input type="hidden" id="%4$s" name="%9$s" value="%5$s">'
					. '<button type="button" class="button pergel-image__choose"%10$s>%6$s</button> '
					. '<button type="button" class="button pergel-image__remove"%7$s>%8$s</button>'
					. '</div>',
					esc_attr( (string) ( $text['label'] ?? '' ) ),
					esc_attr__( 'Use this image', 'pergel-engine' ),
					$image, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
					esc_attr( $id ),
					esc_attr( (string) (int) $value ),
					esc_html__( 'Choose image', 'pergel-engine' ),
					'' === $image ? ' hidden' : '',
					esc_html__( 'Remove', 'pergel-engine' ),
					esc_attr( $name ),
					$aria, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row().
					( $field['svg'] ?? true ) ? '' : ' data-types="' . esc_attr( implode( ',', Svg::SHAREABLE ) ) . '"'
				);
				return;
		}

		$type = match ( $field['type'] ) {
			'email' => 'email',
			'url'   => 'url',
			'tel'   => 'tel',
			'time'  => 'time',
			default => 'text',
		};
		$size = in_array( $field['type'], array( 'country', 'time' ), true ) ? 'small-text' : 'regular-text';
		$max  = 'country' === $field['type'] ? 2 : ( $field['max'] ?? 0 );
		printf(
			'<input type="%1$s" %2$s class="%3$s" value="%4$s"%5$s>',
			esc_attr( $type ),
			$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_attr( $size ),
			esc_attr( (string) $value ),
			$max > 0 ? ' maxlength="' . (int) $max . '"' : ''
		);
	}

	/**
	 * Choices of a pattern field: none, then the site's patterns (Appearance
	 * → Editor → Patterns) by title. A chosen pattern that is gone is listed
	 * as such, so saving the form does not change the value unnoticed.
	 *
	 * @param int $current Chosen pattern id.
	 * @return array<int, string>
	 */
	public static function patterns( int $current ): array {
		$choices = array( 0 => __( 'None', 'pergel-engine' ) );
		$posts   = get_posts(
			array(
				'post_type'      => 'wp_block',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			$choices[ $post->ID ] = '' !== $post->post_title ? $post->post_title : sprintf( '#%d', $post->ID );
		}
		if ( $current > 0 && ! isset( $choices[ $current ] ) ) {
			/* translators: %d: pattern id. */
			$choices[ $current ] = sprintf( __( 'Pattern #%d (not found)', 'pergel-engine' ), $current );
		}
		return $choices;
	}

	/**
	 * Menu icon: a pergola, as a data URI so WordPress can recolor it.
	 */
	private static function icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M2 3h16v2.5H2zM2 7h16v1.5H2zM3.5 5.5h2V17h-2zM9 5.5h2V17H9zM14.5 5.5h2V17h-2z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon data URI.
	}
}
