<?php
/**
 * Shop layout fields on product categories.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Woo;

use Pergel\Settings\Labels;
use Pergel\Settings\Schema;
use Pergel\Settings\Screen;

/**
 * The settings a product category may choose for itself (schema fields with
 * the "category" level), in the category forms. Each starts at "same as the
 * parent category or the shop"; only real choices are stored.
 *
 * @phpstan-import-type Field from Schema
 */
final class LayoutFields {

	/**
	 * Form field name; values are posted as name["group.key"].
	 */
	private const NAME = 'pergel_shop';

	/**
	 * Adds the admin hooks.
	 */
	public static function register(): void {
		add_action( 'product_cat_add_form_fields', array( self::class, 'category_add' ), 20 );
		add_action( 'product_cat_edit_form_fields', array( self::class, 'category_edit' ), 20 );
		add_action( 'created_product_cat', array( self::class, 'save_category' ) );
		add_action( 'edited_product_cat', array( self::class, 'save_category' ) );
	}

	/**
	 * The fields in the "Add new category" form.
	 */
	public static function category_add(): void {
		foreach ( self::fields() as $path => $field ) {
			printf(
				'<div class="form-field"><label for="%1$s">%2$s</label>%3$s%4$s</div>',
				esc_attr( self::id( $path ) ),
				esc_html( self::label( $path ) ),
				self::control( $path, $field, null ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in control().
				self::help( $path, 'p' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in help().
			);
		}
	}

	/**
	 * The fields in the category edit form, under a heading row.
	 *
	 * @param mixed $term Category being edited.
	 */
	public static function category_edit( mixed $term ): void {
		$own = $term instanceof \WP_Term ? Layout::own( $term->term_id ) : array();
		printf( '<tr class="form-field"><th scope="row" colspan="2"><h2>%s</h2></th></tr>', esc_html__( 'Shop layout', 'pergel-engine' ) );
		foreach ( self::fields() as $path => $field ) {
			printf(
				'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td>%3$s%4$s</td></tr>',
				esc_attr( self::id( $path ) ),
				esc_html( self::label( $path ) ),
				self::control( $path, $field, $own[ $path ] ?? null ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in control().
				self::help( $path, 'p class="description"' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in help().
			);
		}
	}

	/**
	 * Saves a category's own values. WordPress has checked the form nonce.
	 *
	 * @param int $term_id Category id.
	 */
	public static function save_category( int $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked by WordPress before the term is saved.
		if ( ! isset( $_POST[ self::NAME ] ) || ! is_array( $_POST[ self::NAME ] ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; Schema::level_values() checks every value against the schema.
		$values = Schema::level_values( wp_unslash( $_POST[ self::NAME ] ), 'category' );
		if ( $values ) {
			update_term_meta( $term_id, Layout::TERM_META, $values );
		} else {
			delete_term_meta( $term_id, Layout::TERM_META );
		}
	}

	/**
	 * The category level's fields by "group.key".
	 *
	 * @return array<string, Field>
	 */
	private static function fields(): array {
		$fields = array();
		foreach ( Schema::level_fields( 'category' ) as $group => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				$fields[ "$group.$key" ] = $field;
			}
		}
		return $fields;
	}

	/**
	 * Element id of a field.
	 *
	 * @param string $path "group.key".
	 */
	private static function id( string $path ): string {
		return 'pergel-shop-' . str_replace( array( '.', '_' ), '-', $path );
	}

	/**
	 * Labels of a field from the settings screen.
	 *
	 * @param string $path "group.key".
	 * @return array<string, mixed>
	 */
	private static function text( string $path ): array {
		[ $group, $key ] = explode( '.', $path, 2 );
		return Labels::fields()[ $group ][ $key ] ?? array();
	}

	/**
	 * Label of a field.
	 *
	 * @param string $path "group.key".
	 */
	private static function label( string $path ): string {
		return (string) ( self::text( $path )['label'] ?? $path );
	}

	/**
	 * Help paragraph of a field, or nothing.
	 *
	 * @param string $path "group.key".
	 * @param string $tag  Opening tag contents, e.g. 'p class="description"'.
	 */
	private static function help( string $path, string $tag ): string {
		$help = (string) ( self::text( $path )['help'] ?? '' );
		return '' === $help ? '' : '<' . $tag . '>' . esc_html( $help ) . '</p>';
	}

	/**
	 * The input: a select box with "inherit" first, or a number box whose
	 * empty value inherits.
	 *
	 * @param string               $path  "group.key".
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Own value; null inherits.
	 * @phpstan-param Field $field
	 */
	private static function control( string $path, array $field, mixed $value ): string {
		$attrs = sprintf( 'id="%1$s" name="%2$s"', esc_attr( self::id( $path ) ), esc_attr( self::NAME . "[$path]" ) );
		if ( 'int' === $field['type'] ) {
			return sprintf(
				'<input type="number" %1$s class="small-text" value="%2$s" min="%3$d" max="%4$d" placeholder="%5$s">',
				$attrs,
				null === $value ? '' : esc_attr( (string) (int) $value ),
				(int) ( $field['min'] ?? 0 ),
				(int) ( $field['max'] ?? 0 ),
				esc_attr__( 'Inherit', 'pergel-engine' )
			);
		}
		$choices = array( '' => Labels::inherit( 'category' ) );
		if ( 'pattern' === $field['type'] ) {
			$choices += Screen::patterns( null === $value ? 0 : (int) $value );
		} elseif ( 'bool' === $field['type'] ) {
			$choices += array(
				'1' => __( 'On', 'pergel-engine' ),
				'0' => __( 'Off', 'pergel-engine' ),
			);
			$value    = null === $value ? null : ( $value ? '1' : '0' );
		} else {
			$names = (array) ( self::text( $path )['choices'] ?? array() );
			foreach ( $field['options'] ?? array() as $option ) {
				$choices[ $option ] = (string) ( $names[ $option ] ?? $option );
			}
		}
		$html = '<select ' . $attrs . '>';
		foreach ( $choices as $option => $label ) {
			$html .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $option ), selected( (string) $value, (string) $option, false ), esc_html( $label ) );
		}
		return $html . '</select>';
	}
}
