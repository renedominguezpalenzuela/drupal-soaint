(function ($) {
	Drupal.behaviors.webformJS = {
		attach: function (context, settings) {
			$('.form-checkboxes > .form-item > input').remove();
			$('.node-webform button.btn.btn-bsf.form-submit').html('<i class="fa fa-envelope-o" aria-hidden="true"></i>' + $('.node-webform button.btn.btn-bsf.form-submit').text());
		}
	};
}(jQuery));