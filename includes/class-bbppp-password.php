<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * BBPPP_Password
 *
 * - Adds a user-chosen password (with confirmation) to the WP registration form.
 * - Positions password fields directly under the username via inline JS reordering.
 * - Forces the anti-spam captcha to the bottom of the form (after xProfile).
 * - Normalises the confirm-email field width to match the email field.
 * - Feeds the chosen password into $_POST['user_pass'] so BBPPP_Activation
 *   creates the pending user with the correct password.
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
        // Render the password fields. Priority 1 = earliest on register_form,
        // combined with JS reordering, ensures they sit just below the username.
        add_action( 'register_form',       array( $this, 'render_fields' ), 1 );

        // Validate + inject into $_POST['user_pass'] before activation runs.
        add_filter( 'registration_errors', array( $this, 'validate' ), 20, 3 );

        // Inline JS + CSS on wp-login.php to:
        //  (a) move password block right after #user_login field
        //  (b) move captcha block to the bottom
        //  (c) match confirm-email width to the email input
        add_action( 'login_enqueue_scripts', array( $this, 'login_assets' ) );
    }

    /**
     * Render password + confirm password fields.
     * Wrapped in an identifiable container so JS can relocate it.
     */
    public function render_fields() {
        ?>
        <div id="bbppp-password-block" class="bbppp-password-block">
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
            <p class="description" style="font-size:11px;color:#666;"><?php esc_html_e( 'Choose a password of at least 8 characters. You will use this password to log in once your account is activated.', 'bbp-profile-plus' ); ?></p>
        </div>
        <?php
    }

    /**
     * Validate the password and inject it into $_POST['user_pass'] so the
     * downstream activation class picks it up when creating the pending user.
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

        // If no password errors, hand the plaintext off to the activation flow.
        if ( ! $errors->get_error_messages( 'bbppp_pass_empty' ) &&
             ! $errors->get_error_messages( 'bbppp_pass_short' ) &&
             ! $errors->get_error_messages( 'bbppp_pass_mismatch' ) ) {
            $_POST['user_pass'] = $pass1;
        }

        return $errors;
    }

    /**
     * Inline CSS + JS on wp-login.php (registration view) to:
     *   - move the password block right after username
     *   - move the captcha block to the bottom of the form
     *   - stretch the confirm-email input to full width (like #user_email)
     */
    public function login_assets() {
        // Only act on the register view.
        $action = isset( $_GET['action'] ) ? $_GET['action'] : '';
        if ( 'register' !== $action ) return;

        $css = '
            #bbppp-password-block input[type="password"] { width: 100%; font-size: 24px; padding: 3px; margin: 2px 6px 16px 0; }
            #bbppp-password-block label { display:block; }
            .bbppp-confirm-email input[type="email"],
            .bbppp-confirm-email input[type="text"] { width: 100%; font-size: 24px; padding: 3px; margin: 2px 6px 16px 0; }
            .bbppp-captcha-block { margin-top: 14px; }
        ';
        wp_register_style( 'bbppp-register-layout', false );
        wp_enqueue_style( 'bbppp-register-layout' );
        wp_add_inline_style( 'bbppp-register-layout', $css );

        $js = <<<JS
(function(){
    function ready(fn){ if(document.readyState!="loading"){fn();} else {document.addEventListener("DOMContentLoaded",fn);} }
    ready(function(){
        var form = document.getElementById("registerform");
        if (!form) return;

        // 1) Move password block to directly after the username field's <p>.
        var pwBlock = document.getElementById("bbppp-password-block");
        var userLogin = document.getElementById("user_login");
        if (pwBlock && userLogin) {
            var userP = userLogin.closest("p");
            if (userP && userP.parentNode === form) {
                form.insertBefore(pwBlock, userP.nextSibling);
            }
        }

        // 2) Match the confirm-email field's width/styling to the email input.
        //    We detect the confirm-email field by common name/id patterns.
        var emailField = document.getElementById("user_email");
        var candidates = form.querySelectorAll('input[name*="confirm"], input[id*="confirm"], input[name*="email_confirm"], input[name="bbppp_confirm_email"]');
        candidates.forEach(function(inp){
            if (!inp) return;
            if (emailField) {
                inp.style.width = window.getComputedStyle(emailField).width;
                inp.style.fontSize = window.getComputedStyle(emailField).fontSize;
                inp.style.padding = window.getComputedStyle(emailField).padding;
            } else {
                inp.style.width = "100%";
            }
            var wrap = inp.closest("p") || inp.parentNode;
            if (wrap) wrap.classList.add("bbppp-confirm-email");
        });

        // 3) Move the captcha block (and honeypot/time gate wrappers) to the end.
        //    Detect by common BBPPP anti-spam markers.
        var captchaSelectors = [
            "#bbppp_captcha", "[name=bbppp_captcha]",
            "[name=bbppp_hp]", "[name=bbppp_ts]", "[name=bbppp_token]"
        ];
        var handled = new Set();
        captchaSelectors.forEach(function(sel){
            form.querySelectorAll(sel).forEach(function(el){
                var wrap = el.closest("p") || el.parentNode;
                if (!wrap || handled.has(wrap)) return;
                handled.add(wrap);
                wrap.classList.add("bbppp-captcha-block");
                // Move just before the submit row.
                var submit = form.querySelector("#wp-submit");
                var submitP = submit ? submit.closest("p") : null;
                if (submitP && submitP.parentNode === form) {
                    form.insertBefore(wrap, submitP);
                } else {
                    form.appendChild(wrap);
                }
            });
        });
    });
})();
JS;
        wp_register_script( 'bbppp-register-layout', '', array(), false, true );
        wp_enqueue_script( 'bbppp-register-layout' );
        wp_add_inline_script( 'bbppp-register-layout', $js );
    }
}
