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
		$this->define_content_hooks();
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
		$admin    = new FitnessPro_Admin( $this->version );
		$settings = new FitnessPro_Settings();
		$coach    = new FitnessPro_Coach_Panel( $this->version );

		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_metabox_assets' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_settings_assets' );
		$this->loader->add_action( 'admin_enqueue_scripts', $coach, 'enqueue_assets' );
		$this->loader->add_action( 'admin_menu', $admin, 'register_menus' );

		// Product map save (form POST via admin-post.php)
		$this->loader->add_action( 'admin_post_fp_save_product_map', $settings, 'handle_product_map_save' );

		// Dynamic field AJAX (admin-only; no nopriv variant needed)
		$this->loader->add_action( 'wp_ajax_fp_add_field_item',    $settings, 'ajax_add_field_item' );
		$this->loader->add_action( 'wp_ajax_fp_remove_field_item', $settings, 'ajax_remove_field_item' );

		// Coach panel AJAX — coaches + admins
		$this->loader->add_action( 'wp_ajax_fp_get_coach_templates', $coach, 'ajax_get_templates' );
		$this->loader->add_action( 'wp_ajax_fp_assign_plan',         $coach, 'ajax_assign_plan' );

		// Coach assignment — admin only
		$this->loader->add_action( 'wp_ajax_fp_assign_coach', $coach, 'ajax_assign_coach' );
	}

	private function define_public_hooks() {
		$public = new FitnessPro_Public( $this->version );
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );

		$checkout_ui = new FitnessPro_Checkout_UI( $this->version );
		$this->loader->add_action( 'init',               $checkout_ui, 'register_shortcode' );
		$this->loader->add_action( 'wp_enqueue_scripts', $checkout_ui, 'maybe_enqueue_assets' );

		// Checkout flow AJAX — auth available to guests, others require login
		$this->loader->add_action( 'wp_ajax_nopriv_fp_cof_auth',  $checkout_ui, 'ajax_auth' );
		$this->loader->add_action( 'wp_ajax_fp_cof_auth',         $checkout_ui, 'ajax_auth' );
		$this->loader->add_action( 'wp_ajax_fp_cof_save_profile', $checkout_ui, 'ajax_save_profile' );
		$this->loader->add_action( 'wp_ajax_fp_cof_add_to_cart',  $checkout_ui, 'ajax_add_to_cart' );

		// Combined Pay button handler: save profile transient + add to WC cart + return checkout URL
		$this->loader->add_action( 'wp_ajax_fp_process_checkout', $checkout_ui, 'ajax_process_checkout' );

		// AI landing page shortcode + conditional asset enqueue
		$ai_landing = new FitnessPro_AI_Landing( $this->version );
		$this->loader->add_action( 'init',               $ai_landing, 'register_shortcode' );
		$this->loader->add_action( 'wp_enqueue_scripts', $ai_landing, 'maybe_enqueue_assets' );

		// WooCommerce order integration
		$wc_integration = new FitnessPro_WC_Integration();
		$this->loader->add_action( 'woocommerce_order_status_completed', $wc_integration, 'on_order_completed' );
		$this->loader->add_action( 'woocommerce_thankyou',               $wc_integration, 'redirect_to_ai_landing' );

		// User dashboard shortcode + AJAX
		$dashboard = new FitnessPro_User_Dashboard( $this->version );
		$this->loader->add_action( 'init',               $dashboard, 'register_shortcode' );
		$this->loader->add_action( 'wp_enqueue_scripts', $dashboard, 'maybe_enqueue_assets' );
		$this->loader->add_action( 'wp_ajax_fp_save_progress', $dashboard, 'ajax_save_progress' );
	}

	private function define_role_hooks() {
		$roles = new FitnessPro_Roles();
		$this->loader->add_action( 'admin_init', $roles, 'restrict_dashboard_access' );
	}

	private function define_content_hooks() {
		// CPTs
		$cpts = new FitnessPro_CPTs();
		$this->loader->add_action( 'init', $cpts, 'register' );

		// Meta Boxes
		$meta_boxes = new FitnessPro_Meta_Boxes();
		$this->loader->add_action( 'add_meta_boxes', $meta_boxes, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post', $meta_boxes, 'save_meta', 10, 2 );
	}

	public function run() {
		$this->loader->run();
	}
}
