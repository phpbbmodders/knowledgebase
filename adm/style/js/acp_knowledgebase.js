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
})(jQuery);
