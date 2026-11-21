'use strict';
/*
 * Builds the site's original graphics from data/defaults.json:
 *   theme  assets/img/favicon.svg, logo-512.png
 *   plugin assets/img/og-default.png               (1200×630)
 *          assets/img/seasons/og-{slug}.png        (1200×630, social cards)
 *          assets/img/seasons/{slug}-palette.webp  (1200×900, palette with names and hex)
 * Rendered with the installed Chrome through puppeteer-core:
 *   NODE_PATH=<folder with puppeteer-core> node tools/build-images.js
 * Re-run after editing palettes. All graphics are drawn from our own data.
 */
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

const ROOT = path.join(__dirname, '..');
const PLUGIN = path.join(ROOT, 'wp-content', 'plugins', 'seasonal-color-toolkit');
const THEME = path.join(ROOT, 'wp-content', 'themes', 'seasonal-color');
const data = JSON.parse(fs.readFileSync(path.join(PLUGIN, 'data', 'defaults.json'), 'utf8'));
const CHROME = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const SERIF = "'Iowan Old Style','Palatino Linotype',Palatino,Georgia,serif";
const SANS = "system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif";
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const lum = (hex) => {
	const n = parseInt(hex.slice(1), 16);
	return [n >> 16 & 255, n >> 8 & 255, n & 255].map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; })
		.reduce((s, v, i) => s + v * [0.2126, 0.7152, 0.0722][i], 0);
};
const FAMILY = { spring: 'Spring', summer: 'Summer', autumn: 'Autumn', winter: 'Winter' };
const label = (v, words) => words[v < -0.5 ? 0 : v < -0.15 ? 1 : v <= 0.15 ? 2 : v <= 0.5 ? 3 : 4];
const W = { warmth: ['Cool', 'Cool-neutral', 'Neutral', 'Warm-neutral', 'Warm'], depth: ['Light', 'Light to medium', 'Medium', 'Medium to deep', 'Deep'], chroma: ['Soft', 'Soft to medium', 'Medium', 'Clear', 'Bright'] };

const page = (body, w, h) => `<!doctype html><html><head><meta charset="utf-8"><style>
*{box-sizing:border-box;margin:0;padding:0} html,body{width:${w}px;height:${h}px;background:#FBF7F1;color:#2B2A29;font-family:${SANS}}
</style></head><body>${body}</body></html>`;

function ogSeason(s) {
	const pal = data.palettes[s.slug].filter((c) => c[2] !== 'avoid');
	const core = pal.filter((c) => c[2] !== 'neutral').slice(0, 15);
	const neutrals = pal.filter((c) => c[2] === 'neutral').slice(0, 6);
	const alt = s.alt_names ? `Also called ${s.alt_names}` : '';
	const traits = [label(s.warmth, W.warmth), label(s.depth, W.depth), label(s.chroma, W.chroma)].join(' · ');
	return page(`
	<div style="display:flex;height:630px;padding:56px;gap:48px">
	  <div style="flex:0 0 470px;display:flex;flex-direction:column;justify-content:space-between">
	    <div>
	      <div style="font:700 18px ${SANS};letter-spacing:.14em;text-transform:uppercase;color:#9F543F">${FAMILY[s.family]} family</div>
	      <div style="font:600 76px/1.02 ${SERIF};margin:18px 0 14px">${esc(s.name)}</div>
	      <div style="font:600 34px ${SERIF};color:#6B6560">Color Palette</div>
	      <div style="margin-top:26px;font:500 22px ${SANS};color:#2B2A29">${esc(traits)}</div>
	      <div style="margin-top:8px;font:20px ${SANS};color:#6B6560">${esc(alt)}</div>
	    </div>
	    <div style="font:600 19px ${SANS};color:#6B6560">Seasonal Color Analysis</div>
	  </div>
	  <div style="flex:1;display:flex;flex-direction:column;gap:14px">
	    <div style="flex:1;display:grid;grid-template-columns:repeat(5,1fr);gap:12px">
	      ${core.map((c) => `<div style="background:${c[1]};border-radius:14px;box-shadow:inset 0 0 0 1px rgba(0,0,0,.06)"></div>`).join('')}
	    </div>
	    <div style="height:70px;display:flex;border-radius:14px;overflow:hidden;box-shadow:inset 0 0 0 1px rgba(0,0,0,.06)">
	      ${neutrals.map((c) => `<div style="flex:1;background:${c[1]}"></div>`).join('')}
	    </div>
	  </div>
	</div>`, 1200, 630);
}

