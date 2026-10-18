<?php
/**
 * Questions and specifications read from the blocks of a page.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

/**
 * Structured data the content already holds. Questions are Details blocks,
 * or groups that open with a heading of level 3 or below (question cards),
 * inside a block with the "pergel-faq" class (the FAQ patterns); they become
 * a FAQPage. Specifications are the two-cell rows of tables in the
 * "Specifications" style; on a product they become additionalProperty
 * values. The visitor sees the same text, so the data cannot drift from the
 * page.
 *
 * Takes parse_blocks() output and makes no WordPress calls.
 */
final class Facts {

	public const FAQ = 'pergel-faq';

	public const SPECS = 'is-style-pergel-specs';

	/**
	 * Closing tags after which text needs a space.
	 */
	private const BREAKS = '#(</(?:p|h[1-6]|li|dt|dd|div|blockquote|figcaption|td|th|pre|summary)>|<br\s*/?>)#i';

	/**
	 * Questions and answers, in page order.
	 *
	 * @param array<mixed> $blocks Parsed blocks.
	 * @param bool         $inside Whether the blocks are inside an FAQ.
	 * @return list<array{question: string, answer: string}>
	 */
	public static function questions( array $blocks, bool $inside = false ): array {
		$found = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( $inside && 'core/details' === ( $block['blockName'] ?? '' ) ) {
				$question = preg_match( '#<summary\b[^>]*>(.*?)</summary>#is', self::string( $block['innerHTML'] ?? '' ), $match ) ? self::plain( $match[1] ) : '';
				$answer   = self::plain( implode( ' ', array_map( array( self::class, 'html' ), self::inner( $block ) ) ) );
				if ( '' !== $question && '' !== $answer ) {
					$found[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
				continue;
			}
			$inner = self::inner( $block );
			if ( $inside && 'core/group' === ( $block['blockName'] ?? '' ) && count( $inner ) > 1 && self::is_question( $inner[0] ) ) {
				$question = self::plain( self::html( $inner[0] ) );
				$answer   = self::plain( implode( ' ', array_map( array( self::class, 'html' ), array_slice( $inner, 1 ) ) ) );
				if ( '' !== $question && '' !== $answer ) {
					$found[] = array(
						'question' => $question,
						'answer'   => $answer,
					);
				}
				continue;
			}
			$found = array_merge( $found, self::questions( $inner, $inside || self::has_class( $block, self::FAQ ) ) );
		}
		return $found;
	}

	/**
	 * Name and value of each two-cell row in a Specifications table, in
	 * page order. Rows with more cells (a compatibility or ordering table)
	 * and header rows are left out.
	 *
	 * @param array<mixed> $blocks Parsed blocks.
	 * @return list<array{name: string, value: string}>
	 */
	public static function properties( array $blocks ): array {
		$found = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( 'core/table' === ( $block['blockName'] ?? '' ) && self::has_class( $block, self::SPECS ) ) {
				$html = self::string( $block['innerHTML'] ?? '' );
				$html = (string) preg_replace( '#<thead\b.*?</thead>#is', '', $html );
				preg_match_all( '#<tr\b[^>]*>(.*?)</tr>#is', $html, $rows );
				foreach ( $rows[1] as $row ) {
					preg_match_all( '#<t[dh]\b[^>]*>(.*?)</t[dh]>#is', $row, $cells );
					if ( 2 !== count( $cells[1] ) ) {
						continue;
					}
					$name  = self::plain( $cells[1][0] );
					$value = self::plain( $cells[1][1] );
					if ( '' !== $name && '' !== $value ) {
						$found[] = array(
							'name'  => $name,
							'value' => $value,
						);
					}
				}
				continue;
			}
			$found = array_merge( $found, self::properties( self::inner( $block ) ) );
		}
		return $found;
	}

