<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce integration: creates wp_fitness_user_plans on order completion
 * and redirects the thank-you page to the AI processing animation.
 */
class FitnessPro_WC_Integration {

	const META_PLAN_CREATED  = '_fp_plan_created';
	const PLAN_DURATION_DAYS = 30;

	// ─── Order Completed Hook ─────────────────────────────────────────────────

	public function on_order_completed( int $order_id ) {
		if ( ! FitnessPro_Core::is_woocommerce_active() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Guard against duplicate processing on repeated status transitions
		if ( $order->get_meta( self::META_PLAN_CREATED ) ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( ! $user_id ) {
			return; // Guest checkout — no user to attach the plan to
		}

		// Determine plan type from ordered products vs. the admin product map
		$plan_type = $this->resolve_plan_type( $order );
		if ( ! $plan_type ) {
			return; // Order contains no mapped fitness product
		}

		global $wpdb;
		$table = $wpdb->prefix . 'fitness_user_plans';

		// Skip if an active/pending plan of the same type already exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table}
			 WHERE user_id = %d AND type = %s AND status IN ('active','pending')
			 LIMIT 1",
			$user_id,
			$plan_type
		) );

		if ( $existing ) {
			$order->update_meta_data( self::META_PLAN_CREATED, '1' );
			$order->save();
			return;
		}

		$expiry_date = gmdate( 'Y-m-d H:i:s', strtotime( '+' . self::PLAN_DURATION_DAYS . ' days' ) );

		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'     => $user_id,
				'coach_id'    => 0,
				'order_id'    => $order_id,
				'type'        => $plan_type,
				'status'      => 'pending',
				'expiry_date' => $expiry_date,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( $inserted ) {
			$order->update_meta_data( self::META_PLAN_CREATED, '1' );
			$order->save();

			// Wipe the checkout transient — data is now in the plans table
			delete_transient( 'fp_checkout_profile_' . $user_id );
		}
	}

	// ─── Thank-you Page Redirect ──────────────────────────────────────────────

	/**
	 * Fires on woocommerce_thankyou. Injects a JS redirect to the AI
	 * processing page when the completed order is a fitness plan order.
	 */
	public function redirect_to_ai_landing( int $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( ! $this->resolve_plan_type( $order ) ) {
			return; // Not a fitness order; leave WC thank-you page untouched
		}

		$landing_url = $this->get_ai_landing_url( $order_id );
		if ( ! $landing_url ) {
			return; // AI landing page not yet created by admin
		}
		?>
		<script>
		(function() {
			if ( window.location.href.indexOf( 'fitness_ai_processing' ) === -1 &&
			     window.location.href.indexOf( 'ai-processing' )         === -1 ) {
				window.location.replace( <?php echo wp_json_encode( $landing_url ); ?> );
			}
		}());
		</script>
		<?php
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Returns 'workout', 'meal', or null by comparing order items to the
	 * admin-configured product map.
	 */
	private function resolve_plan_type( \WC_Order $order ): ?string {
		$product_map = FitnessPro_Settings::get_product_map();
		$workout_id  = (int) ( $product_map['workout_product_id'] ?? 0 );
		$meal_id     = (int) ( $product_map['meal_product_id'] ?? 0 );

		foreach ( $order->get_items() as $item ) {
			$pid = (int) $item->get_product_id();
			if ( $workout_id && $pid === $workout_id ) {
				return 'workout';
			}
			if ( $meal_id && $pid === $meal_id ) {
				return 'meal';
			}
		}

		return null;
	}

	/**
	 * Finds the published page containing [fitness_ai_processing] shortcode.
	 * Returns null if no such page exists yet.
	 */
	private function get_ai_landing_url( int $order_id ): ?string {
		global $wpdb;

		$page_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_content LIKE %s
				   AND post_status  = 'publish'
				   AND post_type    = 'page'
				 LIMIT 1",
				'%' . $wpdb->esc_like( FitnessPro_AI_Landing::SHORTCODE ) . '%'
			)
		);

		if ( ! $page_id ) {
			return null;
		}

		return add_query_arg( 'order_id', $order_id, get_permalink( $page_id ) );
	}
}
