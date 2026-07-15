(function ($) {
	'use strict';

	function uniqueId(prefix) {
		return prefix + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
	}

	function feedUuid() {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return window.crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (character) {
			var random = Math.random() * 16 | 0;
			var value = character === 'x' ? random : (random & 0x3 | 0x8);
			return value.toString(16);
		});
	}

	$(document).on('click', '.iwmm-add-feed', function () {
		var id = feedUuid();
		var html = $('#tmpl-iwmm-feed').html().replaceAll('__FEED__', id);
		$('#iwmm-feeds').append(html);
	});

	$(document).on('click', '.iwmm-remove-feed', function () {
		$(this).closest('.iwmm-feed').remove();
	});

	$(document).on('click', '.iwmm-add-condition', function () {
		var feed = $(this).closest('.iwmm-feed');
		var feedId = feed.data('feed-id');
		var conditionId = uniqueId('condition_');
		var base = 'settings[iwmm][feeds][' + feedId + '][conditions][' + conditionId + ']';
		var html = '<div class="iwmm-condition">' +
			'<input type="number" min="1" placeholder="Field ID" name="' + base + '[field_id]">' +
			'<select name="' + base + '[operator]"><option value="equals">Equals</option><option value="not_equals">Does not equal</option><option value="contains">Contains</option><option value="not_contains">Does not contain</option><option value="empty">Is empty</option><option value="not_empty">Is not empty</option></select>' +
			'<input type="text" placeholder="Value" name="' + base + '[value]">' +
			'<button type="button" class="button-link-delete iwmm-remove-condition" aria-label="Remove condition">&times;</button></div>';
		feed.find('.iwmm-conditions').append(html);
	});

	$(document).on('click', '.iwmm-remove-condition', function () {
		$(this).closest('.iwmm-condition').remove();
	});
})(jQuery);
