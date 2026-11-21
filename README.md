# Color Analysis

An SEO-focused WordPress site with a free AI color analysis from a selfie
(analysed in the browser, never uploaded), a 12-season color analysis quiz,
season palettes with hex codes, skin tone and undertone guides, a palette
explorer and a season comparison tool. Target markets: US, UK, Canada and Australia.

- **Theme:** GeneratePress (free) with a small child theme, `seasonal-color`,
  that only handles design.
- **Plugin:** `seasonal-color-toolkit` contains all quiz, scoring, palette,
  comparison and admin logic. It works with any theme.
- **Keyword research and site structure:** see
  [docs/KEYWORD-RESEARCH.md](docs/KEYWORD-RESEARCH.md).
- **SEO, AEO, schema, speed and the off-page plan:** see
  [docs/SEO-STRATEGY.md](docs/SEO-STRATEGY.md).
- **PRD status:** see [docs/STATUS.md](docs/STATUS.md).
- **Competitor gap plan (color-analysis.app):** see [docs/COMPETITOR-GAP.md](docs/COMPETITOR-GAP.md).

## Run it locally (free, no PHP or MySQL needed)

Requirements: Node.js 20 or newer.

```bash
npm install        # first time only
npm start          # WordPress at http://127.0.0.1:9500 (admin / password)
npm test           # engine, color and photo tests (58)
npm run calibrate  # calibration report for human review
npm run lint:php   # PHP syntax check (runs in Playground PHP)
npm run build      # minified JS + palette images (images need puppeteer-core, see tools/build-images.js)
```

`npm start` runs [WordPress Playground](https://wordpress.github.io/wordpress-playground/).
On every start it:

1. installs GeneratePress from wordpress.org
2. mounts the child theme and the plugin from this folder
3. runs `setup/seed.php`, which creates the tables, default data, pages and
   menu

The database lives in memory and is rebuilt on each start. Edits to the
theme and plugin files show up on refresh.

## Plugin structure

```
wp-content/plugins/seasonal-color-toolkit/
  seasonal-color-toolkit.php     bootstrap, activation
  data/defaults.json             12 seasons, curated palettes, 15 questions, weights
  includes/
    class-sca-install.php        dbDelta tables (prefix-aware), default data
    class-sca-repository.php     cached reads, browser config
    class-sca-post-types.php     color_season, color_guide, season_comparison at root URLs
    class-sca-render.php         palette, meters and card HTML
    class-sca-shortcodes.php     [sca_quiz], [sca_palette], [sca_compare] …, breadcrumbs
    class-sca-assets.php         scripts only on pages that use them
    class-sca-starter-content.php  pages and guides (never overwrites)
    class-sca-seo.php            titles, meta, Open Graph, JSON-LD, robots, sitemap, llms.txt
    starter/seasons.php          editorial text for the 12 season guides
    starter/guides.php           skin tone, undertone, family and styling guides (block arrays)
    admin/class-sca-admin.php    dashboard, seasons, palettes, questions,
                                 answers, scoring, comparisons, settings
  assets/js/sca-engine.js        scoring engine (browser + Node, unit tested)
  assets/js/sca-quiz.js          quiz app (answers never leave the browser)
  assets/js/sca-photo-measure.js photo measurement: white balance, CIELAB, four dimensions (unit tested)
  assets/js/sca-photo.js         [sca_photo] AI color analysis UI: face landmarks, draping, result card
  assets/css/sca-photo.css       photo tool styles (inlined only where [sca_photo] is used)
  assets/js/sca-palette.js       copy, copy all, PNG download, explorer tabs
  assets/js/sca-color.js         OKLab color math + palette generator (unit tested)
  assets/js/sca-generator.js     generator, trend panel and color checker UI
  assets/img/                    generated OG images and palette graphics
  assets/css/sca.css             component styles (themeable with --sca-* variables)
```

Tables: `{prefix}sca_questions`, `{prefix}sca_answers`, `{prefix}sca_seasons`
and `{prefix}sca_palette_colors`. No table stores visitor answers.

## Scoring (PRD section 12)

Each answer has a score from -1 to +1 on the dimensions it informs. Each
dimension score is the weighted average, normalized so that always choosing
the strongest answer gives ±1. Seasons are prototype points in four
dimensions, ranked by weighted Euclidean distance. The closest season is
the primary result and the second closest is the alternative. Results are
marked as tentative when there are too few clear answers, and as uncertain
when the top two seasons are closer than the margin. Weights, prototypes,
scores and thresholds are all editable in **Color Toolkit** in the admin.

## AI color analysis (photo)

`[sca_photo]` adds the photo tool to any page (used on `/ai-color-analysis/`
and `/skin-tone-test/`). The photo is drawn into a canvas and analysed in the
browser; nothing is uploaded. MediaPipe FaceLandmarker (model and WASM from
jsDelivr and Google Cloud Storage) finds 478 face points. Skin, hair, iris and
eye-white samples are trimmed of shadow and shine, white-balanced from the eye
whites, converted to CIELAB and turned into the same four dimensions as the
quiz. Seasons are ranked with `SCAEngine.rank()` and the admin-editable
prototypes, so the quiz and photo tool agree. If the model cannot load, the
visitor taps the photo to pick the colors. Calibration cases are in
`tests/photo.test.js`.
