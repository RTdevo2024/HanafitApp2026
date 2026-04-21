<?php
defined( 'ABSPATH' ) || exit;

/**
 * User Dashboard — shortcode [fitnesspro_dashboard]
 *
 * Displays the logged-in user's active workout / meal plan for today,
 * a circular SVG progress ring, neon checkboxes persisted via AJAX to
 * wp_fitness_daily_progress, and a prominent renewal CTA when the plan
 * expires within 3 days.
 */
class FitnessPro_User_Dashboard {

	const SHORTCODE    = 'fitnesspro_dashboard';
	const NONCE_KEY    = 'fp_dash_nonce';
	const RENEWAL_DAYS = 3;

	// PHP date('w'): 0 = Sunday … 6 = Saturday
	private const DAY_MAP = array(
		0 => 'sunday', 1 => 'monday', 2 => 'tuesday',
		3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday',
	);

	private const DAY_LABELS = array(
		'saturday'  => 'شنبه',
		'sunday'    => 'یکشنبه',
		'monday'    => 'دوشنبه',
		'tuesday'   => 'سه‌شنبه',
		'wednesday' => 'چهارشنبه',
		'thursday'  => 'پنج‌شنبه',
		'friday'    => 'جمعه',
	);

	private const MEAL_KEYS = array( 'breakfast', 'snack1', 'lunch', 'snack2', 'dinner' );

	private const MEAL_LABELS = array(
		'breakfast' => 'صبحانه',
		'snack1'    => 'میان‌وعده ۱',
		'lunch'     => 'ناهار',
		'snack2'    => 'میان‌وعده ۲',
		'dinner'    => 'شام',
	);

	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	// ─── Hook Registration ────────────────────────────────────────────────────

	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	public function maybe_enqueue_assets(): void {
		global $post;
		if ( ! is_singular() || ! is_a( $post, 'WP_Post' ) ) {
			return;
		}
		if ( ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
		}
		$this->do_enqueue();
	}

	private function do_enqueue(): void {
		wp_enqueue_style(
			'fitnesspro-vazirmatn',
			'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'fitnesspro-user-dashboard',
			FITNESSPRO_PLUGIN_URL . 'assets/css/user-dashboard.css',
			array( 'fitnesspro-vazirmatn' ),
			$this->version
		);
		wp_enqueue_script(
			'fitnesspro-user-dashboard',
			FITNESSPRO_PLUGIN_URL . 'assets/js/user-dashboard.js',
			array(),
			$this->version,
			true
		);
	}

	// ─── Shortcode Entry Point ────────────────────────────────────────────────

	public function render( $atts ): string {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->render_login_prompt();
		}

		$plans = $this->get_active_plans( $user_id );
		if ( ! empty( $plans ) ) {
			return $this->render_dashboard( $user_id, $plans );
		}

		if ( $this->has_any_plan( $user_id ) ) {
			return $this->render_preparing();
		}

