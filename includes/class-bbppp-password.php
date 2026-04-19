<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BBPPP_Password
 *
 * Adds a user-chosen password (with confirmation) to the registration form
 * instead of auto-generating one. Integrated into the core plugin.
 */
final class BBPPP_Password {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'register_form',        array( $this, 'render_fields' ), 99 );
        add_filter( 'registration_errors',  array( $this, 'validate' ), 20, 3 );
        add_action( 'register_post',        array( $this, 'capture_password' ), 99, 3 );
        add_action( 'added_option',         array( $this, 'on_added_option' ), 10, 2 );
        add_action( 'updated_option',       array( $this, 'on_updated_option' ), 10, 3 );
        add_action( 'user_register',        array( $this, 'apply_chosen_password' ), 5, 1 );
    }

    /**
     * Render password + confirm password fields on the WP registration form.
     */
    public function render_fields() {
        ?>
        <p>
            <label for="bbppp_pass1"><?php esc_html_e( 'Password', 'bbp-profile-plus' ); ?><br/>
                <input type="password" name="bbppp_pass1" id="bbppp_pass1" class="input" autocomplete="new-password" spellcheck="false" required minlength="8" size="25" />
            </label>
        </p>
        <p>
            <label for="bbppp_pass2"><?php esc_html_e( 'Confirm password', 'bbp-profile-plus' ); ?><br/>
                <input type="password" name="bbppp_pass2" id="bbppp_pass2" class="input" autocomplete="new-password" spellcheck="false" required minlength="8" size="25" />
            </label>
        </p>
        <p class="description"><?php esc_html_e( 'Choose a password of at least 8 characters. You will use this password to log in once your account is activated.', 'bbp-profile-plus' ); ?></p>
        <?php
    }

    /**
     * Validate password fields during registration.
     */
    public function validate( $errors, $sanitized_user_login, $user_email ) {
        $pass1 = isset( $_POST['bbppp_pass1'] ) ? (string) $_POST['bbppp_pass1'] : '';
        $pass2 = isset( $_POST['bbppp_pass2'] ) ? (string) $_POST['bbppp_pass2'] : '';

        if ( '' === $pass1 || '' === $pass2 ) {
            $errors->add( 'bbppp_pass_empty', __( '<strong>Error:</strong> Please enter a password and confirm it.', 'bbp-profile-plus' ) );
            return $errors;
        }
        if ( strlen( $pass1 ) < 8 ) {
            $errors->add( 'bbppp_pass_short', __( '<strong>Error:</strong> Password must be at least 8 characters.', 'bbp-profile-plus' ) );
        }
        if ( $pass1 !== $pass2 ) {
            $errors->add( 'bbppp_pass_mismatch', __( '<strong>Error:</strong> The two passwords do not match.', 'bbp-profile-plus' ) );
        }
        return $errors;
    }

    /**
     * Stash the chosen password (hashed) in a short-lived transient keyed by login+email.
     */
    public function capture_password( $sanitized_user_login, $user_email, $errors ) {
        if ( $errors->has_errors() ) return;
        $pass1 = isset( $_POST['bbppp_pass1'] ) ? (string) $_POST['bbppp_pass1'] : '';
        if ( '' === $pass1 ) return;
        set_transient(
            'bbppp_pwd_' . md5( $sanitized_user_login . '|' . $user_email ),
            wp_hash_password( $pass1 ),
            HOUR_IN_SECONDS
        );
    }

    public function on_added_option( $option, $value ) {
        if ( 'bbppp_pending_activations' !== $option ) return;
        $this->merge_into_pending( $value );
    }

    public function on_updated_option( $option, $old_value, $value ) {
        if ( 'bbppp_pending_activations' !== $option ) return;
        $this->merge_into_pending( $value );
    }

    /**
     * Merge the stashed hashed password into the pending activations option.
     */
    private function merge_into_pending( $value ) {
        if ( ! is_array( $value ) ) return;
        $changed = false;
        foreach ( $value as $key => $entry ) {
            if ( ! is_array( $entry ) ) continue;
            if ( ! empty( $entry['bbppp_hashed_pass'] ) ) continue;
            $login = isset( $entry['user_login'] ) ? $entry['user_login'] : '';
            $email = isset( $entry['user_email'] ) ? $entry['user_email'] : '';
            if ( '' === $login || '' === $email ) continue;
            $tkey   = 'bbppp_pwd_' . md5( $login . '|' . $email );
            $hashed = get_transient( $tkey );
            if ( $hashed ) {
                $value[ $key ]['bbppp_hashed_pass'] = $hashed;
                delete_transient( $tkey );
                $changed = true;
            }
        }
        if ( $changed ) {
            remove_action( 'updated_option', array( $this, 'on_updated_option' ), 10 );
            remove_action( 'added_option',   array( $this, 'on_added_option' ), 10 );
            update_option( 'bbppp_pending_activations', $value, false );
            add_action( 'updated_option', array( $this, 'on_updated_option' ), 10, 3 );
            add_action( 'added_option',   array( $this, 'on_added_option' ), 10, 2 );
        }
    }

    /**
     * When the user is actually created (after clicking the activation link),
     * replace the auto-generated password with the one they chose.
     */
    public function apply_chosen_password( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;
        $pending = get_option( 'bbppp_pending_activations', array() );
        if ( ! is_array( $pending ) || empty( $pending ) ) return;
        foreach ( $pending as $entry ) {
            if ( ! is_array( $entry ) ) continue;
            if ( empty( $entry['bbppp_hashed_pass'] ) ) continue;
            $login = isset( $entry['user_login'] ) ? $entry['user_login'] : '';
            $email = isset( $entry['user_email'] ) ? $entry['user_email'] : '';
            if ( $login === $user->user_login || $email === $user->user_email ) {
                global $wpdb;
                $wpdb->update( $wpdb->users, array( 'user_pass' => $entry['bbppp_hashed_pass'] ), array( 'ID' => $user_id ) );
                clean_user_cache( $user_id );
                wp_cache_delete( $user_id, 'users' );
                wp_cache_delete( $user->user_login, 'userlogins' );
                return;
            }
        }
    }
}
