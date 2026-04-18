<?php
// Only run when WordPress triggers an uninstall — never directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-fitnesspro-database.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-fitnesspro-roles.php';

FitnessPro_Database::drop_tables();
FitnessPro_Roles::remove_roles();
delete_option( 'fitnesspro_db_version' );
