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
