<?php
/**
 * Plugin Name:       FitnessPro System
 * Plugin URI:        https://rtdevo2024.com/fitnesspro
 * Description:       سیستم حرفه‌ای مدیریت برنامه تمرینی و تغذیه با یکپارچه‌سازی ووکامرس و پشتیبانی کامل از RTL
 * Version:           1.0.0
 * Author:            RTdevo2024
 * Text Domain:       fitnesspro
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'FITNESSPRO_VERSION',       '1.0.0' );
define( 'FITNESSPRO_PLUGIN_FILE',   __FILE__ );
define( 'FITNESSPRO_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'FITNESSPRO_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'FITNESSPRO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once FITNESSPRO_PLUGIN_DIR . 'includes/class-fitnesspro-loader.php';
require_once FITNESSPRO_PLUGIN_DIR . 'includes/class-fitnesspro-database.php';
require_once FITNESSPRO_PLUGIN_DIR . 'includes/class-fitnesspro-roles.php';
require_once FITNESSPRO_PLUGIN_DIR . 'includes/class-fitnesspro-cpts.php';
require_once FITNESSPRO_PLUGIN_DIR . 'includes/class-fitnesspro-meta-boxes.php';
require_once FITNESSPRO_PLUGIN_DIR . 'includes/class-fitnesspro-activator.php';
require_once FITNESSPRO_PLUGIN_DIR . 'includes/class-fitnesspro-deactivator.php';
require_once FITNESSPRO_PLUGIN_DIR . 'admin/class-fitnesspro-admin.php';
require_once FITNESSPRO_PLUGIN_DIR . 'public/class-fitnesspro-public.php';
require_once FITNESSPRO_PLUGIN_DIR . 'includes/class-fitnesspro-core.php';

register_activation_hook( __FILE__, array( 'FitnessPro_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FitnessPro_Deactivator', 'deactivate' ) );

function fitnesspro_run() {
	$plugin = new FitnessPro_Core();
	$plugin->run();
}
fitnesspro_run();
