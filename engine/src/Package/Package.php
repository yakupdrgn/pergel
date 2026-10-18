<?php
/**
 * A project package read from disk.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Package;

use Closure;
use InvalidArgumentException;
use Pergel\Settings\Schema;
use Pergel\Tokens\Compiler;
use Pergel\Tokens\Invalid;

/**
 * Reads package.yaml, sitemap.yaml, content/*.md, media.yaml and
 * brand/tokens.json, and reports every problem at once as
 * "file: where: problem", so the author fixes them in one pass.
 * See docs/PROJECT-PACKAGE.md for the format.
 *
 * Problems stop an import; notes do not (unless --strict): a field that
 * keeps the pattern's sample text, an image without alt text, a content
 * file no page uses.
 *
 * read() needs only PHP. check() fills patterns and checks settings, so it
 * runs inside WordPress.
 *
 * @phpstan-type Page array{path: string, parent: string|null, slug: string, title: string, content: string|null, template: string, type: string, menu: bool, where: string}
 * @phpstan-type Content array{seo: array<string, mixed>, sections: list<array<string, mixed>>, body: string, offset: int}
 */
final class Package {

	/**
	 * Package format this engine reads.
	 */
	public const FORMAT = 1;

	/**
	 * Image file types a package may hold.
	 */
	public const IMAGE_TYPES = array( 'jpg', 'jpeg', 'png', 'webp', 'avif', 'gif' );

	/**
	 * Page types; all but "page" may be used once.
	 */
	public const PAGE_TYPES = array( 'page', 'posts', 'shop', 'cart', 'checkout', 'account' );

	/**
	 * Page types left out of the menu unless the sitemap lists a menu.
	 */
	private const NOT_IN_MENU = array( 'cart', 'checkout', 'account' );

	/**
	 * Slugs: lowercase ASCII words joined by single hyphens.
	 */
	private const SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

	/**
	 * Site details from package.yaml.
	 *
	 * @var array{name: string, title: string, tagline: string|null, locale: string|null}
	 */
	public array $site = array(
		'name'    => '',
		'title'   => '',
		'tagline' => null,
		'locale'  => null,
	);

	/**
	 * Settings from package.yaml as written: group => key => value, images as package paths.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public array $settings = array();

	/**
	 * Pages in sitemap order, each parent before its children.
	 *
	 * @var list<Page>
	 */
	public array $pages = array();

	/**
	 * Menu items: { label, page (path) or url, children }.
	 *
	 * @var list<array<string, mixed>>
	 */
	public array $menu = array();

	/**
	 * Content files by package path.
	 *
	 * @var array<string, Content>
	 */
	public array $content = array();

	/**
	 * Image details from media.yaml: package path => { alt, caption, source, license, author }.
	 *
	 * @var array<string, array<string, string>>
	 */
	public array $media = array();

	/**
	 * Design tokens from brand/tokens.json, when the package has one.
	 *
	 * @var array<string, mixed>|null
	 */
	public ?array $tokens = null;

	/**
	 * What stops an import.
	 *
	 * @var list<string>
	 */
	public array $problems = array();

	/**
	 * What an author should look at.
	 *
	 * @var array<int, string>
	 */
	public array $notes = array();

	/**
	 * Use read().
	 *
	 * @param string $dir Package directory, with / separators and no trailing one.
	 */
	private function __construct( public readonly string $dir ) {
	}

	/**
	 * Reads a package directory.
	 *
	 * @param string $dir Package directory.
	 */
	public static function read( string $dir ): self {
		$package = new self( rtrim( str_replace( '\\', '/', $dir ), '/' ) );
		if ( ! is_dir( $package->dir ) ) {
			$package->problems[] = "$dir: not a directory";
			return $package;
		}
		$package->read_site();
		$package->read_media();
		$package->read_sitemap();
		$package->read_tokens();
		foreach ( $package->files( 'content', '.md' ) as $file ) {
			if ( ! isset( $package->content[ $file ] ) ) {
				$package->notes[] = "$file: no page in sitemap.yaml uses this file";
			}
		}
		return $package;
	}

