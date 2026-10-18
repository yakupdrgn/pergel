<?php
/**
 * Fields of a pattern: the texts, images and buttons a package fills in.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Package;

use Closure;
use InvalidArgumentException;

/**
 * Finds the fields of a pattern from its blocks and writes package values
 * into them, so every pattern can be used from a package without a field
 * list kept by hand next to it.
 *
 * Works on parse_blocks() arrays and gives back the same shape for
 * serialize_blocks(); pure PHP, the integration tests feed it the parser's
 * output.
 *
 * A pattern's fields, in the order they appear:
 * - `heading`: a heading block.
 * - `eyebrow`: a paragraph before the first heading of its scope (only
 *   when there is a heading); other paragraphs are `text`.
 * - `buttons`: a buttons block, as a list of { label, url }.
 * - `image`: an image or media & text image, as a path/URL or { src, alt };
 *   a cover's image is `background`.
 * - `list`: a list block, as a list of item texts.
 * - `summary`: the visible line of a details block (its paragraphs are text).
 * - `quote` and `cite`: paragraphs inside a quote, and its citation.
 * - `items`: two or more sibling blocks of the same kind whose fields match
 *   (one may have fields the other lacks, as a featured price plan does);
 *   a list of field maps, one per item. Items may hold items.
 * A second field of a kind in the same scope is `text_2`, `heading_2` and
 * so on. Texts take inline Markdown. A null value removes the block; a
 * field left out keeps the pattern's text and is reported.
 *
 * Blocks that show site data (queries, navigation, forms, maps, shop blocks,
 * nested patterns, bound paragraphs) have no fields.
 */
final class Fields {

	/**
	 * Blocks whose content is not written by hand: their subtree is skipped.
	 */
	private const SKIP = array(
		'core/query',
		'core/navigation',
		'core/pattern',
		'core/block',
		'core/comments',
		'core/terms-query',
		'core/search',
		'core/latest-posts',
		'core/categories',
		'core/page-list',
		'core/table',
		'core/accordion',
		'core/social-links',
		'pergel/form',
		'pergel/map',
		'pergel/logo-marquee',
	);

	/**
	 * Block names whose siblings may form items.
	 */
	private const LEAVES = array( 'core/paragraph', 'core/heading', 'core/image', 'core/buttons', 'core/button', 'core/list', 'core/list-item' );

	/**
	 * Field schema of a pattern, for the catalogue and validation.
	 *
	 * @param list<array<string, mixed>> $blocks parse_blocks() output.
	 * @return array<string, array<string, mixed>> Field name => { type, default } (items: { type, items: list<schema> }).
	 */
	public static function schema( array $blocks ): array {
		$root = self::root( $blocks );
		return self::describe( $root, self::scan( $root ) );
	}

	/**
	 * Field names in one line, items as name[count]{fields}.
	 *
	 * @param array<string, array<string, mixed>> $schema Field schema.
	 */
	public static function summary( array $schema ): string {
		$names = array();
		foreach ( $schema as $name => $field ) {
			$names[] = 'items' === $field['type']
				? sprintf( '%s[%d]{%s}', $name, count( $field['items'] ), self::summary( $field['items'][0] ?? array() ) )
				: $name;
		}
		return implode( ' ', $names );
	}

	/**
	 * Values a package could write: the sample texts, images under media/.
	 *
	 * @param array<string, array<string, mixed>> $schema Field schema.
	 * @return array<string, mixed>
	 */
	public static function example( array $schema ): array {
		$values = array();
		foreach ( $schema as $name => $field ) {
			$values[ $name ] = match ( $field['type'] ) {
				'items' => array_map( array( self::class, 'example' ), $field['items'] ),
				'image' => 'media/' . ( '' !== $field['default'] ? pathinfo( $field['default'], PATHINFO_FILENAME ) : 'image' ) . '.jpg',
				default => $field['default'],
			};
		}
		return $values;
	}

	/**
	 * Writes values into a pattern's blocks.
	 *
	 * @param list<array<string, mixed>> $blocks parse_blocks() output.
	 * @param array<string, mixed>       $values Field values.
	 * @param Closure                    $media Turns an image path or URL, and the alt text written with it (null = none), into an attachment id (0 = none), URL and alt text.
	 * @phpstan-param Closure(string, ?string): array{id: int, url: string, alt: string} $media
	 * @param array<int, string>         $notes  Fields left with the pattern's content, by path.
	 * @return list<array{blockName: string|null, attrs: array<string, mixed>, innerBlocks: list<array<string, mixed>>, innerHTML: string, innerContent: list<string|null>}> Blocks for serialize_blocks().
	 * @throws InvalidArgumentException With the field path, for unknown fields and wrong values.
	 */
	public static function fill( array $blocks, array $values, Closure $media, array &$notes = array() ): array {
		$root = self::root( $blocks );
		self::fill_scope( $root, $values, $media, $notes, '' );
		return $root['innerBlocks'];
	}

	/**
	 * Reads a section back into field values: the reverse of fill(). The
	 * section is lined up with the pattern block by block, so a field whose
	 * block is gone reads as null and items are counted. A field that cannot
	 * be read (an image outside the package) is left out with a note.
	 *
	 * @param list<array<string, mixed>> $pattern The pattern's parse_blocks() output.
	 * @param list<array<string, mixed>> $section The section's blocks.
	 * @param Closure                    $image   Turns an image's URL, attachment id and alt text into a field value (a path or { src, alt }), null when it cannot be in the package.
	 * @phpstan-param Closure(string, int, string): (string|array<string, string>|null) $image
	 * @param array<int, string>         $notes   Fields that could not be read, with why.
	 * @return array<string, mixed>
	 */
	public static function values( array $pattern, array $section, Closure $image, array &$notes = array() ): array {
		$named = array_values( array_filter( $section, static fn( array $block ): bool => null !== $block['blockName'] ) );
		return self::read_scope( self::root( $pattern ), self::root( $named ), $image, $notes, '' );
	}

