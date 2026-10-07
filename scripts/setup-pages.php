<?php
/**
 * Fill the home, about and contact pages with content.
 *
 * Run from scripts/setup.sh. Non-destructive: the content is written on the
 * first run, and on later runs only while the page still matches what this
 * script last produced. Once someone edits a page in wp-admin it is theirs, and
 * re-running setup.sh leaves it alone.
 *
 * Pass "force" to overwrite regardless:
 *     ./scripts/setup.sh --force-pages
 *
 * Content is core block markup plus shortcodes, not Elementor: the layouts are
 * simple enough that blocks keep them reproducible from the script with no
 * builder state in the database.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta key recording the checksum of the content this script last wrote.
 */
const CAFEBEAN_GENERATED_HASH = '_cafebean_generated_hash';

/**
 * Set a page's content by slug, unless a human has edited it.
 *
 * The excerpt is set too: the meta description is built from it, and a page
 * with no excerpt ends up with no description at all.
 *
 * @param string $slug    Page slug.
 * @param string $content Block markup.
 * @param string $excerpt Meta description source.
 * @return void
 */
function cafebean_set_page_content( $slug, $content, $excerpt = '' ) {
	$pages = get_posts(
		array(
			'post_type'   => 'page',
			'name'        => $slug,
			'post_status' => 'any',
			'numberposts' => 1,
		)
	);

	if ( ! $pages ) {
		WP_CLI::warning( "page not found: {$slug}" );
		return;
	}

	$page    = $pages[0];
	$current = (string) $page->post_content;
	$stored  = (string) get_post_meta( $page->ID, CAFEBEAN_GENERATED_HASH, true );

	// Compare checksums rather than the strings themselves: the editor
	// normalises whitespace in block markup on save, so a byte comparison would
	// report an edit that nobody made.
	$current_hash = md5( trim( $current ) );

	if ( ! CAFEBEAN_FORCE_PAGES && '' !== trim( $current ) ) {
		if ( '' === $stored ) {
			WP_CLI::log( "  skipping {$slug}: has content this script did not write" );
			return;
		}
		if ( $stored !== $current_hash ) {
			WP_CLI::log( "  skipping {$slug}: edited in the admin since it was generated" );
			return;
		}
	}

	wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
		)
	);
	update_post_meta( $page->ID, CAFEBEAN_GENERATED_HASH, md5( trim( $content ) ) );
	WP_CLI::log( "  wrote {$slug}" );
}

$home = <<<'HTML'
<!-- wp:group {"align":"full","className":"cb-hero","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull cb-hero">
<!-- wp:heading {"level":1,"className":"cb-hero__title"} -->
<h1 class="wp-block-heading cb-hero__title">قهوه تازه برشته، مستقیم از برشته‌کاری ما</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"cb-hero__text"} -->
<p class="cb-hero__text">دانه‌های تک‌خاستگاه و ترکیب‌های اختصاصی کافه‌بین را در روز سفارش برشته می‌کنیم و تازه به دستتان می‌رسانیم.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/shop/">مشاهده فروشگاه</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"cb-statement-band","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull cb-statement-band">
<!-- wp:paragraph {"align":"center","className":"cb-statement"} -->
<p class="has-text-align-center cb-statement">ما دانه‌ها را مستقیم از تولیدکننده می‌خریم و در دسته‌های کوچک برشته می‌کنیم. هر بسته تاریخ برشته‌کاری دارد، چون قهوه تازه با قهوه مانده یکی نیست.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:shortcode -->
[cafebean_trust_badges]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2 class="wp-block-heading">پیشنهاد ویژه</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[products limit="4" columns="4" visibility="featured"]
<!-- /wp:shortcode -->

<!-- wp:group {"align":"full","className":"cb-band","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull cb-band">
<!-- wp:heading {"level":2,"className":"cb-band__title"} -->
<h2 class="wp-block-heading cb-band__title">از مزرعه تا فنجان شما</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"cb-band__text"} -->
<p class="cb-band__text">دانه‌ها را از کشاورزانی می‌خریم که سال‌هاست با آن‌ها کار می‌کنیم. هر محموله پیش از برشته‌کاری چشیده و امتیازدهی می‌شود و فقط دانه‌هایی که به استاندارد ما می‌رسند وارد فروشگاه می‌شوند.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/about/">داستان ما</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">دسته‌بندی‌ها</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[product_categories number="3" columns="3" parent="0"]
<!-- /wp:shortcode -->
HTML;

$about = <<<'HTML'
<!-- wp:heading -->
<h2 class="wp-block-heading">ما کی هستیم</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>کافه‌بین یک برشته‌کاری کوچک است که از سال ۱۳۹۶ روی قهوه‌های تک‌خاستگاه کار می‌کند. ما دانه‌ها را در دسته‌های کوچک برشته می‌کنیم تا هر بسته‌ای که به دست شما می‌رسد، تازه باشد.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">چطور کار می‌کنیم</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>هر سفارش پس از ثبت برشته و بسته‌بندی می‌شود. برای قهوه‌های آسیاب‌شده، اندازه آسیاب را متناسب با روش دم‌آوری شما تنظیم می‌کنیم؛ کافی است هنگام سفارش آن را انتخاب کنید.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>اگر از کیفیت سفارش خود راضی نبودید، تا هفت روز می‌توانید آن را بازگردانید.</p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[cafebean_trust_badges]
<!-- /wp:shortcode -->
HTML;

$contact = <<<'HTML'
<!-- wp:paragraph -->
<p>برای سفارش عمده، همکاری یا هر پرسشی درباره محصولات، فرم زیر را پر کنید. معمولاً ظرف یک روز کاری پاسخ می‌دهیم.</p>
<!-- /wp:paragraph -->

<!-- wp:shortcode -->
[cafebean_contact_form]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2 class="wp-block-heading">اطلاعات تماس</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>نشانی: تهران، خیابان نمونه، پلاک ۱۲<br>تلفن: ۰۲۱۱۲۳۴۵۶۷۸<br>ایمیل: info@cafebean.test</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"cb-note"} -->
<p class="cb-note">این نشانی و شماره‌ها نمونه هستند و به جایی تعلق ندارند.</p>
<!-- /wp:paragraph -->
HTML;

/*
 * wp-cli exposes positional arguments to eval-file as $args. This has to be a
 * constant rather than a variable: eval-file runs the file inside a function
 * scope, so a "global" here is really a local and the functions below cannot
 * see it.
 */
define( 'CAFEBEAN_FORCE_PAGES', isset( $args ) && in_array( 'force', (array) $args, true ) );
if ( CAFEBEAN_FORCE_PAGES ) {
	WP_CLI::warning( 'forcing page content, admin edits will be discarded' );
}

cafebean_set_page_content(
	'home',
	$home,
	'فروشگاه آنلاین کافه‌بین؛ دانه قهوه تک‌خاستگاه، ترکیب‌های اختصاصی اسپرسو و لوازم جانبی باریستا با ارسال سریع به سراسر ایران.'
);
cafebean_set_page_content(
	'about',
	$about,
	'کافه‌بین یک برشته‌کاری کوچک است که دانه‌ها را در دسته‌های کوچک و پس از ثبت سفارش برشته می‌کند.'
);
cafebean_set_page_content(
	'contact',
	$contact,
	'برای سفارش عمده، همکاری یا پرسش درباره محصولات کافه‌بین با ما تماس بگیرید.'
);

// WooCommerce stores the short description as the excerpt, so products already
// have one; only pages needed fixing.

WP_CLI::success( 'page content written' );
