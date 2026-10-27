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
use Pergel\Woo\Items;
use Pergel\Woo\Preset;
use Pergel\Woo\ProductBoxes;
use Pergel\Woo\StoreHeader;

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
	 * The Shop tab, which holds the shop layout group too, in a menu of pages.
	 */
	public const MENU = 'woo';

	/**
	 * The shop layout group: no tab of its own.
	 */
	public const LAYOUT = 'shop';

	/**
	 * Adds the admin hooks.
	 */
	public static function register(): void {
		// Before the post type submenus (priority 10), so Settings stays the first item.
		add_action( 'admin_menu', array( self::class, 'menu' ), 9 );
		// Plugins such as Site Kit move their menu under Dashboard; Pergel goes back on top of them.
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( self::class, 'menu_order' ), 20 );
		add_action( 'pergel/settings/invalid', array( self::class, 'report' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FILE ), array( self::class, 'action_links' ) );
	}

	/**
	 * Filter for menu_order: the Pergel menu right after Dashboard.
	 *
	 * @param mixed $order Menu slugs in order.
	 * @return mixed
	 */
	public static function menu_order( mixed $order ): mixed {
		if ( ! is_array( $order ) || ! in_array( self::SLUG, $order, true ) ) {
			return $order;
		}
		$order     = array_values( array_diff( $order, array( self::SLUG ) ) );
		$dashboard = array_search( 'index.php', $order, true );
		array_splice( $order, false === $dashboard ? 0 : $dashboard + 1, 0, array( self::SLUG ) );
		return $order;
	}

	/**
	 * A menu of its own, right under Dashboard (2), before what plugins put
	 * at 3 and before the first separator (4). Hooked on admin_menu.
	 */
	public static function menu(): void {
		$hook = add_menu_page(
			__( 'Pergel settings', 'pergel-engine' ),
			'Pergel',
			'manage_options',
			self::SLUG,
			array( self::class, 'render' ),
			self::icon(),
			2.5
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
			// The Shop tab's menu of pages on the left.
			. '.pergel-settings__area{display:grid;grid-template-columns:220px minmax(0,1fr);gap:24px;margin-top:20px;align-items:start}'
			. '.pergel-settings__menu ul{position:sticky;top:52px;margin:0;padding:8px;background:#fff;border:1px solid #c3c4c7;border-radius:4px}'
			. '.pergel-settings__menu li{margin:0}'
			. '.pergel-settings__menu a{display:block;padding:8px 12px;border-radius:3px;color:#1d2327;text-decoration:none}'
			. '.pergel-settings__menu a:hover{background:#f0f0f1;color:var(--wp-admin-theme-color,#2271b1)}'
			. '.pergel-settings__menu a[aria-current]{background:#f0f6fc;box-shadow:inset 3px 0 0 var(--wp-admin-theme-color,#2271b1);color:var(--wp-admin-theme-color,#2271b1);font-weight:600}'
			. '.pergel-settings__page>h2:first-child{margin:4px 0 8px;font-size:1.5em}'
			. '.pergel-settings__page h3{margin:2em 0 0;padding-top:1.25em;border-top:1px solid #dcdcde;font-size:1.15em}'
			. '.pergel-settings__page form>h3:first-of-type{margin-top:1em;padding-top:0;border-top:0}'
			. '@media (max-width:782px){.pergel-settings__area{grid-template-columns:minmax(0,1fr)}.pergel-settings__menu ul{position:static;display:flex;flex-wrap:wrap;gap:4px}}'
			// Lengths: wide enough for the "Default" placeholder.
			. '.pergel-settings input[type=number].pergel-length{width:8em}'
			// Colour fields: a swatch (chequered while not set), the list and the picker.
			. '.pergel-color{display:inline-flex;align-items:center;gap:8px}'
			. '.pergel-color__swatch{width:28px;height:28px;flex:none;box-sizing:border-box;border:1px solid #8c8f94;border-radius:50%;background:repeating-conic-gradient(#dcdcde 0 25%,#fff 0 50%) 50%/10px 10px}'
			. '.pergel-color__hex{width:44px;height:30px;padding:2px}'
			. '.pergel-color__hex[hidden]{display:none}'
			// Item lists: one row per item, with its box and arrows.
			. '.pergel-items__list{max-width:30em;margin:8px 0 0;padding:0;list-style:none}'
			. '.pergel-items__list li{display:flex;align-items:center;gap:8px;margin:0 0 -1px;padding:4px 8px;border:1px solid #dcdcde;background:#fff}'
			. '.pergel-items__list label{flex:1}'
			. '.pergel-items__note{color:#646970;font-size:12px}'
			. '.pergel-items__list.is-off{opacity:.55}'
			// The live preview: a panel on the right with the site drawn at
			// 1280px and scaled to fit; the settings move aside.
			. '.pergel-preview{position:fixed;top:var(--wp-admin--admin-bar--height,32px);right:0;bottom:0;z-index:9989;display:flex;flex-direction:column;width:min(50vw,960px);background:#fff;border-left:1px solid #c3c4c7;box-shadow:-4px 0 16px rgb(0 0 0/.08)}'
			. '.pergel-preview[hidden]{display:none}'
			. '.pergel-previewing .pergel-settings{margin-right:min(50vw,960px)}'
			. '.pergel-preview__bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;padding:8px 12px;border-bottom:1px solid #dcdcde}'
			. '.pergel-preview__state{flex:1;color:#646970}'
			. '.pergel-preview__frame{position:relative;flex:1;overflow:hidden;background:#f0f0f1}'
			. '.pergel-preview__frame iframe{position:absolute;top:0;left:0;width:1280px;border:0;background:#fff;transform-origin:0 0}'
			. '.pergel-settings__page>h2:first-child .pergel-preview__open{margin-left:12px;vertical-align:middle}'
			. '@media (max-width:960px){.pergel-preview{width:100%}.pergel-previewing .pergel-settings{margin-right:0}}'
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
		// The shop layout group lives in the Shop tab's menu; old links still open it.
		$legacy = self::LAYOUT === $tab;
		if ( $legacy ) {
			$tab = self::MENU;
		}
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
			if ( self::LAYOUT === $group ) {
				continue;
			}
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url( add_query_arg( 'tab', $group, admin_url( 'admin.php?page=' . self::SLUG ) ) ),
				$group === $tab ? ' nav-tab-active' : '',
				$group === $tab ? ' aria-current="page"' : '',
				esc_html( $tabs[ $group ]['title'] ?? ucfirst( $group ) )
			);
		}
		echo '</nav>';

		if ( self::MENU === $tab ) {
			self::menu_page( $groups, $errors, $legacy );
			echo '</div>';
			return;
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
	 * The Shop tab: its pages listed on the left, the open one beside them.
	 * A page posts only its own fields; Schema::sanitize() keeps the rest.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $groups Shown schema groups.
	 * @param array<string, string>                              $errors Errors by field path.
	 * @param bool                                               $legacy Opened with the old shop layout tab link.
	 * @phpstan-param array<string, array<string, Field>> $groups
	 */
	private static function menu_page( array $groups, array $errors, bool $legacy ): void {
		$pages = self::pages();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page switch, read only.
		$open = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		if ( '' === $open && ( $legacy || Preset::previewing() ) ) {
			$open = Preset::previewing() ? 'presets' : 'listing';
		}
		if ( ! isset( $pages[ $open ] ) ) {
			$open = (string) array_key_first( $pages );
		}
		$page = $pages[ $open ];

		echo '<div class="pergel-settings__area">';
		echo '<nav class="pergel-settings__menu" aria-label="' . esc_attr__( 'Shop settings', 'pergel-engine' ) . '"><ul>';
		foreach ( $pages as $key => $item ) {
			printf(
				'<li><a href="%1$s"%2$s>%3$s</a></li>',
				esc_url( self::url( $key ) ),
				$key === $open ? ' aria-current="page"' : '',
				esc_html( $item['title'] )
			);
		}
		echo '</ul></nav><div class="pergel-settings__page">';
		$preview = 'presets' !== $open;
		printf(
			'<h2>%1$s%2$s</h2>',
			esc_html( $page['title'] ),
			$preview ? ' <button type="button" class="button pergel-preview__open" aria-expanded="false" aria-controls="pergel-preview">' . esc_html__( 'Preview', 'pergel-engine' ) . '</button>' : ''
		);
		if ( '' !== $page['description'] ) {
			echo '<p>' . esc_html( $page['description'] ) . '</p>';
		}

		if ( 'presets' === $open ) {
			echo Preset::previewing() ? Preset::preview() : Preset::box(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Preset.
			echo '</div></div>';
			return;
		}

		$lost = in_array( self::LAYOUT, array_column( $page['sections'], 'group' ), true ) ? Layout::templates_without_grid() : array();
		if ( $lost ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				/* translators: %s: template names, comma separated. */
				esc_html( sprintf( __( 'These templates were changed in the Site Editor and no longer hold the theme\'s product grid, so the settings below do not reach them: %s. Reset the template in the Site Editor to use them again.', 'pergel-engine' ), implode( ', ', $lost ) ) )
			);
		}

		echo '<form method="post" action="options.php">';
		settings_fields( 'pergel' );
		$values = Settings::all();
		foreach ( $page['sections'] as $section ) {
			$group = $section['group'];
			if ( count( $page['sections'] ) > 1 && $section['title'] !== $page['title'] ) {
				echo '<h3>' . esc_html( $section['title'] ) . '</h3>';
			}
			echo '<table class="form-table" role="presentation"><tbody>';
			foreach ( $section['fields'] as $key ) {
				self::row( $group, $key, $groups[ $group ][ $key ], $values[ $group ][ $key ], $errors[ "$group.$key" ] ?? '' );
			}
			echo '</tbody></table>';
		}
		submit_button();
		echo '</form></div></div>';
		self::preview( in_array( $open, array( 'product', 'details' ), true ) ? 'product' : 'shop' );
	}

	/**
	 * The live preview panel: settings.js sends the form's unsaved values to
	 * the preview route whenever one changes, and reloads the page shown.
	 *
	 * @param string $page shop or product: the page shown first.
	 */
	private static function preview( string $page ): void {
		printf(
			'<aside id="pergel-preview" class="pergel-preview" hidden aria-label="%1$s" data-endpoint="%2$s" data-nonce="%3$s" data-page="%4$s" data-busy="%5$s" data-done="%6$s" data-failed="%7$s">'
			. '<div class="pergel-preview__bar"><strong>%1$s</strong>'
			. '<select aria-label="%8$s"><option value="shop"%9$s>%10$s</option><option value="product"%11$s>%12$s</option></select>'
			. '<span class="pergel-preview__state" aria-live="polite"></span>'
			. '<button type="button" class="button pergel-preview__close">%13$s</button></div>'
			. '<div class="pergel-preview__frame"><iframe title="%14$s" src="about:blank"></iframe></div></aside>',
			esc_attr__( 'Preview', 'pergel-engine' ),
			esc_url( rest_url( 'pergel/v1/preview' ) ),
			esc_attr( wp_create_nonce( 'wp_rest' ) ),
			esc_attr( $page ),
			esc_attr__( 'Updating the preview…', 'pergel-engine' ),
			esc_attr__( 'Preview shows the unsaved settings. Nothing is saved until you save.', 'pergel-engine' ),
			esc_attr__( 'The preview could not be updated.', 'pergel-engine' ),
			esc_attr__( 'Page to preview', 'pergel-engine' ),
			selected( $page, 'shop', false ),
			esc_html__( 'Shop page', 'pergel-engine' ),
			selected( $page, 'product', false ),
			esc_html__( 'A product page', 'pergel-engine' ),
			esc_html__( 'Close preview', 'pergel-engine' ),
			esc_attr__( 'Preview of the unsaved settings', 'pergel-engine' )
		);
	}

	/**
	 * The Shop tab's pages (Labels::menu()) with the shown fields of their
	 * sections. Sections of the two groups that Labels::menu() does not
	 * place, and fields that no section holds, go to a last "Other" page.
	 * Pages left without fields are dropped, except the presets.
	 *
	 * @return array<string, array{title: string, description: string, sections: list<array{title: string, group: string, fields: list<string>}>}>
	 */
	public static function pages(): array {
		$sections = array();
		foreach ( array( self::MENU, self::LAYOUT ) as $group ) {
			if ( ! self::shown( $group ) ) {
				continue;
			}
			foreach ( self::sections( $group ) as $section ) {
				$sections[ $group . '.' . $section['key'] ] = array(
					'title'  => $section['title'],
					'group'  => $group,
					'fields' => $section['fields'],
				);
			}
		}

		$pages = array();
		foreach ( Labels::menu() as $key => $page ) {
			$held = array();
			foreach ( $page['parts'] as $part ) {
				if ( isset( $sections[ $part ] ) ) {
					$held[] = $sections[ $part ];
					unset( $sections[ $part ] );
				}
			}
			if ( $held || 'presets' === $key ) {
				$pages[ $key ] = array(
					'title'       => $page['title'],
					'description' => $page['description'],
					'sections'    => $held,
				);
			}
		}
		if ( $sections ) {
			$pages['other'] = array(
				'title'       => __( 'Other', 'pergel-engine' ),
				'description' => '',
				'sections'    => array_values( $sections ),
			);
		}
		return $pages;
	}

	/**
	 * The title of the Shop tab page that holds a field, '' if none does.
	 *
	 * @param string $group Schema group.
	 * @param string $key   Field key.
	 */
	public static function page_title( string $group, string $key ): string {
		foreach ( self::pages() as $page ) {
			foreach ( $page['sections'] as $section ) {
				if ( $group === $section['group'] && in_array( $key, $section['fields'], true ) ) {
					return $page['title'];
				}
			}
		}
		return '';
	}

	/**
	 * Address of a Shop tab page.
	 *
	 * @param string                $page Page key from pages().
	 * @param array<string, string> $args More query arguments.
	 */
	public static function url( string $page, array $args = array() ): string {
		return add_query_arg(
			array(
				'tab'     => self::MENU,
				'section' => $page,
			) + $args,
			admin_url( 'admin.php?page=' . self::SLUG )
		);
	}

	/**
	 * Sections of a tab. Every schema field appears exactly once: fields
	 * that Labels does not place go to a final "Other" section.
	 *
	 * @param string $group Schema group.
	 * @return list<array{key: string, title: string, fields: list<string>}>
	 */
	public static function sections( string $group ): array {
		$fields   = array_values( array_filter( array_keys( Schema::groups()[ $group ] ?? array() ), static fn ( string $key ): bool => self::shown( $group, $key ) ) );
		$sections = array();
		$placed   = array();
		foreach ( Labels::sections()[ $group ] ?? array() as $name => $section ) {
			$keys = array_values( array_intersect( $section['fields'], $fields ) );
			if ( $keys ) {
				$sections[] = array(
					'key'    => $name,
					'title'  => $section['title'],
					'fields' => $keys,
				);
				$placed     = array_merge( $placed, $keys );
			}
		}
		$rest = array_values( array_diff( $fields, $placed ) );
		if ( $rest ) {
			$sections[] = array(
				'key'    => 'other',
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
		$set   = $cards || 'items' === $field['type'];
		$aria  = ( $describedby ? ' aria-describedby="' . esc_attr( implode( ' ', $describedby ) ) . '"' : '' )
			. ( '' !== $error && 'image' !== $field['type'] && ! $set ? ' aria-invalid="true"' : '' );

		// An image field has no input to label; its buttons carry the
		// description. Cards and item lists are named by their fieldset.
		$th = 'image' === $field['type'] || $set ? esc_html( $label ) : '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
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

			case 'length':
				// Empty: the style's own size.
				printf(
					'<input type="number" %1$s class="pergel-length" value="%2$s" min="%3$d" max="%4$d" placeholder="%5$s"> <span aria-hidden="true">px</span>',
					$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
					'' === $value ? '' : (int) $value,
					(int) ( $field['min'] ?? 0 ),
					(int) ( $field['max'] ?? 999 ),
					esc_attr__( 'Default', 'pergel-engine' )
				);
				return;

			case 'color':
				self::color( $id, $name, (string) $value, $aria );
				return;

			case 'items':
				self::items( $field, $id, $name, (string) $value, $aria, $text );
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

			case 'menu':
				echo '<select ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				foreach ( self::menus( (int) $value, (string) ( $text['none'] ?? __( 'None', 'pergel-engine' ) ) ) as $id => $title ) {
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
		// A placeholder shows what an empty value uses.
		$placeholder = (string) ( $text['placeholder'] ?? '' );
		printf(
			'<input type="%1$s" %2$s class="%3$s" value="%4$s"%5$s%6$s>',
			esc_attr( $type ),
			$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			esc_attr( $size ),
			esc_attr( (string) $value ),
			$max > 0 ? ' maxlength="' . (int) $max . '"' : '',
			'' === $placeholder ? '' : ' placeholder="' . esc_attr( $placeholder ) . '"'
		);
	}

	/**
	 * A colour field: the default, the theme's colours or a custom one. The
	 * custom colour's picker shows only while "Custom colour" is chosen
	 * (settings.js); Schema reads the two values together.
	 *
	 * @param string $id    Field id.
	 * @param string $name  Form name.
	 * @param string $value Stored colour: '', a palette slug or #rrggbb.
	 * @param string $aria  Escaped aria attributes.
	 */
	private static function color( string $id, string $name, string $value, string $aria ): void {
		$palette = self::palette();
		$custom  = str_starts_with( $value, '#' );
		$known   = '' === $value || $custom || isset( $palette[ $value ] );
		$shown   = $custom ? $value : ( $palette[ $value ]['color'] ?? '' );

		printf(
			'<span class="pergel-color"><span class="pergel-color__swatch" aria-hidden="true"%1$s></span><select id="%2$s" name="%3$s"%4$s>',
			'' === $shown ? '' : ' style="background:' . esc_attr( $shown ) . '"',
			esc_attr( $id ),
			esc_attr( $name . '[pick]' ),
			$aria // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row().
		);
		printf( '<option value=""%1$s>%2$s</option>', selected( $value, '', false ), esc_html__( 'Default', 'pergel-engine' ) );
		foreach ( $palette as $slug => $color ) {
			printf(
				'<option value="%1$s" data-color="%2$s"%3$s>%4$s</option>',
				esc_attr( $slug ),
				esc_attr( $color['color'] ),
				selected( $value, $slug, false ),
				esc_html( $color['name'] )
			);
		}
		if ( ! $known ) {
			// A colour the theme no longer has stays chosen until changed.
			printf( '<option value="%1$s" selected>%1$s</option>', esc_attr( $value ) );
		}
		printf(
			'<option value="custom"%1$s>%2$s</option></select> <input type="color" class="pergel-color__hex" name="%3$s" value="%4$s" aria-label="%5$s"></span>',
			selected( $custom, true, false ),
			esc_html__( 'Custom colour', 'pergel-engine' ),
			esc_attr( $name . '[hex]' ),
			esc_attr( $custom ? $value : '#000000' ),
			esc_attr__( 'Custom colour', 'pergel-engine' )
		);
	}

	/**
	 * An item list: "As in the template", then one row per item with a box
	 * and arrows (settings.js moves the rows and keeps the order field up to
	 * date). Without a list the rows start as the template shows them.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param string               $id    Element id.
	 * @param string               $name  Form name.
	 * @param string               $value Current value.
	 * @param string               $aria  Escaped aria attributes.
	 * @param array<string, mixed> $text  Labels for the field.
	 * @phpstan-param Field $field
	 */
	private static function items( array $field, string $id, string $name, string $value, string $aria, array $text ): void {
		$kind    = (string) ( $text['items'] ?? 'card' );
		$fixed   = StoreHeader::DEFAULTS[ $kind ] ?? ( 'facts' === $kind ? ProductBoxes::FACTS : null );
		$choices = is_array( $text['choices'] ?? null ) ? $text['choices'] : array();
		if ( null !== $fixed ) {
			// A row of the store header or the facts box: its own default,
			// and every item can be added.
			$template = $fixed;
			$shown    = '' === $value ? $template : explode( ',', $value );
		} else {
			$kind     = 'summary' === $kind ? 'summary' : 'card';
			$template = Items::in_template( $kind );
			$shown    = '' === $value ? $template : Items::keys( $value, 'card' === $kind ? Items::CARD : Items::SUMMARY );
		}
		$order = array_values( array_unique( array_merge( $shown, $template, $field['options'] ?? array() ) ) );

		printf(
			'<fieldset class="pergel-items"%1$s><legend class="screen-reader-text">%2$s</legend>'
			. '<input type="hidden" name="%3$s" value="%4$s">'
			. '<label><input type="checkbox" id="%5$s" class="pergel-items__template" name="%6$s" value="1"%7$s> %8$s</label>'
			. '<ol class="pergel-items__list">',
			$aria, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row().
			esc_html( (string) ( $text['label'] ?? '' ) ),
			esc_attr( $name . '[order]' ),
			esc_attr( implode( ',', $order ) ),
			esc_attr( $id ),
			esc_attr( $name . '[template]' ),
			checked( '' === $value, true, false ),
			esc_html( null !== $fixed ? __( 'Default arrangement', 'pergel-engine' ) : __( 'As in the template', 'pergel-engine' ) )
		);
		foreach ( $order as $key ) {
			$label  = (string) ( $choices[ $key ] ?? $key );
			$absent = null === $fixed && ! in_array( $key, $template, true );
			// An item whose block holds blocks of its own cannot be added.
			$locked = $absent && ! in_array( $key, Items::ADDABLE, true );
			printf(
				'<li data-key="%1$s"><label><input type="checkbox" name="%2$s" value="%1$s"%3$s%4$s> %5$s</label>%6$s'
				. '<button type="button" class="button button-small" data-move="-1" aria-label="%7$s">&uarr;</button>'
				. '<button type="button" class="button button-small" data-move="1" aria-label="%8$s">&darr;</button></li>',
				esc_attr( $key ),
				esc_attr( $name . '[show][]' ),
				checked( in_array( $key, $shown, true ), true, false ),
				$locked ? ' disabled data-locked' : '',
				esc_html( $label ),
				$absent ? ' <span class="pergel-items__note">' . esc_html( $locked ? __( 'Not in the template', 'pergel-engine' ) : __( 'Added when ticked', 'pergel-engine' ) ) . '</span>' : '',
				/* translators: %s: item name, e.g. "Price". */
				esc_attr( sprintf( __( 'Move %s up', 'pergel-engine' ), $label ) ),
				/* translators: %s: item name, e.g. "Price". */
				esc_attr( sprintf( __( 'Move %s down', 'pergel-engine' ), $label ) )
			);
		}
		printf(
			'</ol><p class="screen-reader-text" aria-live="polite" data-moved="%s"></p></fieldset>',
			/* translators: 1: item name, 2: its place, 3: number of items. */
			esc_attr__( '%1$s: %2$s of %3$s', 'pergel-engine' )
		);
	}

	/**
	 * The theme's colours (theme.json and the Site Editor), by slug.
	 *
	 * @return array<string, array{name: string, color: string}>
	 */
	public static function palette(): array {
		$colors = array();
		$sets   = wp_get_global_settings( array( 'color', 'palette' ) );
		foreach ( array( 'theme', 'custom' ) as $origin ) {
			foreach ( is_array( $sets[ $origin ] ?? null ) ? $sets[ $origin ] : array() as $color ) {
				if ( is_array( $color ) && is_string( $color['slug'] ?? null ) && is_string( $color['color'] ?? null ) ) {
					$colors[ $color['slug'] ] = array(
						'name'  => is_string( $color['name'] ?? null ) ? $color['name'] : $color['slug'],
						'color' => $color['color'],
					);
				}
			}
		}
		return $colors;
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
	 * Choices of a menu field: none, then the site's menus (Appearance →
	 * Editor → Navigation) by title. A chosen menu that is gone is listed as
	 * such, as with patterns.
	 *
	 * @param int    $current Chosen menu id.
	 * @param string $none    Name of the empty choice.
	 * @return array<int, string>
	 */
	public static function menus( int $current, string $none ): array {
		$choices = array( 0 => $none );
		$posts   = get_posts(
			array(
				'post_type'      => 'wp_navigation',
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
			/* translators: %d: menu id. */
			$choices[ $current ] = sprintf( __( 'Menu #%d (not found)', 'pergel-engine' ), $current );
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
