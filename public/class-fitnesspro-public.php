<?php
defined( 'ABSPATH' ) || exit;

/**
 * Frontend-side functionality: assets and shortcode registration.
 */
class FitnessPro_Public {

	private $version;

	public function __construct( $version ) {
		$this->version = $version;
	}

	public function enqueue_styles() {
		wp_enqueue_style(
			'fitnesspro-public-rtl',
			FITNESSPRO_PLUGIN_URL . 'assets/css/public-rtl.css',
			array(),
			$this->version
		);
	}

	public function enqueue_scripts() {
		wp_enqueue_script(
			'fitnesspro-public',
			FITNESSPRO_PLUGIN_URL . 'assets/js/public.js',
			array(),
			$this->version,
			true
		);
		wp_localize_script(
			'fitnesspro-public',
			'fitnesspro_public',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'fitnesspro_public_nonce' ),
				'strings'  => array(
					'loading' => __( 'در حال بارگذاری...', 'fitnesspro' ),
					'error'   => __( 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'fitnesspro' ),
					'success' => __( 'عملیات با موفقیت انجام شد.', 'fitnesspro' ),
				),
			)
		);
	}
}
