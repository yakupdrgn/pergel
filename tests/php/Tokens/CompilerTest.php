<?php
/**
 * Token compiler tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Tokens;

use Pergel\Tokens\Compiler;
use Pergel\Tokens\Css;
use Pergel\Tokens\Invalid;
use PHPUnit\Framework\TestCase;

/**
 * Covers value conversion, aliases, presets, contrast and file output.
 */
final class CompilerTest extends TestCase {

	/**
	 * Smallest valid token tree, so each test only states what it changes.
	 *
	 * @return array<string, mixed>
	 */
	private static function minimal(): array {
		$dim = static fn ( float $v, string $u = 'rem' ): array => array( '$value' => array( 'value' => $v, 'unit' => $u ) );
		return array(
			'color'       => array(
				'$type'     => 'color',
				'base'      => array( '$value' => '#ffffff' ),
				'surface'   => array( '$value' => '#f4f5f7' ),
				'contrast'  => array( '$value' => '#16181d' ),
				'muted'     => array( '$value' => '#5b6270' ),
				'accent'    => array( '$value' => '#1f4fd1' ),
				'on-accent' => array( '$value' => '#ffffff' ),
			),
			'font-family' => array(
				'$type'   => 'fontFamily',
				'body'    => array( '$value' => array( 'Inter Variable', 'system-ui', 'sans-serif' ) ),
				'heading' => array( '$value' => '{font-family.body}' ),
				'mono'    => array( '$value' => 'monospace' ),
			),
			'font-size'   => array(
				'$type'    => 'dimension',
				'small'    => $dim( 0.875 ),
				'medium'   => $dim( 1 ),
				'large'    => $dim( 1.25 ),
				'x-large'  => $dim( 2 ),
				'xx-large' => $dim( 3 ),
			),
			'spacing'     => array(
				'$type' => 'dimension',
				'20'    => $dim( 0.5 ),
				'30'    => $dim( 0.75 ),
				'40'    => $dim( 1 ),
				'50'    => $dim( 1.5 ),
				'60'    => $dim( 2.5 ),
				'70'    => $dim( 4 ),
				'80'    => $dim( 6 ),
			),
			'layout'      => array(
				'$type'   => 'dimension',
				'content' => $dim( 720, 'px' ),
				'wide'    => $dim( 1240, 'px' ),
			),
		);
	}

	/**
	 * Compiles a tree and returns the problems, or an empty list on success.
	 *
	 * @param array<string, mixed> $tree Tree.
	 * @return list<string>
	 */
	private static function problems( array $tree ): array {
		try {
			Compiler::compile( $tree, 't.json' );
			return array();
		} catch ( Invalid $e ) {
			return $e->problems;
		}
	}

	public function test_minimal_tree_compiles_to_presets(): void {
		$settings = Compiler::compile( self::minimal(), 't.json' );

		$this->assertSame( array( 'base', 'surface', 'contrast', 'muted', 'accent', 'on-accent' ), array_column( $settings['color']['palette'], 'slug' ) );
		$this->assertSame( 'On accent', $settings['color']['palette'][5]['name'] );
		$this->assertSame( '"Inter Variable", system-ui, sans-serif', $settings['typography']['fontFamilies'][0]['fontFamily'] );
		$this->assertSame( $settings['typography']['fontFamilies'][0]['fontFamily'], $settings['typography']['fontFamilies'][1]['fontFamily'], 'alias resolved' );
		$this->assertFalse( $settings['typography']['fontSizes'][0]['fluid'] );
		$this->assertSame( '720px', $settings['layout']['contentSize'] );
	}

