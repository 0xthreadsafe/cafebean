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

/**
 * Meta key holding the optional product badge text (e.g. «تازه برشته»).
 *
 * Registered here rather than in the theme: the value is product data, and it
 * must survive a theme switch along with the admin field that edits it.
 */
const CAFEBEAN_BADGE_META = '_cafebean_badge';

/**
 * Register the badge meta so it is sanitised consistently and exposed to the
 * REST API for the block editor.
 *
 * @return void
 */
function cafebean_register_product_meta() {
	register_post_meta(
		'product',
		CAFEBEAN_BADGE_META,
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => static function () {
				return current_user_can( 'edit_products' );
			},
		)
	);
}
add_action( 'init', 'cafebean_register_product_meta' );

/**
 * Read a product's badge text.
 *
 * @param int $product_id Product ID.
 * @return string Badge text, or an empty string.
 */
function cafebean_get_badge( $product_id ) {
	return (string) get_post_meta( $product_id, CAFEBEAN_BADGE_META, true );
}

/**
 * Add the badge input to the product data panel.
 *
 * @return void
 */
function cafebean_badge_field() {
	woocommerce_wp_text_input(
		array(
			'id'          => CAFEBEAN_BADGE_META,
			'label'       => __( 'برچسب محصول', 'cafebean' ),
			'description' => __( 'متن کوتاهی که روی تصویر محصول نمایش داده می‌شود، مثلاً «تازه برشته». خالی بگذارید تا برچسبی نمایش داده نشود.', 'cafebean' ),
			'desc_tip'    => true,
			'placeholder' => __( 'تازه برشته', 'cafebean' ),
		)
	);
}
add_action( 'woocommerce_product_options_general_product_data', 'cafebean_badge_field' );

/**
 * Persist the badge input.
 *
 * @param int $product_id Product ID.
 * @return void
 */
function cafebean_save_badge_field( $product_id ) {
	// WooCommerce verifies its own nonce before firing this hook; this check
	// keeps the function safe if it is ever called from elsewhere.
	if ( ! current_user_can( 'edit_product', $product_id ) ) {
		return;
	}

	$badge = isset( $_POST[ CAFEBEAN_BADGE_META ] )
		? sanitize_text_field( wp_unslash( $_POST[ CAFEBEAN_BADGE_META ] ) )
		: '';

	if ( '' === $badge ) {
		delete_post_meta( $product_id, CAFEBEAN_BADGE_META );
		return;
	}

	update_post_meta( $product_id, CAFEBEAN_BADGE_META, $badge );
}
add_action( 'woocommerce_process_product_meta', 'cafebean_save_badge_field' );
