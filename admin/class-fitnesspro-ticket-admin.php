<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin ticket monitoring page.
 *
 * Super-admins can browse all coach-user conversation threads and
 * read the full message history for any thread (read-only).
 */
class FitnessPro_Ticket_Admin {

	const NONCE_KEY = 'fp_ticket_admin_nonce';

	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	// ─── Asset Enqueue ────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'fitnesspro-tickets' ) ) {
			return;
		}

		wp_enqueue_style(
			'fitnesspro-tickets',
			FITNESSPRO_PLUGIN_URL . 'assets/css/tickets.css',
			array( 'fitnesspro-admin-rtl' ),
			$this->version
		);

		wp_enqueue_script(
			'fitnesspro-tickets',
			FITNESSPRO_PLUGIN_URL . 'assets/js/tickets.js',
			array(),
			$this->version,
			true
		);

		wp_add_inline_script(
			'fitnesspro-tickets',
			'window.fp_tickets_admin_config = ' . wp_json_encode( array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_KEY ),
				'is_admin' => true,
			) ) . ';',
			'before'
		);
	}

	// ─── Page Renderer ────────────────────────────────────────────────────────

	public function render(): void {
		$threads = FitnessPro_Tickets::get_all_threads();
		?>
		<div class="wrap fitnesspro-wrap fp-ticket-admin-wrap" dir="rtl">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'مانیتورینگ پیام‌ها', 'fitnesspro' ); ?></h1>
			<hr class="wp-header-end">

			<?php if ( empty( $threads ) ) : ?>
			<div class="fp-coach-empty">
				<span class="dashicons dashicons-email-alt" style="font-size:48px;width:48px;height:48px;color:#b0b0b0;"></span>
				<p><?php esc_html_e( 'هنوز هیچ مکالمه‌ای وجود ندارد.', 'fitnesspro' ); ?></p>
			</div>

			<?php else : ?>
			<div class="fp-ticket-admin-layout" id="fp-ticket-admin-layout">

				<?php /* Thread list (left panel) */ ?>
				<div class="fp-ticket-thread-list" id="fp-ticket-thread-list">
					<?php foreach ( $threads as $thread ) : ?>
					<button type="button"
					        class="fp-thread-item"
					        data-user-id="<?php echo (int) $thread['user_id']; ?>"
					        data-coach-id="<?php echo (int) $thread['coach_id']; ?>"
					        data-user-name="<?php echo esc_attr( $thread['user_name'] ); ?>"
					        data-coach-name="<?php echo esc_attr( $thread['coach_name'] ); ?>">
						<div class="fp-thread-item__names">
							<strong><?php echo esc_html( $thread['user_name'] ); ?></strong>
							<span class="fp-thread-item__sep">↔</span>
							<span><?php echo esc_html( $thread['coach_name'] ); ?></span>
						</div>
						<p class="fp-thread-item__preview">
							<?php echo esc_html( mb_strimwidth( $thread['last_message'], 0, 60, '…' ) ); ?>
						</p>
						<div class="fp-thread-item__meta">
							<span><?php printf( esc_html__( '%d پیام', 'fitnesspro' ), (int) $thread['message_count'] ); ?></span>
							<time><?php echo esc_html( $this->format_time( (string) ( $thread['last_at'] ?? '' ) ) ); ?></time>
						</div>
					</button>
					<?php endforeach; ?>
				</div><!-- .fp-ticket-thread-list -->

				<?php /* Thread viewer (right panel) — populated by JS */ ?>
				<div class="fp-ticket-thread-viewer" id="fp-ticket-thread-viewer">
					<div class="fp-ticket-viewer-empty">
						<span aria-hidden="true">💬</span>
						<p><?php esc_html_e( 'یک مکالمه را از لیست انتخاب کنید.', 'fitnesspro' ); ?></p>
					</div>
				</div>

			</div><!-- .fp-ticket-admin-layout -->
			<?php endif; ?>
		</div>
		<?php
	}

	// ─── AJAX: Load Any Thread (admin only) ──────────────────────────────────

	public function ajax_get_thread(): void {
		check_ajax_referer( self::NONCE_KEY, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$user_id  = absint( $_POST['user_id']  ?? 0 );
		$coach_id = absint( $_POST['coach_id'] ?? 0 );

		if ( ! $user_id || ! $coach_id ) {
			wp_send_json_error( array( 'message' => 'Invalid params' ) );
		}

		$messages = FitnessPro_Tickets::get_thread( $user_id, $coach_id );
		wp_send_json_success( array( 'messages' => $messages ) );
	}

	// ─── Private Helpers ─────────────────────────────────────────────────────

	private function format_time( string $datetime ): string {
		$ts = strtotime( $datetime );
		if ( ! $ts ) {
			return '';
		}
		$diff = time() - $ts;
		if ( $diff < 60 )    return __( 'همین الان', 'fitnesspro' );
		if ( $diff < 3600 )  return sprintf( __( '%d دقیقه پیش', 'fitnesspro' ), (int) ( $diff / 60 ) );
		if ( $diff < 86400 ) return sprintf( __( '%d ساعت پیش', 'fitnesspro' ), (int) ( $diff / 3600 ) );
		return (string) wp_date( 'Y/m/d H:i', $ts );
	}
}
