<?php
defined( 'ABSPATH' ) || exit;

/**
 * Manages custom user roles for the plugin.
 *
 * Roles:
 *   - fitness_coach : Can manage plans; restricted to plugin pages only in wp-admin.
 */
class FitnessPro_Roles {

	const COACH_ROLE = 'fitness_coach';

	public static function add_roles() {
		add_role(
			self::COACH_ROLE,
			__( 'مربی فیتنس', 'fitnesspro' ),
			array(
				'read'       => true,
				'edit_posts' => true,
			)
		);
	}

	public static function remove_roles() {
		remove_role( self::COACH_ROLE );
	}

	/**
	 * Redirects coaches away from unrelated wp-admin pages.
	 * Hooked to admin_init (runs before any output).
	 */
	public function restrict_dashboard_access() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! in_array( self::COACH_ROLE, (array) $user->roles, true ) ) {
			return;
		}

		$pagenow          = isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
		$page_param       = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$is_plugin_page   = ( strpos( $page_param, 'fitnesspro' ) === 0 );
		$allowed_pagenow  = array( 'profile.php', 'admin-post.php', 'admin-ajax.php' );

		if ( ! $is_plugin_page && ! in_array( $pagenow, $allowed_pagenow, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=fitnesspro-coach-dashboard' ) );
			exit;
		}
	}
}
