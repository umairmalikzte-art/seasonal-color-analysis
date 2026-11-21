'use strict';
/* Color math and palette generator tests. */
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');

const PLUGIN = path.join(__dirname, '..', 'wp-content', 'plugins', 'seasonal-color-toolkit');
const C = require(path.join(PLUGIN, 'assets', 'js', 'sca-color.js'));
const defaults = JSON.parse(fs.readFileSync(path.join(PLUGIN, 'data', 'defaults.json'), 'utf8'));
const seasons = defaults.seasons.map((s) => ({
	slug: s.slug, name: s.name,
	palette: defaults.palettes[s.slug].map(([name, hex, category]) => ({ name, hex, category })),
}));
const bySlug = Object.fromEntries(seasons.map((s) => [s.slug, s]));

test('OKLab reference values', () => {
	const w = C.oklab('#FFFFFF');
	assert.ok(Math.abs(w.L - 1) < 1e-3 && Math.abs(w.a) < 1e-3 && Math.abs(w.b) < 1e-3);
	const k = C.oklab('#000000');
	assert.ok(Math.abs(k.L) < 1e-9);
	// Published OKLab of sRGB red: L 0.62796, a 0.22486, b 0.12585
	const r = C.oklab('#FF0000');
	assert.ok(Math.abs(r.L - 0.62796) < 1e-3 && Math.abs(r.a - 0.22486) < 1e-3 && Math.abs(r.b - 0.12585) < 1e-3);
	assert.equal(C.oklab('nope'), null);
});

test('distance and hue difference', () => {
	assert.equal(C.distance('#336699', '#336699'), 0);
	assert.ok(C.distance('#FFFFFF', '#000000') > 0.99);
	assert.ok(C.distance('#F0EEE9', '#EFEDE8') < 0.01);
	assert.equal(C.hueDiff(350, 10), 20);
	assert.equal(C.hueDiff(0, 180), 180);
});

test('fit labels', () => {
	assert.equal(C.fit(0.01).key, 'in');
	assert.equal(C.fit(0.06).key, 'close');
	assert.equal(C.fit(0.12).key, 'adapt');
	assert.equal(C.fit(0.5).key, 'far');
});

test('nearest never returns an "avoid" color', () => {
	// Pure black is in Soft Autumn's avoid list; the nearest wearable color must be another one.
	const n = C.nearest(bySlug['soft-autumn'].palette, '#111111');
	assert.notEqual(n.color.category, 'avoid');
	assert.notEqual(n.color.hex, '#111111');
});

test('trend fits rank sensible seasons', () => {
	// Cloud Dancer (a soft white) should be closest to a Summer/Winter soft or pure white, far from Deep Autumn's darkest.
	const cloud = C.seasonFits('#F0EEE9', seasons);
	assert.ok(['soft-summer', 'true-summer', 'light-summer', 'true-winter', 'deep-winter', 'bright-winter'].includes(cloud[0].slug), cloud[0].slug);
	// A clear cobalt-like blue fits the Winters and Bright Spring better than Soft Autumn.
	const blue = C.seasonFits('#2F45A8', seasons);
	const rank = blue.map((x) => x.slug);
	assert.ok(rank.indexOf('soft-autumn') > rank.indexOf('true-winter'));
	assert.equal(blue.length, 12);
});

for (const harmony of Object.keys(C.HARMONIES)) {
	test(`generate (${harmony}) returns 5 distinct wearable colors from the palette, for every season`, () => {
		for (const s of seasons) {
			for (let seed = 1; seed <= 25; seed++) {
				const out = C.generate(s.palette, { harmony, seed });
				assert.equal(out.length, 5, `${s.slug} seed ${seed}`);
				const hexes = out.map((c) => c.hex);
				assert.equal(new Set(hexes).size, 5, `${s.slug} ${harmony} seed ${seed}: ${hexes}`);
				const allowed = new Set(s.palette.filter((c) => c.category !== 'avoid').map((c) => c.hex.toUpperCase()));
				for (const h of hexes) assert.ok(allowed.has(h), `${h} not in ${s.slug}`);
			}
		}
	});
}

test('generate is deterministic for a seed and varies across seeds', () => {
	const p = bySlug['true-autumn'].palette;
	assert.deepEqual(C.generate(p, { harmony: 'analogous', seed: 7 }), C.generate(p, { harmony: 'analogous', seed: 7 }));
	const variants = new Set();
	for (let seed = 1; seed <= 20; seed++) variants.add(C.generate(p, { harmony: 'analogous', seed }).map((c) => c.hex).join());
	assert.ok(variants.size >= 5, `only ${variants.size} variants`);
});

test('a base color picks the closest palette color as Main', () => {
	const p = bySlug['soft-summer'].palette;
	const out = C.generate(p, { harmony: 'tonal', base: '#2F45A8', seed: 3 });
	const expected = p.filter((c) => c.category !== 'neutral' && c.category !== 'avoid')
		.sort((a, b) => C.distance(a.hex, '#2F45A8') - C.distance(b.hex, '#2F45A8'))[0];
	assert.equal(out[0].hex, expected.hex.toUpperCase());
	assert.equal(out[0].role, 'Main');
});

test('locked colors stay in place', () => {
	const p = bySlug['bright-winter'].palette;
	const first = C.generate(p, { harmony: 'triadic', seed: 1 });
	const locked = { 0: first[0], 3: first[3] };
	for (let seed = 2; seed < 12; seed++) {
		const out = C.generate(p, { harmony: 'triadic', seed, locked });
		assert.equal(out[0].hex, first[0].hex);
		assert.equal(out[3].hex, first[3].hex);
		assert.equal(new Set(out.map((c) => c.hex)).size, 5);
	}
});

test('neutral + pop: three neutrals and one saturated pop', () => {
	const out = C.generate(bySlug['deep-winter'].palette, { harmony: 'neutral', seed: 4 });
	assert.deepEqual(out.map((c) => c.role), ['Main', 'Neutral', 'Neutral', 'Pop', 'Accent']);
	for (const i of [0, 1, 2]) assert.ok(out[i].category === 'neutral' || C.oklch(out[i].hex).C < 0.045, out[i].name);
});

test('trend data has a source for every color', () => {
	assert.ok(defaults.trends.length >= 5);
	for (const t of defaults.trends) {
		assert.match(t.hex, /^#[0-9A-F]{6}$/);
		assert.match(t.source, /^https:\/\//);
		assert.ok(t.collection);
	}
});
