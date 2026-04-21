<?php
defined( 'ABSPATH' ) || exit;

/**
 * Public ticket/chat shortcode [fitnesspro_tickets].
 *
 * Renders a dark-mode RTL chat thread between the logged-in user and their
 * assigned coach. Also owns the notification bell AJAX endpoints used by both
 * the ticket page and the user dashboard.
 */
class FitnessPro_Tickets_UI {

	const SHORTCODE = 'fitnesspro_tickets';
	const NONCE_KEY = 'fp_tickets_nonce';

	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	// ─── Hook Registration ────────────────────────────────────────────────────

	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Load tickets CSS + JS on any page that contains either the ticket
	 * shortcode or the dashboard shortcode (for the notification bell).
	 */
	public function maybe_enqueue_assets(): void {
		global $post;
		if ( ! is_singular() || ! is_a( $post, 'WP_Post' ) ) {
			return;
		}
		if (
			has_shortcode( $post->post_content, self::SHORTCODE ) ||
			has_shortcode( $post->post_content, FitnessPro_User_Dashboard::SHORTCODE )
		) {
			$this->do_enqueue();
		}
	}

	private function do_enqueue(): void {
		wp_enqueue_style(
			'fitnesspro-tickets',
			FITNESSPRO_PLUGIN_URL . 'assets/css/tickets.css',
			array( 'fitnesspro-user-dashboard' ),
			$this->version
		);
		wp_enqueue_script(
			'fitnesspro-tickets',
			FITNESSPRO_PLUGIN_URL . 'assets/js/tickets.js',
			array(),
			$this->version,
			true
		);
	}

	// ─── Shortcode Render ─────────────────────────────────────────────────────

	public function render( $atts ): string {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $this->render_state(
				'🔒',
				__( 'ابتدا وارد حساب کاربری خود شوید', 'fitnesspro' ),
				'',
				wp_login_url( get_permalink() ),
				__( 'ورود به حساب', 'fitnesspro' )
			);
		}

		$coach = $this->get_user_coach( $user_id );
		if ( ! $coach ) {
			return $this->render_state(
				'⏳',
				__( 'هنوز مربی به شما تخصیص داده نشده است', 'fitnesspro' ),
				__( 'پس از خرید برنامه، مربی شما تخصیص می‌یابد.', 'fitnesspro' )
			);
		}

		$this->inject_config( $user_id, $coach );

		$messages = FitnessPro_Tickets::get_thread( $user_id, $coach['id'] );
		$last_id  = empty( $messages ) ? 0 : (int) end( $messages )['id'];

		ob_start();
		?>
		<div id="fp-tickets-wrap" class="fp-tickets-wrap" dir="rtl">
			<div class="fp-tickets-inner">

				<header class="fp-tickets-header">
					<div class="fp-tickets-coach-info">
						<div class="fp-tickets-avatar" aria-hidden="true">🏋️</div>
						<div>
							<p class="fp-tickets-coach-name"><?php echo esc_html( $coach['name'] ); ?></p>
							<p class="fp-tickets-subtitle"><?php esc_html_e( 'مربی شما', 'fitnesspro' ); ?></p>
						</div>
					</div>
				</header>

				<div class="fp-thread" id="fp-thread"
				     role="log" aria-live="polite"
				     aria-label="<?php esc_attr_e( 'مکالمه با مربی', 'fitnesspro' ); ?>"
				     data-last-id="<?php echo $last_id; ?>">

					<?php if ( empty( $messages ) ) : ?>
					<div class="fp-thread-empty" id="fp-thread-empty">
						<span aria-hidden="true">💬</span>
						<p><?php esc_html_e( 'هنوز پیامی ارسال نشده. اولین پیام را ارسال کنید.', 'fitnesspro' ); ?></p>
					</div>
					<?php else : ?>
					<?php foreach ( $messages as $msg ) :
						$is_me = (int) $msg['sender_id'] === $user_id;
					?>
					<?php echo $this->msg_html( $msg, $is_me ); ?>
					<?php endforeach; ?>
					<?php endif; ?>

				</div><!-- .fp-thread -->

				<form class="fp-msg-form" id="fp-msg-form" novalidate enctype="multipart/form-data">
					<div class="fp-msg-input-area">
						<label class="fp-attach-btn"
						       for="fp-file-input"
						       title="<?php esc_attr_e( 'پیوست تصویر', 'fitnesspro' ); ?>"
						       aria-label="<?php esc_attr_e( 'پیوست تصویر', 'fitnesspro' ); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
							<input type="file" id="fp-file-input" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
						</label>
						<div class="fp-attach-preview" id="fp-attach-preview" hidden>
							<img id="fp-preview-img" src="" alt="">
							<button type="button" class="fp-attach-remove" id="fp-attach-remove"
							        aria-label="<?php esc_attr_e( 'حذف پیوست', 'fitnesspro' ); ?>">&#x2715;</button>
						</div>
						<textarea class="fp-msg-textarea" id="fp-msg-textarea"
						          placeholder="<?php esc_attr_e( 'پیام خود را بنویسید...', 'fitnesspro' ); ?>"
						          rows="1" maxlength="2000"
						          aria-label="<?php esc_attr_e( 'متن پیام', 'fitnesspro' ); ?>"></textarea>
						<button type="submit" class="fp-send-btn" id="fp-send-btn"
						        aria-label="<?php esc_attr_e( 'ارسال پیام', 'fitnesspro' ); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
						</button>
					</div>
				</form>

