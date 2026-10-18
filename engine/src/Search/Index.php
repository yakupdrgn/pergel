<?php
/**
 * The search index table.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Search;

/**
 * One row per (object, token) with the weight of the best field the token
 * is in: title 10, SKU 9, category and other terms 5, attributes 4,
 * excerpt 3, content 1. Objects are posts of the searchable post types
 * (products included while WooCommerce is active) and, for the
 * suggestions, the terms of the searchable taxonomies.
 *
 * Tokens are folded (Text::fold) and stored with a binary collation, so
 * a prefix search is an index range scan: `token LIKE 'mik%'`.
 *
 * The index changes when content is saved, deleted, re-stocked or
 * re-categorized; a page view reads it and never writes. Changes made
 * during a request are written once, at its end. A full build runs in
 * batches, from WP-Cron or `wp pergel search reindex`; until the first one
 * finishes, searches use WordPress's own search.
 *
 * @phpstan-type State array{db: int, ready: bool, phase: string, cursor: int}
 */
final class Index {

	public const KIND_POST = 0;
	public const KIND_TERM = 1;

	/**
	 * Table schema version; a higher one makes install() run dbDelta.
	 */
	public const SCHEMA = 1;

	/**
	 * Autoloaded state: schema version, whether the index is complete, and
	 * where a running build is.
	 */
	public const STATE = 'pergel_search_state';

	/**
	 * Cron hook of the build, and of re-indexing a renamed term's posts.
	 */
	public const BUILD_HOOK = 'pergel/search/build';
	public const TERM_HOOK  = 'pergel/search/term_posts';

	/**
	 * Cache group whose last-changed time keys every cached query result.
	 */
	public const CACHE_GROUP = 'pergel_search';

	/**
	 * Field weights.
	 */
	public const WEIGHTS = array(
		'title'      => 10,
		'sku'        => 9,
		'terms'      => 5,
		'attributes' => 4,
		'excerpt'    => 3,
		'content'    => 1,
	);

	/**
	 * Most tokens taken from a post's content; the start of a long text is
	 * what describes it.
	 */
	public const CONTENT_TOKENS = 600;

	/**
	 * Posts per build batch.
	 */
	public const BATCH = 200;

	/**
	 * Objects waiting to be written at the end of the request, by kind.
	 *
	 * @var array<int, array<int, true>>
	 */
	private static array $queue = array();

	/**
	 * Hooks the index upkeep in.
	 */
	public static function register(): void {
		add_action( 'wp_after_insert_post', array( self::class, 'queue_post' ) );
		add_action( 'deleted_post', array( self::class, 'queue_post' ) );
		add_action( 'set_object_terms', array( self::class, 'queue_post' ) );
		add_action( 'created_term', array( self::class, 'queue_term' ), 10, 3 );
		add_action( 'edited_term', array( self::class, 'term_edited' ), 10, 3 );
		add_action( 'delete_term', array( self::class, 'queue_term' ), 10, 3 );
		// WooCommerce writes SKUs, stock and visibility after the post.
		add_action( 'woocommerce_new_product', array( self::class, 'queue_post' ) );
		add_action( 'woocommerce_update_product', array( self::class, 'queue_post' ) );
		add_action( 'woocommerce_product_set_stock_status', array( self::class, 'queue_post' ) );
		add_action( 'woocommerce_update_product_variation', array( self::class, 'queue_variation' ) );
		add_action( 'woocommerce_variation_set_stock_status', array( self::class, 'queue_variation' ) );
		add_action( 'shutdown', array( self::class, 'flush' ) );
		add_action( self::BUILD_HOOK, array( self::class, 'build_from_cron' ) );
		add_action( self::TERM_HOOK, array( self::class, 'term_posts' ), 10, 2 );
	}

	/**
	 * The table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'pergel_search';
	}

	/**
	 * The stored state, with defaults.
	 *
	 * @return array<string, mixed>
	 * @phpstan-return State
	 */
	public static function state(): array {
		$state = get_option( self::STATE );
		$state = is_array( $state ) ? $state : array();
		return array(
			'db'     => (int) ( $state['db'] ?? 0 ),
			'ready'  => true === ( $state['ready'] ?? false ),
			'phase'  => in_array( $state['phase'] ?? '', array( 'posts', 'terms' ), true ) ? (string) $state['phase'] : '',
			'cursor' => (int) ( $state['cursor'] ?? 0 ),
		);
	}

