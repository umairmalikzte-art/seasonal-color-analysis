# Seasonal Color Analysis: SEO, AEO and performance

November 2026. Read this together with
[KEYWORD-RESEARCH.md](KEYWORD-RESEARCH.md), which covers the keyword groups,
the search intents and the URL map.

Ahrefs and Semrush were not connected, so no search volumes or KD scores
were used. The competitor findings come from the pages that rank for the
core queries.

## 1. Competitor gaps and how this site closes them

| Gap we found | Who has it | What this site does |
|---|---|---|
| The free value is a funnel into an app or a premium plan | seasonapproved.com (app, 7-day trial), facetune (app with a photo), mystylebox (paid palettes and guides) | Everything is free, needs no account, and the quiz answers stay in the browser |
| The quiz only uses 4 seasons, or needs a photo | e.l.f., facetune, JobCannon | 15-question quiz with 12 seasons, a closest alternative and an "uncertain" flag |
| Guides with no tools and no hex codes | theconceptwardrobe.com | Every palette can be copied, downloaded as a PNG and compared |
| Two sets of season names mixed together, or "Dark" vs "Deep" left unexplained | seasonapproved.com, theconceptwardrobe.com | One page per season. The alternative names appear in the H1 summary, the FAQ, `DefinedTerm.alternateName` and llms.txt |
| Only a few fixed "X vs Y" pages | seasonapproved.com (about 10) | A comparison tool for any pair, plus written pages for the pairs people confuse most |
| "Seasonal color palette generator" returns design tools (Figma, Octet, paint brands) | SERP | The only generator built from personal-color palettes, with trend colors mapped to each season |
| Trend colors are announced without saying who they suit | Pantone, WGSN and the media | A trend table showing which seasons each trend suits closely and which least, with a source link for each trend |
| Thin E-E-A-T: no method, no sources | Most quiz pages | A "How the quiz works" section, accuracy limits, editorial standards and a history section with named entities |

Things we do not copy:
- Celebrity season claims, because they are guesses about real people.
- Invented "% of people are X" statistics.
- Fake reviews or ratings in the schema.

## 2. What is built

### On-page
- **Titles:** one per page, 60 characters or fewer, with the keyword first.
  Set in the `_sca_seo_title` post meta. The brand is added automatically
  only when it fits.
- **Meta descriptions:** from the excerpt, 70–160 characters each. The
  audit found no duplicates.
- **Headings:** one H1 per page, a logical H2/H3 order, and an
  answer-first first paragraph (featured snippets, AI answers).
- **Season pages:**
  - "At a glance" facts: family, signature colors, best neutrals, colors to
    wear with care
  - meters for temperature, depth, chroma and contrast
  - an original palette image in WebP, lazy-loaded, with descriptive alt
    text
- **Navigation aids:** breadcrumbs and a "Last updated" date on inner pages.
- **Internal links:**
  - every season page links to its neighbours, its family, a comparison,
    the generator, the quiz and the hub
  - the quiz result links to the season, the alternative, the comparison
    and the generator

### Entities and NLP
- **Named entities covered:**
  - color analysis, the Munsell color system (hue, value, chroma), contrast,
    human skin color
  - complementary and analogous colors, the color wheel, OKLab
  - Pantone, WGSN and Coloro, Johannes Itten, Suzanne Caygill, Carole
    Jackson, *Color Me Beautiful*
- **Wikipedia links:** schema `about` and `mentions` use `sameAs` links to
  English Wikipedia. All URLs were checked and return 200. "Pantone Color
  of the Year" has no article, so it is not used.

### Structured data (one `@graph` per page, built in `includes/class-sca-seo.php`)
- **All pages:** Organization, WebSite, WebPage (or CollectionPage or
  AboutPage), plus BreadcrumbList on inner pages.
- **Quiz and generator:** WebApplication (free offer, featureList,
  `isAccessibleForFree`).
- **Season pages:** Article, with `about` set to DefinedTerm
  (`alternateName`, `inDefinedTermSet` "The 12 color seasons").
- **Comparisons:** Article, with `about` set to the two DefinedTerms.
- **Hub:** CollectionPage, with `mainEntity` set to an ItemList of the 12
  seasons.
- **FAQPage:** generated from the visible FAQ section (H3 plus paragraph),
  so schema and page always match. It is on 18 pages. Google shows FAQ rich
  results only for a few sites. The markup is kept for other engines and AI
  assistants.
- **What switches off:** if Yoast SEO or Rank Math is installed (PRD 10),
  the title, meta, Open Graph, schema and sitemap parts turn off, so
  nothing is output twice.

