<?php
/**
 * Plugin Name:       Seasonal Color Toolkit
 * Description:       Seasonal color analysis quiz, 12-season palettes, palette explorer and season comparison. Quiz answers never leave the visitor's browser.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Seasonal Color Analysis
 * License:           GPL-2.0-or-later
 * Text Domain:       seasonal-color-toolkit
 *
 * @package SeasonalColorToolkit
 */

defined( 'ABSPATH' ) || exit;

define( 'SCA_VERSION', '0.1.0' );
define( 'SCA_DB_VERSION', '1' );
define( 'SCA_FILE', __FILE__ );
define( 'SCA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCA_URL', plugin_dir_url( __FILE__ ) );

require_once SCA_DIR . 'includes/class-sca-install.php';
require_once SCA_DIR . 'includes/class-sca-repository.php';
require_once SCA_DIR . 'includes/class-sca-post-types.php';
require_once SCA_DIR . 'includes/class-sca-render.php';
require_once SCA_DIR . 'includes/class-sca-shortcodes.php';
require_once SCA_DIR . 'includes/class-sca-assets.php';
require_once SCA_DIR . 'includes/class-sca-starter-content.php';

if ( is_admin() ) {
	require_once SCA_DIR . 'includes/admin/class-sca-admin.php';
	SCA_Admin::init();
}

register_activation_hook( __FILE__, array( 'SCA_Install', 'activate' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'plugins_loaded', array( 'SCA_Install', 'maybe_upgrade' ) );
SCA_Post_Types::init();
SCA_Shortcodes::init();
SCA_Assets::init();
