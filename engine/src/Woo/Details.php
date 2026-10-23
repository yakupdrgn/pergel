<?php
/**
 * The sections under the product from the settings.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * Applies the "Under the product" settings to the accordion of the
 * product details block (description, specifications, reviews), while the
 * template renders; the template stays a plain block template (K10):
 *
 * - extra sections in the order Description · Specifications ·
 *   Additional information · the product's own tab · the two pattern
 *   tabs · Questions · Reviews; tabs other plugins add through
 *   woocommerce_product_tabs still come after them (WooCommerce adds those);
 * - with the feature list on, a product's own list shows as
 *   "Specifications" and the attribute table as "Additional information";
 * - "Tabs": one section open at a time (the accordion's own autoclose) and a
 *   row of tabs that opens them; on phones the sections stay one under the
 *   other, or the row scrolls sideways. "All open": every section open;
 * - the rating summary above the reviews.
 *
 * Layout loads this class only on product pages where one of these
 * settings differs from its default, or whose product has a feature list
 * or tab of its own (DetailsFields).
 */
final class Details {

	/**
	 * The product on screen.
	 *
	 * @var int
	 */
	private static int $product = 0;

	/**
	 * Adds the hooks and the CSS. Called on wp.
	 *
	 * @param int $product The product on screen.
	 */
	public static function register( int $product ): void {
		self::$product = $product;
		add_filter( 'render_block_data', array( self::class, 'sections' ) );
		add_filter( 'render_block_woocommerce/product-details', array( self::class, 'tabs' ) );
		add_filter( 'render_block_woocommerce/product-reviews', array( self::class, 'rating' ), 10, 3 );
		wp_enqueue_style( Layout::DETAILS_STYLE );
	}

