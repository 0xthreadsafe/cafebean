<?php
/**
 * Give each product category a thumbnail.
 *
 * Run from scripts/setup.sh. Without this WooCommerce prints its grey
 * placeholder in the category grid, which looks broken on the home page.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

$mapping = array(
	'دانه قهوه'   => 'dane-ghahve-boryan',
	'پودر قهوه'   => 'ghahve-asiab-shode',
	'لوازم جانبی' => 'loazem-espresso',
);

$set = 0;
foreach ( $mapping as $category => $image_slug ) {
	$term = get_term_by( 'name', $category, 'product_cat' );
	if ( ! $term ) {
		continue;
	}

	$attachments = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_cafebean_source',
			'meta_value'  => $image_slug,
		)
	);
	if ( ! $attachments ) {
		continue;
	}

	update_term_meta( $term->term_id, 'thumbnail_id', (int) $attachments[0] );
	++$set;
}

WP_CLI::success( "category thumbnails set: {$set}" );
