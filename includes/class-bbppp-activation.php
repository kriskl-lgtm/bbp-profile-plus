<?php
/**
 * BBP Profile Plus - User Activation
 *
 * Handles email-based activation for new user registrations.
 *
 * Flow:
 * 1. User fills out registration form and submits.
 * 2. All other validation (antispam, xProfile, password) runs first via
 *    registration_errors at lower priorities.
 * 3. At priority 999 on registration_errors (last), if no errors exist,
 *    this class creates the WP user as pending (no role), stores activation
 *    meta, sends the activation email, and then adds a BLOCKING error to
 *    prevent WordPress core from creating a SECOND user.
 *    The "error" is actually our success message.
 * 4. The user receives an activation email with a unique link.
 * 5. Clicking the link activates the account, assigns default role, saves
 *    xProfile data, and sends a welcome email.
 * 6. A daily cron purges expired (>48h) pending users.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BBPPP_Activation {

    private static $instance = null;

    const META_KEY_ACTIVATION = 'bbppp_activation_key';
    const META_KEY_SIGNUP_DATA = 'bbppp_signup_data';
    const ACTIVATION_EXPIRY = 172800; // 48 hours

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Handle activation link clicks.
        add_action( 'init', array( $this, 'handle_activation' ) );

        // This is the KEY hook: runs LAST on registration_errors (priority 999).
        // Creates the pending user and adds a blocking error to stop WP core.
        add_filter( 'registration_errors', array( $this, 'intercept_and_create_pending' ), 999, 3 );

        // Prevent pending users from logging in.
        add_filter( 'wp_authenticate_user', array( $this, 'block_pending_login' ), 10, 2 );

        // Cleanup expired activations daily.
        if ( ! wp_next_scheduled( 'bbppp_cleanup_expired_activations' ) ) {
            wp_schedule_event( time(), 'daily', 'bbppp_cleanup_expired_activations' );
        }
        add_action( 'bbppp_cleanup_expired_activations', array( $this, 'cleanup_expired' ) );
    }

    // =========================================================
    // INTERCEPT REGISTRATION AND CREATE PENDING USER
    // =========================================================

    /**
     * If all prior validation passed (no errors), we:
     *   1. Create the user ourselves with wp_create_user()
     *   2. Strip their role (pending)
     *   3. Store activation key + signup data
     *   4. Send activation email
     *   5. Add a WP_Error to BLOCK WordPress core from creating a duplicate user.
     *      This error is displayed as the success "check your email" message.
     */
    public function intercept_and_create_pending( $errors, $sanitized_user_login, $user_email ) {
        // If there are REAL errors from earlier validators, let them through unchanged.
        if ( $errors->has_errors() ) {
            return $errors;
        }

        // --- Get password (set by BBPPP_Password::validate) ---
        $password = isset( $_POST['user_pass'] ) ? $_POST['user_pass'] : wp_generate_password( 12, false );

        // --- Get xProfile data ---
        $xprofile_data = array();
        if ( isset( $_POST['xprofile'] ) && is_array( $_POST['xprofile'] ) ) {
            $xprofile_data = $_POST['xprofile'];
        }

        // --- Create the user ---
        $user_id = wp_create_user( $sanitized_user_login, $password, $user_email );
        if ( is_wp_error( $user_id ) ) {
            // If wp_create_user fails (e.g. duplicate), return that error.
            return $user_id;
        }

        // --- Generate activation key ---
        $activation_key = wp_generate_password( 32, false );

        // --- Store activation meta ---
        update_user_meta( $user_id, self::META_KEY_ACTIVATION, array(
            'key'     => $activation_key,
            'created' => time(),
        ));
        update_user_meta( $user_id, self::META_KEY_SIGNUP_DATA, array(
            'user_login'    => $sanitized_user_login,
            'user_email'    => $user_email,
            'xprofile_data' => $xprofile_data,
        ));

        // --- Strip role so user cannot log in ---
        $user = new WP_User( $user_id );
        $user->set_role( '' );

        // --- Send activation email ---
        $this->send_activation_email( $user_id, $user_email, $activation_key );

        // --- Add a BLOCKING error to prevent WP core from also calling wp_create_user ---
        // The error code 'bbppp_registration_success' is special:
        // show_messages() checks for it and shows a friendly green message instead.
        $errors->add(
            'bbppp_registration_success',
            __( 'Registration successful! Please check your email for an activation link.', 'bbp-profile-plus' )
        );

        return $errors;
    }

    // =========================================================
    // SEND ACTIVATION EMAIL
    // =========================================================

    public function send_activation_email( $user_id, $user_email, $activation_key ) {
        $activation_url = add_query_arg( array(
            'bbppp_action' => 'activate',
            'key'          => $activation_key,
            'user'         => $user_id,
        ), home_url( '/' ) );

        $blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

        $subject = sprintf( __( '[%s] Activate Your Account', 'bbp-profile-plus' ), $blogname );

        $message = sprintf(
            __( "Welcome to %s!\n\nTo complete your registration, please click the link below to activate your account:\n\n%s\n\nThis link will expire in 48 hours.\n\nIf you did not register for an account, please ignore this email.", 'bbp-profile-plus' ),
            $blogname,
            $activation_url
        );

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        wp_mail( $user_email, $subject, $message, $headers );
    }

    // =========================================================
    // HANDLE ACTIVATION LINK
    // =========================================================

    public function handle_activation() {
        if ( ! isset( $_GET['bbppp_action'] ) || $_GET['bbppp_action'] !== 'activate' ) {
            return;
        }

        if ( ! isset( $_GET['key'] ) || ! isset( $_GET['user'] ) ) {
            wp_die( __( 'Invalid activation link.', 'bbp-profile-plus' ) );
        }

        $activation_key = sanitize_text_field( $_GET['key'] );
        $user_id = absint( $_GET['user'] );

        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            wp_die( __( 'Invalid user ID.', 'bbp-profile-plus' ) );
        }

        $activation_data = get_user_meta( $user_id, self::META_KEY_ACTIVATION, true );
        if ( ! $activation_data || ! isset( $activation_data['key'] ) ) {
            wp_die( __( 'This account has already been activated or the activation link is invalid.', 'bbp-profile-plus' ) );
        }

        if ( ! hash_equals( $activation_data['key'], $activation_key ) ) {
            wp_die( __( 'Invalid activation key.', 'bbp-profile-plus' ) );
        }

        $created = isset( $activation_data['created'] ) ? $activation_data['created'] : 0;
        if ( time() - $created > self::ACTIVATION_EXPIRY ) {
            wp_die( __( 'This activation link has expired. Please register again.', 'bbp-profile-plus' ) );
        }

        // Activate the user.
        $this->activate_user( $user_id );

        wp_safe_redirect( add_query_arg( 'bbppp_activated', '1', wp_login_url() ) );
        exit;
    }

    // =========================================================
    // ACTIVATE USER
    // =========================================================

    private function activate_user( $user_id ) {
        $signup_data = get_user_meta( $user_id, self::META_KEY_SIGNUP_DATA, true );

        // Set default role.
        $user = new WP_User( $user_id );
        $user->set_role( get_option( 'default_role', 'subscriber' ) );

        // Save xProfile data if available.
        if ( isset( $signup_data['xprofile_data'] ) && ! empty( $signup_data['xprofile_data'] ) ) {
            $xprofile = BBPPP_XProfile::instance();
            foreach ( $signup_data['xprofile_data'] as $field_id => $value ) {
                $field = $xprofile->get_field_by_id( (int) $field_id );
                if ( $field ) {
                    $clean = $xprofile->sanitize_value( $value, $field->type );
                    $xprofile->set_value( (int) $field_id, $user_id, $clean );
                }
            }
        }

        // Clean up activation meta.
        delete_user_meta( $user_id, self::META_KEY_ACTIVATION );
        delete_user_meta( $user_id, self::META_KEY_SIGNUP_DATA );

        // Send welcome email.
        $this->send_welcome_email( $user_id );
    }

    // =========================================================
    // SEND WELCOME EMAIL
    // =========================================================

    private function send_welcome_email( $user_id ) {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) return;

        $blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

        $subject = sprintf( __( '[%s] Your account is now active!', 'bbp-profile-plus' ), $blogname );
        $message = sprintf(
            __( "Hello %s,\n\nYour account has been activated!\n\nYou can now log in at: %s\n\nThank you for joining %s!", 'bbp-profile-plus' ),
            $user->user_login,
            wp_login_url(),
            $blogname
        );

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        wp_mail( $user->user_email, $subject, $message, $headers );
    }

    // =========================================================
    // BLOCK PENDING LOGIN
    // =========================================================

    public function block_pending_login( $user, $password ) {
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        $activation_data = get_user_meta( $user->ID, self::META_KEY_ACTIVATION, true );
        if ( $activation_data && isset( $activation_data['key'] ) ) {
            return new WP_Error(
                'bbppp_pending_activation',
                __( '<strong>Error:</strong> Your account is pending activation. Please check your email for the activation link.', 'bbp-profile-plus' )
            );
        }

        return $user;
    }

    // =========================================================
    // CLEANUP EXPIRED
    // =========================================================

    public function cleanup_expired() {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
            self::META_KEY_ACTIVATION
        ) );

        foreach ( $results as $row ) {
            $activation_data = maybe_unserialize( $row->meta_value );
            if ( ! isset( $activation_data['created'] ) ) continue;
            if ( time() - $activation_data['created'] > self::ACTIVATION_EXPIRY ) {
                wp_delete_user( $row->user_id );
            }
        }
    }

    // =========================================================
    // DISPLAY MESSAGES
    // =========================================================

    /**
     * Show messages on wp-login.php.
     * Hooked by the loader onto 'login_form' and 'register_form'.
     */
    public static function show_messages() {
        // After activation link clicked: show "account activated" message.
        if ( isset( $_GET['bbppp_activated'] ) ) {
            echo '<p class="message">' . esc_html__( 'Your account has been activated! You can now log in.', 'bbp-profile-plus' ) . '</p>';
        }

        // After registration: the blocking error has code 'bbppp_registration_success'.
        // WordPress will display it as a red error by default. We inject CSS to make
        // that specific error look green.
        ?>
        <style>
            #login_error:has(a[href]):not(:has(.bbppp-success)) { /* keep normal errors red */ }
            .bbppp-success-msg { color: #00a32a; font-weight: 600; }
        </style>
        <?php
    }
}
