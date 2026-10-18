<?php
/**
 * Builds a site from a project package.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Package;

use Closure;
use InvalidArgumentException;
use Pergel\Seo\Meta;
use Pergel\Settings\Schema;
use Pergel\Settings\Settings;
use Pergel\Starters\Starter;
use Pergel\Tokens\Compiler;
use Pergel\Woo\Module as Woo;
use RuntimeException;
use WP_Post;

/**
 * Applies a checked package: site options, images, Pergel settings, pages,
 * reading settings, the menu and, for brand tokens, a child theme.
 *
 * Every post the package makes carries its name and its key (the page path
 * as "/path", the image path, or "menu"), so a second import finds it
 * instead of making a copy. Two hashes say what changed since then: one of
 * what the package asked for, one of the post as it was saved. A package
 * that did not change keeps the post; a post edited in the panel since the
 * import is skipped with a note, unless $force.
 *
 * @phpstan-import-type Page from Package
 * @phpstan-type Row array{action: string, type: string, key: string, id: string, title: string}
 */
final class Importer {

	public const META    = '_pergel_package';
	public const KEY     = '_pergel_package_key';
	public const SOURCE  = '_pergel_package_source';
	public const SAVED   = '_pergel_package_saved';
	public const FILE    = '_pergel_package_file';
	public const CREDITS = array(
		'source'  => '_pergel_media_source',
		'license' => '_pergel_media_license',
		'author'  => '_pergel_media_author',
	);

	/**
	 * WooCommerce page options by page type.
	 */
	private const SHOP_OPTIONS = array(
		'shop'     => 'woocommerce_shop_page_id',
		'cart'     => 'woocommerce_cart_page_id',
		'checkout' => 'woocommerce_checkout_page_id',
		'account'  => 'woocommerce_myaccount_page_id',
	);

	/**
	 * Header that marks a child theme a package wrote.
	 */
	public const THEME_MARK = 'Pergel package';

	/**
	 * What happened, one row per item.
	 *
	 * @var list<Row>
	 */
	public array $rows = array();

	/**
	 * What the author should look at.
	 *
	 * @var list<string>
	 */
	public array $notes = array();

	/**
	 * Posts this package made before, by key.
	 *
	 * @var array<string, WP_Post>
	 */
	private array $owned = array();

	/**
	 * Images in use: package path => { id, url }.
	 *
	 * @var array<string, array{id: int, url: string}>
	 */
	private array $images = array();

	/**
	 * Page IDs by path (0 in a dry run for a page still to be made).
	 *
	 * @var array<string, int>
	 */
	private array $ids = array();

	/**
	 * Keys the package still has; the rest of $owned is stale.
	 *
	 * @var array<string, true>
	 */
	private array $seen = array();

