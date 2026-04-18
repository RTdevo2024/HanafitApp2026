<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the [fitness_checkout_flow] shortcode and manages its frontend assets.
 *
 * Shortcode usage: [fitness_checkout_flow]
 *
 * The shortcode outputs the app shell (#fitness-app-container).
 * The 6-step HTML and JavaScript controller are injected in Part 2.
 *
 * Data passed to the JS layer via data-* attributes on the container:
 *   data-nonce        → wp_create_nonce( 'fp_checkout_nonce' )
 *   data-ajax-url     → admin_url( 'admin-ajax.php' )
 *   data-logged-in    → '1' | '0'
 *   data-start-step   → 1 (guest) | 2 (already logged-in user)
 *   data-total-steps  → 6 (guest) | 5 (logged-in, auth step skipped)
 *   data-plan-map     → JSON { workout_product_id, meal_product_id }
 *   data-goals        → JSON array of goal strings from admin settings
 *   data-activities   → JSON array of activity-level strings
 *   data-diseases     → JSON array of disease strings
 *   data-eating-dis   → JSON array of eating-disorder strings
 */
class FitnessPro_Checkout_UI {

	const SHORTCODE  = 'fitness_checkout_flow';
	const NONCE_KEY  = 'fp_checkout_nonce';
	const TOTAL_STEPS = 6;

	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	// ─── Hook Registration ────────────────────────────────────────────────────

	public function register_shortcode() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Conditionally enqueue assets only on pages that contain the shortcode.
	 * Hooked to wp_enqueue_scripts.
	 */
	public function maybe_enqueue_assets() {
		global $post;

		if ( ! is_singular() || ! is_a( $post, 'WP_Post' ) ) {
			return;
		}
		if ( ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
		}

		$this->do_enqueue();
	}

	private function do_enqueue() {
		// Google Fonts — Vazirmatn as reliable fallback for Yekan Bakh
		wp_enqueue_style(
			'fitnesspro-vazirmatn',
			'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'fitnesspro-checkout-flow',
			FITNESSPRO_PLUGIN_URL . 'assets/css/checkout-flow.css',
			array( 'fitnesspro-vazirmatn' ),
			$this->version
		);

		// JS controller registered here, populated in Part 2.
		// wp_enqueue_script( 'fitnesspro-checkout-flow', ... , true );
	}

	// ─── Shortcode Renderer ───────────────────────────────────────────────────

	public function render( $atts ): string {
		shortcode_atts( array(), $atts, self::SHORTCODE );

		// Pull all dynamic field data from settings at render time so the JS
		// layer receives them as JSON without needing a separate AJAX call.
		$settings         = new FitnessPro_Settings();
		$product_map      = FitnessPro_Settings::get_product_map();
		$goals            = $settings->get_field_items( 'goals' );
		$activities       = $settings->get_field_items( 'activity_levels' );
		$diseases         = $settings->get_field_items( 'diseases' );
		$eating_disorders = $settings->get_field_items( 'eating_disorders' );

		$is_logged_in = is_user_logged_in();
		$start_step   = $is_logged_in ? 2 : 1;
		$total_steps  = $is_logged_in ? self::TOTAL_STEPS - 1 : self::TOTAL_STEPS;

		// Fallback activity levels when admin list is empty
		if ( empty( $activities ) ) {
			$activities = array(
				__( 'کم‌تحرک', 'fitnesspro' ),
				__( 'کمی فعال', 'fitnesspro' ),
				__( 'نسبتاً فعال', 'fitnesspro' ),
				__( 'بسیار فعال', 'fitnesspro' ),
				__( 'فوق فعال', 'fitnesspro' ),
			);
		}

		// Fallback goals
		if ( empty( $goals ) ) {
			$goals = array(
				__( 'کاهش وزن', 'fitnesspro' ),
				__( 'افزایش حجم عضلانی', 'fitnesspro' ),
				__( 'تناسب اندام', 'fitnesspro' ),
				__( 'افزایش استقامت', 'fitnesspro' ),
			);
		}

		ob_start();
		?>
		<div id="fitness-app-container"
			class="fitness-rtl"
			data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_KEY ) ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-logged-in="<?php echo $is_logged_in ? '1' : '0'; ?>"
			data-start-step="<?php echo esc_attr( $start_step ); ?>"
			data-total-steps="<?php echo esc_attr( $total_steps ); ?>"
			data-plan-map="<?php echo esc_attr( wp_json_encode( $product_map ) ); ?>"
			data-goals="<?php echo esc_attr( wp_json_encode( $goals ) ); ?>"
			data-activities="<?php echo esc_attr( wp_json_encode( $activities ) ); ?>"
			data-diseases="<?php echo esc_attr( wp_json_encode( $diseases ) ); ?>"
			data-eating-dis="<?php echo esc_attr( wp_json_encode( $eating_disorders ) ); ?>"
			dir="rtl"
			role="main"
			aria-label="<?php esc_attr_e( 'فرم دریافت برنامه فیتنس', 'fitnesspro' ); ?>">

			<div class="fco-inner">

				<?php /* ── App Header: Brand + Progress Bar ── */ ?>
				<header class="fco-header">
					<div class="fco-brand" aria-hidden="true">
						<span class="fco-brand__icon"></span>
						<span class="fco-brand__name"><?php esc_html_e( 'فیتنس‌پرو', 'fitnesspro' ); ?></span>
					</div>

					<div class="fco-progress-wrap" role="progressbar"
						aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
						aria-label="<?php esc_attr_e( 'پیشرفت فرم', 'fitnesspro' ); ?>">
						<div class="fco-progress-track">
							<div class="fco-progress-fill" id="fco-progress-fill"></div>
						</div>
					</div>

					<div class="fco-step-counter" aria-live="polite">
						<span class="fco-step-current" id="fco-step-current">
							<?php echo esc_html( $start_step ); ?>
						</span>
						<span class="fco-step-sep">/</span>
						<span class="fco-step-total"><?php echo esc_html( $total_steps ); ?></span>
					</div>
				</header>

				<?php /* ── Steps Wrapper — HTML steps injected by JS (Part 2) ── */ ?>
				<div class="fco-steps-wrapper" id="fco-steps-wrapper">

					<?php /* Shown until JS initialises */ ?>
					<div class="fco-init-spinner" id="fco-init-spinner" aria-hidden="true">
						<div class="fco-spinner"></div>
						<p class="fco-spinner-text"><?php esc_html_e( 'در حال بارگذاری...', 'fitnesspro' ); ?></p>
					</div>

					<noscript>
						<div class="fco-noscript-msg">
							<p><?php esc_html_e( 'برای استفاده از این بخش، لطفاً جاوا اسکریپت مرورگر خود را فعال کنید.', 'fitnesspro' ); ?></p>
						</div>
					</noscript>

				</div><!-- #fco-steps-wrapper -->

			</div><!-- .fco-inner -->

		</div><!-- #fitness-app-container -->
		<?php
		return ob_get_clean();
	}
}
