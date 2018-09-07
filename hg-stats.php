<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://iamevan.me
 * @since             1.0.0
 * @package           Hg_Stats
 *
 * @wordpress-plugin
 * Plugin Name:       HostedGraphite Statistics
 * Plugin URI:        https://iamevan.me
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            Evan Smith
 * Author URI:        https://iamevan.me
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       hg-stats
 * Domain Path:       /languages
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require __DIR__ . '/vendor/autoload.php';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'HG_STATS_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-hg-stats-activator.php
 */
function activate_hg_stats() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-hg-stats-activator.php';
	Hg_Stats_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-hg-stats-deactivator.php
 */
function deactivate_hg_stats() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-hg-stats-deactivator.php';
	Hg_Stats_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_hg_stats' );
register_deactivation_hook( __FILE__, 'deactivate_hg_stats' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-hg-stats.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_hg_stats() {

	$plugin = new Hg_Stats();
	$plugin->run();

}
run_hg_stats();
