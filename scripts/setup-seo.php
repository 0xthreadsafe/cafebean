<?php
/**
 * Rank Math configuration.
 *
 * Run from scripts/setup.sh.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

// Persian product permalinks: /product/قهوه-اسپرسو.../ with no extra base.
update_option( 'woocommerce_permalinks', array(
	'product_base'           => 'product',
	'category_base'          => 'product-category',
	'tag_base'               => 'product-tag',
	'attribute_base'         => '',
	'use_verbose_page_rules' => false,
) );

$titles = (array) get_option( 'rank-math-options-titles', array() );
$titles['noindex_empty_taxonomies'] = 'on';
$titles['title_separator']          = '-';
$titles['pt_product_default_rich_snippet'] = 'product';
$titles['pt_product_default_snippet_name'] = '%title%';
$titles['pt_product_default_snippet_desc'] = '%excerpt%';
// The demo must not compete with real client sites in search results.
$titles['noindex_archive_subpages'] = 'on';
update_option( 'rank-math-options-titles', $titles );

/*
 * Sitemaps come from WordPress core (/wp-sitemap.xml), not Rank Math.
 *
 * Rank Math only registers its sitemap routes once its setup wizard has run,
 * and that wizard is interactive, so it cannot be driven from setup.sh. The core
 * sitemap already lists every page, product and product category with the
 * Persian permalinks, which is what the SEO target asks for. Rank Math is still
 * doing the work that matters here: titles, meta descriptions and breadcrumbs.
 *
 * Note both sitemaps are suppressed entirely while blog_public is 0, so the
 * deployed demo - which is deliberately noindex - will not serve one.
 */

$general = (array) get_option( 'rank-math-options-general', array() );
$general['strip_category_base'] = 'off';
$general['breadcrumbs']         = 'on';
$general['breadcrumbs_home_label'] = 'خانه';
$general['breadcrumbs_separator']  = '/';
update_option( 'rank-math-options-general', $general );

$modules = (array) get_option( 'rank_math_modules', array() );
foreach ( array( 'rich-snippet', 'woocommerce' ) as $module ) {
	if ( ! in_array( $module, $modules, true ) ) {
		$modules[] = $module;
	}
}
update_option( 'rank_math_modules', $modules );

/*
 * Drop the cached rules instead of flushing them here: a CLI flush regenerates
 * them without the plugins that only register routes on a web request, so the
 * next front-end hit is what rebuilds them correctly.
 */
delete_option( 'rewrite_rules' );

WP_CLI::success( 'SEO options written' );
