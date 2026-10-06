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
 * Mark the story band so its photo is fetched after load.
 *
 * The band sits well below the fold, but a CSS background is fetched eagerly
 * regardless, and its weight was competing with the hero for bandwidth.
 *
 * @param string $content Rendered block HTML.
 * @param array  $block   Parsed block.
 * @return string
 */
function cafebean_defer_band_image( $content, $block ) {
	if ( 'core/group' !== ( $block['blockName'] ?? '' ) ) {
		return $content;
	}
	if ( false === strpos( (string) $content, 'cb-band' ) ) {
		return $content;
	}

	return preg_replace(
		'/(<div[^>]*class="[^"]*cb-band[^"]*")/',
		'$1 data-cb-bg="' . esc_url( CAFEBEAN_URI . '/assets/img/band.webp' ) . '"',
		$content,
		1
	);
}
add_filter( 'render_block', 'cafebean_defer_band_image', 10, 2 );

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
		function paint( el ) {
			if ( el.style.backgroundImage ) {
				return;
			}
			var image = 'url(' + el.dataset.cbBg + ')';
			// The band keeps its darkening scrim, which lives in the same
			// property, so it has to be re-stated alongside the image.
			el.style.backgroundImage = el.classList.contains( 'cb-band' )
				? 'linear-gradient(rgba(25,15,9,.76),rgba(25,15,9,.76)),' + image
				: image;
		}

		function load() {
			// Off-screen bands wait until the reader approaches them rather
			// than competing with the hero for bandwidth straight after load.
			var lazy = document.querySelectorAll( '.cb-band[data-cb-bg]' );
			if ( 'IntersectionObserver' in window ) {
				var io = new IntersectionObserver( function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( entry.isIntersecting ) {
							paint( entry.target );
							io.unobserve( entry.target );
						}
					} );
				}, { rootMargin: '300px' } );
				for ( var b = 0; b < lazy.length; b++ ) {
					io.observe( lazy[ b ] );
				}
			} else {
				for ( var f = 0; f < lazy.length; f++ ) {
					paint( lazy[ f ] );
				}
			}

			// The second slide is not shown for six seconds, so there is no
			// reason to fetch the rotation while the first paint settles.
			setTimeout( function () {
				var slides = document.querySelectorAll( '.cb-hero__slide[data-cb-bg]' );
				for ( var s = 0; s < slides.length; s++ ) {
					paint( slides[ s ] );
				}
			}, 2500 );
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
