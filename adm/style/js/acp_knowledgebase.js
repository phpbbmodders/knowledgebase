(function($) { // Avoid conflicts with other libraries
	'use strict';

	$('#kb_font_icon').on('keyup blur', function() {
		let input = $(this).val();
		let $icon = $(this).next('i');
		if (input.length > 0) {
			$icon.attr('class', 'icon acp-icon fa-2x fa-' + input);
		} else {
			$icon.attr('class', '');
		}
	});
	// The introduction's text and Markdown options show only while it is
	// switched on. They are in the page from the start, so the setting still
	// works without JavaScript.
	let $introEnabled = $('#intro_enabled');
	let $introOptions = $('#intro_options');
	if ($introEnabled.length && $introOptions.length) {
		$introOptions.toggle($introEnabled.prop('checked'));
		$introEnabled.on('change', function() {
			$introOptions.toggle(this.checked);
		});
	}
})(jQuery);
