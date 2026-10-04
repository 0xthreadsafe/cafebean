<?php
/**
 * Fill the home, about and contact pages with content.
 *
 * Run from scripts/setup.sh. setup.sh is the source of truth for the demo, so
 * this overwrites the page content every run rather than merging.
 *
 * Content is core block markup plus shortcodes, not Elementor: the layouts are
 * simple enough that blocks keep them reproducible from the script with no
 * builder state in the database.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set a page's content by slug.
 *
 * @param string $slug    Page slug.
 * @param string $content Block markup.
 * @return void
 */
function cafebean_set_page_content( $slug, $content ) {
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

	wp_update_post(
		array(
			'ID'           => $pages[0]->ID,
			'post_content' => $content,
		)
	);
}

$home = <<<'HTML'
<!-- wp:group {"className":"cb-hero","layout":{"type":"constrained"}} -->
<div class="wp-block-group cb-hero">
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

<!-- wp:shortcode -->
[cafebean_trust_badges]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2 class="wp-block-heading">پیشنهاد ویژه</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[products limit="4" columns="4" visibility="featured"]
<!-- /wp:shortcode -->

<!-- wp:heading -->
<h2 class="wp-block-heading">دسته‌بندی‌ها</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[product_categories number="3" parent="0"]
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

cafebean_set_page_content( 'home', $home );
cafebean_set_page_content( 'about', $about );
cafebean_set_page_content( 'contact', $contact );

WP_CLI::success( 'page content written' );
