<?php
/**
 * Plugin Name: CafeBean Core
 * Description: سایت‌محور — منطقی که باید مستقل از قالب فعال باقی بماند.
 * Version: 0.1.0
 * Text Domain: cafebean
 *
 * Lives in mu-plugins because digit conversion is relied on by checkout
 * validation and stored data, which must keep working across a theme switch.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cart subtotal in Toman above which shipping is free.
 *
 * Defined here because two places must agree on it: the shipping zone setup
 * that configures the free_shipping method, and the cart progress bar that
 * tells the customer how much is left to qualify.
 */
const CAFEBEAN_FREE_SHIPPING_THRESHOLD = 500000;

/**
 * Persian and Arabic-Indic digits, indexed 0-9 to match their ASCII position.
 */
function cafebean_digit_map() {
	return array(
		'fa' => array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
		'ar' => array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ),
		'en' => array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
	);
}

/**
 * Render ASCII digits as Persian ones. Display only.
 *
 * @param string $value Text possibly containing ASCII digits.
 * @return string
 */
function cafebean_to_persian_digits( $value ) {
	$map = cafebean_digit_map();
	return str_replace( $map['en'], $map['fa'], (string) $value );
}

/**
 * Normalise Persian and Arabic-Indic digits to ASCII.
 *
 * Everything stored or validated goes through this first: a phone number typed
 * on a Persian keyboard is the same number, and must not fail a regex over it.
 *
 * @param string $value Text possibly containing Persian or Arabic digits.
 * @return string
 */
function cafebean_to_english_digits( $value ) {
	$map   = cafebean_digit_map();
	$value = str_replace( $map['fa'], $map['en'], (string) $value );
	return str_replace( $map['ar'], $map['en'], $value );
}
