<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class BBPPP_Account {
  private static $instance = null;
  public static function instance() {
    if ( null === self::$instance ) self::$instance = new self();
    return self::$instance;
  }
  private function __construct() {
    add_action( 'wp_ajax_bbppp_save_account',        array( $this, 'ajax_save_account' ) );
    add_action( 'wp_ajax_bbppp_save_password',       array( $this, 'ajax_save_password' ) );
    add_action( 'wp_ajax_bbppp_save_notifications',  array( $this, 'ajax_save_notifications' ) );
    add_action( 'wp_ajax_bbppp_delete_account',      array( $this, 'ajax_delete_account' ) );
    add_action( 'wp_ajax_bbppp_upload_avatar',       array( $this, 'ajax_upload_avatar' ) );
    add_action( 'wp_ajax_bbppp_delete_avatar',       array( $this, 'ajax_delete_avatar' ) );
  }
  public function get_account_tabs() {
    return apply_filters( 'bbppp_account_tabs', array(
      'general'       => array( 'label' => __( 'General',        'bbp-profile-plus' ), 'icon' => 'dashicons-admin-generic' ),
      'password'      => array( 'label' => __( 'Password',       'bbp-profile-plus' ), 'icon' => 'dashicons-lock' ),
      'notifications' => array( 'label' => __( 'Notifications',  'bbp-profile-plus' ), 'icon' => 'dashicons-bell' ),
      'delete'        => array( 'label' => __( 'Delete Account', 'bbp-profile-plus' ), 'icon' => 'dashicons-trash' ),
    ) );
  }
  public function ajax_save_account() {
    check_ajax_referer( 'bbppp_account_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( __( 'Not logged in.', 'bbp-profile-plus' ) );
    $user_id  = get_current_user_id();
    $data     = array();
    $xprofile = BBPPP_XProfile::instance();

    // --- Validate required xProfile fields BEFORE saving anything ---
    $all_fields    = $xprofile->get_all_fields();
    $missing_names = array();
    foreach ( $all_fields as $field ) {
      if ( empty( $field->is_required ) ) continue; // skip non-required
      $field_id = (int) $field->id;
      // Value may come from POST or already stored in DB
      $posted = isset( $_POST['xprofile'][ $field_id ] ) ? $_POST['xprofile'][ $field_id ] : null;
      if ( $posted === null ) {
        // Field not submitted at all - treat as empty
        $missing_names[] = esc_html( $field->name );
        continue;
      }
      // Determine if the submitted value is truly empty
      $is_empty = false;
      if ( is_array( $posted ) ) {
        $filtered = array_filter( array_map( 'trim', $posted ) );
        $is_empty = empty( $filtered );
      } else {
        $is_empty = ( trim( (string) $posted ) === '' );
      }
      if ( $is_empty ) {
        $missing_names[] = esc_html( $field->name );
      }
    }
    if ( ! empty( $missing_names ) ) {
      wp_send_json_error(
        sprintf(
          /* translators: %s = comma-separated list of field names */
          __( 'The following required fields are empty: %s', 'bbp-profile-plus' ),
          implode( ', ', $missing_names )
        )
      );
    }

    // --- Standard WP user fields ---
    if ( ! empty( $_POST['first_name'] ) ) $data['first_name']  = sanitize_text_field( $_POST['first_name'] );
    if ( ! empty( $_POST['last_name'] ) )  $data['last_name']   = sanitize_text_field( $_POST['last_name'] );
    if ( ! empty( $_POST['user_email'] ) ) {
      $email = sanitize_email( $_POST['user_email'] );
      if ( ! is_email( $email ) ) wp_send_json_error( __( 'Invalid email address.', 'bbp-profile-plus' ) );
      $existing = get_user_by( 'email', $email );
      if ( $existing && $existing->ID !== $user_id ) wp_send_json_error( __( 'Email already in use.', 'bbp-profile-plus' ) );
      $data['user_email'] = $email;
    }
    if ( ! empty( $_POST['description'] ) ) $data['description'] = wp_kses_post( $_POST['description'] );
    $data['ID'] = $user_id;
    $result = wp_update_user( $data );
    if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

    // --- Save xProfile fields ---
    if ( ! empty( $_POST['xprofile'] ) && is_array( $_POST['xprofile'] ) ) {
      foreach ( $_POST['xprofile'] as $field_id => $value ) {
        $field = $xprofile->get_field_by_id( (int) $field_id );
        if ( $field ) {
          $clean = $xprofile->sanitize_value( $value, $field->type );
          $xprofile->set_value( (int) $field_id, $user_id, $clean );
        }
      }
    }
    wp_send_json_success( __( 'Settings saved.', 'bbp-profile-plus' ) );
  }
  public function ajax_save_password() {
    check_ajax_referer( 'bbppp_account_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( __( 'Not logged in.', 'bbp-profile-plus' ) );
    $user_id  = get_current_user_id();
    $current  = isset( $_POST['current_password'] )  ? $_POST['current_password']  : '';
    $new_pass = isset( $_POST['new_password'] )      ? $_POST['new_password']      : '';
    $confirm  = isset( $_POST['confirm_password'] )  ? $_POST['confirm_password']  : '';
    if ( empty( $current ) || empty( $new_pass ) || empty( $confirm ) ) wp_send_json_error( __( 'All fields required.', 'bbp-profile-plus' ) );
    if ( $new_pass !== $confirm ) wp_send_json_error( __( 'Passwords do not match.', 'bbp-profile-plus' ) );
    if ( strlen( $new_pass ) < 8 ) wp_send_json_error( __( 'Password must be at least 8 characters.', 'bbp-profile-plus' ) );
    $user = get_user_by( 'id', $user_id );
    if ( ! wp_check_password( $current, $user->user_pass, $user_id ) ) wp_send_json_error( __( 'Current password is incorrect.', 'bbp-profile-plus' ) );
    wp_set_password( $new_pass, $user_id );
    wp_send_json_success( __( 'Password changed. Please log in again.', 'bbp-profile-plus' ) );
  }
  public function ajax_save_notifications() {
    check_ajax_referer( 'bbppp_account_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( __( 'Not logged in.', 'bbp-profile-plus' ) );
    $user_id        = get_current_user_id();
    $notify_replies = isset( $_POST['notify_replies'] ) ? 1 : 0;
    $notify_topics  = isset( $_POST['notify_topics'] )  ? 1 : 0;
    update_user_meta( $user_id, 'bbppp_notify_replies', $notify_replies );
    update_user_meta( $user_id, 'bbppp_notify_topics',  $notify_topics );
    do_action( 'bbppp_save_notifications', $user_id, $_POST );
    wp_send_json_success( __( 'Notification preferences saved.', 'bbp-profile-plus' ) );
  }
  public function ajax_delete_account() {
    check_ajax_referer( 'bbppp_account_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( __( 'Not logged in.', 'bbp-profile-plus' ) );
    $user_id  = get_current_user_id();
    $password = isset( $_POST['confirm_password'] ) ? $_POST['confirm_password'] : '';
    if ( empty( $password ) ) wp_send_json_error( __( 'Password required to delete account.', 'bbp-profile-plus' ) );
    $user = get_user_by( 'id', $user_id );
    if ( ! wp_check_password( $password, $user->user_pass, $user_id ) ) wp_send_json_error( __( 'Incorrect password.', 'bbp-profile-plus' ) );
    if ( user_can( $user_id, 'administrator' ) ) wp_send_json_error( __( 'Administrators cannot delete their own account here.', 'bbp-profile-plus' ) );
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_logout();
    wp_delete_user( $user_id );
    wp_send_json_success( __( 'Account deleted.', 'bbp-profile-plus' ) );
  }
  public function ajax_upload_avatar() {
    check_ajax_referer( 'bbppp_account_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( __( 'Not logged in.', 'bbp-profile-plus' ) );
    if ( empty( $_FILES['avatar'] ) ) wp_send_json_error( __( 'No file uploaded.', 'bbp-profile-plus' ) );
    $user_id = get_current_user_id();
    $allowed = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
    if ( ! in_array( $_FILES['avatar']['type'], $allowed ) ) wp_send_json_error( __( 'Invalid file type.', 'bbp-profile-plus' ) );
    if ( $_FILES['avatar']['size'] > 2 * MB_IN_BYTES ) wp_send_json_error( __( 'File too large. Max 2MB.', 'bbp-profile-plus' ) );
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $upload = wp_handle_upload( $_FILES['avatar'], array( 'test_form' => false ) );
    if ( isset( $upload['error'] ) ) wp_send_json_error( $upload['error'] );
    $old = get_user_meta( $user_id, 'bbppp_avatar_url', true );
    if ( $old ) {
      $old_path = str_replace( wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $old );
      if ( file_exists( $old_path ) ) @unlink( $old_path );
    }
    update_user_meta( $user_id, 'bbppp_avatar_url', esc_url_raw( $upload['url'] ) );
    wp_send_json_success( array( 'url' => esc_url( $upload['url'] ), 'message' => __( 'Avatar updated.', 'bbp-profile-plus' ) ) );
  }
  public function ajax_delete_avatar() {
    check_ajax_referer( 'bbppp_account_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( __( 'Not logged in.', 'bbp-profile-plus' ) );
    $user_id = get_current_user_id();
    $url     = get_user_meta( $user_id, 'bbppp_avatar_url', true );
    if ( $url ) {
      $path = str_replace( wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $url );
      if ( file_exists( $path ) ) @unlink( $path );
      delete_user_meta( $user_id, 'bbppp_avatar_url' );
    }
    wp_send_json_success( __( 'Avatar removed.', 'bbp-profile-plus' ) );
  }
}
