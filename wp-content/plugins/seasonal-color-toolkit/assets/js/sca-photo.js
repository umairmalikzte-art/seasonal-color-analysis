/**
 * Seasonal Color Toolkit – AI color analysis from a photo ([sca_photo]).
 *
 * Privacy: the photo is drawn into a <canvas> and analysed on the device.
 * Nothing is uploaded. Face landmarks come from MediaPipe FaceLandmarker,
 * loaded on demand from a CDN (the model file only, never the photo). If it
 * cannot load, the visitor taps to pick skin, hair and eye colors.
 *
 * Measurement: SCAPhotoMeasure (unit tested). Ranking: SCAEngine.rank() with
 * the same admin-editable season prototypes and weights as the quiz.
 * Config: window.SCA_PHOTO = { seasons, settings, compareUrl, quizUrl, guides, site }.
 */
(function () {
	'use strict';
	var root = document.querySelector('.sca-ph');
	var CFG = window.SCA_PHOTO;
	var M = window.SCAPhotoMeasure, E = window.SCAEngine;
	if (!root || !CFG || !M || !E) return;

	var MP_VER = '0.10.14';
	var MP_URL = 'https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@' + MP_VER;
	var MODEL = 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task';

	var $ = function (s) { return root.querySelector(s); };
	var $$ = function (s) { return Array.prototype.slice.call(root.querySelectorAll(s)); };
	var clamp = function (x, a, b) { return Math.max(a, Math.min(b, x)); };
	var esc = function (t) { return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
	var bySlug = {};
	CFG.seasons.forEach(function (s) { bySlug[s.slug] = s; });

	var state = { canvas: null, ctx: null, clean: null, data: null, W: 0, H: 0, landmarks: null, markers: null,
		samples: {}, manual: false, tapTarget: null, answers: {}, analysis: null, result: null, drape: null };
	var landmarkerPromise = null;

	function loadLandmarker() {
		if (landmarkerPromise) return landmarkerPromise;
		landmarkerPromise = import(MP_URL + '/vision_bundle.mjs').then(function (mod) {
			return mod.FilesetResolver.forVisionTasks(MP_URL + '/wasm').then(function (fs) {
				return mod.FaceLandmarker.createFromOptions(fs, { baseOptions: { modelAssetPath: MODEL }, runningMode: 'IMAGE', numFaces: 1 });
			});
		});
		landmarkerPromise.catch(function () { landmarkerPromise = null; });
		return landmarkerPromise;
	}

	function palette(s, cats) { return (s.palette || []).filter(function (c) { return cats.indexOf(c.category) !== -1; }); }
	function best(s) { return palette(s, ['core', 'accent']).slice(0, 12); }
	function avoid(s) { return palette(s, ['avoid']); }

	/* ---------- steps ---------- */
	function step(n) {
		$$('.sca-ph-step').forEach(function (el) { el.hidden = el.getAttribute('data-step') !== String(n); });
		$$('.sca-ph-progress li').forEach(function (li, i) { li.classList.toggle('is-active', i + 1 === n); li.classList.toggle('is-done', i + 1 < n); });
		var top = root.getBoundingClientRect().top + window.pageYOffset - 20;
		if (window.pageYOffset > top) window.scrollTo({ top: top, behavior: 'smooth' });
	}
	function status(msg, kind) { var s = $('.sca-ph-status'); s.textContent = msg || ''; s.className = 'sca-ph-status' + (kind ? ' is-' + kind : ''); }

	/* ---------- photo intake ---------- */
	function loadFile(file) {
		if (!file || !/^image\//.test(file.type)) { status('Please choose a photo (JPG or PNG).', 'error'); step(2); return; }
		var url = URL.createObjectURL(file), img = new Image();
		img.onload = function () { setImage(img); URL.revokeObjectURL(url); };
		img.onerror = function () { step(2); status('That photo could not be opened. Try a JPG or PNG.', 'error'); };
		img.src = url;
	}
	function setImage(src) {
		var w = src.naturalWidth || src.width, h = src.naturalHeight || src.height, s = Math.min(1, 1100 / Math.max(w, h));
		var c = state.canvas = $('.sca-ph-photo');
		c.width = Math.round(w * s); c.height = Math.round(h * s);
		state.W = c.width; state.H = c.height;
		state.ctx = c.getContext('2d', { willReadFrequently: true });
		state.ctx.drawImage(src, 0, 0, c.width, c.height);
		state.data = state.ctx.getImageData(0, 0, c.width, c.height);
		state.clean = document.createElement('canvas'); state.clean.width = c.width; state.clean.height = c.height;
		state.clean.getContext('2d').drawImage(c, 0, 0);
		state.samples = {}; state.landmarks = null; state.markers = null; state.analysis = null;
		$('.sca-ph-manual').hidden = true; $('.sca-ph-samples').innerHTML = ''; $('.sca-ph-quality').innerHTML = '';
		$('.sca-ph-next').disabled = true;
		step(2); detect();
	}

	/* ---------- sampling ---------- */
	function pixels(cx, cy, r) {
		var out = [], d = state.data.data;
		for (var y = -r; y <= r; y++) for (var x = -r; x <= r; x++) {
			if (x * x + y * y > r * r) continue;
			var px = Math.round(cx + x), py = Math.round(cy + y);
			if (px < 0 || py < 0 || px >= state.W || py >= state.H) continue;
			var i = (py * state.W + px) * 4; out.push([d[i], d[i + 1], d[i + 2]]);
		}
		return out;
	}
	function patch(p, r, lo, hi) { return M.robustColor(pixels(p.x, p.y, r), lo, hi); }

	function fromLandmarks(lm) {
		var P = function (i) { return { x: lm[i].x * state.W, y: lm[i].y * state.H }; };
		var faceH = Math.hypot(P(10).x - P(152).x, P(10).y - P(152).y), r = Math.max(3, Math.round(faceH * 0.035));
		var ux = (P(10).x - P(152).x) / faceH, uy = (P(10).y - P(152).y) / faceH; // chin → forehead
		var skinIdx = [50, 101, 118, 280, 330, 347, 151, 9, 199, 205, 425];
		var skinPts = skinIdx.map(P);
		var top = P(10);
		var hairPts = [
			{ x: top.x + ux * faceH * 0.16, y: top.y + uy * faceH * 0.16 },
			{ x: top.x + ux * faceH * 0.22 - uy * faceH * 0.18, y: top.y + uy * faceH * 0.22 + ux * faceH * 0.18 },
			{ x: top.x + ux * faceH * 0.22 + uy * faceH * 0.18, y: top.y + uy * faceH * 0.22 - ux * faceH * 0.18 },
			{ x: P(127).x - (P(356).x - P(127).x) * 0.06, y: P(127).y - faceH * 0.12 },
			{ x: P(356).x + (P(356).x - P(127).x) * 0.06, y: P(356).y - faceH * 0.12 }
		];
		var irisR = Math.max(2, Math.round(Math.hypot(P(469).x - P(471).x, P(469).y - P(471).y) / 2 * 0.55));
		var scleraPts = [[468, 33], [468, 133], [473, 263], [473, 362]].map(function (pair) {
			var a = P(pair[0]), b = P(pair[1]); return { x: a.x + (b.x - a.x) * 0.55, y: a.y + (b.y - a.y) * 0.55 };
		});
		state.markers = { skin: skinPts, hair: hairPts, eyes: [P(468), P(473)], sclera: scleraPts, r: r };
		return {
			skin: M.average(skinPts.map(function (p) { return patch(p, r); })),
			cheekL: M.average([50, 101, 118].map(function (i) { return patch(P(i), r); })),
			cheekR: M.average([280, 330, 347].map(function (i) { return patch(P(i), r); })),
			hair: M.average(hairPts.map(function (p) { return patch(p, Math.round(r * 1.2), 0.1, 0.3); })),
			eyes: M.average([468, 473].map(function (i) { return patch(P(i), irisR, 0.25, 0.1); })),
			sclera: M.average(scleraPts.map(function (p) { return patch(p, Math.max(2, Math.round(irisR * 0.6)), 0.5, 0.05); }))
		};
	}

	function detect() {
		status('Finding your face… (on your device)', 'busy');
		drawPhoto();
		var timeout = new Promise(function (_, rej) { setTimeout(function () { rej(new Error('timeout')); }, 15000); });
		Promise.race([loadLandmarker(), timeout]).then(function (lm) {
			var res = lm.detect(state.canvas);
			if (!res.faceLandmarks || !res.faceLandmarks.length) throw new Error('noface');
			state.landmarks = res.faceLandmarks[0];
			state.samples = fromLandmarks(state.landmarks);
			state.manual = false;
			analyse();
		}).catch(function (e) {
			state.manual = true; state.markers = null;
			status(e && e.message === 'noface'
				? 'No face found. Tap the photo to pick your skin, hair and eye color.'
				: 'Face detection could not load. Tap the photo to pick your skin, hair and eye color.', 'warn');
			startManual();
		});
	}

	function startManual() { $('.sca-ph-manual').hidden = false; state.tapTarget = 'skin'; tapButtons(); }
	function tapButtons() {
		$$('[data-tap]').forEach(function (b) {
			var k = b.getAttribute('data-tap');
			b.classList.toggle('is-active', k === state.tapTarget);
			b.classList.toggle('is-done', !!state.samples[k]);
			b.setAttribute('aria-pressed', k === state.tapTarget ? 'true' : 'false');
		});
	}
	function onTap(ev) {
		if (!state.manual || !state.tapTarget) return;
		var rect = state.canvas.getBoundingClientRect();
		var p = { x: (ev.clientX - rect.left) * state.W / rect.width, y: (ev.clientY - rect.top) * state.H / rect.height };
		var r = Math.max(3, Math.round(state.W * 0.012));
		var k = state.tapTarget;
		state.samples[k] = patch(p, k === 'eyes' || k === 'sclera' ? Math.max(2, r >> 1) : r);
		state.markers = state.markers || { skin: [], hair: [], eyes: [], sclera: [], r: r };
		state.markers[k] = [p];
		state.tapTarget = ['skin', 'hair', 'eyes', 'sclera'].filter(function (x) { return !state.samples[x]; })[0] || null;
		tapButtons(); drawPhoto();
		if (state.samples.skin && state.samples.hair && state.samples.eyes) analyse();
	}

	/* ---------- analysis ---------- */
	function analyse() {
		state.analysis = M.analyse(state.samples, {});
		drawPhoto();
		var a = state.analysis, q = a.quality;
		$('.sca-ph-samples').innerHTML = chip('Skin', a.hex.skin) + chip('Hair', a.hex.hair) + chip('Eyes', a.hex.eyes);
		$('.sca-ph-quality').innerHTML = '<div class="sca-ph-meter"><span style="width:' + q.score + '%"></span></div><p><b>Photo quality: ' + q.score + '/100</b></p>' +
			(q.tips.length ? '<ul>' + q.tips.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') + '</ul>' : '<p>Even light, no strong color cast.</p>');
		status(q.tips.length ? 'Photo read. See the tips below.' : 'Photo read. The lighting looks good.', q.score < 60 ? 'warn' : 'ok');
		$('.sca-ph-next').disabled = false;
	}
	function chip(label, hex) {
		return '<div class="sca-ph-chip"><span class="sca-ph-chip-sw" style="background:' + hex + '"></span><span><b>' + label + '</b><small>' + hex + '</small></span></div>';
	}

	function drawPhoto() {
		var c = state.ctx; if (!c) return;
		c.putImageData(state.data, 0, 0);
		var m = state.markers; if (!m) return;
		var draw = function (pts, col) {
			(pts || []).forEach(function (p) {
				c.beginPath(); c.arc(p.x, p.y, Math.max(4, m.r * 0.8), 0, Math.PI * 2);
				c.lineWidth = 3; c.strokeStyle = '#fff'; c.stroke(); c.lineWidth = 1.5; c.strokeStyle = col; c.stroke();
			});
		};
		draw(m.skin, '#c2603f'); draw(m.hair, '#3d405b'); draw(m.eyes, '#2a9d8f'); draw(m.sclera, '#8a8a8a');
	}

	/* ---------- result ---------- */
	function describe(d, a) {
		return 'Your coloring reads <b>' + esc(E.label('warmth', d.warmth).toLowerCase()) + '</b> (' + esc(a.undertone.toLowerCase()) + ' undertone), <b>' +
			esc(E.label('depth', d.depth).toLowerCase()) + '</b> in depth, <b>' + esc(E.label('chroma', d.chroma).toLowerCase()) + '</b> in chroma, with <b>' +
			esc(E.label('contrast', d.contrast).toLowerCase()) + '</b> contrast.';
	}
	function axis(label, val, left, right) {
		return '<div class="sca-ph-axis"><b>' + label + '</b><div class="sca-ph-axis-track"><span style="left:' + ((val + 1) / 2 * 100).toFixed(0) + '%"></span></div>' +
			'<div class="sca-ph-axis-l"><small>' + left + '</small><small>' + right + '</small></div></div>';
	}
	function swatches(list, cls) {
		return list.map(function (c) {
			return '<button type="button" class="sca-ph-sw ' + (cls || '') + '" data-hex="' + c.hex + '" style="background:' + c.hex + '" title="' + esc(c.name) + ' ' + c.hex + '" aria-label="Drape ' + esc(c.name) + '"></button>';
		}).join('');
	}

	function renderResult() {
		var a = M.analyse(state.samples, state.answers);
		var ranking = E.rank(a.dims, CFG.seasons, (CFG.settings && CFG.settings.weights) || {});
		var conf = M.confidence(ranking, a.quality.score);
		var top = bySlug[ranking[0].slug], alt = bySlug[ranking[1].slug];
		state.result = { a: a, ranking: ranking, conf: conf, top: top };
		var g = CFG.guides || {};
		var html =
			'<div class="sca-ph-head"><p class="sca-ph-kicker">Your best match</p><h2>' + esc(top.name) + '</h2>' +
			(top.altNames && top.altNames.length ? '<p class="sca-ph-alias">Also called ' + esc(top.altNames.join(', ')) + '</p>' : '') +
			'<p>' + esc(top.description) + '</p><p>' + describe(a.dims, a) + '</p>' +
			'<div class="sca-ph-conf"><span>Confidence</span><div class="sca-ph-meter"><span style="width:' + conf.score + '%"></span></div><b>' + conf.score + '%</b></div></div>' +
			'<div class="sca-ph-top3">' + ranking.slice(0, 3).map(function (r, i) {
				var s = bySlug[r.slug];
				return '<a class="sca-ph-alt' + (i === 0 ? ' is-top' : '') + '" href="' + esc(s.url) + '"><span class="sca-ph-alt-sw">' +
					best(s).slice(0, 5).map(function (c) { return '<i style="background:' + c.hex + '"></i>'; }).join('') +
					'</span><b>' + esc(s.name) + '</b><small>' + conf.pct[i] + '% match</small></a>';
			}).join('') + '</div>' +
			'<div class="sca-ph-facts">' +
			'<div><small>Skin tone</small><b>' + esc(a.skinTone) + '</b>' + (g.skin ? '<a href="' + esc(g.skin) + '">Skin tone chart</a>' : '') + '</div>' +
			'<div><small>Undertone</small><b>' + esc(a.undertone) + '</b>' + (g.undertone ? '<a href="' + esc(g.undertone) + '">About undertones</a>' : '') + '</div>' +
			'<div><small>Family</small><b>' + esc(top.family.charAt(0).toUpperCase() + top.family.slice(1)) + '</b></div></div>' +
			'<h3>Your four color qualities</h3><div class="sca-ph-axes">' +
			axis('Undertone', a.dims.warmth, 'Cool', 'Warm') + axis('Depth', a.dims.depth, 'Light', 'Deep') +
			axis('Chroma', a.dims.chroma, 'Soft', 'Clear') + axis('Contrast', a.dims.contrast, 'Low', 'High') + '</div>' +
			'<h3>Virtual draping</h3><p class="sca-ph-help">Tap a color to hold it under your face like a fabric drape. Turn on split view to compare a best color with one to wear with care.</p>' +
			'<div class="sca-ph-drape-wrap"><canvas class="sca-ph-drape" width="420" height="520" aria-label="Your photo with a color drape"></canvas><div>' +
			'<p class="sca-ph-sub">Best colors for ' + esc(top.name) + '</p><div class="sca-ph-swatches">' + swatches(best(top)) + '</div>' +
			'<p class="sca-ph-sub">Wear with care</p><div class="sca-ph-swatches">' + swatches(avoid(top), 'is-avoid') + '</div>' +
			'<label class="sca-ph-split"><input type="checkbox" data-split> Split view: best vs wear with care</label></div></div>' +
			'<div class="sca-ph-actions">' +
			'<a class="sca-btn sca-btn--primary" href="' + esc(top.url) + '">See the full ' + esc(top.name) + ' palette</a>' +
			'<button type="button" class="sca-btn sca-btn--ghost" data-act="copy">Copy hex codes</button>' +
			'<button type="button" class="sca-btn sca-btn--ghost" data-act="card">Download result card</button>' +
			(CFG.compareUrl ? '<a class="sca-btn sca-btn--ghost" rel="nofollow" href="' + esc(CFG.compareUrl + '?a=' + top.slug + '&b=' + alt.slug) + '">Compare with ' + esc(alt.name) + '</a>' : '') +
			(CFG.quizUrl ? '<a class="sca-btn sca-btn--ghost" href="' + esc(CFG.quizUrl) + '">Double-check with the quiz</a>' : '') +
			'<button type="button" class="sca-btn sca-btn--ghost" data-act="restart">Try another photo</button></div>' +
			'<p class="sca-ph-note">This is an estimate from one photo. Camera, light and makeup change how color looks. Confirm it by holding your best and wear-with-care colors near your face in daylight.</p>';
		$('.sca-ph-result').innerHTML = html;
		state.drape = best(top)[0] ? best(top)[0].hex : '#ffffff';
		var first = $('.sca-ph-sw'); if (first) first.classList.add('is-on');
		step(4);
		drawDrape();
	}

	/* ---------- virtual draping ---------- */
	function faceBox() {
		if (state.landmarks) {
			var xs = state.landmarks.map(function (p) { return p.x * state.W; }), ys = state.landmarks.map(function (p) { return p.y * state.H; });
			var x0 = Math.min.apply(null, xs), x1 = Math.max.apply(null, xs), y0 = Math.min.apply(null, ys), y1 = Math.max.apply(null, ys);
			var w = x1 - x0, h = y1 - y0;
			return { x: x0 - w * 0.45, y: y0 - h * 0.45, w: w * 1.9, h: h * 1.75, chinY: y1 };
		}
		var m = state.markers && state.markers.skin && state.markers.skin[0];
		var cx = m ? m.x : state.W / 2, cy = m ? m.y : state.H / 2, sz = Math.min(state.W, state.H) * 0.7;
		return { x: cx - sz / 2, y: cy - sz * 0.55, w: sz, h: sz * 1.2, chinY: cy + sz * 0.25 };
	}
	function drawDrape() {
		var cv = $('.sca-ph-drape'); if (!cv || !state.result) return;
		var c = cv.getContext('2d'), b = faceBox(), split = ($('[data-split]') || {}).checked, top = state.result.top;
		var sc = Math.max(cv.width / b.w, cv.height / b.h);
		c.fillStyle = '#eee'; c.fillRect(0, 0, cv.width, cv.height);
		c.drawImage(state.clean, b.x, b.y, cv.width / sc, cv.height / sc, 0, 0, cv.width, cv.height);
		var y = Math.min(cv.height - 120, (b.chinY - b.y) * sc + 10);
		var cape = function (col) {
			c.fillStyle = col; c.beginPath(); c.moveTo(-20, cv.height); c.lineTo(-20, y + 60);
			c.quadraticCurveTo(cv.width / 2, y - 10, cv.width + 20, y + 60); c.lineTo(cv.width + 20, cv.height); c.closePath(); c.fill();
		};
		var care = avoid(top)[0];
		if (split && care) {
			c.save(); c.beginPath(); c.rect(0, 0, cv.width / 2, cv.height); c.clip(); cape(state.drape); c.restore();
			c.save(); c.beginPath(); c.rect(cv.width / 2, 0, cv.width / 2, cv.height); c.clip(); cape(care.hex); c.restore();
			c.fillStyle = 'rgba(255,255,255,.9)'; c.fillRect(cv.width / 2 - 1, 0, 2, cv.height);
			tag(c, 'Best', 12, 26); tag(c, 'With care', cv.width - 96, 26);
		} else {
			cape(state.drape);
		}
	}
	function tag(c, t, x, y) {
		c.font = '600 14px system-ui, sans-serif';
		c.fillStyle = 'rgba(0,0,0,.6)'; c.fillRect(x - 6, y - 17, c.measureText(t).width + 12, 24);
		c.fillStyle = '#fff'; c.fillText(t, x, y);
	}

	/* ---------- result card ---------- */
	function downloadCard() {
		var r = state.result, s = r.top, cv = document.createElement('canvas'); cv.width = 1080; cv.height = 1350;
		var c = cv.getContext('2d');
		c.fillStyle = '#fbf7f1'; c.fillRect(0, 0, 1080, 1350);
		c.fillStyle = '#2b2a29'; c.font = '600 34px Georgia, serif'; c.fillText('My color season', 80, 120);
		c.font = '700 96px Georgia, serif'; c.fillText(s.name, 80, 230);
		c.font = '28px system-ui, sans-serif'; c.fillStyle = '#6b6560';
		c.fillText(r.a.skinTone + ' skin · ' + r.a.undertone + ' undertone · ' + r.conf.score + '% confidence', 80, 290);
		best(s).forEach(function (col, i) {
			var x = 80 + (i % 4) * 235, y = 350 + Math.floor(i / 4) * 250;
			c.fillStyle = col.hex; rr(c, x, y, 210, 170, 18);
			c.fillStyle = '#2b2a29'; c.font = '600 22px system-ui'; c.fillText(col.name, x, y + 205);
			c.fillStyle = '#6b6560'; c.font = '20px system-ui'; c.fillText(col.hex, x, y + 232);
		});
		c.fillStyle = '#2b2a29'; c.font = '600 30px system-ui';
		c.fillText('Closest alternatives: ' + bySlug[r.ranking[1].slug].name + ', ' + bySlug[r.ranking[2].slug].name, 80, 1170);
		c.fillStyle = '#6b6560'; c.font = '24px system-ui'; c.fillText((CFG.site || location.hostname) + ' · AI Color Analysis', 80, 1260);
		cv.toBlob(function (b) {
			var link = document.createElement('a'); link.href = URL.createObjectURL(b); link.download = s.slug + '-color-analysis.png';
			document.body.appendChild(link); link.click(); link.remove();
			setTimeout(function () { URL.revokeObjectURL(link.href); }, 2000);
		});
	}
	function rr(c, x, y, w, h, r) { c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath(); c.fill(); }

	/* ---------- camera ---------- */
	var stream = null;
	function openCamera() {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { status('Your browser cannot use the camera here. Upload a photo instead.', 'error'); return; }
		navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 } }, audio: false }).then(function (st) {
			stream = st; var v = $('.sca-ph-video'); v.srcObject = st; $('.sca-ph-cam').hidden = false; $('.sca-ph-drop').hidden = true; v.play();
		}).catch(function () { alert('Camera access was blocked. You can upload a photo instead.'); });
	}
	function snap() {
		var v = $('.sca-ph-video'), c = document.createElement('canvas'); c.width = v.videoWidth; c.height = v.videoHeight;
		var x = c.getContext('2d'); x.translate(c.width, 0); x.scale(-1, 1); x.drawImage(v, 0, 0);
		closeCamera();
		var img = new Image(); img.onload = function () { setImage(img); }; img.src = c.toDataURL('image/jpeg', 0.92);
	}
	function closeCamera() {
		if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
		stream = null; $('.sca-ph-cam').hidden = true; $('.sca-ph-drop').hidden = false;
	}

	/* ---------- events ---------- */
	$('.sca-ph-file').addEventListener('change', function (e) { loadFile(e.target.files[0]); });
	var drop = $('.sca-ph-drop');
	['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
	['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
	drop.addEventListener('drop', function (e) { if (e.dataTransfer.files[0]) loadFile(e.dataTransfer.files[0]); });
	$('.sca-ph-photo').addEventListener('click', onTap);
	$$('.sca-ph-q input').forEach(function (i) { i.addEventListener('change', function () { state.answers[i.name] = i.value; }); });

	root.addEventListener('click', function (e) {
		var sw = e.target.closest('.sca-ph-sw');
		if (sw) { state.drape = sw.getAttribute('data-hex'); $$('.sca-ph-sw').forEach(function (x) { x.classList.toggle('is-on', x === sw); }); drawDrape(); return; }
		var tap = e.target.closest('[data-tap]');
		if (tap) { state.tapTarget = tap.getAttribute('data-tap'); tapButtons(); return; }
		var btn = e.target.closest('[data-act]'); if (!btn) return;
		var act = btn.getAttribute('data-act');
		if (act === 'camera') openCamera();
		else if (act === 'snap') snap();
		else if (act === 'cam-cancel') closeCamera();
		else if (act === 'next') step(3);
		else if (act === 'result') renderResult();
		else if (act === 'skip') { state.answers = {}; $$('.sca-ph-q input').forEach(function (i) { i.checked = false; }); renderResult(); }
		else if (act === 'back') step(1);
		else if (act === 'manual') {
			state.manual = true; state.samples = {}; state.markers = null; state.landmarks = null; $('.sca-ph-next').disabled = true;
			drawPhoto(); startManual(); status('Tap your cheek, then your hair roots, then the colored part of your eye.', 'warn');
		} else if (act === 'copy') {
			var s = state.result.top, text = s.name + ': ' + best(s).map(function (c) { return c.name + ' ' + c.hex; }).join(', ');
			if (navigator.clipboard) navigator.clipboard.writeText(text).then(function () { btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = 'Copy hex codes'; }, 1500); });
		} else if (act === 'card') downloadCard();
		else if (act === 'restart') {
			state.answers = {}; $$('.sca-ph-q input').forEach(function (i) { i.checked = false; });
			$('.sca-ph-file').value = ''; step(1);
		}
	});
	root.addEventListener('change', function (e) { if (e.target.hasAttribute('data-split')) drawDrape(); });

	// Fetch the face model when the tool scrolls near the viewport, so it is ready on upload.
	if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (en) {
			if (en[0].isIntersecting) { loadLandmarker().catch(function () {}); io.disconnect(); }
		}, { rootMargin: '200px' });
		io.observe(root);
	}

	// Test hook (used by the Playwright check, harmless in production).
	window.SCAPhoto = { setImage: setImage, state: state };
})();