	/**
	 * Checks what needs WordPress: patterns and their fields, page
	 * templates, setting values and design tokens.
	 *
	 * @param Closure              $pattern   Parsed blocks of a pattern by name, null when there is none.
	 * @param array<int, string>   $templates Page template slugs the theme has.
	 * @param array<string, mixed> $tokens    The theme's token tree that brand/tokens.json changes.
	 * @phpstan-param Closure(string): (list<array<string, mixed>>|null) $pattern
	 */
	public function check( Closure $pattern, array $templates, array $tokens ): void {
		foreach ( $this->pages as $page ) {
			if ( '' !== $page['template'] && ! in_array( $page['template'], $templates, true ) ) {
				$this->problems[] = sprintf( 'sitemap.yaml: %s: template: "%s" is not a page template; templates: %s', $page['where'], $page['template'], implode( ', ', $templates ) );
			}
		}

		$media = static fn( string $path ): array => array(
			'id'  => 0,
			'url' => $path,
		);
		foreach ( array_keys( $this->content ) as $file ) {
			$this->page_content( $file, $pattern, $media );
		}

		$this->problems = array_merge( $this->problems, $this->settings( $media )['problems'] );

		if ( null !== $this->tokens ) {
			try {
				Compiler::compile( Compiler::merge( $tokens, $this->tokens ), 'brand/tokens.json' );
			} catch ( Invalid $e ) {
				$this->problems = array_merge( $this->problems, $e->problems );
			}
		}
		$this->notes = array_values( array_unique( $this->notes ) );
	}

	/**
	 * Runs check() against the active theme's patterns, templates and tokens.
	 */
	public function check_theme(): void {
		try {
			$tokens = Compiler::read_json( get_template_directory() . '/tokens/default.json' );
			$child  = get_stylesheet_directory() . '/tokens/default.json';
			if ( get_stylesheet_directory() !== get_template_directory() && is_file( $child ) ) {
				$tokens = Compiler::merge( $tokens, Compiler::read_json( $child ) );
			}
		} catch ( Invalid $e ) {
			$this->problems = array_merge( $this->problems, $e->problems );
			$tokens         = array();
		}
		// The theme's own page templates; plugins add templates of their own that a package does not choose.
		$templates = array();
		foreach ( wp_get_theme_data_custom_templates() as $name => $data ) {
			if ( in_array( 'page', (array) $data['postTypes'], true ) ) {
				$templates[] = (string) $name;
			}
		}
		$this->check( Closure::fromCallable( array( self::class, 'pattern' ) ), $templates, $tokens );
	}

	/**
	 * Parsed blocks of a registered Pergel pattern.
	 *
	 * @param string $name Pattern name, like pergel/hero-split.
	 * @return list<array<string, mixed>>|null Null when there is no such pattern.
	 */
	public static function pattern( string $name ): ?array {
		$pattern = str_starts_with( $name, 'pergel/' ) ? \WP_Block_Patterns_Registry::get_instance()->get_registered( $name ) : null;
		return is_array( $pattern ) ? array_values( parse_blocks( $pattern['content'] ) ) : null;
	}

