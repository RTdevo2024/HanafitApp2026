<?php
defined( 'ABSPATH' ) || exit;

class FitnessPro_Checkout_UI {

	const SHORTCODE    = 'fitness_checkout_flow';
	const NONCE_KEY    = 'fp_checkout_nonce';
	const TOTAL_STEPS  = 6;
	const TRANSIENT_TTL = 7200; // 2 hours

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
			'fitnesspro-checkout-flow',
			FITNESSPRO_PLUGIN_URL . 'assets/css/checkout-flow.css',
			array( 'fitnesspro-vazirmatn' ),
			$this->version
		);
		wp_enqueue_script(
			'fitnesspro-checkout-flow',
			FITNESSPRO_PLUGIN_URL . 'assets/js/checkout-flow.js',
			array(),
			$this->version,
			true
		);
	}

	// ─── AJAX: Auth (Login / Register) ───────────────────────────────────────

	public function ajax_auth() {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		$action = isset( $_POST['auth_action'] ) ? sanitize_key( $_POST['auth_action'] ) : '';

		if ( 'login' === $action ) {
			$this->do_login();
		} elseif ( 'register' === $action ) {
			$this->do_register();
		} else {
			wp_send_json_error( array( 'message' => __( 'درخواست نامعتبر است.', 'fitnesspro' ) ) );
		}
	}

	private function do_login() {
		$email    = isset( $_POST['email'] )    ? sanitize_email( wp_unslash( $_POST['email'] ) )         : '';
		$password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] )                        : '';

		if ( empty( $email ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'ایمیل و رمز عبور الزامی است.', 'fitnesspro' ) ) );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'کاربری با این ایمیل یافت نشد.', 'fitnesspro' ) ) );
		}

		$result = wp_authenticate( $user->user_login, $password );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'رمز عبور اشتباه است.', 'fitnesspro' ) ) );
		}

		wp_set_auth_cookie( $result->ID, true );
		wp_set_current_user( $result->ID );

		wp_send_json_success( array(
			'message' => __( 'ورود موفق!', 'fitnesspro' ),
			'nonce'   => wp_create_nonce( self::NONCE_KEY ),
		) );
	}

	private function do_register() {
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] )  ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) )  : '';
		$email      = isset( $_POST['email'] )       ? sanitize_email( wp_unslash( $_POST['email'] ) )           : '';
		$password   = isset( $_POST['password'] )    ? wp_unslash( $_POST['password'] )                          : '';
		$phone      = isset( $_POST['phone'] )       ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )      : '';

		if ( empty( $email ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'ایمیل و رمز عبور الزامی است.', 'fitnesspro' ) ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'فرمت ایمیل نامعتبر است.', 'fitnesspro' ) ) );
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'این ایمیل قبلاً ثبت شده است.', 'fitnesspro' ) ) );
		}

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'رمز عبور باید حداقل ۸ کاراکتر باشد.', 'fitnesspro' ) ) );
		}

		$username = sanitize_user( current( explode( '@', $email ) ) . '_' . wp_generate_password( 4, false ) );
		$user_id  = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		wp_update_user( array(
			'ID'         => $user_id,
			'first_name' => $first_name,
			'last_name'  => $last_name,
		) );

		if ( ! empty( $phone ) ) {
			update_user_meta( $user_id, 'fp_phone', $phone );
		}

		wp_set_auth_cookie( $user_id, true );
		wp_set_current_user( $user_id );

		wp_send_json_success( array(
			'message' => __( 'ثبت‌نام موفق!', 'fitnesspro' ),
			'nonce'   => wp_create_nonce( self::NONCE_KEY ),
		) );
	}

	// ─── AJAX: Save Profile to Transient ─────────────────────────────────────

	public function ajax_save_profile() {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'لطفاً ابتدا وارد شوید.', 'fitnesspro' ) ) );
		}

		$raw  = isset( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : '';
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'داده‌های ارسالی نامعتبر است.', 'fitnesspro' ) ) );
		}

		$clean = array(
			'plan_type'     => isset( $data['plan_type'] )     ? sanitize_key( $data['plan_type'] )                              : '',
			'first_name'    => isset( $data['first_name'] )    ? sanitize_text_field( $data['first_name'] )                      : '',
			'last_name'     => isset( $data['last_name'] )     ? sanitize_text_field( $data['last_name'] )                       : '',
			'age'           => isset( $data['age'] )           ? absint( $data['age'] )                                          : 0,
			'gender'        => isset( $data['gender'] )        ? sanitize_key( $data['gender'] )                                 : '',
			'height'        => isset( $data['height'] )        ? (float) $data['height']                                         : 0.0,
			'weight'        => isset( $data['weight'] )        ? (float) $data['weight']                                         : 0.0,
			'target_weight' => isset( $data['target_weight'] ) ? (float) $data['target_weight']                                  : 0.0,
			'goals'         => isset( $data['goals'] )         ? array_map( 'sanitize_text_field', (array) $data['goals'] )      : array(),
			'diseases'      => isset( $data['diseases'] )      ? array_map( 'sanitize_text_field', (array) $data['diseases'] )   : array(),
			'eating_dis'    => isset( $data['eating_dis'] )    ? array_map( 'sanitize_text_field', (array) $data['eating_dis'] ) : array(),
			'activity'      => isset( $data['activity'] )      ? sanitize_text_field( $data['activity'] )                        : '',
		);

		set_transient( 'fp_checkout_profile_' . get_current_user_id(), $clean, self::TRANSIENT_TTL );

		wp_send_json_success( array( 'message' => __( 'اطلاعات ذخیره شد.', 'fitnesspro' ) ) );
	}

	// ─── AJAX: Add WC Product to Cart ────────────────────────────────────────

	public function ajax_add_to_cart() {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'لطفاً ابتدا وارد شوید.', 'fitnesspro' ) ) );
		}

		if ( ! FitnessPro_Core::is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => __( 'ووکامرس فعال نیست.', 'fitnesspro' ) ) );
		}

		$plan_type   = isset( $_POST['plan_type'] ) ? sanitize_key( $_POST['plan_type'] ) : '';
		$product_map = FitnessPro_Settings::get_product_map();

		$product_id = 0;
		if ( 'workout' === $plan_type ) {
			$product_id = (int) ( $product_map['workout_product_id'] ?? 0 );
		} elseif ( 'meal' === $plan_type ) {
			$product_id = (int) ( $product_map['meal_product_id'] ?? 0 );
		}

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'محصول مرتبط با این برنامه یافت نشد.', 'fitnesspro' ) ) );
		}

		WC()->cart->empty_cart();
		$added = WC()->cart->add_to_cart( $product_id );

		if ( ! $added ) {
			wp_send_json_error( array( 'message' => __( 'خطا در افزودن محصول به سبد خرید.', 'fitnesspro' ) ) );
		}

		wp_send_json_success( array(
			'message'      => __( 'محصول به سبد خرید اضافه شد.', 'fitnesspro' ),
			'checkout_url' => wc_get_checkout_url(),
		) );
	}

	// ─── Shortcode Renderer ───────────────────────────────────────────────────

	public function render( $atts ): string {
		shortcode_atts( array(), $atts, self::SHORTCODE );

		$settings         = new FitnessPro_Settings();
		$product_map      = FitnessPro_Settings::get_product_map();
		$goals            = $settings->get_field_items( 'goals' );
		$activities       = $settings->get_field_items( 'activity_levels' );
		$diseases         = $settings->get_field_items( 'diseases' );
		$eating_disorders = $settings->get_field_items( 'eating_disorders' );

		if ( empty( $activities ) ) {
			$activities = array(
				__( 'کم‌تحرک', 'fitnesspro' ),
				__( 'کمی فعال', 'fitnesspro' ),
				__( 'نسبتاً فعال', 'fitnesspro' ),
				__( 'بسیار فعال', 'fitnesspro' ),
				__( 'فوق فعال', 'fitnesspro' ),
			);
		}

		if ( empty( $goals ) ) {
			$goals = array(
				__( 'کاهش وزن', 'fitnesspro' ),
				__( 'افزایش حجم عضلانی', 'fitnesspro' ),
				__( 'تناسب اندام', 'fitnesspro' ),
				__( 'افزایش استقامت', 'fitnesspro' ),
			);
		}

		$activity_icons   = array( '🪑', '🚶', '🏃', '💪', '🏆' );
		$is_logged_in     = is_user_logged_in();
		$start_step       = $is_logged_in ? 2 : 1;
		$total_steps      = $is_logged_in ? self::TOTAL_STEPS - 1 : self::TOTAL_STEPS;
		$workout_disabled = empty( $product_map['workout_product_id'] );
		$meal_disabled    = empty( $product_map['meal_product_id'] );

		ob_start();
		?>
		<div id="fitness-app-container"
			class="fitness-rtl"
			data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_KEY ) ); ?>"
			data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-logged-in="<?php echo $is_logged_in ? '1' : '0'; ?>"
			data-start-step="<?php echo esc_attr( $start_step ); ?>"
			data-total-steps="<?php echo esc_attr( $total_steps ); ?>"
			dir="rtl"
			role="main"
			aria-label="<?php esc_attr_e( 'فرم دریافت برنامه فیتنس', 'fitnesspro' ); ?>">

			<div class="fco-inner">

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
						<span class="fco-step-current" id="fco-step-current">1</span>
						<span class="fco-step-sep">/</span>
						<span class="fco-step-total"><?php echo esc_html( $total_steps ); ?></span>
					</div>
				</header>

				<div class="fco-steps-wrapper" id="fco-steps-wrapper">

					<?php /* ── Step 1: Auth — guests only ── */ ?>
					<?php if ( ! $is_logged_in ) : ?>
					<div class="fco-step" data-step="1" id="fco-step-1">
						<h2 class="fco-step-title"><?php esc_html_e( 'خوش آمدید', 'fitnesspro' ); ?></h2>
						<p class="fco-step-subtitle"><?php esc_html_e( 'برای دریافت برنامه اختصاصی، وارد شوید یا ثبت‌نام کنید.', 'fitnesspro' ); ?></p>

						<div class="fco-notice" id="fco-auth-notice" role="alert"></div>

						<div class="fco-auth-tabs" role="tablist">
							<button class="fco-auth-tab is-active" data-tab="login" role="tab" aria-selected="true">
								<?php esc_html_e( 'ورود', 'fitnesspro' ); ?>
							</button>
							<button class="fco-auth-tab" data-tab="register" role="tab" aria-selected="false">
								<?php esc_html_e( 'ثبت‌نام', 'fitnesspro' ); ?>
							</button>
						</div>

						<div class="fco-auth-panel is-active" id="fco-panel-login" role="tabpanel">
							<div class="fco-field">
								<label class="fco-label" for="fco-login-email"><?php esc_html_e( 'ایمیل', 'fitnesspro' ); ?></label>
								<input type="email" id="fco-login-email" class="fco-input"
									autocomplete="email"
									placeholder="<?php esc_attr_e( 'example@email.com', 'fitnesspro' ); ?>">
							</div>
							<div class="fco-field fco-field--password">
								<label class="fco-label" for="fco-login-password"><?php esc_html_e( 'رمز عبور', 'fitnesspro' ); ?></label>
								<input type="password" id="fco-login-password" class="fco-input"
									autocomplete="current-password"
									placeholder="<?php esc_attr_e( '••••••••', 'fitnesspro' ); ?>">
								<button type="button" class="fco-pass-toggle" data-target="fco-login-password"
									aria-label="<?php esc_attr_e( 'نمایش/مخفی رمز', 'fitnesspro' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
									</svg>
								</button>
							</div>
							<button type="button" class="fco-btn fco-btn--primary fco-btn--full" id="fco-btn-login">
								<?php esc_html_e( 'ورود به حساب', 'fitnesspro' ); ?>
							</button>
						</div>

						<div class="fco-auth-panel" id="fco-panel-register" role="tabpanel">
							<div class="fco-input-row">
								<div class="fco-field">
									<label class="fco-label" for="fco-reg-firstname"><?php esc_html_e( 'نام', 'fitnesspro' ); ?></label>
									<input type="text" id="fco-reg-firstname" class="fco-input"
										autocomplete="given-name"
										placeholder="<?php esc_attr_e( 'علی', 'fitnesspro' ); ?>">
								</div>
								<div class="fco-field">
									<label class="fco-label" for="fco-reg-lastname"><?php esc_html_e( 'نام خانوادگی', 'fitnesspro' ); ?></label>
									<input type="text" id="fco-reg-lastname" class="fco-input"
										autocomplete="family-name"
										placeholder="<?php esc_attr_e( 'رضایی', 'fitnesspro' ); ?>">
								</div>
							</div>
							<div class="fco-field">
								<label class="fco-label" for="fco-reg-email"><?php esc_html_e( 'ایمیل', 'fitnesspro' ); ?></label>
								<input type="email" id="fco-reg-email" class="fco-input"
									autocomplete="email"
									placeholder="<?php esc_attr_e( 'example@email.com', 'fitnesspro' ); ?>">
							</div>
							<div class="fco-field">
								<label class="fco-label" for="fco-reg-phone"><?php esc_html_e( 'شماره موبایل', 'fitnesspro' ); ?></label>
								<input type="tel" id="fco-reg-phone" class="fco-input"
									autocomplete="tel"
									placeholder="<?php esc_attr_e( '09XXXXXXXXX', 'fitnesspro' ); ?>">
							</div>
							<div class="fco-field fco-field--password">
								<label class="fco-label" for="fco-reg-password"><?php esc_html_e( 'رمز عبور', 'fitnesspro' ); ?></label>
								<input type="password" id="fco-reg-password" class="fco-input"
									autocomplete="new-password"
									placeholder="<?php esc_attr_e( 'حداقل ۸ کاراکتر', 'fitnesspro' ); ?>">
								<button type="button" class="fco-pass-toggle" data-target="fco-reg-password"
									aria-label="<?php esc_attr_e( 'نمایش/مخفی رمز', 'fitnesspro' ); ?>">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
									</svg>
								</button>
							</div>
							<button type="button" class="fco-btn fco-btn--primary fco-btn--full" id="fco-btn-register">
								<?php esc_html_e( 'ایجاد حساب', 'fitnesspro' ); ?>
							</button>
						</div>
					</div>
					<?php endif; ?>

					<?php /* ── Step 2: Choose Plan ── */ ?>
					<div class="fco-step" data-step="2" id="fco-step-2">
						<h2 class="fco-step-title"><?php esc_html_e( 'نوع برنامه را انتخاب کنید', 'fitnesspro' ); ?></h2>
						<p class="fco-step-subtitle"><?php esc_html_e( 'برنامه تمرینی یا تغذیه؟ یکی را انتخاب کنید.', 'fitnesspro' ); ?></p>

						<div class="fco-notice" id="fco-plan-notice" role="alert"></div>

						<div class="fco-plan-grid">
							<div class="fco-plan-card <?php echo $workout_disabled ? 'fco-plan-card--disabled' : ''; ?>"
								data-plan="workout"
								role="button"
								tabindex="<?php echo $workout_disabled ? '-1' : '0'; ?>"
								aria-disabled="<?php echo $workout_disabled ? 'true' : 'false'; ?>">
								<?php if ( ! $workout_disabled ) : ?>
									<span class="fco-plan-card__badge"><?php esc_html_e( 'محبوب', 'fitnesspro' ); ?></span>
								<?php endif; ?>
								<span class="fco-plan-card__icon">🏋️</span>
								<div class="fco-plan-card__name"><?php esc_html_e( 'برنامه تمرینی', 'fitnesspro' ); ?></div>
								<div class="fco-plan-card__desc"><?php esc_html_e( 'برنامه ۷ روزه اختصاصی متناسب با هدف شما', 'fitnesspro' ); ?></div>
								<?php if ( $workout_disabled ) : ?>
									<div class="fco-plan-card__unavailable"><?php esc_html_e( 'موجود نیست', 'fitnesspro' ); ?></div>
								<?php endif; ?>
							</div>

							<div class="fco-plan-card <?php echo $meal_disabled ? 'fco-plan-card--disabled' : ''; ?>"
								data-plan="meal"
								role="button"
								tabindex="<?php echo $meal_disabled ? '-1' : '0'; ?>"
								aria-disabled="<?php echo $meal_disabled ? 'true' : 'false'; ?>">
								<span class="fco-plan-card__icon">🥗</span>
								<div class="fco-plan-card__name"><?php esc_html_e( 'برنامه تغذیه', 'fitnesspro' ); ?></div>
								<div class="fco-plan-card__desc"><?php esc_html_e( 'رژیم غذایی متعادل طراحی‌شده توسط متخصص', 'fitnesspro' ); ?></div>
								<?php if ( $meal_disabled ) : ?>
									<div class="fco-plan-card__unavailable"><?php esc_html_e( 'موجود نیست', 'fitnesspro' ); ?></div>
								<?php endif; ?>
							</div>
						</div>

						<div class="fco-nav">
							<?php if ( ! $is_logged_in ) : ?>
								<button type="button" class="fco-btn fco-btn--secondary fco-btn-back"><?php esc_html_e( 'برگشت', 'fitnesspro' ); ?></button>
							<?php endif; ?>
							<button type="button" class="fco-btn fco-btn--primary fco-btn-next"><?php esc_html_e( 'ادامه', 'fitnesspro' ); ?></button>
						</div>
					</div>

					<?php /* ── Step 3: Profile Form ── */ ?>
					<div class="fco-step" data-step="3" id="fco-step-3">
						<h2 class="fco-step-title"><?php esc_html_e( 'اطلاعات پروفایل', 'fitnesspro' ); ?></h2>
						<p class="fco-step-subtitle"><?php esc_html_e( 'این اطلاعات برای طراحی برنامه اختصاصی شما استفاده می‌شود.', 'fitnesspro' ); ?></p>

						<div class="fco-notice" id="fco-profile-notice" role="alert"></div>

						<div class="fco-input-row">
							<div class="fco-field">
								<label class="fco-label" for="fco-firstname"><?php esc_html_e( 'نام', 'fitnesspro' ); ?></label>
								<input type="text" id="fco-firstname" class="fco-input"
									placeholder="<?php esc_attr_e( 'علی', 'fitnesspro' ); ?>">
							</div>
							<div class="fco-field">
								<label class="fco-label" for="fco-lastname"><?php esc_html_e( 'نام خانوادگی', 'fitnesspro' ); ?></label>
								<input type="text" id="fco-lastname" class="fco-input"
									placeholder="<?php esc_attr_e( 'رضایی', 'fitnesspro' ); ?>">
							</div>
						</div>

						<div class="fco-field">
							<label class="fco-label"><?php esc_html_e( 'جنسیت', 'fitnesspro' ); ?></label>
							<div class="fco-gender-row">
								<button type="button" class="fco-gender-btn" data-gender="male"><?php esc_html_e( '♂ مرد', 'fitnesspro' ); ?></button>
								<button type="button" class="fco-gender-btn" data-gender="female"><?php esc_html_e( '♀ زن', 'fitnesspro' ); ?></button>
							</div>
						</div>

						<div class="fco-input-row">
							<div class="fco-field">
								<label class="fco-label" for="fco-age"><?php esc_html_e( 'سن (سال)', 'fitnesspro' ); ?></label>
								<input type="number" id="fco-age" class="fco-input" min="12" max="90" placeholder="25">
							</div>
							<div class="fco-field">
								<label class="fco-label" for="fco-height"><?php esc_html_e( 'قد (سانتی‌متر)', 'fitnesspro' ); ?></label>
								<input type="number" id="fco-height" class="fco-input" min="100" max="250" placeholder="175">
							</div>
						</div>

						<div class="fco-input-row">
							<div class="fco-field">
								<label class="fco-label" for="fco-weight"><?php esc_html_e( 'وزن (کیلوگرم)', 'fitnesspro' ); ?></label>
								<input type="number" id="fco-weight" class="fco-input" min="30" max="300" step="0.5" placeholder="70">
							</div>
							<div class="fco-field">
								<label class="fco-label" for="fco-target-weight"><?php esc_html_e( 'وزن هدف (کیلوگرم)', 'fitnesspro' ); ?></label>
								<input type="number" id="fco-target-weight" class="fco-input" min="30" max="300" step="0.5" placeholder="65">
							</div>
						</div>

						<div class="fco-field">
							<label class="fco-label"><?php esc_html_e( 'هدف اصلی شما', 'fitnesspro' ); ?></label>
							<div class="fco-chips" id="fco-goals-chips">
								<?php foreach ( $goals as $goal ) : ?>
									<span class="fco-chip" data-value="<?php echo esc_attr( $goal ); ?>"><?php echo esc_html( $goal ); ?></span>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="fco-nav">
							<button type="button" class="fco-btn fco-btn--secondary fco-btn-back"><?php esc_html_e( 'برگشت', 'fitnesspro' ); ?></button>
							<button type="button" class="fco-btn fco-btn--primary fco-btn-next"><?php esc_html_e( 'ادامه', 'fitnesspro' ); ?></button>
						</div>
					</div>

					<?php /* ── Step 4: Health Form ── */ ?>
					<div class="fco-step" data-step="4" id="fco-step-4">
						<h2 class="fco-step-title"><?php esc_html_e( 'وضعیت سلامت', 'fitnesspro' ); ?></h2>
						<p class="fco-step-subtitle"><?php esc_html_e( 'موارد مرتبط با وضعیت سلامت خود را انتخاب کنید (اختیاری).', 'fitnesspro' ); ?></p>

						<?php if ( ! empty( $diseases ) ) : ?>
						<div class="fco-field">
							<label class="fco-label fco-section-label"><?php esc_html_e( 'بیماری‌ها', 'fitnesspro' ); ?></label>
							<div class="fco-chips" id="fco-diseases-chips">
								<?php foreach ( $diseases as $disease ) : ?>
									<span class="fco-chip" data-value="<?php echo esc_attr( $disease ); ?>"><?php echo esc_html( $disease ); ?></span>
								<?php endforeach; ?>
							</div>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $eating_disorders ) ) : ?>
						<div class="fco-field">
							<label class="fco-label fco-section-label"><?php esc_html_e( 'اختلالات تغذیه‌ای', 'fitnesspro' ); ?></label>
							<div class="fco-chips" id="fco-eating-chips">
								<?php foreach ( $eating_disorders as $disorder ) : ?>
									<span class="fco-chip" data-value="<?php echo esc_attr( $disorder ); ?>"><?php echo esc_html( $disorder ); ?></span>
								<?php endforeach; ?>
							</div>
						</div>
						<?php endif; ?>

						<?php if ( empty( $diseases ) && empty( $eating_disorders ) ) : ?>
						<div class="fco-card">
							<p style="margin:0; color:var(--fco-text-muted); font-size:14px;">
								<?php esc_html_e( 'موردی برای نمایش وجود ندارد. می‌توانید ادامه دهید.', 'fitnesspro' ); ?>
							</p>
						</div>
						<?php endif; ?>

						<div class="fco-nav">
							<button type="button" class="fco-btn fco-btn--secondary fco-btn-back"><?php esc_html_e( 'برگشت', 'fitnesspro' ); ?></button>
							<button type="button" class="fco-btn fco-btn--primary fco-btn-next"><?php esc_html_e( 'ادامه', 'fitnesspro' ); ?></button>
						</div>
					</div>

					<?php /* ── Step 5: Activity Level ── */ ?>
					<div class="fco-step" data-step="5" id="fco-step-5">
						<h2 class="fco-step-title"><?php esc_html_e( 'سطح فعالیت', 'fitnesspro' ); ?></h2>
						<p class="fco-step-subtitle"><?php esc_html_e( 'سطح فعالیت روزانه خود را به طور تقریبی انتخاب کنید.', 'fitnesspro' ); ?></p>

						<div class="fco-notice" id="fco-activity-notice" role="alert"></div>

						<div class="fco-activity-grid" id="fco-activity-grid">
							<?php foreach ( $activities as $i => $activity ) :
								$icon = $activity_icons[ $i ] ?? '⚡';
							?>
							<div class="fco-activity-card" data-value="<?php echo esc_attr( $activity ); ?>" role="button" tabindex="0">
								<span class="fco-activity-card__icon"><?php echo $icon; ?></span>
								<span class="fco-activity-card__label"><?php echo esc_html( $activity ); ?></span>
							</div>
							<?php endforeach; ?>
						</div>

						<div class="fco-nav">
							<button type="button" class="fco-btn fco-btn--secondary fco-btn-back"><?php esc_html_e( 'برگشت', 'fitnesspro' ); ?></button>
							<button type="button" class="fco-btn fco-btn--primary fco-btn-next"><?php esc_html_e( 'ادامه', 'fitnesspro' ); ?></button>
						</div>
					</div>

					<?php /* ── Step 6: BMI Gauge + Summary + Pay ── */ ?>
					<div class="fco-step" data-step="6" id="fco-step-6">
						<h2 class="fco-step-title"><?php esc_html_e( 'خلاصه و پرداخت', 'fitnesspro' ); ?></h2>
						<p class="fco-step-subtitle"><?php esc_html_e( 'شاخص توده بدنی و خلاصه برنامه شما.', 'fitnesspro' ); ?></p>

						<div class="fco-notice" id="fco-pay-notice" role="alert"></div>

						<div class="fco-bmi-panel">
							<svg class="fco-bmi-svg" viewBox="0 0 220 145" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
								<path d="M 20 120 A 90 90 0 0 1 200 120"
									stroke="rgba(255,255,255,0.08)" stroke-width="14" fill="none" stroke-linecap="round"/>
								<path class="fco-bmi-arc-fill"
									d="M 20 120 A 90 90 0 0 1 200 120"
									stroke="#00FF39" stroke-width="14" fill="none" stroke-linecap="round"
									stroke-dasharray="0 282.74"/>
								<text class="fco-bmi-number" x="110" y="100"
									text-anchor="middle" fill="#00FF39"
									font-size="34" font-weight="800" font-family="Vazirmatn,Tahoma,sans-serif">--</text>
								<text class="fco-bmi-category-text" x="110" y="122"
									text-anchor="middle" fill="#8A8A8A"
									font-size="11" font-family="Vazirmatn,Tahoma,sans-serif">
									<?php esc_html_e( 'شاخص توده بدنی', 'fitnesspro' ); ?>
								</text>
							</svg>
							<div class="fco-bmi-stats">
								<div class="fco-bmi-stat">
									<span class="fco-bmi-stat__key"><?php esc_html_e( 'وضعیت', 'fitnesspro' ); ?></span>
									<span class="fco-bmi-stat__val" id="fco-bmi-status">--</span>
								</div>
								<div class="fco-bmi-stat">
									<span class="fco-bmi-stat__key"><?php esc_html_e( 'وزن', 'fitnesspro' ); ?></span>
									<span class="fco-bmi-stat__val" id="fco-bmi-weight">--</span>
								</div>
								<div class="fco-bmi-stat">
									<span class="fco-bmi-stat__key"><?php esc_html_e( 'قد', 'fitnesspro' ); ?></span>
									<span class="fco-bmi-stat__val" id="fco-bmi-height">--</span>
								</div>
							</div>
						</div>

						<div class="fco-card" id="fco-summary-card">
							<div class="fco-summary-row">
								<span class="fco-summary-row__key"><?php esc_html_e( 'نوع برنامه', 'fitnesspro' ); ?></span>
								<span class="fco-summary-row__val fco-summary-row__val--accent" id="fco-sum-plan">--</span>
							</div>
							<div class="fco-summary-row">
								<span class="fco-summary-row__key"><?php esc_html_e( 'هدف', 'fitnesspro' ); ?></span>
								<span class="fco-summary-row__val" id="fco-sum-goal">--</span>
							</div>
							<div class="fco-summary-row">
								<span class="fco-summary-row__key"><?php esc_html_e( 'سطح فعالیت', 'fitnesspro' ); ?></span>
								<span class="fco-summary-row__val" id="fco-sum-activity">--</span>
							</div>
							<div class="fco-summary-row">
								<span class="fco-summary-row__key"><?php esc_html_e( 'وزن هدف', 'fitnesspro' ); ?></span>
								<span class="fco-summary-row__val" id="fco-sum-target">--</span>
							</div>
						</div>

						<button type="button" class="fco-btn fco-btn--primary fco-btn--full fco-btn-pay" id="fco-btn-pay">
							<?php esc_html_e( '💳 پرداخت و دریافت برنامه', 'fitnesspro' ); ?>
						</button>

						<div class="fco-nav" style="margin-top:12px;">
							<button type="button" class="fco-btn fco-btn--secondary fco-btn-back"><?php esc_html_e( 'برگشت', 'fitnesspro' ); ?></button>
						</div>
					</div>

				</div><!-- #fco-steps-wrapper -->
			</div><!-- .fco-inner -->
		</div><!-- #fitness-app-container -->
		<?php
		return ob_get_clean();
	}
}
