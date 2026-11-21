'use strict';
/**
 * Builds the engine config from defaults.json in the same shape the plugin
 * sends to the browser (SCA_Repository::frontend_config in PHP).
 */
function toConfig(defaults) {
	return {
		settings: defaults.settings,
		seasons: defaults.seasons.map((s) => ({
			slug: s.slug, name: s.name, family: s.family,
			warmth: s.warmth, depth: s.depth, chroma: s.chroma, contrast: s.contrast,
		})),
		questions: defaults.questions.map((q) => ({
			key: q.key, text: q.text, weight: q.weight,
			answers: q.answers.map(([key, text, scores]) => ({ key, text, scores, weight: 1 })),
		})),
	};
}

module.exports = { toConfig };