	/**
	 * Saves part of the state.
	 *
	 * @param array<string, mixed> $changes Keys of State.
	 */
	private static function save_state( array $changes ): void {
		update_option( self::STATE, array_merge( self::state(), $changes ), true );
	}

	/**
	 * Whether searches can use the index: the table exists and a full build
	 * has finished.
	 */
	public static function ready(): bool {
		$state = self::state();
		return $state['ready'] && self::SCHEMA === $state['db'];
	}

	/**
	 * Creates or updates the table when its schema is older than SCHEMA,
	 * and starts a build when the index is not complete. Hooked on
	 * admin_init; cheap when there is nothing to do.
	 */
	public static function maintain(): void {
		$state = self::state();
		if ( self::SCHEMA !== $state['db'] ) {
			self::install();
			self::start();
			return;
		}
		if ( ! $state['ready'] && '' === $state['phase'] ) {
			self::start();
		}
	}

	/**
	 * Creates the table.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->has_cap( 'utf8mb4' ) ? 'utf8mb4' : 'utf8';
		// Folded tokens compare byte by byte: no collation folding, and a prefix LIKE stays a range scan.
		dbDelta(
			"CREATE TABLE $table (
  kind tinyint(3) unsigned NOT NULL DEFAULT 0,
  token varchar(32) CHARACTER SET $charset COLLATE {$charset}_bin NOT NULL,
  object_id bigint(20) unsigned NOT NULL,
  weight smallint(5) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY  (kind,token,object_id),
  KEY object (kind,object_id)
) {$wpdb->get_charset_collate()};"
		);
		self::save_state( array( 'db' => self::SCHEMA ) );
	}

	/**
	 * Drops the table and the state. For uninstalling and tests.
	 */
	public static function uninstall(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table, name from the prefix.
		delete_option( self::STATE );
		wp_clear_scheduled_hook( self::BUILD_HOOK );
	}

	/**
	 * Marks the index incomplete and schedules a build. Searches use
	 * WordPress's own search until it finishes.
	 */
	public static function start(): void {
		self::save_state(
			array(
				'ready'  => false,
				'phase'  => 'posts',
				'cursor' => 0,
			)
		);
		self::clear();
		wp_clear_scheduled_hook( self::BUILD_HOOK );
		wp_schedule_single_event( time(), self::BUILD_HOOK );
	}

	/**
	 * Runs build batches for up to 20 seconds and schedules the rest.
	 * Hooked on the build cron event.
	 */
	public static function build_from_cron(): void {
		$until = microtime( true ) + 20;
		do {
			$done = self::step();
		} while ( ! $done && microtime( true ) < $until );
		if ( ! $done ) {
			wp_schedule_single_event( time(), self::BUILD_HOOK );
		}
	}

