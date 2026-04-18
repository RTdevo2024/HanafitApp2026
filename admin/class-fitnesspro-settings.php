<?php
defined( 'ABSPATH' ) || exit;

/**
 * Handles all admin settings logic:
 *  - WooCommerce product-to-plan mapping (saved via admin_post)
 *  - Dynamic field lists (AJAX add/remove, stored in wp_options as JSON)
 *
 * Option keys:
 *  fitnesspro_product_map       → { workout_product_id, meal_product_id }
 *  fitnesspro_field_diseases    → JSON array
 *  fitnesspro_field_goals       → JSON array
 *  fitnesspro_field_activity_levels  → JSON array
 *  fitnesspro_field_eating_disorders → JSON array
 */
class FitnessPro_Settings {

	const OPT_PRODUCT_MAP      = 'fitnesspro_product_map';
	const OPT_DISEASES         = 'fitnesspro_field_diseases';
	const OPT_GOALS            = 'fitnesspro_field_goals';
	const OPT_ACTIVITY_LEVELS  = 'fitnesspro_field_activity_levels';
	const OPT_EATING_DISORDERS = 'fitnesspro_field_eating_disorders';

	// ─── admin_post Handler ───────────────────────────────────────────────────

	public function handle_product_map_save() {
		check_admin_referer( 'fp_product_map_save' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'fitnesspro' ) );
		}

		update_option(
			self::OPT_PRODUCT_MAP,
			array(
				'workout_product_id' => absint( $_POST['workout_product_id'] ?? 0 ),
				'meal_product_id'    => absint( $_POST['meal_product_id'] ?? 0 ),
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'fitnesspro-settings', 'tab' => 'products', 'updated' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ─── AJAX: Add field item ─────────────────────────────────────────────────

	public function ajax_add_field_item() {
		check_ajax_referer( 'fitnesspro_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'fitnesspro' ) ) );
		}

		$field_key = sanitize_key( wp_unslash( $_POST['field_key'] ?? '' ) );
		$value     = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

		if ( ! $this->is_valid_field_key( $field_key ) || '' === $value ) {
			wp_send_json_error( array( 'message' => __( 'داده‌های ورودی نامعتبر است.', 'fitnesspro' ) ) );
		}

		$items = $this->get_field_items( $field_key );

		if ( in_array( $value, $items, true ) ) {
			wp_send_json_error( array( 'message' => __( 'این مورد قبلاً در لیست موجود است.', 'fitnesspro' ) ) );
		}

		$items[] = $value;
		update_option( $this->field_key_to_option( $field_key ), wp_json_encode( $items ) );

		wp_send_json_success( array( 'items' => $items ) );
	}

	// ─── AJAX: Remove field item ──────────────────────────────────────────────

	public function ajax_remove_field_item() {
		check_ajax_referer( 'fitnesspro_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'fitnesspro' ) ) );
		}

		$field_key = sanitize_key( wp_unslash( $_POST['field_key'] ?? '' ) );
		$index     = isset( $_POST['index'] ) ? (int) $_POST['index'] : -1;

		if ( ! $this->is_valid_field_key( $field_key ) || $index < 0 ) {
			wp_send_json_error( array( 'message' => __( 'داده‌های ورودی نامعتبر است.', 'fitnesspro' ) ) );
		}

		$items = $this->get_field_items( $field_key );

		if ( ! isset( $items[ $index ] ) ) {
			wp_send_json_error( array( 'message' => __( 'آیتم مورد نظر یافت نشد.', 'fitnesspro' ) ) );
		}

		array_splice( $items, $index, 1 );
		update_option( $this->field_key_to_option( $field_key ), wp_json_encode( array_values( $items ) ) );

		wp_send_json_success( array( 'items' => array_values( $items ) ) );
	}

	// ─── Page Renderer ────────────────────────────────────────────────────────

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'products';
		// phpcs:ignore WordPress.Security.NonceVerification
		$updated = ! empty( $_GET['updated'] );

		$product_map = (array) get_option(
			self::OPT_PRODUCT_MAP,
			array( 'workout_product_id' => 0, 'meal_product_id' => 0 )
		);
		?>
		<div class="wrap fitnesspro-wrap fp-settings-page" dir="rtl">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'تنظیمات فیتنس‌پرو', 'fitnesspro' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( $updated ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'تنظیمات با موفقیت ذخیره شد.', 'fitnesspro' ); ?></p>
			</div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper fp-nav-tabs">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fitnesspro-settings', 'tab' => 'products' ), admin_url( 'admin.php' ) ) ); ?>"
					class="nav-tab<?php echo 'products' === $active_tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'محصولات ووکامرس', 'fitnesspro' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'fitnesspro-settings', 'tab' => 'fields' ), admin_url( 'admin.php' ) ) ); ?>"
					class="nav-tab<?php echo 'fields' === $active_tab ? ' nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'فیلدهای پویا', 'fitnesspro' ); ?>
				</a>
			</nav>

			<div class="fp-settings-body">
				<?php if ( 'products' === $active_tab ) : ?>
					<?php $this->render_product_map_tab( $product_map ); ?>
				<?php else : ?>
					<?php $this->render_dynamic_fields_tab(); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// ─── Product Map Tab ──────────────────────────────────────────────────────

	private function render_product_map_tab( array $map ) {
		?>
		<div class="fp-settings-section">
			<p class="fp-settings-desc">
				<?php esc_html_e( 'محصولات ووکامرس را که نمایانگر خرید هر نوع برنامه هستند انتخاب کنید. پس از تکمیل پرداخت، برنامه مربوطه برای کاربر فعال می‌شود.', 'fitnesspro' ); ?>
			</p>

			<?php if ( ! FitnessPro_Core::is_woocommerce_active() ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php esc_html_e( 'ووکامرس فعال نیست. این بخش پس از فعال‌سازی ووکامرس در دسترس خواهد بود.', 'fitnesspro' ); ?>
			</p></div>
			<?php else : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'fp_product_map_save' ); ?>
				<input type="hidden" name="action" value="fp_save_product_map">

				<table class="form-table fp-form-table">
					<tbody>
					<?php
					$fields = array(
						'workout_product_id' => array(
							__( 'محصول برنامه تمرینی', 'fitnesspro' ),
							__( 'محصولی که خرید آن یک برنامه تمرینی را فعال می‌کند.', 'fitnesspro' ),
						),
						'meal_product_id'    => array(
							__( 'محصول برنامه تغذیه', 'fitnesspro' ),
							__( 'محصولی که خرید آن یک برنامه تغذیه را فعال می‌کند.', 'fitnesspro' ),
						),
					);
					foreach ( $fields as $field_name => list( $label, $desc ) ) :
						$saved_id = (int) ( $map[ $field_name ] ?? 0 );
					?>
					<tr>
						<th scope="row">
							<label for="<?php echo esc_attr( $field_name ); ?>"><?php echo esc_html( $label ); ?></label>
						</th>
						<td>
							<select id="<?php echo esc_attr( $field_name ); ?>"
								name="<?php echo esc_attr( $field_name ); ?>"
								class="fp-product-select">
								<option value="0"><?php esc_html_e( '— انتخاب محصول —', 'fitnesspro' ); ?></option>
								<?php foreach ( $this->get_wc_products() as $product ) : ?>
								<option value="<?php echo esc_attr( $product->ID ); ?>"
									<?php selected( $saved_id, $product->ID ); ?>>
									#<?php echo esc_html( $product->ID ); ?> &mdash; <?php echo esc_html( $product->post_title ); ?>
								</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html( $desc ); ?></p>
						</td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'ذخیره تنظیمات', 'fitnesspro' ) ); ?>
			</form>

			<?php endif; ?>
		</div>
		<?php
	}

	// ─── Dynamic Fields Tab ───────────────────────────────────────────────────

	private function render_dynamic_fields_tab() {
		$groups = $this->get_field_group_definitions();
		?>
		<div class="fp-settings-section">
			<p class="fp-settings-desc">
				<?php esc_html_e( 'آیتم‌های هر لیست را مدیریت کنید. این مقادیر در فرم اطلاعات کاربران نمایش داده می‌شوند.', 'fitnesspro' ); ?>
			</p>

			<div class="fp-field-groups-grid" id="fp-field-groups-grid">
				<?php foreach ( $groups as $key => $group ) :
					$items = $this->get_field_items( $key );
				?>
				<div class="fp-field-group-card" data-field-key="<?php echo esc_attr( $key ); ?>">
					<div class="fp-field-group-card__header">
						<h3><?php echo esc_html( $group['label'] ); ?></h3>
						<span class="fp-item-counter"><?php echo count( $items ); ?> <?php esc_html_e( 'مورد', 'fitnesspro' ); ?></span>
					</div>
					<div class="fp-field-group-card__body">
						<div class="fp-tags-list">
							<?php if ( empty( $items ) ) : ?>
								<span class="fp-empty-notice"><?php esc_html_e( 'هنوز موردی اضافه نشده.', 'fitnesspro' ); ?></span>
							<?php else : ?>
								<?php foreach ( $items as $i => $item ) : ?>
								<span class="fp-tag" data-index="<?php echo esc_attr( $i ); ?>">
									<span class="fp-tag__text"><?php echo esc_html( $item ); ?></span>
									<button type="button"
										class="fp-tag__remove fp-js-remove-item"
										data-field="<?php echo esc_attr( $key ); ?>"
										data-index="<?php echo esc_attr( $i ); ?>"
										aria-label="<?php esc_attr_e( 'حذف', 'fitnesspro' ); ?>">
										&times;
									</button>
								</span>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<div class="fp-add-item-row">
							<input type="text"
								class="fp-add-item-input"
								placeholder="<?php echo esc_attr( $group['placeholder'] ); ?>"
								data-field="<?php echo esc_attr( $key ); ?>"
								autocomplete="off">
							<button type="button"
								class="fp-btn fp-btn--sm fp-js-add-item"
								data-field="<?php echo esc_attr( $key ); ?>">
								<?php esc_html_e( '+ افزودن', 'fitnesspro' ); ?>
							</button>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	// ─── Public Data Accessors ────────────────────────────────────────────────

	public function get_field_items( string $field_key ): array {
		$option = $this->field_key_to_option( $field_key );
		if ( ! $option ) {
			return array();
		}
		$raw   = get_option( $option, '[]' );
		$items = json_decode( $raw, true );
		return is_array( $items ) ? $items : array();
	}

	public static function get_product_map(): array {
		return (array) get_option(
			self::OPT_PRODUCT_MAP,
			array( 'workout_product_id' => 0, 'meal_product_id' => 0 )
		);
	}

	// ─── Internals ────────────────────────────────────────────────────────────

	private function get_field_group_definitions(): array {
		return array(
			'diseases'         => array(
				'label'       => __( 'بیماری‌ها', 'fitnesspro' ),
				'placeholder' => __( 'مثال: دیابت نوع ۲', 'fitnesspro' ),
			),
			'goals'            => array(
				'label'       => __( 'اهداف', 'fitnesspro' ),
				'placeholder' => __( 'مثال: کاهش وزن', 'fitnesspro' ),
			),
			'activity_levels'  => array(
				'label'       => __( 'سطح فعالیت', 'fitnesspro' ),
				'placeholder' => __( 'مثال: نیمه‌حرفه‌ای', 'fitnesspro' ),
			),
			'eating_disorders' => array(
				'label'       => __( 'اختلالات تغذیه‌ای', 'fitnesspro' ),
				'placeholder' => __( 'مثال: بی‌اشتهایی عصبی', 'fitnesspro' ),
			),
		);
	}

	private function field_key_to_option( string $key ): string {
		$map = array(
			'diseases'         => self::OPT_DISEASES,
			'goals'            => self::OPT_GOALS,
			'activity_levels'  => self::OPT_ACTIVITY_LEVELS,
			'eating_disorders' => self::OPT_EATING_DISORDERS,
		);
		return $map[ $key ] ?? '';
	}

	private function is_valid_field_key( string $key ): bool {
		return array_key_exists( $key, array(
			'diseases'         => 1,
			'goals'            => 1,
			'activity_levels'  => 1,
			'eating_disorders' => 1,
		) );
	}

	private function get_wc_products(): array {
		return get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
	}
}
