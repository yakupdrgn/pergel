<?php
/**
 * The table of contents block and heading anchors.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Blocks;

/**
 * Lists the headings of the post's content as links. The headings get the
 * matching ids when they render: a heading with its own HTML anchor keeps
 * it, the others get one made from their text. On singular pages every
 * heading of the post's content gets an id, so the block works wherever it
 * sits in the template (before or after the content) and sections can be
 * linked to without the block.
 */
final class Toc {

	public const MAX_LEVEL = 3;

	/**
	 * Headings per post id.
	 *
	 * @var array<int, list<array{level: int, text: string, id: string}>>
	 */
	private static array $headings = array();

	/**
	 * Ids already given out per post id and heading key, since the content
	 * last started to render.
	 *
	 * @var array<int, array<string, int>>
	 */
	private static array $given = array();

	/**
	 * Hooks the heading anchors in.
	 */
	public static function register(): void {
		add_filter( 'render_block_core/heading', array( self::class, 'anchor' ), 10, 1 );
		add_filter( 'the_content', array( self::class, 'restart' ), 8, 1 );
	}

	/**
	 * Block markup; empty when the post has fewer than two headings.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param \WP_Block|null       $block      Block instance.
	 */
	public static function render( array $attributes, ?\WP_Block $block = null ): string {
		$post_id = $block instanceof \WP_Block && is_int( $block->context['postId'] ?? null ) ? $block->context['postId'] : (int) get_the_ID();
		$post    = get_post( $post_id );
		$max     = is_int( $attributes['maxLevel'] ?? null ) ? max( 2, min( 6, $attributes['maxLevel'] ) ) : self::MAX_LEVEL;
		$items   = array();
		foreach ( $post instanceof \WP_Post ? self::headings( $post ) : array() as $heading ) {
			if ( $heading['level'] >= 2 && $heading['level'] <= $max ) {
				$items[] = $heading;
			}
		}
		if ( count( $items ) < 2 ) {
			return wp_is_serving_rest_request()
				? '<div ' . get_block_wrapper_attributes() . '><p>' . esc_html__( 'The headings of the content are listed here once there are at least two.', 'pergel-engine' ) . '</p></div>'
				: '';
		}
		$label = wp_unique_id( 'pergel-toc-' );
		return '<nav ' . get_block_wrapper_attributes( array( 'aria-labelledby' => $label ) ) . '>'
			. '<p class="wp-block-pergel-table-of-contents__title" id="' . esc_attr( $label ) . '">' . esc_html__( 'On this page', 'pergel-engine' ) . '</p>'
			. self::nest( $items )
			. '</nav>';
	}

	/**
	 * Nested ordered lists of links; a deeper heading opens a list inside
	 * the item before it.
	 *
	 * @param list<array{level: int, text: string, id: string}> $items Headings in order.
	 */
	public static function nest( array $items ): string {
		$out   = '<ol>';
		$stack = array();
		foreach ( $items as $item ) {
			if ( array() !== $stack ) {
				if ( $item['level'] > end( $stack ) ) {
					$out .= '<ol>';
				} else {
					$out .= '</li>';
					array_pop( $stack );
					while ( array() !== $stack && end( $stack ) >= $item['level'] ) {
						$out .= '</ol></li>';
						array_pop( $stack );
					}
				}
			}
			$out    .= '<li><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a>';
			$stack[] = $item['level'];
		}
		if ( array() !== $stack ) {
			$out .= '</li>';
			array_pop( $stack );
		}
		$out .= str_repeat( '</ol></li>', count( $stack ) );
		return $out . '</ol>';
	}

	/**
	 * Headings of a post's content with their ids, in order.
	 *
	 * @param \WP_Post $post Post.
	 * @return list<array{level: int, text: string, id: string}>
	 */
	public static function headings( \WP_Post $post ): array {
		if ( isset( self::$headings[ $post->ID ] ) ) {
			return self::$headings[ $post->ID ];
		}
		$found = array();
		self::collect( parse_blocks( $post->post_content ), $found );
		$taken = array();
		foreach ( $found as $heading ) {
			if ( '' !== $heading['id'] ) {
				$taken[ $heading['id'] ] = true;
			}
		}
		$list = array();
		foreach ( $found as $heading ) {
			if ( '' === $heading['id'] ) {
				$base = sanitize_title( $heading['text'] );
				$base = '' === $base ? 'section' : $base;
				$id   = $base;
				for ( $n = 2; isset( $taken[ $id ] ); $n++ ) {
					$id = $base . '-' . $n;
				}
				$taken[ $id ]  = true;
				$heading['id'] = $id;
			}
			$list[] = $heading;
		}
		self::$headings[ $post->ID ] = $list;
		return $list;
	}

	/**
	 * Gives a heading of the current post's content its id. Hooked on the
	 * heading block's render.
	 *
	 * @param mixed $content Rendered heading.
	 * @return mixed
	 */
	public static function anchor( mixed $content ): mixed {
		if ( ! is_string( $content ) || ! is_singular() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post || get_queried_object_id() !== $post->ID ) {
			return $content;
		}
		$tags = new \WP_HTML_Tag_Processor( $content );
		if ( ! $tags->next_tag() || 1 !== preg_match( '/^H([1-6])$/', (string) $tags->get_tag(), $level ) || null !== $tags->get_attribute( 'id' ) ) {
			return $content;
		}
		$key   = $level[1] . '|' . self::text( $content );
		$count = self::$given[ $post->ID ][ $key ] ?? 0;
		$match = 0;
		foreach ( self::headings( $post ) as $heading ) {
			if ( $heading['level'] . '|' . $heading['text'] === $key && $match++ === $count ) {
				self::$given[ $post->ID ][ $key ] = $count + 1;
				$tags->set_attribute( 'id', $heading['id'] );
				return $tags->get_updated_html();
			}
		}
		return $content;
	}

	/**
	 * Starts giving out ids from the first heading again. The content can
	 * render more than once on a page (an excerpt for the meta description
	 * renders its headings first); hooked on the_content before do_blocks.
	 *
	 * @param mixed $content Post content.
	 * @return mixed
	 */
	public static function restart( mixed $content ): mixed {
		self::$given = array();
		return $content;
	}

	/**
	 * Forgets the headings. For tests.
	 */
	public static function flush(): void {
		self::$headings = array();
		self::$given    = array();
	}

	/**
	 * Walks parsed blocks for headings.
	 *
	 * @param array<mixed>                                      $blocks Parsed blocks.
	 * @param list<array{level: int, text: string, id: string}> $found  Headings found so far.
	 */
	private static function collect( array $blocks, array &$found ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( 'core/heading' === ( $block['blockName'] ?? null ) && is_string( $block['innerHTML'] ?? null ) ) {
				$tags = new \WP_HTML_Tag_Processor( $block['innerHTML'] );
				$text = self::text( $block['innerHTML'] );
				if ( '' !== $text && $tags->next_tag() && 1 === preg_match( '/^H([1-6])$/', (string) $tags->get_tag(), $level ) ) {
					$id      = $tags->get_attribute( 'id' );
					$found[] = array(
						'level' => (int) $level[1],
						'text'  => $text,
						'id'    => is_string( $id ) ? $id : '',
					);
				}
			}
			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				self::collect( $block['innerBlocks'], $found );
			}
		}
	}

	/**
	 * A heading's text without tags or entities.
	 *
	 * @param string $html Heading markup.
	 */
	private static function text( string $html ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	}
}
