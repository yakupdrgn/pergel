<?php
/**
 * Searching the index.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Search;

/**
 * Finds objects in the index and answers WordPress searches from it.
 *
 * A result has every term of the query, each as a whole token or the
 * start of one; its score is the sum of the best weights, plus a little
 * for a whole-word match. Ties go to the newer post. Front-end searches
 * (the search page, the product search, query blocks with a search) get
 * their ids from here instead of WordPress's `LIKE '%x%'` search: the ids
 * become post__in, in score order unless the visitor chose a sorting.
 *
 * A term found in very many index rows (a word nearly every post has) is
 * not gathered on its own: it is looked up only on the objects the other
 * terms found, so it costs as much as they do. A query of common terms
 * alone still gathers them.
 */
final class Query {

	/**
	 * Most results a search returns; pages beyond them are empty.
	 */
	public const MAX_RESULTS = 1000;

	/**
	 * Score added when a token is the whole term, not only its start.
	 */
	private const WHOLE_WORD = 2;

	/**
	 * Index rows above which a term counts as common.
	 */
	public const COMMON_ROWS = 5000;

	/**
	 * Hooks the front-end search in.
	 */
	public static function register(): void {
		// After WooCommerce (10) has set the shop's sorting.
		add_action( 'pre_get_posts', array( self::class, 'prepare' ), 20 );
		add_filter( 'posts_search', array( self::class, 'search_sql' ), 10, 2 );
	}

