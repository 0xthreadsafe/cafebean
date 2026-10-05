<?php
/**
 * WooCommerce image sizing.
 *
 * Run from scripts/setup.sh.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/*
 * Catalogue photos come from mixed sources with mixed aspect ratios, which
 * leaves the shop grid ragged. Hard-cropping thumbnails to a square makes every
 * card the same shape whatever is uploaded. The CSS enforces the same ratio, so
 * the layout holds even before thumbnails are regenerated.
 */
update_option( 'woocommerce_thumbnail_cropping', '1:1' );
update_option( 'woocommerce_thumbnail_image_width', 400 );
update_option( 'woocommerce_single_image_width', 800 );

WP_CLI::success( 'image sizes set' );
