/**
 * Seasonal Color Toolkit – photo measurement (AI color analysis).
 *
 * Pure functions, no DOM. Runs in the browser (window.SCAPhotoMeasure) and in
 * Node (module.exports) so it is unit tested like the quiz engine.
 *
 * Input: median sRGB colors sampled from a selfie (skin, hair, eyes, eye
 * whites). Output: the same four dimensions the quiz uses, each between -1
 * and +1, so seasons are ranked by the same SCAEngine.rank() and the same
 * admin-editable prototypes and weights.
 *
 * Method:
 * 1. White balance: the eye whites (or a white sheet of paper) are used as a
 *    neutral reference. The correction is damped for eye whites, which are
 *    naturally a little warm.
 * 2. Colors are converted to CIELAB (D65).
 * 3. warmth   – skin hue angle (pink → golden) and yellowness (b*).
 *    depth    – lightness of skin, hair and eyes together.
 *    chroma   – saturation of skin, eyes and hair, blended with value
 *               contrast (high contrast reads "clear" to the eye).
 *    contrast – lightness difference between skin and hair / eyes.
 * 4. Optional answers (veins, metal, sun, white) refine warmth.
 */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		module.exports = factory();
	} else {
		root.SCAPhotoMeasure = factory();
	}
})(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }

	function lin(c) { c /= 255; return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); }

	/** sRGB [r,g,b] (0–255) → {L,a,b,C,h}. */
	function rgbToLab(rgb) {
		var R = lin(rgb[0]), G = lin(rgb[1]), B = lin(rgb[2]);
		var X = (R * 0.4124 + G * 0.3576 + B * 0.1805) / 0.95047;
		var Y = R * 0.2126 + G * 0.7152 + B * 0.0722;
		var Z = (R * 0.0193 + G * 0.1192 + B * 0.9505) / 1.08883;
		function f(t) { return t > 0.008856 ? Math.cbrt(t) : 7.787 * t + 16 / 116; }
		var fx = f(X), fy = f(Y), fz = f(Z);
		var L = 116 * fy - 16, A = 500 * (fx - fy), Bb = 200 * (fy - fz);
		return { L: L, a: A, b: Bb, C: Math.sqrt(A * A + Bb * Bb), h: (Math.atan2(Bb, A) * 180 / Math.PI + 360) % 360 };
	}

	function toHex(rgb) {
		return '#' + rgb.map(function (v) {
			var s = Math.round(clamp(v, 0, 255)).toString(16);
			return s.length < 2 ? '0' + s : s;
		}).join('').toUpperCase();
	}

	/** Robust median of an array of numbers. */
	function median(a) {
		if (!a.length) return 0;
		var s = a.slice().sort(function (x, y) { return x - y; });
		var m = s.length >> 1;
		return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2;
	}

	/**
	 * Median color of a list of [r,g,b] pixels after dropping the darkest
	 * `trimLo` and brightest `trimHi` share (shadows and shine).
	 */
	function robustColor(pixels, trimLo, trimHi) {
		if (!pixels || pixels.length < 4) return null;
		trimLo = trimLo === undefined ? 0.2 : trimLo;
		trimHi = trimHi === undefined ? 0.2 : trimHi;
		var withY = pixels.map(function (p) { return [0.299 * p[0] + 0.587 * p[1] + 0.114 * p[2], p]; });
		withY.sort(function (a, b) { return a[0] - b[0]; });
		var lo = Math.floor(withY.length * trimLo);
		var hi = Math.max(lo + 1, Math.ceil(withY.length * (1 - trimHi)));
		var keep = withY.slice(lo, hi).map(function (q) { return q[1]; });
		return [0, 1, 2].map(function (i) { return median(keep.map(function (p) { return p[i]; })); });
	}

	function average(list) {
		list = (list || []).filter(Boolean);
		if (!list.length) return null;
		var s = [0, 0, 0];
		list.forEach(function (c) { s[0] += c[0]; s[1] += c[1]; s[2] += c[2]; });
		return s.map(function (v) { return v / list.length; });
	}

	/**
	 * Per-channel gains that make the reference neutral.
	 * @param {number[]|null} ref     reference color (eye whites or paper)
	 * @param {boolean}       isPaper paper is truly white, eye whites are damped
	 */
	function whiteBalance(ref, isPaper) {
		var out = { gains: [1, 1, 1], cast: null, used: false };
		if (!ref) return out;
		var lab = rgbToLab(ref);
		if (lab.L < 45) return out; // too dark to trust (shadowed or not really the eye white)
		var mean = (ref[0] + ref[1] + ref[2]) / 3;
		var damp = isPaper ? 1 : 0.65;
		out.gains = ref.map(function (v) { return clamp(1 + damp * (mean / Math.max(v, 1) - 1), 0.6, 1.6); });
		out.used = true;
		if (lab.C > 10) out.cast = lab.b > 0 ? 'warm' : 'cool';
		return out;
	}

	function applyGains(rgb, gains) {
		return rgb ? [rgb[0] * gains[0], rgb[1] * gains[1], rgb[2] * gains[2]] : null;
	}

	/** Plain skin depth label used on the result and the skin tone pages. */
	function skinToneName(L) {
		if (L >= 78) return 'Fair';
		if (L >= 70) return 'Light';
		if (L >= 60) return 'Medium';
		if (L >= 50) return 'Tan';
		if (L >= 38) return 'Brown';
		return 'Deep';
	}

	/**
	 * Four dimensions from LAB colors of skin, hair and eyes.
	 * @param {{skin:Object,hair:Object,eyes:Object}} labs
	 * @returns {{warmth,depth,chroma,contrast,olive,undertone,skinTone}}
	 */
	function dimensions(labs) {
		var skin = labs.skin, hair = labs.hair || labs.skin, eyes = labs.eyes || labs.skin;
		var warmth = clamp((skin.h - 54) / 14 * 0.75 + (skin.b - 17) / 10 * 0.25, -1, 1);
		var olive = skin.a < 7 && skin.h > 68 && skin.L < 72;
		if (olive) warmth *= 0.6; // olive reads golden in hue but behaves neutral in clothing

		var lAvg = skin.L * 0.5 + hair.L * 0.35 + eyes.L * 0.15;
		var depth01 = clamp((78 - lAvg) / 46, 0, 1);
		var contrast01 = clamp((Math.abs(skin.L - hair.L) * 0.7 + Math.abs(skin.L - eyes.L) * 0.3 - 18) / 40, 0, 1);
		var measured = clamp(((skin.C - 15) / 16) * 0.45 + ((eyes.C - 10) / 22) * 0.35 + ((hair.C - 12) / 22) * 0.2, 0, 1);
		var chroma01 = clamp(measured * 0.55 + contrast01 * 0.45, 0, 1);

		var undertone = olive ? 'Olive' : warmth > 0.35 ? 'Warm' : warmth < -0.35 ? 'Cool' : 'Neutral';
		return {
			warmth: warmth,
			depth: depth01 * 2 - 1,
			chroma: chroma01 * 2 - 1,
			contrast: contrast01 * 2 - 1,
			olive: olive,
			undertone: undertone,
			skinTone: skinToneName(skin.L)
		};
	}

	/** Answer → warmth score. */
	var ANSWER_WARMTH = {
		veins: { blue: -1, green: 1, both: 0 },
		metal: { silver: -1, gold: 1, both: 0 },
		sun: { burn: -0.6, tan: 0.6, both: 0 },
		white: { bright: -0.8, cream: 0.8, both: 0 }
	};

	/** Blends optional answers into the photo dimensions. */
	function refine(dims, answers) {
		var out = {}, k;
		for (k in dims) if (Object.prototype.hasOwnProperty.call(dims, k)) out[k] = dims[k];
		answers = answers || {};
		var sum = 0, n = 0;
		Object.keys(ANSWER_WARMTH).forEach(function (q) {
			var v = ANSWER_WARMTH[q][answers[q]];
			if (v !== undefined) { sum += v; n++; }
		});
		if (n) {
			out.warmth = clamp(dims.warmth * 0.65 + (sum / n) * 0.35, -1, 1);
			if (!dims.olive) out.undertone = out.warmth > 0.35 ? 'Warm' : out.warmth < -0.35 ? 'Cool' : 'Neutral';
		}
		if (answers.hairnow === 'dyed' || answers.hairnow === 'gray') {
			out.contrast = dims.contrast * 0.85; // trust hair-based contrast a little less
		}
		return out;
	}

	/**
	 * Photo quality score (20–100) and plain-language tips.
	 * @param {Object} s     raw samples {skin, cheekL, cheekR, sclera, paper}
	 * @param {Object} labs  corrected LAB colors
	 * @param {Object} wb    whiteBalance() result
	 */
	function quality(s, labs, wb) {
		var tips = [], score = 100;
		if (wb && wb.cast) tips.push('Your light has a ' + (wb.cast === 'warm' ? 'warm, yellow' : 'cool, blue') + ' cast. It was corrected using the whites of your eyes.');
		if (labs.skin.L < 30) { tips.push('The photo is dark. Face a window for brighter, even light.'); score -= 25; }
		if (labs.skin.L > 90) { tips.push('The photo looks over-exposed. Avoid flash and direct sun.'); score -= 20; }
		if (s.cheekL && s.cheekR) {
			var dl = Math.abs(rgbToLab(s.cheekL).L - rgbToLab(s.cheekR).L);
			if (dl > 12) { tips.push('One side of your face is in shadow. Face the light straight on.'); score -= 15; }
		}
		if (labs.skin.C > 34) { tips.push('Skin looks very saturated. Turn off beauty filters and warm photo modes.'); score -= 15; }
		if (!wb || !wb.used) { tips.push('Your eye whites could not be read, so the light color was not corrected.'); score -= 10; }
		return { score: clamp(score, 20, 100), tips: tips };
	}

	/**
	 * Full analysis from raw samples.
	 * @param {Object} s       {skin, hair, eyes, sclera?, paper?, cheekL?, cheekR?} as [r,g,b]
	 * @param {Object} answers optional answers
	 */
	function analyse(s, answers) {
		var wb = whiteBalance(s.paper || s.sclera || null, !!s.paper);
		var colors = {
			skin: applyGains(s.skin, wb.gains),
			hair: applyGains(s.hair || s.skin, wb.gains),
			eyes: applyGains(s.eyes || s.hair || s.skin, wb.gains)
		};
		var labs = { skin: rgbToLab(colors.skin), hair: rgbToLab(colors.hair), eyes: rgbToLab(colors.eyes) };
		var dims = refine(dimensions(labs), answers);
		return {
			colors: colors,
			hex: { skin: toHex(colors.skin), hair: toHex(colors.hair), eyes: toHex(colors.eyes) },
			labs: labs,
			dims: { warmth: dims.warmth, depth: dims.depth, chroma: dims.chroma, contrast: dims.contrast },
			undertone: dims.undertone,
			skinTone: dims.skinTone,
			olive: dims.olive,
			whiteBalance: wb,
			quality: quality(s, labs, wb)
		};
	}

	/**
	 * Match percentages for the top seasons from an engine ranking
	 * (softmax over distance), and a confidence score from the gap between
	 * the top two and the photo quality.
	 */
	function confidence(ranking, qualityScore) {
		var top = ranking.slice(0, 3);
		var exps = top.map(function (r) { return Math.exp(-r.distance * 4); });
		var sum = exps.reduce(function (a, b) { return a + b; }, 0) || 1;
		var pct = exps.map(function (e) { return Math.round(e / sum * 100); });
		var gap = (pct[0] || 0) - (pct[1] || 0);
		return { pct: pct, score: Math.round(clamp(gap * 0.9 + qualityScore * 0.45, 25, 97)) };
	}

	return {
		rgbToLab: rgbToLab,
		toHex: toHex,
		robustColor: robustColor,
		average: average,
		whiteBalance: whiteBalance,
		applyGains: applyGains,
		skinToneName: skinToneName,
		dimensions: dimensions,
		refine: refine,
		quality: quality,
		analyse: analyse,
		confidence: confidence
	};
});
