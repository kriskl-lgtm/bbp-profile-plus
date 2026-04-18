<?php
/**
 * Plugin Name:  bbPress Profile Plus
 * Plugin URI:   https://opentuition.com
 * Description:  Replaces BuddyPress Extended Profiles and Account Settings for bbPress. Uses existing BuddyPress xProfile tables. No BuddyPress runtime needed.
 * Version:      1.0.0
 * Author:       OpenTuition
 * Text Domain:  bbppp
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
define( 'BBPPP_VERSION',    '1.0.0' );
define( 'BBPPP_FILE',       __FILE__ );
define( 'BBPPP_DIR',        plugin_dir_path( __FILE__ ) );
define( 'BBPPP_URL',        plugin_dir_url( __FILE__ ) );
define( 'BBPPP_TPL_DIR',    BBPPP_DIR . 'templates/' );
define( 'BBPPP_ASSETS_URL', BBPPP_URL . 'assets/' );
require_once BBPPP_DIR . 'includes/class-bbppp-loader.php';
register_activation_hook( __FILE__,   array( 'BBPPP_Loader', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BBPPP_Loader', 'deactivate' ) );
BBPPP_Loader::init();
