<?php
/**
 * CafeBean child theme bootstrap.
 *
 * This file only wires up inc/; no logic lives here.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

define( 'CAFEBEAN_DIR', get_stylesheet_directory() );
define( 'CAFEBEAN_URI', get_stylesheet_directory_uri() );
define( 'CAFEBEAN_VERSION', '0.1.0' );

require_once CAFEBEAN_DIR . '/inc/setup.php';
require_once CAFEBEAN_DIR . '/inc/woocommerce.php';
require_once CAFEBEAN_DIR . '/inc/checkout.php';
