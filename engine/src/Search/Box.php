<?php
/**
 * Live suggestions on the core Search block.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Search;

use Pergel\Settings\Settings;

use const Pergel\FILE;
use const Pergel\VERSION;

/**
 * Turns every Search block into a combobox: while the visitor types, a
 * list under the field shows the matching categories, the first results
 * and a link to all of them. The block keeps its markup and works as a
 * plain form without JavaScript; the theme's header, footer and 404
 * patterns need no change. A Search block limited to products (the
 * WooCommerce product search) suggests products only.
 *
 * The view script is a small script module without Interactivity API
 * directives: the core block already runs its own store on the form.
 * Texts travel in a data attribute, translated on the server. The script
 * and its CSS load only on pages that show a Search block.
 */
final class Box {

	public const STYLE = 'pergel-search';

	public const SCRIPT = 'pergel/search';

	/**
	 * Hooks the suggestions in.
	 */
	public static function register(): void {
		add_filter( 'render_block_core/search', array( self::class, 'render' ), 10, 2 );
		add_action( 'init', array( self::class, 'register_assets' ) );
	}

	/**
	 * Registers the CSS (core inlines it, it is small) and the script module.
	 * Hooked on init.
	 */
	public static function register_assets(): void {
		wp_register_style( self::STYLE, plugins_url( 'assets/search.css', FILE ), array(), VERSION );
		wp_style_add_data( self::STYLE, 'path', dirname( FILE ) . '/assets/search.css' );
		wp_register_script_module( self::SCRIPT, plugins_url( 'assets/search.js', FILE ), array(), VERSION, array( 'fetchpriority' => 'low' ) );
	}

	/**
	 * Adds the combobox attributes, the empty list and the status line.
	 *
	 * @param mixed $content Rendered block.
	 * @param mixed $block   Parsed block.
	 * @return mixed
	 */
	public static function render( mixed $content, mixed $block ): mixed {
		if ( ! is_string( $content ) || ! str_contains( $content, '</form>' ) || true !== Settings::get( 'search.live' ) ) {
			return $content;
		}
		$attrs = is_array( $block ) && is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$type  = 'product' === ( $attrs['query']['post_type'] ?? '' ) ? 'product' : '';
		$id    = wp_unique_id( 'pergel-search-' );

		$tags = new \WP_HTML_Tag_Processor( $content );
		if ( ! $tags->next_tag( array( 'tag_name' => 'form' ) ) ) {
			return $content;
		}
		$tags->set_attribute( 'data-pergel-search', (string) wp_json_encode( self::config( $type ) ) );
		if ( ! $tags->next_tag(
			array(
				'tag_name'   => 'input',
				'class_name' => 'wp-block-search__input',
			)
		) ) {
			return $content;
		}
		$tags->set_attribute( 'role', 'combobox' );
		$tags->set_attribute( 'aria-autocomplete', 'list' );
		$tags->set_attribute( 'aria-expanded', 'false' );
		$tags->set_attribute( 'aria-controls', $id );
		$tags->set_attribute( 'autocomplete', 'off' );

		$html = $tags->get_updated_html();
		$at   = strrpos( $html, '</form>' );
		if ( false === $at ) {
			return $content;
		}
		$label = 'product' === $type ? __( 'Product suggestions', 'pergel-engine' ) : __( 'Search suggestions', 'pergel-engine' );
		$panel = sprintf(
			'<div class="pergel-search__panel" hidden><div class="pergel-search__list" id="%1$s" role="listbox" aria-label="%2$s"></div></div><div class="pergel-search__status screen-reader-text" role="status"></div>',
			esc_attr( $id ),
			esc_attr( $label )
		);

		wp_enqueue_style( self::STYLE );
		wp_enqueue_script_module( self::SCRIPT );
		return substr( $html, 0, $at ) . $panel . substr( $html, $at );
	}

	/**
	 * What the view script needs: the endpoint, the search type and its
	 * texts.
	 *
	 * @param string $type 'product' or ''.
	 * @return array<string, mixed>
	 */
	public static function config( string $type ): array {
		return array(
			'endpoint' => rest_url( Suggest::NAMESPACE . '/' . Suggest::ROUTE ),
			'type'     => $type,
			'text'     => array(
				'terms'     => __( 'Categories', 'pergel-engine' ),
				'results'   => 'product' === $type ? __( 'Products', 'pergel-engine' ) : __( 'Results', 'pergel-engine' ),
				// The script picks the "one" text for a count of 1; plural rules beyond that are not known in the browser.
				'allOne'    => __( 'See the result on the search page', 'pergel-engine' ),
				/* translators: %d: number of results, more than one. */
				'all'       => __( 'See all %d results', 'pergel-engine' ),
				/* translators: %s: search text. */
				'none'      => __( 'Nothing found for “%s”.', 'pergel-engine' ),
				'statusOne' => __( 'One suggestion. Use the down arrow key to reach it.', 'pergel-engine' ),
				/* translators: %d: number of suggestions, more than one. */
				'status'    => __( '%d suggestions. Use the up and down arrow keys to browse them.', 'pergel-engine' ),
			),
		);
	}
}
