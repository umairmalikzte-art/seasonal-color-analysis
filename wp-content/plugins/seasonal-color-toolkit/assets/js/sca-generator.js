/**
 * Seasonal Color Toolkit – palette generator and color checker UI.
 * Uses SCAColor (math) and SCAPalette (copy + PNG download).
 * Everything runs in the browser; nothing is sent to the server.
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-sca-generator]');
	var cfg = window.SCA_GEN;
	var C = window.SCAColor;
	if (!root || !cfg || !C || !cfg.seasons || !cfg.seasons.length) return;

	var seasons = {};
	cfg.seasons.forEach(function (s) { seasons[s.slug] = s; });
	var trends = cfg.trends || [];

	var els = {
		season: root.querySelector('[data-gen-season]'),
		trend: root.querySelector('[data-gen-trend]'),
		harmony: root.querySelectorAll('input[name="sca-harmony"]'),
		result: root.querySelector('[data-gen-result]'),
		trendOut: root.querySelector('[data-gen-trend-out]'),
		shuffle: root.querySelector('[data-gen-shuffle]'),
		copy: root.querySelector('[data-gen-copy]'),
		download: root.querySelector('[data-gen-download]'),
		status: root.querySelector('[data-gen-status]'),
		checkInputs: root.querySelectorAll('[data-check-color]'),
		checkText: root.querySelectorAll('[data-check-hex]'),
		checkOut: root.querySelector('[data-check-out]')
	};

	var params = new URLSearchParams(location.search);
	var state = {
		season: seasons[params.get('season')] ? params.get('season') : (cfg.defaultSeason || cfg.seasons[0].slug),
		harmony: C.HARMONIES[params.get('harmony')] ? params.get('harmony') : 'analogous',
		trend: params.get('trend') || '',
		seed: Math.floor(Math.random() * 1e9) + 1,
		locked: {},
		current: []
	};

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function ink(hex) {
		var lab = C.oklab(hex);
		return lab && lab.L > 0.68 ? '#222222' : '#ffffff';
	}
	function trendBySlug(slug) {
		for (var i = 0; i < trends.length; i++) if (trends[i].slug === slug) return trends[i];
		return null;
	}
	function say(text) {
		if (!els.status) return;
		els.status.textContent = text;
		clearTimeout(els.status._t);
		els.status._t = setTimeout(function () { els.status.textContent = ''; }, 2500);
	}
	function syncUrl() {
		var u = new URL(location.href);
		u.searchParams.set('season', state.season);
		u.searchParams.set('harmony', state.harmony);
		if (state.trend) u.searchParams.set('trend', state.trend); else u.searchParams.delete('trend');
		history.replaceState(null, '', u.toString());
	}

	/* ---------- Generate ---------- */

	function render() {
		var season = seasons[state.season];
		var trend = trendBySlug(state.trend);
		var locked = {};
		Object.keys(state.locked).forEach(function (i) { if (state.locked[i]) locked[i] = state.current[i]; });
		state.current = C.generate(season.palette, {
			harmony: state.harmony,
			base: trend ? trend.hex : null,
			seed: state.seed,
			locked: locked
		});

		var bar = '<div class="sca-gen__bar" aria-hidden="true">';
		state.current.forEach(function (c) { bar += '<span style="flex:' + c.share + ';background:' + esc(c.hex) + '"></span>'; });
		bar += '</div>';

		var cards = '<ul class="sca-gen__colors" role="list">';
		state.current.forEach(function (c, i) {
			var isLocked = !!state.locked[i];
			cards += '<li class="sca-gen__color' + (isLocked ? ' is-locked' : '') + '">';
			cards += '<button type="button" class="sca-swatch__chip sca-gen__chip" data-hex="' + esc(c.hex) + '" style="background:' + esc(c.hex) + ';color:' + ink(c.hex) + '">';
			cards += '<span class="sca-gen__role">' + esc(c.role) + '</span>';
			cards += '<span class="screen-reader-text">Copy ' + esc(c.name) + ', ' + esc(c.hex) + '</span><span class="sca-swatch__copied" aria-hidden="true">Copied</span></button>';
			cards += '<span class="sca-swatch__name">' + esc(c.name) + '</span><code class="sca-swatch__hex">' + esc(c.hex) + '</code>';
			cards += '<button type="button" class="sca-gen__lock" data-lock="' + i + '" aria-pressed="' + isLocked + '">' + (isLocked ? 'Locked' : 'Lock') + '<span class="screen-reader-text"> ' + esc(c.name) + '</span></button>';
			cards += '</li>';
		});
		cards += '</ul>';

		els.result.innerHTML = bar + cards + '<p class="sca-note">Wear the Main color in the largest area, such as a coat, dress or trousers. Neutrals fill the rest, and the Accent works best in small doses near the face or as an accessory.</p>';

		renderTrend(season, trend);
		syncUrl();
	}

	function renderTrend(season, trend) {
		if (!els.trendOut) return;
		if (!trend) { els.trendOut.hidden = true; els.trendOut.innerHTML = ''; return; }
		var n = C.nearest(season.palette, trend.hex);
		var fit = C.fit(n.distance);
		var best = C.seasonFits(trend.hex, cfg.seasons).slice(0, 3);
		var html = '<div class="sca-gen__trend-head">';
		html += '<span class="sca-gen__dot" style="background:' + esc(trend.hex) + '" aria-hidden="true"></span>';
		html += '<div><p class="sca-gen__trend-name"><strong>' + esc(trend.name) + '</strong> <span>' + esc(trend.collection) + '</span></p>';
		html += '<p><span class="sca-fit sca-fit--' + fit.key + '">' + esc(fit.label) + '</span> Your ' + esc(season.name) + ' version: <strong>' + esc(n.color.name) + '</strong> <code>' + esc(n.color.hex) + '</code>';
		html += ' <span class="sca-gen__dot sca-gen__dot--small" style="background:' + esc(n.color.hex) + '" aria-hidden="true"></span></p></div></div>';
		html += '<p class="sca-gen__trend-best">Seasons it suits most closely: ' + best.map(function (b) {
			return '<a href="' + esc(seasons[b.slug].url) + '">' + esc(b.name) + '</a>';
		}).join(', ') + '.</p>';
		html += '<p class="sca-note">Trend hex values are on-screen approximations. <a href="' + esc(trend.source) + '" rel="noopener nofollow" target="_blank">Source: ' + esc(trend.collection) + '</a>.</p>';
		els.trendOut.innerHTML = html;
		els.trendOut.hidden = false;
	}

	/* ---------- Check my colors ---------- */

	function renderCheck() {
		if (!els.checkOut) return;
		var season = seasons[state.season];
		var rows = '', count = 0, total = 0;
		Array.prototype.forEach.call(els.checkInputs, function (input, i) {
			var hex = C.normalizeHex(input.value);
			if (!hex) return;
			total++;
			var n = C.nearest(season.palette, hex);
			var fit = C.fit(n.distance);
			if (fit.key === 'in' || fit.key === 'close') count++;
			rows += '<li class="sca-check__row">';
			rows += '<span class="sca-check__swatch" style="background:' + esc(hex) + '" aria-hidden="true"></span>';
			rows += '<span class="sca-check__arrow" aria-hidden="true">→</span>';
			rows += '<span class="sca-check__swatch" style="background:' + esc(n.color.hex) + '" aria-hidden="true"></span>';
			rows += '<span class="sca-check__text"><strong>Color ' + (i + 1) + ' <code>' + esc(hex) + '</code></strong>';
			rows += '<span class="sca-fit sca-fit--' + fit.key + '">' + esc(fit.label) + '</span>';
			rows += '<span>Closest ' + esc(season.name) + ' color: ' + esc(n.color.name) + ' <code>' + esc(n.color.hex) + '</code></span></span></li>';
		});
		var summary = total ? '<p class="sca-check__summary"><strong>' + count + ' of ' + total + '</strong> colors are in or close to the ' + esc(season.name) + ' palette.</p>' : '';
		els.checkOut.innerHTML = summary + '<ul class="sca-check__list" role="list">' + rows + '</ul>';
	}

	/* ---------- Events ---------- */

	els.season.value = state.season;
	if (els.trend) els.trend.value = trendBySlug(state.trend) ? state.trend : '';
	Array.prototype.forEach.call(els.harmony, function (r) { r.checked = r.value === state.harmony; });

	els.season.addEventListener('change', function () {
		state.season = els.season.value;
		state.locked = {};
		render();
		renderCheck();
	});
	if (els.trend) els.trend.addEventListener('change', function () {
		state.trend = els.trend.value;
		state.locked = {};
		state.seed++;
		render();
	});
	Array.prototype.forEach.call(els.harmony, function (r) {
		r.addEventListener('change', function () {
			state.harmony = r.value;
			state.locked = {};
			render();
		});
	});
	els.shuffle.addEventListener('click', function () {
		state.seed = Math.floor(Math.random() * 1e9) + 1;
		render();
		say('New combination');
	});
	els.result.addEventListener('click', function (e) {
		var lock = e.target.closest('[data-lock]');
		if (!lock) return;
		var i = lock.getAttribute('data-lock');
		state.locked[i] = !state.locked[i];
		lock.setAttribute('aria-pressed', state.locked[i] ? 'true' : 'false');
		lock.firstChild.textContent = state.locked[i] ? 'Locked' : 'Lock';
		lock.closest('.sca-gen__color').classList.toggle('is-locked', !!state.locked[i]);
	});
	els.copy.addEventListener('click', function () {
		var text = state.current.map(function (c) { return c.role + ': ' + c.name + ' ' + c.hex; }).join('\n');
		window.SCAPalette.copy(text).then(function () { say('5 codes copied'); }, function () { say('Copy failed'); });
	});
	els.download.addEventListener('click', function () {
		var season = seasons[state.season];
		window.SCAPalette.download(season.name + ' ' + C.HARMONIES[state.harmony].label, state.current.map(function (c) {
			return { name: c.role + ': ' + c.name, hex: c.hex };
		}), season.name + ' outfit palette');
		say('Palette downloaded');
	});

	Array.prototype.forEach.call(els.checkInputs, function (input, i) {
		var text = els.checkText[i];
		input.addEventListener('input', function () { if (text) text.value = input.value.toUpperCase(); renderCheck(); });
		if (text) text.addEventListener('change', function () {
			var hex = C.normalizeHex(text.value.charAt(0) === '#' ? text.value : '#' + text.value);
			if (hex) { input.value = hex.toLowerCase(); text.value = hex; renderCheck(); }
			else { text.value = input.value.toUpperCase(); }
		});
	});

	// Tabs (WAI-ARIA pattern)
	var tabs = Array.prototype.slice.call(root.querySelectorAll('.sca-gen__tabs [role="tab"]'));
	function selectTab(tab, focus) {
		tabs.forEach(function (t) {
			var on = t === tab;
			t.setAttribute('aria-selected', on ? 'true' : 'false');
			t.tabIndex = on ? 0 : -1;
			document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
		});
		if (focus) tab.focus();
	}
	tabs.forEach(function (tab, i) {
		tab.addEventListener('click', function () { selectTab(tab); });
		tab.addEventListener('keydown', function (e) {
			var next = e.key === 'ArrowRight' ? tabs[(i + 1) % tabs.length] : e.key === 'ArrowLeft' ? tabs[(i - 1 + tabs.length) % tabs.length] : null;
			if (next) { e.preventDefault(); selectTab(next, true); }
		});
	});

	root.classList.add('is-ready');
	render();
	renderCheck();
})();
