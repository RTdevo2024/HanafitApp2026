<?php
defined( 'ABSPATH' ) || exit;

/**
 * [fitness_ai_processing] shortcode — full-screen 10-second AI animation
 * displayed after payment, before redirecting to the user dashboard.
 *
 * Usage: [fitness_ai_processing redirect="https://example.com/dashboard" seconds="10"]
 */
class FitnessPro_AI_Landing {

	const SHORTCODE    = 'fitness_ai_processing';
	const PROCESS_SECS = 10;

	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	// ─── Hook Registration ────────────────────────────────────────────────────

	public function register_shortcode() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

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
		wp_enqueue_style(
			'fitnesspro-vazirmatn',
			'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'fitnesspro-ai-landing',
			FITNESSPRO_PLUGIN_URL . 'assets/css/ai-landing.css',
			array( 'fitnesspro-vazirmatn' ),
			$this->version
		);
		wp_enqueue_script(
			'fitnesspro-ai-landing',
			FITNESSPRO_PLUGIN_URL . 'assets/js/ai-landing.js',
			array(),
			$this->version,
			true
		);
	}

	// ─── Shortcode Renderer ───────────────────────────────────────────────────

	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'redirect' => '',
				'seconds'  => self::PROCESS_SECS,
			),
			$atts,
			self::SHORTCODE
		);

		$seconds = max( 3, absint( $atts['seconds'] ) );

		if ( ! empty( $atts['redirect'] ) ) {
			$redirect_url = esc_url_raw( $atts['redirect'] );
		} elseif ( FitnessPro_Core::is_woocommerce_active() ) {
			$redirect_url = wc_get_account_endpoint_url( 'dashboard' );
		} else {
			$redirect_url = home_url( '/dashboard/' );
		}

		$steps = array(
			array( 'delay' => 1, 'label' => __( 'تحلیل شاخص توده بدنی', 'fitnesspro' ) ),
			array( 'delay' => 3, 'label' => __( 'پردازش اطلاعات سلامت', 'fitnesspro' ) ),
			array( 'delay' => 5, 'label' => __( 'طراحی برنامه اختصاصی', 'fitnesspro' ) ),
			array( 'delay' => 8, 'label' => __( 'نهایی‌سازی و آماده‌سازی', 'fitnesspro' ) ),
		);

		ob_start();
		?>
		<div id="fp-ai-container"
			class="fp-ai-container"
			data-redirect="<?php echo esc_attr( $redirect_url ); ?>"
			data-seconds="<?php echo esc_attr( $seconds ); ?>"
			dir="rtl"
			role="main"
			aria-label="<?php esc_attr_e( 'در حال پردازش برنامه', 'fitnesspro' ); ?>">

			<?php /* ── Animated background layer ── */ ?>
			<div class="fp-ai-bg" aria-hidden="true">
				<div class="fp-ai-grid"></div>
				<?php for ( $i = 1; $i <= 8; $i++ ) : ?>
					<div class="fp-ai-particle fp-ai-particle--<?php echo $i; ?>"></div>
				<?php endfor; ?>
				<div class="fp-ai-scanline"></div>
			</div>

			<?php /* ── Main content ── */ ?>
			<div class="fp-ai-body">

				<?php /* Glowing orb */ ?>
				<div class="fp-ai-visual" aria-hidden="true">
					<div class="fp-ai-orb">
						<div class="fp-ai-orb__ring fp-ai-orb__ring--3"></div>
						<div class="fp-ai-orb__ring fp-ai-orb__ring--2"></div>
						<div class="fp-ai-orb__ring fp-ai-orb__ring--1"></div>
						<div class="fp-ai-orb__core">
							<span class="fp-ai-orb__icon">🧠</span>
						</div>
						<div class="fp-ai-orb__scan"></div>
					</div>
				</div>

				<?php /* Text */ ?>
				<div class="fp-ai-text-block">
					<p class="fp-ai-badge" aria-hidden="true">
						<?php esc_html_e( 'هوش مصنوعی', 'fitnesspro' ); ?>
					</p>
					<h1 class="fp-ai-headline">
						<?php esc_html_e( 'در حال تحلیل داده‌های بدنی و طراحی برنامه اختصاصی شما با هوش مصنوعی...', 'fitnesspro' ); ?>
					</h1>
					<p class="fp-ai-status" id="fp-ai-status" aria-live="polite">
						<?php esc_html_e( 'در حال بارگذاری اطلاعات...', 'fitnesspro' ); ?>
					</p>
				</div>

				<?php /* Progress bar */ ?>
				<div class="fp-ai-progress-wrap">
					<div class="fp-ai-progress-bar" role="progressbar"
						aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"
						aria-label="<?php esc_attr_e( 'پیشرفت پردازش', 'fitnesspro' ); ?>">
						<div class="fp-ai-progress-fill" id="fp-ai-fill"></div>
					</div>
					<div class="fp-ai-progress-meta">
						<span class="fp-ai-pct"><span id="fp-ai-pct">0</span>%</span>
						<span class="fp-ai-eta" id="fp-ai-eta">
							<?php printf(
								/* translators: %d: seconds remaining */
								esc_html__( '%d ثانیه دیگر', 'fitnesspro' ),
								$seconds
							); ?>
						</span>
					</div>
				</div>

				<?php /* Step checklist */ ?>
				<ul class="fp-ai-steps" id="fp-ai-steps" aria-label="<?php esc_attr_e( 'مراحل پردازش', 'fitnesspro' ); ?>">
					<?php foreach ( $steps as $step ) : ?>
					<li class="fp-ai-step-item" data-delay="<?php echo esc_attr( $step['delay'] ); ?>">
						<span class="fp-ai-step-dot" aria-hidden="true"></span>
						<span class="fp-ai-step-label"><?php echo esc_html( $step['label'] ); ?></span>
					</li>
					<?php endforeach; ?>
				</ul>

			</div><!-- .fp-ai-body -->
		</div><!-- #fp-ai-container -->
		<?php
		return ob_get_clean();
	}
}
