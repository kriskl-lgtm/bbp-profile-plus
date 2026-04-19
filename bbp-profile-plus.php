<?php
/**
 * Plugin Name:  bbPress Profile Plus
 * Plugin URI:   https://opentuition.com
 * Description:  Replaces BuddyPress Extended Profiles and Account Settings for bbPress. Uses existing BuddyPress xProfile tables. No BuddyPress runtime needed.
 * Version:      1.0.0
 * Author:       OpenTuition
 * Text Domain:  bbppp
 * Requires PHP: 8.0 * Requires at least: 6.0
 */

// Check PHP version
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="error"><p><strong>BBPress Profile Plus</strong> requires PHP 8.0 or higher. You are running PHP ' . PHP_VERSION . '.</p></div>';
	} );
	return;
}
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

// Fix broken registration URLs
add_filter( 'register_url', 'bbppp_fix_register_url' );
function bbppp_fix_register_url( $url ) {
	// If the URL points to a non-existent page, redirect to WordPress default registration
	if ( strpos( $url, '?p=' ) !== false || strpos( $url, 'create-your-account' ) !== false ) {
		return wp_registration_url();
	}
	return $url;
}
