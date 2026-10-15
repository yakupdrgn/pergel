<?php
/**
 * Header option tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Blocks;

use Pergel\Blocks\Header;
use PHPUnit\Framework\TestCase;

/**
 * Attributes and wrapper classes of the header part options.
 */
final class HeaderTest extends TestCase {

	public function test_no_option_adds_no_class(): void {
		$this->assertSame( array(), Header::classes( array() ) );
		$this->assertSame(
			array(),
			Header::classes(
				array(
					'pergelSticky'  => '',
					'pergelOverlay' => false,
				)
			)
		);
	}

	public function test_classes_follow_the_options(): void {
		$this->assertSame( array( 'pergel-header', 'is-sticky' ), Header::classes( array( 'pergelSticky' => 'always' ) ) );
		$this->assertSame(
			array( 'pergel-header', 'is-smart', 'is-overlay' ),
			Header::classes(
				array(
					'pergelSticky'  => 'smart',
					'pergelOverlay' => true,
				)
			)
		);
		$this->assertSame( array( 'pergel-header', 'is-overlay' ), Header::classes( array( 'pergelOverlay' => true ) ) );
	}

	public function test_unknown_values_are_ignored(): void {
		$this->assertSame(
			array(),
			Header::classes(
				array(
					'pergelSticky'  => 'fixed',
					'pergelOverlay' => 'yes',
				)
			)
		);
	}

	public function test_attributes_go_only_to_the_template_part_block(): void {
		$args = Header::attributes( array( 'attributes' => array( 'slug' => array( 'type' => 'string' ) ) ), 'core/template-part' );

		$this->assertIsArray( $args );
		$this->assertSame( array( 'slug', 'pergelSticky', 'pergelOverlay' ), array_keys( $args['attributes'] ) );
		$this->assertSame( array( '', 'always', 'smart' ), $args['attributes']['pergelSticky']['enum'] );
		$this->assertSame( array( 'title' => 'x' ), Header::attributes( array( 'title' => 'x' ), 'core/group' ) );
	}
}
