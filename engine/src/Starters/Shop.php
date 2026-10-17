<?php
/**
 * The shop part of a starter.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Starters;

use InvalidArgumentException;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_External;
use WC_Product_Factory;
use WC_Product_Grouped;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Term;

/**
 * Product categories, attributes, products and their images from the `shop`
 * part of starter.json. Needs WooCommerce.
 *
 * Everything it makes carries the starter's name and the item's key (post
 * meta on products, variations and images, term meta on terms), so a second
 * install finds it and `remove` deletes only that. A global attribute that
 * already exists is used, not made, and stays when the starter goes; the
 * attributes it made are listed in an option. Images are drawn on install
 * (Images), not shipped.
 */
final class Shop {

	public const ATTRIBUTES_OPTION = 'pergel_starter_attributes';

	private const TYPES = array( 'simple', 'variable', 'grouped', 'external' );

	private const MAX_VARIATIONS = 50;

	/**
	 * The active style's colours, read once.
	 *
	 * @var array<string, string>
	 */
	private array $palette = array();

	/**
	 * Category IDs by key (0 in a dry run).
	 *
	 * @var array<string, int>
	 */
	private array $categories = array();

	/**
	 * Attribute data by slug: taxonomy and term slugs, IDs and colours by term key.
	 *
	 * @var array<string, array{taxonomy: string, id: int, terms: array<string, array{id: int, slug: string, color: string}>}>
	 */
	private array $attributes = array();

	/**
	 * Report rows.
	 *
	 * @var list<array{action: string, type: string, key: string, id: string, title: string}>
	 */
	private array $rows = array();

	/**
	 * Use with validated data.
	 *
	 * @param string               $name Starter name.
	 * @param array<string, mixed> $data Output of validate().
	 */
	public function __construct( private readonly string $name, private readonly array $data ) {}

	/**
	 * Whether WooCommerce is running.
	 */
	public static function active(): bool {
		return class_exists( 'WooCommerce', false ) && function_exists( 'wc_get_product' );
	}

	/**
	 * Makes the categories, attributes, products and images.
	 *
	 * @param bool $dry_run Only report.
	 * @param bool $reset   Rewrite items made before.
	 * @return array{rows: list<array{action: string, type: string, key: string, id: string, title: string}>, categories: array<string, int>}
	 */
	public function install( bool $dry_run, bool $reset ): array {
		$this->palette = Images::palette();
		$this->install_categories( $dry_run, $reset );
		$this->install_attributes( $dry_run );
		$this->install_products( $dry_run, $reset );
		return array(
			'rows'       => $this->rows,
			'categories' => $this->categories,
		);
	}

	/**
	 * A navigation link block for a shop menu entry: `wc:shop` or `category:<key>`.
	 *
	 * @param string             $entry      Menu entry.
	 * @param array<string, int> $categories Category IDs by key.
	 */
	public static function menu_link( string $entry, array $categories ): string {
		if ( 'wc:shop' === $entry ) {
			$page = get_post( function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0 );
			if ( ! $page || 'page' !== $page->post_type ) {
				return '';
			}
			$attrs = array(
				'label' => $page->post_title,
				'type'  => 'page',
				'id'    => $page->ID,
				'url'   => (string) get_permalink( $page ),
				'kind'  => 'post-type',
			);
		} else {
			$term = get_term( $categories[ substr( $entry, strlen( 'category:' ) ) ] ?? 0, 'product_cat' );
			$url  = $term instanceof WP_Term ? get_term_link( $term ) : '';
			if ( ! $term instanceof WP_Term || ! is_string( $url ) ) {
				return '';
			}
			$attrs = array(
				'label' => $term->name,
				'type'  => 'product_cat',
				'id'    => $term->term_id,
				'url'   => $url,
				'kind'  => 'taxonomy',
			);
		}
		return get_comment_delimited_block_content( 'core/navigation-link', $attrs, '' );
	}

