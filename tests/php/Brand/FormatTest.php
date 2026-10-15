<?php
/**
 * Brand formatting tests.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Tests\Brand;

use Pergel\Brand\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers tel:/mailto: links, the address line, social networks and the
 * Organization structure.
 */
final class FormatTest extends TestCase {

	/**
	 * Display numbers and their tel: links.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function phones(): array {
		return array(
			'international' => array( '+90 (212) 000 00 00', 'tel:+902120000000' ),
			'local'         => array( '0212 000-00-00', 'tel:02120000000' ),
			'leading space' => array( ' +372 600 0000', 'tel:+3726000000' ),
			'dots'          => array( '+1.555.010.0000', 'tel:+15550100000' ),
			'empty'         => array( '', '' ),
			'no digits'     => array( '+ ( ) -', '' ),
		);
	}

	#[DataProvider( 'phones' )]
	public function test_tel_uri( string $phone, string $expected ): void {
		$this->assertSame( $expected, Format::tel_uri( $phone ) );
	}

	public function test_mailto(): void {
		$this->assertSame( 'mailto:info@example.com', Format::mailto( 'info@example.com' ) );
		$this->assertSame( '', Format::mailto( '' ) );
	}

	public function test_address_joins_filled_parts(): void {
		$full = array(
			'street'      => 'Örnek Sok. 1',
			'postal_code' => '34000',
			'locality'    => 'Kadıköy',
			'region'      => 'İstanbul',
			'country'     => 'TR',
		);
		$this->assertSame( 'Örnek Sok. 1, 34000 Kadıköy, İstanbul', Format::address( $full ) );
		$this->assertSame( 'Kadıköy, İstanbul', Format::address( array( 'locality' => 'Kadıköy', 'region' => 'İstanbul' ) ) );
		$this->assertSame( '34000', Format::address( array( 'postal_code' => '34000' ) ) );
		$this->assertSame( '', Format::address( array( 'country' => 'TR' ) ) );
		$this->assertSame( '', Format::address( array( 'street' => array( 'not text' ) ) ) );
	}

	/**
	 * Profile URLs and the network they belong to.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function profiles(): array {
		return array(
			'linkedin'        => array( 'https://www.linkedin.com/company/example', 'linkedin' ),
			'country host'    => array( 'https://tr.linkedin.com/in/example', 'linkedin' ),
			'instagram'       => array( 'https://instagram.com/example', 'instagram' ),
			'youtube short'   => array( 'https://youtu.be/abc', 'youtube' ),
			'youtube mobile'  => array( 'https://m.youtube.com/@example', 'youtube' ),
			'x'               => array( 'https://x.com/example', 'x' ),
			'twitter'         => array( 'https://twitter.com/example', 'x' ),
			'whatsapp'        => array( 'https://wa.me/905550000000', 'whatsapp' ),
			'unknown'         => array( 'https://example.com/linkedin', '' ),
			'name in path'    => array( 'https://example.com/x', '' ),
			'tld is not name' => array( 'https://example.x', '' ),
			'no host'         => array( 'linkedin', '' ),
		);
	}

	#[DataProvider( 'profiles' )]
	public function test_network( string $url, string $expected ): void {
		$this->assertSame( $expected, Format::network( $url ) );
	}

	public function test_social_returns_first_match(): void {
		$urls = array( 'https://x.com/a', 'https://www.linkedin.com/company/a', 'https://twitter.com/b' );
		$this->assertSame( 'https://x.com/a', Format::social( $urls, 'x' ) );
		$this->assertSame( 'https://www.linkedin.com/company/a', Format::social( $urls, 'linkedin' ) );
		$this->assertSame( '', Format::social( $urls, 'instagram' ) );
		$this->assertSame( '', Format::social( $urls, '' ) );
	}

	public function test_every_alias_belongs_to_a_known_network(): void {
		$this->assertSame( 'whatsapp', Format::network( 'https://whatsapp.com/channel/x' ) );
		foreach ( array_keys( Format::NETWORKS ) as $network ) {
			$this->assertSame( $network, Format::network( "https://www.$network.com/example" ), $network );
		}
	}

	public function test_organization_full(): void {
		$org = Format::organization(
			array(
				'name'        => 'Ariumlab',
				'legal_name'  => 'Ariumlab OÜ',
				'url'         => 'https://example.com/',
				'email'       => 'info@example.com',
				'phone'       => '+372 600 0000',
				'vat_id'      => 'EE100000000',
				'street'      => 'Example 1',
				'locality'    => 'Tallinn',
				'postal_code' => '10111',
				'country'     => 'EE',
				'region'      => '',
				'social'      => array( 'https://www.linkedin.com/company/example' ),
			),
			array(
				'url'    => 'https://example.com/logo.png',
				'width'  => 300,
				'height' => 90,
			)
		);

		$this->assertSame(
			array(
				'@type'     => 'Organization',
				'@id'       => 'https://example.com/#organization',
				'name'      => 'Ariumlab',
				'legalName' => 'Ariumlab OÜ',
				'url'       => 'https://example.com/',
				'logo'      => array(
					'@type'  => 'ImageObject',
					'url'    => 'https://example.com/logo.png',
					'width'  => 300,
					'height' => 90,
				),
				'email'     => 'info@example.com',
				'telephone' => '+372 600 0000',
				'vatID'     => 'EE100000000',
				'address'   => array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => 'Example 1',
					'addressLocality' => 'Tallinn',
					'postalCode'      => '10111',
					'addressCountry'  => 'EE',
				),
				'sameAs'    => array( 'https://www.linkedin.com/company/example' ),
			),
			$org
		);
	}

	public function test_organization_leaves_out_empty_properties(): void {
		$this->assertSame(
			array(
				'@type' => 'Organization',
				'@id'   => 'https://example.com/#organization',
				'name'  => 'Example',
				'url'   => 'https://example.com/',
			),
			Format::organization(
				array(
					'name'    => 'Example',
					'url'     => 'https://example.com/',
					'email'   => '',
					'country' => '',
					'social'  => array(),
				)
			)
		);
	}

	public function test_organization_ignores_non_string_social_items(): void {
		$org = Format::organization(
			array(
				'name'   => 'Example',
				'url'    => 'https://example.com/',
				'social' => array( 'https://x.com/a', 42, null ),
			)
		);
		$this->assertSame( array( 'https://x.com/a' ), $org['sameAs'] );
	}
}