		return $this->render_no_plan();
	}

	// ─── Main Dashboard Render ────────────────────────────────────────────────

	private function render_dashboard( int $user_id, array $plans ): string {
		$user       = wp_get_current_user();
		$today_key  = $this->get_today_key();
		$today_date = wp_date( 'Y-m-d' );
		$all_prog   = $this->get_today_all_progress( $user_id, $today_date );
		$has_tabs   = count( $plans ) > 1;

		// Circumference for r=50 circle: 2π×50 = 314.16
		$circ = round( 2 * M_PI * 50, 2 );

		// Inject AJAX config before the script file runs
		wp_add_inline_script(
			'fitnesspro-user-dashboard',
			'window.fp_dashboard_config = ' . wp_json_encode( array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_KEY ),
			) ) . ';',
			'before'
		);

		ob_start();
		?>
		<div id="fp-dashboard" class="fp-dashboard" dir="rtl">
			<div class="fp-dash-inner">

				<?php /* ── Header ── */ ?>
				<header class="fp-dash-header">
					<div class="fp-dash-greeting-wrap">
						<p class="fp-dash-hi">
							<?php
							/* translators: %s: user display name */
							printf( esc_html__( 'سلام، %s 👋', 'fitnesspro' ), esc_html( $user->display_name ) );
							?>
						</p>
						<p class="fp-dash-weekday">
							<?php echo esc_html( self::DAY_LABELS[ $today_key ] ?? '' ); ?>
						</p>
					</div>
					<div class="fp-dash-logo" aria-hidden="true">
						<?php echo $has_tabs ? '💪' : ( ( $plans[0]['type'] ?? '' ) === 'meal' ? '🥗' : '💪' ); ?>
					</div>
				</header>

				<?php if ( $has_tabs ) : ?>
				<?php /* ── Plan type tabs ── */ ?>
				<div class="fp-plan-tabs" role="tablist">
					<?php foreach ( $plans as $i => $plan ) : ?>
					<button class="fp-plan-tab<?php echo ( 0 === $i ) ? ' is-active' : ''; ?>"
							role="tab"
							data-tab="fp-plan-<?php echo esc_attr( $plan['id'] ); ?>">
						<?php echo 'workout' === $plan['type']
							? esc_html__( '🏋️ تمرین', 'fitnesspro' )
							: esc_html__( '🥗 تغذیه', 'fitnesspro' ); ?>
					</button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<?php foreach ( $plans as $i => $plan ) :
					$plan_id   = (int) $plan['id'];
					$content   = $this->get_plan_content( $plan_id );
					$items     = $content ? $this->extract_today_items( $content, $plan['type'], $today_key ) : array();
					$total     = count( $items );
					$prog      = $all_prog[ $plan_id ] ?? array();
					$done_n    = count( array_filter( $prog ) );
					$pct       = ( $total > 0 ) ? (int) round( $done_n / $total * 100 ) : 0;
					$offset    = round( $circ * ( 1 - $pct / 100 ), 2 );
					$days_left = $this->days_until_expiry( (string) ( $plan['expiry_date'] ?? '' ) );
					$renew     = ( $days_left <= self::RENEWAL_DAYS );
				?>
				<div class="fp-plan-pane<?php echo ( 0 === $i || ! $has_tabs ) ? ' is-active' : ''; ?>"
					 id="fp-plan-<?php echo $plan_id; ?>"
					 role="tabpanel"
					 data-plan-id="<?php echo $plan_id; ?>">

					<?php /* ── Progress ring + expiry strip ── */ ?>
					<div class="fp-progress-area">
						<div class="fp-ring-wrap">
							<svg class="fp-progress-svg"
								 viewBox="0 0 120 120"
								 role="progressbar"
								 aria-valuenow="<?php echo $pct; ?>"
								 aria-valuemin="0"
								 aria-valuemax="100"
								 aria-label="<?php esc_attr_e( 'پیشرفت امروز', 'fitnesspro' ); ?>">
								<circle class="fp-ring-bg" cx="60" cy="60" r="50"/>
								<circle class="fp-ring-fg"
										cx="60" cy="60" r="50"
										transform="rotate(-90 60 60)"
										stroke-dasharray="<?php echo $circ; ?>"
										stroke-dashoffset="<?php echo $circ; ?>"
										data-target="<?php echo $offset; ?>"/>
							</svg>
							<div class="fp-ring-center" aria-hidden="true">
								<span class="fp-progress-pct" id="fp-pct-<?php echo $plan_id; ?>"><?php echo $pct; ?></span>
								<span class="fp-progress-unit">%</span>
								<span class="fp-progress-sub"><?php esc_html_e( 'امروز', 'fitnesspro' ); ?></span>
							</div>
						</div><!-- .fp-ring-wrap -->

						<div class="fp-progress-aside">
							<?php if ( $renew ) : ?>
							<div class="fp-renewal-banner">
								<p class="fp-renewal-text">
									<?php if ( $days_left <= 0 ) : ?>
										<strong><?php esc_html_e( 'برنامه شما به پایان رسید!', 'fitnesspro' ); ?></strong>
									<?php else : ?>
										<strong>
											<?php
											printf(
												/* translators: %d: days remaining */
												esc_html__( 'فقط %d روز مانده!', 'fitnesspro' ),
												$days_left
											);
											?>
										</strong>
									<?php endif; ?>
								</p>
								<a href="<?php echo esc_url( $this->get_checkout_url() ); ?>"
								   class="fp-renewal-btn">
									<?php esc_html_e( 'تمدید ماه جدید', 'fitnesspro' ); ?>
								</a>
							</div>
							<?php else : ?>
							<div class="fp-expiry-pill">
								<span aria-hidden="true">📅</span>
								<?php
								printf(
									/* translators: %d: days remaining */
									esc_html__( '%d روز مانده', 'fitnesspro' ),
									max( 0, $days_left )
								);
								?>
							</div>
							<?php endif; ?>

							<div class="fp-all-done" id="fp-all-done-<?php echo $plan_id; ?>" hidden>
								<span aria-hidden="true">🎉</span>
								<p><?php esc_html_e( 'آفرین! برنامه امروز تمام شد.', 'fitnesspro' ); ?></p>
							</div>
						</div><!-- .fp-progress-aside -->
					</div><!-- .fp-progress-area -->

					<?php /* ── Today's plan title ── */ ?>
					<h2 class="fp-day-title">
						<span aria-hidden="true"><?php echo 'workout' === $plan['type'] ? '🏋️' : '🥗'; ?></span>
						<?php
						printf(
							/* translators: %s: day name in Persian */
							esc_html__( 'برنامه %s', 'fitnesspro' ),
							esc_html( self::DAY_LABELS[ $today_key ] ?? '' )
						);
						?>
					</h2>

					<?php /* ── Task list or rest day ── */ ?>
					<?php if ( empty( $items ) ) : ?>
					<div class="fp-rest-day">
						<span class="fp-rest-icon" aria-hidden="true">😴</span>
						<p><?php esc_html_e( 'امروز روز استراحت است.', 'fitnesspro' ); ?></p>
					</div>
					<?php else : ?>
					<ul class="fp-task-list" id="fp-tasks-<?php echo $plan_id; ?>">
						<?php foreach ( $items as $key => $item ) :
							$is_done = ! empty( $prog[ $key ] );
						?>
						<li class="fp-task-item<?php echo $is_done ? ' is-done' : ''; ?>">
							<label class="fp-task-label">
								<input type="checkbox"
									   class="fp-dash-check"
									   name="<?php echo esc_attr( $key ); ?>"
									   data-plan-id="<?php echo $plan_id; ?>"
									   <?php echo $is_done ? 'checked' : ''; ?>>
								<span class="fp-checkmark" aria-hidden="true"></span>
								<div class="fp-task-body">
									<?php if ( 'workout' === $plan['type'] ) : ?>
										<span class="fp-task-name"><?php echo esc_html( $item['name'] ?? '' ); ?></span>
										<?php if ( ! empty( $item['sets'] ) || ! empty( $item['reps'] ) ) : ?>
										<div class="fp-task-chips">
											<?php if ( ! empty( $item['sets'] ) ) : ?>
											<span class="fp-chip"><?php echo absint( $item['sets'] ); ?> ست</span>
											<?php endif; ?>
											<?php if ( ! empty( $item['reps'] ) ) : ?>
											<span class="fp-chip"><?php echo absint( $item['reps'] ); ?> تکرار</span>
											<?php endif; ?>
										</div>
										<?php endif; ?>
										<?php if ( ! empty( $item['note'] ) ) : ?>
										<p class="fp-task-note"><?php echo esc_html( $item['note'] ); ?></p>
										<?php endif; ?>
									<?php else : /* meal */ ?>
										<span class="fp-task-name"><?php echo esc_html( self::MEAL_LABELS[ $key ] ?? $key ); ?></span>
										<?php if ( ! empty( $item['items'] ) ) : ?>
										<p class="fp-task-items-text"><?php echo esc_html( $item['items'] ); ?></p>
										<?php endif; ?>
										<?php
										$macros = array_filter( array(
											'calories' => (int) ( $item['calories'] ?? 0 ),
											'protein'  => (int) ( $item['protein']  ?? 0 ),
											'carbs'    => (int) ( $item['carbs']    ?? 0 ),
											'fat'      => (int) ( $item['fat']      ?? 0 ),
										) );
										?>
										<?php if ( ! empty( $macros ) ) : ?>
										<div class="fp-task-chips">
											<?php if ( ! empty( $macros['calories'] ) ) : ?>
											<span class="fp-chip fp-chip--kcal"><?php echo $macros['calories']; ?> کالری</span>
											<?php endif; ?>
											<?php if ( ! empty( $macros['protein'] ) ) : ?>
											<span class="fp-chip">P: <?php echo $macros['protein']; ?>g</span>
											<?php endif; ?>
											<?php if ( ! empty( $macros['carbs'] ) ) : ?>
											<span class="fp-chip">C: <?php echo $macros['carbs']; ?>g</span>
											<?php endif; ?>
											<?php if ( ! empty( $macros['fat'] ) ) : ?>
											<span class="fp-chip">F: <?php echo $macros['fat']; ?>g</span>
											<?php endif; ?>
										</div>
										<?php endif; ?>
										<?php if ( ! empty( $item['note'] ) ) : ?>
										<p class="fp-task-note"><?php echo esc_html( $item['note'] ); ?></p>
										<?php endif; ?>
									<?php endif; ?>
								</div><!-- .fp-task-body -->
							</label>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>

				</div><!-- .fp-plan-pane -->
				<?php endforeach; ?>

			</div><!-- .fp-dash-inner -->
		</div><!-- #fp-dashboard -->
		<?php
		return ob_get_clean();
	}

	// ─── State Screens ────────────────────────────────────────────────────────

	private function render_preparing(): string {
		ob_start();
		?>
		<div class="fp-dashboard fp-dashboard--state" dir="rtl">
			<div class="fp-state-icon" aria-hidden="true">⏳</div>
			<h2><?php esc_html_e( 'برنامه در حال آماده‌سازی است', 'fitnesspro' ); ?></h2>
			<p><?php esc_html_e( 'مربی شما در حال تهیه برنامه اختصاصی‌تان است. به‌زودی آماده می‌شود.', 'fitnesspro' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_no_plan(): string {
		ob_start();
		?>
		<div class="fp-dashboard fp-dashboard--state" dir="rtl">
			<div class="fp-state-icon" aria-hidden="true">🚀</div>
			<h2><?php esc_html_e( 'هنوز برنامه‌ای ندارید', 'fitnesspro' ); ?></h2>
			<p><?php esc_html_e( 'برای شروع سفر تناسب‌اندام خود، یک برنامه خریداری کنید.', 'fitnesspro' ); ?></p>
			<a href="<?php echo esc_url( $this->get_checkout_url() ); ?>" class="fp-start-btn">
				<?php esc_html_e( 'خرید برنامه', 'fitnesspro' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_login_prompt(): string {
		ob_start();
		?>
		<div class="fp-dashboard fp-dashboard--state" dir="rtl">
			<div class="fp-state-icon" aria-hidden="true">🔒</div>
			<h2><?php esc_html_e( 'ابتدا وارد حساب کاربری خود شوید', 'fitnesspro' ); ?></h2>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="fp-start-btn">
				<?php esc_html_e( 'ورود به حساب', 'fitnesspro' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	// ─── AJAX: Save Daily Progress ────────────────────────────────────────────

	public function ajax_save_progress(): void {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in' ), 401 );
		}

		$user_id = get_current_user_id();
		$plan_id = absint( $_POST['plan_id'] ?? 0 );
		$key     = sanitize_key( $_POST['key']  ?? '' );
		$checked = ( '1' === ( $_POST['checked'] ?? '' ) );
		$date    = wp_date( 'Y-m-d' );

		if ( ! $plan_id || ! $key ) {
			wp_send_json_error( array( 'message' => 'Invalid params' ) );
		}

		global $wpdb;
		$tbl_plans = $wpdb->prefix . 'fitness_user_plans';
		$tbl_prog  = $wpdb->prefix . 'fitness_daily_progress';

		// Ownership check — prevent users from toggling another user's progress
		$owner = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT user_id FROM {$tbl_plans} WHERE id = %d AND status = 'active' LIMIT 1",
			$plan_id
		) );
		if ( $owner !== $user_id ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}

		// Read existing day record
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, completed_json FROM {$tbl_prog} WHERE user_id = %d AND date = %s LIMIT 1",
			$user_id,
			$date
		), ARRAY_A );

		$data = array();
		if ( $row ) {
			$decoded = json_decode( $row['completed_json'], true );
			$data    = is_array( $decoded ) ? $decoded : array();
		}

		// Nested: $data[$plan_id][$key] = bool
		if ( ! isset( $data[ $plan_id ] ) || ! is_array( $data[ $plan_id ] ) ) {
			$data[ $plan_id ] = array();
		}
		$data[ $plan_id ][ $key ] = $checked;

		$json = wp_json_encode( $data );

		if ( $row ) {
			$wpdb->update(
				$tbl_prog,
				array( 'completed_json' => $json ),
				array( 'id' => (int) $row['id'] ),
				array( '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$tbl_prog,
				array(
					'user_id'        => $user_id,
					'date'           => $date,
					'completed_json' => $json,
				),
				array( '%d', '%s', '%s' )
			);
		}

		wp_send_json_success( array( 'saved' => true ) );
	}

	// ─── Private Helpers ─────────────────────────────────────────────────────

	private function get_active_plans( int $user_id ): array {
		global $wpdb;
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*
				 FROM   {$wpdb->prefix}fitness_user_plans p
				 INNER JOIN {$wpdb->prefix}fitness_active_content c ON c.user_plan_id = p.id
				 WHERE  p.user_id = %d AND p.status = 'active'
				 ORDER BY p.type ASC, p.created_at DESC",
				$user_id
			),
			ARRAY_A
		);
	}

	private function has_any_plan( int $user_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}fitness_user_plans
			 WHERE user_id = %d AND status IN ('active','pending') LIMIT 1",
			$user_id
		) );
	}

	private function get_plan_content( int $plan_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT content_json FROM {$wpdb->prefix}fitness_active_content
			 WHERE user_plan_id = %d LIMIT 1",
			$plan_id
		), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		$data = json_decode( $row['content_json'], true );
		return is_array( $data ) ? $data : null;
	}

	private function get_today_key(): string {
		return self::DAY_MAP[ (int) wp_date( 'w' ) ] ?? 'saturday';
	}

	private function extract_today_items( array $content, string $type, string $day ): array {
		if ( 'workout' === $type ) {
			$exercises = $content[ $day ]['exercises'] ?? array();
			$result    = array();
			foreach ( $exercises as $i => $ex ) {
				$result[ 'exercise_' . $i ] = $ex;
			}
			return $result;
		}
		// Meal — same every day; show only populated slots
		$result = array();
		foreach ( self::MEAL_KEYS as $meal ) {
			if ( ! empty( $content[ $meal ] ) ) {
				$result[ $meal ] = $content[ $meal ];
			}
		}
		return $result;
	}

	private function get_today_all_progress( int $user_id, string $date ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT completed_json FROM {$wpdb->prefix}fitness_daily_progress
			 WHERE user_id = %d AND date = %s LIMIT 1",
			$user_id,
			$date
		), ARRAY_A );
		if ( ! $row ) {
			return array();
		}
		$data = json_decode( $row['completed_json'], true );
		return is_array( $data ) ? $data : array();
	}

	private function days_until_expiry( string $expiry_date ): int {
		if ( ! $expiry_date ) {
			return PHP_INT_MAX;
		}
		// Use end-of-expiry-day so the plan is valid all day on the expiry date
		$exp = strtotime( $expiry_date . ' 23:59:59' );
		if ( ! $exp ) {
			return PHP_INT_MAX;
		}
		return (int) ceil( ( $exp - time() ) / DAY_IN_SECONDS );
	}

	private function get_checkout_url(): string {
		global $wpdb;
		$page_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_content LIKE %s
			   AND post_status  = 'publish'
			   AND post_type    = 'page'
			 LIMIT 1",
			'%' . $wpdb->esc_like( FitnessPro_Checkout_UI::SHORTCODE ) . '%'
		) );
		return $page_id ? (string) get_permalink( $page_id ) : home_url( '/' );
	}
}
