<?php
/**
 * Rules for the theme's pattern files.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Patterns;

/**
 * Checks one pattern file against the library's rules, without WordPress
 * (CI lint job, `php tools/patterns.php --check`):
 *
 * - The header names the pattern: Title, Slug `pergel/<file name>`, known
 *   Categories and a Description (hidden patterns need only Title and Slug).
 * - Only core and Pergel blocks; no Custom HTML, Classic or Shortcode block.
 *   Hidden shop patterns (`pergel/hidden-product-*`), which only the shop's
 *   templates use, may also hold WooCommerce blocks.
 * - No hard-coded text: every visible string, alt text and label comes from a
 *   translation function with the `pergel` text domain.
 * - At most one H1, and only in hero (banner) and full-page patterns.
 * - One root block, so the editor inserts it as one section that opens for
 *   content editing only. Full pages are a list of pattern blocks.
 * - No external URLs, scripts, styles or stylesheet links; images have alt.
 */
final class Rules {

	/**
	 * Core categories the library uses, and Pergel's own.
	 */
	public const CATEGORIES = array(
		'about',
		'banner',
		'call-to-action',
		'contact',
		'footer',
		'gallery',
		'header',
		'portfolio',
		'posts',
		'services',
		'team',
		'testimonials',
		'pergel-faq',
		'pergel-logos',
		'pergel-pages',
		'pergel-pricing',
		'pergel-process',
		'pergel-stats',
	);

	/**
	 * Categories whose patterns may carry the page's H1.
	 */
	public const H1_CATEGORIES = array( 'banner', 'pergel-pages' );

	/**
	 * Core blocks that hold free HTML or legacy content.
	 */
	public const BANNED_BLOCKS = array( 'core/html', 'core/freeform', 'core/shortcode', 'core/missing' );

	/**
	 * Slug prefix of the hidden patterns that may hold WooCommerce blocks.
	 */
	public const SHOP_PREFIX = 'pergel/hidden-product-';

	/**
	 * Block attributes that hold text a visitor sees or hears.
	 */
	public const TEXT_ATTRIBUTES = array( 'alt', 'ariaLabel', 'buttonText', 'caption', 'content', 'label', 'name', 'placeholder', 'query', 'submitLabel', 'successMessage', 'text', 'title' );

	/**
	 * Translation functions and the position of their text domain argument.
	 */
	private const I18N = array(
		'__'         => 1,
		'_e'         => 1,
		'_x'         => 2,
		'_ex'        => 2,
		'esc_html__' => 1,
		'esc_html_e' => 1,
		'esc_html_x' => 2,
		'esc_attr__' => 1,
		'esc_attr_e' => 1,
		'esc_attr_x' => 2,
		'_n'         => 3,
	);

	/**
	 * Header fields of a pattern file.
	 *
	 * @param string $source File contents.
	 * @return array<string, string>
	 */
	public static function header( string $source ): array {
		$fields = array();
		if ( 1 === preg_match( '#/\*\*(.*?)\*/#s', $source, $doc ) ) {
			preg_match_all( '/^\s*\*\s*([A-Z][A-Za-z ]+):\s*(.+?)\s*$/m', $doc[1], $lines, PREG_SET_ORDER );
			foreach ( $lines as $line ) {
				$fields[ $line[1] ] = $line[2];
			}
		}
		return $fields;
	}

	/**
	 * Categories listed in a header.
	 *
	 * @param array<string, string> $header Header fields.
	 * @return list<string>
	 */
	public static function categories( array $header ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', $header['Categories'] ?? '' ) ) ) );
	}

	/**
	 * The block markup: everything after the PHP preamble.
	 *
	 * @param string $source File contents.
	 */
	public static function markup( string $source ): string {
		$end = strpos( $source, '?>' );
		return false === $end ? '' : substr( $source, $end + 2 );
	}

	/**
	 * Markup with the PHP output removed: what is left is literal.
	 *
	 * @param string $markup Block markup.
	 */
	public static function literal( string $markup ): string {
		return (string) preg_replace( '/<\?php.*?\?>/s', '', $markup );
	}

	/**
	 * Block names in order, with core/ added to bare names.
	 *
	 * @param string $markup Block markup.
	 * @return list<string>
	 */
	public static function blocks( string $markup ): array {
		preg_match_all( '#<!--\s+wp:([a-z][a-z0-9_-]*(?:/[a-z][a-z0-9_-]*)?)#', $markup, $found );
		return array_map( static fn( string $name ): string => str_contains( $name, '/' ) ? $name : 'core/' . $name, $found[1] );
	}

