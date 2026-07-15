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
		var feed = $(html).appendTo('#iwmm-feeds');
		feed.trigger('connectionRendered');
	});

	$(document).on('click', '.iwmm-remove-feed', function () {
		$(this).closest('.iwmm-feed').remove();
	});

	$(document).on('click', '.iwmm-add-condition', function () {
		var feed = $(this).closest('.iwmm-feed');
		var feedId = feed.data('feed-id');
		var conditionId = uniqueId('condition_');
		var html = $('#tmpl-iwmm-condition').html()
			.replaceAll('__FEED__', feedId)
			.replaceAll('__CONDITION__', conditionId);
		feed.find('.iwmm-conditions').append(html);
	});

	$(document).on('click', '.iwmm-remove-condition', function () {
		$(this).closest('.iwmm-condition').remove();
	});
})(jQuery);
