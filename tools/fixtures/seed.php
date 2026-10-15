<?php
/**
 * Creates the fixed content that the budget and smoke tests measure.
 *
 * Usage: wp eval-file tools/fixtures/seed.php
 *
 * Idempotent: posts are matched by slug and rewritten, so running it again
 * gives the same site. Development and CI only.
 *
 * Runs through eval(), so it cannot declare strict_types.
 *
 * @package Pergel
 */

namespace Pergel\Dev;

defined( 'ABSPATH' ) || exit;

/**
 * Inserts or updates a post by slug.
 *
 * @param array<string, mixed> $data wp_insert_post() arguments; post_name is required.
 */
function seed_post( array $data ): int {
	$existing = get_page_by_path( (string) $data['post_name'], OBJECT, (string) $data['post_type'] );
	if ( $existing instanceof \WP_Post ) {
		$data['ID'] = $existing->ID;
	}
	$id = wp_insert_post( wp_slash( $data + array( 'post_status' => 'publish' ) ), true );
	if ( is_wp_error( $id ) ) {
		\WP_CLI::error( $id );
	}
	return $id;
}

/**
 * Seeds the fixed post, page, category, front page and posts page.
 */
function seed(): void {
	$paragraph = '<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->';
	$lorem     = 'Pergel is measured on every release. This paragraph stands in for real copy: it is long enough to wrap over several lines, carries a <a href="https://wordpress.org/">link</a> and <strong>strong text</strong>, and keeps the page close to a typical article.';

	$article = implode(
		"\n\n",
		array(
			sprintf( $paragraph, $lorem ),
			'<!-- wp:heading --><h2 class="wp-block-heading">What the budget checks</h2><!-- /wp:heading -->',
			sprintf( $paragraph, $lorem ),
			'<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>PHP files the theme and engine add to a request</li><!-- /wp:list-item --><!-- wp:list-item --><li>Database queries and writes</li><!-- /wp:list-item --><!-- wp:list-item --><li>HTML size, browser requests and JavaScript weight</li><!-- /wp:list-item --></ul><!-- /wp:list -->',
			'<!-- wp:heading --><h2 class="wp-block-heading">Why it matters</h2><!-- /wp:heading -->',
			sprintf( $paragraph, $lorem ),
			'<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>A fast theme stays fast only if every release is measured.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->',
			sprintf( $paragraph, $lorem ),
		)
	);

	$category = term_exists( 'pergel-test', 'category' );
	if ( ! $category ) {
		$category = wp_insert_term( 'Pergel test', 'category', array( 'slug' => 'pergel-test' ) );
	}
	$category_id = is_array( $category ) ? (int) $category['term_id'] : 0;

	$post = seed_post(
		array(
			'post_type'     => 'post',
			'post_name'     => 'pergel-sample-post',
			'post_title'    => 'Pergel sample post',
			'post_excerpt'  => 'A fixed article used by the Pergel performance budget.',
			'post_content'  => $article,
			'post_date'     => '2026-10-14 10:00:00',
			'post_category' => array( $category_id ),
		)
	);

	$page = seed_post(
		array(
			'post_type'    => 'page',
			'post_name'    => 'pergel-sample-page',
			'post_title'   => 'Pergel sample page',
			'post_content' => sprintf( $paragraph, $lorem ) . "\n\n" . sprintf( $paragraph, $lorem ),
		)
	);

	// A static front page whose first section carries the H1, and a posts page.
	$front = seed_post(
		array(
			'post_type'    => 'page',
			'post_name'    => 'pergel-front',
			'post_title'   => 'Home',
			'post_content' => implode(
				"\n\n",
				array(
					'<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:heading {"level":1,"fontSize":"xx-large"} --><h1 class="wp-block-heading has-xx-large-font-size">Pergel front page</h1><!-- /wp:heading -->' . sprintf( $paragraph, 'A fixed front page used by the Pergel performance budget.' ) . '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( home_url( '/blog/' ) ) . '">Read the blog</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->',
					'<!-- wp:heading --><h2 class="wp-block-heading">What the budget checks</h2><!-- /wp:heading -->',
					sprintf( $paragraph, $lorem ),
				)
			),
		)
	);
	$blog  = seed_post(
		array(
			'post_type'  => 'page',
			'post_name'  => 'blog',
			'post_title' => 'Blog',
		)
	);
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $front );
	update_option( 'page_for_posts', $blog );

	// WordPress caches the list of theme pattern files; new files appear only after this.
	wp_get_theme()->delete_pattern_cache();

	\WP_CLI::success( sprintf( 'Seeded post %d, page %d, front page %d and posts page %d.', $post, $page, $front, $blog ) );
}

seed();