	/**
	 * Deletes the products, variations, terms, attributes and images the named
	 * starter made.
	 *
	 * @param string $name    Starter name.
	 * @param bool   $dry_run Only report.
	 * @return list<array{action: string, type: string, key: string, id: string, title: string}>
	 */
	public static function remove( string $name, bool $dry_run ): array {
		$rows = array();
		foreach ( array( 'product_variation', 'product', 'attachment' ) as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'meta_key'       => Starter::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI only.
					'meta_value'     => $name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- CLI only.
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			foreach ( $posts as $post ) {
				$key = (string) get_post_meta( $post->ID, Starter::META_KEY, true );
				if ( 'product' === $post_type ) {
					$rows[] = Starter::row( 'delete', 'product', $key, $post->ID, $post->post_title );
				} elseif ( 'attachment' === $post_type ) {
					$rows[] = Starter::row( 'delete', 'image', $key, $post->ID, $post->post_title );
				}
				if ( $dry_run ) {
					continue;
				}
				if ( 'attachment' === $post_type ) {
					wp_delete_attachment( $post->ID, true );
				} else {
					$product = self::active() ? wc_get_product( $post->ID ) : null;
					if ( $product instanceof WC_Product ) {
						$product->delete( true );
					} else {
						wp_delete_post( $post->ID, true );
					}
				}
			}
		}

		$taxonomies = array_merge( array( 'product_cat' ), self::active() ? wc_get_attribute_taxonomy_names() : array() );
		$terms      = get_terms(
			array(
				'taxonomy'   => array_values( array_filter( $taxonomies, 'taxonomy_exists' ) ),
				'hide_empty' => false,
				'meta_key'   => Starter::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI only.
				'meta_value' => $name, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- CLI only.
				'orderby'    => 'term_id',
				'order'      => 'DESC',
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( 'product_cat' === $term->taxonomy ) {
				$rows[] = Starter::row( 'delete', 'category', (string) get_term_meta( $term->term_id, Starter::META_KEY, true ), $term->term_id, $term->name );
			}
			if ( ! $dry_run && '' !== $term->taxonomy ) {
				wp_delete_term( $term->term_id, $term->taxonomy );
			}
		}

		$made = get_option( self::ATTRIBUTES_OPTION, array() );
		$made = is_array( $made ) ? $made : array();
		if ( self::active() ) {
			foreach ( array_map( 'intval', (array) ( $made[ $name ] ?? array() ) ) as $id ) {
				$attribute = wc_get_attribute( $id );
				if ( null === $attribute ) {
					continue;
				}
				$rows[] = Starter::row( 'delete', 'attribute', (string) preg_replace( '/^pa_/', '', $attribute->slug ), $id, $attribute->name );
				if ( ! $dry_run ) {
					wc_delete_attribute( $id );
				}
			}
			if ( ! $dry_run && isset( $made[ $name ] ) ) {
				unset( $made[ $name ] );
				update_option( self::ATTRIBUTES_OPTION, $made, false );
			}
		}
		return $rows;
	}

	/**
	 * Checks the `shop` part of starter.json.
	 *
	 * @param mixed $shop Decoded `shop` value.
	 * @return array{categories: list<array<string, mixed>>, attributes: list<array<string, mixed>>, products: list<array<string, mixed>>}
	 * @throws InvalidArgumentException On the first problem.
	 */
	public static function validate( mixed $shop ): array {
		$fail = static function ( string $message ): never {
			throw new InvalidArgumentException( esc_html( "starter.json: shop.$message" ) );
		};
		$list = static fn( mixed $value ): bool => is_array( $value ) && array_is_list( $value );
		if ( ! is_array( $shop ) ) {
			$fail( ' must be an object.' );
		}
		$key_ok = static fn( mixed $key ): bool => is_string( $key ) && 1 === preg_match( '/^[a-z0-9-]+$/', $key );

		$categories = $shop['categories'] ?? null;
		if ( ! $list( $categories ) || array() === $categories ) {
			$fail( 'categories must be a non-empty list.' );
		}
		$cats = array();
		foreach ( $categories as $i => $category ) {
			$key = is_array( $category ) ? ( $category['key'] ?? null ) : null;
			if ( ! $key_ok( $key ) || isset( $cats[ $key ] ) ) {
				$fail( "categories[$i].key must be a unique lowercase key." );
			}
			if ( '' === Starter::pick( $category['name'] ?? '' ) || '' === Starter::pick( $category['slug'] ?? '' ) ) {
				$fail( "categories[$i] needs a name and a slug." );
			}
			if ( isset( $category['parent'] ) && ! isset( $cats[ $category['parent'] ] ) ) {
				$fail( "categories[$i].parent must be the key of a category listed before it." );
			}
			$cats[ $key ] = true;
		}

		$attributes = $shop['attributes'] ?? array();
		if ( ! $list( $attributes ) ) {
			$fail( 'attributes must be a list.' );
		}
		$attrs = array();
		foreach ( $attributes as $i => $attribute ) {
			$slug = is_array( $attribute ) ? ( $attribute['slug'] ?? null ) : null;
			if ( ! is_string( $slug ) || ! preg_match( '/^[a-z][a-z0-9-]{0,26}$/', $slug ) || isset( $attrs[ $slug ] ) ) {
				$fail( "attributes[$i].slug must be a unique lowercase slug of at most 27 characters." );
			}
			if ( '' === Starter::pick( $attribute['name'] ?? '' ) || ! $list( $attribute['terms'] ?? null ) || array() === $attribute['terms'] ) {
				$fail( "attributes[$i] needs a name and a non-empty list of terms." );
			}
			$attrs[ $slug ] = array();
			foreach ( $attribute['terms'] as $j => $term ) {
				$key = is_array( $term ) ? ( $term['key'] ?? null ) : null;
				if ( ! $key_ok( $key ) || isset( $attrs[ $slug ][ $key ] ) || '' === Starter::pick( $term['name'] ?? '' ) ) {
					$fail( "attributes[$i].terms[$j] needs a unique lowercase key and a name." );
				}
				if ( isset( $term['color'] ) && null === Images::rgb( (string) $term['color'] ) ) {
					$fail( "attributes[$i].terms[$j].color must be a #rrggbb colour." );
				}
				$attrs[ $slug ][ $key ] = true;
			}
		}

		$products = $shop['products'] ?? null;
		if ( ! $list( $products ) || array() === $products ) {
			$fail( 'products must be a non-empty list.' );
		}
		$types = array();
		foreach ( $products as $i => $product ) {
			$key = is_array( $product ) ? ( $product['key'] ?? null ) : null;
			if ( ! $key_ok( $key ) || isset( $types[ $key ] ) ) {
				$fail( "products[$i].key must be a unique lowercase key." );
			}
			$type = $product['type'] ?? 'simple';
			if ( ! in_array( $type, self::TYPES, true ) ) {
				$fail( "products[$i].type must be one of " . implode( ', ', self::TYPES ) . '.' );
			}
			if ( '' === Starter::pick( $product['name'] ?? '' ) || '' === Starter::pick( $product['slug'] ?? '' ) ) {
				$fail( "products[$i] needs a name and a slug." );
			}
			if ( 'grouped' !== $type && ! self::money( $product['price'] ?? null ) ) {
				$fail( "products[$i].price must be a price such as 49.90 (a string)." );
			}
			if ( isset( $product['sale'] ) && ( ! self::money( $product['sale'] ) || (float) $product['sale'] >= (float) ( $product['price'] ?? 0 ) ) ) {
				$fail( "products[$i].sale must be a price below the regular price." );
			}
			if ( isset( $product['stock'] ) && ( ! is_int( $product['stock'] ) || $product['stock'] < 0 ) ) {
				$fail( "products[$i].stock must be a whole number, 0 or more." );
			}
			foreach ( (array) ( $product['categories'] ?? array() ) as $category ) {
				if ( ! isset( $cats[ $category ] ) ) {
					$fail( "products[$i].categories lists an unknown category key." );
				}
			}
			if ( 'variable' === $type ) {
				$options = $product['attributes'] ?? null;
				if ( ! is_array( $options ) || array() === $options || array_is_list( $options ) ) {
					$fail( "products[$i].attributes must map attribute slugs to term keys." );
				}
				$combinations = 1;
				foreach ( $options as $slug => $keys ) {
					if ( ! isset( $attrs[ $slug ] ) || ! $list( $keys ) || array() === $keys || array_diff( $keys, array_keys( $attrs[ $slug ] ) ) ) {
						$fail( "products[$i].attributes.$slug must list term keys of a listed attribute." );
					}
					$combinations *= count( $keys );
				}
				if ( $combinations > self::MAX_VARIATIONS ) {
					$fail( "products[$i] would have more than " . self::MAX_VARIATIONS . ' variations.' );
				}
			}
			if ( 'external' === $type && ! wp_http_validate_url( (string) ( $product['url'] ?? '' ) ) ) {
				$fail( "products[$i].url must be an http(s) address." );
			}
			$types[ $key ] = $type;
		}
		foreach ( $products as $i => $product ) {
			foreach ( array( 'cross_sells', 'upsells', 'children' ) as $field ) {
				foreach ( (array) ( $product[ $field ] ?? array() ) as $other ) {
					if ( ! isset( $types[ $other ] ) || $other === $product['key'] ) {
						$fail( "products[$i].$field lists an unknown product key." );
					}
					if ( 'children' === $field && in_array( $types[ $other ], array( 'grouped', 'variable' ), true ) ) {
						$fail( "products[$i].children may list only simple and external products." );
					}
				}
			}
			if ( 'grouped' === $types[ $product['key'] ] && array() === (array) ( $product['children'] ?? array() ) ) {
				$fail( "products[$i] is grouped and needs children." );
			}
		}

		return array(
			'categories' => $categories,
			'attributes' => $attributes,
			'products'   => $products,
		);
	}

	/**
	 * Makes or finds the product categories, with their images.
	 *
	 * @param bool $dry_run Only report.
	 * @param bool $reset   Rewrite categories made before.
	 * @throws InvalidArgumentException When a category of the same name is already there.
	 */
	private function install_categories( bool $dry_run, bool $reset ): void {
		$tones = Images::tones( $this->palette );
		foreach ( $this->data['categories'] as $i => $category ) {
			$key      = (string) $category['key'];
			$name     = Starter::pick( $category['name'] );
			$existing = $this->term( 'product_cat', $key );
			$parent   = max( 0, isset( $category['parent'] ) ? ( $this->categories[ $category['parent'] ] ?? 0 ) : 0 );
			$args     = array(
				'description' => Starter::pick( $category['description'] ?? '' ),
				'parent'      => $parent,
			);
			$action   = $existing ? ( $reset ? 'reset' : 'keep' ) : 'create';
			$id       = $existing ? $existing->term_id : 0;

			if ( ! $dry_run && 'create' === $action ) {
				$made = wp_insert_term( $name, 'product_cat', $args + array( 'slug' => sanitize_title( Starter::pick( $category['slug'] ) ) ) );
				if ( is_wp_error( $made ) ) {
					throw new InvalidArgumentException( esc_html( "Category $name: " . $made->get_error_message() ) );
				}
				$id = (int) $made['term_id'];
				update_term_meta( $id, Starter::META, $this->name );
				update_term_meta( $id, Starter::META_KEY, $key );
			} elseif ( ! $dry_run && 'reset' === $action ) {
				wp_update_term( $id, 'product_cat', $args + array( 'name' => $name ) );
			}
			$this->categories[ $key ] = $id;
			$this->rows[]             = Starter::row( $action, 'category', $key, $id, $name );

			$color = $tones[ $i % count( $tones ) ];
			$image = $this->image( 'category:' . $key, $name, $color, Images::mix( $color, $this->palette['base'], 0.86 ), 800, 600, $dry_run );
			if ( ! $dry_run && $image && ( 'keep' !== $action || ! get_term_meta( $id, 'thumbnail_id', true ) ) ) {
				update_term_meta( $id, 'thumbnail_id', $image );
			}
		}
	}

	/**
	 * Makes or finds the global attributes and their terms.
	 *
	 * @param bool $dry_run Only report.
	 * @throws InvalidArgumentException When WooCommerce refuses an attribute or term.
	 */
	private function install_attributes( bool $dry_run ): void {
		foreach ( $this->data['attributes'] as $attribute ) {
			$slug     = (string) $attribute['slug'];
			$name     = Starter::pick( $attribute['name'] );
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			$id       = wc_attribute_taxonomy_id_by_name( $slug );
			$action   = $id ? 'keep' : 'create';
			$colors   = array() !== array_filter( array_column( $attribute['terms'], 'color' ) );

			if ( ! $id && ! $dry_run ) {
				$visual = $colors && array_key_exists( 'wc-visual', wc_get_attribute_types() );
				$id     = wc_create_attribute(
					array(
						'name'         => $name,
						'slug'         => $slug,
						'type'         => $visual ? 'wc-visual' : 'select',
						'order_by'     => 'menu_order',
						'has_archives' => false,
					)
				);
				if ( is_wp_error( $id ) ) {
					throw new InvalidArgumentException( esc_html( "Attribute $name: " . $id->get_error_message() ) );
				}
				$made                = get_option( self::ATTRIBUTES_OPTION, array() );
				$made                = is_array( $made ) ? $made : array();
				$made[ $this->name ] = array_values( array_unique( array_merge( (array) ( $made[ $this->name ] ?? array() ), array( $id ) ) ) );
				update_option( self::ATTRIBUTES_OPTION, $made, false );
			}
			// WooCommerce registers attribute taxonomies on init; a new one is needed now.
			if ( $id && ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy(
					$taxonomy,
					array( 'product' ),
					array(
						'hierarchical' => false,
						'public'       => false,
						'rewrite'      => false,
					)
				);
			}

			$terms = array();
			foreach ( $attribute['terms'] as $order => $term ) {
				$key   = (string) $term['key'];
				$label = Starter::pick( $term['name'] );
				$found = $id ? $this->term( $taxonomy, $key ) : null;
				if ( ! $found && $id ) {
					$by_slug = get_term_by( 'slug', sanitize_title( Starter::pick( $term['slug'] ?? $label ) ), $taxonomy );
					$found   = $by_slug instanceof WP_Term ? $by_slug : null;
				}
				if ( ! $found && ! $dry_run ) {
					$made = wp_insert_term( $label, $taxonomy, array( 'slug' => sanitize_title( Starter::pick( $term['slug'] ?? $label ) ) ) );
					if ( is_wp_error( $made ) ) {
						throw new InvalidArgumentException( esc_html( "$name $label: " . $made->get_error_message() ) );
					}
					$tid = (int) $made['term_id'];
					update_term_meta( $tid, Starter::META, $this->name );
					update_term_meta( $tid, Starter::META_KEY, $key );
					update_term_meta( $tid, 'order', $order );
					if ( isset( $term['color'] ) ) {
						update_term_meta( $tid, 'color', strtolower( (string) $term['color'] ) );
					}
					$found = get_term( $tid, $taxonomy );
				}
				$terms[ $key ] = array(
					'id'    => $found instanceof WP_Term ? $found->term_id : 0,
					'slug'  => $found instanceof WP_Term ? $found->slug : sanitize_title( Starter::pick( $term['slug'] ?? $label ) ),
					'color' => (string) ( $term['color'] ?? '' ),
				);
			}
			$this->attributes[ $slug ] = array(
				'taxonomy' => $taxonomy,
				'id'       => (int) $id,
				'terms'    => $terms,
			);

			$this->rows[] = Starter::row( $action, 'attribute', $slug, (int) $id, sprintf( '%s (%d options)', $name, count( $terms ) ) );
		}
	}

	/**
	 * Makes or finds the products, then links them to each other.
	 *
	 * @param bool $dry_run Only report.
	 * @param bool $reset   Rewrite products made before.
	 */
	private function install_products( bool $dry_run, bool $reset ): void {
		$tones   = Images::tones( $this->palette );
		$ids     = array();
		$written = array();
		foreach ( $this->data['products'] as $i => $data ) {
			$key      = (string) $data['key'];
			$type     = (string) ( $data['type'] ?? 'simple' );
			$name     = Starter::pick( $data['name'] );
			$existing = $this->post( 'product', $key );
			$action   = $existing ? ( $reset ? 'reset' : 'keep' ) : 'create';
			$id       = $existing ?? 0;
			$title    = $name;

			// Images first: the product and, for colour options, one per colour.
			$colors = array();
			foreach ( 'variable' === $type ? (array) $data['attributes'] : array() as $slug => $keys ) {
				foreach ( $keys as $term ) {
					$color = $this->attributes[ $slug ]['terms'][ $term ]['color'] ?? '';
					if ( '' !== $color ) {
						$colors[ $slug . ':' . $term ] = $color;
					}
				}
				if ( $colors ) {
					break;
				}
			}
			$images = array();
			if ( array() === $colors ) {
				$colors = array( '' => $tones[ $i % count( $tones ) ] );
			}
			foreach ( $colors as $option => $color ) {
				$images[ $option ] = $this->image( 'product:' . $key . ( '' === $option ? '' : ':' . $option ), $name, $color, $this->palette['surface'], 800, 800, $dry_run );
			}

			if ( ! $dry_run && 'keep' !== $action ) {
				$class   = WC_Product_Factory::get_classname_from_product_type( $type );
				$product = new $class( $id );
				$this->fill( $product, $data, 'create' === $action );
				$product->set_image_id( (int) reset( $images ) );
				$product->update_meta_data( Starter::META, $this->name );
				$product->update_meta_data( Starter::META_KEY, $key );
				$id        = $product->save();
				$written[] = $key;
				if ( 'variable' === $type ) {
					$title .= sprintf( ' (%d variations)', $this->variations( $product, $data, $images ) );
				}
			}
			$ids[ $key ]  = (int) $id;
			$this->rows[] = Starter::row( $action, 'product', $key, (int) $id, $title );
		}

		// Links need every product's ID.
		foreach ( $this->data['products'] as $data ) {
			if ( ! in_array( $data['key'], $written, true ) ) {
				continue;
			}
			$product = wc_get_product( $ids[ $data['key'] ] );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			$pick = static fn( string $field ): array => array_values( array_filter( array_map( static fn( string $other ): int => $ids[ $other ] ?? 0, (array) ( $data[ $field ] ?? array() ) ) ) );
			$product->set_cross_sell_ids( $pick( 'cross_sells' ) );
			$product->set_upsell_ids( $pick( 'upsells' ) );
			if ( $product instanceof WC_Product_Grouped ) {
				$product->set_children( $pick( 'children' ) );
			}
			$product->save();
		}
	}

	/**
	 * Sets a product's fields from its data.
	 *
	 * @param WC_Product           $product Product.
	 * @param array<string, mixed> $data    Product data.
	 * @param bool                 $is_new  Whether it is being made (the address is set only then).
	 */
	private function fill( WC_Product $product, array $data, bool $is_new ): void {
		$product->set_name( Starter::pick( $data['name'] ) );
		if ( $is_new ) {
			$product->set_slug( sanitize_title( Starter::pick( $data['slug'] ) ) );
		}
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_short_description( wpautop( Starter::pick( $data['short'] ?? '' ) ) );
		$product->set_description( wpautop( Starter::pick( $data['description'] ?? '' ) ) );
		$product->set_featured( ! empty( $data['featured'] ) );
		$product->set_category_ids( array_values( array_filter( array_map( fn( string $key ): int => $this->categories[ $key ] ?? 0, (array) ( $data['categories'] ?? array() ) ) ) ) );
		$product->set_total_sales( (int) ( $data['sales'] ?? 0 ) );

		$sku = (string) ( $data['sku'] ?? '' );
		if ( '' !== $sku && in_array( wc_get_product_id_by_sku( $sku ), array( 0, $product->get_id() ), true ) ) {
			$product->set_sku( $sku );
		}

		if ( ! $product->is_type( 'variable' ) && ! $product->is_type( 'grouped' ) ) {
			$product->set_regular_price( (string) $data['price'] );
			$product->set_sale_price( (string) ( $data['sale'] ?? '' ) );
		}
		if ( $product->is_type( 'simple' ) ) {
			$this->stock( $product, $data );
		}
		if ( $product instanceof WC_Product_External ) {
			$product->set_product_url( (string) $data['url'] );
			$product->set_button_text( Starter::pick( $data['button'] ?? '' ) );
		}
		if ( $product->is_type( 'variable' ) ) {
			$attributes = array();
			foreach ( (array) $data['attributes'] as $slug => $keys ) {
				$attribute = new WC_Product_Attribute();
				$attribute->set_id( $this->attributes[ $slug ]['id'] );
				$attribute->set_name( $this->attributes[ $slug ]['taxonomy'] );
				$attribute->set_options( array_map( fn( string $key ): int => $this->attributes[ $slug ]['terms'][ $key ]['id'], $keys ) );
				$attribute->set_visible( true );
				$attribute->set_variation( true );
				$attributes[] = $attribute;
			}
			$product->set_attributes( $attributes );
		}
	}

	/**
	 * Makes, updates and prunes a variable product's variations: one for each
	 * combination of its options.
	 *
	 * @param WC_Product           $product Saved variable product.
	 * @param array<string, mixed> $data    Product data.
	 * @param array<string, int>   $images  Image IDs by colour option ("slug:term"), or one under ''.
	 * @return int How many there are.
	 */
	private function variations( WC_Product $product, array $data, array $images ): int {
		$existing = array();
		foreach ( $product->get_children() as $child ) {
			$existing[ (string) get_post_meta( $child, Starter::META_KEY, true ) ] = $child;
		}

		$combinations = array( array() );
		foreach ( (array) $data['attributes'] as $slug => $keys ) {
			$next = array();
			foreach ( $combinations as $combination ) {
				foreach ( $keys as $key ) {
					$next[] = $combination + array( $slug => $key );
				}
			}
			$combinations = $next;
		}

		$kept = array();
		foreach ( $combinations as $combination ) {
			$key       = $data['key'] . ':' . implode( '-', $combination );
			$variation = new WC_Product_Variation( $existing[ $key ] ?? 0 );
			$variation->set_parent_id( $product->get_id() );
			$values = array();
			$image  = (int) reset( $images );
			foreach ( $combination as $slug => $term ) {
				$values[ $this->attributes[ $slug ]['taxonomy'] ] = $this->attributes[ $slug ]['terms'][ $term ]['slug'];
				$image = $images[ $slug . ':' . $term ] ?? $image;
			}
			$variation->set_attributes( $values );
			$variation->set_status( 'publish' );
			$variation->set_regular_price( (string) $data['price'] );
			$variation->set_sale_price( (string) ( $data['sale'] ?? '' ) );
			$variation->set_image_id( $image );
			$this->stock( $variation, $data );
			$variation->update_meta_data( Starter::META, $this->name );
			$variation->update_meta_data( Starter::META_KEY, $key );
			$variation->save();
			$kept[] = $key;
		}
		foreach ( array_diff_key( $existing, array_flip( $kept ) ) as $id ) {
			$old = wc_get_product( $id );
			if ( $old instanceof WC_Product ) {
				$old->delete( true );
			}
		}
		WC_Product_Variable::sync( $product->get_id() );
		return count( $kept );
	}

	/**
	 * Stock from the data: a count when it has one, otherwise in stock without counting.
	 *
	 * @param WC_Product           $product Product or variation.
	 * @param array<string, mixed> $data    Product data.
	 */
	private function stock( WC_Product $product, array $data ): void {
		$counted = isset( $data['stock'] );
		$product->set_manage_stock( $counted );
		if ( $counted ) {
			$product->set_stock_quantity( (int) $data['stock'] );
		} else {
			$product->set_stock_quantity( null );
			$product->set_stock_status( 'instock' );
		}
	}

	/**
	 * The image this starter drew under $key, or a new one.
	 *
	 * @param string $key     Item key, e.g. product:mug.
	 * @param string $title   Attachment title.
	 * @param string $color   Shape colour.
	 * @param string $ground  Background colour.
	 * @param int    $width   Width.
	 * @param int    $height  Height.
	 * @param bool   $dry_run Only report.
	 * @return int Attachment ID, 0 in a dry run for a new one.
	 * @throws InvalidArgumentException When the image cannot be made.
	 */
	private function image( string $key, string $title, string $color, string $ground, int $width, int $height, bool $dry_run ): int {
		$found = $this->post( 'attachment', $key );
		if ( $found ) {
			$this->rows[] = Starter::row( 'keep', 'image', $key, $found, $title );
			return $found;
		}
		if ( $dry_run ) {
			$this->rows[] = Starter::row( 'create', 'image', $key, 0, $title );
			return 0;
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			throw new InvalidArgumentException( esc_html( (string) $upload['error'] ) );
		}
		$file = wp_unique_filename( $upload['path'], sanitize_file_name( 'pergel-' . $this->name . '-' . str_replace( ':', '-', $key ) ) . '.webp' );
		$path = Images::draw( $upload['path'] . '/' . pathinfo( $file, PATHINFO_FILENAME ), $key, $color, $ground, $width, $height );
		$id   = wp_insert_attachment(
			array(
				'post_mime_type' => (string) wp_check_filetype( $path )['type'],
				'post_title'     => $title,
				// Named after the file, not the title: an attachment's slug
				// is taken from pages too, so a category image titled "Home"
				// would push the starter's home page to home-2.
				'post_name'      => sanitize_title( pathinfo( $file, PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'meta_input'     => array(
					Starter::META     => $this->name,
					Starter::META_KEY => $key,
				),
			),
			$path,
			0,
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $path );
			throw new InvalidArgumentException( esc_html( $id->get_error_message() ) );
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
		$this->rows[] = Starter::row( 'create', 'image', $key, $id, $title );
		return $id;
	}

	/**
	 * ID of the post of $type this starter made under $key, or null.
	 *
	 * @param string $type Post type.
	 * @param string $key  Item key.
	 */
	private function post( string $type, string $key ): ?int {
		$ids = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI only.
					array(
						'key'   => Starter::META,
						'value' => $this->name,
					),
					array(
						'key'   => Starter::META_KEY,
						'value' => $key,
					),
				),
			)
		);
		return $ids ? (int) $ids[0] : null;
	}

	/**
	 * The term of $taxonomy this starter made under $key, or null.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $key      Item key.
	 */
	private function term( string $taxonomy, string $key ): ?WP_Term {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 1,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- CLI only.
					array(
						'key'   => Starter::META,
						'value' => $this->name,
					),
					array(
						'key'   => Starter::META_KEY,
						'value' => $key,
					),
				),
			)
		);
		return is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof WP_Term ? $terms[0] : null;
	}

	/**
	 * Whether a value is a price such as "49" or "49.90".
	 *
	 * @param mixed $value Value.
	 */
	private static function money( mixed $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^\d+(\.\d{1,2})?$/', $value );
	}
}