	/**
	 * Block markup of a content file: its sections, then its Markdown body
	 * (or the body where a `markdown: true` section stands). Problems are
	 * added to $this->problems; the markup leaves out what has a problem.
	 *
	 * @param string  $file    Content file, as the sitemap names it.
	 * @param Closure $pattern Parsed blocks of a pattern by name, see check().
	 * @param Closure $media   Turns a package image path into an attachment id (0 = none) and URL.
	 * @phpstan-param Closure(string): (list<array<string, mixed>>|null) $pattern
	 * @phpstan-param Closure(string): array{id: int, url: string} $media
	 */
	public function page_content( string $file, Closure $pattern, Closure $media ): string {
		$content = $this->content[ $file ];
		$parts   = array();
		$body    = null;

		foreach ( $content['sections'] as $i => $section ) {
			$where = sprintf( '%s: sections[%d]', $file, $i + 1 );
			if ( isset( $section['markdown'] ) ) {
				$body    = $this->body( $file, $media );
				$parts[] = $body;
				continue;
			}
			$name   = (string) $section['pattern'];
			$blocks = $pattern( str_contains( $name, '/' ) ? $name : "pergel/$name" );
			if ( null === $blocks ) {
				$this->problems[] = "$where: pattern: \"$name\" is not a Pergel pattern; wp pergel pattern list shows them";
				continue;
			}
			unset( $section['pattern'] );
			$image = function ( string $src, ?string $alt ) use ( $media, $where ): array {
				$path = $this->image( $src );
				if ( null === $alt ) {
					$alt = $this->alt( $path, $where );
				}
				return array( 'alt' => $alt ) + $media( $path );
			};
			$kept  = array();
			try {
				$parts[] = serialize_blocks( Fields::fill( $blocks, $section, $image, $kept ) );
			} catch ( InvalidArgumentException $e ) {
				$this->problems[] = "$where ($name): " . $e->getMessage();
			}
			foreach ( $kept as $field ) {
				$this->notes[] = "$where ($name): $field keeps the pattern's sample text; give it a value, or null to remove it";
			}
		}

		if ( null === $body && '' !== trim( $content['body'] ) ) {
			$parts[] = $this->body( $file, $media );
		}
		return implode( "\n\n", array_filter( $parts, static fn( string $part ): bool => '' !== $part ) );
	}

	/**
	 * Settings to apply, with image paths turned into attachment ids, and
	 * their problems.
	 *
	 * @param Closure $media Turns a package image path into an attachment id and URL.
	 * @phpstan-param Closure(string): array{id: int, url: string} $media
	 * @return array{changes: array<string, array<string, mixed>>, problems: list<string>}
	 */
	public function settings( Closure $media ): array {
		$groups   = Schema::groups();
		$changes  = array();
		$problems = array();
		foreach ( $this->settings as $group => $values ) {
			foreach ( $values as $key => $value ) {
				if ( 'image' === $groups[ $group ][ $key ]['type'] ) {
					try {
						$value = $media( $this->image( is_string( $value ) ? $value : '' ) )['id'];
					} catch ( InvalidArgumentException $e ) {
						$problems[] = "package.yaml: settings.$group.$key: " . $e->getMessage();
						continue;
					}
				}
				$changes[ $group ][ $key ] = $value;
			}
		}
		foreach ( Schema::sanitize( $changes )['problems'] as $problem ) {
			$problems[] = sprintf( 'package.yaml: settings.%s: %s', $problem['field'], str_replace( '_', ' ', $problem['code'] ) );
		}
		return array(
			'changes'  => $changes,
			'problems' => $problems,
		);
	}

	/**
	 * Checks an image path: a jpg, jpeg, png, webp, avif or gif file in the package.
	 *
	 * @param string $src Path as written.
	 * @return string The path.
	 * @throws InvalidArgumentException When the path cannot be used.
	 */
	public function image( string $src ): string {
		if ( preg_match( '#^([a-z][a-z0-9+.-]*:|//)#i', $src ) ) {
			throw new InvalidArgumentException( "\"$src\": only images in the package can be used; save the image under media/ and write its path" );
		}
		if ( str_starts_with( $src, '/' ) || str_contains( $src, '\\' ) || in_array( '..', explode( '/', $src ), true ) ) {
			throw new InvalidArgumentException( "\"$src\": a path in the package is expected, like media/hero.jpg" );
		}
		if ( ! in_array( strtolower( pathinfo( $src, PATHINFO_EXTENSION ) ), self::IMAGE_TYPES, true ) ) {
			throw new InvalidArgumentException( "\"$src\": an image is a " . implode( ', ', self::IMAGE_TYPES ) . ' file' );
		}
		if ( ! is_file( $this->dir . '/' . $src ) ) {
			throw new InvalidArgumentException( "\"$src\": file not found in the package" );
		}
		return $src;
	}