			</div><!-- .fp-tickets-inner -->
		</div><!-- #fp-tickets-wrap -->
		<?php
		return ob_get_clean();
	}

	// ─── AJAX: Send Message ───────────────────────────────────────────────────

	public function ajax_send_ticket(): void {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in' ), 401 );
		}

		$user_id  = get_current_user_id();
		$message  = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		$has_file = ! empty( $_FILES['attachment']['name'] );

		if ( '' === $message && ! $has_file ) {
			wp_send_json_error( array( 'message' => __( 'پیام یا پیوست ضروری است.', 'fitnesspro' ) ) );
		}

		$coach = $this->get_user_coach( $user_id );
		if ( ! $coach ) {
			wp_send_json_error( array( 'message' => __( 'مربی یافت نشد.', 'fitnesspro' ) ) );
		}

		$attachment_url = '';
		if ( $has_file ) {
			$attachment_url = FitnessPro_Tickets::handle_upload( $_FILES['attachment'] );
			if ( '' === $attachment_url ) {
				wp_send_json_error( array( 'message' => __( 'خطا در بارگذاری فایل. فرمت یا اندازه غیرمجاز.', 'fitnesspro' ) ) );
			}
		}

		$id = FitnessPro_Tickets::send_message( $user_id, $coach['id'], $message, $attachment_url );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'خطا در ارسال پیام.', 'fitnesspro' ) ) );
		}

		self::push_notification_to(
			$coach['id'],
			'new_message',
			sprintf(
				/* translators: %s: sender display name */
				__( 'پیام جدید از %s', 'fitnesspro' ),
				wp_get_current_user()->display_name
			)
		);

		wp_send_json_success( array(
			'id'             => $id,
			'sender_id'      => $user_id,
			'message'        => $message,
			'attachment_url' => $attachment_url,
			'created_at'     => current_time( 'mysql', true ),
		) );
	}

	// ─── AJAX: Poll New Messages ──────────────────────────────────────────────

	public function ajax_get_tickets(): void {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not logged in' ), 401 );
		}

		$user_id  = get_current_user_id();
		$since_id = absint( $_POST['since_id'] ?? 0 );
		$coach    = $this->get_user_coach( $user_id );

		if ( ! $coach ) {
			wp_send_json_success( array( 'messages' => array() ) );
			return;
		}

		global $wpdb;
		$t = $wpdb->prefix . 'fitness_tickets';

		$messages = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$t}
				 WHERE id > %d
				   AND ((sender_id = %d AND receiver_id = %d)
				        OR (sender_id = %d AND receiver_id = %d))
				 ORDER BY created_at ASC",
				$since_id,
				$user_id, $coach['id'],
				$coach['id'], $user_id
			),
			ARRAY_A
		);

		wp_send_json_success( array( 'messages' => $messages ) );
	}

	// ─── AJAX: Notification Bell ──────────────────────────────────────────────

	public function ajax_get_notifications(): void {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( null, 401 );
		}

		$user_id = get_current_user_id();
		$raw     = get_user_meta( $user_id, 'fp_notifications', true );
		$list    = $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $list ) ) {
			$list = array();
		}

		$list   = array_reverse( $list );
		$unread = 0;
		$items  = array();
		foreach ( $list as $n ) {
			if ( ! ( $n['read'] ?? false ) ) {
				$unread++;
			}
			$items[] = $n;
			if ( count( $items ) >= 20 ) {
				break;
			}
		}

		wp_send_json_success( array( 'unread' => $unread, 'items' => $items ) );
	}

	public function ajax_mark_notifications_read(): void {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( null, 401 );
		}

		$user_id = get_current_user_id();
		$raw     = get_user_meta( $user_id, 'fp_notifications', true );
		$list    = $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		foreach ( $list as &$n ) {
			$n['read'] = true;
		}
		unset( $n );
		update_user_meta( $user_id, 'fp_notifications', wp_json_encode( $list ) );
		wp_send_json_success( array( 'ok' => true ) );
	}

	// ─── Private / Static Helpers ─────────────────────────────────────────────

	/**
	 * Build the inline config block injected before tickets.js on any page
	 * that contains the ticket shortcode.  Pages that only contain the
	 * dashboard shortcode get a minimal config (ajax_url + nonce + user_id)
	 * injected by FitnessPro_User_Dashboard::render_dashboard() instead.
	 */
	private function inject_config( int $user_id, array $coach ): void {
		wp_add_inline_script(
			'fitnesspro-tickets',
			'window.fp_tickets_config = ' . wp_json_encode( array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_KEY ),
				'user_id'  => $user_id,
				'coach_id' => $coach['id'],
				'poll_ms'  => 15000,
				'strings'  => array(
					'sending'    => __( 'در حال ارسال...', 'fitnesspro' ),
					'error'      => __( 'خطا در ارسال. لطفاً دوباره تلاش کنید.', 'fitnesspro' ),
					'file_error' => __( 'فقط تصاویر (JPG, PNG, GIF, WebP) مجاز هستند. حداکثر ۵ مگابایت.', 'fitnesspro' ),
				),
			) ) . ';',
			'before'
		);
	}

	private function msg_html( array $msg, bool $is_me ): string {
		$side = $is_me ? 'fp-msg--me' : 'fp-msg--them';
		$time = $this->format_time( (string) ( $msg['created_at'] ?? '' ) );
		$html = '<div class="fp-msg ' . $side . '" data-id="' . (int) $msg['id'] . '">';
		$html .= '<div class="fp-msg-bubble">';
		if ( ! empty( $msg['message'] ) ) {
			$html .= '<p class="fp-msg-text">' . nl2br( esc_html( $msg['message'] ) ) . '</p>';
		}
		if ( ! empty( $msg['attachment_url'] ) ) {
			$url   = esc_url( $msg['attachment_url'] );
			$html .= '<a href="' . $url . '" target="_blank" rel="noopener" class="fp-msg-attachment">'
			       . '<img src="' . $url . '" alt="' . esc_attr__( 'پیوست', 'fitnesspro' ) . '" loading="lazy">'
			       . '</a>';
		}
		$html .= '<time class="fp-msg-time" datetime="' . esc_attr( $msg['created_at'] ?? '' ) . '">'
		       . esc_html( $time )
		       . '</time>';
		$html .= '</div></div>';
		return $html;
	}

	private function render_state( string $icon, string $title, string $desc = '', string $btn_url = '', string $btn_label = '' ): string {
		ob_start();
		?>
		<div class="fp-dashboard fp-dashboard--state" dir="rtl">
			<div class="fp-state-icon" aria-hidden="true"><?php echo $icon; ?></div>
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( $desc ) : ?>
			<p><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
			<?php if ( $btn_url && $btn_label ) : ?>
			<a href="<?php echo esc_url( $btn_url ); ?>" class="fp-start-btn">
				<?php echo esc_html( $btn_label ); ?>
			</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Return the coach assigned to a user (most recent active/pending plan).
	 */
	private function get_user_coach( int $user_id ): ?array {
		global $wpdb;
		$coach_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT coach_id FROM {$wpdb->prefix}fitness_user_plans
			 WHERE user_id = %d AND status IN ('active','pending') AND coach_id > 0
			 ORDER BY created_at DESC LIMIT 1",
			$user_id
		) );
		if ( ! $coach_id ) {
			return null;
		}
		$coach = get_userdata( $coach_id );
		if ( ! $coach ) {
			return null;
		}
		return array( 'id' => $coach_id, 'name' => $coach->display_name );
	}

	/**
	 * Append a notification to any WP user's fp_notifications meta array.
	 * Public static so FitnessPro_Coach_Panel can call it too.
	 */
	public static function push_notification_to( int $user_id, string $type, string $message ): void {
		$raw  = get_user_meta( $user_id, 'fp_notifications', true );
		$list = $raw ? json_decode( $raw, true ) : array();
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		$list[] = array(
			'type'       => $type,
			'message'    => $message,
			'created_at' => current_time( 'mysql', true ),
			'read'       => false,
		);
		update_user_meta( $user_id, 'fp_notifications', wp_json_encode( $list ) );
	}

	private function format_time( string $datetime ): string {
		$ts = strtotime( $datetime );
		if ( ! $ts ) {
			return '';
		}
		$diff = time() - $ts;
		if ( $diff < 60 ) {
			return __( 'همین الان', 'fitnesspro' );
		}
		if ( $diff < 3600 ) {
			/* translators: %d: minutes ago */
			return sprintf( __( '%d دقیقه پیش', 'fitnesspro' ), (int) ( $diff / 60 ) );
		}
		if ( $diff < 86400 ) {
			/* translators: %d: hours ago */
			return sprintf( __( '%d ساعت پیش', 'fitnesspro' ), (int) ( $diff / 3600 ) );
		}
		return (string) wp_date( 'Y/m/d H:i', $ts );
	}
}