	public function test_fluid_spacing_reaches_min_and_max_at_viewport_edges(): void {
		$tree                                = self::minimal();
		$tree['spacing']['50']['$extensions'] = array( Compiler::EXT => array( 'fluid' => array( 'min' => '1.25rem' ) ) );
		$size                                = Compiler::compile( $tree, 't.json' )['spacing']['spacingSizes'][3]['size'];

		$this->assertSame( 'clamp(1.25rem, 1.1477rem + 0.4545vw, 1.5rem)', $size );
		// 1.1477rem + 0.4545vw at 360px = 18.36 + 1.64 = 20px (1.25rem); at 1240px = 18.36 + 5.64 = 24px (1.5rem).
		$this->assertEqualsWithDelta( 20.0, 1.1477 * 16 + 0.004545 * 360, 0.01 );
		$this->assertEqualsWithDelta( 24.0, 1.1477 * 16 + 0.004545 * 1240, 0.01 );
	}

	public function test_fluid_font_size_maps_to_theme_json_fluid(): void {
		$tree                                    = self::minimal();
		$tree['font-size']['large']['$extensions'] = array( Compiler::EXT => array( 'fluid' => array( 'min' => array( 'value' => 1.125, 'unit' => 'rem' ) ) ) );

		$this->assertSame(
			array(
				'min' => '1.125rem',
				'max' => '1.25rem',
			),
			Compiler::compile( $tree, 't.json' )['typography']['fontSizes'][2]['fluid']
		);
	}

	public function test_custom_groups_go_to_settings_custom(): void {
		$tree                = self::minimal();
		$tree['radius']      = array(
			'$type'  => 'dimension',
			'small'  => array( '$value' => '4px' ),
			'button' => array( '$value' => '{radius.small}' ),
		);
		$tree['font-weight'] = array(
			'$type'   => 'fontWeight',
			'heading' => array( '$value' => 650 ),
		);
		$custom              = Compiler::compile( $tree, 't.json' )['custom'];

		$this->assertSame( '4px', $custom['radius']['button'] );
		$this->assertSame( 650, $custom['font-weight']['heading'] );
	}

	public function test_low_contrast_fails_with_ratio(): void {
		$tree                           = self::minimal();
		$tree['color']['muted']['$value'] = '#a5a5a5';

		$problems = self::problems( $tree );
		$this->assertCount( 1, $problems );
		$this->assertStringContainsString( 'color.muted on color.base is 2.46:1', $problems[0] );
	}

	public function test_missing_required_token_and_bad_alias_are_all_reported(): void {
		$tree = self::minimal();
		unset( $tree['spacing']['80'] );
		$tree['font-family']['heading']['$value'] = '{font-family.nope}';

		$problems = implode( "\n", self::problems( $tree ) );
		$this->assertStringContainsString( 'required token spacing.80 is missing', $problems );
		$this->assertStringContainsString( 'alias target {font-family.nope} does not exist', $problems );
	}

	public function test_alias_cycle_is_reported(): void {
		$tree                     = self::minimal();
		$tree['radius']           = array(
			'$type' => 'dimension',
			'a'     => array( '$value' => '{radius.b}' ),
			'b'     => array( '$value' => '{radius.a}' ),
		);
		$this->assertStringContainsString( 'alias cycle', implode( "\n", self::problems( $tree ) ) );
	}

	public function test_dtcg_color_object_and_alpha(): void {
		$this->assertSame( '#1e64e6', Css::color( array( 'colorSpace' => 'srgb', 'components' => array( 30 / 255, 100 / 255, 230 / 255 ) ) ) );
		$this->assertSame( '#16181d14', Css::color( '#16181d14' ) );
		$this->assertEqualsWithDelta( 5.22, Css::contrast( '#ffffff', '#1e64e6' ), 0.01 );
	}

	public function test_shadow_layers(): void {
		$this->assertSame(
			'0 1px 2px 0 #00000014, inset 0 0 0 1px #000000',
			Css::shadow(
				array(
					array(
						'color'   => '#00000014',
						'offsetX' => '0px',
						'offsetY' => '1px',
						'blur'    => '2px',
					),
					array(
						'color'   => '#000',
						'offsetX' => 0,
						'offsetY' => 0,
						'spread'  => '1px',
						'inset'   => true,
					),
				)
			)
		);
	}

