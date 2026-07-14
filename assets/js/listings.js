(function(){
	'use strict';

	if (window.AomarkListingsReady) { return; }
	window.AomarkListingsReady = true;

	var resultStates = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
	var mapCounter = 0;
	var elementorHooksRegistered = false;
	var lifecycleObserver = null;
	var historyIndex = null;
	var historySnapshots = {};

	function qs(root, selector){ return (root || document).querySelector(selector); }
	function qsa(root, selector){ return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
	function settings(){ return window.AomarkListingsSettings || {}; }
	function message(key, fallback) {
		var messages = settings().i18n || {};
		return messages[key] || fallback;
	}
	function escapeHtml(value) {
		return String(value == null ? '' : value).replace(/[&<>'"]/g, function(char){
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char];
		});
	}
	function parseJson(value) {
		try { return JSON.parse(value || '{}'); } catch(e) { return {}; }
	}
	function dispatch(name, target, detail) {
		var event;
		if (typeof window.CustomEvent === 'function') {
			event = new CustomEvent(name, { bubbles: true, detail: detail || {} });
		} else {
			event = document.createEvent('CustomEvent');
			event.initCustomEvent(name, true, false, detail || {});
		}
		(target || document).dispatchEvent(event);
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
		return parseJson(widget.getAttribute('data-config'));
	}

	function getComponent(element) {
		return element && element.closest ? element.closest('[data-aomark-component]') : null;
	}

	function componentValue(element, name) {
		var component = getComponent(element);
		if (component) { return component.getAttribute(name) || ''; }
		return element && element.getAttribute ? element.getAttribute(name) || '' : '';
	}

	function widgetModel(widget) {
		return componentValue(widget, 'data-model-id') || getConfig(widget).model_id || '';
	}

	function filtersForWidget(widget, rawFilters) {
		var params = new URLSearchParams(rawFilters || '');
		var model = widgetModel(widget);
		var urlModel = params.get('alm_model') || '';
		if (model && urlModel && model !== urlModel) {
			params = new URLSearchParams();
		}
		if (model) { params.set('alm_model', model); }
		return params.toString();
	}

	function resultState(widget) {
		var state = resultStates ? resultStates.get(widget) : widget._aomarkListingsState;
		if (state) { return state; }

		var sort = qs(widget, '[data-aomark-listings-sort]');
		var scopedFilters = filtersForWidget(widget, currentFilters());
		var params = new URLSearchParams(scopedFilters);
		state = {
			controller: null,
			filters: scopedFilters,
			initialMapSync: false,
			lastOptions: {},
			maxPages: 0,
			page: Math.max(1, parseInt(params.get('alm_page') || '1', 10) || 1),
			requestSequence: 0,
			sort: sort ? sort.value : (params.get('alm_sort') || '')
		};

		if (resultStates) { resultStates.set(widget, state); }
		else { widget._aomarkListingsState = state; }
		return state;
	}

	function buildBody(widget, state) {
		var body = new URLSearchParams();
		var mapDescriptor = mapDescriptorFor(widget);
		body.append('action', 'aomark_listings_results');
		body.append('nonce', settings().nonce || '');
		body.append('page', state.page || 1);
		// The descriptor may be a signed object. Repost it without inspecting or
		// rebuilding it so the server remains the authority for widget settings.
		body.append('settings', JSON.stringify(getConfig(widget)));
		body.append('filters', state.filters || '');
		if (state.sort) { body.append('sort', state.sort); }
		if (mapDescriptor) { body.append('map_descriptor', mapDescriptor); }
		return body;
	}

	function isSameDocumentAction(actionUrl) {
		try {
			var target = new URL(actionUrl || window.location.href, window.location.href);
			return target.origin === window.location.origin && target.pathname === window.location.pathname;
		} catch(e) {
			return false;
		}
	}

	function historyUrl(filters, actionUrl, state, page) {
		var url = new URL(actionUrl || window.location.href, window.location.href);
		var params = new URLSearchParams(filters || '');
		params.delete('alm_page');
		if (state && state.sort) { params.set('alm_sort', state.sort); }
		else { params.delete('alm_sort'); }
		if (page && page > 1) { params.set('alm_page', String(page)); }
		url.search = params.toString() ? '?' + params.toString() : '';
		return url;
	}

	function widgetContext(widget) {
		return {
			connection: componentValue(widget, 'data-aomark-connection').substring(0, 120),
			model: widgetModel(widget).substring(0, 120)
		};
	}

	function snapshotKey(context) {
		if (!context || !context.model) { return ''; }
		return 'model:' + encodeURIComponent(context.model) + '|connection:' + encodeURIComponent(context.connection || '');
	}

	function normalizeSnapshot(value) {
		if (!value || typeof value !== 'object') { return null; }
		var filters = typeof value.filters === 'string' ? value.filters.substring(0, 8192) : '';
		var page = Math.max(1, Math.min(200, parseInt(value.page, 10) || 1));
		var allowedSorts = ['date_desc', 'date_asc', 'title_asc', 'title_desc', 'price_asc', 'price_desc'];
		var sort = allowedSorts.indexOf(value.sort) !== -1 ? value.sort : '';
		return { filters: filters, page: page, sort: sort };
	}

	function cloneSnapshots(source) {
		var copy = {};
		var count = 0;
		if (!source || typeof source !== 'object') { return copy; }
		Object.keys(source).forEach(function(key){
			if (count >= 20 || key.length > 300 || key.indexOf('model:') !== 0) { return; }
			var snapshot = normalizeSnapshot(source[key]);
			if (!snapshot) { return; }
			copy[key] = snapshot;
			count += 1;
		});
		return copy;
	}

	function snapshotForWidget(widget, state, filters, page, sort) {
		var key = snapshotKey(widgetContext(widget));
		if (!key) { return null; }
		var snapshot = normalizeSnapshot({
			filters: Object.prototype.hasOwnProperty.call(arguments, 2) ? filters : state.filters,
			page: Object.prototype.hasOwnProperty.call(arguments, 3) ? page : state.page,
			sort: Object.prototype.hasOwnProperty.call(arguments, 4) ? sort : state.sort
		});
		historySnapshots[key] = snapshot;
		return snapshot;
	}

	function historyState(base, index, context) {
		var next = {};
		if (base && typeof base === 'object') {
			Object.keys(base).forEach(function(key){ next[key] = base[key]; });
		}
		next.aomarkListings = true;
		next.aomarkListingsIndex = index;
		next.aomarkListingsSnapshots = cloneSnapshots(historySnapshots);
		next.connection = context ? context.connection : (next.connection || '');
		next.model = context ? context.model : (next.model || '');
		return next;
	}

	function ensureHistoryState() {
		if (historyIndex !== null || !window.history || !window.history.replaceState) { return; }
		var current = window.history.state || {};
		var parsedIndex = parseInt(current.aomarkListingsIndex, 10);
		historyIndex = isNaN(parsedIndex) ? 0 : parsedIndex;
		historySnapshots = cloneSnapshots(current.aomarkListingsSnapshots);
		window.history.replaceState(historyState(current, historyIndex, null), '', window.location.href);
	}

	function replaceCurrentHistoryState() {
		if (!window.history || !window.history.replaceState) { return; }
		ensureHistoryState();
		window.history.replaceState(historyState(window.history.state || {}, historyIndex, null), '', window.location.href);
	}

	function updateUrl(filters, actionUrl, state, page, replace) {
		if (!window.history || !window.history.pushState) { return; }
		ensureHistoryState();
		var context = state && state.widget ? widgetContext(state.widget) : { connection: '', model: '' };
		if (state && state.widget) {
			filters = filtersForWidget(state.widget, filters || '');
			page = Math.max(1, Math.min(200, parseInt(page, 10) || 1));
			state.filters = filters;
			state.page = page;
			snapshotForWidget(state.widget, state, filters, page, state.sort || '');
		}
		var url = historyUrl(filters, actionUrl, state, page);
		var nextIndex = replace ? historyIndex : historyIndex + 1;
		var nextState = historyState(window.history.state || {}, nextIndex, context);
		window.history[replace ? 'replaceState' : 'pushState'](nextState, '', url.toString());
		historyIndex = nextIndex;
	}

	function resultWidgets(root) {
		var widgets = qsa(root || document, '.aomark-listings-results[data-ajax="yes"]');
		if (root && root.matches && root.matches('.aomark-listings-results[data-ajax="yes"]')) {
			widgets.unshift(root);
		}
		return widgets.filter(function(widget, index, all){ return all.indexOf(widget) === index; });
	}

	function targetResults(form) {
		var integrated = form.closest('.aomark-listings-results[data-ajax="yes"]');
		if (integrated) { return [integrated]; }

		var connection = componentValue(form, 'data-aomark-connection');
		var model = componentValue(form, 'data-model-id') || ((qs(form, '[name="alm_model"]') || {}).value || '');
		return resultWidgets(document).filter(function(widget){
			var widgetConnection = componentValue(widget, 'data-aomark-connection');
			var resultModel = widgetModel(widget);
			var sameModel = !model || !resultModel || model === resultModel;
			if (connection || widgetConnection) { return sameModel && connection !== '' && connection === widgetConnection; }
			return model !== '' && model === resultModel;
		});
	}

	function matchesMap(widget, mapTarget) {
		var resultConnection = componentValue(widget, 'data-aomark-connection');
		var bareMap = mapTarget.matches && mapTarget.matches('.aomark-listings-map');
		var mapConnection = bareMap ? '' : (mapTarget.getAttribute('data-aomark-connection') || '');
		var resultModel = widgetModel(widget);
		var mapModel = mapTarget.getAttribute('data-model-id') || '';
		var sameModel = !resultModel || !mapModel || resultModel === mapModel;
		if (resultConnection || mapConnection) {
			return sameModel && resultConnection !== '' && resultConnection === mapConnection;
		}
		return sameModel && resultModel !== '' && resultModel === mapModel;
	}

	function mapComponentsFor(widget) {
		var components = qsa(document, '[data-aomark-component="map"][data-map-source="query"]');
		var bareMaps = qsa(document, '.aomark-listings-map[data-query-map="yes"]').filter(function(element){
			return !mapComponent(element);
		});
		return components.concat(bareMaps).filter(function(target){ return matchesMap(widget, target); });
	}

	function mapDescriptorFor(widget) {
		var components = mapComponentsFor(widget);
		for (var index = 0; index < components.length; index += 1) {
			var component = components[index];
			var element = component.matches && component.matches('.aomark-listings-map') ? component : qs(component, '.aomark-listings-map');
			var rawDescriptor = (element && element.getAttribute('data-map-query')) || component.getAttribute('data-map-query') || '';
			if (rawDescriptor) { return rawDescriptor; }
			var descriptor = element ? mapConfig(element).ajaxDescriptor : null;
			if (descriptor && typeof descriptor === 'object' && descriptor.v) { return JSON.stringify(descriptor); }
		}
		return null;
	}

	function statusNode(widget) {
		var component = getComponent(widget);
		return component ? qs(component, '[data-aomark-listings-status]') : null;
	}

	function announce(widget, text, focus) {
		var status = statusNode(widget);
		if (!status) { return; }
		status.textContent = '';
		window.setTimeout(function(){
			status.textContent = text || '';
			if (focus) {
				status.setAttribute('tabindex', '-1');
				status.focus();
			}
		}, 20);
	}

	function removeError(widget) {
		qsa(widget, '[data-aomark-listings-error]').forEach(function(error){ error.remove(); });
	}

	function showError(widget, error) {
		var inner = qs(widget, '[data-aomark-listings-results-inner]');
		if (!inner) { return; }
		removeError(widget);
		var notice = document.createElement('div');
		notice.className = 'aomark-listings-empty aomark-listings-error';
		notice.setAttribute('data-aomark-listings-error', '');
		notice.setAttribute('role', 'alert');
		notice.innerHTML = escapeHtml(message('loadError', 'Unable to load listings.')) + ' <button type="button" class="aomark-listings-retry">' + escapeHtml(message('retry', 'Try again')) + '</button>';
		inner.insertBefore(notice, inner.firstChild);
		announce(widget, message('loadError', 'Unable to load listings.'), false);
		dispatch('aomark:listings:error', widget, { error: error || null });
	}

	function applyResponse(widget, data, append) {
		var inner = qs(widget, '[data-aomark-listings-results-inner]');
		if (!inner) { return; }

		if (append) {
			var tmp = document.createElement('div');
			tmp.innerHTML = data.html || '';
			var oldGrid = qs(inner, '.aomark-listings-grid');
			var newGrid = qs(tmp, '.aomark-listings-grid');
			if (oldGrid && newGrid) {
				qsa(newGrid, '.aomark-listings-card').forEach(function(card){ oldGrid.appendChild(card); });
			} else {
				inner.innerHTML = data.html || '';
			}
			var oldActions = qs(inner, '.aomark-listings-results__actions');
			var newActions = qs(tmp, '.aomark-listings-results__actions');
			if (oldActions) { oldActions.remove(); }
			if (newActions) { inner.appendChild(newActions); }
		} else {
			inner.innerHTML = data.html || '';
		}
	}

	function updateMaps(widget, items) {
		if (!Array.isArray(items)) { return; }
		mapComponentsFor(widget).forEach(function(target){
			if (target.matches && target.matches('.aomark-listings-map')) {
				window.AomarkListings.renderMap(target, items);
			} else {
				updateMapComponent(target, items);
			}
		});
	}

	function loadWidget(widget, options) {
		options = options || {};
		var mapOnly = options.mapOnly === true;
		var inner = qs(widget, '[data-aomark-listings-results-inner]');
		if (!inner) { return Promise.resolve(false); }

		var state = resultState(widget);
		state.widget = widget;
		if (Object.prototype.hasOwnProperty.call(options, 'filters')) { state.filters = options.filters || ''; }
		if (Object.prototype.hasOwnProperty.call(options, 'sort')) { state.sort = options.sort || ''; }
		if (Object.prototype.hasOwnProperty.call(options, 'page')) { state.page = Math.max(1, parseInt(options.page, 10) || 1); }
		if (!mapOnly) {
			state.lastOptions = {
				append: options.append === true,
				filters: state.filters,
				page: state.page,
				sort: state.sort
			};
		}
		state.requestSequence += 1;
		var sequence = state.requestSequence;

		if (state.controller) { state.controller.abort(); }
		state.controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
		if (!mapOnly) {
			removeError(widget);
			widget.classList.add('is-loading');
			widget.setAttribute('aria-busy', 'true');
			dispatch('aomark:listings:request', widget, { page: state.page, append: options.append === true });
		}

		var fetchOptions = {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: buildBody(widget, state)
		};
		if (state.controller) { fetchOptions.signal = state.controller.signal; }

		return fetch(settings().ajaxUrl || '', fetchOptions).then(function(response){
			if (!response.ok) { throw new Error('HTTP ' + response.status); }
			return response.json();
		}).then(function(json){
			if (sequence !== state.requestSequence) { return false; }
			if (!json || !json.success || !json.data) {
				var responseMessage = json && json.data && json.data.message ? json.data.message : 'Bad response';
				throw new Error(responseMessage);
			}

			if (!mapOnly) {
				applyResponse(widget, json.data, options.append === true);
				state.page = Math.max(1, parseInt(json.data.page || state.page, 10) || 1);
				state.maxPages = Math.max(0, parseInt(json.data.maxPages || json.data.max_pages || 0, 10) || 0);
			}
			if (Object.prototype.hasOwnProperty.call(json.data, 'mapItems')) {
				updateMaps(widget, Array.isArray(json.data.mapItems) ? json.data.mapItems : []);
			}

			if (!mapOnly) {
				var total = parseInt(json.data.total, 10);
				var text = isNaN(total) ? message('resultsUpdated', 'Listings updated.') : message('resultsCount', '%d listings found.').replace('%d', String(total));
				announce(widget, text, options.focusStatus === true);
				dispatch('aomark:listings:loaded', widget, {
					append: options.append === true,
					maxPages: state.maxPages,
					page: state.page,
					total: isNaN(total) ? null : total
				});
			}
			// "Load more" appends a partial page without changing the URL. Keep the
			// current history snapshot on the complete, URL-addressable result state.
			if (options.append !== true) {
				snapshotForWidget(widget, state);
				replaceCurrentHistoryState();
			}
			return true;
		}).catch(function(error){
			if (error && error.name === 'AbortError') { return false; }
			if (!mapOnly && sequence === state.requestSequence) { showError(widget, error); }
			return false;
		}).then(function(result){
			if (sequence === state.requestSequence) {
				if (!mapOnly) {
					widget.classList.remove('is-loading');
					widget.setAttribute('aria-busy', 'false');
				}
				state.controller = null;
			}
			return result;
		});
	}

	function loadTargets(widgets, filters, actionUrl, options) {
		options = options || {};
		if (!widgets.length) { return false; }
		var firstState = resultState(widgets[0]);
		firstState.widget = widgets[0];
		if (Object.prototype.hasOwnProperty.call(options, 'sort')) { firstState.sort = options.sort || ''; }
		if (options.updateHistory !== false) { updateUrl(filters, actionUrl, firstState, options.page || 1, false); }
		widgets.forEach(function(widget){
			loadWidget(widget, {
				filters: filters,
				focusStatus: options.focusStatus === true,
				page: options.page || 1,
				sort: Object.prototype.hasOwnProperty.call(options, 'sort') ? options.sort : resultState(widget).sort
			});
		});
		return true;
	}

	function syncForms(filters, connection, model) {
		var params = new URLSearchParams(filters || '');
		qsa(document, '.aomark-listings-filter__form').forEach(function(form){
			var formConnection = componentValue(form, 'data-aomark-connection');
			var formModel = componentValue(form, 'data-model-id') || ((qs(form, '[name="alm_model"]') || {}).value || '');
			if (model && model !== formModel) { return; }
			if (connection && connection !== formConnection) { return; }
			Array.prototype.slice.call(form.elements || []).forEach(function(field){
				if (!field.name || field.type === 'submit' || field.type === 'button') { return; }
				var values = params.getAll(field.name);
				if (field.type === 'checkbox' || field.type === 'radio') {
					field.checked = values.indexOf(field.value) !== -1;
				} else if (field.tagName === 'SELECT' && field.multiple) {
					Array.prototype.slice.call(field.options).forEach(function(option){ option.selected = values.indexOf(option.value) !== -1; });
				} else if (field.type !== 'hidden' || field.name !== 'alm_model') {
					field.value = values.length ? values[0] : '';
				}
			});
		});
	}

	document.addEventListener('submit', function(event){
		var form = event.target;
		if (!form || !form.classList || !form.classList.contains('aomark-listings-filter__form')) { return; }
		if (!isSameDocumentAction(form.getAttribute('action') || window.location.href)) { return; }
		var widgets = targetResults(form);
		if (!widgets.length) { return; }
		event.preventDefault();
		loadTargets(widgets, serializeForm(form), form.getAttribute('action') || window.location.href, { focusStatus: false });
	}, true);

	document.addEventListener('click', function(event){
		var reset = event.target.closest && event.target.closest('.aomark-listings-reset');
		if (reset) {
			var resetForm = reset.closest('.aomark-listings-filter__form');
			if (!resetForm) { return; }
			Array.prototype.slice.call(resetForm.elements || []).forEach(function(field){
				if (!field.name || (field.type === 'hidden' && field.name === 'alm_model') || field.type === 'submit' || field.type === 'button') { return; }
				if (field.type === 'checkbox' || field.type === 'radio') {
					field.checked = false;
				} else if (field.tagName === 'SELECT') {
					field.selectedIndex = 0;
				} else {
					field.value = '';
				}
			});
			var resetTargets = targetResults(resetForm);
			if (resetTargets.length && isSameDocumentAction(resetForm.getAttribute('action') || window.location.href)) {
				event.preventDefault();
				loadTargets(resetTargets, serializeForm(resetForm), resetForm.getAttribute('action') || window.location.href, { focusStatus: false });
			} else {
				resetForm.submit();
			}
			return;
		}

		var retry = event.target.closest && event.target.closest('.aomark-listings-retry');
		if (retry) {
			var retryWidget = retry.closest('.aomark-listings-results');
			if (retryWidget) { event.preventDefault(); loadWidget(retryWidget, resultState(retryWidget).lastOptions); }
			return;
		}

		var pageButton = event.target.closest && event.target.closest('.aomark-listings-pagination [data-page]');
		if (pageButton) {
			var widget = pageButton.closest('.aomark-listings-results');
			if (widget && widget.getAttribute('data-ajax') === 'yes') {
				event.preventDefault();
				var page = Math.max(1, parseInt(pageButton.getAttribute('data-page') || '1', 10) || 1);
				var state = resultState(widget);
				state.widget = widget;
				updateUrl(state.filters, window.location.href, state, page, false);
				loadWidget(widget, { page: page, focusStatus: true });
			}
			return;
		}

		var more = event.target.closest && event.target.closest('.aomark-listings-load-more');
		if (more) {
			var parent = more.closest('.aomark-listings-results');
			if (parent && parent.getAttribute('data-ajax') === 'yes') {
				event.preventDefault();
				loadWidget(parent, { page: more.getAttribute('data-page') || 1, append: true, focusStatus: false });
			}
		}
	});

	document.addEventListener('change', function(event){
		if (!event.target || !event.target.matches('[data-aomark-listings-sort]')) { return; }
		var widget = event.target.closest('.aomark-listings-results');
		if (!widget) { return; }
		if (widget.getAttribute('data-ajax') !== 'yes') {
			var sortForm = event.target.closest('form');
			if (sortForm) { sortForm.submit(); }
			return;
		}
		var state = resultState(widget);
		state.widget = widget;
		state.sort = event.target.value || '';
		updateUrl(state.filters, window.location.href, state, 1, false);
		loadWidget(widget, { page: 1, sort: state.sort, focusStatus: false });
	});

	window.addEventListener('popstate', function(event){
		ensureHistoryState();
		var targetState = event.state || {};
		var targetIndex = parseInt(targetState.aomarkListingsIndex, 10);
		targetIndex = isNaN(targetIndex) ? historyIndex : targetIndex;
		historyIndex = targetIndex;
		var targetSnapshots = cloneSnapshots(targetState.aomarkListingsSnapshots);
		var widgets = resultWidgets(document);

		if (Object.keys(targetSnapshots).length) {
			historySnapshots = targetSnapshots;
			widgets.forEach(function(widget){
				var context = widgetContext(widget);
				var snapshot = targetSnapshots[snapshotKey(context)];
				if (!snapshot) { return; }
				var state = resultState(widget);
				state.widget = widget;
				syncForms(snapshot.filters, context.connection, context.model);
				if (state.filters === snapshot.filters && state.page === snapshot.page && state.sort === snapshot.sort) { return; }
				loadWidget(widget, {
					filters: snapshot.filters,
					focusStatus: false,
					page: snapshot.page,
					sort: snapshot.sort
				});
			});
		} else {
			var filters = currentFilters();
			var params = new URLSearchParams(filters);
			var model = targetState.model || params.get('alm_model') || '';
			var connection = targetState.connection || '';
			widgets.forEach(function(widget){
				var context = widgetContext(widget);
				if (!model || context.model !== model || (connection && context.connection !== connection)) { return; }
				var scopedFilters = filtersForWidget(widget, filters);
				var scopedParams = new URLSearchParams(scopedFilters);
				var snapshot = normalizeSnapshot({
					filters: scopedFilters,
					page: scopedParams.get('alm_page') || 1,
					sort: scopedParams.get('alm_sort') || ''
				});
				historySnapshots[snapshotKey(context)] = snapshot;
				syncForms(snapshot.filters, context.connection, context.model);
				loadWidget(widget, { filters: snapshot.filters, page: snapshot.page, sort: snapshot.sort, focusStatus: false });
			});
		}
		replaceCurrentHistoryState();
	});

	function popupHtml(item, config) {
		var html = '<div class="aomark-listings-popup">';
		if (config.popupImage !== false && item.image) { html += '<img src="' + escapeHtml(item.image) + '" alt="">'; }
		html += '<strong><a href="' + escapeHtml(item.url) + '">' + escapeHtml(item.title) + '</a></strong>';
		if (config.popupPrice !== false && item.price) { html += '<div class="aomark-listings-popup__price">' + escapeHtml(item.price) + '</div>'; }
		if (config.popupAddress !== false && item.address) { html += '<small class="aomark-listings-popup__address">' + escapeHtml(item.address) + '</small>'; }
		return html + '</div>';
	}

	function mapComponent(element) {
		return element && element.closest ? element.closest('[data-aomark-component="map"]') : null;
	}

	function mapOwner(element) {
		return mapComponent(element) || (element && element.closest ? element.closest('.aomark-listings-map-wrap') : null);
	}

	function mapConfig(element) {
		var component = mapComponent(element);
		var outer = component ? parseJson(component.getAttribute('data-map-config')) : {};
		var inner = element ? parseJson(element.getAttribute('data-map-config')) : {};
		Object.keys(inner).forEach(function(key){ outer[key] = inner[key]; });
		return outer;
	}

	function mapEmptyNode(component) {
		if (!component) { return null; }
		var empty = qs(component, '[data-aomark-map-empty]') || qs(component, '[data-aomark-listings-map-empty]') || qs(component, '.aomark-listings-empty');
		if (!empty) {
			empty = document.createElement('div');
			empty.className = 'aomark-listings-empty';
			empty.textContent = component.getAttribute('data-empty-message') || message('mapEmpty', 'No mapped listings found.');
			component.appendChild(empty);
		}
		empty.setAttribute('data-aomark-map-empty', '');
		empty.setAttribute('role', 'status');
		empty.setAttribute('aria-live', 'polite');
		return empty;
	}

	function showMapEmpty(component, element) {
		if (element) {
			element.hidden = true;
			element.setAttribute('aria-hidden', 'true');
		}
		var empty = mapEmptyNode(component);
		if (empty) { empty.hidden = false; }
	}

	function showMapCanvas(component, element) {
		var empty = mapEmptyNode(component);
		if (empty) { empty.hidden = true; }
		element.hidden = false;
		element.removeAttribute('aria-hidden');
	}

	function ensureMapElement(component) {
		var element = qs(component, '.aomark-listings-map');
		if (element) { return element; }
		var wrap = document.createElement('div');
		wrap.className = 'aomark-listings-map-wrap';
		element = document.createElement('div');
		element.id = 'aomark-listings-map-dynamic-' + (++mapCounter);
		element.className = 'aomark-listings-map';
		element.setAttribute('data-map-config', component.getAttribute('data-map-config') || '{}');
		wrap.appendChild(element);
		component.appendChild(wrap);
		return element;
	}

	window.AomarkListings = window.AomarkListings || {};
	window.AomarkListings.renderMap = function(element, forcedItems) {
		if (!element) { return; }
		if (Array.isArray(forcedItems)) { element._aomarkItems = forcedItems; }
		if (!window.L) { return; }
		var config = mapConfig(element);
		var items = Array.isArray(forcedItems) ? forcedItems : (Array.isArray(element._aomarkItems) ? element._aomarkItems : (Array.isArray(config.items) ? config.items : []));
		var component = mapOwner(element);
		element._aomarkItems = items;

		element.setAttribute('role', 'region');
		if (!element.getAttribute('aria-label')) { element.setAttribute('aria-label', message('mapLabel', 'Listing map')); }
		if (element._aomarkMap) {
			(element._aomarkMarkers || []).forEach(function(marker){ marker.remove(); });
			element._aomarkMarkers = [];
		}

		if (!items.length) {
			showMapEmpty(component, element);
			dispatch('aomark:listings:map-updated', element, { count: 0 });
			return;
		}

		showMapCanvas(component, element);
		if (!element._aomarkMap) {
			element._aomarkMap = window.L.map(element, {
				scrollWheelZoom: config.scrollWheelZoom === true,
				zoomControl: config.zoomControl !== false,
				dragging: config.dragging !== false
			});
			window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
			}).addTo(element._aomarkMap);
			element._aomarkMarkers = [];
		}

		var bounds = [];
		items.forEach(function(item){
			var lat = Number(item.lat);
			var lng = Number(item.lng);
			if (!isFinite(lat) || !isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) { return; }
			var markerTitle = String(item.title || '');
			var marker = window.L.marker([lat, lng], { title: markerTitle, alt: markerTitle }).addTo(element._aomarkMap);
			marker.bindPopup(popupHtml(item, config));
			element._aomarkMarkers.push(marker);
			bounds.push([lat, lng]);
		});

		if (!bounds.length) {
			showMapEmpty(component, element);
			dispatch('aomark:listings:map-updated', element, { count: 0 });
			return;
		}
		if (bounds.length > 1 && config.fitBounds !== false) {
			element._aomarkMap.fitBounds(bounds, { padding: [28, 28], maxZoom: 14 });
		} else {
			element._aomarkMap.setView(bounds[0], config.zoom || 10);
		}
		window.setTimeout(function(){ element._aomarkMap.invalidateSize(); }, 80);
		dispatch('aomark:listings:map-updated', element, { count: bounds.length });
	};

	function updateMapComponent(component, items) {
		var existing = qs(component, '.aomark-listings-map');
		if (!items.length && !existing) {
			showMapEmpty(component, null);
			return;
		}
		var element = existing || ensureMapElement(component);
		window.AomarkListings.renderMap(element, items);
	}

	function initResults(root) {
		resultWidgets(root || document).forEach(function(widget){
			var state = resultState(widget);
			var context = widgetContext(widget);
			var key = snapshotKey(context);
			var saved = key ? historySnapshots[key] : null;
			var restoring = false;
			state.widget = widget;
			widget.setAttribute('aria-busy', 'false');

			if (saved) {
				syncForms(saved.filters, context.connection, context.model);
				if (state.filters !== saved.filters || state.page !== saved.page || state.sort !== saved.sort) {
					restoring = true;
					state.initialMapSync = true;
					loadWidget(widget, { filters: saved.filters, page: saved.page, sort: saved.sort, focusStatus: false });
				}
			} else if (key) {
				snapshotForWidget(widget, state);
			}

			if (!restoring && !state.initialMapSync && widget.getAttribute('data-map-sync') === 'yes' && mapComponentsFor(widget).length && mapDescriptorFor(widget)) {
				state.initialMapSync = true;
				loadWidget(widget, {
					filters: state.filters,
					mapOnly: true,
					page: state.page,
					sort: state.sort
				});
			}
		});
		replaceCurrentHistoryState();
	}

	function initMaps(root) {
		var scope = root || document;
		qsa(scope, '[data-aomark-component="map"]').forEach(function(component){
			var element = qs(component, '.aomark-listings-map');
			if (element) {
				var config = mapConfig(element);
				if (config.queryMap) { element.setAttribute('data-query-map', 'yes'); }
				window.AomarkListings.renderMap(element);
			} else {
				mapEmptyNode(component);
			}
		});
		// Backwards compatibility for maps rendered outside Elementor wrappers.
		qsa(scope, '.aomark-listings-map').forEach(function(element){
			if (!mapComponent(element)) { window.AomarkListings.renderMap(element); }
		});
	}

	function initScope(scope) {
		ensureHistoryState();
		var node = scope && scope[0] ? scope[0] : scope;
		if (!node || !node.querySelectorAll) { node = document; }
		initResults(node);
		initMaps(node);
	}

	function matchesAndDescendants(root, selector) {
		if (!root || root.nodeType !== 1) { return []; }
		var matches = root.matches && root.matches(selector) ? [root] : [];
		return matches.concat(qsa(root, selector));
	}

	function destroyScope(root) {
		if (root && root.isConnected) { return; }
		matchesAndDescendants(root, '.aomark-listings-results').forEach(function(widget){
			var state = resultStates ? resultStates.get(widget) : widget._aomarkListingsState;
			if (state && state.controller) { state.controller.abort(); }
		});
		matchesAndDescendants(root, '.aomark-listings-map').forEach(function(element){
			if (element._aomarkMap && element._aomarkMap.remove) {
				element._aomarkMap.remove();
				element._aomarkMap = null;
				element._aomarkMarkers = [];
			}
		});
	}

	function observeLifecycle() {
		if (lifecycleObserver || typeof MutationObserver === 'undefined' || !document.documentElement) { return; }
		lifecycleObserver = new MutationObserver(function(records){
			records.forEach(function(record){
				Array.prototype.slice.call(record.removedNodes || []).forEach(destroyScope);
			});
		});
		lifecycleObserver.observe(document.documentElement, { childList: true, subtree: true });
	}

	function registerElementorHooks() {
		if (elementorHooksRegistered || !window.elementorFrontend || !window.elementorFrontend.hooks) { return; }
		elementorHooksRegistered = true;
		[ 'aomark_listing_results', 'aomark_listing_filter', 'aomark_listing_map' ].forEach(function(widgetName){
			window.elementorFrontend.hooks.addAction('frontend/element_ready/' + widgetName + '.default', initScope);
		});
	}

	window.AomarkListings.refresh = initScope;
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function(){ initScope(document); registerElementorHooks(); observeLifecycle(); });
	} else {
		initScope(document);
		registerElementorHooks();
		observeLifecycle();
	}

	window.addEventListener('elementor/frontend/init', function(){
		registerElementorHooks();
		window.setTimeout(function(){ initScope(document); }, 0);
	});
	window.addEventListener('load', function(){ initMaps(document); });
})();
