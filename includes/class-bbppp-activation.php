<?php
/**
 * BBP Profile Plus - User Activation
 *
 * Handles email activation for new user registrations.
 * - Generates secure activation keys
 * - Sends activation emails
 * - Processes activation links
 * - Stores pending signups in usermeta
 * - Auto-cleanup of expired activation keys (48 hours)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class BBPPP_Activation {

	private static $instance = null;
	const META_KEY_ACTIVATION = 'bbppp_activation_key';
	const META_KEY_SIGNUP_DATA = 'bbppp_signup_data';
	const ACTIVATION_EXPIRY = 172800; // 48 hours in seconds

	public static function instance() {
		if ( null === self::$instance ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		// Handle activation link clicks
		add_action( 'init', array( $this, 'handle_activation' ) );
		
		// Hook into user registration
		add_filter( 'registration_errors', array( $this, 'intercept_registration' ), 999, 3 );
		add_action( 'register_post', array( $this, 'create_pending_user' ), 10, 3 );
		
		// Prevent pending users from logging in
		add_filter( 'wp_authenticate_user', array( $this, 'block_pending_login' ), 10, 2 );
		
		// Cleanup expired activations daily
		if ( ! wp_next_scheduled( 'bbppp_cleanup_expired_activations' ) ) {
			wp_schedule_event( time(), 'daily', 'bbppp_cleanup_expired_activations' );
		}
		add_action( 'bbppp_cleanup_expired_activations', array( $this, 'cleanup_expired' ) );
	}

	// =========================================================
	// CREATE PENDING USER
	// =========================================================
	
	/**
	 * Intercept registration to check if user should be created as pending
	 */
	public function intercept_registration( $errors, $sanitized_user_login, $user_email ) {
		// If there are existing errors, don't proceed
		if ( $errors->has_errors() ) {
			return $errors;
		}
		
		// Store that we want to create a pending user
		set_transient( 'bbppp_creating_pending_user', true, 60 );
		
		return $errors;
	}
	
	/**
	 * Create pending user after WordPress validates everything
	 */
	public function create_pending_user( $sanitized_user_login, $user_email, $errors ) {
		// Check if we should create pending user
		if ( ! get_transient( 'bbppp_creating_pending_user' ) ) {
			return;
		}
		
		delete_transient( 'bbppp_creating_pending_user' );
		
		// If there are errors, don't create user
		if ( $errors->has_errors() ) {
			return;
		}
		
		// Generate activation key
		$activation_key = $this->generate_activation_key();
		
		// Get password from POST
		$password = isset( $_POST['user_pass'] ) ? $_POST['user_pass'] : wp_generate_password( 12, false );
		
		// Get xProfile data from POST
		$xprofile_data = array();
		if ( isset( $_POST['xprofile'] ) && is_array( $_POST['xprofile'] ) ) {
			$xprofile_data = $_POST['xprofile'];
		}
		
		// Create the user immediately but mark as pending
		$user_id = wp_create_user( $sanitized_user_login, $password, $user_email );
		
		if ( is_wp_error( $user_id ) ) {
			return;
		}
		
		// Store activation key and signup data
		update_user_meta( $user_id, self::META_KEY_ACTIVATION, array(
			'key' => $activation_key,
			'created' => time(),
		));
		
		update_user_meta( $user_id, self::META_KEY_SIGNUP_DATA, array(
			'user_login' => $sanitized_user_login,
			'user_email' => $user_email,
			'xprofile_data' => $xprofile_data,
		));
		
		// Block user from logging in (set role to pending)
		wp_update_user( array(
			'ID' => $user_id,
			'role' => '' // No role = can't login
		));
		
		// Send activation email
		$this->send_activation_email( $user_id, $user_email, $activation_key );
		
		// Show success message and prevent default WP login
		add_filter( 'registration_redirect', array( $this, 'registration_redirect' ) );
	}
	
	// =========================================================
	// ACTIVATION KEY GENERATION
	// =========================================================
	
	private function generate_activation_key() {
		return wp_generate_password( 32, false );
	}
	
	// =========================================================
	// SEND ACTIVATION EMAIL
	// =========================================================
	
	public function send_activation_email( $user_id, $user_email, $activation_key ) {
		$activation_url = add_query_arg( array(
			'bbppp_action' => 'activate',
			'key' => $activation_key,
			'user' => $user_id,
		), home_url( '/' ) );
		
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		
		$subject = sprintf(
			__( '[%s] Activate Your Account', 'bbp-profile-plus' ),
			$blogname
		);
		
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
		
		// Verify user exists
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			wp_die( __( 'Invalid user ID.', 'bbp-profile-plus' ) );
		}
		
		// Get stored activation data
		$activation_data = get_user_meta( $user_id, self::META_KEY_ACTIVATION, true );
		
		if ( ! $activation_data || ! isset( $activation_data['key'] ) ) {
			wp_die( __( 'This account has already been activated or the activation link is invalid.', 'bbp-profile-plus' ) );
		}
		
		// Check if key matches
		if ( ! hash_equals( $activation_data['key'], $activation_key ) ) {
			wp_die( __( 'Invalid activation key.', 'bbp-profile-plus' ) );
		}
		
		// Check if expired (48 hours)
		$created = isset( $activation_data['created'] ) ? $activation_data['created'] : 0;
		if ( time() - $created > self::ACTIVATION_EXPIRY ) {
			wp_die( __( 'This activation link has expired. Please register again.', 'bbp-profile-plus' ) );
		}
		
		// Activate user
		$this->activate_user( $user_id );
		
		// Redirect to login page with success message
		wp_safe_redirect( add_query_arg( 'bbppp_activated', '1', wp_login_url() ) );
		exit;
	}
	
	// =========================================================
	// ACTIVATE USER
	// =========================================================
	
	private function activate_user( $user_id ) {
		// Get signup data
		$signup_data = get_user_meta( $user_id, self::META_KEY_SIGNUP_DATA, true );
		
		// Set user role to subscriber (or default role)
		$user = new WP_User( $user_id );
		$user->set_role( get_option( 'default_role', 'subscriber' ) );
		
		// Save xProfile data if available
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
		
		// Delete activation meta
		delete_user_meta( $user_id, self::META_KEY_ACTIVATION );
		delete_user_meta( $user_id, self::META_KEY_SIGNUP_DATA );
		
		// Send welcome email (optional)
		$this->send_welcome_email( $user_id );
	}
	
	// =========================================================
	// SEND WELCOME EMAIL
	// =========================================================
	
	private function send_welcome_email( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) return;
		
		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		
		$subject = sprintf(
			__( '[%s] Your account is now active!', 'bbp-profile-plus' ),
			$blogname
		);
		
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
		
		// Check if user has pending activation
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
	// REGISTRATION REDIRECT
	// =========================================================
	
	public function registration_redirect( $redirect_to ) {
		return add_query_arg( 'bbppp_check_email', '1', wp_registration_url() );
	}
	
	// =========================================================
	// CLEANUP EXPIRED ACTIVATIONS
	// =========================================================
	
	public function cleanup_expired() {
		global $wpdb;
		
		// Get all users with pending activation
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
				self::META_KEY_ACTIVATION
			)
		);
		
		foreach ( $results as $row ) {
			$activation_data = maybe_unserialize( $row->meta_value );
			
			if ( ! isset( $activation_data['created'] ) ) continue;
			
			// If expired, delete the user
			if ( time() - $activation_data['created'] > self::ACTIVATION_EXPIRY ) {
				wp_delete_user( $row->user_id );
			}
		}
	}
	
	// =========================================================
	// DISPLAY MESSAGES
	// =========================================================
	
	public static function show_messages() {
		// Show "check email" message after registration
		if ( isset( $_GET['bbppp_check_email'] ) ) {
			echo '<p class="message bbppp-message">' . esc_html__( 'Registration successful! Please check your email for an activation link.', 'bbp-profile-plus' ) . '</p>';
		}
		
		// Show success message after activation
		if ( isset( $_GET['bbppp_activated'] ) ) {
			echo '<p class="message bbppp-message">' . esc_html__( 'Your account has been activated! You can now log in.', 'bbp-profile-plus' ) . '</p>';
		}
	}
}