	/**
	 * Ids of matching objects with their scores, best first.
	 *
	 * @param int      $kind  Index::KIND_POST or Index::KIND_TERM.
	 * @param string[] $terms Folded terms, from Text::terms().
	 * @param string[] $types Post types to keep (posts only); empty keeps all.
	 * @param int      $limit Most results.
	 * @return array<int, int>
	 */
	public static function match( int $kind, array $terms, array $types = array(), int $limit = self::MAX_RESULTS ): array {
		global $wpdb;
		if ( array() === $terms || $limit < 1 ) {
			return array();
		}
		sort( $types );
		$key    = md5( (string) wp_json_encode( array( $kind, $terms, $types, $limit ) ) ) . ':' . wp_cache_get_last_changed( Index::CACHE_GROUP );
		$cached = wp_cache_get( $key, Index::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table  = Index::table();
		$common = self::common( $kind, $terms );
		$rare   = array_values( array_diff( $terms, $common ) );
		$parts  = array();
		$values = array();
		foreach ( $rare as $term ) {
			$parts[] = "SELECT object_id, MAX(weight + IF(token = %s, %d, 0)) AS w FROM $table WHERE kind = %d AND token LIKE %s GROUP BY object_id";
			array_push( $values, $term, self::WHOLE_WORD, $kind, $wpdb->esc_like( $term ) . '%' );
		}
		$join = '';
		$date = 'm.object_id';
		if ( Index::KIND_POST === $kind ) {
			$join = "INNER JOIN {$wpdb->posts} p ON p.ID = m.object_id";
			$date = 'MAX(p.post_date)';
			if ( array() !== $types ) {
				$join  .= ' AND p.post_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')';
				$values = array_merge( $values, $types );
			}
		}
		$values[] = count( $rare );
		$sql      = "SELECT m.object_id, SUM(m.w) AS score, $date AS d FROM (" . implode( ' UNION ALL ', $parts ) . ") m $join GROUP BY m.object_id HAVING COUNT(*) = %d";
		if ( array() !== $common ) {
			// Each common term on the objects found so far; a missing one leaves the score NULL.
			$add  = '';
			$head = array();
			foreach ( $common as $term ) {
				$add .= " + (SELECT MAX(c.weight + IF(c.token = %s, %d, 0)) FROM $table c WHERE c.kind = %d AND c.object_id = r.object_id AND c.token LIKE %s)";
				array_push( $head, $term, self::WHOLE_WORD, $kind, $wpdb->esc_like( $term ) . '%' );
			}
			// These placeholders come before the inner query's.
			$values = array_merge( $head, $values );
			$sql    = "SELECT r.object_id, r.score$add AS score, r.d FROM ($sql) r";
		}
		$values[] = $limit;
		$sql      = "SELECT x.object_id, x.score FROM ($sql) x WHERE x.score IS NOT NULL ORDER BY x.score DESC, x.d DESC, x.object_id DESC LIMIT %d";

		$rows    = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Built from placeholders only; cached above by last-changed.
		$results = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$results[ (int) $row['object_id'] ] = (int) $row['score'];
		}
		wp_cache_set( $key, $results, Index::CACHE_GROUP, HOUR_IN_SECONDS );
		return $results;
	}

	/**
	 * Terms of a query found in more than COMMON_ROWS index rows, unless
	 * every term is: then none, and the query gathers them all.
	 *
	 * @param int      $kind  Index::KIND_POST or Index::KIND_TERM.
	 * @param string[] $terms Folded terms.
	 * @return list<string>
	 */
	private static function common( int $kind, array $terms ): array {
		global $wpdb;
		if ( count( $terms ) < 2 ) {
			return array();
		}
		/**
		 * Filters how many index rows make a search term common (see
		 * Search\Query). Larger keeps more terms gathered on their own.
		 *
		 * @param int $rows Rows; default 5000.
		 */
		$rows   = max( 1, (int) apply_filters( 'pergel/search/common_rows', self::COMMON_ROWS ) );
		$table  = Index::table();
		$common = array();
		foreach ( $terms as $term ) {
			// Reads at most $rows entries of the primary key, however common the term is.
			$found = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $table WHERE kind = %d AND token LIKE %s LIMIT 1 OFFSET %d", $kind, $wpdb->esc_like( $term ) . '%', $rows ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Own table; the result of match() is cached.
			if ( null !== $found ) {
				$common[] = $term;
			}
		}
		return count( $common ) === count( $terms ) ? array() : $common;
	}

	/**
	 * Post types a search query asks for, limited to the indexed ones.
	 *
	 * @param mixed $post_type The query's post_type var.
	 * @return list<string>
	 */
	public static function types( mixed $post_type ): array {
		$indexed = Index::post_types();
		if ( '' === $post_type || 'any' === $post_type || null === $post_type || array() === $post_type ) {
			return $indexed;
		}
		return array_values( array_intersect( $indexed, (array) $post_type ) );
	}

	/**
	 * Takes over a front-end search. Hooked on pre_get_posts.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function prepare( \WP_Query $query ): void {
		$search = $query->get( 's' );
		if ( ! is_string( $search ) || '' === trim( $search ) || ! self::applies( $query ) ) {
			return;
		}
		$ids = array_keys( self::match( Index::KIND_POST, Text::terms( $search ), self::types( $query->get( 'post_type' ) ) ) );

		$within = $query->get( 'post__in' );
		if ( is_array( $within ) && array() !== $within ) {
			$within = array_map( 'intval', $within );
			$ids    = array_values( array_filter( $ids, static fn ( int $id ): bool => in_array( $id, $within, true ) ) );
		}
		$query->set( 'post__in', array() === $ids ? array( 0 ) : $ids );
		$orderby = $query->get( 'orderby' );
		if ( '' === $orderby || 'relevance' === $orderby || null === $orderby ) {
			$query->set( 'orderby', 'post__in' );
		}
		$query->set( 'pergel_search', true );
	}

	/**
	 * Whether the index answers this query: a front-end query (not the
	 * admin, the REST API or a feed), once the index is complete.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function applies( \WP_Query $query ): bool {
		$applies = ! is_admin()
			&& ! wp_is_serving_rest_request()
			&& ! $query->is_feed()
			&& false !== $query->get( 'pergel_search', null )
			&& Index::ready();

		/**
		 * Filters whether the Pergel index answers a search query. Return
		 * false to leave a query to WordPress's own search.
		 *
		 * @param bool      $applies Whether the index is used.
		 * @param \WP_Query $query   Query.
		 */
		return (bool) apply_filters( 'pergel/search/applies', $applies, $query );
	}

	/**
	 * Drops WordPress's LIKE clauses from a query the index answered.
	 * Hooked on posts_search.
	 *
	 * @param mixed     $sql   Search SQL.
	 * @param \WP_Query $query Query.
	 * @return mixed
	 */
	public static function search_sql( mixed $sql, \WP_Query $query ): mixed {
		return true === $query->get( 'pergel_search' ) ? '' : $sql;
	}
}
