<?php
/**
 * Writes a site back out as a project package.
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
use WP_Post;

/**
 * The reverse of Importer: site options, Pergel settings, pages, the menu,
 * products, images and brand tokens as package.yaml, sitemap.yaml,
 * content/*.md, products/*.md, media/, media.yaml and brand/tokens.json.
 *
 * A section a pattern made (its root block names the pattern, as the block
 * inserter and the importer mark it) is read back into the pattern's
 * fields; other blocks become the page's Markdown body. Each part is
 * filled or converted back and compared with the page, so what an import
 * would not give back the same (a block Markdown cannot hold, a section
 * edited beyond its fields, an image outside the media library) is a note
 * instead of a silent change.
 *
 * By default the pages a package made are exported, so a site built from
 * a package gives that package back; $all exports every published page.
 * Products are exported when the package filled them in, each with the
 * fields it wrote: a package of products without pages gets no sitemap.
 *
 * @phpstan-import-type Row from Importer
 * @phpstan-type Item array{label: string, page: string|null, url: string|null, children: list<array<string, mixed>>}
 */
final class Exporter {

	/**
	 * Page types by the option that names their page.
	 */
	private const TYPE_OPTIONS = array(
		'page_for_posts'                => 'posts',
		'woocommerce_shop_page_id'      => 'shop',
		'woocommerce_cart_page_id'      => 'cart',
		'woocommerce_checkout_page_id'  => 'checkout',
		'woocommerce_myaccount_page_id' => 'account',
	);

	/**
	 * Page types left out of the default menu (as Package reads it).
	 */
	private const NOT_IN_MENU = array( 'cart', 'checkout', 'account' );

	/**
	 * Slugs a package accepts.
	 */
	private const SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

	/**
	 * What was written, one row per file.
	 *
	 * @var list<Row>
	 */
	public array $rows = array();

	/**
	 * What does not come back the same on import.
	 *
	 * @var list<string>
	 */
	public array $notes = array();

	/**
	 * Text files to write: package path => contents.
	 *
	 * @var array<string, string>
	 */
	private array $files = array();

	/**
	 * Images to copy: package path => file on the site.
	 *
	 * @var array<string, string>
	 */
	private array $copies = array();

	/**
	 * What a row says about a file: package path => title.
	 *
	 * @var array<string, string>
	 */
	private array $labels = array();

	/**
	 * Images in the package: package path => { id, url } on this site.
	 *
	 * @var array<string, array{id: int, url: string}>
	 */
	private array $images = array();

	/**
	 * Package paths by attachment ID.
	 *
	 * @var array<int, string>
	 */
	private array $paths = array();

	/**
	 * The media.yaml entries: package path => details.
	 *
	 * @var array<string, array<string, string>>
	 */
	private array $media = array();

	/**
	 * Exported pages in sitemap order: { path, parent, type, title } by page ID.
	 *
	 * @var array<int, array{path: string, parent: string|null, type: string, title: string}>
	 */
	private array $tree = array();

	/**
	 * Set up an export.
	 *
	 * @param string $dir     Package folder to write.
	 * @param string $name    Package name.
	 * @param bool   $all     Export every published page, not only the package's.
	 * @param bool   $dry_run Only report what would be written.
	 */
	public function __construct(
		private readonly string $dir,
		private readonly string $name,
		private readonly bool $all = false,
		private readonly bool $dry_run = false
	) {
	}

	/**
	 * Names of the packages that made pages or filled in products on this site.
	 *
	 * @return list<string>
	 */
	public static function names(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- CLI only, one query.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND ( ( p.post_type = 'page' AND p.post_status = 'publish' ) OR ( p.post_type = 'product' AND p.post_status <> 'trash' ) ) ORDER BY m.meta_value", Importer::META ) );
		return array_values( array_map( 'strval', (array) $names ) );
	}

