<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://twitter.com/xrpfactchecker
 * @since             0.1.0
 * @package           Xummlms
 *
 * @wordpress-plugin
 * Plugin Name:       XUMM LMS
 * Plugin URI:        https://xummlms.xrplstatus.com/
 * Description:       This plugin is an extension of the XUMM Login plugin that is required to run. XUMM LMS extends Sensei LMS (also required) to reward users with XRPL tokens.
 * Version:           1.1.0
 * Author:            XRP Fact Checker
 * Author URI:        https://twitter.com/xrpfactchecker
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       xummlms
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Default fee for the XRPL transaction if none are provided in the settings
 */
defined('DEFAULT_FEE_TX') or define('DEFAULT_FEE_TX', '12');

/**
 * Currently plugin version.
 * Start at version 0.1.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'XUMMLMS_VERSION', '1.1.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-xummlms-activator.php
 */
function activate_xummlms() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-xummlms-activator.php';
	Xummlms_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-xummlms-deactivator.php
 */
function deactivate_xummlms() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-xummlms-deactivator.php';
	Xummlms_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_xummlms' );
register_deactivation_hook( __FILE__, 'deactivate_xummlms' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-xummlms.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.1.0
 */
function run_xummlms() {

	$plugin = new Xummlms();
	$plugin->run();

}
run_xummlms();
