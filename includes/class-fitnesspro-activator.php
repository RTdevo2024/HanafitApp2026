<?php
defined( 'ABSPATH' ) || exit;

class FitnessPro_Activator {

	public static function activate() {
		FitnessPro_Database::create_tables();
		FitnessPro_Roles::add_roles();
		flush_rewrite_rules();
	}
}
