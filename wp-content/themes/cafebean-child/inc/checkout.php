<?php
/**
 * Iranian checkout fields and server-side validation.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fields that are normalised from Persian/Arabic digits before validation.
 */
const CAFEBEAN_NUMERIC_FIELDS = array(
	'billing_phone',
	'billing_postcode',
	'shipping_postcode',
);

/**
 * Reshape the checkout to Iranian conventions.
 *
 * Company and address_2 are dropped because Iranian addresses do not use them,
 * and the country field is hidden rather than removed: WooCommerce needs a
 * country to pick shipping rates and to populate the province dropdown.
 *
 * @param array $fields Checkout fields.
 * @return array
 */
function cafebean_checkout_fields( $fields ) {
	foreach ( array( 'billing', 'shipping' ) as $section ) {
		unset( $fields[ $section ][ $section . '_company' ] );
		unset( $fields[ $section ][ $section . '_address_2' ] );

		if ( isset( $fields[ $section ][ $section . '_country' ] ) ) {
			$fields[ $section ][ $section . '_country' ]['type']  = 'hidden';
			$fields[ $section ][ $section . '_country' ]['label'] = '';
		}

		$fields[ $section ][ $section . '_state' ]['label']    = esc_html__( 'استان', 'cafebean' );
		$fields[ $section ][ $section . '_state' ]['required'] = true;
		$fields[ $section ][ $section . '_state' ]['priority'] = 50;

		$fields[ $section ][ $section . '_city' ]['label']    = esc_html__( 'شهر', 'cafebean' );
		$fields[ $section ][ $section . '_city' ]['priority'] = 60;

		$fields[ $section ][ $section . '_address_1' ]['label']       = esc_html__( 'نشانی', 'cafebean' );
		$fields[ $section ][ $section . '_address_1' ]['placeholder'] = esc_html__( 'خیابان، کوچه، پلاک، واحد', 'cafebean' );
		$fields[ $section ][ $section . '_address_1' ]['priority']    = 70;

		$fields[ $section ][ $section . '_postcode' ]['label']       = esc_html__( 'کد پستی', 'cafebean' );
		$fields[ $section ][ $section . '_postcode' ]['required']    = true;
		$fields[ $section ][ $section . '_postcode' ]['priority']    = 80;
		$fields[ $section ][ $section . '_postcode' ]['placeholder'] = esc_html__( 'ده رقم بدون خط تیره', 'cafebean' );
		// inputmode rather than type=number: a postcode is a digit string, not a
		// quantity, and number inputs strip leading zeros.
		$fields[ $section ][ $section . '_postcode' ]['custom_attributes'] = array(
			'inputmode'  => 'numeric',
			'maxlength'  => '10',
			'autocomplete' => 'postal-code',
		);
	}

	$fields['billing']['billing_first_name']['priority'] = 10;
	$fields['billing']['billing_last_name']['priority']  = 20;

	$fields['billing']['billing_phone']['label']       = esc_html__( 'شماره موبایل', 'cafebean' );
	$fields['billing']['billing_phone']['required']    = true;
	$fields['billing']['billing_phone']['priority']    = 30;
	$fields['billing']['billing_phone']['placeholder'] = esc_html__( '۰۹۱۲۳۴۵۶۷۸۹', 'cafebean' );
	$fields['billing']['billing_phone']['custom_attributes'] = array(
		'inputmode'    => 'numeric',
		'maxlength'    => '11',
		'autocomplete' => 'tel',
	);

	$fields['billing']['billing_email']['priority'] = 40;

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'cafebean_checkout_fields', 20 );

/**
 * Drop company and address_2 from the account address book too, so the forms
 * there match the checkout.
 *
 * @param array $fields Default address fields.
 * @return array
 */
function cafebean_default_address_fields( $fields ) {
	unset( $fields['company'], $fields['address_2'] );
	return $fields;
}
add_filter( 'woocommerce_default_address_fields', 'cafebean_default_address_fields', 20 );

/**
 * Normalise a submitted value to ASCII digits.
 *
 * Runs before validation and before the value is stored, so a number typed on a
 * Persian keyboard is saved in the same form as one typed on a Latin one.
 *
 * @param string $value Submitted value.
 * @return string
 */
function cafebean_normalise_digits( $value ) {
	if ( ! is_string( $value ) ) {
		return $value;
	}
	// Iranians often paste numbers with ZWNJ, dashes or Arabic comma; strip
	// everything that is not a digit once the digits themselves are ASCII.
	$value = cafebean_to_english_digits( $value );
	return preg_replace( '/[^0-9]/', '', $value );
}

foreach ( CAFEBEAN_NUMERIC_FIELDS as $cafebean_field ) {
	add_filter( 'woocommerce_process_checkout_field_' . $cafebean_field, 'cafebean_normalise_digits', 5 );
}
unset( $cafebean_field );

/**
 * Server-side validation for the Iranian-specific fields.
 *
 * The client-side pattern attributes are a convenience; this is the check that
 * actually matters, because a POST can skip the browser entirely.
 *
 * @param array    $data   Posted checkout data.
 * @param WP_Error $errors Error collector.
 * @return void
 */
function cafebean_validate_checkout( $data, $errors ) {
	$phone = cafebean_normalise_digits( $data['billing_phone'] ?? '' );
	if ( '' === $phone || ! preg_match( '/^09\d{9}$/', $phone ) ) {
		$errors->add(
			'billing_phone',
			esc_html__( 'شماره موبایل باید ۱۱ رقم باشد و با ۰۹ شروع شود.', 'cafebean' )
		);
	}

	$ship_to_different = ! empty( $data['ship_to_different_address'] );
	$postcode_fields   = $ship_to_different
		? array( 'billing_postcode', 'shipping_postcode' )
		: array( 'billing_postcode' );

	foreach ( $postcode_fields as $field ) {
		$postcode = cafebean_normalise_digits( $data[ $field ] ?? '' );
		if ( '' === $postcode || ! preg_match( '/^\d{10}$/', $postcode ) ) {
			$errors->add(
				$field,
				esc_html__( 'کد پستی باید دقیقاً ۱۰ رقم باشد.', 'cafebean' )
			);
		}
	}
}
add_action( 'woocommerce_after_checkout_validation', 'cafebean_validate_checkout', 10, 2 );

/**
 * Apply the same normalisation to the address book in حساب کاربری.
 */
add_filter( 'woocommerce_process_myaccount_field_billing_phone', 'cafebean_normalise_digits', 5 );
add_filter( 'woocommerce_process_myaccount_field_billing_postcode', 'cafebean_normalise_digits', 5 );
add_filter( 'woocommerce_process_myaccount_field_shipping_postcode', 'cafebean_normalise_digits', 5 );