function paletteSeason(s) {
	const pal = data.palettes[s.slug];
	const groups = [['Neutrals', 'neutral'], ['Core colors', 'core'], ['Accents', 'accent']];
	const cell = (c) => `<div><div style="height:96px;border-radius:12px;background:${c[1]};box-shadow:inset 0 0 0 1px rgba(0,0,0,${lum(c[1]) > 0.85 ? '.12' : '.05'})"></div>
	  <div style="margin-top:8px;font:600 15px ${SANS}">${esc(c[0])}</div><div style="font:14px ui-monospace,Consolas,monospace;color:#6B6560">${c[1]}</div></div>`;
	return page(`
	<div style="padding:48px 56px">
	  <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:24px">
	    <div><div style="font:600 46px ${SERIF}">${esc(s.name)} Color Palette</div>
	    <div style="font:19px ${SANS};color:#6B6560;margin-top:6px">${esc(s.description)}</div></div>
	  </div>
	  ${groups.map(([title, cat]) => `
	    <div style="font:700 14px ${SANS};letter-spacing:.1em;text-transform:uppercase;color:#6B6560;margin:18px 0 10px">${title}</div>
	    <div style="display:grid;grid-template-columns:repeat(${cat === 'core' ? 6 : 6},1fr);gap:14px 16px">${pal.filter((c) => c[2] === cat).map(cell).join('')}</div>`).join('')}
	  <div style="margin-top:22px;font:15px ${SANS};color:#8A837C">Seasonal Color Analysis · A guide, not a professional color analysis</div>
	</div>`, 1200, 900);
}

function ogDefault() {
	const rows = data.seasons.map((s) => {
		const pal = data.palettes[s.slug].filter((c) => c[2] === 'core').slice(0, 8);
		return `<div style="display:flex;align-items:center;gap:14px"><div style="width:150px;font:600 17px ${SANS};text-align:right">${esc(s.name)}</div>
		<div style="flex:1;display:flex;height:28px;border-radius:999px;overflow:hidden">${pal.map((c) => `<div style="flex:1;background:${c[1]}"></div>`).join('')}</div></div>`;
	}).join('');
	return page(`
	<div style="display:flex;height:630px;padding:56px;gap:56px;align-items:center">
	  <div style="flex:0 0 430px">
	    <div style="font:600 64px/1.05 ${SERIF}">Seasonal Color Analysis</div>
	    <div style="font:24px/1.4 ${SANS};color:#6B6560;margin-top:20px">Free quiz, 12 season palettes with hex codes and a palette generator.</div>
	    <div style="font:700 18px ${SANS};letter-spacing:.12em;text-transform:uppercase;color:#9F543F;margin-top:28px">Find your colors</div>
	  </div>
	  <div style="flex:1;display:grid;gap:12px">${rows}</div>
	</div>`, 1200, 630);
}

const FAVICON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle cx="32" cy="32" r="30" fill="#F9A88F"/><path d="M32 2a30 30 0 0 1 30 30H32z" fill="#B0CFE8"/><path d="M62 32a30 30 0 0 1-30 30V32z" fill="#0F52BA"/><path d="M32 62A30 30 0 0 1 2 32h30z" fill="#C47A5E"/></svg>`;

function logo() {
	return page(`<div style="width:512px;height:512px;display:flex;align-items:center;justify-content:center;background:#FBF7F1">
	<div style="width:400px;height:400px">${FAVICON.replace('<svg', '<svg width="400" height="400"')}</div></div>`, 512, 512);
}

(async () => {
	const out = { seasons: path.join(PLUGIN, 'assets', 'img', 'seasons'), plugin: path.join(PLUGIN, 'assets', 'img'), theme: path.join(THEME, 'assets', 'img') };
	Object.values(out).forEach((d) => fs.mkdirSync(d, { recursive: true }));
	fs.writeFileSync(path.join(out.theme, 'favicon.svg'), FAVICON);

	const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new' });
	const p = await browser.newPage();
	async function shot(html, w, h, file, type = 'png') {
		await p.setViewport({ width: w, height: h, deviceScaleFactor: 1 });
		await p.setContent(html, { waitUntil: 'load' });
		await p.screenshot({ path: file, type, ...(type === 'webp' ? { quality: 82 } : {}) });
		console.log(path.relative(ROOT, file), Math.round(fs.statSync(file).size / 1024) + ' KB');
	}
	await shot(logo(), 512, 512, path.join(out.theme, 'logo-512.png'));
	await shot(ogDefault(), 1200, 630, path.join(out.plugin, 'og-default.png'));
	for (const s of data.seasons) {
		await shot(ogSeason(s), 1200, 630, path.join(out.seasons, `og-${s.slug}.png`));
		await shot(paletteSeason(s), 1200, 900, path.join(out.seasons, `${s.slug}-palette.webp`), 'webp');
	}
	await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