	/**
	 * Set up an import.
	 *
	 * @param Package $package A package read and checked without problems.
	 * @param bool    $dry_run Only report what would happen.
	 * @param bool    $force   Overwrite posts edited in the panel.
	 * @param bool    $prune   Remove what the package made and no longer has.
	 */
	public function __construct(
		private readonly Package $package,
		private readonly bool $dry_run = false,
		private readonly bool $force = false,
		private readonly bool $prune = false
	) {
		$posts = get_posts(
			array(
				'post_type'      => array( 'page', 'attachment', 'wp_navigation' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI only.
				'meta_value'     => $package->site['name'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- CLI only.
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		foreach ( $posts as $post ) {
			$key = (string) get_post_meta( $post->ID, self::KEY, true );
			if ( '' !== $key && ! isset( $this->owned[ $key ] ) ) {
				$this->owned[ $key ] = $post;
			}
		}
	}

	/**
	 * What stops the import on this site, checked before anything is saved:
	 * shop pages without WooCommerce, pages at the package's addresses that
	 * the package did not make, and a theme folder or active child theme the
	 * package cannot use.
	 *
	 * @return list<string>
	 */
	public function preflight(): array {
		$problems = array();
		foreach ( $this->package->pages as $page ) {
			if ( isset( self::SHOP_OPTIONS[ $page['type'] ] ) && ! Woo::active() ) {
				$problems[] = sprintf( 'sitemap.yaml: %s: type: a %s page needs WooCommerce; install and activate it first', $page['where'], $page['type'] );
			}
			if ( '' === $page['path'] || isset( $this->owned[ '/' . $page['path'] ] ) ) {
				continue;
			}
			$taken = get_page_by_path( $page['path'] );
			if ( $taken instanceof WP_Post && $taken->ID !== $this->shop_page( $page['type'] ) ) {
				$problems[] = sprintf( 'sitemap.yaml: %s: /%s/ is page %d, which this package did not make; move or delete it first', $page['where'], $page['path'], $taken->ID );
			}
		}

		$dir = $this->theme_dir();
		if ( null !== $this->package->tokens ) {
			if ( is_dir( $dir ) && ! $this->is_package_theme( $dir ) ) {
				$problems[] = sprintf( 'brand/tokens.json: the theme folder %s exists and is not this package\'s; rename the package or the folder', basename( $dir ) );
			}
			$active = get_stylesheet();
			if ( get_template() !== $active && basename( $dir ) !== $active ) {
				$problems[] = sprintf( 'brand/tokens.json: the active theme %s is a child theme this package did not write; activate the Pergel theme first', $active );
			}
		}
		return $problems;
	}

	/**
	 * Notes about this site known before anything is saved, so that --strict
	 * can stop on them as it does on the package's own: a language that is
	 * not installed.
	 *
	 * @return list<string>
	 */
	public function site_notes(): array {
		$locale = $this->package->site['locale'];
		if ( null === $locale || 'en_US' === $locale || in_array( $locale, get_available_languages(), true ) ) {
			return array();
		}
		return array( sprintf( 'package.yaml: locale: %1$s is not installed, the site language stays as it is; wp language core install %1$s installs it', $locale ) );
	}

	/**
	 * Switches this request to the package's language when the site has it.
	 * Call it before anything reads a pattern: WordPress keeps a pattern's
	 * markup from its first read, so pattern text that is not a field (form
	 * labels, a fallback address) would otherwise come in the site's previous
	 * language on the first import and in the package's on the next. The
	 * theme's patterns are registered again for the same reason: WordPress
	 * translates their titles when it registers them on init and writes the
	 * title and description into the metadata of every section made from them.
	 */
	public function use_locale(): void {
		$locale = $this->package->site['locale'];
		if ( null === $locale || array() !== $this->site_notes() || determine_locale() === $locale ) {
			return;
		}
		switch_to_locale( $locale );
		$registry = \WP_Block_Patterns_Registry::get_instance();
		$theme    = wp_get_theme();
		foreach ( array_filter( array( $theme, $theme->parent() ) ) as $source ) {
			foreach ( $source->get_block_patterns() as $pattern ) {
				if ( $registry->is_registered( $pattern['slug'] ) ) {
					$registry->unregister( $pattern['slug'] );
				}
			}
		}
		_register_theme_block_patterns();
	}

	/**
	 * Runs the import.
	 *
	 * @throws InvalidArgumentException When WordPress refuses a post or an image, or the child theme cannot be written.
	 */
	public function run(): void {
		$this->site_options();
		$this->settings();
		$this->pages();
		$this->reading();
		$this->menu();
		try {
			$this->theme();
		} catch ( RuntimeException $e ) {
			throw new InvalidArgumentException( esc_html( 'brand/tokens.json: ' . $e->getMessage() ) );
		}
		$this->stale();
		if ( array() !== $this->package->problems ) {
			throw new InvalidArgumentException( esc_html( implode( '; ', $this->package->problems ) ) );
		}
		$this->notes = array_values( array_unique( array_merge( $this->package->notes, $this->notes ) ) );
	}

	/**
	 * Site title, tagline and language.
	 */
	private function site_options(): void {
		$site    = $this->package->site;
		$options = array( 'blogname' => $site['title'] );
		if ( null !== $site['tagline'] ) {
			$options['blogdescription'] = $site['tagline'];
		}
		if ( null !== $site['locale'] && array() === $this->site_notes() ) {
			$options['WPLANG'] = 'en_US' === $site['locale'] ? '' : $site['locale'];
		}
		$this->notes = array_merge( $this->notes, $this->site_notes() );
		foreach ( $options as $option => $value ) {
			$this->option( $option, $value );
		}
	}

	/**
	 * Pergel settings from package.yaml.
	 */
	private function settings(): void {
		$settings = $this->package->settings( $this->media() );
		if ( array() === $settings['changes'] ) {
			return;
		}
		$current = Settings::all();
		$value   = Schema::sanitize( $settings['changes'], $current )['value'];
		$groups  = Schema::groups();
		$changed = false;
		foreach ( $settings['changes'] as $group => $values ) {
			foreach ( array_keys( $values ) as $key ) {
				$new  = $value[ $group ][ $key ] ?? null;
				$same = ( $current[ $group ][ $key ] ?? null ) === $new;
				// In a dry run an image still to be imported has no ID yet.
				$pending      = $this->dry_run && 0 === $new && 'image' === $groups[ $group ][ $key ]['type'];
				$changed      = $changed || ! $same;
				$this->rows[] = Starter::row( $same && ! $pending ? 'keep' : 'set', 'setting', "$group.$key", 0, $pending ? '(new image)' : self::show( $new ) );
			}
		}
		if ( $changed && ! $this->dry_run ) {
			Settings::update( $settings['changes'] );
		}
	}

	/**
	 * Pages in sitemap order, each parent before its children.
	 *
	 * @throws InvalidArgumentException When WordPress refuses a page.
	 */
	private function pages(): void {
		if ( ! $this->dry_run && Woo::active() && class_exists( 'WC_Install' ) ) {
			foreach ( $this->package->pages as $page ) {
				if ( isset( self::SHOP_OPTIONS[ $page['type'] ] ) && null === $page['content'] && 0 === $this->shop_page( $page['type'] ) && ! isset( $this->owned[ '/' . $page['path'] ] ) ) {
					// WooCommerce's own cart, checkout and account pages, to adopt below.
					\WC_Install::create_pages();
					break;
				}
			}
		}

		foreach ( $this->package->pages as $i => $page ) {
			$key     = '/' . $page['path'];
			$postarr = array(
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_title'    => $page['title'],
				'post_name'     => '' === $page['slug'] ? sanitize_title( $page['title'] ) : $page['slug'],
				'post_parent'   => null === $page['parent'] ? 0 : $this->ids[ $page['parent'] ],
				'menu_order'    => $i,
				'page_template' => '' === $page['template'] ? 'default' : $page['template'],
				'meta_input'    => $this->seo( $page['content'] ),
			);
			if ( null !== $page['content'] ) {
				$postarr['post_content'] = $this->package->page_content( $page['content'], Closure::fromCallable( array( Package::class, 'pattern' ) ), $this->media() );
			} elseif ( ! isset( self::SHOP_OPTIONS[ $page['type'] ] ) ) {
				$postarr['post_content'] = '';
			}

			$existing = $this->owned[ $key ] ?? null;
			$action   = null;
			if ( null === $existing && isset( self::SHOP_OPTIONS[ $page['type'] ] ) ) {
				// WooCommerce's page keeps its blocks unless the package gives content.
				$shop     = get_post( $this->shop_page( $page['type'] ) );
				$existing = $shop instanceof WP_Post && 'page' === $shop->post_type ? $shop : null;
				$action   = null === $existing ? null : 'adopt';
			}
			$id                         = $this->save( $key, $postarr, $existing, 'page', $action );
			$this->ids[ $page['path'] ] = $id;

			if ( ! $this->dry_run && $id > 0 ) {
				$name = (string) get_post_field( 'post_name', $id );
				if ( $name !== $postarr['post_name'] ) {
					$this->notes[] = sprintf( 'sitemap.yaml: %s: WordPress gave the page the address %s, as %s is taken', $page['where'], $name, $postarr['post_name'] );
				}
			}
		}
	}

	/**
	 * Front page, posts page and WooCommerce's pages.
	 */
	private function reading(): void {
		$options = array(
			'show_on_front'  => 'posts',
			'page_on_front'  => 0,
			'page_for_posts' => 0,
		);
		$pending = array();
		foreach ( $this->package->pages as $page ) {
			$id     = $this->ids[ $page['path'] ];
			$option = null;
			if ( '' === $page['path'] ) {
				$options['show_on_front'] = 'page';
				$option                   = 'page_on_front';
			} elseif ( 'posts' === $page['type'] ) {
				$option = 'page_for_posts';
			} elseif ( isset( self::SHOP_OPTIONS[ $page['type'] ] ) ) {
				$option = self::SHOP_OPTIONS[ $page['type'] ];
			}
			if ( null !== $option ) {
				$options[ $option ] = $id;
				$pending[ $option ] = 0 === $id;
			}
		}
		foreach ( $options as $option => $value ) {
			$this->option( $option, $value, $pending[ $option ] ?? false );
		}
	}

	/**
	 * The navigation menu. Navigation blocks without a menu of their own
	 * show the newest menu, so the header picks it up.
	 *
	 * @throws InvalidArgumentException When WordPress refuses the menu.
	 */
	private function menu(): void {
		$this->save(
			'menu',
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => $this->package->site['title'],
				'post_content' => implode( "\n", array_map( array( $this, 'menu_item' ), $this->package->menu ) ),
			),
			$this->owned['menu'] ?? null,
			'menu'
		);
	}

	/**
	 * One menu item as a navigation link, or a submenu with its children.
	 *
	 * @param array<string, mixed> $item { label, page or url, children }.
	 */
	private function menu_item( array $item ): string {
		$attrs = array( 'label' => (string) $item['label'] );
		if ( null !== $item['page'] ) {
			$id     = $this->ids[ (string) $item['page'] ] ?? 0;
			$attrs += array(
				'type' => 'page',
				'id'   => $id,
				'url'  => $id > 0 ? (string) get_permalink( $id ) : '',
				'kind' => 'post-type',
			);
		} else {
			$attrs += array(
				'url'  => (string) $item['url'],
				'kind' => 'custom',
			);
		}
		$children = (array) $item['children'];
		if ( array() === $children ) {
			return get_comment_delimited_block_content( 'core/navigation-link', $attrs, '' );
		}
		return get_comment_delimited_block_content( 'core/navigation-submenu', $attrs, implode( "\n", array_map( array( $this, 'menu_item' ), $children ) ) );
	}

	/**
	 * Brand tokens: a child theme of the active Pergel theme whose
	 * tokens/default.json is the package's, built and activated. Without
	 * tokens the package's child theme, if active, gives way to Pergel.
	 *
	 * @throws RuntimeException When the theme cannot be written.
	 */
	private function theme(): void {
		$dir   = $this->theme_dir();
		$slug  = basename( $dir );
		$theme = $this->package->tokens;
		if ( null === $theme ) {
			if ( get_stylesheet() === $slug ) {
				$this->rows[] = Starter::row( 'set', 'theme', 'stylesheet', 0, get_template() );
				if ( ! $this->dry_run ) {
					switch_theme( get_template() );
				}
			}
			return;
		}

		$exists = is_dir( $dir );
		$files  = array(
			'style.css'           => $this->theme_header(),
			'tokens/default.json' => Compiler::encode( $theme ),
		);
		if ( $this->dry_run ) {
			$changed = Compiler::sync( $dir, $files, true );
		} else {
			$changed = Compiler::sync( $dir, $files, false );
			// Built against the parent's tokens, like `wp pergel tokens build` builds a child theme.
			$changed = array_merge( $changed, Compiler::sync( $dir, Compiler::build( $dir, get_template_directory() ), false ) );
		}
		$this->rows[] = Starter::row( $exists ? ( array() === $changed ? 'keep' : 'update' ) : 'create', 'theme', $slug, 0, array() === $changed ? '' : implode( ', ', $changed ) );

		if ( get_stylesheet() !== $slug ) {
			$this->rows[] = Starter::row( 'set', 'theme', 'stylesheet', 0, $slug );
			if ( ! $this->dry_run ) {
				search_theme_directories( true );
				switch_theme( $slug );
			}
		}
	}

	/**
	 * Lists, and with $prune removes, what the package made and no longer has.
	 * Pages and the menu go to the trash; images are deleted.
	 */
	private function stale(): void {
		foreach ( $this->owned as $key => $post ) {
			if ( isset( $this->seen[ $key ] ) ) {
				continue;
			}
			$type         = array(
				'page'          => 'page',
				'attachment'    => 'image',
				'wp_navigation' => 'menu',
			)[ $post->post_type ] ?? $post->post_type;
			$this->rows[] = Starter::row( $this->prune ? 'remove' : 'stale', $type, $key, $post->ID, $post->post_title );
			if ( $this->prune && ! $this->dry_run ) {
				'attachment' === $post->post_type ? wp_delete_attachment( $post->ID, true ) : wp_trash_post( $post->ID );
			}
		}
		if ( ! $this->prune && array_diff_key( $this->owned, $this->seen ) ) {
			$this->notes[] = 'Items marked stale were made by an earlier import and are no longer in the package; --prune removes them';
		}
	}

	/**
	 * Inserts, updates or keeps a post the package owns under $key.
	 *
	 * @param string               $key      Package key.
	 * @param array<string, mixed> $postarr  wp_insert_post() arguments.
	 * @param WP_Post|null         $existing The post made before (or adopted).
	 * @param string               $type     Row type.
	 * @param string|null          $action   Action to report instead of update.
	 * @return int Post ID, 0 in a dry run for a post still to be made.
	 * @throws InvalidArgumentException When WordPress refuses the post.
	 */
	private function save( string $key, array $postarr, ?WP_Post $existing, string $type, ?string $action = null ): int {
		$this->seen[ $key ] = true;
		$title              = (string) $postarr['post_title'];
		$source             = self::hash( $postarr );
		if ( null !== $existing ) {
			$owned = isset( $this->owned[ $key ] );
			if ( $owned && self::matches( $existing, $postarr ) ) {
				// Already as the package has it, edited in the panel or not
				// (a package exported from this site): kept, and from now on
				// compared with this package.
				$this->rows[] = Starter::row( 'keep', $type, $key, $existing->ID, $title );
				if ( ! $this->dry_run ) {
					update_post_meta( $existing->ID, self::SOURCE, $source );
					update_post_meta( $existing->ID, self::SAVED, self::state( $existing ) );
				}
				return $existing->ID;
			}
			$edited = $owned && (string) get_post_meta( $existing->ID, self::SAVED, true ) !== self::state( $existing );
			if ( $edited && ! $this->force ) {
				$this->rows[]  = Starter::row( 'skip', $type, $key, $existing->ID, $title );
				$this->notes[] = sprintf( '%s %s (%d) was edited in the panel after the last import and is kept as it is; --force overwrites it', $type, $key, $existing->ID );
				return $existing->ID;
			}
			if ( $owned && ! $edited && (string) get_post_meta( $existing->ID, self::SOURCE, true ) === $source ) {
				$this->rows[] = Starter::row( 'keep', $type, $key, $existing->ID, $title );
				return $existing->ID;
			}
			$action ??= 'update';
		}
		$action ??= 'create';
		if ( $this->dry_run ) {
			$this->rows[] = Starter::row( $action, $type, $key, $existing->ID ?? 0, $title );
			return $existing->ID ?? 0;
		}

		$postarr['ID']         = $existing->ID ?? 0;
		$postarr['meta_input'] = (array) ( $postarr['meta_input'] ?? array() ) + array(
			self::META   => $this->package->site['name'],
			self::KEY    => $key,
			self::SOURCE => $source,
		);
		// The content comes from the package and the theme's patterns, as
		// trusted as the theme, so it is saved without the HTML filter a user
		// without the unfiltered_html capability gets (WP-CLI has no user).
		$filtered = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		kses_remove_filters();
		$id = wp_insert_post( wp_slash( $postarr ), true );
		if ( $filtered ) {
			kses_init_filters();
		}
		if ( is_wp_error( $id ) ) {
			throw new InvalidArgumentException( esc_html( "$key: " . $id->get_error_message() ) );
		}
		$saved = get_post( $id );
		if ( $saved instanceof WP_Post ) {
			update_post_meta( $id, self::SAVED, self::state( $saved ) );
		}
		$this->rows[] = Starter::row( $action, $type, $key, $id, $title );
		return $id;
	}

	/**
	 * Resolves package images to attachments, importing each on first use.
	 *
	 * @return Closure(string): array{id: int, url: string}
	 */
	private function media(): Closure {
		return function ( string $path ): array {
			if ( ! isset( $this->images[ $path ] ) ) {
				$this->images[ $path ] = $this->image( $path );
			}
			return $this->images[ $path ];
		};
	}

	/**
	 * Imports, replaces, updates or keeps one image.
	 *
	 * @param string $path Package image path.
	 * @return array{id: int, url: string}
	 * @throws InvalidArgumentException When WordPress refuses the image.
	 */
	private function image( string $path ): array {
		$this->seen[ $path ] = true;
		$file                = $this->package->dir . '/' . $path;
		$sum                 = (string) sha1_file( $file );
		$details             = $this->package->media[ $path ] ?? array();
		$meta                = array( '_wp_attachment_image_alt' => $details['alt'] ?? '' );
		foreach ( self::CREDITS as $field => $meta_key ) {
			$meta[ $meta_key ] = $details[ $field ] ?? '';
		}
		$caption  = $details['caption'] ?? '';
		$existing = $this->owned[ $path ] ?? null;

		if ( null !== $existing && (string) get_post_meta( $existing->ID, self::FILE, true ) === $sum ) {
			$same = $existing->post_excerpt === $caption;
			foreach ( $meta as $meta_key => $value ) {
				$same = $same && (string) get_post_meta( $existing->ID, $meta_key, true ) === $value;
			}
			$this->rows[] = Starter::row( $same ? 'keep' : 'update', 'image', $path, $existing->ID, basename( $path ) );
			if ( ! $same && ! $this->dry_run ) {
				wp_update_post(
					array(
						'ID'           => $existing->ID,
						'post_excerpt' => $caption,
					)
				);
				foreach ( $meta as $meta_key => $value ) {
					update_post_meta( $existing->ID, $meta_key, $value );
				}
			}
			return array(
				'id'  => $existing->ID,
				'url' => (string) wp_get_attachment_url( $existing->ID ),
			);
		}

		// A new file under the same path replaces the old attachment.
		$action = null === $existing ? 'create' : 'replace';
		if ( $this->dry_run ) {
			$this->rows[] = Starter::row( $action, 'image', $path, $existing->ID ?? 0, basename( $path ) );
			return array(
				'id'  => 0,
				'url' => $path,
			);
		}
		if ( null !== $existing ) {
			wp_delete_attachment( $existing->ID, true );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$tmp = wp_tempnam( basename( $path ) );
		copy( $file, $tmp );
		$id = media_handle_sideload(
			array(
				'name'     => basename( $path ),
				'tmp_name' => $tmp,
			),
			0,
			pathinfo( $path, PATHINFO_FILENAME ),
			array( 'post_excerpt' => $caption )
		);
		if ( is_file( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		if ( is_wp_error( $id ) ) {
			throw new InvalidArgumentException( esc_html( "$path: " . $id->get_error_message() ) );
		}
		$meta += array(
			self::META => $this->package->site['name'],
			self::KEY  => $path,
			self::FILE => $sum,
		);
		foreach ( $meta as $meta_key => $value ) {
			update_post_meta( $id, $meta_key, $value );
		}
		$this->rows[] = Starter::row( $action, 'image', $path, $id, basename( $path ) );
		return array(
			'id'  => $id,
			'url' => (string) wp_get_attachment_url( $id ),
		);
	}

	/**
	 * SEO fields of a page from its content file.
	 *
	 * @param string|null $file Content file.
	 * @return array<string, string|bool>
	 */
	private function seo( ?string $file ): array {
		$seo = null === $file ? array() : $this->package->content[ $file ]['seo'];
		return array(
			Meta::TITLE       => (string) ( $seo['title'] ?? '' ),
			Meta::DESCRIPTION => (string) ( $seo['description'] ?? '' ),
			Meta::NOINDEX     => (bool) ( $seo['noindex'] ?? false ),
		);
	}

	/**
	 * Sets an option, reporting it.
	 *
	 * @param string     $option  Option name.
	 * @param string|int $value   New value.
	 * @param bool       $pending A page ID that a dry run does not have yet.
	 */
	private function option( string $option, string|int $value, bool $pending = false ): void {
		$same         = ! $pending && (string) get_option( $option ) === (string) $value;
		$this->rows[] = Starter::row( $same ? 'keep' : 'set', 'option', $option, 0, $pending ? '(new page)' : (string) $value );
		if ( ! $same && ! $this->dry_run ) {
			update_option( $option, $value );
		}
	}

	/**
	 * WooCommerce's page of a type, 0 when it has none.
	 *
	 * @param string $type Page type.
	 */
	private function shop_page( string $type ): int {
		return isset( self::SHOP_OPTIONS[ $type ] ) ? (int) get_option( self::SHOP_OPTIONS[ $type ] ) : 0;
	}

	/**
	 * Folder of the package's child theme.
	 */
	private function theme_dir(): string {
		return get_theme_root() . '/' . $this->package->site['name'];
	}

	/**
	 * Whether a theme folder holds a child theme a package wrote.
	 *
	 * @param string $dir Theme folder.
	 */
	private function is_package_theme( string $dir ): bool {
		$file = $dir . '/style.css';
		if ( ! is_file( $file ) ) {
			return false;
		}
		$mark = get_file_data( $file, array( 'package' => self::THEME_MARK ) )['package'];
		return $mark === $this->package->site['name'];
	}

	/**
	 * The child theme's style.css.
	 */
	private function theme_header(): string {
		return implode(
			"\n",
			array(
				'/*',
				'Theme Name: ' . $this->package->site['title'],
				'Template: ' . get_template(),
				'Description: Brand tokens of the project package ' . $this->package->site['name'] . '. Written by wp pergel import; changes here are overwritten.',
				'Version: 1.0.0',
				self::THEME_MARK . ': ' . $this->package->site['name'],
				'*/',
				'',
			)
		);
	}

	/**
	 * A post as saved, to tell later whether it was edited in the panel.
	 *
	 * @param WP_Post $post Post.
	 */
	private static function state( WP_Post $post ): string {
		$state = array( $post->post_title, $post->post_content, $post->post_name, $post->post_parent, $post->menu_order, $post->post_status );
		if ( 'page' === $post->post_type ) {
			$state[] = get_page_template_slug( $post );
			foreach ( array( Meta::TITLE, Meta::DESCRIPTION, Meta::NOINDEX ) as $key ) {
				$state[] = (string) get_post_meta( $post->ID, $key, true );
			}
		}
		return self::hash( $state );
	}

	/**
	 * Whether a post already holds what saving it would write.
	 *
	 * @param WP_Post              $post    Post.
	 * @param array<string, mixed> $postarr What save() was given.
	 */
	private static function matches( WP_Post $post, array $postarr ): bool {
		foreach ( array( 'post_title', 'post_content', 'post_name', 'post_parent', 'menu_order', 'post_status' ) as $field ) {
			if ( isset( $postarr[ $field ] ) && (string) $post->$field !== (string) $postarr[ $field ] ) {
				return false;
			}
		}
		if ( isset( $postarr['page_template'] ) && (string) get_page_template_slug( $post ) !== ( 'default' === $postarr['page_template'] ? '' : (string) $postarr['page_template'] ) ) {
			return false;
		}
		foreach ( (array) ( $postarr['meta_input'] ?? array() ) as $meta_key => $value ) {
			if ( (string) get_post_meta( $post->ID, (string) $meta_key, true ) !== (string) $value ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A short hash of a value.
	 *
	 * @param array<mixed> $value Value.
	 */
	private static function hash( array $value ): string {
		return sha1( (string) wp_json_encode( $value ) );
	}

	/**
	 * A setting value for the report.
	 *
	 * @param mixed $value Value.
	 */
	private static function show( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
