<?php
/**
 * Starter packages: a set of pages, a menu and reading settings built from
 * Pergel's full-page patterns, and for a shop, products to fill them.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Starters;

use InvalidArgumentException;
use WP_Block_Patterns_Registry;
use WP_Post;

use const Pergel\FILE;

/**
 * Reads `starters/<name>/starter.json`, installs it and removes it again.
 *
 * Every page and menu a starter makes carries two meta fields, the starter's
 * name and the item's key, so running the install again finds them instead
 * of making copies, and `remove` deletes only what the starter made. An
 * optional `shop` part (Shop) adds product categories, attributes, products
 * and their images; it needs WooCommerce.
 */
final class Starter {

	public const META     = '_pergel_starter';
	public const META_KEY = '_pergel_starter_key';
	private const FORMAT  = 1;

	/**
	 * Package data from starter.json, validated.
	 *
	 * @var array{title: string, description: string, pages: list<array<string, mixed>>, menu: array{title: string|array<string, string>, pages: list<string>}, shop: array<string, mixed>|null}
	 */
	private array $data;

	/**
	 * Use load().
	 *
	 * @param string               $name Starter name: its folder name.
	 * @param array<string, mixed> $data Decoded starter.json.
	 * @throws InvalidArgumentException When the package is not valid.
	 */
	private function __construct( public readonly string $name, array $data ) {
		$this->data = self::validate( $data );
	}

	/**
	 * Folder of the starters that come with Pergel.
	 */
	public static function dir(): string {
		return dirname( FILE, 2 ) . '/starters';
	}

	/**
	 * Starters in dir(), by name.
	 *
	 * @return array<string, self>
	 */
	public static function available(): array {
		$found = array();
		$files = glob( self::dir() . '/*/starter.json' );
		foreach ( false === $files ? array() : $files as $file ) {
			$name = basename( dirname( $file ) );
			try {
				$found[ $name ] = self::load( $name );
			} catch ( InvalidArgumentException ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- `install` reports the problem.
			}
		}
		return $found;
	}

	/**
	 * Reads a starter by name (from dir()) or from a folder path.
	 *
	 * @param string $name_or_path Starter name or folder.
	 * @throws InvalidArgumentException When it is missing or not valid.
	 */
	public static function load( string $name_or_path ): self {
		$dir  = preg_match( '/^[a-z0-9-]+$/', $name_or_path ) ? self::dir() . '/' . $name_or_path : rtrim( $name_or_path, '/\\' );
		$file = $dir . '/starter.json';
		if ( ! is_file( $file ) ) {
			throw new InvalidArgumentException( esc_html( "No starter at $dir (starter.json not found)." ) );
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		if ( ! is_array( $data ) ) {
			throw new InvalidArgumentException( esc_html( "$file is not valid JSON." ) );
		}
		return new self( basename( $dir ), $data );
	}

	/**
	 * Title in the site language.
	 */
	public function title(): string {
		return $this->data['title'];
	}

	/**
	 * Description in the site language.
	 */
	public function description(): string {
		return $this->data['description'];
	}

	/**
	 * Makes the starter's shop data, pages, menu and reading settings.
	 *
	 * Items the starter made before are kept as they are, unless $reset is
	 * set: then their title, content and template go back to the starter's.
	 *
	 * @param bool $dry_run Only report what would happen.
	 * @param bool $reset   Rewrite items made before.
	 * @return list<array{action: string, type: string, key: string, id: string, title: string}>
	 */
	public function install( bool $dry_run = false, bool $reset = false ): array {
		$rows       = array();
		$ids        = array();
		$categories = array();

		// Products first: the shop pages show them.
		if ( null !== $this->data['shop'] ) {
			$shop       = ( new Shop( $this->name, $this->data['shop'] ) )->install( $dry_run, $reset );
			$rows       = $shop['rows'];
			$categories = $shop['categories'];
		}

		foreach ( $this->data['pages'] as $page ) {
			$key      = (string) $page['key'];
			$existing = $this->find( 'page', $key );
			$title    = self::pick( $page['title'] );
			$slug     = sanitize_title( self::pick( $page['slug'] ) );
			$postarr  = array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => isset( $page['pattern'] ) ? self::content( (string) $page['pattern'] ) : '',
				'meta_input'   => array(
					self::META     => $this->name,
					self::META_KEY => $key,
				),
			);
			if ( isset( $page['pattern'] ) ) {
				// Full pages bring their own H1 in the hero.
				$postarr['page_template'] = 'page-landing';
			}

			if ( $existing && ! $reset ) {
				$action = 'keep';
			} elseif ( $existing ) {
				$action = 'reset';
			} else {
				$action               = 'create';
				$postarr['post_name'] = $slug;
				$taken                = get_page_by_path( $slug );
				if ( $taken instanceof WP_Post ) {
					$title .= sprintf( ' (/%s/ is taken, WordPress picks another address)', $slug );
				}
			}
			$id = $existing ? $existing->ID : 0;
			if ( ! $dry_run && 'keep' !== $action ) {
				$id = $this->save( $postarr + array( 'ID' => $id ) );
			}
			$ids[ $key ] = $id;
			$rows[]      = self::row( $action, 'page', $key, $id, $title );
		}

		$rows[] = $this->install_menu( $ids, $categories, $dry_run, $reset );

		foreach ( $this->reading( $ids ) as $option => $value ) {
			// In a dry run a page still to be made has no ID yet.
			$same   = 0 !== $value && (string) get_option( $option ) === (string) $value;
			$rows[] = self::row( $same ? 'keep' : 'set', 'option', $option, 0, 0 === $value ? '(new page)' : (string) $value );
			if ( ! $dry_run && ! $same ) {
				update_option( $option, $value );
			}
		}
		return $rows;
	}

