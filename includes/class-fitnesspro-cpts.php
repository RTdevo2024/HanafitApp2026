<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the workout_template and meal_template custom post types.
 * Both are private (not publicly queryable) and appear under the FitnessPro
 * admin menu via show_in_menu.
 */
class FitnessPro_CPTs {

	public function register() {
		$this->register_workout_template();
		$this->register_meal_template();
	}

	private function register_workout_template() {
		$labels = array(
			'name'               => __( 'تمپلت‌های تمرینی', 'fitnesspro' ),
			'singular_name'      => __( 'تمپلت تمرینی', 'fitnesspro' ),
			'add_new'            => __( 'تمپلت جدید', 'fitnesspro' ),
			'add_new_item'       => __( 'افزودن تمپلت تمرینی', 'fitnesspro' ),
			'edit_item'          => __( 'ویرایش تمپلت تمرینی', 'fitnesspro' ),
			'view_item'          => __( 'مشاهده تمپلت تمرینی', 'fitnesspro' ),
			'search_items'       => __( 'جستجو در تمپلت‌ها', 'fitnesspro' ),
			'not_found'          => __( 'تمپلتی یافت نشد.', 'fitnesspro' ),
			'not_found_in_trash' => __( 'سطل زباله خالی است.', 'fitnesspro' ),
			'all_items'          => __( 'همه تمپلت‌های تمرینی', 'fitnesspro' ),
			'menu_name'          => __( 'تمپلت تمرین', 'fitnesspro' ),
		);

		register_post_type(
			'workout_template',
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'fitnesspro-dashboard',
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'edit_posts' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'author' ),
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	private function register_meal_template() {
		$labels = array(
			'name'               => __( 'تمپلت‌های تغذیه', 'fitnesspro' ),
			'singular_name'      => __( 'تمپلت تغذیه', 'fitnesspro' ),
			'add_new'            => __( 'تمپلت جدید', 'fitnesspro' ),
			'add_new_item'       => __( 'افزودن تمپلت تغذیه', 'fitnesspro' ),
			'edit_item'          => __( 'ویرایش تمپلت تغذیه', 'fitnesspro' ),
			'view_item'          => __( 'مشاهده تمپلت تغذیه', 'fitnesspro' ),
			'search_items'       => __( 'جستجو در تمپلت‌ها', 'fitnesspro' ),
			'not_found'          => __( 'تمپلتی یافت نشد.', 'fitnesspro' ),
			'not_found_in_trash' => __( 'سطل زباله خالی است.', 'fitnesspro' ),
			'all_items'          => __( 'همه تمپلت‌های تغذیه', 'fitnesspro' ),
			'menu_name'          => __( 'تمپلت تغذیه', 'fitnesspro' ),
		);

		register_post_type(
			'meal_template',
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'fitnesspro-dashboard',
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'edit_posts' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title', 'author' ),
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}
}
