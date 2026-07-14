(function($){
	'use strict';

	function nextIndex(type) {
		return $('[data-aomark-rows="' + type + '"] .aomark-listings-repeater-row').length + '_' + Date.now();
	}

	function activateTab(button, tabAttribute, panelAttribute) {
		var tab = button.attr(tabAttribute);
		var scope = button.closest('.aomark-listings-model-editor, .aomark-listings-metabox-shell');

		scope.find('[' + tabAttribute + ']').removeClass('is-active');
		scope.find('[' + panelAttribute + ']').removeClass('is-active');
		button.addClass('is-active');
		scope.find('[' + panelAttribute + '="' + tab + '"]').addClass('is-active');
	}

	function fieldSlug(value) {
		var slug = String(value || '')
			.replace(/[Đđ]/g, 'dj')
			.replace(/&/g, ' and ');

		if (typeof slug.normalize === 'function') {
			slug = slug.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		}

		return slug
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '_')
			.replace(/^_+|_+$/g, '');
	}

	function syncGeneratedInput(input, nextValue) {
		var currentValue = String(input.val() || '');
		var previousGeneratedValue = String(input.attr('data-aomark-generated-value') || '');

		if (!currentValue || (previousGeneratedValue && currentValue === previousGeneratedValue)) {
			input.val(nextValue).attr('data-aomark-generated-value', nextValue);
		}
	}

	function autoGenerateFieldKeys(row) {
		if (!row.hasClass('aomark-listings-repeater-row--field')) {
			return;
		}

		var slug = fieldSlug(row.find('[data-aomark-field-label]').val());
		var metaKey = slug ? (slug.indexOf('aomark_') === 0 ? slug : 'aomark_' + slug) : '';

		syncGeneratedInput(row.find('[data-aomark-field-id]'), slug);
		syncGeneratedInput(row.find('[data-aomark-field-key]'), metaKey);
	}

	function syncRowSummary(row) {
		var label = row.find('input[name*="[label]"], input[name*="[singular]"]').first().val();
		var name = row.find('input[name*="[key]"], input[name*="[slug]"]').first().val();
		var type = row.find('select[name*="[type]"] option:selected').text();
		var typeValue = row.find('select[name*="[type]"]').val();
		var hierarchical = row.find('input[name*="[hierarchical]"]').is(':checked');
		var groupSelect = row.find('select[name*="[group]"]');
		var group = groupSelect.find('option:selected').text();
		var groupValue = groupSelect.val();

		row.find('.aomark-listings-row-title strong').text(label || (row.hasClass('aomark-listings-repeater-row--taxonomy') ? 'New taxonomy' : 'New field'));
		row.find('.aomark-listings-row-title code').text(name || 'not saved yet');

		if (type) {
			row.find('.aomark-listings-type-pill').attr('class', 'aomark-listings-type-pill aomark-listings-type-pill--' + typeValue).text(type);
		} else if (row.hasClass('aomark-listings-repeater-row--taxonomy')) {
			row.find('.aomark-listings-type-pill').text(hierarchical ? 'Category style' : 'Tag style');
		}

		if (row.hasClass('aomark-listings-repeater-row--field')) {
			var placeholderTypes = ['text', 'textarea', 'number', 'price', 'select', 'location', 'url', 'email', 'date'];
			if (!groupValue) {
				var id = String(row.find('input[name*="[id]"]').val() || '').toLowerCase();
				var featureIds = ['area', 'bedrooms', 'bathrooms', 'rooms', 'parking', 'garages'];
				var automaticGroup = featureIds.indexOf(id) !== -1 ? 'Features' : (typeValue === 'location' ? 'Location' : (typeValue === 'image' || typeValue === 'gallery' ? 'Media' : 'Details'));
				group = 'Automatic · ' + automaticGroup;
			}
			row.find('.aomark-listings-row-tab').text(group || 'Automatic');
			row.find('.aomark-listings-options').toggleClass('is-visible', typeValue === 'select');
			row.find('.aomark-listings-placeholder').toggleClass('is-visible', placeholderTypes.indexOf(typeValue) !== -1);
		}
	}

	function setRowExpanded(row, expanded) {
		row.toggleClass('is-collapsed', !expanded);
		row.find('[data-aomark-toggle-row]').first().attr('aria-expanded', expanded ? 'true' : 'false');
	}

	function adminString(key, fallback) {
		var strings = (window.AomarkListingsAdmin && window.AomarkListingsAdmin.strings) || {};
		return strings[key] || fallback;
	}

	function initLocationEditors(context) {
		var editors = $(context || document).find('[data-aomark-location-editor]');
		if ($(context).is && $(context).is('[data-aomark-location-editor]')) {
			editors = editors.add(context);
		}

		editors.each(function(){
			var editor = $(this);
			if (editor.data('aomark-location-ready')) { return; }
			editor.data('aomark-location-ready', true);

			var address = editor.find('[data-aomark-location-address]');
			var latInput = editor.find('[data-aomark-location-lat]');
			var lngInput = editor.find('[data-aomark-location-lng]');
			var canvas = editor.find('[data-aomark-location-map]').get(0);
			var results = editor.find('[data-aomark-location-results]');
			var status = editor.find('[data-aomark-location-status]');
			var settings = window.AomarkListingsAdmin || {};
			var defaultCenter = Array.isArray(settings.defaultCenter) && settings.defaultCenter.length === 2 ? settings.defaultCenter : [20, 0];
			var defaultZoom = parseInt(settings.defaultZoom, 10) || 2;
			var timer = null;
			var requestController = null;
			var map = null;
			var marker = null;

			address.attr('data-aomark-selected-address', address.val() || '');

			function validPoint(lat, lng) {
				return isFinite(lat) && isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180 && !(lat === 0 && lng === 0);
			}

			function writePoint(lat, lng) {
				latInput.val(Number(lat).toFixed(7));
				lngInput.val(Number(lng).toFixed(7));
			}

			function clearPoint() {
				latInput.val('');
				lngInput.val('');
				if (marker && map) {
					map.removeLayer(marker);
					marker = null;
				}
			}

			function setPoint(lat, lng, centerMap, message) {
				lat = parseFloat(lat);
				lng = parseFloat(lng);
				if (!validPoint(lat, lng)) { return; }
				writePoint(lat, lng);
				if (map) {
					if (!marker) {
						marker = window.L.marker([lat, lng], { draggable: true }).addTo(map);
						marker.on('dragend', function(){
							var point = marker.getLatLng();
							writePoint(point.lat, point.lng);
							status.text(adminString('pinMoved', 'Pin position updated.'));
						});
					} else {
						marker.setLatLng([lat, lng]);
					}
					if (centerMap) { map.setView([lat, lng], Math.max(map.getZoom(), 16)); }
				}
				if (message) { status.text(message); }
			}

			function closeResults() {
				results.empty().prop('hidden', true);
				address.attr('aria-expanded', 'false');
			}

			function showResults(items) {
				results.empty();
				if (!items.length) {
					closeResults();
					status.text(adminString('noResults', 'No matching addresses found.'));
					return;
				}

				items.forEach(function(item){
					$('<button type="button" role="option" class="aomark-listings-location-result"></button>')
						.text(item.label || '')
						.attr('data-lat', item.lat)
						.attr('data-lng', item.lng)
						.attr('data-label', item.label || '')
						.appendTo(results);
				});
				results.prop('hidden', false);
				address.attr('aria-expanded', 'true');
			}

			function searchAddress(query) {
				if (!settings.ajaxUrl || !settings.geocodeNonce) { return; }
				if (requestController && requestController.abort) { requestController.abort(); }
				requestController = window.AbortController ? new window.AbortController() : null;
				status.text(adminString('searching', 'Searching addresses…'));

				var separator = settings.ajaxUrl.indexOf('?') === -1 ? '?' : '&';
				var url = settings.ajaxUrl + separator + new URLSearchParams({
					action: 'aomark_listings_geocode',
					nonce: settings.geocodeNonce,
					query: query
				}).toString();

				fetch(url, {
					credentials: 'same-origin',
					signal: requestController ? requestController.signal : undefined
				}).then(function(response){
					if (!response.ok) { throw new Error('Geocoder request failed'); }
					return response.json();
				}).then(function(payload){
					if (!payload || !payload.success || !payload.data) { throw new Error('Invalid geocoder response'); }
					showResults(Array.isArray(payload.data.results) ? payload.data.results : []);
				}).catch(function(error){
					if (error && error.name === 'AbortError') { return; }
					closeResults();
					status.text(adminString('searchError', 'Address search is temporarily unavailable.'));
				});
			}

			if (window.L && canvas) {
				map = window.L.map(canvas, { scrollWheelZoom: false }).setView(defaultCenter, defaultZoom);
				window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
					maxZoom: 19,
					attribution: '&copy; OpenStreetMap contributors'
				}).addTo(map);
				map.on('click', function(event){
					setPoint(event.latlng.lat, event.latlng.lng, false, adminString('pinMoved', 'Pin position updated.'));
				});
				canvas._aomarkLocationMap = map;
				var savedLat = parseFloat(latInput.val());
				var savedLng = parseFloat(lngInput.val());
				if (validPoint(savedLat, savedLng)) { setPoint(savedLat, savedLng, true); }
				setTimeout(function(){ map.invalidateSize(); }, 100);
			} else {
				status.text(adminString('mapUnavailable', 'Map could not be loaded.'));
			}

			address.on('input', function(){
				var query = $.trim(address.val());
				clearTimeout(timer);
				if (query !== address.attr('data-aomark-selected-address')) { clearPoint(); }
				if (query.length < 3) {
					closeResults();
					status.text(query ? adminString('typeMore', 'Type at least three characters to search.') : '');
					return;
				}
				timer = setTimeout(function(){ searchAddress(query); }, 650);
			});

			address.on('keydown', function(event){
				if (event.key === 'ArrowDown' && !results.prop('hidden')) {
					event.preventDefault();
					results.find('button').first().trigger('focus');
				} else if (event.key === 'Escape') {
					closeResults();
				}
			});

			results.on('click', '.aomark-listings-location-result', function(){
				var result = $(this);
				var label = result.attr('data-label') || result.text();
				address.val(label).attr('data-aomark-selected-address', label);
				setPoint(result.attr('data-lat'), result.attr('data-lng'), true, adminString('locationSet', 'Location selected.'));
				closeResults();
				address.trigger('focus');
			});

			address.on('blur', function(){
				setTimeout(closeResults, 160);
			});
		});
	}

	$(document).on('click', '[data-aomark-admin-tab]', function(event){
		event.preventDefault();
		activateTab($(this), 'data-aomark-admin-tab', 'data-aomark-admin-panel');
	});

	$(document).on('click', '[data-aomark-metabox-tab]', function(event){
		event.preventDefault();
		activateTab($(this), 'data-aomark-metabox-tab', 'data-aomark-metabox-panel');
		setTimeout(function(){
			$('[data-aomark-location-map]').each(function(){
				if (this._aomarkLocationMap) { this._aomarkLocationMap.invalidateSize(); }
			});
		}, 80);
	});

	$(document).on('click', '[data-aomark-toggle-row]', function(event){
		event.preventDefault();
		var row = $(this).closest('.aomark-listings-repeater-row');
		var shouldOpen = row.hasClass('is-collapsed');
		if (shouldOpen) {
			row.siblings('.aomark-listings-repeater-row').each(function(){
				setRowExpanded($(this), false);
			});
		}
		setRowExpanded(row, shouldOpen);
	});

	$(document).on('click', '[data-aomark-add-row]', function(event){
		event.preventDefault();
		var type = $(this).data('aomark-add-row');
		var template = $('#tmpl-aomark-' + type + '-row').html();
		if (!template) { return; }
		var row = $(template.replace(/__i__/g, nextIndex(type)));
		$('[data-aomark-rows="' + type + '"]').append(row);
		syncRowSummary(row);
		if (type === 'field' || type === 'taxonomy') {
			row.siblings('.aomark-listings-repeater-row').each(function(){
				setRowExpanded($(this), false);
			});
			setRowExpanded(row, true);
			row.find(type === 'field' ? '[data-aomark-field-label]' : 'input[name*="[singular]"]').first().trigger('focus');
		}
	});

	$(document).on('click', '[data-aomark-remove-row]', function(event){
		event.preventDefault();
		$(this).closest('.aomark-listings-repeater-row').remove();
	});

	$(document).on('input change', '.aomark-listings-repeater-row input, .aomark-listings-repeater-row select', function(){
		var row = $(this).closest('.aomark-listings-repeater-row');
		if ($(this).is('[data-aomark-field-label]')) {
			autoGenerateFieldKeys(row);
		}
		syncRowSummary(row);
	});

	function openImageFrame(callback, multiple) {
		var frame = wp.media({
			title: multiple ? 'Choose Gallery' : 'Choose Image',
			button: { text: multiple ? 'Use Gallery' : 'Use Image' },
			library: { type: 'image' },
			multiple: !!multiple
		});
		frame.on('select', function(){
			callback(frame.state().get('selection'));
		});
		frame.open();
	}

	$(document).on('click', '.aomark-listings-select-image', function(event){
		event.preventDefault();
		var wrap = $(this).closest('.aomark-listings-media-field');
		openImageFrame(function(selection){
			var model = selection.first();
			if (!model) { return; }
			var data = model.toJSON();
			wrap.find('input[type="hidden"]').val(data.id || '');
			var url = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;
			wrap.find('.aomark-listings-media-preview').html(url ? '<img src="' + url + '" alt="">' : '');
		}, false);
	});

	$(document).on('click', '.aomark-listings-select-gallery', function(event){
		event.preventDefault();
		var wrap = $(this).closest('.aomark-listings-gallery-field');
		openImageFrame(function(selection){
			var ids = [];
			var html = '';
			selection.each(function(model){
				var data = model.toJSON();
				if (!data.id) { return; }
				ids.push(data.id);
				var url = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;
				if (url) { html += '<span data-id="' + data.id + '"><img src="' + url + '" alt=""></span>'; }
			});
			wrap.find('input[type="hidden"]').val(ids.join(','));
			wrap.find('.aomark-listings-gallery-preview').html(html);
		}, true);
	});

	$(document).on('click', '.aomark-listings-clear-media', function(event){
		event.preventDefault();
		var wrap = $(this).closest('.aomark-listings-media-field, .aomark-listings-gallery-field');
		wrap.find('input[type="hidden"]').val('');
		wrap.find('.aomark-listings-media-preview, .aomark-listings-gallery-preview').empty();
	});

	$(function(){
		initLocationEditors(document);
	});
})(jQuery);