	/**
	 * What stops the export: a folder that is not empty and holds no package,
	 * or holds another package.
	 *
	 * @return list<string>
	 */
	public function preflight(): array {
		if ( file_exists( $this->dir ) && ! is_dir( $this->dir ) ) {
			return array( "$this->dir: a file, not a folder" );
		}
		if ( ! is_dir( $this->dir ) || array() === array_diff( (array) scandir( $this->dir ), array( '.', '..' ) ) ) {
			return array();
		}
		$file = $this->dir . '/package.yaml';
		if ( ! is_file( $file ) ) {
			return array( "$this->dir: the folder is not empty and holds no package.yaml; export into an empty folder or a package folder" );
		}
		try {
			$data = Yaml::parse( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local package file, read under WP-CLI.
		} catch ( InvalidArgumentException $e ) {
			return array( "$file: " . $e->getMessage() );
		}
		$name = is_array( $data ) ? ( $data['name'] ?? null ) : null;
		if ( is_string( $name ) && $name !== $this->name ) {
			return array( "$this->dir holds the package $name; export into another folder or give --name=$name" );
		}
		return array();
	}

	/**
	 * Runs the export.
	 *
	 * @throws InvalidArgumentException When there are no pages or products to export or a file cannot be written.
	 */
	public function run(): void {
		$pages    = $this->find_pages();
		$products = $this->find_products();
		if ( array() === $pages && array() === $products ) {
			throw new InvalidArgumentException( esc_html( $this->all ? 'There is no published page to export.' : "No published page or product was made by the package $this->name; --all exports every page." ) );
		}
		$sitemap = null;
		if ( array() !== $pages ) {
			$sitemap = array( 'pages' => $this->sitemap( $pages, $this->children( $pages ), 0 ) );
			$menu    = $this->menu();
			if ( null !== $menu ) {
				$sitemap['menu'] = $menu;
			}
		}
		foreach ( $products as $product ) {
			$this->product( $product );
		}
		$this->put( 'package.yaml', $this->site( array() !== $products ), '' );
		if ( null !== $sitemap ) {
			$this->put( 'sitemap.yaml', Yaml::dump( $sitemap ), '' );
		}
		$this->tokens();
		if ( array() !== $this->media ) {
			ksort( $this->media );
			$this->put( 'media.yaml', Yaml::dump( $this->media ), '' );
		}
		$this->write();
		$this->notes = array_values( array_unique( $this->notes ) );
	}

	/**
	 * Published pages to export, by ID.
	 *
	 * @return array<int, WP_Post>
	 */
	private function find_pages(): array {
		$query = array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'ID'         => 'ASC',
			),
		);
		if ( ! $this->all ) {
			$query['meta_key']   = Importer::META; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI only.
			$query['meta_value'] = $this->name; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- CLI only.
		}
		$pages = array();
		foreach ( get_posts( $query ) as $page ) {
			$pages[ $page->ID ] = $page;
		}
		return $pages;
	}

	/**
	 * Products the package filled in, by ID.
	 *
	 * @return array<int, WP_Post>
	 */
	private function find_products(): array {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}
		$products = array();
		$query    = array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => Importer::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI only.
			'meta_value'     => $this->name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- CLI only.
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);
		foreach ( get_posts( $query ) as $product ) {
			$products[ $product->ID ] = $product;
		}
		return $products;
	}

	/**
	 * Writes a product's file, products/<SKU>.md, with the fields the
	 * package wrote when it filled the product in.
	 *
	 * @param WP_Post $product Product.
	 */
	private function product( WP_Post $product ): void {
		$sku = (string) get_post_meta( $product->ID, '_sku', true );
		if ( ! preg_match( Package::SKU, $sku ) || isset( $this->files[ "products/$sku.md" ] ) ) {
			$this->notes[] = sprintf( 'product %d (%s): its SKU "%s" cannot name a product file of its own; it is left out', $product->ID, $product->post_title, $sku );
			return;
		}
		$file   = "products/$sku.md";
		$saved  = (string) get_post_meta( $product->ID, Importer::SAVED, true );
		$fields = str_contains( $saved, ':' ) ? explode( ',', (string) strstr( $saved, ':', true ) ) : array( 'post_title', 'post_content', 'post_excerpt', 'page_template', Meta::TITLE, Meta::DESCRIPTION, Meta::NOINDEX );
		$writes = static fn( string $field ): bool => in_array( $field, $fields, true );

		$front = array();
		if ( $writes( 'post_title' ) ) {
			$front['title'] = $product->post_title;
		}
		if ( $writes( 'post_excerpt' ) ) {
			$front['excerpt'] = MarkdownWriter::inline( $product->post_excerpt );
			if ( Markdown::inline( $front['excerpt'] ) !== $product->post_excerpt ) {
				$this->notes[] = "$file: excerpt: the short description does not come back the same from Markdown; check it after an import";
			}
		}
		if ( $writes( 'page_template' ) ) {
			$front['template'] = (string) get_page_template_slug( $product );
		}
		foreach ( array( 'regular', 'sale' ) as $which ) {
			if ( $writes( "_{$which}_price" ) ) {
				$price                    = (string) get_post_meta( $product->ID, "_{$which}_price", true );
				$front['price'][ $which ] = '' === $price ? null : $price;
			}
		}
		$seo = array();
		foreach ( array(
			'title'       => Meta::TITLE,
			'description' => Meta::DESCRIPTION,
			'noindex'     => Meta::NOINDEX,
		) as $key => $meta_key ) {
			if ( $writes( $meta_key ) ) {
				$seo[ $key ] = 'noindex' === $key ? Meta::noindex( $product->ID ) : (string) get_post_meta( $product->ID, $meta_key, true );
			}
		}
		if ( array() !== $seo ) {
			$front['seo'] = $seo;
		}
		$blocks = $writes( 'post_content' ) ? parse_blocks( $product->post_content ) : array();
		$this->put( $file, $this->document( $file, $blocks, $front ), $product->post_title );
	}

	/**
	 * Child page IDs by parent ID (0 = top), in menu order; a page whose
	 * parent is not exported, and a child of the front page, go to the top.
	 *
	 * @param array<int, WP_Post> $pages Pages by ID.
	 * @return array<int, list<int>>
	 */
	private function children( array $pages ): array {
		$front    = $this->front();
		$children = array();
		foreach ( $pages as $id => $page ) {
			$parent = $page->post_parent;
			if ( $parent > 0 && ( ! isset( $pages[ $parent ] ) || $parent === $front || $id === $front ) ) {
				$this->notes[] = sprintf( 'page %d (%s) is under %s, which a package cannot hold; it moves to the top', $id, $page->post_title, isset( $pages[ $parent ] ) ? 'the front page' : "page $parent, which is not exported" );
				$parent        = 0;
			}
			$children[ $parent ][] = $id;
		}
		return $children;
	}

	/**
	 * Sitemap entries for the pages under one parent, writing their content files.
	 *
	 * @param array<int, WP_Post>   $pages    Pages by ID.
	 * @param array<int, list<int>> $children Child IDs by parent ID.
	 * @param int                   $above    Parent ID; 0 at the top.
	 * @return list<array<string, mixed>>
	 */
	private function sitemap( array $pages, array $children, int $above ): array {
		$entries = array();
		$slugs   = array();
		$under   = 0 === $above ? null : $this->tree[ $above ]['path'];
		foreach ( $children[ $above ] ?? array() as $id ) {
			$page = $pages[ $id ];
			$slug = $id === $this->front() ? '' : $this->slug( $page );
			if ( isset( $slugs[ $slug ] ) ) {
				$slug .= '-' . $id;
			}
			$slugs[ $slug ] = true;
			$path           = null === $under ? $slug : "$under/$slug";
			$type           = $this->type( $id );
			$title          = $page->post_title;
			if ( '' === trim( $title ) ) {
				$title         = '' === $slug ? 'Home' : $slug;
				$this->notes[] = sprintf( 'page %d has no title; the package calls it "%s"', $id, $title );
			}
			$this->tree[ $id ] = array(
				'path'   => $path,
				'parent' => $under,
				'type'   => $type,
				'title'  => $title,
			);

			$entry = array(
				'slug'  => $slug,
				'title' => $title,
			);
			$file  = $this->content( $page, $path, $type );
			if ( null !== $file ) {
				$entry['content'] = $file;
			}
			$template = (string) get_page_template_slug( $page );
			if ( '' !== $template ) {
				$entry['template'] = $template;
			}
			if ( 'page' !== $type ) {
				$entry['type'] = $type;
			}
			$below = $this->sitemap( $pages, $children, $id );
			if ( array() !== $below ) {
				$entry['children'] = $below;
			}
			$entries[] = $entry;
		}
		return $entries;
	}

	/**
	 * The front page's ID, 0 when the front page shows the latest posts.
	 */
	private function front(): int {
		return 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
	}

	/**
	 * Page type from the reading and WooCommerce options.
	 *
	 * @param int $id Page ID.
	 */
	private function type( int $id ): string {
		foreach ( self::TYPE_OPTIONS as $option => $type ) {
			if ( (int) get_option( $option ) === $id ) {
				return $type;
			}
		}
		return 'page';
	}

	/**
	 * A page's slug as a package writes it; one WordPress made with other
	 * characters is turned into one, with a note.
	 *
	 * @param WP_Post $page Page.
	 */
	private function slug( WP_Post $page ): string {
		$name = urldecode( $page->post_name );
		if ( preg_match( self::SLUG, $name ) ) {
			return $name;
		}
		$slug          = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $name ) ) ), '-' );
		$slug          = '' === $slug ? 'page-' . $page->ID : $slug;
		$this->notes[] = sprintf( 'page %d (%s): the address %s becomes %s in the package', $page->ID, $page->post_title, $name, $slug );
		return $slug;
	}

	/**
	 * Writes a page's content file: SEO fields, sections read back into
	 * pattern fields, and the other blocks as Markdown.
	 *
	 * @param WP_Post $page Page.
	 * @param string  $path Page path.
	 * @param string  $type Page type.
	 * @return string|null The file, null when the page needs none.
	 */
	private function content( WP_Post $page, string $path, string $type ): ?string {
		$seo = array_filter(
			array(
				'title'       => (string) get_post_meta( $page->ID, Meta::TITLE, true ),
				'description' => (string) get_post_meta( $page->ID, Meta::DESCRIPTION, true ),
				'noindex'     => Meta::noindex( $page->ID ),
			)
		);
		// The posts page shows posts, and WooCommerce's pages keep their own
		// blocks: an import adopts the page WooCommerce makes.
		$blocks = 'page' === $type ? parse_blocks( $page->post_content ) : array();
		if ( array() === $seo && '' === trim( serialize_blocks( $blocks ) ) ) {
			return null;
		}

		// content/home.md is the front page's, even when a page comes first.
		$base  = '' === $path ? 'home' : str_replace( '/', '-', $path );
		$file  = "content/$base.md";
		$taken = fn( string $file ): bool => isset( $this->files[ $file ] ) || ( '' !== $path && 'content/home.md' === $file && $this->front() > 0 );
		$n     = 1;
		while ( $taken( $file ) ) {
			++$n;
			$file = "content/$base-$n.md";
		}

		$front = array();
		if ( array() !== $seo ) {
			$front['seo'] = $seo;
		}
		$this->put( $file, $this->document( $file, $blocks, $front ), $page->post_title );
		return $file;
	}

	/**
	 * A content file's text: the frontmatter given, the sections read back
	 * into pattern fields, and the other blocks as Markdown.
	 *
	 * @param string                                                                                                                                                                                 $file   Package path, for notes.
	 * @param array<int|string, array{blockName: string|null, attrs: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>, innerHTML: string, innerContent: array<int, string|null>}> $blocks Parsed blocks.
	 * @param array<string, mixed>                                                                                                                                                                   $front  Frontmatter before the sections.
	 */
	private function document( string $file, array $blocks, array $front ): string {
		$sections = array();
		$body     = array();
		$runs     = 0;
		$last     = null;
		$written  = array();
		foreach ( array_values( $blocks ) as $i => $block ) {
			if ( null === $block['blockName'] ) {
				if ( '' !== trim( (string) $block['innerHTML'] ) ) {
					$this->notes[] = sprintf( '%s: HTML outside blocks (classic content) is left out', $file );
				}
				continue;
			}
			$name    = (string) ( $block['attrs']['metadata']['patternName'] ?? '' );
			$pattern = '' === $name ? null : Package::pattern( $name );
			if ( null !== $pattern ) {
				$sections[] = $this->section( $pattern, $block, $name, sprintf( '%s: sections[%d] (%s)', $file, count( $sections ) + 1, $name ) );
				$last       = 'section';
				continue;
			}
			$markdown = MarkdownWriter::block( $block, $this->path_reader() );
			$where    = sprintf( '%s: block %d (%s)', $file, $i + 1, $block['blockName'] );
			if ( null === $markdown ) {
				$this->notes[] = "$where cannot be written as Markdown and is left out";
				continue;
			}
			if ( 'body' !== $last ) {
				if ( ++$runs > 1 ) {
					$this->notes[] = "$where moves up to the Markdown body, which a page has once";
				} else {
					$sections[] = array( 'markdown' => true );
				}
			}
			$last = 'body';
			if ( ! self::same( serialize_block( $block ), $this->convert( $markdown ) ) ) {
				$this->notes[] = "$where does not come back the same from Markdown; check it after an import";
			}
			$body[]    = $markdown;
			$written[] = $block;
		}
		if ( 'body' === $last && 1 === $runs ) {
			// The body goes after the sections anyway.
			array_pop( $sections );
		}
		if ( array() !== $body && ! self::same( serialize_blocks( $written ), $this->convert( implode( "\n\n", $body ) ) ) ) {
			$this->notes[] = "$file: blocks next to each other run together in Markdown (two lists, say); check the page after an import";
		}

		if ( array() !== $sections ) {
			$front['sections'] = $sections;
		}
		$text = array() === $front ? '' : "---\n" . Yaml::dump( $front ) . "---\n";
		if ( array() !== $body ) {
			$text .= ( '' === $text ? '' : "\n" ) . implode( "\n\n", $body ) . "\n";
		}
		return $text;
	}

	/**
	 * A section's pattern and field values, checked by filling them back.
	 *
	 * @param list<array<string, mixed>> $pattern The pattern's blocks.
	 * @param array<string, mixed>       $block   The section's root block.
	 * @param string                     $name    Pattern name.
	 * @param string                     $where   Where the section goes, for notes.
	 * @return array<string, mixed>
	 */
	private function section( array $pattern, array $block, string $name, string $where ): array {
		$notes  = array();
		$values = Fields::values( $pattern, array( $block ), $this->field_reader(), $notes );
		$themes = array_unique( array( get_template_directory_uri() . '/', get_stylesheet_directory_uri() . '/' ) );
		foreach ( $notes as $note ) {
			// The pattern's own placeholder image (in the parent or the child
			// theme) stays as it is; checking the package says so for the field anyway.
			if ( array() === array_filter( $themes, static fn( string $theme ): bool => str_contains( $note, ": $theme" ) ) ) {
				$this->notes[] = "$where: $note";
			}
		}
		$align = Package::width( array( $block ) );
		$width = Package::width( $pattern ) === $align ? array() : array( 'align' => $align );
		$kept  = array();
		try {
			$back = serialize_blocks( Fields::fill( Package::widened( $pattern, $align ), $values, $this->field_media(), $kept ) );
		} catch ( InvalidArgumentException $e ) {
			$back = '';
		}
		if ( array() === $notes && ! self::same( serialize_block( $block ), $back ) ) {
			$this->notes[] = "$where was changed beyond its fields (a block added, a style or a setting); an import gives back the pattern with these fields";
		}
		return array( 'pattern' => Package::section_name( $name ) ) + $width + $values;
	}

	/**
	 * Package image path for an image on the site, copying it into the
	 * package on first use; null when it is not in the media library.
	 *
	 * @param string $url  URL as the block has it.
	 * @param int    $id   Attachment ID the block names, 0 when none.
	 * @param bool   $file Whether a file field's file (a pdf too) is read.
	 * @param bool   $svg  Whether an svg file is read (a logo).
	 */
	private function image( string $url, int $id, bool $file = false, bool $svg = false ): ?string {
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
			$id = attachment_url_to_postid( $url );
			if ( 0 === $id ) {
				// A resized or scaled copy names its original.
				$id = attachment_url_to_postid( (string) preg_replace( '/-(\d+x\d+|scaled)(?=\.[a-z0-9]+$)/i', '', $url ) );
			}
		}
		if ( $id <= 0 ) {
			return null;
		}
		if ( isset( $this->paths[ $id ] ) ) {
			return $this->paths[ $id ];
		}
		$types = array_merge( Package::IMAGE_TYPES, $file ? Package::FILE_TYPES : array(), $svg ? Package::SVG_TYPES : array() );
		$file  = wp_get_original_image_path( $id );
		$file  = is_string( $file ) && is_file( $file ) ? $file : (string) get_attached_file( $id );
		if ( ! is_file( $file ) ) {
			return null;
		}
		$key  = (string) get_post_meta( $id, Importer::KEY, true );
		$path = get_post_meta( $id, Importer::META, true ) === $this->name && str_starts_with( $key, 'media/' ) ? $key : '';
		if ( '' === $path ) {
			$base = strtolower( sanitize_file_name( remove_accents( basename( $file ) ) ) );
			$path = 'media/' . $base;
			for ( $n = 2; isset( $this->images[ $path ] ); $n++ ) {
				$path = 'media/' . pathinfo( $base, PATHINFO_FILENAME ) . "-$n." . pathinfo( $base, PATHINFO_EXTENSION );
			}
		}
		if ( ! in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), $types, true ) ) {
			return null;
		}

		$this->paths[ $id ]    = $path;
		$this->images[ $path ] = array(
			'id'  => $id,
			'url' => (string) wp_get_attachment_url( $id ),
		);
		$this->copies[ $path ] = $file;
		$this->labels[ $path ] = get_the_title( $id );
		$details               = array( 'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		$details['caption']    = (string) get_post_field( 'post_excerpt', $id );
		foreach ( Importer::CREDITS as $field => $meta_key ) {
			$details[ $field ] = (string) get_post_meta( $id, $meta_key, true );
		}
		$this->media[ $path ] = array( 'alt' => $details['alt'] ) + array_filter( $details, static fn( string $value ): bool => '' !== $value );
		return $path;
	}

	/**
	 * Image reader for MarkdownWriter: the package path.
	 *
	 * @return Closure(string, int, string): ?string
	 */
	private function path_reader(): Closure {
		return fn( string $url, int $id ): ?string => $this->image( $url, $id );
	}

	/**
	 * Image reader for Fields::values(): the path, or { src, alt } when the
	 * section's alt text is not the image's own.
	 *
	 * @return Closure(string, int, string, string=): (string|array<string, string>|null)
	 */
	private function field_reader(): Closure {
		return function ( string $url, int $id, string $alt, string $kind = 'image' ): string|array|null {
			$path = $this->image( $url, $id, 'file' === $kind );
			if ( null === $path ) {
				return null;
			}
			return $alt === $this->media[ $path ]['alt'] ? $path : array(
				'src' => $path,
				'alt' => $alt,
			);
		};
	}

	/**
	 * Image resolver for Fields::fill(), as the importer's gives on this site.
	 *
	 * @return Closure(string, ?string): array{id: int, url: string, alt: string}
	 */
	private function field_media(): Closure {
		return function ( string $src, ?string $alt ): array {
			if ( ! isset( $this->images[ $src ] ) ) {
				throw new InvalidArgumentException( esc_html( "$src is not in the package" ) );
			}
			return $this->images[ $src ] + array( 'alt' => $alt ?? $this->media[ $src ]['alt'] );
		};
	}

	/**
	 * Block markup of Markdown, as the importer converts it on this site.
	 *
	 * @param string $markdown Markdown.
	 */
	private function convert( string $markdown ): string {
		try {
			return Markdown::convert(
				$markdown,
				function ( string $src ): array {
					if ( ! isset( $this->images[ $src ] ) ) {
						throw new InvalidArgumentException( esc_html( "$src is not in the package" ) );
					}
					return $this->images[ $src ];
				}
			);
		} catch ( InvalidArgumentException $e ) {
			return '';
		}
	}

	/**
	 * The package's navigation menu, or else the one the header shows (the
	 * newest), as sitemap items; null when it is the menu the pages give
	 * anyway, or there is none.
	 *
	 * @return list<mixed>|null
	 */
	private function menu(): ?array {
		$query = array(
			'post_type'      => 'wp_navigation',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$navs  = get_posts(
			$query + array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI only.
					array(
						'key'   => Importer::META,
						'value' => $this->name,
					),
					array(
						'key'   => Importer::KEY,
						'value' => 'menu',
					),
				),
			)
		);
		$navs  = array() === $navs ? get_posts( $query ) : $navs;
		if ( array() === $navs ) {
			return null;
		}
		$items = $this->menu_items( parse_blocks( $navs[0]->post_content ), 'menu' );
		if ( $items === $this->default_menu( null ) ) {
			return null;
		}
		return $this->menu_yaml( $items );
	}

	/**
	 * Menu items from navigation blocks.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks Navigation blocks.
	 * @param string                                  $at     Where they are, for notes.
	 * @return list<Item>
	 */
	private function menu_items( array $blocks, string $at ): array {
		$paths = array();
		foreach ( $this->tree as $id => $page ) {
			$paths[ $id ] = $page['path'];
		}
		$items = array();
		foreach ( $blocks as $block ) {
			$name = $block['blockName'];
			if ( null === $name ) {
				continue;
			}
			$where = sprintf( 'sitemap.yaml: %s[%d]', $at, count( $items ) + 1 );
			if ( ! in_array( $name, array( 'core/navigation-link', 'core/navigation-submenu' ), true ) ) {
				$this->notes[] = "$where: the menu's $name block cannot be written in sitemap.yaml and is left out";
				continue;
			}
			$attrs = (array) $block['attrs'];
			$label = trim( html_entity_decode( wp_strip_all_tags( (string) ( $attrs['label'] ?? '' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$id    = (int) ( $attrs['id'] ?? 0 );
			if ( 'post-type' === ( $attrs['kind'] ?? '' ) && isset( $paths[ $id ] ) ) {
				$item = array(
					'label' => '' === $label ? $this->tree[ $id ]['title'] : $label,
					'page'  => $paths[ $id ],
					'url'   => null,
				);
			} else {
				$url  = $id > 0 && 'post-type' === ( $attrs['kind'] ?? '' ) ? (string) get_permalink( $id ) : (string) ( $attrs['url'] ?? '' );
				$home = untrailingslashit( home_url() );
				if ( str_starts_with( $url, $home . '/' ) || $url === $home ) {
					$url = '/' . ltrim( substr( $url, strlen( $home ) ), '/' );
				}
				try {
					$url = Markdown::url( $url );
				} catch ( InvalidArgumentException $e ) {
					$url = '';
				}
				if ( '' === $url || '' === $label ) {
					$this->notes[] = "$where: a menu link without an address or a label is left out";
					continue;
				}
				$item = array(
					'label' => $label,
					'page'  => null,
					'url'   => $url,
				);
			}
			$item['children'] = $this->menu_items( (array) $block['innerBlocks'], $at . '[' . ( count( $items ) + 1 ) . '].children' );
			$items[]          = $item;
		}
		return $items;
	}

	/**
	 * The menu Package makes when the sitemap lists none.
	 *
	 * @param string|null $under Parent page path; null at the top.
	 * @return list<Item>
	 */
	private function default_menu( ?string $under ): array {
		$menu = array();
		foreach ( $this->tree as $page ) {
			if ( $page['parent'] !== $under || '' === $page['path'] || in_array( $page['type'], self::NOT_IN_MENU, true ) ) {
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
	 * Menu items as sitemap.yaml writes them: a page path alone when the
	 * label is the page title.
	 *
	 * @param array<int, array<string, mixed>> $items Items, as menu_items() gives them.
	 * @return list<mixed>
	 */
	private function menu_yaml( array $items ): array {
		$titles = array();
		foreach ( $this->tree as $page ) {
			$titles[ $page['path'] ] = $page['title'];
		}
		$out = array();
		foreach ( $items as $item ) {
			$label    = is_string( $item['label'] ?? null ) ? $item['label'] : '';
			$page     = is_string( $item['page'] ?? null ) ? $item['page'] : null;
			$children = $this->menu_yaml( is_array( $item['children'] ?? null ) ? $item['children'] : array() );
			if ( null !== $page ) {
				$title = $titles[ $page ] ?? '';
				if ( array() === $children && $label === $title ) {
					$out[] = $page;
					continue;
				}
				$entry = array( 'page' => $page );
				if ( $label !== $title ) {
					$entry['label'] = $label;
				}
			} else {
				$entry = array(
					'label' => $label,
					'url'   => $item['url'] ?? '',
				);
			}
			if ( array() !== $children ) {
				$entry['children'] = $children;
			}
			$out[] = $entry;
		}
		return $out;
	}

	/**
	 * The package.yaml text: the site title, tagline, language, logo and the
	 * Pergel settings that are not their defaults. A package stays at the
	 * oldest format that holds it (1 without products, 2 without a logo),
	 * so that older engines still read it.
	 *
	 * @param bool $products Whether the package has products.
	 */
	private function site( bool $products ): string {
		$title = html_entity_decode( (string) get_option( 'blogname' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( '' === trim( $title ) ) {
			$title         = $this->name;
			$this->notes[] = "package.yaml: the site has no title; the package uses its name, $title";
		}
		// Like pages, the logo goes only when the package made it, or with --all.
		$logo_id = (int) get_option( 'site_logo' );
		$logo    = null;
		if ( $logo_id > 0 && ( $this->all || get_post_meta( $logo_id, Importer::META, true ) === $this->name ) ) {
			$logo = $this->image( '', $logo_id, false, true );
			if ( null === $logo ) {
				$this->notes[] = 'package.yaml: logo: the Site Logo is not an image file in the media library and is left out';
			}
		}
		$site = array(
			'format'  => null !== $logo ? Package::LOGO_FORMAT : ( $products ? Package::PRODUCTS_FORMAT : Package::PAGES_FORMAT ),
			'name'    => $this->name,
			'title'   => $title,
			'tagline' => html_entity_decode( (string) get_option( 'blogdescription' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'locale'  => '' === (string) get_option( 'WPLANG' ) ? 'en_US' : (string) get_option( 'WPLANG' ),
		);
		if ( null !== $logo ) {
			$site['logo'] = $logo;
		}
		$settings = $this->settings();
		if ( array() !== $settings ) {
			$site['settings'] = $settings;
		}
		return Yaml::dump( $site );
	}

	/**
	 * Pergel settings that differ from their defaults, images as package paths.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function settings(): array {
		$groups   = Schema::groups();
		$defaults = Schema::defaults();
		$out      = array();
		foreach ( Settings::all() as $group => $values ) {
			foreach ( $values as $key => $value ) {
				if ( ! isset( $groups[ $group ][ $key ] ) || $value === $defaults[ $group ][ $key ] ) {
					continue;
				}
				if ( 'image' === $groups[ $group ][ $key ]['type'] ) {
					$value = $this->image( '', (int) $value, false, $groups[ $group ][ $key ]['svg'] ?? true );
					if ( null === $value ) {
						$this->notes[] = "package.yaml: settings.$group.$key: its image is not in the media library and is left out";
						continue;
					}
				}
				$out[ $group ][ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Writes brand/tokens.json from an active child theme's design tokens.
	 */
	private function tokens(): void {
		$file = get_stylesheet_directory() . '/tokens/default.json';
		if ( get_stylesheet() === get_template() || ! is_file( $file ) ) {
			return;
		}
		$this->put( 'brand/tokens.json', (string) file_get_contents( $file ), get_stylesheet() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file, read under WP-CLI.
		$mark = get_file_data( get_stylesheet_directory() . '/style.css', array( 'package' => Importer::THEME_MARK ) )['package'];
		if ( $mark !== $this->name ) {
			$this->notes[] = sprintf( 'brand/tokens.json: from the child theme %s, which this package did not write; importing on this site needs the Pergel theme active first', get_stylesheet() );
		}
	}

	/**
	 * Adds a text file to write.
	 *
	 * @param string $path  Package path.
	 * @param string $text  Contents.
	 * @param string $label What the row says.
	 */
	private function put( string $path, string $text, string $label ): void {
		$this->files[ $path ]  = $text;
		$this->labels[ $path ] = $label;
	}

	/**
	 * Writes the files that changed and removes the package files the site
	 * no longer has, reporting each.
	 *
	 * @throws InvalidArgumentException When a file cannot be written.
	 */
	private function write(): void {
		$written = array();
		foreach ( $this->files as $path => $text ) {
			$file             = "$this->dir/$path";
			$written[ $path ] = true;
			$same             = is_file( $file ) && (string) file_get_contents( $file ) === $text; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local package file.
			$this->row( $file, $path, $same, fn(): bool => false !== file_put_contents( $file, $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local package file, written under WP-CLI.
		}
		ksort( $this->copies );
		foreach ( $this->copies as $path => $source ) {
			$file             = "$this->dir/$path";
			$written[ $path ] = true;
			$same             = is_file( $file ) && sha1_file( $file ) === sha1_file( $source );
			$this->row( $file, $path, $same, static fn(): bool => copy( $source, $file ) );
		}

		foreach ( $this->package_files() as $path ) {
			if ( isset( $written[ $path ] ) ) {
				continue;
			}
			$this->rows[] = Starter::row( 'remove', 'file', $path, 0, '' );
			if ( ! $this->dry_run ) {
				wp_delete_file( "$this->dir/$path" );
			}
		}
	}

	/**
	 * Reports one file and writes it unless it is the same or this is a dry run.
	 *
	 * @param string  $file  File.
	 * @param string  $path  Package path.
	 * @param bool    $same  Whether the file already holds this.
	 * @param Closure $write Writes it.
	 * @phpstan-param Closure(): bool $write
	 * @throws InvalidArgumentException When it cannot be written.
	 */
	private function row( string $file, string $path, bool $same, Closure $write ): void {
		$this->rows[] = Starter::row( $same ? 'keep' : ( is_file( $file ) ? 'update' : 'create' ), 'file', $path, 0, $this->labels[ $path ] ?? '' );
		if ( $same || $this->dry_run ) {
			return;
		}
		if ( ( ! is_dir( dirname( $file ) ) && ! wp_mkdir_p( dirname( $file ) ) ) || ! $write() ) {
			throw new InvalidArgumentException( esc_html( "$file: cannot be written" ) );
		}
	}

	/**
	 * Files already in the folder that an export writes: the sitemap,
	 * content and product files, images, media.yaml and brand/tokens.json.
	 *
	 * @return list<string>
	 */
	private function package_files(): array {
		$found = array();
		foreach ( array( 'sitemap.yaml', 'media.yaml', 'brand/tokens.json' ) as $path ) {
			if ( is_file( "$this->dir/$path" ) ) {
				$found[] = $path;
			}
		}
		foreach ( array( 'content', 'products', 'media' ) as $dir ) {
			if ( ! is_dir( "$this->dir/$dir" ) ) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( "$this->dir/$dir", \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				$path = substr( str_replace( '\\', '/', (string) $file ), strlen( $this->dir ) + 1 );
				if ( 'media' !== $dir ? str_ends_with( $path, '.md' ) : in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), array_merge( Package::IMAGE_TYPES, Package::FILE_TYPES ), true ) ) {
					$found[] = $path;
				}
			}
		}
		sort( $found );
		return $found;
	}

	/**
	 * Whether two pieces of block markup are the same block for block, past
	 * what the editor writes differently: entities, no-break spaces, white
	 * space between tags and block metadata.
	 *
	 * @param string $a Markup.
	 * @param string $b Markup.
	 */
	private static function same( string $a, string $b ): bool {
		return self::normal( parse_blocks( $a ) ) === self::normal( parse_blocks( $b ) );
	}

	/**
	 * Blocks reduced to what same() compares.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks Parsed blocks.
	 * @return list<mixed>
	 */
	private static function normal( array $blocks ): array {
		$out = array();
		foreach ( $blocks as $block ) {
			$html = (string) preg_replace( '/>\s+</', '><', (string) preg_replace( '/\s+/u', ' ', str_replace( "\u{00A0}", ' ', html_entity_decode( implode( '', array_filter( (array) $block['innerContent'], 'is_string' ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) );
			if ( null === $block['blockName'] ) {
				if ( '' !== trim( $html ) ) {
					$out[] = trim( $html );
				}
				continue;
			}
			$attrs = (array) $block['attrs'];
			unset( $attrs['metadata'] );
			$out[] = array( $block['blockName'], self::sorted( $attrs ), trim( $html ), self::normal( (array) $block['innerBlocks'] ) );
		}
		return $out;
	}

	/**
	 * An array with its string keys sorted, all the way down.
	 *
	 * @param array<int|string, mixed> $value Array.
	 * @return array<int|string, mixed>
	 */
	private static function sorted( array $value ): array {
		if ( ! array_is_list( $value ) ) {
			ksort( $value );
		}
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::sorted( $item );
			}
		}
		return $value;
	}
}
