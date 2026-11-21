/**
 * Seasonal Color Toolkit – scoring engine.
 *
 * Pure functions, no DOM. Runs in the browser (window.SCAEngine) and in
 * Node (module.exports) so the same code is unit tested.
 *
 * Method (PRD section 12):
 * 1. Each answer carries a score between -1 and +1 for the dimensions it
 *    informs (warmth, depth, chroma, contrast).
 * 2. Dimension score = Σ(score × weight) / Σ(maxScore × weight), where
 *    maxScore is the largest absolute score any answer of that question has
 *    for the dimension. This is the weighted average from the PRD,
 *    normalized so that always choosing the strongest answer gives ±1.
 * 3. Each season is a prototype point in 4-D space. The weighted Euclidean
 *    distance ranks them; the closest is the primary match, the second the
 *    alternative.
 * 4. The match strength is a plain label, not a probability.
 */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		module.exports = factory();
	} else {
		root.SCAEngine = factory();
	}
})(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	var DIMENSIONS = ['warmth', 'depth', 'chroma', 'contrast'];

	function clamp(v, lo, hi) {
		return Math.max(lo, Math.min(hi, v));
	}

	function num(v, fallback) {
		var n = parseFloat(v);
		return isFinite(n) ? n : fallback;
	}

	/** Largest absolute score any answer of the question gives a dimension. */
	function maxAbs(question, dim) {
		var m = 0;
		(question.answers || []).forEach(function (a) {
			if (a.scores && a.scores[dim] !== undefined && a.scores[dim] !== null) {
				m = Math.max(m, Math.abs(num(a.scores[dim], 0)));
			}
		});
		return m;
	}

	/**
	 * Turn answers into the four normalized dimension scores.
	 * @param {Object} config  { questions: [...] }
	 * @param {Object} answers { questionKey: answerKey }
	 */
	function dimensionScores(config, answers) {
		var top = {}, bottom = {}, answered = 0, total = 0;
		DIMENSIONS.forEach(function (d) { top[d] = 0; bottom[d] = 0; });

		(config.questions || []).forEach(function (q) {
			total++;
			var key = answers ? answers[q.key] : undefined;
			if (!key) return;
			var answer = null;
			(q.answers || []).forEach(function (a) { if (a.key === key) answer = a; });
			if (!answer || !answer.scores) return;

			var informative = false;
			var w = num(q.weight, 1) * num(answer.weight, 1);
			DIMENSIONS.forEach(function (d) {
				var s = answer.scores[d];
				if (s === undefined || s === null) return;
				var m = maxAbs(q, d);
				if (m === 0) return;
				top[d] += num(s, 0) * w;
				bottom[d] += m * w;
				informative = true;
			});
			if (informative) answered++;
		});

		var dims = {}, coverage = {};
		DIMENSIONS.forEach(function (d) {
			coverage[d] = bottom[d] > 0;
			dims[d] = bottom[d] > 0 ? clamp(top[d] / bottom[d], -1, 1) : 0;
		});
		return { dims: dims, coverage: coverage, answered: answered, total: total };
	}

	function distance(dims, season, weights) {
		var sum = 0;
		DIMENSIONS.forEach(function (d) {
			var diff = dims[d] - num(season[d], 0);
			sum += num(weights[d], 1) * diff * diff;
		});
		return Math.sqrt(sum);
	}

	/** Rank every active season by distance to the dimension scores. */
	function rank(dims, seasons, weights) {
		return (seasons || [])
			.map(function (s) { return { slug: s.slug, name: s.name, distance: distance(dims, s, weights || {}) }; })
			.sort(function (a, b) { return a.distance - b.distance; });
	}

	/**
	 * Full result.
	 * @returns {{dims, coverage, answered, total, ranking, primary, alternative,
	 *            margin, incomplete, uncertain, strength}}
	 */
	function score(config, answers) {
		var settings = config.settings || {};
		var weights = settings.weights || {};
		var t = settings.thresholds || {};
		var margin = num(t.margin, 0.08);
		var strongDistance = num(t.strong_distance, 0.45);
		var moderateDistance = num(t.moderate_distance, 0.8);
		var minAnswered = num(t.min_answered, 10);

		var d = dimensionScores(config, answers);
		var ranking = rank(d.dims, config.seasons, weights);
		var primary = ranking[0] || null;
		var alternative = ranking[1] || null;
		var gap = primary && alternative ? alternative.distance - primary.distance : Infinity;

		var missingDimension = DIMENSIONS.some(function (k) { return !d.coverage[k]; });
		var incomplete = d.answered < Math.min(minAnswered, d.total) || missingDimension;
		var uncertain = gap < margin;

		var strength;
		if (incomplete || uncertain || !primary) strength = 'tentative';
		else if (primary.distance <= strongDistance && gap >= margin * 2) strength = 'strong';
		else if (primary.distance <= moderateDistance) strength = 'moderate';
		else strength = 'tentative';

		return {
			dims: d.dims,
			coverage: d.coverage,
			answered: d.answered,
			total: d.total,
			ranking: ranking,
			primary: primary,
			alternative: alternative,
			margin: gap,
			incomplete: incomplete,
			uncertain: uncertain,
			strength: strength
		};
	}

	var LABELS = {
		warmth: ['Cool', 'Cool-neutral', 'Neutral', 'Warm-neutral', 'Warm'],
		depth: ['Light', 'Light to medium', 'Medium', 'Medium to deep', 'Deep'],
		chroma: ['Soft', 'Soft to medium', 'Medium', 'Clear', 'Bright'],
		contrast: ['Low', 'Low to medium', 'Medium', 'Medium to high', 'High']
	};

	/** Plain-language label for a dimension value between -1 and 1. */
	function label(dim, value) {
		var v = num(value, 0), i;
		if (v < -0.5) i = 0;
		else if (v < -0.15) i = 1;
		else if (v <= 0.15) i = 2;
		else if (v <= 0.5) i = 3;
		else i = 4;
		return (LABELS[dim] || LABELS.depth)[i];
	}

	/** "#AABBCC" → {r,g,b} or null. */
	function hexToRgb(hex) {
		var m = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());
		if (!m) return null;
		var n = parseInt(m[1], 16);
		return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
	}

	/** WCAG relative luminance. */
	function luminance(hex) {
		var c = hexToRgb(hex);
		if (!c) return 0;
		function ch(v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }
		return 0.2126 * ch(c.r) + 0.7152 * ch(c.g) + 0.0722 * ch(c.b);
	}

	/** Text color (dark or light) that reads best on a swatch. */
	function inkFor(hex) {
		return luminance(hex) > 0.36 ? '#222222' : '#ffffff';
	}

	return {
		DIMENSIONS: DIMENSIONS,
		dimensionScores: dimensionScores,
		distance: distance,
		rank: rank,
		score: score,
		label: label,
		hexToRgb: hexToRgb,
		luminance: luminance,
		inkFor: inkFor
	};
});
