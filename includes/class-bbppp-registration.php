<?php
/**
 * BBP Profile Plus - Custom Registration
 *
 * BuddyPress-style front-end registration form.
 * Replaces wp-login.php?action=register with a themed front-end page.
 *
 * Features:
 * - Full front-end registration at /register/
 * - AJAX form submission with client-side validation
 * - Migrates all existing form fields (username, password, email, confirm email,
 *   xProfile fields, anti-spam captcha)
 * - Pending activation with email verification
 * - Logged-in users redirected to their profile
 * - Fully styled to match the site theme
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class BBPPP_Registration {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // AJAX handler for registration
        add_action( 'wp_ajax_nopriv_bbppp_register', array( $this, 'ajax_register' ) );
        add_action( 'wp_ajax_bbppp_register',        array( $this, 'ajax_register' ) );

        // Redirect wp-login.php?action=register to our front-end form
        add_action( 'login_form_register', array( $this, 'redirect_wp_register' ) );
    }

    /**
     * Redirect wp-login.php?action=register to /register/
     */
    public function redirect_wp_register() {
        if ( is_user_logged_in() ) {
            wp_safe_redirect( bbppp_get_account_url() );
            exit;
        }
        wp_safe_redirect( home_url( '/register/' ) );
        exit;
    }

    /**
     * Get the register page URL
     */
    public static function get_url() {
        return home_url( '/register/' );
    }

    // =========================================================
    // AJAX REGISTRATION HANDLER
    // =========================================================

    public function ajax_register() {
        // Verify nonce
        if ( ! check_ajax_referer( 'bbppp_register_nonce', 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed. Please reload the page.', 'bbp-profile-plus' ) );
        }

        // Already logged in?
        if ( is_user_logged_in() ) {
            wp_send_json_error( __( 'You are already registered and logged in.', 'bbp-profile-plus' ) );
        }

        // Registration disabled?
        if ( ! get_option( 'users_can_register' ) ) {
            wp_send_json_error( __( 'Registration is currently closed.', 'bbp-profile-plus' ) );
        }

        $errors = array();

        // ---- Sanitize inputs ----
        $username      = isset( $_POST['username'] ) ? sanitize_user( $_POST['username'] ) : '';
        $email         = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        $confirm_email = isset( $_POST['confirm_email'] ) ? sanitize_email( $_POST['confirm_email'] ) : '';
        $password      = isset( $_POST['password'] ) ? $_POST['password'] : '';
        $confirm_pass  = isset( $_POST['confirm_password'] ) ? $_POST['confirm_password'] : '';

        // ---- Validate username ----
        if ( empty( $username ) ) {
            $errors[] = __( 'Please enter a username.', 'bbp-profile-plus' );
        } elseif ( ! validate_username( $username ) ) {
            $errors[] = __( 'Invalid username. Please use only letters, numbers, and underscores.', 'bbp-profile-plus' );
        } elseif ( username_exists( $username ) ) {
            $errors[] = __( 'That username is already taken. Please choose another.', 'bbp-profile-plus' );
        }

        // ---- Validate email ----
        if ( empty( $email ) ) {
            $errors[] = __( 'Please enter your email address.', 'bbp-profile-plus' );
        } elseif ( ! is_email( $email ) ) {
            $errors[] = __( 'Please enter a valid email address.', 'bbp-profile-plus' );
        } elseif ( email_exists( $email ) ) {
            $errors[] = __( 'That email is already registered.', 'bbp-profile-plus' );
        }

        if ( $email !== $confirm_email ) {
            $errors[] = __( 'Email addresses do not match.', 'bbp-profile-plus' );
        }

        // ---- Validate password ----
        if ( empty( $password ) ) {
            $errors[] = __( 'Please enter a password.', 'bbp-profile-plus' );
        } elseif ( strlen( $password ) < 8 ) {
            $errors[] = __( 'Password must be at least 8 characters.', 'bbp-profile-plus' );
        }

        if ( $password !== $confirm_pass ) {
            $errors[] = __( 'Passwords do not match.', 'bbp-profile-plus' );
        }

        // ---- Anti-spam checks ----
        $spam_result = $this->check_antispam();
        if ( is_array( $spam_result ) ) {
            $errors = array_merge( $errors, $spam_result );
        }

        // ---- Validate required xProfile fields ----
        $xprofile = BBPPP_XProfile::instance();
        $xprofile_data = isset( $_POST['xprofile'] ) && is_array( $_POST['xprofile'] ) ? $_POST['xprofile'] : array();
        $all_fields = $xprofile->get_all_fields();

        foreach ( $all_fields as $field ) {
            if ( empty( $field->is_required ) ) continue;
            $field_id = (int) $field->id;
            $val = isset( $xprofile_data[ $field_id ] ) ? $xprofile_data[ $field_id ] : '';
            if ( is_array( $val ) ) {
                $val = array_filter( array_map( 'trim', $val ) );
            } else {
                $val = trim( (string) $val );
            }
            if ( empty( $val ) ) {
                $errors[] = sprintf(
                    __( 'The field "%s" is required.', 'bbp-profile-plus' ),
                    esc_html( $field->name )
                );
            }
        }

        // ---- Return errors if any ----
        if ( ! empty( $errors ) ) {
            wp_send_json_error( implode( '<br>', $errors ) );
        }

        // ---- Create the user (pending) ----
        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( $user_id->get_error_message() );
        }

        // ---- Generate activation key ----
        $activation_key = wp_generate_password( 32, false );

        // ---- Store activation meta ----
        update_user_meta( $user_id, BBPPP_Activation::META_KEY_ACTIVATION, array(
            'key'     => $activation_key,
            'created' => time(),
        ));

        update_user_meta( $user_id, BBPPP_Activation::META_KEY_SIGNUP_DATA, array(
            'user_login'    => $username,
            'user_email'    => $email,
            'xprofile_data' => $xprofile_data,
        ));

        // ---- Strip role (pending) ----
        $user = new WP_User( $user_id );
        $user->set_role( '' );

        // ---- Send activation email ----
        $activation = BBPPP_Activation::instance();
        $activation->send_activation_email( $user_id, $email, $activation_key );

        // ---- Save xProfile data immediately (will be available on activation) ----
        foreach ( $xprofile_data as $field_id => $value ) {
            $field = $xprofile->get_field_by_id( (int) $field_id );
            if ( $field ) {
                $clean = $xprofile->sanitize_value( $value, $field->type );
                $xprofile->set_value( (int) $field_id, $user_id, $clean );
            }
        }

        wp_send_json_success( __( 'Registration successful! Please check your email for an activation link.', 'bbp-profile-plus' ) );
    }

    // =========================================================
    // ANTI-SPAM VALIDATION
    // =========================================================

    private function check_antispam() {
        $errors = array();

        // 1. Honeypot
        $hp = isset( $_POST['bbppp_hp_name'] ) ? $_POST['bbppp_hp_name'] : null;
        if ( $hp !== null && $hp !== '' ) {
            $errors[] = __( 'Spam detected.', 'bbp-profile-plus' );
            return $errors;
        }

        // 2. Time gate
        $submitted_at = isset( $_POST['bbppp_form_time'] ) ? (int) $_POST['bbppp_form_time'] : 0;
        $elapsed = time() - $submitted_at;
        if ( $submitted_at === 0 || $elapsed < 11 || $elapsed > 3600 ) {
            $errors[] = __( 'Please take a moment to complete the form properly.', 'bbp-profile-plus' );
            return $errors;
        }

        // 3. Math CAPTCHA
        $token  = isset( $_POST['bbppp_captcha_token'] ) ? sanitize_text_field( $_POST['bbppp_captcha_token'] ) : '';
        $answer = isset( $_POST['bbppp_captcha_answer'] ) ? sanitize_text_field( $_POST['bbppp_captcha_answer'] ) : '';

        if ( empty( $token ) || $answer === '' ) {
            $errors[] = __( 'Please answer the spam-check question.', 'bbp-profile-plus' );
            return $errors;
        }

        $stored_hash = get_transient( 'bbppp_captcha_' . $token );
        if ( false === $stored_hash ) {
            $errors[] = __( 'Spam-check expired. Please reload and try again.', 'bbp-profile-plus' );
            return $errors;
        }

        if ( ! hash_equals( $stored_hash, wp_hash( $answer ) ) ) {
            $errors[] = __( 'Incorrect answer to the spam-check question.', 'bbp-profile-plus' );
            return $errors;
        }

        // Consume token
        delete_transient( 'bbppp_captcha_' . $token );

        return null; // No errors
    }
}
