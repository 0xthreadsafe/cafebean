<?php
/**
 * Plugin Name: CafeBean Forms and Content Blocks
 * Description: شورت‌کدهای محتوایی و فرم تماس با محافظت honeypot.
 * Version: 0.1.0
 * Text Domain: cafebean
 *
 * These are shortcodes used inside page content, so they live in mu-plugins:
 * a theme switch must not turn published pages into raw shortcode text.
 *
 * @package cafebean
 */

defined( 'ABSPATH' ) || exit;

const CAFEBEAN_MESSAGE_CPT = 'cafebean_message';
// A bot fills every field it finds; a human never sees this one.
const CAFEBEAN_HONEYPOT    = 'cafebean_website';

/**
 * Store contact submissions so the demo can show them in the admin.
 *
 * @return void
 */
function cafebean_register_message_cpt() {
	register_post_type(
		CAFEBEAN_MESSAGE_CPT,
		array(
			'labels'       => array(
				'name'          => __( 'پیام‌های تماس', 'cafebean' ),
				'singular_name' => __( 'پیام تماس', 'cafebean' ),
				'menu_name'     => __( 'پیام‌های تماس', 'cafebean' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-email',
			'capability_type' => 'post',
			'capabilities' => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap' => true,
			'supports'     => array( 'title', 'editor' ),
		)
	);
}
add_action( 'init', 'cafebean_register_message_cpt' );

/**
 * Trust badges for the home page.
 *
 * @return string
 */
function cafebean_trust_badges_shortcode() {
	$badges = array(
		array( 'ارسال سریع', 'سفارش‌های تهران همان روز ارسال می‌شود.' ),
		array( 'پرداخت امن', 'پرداخت از طریق درگاه معتبر و رمزگذاری‌شده.' ),
		array( 'ضمانت بازگشت', 'تا هفت روز امکان بازگشت کالا وجود دارد.' ),
	);

	$out = '<ul class="cb-trust">';
	foreach ( $badges as $badge ) {
		$out .= sprintf(
			'<li class="cb-trust__item"><strong class="cb-trust__title">%s</strong><span class="cb-trust__text">%s</span></li>',
			esc_html( $badge[0] ),
			esc_html( $badge[1] )
		);
	}
	$out .= '</ul>';

	return $out;
}
add_shortcode( 'cafebean_trust_badges', 'cafebean_trust_badges_shortcode' );

/**
 * Handle a contact submission.
 *
 * No reCAPTCHA by design - it is unreliable from Iran. A honeypot plus a nonce
 * plus server-side validation covers casual spam without a third-party request.
 *
 * @return array{sent:bool,errors:string[],values:array}
 */
function cafebean_handle_contact_submission() {
	$state = array(
		'sent'   => false,
		'errors' => array(),
		'values' => array(
			'name'    => '',
			'email'   => '',
			'message' => '',
		),
	);

	if ( empty( $_POST['cafebean_contact_submit'] ) ) {
		return $state;
	}

	if ( ! isset( $_POST['cafebean_contact_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cafebean_contact_nonce'] ) ), 'cafebean_contact' ) ) {
		$state['errors'][] = __( 'درخواست معتبر نیست. لطفاً صفحه را تازه کنید و دوباره تلاش کنید.', 'cafebean' );
		return $state;
	}

	// Honeypot: a filled value means a bot, so accept silently and discard.
	if ( ! empty( $_POST[ CAFEBEAN_HONEYPOT ] ) ) {
		$state['sent'] = true;
		return $state;
	}

	$name    = isset( $_POST['cafebean_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cafebean_name'] ) ) : '';
	$email   = isset( $_POST['cafebean_email'] ) ? sanitize_email( wp_unslash( $_POST['cafebean_email'] ) ) : '';
	$message = isset( $_POST['cafebean_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cafebean_message'] ) ) : '';

	$state['values'] = array(
		'name'    => $name,
		'email'   => $email,
		'message' => $message,
	);

	if ( '' === $name ) {
		$state['errors'][] = __( 'لطفاً نام خود را وارد کنید.', 'cafebean' );
	}
	if ( '' === $email || ! is_email( $email ) ) {
		$state['errors'][] = __( 'لطفاً یک نشانی ایمیل معتبر وارد کنید.', 'cafebean' );
	}
	if ( mb_strlen( $message ) < 10 ) {
		$state['errors'][] = __( 'متن پیام باید دست‌کم ۱۰ نویسه باشد.', 'cafebean' );
	}

	if ( $state['errors'] ) {
		return $state;
	}

	wp_insert_post(
		array(
			'post_type'    => CAFEBEAN_MESSAGE_CPT,
			'post_status'  => 'private',
			/* translators: %s: sender name. */
			'post_title'   => sprintf( __( 'پیام از %s', 'cafebean' ), $name ),
			'post_content' => $message,
			'meta_input'   => array(
				'_cafebean_email' => $email,
			),
		)
	);

	$state['sent']   = true;
	$state['values'] = array(
		'name'    => '',
		'email'   => '',
		'message' => '',
	);

	return $state;
}

