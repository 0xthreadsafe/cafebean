<?php
/**
 * Front-end weight reduction.
 *
 * Elementor is deliberately not installed - the pages are core blocks - so
 * there are no builder assets to dequeue here.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remove the emoji detection script and its inline styles.
 *
 * Nothing in the site relies on it and it costs a script plus a DNS prefetch on
 * every page.
 *
 * @return void
 */
function cafebean_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'cafebean_disable_emojis' );

/**
 * Drop oEmbed discovery and its front-end script.
 *
 * The store embeds nothing, so the host script and the discovery links are pure
 * overhead.
 *
 * @return void
 */
function cafebean_disable_embeds() {
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'rest_api_init', 'wp_oembed_register_route' );
	add_filter( 'embed_oembed_discover', '__return_false' );
}
add_action( 'init', 'cafebean_disable_embeds' );

/**
 * Remove head clutter that the store does not use.
 *
 * @return void
 */
function cafebean_clean_head() {
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
}
add_action( 'init', 'cafebean_clean_head' );

/**
 * Is this request a WooCommerce-driven view?
 *
 * @return bool
 */
function cafebean_is_woocommerce_page() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}
	return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
}

/**
 * Drop WooCommerce's stylesheets and block styles away from the shop.
 *
 * wc-cart-fragments is deliberately kept: the header mini cart and the free
 * shipping progress bar inside it update through that script on every page, so
 * removing it would break the cart count sitewide.
 *
 * @return void
 */
function cafebean_dequeue_woocommerce_assets() {
	if ( cafebean_is_woocommerce_page() ) {
		return;
	}

	foreach ( array(
		'woocommerce-general',
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'wc-blocks-style',
		'brands-styles',
	) as $handle ) {
		wp_dequeue_style( $handle );
	}

	// The home page prints products through a shortcode, so its styles stay.
	if ( is_front_page() ) {
		wp_enqueue_style( 'woocommerce-general' );
		wp_enqueue_style( 'woocommerce-layout' );
	}
}
add_action( 'wp_enqueue_scripts', 'cafebean_dequeue_woocommerce_assets', 99 );

/**
 * Remove the block library's front-end CSS on WooCommerce templates.
 *
 * Those templates render no blocks, and the file is large.
 *
 * @return void
 */
function cafebean_dequeue_block_styles() {
	if ( ! cafebean_is_woocommerce_page() || is_cart() || is_checkout() ) {
		return;
	}
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'classic-theme-styles' );
	wp_dequeue_style( 'global-styles' );
}
add_action( 'wp_enqueue_scripts', 'cafebean_dequeue_block_styles', 99 );
