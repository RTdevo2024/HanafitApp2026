<?php
defined( 'ABSPATH' ) || exit;

class FitnessPro_Deactivator {

	public static function deactivate() {
		// Tables and roles are intentionally preserved on deactivation to prevent data loss.
		// Full removal is handled by uninstall.php.
		flush_rewrite_rules();
	}
}
