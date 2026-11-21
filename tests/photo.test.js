'use strict';
/*
 * AI color analysis (photo) tests. Colors are typical median samples for
 * skin, hair and eyes of each season, as the face-landmark sampler would
 * return them in daylight. Seasons are ranked by the real engine with the
 * real prototypes and weights from data/defaults.json, so a change to the
 * prototypes that breaks the photo tool shows up here.
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');

const PLUGIN = path.join(__dirname, '..', 'wp-content', 'plugins', 'seasonal-color-toolkit');
const E = require(path.join(PLUGIN, 'assets', 'js', 'sca-engine.js'));
const M = require(path.join(PLUGIN, 'assets', 'js', 'sca-photo-measure.js'));
const defaults = JSON.parse(fs.readFileSync(path.join(PLUGIN, 'data', 'defaults.json'), 'utf8'));
const { toConfig } = require('./config');
const config = toConfig(defaults);

// [skin, hair, eyes] as sRGB
const PHOTO_PERSONAS = {
	'light-spring': [[240, 210, 185], [215, 180, 120], [130, 165, 175]],
	'true-spring': [[230, 190, 150], [200, 130, 60], [120, 140, 80]],
	'bright-spring': [[235, 200, 170], [70, 45, 30], [60, 120, 190]],
	'light-summer': [[238, 208, 205], [200, 185, 160], [140, 160, 175]],
	'true-summer': [[220, 180, 175], [120, 100, 85], [110, 130, 145]],
	'soft-summer': [[205, 170, 160], [125, 105, 90], [120, 125, 115]],
	'soft-autumn': [[210, 172, 145], [140, 110, 80], [130, 115, 80]],
	'true-autumn': [[215, 165, 120], [130, 65, 30], [100, 85, 45]],
	'deep-autumn': [[150, 100, 70], [40, 28, 20], [60, 40, 25]],
	'deep-winter': [[170, 125, 110], [22, 20, 22], [45, 32, 28]],
	'true-winter': [[230, 200, 198], [20, 18, 20], [50, 40, 38]],
	'bright-winter': [[228, 195, 185], [25, 22, 22], [50, 110, 190]]
};

const rank = (dims) => E.rank(dims, config.seasons, config.settings.weights);

test('LAB conversion: white, black and a known red', () => {
	const w = M.rgbToLab([255, 255, 255]);
	assert.ok(Math.abs(w.L - 100) < 0.1 && Math.abs(w.a) < 0.1 && Math.abs(w.b) < 0.1);
	assert.ok(Math.abs(M.rgbToLab([0, 0, 0]).L) < 0.01);
	const r = M.rgbToLab([255, 0, 0]);
	assert.ok(Math.abs(r.L - 53.2) < 0.5 && Math.abs(r.a - 80.1) < 0.5 && Math.abs(r.b - 67.2) < 0.5);
});

test('robust color drops shadows and highlights', () => {
	const px = [];
	for (let i = 0; i < 60; i++) px.push([200, 160, 140]);
	for (let i = 0; i < 10; i++) px.push([20, 15, 12]);   // shadow
	for (let i = 0; i < 10; i++) px.push([255, 255, 255]); // shine
	assert.deepEqual(M.robustColor(px), [200, 160, 140]);
	assert.equal(M.robustColor([[1, 2, 3]]), null);
});

test('white balance neutralizes a yellow cast from the eye whites', () => {
	const wb = M.whiteBalance([235, 220, 175], false);
	assert.equal(wb.used, true);
	assert.equal(wb.cast, 'warm');
	const fixed = M.applyGains([235, 220, 175], wb.gains);
	assert.ok(M.rgbToLab(fixed).C < M.rgbToLab([235, 220, 175]).C);
	assert.equal(M.whiteBalance([60, 50, 40], false).used, false, 'dark reference is ignored');
});

test('a warm lamp cast does not flip a cool person to warm', () => {
	const cool = PHOTO_PERSONAS['true-summer'];
	const lamp = [1.08, 1.0, 0.82];
	const cast = (c) => M.applyGains(c, lamp);
	const r = M.analyse({ skin: cast(cool[0]), hair: cast(cool[1]), eyes: cast(cool[2]), sclera: cast([232, 230, 228]) });
	assert.ok(r.dims.warmth < 0, 'warmth ' + r.dims.warmth.toFixed(2));
});

test('dimensions stay between -1 and 1', () => {
	for (const slug of Object.keys(PHOTO_PERSONAS)) {
		const [skin, hair, eyes] = PHOTO_PERSONAS[slug];
		const { dims } = M.analyse({ skin, hair, eyes });
		for (const d of E.DIMENSIONS) assert.ok(dims[d] >= -1 && dims[d] <= 1, slug + ' ' + d);
	}
});

for (const slug of Object.keys(PHOTO_PERSONAS)) {
	test('photo persona ' + slug + ' ranks its season first or second', () => {
		const [skin, hair, eyes] = PHOTO_PERSONAS[slug];
		const r = rank(M.analyse({ skin, hair, eyes }).dims);
		const top2 = r.slice(0, 2).map((x) => x.slug);
		assert.ok(top2.includes(slug), slug + ' got ' + top2.join(', '));
	});
}

test('answers refine warmth: blue veins + silver pull toward cool', () => {
	const [skin, hair, eyes] = PHOTO_PERSONAS['soft-autumn'];
	const plain = M.analyse({ skin, hair, eyes });
	const cool = M.analyse({ skin, hair, eyes }, { veins: 'blue', metal: 'silver', sun: 'burn', white: 'bright' });
	assert.ok(cool.dims.warmth < plain.dims.warmth);
});

test('skin tone and undertone labels', () => {
	assert.equal(M.skinToneName(85), 'Fair');
	assert.equal(M.skinToneName(65), 'Medium');
	assert.equal(M.skinToneName(30), 'Deep');
	assert.equal(M.analyse({ skin: [230, 200, 198], hair: [20, 18, 20], eyes: [50, 40, 38] }).undertone, 'Cool');
	assert.equal(M.analyse({ skin: [215, 165, 120], hair: [130, 65, 30], eyes: [100, 85, 45] }).undertone, 'Warm');
});

test('confidence gives percentages for the top 3 and a 25–97 score', () => {
	const [skin, hair, eyes] = PHOTO_PERSONAS['deep-autumn'];
	const c = M.confidence(rank(M.analyse({ skin, hair, eyes }).dims), 90);
	assert.equal(c.pct.length, 3);
	assert.ok(c.pct[0] >= c.pct[1] && c.pct[1] >= c.pct[2]);
	assert.ok(c.score >= 25 && c.score <= 97);
});
