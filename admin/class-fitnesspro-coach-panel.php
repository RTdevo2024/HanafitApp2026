<?php
defined( 'ABSPATH' ) || exit;

/**
 * Coach Fulfillment Panel
 *
 * Coaches see their assigned pending/active plans, browse the client's full
 * health profile, pick a template, personalise it inline, and publish the
 * result into wp_fitness_active_content.  A system notification (user meta +
 * email) is sent to the client on publish.
 *
 * Admin-only AJAX endpoint fp_assign_coach lets admins wire a coach to a plan
 * directly from the orders table.
 */
class FitnessPro_Coach_Panel {

	const NONCE_KEY = 'fp_coach_nonce';

	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	// ─── Asset Enqueue ────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'fitnesspro-coach-dashboard' ) ) {
			return;
		}

		wp_enqueue_style(
			'fitnesspro-coach-panel',
			FITNESSPRO_PLUGIN_URL . 'assets/css/coach-panel.css',
			array( 'fitnesspro-admin-rtl' ),
			$this->version
		);

		wp_enqueue_script(
			'fitnesspro-coach-panel',
			FITNESSPRO_PLUGIN_URL . 'assets/js/coach-panel.js',
			array(),
			$this->version,
			true
		);

		wp_localize_script(
			'fitnesspro-coach-panel',
			'fp_coach',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_KEY ),
				'strings'  => array(
					'loading'      => __( 'در حال بارگذاری...', 'fitnesspro' ),
					'error'        => __( 'خطایی رخ داد.', 'fitnesspro' ),
					'publish_ok'   => __( 'برنامه با موفقیت منتشر شد و کاربر مطلع شد.', 'fitnesspro' ),
					'select_tpl'   => __( 'ابتدا یک تمپلت انتخاب کنید.', 'fitnesspro' ),
					'confirm_pub'  => __( 'برنامه برای کاربر منتشر شود؟', 'fitnesspro' ),
					'no_templates' => __( 'هیچ تمپلتی یافت نشد. ابتدا از بخش مدیریت یک تمپلت بسازید.', 'fitnesspro' ),
					'add_exercise' => __( 'افزودن تمرین', 'fitnesspro' ),
					'remove'       => __( 'حذف', 'fitnesspro' ),
					'days'         => array(
						'saturday'  => __( 'شنبه', 'fitnesspro' ),
						'sunday'    => __( 'یکشنبه', 'fitnesspro' ),
						'monday'    => __( 'دوشنبه', 'fitnesspro' ),
						'tuesday'   => __( 'سه‌شنبه', 'fitnesspro' ),
						'wednesday' => __( 'چهارشنبه', 'fitnesspro' ),
						'thursday'  => __( 'پنج‌شنبه', 'fitnesspro' ),
						'friday'    => __( 'جمعه', 'fitnesspro' ),
					),
					'meals'        => array(
						'breakfast' => __( 'صبحانه', 'fitnesspro' ),
						'snack1'    => __( 'میان‌وعده ۱', 'fitnesspro' ),
						'lunch'     => __( 'ناهار', 'fitnesspro' ),
						'snack2'    => __( 'میان‌وعده ۲', 'fitnesspro' ),
						'dinner'    => __( 'شام', 'fitnesspro' ),
					),
					'fields'       => array(
						'exercise' => __( 'نام تمرین', 'fitnesspro' ),
						'sets'     => __( 'ست', 'fitnesspro' ),
						'reps'     => __( 'تکرار', 'fitnesspro' ),
						'note'     => __( 'یادداشت', 'fitnesspro' ),
						'items'    => __( 'مواد غذایی', 'fitnesspro' ),
						'calories' => __( 'کالری', 'fitnesspro' ),
						'protein'  => __( 'پروتئین (g)', 'fitnesspro' ),
						'carbs'    => __( 'کربوهیدرات (g)', 'fitnesspro' ),
						'fat'      => __( 'چربی (g)', 'fitnesspro' ),
					),
				),
			)
		);
	}

	// ─── Page Renderer ────────────────────────────────────────────────────────

	public function render(): void {
		$coach_id = get_current_user_id();
		$plans    = $this->get_coach_plans( $coach_id );
		?>
		<div class="wrap fitnesspro-wrap fp-coach-wrap" dir="rtl">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'داشبورد مربی', 'fitnesspro' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( empty( $plans ) ) : ?>
			<div class="fp-coach-empty">
				<span class="dashicons dashicons-clipboard" style="font-size:48px;width:48px;height:48px;color:#b0b0b0;"></span>
				<p><?php esc_html_e( 'هیچ برنامه‌ای هنوز به شما تخصیص داده نشده است.', 'fitnesspro' ); ?></p>
			</div>

			<?php else : ?>
			<div class="fp-coach-layout">

				<?php /* ── Clients list ── */ ?>
				<div class="fp-coach-list" id="fp-coach-list">
					<div class="fp-coach-list__head">
						<span><?php esc_html_e( 'کاربر', 'fitnesspro' ); ?></span>
						<span><?php esc_html_e( 'نوع', 'fitnesspro' ); ?></span>
						<span><?php esc_html_e( 'وضعیت', 'fitnesspro' ); ?></span>
						<span><?php esc_html_e( 'عملیات', 'fitnesspro' ); ?></span>
					</div>

					<?php foreach ( $plans as $plan ) :
						$profile    = $this->get_user_profile( (int) $plan['user_id'] );
						$bmi_text   = $this->bmi_label( $profile );
						$status_map = array(
							'pending' => __( 'در انتظار', 'fitnesspro' ),
							'active'  => __( 'فعال', 'fitnesspro' ),
							'expired' => __( 'منقضی', 'fitnesspro' ),
						);
						?>
					<div class="fp-coach-row" id="fp-row-<?php echo (int) $plan['id']; ?>">

						<div class="fp-coach-row__cell fp-coach-row__user">
							<strong><?php echo esc_html( $plan['display_name'] ); ?></strong>
							<span class="fp-row-email"><?php echo esc_html( $plan['user_email'] ); ?></span>
							<?php if ( $bmi_text ) : ?>
							<span class="fp-bmi-chip"><?php echo esc_html( $bmi_text ); ?></span>
							<?php endif; ?>
						</div>

						<div class="fp-coach-row__cell">
							<span class="fitnesspro-badge fitnesspro-badge--<?php echo 'workout' === $plan['type'] ? 'active' : 'pending'; ?>">
								<?php echo 'workout' === $plan['type']
									? esc_html__( 'تمرین', 'fitnesspro' )
									: esc_html__( 'تغذیه', 'fitnesspro' ); ?>
							</span>
						</div>

						<div class="fp-coach-row__cell">
							<span class="fitnesspro-badge fitnesspro-badge--<?php echo esc_attr( sanitize_html_class( $plan['status'] ) ); ?>">
								<?php echo esc_html( $status_map[ $plan['status'] ] ?? $plan['status'] ); ?>
							</span>
						</div>

						<div class="fp-coach-row__cell fp-coach-row__actions">
							<button type="button"
									class="button fp-btn-profile"
									data-plan-id="<?php echo esc_attr( $plan['id'] ); ?>"
									data-profile="<?php echo esc_attr( wp_json_encode( $profile ) ); ?>"
									data-user="<?php echo esc_attr( $plan['display_name'] ); ?>">
								<?php esc_html_e( 'پروفایل', 'fitnesspro' ); ?>
							</button>
							<button type="button"
									class="button button-primary fp-btn-assign"
									data-plan-id="<?php echo esc_attr( $plan['id'] ); ?>"
									data-plan-type="<?php echo esc_attr( $plan['type'] ); ?>"
									data-user="<?php echo esc_attr( $plan['display_name'] ); ?>"
									<?php echo 'active' === $plan['status'] ? 'disabled' : ''; ?>>
								<?php echo 'active' === $plan['status']
									? esc_html__( 'منتشر شده', 'fitnesspro' )
									: esc_html__( 'تخصیص برنامه', 'fitnesspro' ); ?>
							</button>
						</div>
					</div>
					<?php endforeach; ?>
				</div><!-- .fp-coach-list -->

				<?php /* ── Side profile panel ── */ ?>
				<div class="fp-coach-detail" id="fp-coach-detail" hidden>
					<div class="fp-coach-detail__inner" id="fp-coach-detail-inner"></div>
				</div>

			</div><!-- .fp-coach-layout -->

			<?php /* ── Template + plan editor modal ── */ ?>
			<div class="fp-modal-wrap" id="fp-coach-modal" role="dialog" aria-modal="true" hidden>
				<div class="fp-modal-overlay" id="fp-modal-overlay"></div>
				<div class="fp-modal-box">

					<div class="fp-modal-head">
						<h2 id="fp-modal-title"><?php esc_html_e( 'تخصیص برنامه', 'fitnesspro' ); ?></h2>
						<button type="button" class="fp-modal-close" id="fp-modal-close"
								aria-label="<?php esc_attr_e( 'بستن', 'fitnesspro' ); ?>">&#x2715;</button>
					</div>

					<div class="fp-modal-body">

						<?php /* Step 1 – template selector */ ?>
						<div id="fp-step-select" class="fp-modal-step">
							<p class="fp-modal-hint">
								<?php esc_html_e( 'یک تمپلت پایه انتخاب کنید و در مرحله بعد آن را برای این کاربر ویرایش کنید:', 'fitnesspro' ); ?>
							</p>
							<div id="fp-tpl-list" class="fp-tpl-list">
								<span class="fp-spinner"></span>
							</div>
							<div class="fp-modal-foot">
								<button type="button" class="button" id="fp-select-cancel">
									<?php esc_html_e( 'انصراف', 'fitnesspro' ); ?>
								</button>
								<button type="button" class="button button-primary" id="fp-select-next" disabled>
									<?php esc_html_e( 'ویرایش و ادامه ←', 'fitnesspro' ); ?>
								</button>
							</div>
						</div>

						<?php /* Step 2 – plan editor */ ?>
						<div id="fp-step-edit" class="fp-modal-step" hidden>
							<div id="fp-plan-editor" class="fp-plan-editor"></div>
							<div class="fp-modal-foot">
								<button type="button" class="button" id="fp-edit-back">
									<?php esc_html_e( '→ انتخاب تمپلت دیگر', 'fitnesspro' ); ?>
								</button>
								<button type="button" class="button button-primary" id="fp-btn-publish">
									<span class="fp-spinner" hidden></span>
									<?php esc_html_e( 'انتشار برنامه', 'fitnesspro' ); ?>
								</button>
							</div>
						</div>

					</div><!-- .fp-modal-body -->
				</div><!-- .fp-modal-box -->
			</div><!-- .fp-modal-wrap -->

			<?php endif; // empty($plans) ?>
		</div><!-- .fp-coach-wrap -->
		<?php
	}

	// ─── AJAX: Load Templates ─────────────────────────────────────────────────

	public function ajax_get_templates(): void {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! $this->is_authorized() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'fitnesspro' ) ), 403 );
		}

		$plan_type = sanitize_key( $_POST['plan_type'] ?? '' );
		$post_type = 'meal' === $plan_type ? 'meal_template' : 'workout_template';

		$posts = get_posts( array(
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'title',
			'order'       => 'ASC',
		) );

		$result = array();
		foreach ( $posts as $p ) {
			$raw  = get_post_meta( $p->ID, '_tpl_data', true );
			$data = $raw ? json_decode( $raw, true ) : array();
			$result[] = array(
				'id'    => $p->ID,
				'title' => $p->post_title,
				'data'  => is_array( $data ) ? $data : array(),
			);
		}

		wp_send_json_success( $result );
	}

	// ─── AJAX: Publish Plan ───────────────────────────────────────────────────

	public function ajax_assign_plan(): void {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! $this->is_authorized() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'fitnesspro' ) ), 403 );
		}

		$plan_id  = absint( $_POST['plan_id']  ?? 0 );
		$raw_data = isset( $_POST['plan_data'] ) ? wp_unslash( $_POST['plan_data'] ) : '';

		if ( ! $plan_id || ! $raw_data ) {
			wp_send_json_error( array( 'message' => __( 'داده‌های ناقص ارسال شده.', 'fitnesspro' ) ) );
		}

		global $wpdb;
		$tbl_plans   = $wpdb->prefix . 'fitness_user_plans';
		$tbl_content = $wpdb->prefix . 'fitness_active_content';

		// Admins can publish any plan; coaches only their assigned plans
		if ( current_user_can( 'manage_options' ) ) {
			$plan = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$tbl_plans} WHERE id = %d LIMIT 1",
				$plan_id
			) );
		} else {
			$plan = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$tbl_plans} WHERE id = %d AND coach_id = %d LIMIT 1",
				$plan_id,
				get_current_user_id()
			) );
		}

		if ( ! $plan ) {
			wp_send_json_error( array( 'message' => __( 'برنامه یافت نشد یا دسترسی ندارید.', 'fitnesspro' ) ) );
		}

		$decoded = json_decode( $raw_data, true );
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'message' => __( 'فرمت داده نامعتبر است.', 'fitnesspro' ) ) );
		}

		$content_json = wp_json_encode( $this->sanitize_plan_data( $decoded, $plan->type ) );

		// Upsert into wp_fitness_active_content
		$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$tbl_content} WHERE user_plan_id = %d LIMIT 1",
			$plan_id
		) );

		if ( $existing_id ) {
			$wpdb->update(
				$tbl_content,
				array(
					'content_json' => $content_json,
					'updated_at'   => current_time( 'mysql', true ),
				),
				array( 'id' => $existing_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$tbl_content,
				array(
					'user_plan_id' => $plan_id,
					'content_json' => $content_json,
					'updated_at'   => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%s' )
			);
		}

		// Activate plan status
		$wpdb->update(
			$tbl_plans,
			array( 'status' => 'active' ),
			array( 'id'     => $plan_id ),
			array( '%s' ),
			array( '%d' )
		);

		// System + email notification
		$this->push_notification( (int) $plan->user_id, $plan->type );

		wp_send_json_success( array(
			'message' => __( 'برنامه با موفقیت منتشر شد و کاربر مطلع شد.', 'fitnesspro' ),
			'plan_id' => $plan_id,
		) );
	}

	// ─── AJAX: Assign Coach to Plan (admin only) ──────────────────────────────

	public function ajax_assign_coach(): void {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'fitnesspro' ) ), 403 );
		}

		$plan_id  = absint( $_POST['plan_id']  ?? 0 );
		$coach_id = absint( $_POST['coach_id'] ?? 0 );

		if ( ! $plan_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه برنامه نامعتبر است.', 'fitnesspro' ) ) );
		}

		// 0 means "unassign"; non-zero must be a real coach
		if ( $coach_id ) {
			$coach = get_userdata( $coach_id );
			if ( ! $coach || ! in_array( FitnessPro_Roles::COACH_ROLE, (array) $coach->roles, true ) ) {
				wp_send_json_error( array( 'message' => __( 'مربی انتخاب‌شده معتبر نیست.', 'fitnesspro' ) ) );
			}
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'fitness_user_plans',
			array( 'coach_id' => $coach_id ),
			array( 'id'       => $plan_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'خطا در ذخیره‌سازی.', 'fitnesspro' ) ) );
		}

		$name = $coach_id
			? get_userdata( $coach_id )->display_name
			: __( 'تخصیص‌نیافته', 'fitnesspro' );

		wp_send_json_success( array(
			'message'    => __( 'مربی با موفقیت تخصیص داده شد.', 'fitnesspro' ),
			'coach_name' => $name,
			'coach_id'   => $coach_id,
		) );
	}

	// ─── Private Helpers ─────────────────────────────────────────────────────

	private function get_coach_plans( int $coach_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'fitness_user_plans';

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.id, p.user_id, p.type, p.status, p.expiry_date, p.created_at,
				        u.display_name, u.user_email
				 FROM   {$table} p
				 INNER JOIN {$wpdb->users} u ON u.ID = p.user_id
				 WHERE  p.coach_id = %d AND p.status IN ('pending','active')
				 ORDER BY p.status ASC, p.created_at DESC",
				$coach_id
			),
			ARRAY_A
		);
	}

	private function get_user_profile( int $user_id ): array {
		$raw = get_user_meta( $user_id, 'fp_health_profile', true );
		if ( ! $raw ) {
			return array();
		}
		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : array();
	}

	private function bmi_label( array $profile ): string {
		$w = (float) ( $profile['weight'] ?? 0 );
		$h = (float) ( $profile['height'] ?? 0 );
		if ( ! $w || ! $h ) {
			return '';
		}
		return 'BMI: ' . round( $w / ( ( $h / 100 ) ** 2 ), 1 );
	}

	private function push_notification( int $user_id, string $plan_type ): void {
		$type_label = 'meal' === $plan_type
			? __( 'تغذیه', 'fitnesspro' )
			: __( 'تمرین', 'fitnesspro' );

		// Append to fp_notifications user meta array
		$raw  = get_user_meta( $user_id, 'fp_notifications', true );
		$list = ( $raw ) ? json_decode( $raw, true ) : array();
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		$list[] = array(
			'type'       => 'plan_ready',
			'plan_type'  => $plan_type,
			'message'    => sprintf(
				/* translators: %s: plan type label */
				__( 'برنامه %s اختصاصی شما آماده است!', 'fitnesspro' ),
				$type_label
			),
			'created_at' => current_time( 'mysql', true ),
			'read'       => false,
		);
		update_user_meta( $user_id, 'fp_notifications', wp_json_encode( $list ) );

		// Email notification
		$user = get_userdata( $user_id );
		if ( $user ) {
			wp_mail(
				$user->user_email,
				/* translators: %s: plan type label */
				sprintf( __( 'برنامه %s شما آماده است — فیتنس‌پرو', 'fitnesspro' ), $type_label ),
				sprintf(
					/* translators: 1: display_name, 2: plan type */
					__( "سلام %1\$s،\n\nمربی شما برنامه %2\$s اختصاصی‌تان را آماده و منتشر کرده است.\nاکنون می‌توانید به داشبورد خود وارد شوید و برنامه را مشاهده کنید.\n\n— تیم فیتنس‌پرو", 'fitnesspro' ),
					$user->display_name,
					$type_label
				)
			);
		}
	}

	private function sanitize_plan_data( array $data, string $type ): array {
		$clean = array();

		if ( 'workout' === $type ) {
			$days = array( 'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' );
			foreach ( $days as $day ) {
				$exercises = array();
				foreach ( (array) ( $data[ $day ]['exercises'] ?? array() ) as $ex ) {
					if ( empty( trim( (string) ( $ex['name'] ?? '' ) ) ) ) {
						continue;
					}
					$exercises[] = array(
						'name' => sanitize_text_field( $ex['name'] ),
						'sets' => absint( $ex['sets'] ?? 0 ),
						'reps' => absint( $ex['reps'] ?? 0 ),
						'note' => sanitize_textarea_field( $ex['note'] ?? '' ),
					);
				}
				$clean[ $day ] = array( 'exercises' => $exercises );
			}
		} else {
			$meals = array( 'breakfast', 'snack1', 'lunch', 'snack2', 'dinner' );
			foreach ( $meals as $meal ) {
				$s = $data[ $meal ] ?? array();
				$clean[ $meal ] = array(
					'items'    => sanitize_textarea_field( $s['items']    ?? '' ),
					'calories' => absint( $s['calories'] ?? 0 ),
					'protein'  => absint( $s['protein']  ?? 0 ),
					'carbs'    => absint( $s['carbs']    ?? 0 ),
					'fat'      => absint( $s['fat']      ?? 0 ),
					'note'     => sanitize_textarea_field( $s['note']     ?? '' ),
				);
			}
		}

		return $clean;
	}

	private function is_authorized(): bool {
		$user = wp_get_current_user();
		return in_array( FitnessPro_Roles::COACH_ROLE, (array) $user->roles, true )
			|| current_user_can( 'manage_options' );
	}
}