/**
 * Contact form.
 *
 * @return string
 */
function cafebean_contact_form_shortcode() {
	$state = cafebean_handle_contact_submission();

	ob_start();

	if ( $state['sent'] ) {
		printf(
			'<p class="cb-notice cb-notice--success">%s</p>',
			esc_html__( 'پیام شما دریافت شد. به زودی پاسخ می‌دهیم.', 'cafebean' )
		);
	}

	foreach ( $state['errors'] as $error ) {
		printf( '<p class="cb-notice cb-notice--error">%s</p>', esc_html( $error ) );
	}
	?>
	<form class="cb-form" method="post" action="">
		<?php wp_nonce_field( 'cafebean_contact', 'cafebean_contact_nonce' ); ?>

		<p class="cb-form__row">
			<label for="cafebean_name"><?php esc_html_e( 'نام', 'cafebean' ); ?></label>
			<input type="text" id="cafebean_name" name="cafebean_name" required
				value="<?php echo esc_attr( $state['values']['name'] ); ?>">
		</p>

		<p class="cb-form__row">
			<label for="cafebean_email"><?php esc_html_e( 'ایمیل', 'cafebean' ); ?></label>
			<input type="email" id="cafebean_email" name="cafebean_email" required
				value="<?php echo esc_attr( $state['values']['email'] ); ?>">
		</p>

		<p class="cb-form__row">
			<label for="cafebean_message"><?php esc_html_e( 'پیام', 'cafebean' ); ?></label>
			<textarea id="cafebean_message" name="cafebean_message" rows="6" required><?php
				echo esc_textarea( $state['values']['message'] );
			?></textarea>
		</p>

		<?php // Hidden from people, visible to bots. Not type=hidden: bots skip those. ?>
		<p class="cb-honeypot" aria-hidden="true">
			<label for="<?php echo esc_attr( CAFEBEAN_HONEYPOT ); ?>">
				<?php esc_html_e( 'این فیلد را خالی بگذارید', 'cafebean' ); ?>
			</label>
			<input type="text" tabindex="-1" autocomplete="off"
				id="<?php echo esc_attr( CAFEBEAN_HONEYPOT ); ?>"
				name="<?php echo esc_attr( CAFEBEAN_HONEYPOT ); ?>" value="">
		</p>

		<p class="cb-form__row">
			<button type="submit" name="cafebean_contact_submit" value="1" class="button">
				<?php esc_html_e( 'ارسال پیام', 'cafebean' ); ?>
			</button>
		</p>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'cafebean_contact_form', 'cafebean_contact_form_shortcode' );

/**
 * Scrolling value-proposition ticker under the header.
 *
 * The item list is printed twice: the CSS animation translates the track by
 * exactly half its width, so the second run takes over where the first ends and
 * the loop has no visible seam.
 *
 * @return void
 */
function cafebean_ticker() {
	$items = array(
		__( 'ارسال رایگان بالای ۵۰۰ هزار تومان', 'cafebean' ),
		__( 'برشته‌کاری روزانه', 'cafebean' ),
		__( 'پرداخت امن', 'cafebean' ),
		__( 'ضمانت بازگشت هفت‌روزه', 'cafebean' ),
		__( 'ارسال به سراسر ایران', 'cafebean' ),
	);

	echo '<div class="cb-ticker"><div class="cb-ticker__track">';
	// aria-hidden on the duplicate so screen readers announce the list once.
	for ( $run = 0; $run < 2; $run++ ) {
		foreach ( $items as $item ) {
			printf(
				'<span class="cb-ticker__item"%s>%s</span>',
				1 === $run ? ' aria-hidden="true"' : '',
				esc_html( $item )
			);
		}
	}
	echo '</div></div>';
}
add_action( 'wp_body_open', 'cafebean_ticker', 6 );
