<?php
/**
 * Schema.org graph for a page.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Seo;

/**
 * Builds the JSON-LD graph Pergel prints when no SEO plugin is active, and
 * fills the gaps in an SEO plugin's Organization from the brand settings.
 *
 * Pure PHP (no WordPress calls) so it can be unit tested. Head collects the
 * page data and passes it in.
 *
 * @phpstan-type Image array{url?: string, width?: int, height?: int, alt?: string}
 * @phpstan-type Site array{url: string, name: string, description: string, language: string, search: string}
 * @phpstan-type Article array{type?: string, headline?: string, published?: string, modified?: string, author?: string}
 * @phpstan-type Crumb array{name: string, url: string}
 * @phpstan-type Page array{type: string, url: string, name: string, description: string, image: Image, crumbs: list<Crumb>, article: Article, front: bool}
 */
final class Graph {

	/**
	 * Types an SEO plugin may give the site's organization. Rank Math's
	 * local business types are matched through the "#organization" id.
	 */
	private const ORGANIZATION_TYPES = array( 'Organization', 'Corporation', 'LocalBusiness', 'OnlineBusiness', 'OnlineStore', 'NGO' );

	/**
	 * The graph for one page: Organization, WebSite, WebPage, and Article
	 * and BreadcrumbList when they apply.
	 *
	 * @param array<string, mixed> $site         Site data.
	 * @param array<string, mixed> $page         Page data.
	 * @param array<string, mixed> $organization Organization node (Brand::organization()).
	 * @phpstan-param Site $site
	 * @phpstan-param Page $page
	 * @return list<array<string, mixed>>
	 */
	public static function build( array $site, array $page, array $organization ): array {
		$org_ref      = array( '@id' => is_string( $organization['@id'] ?? null ) ? $organization['@id'] : $site['url'] . '#organization' );
		$website_id   = $site['url'] . '#website';
		$webpage_id   = $page['url'] . '#webpage';
		$organization = array( '@type' => 'Organization' ) + $org_ref + $organization;

		$website = array(
			'@type'       => 'WebSite',
			'@id'         => $website_id,
			'url'         => $site['url'],
			'name'        => $site['name'],
			'description' => $site['description'],
			'publisher'   => $org_ref,
			'inLanguage'  => $site['language'],
		);
		if ( '' !== $site['search'] ) {
			$website['potentialAction'] = array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => $site['search'],
				),
				'query-input' => 'required name=search_term_string',
			);
		}

		$webpage = array(
			'@type'       => '' !== $page['type'] ? $page['type'] : 'WebPage',
			'@id'         => $webpage_id,
			'url'         => $page['url'],
			'name'        => $page['name'],
			'description' => $page['description'],
			'isPartOf'    => array( '@id' => $website_id ),
			'inLanguage'  => $site['language'],
		);
		$image   = self::image( $page['image'], $page['url'] . '#primaryimage' );
		if ( $image ) {
			$webpage['primaryImageOfPage'] = $image;
		}
		if ( $page['front'] ) {
			$webpage['about'] = $org_ref;
		}
		$crumbs = self::breadcrumbs( $page['crumbs'], $page['url'] . '#breadcrumb' );
		if ( $crumbs ) {
			$webpage['breadcrumb'] = array( '@id' => $crumbs['@id'] );
		}

		$graph = array( self::clean( $organization ), self::clean( $website ), self::clean( $webpage ) );

		$article = $page['article'];
		if ( '' !== ( $article['headline'] ?? '' ) ) {
			$graph[] = self::clean(
				array(
					'@type'            => $article['type'] ?? 'Article',
					'@id'              => $page['url'] . '#article',
					'headline'         => $article['headline'] ?? '',
					'datePublished'    => $article['published'] ?? '',
					'dateModified'     => $article['modified'] ?? '',
					'author'           => self::person( $article['author'] ?? '' ),
					'publisher'        => $org_ref,
					'image'            => $image ? array( '@id' => $image['@id'] ) : array(),
					'isPartOf'         => array( '@id' => $webpage_id ),
					'mainEntityOfPage' => array( '@id' => $webpage_id ),
					'inLanguage'       => $site['language'],
				)
			);
		}

		if ( $crumbs ) {
			$graph[] = $crumbs;
		}
		return $graph;
	}

	/**
	 * BreadcrumbList from the trail, or an empty array for fewer than two
	 * steps (the home page alone is not a trail).
	 *
	 * @param array<array{name: string, url: string}> $crumbs Trail from the home page to the current page.
	 * @param string                                  $id     Node id.
	 * @return array<string, mixed>
	 */
	public static function breadcrumbs( array $crumbs, string $id ): array {
		$crumbs = array_values( array_filter( $crumbs, static fn ( array $crumb ): bool => '' !== $crumb['name'] && '' !== $crumb['url'] ) );
		if ( count( $crumbs ) < 2 ) {
			return array();
		}
		$items = array();
		foreach ( $crumbs as $index => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $crumb['name'],
				'item'     => $crumb['url'],
			);
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $id,
			'itemListElement' => $items,
		);
	}

	/**
	 * Fills the gaps in an SEO plugin's organization node. Existing values
	 * win; social profiles are merged. Other nodes, and references that carry
	 * only an @id, are returned unchanged.
	 *
	 * @param mixed                $node         A graph node from the plugin.
	 * @param array<string, mixed> $organization Organization node from the brand settings.
	 */
	public static function enrich( mixed $node, array $organization ): mixed {
		if ( ! is_array( $node ) || ! isset( $node['@type'] ) || ! self::is_organization( $node ) ) {
			return $node;
		}
		foreach ( $organization as $key => $value ) {
			if ( '@type' === $key || '@id' === $key ) {
				continue;
			}
			if ( 'sameAs' === $key && is_array( $value ) ) {
				$current        = isset( $node['sameAs'] ) ? (array) $node['sameAs'] : array();
				$node['sameAs'] = array_values( array_unique( array_merge( array_filter( $current, 'is_string' ), $value ) ) );
				continue;
			}
			if ( ! isset( $node[ $key ] ) || '' === $node[ $key ] || array() === $node[ $key ] ) {
				$node[ $key ] = $value;
			}
		}
		return $node;
	}

	/**
	 * Enrich() over a list or map of nodes; keys are kept. A publisher written
	 * out inside a node (The SEO Framework nests the Organization in WebSite on
	 * inner pages) is filled too. Other nested nodes are left alone: an
	 * Organization deeper in the graph may be another company.
	 *
	 * @param mixed                $graph        Nodes from the plugin.
	 * @param array<string, mixed> $organization Organization node from the brand settings.
	 */
	public static function enrich_all( mixed $graph, array $organization ): mixed {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}
		foreach ( $graph as $key => $node ) {
			$node = self::enrich( $node, $organization );
			if ( is_array( $node ) && is_array( $node['publisher'] ?? null ) ) {
				$node['publisher'] = self::enrich( $node['publisher'], $organization );
			}
			$graph[ $key ] = $node;
		}
		return $graph;
	}

	/**
	 * Whether a node describes the site's organization. A person (Yoast and
	 * Rank Math use [Person, Organization] for personal sites) is left alone.
	 *
	 * @param array<mixed> $node Graph node.
	 */
	public static function is_organization( array $node ): bool {
		$types = isset( $node['@type'] ) ? (array) $node['@type'] : array();
		if ( in_array( 'Person', $types, true ) ) {
			return false;
		}
		if ( array_intersect( self::ORGANIZATION_TYPES, $types ) ) {
			return true;
		}
		return is_string( $node['@id'] ?? null ) && str_ends_with( $node['@id'], '#organization' );
	}

	/**
	 * Shortens text for a meta description: whole words, at most $max
	 * characters including the ellipsis.
	 *
	 * @param string $text Plain text.
	 * @param int    $max  Maximum length in characters.
	 */
	public static function clip( string $text, int $max = 160 ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, $max - 1, 'UTF-8' );
		$space = mb_strrpos( $cut, ' ', 0, 'UTF-8' );
		if ( false !== $space && $space > $max / 2 ) {
			$cut = mb_substr( $cut, 0, $space, 'UTF-8' );
		}
		return rtrim( $cut, " \t,.;:-\u{2013}\u{2014}" ) . '…';
	}

	/**
	 * Person node from a name, or an empty array.
	 *
	 * @param string $name Display name.
	 * @return array<string, string>
	 */
	private static function person( string $name ): array {
		return '' === $name ? array() : array(
			'@type' => 'Person',
			'name'  => $name,
		);
	}

	/**
	 * ImageObject with an id, or an empty array without a URL.
	 *
	 * @param array<string, mixed> $image Image data.
	 * @param string               $id    Node id.
	 * @phpstan-param Image $image
	 * @return array<string, mixed>
	 */
	private static function image( array $image, string $id ): array {
		if ( '' === ( $image['url'] ?? '' ) ) {
			return array();
		}
		return self::clean(
			array(
				'@type'   => 'ImageObject',
				'@id'     => $id,
				'url'     => $image['url'],
				'width'   => $image['width'] ?? 0,
				'height'  => $image['height'] ?? 0,
				'caption' => $image['alt'] ?? '',
			)
		);
	}

	/**
	 * Drops empty strings, zeros and empty arrays from a node.
	 *
	 * @param array<string, mixed> $node Graph node.
	 * @return array<string, mixed>
	 */
	private static function clean( array $node ): array {
		return array_filter( $node, static fn ( $value ): bool => '' !== $value && 0 !== $value && array() !== $value && null !== $value );
	}
}
