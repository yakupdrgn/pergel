<?php
/**
 * Compiles design tokens (docs/TOKENS.md) into theme.json presets and style variations.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tokens;

/**
 * Reads `<theme>/tokens/default.json` (merged over the parent theme's file
 * for child themes) and every other `tokens/<slug>.json` as a style
 * variation. Pure PHP with no WordPress calls, so CI can run it without a site.
 */
final class Compiler {

	/**
	 * Key for Pergel data inside DTCG `$extensions`.
	 */
	public const EXT = 'com.ariumlab.pergel';

	/**
	 * Token file format version this compiler understands.
	 */
	public const FORMAT = 1;

	/**
	 * Schema written into generated theme.json files.
	 */
	public const SCHEMA = 'https://schemas.wp.org/wp/7.1/theme.json';

	/**
	 * Preset groups: token group => [ expected $type, required slugs ].
	 * Templates and patterns may rely on every required slug existing.
	 */
	private const PRESETS = array(
		'color'       => array( 'color', array( 'base', 'surface', 'contrast', 'muted', 'accent', 'on-accent' ) ),
		'font-family' => array( 'fontFamily', array( 'body', 'heading', 'mono' ) ),
		'font-size'   => array( 'dimension', array( 'small', 'medium', 'large', 'x-large', 'xx-large' ) ),
		'spacing'     => array( 'dimension', array( '20', '30', '40', '50', '60', '70', '80' ) ),
		'shadow'      => array( 'shadow', array() ),
		'layout'      => array( 'dimension', array( 'content', 'wide' ) ),
	);

	/**
	 * Text/background pairs that must reach WCAG AA (4.5:1) in every token set.
	 */
	private const CONTRAST = array(
		array( 'contrast', 'base', 4.5 ),
		array( 'contrast', 'surface', 4.5 ),
		array( 'muted', 'base', 4.5 ),
		array( 'muted', 'surface', 4.5 ),
		array( 'accent', 'base', 4.5 ),
		array( 'accent', 'surface', 4.5 ),
		array( 'on-accent', 'accent', 4.5 ),
	);

	/**
	 * Viewport range for fluid spacing: from this width up to the wide layout size.
	 */
	private const FLUID_MIN_VIEWPORT = 360.0;

	/**
	 * Builds every output file of a theme.
	 *
	 * @param string      $theme_dir  Theme directory containing tokens/default.json.
	 * @param string|null $parent_dir Parent theme directory for child themes.
	 * @return array<string, string> Relative path => file contents.
	 * @throws Invalid When a token file has problems.
	 */
	public static function build( string $theme_dir, ?string $parent_dir = null ): array {
		$theme_dir = rtrim( $theme_dir, '/\\' );
		$own       = $theme_dir . '/tokens/default.json';
		$inherited = null !== $parent_dir ? rtrim( $parent_dir, '/\\' ) . '/tokens/default.json' : null;
		if ( null !== $inherited && is_file( $inherited ) ) {
			// A child theme's default.json only lists what it changes.
			$base = is_file( $own ) ? self::merge( self::read_json( $inherited ), self::read_json( $own ) ) : self::read_json( $inherited );
		} else {
			$base = self::read_json( $own );
		}

		$theme_json = is_file( $theme_dir . '/theme.json' )
			? self::read_json( $theme_dir . '/theme.json' )
			: array(
				'$schema' => self::SCHEMA,
				'version' => 3,
			);

		$dirs = null !== $parent_dir ? array( $theme_dir, rtrim( $parent_dir, '/\\' ) ) : array( $theme_dir );

		$settings = self::compile( $base, 'tokens/default.json' );
		self::check_font_files( $settings, 'tokens/default.json', $dirs );
		$out = array(
			'theme.json' => self::encode( self::apply( $theme_json, $settings ) ),
		);

		$files = glob( $theme_dir . '/tokens/*.json' );
		foreach ( false === $files ? array() : $files as $file ) {
			$slug = basename( $file, '.json' );
			if ( 'default' === $slug ) {
				continue;
			}
			$raw   = self::read_json( $file );
			$title = $raw['$extensions'][ self::EXT ]['title'] ?? null;
			if ( ! is_string( $title ) || '' === $title ) {
				throw new Invalid( array( "tokens/$slug.json: \$extensions.\"" . self::EXT . '".title is required for a style variation' ) );
			}
			$variation = array(
				'$schema' => self::SCHEMA,
				'version' => 3,
				'title'   => $title,
			);
			// Style rules a token cannot express (e.g. uppercase buttons) are copied as written.
			$styles = $raw['$extensions'][ self::EXT ]['styles'] ?? null;
			if ( null !== $styles ) {
				if ( ! is_array( $styles ) || array_is_list( $styles ) ) {
					throw new Invalid( array( "tokens/$slug.json: \$extensions.\"" . self::EXT . '".styles must be a theme.json styles object' ) );
				}
				$variation['styles'] = $styles;
			}

			$settings = self::compile( self::merge( $base, $raw ), "tokens/$slug.json" );
			self::check_font_files( $settings, "tokens/$slug.json", $dirs );
			$out[ "styles/$slug.json" ] = self::encode( self::apply( $variation, $settings ) );
		}

		return $out;
	}