	/**
	 * Alt text of an image from media.yaml; "" (with a note) when it has none.
	 *
	 * @param string $path  Package image path.
	 * @param string $where Where the image is used, for the note.
	 */
	public function alt( string $path, string $where ): string {
		if ( isset( $this->media[ $path ]['alt'] ) ) {
			return $this->media[ $path ]['alt'];
		}
		$this->notes[] = "$where: $path has no alt text; add it in media.yaml (alt: \"\" marks a decorative image)";
		return '';
	}

	/**
	 * Reads package.yaml.
	 */
	private function read_site(): void {
		$data = $this->yaml( 'package.yaml', true );
		if ( null === $data ) {
			return;
		}
		$where = 'package.yaml: ';
		$this->keys( $data, array( 'format', 'name', 'title', 'tagline', 'locale', 'settings' ), $where );

		$format = $data['format'] ?? null;
		if ( null === $format ) {
			$this->problems[] = $where . 'format: required; write format: ' . self::FORMAT;
		} elseif ( self::FORMAT !== $format ) {
			$this->problems[] = $where . 'format: this engine reads format ' . self::FORMAT;
		}

		$name = $data['name'] ?? null;
		if ( ! is_string( $name ) || ! preg_match( self::SLUG, $name ) ) {
			$this->problems[] = $where . 'name: required, in lowercase letters, digits and hyphens (like a-sirketi)';
		} else {
			$this->site['name'] = $name;
		}

		$title = $data['title'] ?? null;
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$this->problems[] = $where . 'title: required; it is the site title';
		} else {
			$this->site['title'] = $title;
		}

