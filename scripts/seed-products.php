<?php
/**
 * Import the sample catalogue from data/products.csv.
 *
 * Run through scripts/seed-products.sh, not directly. Idempotent: products are
 * matched by SKU and images by source filename, so re-running updates in place
 * instead of creating duplicates.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	WP_CLI::error( 'WooCommerce is not active.' );
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

const CAFEBEAN_CSV    = '/data/products.csv';
const CAFEBEAN_IMG    = '/data/images/';
const CAFEBEAN_WEIGHT = 'وزن';

/**
 * Import an image once and reuse the attachment on later runs.
 *
 * @param string $slug Image basename without extension.
 * @param string $alt  Persian alternative text.
 * @return int Attachment ID, or 0 on failure.
 */
function cafebean_attachment( $slug, $alt ) {
	$existing = get_posts(
		array(
			'post_type'   => 'attachment',
			'post_status' => 'any',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_cafebean_source',
			'meta_value'  => $slug,
		)
	);
	if ( $existing ) {
		return (int) $existing[0];
	}

	$source = CAFEBEAN_IMG . $slug . '.webp';
	if ( ! file_exists( $source ) ) {
		WP_CLI::warning( "missing image: {$source}" );
		return 0;
	}

	// The file is already in the container, so copy it into uploads directly;
	// media_sideload_image would need an HTTP round trip back to ourselves.
	$upload = wp_upload_bits( $slug . '.webp', null, file_get_contents( $source ) );
	if ( ! empty( $upload['error'] ) ) {
		WP_CLI::warning( "upload failed for {$slug}: " . $upload['error'] );
		return 0;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => $alt,
			'post_status'    => 'inherit',
		),
		$upload['file']
	);
	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return 0;
	}

	wp_update_attachment_metadata(
		$attachment_id,
		wp_generate_attachment_metadata( $attachment_id, $upload['file'] )
	);
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $attachment_id, '_cafebean_source', $slug );

	return (int) $attachment_id;
}

/**
 * Create the product category if it does not exist yet.
 *
 * @param string $name Category name.
 * @return int Term ID.
 */
function cafebean_category( $name ) {
	$term = get_term_by( 'name', $name, 'product_cat' );
	if ( $term ) {
		return (int) $term->term_id;
	}
	$created = wp_insert_term( $name, 'product_cat' );
	return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
}

/**
 * Parse "250:185000|500:350000" into weight => price pairs.
 *
 * @param string $raw Raw CSV cell.
 * @return array<string,int>
 */
function cafebean_weights( $raw ) {
	$out = array();
	foreach ( array_filter( explode( '|', $raw ) ) as $pair ) {
		list( $weight, $price ) = array_pad( explode( ':', $pair ), 2, '' );
		if ( '' !== $weight && '' !== $price ) {
			$out[ trim( $weight ) ] = (int) $price;
		}
	}
	return $out;
}

$handle = fopen( CAFEBEAN_CSV, 'r' );
if ( ! $handle ) {
	WP_CLI::error( 'Cannot read ' . CAFEBEAN_CSV );
}
$header  = fgetcsv( $handle );
$created = 0;
$updated = 0;

while ( ( $line = fgetcsv( $handle ) ) !== false ) {
	$row = array_combine( $header, $line );

	$existing_id = wc_get_product_id_by_sku( $row['sku'] );
	$is_variable = 'variable' === $row['type'];
	$weights     = cafebean_weights( $row['weights'] );

	if ( $existing_id ) {
		$product = wc_get_product( $existing_id );
		++$updated;
	} else {
		$product = $is_variable ? new WC_Product_Variable() : new WC_Product_Simple();
		$product->set_sku( $row['sku'] );
		++$created;
	}

	$product->set_name( $row['name'] );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_short_description( $row['short_description'] );
	$product->set_description( $row['description'] );
	$product->set_featured( 'yes' === $row['featured'] );
	$product->set_category_ids( array( cafebean_category( $row['category'] ) ) );
	$product->set_manage_stock( false );
	$product->set_stock_status( 'instock' );

	$image_id = cafebean_attachment( $row['image'], $row['alt'] );
	if ( $image_id ) {
		$product->set_image_id( $image_id );
	}

	if ( $is_variable ) {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( CAFEBEAN_WEIGHT );
		$attribute->set_options( array_keys( $weights ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product->set_attributes( array( $attribute ) );
	} else {
		$product->set_regular_price( (string) (int) $row['price'] );
	}

	$product_id = $product->save();

	if ( $is_variable ) {
		// Index existing variations by weight so a re-run adjusts prices
		// instead of stacking duplicates.
		$by_weight = array();
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( $child ) {
				$by_weight[ $child->get_attribute( CAFEBEAN_WEIGHT ) ] = $child;
			}
		}

		foreach ( $weights as $weight => $price ) {
			$variation = $by_weight[ $weight ] ?? new WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( array( sanitize_title( CAFEBEAN_WEIGHT ) => (string) $weight ) );
			$variation->set_regular_price( (string) $price );
			$variation->set_stock_status( 'instock' );
			$variation->save();
		}

		WC_Product_Variable::sync( $product_id );
	}

	if ( '' !== trim( $row['badge'] ) ) {
		update_post_meta( $product_id, '_cafebean_badge', sanitize_text_field( $row['badge'] ) );
	} else {
		delete_post_meta( $product_id, '_cafebean_badge' );
	}
}

fclose( $handle );
wc_delete_product_transients();

WP_CLI::success( "created {$created}, updated {$updated}" );
