<?php
/**
 * Theme supports and asset loading.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load the parent and child stylesheets.
 */
function cafebean_enqueue_styles() {
	wp_enqueue_style( 'astra-parent', get_template_directory_uri() . '/style.css', array(), CAFEBEAN_VERSION );
	wp_enqueue_style( 'cafebean', CAFEBEAN_URI . '/style.css', array( 'astra-parent' ), CAFEBEAN_VERSION );
}
add_action( 'wp_enqueue_scripts', 'cafebean_enqueue_styles' );
