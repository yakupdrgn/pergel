<?php
/**
 * Sets up WooCommerce on the CI site for the shop job (tools/ci/setup-wp.sh
 * with WOOCOMMERCE set). Safe to run again.
 *
 * Usage: wp eval-file tools/ci/shop.php
 *
 * A Turkish store in TRY like the shop starter's prices, whose checkout
 * starts in its own country and province; the site stays open (new installs
 * start in "coming soon" mode, which hides the shop from guests); free
 * shipping everywhere; the offline gateways cash on delivery
 * and bank transfer; the core pages; and a page with the classic checkout
 * shortcode (pergel-classic-checkout) for the classic checkout test, which
 * swaps it in as the checkout page while it runs.
 *
 * Runs through eval(), so it cannot declare strict_types.
 *
 * @package Pergel
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- runs through wp eval-file, not in WordPress.

if ( ! class_exists( 'WooCommerce', false ) ) {
	WP_CLI::error( 'WooCommerce is not active.' );
}

$pergel_options = array(
	'woocommerce_coming_soon'                    => 'no',
	'woocommerce_store_pages_only'               => 'no',
	'woocommerce_default_country'                => 'TR:TR34',
	'woocommerce_store_address'                  => 'Test Sokak 1',
	'woocommerce_store_city'                     => 'Istanbul',
	'woocommerce_store_postcode'                 => '34000',
	'woocommerce_currency'                       => 'TRY',
	'woocommerce_default_customer_address'       => 'base',
	'woocommerce_allowed_countries'              => 'all',
	'woocommerce_ship_to_countries'              => '',
	'woocommerce_calc_taxes'                     => 'no',
	'woocommerce_enable_guest_checkout'          => 'yes',
	'woocommerce_enable_checkout_login_reminder' => 'yes',
	'woocommerce_allow_tracking'                 => 'no',
	'woocommerce_show_marketplace_suggestions'   => 'no',
	'woocommerce_task_list_hidden'               => 'yes',
	'woocommerce_onboarding_profile'             => array( 'skipped' => true ),
	'woocommerce_cod_settings'                   => array(
		'enabled'      => 'yes',
		'title'        => 'Cash on delivery',
		'description'  => 'Pay with cash upon delivery.',
		'instructions' => 'Pay with cash upon delivery.',
	),
	'woocommerce_bacs_settings'                  => array(
		'enabled'      => 'yes',
		'title'        => 'Direct bank transfer',
		'description'  => 'Pay into our bank account.',
		'instructions' => 'Pay into our bank account.',
	),
);
foreach ( $pergel_options as $pergel_name => $pergel_value ) {
	update_option( $pergel_name, $pergel_value );
}

WC_Install::create_pages();

// Free shipping in the zone for "locations not covered by your other zones".
$pergel_zone = new WC_Shipping_Zone( 0 );
$pergel_has  = array_filter(
	$pergel_zone->get_shipping_methods(),
	static fn ( $method ) => 'free_shipping' === $method->id
);
if ( ! $pergel_has ) {
	$pergel_zone->add_shipping_method( 'free_shipping' );
}

if ( ! get_page_by_path( 'pergel-classic-checkout' ) ) {
	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Classic checkout',
			'post_name'    => 'pergel-classic-checkout',
			'post_content' => "<!-- wp:shortcode -->\n[woocommerce_checkout]\n<!-- /wp:shortcode -->",
		)
	);
}

WP_CLI::success(
	sprintf(
		'WooCommerce %s ready: shop %d, cart %d, checkout %d, my account %d.',
		WC()->version,
		wc_get_page_id( 'shop' ),
		wc_get_page_id( 'cart' ),
		wc_get_page_id( 'checkout' ),
		wc_get_page_id( 'myaccount' )
	)
);
