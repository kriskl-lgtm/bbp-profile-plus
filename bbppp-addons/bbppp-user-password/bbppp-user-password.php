<?php
/**
 * Plugin Name: bbPress Profile Plus - User Password Add-on
 * Description: Companion add-on for bbPress Profile Plus. Adds a user-chosen password (with confirmation) to the registration form instead of auto-generating one. Keeps the core plugin untouched.
 * Version: 1.0.0
 * Author: OpenTuition
 * License: GPL-2.0-or-later
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'BBPPP_UPW_VERSION' ) ) {
    define( 'BBPPP_UPW_VERSION', '1.0.0' );
}

/**
 * Render password + confirm password fields on the WP registration form.
 */
add_action( 'register_form', 'bbppp_upw_render_fields', 99 );
function bbppp_upw_render_fields() {
    ?>
    <p>
        <label for="bbppp_upw_pass1"><?php esc_html_e( 'Password', 'bbppp-upw' ); ?><br/>
            <input type="password" name="bbppp_upw_pass1" id="bbppp_upw_pass1" class="input" autocomplete="new-password" spellcheck="false" required minlength="8" size="25" />
        </label>
    </p>
    <p>
        <label for="bbppp_upw_pass2"><?php esc_html_e( 'Confirm password', 'bbppp-upw' ); ?><br/>
            <input type="password" name="bbppp_upw_pass2" id="bbppp_upw_pass2" class="input" autocomplete="new-password" spellcheck="false" required minlength="8" size="25" />
        </label>
    </p>
    <p class="description"><?php esc_html_e( 'Choose a password of at least 8 characters. You will use this password to log in once your account is activated.', 'bbppp-upw' ); ?></p>
    <?php
}

/**
 * Validate password fields during registration.
 */
add_filter( 'registration_errors', 'bbppp_upw_validate', 20, 3 );
function bbppp_upw_validate( $errors, $sanitized_user_login, $user_email ) {
    $pass1 = isset( $_POST['bbppp_upw_pass1'] ) ? (string) $_POST['bbppp_upw_pass1'] : '';
    $pass2 = isset( $_POST['bbppp_upw_pass2'] ) ? (string) $_POST['bbppp_upw_pass2'] : '';

    if ( '' === $pass1 || '' === $pass2 ) {
        $errors->add( 'bbppp_upw_empty', __( '<strong>Error:</strong> Please enter a password and confirm it.', 'bbppp-upw' ) );
        return $errors;
    }
    if ( strlen( $pass1 ) < 8 ) {
        $errors->add( 'bbppp_upw_short', __( '<strong>Error:</strong> Password must be at least 8 characters.', 'bbppp-upw' ) );
    }
    if ( $pass1 !== $pass2 ) {
        $errors->add( 'bbppp_upw_mismatch', __( '<strong>Error:</strong> The two passwords do not match.', 'bbppp-upw' ) );
    }
    return $errors;
}

/**
 * Stash the chosen password (hashed) in a short-lived transient keyed by login+email.
 */
add_action( 'register_post', 'bbppp_upw_capture_password', 99, 3 );
function bbppp_upw_capture_password( $sanitized_user_login, $user_email, $errors ) {
    if ( $errors->has_errors() ) {
        return;
    }
    $pass1 = isset( $_POST['bbppp_upw_pass1'] ) ? (string) $_POST['bbppp_upw_pass1'] : '';
    if ( '' === $pass1 ) {
        return;
    }
    set_transient(
        'bbppp_upw_pwd_' . md5( $sanitized_user_login . '|' . $user_email ),
        wp_hash_password( $pass1 ),
        HOUR_IN_SECONDS
    );
}

/**
 * Merge the stashed hashed password into the core plugin's pending activations option.
 */
add_action( 'updated_option', 'bbppp_upw_merge_into_pending', 10, 3 );
add_action( 'added_option',   'bbppp_upw_merge_into_pending_added', 10, 2 );

function bbppp_upw_merge_into_pending_added( $option, $value ) {
    if ( 'bbppp_pending_activations' !== $option ) return;
    bbppp_upw_merge_into_pending( $option, null, $value );
}

function bbppp_upw_merge_into_pending( $option, $old_value, $value ) {
    if ( 'bbppp_pending_activations' !== $option ) return;
    if ( ! is_array( $value ) ) return;
    $changed = false;
    foreach ( $value as $key => $entry ) {
        if ( ! is_array( $entry ) ) continue;
        if ( ! empty( $entry['bbppp_upw_hashed'] ) ) continue;
        $login = isset( $entry['user_login'] ) ? $entry['user_login'] : '';
        $email = isset( $entry['user_email'] ) ? $entry['user_email'] : '';
        if ( '' === $login || '' === $email ) continue;
        $tkey   = 'bbppp_upw_pwd_' . md5( $login . '|' . $email );
        $hashed = get_transient( $tkey );
        if ( $hashed ) {
            $value[ $key ]['bbppp_upw_hashed'] = $hashed;
            delete_transient( $tkey );
            $changed = true;
        }
    }
    if ( $changed ) {
        remove_action( 'updated_option', 'bbppp_upw_merge_into_pending', 10 );
        remove_action( 'added_option',   'bbppp_upw_merge_into_pending_added', 10 );
        update_option( 'bbppp_pending_activations', $value, false );
        add_action( 'updated_option', 'bbppp_upw_merge_into_pending', 10, 3 );
        add_action( 'added_option',   'bbppp_upw_merge_into_pending_added', 10, 2 );
    }
}

/**
 * When the user is created (after clicking the activation link),
 * replace the auto-generated password with the one they chose.
 */
add_action( 'user_register', 'bbppp_upw_apply_chosen_password', 5, 1 );
function bbppp_upw_apply_chosen_password( $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) return;
    $pending = get_option( 'bbppp_pending_activations', array() );
    if ( ! is_array( $pending ) || empty( $pending ) ) return;
    foreach ( $pending as $entry ) {
        if ( ! is_array( $entry ) ) continue;
        if ( empty( $entry['bbppp_upw_hashed'] ) ) continue;
        $login = isset( $entry['user_login'] ) ? $entry['user_login'] : '';
        $email = isset( $entry['user_email'] ) ? $entry['user_email'] : '';
        if ( $login === $user->user_login || $email === $user->user_email ) {
            global $wpdb;
            $wpdb->update( $wpdb->users, array( 'user_pass' => $entry['bbppp_upw_hashed'] ), array( 'ID' => $user_id ) );
            clean_user_cache( $user_id );
            wp_cache_delete( $user_id, 'users' );
            wp_cache_delete( $user->user_login, 'userlogins' );
            return;
        }
    }
}
