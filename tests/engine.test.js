'use strict';
/*
 * Scoring engine tests. They use the real default quiz (data/defaults.json),
 * so a change to the questions or prototypes that breaks a season shows up
 * here. The personas are calibration cases for human review (PRD 12): each
 * is a typical, consistent set of answers for one season.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');

const PLUGIN = path.join(__dirname, '..', 'wp-content', 'plugins', 'seasonal-color-toolkit');
const E = require(path.join(PLUGIN, 'assets', 'js', 'sca-engine.js'));
const defaults = JSON.parse(fs.readFileSync(path.join(PLUGIN, 'data', 'defaults.json'), 'utf8'));
const { toConfig } = require('./config');
const config = toConfig(defaults);

const PERSONAS = require('./personas');

test('default data: 12 seasons, 15 questions, 24 palette colors per season', () => {
	assert.equal(config.seasons.length, 12);
	assert.equal(config.questions.length, 15);
	for (const s of defaults.seasons) {
		const colors = defaults.palettes[s.slug];
		const wearable = colors.filter((c) => c[2] !== 'avoid');
		assert.ok(wearable.length >= 20 && wearable.length <= 30, `${s.slug} has ${wearable.length}`);
		for (const c of colors) assert.match(c[1], /^#[0-9A-F]{6}$/, `${s.slug} ${c[0]}`);
		const hexes = wearable.map((c) => c[1]);
		assert.equal(new Set(hexes).size, hexes.length, `${s.slug} has a duplicate hex`);
	}
});

test('every question has a "Not sure" option and answer scores stay in [-1, 1]', () => {
	for (const q of config.questions) {
		assert.ok(q.answers.some((a) => a.key === 'unsure'), q.key);
		for (const a of q.answers) {
			for (const v of Object.values(a.scores)) assert.ok(v >= -1 && v <= 1, `${q.key}.${a.key}`);
		}
	}
});

test('a prototype point maps to its own season', () => {
	for (const s of config.seasons) {
		const ranking = E.rank(s, config.seasons, config.settings.weights);
		assert.equal(ranking[0].slug, s.slug);
		assert.equal(ranking[0].distance, 0);
	}
});

test('dimension score is the normalized weighted average', () => {
	const cfg = {
		questions: [
			{ key: 'a', weight: 1, answers: [{ key: 'x', scores: { warmth: 1 } }, { key: 'y', scores: { warmth: -1 } }] },
			{ key: 'b', weight: 3, answers: [{ key: 'x', scores: { warmth: 0.5 } }, { key: 'y', scores: { warmth: -0.5 } }] },
		],
	};
	// (1*1 + -0.5*3) / (1*1 + 0.5*3) = -0.5 / 2.5 = -0.2
	const r = E.dimensionScores(cfg, { a: 'x', b: 'y' });
	assert.ok(Math.abs(r.dims.warmth - -0.2) < 1e-12);
	assert.equal(r.coverage.depth, false);
	// Always picking the strongest warm answer gives exactly +1.
	assert.equal(E.dimensionScores(cfg, { a: 'x', b: 'x' }).dims.warmth, 1);
});

for (const [slug, persona] of Object.entries(PERSONAS)) {
	test(`persona → ${slug}`, () => {
		const r = E.score(config, persona);
		assert.equal(r.primary.slug, slug, `got ${r.primary.slug}, dims ${JSON.stringify(r.dims)}`);
		assert.equal(r.incomplete, false);
		assert.notEqual(r.alternative.slug, slug);
	});
}

test('all 12 seasons can be returned by the engine', () => {
	const returned = new Set(Object.values(PERSONAS).map((p) => E.score(config, p).primary.slug));
	assert.equal(returned.size, 12);
});

test('the alternative is a sensible neighbour for most personas', () => {
	const bySlug = Object.fromEntries(defaults.seasons.map((s) => [s.slug, s]));
	let ok = 0;
	for (const [slug, persona] of Object.entries(PERSONAS)) {
		const alt = E.score(config, persona).alternative.slug;
		const s = bySlug[slug];
		const neighbours = s.neighbors.split(',');
		const sameFamily = bySlug[alt].family === s.family;
		if (neighbours.includes(alt) || sameFamily) ok++;
	}
	assert.ok(ok >= 10, `only ${ok}/12 alternatives are neighbours or family members`);
});

test('all "Not sure" answers give an incomplete, tentative result', () => {
	const answers = Object.fromEntries(config.questions.map((q) => [q.key, 'unsure']));
	const r = E.score(config, answers);
	assert.equal(r.answered, 0);
	assert.equal(r.incomplete, true);
	assert.equal(r.strength, 'tentative');
});

test('no answers at all does not crash and is tentative', () => {
	const r = E.score(config, {});
	assert.equal(r.strength, 'tentative');
	assert.equal(r.ranking.length, 12);
});

test('conflicting warmth answers give a neutral warmth score', () => {
	const r = E.dimensionScores(config, {
		undertone: 'warm', jewelry: 'silver', white: 'ivory', pink_coral: 'pink',
		beige: 'glow', navy_brown: 'navy',
	});
	assert.ok(Math.abs(r.dims.warmth) < 0.2, String(r.dims.warmth));
});

test('a near-tie between two seasons is flagged as uncertain', () => {
	// Exactly between Soft Summer and Soft Autumn (warmth 0).
	const cfg = { ...config, questions: [] };
	const dims = { warmth: 0, depth: 0, chroma: -0.9, contrast: -0.4 };
	const ranking = E.rank(dims, cfg.seasons, cfg.settings.weights);
	assert.deepEqual(ranking.slice(0, 2).map((r) => r.slug).sort(), ['soft-autumn', 'soft-summer']);
	assert.ok(ranking[1].distance - ranking[0].distance < cfg.settings.thresholds.margin);
});

test('labels and colour helpers', () => {
	assert.equal(E.label('warmth', -0.9), 'Cool');
	assert.equal(E.label('warmth', 0), 'Neutral');
	assert.equal(E.label('chroma', 0.9), 'Bright');
	assert.equal(E.label('depth', 0.3), 'Medium to deep');
	assert.deepEqual(E.hexToRgb('#FF8000'), { r: 255, g: 128, b: 0 });
	assert.equal(E.hexToRgb('nope'), null);
	assert.equal(E.inkFor('#FFFFFF'), '#222222');
	assert.equal(E.inkFor('#111111'), '#ffffff');
	assert.equal(E.inkFor('#C47A5E'), '#222222'); // mid terracotta: dark text has more contrast
});
