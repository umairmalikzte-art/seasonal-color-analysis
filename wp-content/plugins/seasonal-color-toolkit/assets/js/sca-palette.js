/**
 * Seasonal Color Toolkit – palette tools.
 * Copy one hex code, copy all codes, download a palette PNG (drawn on a
 * canvas in the browser, nothing is uploaded) and the explorer tabs.
 */
(function () {
	'use strict';

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.setAttribute('readonly', '');
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.select();
			try {
				document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
			} catch (e) {
				reject(e);
			} finally {
				document.body.removeChild(ta);
			}
		});
	}

	function luminance(hex) {
		var n = parseInt(hex.slice(1), 16);
		return [n >> 16 & 255, n >> 8 & 255, n & 255].map(function (v) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		}).reduce(function (sum, v, i) { return sum + v * [0.2126, 0.7152, 0.0722][i]; }, 0);
	}

	function slugify(s) {
		return String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
	}

	/**
	 * Draws the palette and starts a PNG download.
	 * @param {string} title  e.g. "Soft Autumn"
	 * @param {Array<{name:string,hex:string}>} colors
	 * @param {string} [subtitle]
	 */
	function downloadPalette(title, colors, subtitle) {
		var cols = 6, cell = 180, gap = 16, pad = 56, head = 150, label = 58;
		var rows = Math.ceil(colors.length / cols);
		var w = pad * 2 + cols * cell + (cols - 1) * gap;
		var h = pad + head + rows * (cell + label) + (rows - 1) * gap + pad + 30;
		var canvas = document.createElement('canvas');
		var scale = 2;
		canvas.width = w * scale;
		canvas.height = h * scale;
		var ctx = canvas.getContext('2d');
		ctx.scale(scale, scale);

		ctx.fillStyle = '#FBF7F1';
		ctx.fillRect(0, 0, w, h);
		ctx.fillStyle = '#2B2A29';
		ctx.font = '600 44px Georgia, "Times New Roman", serif';
		ctx.fillText(title + ' Color Palette', pad, pad + 44);
		ctx.fillStyle = '#6B6560';
		ctx.font = '20px system-ui, -apple-system, "Segoe UI", sans-serif';
		ctx.fillText(subtitle || (colors.length + ' colors with hex codes'), pad, pad + 84);

		colors.forEach(function (c, i) {
			var x = pad + (i % cols) * (cell + gap);
			var y = pad + head + Math.floor(i / cols) * (cell + label + gap);
			ctx.fillStyle = c.hex;
			ctx.fillRect(x, y, cell, cell);
			if (luminance(c.hex) > 0.85) {
				ctx.strokeStyle = '#E4DDD3';
				ctx.strokeRect(x + 0.5, y + 0.5, cell - 1, cell - 1);
			}
			ctx.fillStyle = '#2B2A29';
			ctx.font = '600 17px system-ui, -apple-system, "Segoe UI", sans-serif';
			ctx.fillText(c.name, x, y + cell + 24, cell);
			ctx.fillStyle = '#6B6560';
			ctx.font = '16px ui-monospace, Consolas, monospace';
			ctx.fillText(c.hex, x, y + cell + 46);
		});

		ctx.fillStyle = '#8A837C';
		ctx.font = '15px system-ui, -apple-system, "Segoe UI", sans-serif';
		ctx.fillText(location.hostname + ' · A guide, not a professional color analysis', pad, h - pad + 10);

		var a = document.createElement('a');
		a.download = slugify(title) + '-color-palette.png';
		a.href = canvas.toDataURL('image/png');
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
	}

	function status(root, text) {
		var el = root.querySelector('.sca-palette__status');
		if (!el) return;
		el.textContent = text;
		clearTimeout(el._t);
		el._t = setTimeout(function () { el.textContent = ''; }, 2500);
	}

	document.addEventListener('click', function (e) {
		var chip = e.target.closest('.sca-swatch__chip');
		if (chip) {
			var hex = chip.getAttribute('data-hex');
			copyText(hex).then(function () {
				chip.classList.add('is-copied');
				setTimeout(function () { chip.classList.remove('is-copied'); }, 1400);
				var root = chip.closest('.sca-palette');
				if (root) status(root, hex + ' copied');
			}, function () {
				var root = chip.closest('.sca-palette');
				if (root) status(root, 'Copy failed. The code is ' + hex);
			});
			return;
		}
		var all = e.target.closest('[data-sca-copy-all]');
		var dl = e.target.closest('[data-sca-download]');
		if (!all && !dl) return;
		var root = (all || dl).closest('[data-palette]');
		if (!root) return;
		var colors = JSON.parse(root.getAttribute('data-palette') || '[]');
		var name = root.getAttribute('data-name') || 'Palette';
		if (all) {
			var text = colors.map(function (c) { return c.name + ' ' + c.hex; }).join('\n');
			copyText(text).then(function () { status(root, colors.length + ' codes copied'); }, function () { status(root, 'Copy failed'); });
		} else {
			downloadPalette(name, colors);
			status(root, 'Palette downloaded');
		}
	});

	// Explorer tabs (WAI-ARIA tabs pattern, arrow keys move between seasons).
	function initExplorer(root) {
		var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
		function select(tab, focus) {
			tabs.forEach(function (t) {
				var on = t === tab;
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.tabIndex = on ? 0 : -1;
				var panel = document.getElementById(t.getAttribute('aria-controls'));
				if (panel) panel.hidden = !on;
			});
			if (focus) tab.focus();
		}
		tabs.forEach(function (tab, i) {
			tab.addEventListener('click', function () { select(tab, false); });
			tab.addEventListener('keydown', function (e) {
				var next = null;
				if (e.key === 'ArrowRight' || e.key === 'ArrowDown') next = tabs[(i + 1) % tabs.length];
				else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') next = tabs[(i - 1 + tabs.length) % tabs.length];
				else if (e.key === 'Home') next = tabs[0];
				else if (e.key === 'End') next = tabs[tabs.length - 1];
				if (next) { e.preventDefault(); select(next, true); }
			});
		});
		var hash = location.hash.replace('#', '');
		var fromHash = hash && document.getElementById('sca-explorer-' + hash + '-tab');
		if (fromHash) select(fromHash, false);
	}

	function ready() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-sca-explorer]'), initExplorer);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
	else ready();

	window.SCAPalette = { copy: copyText, download: downloadPalette };
})();
