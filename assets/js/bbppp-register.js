/* BBP Profile Plus - Registration Form JS */
(function($) {
  'use strict';

  $(document).ready(function() {
    var $form = $('#bbppp-register-form');
    if (!$form.length) return;

    var $msg  = $('#bbppp-register-msg');
    var $btn  = $('#bbppp-register-submit');
    var btnText = $btn.text().trim();

    // Set timestamp for time-gate anti-spam
    var ts = Math.floor(Date.now() / 1000);
    $('#bbppp_reg_form_time').val(ts);

    // Password strength meter
    var $pw = $('#bbppp_reg_password');
    var $meter = $('#bbppp-password-strength');
    $pw.on('input', function() {
      var val = $(this).val();
      $meter.removeClass('strength-weak strength-medium strength-strong');
      if (val.length === 0) return;
      if (val.length < 8) {
        $meter.addClass('strength-weak');
      } else if (val.length < 12 || !/[A-Z]/.test(val) || !/[0-9]/.test(val)) {
        $meter.addClass('strength-medium');
      } else {
        $meter.addClass('strength-strong');
      }
    });

    // Client-side validation
    function validate() {
      var errors = [];
      var username = $('#bbppp_reg_username').val().trim();
      var password = $pw.val();
      var password2 = $('#bbppp_reg_password2').val();
      var email = $('#bbppp_reg_email').val().trim();
      var email2 = $('#bbppp_reg_email2').val().trim();
      var captcha = $('#bbppp_reg_captcha').val().trim();

      // Clear previous error highlights
      $form.find('.bbppp-input-error').removeClass('bbppp-input-error');

      if (!username) {
        errors.push(bbpppReg.i18n.usernameRequired);
        $('#bbppp_reg_username').addClass('bbppp-input-error');
      }
      if (!email) {
        errors.push(bbpppReg.i18n.emailRequired);
        $('#bbppp_reg_email').addClass('bbppp-input-error');
      }
      if (email && email2 && email !== email2) {
        errors.push(bbpppReg.i18n.emailMismatch);
        $('#bbppp_reg_email2').addClass('bbppp-input-error');
      }
      if (!password) {
        errors.push(bbpppReg.i18n.passwordRequired);
        $pw.addClass('bbppp-input-error');
      } else if (password.length < 8) {
        errors.push(bbpppReg.i18n.passwordShort);
        $pw.addClass('bbppp-input-error');
      }
      if (password && password2 && password !== password2) {
        errors.push(bbpppReg.i18n.passwordMismatch);
        $('#bbppp_reg_password2').addClass('bbppp-input-error');
      }
      if (!captcha) {
        errors.push(bbpppReg.i18n.captchaRequired);
        $('#bbppp_reg_captcha').addClass('bbppp-input-error');
      }

      // Check required xProfile fields
      $form.find('[required]').each(function() {
        var $el = $(this);
        if ($el.attr('name') && $el.attr('name').indexOf('xprofile') === 0) {
          if (!$el.val() || (typeof $el.val() === 'string' && !$el.val().trim())) {
            $el.addClass('bbppp-input-error');
          }
        }
      });

      return errors;
    }

    function showMsg(text, type) {
      $msg.removeClass('is-success is-error')
          .addClass(type === 'success' ? 'is-success' : 'is-error')
          .html(text)
          .show();

      // Scroll to message
      if ($msg.offset()) {
        $('html, body').animate({ scrollTop: $msg.offset().top - 80 }, 300);
      }
    }

    // Form submit
    $form.on('submit', function(e) {
      e.preventDefault();

      var clientErrors = validate();
      if (clientErrors.length > 0) {
        showMsg(clientErrors.join('<br>'), 'error');
        return;
      }

      $btn.prop('disabled', true).text(bbpppReg.i18n.submitting);
      $msg.hide();

      var data = $form.serializeArray();
      data.push({ name: 'action', value: 'bbppp_register' });
      data.push({ name: 'nonce',  value: bbpppReg.nonce });

      $.post(bbpppReg.ajaxurl, data, function(res) {
        if (res.success) {
          showMsg(res.data, 'success');
          $form.find('input, select, textarea, button').prop('disabled', true);
          $btn.hide();
        } else {
          showMsg(res.data, 'error');
          $btn.prop('disabled', false).text(btnText);
        }
      }).fail(function() {
        showMsg(bbpppReg.i18n.serverError, 'error');
        $btn.prop('disabled', false).text(btnText);
      });
    });
  });
})(jQuery);
