'use strict';
/*
 * Calibration personas: one typical, consistent set of answers per season.
 * They are not real people. A human should review them when the questions,
 * scores or prototypes change (PRD section 12).
 */
module.exports = {
	'light-spring': {
		hair_now: 'golden_blonde', hair_child: 'very_light', eye_color: 'light_blue', eye_clarity: 'somewhat',
		undertone: 'warm', jewelry: 'gold', sun: 'burn_then_tan', white: 'ivory', brightness: 'medium',
		lightness: 'light', black: 'harsh', beige: 'glow', pink_coral: 'coral', navy_brown: 'both', contrast: 'low',
	},
	'true-spring': {
		hair_now: 'red', hair_child: 'golden', eye_color: 'green', eye_clarity: 'clear',
		undertone: 'warm', jewelry: 'gold', sun: 'burn_then_tan', white: 'ivory', brightness: 'medium',
		lightness: 'medium', black: 'fine', beige: 'glow', pink_coral: 'coral', navy_brown: 'brown', contrast: 'medium',
	},
	'bright-spring': {
		hair_now: 'warm_light_brown', hair_child: 'golden', eye_color: 'bright_blue', eye_clarity: 'clear',
		undertone: 'neutral', jewelry: 'gold', sun: 'tan_golden', white: 'pure', brightness: 'bright',
		lightness: 'medium', black: 'fine', beige: 'ok', pink_coral: 'coral', navy_brown: 'both', contrast: 'high',
	},
	'light-summer': {
		hair_now: 'light_ash', hair_child: 'very_light', eye_color: 'light_blue', eye_clarity: 'somewhat',
		undertone: 'cool', jewelry: 'silver', sun: 'burn_pink', white: 'soft', brightness: 'medium',
		lightness: 'light', black: 'harsh', beige: 'ok', pink_coral: 'pink', navy_brown: 'navy', contrast: 'low',
	},
	'true-summer': {
		hair_now: 'dark_blonde', hair_child: 'ash', eye_color: 'light_blue', eye_clarity: 'somewhat',
		undertone: 'cool', jewelry: 'silver', sun: 'burn_pink', white: 'soft', brightness: 'rich',
		lightness: 'medium', black: 'fine', beige: 'sallow', pink_coral: 'pink', navy_brown: 'navy', contrast: 'medium',
	},
	'soft-summer': {
		hair_now: 'ash_brown', hair_child: 'ash', eye_color: 'soft_hazel', eye_clarity: 'soft',
		undertone: 'neutral', jewelry: 'silver', sun: 'burn_then_tan', white: 'soft', brightness: 'soft',
		lightness: 'medium', black: 'harsh', beige: 'ok', pink_coral: 'pink', navy_brown: 'navy', contrast: 'low',
	},
	'soft-autumn': {
		hair_now: 'warm_light_brown', hair_child: 'golden', eye_color: 'soft_hazel', eye_clarity: 'soft',
		undertone: 'olive', jewelry: 'gold', sun: 'tan_golden', white: 'ivory', brightness: 'soft',
		lightness: 'medium', black: 'harsh', beige: 'glow', pink_coral: 'both', navy_brown: 'brown', contrast: 'low',
	},
	'true-autumn': {
		hair_now: 'red', hair_child: 'red', eye_color: 'warm_hazel', eye_clarity: 'somewhat',
		undertone: 'warm', jewelry: 'gold', sun: 'tan_golden', white: 'ivory', brightness: 'rich',
		lightness: 'medium', black: 'fine', beige: 'glow', pink_coral: 'coral', navy_brown: 'brown', contrast: 'medium',
	},
	'deep-autumn': {
		hair_now: 'chestnut', hair_child: 'dark', eye_color: 'brown', eye_clarity: 'somewhat',
		undertone: 'warm', jewelry: 'gold', sun: 'tan_deep', white: 'ivory', brightness: 'rich',
		lightness: 'deep', black: 'fine', beige: 'ok', pink_coral: 'coral', navy_brown: 'brown', contrast: 'high',
	},
	'deep-winter': {
		hair_now: 'black', hair_child: 'dark', eye_color: 'very_dark', eye_clarity: 'somewhat',
		undertone: 'neutral', jewelry: 'silver', sun: 'tan_deep', white: 'pure', brightness: 'medium',
		lightness: 'deep', black: 'striking', beige: 'sallow', pink_coral: 'pink', navy_brown: 'both', contrast: 'high',
	},
	'true-winter': {
		hair_now: 'black', hair_child: 'dark', eye_color: 'brown', eye_clarity: 'clear',
		undertone: 'cool', jewelry: 'silver', sun: 'burn_pink', white: 'pure', brightness: 'bright',
		lightness: 'medium', black: 'striking', beige: 'sallow', pink_coral: 'pink', navy_brown: 'navy', contrast: 'high',
	},
	'bright-winter': {
		hair_now: 'ash_brown', hair_child: 'medium_brown', eye_color: 'bright_blue', eye_clarity: 'clear',
		undertone: 'neutral', jewelry: 'silver', sun: 'burn_then_tan', white: 'pure', brightness: 'bright',
		lightness: 'medium', black: 'striking', beige: 'ok', pink_coral: 'both', navy_brown: 'navy', contrast: 'high',
	},
};