	public function test_build_keeps_hand_written_theme_json_and_writes_variations(): void {
		$dir = sys_get_temp_dir() . '/pergel-tokens-' . bin2hex( random_bytes( 4 ) );
		mkdir( $dir . '/tokens', 0777, true );
		file_put_contents( $dir . '/tokens/default.json', (string) json_encode( self::minimal() ) );
		file_put_contents(
			$dir . '/tokens/dark.json',
			(string) json_encode(
				array(
					'$extensions' => array( Compiler::EXT => array( 'title' => 'Dark' ) ),
					'color'       => array(
						'base'      => array( '$value' => '#121417' ),
						'surface'   => array( '$value' => '#1c1f24' ),
						'contrast'  => array( '$value' => '#e8eaed' ),
						'muted'     => array( '$value' => '#a3a9b3' ),
						'accent'    => array( '$value' => '#4c8dff' ),
						'on-accent' => array( '$value' => '#0b0d10' ),
					),
				)
			)
		);
		file_put_contents( $dir . '/theme.json', '{"version":3,"settings":{"appearanceTools":true},"styles":{"color":{"text":"x"}}}' );

		$files = Compiler::build( $dir );
		$this->assertSame( array( 'theme.json', 'styles/dark.json' ), array_keys( $files ) );

		$theme = json_decode( $files['theme.json'], true );
		$this->assertTrue( $theme['settings']['appearanceTools'] );
		$this->assertSame( 'x', $theme['styles']['color']['text'] );
		$this->assertStringStartsWith( "{\n\t\"version\": 3", $files['theme.json'] );

		$dark = json_decode( $files['styles/dark.json'], true );
		$this->assertSame( 'Dark', $dark['title'] );
		$this->assertSame( '#121417', $dark['settings']['color']['palette'][0]['color'] );
		$this->assertCount( 7, $dark['settings']['spacing']['spacingSizes'], 'variation inherits untouched groups' );

		$this->assertSame( array( 'theme.json', 'styles/dark.json' ), Compiler::sync( $dir, $files, true ) );
		Compiler::sync( $dir, $files, false );
		$this->assertSame( array(), Compiler::sync( $dir, Compiler::build( $dir ), true ), 'idempotent' );

		array_map( 'unlink', array_merge( glob( "$dir/*/*.json" ) ?: array(), glob( "$dir/*.json" ) ?: array() ) );
		rmdir( "$dir/tokens" );
		rmdir( "$dir/styles" );
		rmdir( $dir );
	}

	public function test_child_theme_inherits_parent_tokens(): void {
		$root = sys_get_temp_dir() . '/pergel-child-' . bin2hex( random_bytes( 4 ) );
		mkdir( "$root/parent/tokens", 0777, true );
		mkdir( "$root/child/tokens", 0777, true );
		file_put_contents( "$root/parent/tokens/default.json", (string) json_encode( self::minimal() ) );
		file_put_contents( "$root/child/tokens/default.json", '{"color":{"accent":{"$value":"#1e64e6"}}}' );

		$theme   = json_decode( Compiler::build( "$root/child", "$root/parent" )['theme.json'], true );
		$palette = array_column( $theme['settings']['color']['palette'], 'color', 'slug' );
		$this->assertSame( '#1e64e6', $palette['accent'] );
		$this->assertSame( '#16181d', $palette['contrast'] );

		unlink( "$root/parent/tokens/default.json" );
		unlink( "$root/child/tokens/default.json" );
		rmdir( "$root/parent/tokens" );
		rmdir( "$root/child/tokens" );
		rmdir( "$root/parent" );
		rmdir( "$root/child" );
		rmdir( $root );
	}

	public function test_repository_theme_is_in_sync_with_its_tokens(): void {
		$dir = dirname( __DIR__, 3 ) . '/theme';
		$this->assertSame( array(), Compiler::sync( $dir, Compiler::build( $dir ), true ), 'run php tools/tokens.php' );
	}
}
