# PRD status (v1.0)

Last updated: 21 November 2026. The site runs locally in WordPress
Playground. It has not been deployed.

## Acceptance criteria (PRD section 21)

| # | Criterion | Status | How it was checked |
|---|---|---|---|
| 1 | Homepage works | ✅ | All 9 PRD sections render. The "Find My Color Season" button is above the fold at 1366×768, 390×844 and 360×640 |
| 2 | All 12 season pages work | ✅ | 200, one H1 each, at `/{season}-color-palette/` |
| 3 | Quiz works from start to finish | ✅ | Chrome run of 15 questions: back, empty-answer error, keyboard, restart |
| 4 | Scoring tests pass | ✅ | `npm test`: 23 of 23 |
| 5 | All 12 seasons can be returned | ✅ | One calibration persona for each season |
| 6 | Alternative season is displayed | ✅ | Result card, plus an "uncertain" notice when the top two are close |
| 7 | Palette HEX copying works | ✅ | Clipboard checked in Chrome: one swatch and "copy all" |
| 8 | Palette downloads work | ✅ | PNG of about 320 KB, drawn in the browser |
| 9 | Comparison tool works | ✅ | Any two seasons. Works without JavaScript (GET form). `?a=&b=` pages are noindex |
| 10 | Admin can edit all season data | ✅ | Seasons, Palettes, and the Season Guides post type |
| 11 | Admin can edit questions and weights | ✅ | Quiz Questions, Quiz Answers, Scoring Settings, with a live preview on the dashboard |
| 12 | Mobile layout works | ✅ | No horizontal overflow at 390 and 360 px |
| 13 | No critical JS or PHP errors | ✅ | No console errors in the Chrome run, no PHP notices on 22 URLs |
| 14 | SEO metadata works | ✅ | Built into the plugin (titles, meta, Open Graph, JSON-LD, breadcrumbs). It switches off automatically if Rank Math or Yoast is installed. Audit of 22 URLs: no issues. See SEO-STRATEGY.md |
| 15 | Sitemap is accessible | ✅ | `/wp-sitemap.xml` (WordPress core) |
| 16 | HTTPS active on deployment | ⏳ | At deployment |
| 17 | Backups configured | ⏳ | At deployment |
| 18 | No copied or unlicensed assets | ✅ | All graphics are generated from our own palette data (`npm run build:images`). Trend names link to their sources |

## Lighthouse (local, mobile)

| Page | Performance | Accessibility | Best practices | SEO | LCP | CLS | TBT |
|---|---|---|---|---|---|---|---|
| Homepage | 88–94 | 100 | 100 | 100 | 1.4–1.6 s | 0 | 160–340 ms |
| Quiz | 97 | 100 | 100 | 100 | 1.6 s | 0 | 80 ms |
| Soft Autumn | 98 | 100 | 100 | 100 | 1.4 s | 0 | 0 ms |

The server response was about 2 s, because Playground runs PHP in
WebAssembly without a cache. Real hosting with a page cache should be far
faster. On the homepage, TBT varied between runs. Most of it was
unattributed style and layout work plus the GeneratePress menu script, on
the same machine that was running the server.

## Done (P0, plus parts of P1)

- WordPress setup: GeneratePress with a child theme, and the custom plugin.
- 12-season database and curated palettes: 24 wearable colors per season,
  plus 4 "wear with care" colors.
- Quiz with 15 questions. Every question has a "Not sure" option and some
  have swatches.
- Scoring engine with tests and a calibration report.
- Result: likely season, family, profile, match strength, alternative,
  palette, colors to approach with care, clothing and makeup tips, copy,
  download, share and restart.
- 12 season guides, the 12-seasons hub with the palette explorer, the
  comparison tool, one written comparison, two guides, About and Privacy.
- Admin: all 8 sections in PRD 14.

## Added after P0

- **Palette generator (PRD tool C and more):** five harmonies, trend colors mapped to each season, a "check my colors" tab and an admin Trends page.
- **SEO, AEO and performance:** see SEO-STRATEGY.md. The homepage hero starts the quiz with plain links.

## Next

1. **Optional:** install Rank Math or Yoast if the owner prefers one. The
   plugin's SEO module then switches off by itself.
2. **P1 content:** 4 more comparisons and 4 styling guides (see the URL map
   in KEYWORD-RESEARCH.md §4), bringing the total to 30 pages.
3. Original images: 12 palette graphics (they can come from the PNG export),
   four family graphics and an OG image. Keep an image license log.
4. **Owner:** a real author or about details, a contact e-mail, a review of
   the privacy policy, the domain and hosting.
5. **Calibration review (PRD 12):** a human should look at `npm run
   calibrate`. With random answers, True Winter and True Spring rarely win
   (1–3 %). That is expected with inconsistent answers, but it should be
   checked against real test cases.
6. P2 (outfit matcher) only after P0 is approved.
