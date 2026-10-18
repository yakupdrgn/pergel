<?php
/**
 * Pergel's abilities.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Abilities;

use Closure;
use Pergel\Package\Fields;
use Pergel\Package\Importer;
use Pergel\Package\Package;
use Pergel\Settings\Settings;
use Pergel\Tokens\Compiler;
use Throwable;
use WP_Block_Patterns_Registry;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Registers the read-only abilities. Each needs a capability, and every
 * call counts against the user's limit (LIMIT a minute, filterable).
 */
final class Abilities {

	/**
	 * Ability category.
	 */
	public const CATEGORY = 'pergel';

	/**
	 * Calls a user may make in WINDOW seconds.
	 */
	public const LIMIT = 60;

	/**
	 * Length of the limit's window, in seconds.
	 */
	public const WINDOW = 60;

	/**
	 * Most pages a call lists.
	 */
	public const MAX_PAGES = 100;

	/**
	 * Hooked on wp_abilities_api_categories_init.
	 */
	public static function category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Pergel', 'pergel-engine' ),
				'description' => __( 'Read the patterns, pages, design tokens and settings of a site built with Pergel.', 'pergel-engine' ),
			)
		);
	}

	/**
	 * Hooked on wp_abilities_api_init.
	 */
	public static function register(): void {
		self::ability(
			'pergel/list-patterns',
			array(
				'label'        => __( 'List Pergel patterns', 'pergel-engine' ),
				'description'  => __( 'Lists the Pergel patterns a page can be built from, with their categories and the fields a project package fills in them.', 'pergel-engine' ),
				'capability'   => 'edit_posts',
				'input_schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'category' => array(
							'type'        => 'string',
							'description' => __( 'Optional: only patterns in this category, like banner or pergel-faq.', 'pergel-engine' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'       => array( 'type' => 'string' ),
							'title'      => array( 'type' => 'string' ),
							'categories' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'fields'     => array( 'type' => 'string' ),
						),
					),
				),
				'run'          => array( self::class, 'list_patterns' ),
			)
		);

		self::ability(
			'pergel/get-pattern-fields',
			array(
				'label'        => __( 'Get the fields of a Pergel pattern', 'pergel-engine' ),
				'description'  => __( 'Returns the fields of one Pergel pattern with their types and sample values, and a section of a project package content file that uses them.', 'pergel-engine' ),
				'capability'   => 'edit_posts',
				'input_schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'name' => array(
							'type'        => 'string',
							'description' => __( 'Pattern name, like hero-split.', 'pergel-engine' ),
						),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'output'       => array(
					'type'       => 'object',
					'properties' => array(
						'name'    => array( 'type' => 'string' ),
						'title'   => array( 'type' => 'string' ),
						'fields'  => array( 'type' => 'object' ),
						'section' => array( 'type' => 'object' ),
					),
				),
				'run'          => array( self::class, 'pattern_fields' ),
			)
		);

		self::ability(
			'pergel/list-pages',
			array(
				'label'        => __( 'List pages and their sections', 'pergel-engine' ),
				'description'  => __( 'Lists the pages in menu order with their address, template and status, the patterns their sections were made from, and the project package that made them.', 'pergel-engine' ),
				'capability'   => 'edit_pages',
				'input_schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_PAGES,
							'default' => 50,
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output'       => array(
					'type'       => 'object',
					'properties' => array(
						'pages' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'object' ),
						),
						'total' => array( 'type' => 'integer' ),
						'more'  => array( 'type' => 'boolean' ),
					),
				),
				'run'          => array( self::class, 'list_pages' ),
			)
		);

		self::ability(
			'pergel/get-design-tokens',
			array(
				'label'       => __( 'Get the design tokens', 'pergel-engine' ),
				'description' => __( 'Returns the design tokens of the active theme (colors, fonts, spacing, radius, shadows): the Pergel theme\'s, merged with what a child theme changes. A project package writes such changes in brand/tokens.json.', 'pergel-engine' ),
				'capability'  => 'edit_theme_options',
				'output'      => array(
					'type'       => 'object',
					'properties' => array(
						'theme'  => array( 'type' => 'string' ),
						'tokens' => array( 'type' => 'object' ),
					),
				),
				'run'         => array( self::class, 'design_tokens' ),
			)
		);

		self::ability(
			'pergel/get-settings',
			array(
				'label'       => __( 'Get the Pergel settings', 'pergel-engine' ),
				'description' => __( 'Returns the settings under Pergel → Settings by group: the module switches, the brand details, SEO, performance, forms, search and shop.', 'pergel-engine' ),
				'capability'  => 'manage_options',
				'output'      => array( 'type' => 'object' ),
				'run'         => array( Settings::class, 'all' ),
			)
		);
	}

	/**
	 * Registers one ability with the shared parts.
	 *
	 * @param string                                                                                                                                          $name Ability name.
	 * @param array{label: string, description: string, capability: string, input_schema?: array<string, mixed>, output: array<string, mixed>, run: callable} $args Its parts.
	 * @phpstan-param lowercase-string&non-falsy-string $name
	 */
	private static function ability( string $name, array $args ): void {
		$capability = $args['capability'];
		$ability    = array(
			'label'               => $args['label'],
			'description'         => $args['description'],
			'category'            => self::CATEGORY,
			'output_schema'       => $args['output'],
			'execute_callback'    => self::limited( Closure::fromCallable( $args['run'] ) ),
			'permission_callback' => static fn(): bool => current_user_can( $capability ),
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'public'      => true,
				'mcp'         => array(
					'public' => true,
					'type'   => 'tool',
				),
			),
		);
		if ( isset( $args['input_schema'] ) ) {
			$ability['input_schema'] = $args['input_schema'];
		}
		wp_register_ability( $name, $ability );
	}

	/**
	 * Wraps an ability so that each call counts against the user's limit.
	 *
	 * @param Closure $run The ability.
	 */
	private static function limited( Closure $run ): Closure {
		return static function ( mixed $input = null ) use ( $run ): mixed {
			/**
			 * Filters how many ability calls a user may make a minute.
			 *
			 * @param int $limit Calls in WINDOW seconds.
			 */
			$limit = (int) apply_filters( 'pergel/abilities/limit', self::LIMIT );
			$key   = 'pergel_abilities_' . get_current_user_id();
			$count = (int) get_transient( $key );
			if ( $count >= $limit ) {
				return new WP_Error( 'pergel_ability_limit', __( 'Too many requests. Try again in a minute.', 'pergel-engine' ), array( 'status' => 429 ) );
			}
			set_transient( $key, $count + 1, self::WINDOW );
			try {
				return $run( $input );
			} catch ( Throwable $e ) {
				return new WP_Error( 'pergel_ability_failed', $e->getMessage(), array( 'status' => 500 ) );
			}
		};
	}

	/**
	 * Pergel's patterns.
	 *
	 * @param mixed $input { category?: string }.
	 * @return list<array{name: string, title: string, categories: list<string>, fields: string}>
	 */
	public static function list_patterns( mixed $input = null ): array {
		$category = is_array( $input ) && is_string( $input['category'] ?? null ) && '' !== $input['category'] ? $input['category'] : null;
		$rows     = array();
		foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			if ( ! str_starts_with( $pattern['name'], 'pergel/' ) ) {
				continue;
			}
			$categories = array_values( array_map( 'strval', (array) ( $pattern['categories'] ?? array() ) ) );
			if ( null !== $category && ! in_array( $category, $categories, true ) ) {
				continue;
			}
			$rows[] = array(
				'name'       => substr( $pattern['name'], strlen( 'pergel/' ) ),
				'title'      => (string) $pattern['title'],
				'categories' => $categories,
				'fields'     => Fields::summary( Fields::schema( (array) Package::pattern( $pattern['name'] ) ) ),
			);
		}
		usort( $rows, static fn( array $a, array $b ): int => strcmp( $a['name'], $b['name'] ) );
		return $rows;
	}

	/**
	 * One pattern's fields and a section that fills them.
	 *
	 * @param mixed $input { name: string }.
	 * @return array{name: string, title: string, fields: object, section: object}|WP_Error
	 */
	public static function pattern_fields( mixed $input = null ): array|WP_Error {
		$name   = is_array( $input ) && is_string( $input['name'] ?? null ) ? $input['name'] : '';
		$name   = str_starts_with( $name, 'pergel/' ) ? substr( $name, strlen( 'pergel/' ) ) : $name;
		$blocks = '' === $name ? null : Package::pattern( "pergel/$name" );
		if ( null === $blocks ) {
			/* translators: %s: pattern name. */
			return new WP_Error( 'pergel_unknown_pattern', sprintf( __( '"%s" is not a Pergel pattern; pergel/list-patterns lists them.', 'pergel-engine' ), $name ), array( 'status' => 404 ) );
		}
		$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( "pergel/$name" );
		$schema  = Fields::schema( $blocks );
		return array(
			'name'    => $name,
			'title'   => (string) ( $pattern['title'] ?? $name ),
			'fields'  => (object) $schema,
			'section' => (object) ( array( 'pattern' => $name ) + Fields::example( $schema ) ),
		);
	}

	/**
	 * Pages with the outline of their content: the pages the user may edit,
	 * so a role with edit_pages alone does not read other people's drafts.
	 *
	 * @param mixed $input { page?: int, per_page?: int }.
	 * @return array{pages: list<array<string, mixed>>, total: int, more: bool}
	 */
	public static function list_pages( mixed $input = null ): array {
		$input    = is_array( $input ) ? $input : array();
		$page     = max( 1, (int) ( $input['page'] ?? 1 ) );
		$per_page = min( self::MAX_PAGES, max( 1, (int) ( $input['per_page'] ?? 50 ) ) );
		$args     = array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'posts_per_page' => $per_page,
			'paged'          => $page,
		);
		if ( ! current_user_can( 'edit_others_pages' ) ) {
			$args['author'] = get_current_user_id();
		}
		if ( ! current_user_can( 'read_private_pages' ) ) {
			$args['post_status'] = array_values( array_diff( $args['post_status'], array( 'private' ) ) );
		}
		$query = new WP_Query( $args );
		$front    = (int) get_option( 'page_on_front' );
		$pages    = array();
		foreach ( $query->posts ?? array() as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$pages[] = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'status'   => $post->post_status,
				'path'     => $post->ID === $front ? '' : get_page_uri( $post ),
				'url'      => (string) get_permalink( $post ),
				'parent'   => $post->post_parent,
				'template' => (string) get_page_template_slug( $post ),
				'package'  => (string) get_post_meta( $post->ID, Importer::META, true ),
				'outline'  => self::outline( $post->post_content ),
			);
		}
		return array(
			'pages' => $pages,
			'total' => (int) $query->found_posts,
			'more'  => $page < (int) $query->max_num_pages,
		);
	}

	/**
	 * A page's top-level blocks: the pattern a section was made from, or
	 * the block's name.
	 *
	 * @param string $content Post content.
	 * @return list<string>
	 */
	private static function outline( string $content ): array {
		$outline = array();
		foreach ( parse_blocks( $content ) as $block ) {
			if ( null === $block['blockName'] ) {
				continue;
			}
			$pattern   = $block['attrs']['metadata']['patternName'] ?? null;
			$outline[] = is_string( $pattern ) ? $pattern : (string) $block['blockName'];
		}
		return $outline;
	}

	/**
	 * The active theme's design tokens.
	 *
	 * @return array{theme: string, tokens: object}
	 */
	public static function design_tokens(): array {
		$tokens = array();
		$parent = get_template_directory() . '/tokens/default.json';
		$child  = get_stylesheet_directory() . '/tokens/default.json';
		if ( is_file( $parent ) ) {
			$tokens = Compiler::read_json( $parent );
		}
		if ( get_stylesheet() !== get_template() && is_file( $child ) ) {
			$tokens = Compiler::merge( $tokens, Compiler::read_json( $child ) );
		}
		return array(
			'theme'  => get_stylesheet(),
			'tokens' => (object) $tokens,
		);
	}
}
