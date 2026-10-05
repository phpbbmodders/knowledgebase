(function() {
	'use strict';

	// The BBCode buttons make no sense while the article is written in
	// Markdown, so hide them whenever the Markdown box is ticked.
	const box = document.getElementById('article_markdown'),
		buttons = document.getElementById('format-buttons');

	if (!box || !buttons) {
		return;
	}

	function sync() {
		buttons.style.display = box.checked ? 'none' : '';
	}

	box.addEventListener('change', sync);
	sync();
})();
