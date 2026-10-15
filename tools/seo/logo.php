<?php
/**
 * Sets a temporary Site Logo for the SEO matrix, or removes it again.
 *
 * Usage: wp eval-file tools/seo/logo.php add      (prints the attachment ID)
 *        wp eval-file tools/seo/logo.php svg      (an SVG logo, not set as Site Logo)
 *        wp eval-file tools/seo/logo.php remove <id> [<previous site_logo>]
 *
 * Yoast SEO prints no Organization without a logo, and a company site has
 * one. The image is drawn here, so no binary file lives in the repo.
 * Development and CI only.
 *
 * Runs through eval(), so it cannot declare strict_types.
 *
 * @package Pergel
 */

namespace Pergel\Dev;

defined( 'ABSPATH' ) || exit;

/**
 * Draws a 512 × 512 PNG and makes it the Site Logo.
 */
function add_logo(): void {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$upload = wp_upload_dir();
	$file   = trailingslashit( $upload['path'] ) . 'pergel-matrix-logo.png';
	$image  = imagecreatetruecolor( 512, 512 );
	if ( false === $image ) {
		\WP_CLI::error( 'GD could not create the logo.' );
	}
	imagefill( $image, 0, 0, (int) imagecolorallocate( $image, 31, 58, 95 ) );
	imagepng( $image, $file );

	$id = wp_insert_attachment(
		array(
			'post_title'     => 'Pergel matrix logo',
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
		),
		$file,
		0,
		true
	);
	if ( is_wp_error( $id ) ) {
		\WP_CLI::error( $id );
	}
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	update_option( 'site_logo', $id );
	\WP_CLI::line( (string) $id );
}

/**
 * Adds a 240 × 64 SVG logo, as an upload through Safe SVG leaves it, and
 * prints its ID. The Site Logo stays as it is.
 */
function add_svg(): void {
	$upload = wp_upload_dir();
	$file   = trailingslashit( $upload['path'] ) . 'pergel-matrix-logo.svg';
	file_put_contents( $file, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 64"><rect width="240" height="64" fill="#1f3a5f"/></svg>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- dev tool.

	$id = wp_insert_attachment(
		array(
			'post_title'     => 'Pergel matrix logo',
			'post_mime_type' => 'image/svg+xml',
			'post_status'    => 'inherit',
		),
		$file,
		0,
		true
	);
	if ( is_wp_error( $id ) ) {
		\WP_CLI::error( $id );
	}
	wp_update_attachment_metadata(
		$id,
		array(
			'width'  => 240,
			'height' => 64,
			'file'   => _wp_relative_upload_path( $file ),
		)
	);
	\WP_CLI::line( (string) $id );
}

/**
 * Deletes the logo and puts the previous Site Logo back.
 *
 * @param int    $id       Attachment ID printed by add_logo().
 * @param string $previous Previous site_logo value; empty: none.
 */
function remove_logo( int $id, string $previous ): void {
	if ( 'Pergel matrix logo' === get_the_title( $id ) ) {
		wp_delete_attachment( $id, true );
	}
	if ( '' === $previous ) {
		delete_option( 'site_logo' );
	} else {
		update_option( 'site_logo', (int) $previous );
	}
}

$pergel_args = isset( $args ) && is_array( $args ) ? $args : array();
if ( 'add' === ( $pergel_args[0] ?? '' ) ) {
	add_logo();
} elseif ( 'svg' === ( $pergel_args[0] ?? '' ) ) {
	add_svg();
} elseif ( 'remove' === ( $pergel_args[0] ?? '' ) ) {
	remove_logo( (int) ( $pergel_args[1] ?? 0 ), (string) ( $pergel_args[2] ?? '' ) );
} else {
	\WP_CLI::error( 'Usage: wp eval-file tools/seo/logo.php add|svg|remove <id> [<previous>]' );
}
