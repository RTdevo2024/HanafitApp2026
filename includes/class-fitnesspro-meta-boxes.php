<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles all meta boxes for workout_template and meal_template CPTs.
 *
 * Data model:
 *   Both CPTs store their entire plan in a single post meta key `_tpl_data`
 *   as a JSON-encoded array. This avoids multiple meta queries and keeps the
 *   plan structure self-contained.
 *
 * Workout JSON shape:
 *   { "saturday": { "exercises": [ { name, sets, reps, note, media_id, media_url } ] }, ... }
 *
 * Meal JSON shape:
 *   { "breakfast": { items, calories, protein, carbs, fat, note }, ... }
 */
class FitnessPro_Meta_Boxes {

	const META_KEY = '_tpl_data';

	// Called by FitnessPro_Core via the Loader — no register() method needed.

	public function add_meta_boxes() {
		add_meta_box(
			'fp_workout_planner',
			__( 'برنامه‌ریز هفتگی تمرینی', 'fitnesspro' ),
			array( $this, 'render_workout_box' ),
			'workout_template',
			'normal',
			'high'
		);

		add_meta_box(
			'fp_meal_planner',
			__( 'برنامه‌ریز تغذیه روزانه', 'fitnesspro' ),
			array( $this, 'render_meal_box' ),
			'meal_template',
			'normal',
			'high'
		);
	}

	// ─── Workout Meta Box ─────────────────────────────────────────────────────

	public function render_workout_box( $post ) {
		wp_nonce_field( 'fp_save_tpl_data', 'fp_tpl_nonce' );

		$raw  = get_post_meta( $post->ID, self::META_KEY, true );
		$data = $raw ? (array) json_decode( $raw, true ) : array();
		$days = $this->workout_days();
		?>
		<div class="fp-planner fp-workout-planner" dir="rtl">

			<div class="fp-tabs" role="tablist">
				<?php $first = true; foreach ( $days as $key => $label ) : ?>
					<button type="button"
						class="fp-tab-btn<?php echo $first ? ' fp-tab-btn--active' : ''; ?>"
						data-tab="<?php echo esc_attr( $key ); ?>"
						role="tab"
						aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
						<?php echo esc_html( $label ); ?>
					</button>
				<?php $first = false; endforeach; ?>
			</div>

			<?php $first = true; foreach ( $days as $key => $label ) :
				$exercises = isset( $data[ $key ]['exercises'] ) ? (array) $data[ $key ]['exercises'] : array();
			?>
			<div class="fp-tab-panel<?php echo $first ? ' fp-tab-panel--active' : ''; ?>"
				data-day="<?php echo esc_attr( $key ); ?>"
				role="tabpanel">

				<div class="fp-exercises-list" data-day="<?php echo esc_attr( $key ); ?>">
					<?php foreach ( $exercises as $i => $ex ) :
						$this->render_exercise_row( $key, $i, (array) $ex );
					endforeach; ?>
				</div>

				<button type="button"
					class="fp-btn fp-btn--add fp-add-exercise"
					data-day="<?php echo esc_attr( $key ); ?>">
					<?php esc_html_e( '+ افزودن تمرین', 'fitnesspro' ); ?>
				</button>
			</div>
			<?php $first = false; endforeach; ?>

			<?php /* JS cloning template — inputs inside <script> are not submitted */ ?>
			<script type="text/html" id="fp-exercise-row-tpl">
				<?php $this->render_exercise_row( '{{DAY}}', '{{INDEX}}', array() ); ?>
			</script>
		</div>
		<?php
	}

