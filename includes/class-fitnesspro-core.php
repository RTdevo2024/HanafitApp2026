<?php
defined( 'ABSPATH' ) || exit;

/**
 * Core plugin orchestrator. Wires together the loader, admin, public,
 * and role classes, and handles the WooCommerce dependency check.
 */
class FitnessPro_Core {

	protected $loader;
	protected $version;

	public function __construct() {
		$this->version = FITNESSPRO_VERSION;
		$this->loader  = new FitnessPro_Loader();

		$this->load_textdomain();
		$this->check_woocommerce();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_role_hooks();
	}

	private function load_textdomain() {
		$this->loader->add_action( 'init', $this, 'load_plugin_textdomain' );
	}

	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'fitnesspro',
			false,
			dirname( FITNESSPRO_PLUGIN_BASENAME ) . '/languages/'
		);
	}

	private function check_woocommerce() {
		if ( ! self::is_woocommerce_active() ) {
			$this->loader->add_action( 'admin_notices', $this, 'woocommerce_missing_notice' );
		}
	}

	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error is-dismissible" style="direction:rtl;text-align:right;">
			<p>
				<strong><?php esc_html_e( 'سیستم فیتنس‌پرو:', 'fitnesspro' ); ?></strong>
				<?php esc_html_e( 'افزونه ووکامرس فعال نیست. برای استفاده از تمام قابلیت‌های این افزونه، لطفاً ووکامرس را نصب و فعال کنید.', 'fitnesspro' ); ?>
			</p>
		</div>
		<?php
	}

	private function define_admin_hooks() {
		$admin = new FitnessPro_Admin( $this->version );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $admin, 'register_menus' );
	}

	private function define_public_hooks() {
		$public = new FitnessPro_Public( $this->version );
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );
	}

	private function define_role_hooks() {
		$roles = new FitnessPro_Roles();
		$this->loader->add_action( 'admin_init', $roles, 'restrict_dashboard_access' );
	}

	public function run() {
		$this->loader->run();
	}
}
