/**
 * Seasonal Color Toolkit – quiz app.
 *
 * One question per screen, back and next, progress, keyboard support and an
 * uncertainty option on every question. Answers live in memory only: they
 * are never sent to the server, stored, or passed to analytics. A shared
 * link contains the season, not the answers.
 */
(function () {
	'use strict';

	var cfg = window.SCA_CONFIG;
	var E = window.SCAEngine;
	var root = document.querySelector('[data-sca-quiz]');
	if (!root || !cfg || !E || !cfg.questions || !cfg.questions.length) return;

	var questions = cfg.questions;
	var seasons = {};
	cfg.seasons.forEach(function (s) { seasons[s.slug] = s; });

	var state = { step: 0, answers: {}, started: false };

	var STRENGTH = {
		strong: 'Strong match',
		moderate: 'Moderate match',
		tentative: 'Tentative match'
	};
	var DIM_META = {
		warmth: ['Temperature', 'Cool', 'Warm'],
		depth: ['Depth', 'Light', 'Deep'],
		chroma: ['Chroma', 'Soft', 'Bright'],
		contrast: ['Contrast', 'Low', 'High']
	};

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function emit(name, detail) {
		document.dispatchEvent(new CustomEvent('sca:' + name, { detail: detail || {} }));
		// Optional GA4: only the event name and the season, never answers.
		if (typeof window.gtag === 'function') window.gtag('event', 'sca_' + name, detail || {});
	}

	function focusHeading() {
		var h = root.querySelector('[data-focus]');
		if (h) h.focus({ preventScroll: true });
		var top = root.getBoundingClientRect().top + window.pageYOffset - 90;
		if (window.pageYOffset > top) window.scrollTo({ top: top, behavior: 'smooth' });
	}

	/* ---------- Question screen ---------- */

	function renderQuestion() {
		var q = questions[state.step];
		var n = questions.length;
		var pct = Math.round((state.step / n) * 100);
		var chosen = state.answers[q.key];
		var last = state.step === n - 1;

		var html = '';
		html += '<div class="sca-quiz__progress">';
		html += '<p class="sca-quiz__count">Question ' + (state.step + 1) + ' of ' + n + '</p>';
		html += '<div class="sca-quiz__bar" role="progressbar" aria-label="Quiz progress" aria-valuemin="0" aria-valuemax="' + n + '" aria-valuenow="' + state.step + '"><span style="width:' + pct + '%"></span></div>';
		html += '</div>';
		html += '<form class="sca-quiz__form" novalidate>';
		html += '<fieldset class="sca-quiz__fieldset">';
		html += '<legend class="sca-quiz__question" tabindex="-1" data-focus>' + esc(q.text) + '</legend>';
		if (q.help) html += '<p class="sca-quiz__help" id="sca-help-' + esc(q.key) + '">' + esc(q.help) + '</p>';
		html += '<div class="sca-options">';
		q.answers.forEach(function (a, i) {
			var id = 'sca-' + q.key + '-' + i;
			var unsure = a.key === 'unsure';
			html += '<label class="sca-option' + (unsure ? ' sca-option--unsure' : '') + '" for="' + id + '">';
			html += '<input type="radio" id="' + id + '" name="sca-answer" value="' + esc(a.key) + '"' + (chosen === a.key ? ' checked' : '') + (q.help ? ' aria-describedby="sca-help-' + esc(q.key) + '"' : '') + '>';
			if (a.swatches && a.swatches.length) {
				html += '<span class="sca-option__swatches" aria-hidden="true">';
				a.swatches.forEach(function (hex) { html += '<span style="background:' + esc(hex) + '"></span>'; });
				html += '</span>';
			}
			html += '<span class="sca-option__text">' + esc(a.text) + '</span></label>';
		});
		html += '</div></fieldset>';
		html += '<p class="sca-quiz__error" role="alert" hidden>Choose an answer, or pick "Not sure".</p>';
		html += '<div class="sca-quiz__nav">';
		html += '<button type="button" class="sca-btn sca-btn--ghost" data-back' + (state.step === 0 ? ' disabled' : '') + '>Back</button>';
		html += '<button type="submit" class="sca-btn sca-btn--primary">' + (last ? 'See my result' : 'Next') + '</button>';
		html += '</div></form>';
		root.innerHTML = html;

		var form = root.querySelector('form');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var picked = form.querySelector('input[name="sca-answer"]:checked');
			if (!picked) {
				form.querySelector('.sca-quiz__error').hidden = false;
				var first = form.querySelector('input[name="sca-answer"]');
				if (first) first.focus();
				return;
			}
			state.answers[q.key] = picked.value;
			if (!state.started) { state.started = true; emit('quiz_start'); }
			if (last) {
				renderResult();
			} else {
				state.step++;
				renderQuestion();
			}
			focusHeading();
		});
		form.addEventListener('change', function () {
			form.querySelector('.sca-quiz__error').hidden = true;
		});
		root.querySelector('[data-back]').addEventListener('click', function () {
			if (state.step === 0) return;
			var picked = form.querySelector('input[name="sca-answer"]:checked');
			if (picked) state.answers[q.key] = picked.value;
			state.step--;
			renderQuestion();
			focusHeading();
		});
	}

	/* ---------- Result screen ---------- */

	function meters(values) {
		var html = '<dl class="sca-meters">';
		Object.keys(DIM_META).forEach(function (d) {
			var v = Math.max(-1, Math.min(1, values[d] || 0));
			var m = DIM_META[d];
			html += '<div class="sca-meter"><dt>' + m[0] + '</dt><dd><span class="sca-meter__value">' + esc(E.label(d, v)) + '</span>';
			html += '<span class="sca-meter__track sca-meter__track--' + d + '" aria-hidden="true"><span class="sca-meter__dot" style="left:' + ((v + 1) * 50).toFixed(1) + '%"></span></span>';
			html += '<span class="sca-meter__ends" aria-hidden="true"><span>' + m[1] + '</span><span>' + m[2] + '</span></span></dd></div>';
		});
		return html + '</dl>';
	}

	function palette(season, categories, tools) {
		var colors = season.palette.filter(function (c) { return categories.indexOf(c.category) !== -1; });
		if (!colors.length) return '';
		var data = colors.filter(function (c) { return c.category !== 'avoid'; });
		var html = '<div class="sca-palette' + (data.length ? '' : ' sca-palette--avoid') + '" data-name="' + esc(season.name) + '" data-palette="' + esc(JSON.stringify(data)) + '">';
		html += '<ul class="sca-swatches" role="list">';
		colors.forEach(function (c) {
			html += '<li class="sca-swatch"><button type="button" class="sca-swatch__chip" data-hex="' + esc(c.hex) + '" style="background:' + esc(c.hex) + ';color:' + E.inkFor(c.hex) + '">';
			html += '<span class="screen-reader-text">Copy ' + esc(c.name) + ', ' + esc(c.hex) + '</span><span class="sca-swatch__copied" aria-hidden="true">Copied</span></button>';
			html += '<span class="sca-swatch__name">' + esc(c.name) + '</span><code class="sca-swatch__hex">' + esc(c.hex) + '</code></li>';
		});
		html += '</ul>';
		if (tools && data.length) {
			html += '<div class="sca-palette__tools"><button type="button" class="sca-btn sca-btn--ghost" data-sca-copy-all>Copy all hex codes</button>';
			html += '<button type="button" class="sca-btn sca-btn--ghost" data-sca-download>Download palette (PNG)</button>';
			html += '<span class="sca-palette__status" role="status" aria-live="polite"></span></div>';
		}
		return html + '</div>';
	}

	function shareUrl(slug) {
		var u = new URL(cfg.quizUrl || location.href, location.href);
		u.search = '';
		u.hash = '';
		u.searchParams.set('season', slug);
		return u.toString();
	}

	function compareUrl(a, b) {
		var u = new URL(cfg.compareUrl || '/compare-seasons/', location.href);
		u.searchParams.set('a', a);
		u.searchParams.set('b', b);
		u.hash = 'sca-compare-result';
		return u.toString();
	}

	function familyName(f) { return f.charAt(0).toUpperCase() + f.slice(1); }

	function renderResult() {
		var r = E.score(cfg, state.answers);
		var s = seasons[r.primary.slug];
		var alt = r.alternative ? seasons[r.alternative.slug] : null;
		emit('quiz_complete', { season: s.slug, strength: r.strength });

		var html = '<section class="sca-result sca-family--' + esc(s.family) + '" aria-labelledby="sca-result-title">';
		html += '<div class="sca-result__head">';
		html += '<p class="sca-badge sca-badge--' + r.strength + '">' + STRENGTH[r.strength] + '</p>';
		html += '<h2 id="sca-result-title" class="sca-result__title" tabindex="-1" data-focus>Your likely season is ' + esc(s.name) + '.</h2>';
		html += '<p class="sca-result__lead">' + esc(familyName(s.family)) + ' family' + (s.altNames && s.altNames.length ? ' · also called ' + esc(s.altNames.join(', ')) : '') + '. ' + esc(s.description) + '</p>';
		html += '</div>';

		if (r.uncertain && alt) {
			html += '<div class="sca-notice"><strong>Your answers sit between two seasons.</strong> ' + esc(s.name) + ' and ' + esc(alt.name) + ' are almost equally close. Try colors from both palettes next to your face in daylight. <a href="' + esc(compareUrl(s.slug, alt.slug)) + '">Compare ' + esc(s.name) + ' and ' + esc(alt.name) + '</a>.</div>';
		}
		if (r.incomplete) {
			html += '<div class="sca-notice">You gave a clear answer to ' + r.answered + ' of ' + r.total + ' questions, so this result is tentative. Answering more questions makes it more reliable.</div>';
		}

		html += '<div class="sca-result__grid">';
		html += '<div class="sca-result__profile"><h3>Your color profile</h3>' + meters(r.dims) + '</div>';
		if (alt) {
			html += '<div class="sca-result__alt"><h3>Closest alternative</h3>';
			html += '<p><a href="' + esc(alt.url) + '"><strong>' + esc(alt.name) + '</strong></a>. ' + esc(alt.description) + '</p>';
			html += '<p><a href="' + esc(compareUrl(s.slug, alt.slug)) + '">See ' + esc(s.name) + ' and ' + esc(alt.name) + ' side by side</a></p></div>';
		}
		html += '</div>';

		html += '<h3>Your main palette</h3>' + palette(s, ['neutral', 'core', 'accent'], true);
		html += '<h3>Colors to approach with care</h3>' + palette(s, ['avoid'], false);

		if (s.clothing || s.makeup) {
			html += '<div class="sca-result__tips">';
			if (s.clothing) html += '<div><h3>Clothing</h3><p>' + esc(s.clothing) + '</p></div>';
			if (s.makeup) html += '<div><h3>Makeup</h3><p>' + esc(s.makeup) + '</p></div>';
			html += '</div>';
		}

		html += '<div class="sca-result__actions">';
		html += '<a class="sca-btn sca-btn--primary" href="' + esc(s.url) + '">Read the ' + esc(s.name) + ' guide</a>';
		if (cfg.generatorUrl) html += '<a class="sca-btn sca-btn--ghost" href="' + esc(cfg.generatorUrl + (cfg.generatorUrl.indexOf('?') === -1 ? '?' : '&') + 'season=' + s.slug) + '">Generate outfit colors</a>';
		html += '<button type="button" class="sca-btn sca-btn--ghost" data-share="' + esc(s.slug) + '">Share my result</button>';
		html += '<button type="button" class="sca-btn sca-btn--ghost" data-restart>Restart the quiz</button>';
		html += '<span class="sca-quiz__share-status" role="status" aria-live="polite"></span>';
		html += '</div>';
		html += '<p class="sca-note">This is an estimate from your answers, not a professional color analysis. The match strength is a plain-language label, not a scientific probability. Lighting, makeup and hair color can all change how colors look on you.</p>';
		html += '</section>';
		root.innerHTML = html;
		bindResult();
	}

	function bindResult() {
		var restart = root.querySelector('[data-restart]');
		if (restart) restart.addEventListener('click', function () {
			state = { step: 0, answers: {}, started: false };
			if (location.search.indexOf('season=') !== -1) history.replaceState(null, '', location.pathname);
			renderQuestion();
			focusHeading();
		});
		var share = root.querySelector('[data-share]');
		if (share) share.addEventListener('click', function () {
			var s = seasons[share.getAttribute('data-share')];
			var url = shareUrl(s.slug);
			var out = root.querySelector('.sca-quiz__share-status');
			var text = 'My likely color season is ' + s.name + '.';
			if (navigator.share) {
				navigator.share({ title: s.name + ' color palette', text: text, url: url }).catch(function () {});
			} else if (window.SCAPalette) {
				window.SCAPalette.copy(url).then(function () { out.textContent = 'Link copied'; }, function () { out.textContent = url; });
			}
		});
	}

	/* ---------- Shared link view (?season=slug) ---------- */

	function renderShared(s) {
		var html = '<section class="sca-result sca-family--' + esc(s.family) + '" aria-labelledby="sca-result-title">';
		html += '<div class="sca-result__head"><p class="sca-badge">Shared result</p>';
		html += '<h2 id="sca-result-title" class="sca-result__title" tabindex="-1">Someone\'s likely season is ' + esc(s.name) + '.</h2>';
		html += '<p class="sca-result__lead">' + esc(s.description) + '</p></div>';
		html += palette(s, ['neutral', 'core', 'accent'], true);
		html += '<div class="sca-result__actions"><button type="button" class="sca-btn sca-btn--primary" data-restart>Take the quiz yourself</button>';
		html += '<a class="sca-btn sca-btn--ghost" href="' + esc(s.url) + '">Read the ' + esc(s.name) + ' guide</a></div></section>';
		root.innerHTML = html;
		bindResult();
	}

	// Started from the homepage: #start=questionKey:answerKey (a fragment, never sent to the server).
	function startFromHash() {
		var m = /^#start=([a-z0-9_]+):([a-z0-9_]+)$/.exec(location.hash);
		if (!m) return false;
		history.replaceState(null, '', location.pathname + location.search);
		var i = -1;
		questions.forEach(function (q, n) { if (q.key === m[1]) i = n; });
		if (i !== 0) return false;
		var valid = questions[0].answers.some(function (a) { return a.key === m[2]; });
		if (!valid) return false;
		state.answers[m[1]] = m[2];
		state.started = true;
		state.step = 1;
		emit('quiz_start');
		return true;
	}

	var shared = new URLSearchParams(location.search).get('season');
	if (shared && seasons[shared]) renderShared(seasons[shared]);
	else {
		var fromHome = startFromHash();
		renderQuestion();
		if (fromHome) focusHeading();
	}
})();