	private function render_exercise_row( $day, $index, array $ex ) {
		$p = "tpl[{$day}][exercises][{$index}]";
		?>
		<div class="fp-exercise-row">
			<div class="fp-row-handle" aria-hidden="true">&#9776;</div>

			<div class="fp-row-fields">

				<div class="fp-field fp-field--full">
					<label><?php esc_html_e( 'نام تمرین', 'fitnesspro' ); ?></label>
					<input type="text"
						name="<?php echo esc_attr( $p ); ?>[name]"
						value="<?php echo esc_attr( $ex['name'] ?? '' ); ?>"
						placeholder="<?php esc_attr_e( 'مثال: پرس سینه با هالتر', 'fitnesspro' ); ?>">
				</div>

				<div class="fp-field-row">
					<div class="fp-field fp-field--sm">
						<label><?php esc_html_e( 'ست', 'fitnesspro' ); ?></label>
						<input type="number"
							name="<?php echo esc_attr( $p ); ?>[sets]"
							value="<?php echo esc_attr( $ex['sets'] ?? '' ); ?>"
							min="1" max="99"
							placeholder="۴">
					</div>
					<div class="fp-field fp-field--sm">
						<label><?php esc_html_e( 'تکرار', 'fitnesspro' ); ?></label>
						<input type="text"
							name="<?php echo esc_attr( $p ); ?>[reps]"
							value="<?php echo esc_attr( $ex['reps'] ?? '' ); ?>"
							placeholder="۱۲">
					</div>
					<div class="fp-field fp-field--grow">
						<label><?php esc_html_e( 'یادداشت', 'fitnesspro' ); ?></label>
						<input type="text"
							name="<?php echo esc_attr( $p ); ?>[note]"
							value="<?php echo esc_attr( $ex['note'] ?? '' ); ?>"
							placeholder="<?php esc_attr_e( 'نکات فنی یا تمرین جایگزین...', 'fitnesspro' ); ?>">
					</div>
				</div>

				<div class="fp-field fp-media-field">
					<label><?php esc_html_e( 'رسانه (تصویر / ویدئو)', 'fitnesspro' ); ?></label>
					<div class="fp-media-preview">
						<?php if ( ! empty( $ex['media_url'] ) ) : ?>
							<img src="<?php echo esc_url( $ex['media_url'] ); ?>" alt="" class="fp-media-thumb">
						<?php endif; ?>
					</div>
					<input type="hidden"
						name="<?php echo esc_attr( $p ); ?>[media_id]"
						value="<?php echo esc_attr( $ex['media_id'] ?? '' ); ?>"
						class="fp-media-id">
					<input type="hidden"
						name="<?php echo esc_attr( $p ); ?>[media_url]"
						value="<?php echo esc_attr( $ex['media_url'] ?? '' ); ?>"
						class="fp-media-url">
					<div class="fp-media-actions">
						<button type="button" class="fp-btn fp-btn--media fp-select-media">
							<?php esc_html_e( 'انتخاب رسانه', 'fitnesspro' ); ?>
						</button>
						<button type="button"
							class="fp-btn fp-btn--remove-media fp-remove-media"
							<?php echo empty( $ex['media_url'] ) ? 'style="display:none"' : ''; ?>>
							<?php esc_html_e( 'حذف رسانه', 'fitnesspro' ); ?>
						</button>
					</div>
				</div>

			</div>

			<button type="button"
				class="fp-row-delete fp-delete-row"
				title="<?php esc_attr_e( 'حذف این تمرین', 'fitnesspro' ); ?>"
				aria-label="<?php esc_attr_e( 'حذف', 'fitnesspro' ); ?>">
				&times;
			</button>
		</div>
		<?php
	}

	// ─── Meal Meta Box ────────────────────────────────────────────────────────

