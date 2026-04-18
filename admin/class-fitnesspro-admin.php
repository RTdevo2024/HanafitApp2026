<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin-side functionality: menus, assets, and page renderers.
 */
class FitnessPro_Admin {

	private $version;

	public function __construct( $version ) {
		$this->version = $version;
	}

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
	 * Enqueues meta-box assets only on workout_template / meal_template edit screens.
	 * Also loads wp.media for the image/video picker.
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

	public function register_menus() {
		// Main menu — visible to site admins only.
		add_menu_page(
			__( 'فیتنس‌پرو', 'fitnesspro' ),
			__( 'فیتنس‌پرو', 'fitnesspro' ),
			'manage_options',
			'fitnesspro-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-heart',
			30
		);

		// Coach sub-page — accessible to the fitness_coach role.
		add_submenu_page(
			'fitnesspro-dashboard',
			__( 'داشبورد مربی', 'fitnesspro' ),
			__( 'داشبورد مربی', 'fitnesspro' ),
			'edit_posts',
			'fitnesspro-coach-dashboard',
			array( $this, 'render_coach_dashboard' )
		);
	}

	public function render_dashboard() {
		?>
		<div class="wrap fitnesspro-wrap" dir="rtl">
			<h1><?php esc_html_e( 'داشبورد فیتنس‌پرو', 'fitnesspro' ); ?></h1>
			<p><?php esc_html_e( 'به پنل مدیریت سیستم فیتنس‌پرو خوش آمدید.', 'fitnesspro' ); ?></p>
		</div>
		<?php
	}

	public function render_coach_dashboard() {
		?>
		<div class="wrap fitnesspro-wrap" dir="rtl">
			<h1><?php esc_html_e( 'داشبورد مربی', 'fitnesspro' ); ?></h1>
			<p><?php esc_html_e( 'پنل اختصاصی مربی فیتنس — برنامه‌ها و پیام‌های شما اینجا نمایش داده می‌شوند.', 'fitnesspro' ); ?></p>
		</div>
		<?php
	}
}
