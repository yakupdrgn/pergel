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
		$parts  = array();
		$values = array();
		foreach ( $terms as $term ) {
			$parts[] = "SELECT object_id, MAX(weight + IF(token = %s, %d, 0)) AS w FROM $table WHERE kind = %d AND token LIKE %s GROUP BY object_id";
			array_push( $values, $term, self::WHOLE_WORD, $kind, $wpdb->esc_like( $term ) . '%' );
		}
		$join  = '';
		$order = 'score DESC, m.object_id DESC';
		if ( Index::KIND_POST === $kind ) {
			$join  = "INNER JOIN {$wpdb->posts} p ON p.ID = m.object_id";
			$order = 'score DESC, MAX(p.post_date) DESC';
			if ( array() !== $types ) {
				$join  .= ' AND p.post_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')';
				$values = array_merge( $values, $types );
			}
		}
		$values[] = count( $terms );
		$values[] = $limit;
		$sql      = 'SELECT m.object_id, SUM(m.w) AS score FROM (' . implode( ' UNION ALL ', $parts ) . ") m $join GROUP BY m.object_id HAVING COUNT(*) = %d ORDER BY $order LIMIT %d";

		$rows    = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Built from placeholders only; cached above by last-changed.
		$results = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$results[ (int) $row['object_id'] ] = (int) $row['score'];
		}
		wp_cache_set( $key, $results, Index::CACHE_GROUP, HOUR_IN_SECONDS );
		return $results;
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
