/**
 * Seasonal Color Toolkit – color math and the palette generator.
 *
 * Pure functions, no DOM; runs in the browser (window.SCAColor) and in Node.
 * Colors are compared in OKLab, a perceptual color space, so a "distance"
 * roughly matches how different two colors look.
 *
 * The generator never invents colors: every color it returns comes from the
 * season's curated palette. Harmony rules (tonal, analogous, complementary,
 * triadic, neutral + pop) only decide which palette colors go together.
 */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) module.exports = factory();
	else root.SCAColor = factory();
})(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	var NEUTRAL_CHROMA = 0.045;

	function hexToRgb(hex) {
		var m = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());
		if (!m) return null;
		var n = parseInt(m[1], 16);
		return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
	}

	function normalizeHex(hex) {
		var rgb = hexToRgb(hex);
		if (!rgb) return null;
		return '#' + rgb.map(function (v) { return ('0' + v.toString(16)).slice(-2); }).join('').toUpperCase();
	}

	/** sRGB hex → OKLab {L, a, b} (Björn Ottosson's reference matrices). */
	function oklab(hex) {
		var rgb = hexToRgb(hex);
		if (!rgb) return null;
		var lin = rgb.map(function (v) {
			v /= 255;
			return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		});
		var l = Math.cbrt(0.4122214708 * lin[0] + 0.5363325363 * lin[1] + 0.0514459929 * lin[2]);
		var m = Math.cbrt(0.2119034982 * lin[0] + 0.6806995451 * lin[1] + 0.1073969566 * lin[2]);
		var s = Math.cbrt(0.0883024619 * lin[0] + 0.2817188376 * lin[1] + 0.6299787005 * lin[2]);
		return {
			L: 0.2104542553 * l + 0.7936177850 * m - 0.0040720468 * s,
			a: 1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s,
			b: 0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s
		};
	}

	/** OKLab → OKLCH {L, C, h in degrees}. */
	function oklch(hex) {
		var c = oklab(hex);
		if (!c) return null;
		var h = Math.atan2(c.b, c.a) * 180 / Math.PI;
		return { L: c.L, C: Math.sqrt(c.a * c.a + c.b * c.b), h: h < 0 ? h + 360 : h };
	}

	/** Perceptual distance (Euclidean in OKLab). 0.02 is barely visible; 0.1 is clearly different. */
	function distance(hexA, hexB) {
		var a = oklab(hexA), b = oklab(hexB);
		if (!a || !b) return Infinity;
		var dL = a.L - b.L, da = a.a - b.a, db = a.b - b.b;
		return Math.sqrt(dL * dL + da * da + db * db);
	}

	function hueDiff(a, b) {
		var d = Math.abs(a - b) % 360;
		return d > 180 ? 360 - d : d;
	}

	/** Wearable colors only (the "avoid" group is never suggested). */
	function wearable(palette) {
		return (palette || []).filter(function (c) { return c.category !== 'avoid' && hexToRgb(c.hex); });
	}

	/** Closest palette color to any hex. */
	function nearest(palette, hex) {
		var best = null, bestD = Infinity;
		wearable(palette).forEach(function (c) {
			var d = distance(c.hex, hex);
			if (d < bestD) { bestD = d; best = c; }
		});
		return best ? { color: best, distance: bestD } : null;
	}

	var FIT = [
		{ max: 0.04, key: 'in', label: 'In your palette' },
		{ max: 0.08, key: 'close', label: 'Close to your palette' },
		{ max: 0.14, key: 'adapt', label: 'Wear your version' },
		{ max: Infinity, key: 'far', label: 'Far from your palette' }
	];

	/** Plain-language fit for a distance. A guide, not a verdict. */
	function fit(d) {
		for (var i = 0; i < FIT.length; i++) if (d <= FIT[i].max) return FIT[i];
		return FIT[FIT.length - 1];
	}

	/**
	 * How well a color (e.g. a trend) fits each season: distance to that
	 * season's nearest wearable color, closest first.
	 */
	function seasonFits(hex, seasons) {
		return (seasons || []).map(function (s) {
			var n = nearest(s.palette, hex);
			return { slug: s.slug, name: s.name, match: n ? n.color : null, distance: n ? n.distance : Infinity, fit: fit(n ? n.distance : Infinity) };
		}).sort(function (a, b) { return a.distance - b.distance; });
	}

	/** Small deterministic random generator (mulberry32). */
	function rng(seed) {
		var t = (seed >>> 0) || 1;
		return function () {
			t += 0x6D2B79F5;
			var r = Math.imul(t ^ (t >>> 15), 1 | t);
			r ^= r + Math.imul(r ^ (r >>> 7), 61 | r);
			return ((r ^ (r >>> 14)) >>> 0) / 4294967296;
		};
	}

	var HARMONIES = {
		tonal: { label: 'Tonal', roles: ['Main', 'Supporting', 'Accent', 'Neutral', 'Neutral'] },
		analogous: { label: 'Analogous', roles: ['Main', 'Supporting', 'Accent', 'Neutral', 'Neutral'] },
		complementary: { label: 'Complementary', roles: ['Main', 'Supporting', 'Accent', 'Neutral', 'Neutral'] },
		triadic: { label: 'Triadic', roles: ['Main', 'Supporting', 'Accent', 'Neutral', 'Neutral'] },
		neutral: { label: 'Neutral + pop', roles: ['Main', 'Neutral', 'Neutral', 'Pop', 'Accent'] }
	};

	/** Share of an outfit each role usually takes (for the preview bar). */
	var SHARES = {
		Main: 35, Supporting: 20, Accent: 10, Neutral: 17.5, Pop: 10
	};

	/**
	 * Generates a 5-color outfit palette from a season palette.
	 * @param {Array} palette  season palette [{name, hex, category}]
	 * @param {Object} opts    { harmony, base (hex, optional), seed, locked: {index: color} }
	 * @returns {Array<{name, hex, category, role}>}
	 */
	function generate(palette, opts) {
		opts = opts || {};
		var harmony = HARMONIES[opts.harmony] ? opts.harmony : 'analogous';
		var random = rng(opts.seed || 1);
		var locked = opts.locked || {};
		var pool = wearable(palette).map(function (c) {
			var lch = oklch(c.hex);
			return { name: c.name, hex: normalizeHex(c.hex), category: c.category, L: lch.L, C: lch.C, h: lch.h };
		});
		if (pool.length < 5) return [];

		var used = {};
		Object.keys(locked).forEach(function (i) { if (locked[i]) used[normalizeHex(locked[i].hex)] = true; });
		function free(list) { return list.filter(function (c) { return !used[c.hex]; }); }
		function take(c) { if (c) used[c.hex] = true; return c; }
		function anyOf(list) { return list.length ? list[Math.floor(random() * list.length)] : null; }
		var chromatic = pool.filter(function (c) { return c.C >= NEUTRAL_CHROMA && c.category !== 'neutral'; });
		var neutrals = pool.filter(function (c) { return c.C < NEUTRAL_CHROMA || c.category === 'neutral'; });

		/** Palette color whose hue is closest to the target; a little randomness among near-ties. */
		function byHue(target, ref) {
			var list = free(chromatic).map(function (c) {
				// prefer some lightness difference from the reference so colors do not blur together
				var lightGap = ref ? Math.abs(c.L - ref.L) : 0;
				return { c: c, score: hueDiff(c.h, target) - lightGap * 20 };
			}).sort(function (a, b) { return a.score - b.score; });
			if (!list.length) return anyOf(free(pool));
			var near = list.filter(function (x) { return x.score <= list[0].score + 18; }).slice(0, 3);
			return anyOf(near).c;
		}

		/** Same hue family, different lightness (tonal look). */
		function tonalOf(ref) {
			var list = free(chromatic).filter(function (c) { return hueDiff(c.h, ref.h) < 40; })
				.sort(function (a, b) { return Math.abs(b.L - ref.L) - Math.abs(a.L - ref.L); });
			return list.length ? anyOf(list.slice(0, 2)) : byHue(ref.h, ref);
		}

		/** Neutral that contrasts in lightness with a reference color. */
		function neutralFor(ref) {
			var list = free(neutrals).sort(function (a, b) { return Math.abs(b.L - ref.L) - Math.abs(a.L - ref.L); });
			return list.length ? anyOf(list.slice(0, 2)) : anyOf(free(pool));
		}

		var out = [null, null, null, null, null];
		Object.keys(locked).forEach(function (i) { if (locked[i] && out[i] === null) out[i] = locked[i]; });

		// 1. Main color: locked, closest to a requested base color, or random.
		if (!out[0]) {
			var basePool = harmony === 'neutral' ? free(neutrals) : free(chromatic);
			if (opts.base && hexToRgb(opts.base) && basePool.length) {
				out[0] = basePool.slice().sort(function (a, b) { return distance(a.hex, opts.base) - distance(b.hex, opts.base); })[0];
			} else {
				out[0] = anyOf(basePool.length ? basePool : free(pool));
			}
		}
		var base = take(oklchOf(out[0]));

		function fill(i, fn) { if (!out[i]) out[i] = take(fn()); else take(oklchOf(out[i])); }

		if (harmony === 'neutral') {
			fill(1, function () { return neutralFor(base); });
			fill(2, function () { return neutralFor(oklchOf(out[1])); });
			fill(3, function () {
				var list = free(chromatic).sort(function (a, b) { return b.C - a.C; });
				return anyOf(list.slice(0, 4));
			});
			fill(4, function () { return byHue((oklchOf(out[3]).h + 180) % 360, oklchOf(out[3])); });
		} else {
			if (harmony === 'tonal') {
				fill(1, function () { return tonalOf(base); });
				fill(2, function () { return tonalOf(oklchOf(out[1])); });
			} else if (harmony === 'analogous') {
				var dir = random() < 0.5 ? 1 : -1;
				fill(1, function () { return byHue((base.h + 30 * dir + 360) % 360, base); });
				fill(2, function () { return byHue((base.h - 30 * dir + 360) % 360, base); });
			} else if (harmony === 'complementary') {
				fill(1, function () { return tonalOf(base); });
				fill(2, function () { return byHue((base.h + 180) % 360, base); });
			} else {
				fill(1, function () { return byHue((base.h + 120) % 360, base); });
				fill(2, function () { return byHue((base.h + 240) % 360, base); });
			}
			fill(3, function () { return neutralFor(base); });
			fill(4, function () { return neutralFor(oklchOf(out[3])); });
		}

		// Safety net: never return an empty slot.
		for (var i = 0; i < 5; i++) if (!out[i]) out[i] = take(anyOf(free(pool)));

		var roles = HARMONIES[harmony].roles;
		return out.map(function (c, i) {
			return { name: c.name, hex: normalizeHex(c.hex), category: c.category, role: roles[i], share: SHARES[roles[i]] };
		});
	}

	function oklchOf(c) {
		if (c && c.L !== undefined && c.h !== undefined) return c;
		var lch = oklch(c.hex);
		return { name: c.name, hex: normalizeHex(c.hex), category: c.category, L: lch.L, C: lch.C, h: lch.h };
	}

	return {
		HARMONIES: HARMONIES,
		hexToRgb: hexToRgb,
		normalizeHex: normalizeHex,
		oklab: oklab,
		oklch: oklch,
		distance: distance,
		hueDiff: hueDiff,
		nearest: nearest,
		fit: fit,
		seasonFits: seasonFits,
		rng: rng,
		generate: generate
	};
});
