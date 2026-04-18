/* BBP Profile Plus - Frontend JS */
(function($) {
  'use strict';

  var $wrap, nonce;

  function showMsg(msg, type) {
    var $m = $('#bbppp-account-msg');
    $m.removeClass('is-success is-error').addClass(type === 'success' ? 'is-success' : 'is-error').html(msg).show();
    $('html, body').animate({ scrollTop: $m.offset().top - 80 }, 300);
    setTimeout(function() { $m.fadeOut(); }, 5000);
  }

  /* ---- Generic AJAX form submit ---- */
  function bindForm($form) {
    $form.on('submit', function(e) {
      e.preventDefault();
      var action = $form.data('action');
      var data   = $form.serializeArray();
      data.push({ name: 'action', value: action });
      data.push({ name: 'nonce',  value: nonce });
      var $btn = $form.find('[type="submit"]');
      $btn.prop('disabled', true).text(bbpppL10n.saving);
      $.post(bbpppL10n.ajaxurl, data, function(res) {
        $btn.prop('disabled', false).text($btn.data('orig') || $btn.val() || bbpppL10n.save);
        if (res.success) {
          showMsg(res.data, 'success');
          if (action === 'bbppp_save_password') {
            setTimeout(function() { window.location.href = bbpppL10n.loginUrl; }, 2000);
          }
          if (action === 'bbppp_delete_account') {
            setTimeout(function() { window.location.href = bbpppL10n.homeUrl; }, 2000);
          }
        } else {
          showMsg(res.data, 'error');
        }
      }).fail(function() {
        $btn.prop('disabled', false);
        showMsg(bbpppL10n.error, 'error');
      });
    });
  }

  /* ---- Password strength indicator ---- */
  function passwordStrength(pass) {
    var score = 0;
    if (pass.length >= 8)  score++;
    if (pass.length >= 12) score++;
    if (/[A-Z]/.test(pass)) score++;
    if (/[0-9]/.test(pass)) score++;
    if (/[^A-Za-z0-9]/.test(pass)) score++;
    if (score <= 1) return 'weak';
    if (score <= 3) return 'medium';
    return 'strong';
  }

  /* ---- Avatar upload ---- */
  function initAvatarUpload() {
    $('#bbppp-avatar-file').on('change', function() {
      var file = this.files[0];
      if (!file) return;
      var formData = new FormData();
      formData.append('action', 'bbppp_upload_avatar');
      formData.append('nonce', nonce);
      formData.append('avatar', file);
      // Preview
      var reader = new FileReader();
      reader.onload = function(ev) { $('#bbppp-avatar-preview').attr('src', ev.target.result); };
      reader.readAsDataURL(file);
      $.ajax({ url: bbpppL10n.ajaxurl, type: 'POST', data: formData, processData: false, contentType: false,
        success: function(res) {
          if (res.success) {
            showMsg(res.data.message, 'success');
            $('#bbppp-avatar-preview').attr('src', res.data.url);
          } else {
            showMsg(res.data, 'error');
          }
        },
        error: function() { showMsg(bbpppL10n.error, 'error'); }
      });
    });

    $('#bbppp-delete-avatar').on('click', function() {
      if (!confirm(bbpppL10n.confirmRemoveAvatar)) return;
      $.post(bbpppL10n.ajaxurl, { action: 'bbppp_delete_avatar', nonce: nonce }, function(res) {
        if (res.success) {
          showMsg(res.data, 'success');
          $('#bbppp-avatar-preview').attr('src', bbpppL10n.defaultAvatar);
        } else {
          showMsg(res.data, 'error');
        }
      });
    });
  }

  /* ---- Delete account confirmation ---- */
  function initDeleteAccount() {
    $('#bbppp-delete-form').on('submit', function(e) {
      if (!confirm(bbpppL10n.confirmDelete)) {
        e.preventDefault();
        return false;
      }
    });
  }

  /* ---- Init ---- */
  $(document).ready(function() {
    $wrap = $('#bbppp-account');
    if (!$wrap.length) return;
    nonce = $wrap.data('nonce');

    // Bind all forms
    $wrap.find('.bbppp-form').each(function() {
      var $form = $(this);
      $form.find('[type="submit"]').each(function() {
        $(this).data('orig', $(this).text());
      });
      bindForm($form);
    });

    // Password strength
    $('#bbppp-new-pass').on('input', function() {
      var val = $(this).val();
      var $bar = $('#bbppp-pass-strength');
      if (!val) { $bar.html('').removeClass('weak medium strong'); return; }
      var level = passwordStrength(val);
      $bar.html('<span></span>').removeClass('weak medium strong').addClass(level);
    });

    initAvatarUpload();
    initDeleteAccount();
  });

})(jQuery);