	/**
	 * Deletes every page and menu the named starter made, edited or not, and
	 * its shop data.
	 *
	 * Reading settings that point at a deleted page go back to showing the
	 * latest posts.
	 *
	 * @param string $name    Starter name.
	 * @param bool   $dry_run Only report what would happen.
	 * @return list<array{action: string, type: string, key: string, id: string, title: string}>
	 */
	public static function remove( string $name, bool $dry_run = false ): array {
		$rows  = Shop::remove( $name, $dry_run );
		$posts = get_posts(
			array(
				'post_type'      => array( 'page', 'wp_navigation' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI only.
				'meta_value'     => $name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- CLI only.
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		// Read before deleting: WordPress resets these itself when the page goes.
		$deleted = wp_list_pluck( $posts, 'ID' );
		$front   = in_array( (int) get_option( 'page_on_front' ), $deleted, true );
		$blog    = in_array( (int) get_option( 'page_for_posts' ), $deleted, true );
		foreach ( $posts as $post ) {
			$type   = 'page' === $post->post_type ? 'page' : 'menu';
			$rows[] = self::row( 'delete', $type, (string) get_post_meta( $post->ID, self::META_KEY, true ), $post->ID, $post->post_title );
			if ( ! $dry_run ) {
				wp_delete_post( $post->ID, true );
			}
		}

		$reset = array_filter(
			array(
				'show_on_front'  => $front ? 'posts' : null,
				'page_on_front'  => $front ? 0 : null,
				'page_for_posts' => $blog ? 0 : null,
			),
			static fn( $value ): bool => null !== $value
		);
		foreach ( $reset as $option => $value ) {
			$rows[] = self::row( 'set', 'option', $option, 0, 0 === $value ? '-' : (string) $value );
			if ( ! $dry_run ) {
				update_option( $option, $value );
			}
		}
		return $rows;
	}

	/**
	 * Page content: the full-page pattern's sections, each marked the way
	 * the block inserter marks a section, so it opens in content-only mode.
	 *
	 * @param string $name Full-page pattern name.
	 * @throws InvalidArgumentException When a pattern is not registered.
	 */
	public static function content( string $name ): string {
		$sections = array();
		foreach ( self::blocks( $name ) as $block ) {
			if ( 'core/pattern' !== $block['blockName'] ) {
				$sections[] = serialize_block( $block );
				continue;
			}
			$slug    = (string) ( $block['attrs']['slug'] ?? '' );
			$pattern = self::pattern( $slug );
			$roots   = self::blocks( $slug );
			if ( 1 === count( $roots ) ) {
				$roots[0]['attrs']['metadata'] = array_merge(
					(array) ( $roots[0]['attrs']['metadata'] ?? array() ),
					array_filter(
						array(
							'patternName' => $slug,
							'name'        => $pattern['title'] ?? '',
							'description' => $pattern['description'] ?? '',
							'categories'  => $pattern['categories'] ?? array(),
						)
					)
				);
			}
			$sections[] = serialize_blocks( $roots );
		}
		return implode( "\n\n", $sections );
	}

	/**
	 * Makes or finds the starter's navigation menu.
	 *
	 * Navigation blocks without a menu of their own show the newest menu,
	 * so the header picks it up.
	 *
	 * @param array<string, int> $ids        Page IDs by key (0 in a dry run).
	 * @param array<string, int> $categories Product category IDs by key.
	 * @param bool               $dry_run    Only report.
	 * @param bool               $reset      Rewrite the menu if it exists.
	 * @return array{action: string, type: string, key: string, id: string, title: string}
	 */
	private function install_menu( array $ids, array $categories, bool $dry_run, bool $reset ): array {
		$menu     = $this->data['menu'];
		$title    = self::pick( $menu['title'] );
		$existing = $this->find( 'wp_navigation', 'menu' );
		$action   = $existing ? ( $reset ? 'reset' : 'keep' ) : 'create';
		$id       = $existing ? $existing->ID : 0;
		if ( $dry_run || 'keep' === $action ) {
			return self::row( $action, 'menu', 'menu', $id, $title );
		}

		$links = array();
		foreach ( $menu['pages'] as $key ) {
			if ( ! isset( $ids[ $key ] ) ) {
				$links[] = Shop::menu_link( $key, $categories );
				continue;
			}
			$page = get_post( $ids[ $key ] );
			if ( $page instanceof WP_Post ) {
				$links[] = get_comment_delimited_block_content(
					'core/navigation-link',
					array(
						'label' => $page->post_title,
						'type'  => 'page',
						'id'    => $page->ID,
						'url'   => (string) get_permalink( $page ),
						'kind'  => 'post-type',
					),
					''
				);
			}
		}
		$id = $this->save(
			array(
				'ID'           => $id,
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => implode( "\n", array_filter( $links ) ),
				'meta_input'   => array(
					self::META     => $this->name,
					self::META_KEY => 'menu',
				),
			)
		);
		return self::row( $action, 'menu', 'menu', $id, $title );
	}

	/**
	 * Reading settings for the front page and the posts page.
	 *
	 * @param array<string, int> $ids Page IDs by key.
	 * @return array<string, string|int>
	 */
	private function reading( array $ids ): array {
		$options = array();
		foreach ( $this->data['pages'] as $page ) {
			if ( ! empty( $page['front_page'] ) ) {
				$options['show_on_front'] = 'page';
				$options['page_on_front'] = $ids[ $page['key'] ];
			}
			if ( ! empty( $page['posts_page'] ) ) {
				$options['page_for_posts'] = $ids[ $page['key'] ];
			}
		}
		return $options;
	}

	/**
	 * The page or menu this starter made under $key.
	 *
	 * @param string $post_type Post type.
	 * @param string $key       Item key.
	 */
	private function find( string $post_type, string $key ): ?WP_Post {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI only.
					array(
						'key'   => self::META,
						'value' => $this->name,
					),
					array(
						'key'   => self::META_KEY,
						'value' => $key,
					),
				),
			)
		);
		return $posts[0] ?? null;
	}

