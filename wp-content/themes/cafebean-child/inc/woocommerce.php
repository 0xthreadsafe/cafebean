<?php
/**
 * WooCommerce display customisations.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Should the current request get Persian digits?
 *
 * Display only. The admin keeps ASCII digits so that price fields stay
 * editable and sortable, and so pasted values round-trip unchanged. REST
 * responses are left alone because other software consumes them.
 *
 * @return bool
 */
function cafebean_wants_persian_digits() {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return false;
	}
	return true;
}

/**
 * Convert the digits inside a formatted price.
 *
 * This filter receives only the number itself - the currency suffix and the
 * HTML wrapper are added afterwards - so nothing else on the page is touched.
 *
 * @param string $formatted Number formatted with separators.
 * @return string
 */
function cafebean_price_digits( $formatted ) {
	if ( ! cafebean_wants_persian_digits() ) {
		return $formatted;
	}
	return cafebean_to_persian_digits( $formatted );
}
add_filter( 'formatted_woocommerce_price', 'cafebean_price_digits', 20 );

/**
 * Convert digits in text nodes only, never inside tags.
 *
 * Pagination links contain /page/2/ in their href, and converting that would
 * break the link, so attributes are left untouched.
 *
 * @param string $html Markup fragment.
 * @return string
 */
function cafebean_persian_digits_in_text( $html ) {
	return preg_replace_callback(
		'/>([^<]+)</u',
		static function ( $matches ) {
			return '>' . cafebean_to_persian_digits( $matches[1] ) . '<';
		},
		$html
	);
}

/**
 * Buffer a core output function and convert its digits.
 *
 * WooCommerce prints the result count and pagination directly with no filter
 * on the markup, so the output is captured rather than overriding templates.
 *
 * @param callable $callback Core template function.
 * @return void
 */
function cafebean_echo_with_persian_digits( $callback ) {
	if ( ! cafebean_wants_persian_digits() ) {
		call_user_func( $callback );
		return;
	}
	ob_start();
	call_user_func( $callback );
	echo wp_kses_post( cafebean_persian_digits_in_text( ob_get_clean() ) );
}

/**
 * Persian digits in the "نمایش همه ۱۲ نتیجه" counter.
 */
function cafebean_result_count() {
	cafebean_echo_with_persian_digits( 'woocommerce_result_count' );
}
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
add_action( 'woocommerce_before_shop_loop', 'cafebean_result_count', 20 );

/**
 * Persian digits in archive pagination.
 */
function cafebean_pagination() {
	cafebean_echo_with_persian_digits( 'woocommerce_pagination' );
}
remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
add_action( 'woocommerce_after_shop_loop', 'cafebean_pagination', 10 );

/**
 * Persian digits in the وزن variation labels (۲۵۰ گرم rather than 250).
 *
 * WooCommerce passes four arguments here: the option, the term object (null for
 * product-level attributes like ours), the attribute name, and the product.
 *
 * @param string      $option    Option label.
 * @param mixed       $term      Term object, or null for a local attribute.
 * @param string      $attribute Attribute name.
 * @param object|null $product   Product object.
 * @return string
 */
function cafebean_weight_option_digits( $option, $term = null, $attribute = '', $product = null ) {
	if ( ! cafebean_wants_persian_digits() || 'وزن' !== $attribute ) {
		return $option;
	}
	/* translators: %s: weight in grams. */
	return sprintf( esc_html__( '%s گرم', 'cafebean' ), cafebean_to_persian_digits( $option ) );
}
add_filter( 'woocommerce_variation_option_name', 'cafebean_weight_option_digits', 20, 4 );

/**
 * Render the product badge over the thumbnail.
 *
 * The badge text itself lives in product meta (see the mu-plugin) so it is
 * editable per product rather than hard-coded here.
 *
 * @return void
 */
function cafebean_render_badge() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$badge = cafebean_get_badge( $product->get_id() );
	if ( '' === $badge ) {
		return;
	}

	printf(
		'<span class="cb-badge cb-badge--product">%s</span>',
		esc_html( $badge )
	);
}
add_action( 'woocommerce_before_shop_loop_item_title', 'cafebean_render_badge', 15 );
add_action( 'woocommerce_before_single_product_summary', 'cafebean_render_badge', 15 );