	/**
	 * FAQPage node, or an empty array without questions.
	 *
	 * @param list<array{question: string, answer: string}> $questions From questions().
	 * @param string                                        $url       Page URL.
	 * @param string                                        $language  Language tag.
	 * @return array<string, mixed>
	 */
	public static function faq_page( array $questions, string $url, string $language ): array {
		if ( ! $questions || '' === $url ) {
			return array();
		}
		$entities = array();
		foreach ( $questions as $item ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $item['question'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $item['answer'],
				),
			);
		}
		return array_filter(
			array(
				'@type'      => 'FAQPage',
				'@id'        => $url . '#faq',
				'url'        => $url,
				'mainEntity' => $entities,
				'inLanguage' => $language,
			),
			static fn ( $value ): bool => '' !== $value
		);
	}

	/**
	 * PropertyValue nodes for a Product's additionalProperty.
	 *
	 * @param list<array{name: string, value: string}> $properties From properties().
	 * @return list<array{'@type': string, name: string, value: string}>
	 */
	public static function property_values( array $properties ): array {
		$values = array();
		foreach ( $properties as $property ) {
			$values[] = array(
				'@type' => 'PropertyValue',
				'name'  => $property['name'],
				'value' => $property['value'],
			);
		}
		return $values;
	}

	/**
	 * Whether a graph already has a node of this type at its top level (a
	 * plugin's own FAQ block, say).
	 *
	 * @param array<mixed> $graph Graph nodes, a list or a map.
	 * @param string       $type  Schema type.
	 */
	public static function has_type( array $graph, string $type ): bool {
		foreach ( $graph as $node ) {
			if ( is_array( $node ) && in_array( $type, (array) ( $node['@type'] ?? array() ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Adds the properties to every Product node of a graph, or to the node
	 * itself, unless the node has its own.
	 *
	 * @param mixed                                                     $data   A node, or a list or map of nodes.
	 * @param list<array{'@type': string, name: string, value: string}> $values From property_values().
	 */
	public static function add_properties( mixed $data, array $values ): mixed {
		if ( ! is_array( $data ) || ! $values ) {
			return $data;
		}
		if ( isset( $data['@type'] ) ) {
			if ( in_array( 'Product', (array) $data['@type'], true ) && empty( $data['additionalProperty'] ) ) {
				$data['additionalProperty'] = $values;
			}
			return $data;
		}
		foreach ( $data as $key => $node ) {
			$data[ $key ] = self::add_properties( $node, $values );
		}
		return $data;
	}

	/**
	 * The block's markup with its inner blocks in place.
	 *
	 * @param mixed $block Parsed block.
	 */
	private static function html( mixed $block ): string {
		if ( ! is_array( $block ) ) {
			return '';
		}
		$inner = self::inner( $block );
		$html  = '';
		$index = 0;
		foreach ( (array) ( $block['innerContent'] ?? array() ) as $chunk ) {
			$html .= is_string( $chunk ) ? $chunk : self::html( $inner[ $index++ ] ?? null );
		}
		return $html;
	}

	/**
	 * Plain text of a piece of markup, on one line.
	 *
	 * @param string $html Markup.
	 */
	private static function plain( string $html ): string {
		$text = strip_tags( (string) preg_replace( self::BREAKS, '$1 ', $html ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure PHP; the text is escaped again where it is printed.
		return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}

	/**
	 * Whether a block is the heading of a question card: level 3 or below,
	 * so the FAQ's own h2 title is not read as a question.
	 *
	 * @param mixed $block Parsed block.
	 */
	private static function is_question( mixed $block ): bool {
		if ( ! is_array( $block ) || 'core/heading' !== ( $block['blockName'] ?? '' ) ) {
			return false;
		}
		$level = is_array( $block['attrs'] ?? null ) ? ( $block['attrs']['level'] ?? 2 ) : 2;
		return is_int( $level ) && $level >= 3;
	}

	/**
	 * Inner blocks of a block.
	 *
	 * @param array<mixed> $block Parsed block.
	 * @return array<mixed>
	 */
	private static function inner( array $block ): array {
		return is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array();
	}

	/**
	 * Whether the block's className holds a class.
	 *
	 * @param array<mixed> $block Parsed block.
	 * @param string       $name  Class.
	 */
	private static function has_class( array $block, string $name ): bool {
		$class = is_array( $block['attrs'] ?? null ) ? ( $block['attrs']['className'] ?? '' ) : '';
		return is_string( $class ) && in_array( $name, (array) preg_split( '/\s+/', $class ), true );
	}

	/**
	 * A value as a string.
	 *
	 * @param mixed $value Value.
	 */
	private static function string( mixed $value ): string {
		return is_string( $value ) ? $value : '';
	}
}