	/**
	 * The extra sections and how they open, in the details block before
	 * it renders.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	public static function sections( array $block ): array {
		if ( 'woocommerce/product-details' !== ( $block['blockName'] ?? '' ) || get_queried_object_id() !== self::$product ) {
			return $block;
		}
		$product = wc_get_product( self::$product );
		return $product instanceof \WC_Product ? self::find_accordion( $block, $product ) : $block;
	}

	/**
	 * Changes the first accordion inside a block.
	 *
	 * @param array<string, mixed> $block   Parsed block.
	 * @param \WC_Product          $product The product on screen.
	 * @return array<string, mixed>
	 */
	private static function find_accordion( array $block, \WC_Product $product ): array {
		if ( 'core/accordion' === ( $block['blockName'] ?? '' ) ) {
			return self::accordion( $block, $product );
		}
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $index => $inner ) {
			$changed = self::find_accordion( $inner, $product );
			if ( $changed !== $inner ) {
				$block['innerBlocks'][ $index ] = $changed;
				return $block;
			}
		}
		return $block;
	}

	/**
	 * Adds the sections to the accordion and sets how they open.
	 *
	 * @param array<string, mixed> $accordion Parsed accordion.
	 * @param \WC_Product          $product   The product on screen.
	 * @return array<string, mixed>
	 */
	public static function accordion( array $accordion, \WC_Product $product ): array {
		$items  = array_values( array_filter( (array) ( $accordion['innerBlocks'] ?? array() ), static fn ( mixed $item ): bool => is_array( $item ) && 'core/accordion-item' === ( $item['blockName'] ?? '' ) ) );
		$anchor = $items[0] ?? null;
		if ( null === $anchor ) {
			return $accordion;
		}
		$own   = self::own( $product->get_id() );
		$specs = self::holding( $items, 'woocommerce/product-specifications' );
		if ( true === Layout::get( 'shop.details_features' ) && array() !== $own['features'] && null !== $specs ) {
			$list = '';
			foreach ( $own['features'] as $feature ) {
				$list .= '<li>' . esc_html( $feature ) . '</li>';
			}
			$items[ $specs ] = self::retitle( $items[ $specs ], __( 'Additional information', 'pergel-engine' ) );
			array_splice( $items, $specs, 0, array( self::item( $anchor, __( 'Specifications', 'pergel-engine' ), self::html( '<ul class="pergel-features">' . $list . '</ul>' ) ) ) );
		}

		$extra = array();
		if ( '' !== $own['tab_name'] && '' !== $own['tab'] ) {
			$extra[] = self::item( $anchor, $own['tab_name'], self::html( wpautop( $own['tab'] ) ) );
		}
		foreach ( array(
			'details_tab'  => 'details_title',
			'details_tab2' => 'details_title2',
		) as $pattern => $name ) {
			$title = Layout::get( "shop.$name" );
			$id    = Layout::get( "shop.$pattern" );
			$post  = is_int( $id ) && $id > 0 ? get_post( $id ) : null;
			if ( is_string( $title ) && '' !== trim( $title ) && $post instanceof \WP_Post && 'wp_block' === $post->post_type && 'publish' === $post->post_status ) {
				$extra[] = self::item( $anchor, trim( $title ), '<!-- wp:block {"ref":' . $post->ID . '} /-->' );
			}
		}
		$questions = null;
		if ( true === Layout::get( 'shop.details_qa' ) ) {
			[ $title, $html ] = Questions::section( $product );
			$questions        = self::item( $anchor, $title, self::html( $html ) );
			$extra[]          = $questions;
		}
		$reviews = self::holding( $items, 'woocommerce/product-reviews' );
		array_splice( $items, $reviews ?? count( $items ), 0, $extra );

		$style = Layout::get( 'shop.details_style' );
		if ( count( $items ) === count( (array) $accordion['innerBlocks'] ) && 'tabs' !== $style && 'open' !== $style ) {
			return $accordion;
		}
		// Back from sending a question: its section open, so the result shows.
		$asked = null !== $questions && Questions::answering() ? array_search( $questions, $items, true ) : false;
		if ( 'tabs' === $style || 'open' === $style ) {
			$accordion['attrs']['autoclose'] = 'tabs' === $style;
			foreach ( $items as $index => $item ) {
				$items[ $index ] = self::open( $item, 'open' === $style || ( false === $asked ? 0 === $index : $asked === $index ) );
			}
		} elseif ( is_int( $asked ) ) {
			$items[ $asked ] = self::open( $items[ $asked ], true );
		}

		$content                   = is_array( $accordion['innerContent'] ?? null ) ? $accordion['innerContent'] : array();
		$first                     = is_string( reset( $content ) ) ? reset( $content ) : '';
		$last                      = is_string( end( $content ) ) && count( $content ) > 1 ? end( $content ) : '';
		$accordion['innerBlocks']  = $items;
		$accordion['innerContent'] = array_merge( array( $first ), self::nulls( count( $items ) ), array( $last ) );
		return $accordion;
	}

	/**
	 * Inner content slots for a number of inner blocks, with the blank line
	 * the editor puts between them.
	 *
	 * @param int $count Inner blocks.
	 * @return list<string|null>
	 */
	private static function nulls( int $count ): array {
		$slots = array();
		for ( $i = 0; $i < $count; $i++ ) {
			if ( $i > 0 ) {
				$slots[] = "\n\n";
			}
			$slots[] = null;
		}
		return $slots;
	}

	/**
	 * The index of the item whose panel holds a block, if any.
	 *
	 * @param list<array<string, mixed>> $items Accordion items.
	 * @param string                     $name  Block name.
	 */
	private static function holding( array $items, string $name ): ?int {
		foreach ( $items as $index => $item ) {
			if ( self::holds( $item, $name ) ) {
				return $index;
			}
		}
		return null;
	}

	/**
	 * Whether a block or one inside it has a name.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @param string               $name  Block name.
	 */
	private static function holds( array $block, string $name ): bool {
		if ( ( $block['blockName'] ?? '' ) === $name ) {
			return true;
		}
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner ) {
			if ( is_array( $inner ) && self::holds( $inner, $name ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * HTML as the markup of an HTML block.
	 *
	 * @param string $html HTML.
	 */
	private static function html( string $html ): string {
		// A comment would end the HTML block early.
		return '<!-- wp:html -->' . str_replace( '<!--', '&lt;!--', $html ) . '<!-- /wp:html -->';
	}

	/**
	 * A new section like the first one of the template: same heading level
	 * and look, another title and content.
	 *
	 * @param array<string, mixed> $anchor The first accordion item.
	 * @param string               $title  Plain text title.
	 * @param string               $markup Content as block markup.
	 * @return array<string, mixed>
	 */
	private static function item( array $anchor, string $title, string $markup ): array {
		$item          = self::retitle( $anchor, $title );
		$item['attrs'] = array_diff_key( is_array( $item['attrs'] ?? null ) ? $item['attrs'] : array(), array_flip( array( 'anchor', 'className', 'metadata' ) ) );
		$item          = self::open( $item, false );
		foreach ( $item['innerBlocks'] as $index => $inner ) {
			if ( 'core/accordion-panel' !== ( $inner['blockName'] ?? '' ) ) {
				continue;
			}
			$blocks                        = array_values( array_filter( parse_blocks( $markup ), static fn ( array $block ): bool => null !== $block['blockName'] ) );
			$content                       = is_array( $inner['innerContent'] ?? null ) ? $inner['innerContent'] : array();
			$first                         = is_string( reset( $content ) ) ? reset( $content ) : '<div class="wp-block-accordion-panel">';
			$last                          = is_string( end( $content ) ) && count( $content ) > 1 ? end( $content ) : '</div>';
			$item['innerBlocks'][ $index ] = array_merge(
				$inner,
				array(
					'innerBlocks'  => $blocks,
					'innerHTML'    => $first . $last,
					'innerContent' => array_merge( array( $first ), self::nulls( count( $blocks ) ), array( $last ) ),
				)
			);
		}
		return $item;
	}

	/**
	 * An accordion item with another title in its heading.
	 *
	 * @param array<string, mixed> $item  Accordion item.
	 * @param string               $title Plain text title.
	 * @return array<string, mixed>
	 */
	private static function retitle( array $item, string $title ): array {
		foreach ( (array) ( $item['innerBlocks'] ?? array() ) as $index => $inner ) {
			if ( 'core/accordion-heading' !== ( $inner['blockName'] ?? '' ) ) {
				continue;
			}
			$swap                          = static fn ( string $html ): string => (string) preg_replace_callback(
				'#(<span class="wp-block-accordion-heading__toggle-title"[^>]*>).*?(</span>)#s',
				static fn ( array $parts ): string => $parts[1] . esc_html( $title ) . $parts[2],
				$html,
				1
			);
			$inner['innerHTML']            = $swap( (string) ( $inner['innerHTML'] ?? '' ) );
			$inner['innerContent']         = array_map( static fn ( mixed $part ): mixed => is_string( $part ) ? $swap( $part ) : $part, (array) ( $inner['innerContent'] ?? array() ) );
			$item['innerBlocks'][ $index ] = $inner;
		}
		return $item;
	}

	/**
	 * An accordion item open or closed when the page loads; also the
	 * class its saved markup carries.
	 *
	 * @param array<string, mixed> $item Accordion item.
	 * @param bool                 $open Open.
	 * @return array<string, mixed>
	 */
	private static function open( array $item, bool $open ): array {
		$item['attrs']['openByDefault'] = $open;
		$mark                           = static function ( string $html ) use ( $open ): string {
			$tags = new \WP_HTML_Tag_Processor( $html );
			if ( ! $tags->next_tag() ) {
				return $html;
			}
			if ( $open ) {
				$tags->add_class( 'is-open' );
			} else {
				$tags->remove_class( 'is-open' );
			}
			return $tags->get_updated_html();
		};
		$content                        = is_array( $item['innerContent'] ?? null ) ? $item['innerContent'] : array();
		if ( is_string( $content[0] ?? null ) ) {
			$content[0]           = $mark( $content[0] );
			$item['innerContent'] = $content;
		}
		$item['innerHTML'] = $mark( (string) ( $item['innerHTML'] ?? '' ) );
		return $item;
	}

	/**
	 * A product's own feature list and tab, cleaned.
	 *
	 * @param int $product Product id.
	 * @return array{features: list<string>, tab_name: string, tab: string}
	 */
	public static function own( int $product ): array {
		return DetailsFields::clean( get_post_meta( $product, Layout::DETAILS_META, true ) );
	}

	/**
	 * "Tabs": the row of tabs over the sections, made from the rendered
	 * accordion so that tabs other plugins add are in it too, and the
	 * first section open when the template's first one was left out.
	 *
	 * @param string $html Rendered details block.
	 */
	public static function tabs( string $html ): string {
		if ( 'tabs' !== Layout::get( 'shop.details_style' ) || get_queried_object_id() !== self::$product ) {
			return $html;
		}
		$sections = self::headings( $html );
		if ( count( $sections ) < 2 ) {
			return $html;
		}
		$open = array_filter( $sections, static fn ( array $section ): bool => $section['open'] );
		if ( array() === $open ) {
			$html                = self::open_first( $html, $sections[0] );
			$sections[0]['open'] = true;
		}
		$buttons = '';
		foreach ( $sections as $section ) {
			$buttons .= sprintf(
				'<button type="button" role="tab" class="pergel-details-tab" id="%1$s-tab" aria-controls="%2$s" aria-selected="%3$s" tabindex="%4$s" data-pergel-toggle="%1$s">%5$s</button>',
				esc_attr( $section['id'] ),
				esc_attr( $section['panel'] ),
				$section['open'] ? 'true' : 'false',
				$section['open'] ? '0' : '-1',
				esc_html( $section['title'] )
			);
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag() ) {
			return $html;
		}
		$tags->add_class( 'pergel-details' );
		$tags->add_class( 'is-tabs' );
		if ( 'scroll' === Layout::get( 'shop.details_phone' ) ) {
			$tags->add_class( 'is-phone-scroll' );
		}
		$html = $tags->get_updated_html();
		$end  = strpos( $html, '>' );
		if ( false === $end ) {
			return $html;
		}
		wp_enqueue_script_module( Layout::DETAILS_SCRIPT );
		$strip = sprintf( '<div class="pergel-details-tabs" role="tablist" aria-label="%1$s">%2$s</div>', esc_attr__( 'Product details', 'pergel-engine' ), $buttons );
		return substr( $html, 0, $end + 1 ) . $strip . substr( $html, $end + 1 );
	}

	/**
	 * The top level sections of the rendered accordion: the heading
	 * button's id, its panel, title and whether it is open. Empty when the
	 * HTML cannot be read.
	 *
	 * @param string $html Rendered details block.
	 * @return list<array{id: string, panel: string, title: string, open: bool}>
	 */
	public static function headings( string $html ): array {
		$parser = \WP_HTML_Processor::create_fragment( $html );
		if ( null === $parser ) {
			return array();
		}
		$sections = array();
		$top      = null;
		while ( $parser->next_tag() ) {
			if ( 'BUTTON' !== $parser->get_tag() || ! $parser->has_class( 'wp-block-accordion-heading__toggle' ) ) {
				continue;
			}
			$depth = $parser->get_current_depth();
			$top ??= $depth;
			$id    = $parser->get_attribute( 'id' );
			$panel = $parser->get_attribute( 'aria-controls' );
			$open  = 'true' === $parser->get_attribute( 'aria-expanded' );
			if ( $depth !== $top || ! is_string( $id ) || ! is_string( $panel ) ) {
				continue;
			}
			$sections[] = array(
				'id'    => $id,
				'panel' => $panel,
				'title' => self::title( $parser ),
				'open'  => $open,
			);
		}
		return null === $parser->get_last_error() ? $sections : array();
	}

	/**
	 * The text of a heading button's title span, read up to the end of the
	 * button; the icon beside the title is left out.
	 *
	 * @param \WP_HTML_Processor $parser Parser on the button.
	 */
	private static function title( \WP_HTML_Processor $parser ): string {
		$title = '';
		$in    = false;
		while ( $parser->next_token() ) {
			$tag = $parser->get_tag();
			if ( 'BUTTON' === $tag && $parser->is_tag_closer() ) {
				break;
			}
			if ( 'SPAN' === $tag ) {
				$in = ! $parser->is_tag_closer() && true === $parser->has_class( 'wp-block-accordion-heading__toggle-title' );
			} elseif ( $in && '#text' === $parser->get_token_type() ) {
				$title .= $parser->get_modifiable_text();
			}
		}
		return trim( (string) preg_replace( '/\s+/', ' ', $title ) );
	}

	/**
	 * Opens a section in the rendered HTML: its item, button and panel,
	 * and the open state the accordion script starts from.
	 *
	 * @param string                                                      $html    Rendered details block.
	 * @param array{id: string, panel: string, title: string, open: bool} $section The section.
	 */
	private static function open_first( string $html, array $section ): string {
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag() ) {
			$context = $tags->get_attribute( 'data-wp-context' );
			if ( $tags->has_class( 'wp-block-accordion-item' ) && is_string( $context ) && str_contains( $context, '"' . $section['id'] . '"' ) ) {
				$tags->set_attribute( 'data-wp-context', (string) preg_replace( '/"openByDefault":\s*false/', '"openByDefault": true', $context ) );
				$tags->add_class( 'is-open' );
			} elseif ( $section['id'] === $tags->get_attribute( 'id' ) ) {
				$tags->set_attribute( 'aria-expanded', 'true' );
			} elseif ( $section['panel'] === $tags->get_attribute( 'id' ) ) {
				$tags->remove_attribute( 'hidden' );
				break;
			}
		}
		return $tags->get_updated_html();
	}

	/**
	 * The average rating and a bar for each number of stars, above the
	 * reviews of the product on screen.
	 *
	 * @param string    $html     Rendered reviews.
	 * @param array     $block    Parsed block.
	 * @param \WP_Block $instance Block instance.
	 * @phpstan-param array<string, mixed> $block
	 */
	public static function rating( string $html, array $block, \WP_Block $instance ): string {
		if ( true !== Layout::get( 'shop.details_rating' ) || (int) ( $instance->context['postId'] ?? 0 ) !== self::$product ) {
			return $html;
		}
		$product = wc_get_product( self::$product );
		$total   = $product instanceof \WC_Product ? $product->get_review_count() : 0;
		if ( ! $product instanceof \WC_Product || $total < 1 || ! wc_review_ratings_enabled() ) {
			return $html;
		}
		$counts  = $product->get_rating_counts();
		$average = (float) $product->get_average_rating();
		$rated   = max( 1, array_sum( array_map( 'intval', $counts ) ) );
		$bars    = '';
		for ( $stars = 5; $stars >= 1; $stars-- ) {
			$count = (int) ( $counts[ $stars ] ?? 0 );
			$bars .= sprintf(
				'<li class="pergel-rating-summary__row"><span class="pergel-rating-summary__stars">%1$s</span><span class="pergel-rating-summary__bar" style="--pergel-share:%2$s%%" aria-hidden="true"></span><span class="pergel-rating-summary__count">%3$s</span></li>',
				/* translators: %d: number of stars, 1 to 5. */
				esc_html( sprintf( _n( '%d star', '%d stars', $stars, 'pergel-engine' ), $stars ) ),
				esc_attr( (string) round( 100 * $count / $rated ) ),
				esc_html( number_format_i18n( $count ) )
			);
		}
		$summary = sprintf(
			'<div class="pergel-rating-summary"><p class="pergel-rating-summary__average"><span class="pergel-rating-summary__number">%1$s</span> <span class="screen-reader-text">%2$s</span><span class="pergel-rating-summary__total">%3$s</span></p><ul class="pergel-rating-summary__rows">%4$s</ul></div>',
			esc_html( number_format_i18n( $average, 1 ) ),
			esc_html__( 'out of 5', 'pergel-engine' ),
			/* translators: %s: number of reviews. */
			esc_html( sprintf( _n( 'Based on %s review', 'Based on %s reviews', $total, 'pergel-engine' ), number_format_i18n( $total ) ) ),
			$bars
		);
		$end = strpos( $html, '>' );
		return false === $end ? $summary . $html : substr( $html, 0, $end + 1 ) . $summary . substr( $html, $end + 1 );
	}
}
