<?php
/**
 * Create the shipping zones and methods.
 *
 * Run from scripts/setup.sh. Idempotent: zones are matched by name and each
 * method is added only if that method type is not already in the zone.
 *
 * wp-cli has no shipping-zone commands, so this uses the WC_Shipping_Zone API.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Find a zone by name, or create it.
 *
 * @param string $name     Zone name.
 * @param array  $location Location as array( code, type ).
 * @return WC_Shipping_Zone
 */
function cafebean_zone( $name, $location ) {
	foreach ( WC_Shipping_Zones::get_zones() as $data ) {
		if ( $data['zone_name'] === $name ) {
			return new WC_Shipping_Zone( $data['zone_id'] );
		}
	}

	$zone = new WC_Shipping_Zone();
	$zone->set_zone_name( $name );
	$zone->add_location( $location[0], $location[1] );
	$zone->save();

	return $zone;
}

/**
 * Add a shipping method to a zone unless one of that type already exists.
 *
 * @param WC_Shipping_Zone $zone     Target zone.
 * @param string           $type     Method id, e.g. flat_rate.
 * @param array            $settings Method settings.
 * @return void
 */
function cafebean_zone_method( $zone, $type, $settings ) {
	foreach ( $zone->get_shipping_methods() as $method ) {
		if ( $method->id === $type ) {
			foreach ( $settings as $key => $value ) {
				$method->instance_settings[ $key ] = $value;
			}
			update_option(
				$method->get_instance_option_key(),
				$method->instance_settings,
				'yes'
			);
			return;
		}
	}

	$instance_id = $zone->add_shipping_method( $type );
	if ( ! $instance_id ) {
		WP_CLI::warning( "could not add {$type} to {$zone->get_zone_name()}" );
		return;
	}

	$method = WC_Shipping_Zones::get_shipping_method( $instance_id );
	foreach ( $settings as $key => $value ) {
		$method->instance_settings[ $key ] = $value;
	}
	update_option( $method->get_instance_option_key(), $method->instance_settings, 'yes' );
}

// Tehran is matched first: WooCommerce evaluates zones in order and uses the
// first match, so the narrower province zone must come before the country zone.
$tehran = cafebean_zone( 'تهران', array( 'IR:THR', 'state' ) );
cafebean_zone_method(
	$tehran,
	'flat_rate',
	array(
		'title'      => 'پیک تهران',
		'cost'       => '25000',
		'tax_status' => 'none',
	)
);
cafebean_zone_method(
	$tehran,
	'free_shipping',
	array(
		'title'      => 'ارسال رایگان',
		'requires'   => 'min_amount',
		'min_amount' => CAFEBEAN_FREE_SHIPPING_THRESHOLD,
	)
);

$iran = cafebean_zone( 'ایران', array( 'IR', 'country' ) );
cafebean_zone_method(
	$iran,
	'flat_rate',
	array(
		'title'      => 'پست پیشتاز',
		'cost'       => '45000',
		'tax_status' => 'none',
	)
);
cafebean_zone_method(
	$iran,
	'free_shipping',
	array(
		'title'      => 'ارسال رایگان',
		'requires'   => 'min_amount',
		'min_amount' => CAFEBEAN_FREE_SHIPPING_THRESHOLD,
	)
);

$tehran->set_zone_order( 0 );
$tehran->save();
$iran->set_zone_order( 1 );
$iran->save();

WP_CLI::success(
	sprintf(
		'shipping zones ready (free shipping over %s)',
		CAFEBEAN_FREE_SHIPPING_THRESHOLD
	)
);