	public function render_meal_box( $post ) {
		wp_nonce_field( 'fp_save_tpl_data', 'fp_tpl_nonce' );

		$raw   = get_post_meta( $post->ID, self::META_KEY, true );
		$data  = $raw ? (array) json_decode( $raw, true ) : array();
		$meals = $this->meal_slots();
		?>
		<div class="fp-planner fp-meal-planner" dir="rtl">
			<?php foreach ( $meals as $key => $label ) :
				$m  = isset( $data[ $key ] ) ? (array) $data[ $key ] : array();
				$p  = "tpl[{$key}]";
			?>
			<div class="fp-meal-card" data-meal="<?php echo esc_attr( $key ); ?>">

				<div class="fp-meal-card__header fp-meal-card__header--<?php echo esc_attr( $key ); ?>">
					<span class="fp-meal-card__label"><?php echo esc_html( $label ); ?></span>
				</div>

				<div class="fp-meal-card__body">

					<div class="fp-field fp-field--full">
						<label><?php esc_html_e( 'اقلام غذایی', 'fitnesspro' ); ?></label>
						<textarea
							name="<?php echo esc_attr( $p ); ?>[items]"
							rows="3"
							placeholder="<?php esc_attr_e( 'هر قلم در یک خط وارد کنید...', 'fitnesspro' ); ?>"><?php echo esc_textarea( $m['items'] ?? '' ); ?></textarea>
					</div>

					<div class="fp-macros-row">
						<?php
						$macros = array(
							'calories' => array( __( 'کالری', 'fitnesspro' ), 'kcal' ),
							'protein'  => array( __( 'پروتئین', 'fitnesspro' ), 'g' ),
							'carbs'    => array( __( 'کربوهیدرات', 'fitnesspro' ), 'g' ),
							'fat'      => array( __( 'چربی', 'fitnesspro' ), 'g' ),
						);
						foreach ( $macros as $macro_key => list( $macro_label, $unit ) ) : ?>
						<div class="fp-macro-field">
							<label><?php echo esc_html( $macro_label ); ?></label>
							<div class="fp-macro-input-wrap">
								<input type="number"
									name="<?php echo esc_attr( $p ); ?>[<?php echo esc_attr( $macro_key ); ?>]"
									value="<?php echo esc_attr( $m[ $macro_key ] ?? '' ); ?>"
									min="0"
									placeholder="0">
								<span class="fp-unit"><?php echo esc_html( $unit ); ?></span>
							</div>
						</div>
						<?php endforeach; ?>
					</div>

					<div class="fp-field fp-field--full">
						<label><?php esc_html_e( 'یادداشت', 'fitnesspro' ); ?></label>
						<input type="text"
							name="<?php echo esc_attr( $p ); ?>[note]"
							value="<?php echo esc_attr( $m['note'] ?? '' ); ?>"
							placeholder="<?php esc_attr_e( 'مثال: یک ساعت قبل از تمرین مصرف شود', 'fitnesspro' ); ?>">
					</div>

				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	// ─── Save Handler ─────────────────────────────────────────────────────────

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['fp_tpl_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_tpl_nonce'] ) ), 'fp_save_tpl_data' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, array( 'workout_template', 'meal_template' ), true ) ) {
			return;
		}
		if ( empty( $_POST['tpl'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw  = wp_unslash( $_POST['tpl'] );
		$data = ( 'workout_template' === $post->post_type )
			? $this->sanitize_workout( $raw )
			: $this->sanitize_meal( $raw );

		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $data ) );
	}

	private function sanitize_workout( array $raw ): array {
		$clean        = array();
		$allowed_days = array_keys( $this->workout_days() );

		foreach ( $allowed_days as $day ) {
			if ( empty( $raw[ $day ]['exercises'] ) || ! is_array( $raw[ $day ]['exercises'] ) ) {
				continue;
			}
			$clean[ $day ]['exercises'] = array();
			foreach ( $raw[ $day ]['exercises'] as $ex ) {
				if ( empty( $ex['name'] ) ) {
					continue; // Skip unnamed exercises
				}
				$clean[ $day ]['exercises'][] = array(
					'name'      => sanitize_text_field( $ex['name'] ),
					'sets'      => absint( $ex['sets'] ?? 0 ),
					'reps'      => sanitize_text_field( $ex['reps'] ?? '' ),
					'note'      => sanitize_text_field( $ex['note'] ?? '' ),
					'media_id'  => absint( $ex['media_id'] ?? 0 ),
					'media_url' => esc_url_raw( $ex['media_url'] ?? '' ),
				);
			}
		}

		return $clean;
	}

	private function sanitize_meal( array $raw ): array {
		$clean         = array();
		$allowed_meals = array_keys( $this->meal_slots() );

		foreach ( $allowed_meals as $meal ) {
			if ( empty( $raw[ $meal ] ) || ! is_array( $raw[ $meal ] ) ) {
				continue;
			}
			$m             = $raw[ $meal ];
			$clean[ $meal ] = array(
				'items'    => sanitize_textarea_field( $m['items'] ?? '' ),
				'calories' => absint( $m['calories'] ?? 0 ),
				'protein'  => absint( $m['protein'] ?? 0 ),
				'carbs'    => absint( $m['carbs'] ?? 0 ),
				'fat'      => absint( $m['fat'] ?? 0 ),
				'note'     => sanitize_text_field( $m['note'] ?? '' ),
			);
		}

		return $clean;
	}

	// ─── Reference Data ───────────────────────────────────────────────────────

	private function workout_days(): array {
		return array(
			'saturday'  => __( 'شنبه', 'fitnesspro' ),
			'sunday'    => __( 'یکشنبه', 'fitnesspro' ),
			'monday'    => __( 'دوشنبه', 'fitnesspro' ),
			'tuesday'   => __( 'سه‌شنبه', 'fitnesspro' ),
			'wednesday' => __( 'چهارشنبه', 'fitnesspro' ),
			'thursday'  => __( 'پنجشنبه', 'fitnesspro' ),
			'friday'    => __( 'جمعه', 'fitnesspro' ),
		);
	}

	private function meal_slots(): array {
		return array(
			'breakfast' => __( 'صبحانه', 'fitnesspro' ),
			'snack1'    => __( 'میان‌وعده اول', 'fitnesspro' ),
			'lunch'     => __( 'ناهار', 'fitnesspro' ),
			'snack2'    => __( 'میان‌وعده دوم', 'fitnesspro' ),
			'dinner'    => __( 'شام', 'fitnesspro' ),
		);
	}
}