	/**
	 * Writes built files that differ from disk, or only lists them in check mode.
	 *
	 * @param string                $theme_dir Theme directory.
	 * @param array<string, string> $files     Output of build().
	 * @param bool                  $check     List differences without writing.
	 * @return list<string> Relative paths that differ (or were written).
	 * @throws \RuntimeException When a file cannot be written.
	 */
	public static function sync( string $theme_dir, array $files, bool $check ): array {
		$changed = array();
		foreach ( $files as $relative => $contents ) {
			$path    = rtrim( $theme_dir, '/\\' ) . '/' . $relative;
			$current = is_file( $path ) ? str_replace( "\r\n", "\n", (string) file_get_contents( $path ) ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, CLI only.
			if ( $current === $contents ) {
				continue;
			}
			$changed[] = $relative;
			if ( $check ) {
				continue;
			}
			if ( ! is_dir( dirname( $path ) ) && ! mkdir( dirname( $path ), 0755, true ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- CLI only, no WP_Filesystem in CI.
				throw new \RuntimeException( "Cannot create directory for $relative" );
			}
			if ( false === file_put_contents( $path, $contents ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI only, no WP_Filesystem in CI.
				throw new \RuntimeException( "Cannot write $relative" );
			}
		}
		return $changed;
	}

	/**
	 * Compiles one merged token tree into theme.json settings.
	 *
	 * @param array<string, mixed> $tree  Token tree.
	 * @param string               $label File name used in error messages.
	 * @return array<string, mixed> Settings fragment.
	 * @throws Invalid When tokens have problems.
	 */
	public static function compile( array $tree, string $label ): array {
		$problems = array();
		$format   = $tree['$extensions'][ self::EXT ]['format'] ?? self::FORMAT;
		if ( self::FORMAT !== $format ) {
			throw new Invalid( array( "$label: unsupported format $format (this engine reads format " . self::FORMAT . ')' ) );
		}

		$tokens = array();
		self::collect( $tree, array(), null, $tokens, $problems, $label );
		$values = array();
		foreach ( array_keys( $tokens ) as $path ) {
			try {
				$values[ $path ] = self::resolve( $path, $tokens, array() );
			} catch ( \InvalidArgumentException $e ) {
				$problems[] = "$label: $path: " . $e->getMessage();
			}
		}

		$settings = array();
		$custom   = array();
		foreach ( $tokens as $path => $token ) {
			if ( ! isset( $values[ $path ] ) ) {
				continue;
			}
			$segments = explode( '.', $path );
			$group    = $segments[0];
			$value    = $values[ $path ];
			$ext      = is_array( $token['$extensions'][ self::EXT ] ?? null ) ? $token['$extensions'][ self::EXT ] : array();

			try {
				if ( isset( self::PRESETS[ $group ] ) ) {
					if ( 2 !== count( $segments ) ) {
						$problems[] = "$label: $path: preset groups cannot be nested";
						continue;
					}
					if ( self::PRESETS[ $group ][0] !== $token['$type'] ) {
						$problems[] = "$label: $path: expected \$type " . self::PRESETS[ $group ][0] . ', got ' . $token['$type'];
						continue;
					}
					self::add_preset( $settings, $group, $segments[1], $value, $ext, $values );
				} else {
					$custom = self::set_path( $custom, $segments, self::css( (string) $token['$type'], $value ) );
				}
			} catch ( \InvalidArgumentException $e ) {
				$problems[] = "$label: $path: " . $e->getMessage();
			}
		}
		if ( array() !== $custom ) {
			$settings['custom'] = $custom;
		}

		foreach ( self::PRESETS as $group => [ , $required ] ) {
			foreach ( $required as $slug ) {
				if ( ! isset( $tokens[ "$group.$slug" ] ) ) {
					$problems[] = "$label: required token $group.$slug is missing";
				}
			}
		}

		foreach ( self::CONTRAST as [ $fg, $bg, $min ] ) {
			if ( ! isset( $values[ "color.$fg" ], $values[ "color.$bg" ] ) ) {
				continue;
			}
			try {
				$ratio = Css::contrast( $values[ "color.$fg" ], $values[ "color.$bg" ] );
				if ( $ratio < $min ) {
					$problems[] = sprintf( '%s: contrast of color.%s on color.%s is %.2f:1, needs %.1f:1 (WCAG AA)', $label, $fg, $bg, $ratio, $min );
				}
			} catch ( \InvalidArgumentException $e ) {
				$problems[] = "$label: color.$fg on color.$bg: " . $e->getMessage();
			}
		}

		if ( array() !== $problems ) {
			throw new Invalid( $problems );
		}
		return $settings;
	}

	/**
	 * Walks the tree; a node with `$value` is a token, any other object a group.
	 * Group `$type` is inherited by descendants (DTCG).
	 *
	 * @param array<string, mixed>        $node     Current node.
	 * @param string[]                    $path Path to the node.
	 * @phpstan-param list<string> $path
	 * @param string|null                 $type     Inherited $type.
	 * @param array<string, array<mixed>> $tokens   Collected tokens by dotted path.
	 * @param string[]                    $problems Problems found.
	 * @phpstan-param list<string> $problems
	 * @param string                      $label    File name for messages.
	 */
	private static function collect( array $node, array $path, ?string $type, array &$tokens, array &$problems, string $label ): void {
		$type = isset( $node['$type'] ) && is_string( $node['$type'] ) ? $node['$type'] : $type;
		foreach ( $node as $key => $child ) {
			$key = (string) $key;
			if ( str_starts_with( $key, '$' ) ) {
				continue;
			}
			$child_path = array_merge( $path, array( $key ) );
			$dotted     = implode( '.', $child_path );
			if ( ! preg_match( '/^[a-z0-9][a-z0-9-]*$/', $key ) ) {
				$problems[] = "$label: $dotted: names may only contain a-z, 0-9 and '-'";
				continue;
			}
			if ( ! is_array( $child ) ) {
				$problems[] = "$label: $dotted: expected a token or group object";
				continue;
			}
			if ( array_key_exists( '$value', $child ) ) {
				$child['$type'] = isset( $child['$type'] ) && is_string( $child['$type'] ) ? $child['$type'] : $type;
				if ( null === $child['$type'] ) {
					$problems[] = "$label: $dotted: \$type is missing (set it on the token or its group)";
					continue;
				}
				$tokens[ $dotted ] = $child;
			} else {
				self::collect( $child, $child_path, $type, $tokens, $problems, $label );
			}
		}
	}

	/**
	 * Resolves `{group.token}` aliases recursively.
	 *
	 * @param string                      $path   Token path.
	 * @param array<string, array<mixed>> $tokens All tokens.
	 * @param string[]                    $seen Paths on the current alias chain.
	 * @phpstan-param list<string> $seen
	 * @return mixed Raw (unformatted) value.
	 * @throws \InvalidArgumentException On unknown targets, type mismatch or cycles.
	 */
	private static function resolve( string $path, array $tokens, array $seen ): mixed {
		$value = $tokens[ $path ]['$value'];
		if ( ! is_string( $value ) || ! preg_match( '/^\{([a-z0-9.-]+)\}$/', $value, $m ) ) {
			return $value;
		}
		$target = $m[1];
		if ( in_array( $target, $seen, true ) || $target === $path ) {
			throw new \InvalidArgumentException( 'alias cycle: ' . implode( ' -> ', array_merge( $seen, array( $path, $target ) ) ) );
		}
		if ( ! isset( $tokens[ $target ] ) ) {
			throw new \InvalidArgumentException( "alias target {{$target}} does not exist" );
		}
		if ( $tokens[ $target ]['$type'] !== $tokens[ $path ]['$type'] ) {
			throw new \InvalidArgumentException( "alias target {{$target}} has \$type " . $tokens[ $target ]['$type'] );
		}
		return self::resolve( $target, $tokens, array_merge( $seen, array( $path ) ) );
	}

	/**
	 * Adds one preset token to the settings fragment.
	 *
	 * @param array<string, mixed> $settings Settings fragment.
	 * @param string               $group    Token group.
	 * @param string               $slug     Preset slug.
	 * @param mixed                $value    Resolved value.
	 * @param array<string, mixed> $ext      Pergel extension data of the token.
	 * @param array<string, mixed> $values   All resolved values (for layout.wide).
	 * @throws \InvalidArgumentException On invalid values.
	 */
	private static function add_preset( array &$settings, string $group, string $slug, mixed $value, array $ext, array $values ): void {
		$name = isset( $ext['name'] ) && is_string( $ext['name'] ) ? $ext['name'] : ucfirst( str_replace( '-', ' ', $slug ) );

		switch ( $group ) {
			case 'color':
				$settings['color']['palette'][] = array(
					'slug'  => $slug,
					'name'  => $name,
					'color' => Css::color( $value ),
				);
				break;

			case 'font-family':
				$family = array(
					'slug'       => $slug,
					'name'       => $name,
					'fontFamily' => Css::font_family( $value ),
				);
				if ( isset( $ext['fontFace'] ) ) {
					$family['fontFace'] = self::font_faces( $ext['fontFace'], Css::font_family( $value ) );
				}
				$settings['typography']['fontFamilies'][] = $family;
				break;

			case 'font-size':
				$size  = array(
					'slug' => $slug,
					'name' => $name,
					'size' => Css::dimension( $value ),
				);
				$fluid = $ext['fluid'] ?? null;
				if ( is_array( $fluid ) && isset( $fluid['min'] ) ) {
					if ( Css::to_px( $fluid['min'] ) > Css::to_px( $value ) ) {
						throw new \InvalidArgumentException( 'fluid min is larger than the value' );
					}
					$size['fluid'] = array(
						'min' => Css::dimension( $fluid['min'] ),
						'max' => Css::dimension( $value ),
					);
				} else {
					$size['fluid'] = false;
				}
				$settings['typography']['fontSizes'][] = $size;
				break;

			case 'spacing':
				$fluid = $ext['fluid'] ?? null;
				$wide  = isset( $values['layout.wide'] ) ? Css::to_px( $values['layout.wide'] ) : 1240.0;

				$settings['spacing']['spacingSizes'][] = array(
					'slug' => $slug,
					'name' => $name,
					'size' => is_array( $fluid ) && isset( $fluid['min'] )
						? Css::fluid( $fluid['min'], $value, self::FLUID_MIN_VIEWPORT, $wide )
						: Css::dimension( $value ),
				);
				break;

			case 'shadow':
				$settings['shadow']['presets'][] = array(
					'slug'   => $slug,
					'name'   => $name,
					'shadow' => Css::shadow( $value ),
				);
				break;

			case 'layout':
				if ( ! in_array( $slug, array( 'content', 'wide' ), true ) ) {
					throw new \InvalidArgumentException( 'layout only has "content" and "wide"' );
				}
				$settings['layout'][ $slug . 'Size' ] = Css::dimension( $value );
				break;
		}
	}

	/**
	 * Validates fontFace entries and fills in the family name.
	 *
	 * @param mixed  $faces  Raw fontFace list.
	 * @param string $family CSS family stack; the first name is used.
	 * @return list<array<string, mixed>>
	 * @throws \InvalidArgumentException When an entry is malformed.
	 */
	private static function font_faces( mixed $faces, string $family ): array {
		if ( ! is_array( $faces ) || ! array_is_list( $faces ) ) {
			throw new \InvalidArgumentException( 'fontFace must be a list' );
		}
		$first = trim( explode( ',', $family )[0], '"' );
		$out   = array();
		foreach ( $faces as $face ) {
			if ( ! is_array( $face ) || ! isset( $face['src'] ) ) {
				throw new \InvalidArgumentException( 'each fontFace needs src' );
			}
			$src = (array) $face['src'];
			foreach ( $src as $url ) {
				if ( ! is_string( $url ) || ! str_starts_with( $url, 'file:./' ) || ! str_ends_with( $url, '.woff2' ) || str_contains( $url, '..' ) ) {
					throw new \InvalidArgumentException( 'fontFace src must be a local woff2 file ("file:./assets/fonts/name.woff2")' );
				}
			}
			$out[] = array_merge(
				array(
					'fontFamily'  => $first,
					'fontDisplay' => 'swap',
				),
				$face,
				array( 'src' => array_values( $src ) )
			);
		}
		return $out;
	}

	/**
	 * Checks that every fontFace file exists in the theme or, for child themes,
	 * in the parent (WordPress resolves `file:./` the same way).
	 *
	 * @param array<string, mixed> $settings Compiled settings.
	 * @param string               $label    File name for messages.
	 * @param string[]             $dirs     Theme directory first, then the parent.
	 * @throws Invalid When a file is missing.
	 */
	private static function check_font_files( array $settings, string $label, array $dirs ): void {
		$problems = array();
		$families = $settings['typography']['fontFamilies'] ?? array();
		foreach ( is_array( $families ) ? $families : array() as $family ) {
			foreach ( $family['fontFace'] ?? array() as $face ) {
				foreach ( $face['src'] as $src ) {
					$relative = substr( $src, strlen( 'file:./' ) );
					$found    = false;
					foreach ( $dirs as $dir ) {
						$found = $found || is_file( "$dir/$relative" );
					}
					if ( ! $found ) {
						$problems[] = "$label: font-family.{$family['slug']}: $src does not exist in the theme";
					}
				}
			}
		}
		if ( array() !== $problems ) {
			throw new Invalid( $problems );
		}
	}

	/**
	 * Formats a custom (non-preset) token value for settings.custom.
	 *
	 * @param string $type  Token $type.
	 * @param mixed  $value Resolved value.
	 * @return string|int|float
	 * @throws \InvalidArgumentException On unsupported types or values.
	 */
	private static function css( string $type, mixed $value ): string|int|float {
		switch ( $type ) {
			case 'color':
				return Css::color( $value );
			case 'dimension':
				return Css::dimension( $value );
			case 'fontFamily':
				return Css::font_family( $value );
			case 'shadow':
				return Css::shadow( $value );
			case 'fontWeight':
				if ( is_int( $value ) && $value >= 1 && $value <= 1000 ) {
					return $value;
				}
				throw new \InvalidArgumentException( 'fontWeight must be an integer between 1 and 1000' );
			case 'number':
				if ( is_int( $value ) || is_float( $value ) ) {
					return $value;
				}
				throw new \InvalidArgumentException( 'expected a number' );
		}
		throw new \InvalidArgumentException( "unsupported \$type $type" );
	}

	/**
	 * Writes compiled settings into a theme.json array. Token-owned keys are
	 * replaced; everything else (styles, flags, templateParts) is kept.
	 *
	 * @param array<string, mixed> $json     theme.json data.
	 * @param array<string, mixed> $settings Compiled settings.
	 * @return array<string, mixed>
	 */
	public static function apply( array $json, array $settings ): array {
		$current = is_array( $json['settings'] ?? null ) ? $json['settings'] : array();
		$owned   = array(
			array( 'color', 'palette' ),
			array( 'typography', 'fontFamilies' ),
			array( 'typography', 'fontSizes' ),
			array( 'spacing', 'spacingSizes' ),
			array( 'shadow', 'presets' ),
			array( 'layout', 'contentSize' ),
			array( 'layout', 'wideSize' ),
		);
		foreach ( $owned as [ $section, $key ] ) {
			if ( isset( $settings[ $section ][ $key ] ) ) {
				$current[ $section ][ $key ] = $settings[ $section ][ $key ];
			}
		}
		if ( isset( $settings['shadow']['presets'] ) ) {
			$current['shadow']['defaultPresets'] = false;
		}
		if ( isset( $settings['custom'] ) && is_array( $settings['custom'] ) ) {
			$existing          = is_array( $current['custom'] ?? null ) ? $current['custom'] : array();
			$current['custom'] = array_merge( $existing, $settings['custom'] );
		}
		$json['settings'] = $current;
		return $json;
	}

	/**
	 * Deep-merges token trees: objects merge key by key, anything else
	 * (including a token's `$value`) is replaced.
	 *
	 * @param array<string, mixed> $base     Base tree.
	 * @param array<string, mixed> $override Override tree.
	 * @return array<string, mixed>
	 */
	public static function merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( '$value' !== $key && is_array( $value ) && ! array_is_list( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) && ! array_is_list( $base[ $key ] ) ) {
				$base[ $key ] = self::merge( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Sets a nested value by path.
	 *
	 * @param array<string, mixed> $tree     Tree.
	 * @param string[]             $segments Path.
	 * @phpstan-param list<string> $segments
	 * @param mixed                $value    Value.
	 * @return array<string, mixed>
	 */
	private static function set_path( array $tree, array $segments, mixed $value ): array {
		$key = array_shift( $segments );
		if ( array() === $segments ) {
			$tree[ $key ] = $value;
			return $tree;
		}
		$tree[ $key ] = self::set_path( is_array( $tree[ $key ] ?? null ) ? $tree[ $key ] : array(), $segments, $value );
		return $tree;
	}

	/**
	 * Reads a JSON object file.
	 *
	 * @param string $file Path.
	 * @return array<string, mixed>
	 * @throws Invalid When the file is missing or not a JSON object.
	 */
	public static function read_json( string $file ): array {
		$raw = is_file( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, runs only under WP-CLI or CI.
		if ( false === $raw ) {
			throw new Invalid( array( "$file: file not found" ) );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || array_is_list( $data ) ) {
			throw new Invalid( array( "$file: not a JSON object (" . json_last_error_msg() . ')' ) );
		}
		return $data;
	}

	/**
	 * Encodes like the WordPress repo's JSON files: tab indent, unescaped
	 * slashes and Unicode, trailing newline.
	 *
	 * @param array<string, mixed> $data Data.
	 */
	public static function encode( array $data ): string {
		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;
		$json  = (string) json_encode( $data, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- runs without WordPress in CI.
		$json  = (string) preg_replace_callback(
			'/^(?: {4})+/m',
			static fn ( array $m ): string => str_repeat( "\t", (int) ( strlen( $m[0] ) / 4 ) ),
			$json
		);
		return $json . "\n";
	}
}
