<?php
/**
 * Filter page rows in the product category form.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

/**
 * The filter pages of a category (FilterPages), in its edit form: one row
 * per page, and an empty row to add one. Emptying a row's slug or filters
 * removes the page.
 */
final class FilterPagesFields {

	/**
	 * Form field name; rows are posted as name[n][field].
	 */
	private const NAME = 'pergel_filter_pages';

	/**
	 * Adds the admin hooks.
	 */
	public static function register(): void {
		add_action( 'product_cat_edit_form_fields', array( self::class, 'fields' ), 30 );
		add_action( 'edited_product_cat', array( self::class, 'save' ) );
	}

	/**
	 * The rows, under a heading row.
	 *
	 * @param mixed $term Category being edited.
	 */
	public static function fields( mixed $term ): void {
		if ( ! $term instanceof \WP_Term ) {
			return;
		}
		$pages   = FilterPages::pages( $term->term_id );
		$pages[] = array(
			'slug'        => '',
			'filters'     => array(),
			'title'       => '',
			'h1'          => '',
			'description' => '',
			'intro'       => '',
		);
		printf(
			'<tr class="form-field"><th scope="row" colspan="2"><h2>%1$s</h2><p class="description" style="font-weight:400">%2$s</p></th></tr>',
			esc_html__( 'Filter pages', 'pergel-engine' ),
			esc_html__( 'A filter choice that people search for can get a page of its own: an address under this category, a title, a heading and a description for search results. Filter the category on the site, copy the address and paste it under Filters. Other filtered listings are kept out of search results. Empty the slug to remove a page.', 'pergel-engine' )
		);
		foreach ( $pages as $index => $page ) {
			$url = '' === $page['slug'] ? '' : FilterPages::url( $term, $page['slug'] );
			printf(
				'<tr class="form-field"><th scope="row">%1$s</th><td><fieldset>%2$s%3$s%4$s%5$s%6$s%7$s%8$s</fieldset></td></tr>',
				'' === $page['slug'] ? esc_html__( 'New filter page', 'pergel-engine' ) : esc_html( '' !== $page['h1'] ? $page['h1'] : $page['slug'] ),
				self::input( $index, 'slug', __( 'Slug', 'pergel-engine' ), $page['slug'], 'regular-text', 'sbs-format' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in input().
				self::input( $index, 'filters', __( 'Filters', 'pergel-engine' ), build_query( $page['filters'] ), 'large-text code', 'filter_features=automation-sbs' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in input().
				self::input( $index, 'title', __( 'Title in search results', 'pergel-engine' ), $page['title'], 'large-text', '' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in input().
				self::input( $index, 'h1', __( 'Heading', 'pergel-engine' ), $page['h1'], 'large-text', '' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in input().
				self::input( $index, 'description', __( 'Meta description', 'pergel-engine' ), $page['description'], 'large-text', '' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in input().
				self::textarea( $index, $page['intro'] ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in textarea().
				'' === $url ? '' : sprintf( '<p class="description"><a href="%1$s" target="_blank" rel="noopener">%2$s</a></p>', esc_url( $url ), esc_html( $url ) )
			);
		}
	}

	/**
	 * A labelled text box of a row.
	 *
	 * @param int    $index       Row number.
	 * @param string $field       Field name.
	 * @param string $label       Label.
	 * @param string $value       Value.
	 * @param string $classes     Classes of the box.
	 * @param string $placeholder Example value.
	 */
	private static function input( int $index, string $field, string $label, string $value, string $classes, string $placeholder ): string {
		return sprintf(
			'<p><label for="%1$s">%2$s</label><br><input type="text" id="%1$s" name="%3$s" class="%4$s" value="%5$s" placeholder="%6$s"></p>',
			esc_attr( 'pergel-filter-page-' . $index . '-' . $field ),
			esc_html( $label ),
			esc_attr( self::NAME . '[' . $index . '][' . $field . ']' ),
			esc_attr( $classes ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	/**
	 * The intro box of a row.
	 *
	 * @param int    $index Row number.
	 * @param string $value Value.
	 */
	private static function textarea( int $index, string $value ): string {
		return sprintf(
			'<p><label for="%1$s">%2$s</label><br><textarea id="%1$s" name="%3$s" class="large-text" rows="3">%4$s</textarea></p>',
			esc_attr( 'pergel-filter-page-' . $index . '-intro' ),
			esc_html__( 'Intro', 'pergel-engine' ),
			esc_attr( self::NAME . '[' . $index . '][intro]' ),
			esc_textarea( $value )
		);
	}

	/**
	 * Saves the rows. WordPress has checked the form nonce.
	 *
	 * @param int $term_id Category id.
	 */
	public static function save( int $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by WordPress before the term is saved.
		if ( ! isset( $_POST[ self::NAME ] ) || ! is_array( $_POST[ self::NAME ] ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; FilterPages::clean() sanitizes every field.
		$pages = FilterPages::clean( wp_unslash( $_POST[ self::NAME ] ) );
		if ( $pages ) {
			update_term_meta( $term_id, FilterPages::META, $pages );
		} else {
			delete_term_meta( $term_id, FilterPages::META );
		}
	}
}
