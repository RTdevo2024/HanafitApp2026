<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin-side functionality: menus, assets, and page renderers.
 *
 * Menu structure (all under "فیتنس‌پرو"):
 *   - داشبورد          (manage_options)
 *   - سفارشات          (manage_options)
 *   - تنظیمات          (manage_options)
 *   - داشبورد مربی     (edit_posts — visible to fitness_coach role)
 *   CPT sub-pages are auto-appended by WordPress.
 */
class FitnessPro_Admin {

	private $version;

	public function __construct( $version ) {
		$this->version = $version;
	}

	// ─── Asset Enqueuing ──────────────────────────────────────────────────────

	public function enqueue_styles( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'fitnesspro' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'fitnesspro-admin-rtl',
			FITNESSPRO_PLUGIN_URL . 'assets/css/admin-rtl.css',
			array(),
			$this->version
		);
	}

	public function enqueue_scripts( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'fitnesspro' ) === false ) {
			return;
		}
		wp_enqueue_script(
			'fitnesspro-admin',
			FITNESSPRO_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			$this->version,
			true
		);
		wp_localize_script(
			'fitnesspro-admin',
			'fitnesspro_admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'fitnesspro_admin_nonce' ),
				'strings'  => array(
					'confirm_delete' => __( 'آیا از حذف این مورد مطمئن هستید؟', 'fitnesspro' ),
					'loading'        => __( 'در حال بارگذاری...', 'fitnesspro' ),
					'error'          => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'fitnesspro' ),
					'success'        => __( 'عملیات با موفقیت انجام شد.', 'fitnesspro' ),
				),
			)
		);
	}

	/**
	 * Loads meta-box assets (wp.media + CSS/JS) only on CPT edit screens.
	 */
	public function enqueue_metabox_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'workout_template', 'meal_template' ), true ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'fitnesspro-meta-boxes',
			FITNESSPRO_PLUGIN_URL . 'assets/css/meta-boxes.css',
			array(),
			$this->version
		);

		wp_enqueue_script(
			'fitnesspro-meta-boxes',
			FITNESSPRO_PLUGIN_URL . 'assets/js/meta-boxes.js',
			array(),
			$this->version,
			true
		);

		wp_localize_script(
			'fitnesspro-meta-boxes',
			'fitnesspro_mb',
			array(
				'strings' => array(
					'select_media' => __( 'انتخاب تصویر یا ویدئو', 'fitnesspro' ),
					'use_media'    => __( 'استفاده از این رسانه', 'fitnesspro' ),
					'remove_media' => __( 'حذف رسانه', 'fitnesspro' ),
				),
			)
		);
	}

	/**
	 * Loads coach panel CSS/JS on the coach dashboard screen.
	 */
	public function enqueue_coach_assets( $hook_suffix ) {
		( new FitnessPro_Coach_Panel( $this->version ) )->enqueue_assets( $hook_suffix );
	}

	/**
	 * Loads dashboard stats + settings page assets on relevant FitnessPro screens.
	 */
	public function enqueue_settings_assets( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'fitnesspro' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'fitnesspro-admin-settings',
			FITNESSPRO_PLUGIN_URL . 'assets/css/admin-settings.css',
			array( 'fitnesspro-admin-rtl' ),
			$this->version
		);

		// Settings-specific JS only on the dynamic-fields tab
		// phpcs:ignore WordPress.Security.NonceVerification
		$current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( 'fitnesspro-settings' === $current_page ) {
			wp_enqueue_script(
				'fitnesspro-admin-settings',
				FITNESSPRO_PLUGIN_URL . 'assets/js/admin-settings.js',
				array(),
				$this->version,
				true
			);
			wp_localize_script(
				'fitnesspro-admin-settings',
				'fitnesspro_settings',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'fitnesspro_admin_nonce' ),
					'strings'  => array(
						'loading'     => __( 'در حال بارگذاری...', 'fitnesspro' ),
						'error'       => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'fitnesspro' ),
						'empty_list'  => __( 'هنوز موردی اضافه نشده.', 'fitnesspro' ),
						'item_suffix' => __( 'مورد', 'fitnesspro' ),
						'add'         => __( 'افزودن', 'fitnesspro' ),
						'remove'      => __( 'حذف', 'fitnesspro' ),
					),
				)
			);
		}
	}

	// ─── Menu Registration ────────────────────────────────────────────────────

	public function register_menus() {
		add_menu_page(
			__( 'فیتنس‌پرو', 'fitnesspro' ),
			__( 'فیتنس‌پرو', 'fitnesspro' ),
			'manage_options',
			'fitnesspro-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-heart',
			30
		);

		// Rename the auto-duplicated first submenu item
		add_submenu_page(
			'fitnesspro-dashboard',
			__( 'داشبورد مدیریت', 'fitnesspro' ),
			__( 'داشبورد', 'fitnesspro' ),
			'manage_options',
			'fitnesspro-dashboard',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'fitnesspro-dashboard',
			__( 'سفارشات فیتنس', 'fitnesspro' ),
			__( 'سفارشات', 'fitnesspro' ),
			'manage_options',
			'fitnesspro-orders',
			array( $this, 'render_orders_page' )
		);

		add_submenu_page(
			'fitnesspro-dashboard',
			__( 'تنظیمات فیتنس‌پرو', 'fitnesspro' ),
			__( 'تنظیمات', 'fitnesspro' ),
			'manage_options',
			'fitnesspro-settings',
			array( $this, 'render_settings_page' )
		);

		// Accessible to the fitness_coach role (edit_posts cap)
		add_submenu_page(
			'fitnesspro-dashboard',
			__( 'داشبورد مربی', 'fitnesspro' ),
			__( 'داشبورد مربی', 'fitnesspro' ),
			'edit_posts',
			'fitnesspro-coach-dashboard',
			array( $this, 'render_coach_dashboard' )
		);
	}

	// ─── Page Renderers ───────────────────────────────────────────────────────

	public function render_dashboard() {
		global $wpdb;

		$raw_counts = $wpdb->get_results(
			"SELECT status, COUNT(*) AS total FROM {$wpdb->prefix}fitness_user_plans GROUP BY status",
			ARRAY_A
		);
		$counts = array( 'active' => 0, 'pending' => 0, 'expired' => 0 );
		foreach ( (array) $raw_counts as $row ) {
			$counts[ $row['status'] ] = (int) $row['total'];
		}

		$coach_count = count( get_users( array( 'role' => FitnessPro_Roles::COACH_ROLE ) ) );
		?>
		<div class="wrap fitnesspro-wrap" dir="rtl">
			<h1><?php esc_html_e( 'داشبورد فیتنس‌پرو', 'fitnesspro' ); ?></h1>

			<div class="fp-stats-grid">
				<?php
				$cards = array(
					array( 'active',  $counts['active'],  __( 'بسته‌های فعال', 'fitnesspro' ) ),
					array( 'pending', $counts['pending'], __( 'در انتظار تخصیص', 'fitnesspro' ) ),
					array( 'expired', $counts['expired'], __( 'بسته‌های منقضی', 'fitnesspro' ) ),
					array( 'coaches', $coach_count,       __( 'مربیان فعال', 'fitnesspro' ) ),
				);
				foreach ( $cards as list( $mod, $num, $lbl ) ) : ?>
				<div class="fp-stat-card fp-stat-card--<?php echo esc_attr( $mod ); ?>">
					<div class="fp-stat-card__number"><?php echo (int) $num; ?></div>
					<div class="fp-stat-card__label"><?php echo esc_html( $lbl ); ?></div>
				</div>
				<?php endforeach; ?>
			</div>

			<div class="fp-quick-links">
				<h2><?php esc_html_e( 'دسترسی سریع', 'fitnesspro' ); ?></h2>
				<div class="fp-quick-links__grid">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fitnesspro-orders' ) ); ?>" class="fp-quick-link">
						<?php esc_html_e( 'مشاهده سفارشات', 'fitnesspro' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=fitnesspro-settings' ) ); ?>" class="fp-quick-link">
						<?php esc_html_e( 'تنظیمات', 'fitnesspro' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=workout_template' ) ); ?>" class="fp-quick-link">
						<?php esc_html_e( '+ تمپلت تمرین جدید', 'fitnesspro' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=meal_template' ) ); ?>" class="fp-quick-link">
						<?php esc_html_e( '+ تمپلت تغذیه جدید', 'fitnesspro' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_orders_page() {
		$table   = new FitnessPro_Orders_Table();
		$table->prepare_items();
		$coaches = get_users( array( 'role' => FitnessPro_Roles::COACH_ROLE ) );
		$nonce   = wp_create_nonce( FitnessPro_Coach_Panel::NONCE_KEY );
		?>
		<div class="wrap fitnesspro-wrap fitnesspro-orders-wrap" dir="rtl">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'سفارشات فیتنس', 'fitnesspro' ); ?></h1>
			<hr class="wp-header-end">
			<form method="get">
				<input type="hidden" name="page" value="fitnesspro-orders">
				<?php $table->display(); ?>
			</form>

			<?php /* ── Assign Coach modal ── */ ?>
			<input type="hidden" id="fp-coach-nonce-val" value="<?php echo esc_attr( $nonce ); ?>">
			<div class="fp-assign-coach-modal" id="fp-assign-coach-modal" hidden>
				<div class="fp-assign-coach-modal__overlay" id="fp-assign-overlay"></div>
				<div class="fp-assign-coach-modal__box" dir="rtl">
					<h3><?php esc_html_e( 'تخصیص مربی', 'fitnesspro' ); ?></h3>
					<input type="hidden" id="fp-assign-plan-id" value="">
					<select id="fp-assign-coach-sel">
						<option value="0"><?php esc_html_e( '— بدون مربی —', 'fitnesspro' ); ?></option>
						<?php foreach ( $coaches as $coach ) : ?>
						<option value="<?php echo esc_attr( $coach->ID ); ?>">
							<?php echo esc_html( $coach->display_name ); ?>
						</option>
						<?php endforeach; ?>
					</select>
					<div class="fp-assign-modal-foot">
						<button type="button" class="button" id="fp-assign-cancel">
							<?php esc_html_e( 'انصراف', 'fitnesspro' ); ?>
						</button>
						<button type="button" class="button button-primary" id="fp-assign-save">
							<?php esc_html_e( 'ذخیره', 'fitnesspro' ); ?>
						</button>
					</div>
				</div>
			</div><!-- .fp-assign-coach-modal -->

		</div>
		<?php
	}

	public function render_settings_page() {
		( new FitnessPro_Settings() )->render_page();
	}

	public function render_coach_dashboard() {
		( new FitnessPro_Coach_Panel( $this->version ) )->render();
	}
}
