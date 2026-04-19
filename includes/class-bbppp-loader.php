<?php
if ( ! defined( 'ABSPATH' ) ) exit;

final class BBPPP_Loader {

    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 5 );
        add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
    }

    public static function boot() {
        require_once BBPPP_DIR . 'includes/class-bbppp-xprofile.php';
        require_once BBPPP_DIR . 'includes/class-bbppp-account.php';
        require_once BBPPP_DIR . 'includes/class-bbppp-router.php';
        require_once BBPPP_DIR . 'includes/class-bbppp-antispam.php';
        require_once BBPPP_DIR . 'includes/class-bbppp-activation.php';
        require_once BBPPP_DIR . 'includes/class-bbppp-password.php';
        require_once BBPPP_DIR . 'includes/class-bbppp-registration.php';

        BBPPP_XProfile::instance();
        BBPPP_Account::instance();
        BBPPP_Router::instance();
        BBPPP_AntiSpam::instance();
        BBPPP_Password::instance();
        BBPPP_Activation::instance();
        BBPPP_Registration::instance();

        add_action( 'wp_enqueue_scripts',    array( __CLASS__, 'enqueue' ) );
        add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_login' ) );

        add_action( 'login_form',    array( 'BBPPP_Activation', 'show_messages' ) );
        add_action( 'register_form', array( 'BBPPP_Activation', 'show_messages' ) );
    }

    public static function enqueue_login() {
        wp_enqueue_style( 'bbppp-profile-login', BBPPP_ASSETS_URL . 'css/bbppp-profile-login.css', array(), BBPPP_VERSION );
        wp_enqueue_script( 'bbppp-profile-login', BBPPP_ASSETS_URL . 'js/bbppp-profile-login.js', array( 'jquery' ), BBPPP_VERSION, true );
    }

    public static function load_textdomain() {
        load_plugin_textdomain( 'bbp-profile-plus', false, dirname( plugin_basename( BBPPP_DIR . 'bbp-profile-plus.php' ) ) . '/languages/' );
    }

    public static function enqueue() {
        $router = BBPPP_Router::instance();

        // Registration page assets
        if ( $router->is_register_page() ) {
            wp_enqueue_style( 'dashicons' );
            wp_enqueue_style( 'bbppp-register', BBPPP_ASSETS_URL . 'css/bbppp-register.css', array(), BBPPP_VERSION );
            wp_enqueue_script( 'bbppp-register', BBPPP_ASSETS_URL . 'js/bbppp-register.js', array( 'jquery' ), BBPPP_VERSION, true );
            wp_localize_script( 'bbppp-register', 'bbpppReg', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'bbppp_register_nonce' ),
                'i18n'    => array(
                    'usernameRequired' => __( 'Please enter a username.', 'bbp-profile-plus' ),
                    'emailRequired'    => __( 'Please enter your email address.', 'bbp-profile-plus' ),
                    'emailMismatch'    => __( 'Email addresses do not match.', 'bbp-profile-plus' ),
                    'passwordRequired' => __( 'Please enter a password.', 'bbp-profile-plus' ),
                    'passwordShort'    => __( 'Password must be at least 8 characters.', 'bbp-profile-plus' ),
                    'passwordMismatch' => __( 'Passwords do not match.', 'bbp-profile-plus' ),
                    'captchaRequired'  => __( 'Please answer the spam-check question.', 'bbp-profile-plus' ),
                    'submitting'       => __( 'Creating account...', 'bbp-profile-plus' ),
                    'serverError'      => __( 'An error occurred. Please try again.', 'bbp-profile-plus' ),
                ),
            ) );
            return;
        }

        // Profile/account page assets
        if ( ! $router->is_bbppp_page() ) return;

        wp_enqueue_style( 'bbppp-profile', BBPPP_ASSETS_URL . 'css/bbppp-profile.css', array(), BBPPP_VERSION );
        wp_enqueue_script( 'bbppp-profile', BBPPP_ASSETS_URL . 'js/bbppp-profile.js', array( 'jquery' ), BBPPP_VERSION, true );

        $user_id = get_current_user_id();
        wp_localize_script( 'bbppp-profile', 'bbpppL10n', array(
            'ajaxurl'              => admin_url( 'admin-ajax.php' ),
            'homeUrl'              => home_url( '/' ),
            'loginUrl'             => wp_login_url(),
            'defaultAvatar'        => get_avatar_url( $user_id, array( 'size' => 150 ) ),
            'saving'               => __( 'Saving...', 'bbp-profile-plus' ),
            'save'                 => __( 'Save Changes', 'bbp-profile-plus' ),
            'error'                => __( 'An error occurred. Please try again.', 'bbp-profile-plus' ),
            'confirmDelete'        => __( 'This will permanently delete your account. Are you absolutely sure?', 'bbp-profile-plus' ),
            'confirmRemoveAvatar'  => __( 'Remove your profile photo?', 'bbp-profile-plus' ),
        ) );
    }

    public static function activate() {
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}
