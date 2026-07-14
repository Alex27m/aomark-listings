(function(){
	'use strict';

	if (window.AomarkListingsReady) { return; }
	window.AomarkListingsReady = true;

	function qs(root, selector){ return (root || document).querySelector(selector); }
	function qsa(root, selector){ return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
	function settings(){ return window.AomarkListingsSettings || {}; }
	function escapeHtml(value) {
		return String(value == null ? '' : value).replace(/[&<>'"]/g, function(char){
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char];
		});
	}

	function serializeForm(form) {
		var params = new URLSearchParams();
		new FormData(form).forEach(function(value, key){
			if (String(value).trim() !== '') { params.append(key, value); }
		});
		return params.toString();
	}

	function currentFilters() {
		return window.location.search ? window.location.search.substring(1) : '';
	}

	function getConfig(widget) {
		try { return JSON.parse(widget.getAttribute('data-config') || '{}'); } catch(e) { return {}; }
	}

	function buildBody(widget, page, filters, sort) {
		var body = new URLSearchParams();
		body.append('action', 'aomark_listings_results');
		body.append('nonce', settings().nonce || '');
		body.append('page', page || 1);
		body.append('settings', JSON.stringify(getConfig(widget)));
		body.append('filters', filters || currentFilters());
		if (sort) { body.append('sort', sort); }
		return body;
	}

	function updateUrl(filters, actionUrl) {
		if (!window.history || !actionUrl) { return; }
		var url = new URL(actionUrl, window.location.href);
		url.search = filters ? '?' + filters : '';
		window.history.pushState({ aomarkListings: true }, '', url.toString());
	}

	function updateMaps(items) {
		if (!Array.isArray(items)) { return; }
		qsa(document, '.aomark-listings-map[data-query-map="yes"]').forEach(function(el){
			if (window.AomarkListings && window.AomarkListings.renderMap) {
				window.AomarkListings.renderMap(el, items);
			}
		});
	}

	function loadWidget(widget, options) {
		options = options || {};
		var inner = qs(widget, '[data-aomark-listings-results-inner]');
		if (!inner) { return; }
		widget.classList.add('is-loading');
		widget.setAttribute('aria-busy', 'true');
		fetch(settings().ajaxUrl || '', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: buildBody(widget, options.page || 1, options.filters || currentFilters(), options.sort || '')
		}).then(function(response){
			return response.json();
		}).then(function(json){
			if (!json || !json.success || !json.data) { throw new Error('Bad response'); }
			if (options.append) {
				var tmp = document.createElement('div');
				tmp.innerHTML = json.data.html || '';
				var oldGrid = qs(inner, '.aomark-listings-grid');
				var newGrid = qs(tmp, '.aomark-listings-grid');
				if (oldGrid && newGrid) {
					qsa(newGrid, '.aomark-listings-card').forEach(function(card){ oldGrid.appendChild(card); });
				} else {
					inner.innerHTML = json.data.html || '';
				}
				var oldActions = qs(inner, '.aomark-listings-results__actions');
				var newActions = qs(tmp, '.aomark-listings-results__actions');
				if (oldActions) { oldActions.remove(); }
				if (newActions) { inner.appendChild(newActions); }
			} else {
				inner.innerHTML = json.data.html || '';
			}
			updateMaps(json.data.mapItems || []);
		}).catch(function(){
			inner.insertAdjacentHTML('afterbegin', '<div class="aomark-listings-empty">Unable to load listings.</div>');
		}).finally(function(){
			widget.classList.remove('is-loading');
			widget.setAttribute('aria-busy', 'false');
		});
	}

	function loadAll(filters, actionUrl, sort) {
		var widgets = qsa(document, '.aomark-listings-results[data-ajax="yes"]');
		if (!widgets.length) { return false; }
		updateUrl(filters, actionUrl);
		widgets.forEach(function(widget){ loadWidget(widget, { page: 1, filters: filters, sort: sort || '' }); });
		return true;
	}

	document.addEventListener('submit', function(event){
		var form = event.target;
		if (!form || !form.classList || !form.classList.contains('aomark-listings-filter__form')) { return; }
		if (!document.querySelector('.aomark-listings-results[data-ajax="yes"]')) { return; }
		event.preventDefault();
		loadAll(serializeForm(form), form.getAttribute('action') || window.location.href);
	}, true);

	document.addEventListener('click', function(event){
		var reset = event.target.closest && event.target.closest('.aomark-listings-reset');
		if (reset) {
			var resetForm = reset.closest('.aomark-listings-filter__form');
			if (!resetForm) { return; }
			resetForm.reset();
			var resetFilters = serializeForm(resetForm);
			if (!loadAll(resetFilters, resetForm.getAttribute('action') || window.location.href)) {
				resetForm.submit();
			}
			return;
		}

		var pageButton = event.target.closest && event.target.closest('.aomark-listings-pagination button[data-page]');
		if (pageButton) {
			var widget = pageButton.closest('.aomark-listings-results');
			if (widget && widget.getAttribute('data-ajax') === 'yes') {
				event.preventDefault();
				loadWidget(widget, { page: pageButton.getAttribute('data-page') || 1 });
			}
			return;
		}

		var more = event.target.closest && event.target.closest('.aomark-listings-load-more');
		if (more) {
			var parent = more.closest('.aomark-listings-results');
			if (parent && parent.getAttribute('data-ajax') === 'yes') {
				event.preventDefault();
				loadWidget(parent, { page: more.getAttribute('data-page') || 1, append: true });
			}
		}
	});

	document.addEventListener('change', function(event){
		if (!event.target || !event.target.matches('[data-aomark-listings-sort]')) { return; }
		var widget = event.target.closest('.aomark-listings-results');
		if (!widget || widget.getAttribute('data-ajax') !== 'yes') { return; }
		loadWidget(widget, { page: 1, sort: event.target.value || '' });
	});

	function popupHtml(item, config) {
		var html = '<div class="aomark-listings-popup">';
		if (config.popupImage !== false && item.image) { html += '<img src="' + escapeHtml(item.image) + '" alt="">'; }
		html += '<strong><a href="' + escapeHtml(item.url) + '">' + escapeHtml(item.title) + '</a></strong>';
		if (config.popupPrice !== false && item.price) { html += '<div class="aomark-listings-popup__price">' + escapeHtml(item.price) + '</div>'; }
		if (config.popupAddress !== false && item.address) { html += '<small class="aomark-listings-popup__address">' + escapeHtml(item.address) + '</small>'; }
		return html + '</div>';
	}

	window.AomarkListings = window.AomarkListings || {};
	window.AomarkListings.renderMap = function(el, forcedItems) {
		if (!window.L || !el) { return; }
		var config = {};
		try { config = JSON.parse(el.getAttribute('data-map-config') || '{}'); } catch(e) {}
		var items = forcedItems || config.items || [];
		if (el._aomarkMap) {
			el._aomarkMarkers.forEach(function(marker){ marker.remove(); });
			el._aomarkMarkers = [];
		}
		if (!items.length) { return; }
		if (!el._aomarkMap) {
			el._aomarkMap = L.map(el, {
				scrollWheelZoom: config.scrollWheelZoom === true,
				zoomControl: config.zoomControl !== false,
				dragging: config.dragging !== false
			});
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; OpenStreetMap'
			}).addTo(el._aomarkMap);
			el._aomarkMarkers = [];
		}
		var bounds = [];
		items.forEach(function(item){
			if (typeof item.lat === 'undefined' || typeof item.lng === 'undefined') { return; }
			var marker = L.marker([item.lat, item.lng]).addTo(el._aomarkMap);
			marker.bindPopup(popupHtml(item, config));
			el._aomarkMarkers.push(marker);
			bounds.push([item.lat, item.lng]);
		});
		if (bounds.length > 1 && config.fitBounds !== false) {
			el._aomarkMap.fitBounds(bounds, { padding: [28, 28], maxZoom: 14 });
		} else if (bounds.length >= 1) {
			el._aomarkMap.setView(bounds[0], config.zoom || 10);
		}
		setTimeout(function(){ el._aomarkMap.invalidateSize(); }, 80);
	};

	function initMaps() {
		qsa(document, '.aomark-listings-map').forEach(function(el){
			var config = {};
			try { config = JSON.parse(el.getAttribute('data-map-config') || '{}'); } catch(e) {}
			if (config.queryMap) { el.setAttribute('data-query-map', 'yes'); }
			window.AomarkListings.renderMap(el);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initMaps);
	} else {
		initMaps();
	}

	window.addEventListener('elementor/frontend/init', function(){
		setTimeout(initMaps, 120);
	});
})();
