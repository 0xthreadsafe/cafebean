<?php
/**
 * Theme supports, asset loading and fonts.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declare what the child theme supports.
 *
 * Astra already declares most of this; the WooCommerce gallery features are
 * opt-in per theme, so they must be repeated here.
 */
function cafebean_setup() {
	load_child_theme_textdomain( 'cafebean', CAFEBEAN_DIR . '/languages' );

	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'cafebean_setup' );

/**
 * Load the parent and child stylesheets plus the self-hosted font face rules.
 */
function cafebean_enqueue_styles() {
	// Astra's style.css carries only the theme header - its real CSS is in
	// assets/css/minified/main.min-rtl.css, already enqueued by the parent.
	// Enqueuing it here would just add an empty request.
	wp_enqueue_style(
		'cafebean-fonts',
		CAFEBEAN_URI . '/assets/css/fonts.css',
		array(),
		CAFEBEAN_VERSION
	);

	wp_enqueue_style(
		'cafebean',
		CAFEBEAN_URI . '/style.css',
		array( 'astra-theme-css', 'cafebean-fonts' ),
		CAFEBEAN_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'cafebean_enqueue_styles' );

/**
 * Preload the regular weight.
 *
 * Body copy always needs it, and without a preload the font only starts
 * downloading after fonts.css has parsed, which shows a flash of fallback text.
 */
function cafebean_preload_font() {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( CAFEBEAN_URI . '/assets/fonts/Vazirmatn-Regular.woff2' )
	);
}
add_action( 'wp_head', 'cafebean_preload_font', 1 );

/**
 * Use Vazirmatn in the block editor and the admin, so drafting matches the
 * front end and the admin stays readable in Persian.
 */
function cafebean_admin_font() {
	wp_enqueue_style(
		'cafebean-fonts',
		CAFEBEAN_URI . '/assets/css/fonts.css',
		array(),
		CAFEBEAN_VERSION
	);
	wp_add_inline_style(
		'cafebean-fonts',
		'body, .wp-admin, #wpadminbar * { font-family: "Vazirmatn", system-ui, sans-serif; }'
	);
}
add_action( 'admin_enqueue_scripts', 'cafebean_admin_font' );

/**
 * Hide the page title on the front page.
 *
 * The homepage is titled «خانه», which reads as an unfinished template when
 * printed above the hero. Other pages keep their titles.
 *
 * @param bool $enabled Whether Astra prints the title.
 * @return bool
 */
function cafebean_hide_front_page_title( $enabled ) {
	return is_front_page() ? false : $enabled;
}
add_filter( 'astra_the_title_enabled', 'cafebean_hide_front_page_title' );

/**
 * Drop the breadcrumb trail on the front page for the same reason.
 *
 * @param bool $enabled Whether Astra prints breadcrumbs.
 * @return bool
 */
function cafebean_hide_front_page_breadcrumb( $enabled ) {
	return is_front_page() ? false : $enabled;
}
add_filter( 'astra_breadcrumb_enabled', 'cafebean_hide_front_page_breadcrumb' );
