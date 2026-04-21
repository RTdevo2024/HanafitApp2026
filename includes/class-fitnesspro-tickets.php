<?php
defined( 'ABSPATH' ) || exit;

/**
 * Static helpers for wp_fitness_tickets DB operations.
 * Used by both the public Tickets UI and the admin monitoring page.
 */
class FitnessPro_Tickets {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'fitness_tickets';
	}

	/**
	 * All messages between a user and a coach, ordered oldest-first.
	 */
	public static function get_thread( int $user_id, int $coach_id ): array {
		global $wpdb;
		$t = self::table();
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t}
				 WHERE (sender_id = %d AND receiver_id = %d)
				    OR (sender_id = %d AND receiver_id = %d)
				 ORDER BY created_at ASC",
				$user_id, $coach_id,
				$coach_id, $user_id
			),
			ARRAY_A
		);
	}

	/**
	 * Insert one message row; returns new ID or false on failure.
	 *
	 * @return int|false
	 */
	public static function send_message(
		int $sender_id,
		int $receiver_id,
		string $message,
		string $attachment_url = ''
	) {
		global $wpdb;
		$inserted = $wpdb->insert(
			self::table(),
			array(
				'sender_id'      => $sender_id,
				'receiver_id'    => $receiver_id,
				'message'        => $message,
				'attachment_url' => $attachment_url,
				'status'         => 'open',
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * All distinct coach-user conversation threads for the admin monitor.
	 * Joined via wp_fitness_user_plans so we reliably know who is coach / user.
	 */
	public static function get_all_threads(): array {
		global $wpdb;
		$t      = self::table();
		$plans  = $wpdb->prefix . 'fitness_user_plans';
		$users  = $wpdb->users;

		$rows = (array) $wpdb->get_results(
			"SELECT
				p.user_id,
				p.coach_id,
				u.display_name  AS user_name,
				u.user_email    AS user_email,
				c.display_name  AS coach_name,
				COUNT(t.id)     AS message_count,
				MAX(t.created_at) AS last_at
			 FROM {$t} t
			 INNER JOIN {$plans} p ON (
			     (t.sender_id = p.user_id   AND t.receiver_id = p.coach_id)
			  OR (t.sender_id = p.coach_id  AND t.receiver_id = p.user_id)
			 )
			 INNER JOIN {$users} u ON u.ID = p.user_id
			 INNER JOIN {$users} c ON c.ID = p.coach_id
			 WHERE p.coach_id > 0
			 GROUP BY p.user_id, p.coach_id
			 ORDER BY last_at DESC",
			ARRAY_A
		);

		foreach ( $rows as &$row ) {
			$last = $wpdb->get_row( $wpdb->prepare(
				"SELECT message FROM {$t}
				 WHERE (sender_id = %d AND receiver_id = %d)
				    OR (sender_id = %d AND receiver_id = %d)
				 ORDER BY created_at DESC LIMIT 1",
				(int) $row['user_id'],  (int) $row['coach_id'],
				(int) $row['coach_id'], (int) $row['user_id']
			), ARRAY_A );
			$row['last_message'] = $last['message'] ?? '';
		}
		unset( $row );

		return $rows;
	}

	/**
	 * Upload an image via wp_handle_upload; returns public URL or '' on error.
	 *
	 * @param array $file  Element from $_FILES.
	 */
	public static function handle_upload( array $file ): string {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'gif'          => 'image/gif',
					'webp'         => 'image/webp',
				),
			)
		);

		if ( isset( $upload['error'] ) || ! isset( $upload['url'] ) ) {
			return '';
		}
		return (string) $upload['url'];
	}
}
