<?php
/**
 * BBP Profile Plus - Registration Template
 *
 * BuddyPress-style front-end registration page.
 * Loaded by the router when visiting /register/
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Redirect logged-in users
if ( is_user_logged_in() ) {
    wp_safe_redirect( bbppp_get_account_url() );
    exit;
}

// Check if registration is open
$registration_open = get_option( 'users_can_register' );

// Get xProfile data
$xprofile = BBPPP_XProfile::instance();
$groups   = $xprofile->get_groups_with_fields();

// Generate captcha
$antispam = BBPPP_AntiSpam::instance();
$captcha  = $antispam->generate_captcha();

get_header();
?>
<main id="bbppp-register-main" class="bbppp-main">
  <div id="bbppp-register" class="bbppp-wrap bbppp-register-wrap">

    <div class="bbppp-register-header">
      <h1><?php esc_html_e( 'Create an Account', 'bbp-profile-plus' ); ?></h1>
      <p class="bbppp-register-subtitle"><?php esc_html_e( 'Join the community and start participating in the forums.', 'bbp-profile-plus' ); ?></p>
    </div>

    <?php if ( ! $registration_open ) : ?>
      <div class="bbppp-notice bbppp-notice-error">
        <p><?php esc_html_e( 'Registration is currently closed.', 'bbp-profile-plus' ); ?></p>
      </div>
    <?php else : ?>

    <!-- Messages container -->
    <div id="bbppp-register-msg" class="bbppp-notice" style="display:none"></div>

    <form id="bbppp-register-form" class="bbppp-form bbppp-register-form" novalidate>

      <!-- ========== SECTION 1: Account Details ========== -->
      <div class="bbppp-form-section">
        <h2 class="bbppp-form-section-title">
          <span class="dashicons dashicons-admin-users"></span>
          <?php esc_html_e( 'Account Details', 'bbp-profile-plus' ); ?>
        </h2>

        <div class="bbppp-form-row">
          <label for="bbppp_reg_username"><?php esc_html_e( 'Username', 'bbp-profile-plus' ); ?> <span class="bbppp-req">*</span></label>
          <input type="text" id="bbppp_reg_username" name="username" class="bbppp-input" required autocomplete="username" placeholder="<?php esc_attr_e( 'Choose a username', 'bbp-profile-plus' ); ?>">
          <p class="bbppp-field-desc"><?php esc_html_e( 'Only letters, numbers, and underscores. Cannot be changed later.', 'bbp-profile-plus' ); ?></p>
        </div>

        <div class="bbppp-form-row">
          <label for="bbppp_reg_password"><?php esc_html_e( 'Password', 'bbp-profile-plus' ); ?> <span class="bbppp-req">*</span></label>
          <input type="password" id="bbppp_reg_password" name="password" class="bbppp-input" required autocomplete="new-password" minlength="8" placeholder="<?php esc_attr_e( 'Minimum 8 characters', 'bbp-profile-plus' ); ?>">
          <div id="bbppp-password-strength" class="bbppp-password-meter"></div>
        </div>

        <div class="bbppp-form-row">
          <label for="bbppp_reg_password2"><?php esc_html_e( 'Confirm Password', 'bbp-profile-plus' ); ?> <span class="bbppp-req">*</span></label>
          <input type="password" id="bbppp_reg_password2" name="confirm_password" class="bbppp-input" required autocomplete="new-password" minlength="8" placeholder="<?php esc_attr_e( 'Re-enter your password', 'bbp-profile-plus' ); ?>">
        </div>

        <div class="bbppp-form-row">
          <label for="bbppp_reg_email"><?php esc_html_e( 'Email Address', 'bbp-profile-plus' ); ?> <span class="bbppp-req">*</span></label>
          <input type="email" id="bbppp_reg_email" name="email" class="bbppp-input" required autocomplete="email" placeholder="<?php esc_attr_e( 'you@example.com', 'bbp-profile-plus' ); ?>">
        </div>

        <div class="bbppp-form-row">
          <label for="bbppp_reg_email2"><?php esc_html_e( 'Confirm Email', 'bbp-profile-plus' ); ?> <span class="bbppp-req">*</span></label>
          <input type="email" id="bbppp_reg_email2" name="confirm_email" class="bbppp-input" required autocomplete="off" placeholder="<?php esc_attr_e( 'Re-enter your email', 'bbp-profile-plus' ); ?>">
        </div>
      </div>

      <!-- ========== SECTION 2: Profile Fields (xProfile) ========== -->
      <?php if ( ! empty( $groups ) ) : ?>
      <?php foreach ( $groups as $group ) : ?>
      <?php if ( empty( $group->fields ) ) continue; ?>
      <div class="bbppp-form-section">
        <h2 class="bbppp-form-section-title">
          <span class="dashicons dashicons-id-alt"></span>
          <?php echo esc_html( $group->name ); ?>
        </h2>

        <?php if ( ! empty( $group->description ) ) : ?>
          <p class="bbppp-section-desc"><?php echo esc_html( $group->description ); ?></p>
        <?php endif; ?>

        <?php foreach ( $group->fields as $field ) :
          // Skip "Last Name" field
          if ( strtolower( trim( $field->name ) ) === 'last name' ) continue;
          $key  = 'xprofile[' . $field->id . ']';
          $id   = 'bbppp_reg_xf_' . $field->id;
          $req  = $field->is_required ? ' required' : '';
          $rlbl = $field->is_required ? ' <span class="bbppp-req">*</span>' : '';
        ?>
        <div class="bbppp-form-row bbppp-xprofile-field bbppp-type-<?php echo esc_attr( $field->type ); ?>">
          <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field->name ); ?><?php echo $rlbl; ?></label>
          <?php
          switch ( $field->type ) {
            case 'textarea':
              echo '<textarea name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" class="bbppp-input bbppp-textarea"' . $req . '></textarea>';
              break;

            case 'selectbox':
              $opts = $xprofile->get_field_options( $field->id );
              echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" class="bbppp-input bbppp-select"' . $req . '>';
              echo '<option value="">' . esc_html__( '-- Select --', 'bbp-profile-plus' ) . '</option>';
              foreach ( $opts as $o ) {
                echo '<option value="' . esc_attr( $o->name ) . '">' . esc_html( $o->name ) . '</option>';
              }
              echo '</select>';
              break;

            case 'multiselectbox':
              $opts = $xprofile->get_field_options( $field->id );
              echo '<select name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $id ) . '" class="bbppp-input bbppp-select" multiple' . $req . '>';
              foreach ( $opts as $o ) {
                echo '<option value="' . esc_attr( $o->name ) . '">' . esc_html( $o->name ) . '</option>';
              }
              echo '</select>';
              break;

            case 'radio':
              $opts = $xprofile->get_field_options( $field->id );
              echo '<div class="bbppp-radio-group">';
              foreach ( $opts as $o ) {
                $oid = $id . '_' . sanitize_html_class( $o->name );
                echo '<label class="bbppp-radio-label"><input type="radio" name="' . esc_attr( $key ) . '" id="' . esc_attr( $oid ) . '" value="' . esc_attr( $o->name ) . '"' . $req . '> ' . esc_html( $o->name ) . '</label>';
              }
              echo '</div>';
              break;

            case 'checkbox':
              $opts = $xprofile->get_field_options( $field->id );
              echo '<div class="bbppp-checkbox-group">';
              foreach ( $opts as $o ) {
                $oid = $id . '_' . sanitize_html_class( $o->name );
                echo '<label class="bbppp-checkbox-label"><input type="checkbox" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $oid ) . '" value="' . esc_attr( $o->name ) . '"> ' . esc_html( $o->name ) . '</label>';
              }
              echo '</div>';
              break;

            case 'datebox':
              echo '<input type="date" name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" class="bbppp-input"' . $req . '>';
              break;

            case 'url':
              echo '<input type="url" name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" class="bbppp-input"' . $req . ' placeholder="https://">';
              break;

            default: // textbox
              echo '<input type="text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" class="bbppp-input"' . $req . '>';
          }
          ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>

      <!-- ========== SECTION 3: Anti-Spam ========== -->
      <div class="bbppp-form-section bbppp-section-antispam">
        <h2 class="bbppp-form-section-title">
          <span class="dashicons dashicons-shield"></span>
          <?php esc_html_e( 'Security Check', 'bbp-profile-plus' ); ?>
        </h2>

        <!-- Honeypot -->
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
          <input type="text" name="bbppp_hp_name" value="" tabindex="-1" autocomplete="off">
        </div>

        <!-- Time gate -->
        <input type="hidden" name="bbppp_form_time" id="bbppp_reg_form_time" value="">

        <!-- Math CAPTCHA -->
        <div class="bbppp-form-row">
          <label for="bbppp_reg_captcha">
            <?php
            printf(
              esc_html__( 'Spam check: what is %s?', 'bbp-profile-plus' ),
              '<strong>' . esc_html( $captcha['question'] ) . '</strong>'
            );
            ?>
            <span class="bbppp-req">*</span>
          </label>
          <input type="number" id="bbppp_reg_captcha" name="bbppp_captcha_answer" class="bbppp-input bbppp-input-short" required autocomplete="off" inputmode="numeric">
          <input type="hidden" name="bbppp_captcha_token" value="<?php echo esc_attr( $captcha['token'] ); ?>">
        </div>
      </div>

      <!-- ========== Submit ========== -->
      <div class="bbppp-form-submit">
        <button type="submit" id="bbppp-register-submit" class="bbppp-btn bbppp-btn-primary">
          <span class="dashicons dashicons-yes-alt"></span>
          <?php esc_html_e( 'Create Account', 'bbp-profile-plus' ); ?>
        </button>
      </div>

      <div class="bbppp-register-footer">
        <p><?php printf(
          esc_html__( 'Already have an account? %s', 'bbp-profile-plus' ),
          '<a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Log In', 'bbp-profile-plus' ) . '</a>'
        ); ?></p>
      </div>

    </form>
    <?php endif; ?>
  </div>
</main>
<?php
get_footer();