### Technical
- **Canonical:** WordPress core prints it. Tool URLs with parameters
  (`?a=`, `?b=`, `?season=`, `?harmony=`, `?trend=`) are noindex, follow.
- **noindex:** search, 404, attachment, paged and feed views. Author and
  date archives redirect with a 301 to the homepage. `/color-palettes/`
  redirects with a 301 to `/12-color-seasons/#palettes`.
- **Sitemap:** core `/wp-sitemap.xml` with pages, season guides, guides and
  comparisons. The users and taxonomies sitemaps are off, and the privacy
  policy is excluded.
- **robots.txt:** search URLs are blocked, inside the User-agent group.
- **`/llms.txt`:** a Markdown map of the tools, the 12 seasons (with
  alternative names) and the guides.
- **Head clean-up:** RSD, WLW, generator, shortlink, REST and oEmbed links,
  emoji, the extra feed links and dashicons for visitors are all removed.
  XML-RPC is off.
- **Custom 404:** links to the quiz, the 12 seasons and the generator.

### Performance (Core Web Vitals)
- No web fonts (system stacks). The LCP element is text.
- **CSS:** the plugin CSS and the child theme CSS are inlined and minified,
  so neither makes a render-blocking request. Block CSS loads only for the
  blocks a page uses (`should_load_separate_core_block_assets`).
- **JavaScript:**
  - minified with esbuild (`npm run build:js`), deferred, and loaded only
    on pages that use a tool
  - **the homepage loads no plugin JS:** the hero quiz start is plain links
- **Layout stability:** space for the tools is reserved before the scripts
  run, so CLS is 0.
- **Images:** WebP, lazy-loaded, with `width` and `height` set.

## 3. Measured (local, Lighthouse mobile)

| Page | Performance | Accessibility | Best practices | SEO | LCP | CLS | TBT |
|---|---|---|---|---|---|---|---|
| Homepage | 94 | 100 | 100 | 100 | 1.5 s | 0 | 220 ms |
| Palette generator | 97 | 100 | 100 | 100 | 1.6 s | 0 | 90 ms |
| Soft Autumn | 98 | 100 | 100 | 100 | 1.5 s | 0 | 110 ms |
| Quiz | 97 | 100 | 100 | 100 | 1.6 s | 0 | 0 ms |
| 12 Color Seasons | 95 | 100 | 100 | 100 | 2.2 s | 0 | 10 ms |

The server response was about 2 s, because Playground runs PHP in
WebAssembly without a cache, on the same machine that ran Lighthouse. Real
hosting with a page cache should cut this a lot. The custom audit of all 22
sitemap URLs found no issues: title length, description length, canonical,
one H1, Open Graph tags, image alt text, valid JSON-LD, and no duplicate
titles or descriptions.

## 4. Off-page plan (needs the live site and the owner's accounts)

Nothing here has been done yet.

1. **Pinterest first.** Seasonal color analysis is a very visual, very
   "pinnable" topic. Each season has a ready palette image
   (`{season}-palette.webp`) and a social card. Pin one per season, link
   to the season page, and refresh pins when trends change.
2. **Trend updates as digital PR.** Each time Pantone or WGSN × Coloro
   announces colors, update **Color Toolkit › Trends**. Then pitch "which
   seasons can wear the new Color of the Year" to fashion and beauty
   writers. Our trend table is the citable source.
3. **Communities:** give helpful answers in r/coloranalysis and on beauty
   forums. Link the comparison or generator page only where it answers the
   question.
4. **Free embeddable palette widget** for style bloggers, with a credit link
   (later, after launch).
5. **Profiles for the brand entity:** Pinterest, Instagram and TikTok. Add
   them to Organization `sameAs` once they exist.
6. Track referring domains monthly. Do not buy links.

## 5. Launch checklist

1. A real author or editor with a bio on About. Add `author: Person` to the
   Article schema then. Do not invent one.
2. A contact e-mail on About and in the privacy policy. Have the policy
   reviewed.
3. Hosting:
   - HTTPS and HTTP/2 or HTTP/3
   - Brotli or gzip
   - a full-page cache (LiteSpeed Cache if supported, see PRD 10)
   - backups
4. Check that `blog_public` is 1. Submit `/wp-sitemap.xml` to Google Search
   Console and Bing Webmaster Tools.
5. Connect GA4. The quiz only sends `sca_quiz_start` and
   `sca_quiz_complete` with the season, never the answers.
6. Run `npm run build` after editing palettes or JavaScript, so the images
   and `.min.js` files match.
7. Check the trend colors every season and replace expired ones.
8. Review field data (CrUX and Search Console) 28 days after launch.