	/**
	 * Inserts or updates a post.
	 *
	 * The content comes from the theme's own pattern files, as trusted as its
	 * templates, so it is saved without the HTML filter a user without the
	 * unfiltered_html capability gets (WP-CLI has no user).
	 *
	 * @param array<string, mixed> $postarr wp_insert_post() arguments.
	 * @throws InvalidArgumentException When WordPress refuses the post.
	 */
	private function save( array $postarr ): int {
		$filtered = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		kses_remove_filters();
		$id = wp_insert_post( wp_slash( $postarr ), true );
		if ( $filtered ) {
			kses_init_filters();
		}
		if ( is_wp_error( $id ) ) {
			throw new InvalidArgumentException( esc_html( $id->get_error_message() ) );
		}
		return $id;
	}

	/**
	 * Top-level blocks of a registered pattern, without the whitespace between them.
	 *
	 * @param string $name Pattern name.
	 * @return list<array{blockName: string|null, attrs: array<string, mixed>, innerBlocks: array<array<string, mixed>>, innerHTML: string, innerContent: array<string|null>}>
	 * @throws InvalidArgumentException When the pattern is not registered.
	 */
	private static function blocks( string $name ): array {
		$blocks = parse_blocks( (string) ( self::pattern( $name )['content'] ?? '' ) );
		return array_values( array_filter( $blocks, static fn( array $block ): bool => null !== $block['blockName'] ) );
	}

	/**
	 * A registered pattern.
	 *
	 * @param string $name Pattern name.
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When it is not registered.
	 */
	private static function pattern( string $name ): array {
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( $name );
		if ( ! is_array( $pattern ) ) {
			throw new InvalidArgumentException( esc_html( "Pattern $name is not registered. Is the Pergel theme active?" ) );
		}
		return $pattern;
	}

