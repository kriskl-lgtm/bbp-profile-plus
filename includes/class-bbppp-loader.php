<?php
if ( ! defined( 'ABSPATH' ) ) exit;
final class BBPPP_Loader {
    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 5 );
        add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
    }
    public static function boot() {
        foreach ( array(
            'class-bbppp-xprofile.php',
            'class-bbppp-avatar.php',
            'class-bbppp-registration.php',
            'class-bbppp-profile.php',
            'class-bbppp-settings.php',
            'class-bbppp-bbpress.php',
            'class-bbppp-router.php',
        ) as $f ) {
            require_once BBPPP_DIR . 'includes/' . $f;
        }
        require_once BBPPP_DIR . 'includes/admin/class-bbppp-admin.php';
        BBPPP_XProfile::instance();
        BBPPP_Avatar::instance();
        BBPPP_Registration::instance();
        BBPPP_Profile::instance();
        BBPPP_Settings::instance();
        BBPPP_BBPress::instance();
        BBPPP_Router::instance();
        if ( is_admin() ) BBPPP_Admin::instance();
        add_action( 'wp_enqueue_scripts',                  array( __CLASS__, 'enqueue' ) );
        add_action( 'wp_ajax_bbppp_check_username',        array( __CLASS__, 'ajax_check_username' ) );
        add_action( 'wp_ajax_nopriv_bbppp_check_username', array( __CLASS__, 'ajax_check_username' ) );
        add_action( 'wp_ajax_bbppp_upload_avatar',         array( __CLASS__, 'ajax_upload_avatar' ) );
        add_action( 'wp_ajax_bbppp_remove_avatar',         array( __CLASS__, 'ajax_remove_avatar' ) );
    }
    public static function enqueue() {
        wp_register_style( 'bbppp', BBPPP_ASSETS_URL . 'css/bbppp.css', array(), BBPPP_VERSION );
        wp_register_script( 'bbppp', BBPPP_ASSETS_URL . 'js/bbppp.js', array( 'jquery' ), BBPPP_VERSION, true );
        if ( BBPPP_Router::instance()->is_bbppp_page() ) {
            wp_enqueue_style( 'bbppp' );
            wp_enqueue_script( 'bbppp' );
            wp_localize_script( 'bbppp', 'bbppp_vars', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'bbppp_nonce' ),
                'user_id'  => get_current_user_id(),
                'members'  => home_url( '/members/' ),
                'i18n'     => array(
                    'checking'              => __( 'Checking...', 'bbppp' ),
                    'available'             => __( 'Available', 'bbppp' ),
                    'taken'                 => __( 'Already taken', 'bbppp' ),
                    'saving'                => __( 'Saving...', 'bbppp' ),
                    'saved'                 => __( 'Saved', 'bbppp' ),
                    'error'                 => __( 'Error', 'bbppp' ),
                    'remove_avatar_confirm' => __( 'Remove your avatar?', 'bbppp' ),
                ),
            ) );
        }
    }
    public static function ajax_check_username() {
        check_ajax_referer( 'bbppp_nonce', 'nonce' );
        $u = sanitize_user( wp_unslash( isset( $_POST['username'] ) ? $_POST['username'] : '' ) );
        wp_send_json_success( array( 'available' => ( ! username_exists( $u ) && validate_username( $u ) && strlen( $u ) >= 3 ) ) );
    }
    public static function ajax_upload_avatar() {
        check_ajax_referer( 'bbppp_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error();
        BBPPP_Avatar::instance()->handle_ajax_upload( get_current_user_id() );
    }
    public static function ajax_remove_avatar() {
        check_ajax_referer( 'bbppp_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error();
        BBPPP_Avatar::instance()->remove_avatar( get_current_user_id() );
        wp_send_json_success();
    }
    public static function load_textdomain() {
        load_plugin_textdomain( 'bbppp', false, 'bbp-profile-plus/languages' );
    }
    public static function activate() {
        BBPPP_Router::register_rewrite_rules();
        flush_rewrite_rules();
        update_option( 'bbppp_version', BBPPP_VERSION );
    }
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