		foreach ( array( 'tagline', 'locale' ) as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( ! is_string( $data[ $key ] ) ) {
				$this->problems[] = $where . "$key: text is expected";
				continue;
			}
			if ( 'locale' === $key && ! preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $data[ $key ] ) ) {
				$this->problems[] = $where . "locale: \"{$data[$key]}\" is not a locale like tr_TR or en_US";
				continue;
			}
			$this->site[ $key ] = $data[ $key ];
		}

		if ( array_key_exists( 'settings', $data ) ) {
			$this->read_settings( $data['settings'] );
		}
	}

	/**
	 * Reads the settings map of package.yaml; values are checked in check().
	 *
	 * @param mixed $settings The settings value.
	 */
	private function read_settings( mixed $settings ): void {
		$where  = 'package.yaml: settings';
		$groups = Schema::groups();
		if ( ! self::is_map( $settings ) ) {
			$this->problems[] = "$where: a map of setting groups is expected";
			return;
		}
		$this->keys( $settings, array_keys( $groups ), "$where: " );
		foreach ( $settings as $group => $values ) {
			if ( ! isset( $groups[ $group ] ) ) {
				continue;
			}
			if ( ! self::is_map( $values ) ) {
				$this->problems[] = "$where.$group: a map of settings is expected";
				continue;
			}
			$this->keys( $values, array_keys( $groups[ $group ] ), "$where.$group: " );
			$this->settings[ $group ] = array_intersect_key( $values, $groups[ $group ] );
		}
	}

	/**
	 * Reads media.yaml.
	 */
	private function read_media(): void {
		$data = $this->yaml( 'media.yaml', false );
		foreach ( $data ?? array() as $path => $meta ) {
			$where = "media.yaml: $path: ";
			try {
				$this->image( (string) $path );
			} catch ( InvalidArgumentException $e ) {
				$this->problems[] = 'media.yaml: ' . $e->getMessage();
				continue;
			}
			if ( ! self::is_map( $meta ) ) {
				$this->problems[] = $where . 'a map like { alt: ..., source: ..., license: ... } is expected';
				continue;
			}
			$this->keys( $meta, array( 'alt', 'caption', 'source', 'license', 'author' ), $where );
			foreach ( $meta as $key => $value ) {
				if ( ! is_string( $value ) ) {
					$this->problems[] = $where . "$key: text is expected";
					unset( $meta[ $key ] );
				}
			}
			$this->media[ (string) $path ] = $meta;
		}
	}

	/**
	 * Reads sitemap.yaml and the content files its pages use.
	 */
	private function read_sitemap(): void {
		$data = $this->yaml( 'sitemap.yaml', true );
		if ( null === $data ) {
			return;
		}
		$this->keys( $data, array( 'pages', 'menu' ), 'sitemap.yaml: ' );
		$pages = $data['pages'] ?? null;
		if ( ! is_array( $pages ) || ! array_is_list( $pages ) || array() === $pages ) {
			$this->problems[] = 'sitemap.yaml: pages: a list of pages is required';
			return;
		}
		$types = array();
		$this->read_pages( $pages, null, 'pages', $types );
		$this->menu = array_key_exists( 'menu', $data )
			? $this->read_menu( $data['menu'], 'menu' )
			: $this->default_menu( null );
	}

	/**
	 * Reads a list of pages.
	 *
	 * @param array<int, mixed>     $pages Pages as written.
	 * @param string|null           $under Parent page path; null at the top.
	 * @param string                $at    Where the list is, like pages[2].children.
	 * @param array<string, string> $types Special page types seen => where.
	 */
	private function read_pages( array $pages, ?string $under, string $at, array &$types ): void {
		$slugs = array();
		foreach ( $pages as $i => $page ) {
			$here  = sprintf( '%s[%d]', $at, $i + 1 );
			$where = "sitemap.yaml: $here: ";
			if ( ! self::is_map( $page ) ) {
				$this->problems[] = $where . 'a page is a map like { slug: ..., title: ... }';
				continue;
			}
			$this->keys( $page, array( 'slug', 'title', 'content', 'template', 'type', 'menu', 'children' ), $where );

			$slug = $page['slug'] ?? null;
			if ( ! is_string( $slug ) ) {
				$this->problems[] = $where . 'slug: required, as text ("" for the front page)';
				continue;
			}
			if ( '' === $slug && null !== $under ) {
				$this->problems[] = $where . 'slug: only a top-level page can be the front page (slug "")';
			} elseif ( '' !== $slug && ! preg_match( self::SLUG, $slug ) ) {
				$this->problems[] = $where . "slug: \"$slug\" is not a slug; use lowercase letters, digits and hyphens";
			}
			if ( isset( $slugs[ $slug ] ) ) {
				$this->problems[] = $where . ( '' === $slug ? 'slug: only one page can be the front page' : "slug: \"$slug\" is used twice at this level" );
			}
			$slugs[ $slug ] = true;
			$path           = null === $under ? $slug : "$under/$slug";

			$title = $page['title'] ?? null;
			if ( ! is_string( $title ) || '' === trim( $title ) ) {
				$this->problems[] = $where . 'title: required';
				$title            = '';
			}

			$content = null;
			if ( array_key_exists( 'content', $page ) ) {
				$content = $this->content_path( $page['content'], $where );
				if ( null !== $content && ! isset( $this->content[ $content ] ) ) {
					$this->read_content( $content );
				}
			}

			$template = $page['template'] ?? '';
			if ( ! is_string( $template ) ) {
				$this->problems[] = $where . 'template: text is expected';
				$template         = '';
			}

			$type = $page['type'] ?? 'page';
			if ( ! in_array( $type, self::PAGE_TYPES, true ) ) {
				$this->problems[] = $where . 'type: one of ' . implode( ', ', self::PAGE_TYPES ) . ' is expected';
				$type             = 'page';
			} elseif ( 'page' !== $type ) {
				if ( isset( $types[ $type ] ) ) {
					$this->problems[] = $where . "type: only one page can be the $type page; {$types[$type]} is";
				}
				$types[ $type ] = $here;
			}

			$menu = $page['menu'] ?? true;
			if ( ! is_bool( $menu ) ) {
				$this->problems[] = $where . 'menu: true or false is expected';
				$menu             = true;
			}

			$this->pages[] = array(
				'path'     => $path,
				'parent'   => $under,
				'slug'     => $slug,
				'title'    => $title,
				'content'  => $content,
				'template' => $template,
				'type'     => $type,
				'menu'     => $menu,
				'where'    => $here,
			);

			$children = $page['children'] ?? array();
			if ( ! is_array( $children ) || ! array_is_list( $children ) ) {
				$this->problems[] = $where . 'children: a list of pages is expected';
			} elseif ( array() !== $children ) {
				if ( '' === $slug ) {
					$this->problems[] = $where . 'children: the front page cannot have child pages';
					continue;
				}
				$this->read_pages( $children, $path, "$here.children", $types );
			}
		}
	}

	/**
	 * Checks a page's content path.
	 *
	 * @param mixed  $content Value as written.
	 * @param string $where   Message prefix.
	 * @return string|null The path, or null with a problem.
	 */
	private function content_path( mixed $content, string $where ): ?string {
		if ( ! is_string( $content ) || ! preg_match( '#^content/([a-z0-9._-]+/)*[a-z0-9._-]+\.md$#i', $content ) || str_contains( $content, '..' ) ) {
			$this->problems[] = $where . 'content: a Markdown file under content/ is expected, like content/home.md';
			return null;
		}
		if ( ! is_file( $this->dir . '/' . $content ) ) {
			$this->problems[] = $where . "content: $content: file not found";
			return null;
		}
		return $content;
	}

	/**
	 * Reads the menu list of sitemap.yaml.
	 *
	 * @param mixed  $items Items as written.
	 * @param string $at    Where the list is.
	 * @return list<array<string, mixed>>
	 */
	private function read_menu( mixed $items, string $at ): array {
		if ( ! is_array( $items ) || ! array_is_list( $items ) ) {
			$this->problems[] = "sitemap.yaml: $at: a list of menu items is expected";
			return array();
		}
		$titles = array_column( $this->pages, 'title', 'path' );
		$menu   = array();
		foreach ( $items as $i => $item ) {
			$here  = sprintf( '%s[%d]', $at, $i + 1 );
			$where = "sitemap.yaml: $here: ";
			if ( is_string( $item ) ) {
				$item = array( 'page' => $item );
			}
			if ( ! self::is_map( $item ) ) {
				$this->problems[] = $where . 'a page path or a map like { page: ..., label: ... } or { label: ..., url: ... } is expected';
				continue;
			}
			$this->keys( $item, array( 'page', 'label', 'url', 'children' ), $where );
			$page  = $item['page'] ?? null;
			$url   = $item['url'] ?? null;
			$label = $item['label'] ?? null;
			if ( null !== $label && ( ! is_string( $label ) || '' === trim( $label ) ) ) {
				$this->problems[] = $where . 'label: text is expected';
				continue;
			}
			if ( ( null === $page ) === ( null === $url ) ) {
				$this->problems[] = $where . 'either page or url is expected';
				continue;
			}
			if ( null !== $page ) {
				if ( ! is_string( $page ) || ! isset( $titles[ $page ] ) ) {
					$this->problems[] = $where . 'page: "' . ( is_scalar( $page ) ? $page : '' ) . '" is not a page path in pages (like hakkimizda/ekip)';
					continue;
				}
				$label = $label ?? $titles[ $page ];
			} else {
				try {
					$url = Markdown::url( is_string( $url ) ? $url : '' );
				} catch ( InvalidArgumentException $e ) {
					$this->problems[] = $where . 'url: ' . $e->getMessage();
					continue;
				}
				if ( '' === $url || null === $label ) {
					$this->problems[] = $where . 'a url item needs a label and an address';
					continue;
				}
			}
			$menu[] = array(
				'label'    => $label,
				'page'     => $page,
				'url'      => $url,
				'children' => array_key_exists( 'children', $item ) ? $this->read_menu( $item['children'], "$here.children" ) : array(),
			);
		}
		return $menu;
	}

	/**
	 * The menu when the sitemap lists none: the page tree without the front,
	 * cart, checkout and account pages and those with menu: false.
	 *
	 * @param string|null $under  Parent page path; null at the top.
	 * @return list<array<string, mixed>>
	 */
	private function default_menu( ?string $under ): array {
		$menu = array();
		foreach ( $this->pages as $page ) {
			if ( $page['parent'] !== $under || '' === $page['slug'] || ! $page['menu'] || in_array( $page['type'], self::NOT_IN_MENU, true ) ) {
				continue;
			}
			$menu[] = array(
				'label'    => $page['title'],
				'page'     => $page['path'],
				'url'      => null,
				'children' => $this->default_menu( $page['path'] ),
			);
		}
		return $menu;
	}

	/**
	 * Reads a content file: YAML frontmatter between --- lines, then Markdown.
	 *
	 * @param string $file Package path.
	 */
	private function read_content( string $file ): void {
		$text  = str_replace( array( "\r\n", "\r" ), "\n", self::read_file( $this->dir . '/' . $file ) );
		$text  = str_starts_with( $text, "\u{FEFF}" ) ? substr( $text, 3 ) : $text;
		$lines = explode( "\n", $text );
		$front = array();
		$start = 0;
		if ( '---' === rtrim( $lines[0] ) ) {
			$end   = 1;
			$total = count( $lines );
			while ( $end < $total && '---' !== rtrim( $lines[ $end ] ) ) {
				++$end;
			}
			if ( $end >= $total ) {
				$this->problems[] = "$file: line 1: the frontmatter has no closing --- line";
				return;
			}
			try {
				$front = Yaml::parse( implode( "\n", array_slice( $lines, 1, $end - 1 ) ) ) ?? array();
			} catch ( InvalidArgumentException $e ) {
				$this->problems[] = "$file: " . self::shift( $e->getMessage(), 1 );
				return;
			}
			if ( ! self::is_map( $front ) ) {
				$this->problems[] = "$file: the frontmatter is a map like { seo: ..., sections: ... }";
				return;
			}
			$start = $end + 1;
		}

		$where = "$file: ";
		if ( array_key_exists( 'title', $front ) ) {
			$this->problems[] = $where . '"title" is set in sitemap.yaml, not in the content file';
			unset( $front['title'] );
		}
		$this->keys( $front, array( 'seo', 'sections' ), $where );

		$seo = $front['seo'] ?? array();
		if ( ! self::is_map( $seo ) ) {
			$this->problems[] = $where . 'seo: a map like { title: ..., description: ... } is expected';
			$seo              = array();
		}
		$this->keys( $seo, array( 'title', 'description', 'noindex' ), $where . 'seo: ' );
		foreach ( array_intersect_key( $seo, array_flip( array( 'title', 'description', 'noindex' ) ) ) as $key => $value ) {
			if ( 'noindex' === $key ? ! is_bool( $value ) : ! is_string( $value ) ) {
				$this->problems[] = $where . "seo.$key: " . ( 'noindex' === $key ? 'true or false' : 'text' ) . ' is expected';
				unset( $seo[ $key ] );
			}
		}

		$sections = $front['sections'] ?? array();
		$read     = array();
		$markdown = 0;
		if ( ! is_array( $sections ) || ! array_is_list( $sections ) ) {
			$this->problems[] = $where . 'sections: a list of sections is expected';
			$sections         = array();
		}
		foreach ( $sections as $i => $section ) {
			$here = sprintf( '%ssections[%d]: ', $where, $i + 1 );
			if ( ! self::is_map( $section ) ) {
				$this->problems[] = $here . 'a section is a map like { pattern: hero-split, heading: ... }';
				continue;
			}
			if ( array_key_exists( 'markdown', $section ) ) {
				if ( array( 'markdown' => true ) !== $section ) {
					$this->problems[] = $here . 'a { markdown: true } section, which places the Markdown body, has nothing else';
					continue;
				}
				if ( ++$markdown > 1 ) {
					$this->problems[] = $here . 'the Markdown body can be placed once';
					continue;
				}
			} elseif ( ! isset( $section['pattern'] ) || ! is_string( $section['pattern'] ) || '' === $section['pattern'] ) {
				$this->problems[] = $here . 'pattern: required, like pattern: hero-split';
				continue;
			}
			$read[] = $section;
		}

		$this->content[ $file ] = array(
			'seo'      => $seo,
			'sections' => $read,
			'body'     => implode( "\n", array_slice( $lines, $start ) ),
			'offset'   => $start,
		);
	}

	/**
	 * Block markup of a content file's Markdown body.
	 *
	 * @param string  $file  Content file.
	 * @param Closure $media Image resolver, see page_content().
	 * @phpstan-param Closure(string): array{id: int, url: string} $media
	 */
	private function body( string $file, Closure $media ): string {
		$content = $this->content[ $file ];
		try {
			return Markdown::convert(
				$content['body'],
				function ( string $src ) use ( $media ): array {
					return $media( $this->image( $src ) );
				}
			);
		} catch ( InvalidArgumentException $e ) {
			$this->problems[] = "$file: " . self::shift( $e->getMessage(), $content['offset'] );
			return '';
		}
	}

	/**
	 * Reads brand/tokens.json; it is compiled in check().
	 */
	private function read_tokens(): void {
		$file = $this->dir . '/brand/tokens.json';
		if ( ! is_file( $file ) ) {
			return;
		}
		try {
			$this->tokens = Compiler::read_json( $file );
		} catch ( Invalid $e ) {
			foreach ( $e->problems as $problem ) {
				$this->problems[] = str_replace( $this->dir . '/', '', $problem );
			}
		}
	}

	/**
	 * Reads a YAML file that holds a map.
	 *
	 * @param string $file     Package path.
	 * @param bool   $required Whether a missing file is a problem.
	 * @return array<string, mixed>|null Null when missing or unreadable (with a problem).
	 */
	private function yaml( string $file, bool $required ): ?array {
		$path = $this->dir . '/' . $file;
		if ( ! is_file( $path ) ) {
			if ( $required ) {
				$this->problems[] = "$file: file not found";
			}
			return null;
		}
		try {
			$data = Yaml::parse( self::read_file( $path ) ) ?? array();
		} catch ( InvalidArgumentException $e ) {
			$this->problems[] = "$file: " . $e->getMessage();
			return null;
		}
		if ( ! self::is_map( $data ) ) {
			$this->problems[] = "$file: a map of keys is expected at the top";
			return null;
		}
		return $data;
	}

	/**
	 * Adds a problem for each key that is not allowed.
	 *
	 * @param array<int|string, mixed> $map     Map as written.
	 * @param array<int, string>       $allowed Keys allowed.
	 * @param string                   $where   Message prefix.
	 */
	private function keys( array $map, array $allowed, string $where ): void {
		foreach ( array_keys( $map ) as $key ) {
			if ( ! in_array( (string) $key, $allowed, true ) ) {
				$this->problems[] = sprintf( '%s"%s" is not known here; known: %s', $where, $key, implode( ', ', $allowed ) );
			}
		}
	}

	/**
	 * Package files under a directory with an extension, as package paths.
	 *
	 * @param string $dir       Package directory, like content.
	 * @param string $extension Extension with its dot.
	 * @return array<int, string>
	 */
	private function files( string $dir, string $extension ): array {
		if ( ! is_dir( $this->dir . '/' . $dir ) ) {
			return array();
		}
		$files    = array();
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->dir . '/' . $dir, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			$path = str_replace( '\\', '/', (string) $file );
			if ( str_ends_with( $path, $extension ) ) {
				$files[] = substr( $path, strlen( $this->dir ) + 1 );
			}
		}
		sort( $files );
		return $files;
	}

	/**
	 * Whether a value is a map (an empty array counts).
	 *
	 * @param mixed $value Value.
	 * @phpstan-assert-if-true array<string, mixed> $value
	 */
	private static function is_map( mixed $value ): bool {
		return is_array( $value ) && ( array() === $value || ! array_is_list( $value ) );
	}

	/**
	 * Moves the "line N:" of a message by the lines before the text it is about.
	 *
	 * @param string $message Message.
	 * @param int    $by      Lines before.
	 */
	private static function shift( string $message, int $by ): string {
		return (string) preg_replace_callback( '/^line (\d+):/', static fn( array $m ): string => 'line ' . ( (int) $m[1] + $by ) . ':', $message );
	}

	/**
	 * Reads a package file.
	 *
	 * @param string $path File path.
	 */
	private static function read_file( string $path ): string {
		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local package file, read under WP-CLI.
	}
}
