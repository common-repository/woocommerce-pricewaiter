(function($) {

		var $saveButtons = $('#save_wc_pricewaiter_setup_api_user_new, #save_wc_pricewaiter_setup_api_user_existing');

		function pricewiater_create_error(msg) {
			$('#wc_pricewaiter_setup_step_1 .js_error_holder').html('<div id="message" class="error"><p><strong>Please correct the following errors:</strong><br>' + msg + '</p></div>');
		}

		function pricewaiter_handle_user_creation(response) {
			if (response.success) {
				location.reload();
			} else {
				if (response.data && response.data.errors) {
					pricewiater_create_error(response.data.errors);
				}
				pricewaiter_enable_save_buttons();
			}
		}

		function pricewaiter_disable_save_buttons() {
			$saveButtons.attr('disabled', 'disabled');
		}

		function pricewaiter_enable_save_buttons() {
			$saveButtons.removeAttr('disabled');
		}

		$('#save_wc_pricewaiter_setup_api_user_new').on('click', function(e) {

			e.preventDefault();

			pricewaiter_disable_save_buttons();

			var loginField = $('#wc_pricewaiter_api_user_login'),
			emailField = $('#wc_pricewaiter_api_user_email');

			if (!loginField.val() || !emailField.val()) {
				pricewiater_create_error('Please enter a username and email address for the user.');
				pricewaiter_enable_save_buttons();
				return false;
			}

			var data = {
				'action': 'call_create_api_user_ajax',
				'login': loginField.val(),
				'email': emailField.val()
			};

			$.post(ajaxurl, data, function(response) {
				pricewaiter_handle_user_creation(response);
			});

			return false;

		});

		$('#save_wc_pricewaiter_setup_api_user_existing').on('click', function(e) {

			e.preventDefault();

			pricewaiter_disable_save_buttons();

			var $idField = $('#wc_pricewaiter_api_user_existing'),
				$option = $('option:selected', $idField),
				$optionValue = $option.val();

			if (!$optionValue || isNaN($optionValue)) {
				pricewiater_create_error('Please select an administrator.');
				pricewaiter_enable_save_buttons();
				return false;
			}

			var data = {
				'action': 'call_create_api_user_ajax',
				'id': $option.val()
			};

			$.post(ajaxurl, data, function(response) {
				pricewaiter_handle_user_creation(response);
			});

			return false;

		});


})(jQuery);
