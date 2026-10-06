<?php
/**
 * Rotating hero background.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hero slides, in rotation order.
 *
 * Each entry is a file in assets/img and the Persian alternative text that
 * describes it. The images are decorative, so the text is used only to keep the
 * list self-documenting - the markup itself is aria-hidden.
 */
function cafebean_hero_slides() {
	return array(
		'hero-1.webp' => 'دانه‌های قهوه تازه برشته‌شده',
		'hero-2.webp' => 'فنجان اسپرسو از نمای بالا',
		'hero-3.webp' => 'لوازم جانبی باریستا',
		'hero-4.webp' => 'قهوه آسیاب‌شده در دست',
	);
}

/**
 * Build the slide layers.
 *
 * @return string
 */
function cafebean_hero_slides_markup() {
	$slides = cafebean_hero_slides();
	$total  = count( $slides );
	$cycle  = $total * 6; // Six seconds on screen per slide.

	// Decorative: the hero's heading already carries the meaning, so the whole
	// layer is hidden from assistive technology.
	$html = '<div class="cb-hero__slides" aria-hidden="true">';

	$index = 0;
	foreach ( $slides as $file => $label ) {
		/*
		 * A negative delay starts each layer part-way through the same
		 * animation, which staggers them without a JS timer. The offset counts
		 * backwards from the full cycle: using the slide's own position instead
		 * advances later slides so they appear sooner, playing 1, 4, 3, 2.
		 */
		$offset = ( ( $total - $index ) % $total ) * 6;

		$url = esc_url( CAFEBEAN_URI . '/assets/img/' . $file );

		/*
		 * Only the first slide carries its image in the markup. The rest are
		 * handed over after the load event, because four background images
		 * fetched up front competed with the one actually on screen and pushed
		 * largest-contentful-paint from 3.5s to 4.6s.
		 */
		$source = 0 === $index
			? sprintf( 'background-image:url(%s);', $url )
			: '';

		$html .= sprintf(
			'<span class="cb-hero__slide" data-cb-bg="%1$s" style="%2$sanimation-duration:%3$ds;animation-delay:-%4$ds"></span>',
			$url,
			$source,
			(int) $cycle,
			(int) $offset
		);
		++$index;
	}

	return $html . '</div>';
}

/**
 * Inject the slides into the hero group block.
 *
 * Done here rather than in the page content so the hero text stays editable as
 * ordinary blocks in wp-admin, and so a shortcode cannot be mangled by wpautop.
 *
 * @param string $content Rendered block HTML.
 * @param array  $block   Parsed block.
 * @return string
 */
function cafebean_inject_hero_slides( $content, $block ) {
	if ( 'core/group' !== ( $block['blockName'] ?? '' ) ) {
		return $content;
	}
	if ( false === strpos( (string) $content, 'cb-hero' ) ) {
		return $content;
	}

	return preg_replace(
		'/(<div[^>]*class="[^"]*cb-hero[^"]*"[^>]*>)/',
		'$1' . cafebean_hero_slides_markup(),
		$content,
		1
	);
}
add_filter( 'render_block', 'cafebean_inject_hero_slides', 10, 2 );

/**
 * Hand the later slides their images once the page has finished loading.
 *
 * Inline and dependency-free: this must not wait on jQuery, which is itself
 * render-blocking. Without JavaScript the first slide simply stays put, which
 * is the same result as prefers-reduced-motion.
 *
 * @return void
 */
function cafebean_hero_defer_script() {
	if ( ! is_front_page() ) {
		return;
	}
	?>
	<script>
	( function () {
		function load() {
			var slides = document.querySelectorAll( '.cb-hero__slide[data-cb-bg]' );
			for ( var i = 0; i < slides.length; i++ ) {
				if ( ! slides[ i ].style.backgroundImage ) {
					slides[ i ].style.backgroundImage = 'url(' + slides[ i ].dataset.cbBg + ')';
				}
			}
		}
		if ( document.readyState === 'complete' ) {
			load();
		} else {
			window.addEventListener( 'load', load );
		}
	} )();
	</script>
	<?php
}
add_action( 'wp_footer', 'cafebean_hero_defer_script', 99 );
