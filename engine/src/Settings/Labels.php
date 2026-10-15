<?php
/**
 * Human-readable text for the settings schema.
 *
 * @package Pergel
 */

declare( strict_types=1 );

namespace Pergel\Settings;

/**
 * Tab titles, sections, field labels and error messages. Kept apart from
 * Schema so the schema stays pure PHP; only the settings screen loads this.
 */
final class Labels {

	/**
	 * Tabs, one per schema group.
	 *
	 * @return array<string, array{title: string, description: string}>
	 */
	public static function tabs(): array {
		return array(
			'modules' => array(
				'title'       => __( 'Modules', 'pergel-engine' ),
				'description' => __( 'Switch off what this site does not use. A switched-off module loads no code.', 'pergel-engine' ),
			),
			'brand'   => array(
				'title'       => __( 'Brand', 'pergel-engine' ),
				'description' => __( 'Company details, entered once. Blocks show them through the Pergel Brand source, and search engines receive them as Organization data.', 'pergel-engine' ),
			),
		);
	}

	/**
	 * Sections of each tab and the fields they hold, in display order.
	 * Schema fields missing here are shown in a last "Other" section.
	 *
	 * @return array<string, array<string, array{title: string, fields: list<string>}>>
	 */
	public static function sections(): array {
		return array(
			'modules' => array(
				'modules' => array(
					'title'  => __( 'Modules', 'pergel-engine' ),
					'fields' => array( 'brand', 'seo', 'performance' ),
				),
			),
			'brand'   => array(
				'company' => array(
					'title'  => __( 'Company', 'pergel-engine' ),
					'fields' => array( 'name', 'legal_name', 'vat_id' ),
				),
				'contact' => array(
					'title'  => __( 'Contact', 'pergel-engine' ),
					'fields' => array( 'email', 'phone' ),
				),
				'address' => array(
					'title'  => __( 'Address', 'pergel-engine' ),
					'fields' => array( 'street', 'locality', 'region', 'postal_code', 'country' ),
				),
				'online'  => array(
					'title'  => __( 'Social accounts', 'pergel-engine' ),
					'fields' => array( 'social' ),
				),
				'logos'   => array(
					'title'  => __( 'Logos', 'pergel-engine' ),
					'fields' => array( 'logo_inverse' ),
				),
			),
		);
	}

	/**
	 * Field labels and help texts.
	 *
	 * @return array<string, array<string, array{label: string, help?: string}>>
	 */
	public static function fields(): array {
		return array(
			'modules' => array(
				'brand'       => array(
					'label' => __( 'Brand', 'pergel-engine' ),
					'help'  => __( 'Brand details in blocks (Block Bindings) and in structured data.', 'pergel-engine' ),
				),
				'seo'         => array(
					'label' => __( 'SEO', 'pergel-engine' ),
					'help'  => __( 'Hands over to an active SEO plugin and adds the brand details to its data. Without one, Pergel prints the meta tags and structured data itself.', 'pergel-engine' ),
				),
				'performance' => array(
					'label' => __( 'Performance', 'pergel-engine' ),
					'help'  => __( 'Removes front-end assets that most sites do not need.', 'pergel-engine' ),
				),
			),
			'brand'   => array(
				'name'         => array(
					'label' => __( 'Brand name', 'pergel-engine' ),
					'help'  => __( 'The name customers know. Leave empty to use the site title.', 'pergel-engine' ),
				),
				'legal_name'   => array( 'label' => __( 'Legal name', 'pergel-engine' ) ),
				'vat_id'       => array( 'label' => __( 'Tax number', 'pergel-engine' ) ),
				'email'        => array( 'label' => __( 'Email', 'pergel-engine' ) ),
				'phone'        => array(
					'label' => __( 'Phone', 'pergel-engine' ),
					'help'  => __( 'With the country code, e.g. +90 212 000 00 00.', 'pergel-engine' ),
				),
				'street'       => array( 'label' => __( 'Street address', 'pergel-engine' ) ),
				'locality'     => array( 'label' => __( 'City or district', 'pergel-engine' ) ),
				'region'       => array( 'label' => __( 'Region or province', 'pergel-engine' ) ),
				'postal_code'  => array( 'label' => __( 'Postal code', 'pergel-engine' ) ),
				'country'      => array(
					'label' => __( 'Country code', 'pergel-engine' ),
					'help'  => __( 'Two letters (ISO 3166-1), e.g. TR.', 'pergel-engine' ),
				),
				'social'       => array(
					'label' => __( 'Profile links', 'pergel-engine' ),
					'help'  => __( 'One full address per line (LinkedIn, Instagram, YouTube, ...). Up to 10.', 'pergel-engine' ),
				),
				'logo_inverse' => array(
					'label' => __( 'Logo for dark backgrounds', 'pergel-engine' ),
					'help'  => __( 'The main logo is the Site Logo in the Site Editor.', 'pergel-engine' ),
				),
			),
		);
	}

	/**
	 * Message for a schema error code.
	 *
	 * @param string $code Error code from Schema::field().
	 */
	public static function problem( string $code ): string {
		return match ( $code ) {
			'too_long'        => __( 'The value is too long.', 'pergel-engine' ),
			'invalid_email'   => __( 'Enter a valid email address.', 'pergel-engine' ),
			'invalid_url'     => __( 'Enter full web addresses that start with https:// or http://.', 'pergel-engine' ),
			'invalid_tel'     => __( 'Use digits, spaces, + ( ) - and . only.', 'pergel-engine' ),
			'invalid_country' => __( 'Use a two-letter country code such as TR.', 'pergel-engine' ),
			'invalid_choice'  => __( 'Choose one of the listed options.', 'pergel-engine' ),
			'out_of_range'    => __( 'The number is out of range.', 'pergel-engine' ),
			'too_many'        => __( 'There are too many items.', 'pergel-engine' ),
			default           => __( 'The value has the wrong type.', 'pergel-engine' ),
		};
	}
}
