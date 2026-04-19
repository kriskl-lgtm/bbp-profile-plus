<?php
if ( ! defined( 'ABSPATH' ) ) exit;
final class BBPPP_Loader {
  public static function init() {
    add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 5 );
    add_action( 'init',           array( __CLASS__, 'load_textdomain' ) );
  }
  public static function boot() {
    // Load core classes
    require_once BBPPP_DIR . 'includes/class-bbppp-xprofile.php';
    require_once BBPPP_DIR . 'includes/class-bbppp-account.php';
    require_once BBPPP_DIR . 'includes/class-bbppp-router.php';
    require_once BBPPP_DIR . 'includes/class-bbppp-antispam.php';
    // Boot singletons
    BBPPP_XProfile::instance();
    BBPPP_Account::instance();
    BBPPP_Router::instance();
    BBPPP_AntiSpam::instance();
    // Enqueue assets
    add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
      		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_login' );
  }

  	public static function enqueue_login() {;
		wp_enqueue_style(
			'bbppp-profile-login',
			BBPPP_ASSETS_URL . 'css/bbppp-profile-login.css',
			array(),
			BBPPP_VERSION
		);
		wp_enqueue_script(
			'bbppp-profile-login',
			BBPPP_ASSETS_URL . 'js/bbppp-profile-login.js',
			array( 'jquery' ),
			BBPPP_VERSION,
			true
		);
	}
  public static function load_textdomain() {
    load_plugin_textdomain( 'bbp-profile-plus', false, dirname( plugin_basename( BBPPP_FILE ) ) . '/languages' );
  }
  public static function enqueue() {
    if ( ! BBPPP_Router::instance()->is_bbppp_page() ) return;
    wp_enqueue_style(
      'bbppp-profile',
      BBPPP_ASSETS_URL . 'css/bbppp-profile.css',
      array(),
      BBPPP_VERSION
    );
    wp_enqueue_script(
      'bbppp-profile',
      BBPPP_ASSETS_URL . 'js/bbppp-profile.js',
      array( 'jquery' ),
      BBPPP_VERSION,
      true
    );
    $user_id = get_current_user_id();
    wp_localize_script( 'bbppp-profile', 'bbpppL10n', array(
      'ajaxurl'           => admin_url( 'admin-ajax.php' ),
      'homeUrl'           => home_url( '/' ),
      'loginUrl'          => wp_login_url(),
      'defaultAvatar'     => get_avatar_url( $user_id, array( 'size' => 150 ) ),
      'saving'            => __( 'Saving...', 'bbp-profile-plus' ),
      'save'              => __( 'Save Changes', 'bbp-profile-plus' ),
      'error'             => __( 'An error occurred. Please try again.', 'bbp-profile-plus' ),
      'confirmDelete'     => __( 'This will permanently delete your account. Are you absolutely sure?', 'bbp-profile-plus' ),
      'confirmRemoveAvatar'=> __( 'Remove your profile photo?', 'bbp-profile-plus' ),
    ) );
  }
  public static function activate() {
    flush_rewrite_rules();
  }
  public static function deactivate() {
    flush_rewrite_rules();
  }
}