	/**
	 * Indexes one batch of the running build.
	 *
	 * @return bool Whether the build has finished.
	 */
	public static function step(): bool {
		global $wpdb;
		$state = self::state();
		if ( '' === $state['phase'] ) {
			return true;
		}

		if ( 'posts' === $state['phase'] ) {
			$types = self::post_types();
			$ids   = array() === $types ? array() : array_map(
				'intval',
				$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A batch of ids; nothing to cache.
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' AND post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') ORDER BY ID LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One %s per post type.
						array_merge( array( $state['cursor'] ), $types, array( self::BATCH ) )
					)
				)
			);
			if ( array() === $ids ) {
				self::save_state(
					array(
						'phase'  => 'terms',
						'cursor' => 0,
					)
				);
				return false;
			}
			_prime_post_caches( $ids );
			foreach ( $ids as $id ) {
				self::index_post( $id );
			}
			self::save_state( array( 'cursor' => (int) end( $ids ) ) );
			return false;
		}

		$taxonomies = self::taxonomies();
		$terms      = array() === $taxonomies ? array() : get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'hide_empty' => false,
				'fields'     => 'ids',
				'orderby'    => 'term_id',
				'number'     => self::BATCH,
				'offset'     => $state['cursor'],
			)
		);
		if ( ! is_array( $terms ) || array() === $terms ) {
			self::save_state(
				array(
					'ready'  => true,
					'phase'  => '',
					'cursor' => 0,
				)
			);
			wp_cache_set_last_changed( self::CACHE_GROUP );
			return true;
		}
		foreach ( $terms as $id ) {
			self::index_term( (int) $id );
		}
		self::save_state( array( 'cursor' => $state['cursor'] + count( $terms ) ) );
		return false;
	}

	/**
	 * Post types that are searched.
	 *
	 * @return list<string>
	 */
	public static function post_types(): array {
		$types = array_values(
			array_diff(
				get_post_types(
					array(
						'public'              => true,
						'exclude_from_search' => false,
					)
				),
				array( 'attachment' )
			)
		);

		/**
		 * Filters the post types in the search index.
		 *
		 * Rebuild the index after changing it: `wp pergel search reindex`.
		 *
		 * @param string[] $types Post type names.
		 */
		$filtered = apply_filters( 'pergel/search/post_types', $types );
		return is_array( $filtered ) ? array_values( array_filter( $filtered, 'is_string' ) ) : $types;
	}

	/**
	 * Taxonomies whose terms are suggested beside the results.
	 *
	 * @return list<string>
	 */
	public static function taxonomies(): array {
		$taxonomies = array_values( array_filter( array( 'product_cat', 'category' ), 'taxonomy_exists' ) );

		/**
		 * Filters the taxonomies whose terms appear in the search suggestions.
		 *
		 * @param string[] $taxonomies Taxonomy names.
		 */
		$filtered = apply_filters( 'pergel/search/taxonomies', $taxonomies );
		return is_array( $filtered ) ? array_values( array_filter( $filtered, 'is_string' ) ) : $taxonomies;
	}

	/**
	 * Whether a post belongs in the index: published, not password
	 * protected, of a searched type, and for products, visible in search
	 * (catalog visibility, and the "hide out of stock" shop setting).
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function searchable( \WP_Post $post ): bool {
		$searchable = 'publish' === $post->post_status
			&& '' === $post->post_password
			&& in_array( $post->post_type, self::post_types(), true );
		if ( $searchable && 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product    = wc_get_product( $post );
			$searchable = $product instanceof \WC_Product
				&& in_array( $product->get_catalog_visibility(), array( 'visible', 'search' ), true )
				&& ( 'yes' !== get_option( 'woocommerce_hide_out_of_stock_items' ) || $product->is_in_stock() );
		}

		/**
		 * Filters whether a post is in the search index.
		 *
		 * @param bool     $searchable Whether the post is indexed.
		 * @param \WP_Post $post       Post.
		 */
		return (bool) apply_filters( 'pergel/search/searchable', $searchable, $post );
	}

	/**
	 * The texts of a post that are indexed, with their weights.
	 *
	 * @param \WP_Post $post Post.
	 * @return list<array{text: string, weight: int, code?: bool}>
	 */
	public static function fields( \WP_Post $post ): array {
		$fields = array(
			array(
				'text'   => $post->post_title,
				'weight' => self::WEIGHTS['title'],
			),
			array(
				'text'   => $post->post_excerpt,
				'weight' => self::WEIGHTS['excerpt'],
			),
			array(
				'text'   => self::plain( $post->post_content ),
				'weight' => self::WEIGHTS['content'],
			),
		);

		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			if ( 'post_format' === $taxonomy->name || ( ! $taxonomy->public && ! str_starts_with( $taxonomy->name, 'pa_' ) ) ) {
				continue;
			}
			$terms = get_the_terms( $post, $taxonomy->name );
			if ( is_array( $terms ) && array() !== $terms ) {
				$fields[] = array(
					'text'   => implode( ' ', wp_list_pluck( $terms, 'name' ) ),
					'weight' => str_starts_with( $taxonomy->name, 'pa_' ) ? self::WEIGHTS['attributes'] : self::WEIGHTS['terms'],
				);
			}
		}

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$fields = array_merge( $fields, self::product_fields( $post ) );
		}

		/**
		 * Filters the indexed texts of a post, e.g. to add a brand field or a
		 * custom field. A field with "code" also adds its text without
		 * punctuation as one token, as SKUs do.
		 *
		 * @param array<int, array{text: string, weight: int, code?: bool}> $fields Texts and weights.
		 * @param \WP_Post                                                  $post   Post.
		 */
		$filtered = apply_filters( 'pergel/search/fields', $fields, $post );
		return is_array( $filtered ) ? array_values( $filtered ) : $fields;
	}

	/**
	 * SKUs (with those of the variations) and custom attributes of a product.
	 *
	 * @param \WP_Post $post Product post.
	 * @return list<array{text: string, weight: int, code?: bool}>
	 */
	private static function product_fields( \WP_Post $post ): array {
		global $wpdb;
		$product = wc_get_product( $post );
		if ( ! $product instanceof \WC_Product ) {
			return array();
		}
		$fields = array();
		$skus   = array( $product->get_sku() );
		$kids   = array_map( 'intval', $product->get_children() );
		if ( $product->is_type( 'variable' ) && array() !== $kids ) {
			$skus = array_merge(
				$skus,
				$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Saves loading every variation; runs only when indexing.
					$wpdb->prepare(
						"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND post_id IN (" . implode( ',', array_fill( 0, count( $kids ), '%d' ) ) . ')', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One %d per variation.
						$kids
					)
				)
			);
		}
		foreach ( array_unique( array_filter( array_map( 'strval', $skus ) ) ) as $sku ) {
			$fields[] = array(
				'text'   => $sku,
				'weight' => self::WEIGHTS['sku'],
				'code'   => true,
			);
		}
		foreach ( $product->get_attributes() as $attribute ) {
			if ( $attribute instanceof \WC_Product_Attribute && ! $attribute->is_taxonomy() ) {
				$fields[] = array(
					'text'   => implode( ' ', array_map( 'strval', $attribute->get_options() ) ),
					'weight' => self::WEIGHTS['attributes'],
				);
			}
		}
		return $fields;
	}

	/**
	 * Tokens of a post with the best weight each.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, int>
	 */
	public static function document( \WP_Post $post ): array {
		return self::weigh( self::fields( $post ) );
	}

	/**
	 * Tokens of fields with the best weight each.
	 *
	 * @param array<int, mixed> $fields Texts and weights, as from fields().
	 * @return array<string, int>
	 */
	public static function weigh( array $fields ): array {
		$tokens = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! is_string( $field['text'] ?? null ) || ! is_int( $field['weight'] ?? null ) || $field['weight'] < 1 ) {
				continue;
			}
			$weight = min( 1000, $field['weight'] );
			$limit  = self::WEIGHTS['content'] === $weight ? self::CONTENT_TOKENS : 0;
			$words  = Text::tokens( $field['text'], $limit );
			if ( true === ( $field['code'] ?? false ) ) {
				$words[] = Text::compact( $field['text'] );
			}
			foreach ( $words as $token ) {
				if ( '' !== $token && $weight > ( $tokens[ $token ] ?? 0 ) ) {
					$tokens[ $token ] = $weight;
				}
			}
		}
		return $tokens;
	}

	/**
	 * Text of post content without markup, block comments and shortcodes.
	 *
	 * @param string $content Post content.
	 */
	private static function plain( string $content ): string {
		$content = (string) preg_replace( '/<!--.*?-->/s', ' ', $content );
		return wp_strip_all_tags( strip_shortcodes( $content ) );
	}

	/**
	 * Writes or removes one post's rows now.
	 *
	 * @param int $id Post id.
	 */
	public static function index_post( int $id ): void {
		$post = get_post( $id );
		if ( $post instanceof \WP_Post && ! in_array( $post->post_type, self::post_types(), true ) ) {
			// Revisions, menu items and other types that are never indexed.
			return;
		}
		if ( $post instanceof \WP_Post && self::searchable( $post ) ) {
			self::write( self::KIND_POST, $id, self::document( $post ) );
		} else {
			self::write( self::KIND_POST, $id, array() );
		}
	}

	/**
	 * Writes or removes one term's rows now.
	 *
	 * @param int $id Term id.
	 */
	public static function index_term( int $id ): void {
		$term = get_term( $id );
		if ( $term instanceof \WP_Term && in_array( $term->taxonomy, self::taxonomies(), true ) ) {
			self::write(
				self::KIND_TERM,
				$id,
				self::weigh(
					array(
						array(
							'text'   => $term->name,
							'weight' => self::WEIGHTS['title'],
						),
					)
				)
			);
		} else {
			self::write( self::KIND_TERM, $id, array() );
		}
	}

	/**
	 * Replaces an object's rows.
	 *
	 * @param int                $kind   KIND_POST or KIND_TERM.
	 * @param int                $id     Object id.
	 * @param array<string, int> $tokens Tokens and weights; empty removes the object.
	 */
	public static function write( int $kind, int $id, array $tokens ): void {
		global $wpdb;
		if ( self::SCHEMA !== self::state()['db'] ) {
			return;
		}
		$table = self::table();
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Own table; queries are cached by last-changed.
			$table,
			array(
				'kind'      => $kind,
				'object_id' => $id,
			),
			array( '%d', '%d' )
		);
		foreach ( array_chunk( $tokens, 300, true ) as $chunk ) {
			$values = array();
			foreach ( $chunk as $token => $weight ) {
				array_push( $values, $kind, (string) $token, $id, $weight );
			}
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; queries are cached by last-changed.
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Four values per row, in one array.
					"INSERT IGNORE INTO $table (kind, token, object_id, weight) VALUES " . implode( ',', array_fill( 0, count( $chunk ), '(%d,%s,%d,%d)' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Own table name; one group of placeholders per row.
					$values
				)
			);
		}
		wp_cache_set_last_changed( self::CACHE_GROUP );
	}

	/**
	 * Empties the table.
	 */
	public static function clear(): void {
		global $wpdb;
		if ( self::SCHEMA === self::state()['db'] ) {
			$wpdb->query( 'DELETE FROM ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table, name from the prefix.
			wp_cache_set_last_changed( self::CACHE_GROUP );
		}
	}

	/**
	 * Queues a post (or the product of a variation id) for the end of the
	 * request.
	 *
	 * @param mixed $id Post id.
	 */
	public static function queue_post( mixed $id ): void {
		if ( is_numeric( $id ) && (int) $id > 0 ) {
			self::$queue[ self::KIND_POST ][ (int) $id ] = true;
			if ( count( self::$queue[ self::KIND_POST ] ) >= self::BATCH ) {
				self::flush();
			}
		}
	}

	/**
	 * Queues the product of a variation.
	 *
	 * @param mixed $id Variation id.
	 */
	public static function queue_variation( mixed $id ): void {
		if ( is_numeric( $id ) ) {
			self::queue_post( wp_get_post_parent_id( (int) $id ) );
		}
	}

	/**
	 * Queues a term. Hooked on created_term and delete_term.
	 *
	 * @param mixed $term_id  Term id.
	 * @param mixed $tt_id    Term taxonomy id.
	 * @param mixed $taxonomy Taxonomy.
	 */
	public static function queue_term( mixed $term_id, mixed $tt_id = 0, mixed $taxonomy = '' ): void {
		unset( $tt_id );
		if ( is_numeric( $term_id ) && in_array( $taxonomy, self::taxonomies(), true ) ) {
			self::$queue[ self::KIND_TERM ][ (int) $term_id ] = true;
		}
	}

	/**
	 * A renamed term: its own row now, its posts from cron, since a big
	 * category can hold thousands.
	 *
	 * @param mixed $term_id  Term id.
	 * @param mixed $tt_id    Term taxonomy id.
	 * @param mixed $taxonomy Taxonomy.
	 */
	public static function term_edited( mixed $term_id, mixed $tt_id, mixed $taxonomy ): void {
		self::queue_term( $term_id, $tt_id, $taxonomy );
		if ( is_numeric( $tt_id ) && is_string( $taxonomy ) && taxonomy_exists( $taxonomy ) ) {
			wp_schedule_single_event( time(), self::TERM_HOOK, array( (int) $tt_id, 0 ) );
		}
	}

	/**
	 * Re-indexes the posts of a term, a batch per cron run.
	 *
	 * @param int $tt_id  Term taxonomy id.
	 * @param int $offset Posts done so far.
	 */
	public static function term_posts( int $tt_id, int $offset ): void {
		global $wpdb;
		$ids = array_map(
			'intval',
			$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- A batch of ids; nothing to cache.
				$wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d ORDER BY object_id LIMIT %d OFFSET %d", $tt_id, self::BATCH, $offset )
			)
		);
		_prime_post_caches( $ids );
		foreach ( $ids as $id ) {
			self::index_post( $id );
		}
		if ( self::BATCH === count( $ids ) ) {
			wp_schedule_single_event( time(), self::TERM_HOOK, array( $tt_id, $offset + self::BATCH ) );
		}
	}

	/**
	 * Writes the queued objects. Hooked on shutdown.
	 */
	public static function flush(): void {
		$queue       = self::$queue;
		self::$queue = array();
		foreach ( array_keys( $queue[ self::KIND_POST ] ?? array() ) as $id ) {
			self::index_post( $id );
		}
		foreach ( array_keys( $queue[ self::KIND_TERM ] ?? array() ) as $id ) {
			self::index_term( $id );
		}
	}
}