	/**
	 * Values of one scope.
	 *
	 * @param array<string, mixed> $from  Scope in the pattern.
	 * @param array<string, mixed> $to    The same scope in the section.
	 * @param Closure              $image Image reader, see values().
	 * @phpstan-param Closure(string, int, string): (string|array<string, string>|null) $image
	 * @param array<int, string>   $notes Report.
	 * @param string               $where Path of the scope for messages.
	 * @return array<string, mixed>
	 */
	private static function read_scope( array $from, array $to, Closure $image, array &$notes, string $where ): array {
		$values = array();
		foreach ( self::scan( $from ) as $slot ) {
			$name = $slot['name'];
			$at   = $where . $name;
			$path = self::follow( $from, $to, $slot['path'] );
			if ( null === $path ) {
				$values[ $name ] = null;
				continue;
			}
			$block = self::at( $to, $path );
			if ( 'items' === $slot['role'] ) {
				$holder = self::at( $from, $slot['path'] );
				$items  = array();
				foreach ( self::align( $holder, $block, $slot['members'] )['items'] as $n => $item ) {
					$items[] = self::read_scope( $holder['innerBlocks'][ $item[0] ], $block['innerBlocks'][ $item[1] ], $image, $notes, sprintf( '%s[%d].', $at, $n + 1 ) );
				}
				$values[ $name ] = $items;
				continue;
			}
			$value = self::value( $block, $slot['role'], $image );
			if ( is_string( $value ) && str_starts_with( $value, "\0" ) ) {
				$notes[] = "$at: " . substr( $value, 1 );
				continue;
			}
			$values[ $name ] = $value;
		}
		return $values;
	}

	/**
	 * The section path of the block at a pattern path, or null when it is gone.
	 *
	 * @param array<string, mixed> $from Scope in the pattern.
	 * @param array<string, mixed> $to   The same scope in the section.
	 * @param array<int, int>      $path Pattern path.
	 * @return array<int, int>|null
	 */
	private static function follow( array $from, array $to, array $path ): ?array {
		$found = array();
		foreach ( $path as $i ) {
			$map = self::align( $from, $to, self::members( $from['innerBlocks'] ) )['map'];
			if ( ! isset( $map[ $i ] ) ) {
				return null;
			}
			$from    = $from['innerBlocks'][ $i ];
			$to      = $to['innerBlocks'][ $map[ $i ] ];
			$found[] = $map[ $i ];
		}
		return $found;
	}

