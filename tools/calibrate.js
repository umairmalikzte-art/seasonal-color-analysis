'use strict';
/*
 * Calibration report for human review (PRD section 12).
 * 1. Each persona: dimension scores, match, alternative, distance, gap, strength.
 * 2. 20,000 random complete answer sets: how often each season wins, and how
 *    often the result is strong, moderate, tentative or uncertain. A season
 *    that never wins, or one that wins far too often, points to a prototype
 *    or scoring problem.
 * Run: npm run calibrate
 */
const path = require('node:path');
const fs = require('node:fs');
const PLUGIN = path.join(__dirname, '..', 'wp-content', 'plugins', 'seasonal-color-toolkit');
const E = require(path.join(PLUGIN, 'assets', 'js', 'sca-engine.js'));
const defaults = JSON.parse(fs.readFileSync(path.join(PLUGIN, 'data', 'defaults.json'), 'utf8'));
const { toConfig } = require('../tests/config');
const PERSONAS = require('../tests/personas');
const config = toConfig(defaults);

const f = (n) => (n >= 0 ? ' ' : '') + n.toFixed(2);
console.log('Personas');
console.log('expected        warm  depth chroma contr  → match          alt             dist  gap   strength');
for (const [slug, p] of Object.entries(PERSONAS)) {
	const r = E.score(config, p);
	const d = r.dims;
	console.log(
		`${slug.padEnd(15)} ${f(d.warmth)} ${f(d.depth)} ${f(d.chroma)} ${f(d.contrast)}  → ${r.primary.slug.padEnd(15)} ${r.alternative.slug.padEnd(15)} ${r.primary.distance.toFixed(2)}  ${r.margin.toFixed(2)}  ${r.strength}${r.uncertain ? ' (uncertain)' : ''}`
	);
}

// Deterministic pseudo-random generator so the report is repeatable.
let seed = 42;
const rnd = () => ((seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648);
const N = 20000;
const wins = {}, strength = { strong: 0, moderate: 0, tentative: 0 };
let uncertain = 0;
for (let i = 0; i < N; i++) {
	const answers = {};
	for (const q of config.questions) {
		const options = q.answers.filter((a) => a.key !== 'unsure');
		answers[q.key] = options[Math.floor(rnd() * options.length)].key;
	}
	const r = E.score(config, answers);
	wins[r.primary.slug] = (wins[r.primary.slug] || 0) + 1;
	strength[r.strength]++;
	if (r.uncertain) uncertain++;
}
console.log(`\nRandom answer sets (${N}, no "Not sure")`);
for (const s of config.seasons) {
	const n = wins[s.slug] || 0;
	console.log(`${s.slug.padEnd(15)} ${(100 * n / N).toFixed(1).padStart(5)}%  ${'#'.repeat(Math.round(200 * n / N))}`);
}
console.log(`strong ${(100 * strength.strong / N).toFixed(1)}%, moderate ${(100 * strength.moderate / N).toFixed(1)}%, tentative ${(100 * strength.tentative / N).toFixed(1)}%, uncertain ${(100 * uncertain / N).toFixed(1)}%`);
console.log('Random answers are inconsistent by design, so most should not be "strong".');
