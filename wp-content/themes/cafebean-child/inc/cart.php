<?php
/**
 * Free-shipping progress bar.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cart total measured the same way the free_shipping method measures it.
 *
 * WC_Shipping_Free_Shipping uses the displayed subtotal minus discounts, so the
 * bar has to use the identical figure - otherwise it can promise free shipping
 * that the shipping method then refuses to grant.
 *
 * @return float
 */
function cafebean_free_shipping_progress_total() {
	$cart = WC()->cart;
	if ( ! $cart ) {
		return 0.0;
	}

	$total = (float) $cart->get_displayed_subtotal();
	$total -= (float) $cart->get_discount_total();

	if ( WC()->cart->display_prices_including_tax() ) {
		$total -= (float) $cart->get_discount_tax();
	}

	return max( 0.0, $total );
}

/**
 * Render the progress bar.
 *
 * Hidden when the cart is empty or needs no shipping: a progress bar towards
 * free shipping is meaningless when nothing is being shipped.
 *
 * @return void
 */
function cafebean_free_shipping_progress() {
	$cart = WC()->cart;
	if ( ! $cart || $cart->is_empty() || ! $cart->needs_shipping() ) {
		return;
	}

	$threshold = (float) CAFEBEAN_FREE_SHIPPING_THRESHOLD;
	if ( $threshold <= 0 ) {
		return;
	}

	$total     = cafebean_free_shipping_progress_total();
	$remaining = max( 0.0, $threshold - $total );
	$percent   = min( 100, (int) floor( ( $total / $threshold ) * 100 ) );
	$qualified = $remaining <= 0;

	if ( $qualified ) {
		$message = esc_html__( 'سفارش شما شامل ارسال رایگان است 🎉', 'cafebean' );
	} else {
		$message = sprintf(
			/* translators: %s: formatted remaining amount. */
			esc_html__( '%s تا ارسال رایگان باقی مانده است', 'cafebean' ),
			wp_strip_all_tags( wc_price( $remaining ) )
		);
	}
	?>
	<div class="cb-shipping-progress <?php echo $qualified ? 'cb-shipping-progress--done' : ''; ?>">
		<p class="cb-shipping-progress__label"><?php echo esc_html( $message ); ?></p>
		<div
			class="cb-shipping-progress__track"
			role="progressbar"
			aria-valuenow="<?php echo esc_attr( $percent ); ?>"
			aria-valuemin="0"
			aria-valuemax="100"
			aria-label="<?php esc_attr_e( 'پیشرفت تا ارسال رایگان', 'cafebean' ); ?>"
		>
			<span class="cb-shipping-progress__fill" style="inline-size: <?php echo esc_attr( $percent ); ?>%;"></span>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_before_cart_table', 'cafebean_free_shipping_progress', 10 );
// The mini cart is re-rendered inside the AJAX refresh fragment, so hooking it
// here is what keeps the bar current after an add-to-cart without a reload.
add_action( 'woocommerce_widget_shopping_cart_before_buttons', 'cafebean_free_shipping_progress', 5 );
