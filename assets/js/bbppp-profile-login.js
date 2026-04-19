/**
 * BBPress Profile Plus - Login Page JavaScript
 * Enhanced functionality for WordPress login/registration pages
 */

(function($) {
	'use strict';

	/**
	 * Initialize when DOM is ready
	 */
	$(document).ready(function() {
		// Add placeholder support for older browsers
		addPlaceholders();

		// Add form validation
		addFormValidation();

		// Enhance password visibility toggle
		addPasswordToggle();

		// Add smooth transitions
		enhanceFormAnimations();
	});

	/**
	 * Add placeholders to input fields
	 */
	function addPlaceholders() {
		$('#user_login').attr('placeholder', 'Username or Email');
		$('#user_pass').attr('placeholder', 'Password');
		$('#user_email').attr('placeholder', 'Email Address');
	}

	/**
	 * Add form validation
	 */
	function addFormValidation() {
		$('.login form').on('submit', function(e) {
			var isValid = true;
			var $form = $(this);

			// Clear previous errors
			$form.find('.error').removeClass('error');

			// Validate required fields
			$form.find('input[required]').each(function() {
				if ($(this).val().trim() === '') {
					$(this).addClass('error');
					isValid = false;
				}
			});

			// Validate email format
			var $email = $form.find('input[type="email"]');
			if ($email.length && $email.val()) {
				var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if (!emailPattern.test($email.val())) {
					$email.addClass('error');
					isValid = false;
				}
			}

			return isValid;
		});
	}

	/**
	 * Add password visibility toggle
	 */
	function addPasswordToggle() {
		var $passwordField = $('#user_pass');
		if ($passwordField.length) {
			var $toggle = $('<button type="button" class="password-toggle" aria-label="Toggle password visibility">Show</button>');
			$passwordField.after($toggle);

			$toggle.on('click', function() {
				var type = $passwordField.attr('type');
				if (type === 'password') {
					$passwordField.attr('type', 'text');
					$(this).text('Hide');
				} else {
					$passwordField.attr('type', 'password');
					$(this).text('Show');
				}
			});
		}
	}

	/**
	 * Enhance form animations
	 */
	function enhanceFormAnimations() {
		// Add focus class to labels
		$('.login input').on('focus', function() {
			$(this).parent().addClass('focused');
		}).on('blur', function() {
			if (!$(this).val()) {
				$(this).parent().removeClass('focused');
			}
		});

		// Shake on error
		if ($('#login_error').length) {
			$('#login').addClass('shake');
			setTimeout(function() {
				$('#login').removeClass('shake');
			}, 500);
		}
	}

})(jQuery);