	/**
	 * Checks starter.json against format 1.
	 *
	 * @param array<string, mixed> $data Decoded starter.json.
	 * @return array{title: string, description: string, pages: list<array<string, mixed>>, menu: array{title: string|array<string, string>, pages: list<string>}, shop: array<string, mixed>|null}
	 * @throws InvalidArgumentException On the first problem.
	 */
	private static function validate( array $data ): array {
		$fail = static function ( string $message ): never {
			throw new InvalidArgumentException( esc_html( "starter.json: $message" ) );
		};
		if ( self::FORMAT !== ( $data['format'] ?? null ) ) {
			$fail( 'format must be ' . self::FORMAT . '.' );
		}
		$shop = null;
		if ( isset( $data['shop'] ) ) {
			// Before the pages: their shop patterns are missing without WooCommerce too.
			if ( ! Shop::active() ) {
				$fail( 'this starter has a shop and needs WooCommerce. Install and activate WooCommerce first.' );
			}
			$shop = Shop::validate( $data['shop'] );
		}
		$pages = $data['pages'] ?? null;
		if ( ! is_array( $pages ) || ! array_is_list( $pages ) || array() === $pages ) {
			$fail( 'pages must be a non-empty list.' );
		}
		$keys = array();
		foreach ( $pages as $i => $page ) {
			$key = is_array( $page ) ? ( $page['key'] ?? null ) : null;
			if ( ! is_string( $key ) || ! preg_match( '/^[a-z0-9-]+$/', $key ) || isset( $keys[ $key ] ) ) {
				$fail( "pages[$i].key must be a unique lowercase key." );
			}
			foreach ( array( 'title', 'slug' ) as $field ) {
				if ( '' === self::pick( $page[ $field ] ?? '' ) ) {
					$fail( "pages[$i].$field is missing." );
				}
			}
			if ( isset( $page['pattern'] ) ) {
				self::pattern( (string) $page['pattern'] );
			} elseif ( empty( $page['posts_page'] ) ) {
				$fail( "pages[$i] needs a pattern unless it is the posts page." );
			}
			$keys[ $key ] = true;
		}
		foreach ( array( 'front_page', 'posts_page' ) as $flag ) {
			if ( count( array_filter( $pages, static fn( array $page ): bool => ! empty( $page[ $flag ] ) ) ) > 1 ) {
				$fail( "only one page can be the $flag." );
			}
		}
		$menu = $data['menu'] ?? null;
		if ( ! is_array( $menu ) || '' === self::pick( $menu['title'] ?? '' ) || ! is_array( $menu['pages'] ?? null ) ) {
			$fail( 'menu needs a title and a list of page keys.' );
		}
		$shop_links = array();
		if ( null !== $shop ) {
			$shop_links['wc:shop'] = true;
			foreach ( $shop['categories'] as $category ) {
				$shop_links[ 'category:' . $category['key'] ] = true;
			}
		}
		foreach ( $menu['pages'] as $key ) {
			if ( ! is_string( $key ) || ! ( isset( $keys[ $key ] ) || isset( $shop_links[ $key ] ) ) ) {
				$fail( 'menu.pages lists an unknown page key (shop starters may also use wc:shop and category:<key>).' );
			}
		}
		return array(
			'title'       => self::pick( $data['title'] ?? '' ),
			'description' => self::pick( $data['description'] ?? '' ),
			'pages'       => $pages,
			'menu'        => array(
				'title' => $menu['title'],
				'pages' => array_values( $menu['pages'] ),
			),
			'shop'        => $shop,
		);
	}

	/**
	 * A text in the site language: a plain string, or a map such as
	 * {"en": "About us", "tr": "Hakkımızda"} read as tr_TR, then tr, then en.
	 *
	 * @param mixed $value String or language map.
	 */
	public static function pick( mixed $value ): string {
		if ( ! is_array( $value ) ) {
			return is_string( $value ) ? $value : '';
		}
		$locale = get_locale();
		foreach ( array( $locale, strtok( $locale, '_' ), 'en' ) as $lang ) {
			if ( is_string( $lang ) && is_string( $value[ $lang ] ?? null ) ) {
				return $value[ $lang ];
			}
		}
		$first = reset( $value );
		return is_string( $first ) ? $first : '';
	}

	/**
	 * One line of the report.
	 *
	 * @param string $action create, reset, keep, set or delete.
	 * @param string $type   page, menu, option, category, attribute, product or image.
	 * @param string $key    Item key or option name.
	 * @param int    $id     Post ID, 0 when there is none (yet).
	 * @param string $title  Title or new option value.
	 * @return array{action: string, type: string, key: string, id: string, title: string}
	 */
	public static function row( string $action, string $type, string $key, int $id, string $title ): array {
		return array(
			'action' => $action,
			'type'   => $type,
			'key'    => $key,
			'id'     => $id > 0 ? (string) $id : '-',
			'title'  => $title,
		);
	}
}