	/**
	 * Lines up a block's children in the pattern with its children in the
	 * section: the longest run of blocks of the same kind in the same order
	 * (blocks the same down to their attributes count for more), where
	 * pattern blocks may be gone and items may have been added after the
	 * last one.
	 *
	 * @param array<string, mixed> $from    Block in the pattern.
	 * @param array<string, mixed> $to      The same block in the section.
	 * @param list<int>|null       $members Child indexes of the pattern's items.
	 * @return array{map: array<int, int>, items: list<array{0: int, 1: int}>} Pattern child index => section child index; items as [ pattern member, section child ].
	 */
	private static function align( array $from, array $to, ?array $members ): array {
		$p = $from['innerBlocks'];
		$q = $to['innerBlocks'];
		// Tokens: pattern children, then after the last item one (-1) that
		// takes any number of added items and the blocks between them.
		$tokens = array_keys( array_values( $p ) );
		$extra  = array();
		if ( null !== $members ) {
			array_splice( $tokens, (int) end( $members ) + 1, 0, array( -1 ) );
			$extra = array( $p[ $members[0] ]['blockName'] );
			foreach ( self::joiner( $p, $members ) as $block ) {
				$extra[] = $block['blockName'];
			}
		}
		$n    = count( $tokens );
		$m    = count( $q );
		$pair = static function ( int $i, int $j ) use ( $tokens, $p, $q, $extra ): int {
			$token = $tokens[ $i ];
			if ( -1 === $token ) {
				return in_array( $q[ $j ]['blockName'], $extra, true ) ? 1 : 0;
			}
			if ( $p[ $token ]['blockName'] !== $q[ $j ]['blockName'] ) {
				return 0;
			}
			$a = $p[ $token ]['attrs'];
			$b = $q[ $j ]['attrs'];
			unset( $a['metadata'], $b['metadata'] );
			return $a == $b ? 3 : 2; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- the same attributes in any key order.
		};
		// $best[i][j]: the best score for tokens from i and section blocks from j.
		$best = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );
		for ( $i = $n - 1; $i >= 0; $i-- ) {
			for ( $j = $m; $j >= 0; $j-- ) {
				$score = max( $best[ $i + 1 ][ $j ], $j < $m ? $best[ $i ][ $j + 1 ] : 0 );
				$gain  = $j < $m ? $pair( $i, $j ) : 0;
				if ( $gain > 0 ) {
					// The extra token stays to take the next block too.
					$score = max( $score, $gain + ( -1 === $tokens[ $i ] ? $best[ $i ][ $j + 1 ] : $best[ $i + 1 ][ $j + 1 ] ) );
				}
				$best[ $i ][ $j ] = $score;
			}
		}
		$map   = array();
		$items = array();
		$first = $members[0] ?? -1;
		$i     = 0;
		$j     = 0;
		while ( $i < $n ) {
			$gain  = $j < $m ? $pair( $i, $j ) : 0;
			$token = $tokens[ $i ];
			if ( $gain > 0 && $best[ $i ][ $j ] === $gain + ( -1 === $token ? $best[ $i ][ $j + 1 ] : $best[ $i + 1 ][ $j + 1 ] ) ) {
				if ( -1 === $token ) {
					if ( $q[ $j ]['blockName'] === $extra[0] ) {
						$items[] = array( $first, $j );
					}
				} else {
					$map[ $token ] = $j;
					if ( null !== $members && in_array( $token, $members, true ) ) {
						$items[] = array( $token, $j );
					}
					++$i;
				}
				++$j;
			} elseif ( $j >= $m || $best[ $i ][ $j ] === $best[ $i + 1 ][ $j ] ) {
				++$i;
			} else {
				++$j;
			}
		}
		return array(
			'map'   => $map,
			'items' => $items,
		);
	}

	/**
	 * Value of one field as a package writes it, or a note starting with a
	 * NUL byte when it cannot be read.
	 *
	 * @param array<string, mixed> $block Block.
	 * @param string               $role  Role.
	 * @param Closure              $image Image reader, see values().
	 * @phpstan-param Closure(string, int, string): (string|array<string, string>|null) $image
	 * @return string|array<int|string, mixed>|null
	 */
	private static function value( array $block, string $role, Closure $image ): string|array|null {
		$first = (string) ( $block['innerContent'][0] ?? '' );
		switch ( $role ) {
			case 'buttons':
				$buttons = array();
				foreach ( $block['innerBlocks'] as $button ) {
					if ( preg_match( '/<a\b([^>]*)>(.*?)<\/a>/s', (string) ( $button['innerContent'][0] ?? '' ), $m ) ) {
						$buttons[] = array(
							'label' => MarkdownWriter::inline( $m[2] ),
							'url'   => preg_match( '/\shref="([^"]*)"/', $m[1], $href ) ? html_entity_decode( $href[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '',
						);
					}
				}
				return array() === $buttons ? null : $buttons;
			case 'image':
			case 'background':
				$html = implode( '', array_filter( $block['innerContent'], 'is_string' ) );
				if ( ! preg_match( '/<img\b[^>]*>/', $html, $img ) || ! preg_match( '/\ssrc="([^"]*)"/', $img[0], $src ) ) {
					return "\0there is no image to read";
				}
				$alt   = preg_match( '/\salt="([^"]*)"/', $img[0], $a ) ? html_entity_decode( $a[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
				$url   = html_entity_decode( $src[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$value = $image( $url, (int) ( $block['attrs'][ 'core/media-text' === $block['blockName'] ? 'mediaId' : 'id' ] ?? 0 ), $alt );
				return $value ?? "\0$url cannot be put in the package; the pattern's image stays";
			case 'list':
				$items = array();
				foreach ( $block['innerBlocks'] as $item ) {
					if ( preg_match( '/<li\b[^>]*>(.*?)(?:<\/li>\s*)?$/s', (string) ( $item['innerContent'][0] ?? '' ), $m ) ) {
						$items[] = MarkdownWriter::inline( $m[1] );
					}
				}
				return array() === $items ? null : $items;
			case 'summary':
				return MarkdownWriter::inline( (string) MarkdownWriter::inner( $first, 'summary' ) );
			case 'cite':
				return MarkdownWriter::inline( (string) MarkdownWriter::inner( (string) end( $block['innerContent'] ), 'cite' ) );
			default:
				$tag  = 'core/heading' === $block['blockName'] ? 'h' . (int) ( $block['attrs']['level'] ?? 2 ) : 'p';
				$text = MarkdownWriter::inner( $first, $tag );
				return null === $text ? "\0there is no <$tag> to read" : MarkdownWriter::inline( $text );
		}
	}

	/**
	 * Wraps blocks in a container so the root is scanned like any other.
	 *
	 * @param list<array<string, mixed>> $blocks Blocks.
	 * @return array<string, mixed>
	 */
	private static function root( array $blocks ): array {
		// Whitespace between top-level blocks comes as nameless blocks; they
		// stay, so the markup round-trips.
		$content = array();
		foreach ( $blocks as $i => $block ) {
			if ( $i > 0 ) {
				$content[] = "\n\n";
			}
			$content[] = null;
		}
		return array(
			'blockName'    => 'pergel/root',
			'attrs'        => array(),
			'innerBlocks'  => $blocks,
			'innerHTML'    => '',
			'innerContent' => $content,
		);
	}

	/**
	 * Fields of a scope in document order.
	 *
	 * @param array<string, mixed> $scope Scope block.
	 * @return list<array<string, mixed>> Slots: { role, path, ... }; items slots also { members: list<int> }.
	 */
	private static function scan( array $scope ): array {
		$slots = array();
		self::walk( $scope, array(), $slots, true );
		// Name them: paragraphs before the first heading are eyebrows when the
		// scope has a heading.
		$first_heading = null;
		foreach ( $slots as $i => $slot ) {
			if ( 'heading' === $slot['role'] ) {
				$first_heading = $i;
				break;
			}
		}
		$counts = array();
		foreach ( $slots as $i => &$slot ) {
			if ( 'text' === $slot['role'] && null !== $first_heading && $i < $first_heading && ! $slot['inner'] ) {
				$slot['role'] = 'eyebrow';
			}
			$counts[ $slot['role'] ] = ( $counts[ $slot['role'] ] ?? 0 ) + 1;
			$slot['name']            = 1 === $counts[ $slot['role'] ] ? $slot['role'] : $slot['role'] . '_' . $counts[ $slot['role'] ];
		}
		unset( $slot );
		return $slots;
	}

	/**
	 * Collects the slots under a block.
	 *
	 * @param array<string, mixed>       $block Block.
	 * @param array<int, int>            $path  Path of $block from the scope.
	 * @param list<array<string, mixed>> $slots Slots found so far.
	 * @param bool                       $is_scope Whether $block is the scope itself.
	 */
	private static function walk( array $block, array $path, array &$slots, bool $is_scope = false ): void {
		$name = (string) $block['blockName'];
		if ( ! $is_scope ) {
			if ( in_array( $name, self::SKIP, true ) || str_starts_with( $name, 'woocommerce/' ) || str_starts_with( $name, 'core/site-' ) || str_starts_with( $name, 'core/post-' ) || isset( $block['attrs']['metadata']['bindings'] ) ) {
				return;
			}
			$role = self::leaf_role( $block );
			if ( null !== $role ) {
				$slots[] = array(
					'role'  => $role,
					'path'  => $path,
					'inner' => false,
				);
				return;
			}
		}
		// Containers with a field of their own; an item scope keeps it too.
		$own = match ( $name ) {
			'core/details'    => 'summary',
			'core/cover'      => 'background',
			'core/media-text' => 'image',
			default           => null,
		};
		if ( null !== $own ) {
			$slots[] = array(
				'role'  => $own,
				'path'  => $path,
				'inner' => false,
			);
		}

		$children = $block['innerBlocks'];
		$members  = self::members( $children );
		$done     = false;
		foreach ( $children as $i => $child ) {
			if ( null !== $members && in_array( $i, $members, true ) ) {
				if ( ! $done ) {
					$slots[] = array(
						'role'    => 'items',
						'path'    => $path,
						'members' => $members,
						'inner'   => false,
					);
					$done    = true;
				}
				continue;
			}
			$before = count( $slots );
			self::walk( $child, array_merge( $path, array( $i ) ), $slots );
			if ( 'core/quote' === $name ) {
				for ( $j = $before, $n = count( $slots ); $j < $n; $j++ ) {
					if ( 'text' === $slots[ $j ]['role'] ) {
						$slots[ $j ]['role']  = 'quote';
						$slots[ $j ]['inner'] = true;
					}
				}
			}
		}
		if ( 'core/quote' === $name && str_contains( (string) end( $block['innerContent'] ), '<cite' ) ) {
			$slots[] = array(
				'role'  => 'cite',
				'path'  => $path,
				'inner' => false,
			);
		}
	}

	/**
	 * Role of a block that is a field by itself.
	 *
	 * @param array<string, mixed> $block Block.
	 */
	private static function leaf_role( array $block ): ?string {
		return match ( $block['blockName'] ) {
			'core/paragraph' => 'text',
			'core/heading'   => 'heading',
			'core/buttons'   => 'buttons',
			'core/image'     => 'image',
			'core/list'      => 'list',
			default          => null,
		};
	}

	/**
	 * Indexes of children that form items, or null.
	 *
	 * @param list<array<string, mixed>> $children Child blocks.
	 * @return list<int>|null
	 */
	private static function members( array $children ): ?array {
		$by_name = array();
		foreach ( $children as $i => $child ) {
			$name = (string) $child['blockName'];
			if ( '' === $name || in_array( $name, self::LEAVES, true ) || in_array( $name, self::SKIP, true ) ) {
				continue;
			}
			$by_name[ $name ][] = $i;
		}
		foreach ( $by_name as $indexes ) {
			if ( count( $indexes ) < 2 ) {
				continue;
			}
			$shapes = array();
			foreach ( $indexes as $i ) {
				$shapes[] = array_count_values( array_column( self::scan( $children[ $i ] ), 'role' ) );
			}
			// The largest set of blocks alike with each other; a heading group
			// before three contact rows is not one of them.
			$best = array();
			foreach ( $shapes as $k => $base ) {
				$set = array();
				foreach ( $shapes as $j => $shape ) {
					if ( array() === $shape || ! self::alike( $base, $shape ) ) {
						continue;
					}
					foreach ( $set as $other ) {
						if ( ! self::alike( $shapes[ $other ], $shape ) ) {
							continue 2;
						}
					}
					$set[] = $j;
				}
				if ( count( $set ) > count( $best ) && array() !== $shapes[ $k ] ) {
					$best = $set;
				}
			}
			if ( count( $best ) >= 2 ) {
				return array_map( static fn( int $j ): int => $indexes[ $j ], $best );
			}
		}
		return null;
	}

	/**
	 * Whether two blocks have the same fields, or one has a single field
	 * more (a featured price plan's badge). An image column beside a text
	 * column is not alike.
	 *
	 * @param array<int|string, int> $a Role counts.
	 * @param array<int|string, int> $b Role counts.
	 */
	private static function alike( array $a, array $b ): bool {
		$more = 0;
		$less = 0;
		foreach ( array_keys( $a + $b ) as $role ) {
			$diff  = ( $a[ $role ] ?? 0 ) - ( $b[ $role ] ?? 0 );
			$more += max( 0, $diff );
			$less += max( 0, -$diff );
		}
		return $more + $less <= 1;
	}

	/**
	 * Schema for scanned slots.
	 *
	 * @param array<string, mixed>       $scope Scope block.
	 * @param list<array<string, mixed>> $slots Slots.
	 * @return array<string, array<string, mixed>>
	 */
	private static function describe( array $scope, array $slots ): array {
		$schema = array();
		foreach ( $slots as $slot ) {
			$block = self::at( $scope, $slot['path'] );
			if ( 'items' === $slot['role'] ) {
				$items = array();
				foreach ( $slot['members'] as $i ) {
					$member  = $block['innerBlocks'][ $i ];
					$items[] = self::describe( $member, self::scan( $member ) );
				}
				$schema[ $slot['name'] ] = array(
					'type'  => 'items',
					'items' => $items,
				);
				continue;
			}
			$schema[ $slot['name'] ] = array(
				'type'    => self::type( $slot['role'] ),
				'default' => self::read( $block, $slot['role'] ),
			);
		}
		return $schema;
	}

	/**
	 * Value type of a role.
	 *
	 * @param string $role Role.
	 */
	private static function type( string $role ): string {
		return match ( $role ) {
			'buttons'             => 'buttons',
			'image', 'background' => 'image',
			'list'                => 'list',
			default               => 'text',
		};
	}

	/**
	 * Current value of a field, for the catalogue.
	 *
	 * @param array<string, mixed> $block Block.
	 * @param string               $role  Role.
	 * @return string|list<mixed>|array<string, string>
	 */
	private static function read( array $block, string $role ): string|array {
		$html = implode( '', array_filter( $block['innerContent'], 'is_string' ) );
		switch ( $role ) {
			case 'buttons':
				$buttons = array();
				foreach ( $block['innerBlocks'] as $button ) {
					preg_match( '/<a\b([^>]*)>(.*?)<\/a>/s', implode( '', array_filter( $button['innerContent'], 'is_string' ) ), $m );
					preg_match( '/\shref="([^"]*)"/', $m[1] ?? '', $href );
					$buttons[] = array(
						'label' => self::plain( $m[2] ?? '' ),
						'url'   => html_entity_decode( $href[1] ?? '' ),
					);
				}
				return $buttons;
			case 'image':
			case 'background':
				preg_match( '/<img\b[^>]*\bsrc="([^"]*)"/', $html, $m );
				return basename( (string) parse_url( html_entity_decode( $m[1] ?? '' ), PHP_URL_PATH ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure PHP, a path only.
			case 'list':
				return array_map( static fn( array $item ): string => self::plain( (string) ( $item['innerContent'][0] ?? '' ) ), $block['innerBlocks'] );
			case 'summary':
				preg_match( '/<summary\b[^>]*>(.*?)<\/summary>/s', $html, $m );
				return self::plain( $m[1] ?? '' );
			case 'cite':
				preg_match( '/<cite\b[^>]*>(.*?)<\/cite>/s', $html, $m );
				return self::plain( $m[1] ?? '' );
			default:
				return self::plain( $html );
		}
	}

	/**
	 * Fills one scope.
	 *
	 * @param array<string, mixed> $scope  Scope block, changed in place.
	 * @param array<string, mixed> $values Values for the scope.
	 * @param Closure              $media Image resolver.
	 * @phpstan-param Closure(string, ?string): array{id: int, url: string, alt: string} $media
	 * @param array<int, string>   $notes  Report of fields left as they were.
	 * @param string               $where  Path of the scope for messages.
	 * @throws InvalidArgumentException For unknown fields and wrong values.
	 */
	private static function fill_scope( array &$scope, array $values, Closure $media, array &$notes, string $where ): void {
		$slots = self::scan( $scope );
		$names = array_column( $slots, 'name' );
		foreach ( array_keys( $values ) as $key ) {
			if ( ! in_array( (string) $key, $names, true ) ) {
				throw new InvalidArgumentException( sprintf( '%s"%s" is not a field here; fields: %s', $where, $key, array() === $names ? 'none' : implode( ', ', $names ) ) );
			}
		}
		// Last first, so a removed or added block does not move the ones still to do;
		// notes are kept per slot and given in the pattern's order.
		$kept = array();
		foreach ( array_reverse( $slots ) as $slot ) {
			$name = $slot['name'];
			$at   = $where . $name;
			if ( ! array_key_exists( $name, $values ) ) {
				$kept[] = array( $at );
				continue;
			}
			$value  = $values[ $name ];
			$parent = $slot['path'];
			$index  = array_pop( $parent );
			$block  = &self::ref( $scope, $slot['path'] );
			if ( 'items' === $slot['role'] ) {
				$inner = array();
				self::fill_items( $block, $slot['members'], $value, $media, $inner, $at );
				$kept[] = $inner;
			} elseif ( null === $value ) {
				unset( $block );
				$holder = &self::ref( $scope, $parent );
				self::remove( $holder, (int) $index );
				unset( $holder );
				continue;
			} else {
				self::write( $block, $slot['role'], $value, $media, $at );
			}
			unset( $block );
		}
		foreach ( array_reverse( $kept ) as $list ) {
			array_push( $notes, ...$list );
		}
	}

	/**
	 * Fills items: the first ones in place, extra ones as copies of the
	 * first, missing ones removed. Separators between items go and come
	 * with them.
	 *
	 * @param array<string, mixed> $container Block holding the items.
	 * @param array<int, int>      $members   Child indexes of the items.
	 * @param mixed                $values    List of item values.
	 * @param Closure              $media Image resolver.
	 * @phpstan-param Closure(string, ?string): array{id: int, url: string, alt: string} $media
	 * @param array<int, string>   $notes     Report.
	 * @param string               $at        Field path.
	 * @throws InvalidArgumentException When $values is not a list of maps.
	 */
	private static function fill_items( array &$container, array $members, mixed $values, Closure $media, array &$notes, string $at ): void {
		if ( null === $values ) {
			$values = array();
		}
		if ( ! is_array( $values ) || ! array_is_list( $values ) ) {
			throw new InvalidArgumentException( "$at: a list of items is expected" );
		}
		$template = $container['innerBlocks'][ $members[0] ];
		$joiner   = self::joiner( $container['innerBlocks'], $members );
		$step     = count( $joiner );
		$last     = (int) end( $members );
		$indexes  = $members;
		// Extra items go after the last one.
		for ( $n = count( $members ), $want = count( $values ); $n < $want; $n++ ) {
			foreach ( $joiner as $block ) {
				self::insert( $container, ++$last, $block );
			}
			$indexes[] = ++$last;
			self::insert( $container, $last, $template );
		}
		for ( $n = count( $indexes ) - 1; $n >= 0; $n-- ) {
			if ( $n >= count( $values ) ) {
				self::remove( $container, $indexes[ $n ] );
				// The separator before it, or the one after the first item when no item is left.
				for ( $j = 1; $j <= $step; $j++ ) {
					$index = $n > 0 ? $indexes[ $n ] - $j : $indexes[0];
					if ( ( $container['innerBlocks'][ $index ]['blockName'] ?? '' ) === $joiner[ $n > 0 ? $step - $j : $j - 1 ]['blockName'] ) {
						self::remove( $container, $index );
					}
				}
				continue;
			}
			if ( ! is_array( $values[ $n ] ) || ( array() !== $values[ $n ] && array_is_list( $values[ $n ] ) ) ) {
				throw new InvalidArgumentException( sprintf( '%s[%d]: an item is a map of fields', $at, $n + 1 ) );
			}
			self::fill_scope( $container['innerBlocks'][ $indexes[ $n ] ], $values[ $n ], $media, $notes, sprintf( '%s[%d].', $at, $n + 1 ) );
		}
	}

	/**
	 * Blocks that stand between each two items, like a separator, when the
	 * items are evenly spaced by them.
	 *
	 * @param array<int, array<string, mixed>> $children Children of the items' container.
	 * @param array<int, int>                  $members  Child indexes of the items.
	 * @return array<int, array<string, mixed>>
	 */
	private static function joiner( array $children, array $members ): array {
		$step  = $members[1] - $members[0] - 1;
		$total = count( $members );
		for ( $i = 2; $i < $total; $i++ ) {
			if ( $members[ $i ] - $members[ $i - 1 ] - 1 !== $step ) {
				return array();
			}
		}
		$blocks = array_slice( $children, $members[0] + 1, $step );
		foreach ( $blocks as $block ) {
			$blank = null === $block['blockName'] && '' === trim( (string) $block['innerHTML'] );
			if ( ! $blank && ! in_array( $block['blockName'], array( 'core/separator', 'core/spacer' ), true ) ) {
				return array();
			}
		}
		return $blocks;
	}

	/**
	 * Writes one field.
	 *
	 * @param array<string, mixed> $block Block, changed in place.
	 * @param string               $role  Role.
	 * @param mixed                $value Value.
	 * @param Closure              $media Image resolver.
	 * @phpstan-param Closure(string, ?string): array{id: int, url: string, alt: string} $media
	 * @param string               $at    Field path.
	 * @throws InvalidArgumentException For a value of the wrong shape.
	 */
	private static function write( array &$block, string $role, mixed $value, Closure $media, string $at ): void {
		switch ( $role ) {
			case 'buttons':
				self::write_buttons( $block, $value, $at );
				return;
			case 'image':
			case 'background':
				self::write_image( $block, $value, $media, $at );
				return;
			case 'list':
				if ( ! is_array( $value ) || ! array_is_list( $value ) || array() === $value ) {
					throw new InvalidArgumentException( "$at: a list of texts is expected" );
				}
				$template = $block['innerBlocks'][0];
				$count    = count( $block['innerBlocks'] );
				$want     = count( $value );
				for ( $i = $count - 1; $i >= $want; $i-- ) {
					self::remove( $block, $i );
				}
				for ( $i = $count; $i < $want; $i++ ) {
					self::insert( $block, $i, $template );
				}
				foreach ( $value as $i => $text ) {
					$block['innerBlocks'][ $i ]['innerContent'][0] = self::replace_inner( (string) $block['innerBlocks'][ $i ]['innerContent'][0], 'li', self::html( $text, sprintf( '%s[%d]', $at, $i + 1 ) ), true );
				}
				return;
			case 'summary':
				$block['innerContent'][0] = self::replace_inner( (string) $block['innerContent'][0], 'summary', self::html( $value, $at ) );
				return;
			case 'cite':
				$last                           = count( $block['innerContent'] ) - 1;
				$block['innerContent'][ $last ] = self::replace_inner( (string) $block['innerContent'][ $last ], 'cite', self::html( $value, $at ) );
				return;
			default:
				$tag                      = 'core/heading' === $block['blockName'] ? 'h' . (int) ( $block['attrs']['level'] ?? 2 ) : 'p';
				$block['innerContent'][0] = self::replace_inner( (string) $block['innerContent'][0], $tag, self::html( $value, $at ) );
		}
	}

	/**
	 * Writes a buttons block.
	 *
	 * @param array<string, mixed> $block Buttons block.
	 * @param mixed                $value List of { label, url }.
	 * @param string               $at    Field path.
	 * @throws InvalidArgumentException For a value of the wrong shape.
	 */
	private static function write_buttons( array &$block, mixed $value, string $at ): void {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || array() === $value ) {
			throw new InvalidArgumentException( "$at: a list of { label, url } is expected" );
		}
		$count = count( $block['innerBlocks'] );
		$want  = count( $value );
		for ( $i = $count - 1; $i >= $want; $i-- ) {
			self::remove( $block, $i );
		}
		for ( $i = $count; $i < $want; $i++ ) {
			self::insert( $block, $i, $block['innerBlocks'][ $count - 1 ] );
		}
		foreach ( $value as $i => $button ) {
			$where = sprintf( '%s[%d]', $at, $i + 1 );
			if ( ! is_array( $button ) || ! isset( $button['label'], $button['url'] ) || array() !== array_diff( array_keys( $button ), array( 'label', 'url' ) ) ) {
				throw new InvalidArgumentException( "$where: a button is { label, url }" );
			}
			$url  = Markdown::url( trim( (string) $button['url'] ) );
			$html = (string) $block['innerBlocks'][ $i ]['innerContent'][0];
			$html = (string) preg_replace_callback(
				'/<a\b[^>]*>/',
				static function ( array $m ) use ( $url ): string {
					$href = ' href="' . Markdown::attr( $url ) . '"';
					if ( preg_match( '/\shref="[^"]*"/', $m[0] ) ) {
						return (string) preg_replace( '/\shref="[^"]*"/', $href, $m[0], 1 );
					}
					// The editor writes href right after class.
					if ( preg_match( '/\sclass="[^"]*"/', $m[0] ) ) {
						return (string) preg_replace( '/(\sclass="[^"]*")/', '$1' . $href, $m[0], 1 );
					}
					return '<a' . $href . substr( $m[0], 2 );
				},
				$html,
				1
			);
			$block['innerBlocks'][ $i ]['innerContent'][0] = self::replace_inner( $html, 'a', self::html( $button['label'], $where ) );
		}
	}

	/**
	 * Writes an image, cover background or media & text image.
	 *
	 * @param array<string, mixed> $block Block.
	 * @param mixed                $value Path/URL or { src, alt }.
	 * @param Closure              $media Image resolver.
	 * @phpstan-param Closure(string, ?string): array{id: int, url: string, alt: string} $media
	 * @param string               $at    Field path.
	 * @throws InvalidArgumentException For a value of the wrong shape.
	 */
	private static function write_image( array &$block, mixed $value, Closure $media, string $at ): void {
		if ( is_string( $value ) ) {
			$value = array( 'src' => $value );
		}
		if ( ! is_array( $value ) || ! isset( $value['src'] ) || ! is_string( $value['src'] ) || array() !== array_diff( array_keys( $value ), array( 'src', 'alt' ) ) ) {
			throw new InvalidArgumentException( "$at: an image is a path or { src, alt }" );
		}
		try {
			$found = $media( $value['src'], isset( $value['alt'] ) ? (string) $value['alt'] : null );
		} catch ( InvalidArgumentException $e ) {
			throw new InvalidArgumentException( "$at: " . $e->getMessage() );
		}
		$alt  = $found['alt'];
		$name = $block['blockName'];
		$key  = 'core/media-text' === $name ? 'mediaId' : 'id';
		if ( $found['id'] > 0 ) {
			$block['attrs'][ $key ] = $found['id'];
		} else {
			unset( $block['attrs'][ $key ] );
		}
		if ( 'core/cover' === $name ) {
			$block['attrs']['url'] = $found['url'];
		}
		if ( 'core/media-text' === $name && isset( $block['attrs']['mediaLink'] ) ) {
			unset( $block['attrs']['mediaLink'] );
		}
		foreach ( $block['innerContent'] as $i => $piece ) {
			if ( ! is_string( $piece ) || ! str_contains( $piece, '<img' ) ) {
				continue;
			}
			$block['innerContent'][ $i ] = (string) preg_replace_callback(
				'/<img\b[^>]*?\/?>/',
				static function ( array $m ) use ( $found, $alt, $name ): string {
					$tag = $m[0];
					$tag = (string) preg_replace( '/\ssrc="[^"]*"/', ' src="' . Markdown::attr( $found['url'] ) . '"', $tag, 1 );
					$tag = (string) preg_replace( '/\salt="[^"]*"/', ' alt="' . Markdown::attr( $alt ) . '"', $tag, 1 );
					// The editor marks the attachment with a class on the img.
					$class = 'core/cover' === $name ? 'wp-block-cover__image-background' : '';
					$id    = $found['id'] > 0 ? ( 'core/media-text' === $name ? 'wp-image-' . $found['id'] . ' size-full' : 'wp-image-' . $found['id'] ) : ( 'core/media-text' === $name ? 'size-full' : '' );
					$want  = trim( $class . ' ' . $id );
					if ( preg_match( '/\sclass="([^"]*)"/', $tag ) ) {
						$tag = (string) preg_replace( '/\sclass="[^"]*"/', '' !== $want ? ' class="' . $want . '"' : '', $tag, 1 );
					} elseif ( '' !== $want ) {
						$tag = (string) preg_replace( '/\/?>$/', ' class="' . $want . '"$0', $tag, 1 );
					}
					return $tag;
				},
				$piece,
				1
			);
		}
	}

	/**
	 * Inline HTML for a text value.
	 *
	 * @param mixed  $value Text (numbers are allowed).
	 * @param string $at    Field path.
	 * @throws InvalidArgumentException When the value is not text.
	 */
	private static function html( mixed $value, string $at ): string {
		if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
			throw new InvalidArgumentException( "$at: text is expected" );
		}
		try {
			return Markdown::inline( (string) $value );
		} catch ( InvalidArgumentException $e ) {
			throw new InvalidArgumentException( "$at: " . $e->getMessage() );
		}
	}

	/**
	 * Replaces what is between the first <$tag> and its closing tag.
	 *
	 * @param string $html  Markup.
	 * @param string $tag   Element name.
	 * @param string $inner New content.
	 * @param bool   $open  Whether the closing tag is in another piece (a list item with a nested list).
	 */
	private static function replace_inner( string $html, string $tag, string $inner, bool $open = false ): string {
		if ( ! preg_match( '/<' . $tag . '\b[^>]*>/', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}
		$start = $m[0][1] + strlen( $m[0][0] );
		$end   = strpos( $html, '</' . $tag . '>', $start );
		if ( false === $end ) {
			if ( ! $open ) {
				return $html;
			}
			$end = strlen( $html );
		}
		return substr( $html, 0, $start ) . $inner . substr( $html, $end );
	}

	/**
	 * Removes a child block and its placeholder in the parent's markup, with
	 * the whitespace that separated it from a neighbour.
	 *
	 * @param array<string, mixed> $holder Parent block.
	 * @param int                  $index  Child index.
	 */
	private static function remove( array &$holder, int $index ): void {
		array_splice( $holder['innerBlocks'], $index, 1 );
		$content = $holder['innerContent'];
		$at      = self::slot_of( $content, $index );
		if ( null === $at ) {
			return;
		}
		if ( self::gap( $content, $at + 1 ) && array_key_exists( $at + 2, $content ) && null === $content[ $at + 2 ] ) {
			array_splice( $holder['innerContent'], $at, 2 );
		} elseif ( $at >= 2 && self::gap( $content, $at - 1 ) && null === $content[ $at - 2 ] ) {
			array_splice( $holder['innerContent'], $at - 1, 2 );
		} else {
			array_splice( $holder['innerContent'], $at, 1 );
		}
	}

	/**
	 * Inserts a copy of a block as child $index, after child $index - 1.
	 *
	 * @param array<string, mixed> $holder Parent block.
	 * @param int                  $index  New child index, at least 1.
	 * @param array<string, mixed> $block  Block to copy.
	 */
	private static function insert( array &$holder, int $index, array $block ): void {
		$content = $holder['innerContent'];
		// Separate it the way the children already are.
		$gap = "\n\n";
		foreach ( $content as $i => $piece ) {
			if ( $i > 0 && null === $content[ $i - 1 ] && self::gap( $content, $i ) && array_key_exists( $i + 1, $content ) && null === $content[ $i + 1 ] ) {
				$gap = (string) $piece;
				break;
			}
		}
		$after = self::slot_of( $content, $index - 1 );
		array_splice( $holder['innerBlocks'], $index, 0, array( $block ) );
		array_splice( $holder['innerContent'], null === $after ? count( $content ) : $after + 1, 0, array( $gap, null ) );
	}

	/**
	 * Position of child $index's placeholder in innerContent.
	 *
	 * @param array<int, string|null> $content innerContent.
	 * @param int                     $index   Child index.
	 */
	private static function slot_of( array $content, int $index ): ?int {
		$seen = -1;
		foreach ( $content as $i => $piece ) {
			if ( null === $piece && ++$seen === $index ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Whether innerContent[$i] is whitespace only.
	 *
	 * @param array<int, string|null> $content innerContent.
	 * @param int                     $i       Position.
	 */
	private static function gap( array $content, int $i ): bool {
		return isset( $content[ $i ] ) && '' === trim( $content[ $i ] );
	}

	/**
	 * Block at a path.
	 *
	 * @param array<string, mixed> $scope Scope block.
	 * @param array<int, int>      $path  Child indexes.
	 * @return array<string, mixed>
	 */
	private static function at( array $scope, array $path ): array {
		foreach ( $path as $i ) {
			$scope = $scope['innerBlocks'][ $i ];
		}
		return $scope;
	}

	/**
	 * Reference to the block at a path.
	 *
	 * @param array<string, mixed> $scope Scope block.
	 * @param array<int, int>      $path  Child indexes.
	 * @return array<string, mixed>
	 */
	private static function &ref( array &$scope, array $path ): array {
		$block = &$scope;
		foreach ( $path as $i ) {
			$block = &$block['innerBlocks'][ $i ];
		}
		return $block;
	}

	/**
	 * Text without tags or extra whitespace.
	 *
	 * @param string $html Markup.
	 */
	private static function plain( string $html ): string {
		$html = (string) preg_replace( '/<br\s*\/?>/', "\n", $html );
		return trim( (string) preg_replace( '/[ \t]+/', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure PHP; the text is escaped again where it is used.
	}
}
