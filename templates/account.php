<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
if ( ! is_user_logged_in() ) { wp_redirect( wp_login_url( bbppp_get_account_url() ) ); exit; }
$account    = BBPPP_Account::instance();
$xprofile   = BBPPP_XProfile::instance();
$user_id    = get_current_user_id();
$user       = get_userdata( $user_id );
$tabs       = $account->get_account_tabs();
$current    = isset( $bbppp_tab ) ? $bbppp_tab : 'general';
$avatar_url = get_user_meta( $user_id, 'bbppp_avatar_url', true );
if ( ! $avatar_url ) $avatar_url = get_avatar_url( $user_id, array( 'size' => 150 ) );
$groups     = $xprofile->get_groups_with_fields();
?>
<div id="bbppp-account" class="bbppp-wrap bbppp-account-wrap" data-nonce="<?php echo esc_attr( wp_create_nonce( 'bbppp_account_nonce' ) ); ?>">
  <div class="bbppp-account-sidebar">
    <div class="bbppp-account-user">
      <img src="<?php echo esc_url( $avatar_url ); ?>" alt="" class="bbppp-avatar" width="60" height="60" />
      <span class="bbppp-account-name"><?php echo esc_html( $user->display_name ); ?></span>
    </div>
    <nav class="bbppp-account-nav">
      <ul>
        <?php foreach ( $tabs as $slug => $tab ) : ?>
          <li class="<?php echo ( $current === $slug ) ? 'current' : ''; ?>">
            <a href="<?php echo esc_url( bbppp_get_account_url( $slug ) ); ?>">
              <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
              <?php echo esc_html( $tab['label'] ); ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
  <div class="bbppp-account-content">
    <div id="bbppp-account-msg" class="bbppp-notice" style="display:none"></div>
    <?php if ( 'general' === $current ) : ?>
      <h2><?php esc_html_e( 'General Settings', 'bbp-profile-plus' ); ?></h2>
      <form id="bbppp-account-form" class="bbppp-form" data-action="bbppp_save_account">
        <!-- Avatar Upload -->
        <div class="bbppp-form-row bbppp-avatar-row">
          <label><?php esc_html_e( 'Profile Photo', 'bbp-profile-plus' ); ?></label>
          <div class="bbppp-avatar-edit">
            <img id="bbppp-avatar-preview" src="<?php echo esc_url( $avatar_url ); ?>" width="100" height="100" alt="" />
            <div class="bbppp-avatar-actions">
              <label for="bbppp-avatar-file" class="bbppp-btn bbppp-btn-secondary"><?php esc_html_e( 'Change Photo', 'bbp-profile-plus' ); ?></label>
              <input type="file" id="bbppp-avatar-file" name="avatar" accept="image/*" style="display:none" />
              <button type="button" id="bbppp-delete-avatar" class="bbppp-btn bbppp-btn-danger bbppp-btn-sm"><?php esc_html_e( 'Remove', 'bbp-profile-plus' ); ?></button>
            </div>
          </div>
        </div>
        <!-- Basic Info -->
        <div class="bbppp-form-row">
          <label for="bbppp-first-name"><?php esc_html_e( 'First Name', 'bbp-profile-plus' ); ?></label>
          <input type="text" id="bbppp-first-name" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>" />
        </div>
        <div class="bbppp-form-row">
          <label for="bbppp-last-name"><?php esc_html_e( 'Last Name', 'bbp-profile-plus' ); ?></label>
          <input type="text" id="bbppp-last-name" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>" />
        </div>
        <div class="bbppp-form-row">
          <label for="bbppp-email"><?php esc_html_e( 'Email Address', 'bbp-profile-plus' ); ?></label>
          <input type="email" id="bbppp-email" name="user_email" value="<?php echo esc_attr( $user->user_email ); ?>" required />
        </div>
        <div class="bbppp-form-row">
          <label for="bbppp-bio"><?php esc_html_e( 'About Me', 'bbp-profile-plus' ); ?></label>
          <textarea id="bbppp-bio" name="description" rows="4"><?php echo esc_textarea( $user->description ); ?></textarea>
        </div>
        <!-- xProfile Fields -->
        <?php foreach ( $groups as $group ) : ?>
          <?php if ( empty( $group->fields ) ) continue; ?>
          <div class="bbppp-form-section">
            <h3 class="bbppp-form-section-title"><?php echo esc_html( $group->name ); ?></h3>
            <?php foreach ( $group->fields as $field ) : ?>
              <div class="bbppp-form-row">
                <label for="bbppp-xf-<?php echo esc_attr( $field->id ); ?>">
                  <?php echo esc_html( $field->name ); ?>
                  <?php if ( ! empty( $field->is_required ) ) echo '<span class="required">*</span>'; ?>
                </label>
                <?php
                $val = $xprofile->get_value( $field->id, $user_id );
                $xprofile->render_field( $field, $val );
                ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <div class="bbppp-form-submit">
          <button type="submit" class="bbppp-btn bbppp-btn-primary"><?php esc_html_e( 'Save Changes', 'bbp-profile-plus' ); ?></button>
        </div>
      </form>
    <?php elseif ( 'password' === $current ) : ?>
      <h2><?php esc_html_e( 'Change Password', 'bbp-profile-plus' ); ?></h2>
      <form id="bbppp-password-form" class="bbppp-form" data-action="bbppp_save_password">
        <div class="bbppp-form-row">
          <label for="bbppp-current-pass"><?php esc_html_e( 'Current Password', 'bbp-profile-plus' ); ?></label>
          <input type="password" id="bbppp-current-pass" name="current_password" autocomplete="current-password" required />
        </div>
        <div class="bbppp-form-row">
          <label for="bbppp-new-pass"><?php esc_html_e( 'New Password', 'bbp-profile-plus' ); ?></label>
          <input type="password" id="bbppp-new-pass" name="new_password" autocomplete="new-password" required />
          <div id="bbppp-pass-strength" class="bbppp-pass-strength"></div>
        </div>
        <div class="bbppp-form-row">
          <label for="bbppp-confirm-pass"><?php esc_html_e( 'Confirm New Password', 'bbp-profile-plus' ); ?></label>
          <input type="password" id="bbppp-confirm-pass" name="confirm_password" autocomplete="new-password" required />
        </div>
        <div class="bbppp-form-submit">
          <button type="submit" class="bbppp-btn bbppp-btn-primary"><?php esc_html_e( 'Update Password', 'bbp-profile-plus' ); ?></button>
        </div>
      </form>
    <?php elseif ( 'notifications' === $current ) : ?>
      <h2><?php esc_html_e( 'Notification Settings', 'bbp-profile-plus' ); ?></h2>
      <form id="bbppp-notifications-form" class="bbppp-form" data-action="bbppp_save_notifications">
        <div class="bbppp-form-row bbppp-form-row-check">
          <label>
            <input type="checkbox" name="notify_replies" value="1" <?php checked( get_user_meta( $user_id, 'bbppp_notify_replies', true ), 1 ); ?> />
            <?php esc_html_e( 'Email me when someone replies to my topics', 'bbp-profile-plus' ); ?>
          </label>
        </div>
        <div class="bbppp-form-row bbppp-form-row-check">
          <label>
            <input type="checkbox" name="notify_topics" value="1" <?php checked( get_user_meta( $user_id, 'bbppp_notify_topics', true ), 1 ); ?> />
            <?php esc_html_e( 'Email me about new topics in forums I follow', 'bbp-profile-plus' ); ?>
          </label>
        </div>
        <?php do_action( 'bbppp_notification_settings', $user_id ); ?>
        <div class="bbppp-form-submit">
          <button type="submit" class="bbppp-btn bbppp-btn-primary"><?php esc_html_e( 'Save Preferences', 'bbp-profile-plus' ); ?></button>
        </div>
      </form>
    <?php elseif ( 'delete' === $current ) : ?>
      <h2><?php esc_html_e( 'Delete Account', 'bbp-profile-plus' ); ?></h2>
      <div class="bbppp-danger-zone">
        <p class="bbppp-danger-desc"><?php esc_html_e( 'Permanently delete your account and all associated data. This cannot be undone.', 'bbp-profile-plus' ); ?></p>
        <form id="bbppp-delete-form" class="bbppp-form" data-action="bbppp_delete_account">
          <div class="bbppp-form-row">
            <label for="bbppp-delete-pass"><?php esc_html_e( 'Enter your password to confirm', 'bbp-profile-plus' ); ?></label>
            <input type="password" id="bbppp-delete-pass" name="confirm_password" required />
          </div>
          <div class="bbppp-form-submit">
            <button type="submit" class="bbppp-btn bbppp-btn-danger" id="bbppp-delete-confirm"><?php esc_html_e( 'Permanently Delete My Account', 'bbp-profile-plus' ); ?></button>
          </div>
        </form>
      </div>
    <?php else : ?>
      <?php do_action( 'bbppp_account_tab_content', $current, $user_id ); ?>
    <?php endif; ?>
  </div>
</div>