	/**
	 * Root blocks: blocks that open while no other block is open.
	 *
	 * @param string $markup Block markup.
	 * @return list<string>
	 */
	public static function roots( string $markup ): array {
		preg_match_all( '#<!--\s+(/?)wp:([a-z][a-z0-9_/-]*)(?:\s+(?:\{.*?\}\s+)?)?(/?)-->#s', $markup, $found, PREG_SET_ORDER );
		$depth = 0;
		$roots = array();
		foreach ( $found as $comment ) {
			if ( '/' === $comment[1] ) {
				--$depth;
				continue;
			}
			if ( 0 === $depth ) {
				$roots[] = str_contains( $comment[2], '/' ) ? $comment[2] : 'core/' . $comment[2];
			}
			if ( '/' !== $comment[3] ) {
				++$depth;
			}
		}
		return $roots;
	}

	/**
	 * Text domains passed to translation functions; null where none is.
	 *
	 * @param string $source File contents.
	 * @return list<string|null>
	 */
	public static function domains( string $source ): array {
		$tokens  = token_get_all( $source );
		$domains = array();
		$count   = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( self::I18N[ $token[1] ] ) ) {
				continue;
			}
			$args  = array( '' );
			$depth = 0;
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$part = $tokens[ $j ];
				$text = is_array( $part ) ? $part[1] : $part;
				if ( '(' === $text ) {
					++$depth;
					if ( 1 === $depth ) {
						continue;
					}
				} elseif ( ')' === $text ) {
					--$depth;
					if ( 0 === $depth ) {
						break;
					}
				} elseif ( ',' === $text && 1 === $depth ) {
					$args[] = '';
					continue;
				}
				if ( $depth >= 1 && ( ! is_array( $part ) || T_WHITESPACE !== $part[0] ) ) {
					$args[ count( $args ) - 1 ] .= $text;
				}
			}
			$domain    = $args[ self::I18N[ $token[1] ] ] ?? null;
			$domains[] = null === $domain ? null : trim( $domain, '\'"' );
		}
		return $domains;
	}

	/**
	 * Problems in one pattern file; empty when it follows the rules.
	 *
	 * @param string $file   File name, e.g. hero-split.php.
	 * @param string $source File contents.
	 * @return list<string>
	 */
	public static function check( string $file, string $source ): array {
		$errors = array();
		$header = self::header( $source );
		$slug   = 'pergel/' . basename( $file, '.php' );
		$hidden = 'no' === strtolower( $header['Inserter'] ?? '' );
		$shop   = $hidden && str_starts_with( $slug, self::SHOP_PREFIX );

		if ( '' === ( $header['Title'] ?? '' ) ) {
			$errors[] = 'Missing Title.';
		}
		if ( ( $header['Slug'] ?? '' ) !== $slug ) {
			$errors[] = "Slug must be {$slug}.";
		}
		$categories = self::categories( $header );
		if ( ! $hidden ) {
			if ( array() === $categories ) {
				$errors[] = 'Missing Categories.';
			}
			if ( '' === ( $header['Description'] ?? '' ) ) {
				$errors[] = 'Missing Description.';
			}
		}
		foreach ( array_diff( $categories, self::CATEGORIES ) as $unknown ) {
			$errors[] = "Unknown category {$unknown}.";
		}
		if ( ! str_contains( $source, "defined( 'ABSPATH' ) || exit;" ) ) {
			$errors[] = "Missing defined( 'ABSPATH' ) || exit;.";
		}

		$markup  = self::markup( $source );
		$literal = self::literal( $markup );
		if ( '' === trim( $markup ) ) {
			$errors[] = 'No block markup.';
			return $errors;
		}

		foreach ( array_unique( self::blocks( $markup ) ) as $block ) {
			if ( in_array( $block, self::BANNED_BLOCKS, true ) ) {
				$errors[] = "Block {$block} is not allowed.";
			} elseif ( ! str_starts_with( $block, 'core/' ) && ! str_starts_with( $block, 'pergel/' ) && ! ( $shop && str_starts_with( $block, 'woocommerce/' ) ) ) {
				$errors[] = "Block {$block} is not a core or Pergel block.";
			}
		}

		$text = (string) preg_replace( array( '/<!--.*?-->/s', '/<[^>]*>/', '/&[a-z0-9#]+;/i' ), ' ', $literal );
		if ( 1 === preg_match( '/\p{L}[\p{L}\'’ -]*/u', $text, $words ) ) {
			$errors[] = 'Hard-coded text: "' . trim( $words[0] ) . '".';
		}
		if ( 1 === preg_match( '/\b(alt|title|aria-label|placeholder|value)="([^"]*\p{L}[^"]*)"/u', (string) preg_replace( '/<!--.*?-->/s', '', $literal ), $attribute ) ) {
			$errors[] = "Hard-coded {$attribute[1]}: \"{$attribute[2]}\".";
		}
		$keys = implode( '|', self::TEXT_ATTRIBUTES );
		if ( 1 === preg_match( '/"(' . $keys . ')":"(?!var:)((?:[^"\\\\]|\\\\.)*\p{L}(?:[^"\\\\]|\\\\.)*)"/u', $literal, $attribute ) ) {
			$errors[] = "Hard-coded block attribute {$attribute[1]}: \"{$attribute[2]}\".";
		}
		foreach ( self::domains( $source ) as $domain ) {
			if ( 'pergel' !== $domain ) {
				$errors[] = 'Translation without the pergel text domain.';
				break;
			}
		}

		$h1 = preg_match_all( '/<h1[\s>]/', $markup );
		if ( $h1 > 1 ) {
			$errors[] = 'More than one H1.';
		}
		if ( $h1 > 0 && ! $hidden && array() === array_intersect( $categories, self::H1_CATEGORIES ) ) {
			$errors[] = 'An H1 belongs only in hero (banner) and full-page patterns.';
		}

		$roots = self::roots( $markup );
		if ( ! $hidden && count( $roots ) > 1 && array( 'core/pattern' ) !== array_values( array_unique( $roots ) ) ) {
			$errors[] = 'One root block per section pattern (found ' . count( $roots ) . '); full pages list pattern blocks.';
		}

		if ( 1 === preg_match( '/\b(?:src|href|srcset|poster)="(?:https?:)?\/\//i', $literal ) || 1 === preg_match( '/"(?:url|href|src)":"(?:https?:)?\\\\?\/\\\\?\//i', $literal ) ) {
			$errors[] = 'External URL; use theme files (get_theme_file_uri) or "#".';
		}
		if ( 1 === preg_match( '/<(script|style|link|iframe)\b/i', $markup, $tag ) ) {
			$errors[] = "No <{$tag[1]}> in patterns.";
		}
		if ( 1 === preg_match( '/<img\b(?![^>]*\balt=)[^>]*>/i', $literal ) ) {
			$errors[] = 'Image without alt.';
		}

		return $errors;
	}

	/**
	 * Preset slugs a theme.json defines, by preset type.
	 *
	 * @param array<string, mixed> $theme_json Decoded theme.json.
	 * @return array<string, list<string>> color, font-size, font-family, spacing, shadow => slugs.
	 */
	public static function presets( array $theme_json ): array {
		$settings = $theme_json['settings'] ?? array();
		$lists    = array(
			'color'       => $settings['color']['palette'] ?? array(),
			'font-size'   => $settings['typography']['fontSizes'] ?? array(),
			'font-family' => $settings['typography']['fontFamilies'] ?? array(),
			'spacing'     => $settings['spacing']['spacingSizes'] ?? array(),
			'shadow'      => $settings['shadow']['presets'] ?? array(),
		);
		$slugs    = array();
		foreach ( $lists as $type => $list ) {
			$slugs[ $type ] = array();
			foreach ( is_array( $list ) ? $list : array() as $preset ) {
				if ( is_array( $preset ) && isset( $preset['slug'] ) && is_string( $preset['slug'] ) ) {
					$slugs[ $type ][] = $preset['slug'];
				}
			}
		}
		return $slugs;
	}

	/**
	 * Preset references in a pattern that the theme does not define; a renamed
	 * or missing preset fails silently in the browser.
	 *
	 * @param string                      $source  File contents.
	 * @param array<string, list<string>> $presets Output of presets().
	 * @return list<string>
	 */
	public static function unknown_presets( string $source, array $presets ): array {
		$markup = self::markup( $source );
		$found  = array();
		foreach ( array( '/var:preset\|([a-z-]+)\|([a-z0-9-]+)/', '/var\(--wp--preset--([a-z-]+)--([a-z0-9-]+)\)/' ) as $pattern ) {
			if ( preg_match_all( $pattern, $markup, $refs, PREG_SET_ORDER ) ) {
				foreach ( $refs as $ref ) {
					$found[] = array( $ref[1], $ref[2] );
				}
			}
		}
		$named = array(
			'color'       => 'backgroundColor|textColor|borderColor|overlayColor',
			'font-size'   => 'fontSize',
			'font-family' => 'fontFamily',
		);
		foreach ( $named as $type => $keys ) {
			if ( preg_match_all( '/"(?:' . $keys . ')":"([a-z][a-z0-9-]*)"/', $markup, $refs ) ) {
				foreach ( $refs[1] as $slug ) {
					$found[] = array( $type, $slug );
				}
			}
		}

		$errors = array();
		foreach ( $found as [ $type, $slug ] ) {
			if ( isset( $presets[ $type ] ) && ! in_array( $slug, $presets[ $type ], true ) ) {
				$errors[] = "Unknown {$type} preset {$slug}.";
			}
		}
		return array_values( array_unique( $errors ) );
	}
}
