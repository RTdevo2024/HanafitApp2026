<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages all custom database table creation and removal.
 *
 * Tables:
 *   - wp_fitness_user_plans
 *   - wp_fitness_active_content
 *   - wp_fitness_tickets
 *   - wp_fitness_daily_progress
 */
class FitnessPro_Database {

	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// ─── Table 1: User Plans ─────────────────────────────────────────────
		$sql1 = "CREATE TABLE {$wpdb->prefix}fitness_user_plans (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id       BIGINT(20) UNSIGNED NOT NULL,
			coach_id      BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			order_id      BIGINT(20) UNSIGNED DEFAULT NULL,
			type          ENUM('workout','meal') NOT NULL DEFAULT 'workout',
			status        ENUM('active','expired','pending') NOT NULL DEFAULT 'pending',
			expiry_date   DATE DEFAULT NULL,
			created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_user_id  (user_id),
			KEY idx_coach_id (coach_id),
			KEY idx_status   (status),
			KEY idx_order_id (order_id)
		) {$charset_collate};";

		// ─── Table 2: Active Content (personalized plan JSON) ────────────────
		$sql2 = "CREATE TABLE {$wpdb->prefix}fitness_active_content (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_plan_id BIGINT(20) UNSIGNED NOT NULL,
			content_json LONGTEXT NOT NULL,
			updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_user_plan_id (user_plan_id)
		) {$charset_collate};";

		// ─── Table 3: Support Tickets ────────────────────────────────────────
		$sql3 = "CREATE TABLE {$wpdb->prefix}fitness_tickets (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			sender_id      BIGINT(20) UNSIGNED NOT NULL,
			receiver_id    BIGINT(20) UNSIGNED NOT NULL,
			message        LONGTEXT NOT NULL,
			attachment_url VARCHAR(2048) DEFAULT NULL,
			status         ENUM('open','closed','pending') NOT NULL DEFAULT 'open',
			created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_sender_id   (sender_id),
			KEY idx_receiver_id (receiver_id),
			KEY idx_status      (status)
		) {$charset_collate};";

		// ─── Table 4: Daily Progress ─────────────────────────────────────────
		$sql4 = "CREATE TABLE {$wpdb->prefix}fitness_daily_progress (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id        BIGINT(20) UNSIGNED NOT NULL,
			date           DATE NOT NULL,
			completed_json LONGTEXT NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_user_date (user_id, date),
			KEY idx_user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql1 );
		dbDelta( $sql2 );
		dbDelta( $sql3 );
		dbDelta( $sql4 );

		update_option( 'fitnesspro_db_version', FITNESSPRO_VERSION );
	}

	/**
	 * Called only from uninstall.php — drops all plugin tables permanently.
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'fitness_daily_progress',
			$wpdb->prefix . 'fitness_tickets',
			$wpdb->prefix . 'fitness_active_content',
			$wpdb->prefix . 'fitness_user_plans',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( 'fitnesspro_db_version' );
	}
}
