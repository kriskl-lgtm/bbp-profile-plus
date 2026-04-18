<?php
/**
 * BBP Profile Plus - Anti-Spam
 *
 * Three-layer protection on WordPress registration:
 * 1. Honeypot  - invisible field; bots fill it, humans don't
 * 2. Time gate - form must be open >= 11 seconds before submit
 * 3. Math CAPTCHA - server-side question, answer stored in a WP transient
 *                   (answer is NOT in the HTML, so bots can't scrape it)
 *
 * Also validates on registration:
 * - Username not already taken
 * - Email not already registered
 * - All required xProfile fields present
 *
 * Hooks into:
 *  - register_form / registration_errors (WordPress default registration)
 *  - bbp_new_topic_pre_insert / bbp_new_reply_pre_insert (bbPress)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class BBPPP_AntiSpam {

  private static $instance = null;
  const TRANSIENT_PREFIX   = 'bbppp_captcha_';
  const MIN_SUBMIT_SECONDS = 11;

  public static function instance() {
    if ( null === self::$instance ) self::$instance = new self();
    return self::$instance;
  }

  private function __construct() {
    // ---- Registration form ----
    add_action( 'register_form',        array( $this, 'render_fields' ) );
    add_filter( 'registration_errors',  array( $this, 'validate_registration' ), 10, 3 );

    // ---- bbPress new topic / reply (guests only) ----
    add_action( 'bbp_theme_before_topic_form_submit_wrapper', array( $this, 'render_fields' ) );
    add_action( 'bbp_theme_before_reply_form_submit_wrapper', array( $this, 'render_fields' ) );
    add_filter( 'bbp_new_topic_pre_insert', array( $this, 'validate_bbpress' ) );
    add_filter( 'bbp_new_reply_pre_insert', array( $this, 'validate_bbpress' ) );

    // ---- Inline JS: sets hidden timestamp on page load ----
    add_action( 'wp_footer',    array( $this, 'footer_script' ) );
    add_action( 'login_footer', array( $this, 'footer_script' ) );
  }

  // =========================================================
  // CAPTCHA GENERATION
  // =========================================================

  /**
   * Generate a random math question.
   * Returns [ 'question' => '7 + 4', 'answer' => 11, 'token' => 'abc123' ]
   * Stores hashed answer in a 10-minute transient keyed by token.
   */
  public function generate_captcha() {
    $ops = array( '+', '-', '*' );
    $op  = $ops[ array_rand( $ops ) ];
    switch ( $op ) {
      case '+':
        $a = wp_rand( 2, 15 );
        $b = wp_rand( 1, 12 );
        $answer = $a + $b;
        break;
      case '-':
        $a = wp_rand( 5, 20 );
        $b = wp_rand( 1, $a - 1 );
        $answer = $a - $b;
        break;
      case '*':
        $a = wp_rand( 2, 9 );
        $b = wp_rand( 2, 9 );
        $answer = $a * $b;
        break;
      default:
        $a = 3; $b = 4; $op = '+'; $answer = 7;
    }
    $op_labels = array( '+' => '+', '-' => '\u2212', '*' => '\u00d7' );
    $question  = $a . ' ' . $op_labels[ $op ] . ' ' . $b;

    $token = wp_generate_password( 20, false );
    set_transient(
      self::TRANSIENT_PREFIX . $token,
      wp_hash( (string) $answer ),
      10 * MINUTE_IN_SECONDS
    );
    return array(
      'question' => $question,
      'token'    => $token,
    );
  }

  // =========================================================
  // RENDER
  // =========================================================

  public function render_fields() {
    $captcha = $this->generate_captcha();
    ?>
    <div class="bbppp-antispam-wrap">
      <?php /* 1. HONEYPOT */ ?>
      <div class="bbppp-hp-field" aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;height:1px;overflow:hidden;">
        <label for="bbppp_hp_name"><?php esc_html_e( 'Leave this empty', 'bbp-profile-plus' ); ?></label>
        <input type="text" id="bbppp_hp_name" name="bbppp_hp_name" value="" autocomplete="off" tabindex="-1" />
      </div>
      <?php /* 2. TIME GATE */ ?>
      <input type="hidden" name="bbppp_form_time" id="bbppp_form_time" value="" />
      <?php /* 3. MATH CAPTCHA */ ?>
      <p class="bbppp-captcha-row">
        <label for="bbppp_captcha_answer">
          <?php
          printf(
            /* translators: %s is a math expression like "7 + 4" */
            esc_html__( 'Spam check: what is %s?', 'bbp-profile-plus' ),
            '<strong class="bbppp-captcha-q">' . esc_html( $captcha['question'] ) . '</strong>'
          );
          ?>
          <span class="required">*</span>
        </label>
        <input
          type="number"
          id="bbppp_captcha_answer"
          name="bbppp_captcha_answer"
          value=""
          autocomplete="off"
          inputmode="numeric"
          size="5"
          required
        />
        <input type="hidden" name="bbppp_captcha_token" value="<?php echo esc_attr( $captcha['token'] ); ?>" />
      </p>
    </div>

		<?php /* 4. XPROFILE FIELDS */ ?>
		<?php
		$xprofile = BBPPP_XProfile::instance();
		$groups   = $xprofile->get_groups_with_fields();
		if ( ! empty( $groups ) ) :
			foreach ( $groups as $group ) :
				if ( empty( $group->fields ) ) continue;
				?>
				<div class="bbppp-reg-group">
					<h3 class="bbppp-reg-group-title"><?php echo esc_html( $group->name ); ?></h3>
					<?php
					foreach ( $group->fields as $field ) :
						$xprofile->render_field( $field, '' );
					endforeach;
					?>
				</div>
				<?php
			endforeach;
		endif;
		?>
    <?php
  }

  // =========================================================
  // VALIDATION HELPERS
  // =========================================================

  /**
   * Core spam checks: honeypot, time gate, math CAPTCHA.
   * Returns true on pass, WP_Error on fail.
   */
  private function check_submission() {
    // 1. Honeypot
    if ( ! empty( $_POST['bbppp_hp_name'] ) ) {
      return new WP_Error( 'bbppp_spam', __( 'Spam check failed.', 'bbp-profile-plus' ) );
    }
    // 2. Time gate (>= 11 seconds, <= 1 hour)
    $submitted_at = isset( $_POST['bbppp_form_time'] ) ? (int) $_POST['bbppp_form_time'] : 0;
    $elapsed      = time() - $submitted_at;
    if ( $submitted_at === 0 || $elapsed < self::MIN_SUBMIT_SECONDS || $elapsed > 3600 ) {
      return new WP_Error(
        'bbppp_spam',
        __( 'Please take a moment to complete the form properly.', 'bbp-profile-plus' )
      );
    }
    // 3. Math CAPTCHA
    $token  = isset( $_POST['bbppp_captcha_token'] )  ? sanitize_text_field( $_POST['bbppp_captcha_token'] )  : '';
    $answer = isset( $_POST['bbppp_captcha_answer'] ) ? sanitize_text_field( $_POST['bbppp_captcha_answer'] ) : '';
    if ( empty( $token ) || $answer === '' ) {
      return new WP_Error(
        'bbppp_captcha',
        __( 'Please answer the spam-check question.', 'bbp-profile-plus' )
      );
    }
    $stored_hash = get_transient( self::TRANSIENT_PREFIX . $token );
    if ( false === $stored_hash ) {
      return new WP_Error(
        'bbppp_captcha',
        __( 'The spam-check question expired. Please reload the page and try again.', 'bbp-profile-plus' )
      );
    }
    if ( ! hash_equals( $stored_hash, wp_hash( $answer ) ) ) {
      return new WP_Error(
        'bbppp_captcha',
        __( 'Incorrect answer to the spam-check question. Please try again.', 'bbp-profile-plus' )
      );
    }
    // Passed - consume the token
    delete_transient( self::TRANSIENT_PREFIX . $token );
    return true;
  }

  // =========================================================
  // WORDPRESS REGISTRATION VALIDATION
  // =========================================================

  /**
   * Hooked to registration_errors.
   * Runs spam checks AND validates username/email uniqueness.
   */
  public function validate_registration( $errors, $sanitized_user_login, $user_email ) {

    // --- Spam checks ---
    $spam_result = $this->check_submission();
    if ( is_wp_error( $spam_result ) ) {
      $errors->add( $spam_result->get_error_code(), $spam_result->get_error_message() );
      // Return early - no point checking further if it's a bot
      return $errors;
    }

    // --- Username already taken ---
    if ( ! empty( $sanitized_user_login ) && username_exists( $sanitized_user_login ) ) {
      $errors->add(
        'username_exists',
        __( '<strong>Error:</strong> That username is already registered. Please choose a different one.', 'bbp-profile-plus' )
      );
    }

    // --- Email already registered ---
    if ( ! empty( $user_email ) && email_exists( $user_email ) ) {
      $errors->add(
        'email_exists',
        __( '<strong>Error:</strong> That email address is already registered. Did you forget your password?', 'bbp-profile-plus' )
      );
    }

    return $errors;
  }

  // =========================================================
  // BBPRESS VALIDATION (guests only)
  // =========================================================

  public function validate_bbpress( $topic_data ) {
    if ( is_user_logged_in() ) return $topic_data;
    $result = $this->check_submission();
    if ( is_wp_error( $result ) ) {
      bbp_add_error( $result->get_error_code(), $result->get_error_message() );
    }
    return $topic_data;
  }

  // =========================================================
  // FOOTER JS - sets hidden timestamp on page load
  // =========================================================

  public function footer_script() {
    ?>
    <script>
    (function(){
      var ts = Math.floor( Date.now() / 1000 );
      document.querySelectorAll('#bbppp_form_time').forEach(function(el){
        el.value = ts;
      });
    })();
    </script>
    <?php
  }

  // =========================================================
  // FILTER HOOK (allow disabling via code)
  // =========================================================

  public static function is_enabled() {
    return (bool) apply_filters( 'bbppp_antispam_enabled', true );
  }
}
