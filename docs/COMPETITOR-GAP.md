# Competitor gap plan: color-analysis.app

Last updated: 22 November 2026. Source data: the competitor's organic keyword
export (7,666 English keywords, about 71.7k monthly organic visits), its
sitemap (240 URLs) and an audit of this site. The full keyword map (grouped,
de-duplicated, mapped to URLs) is in
[color-analysis-competitor-gap-keyword-plan.xlsx](color-analysis-competitor-gap-keyword-plan.xlsx).

## Where they win, and what we did

| Gap | Competitor | This site now | Status |
|---|---|---|---|
| Head term "color analysis" (99.7k searches, ~35 % of their traffic) | Homepage title starts with "Color Analysis" | Site renamed **Color Analysis**. Homepage H1, title and copy target "color analysis", "personal color analysis" and "colour analysis" | ✅ |
| AI photo analysis (their main feature) | Server-side AI upload | `[sca_photo]` on `/ai-color-analysis/`: in-browser, private, face landmarks, white balance from eye whites, top 3 with confidence, skin tone + undertone, virtual draping with split view, PNG result card | ✅ |
| Skin tone and undertone (~3,700 keywords, ~486k volume) | 60+ posts | 12 guides: skin tone chart, olive, fair, pale, medium, golden, brown, best colors for dark skin, neutral undertone, vein test, undertone test, photo skin tone test | ✅ P1 |
| Season family hubs ("summer color palette" 24.4k) | Family guides | Spring, Summer, Autumn and Winter palette pages | ✅ |
| Styling | Color wheel, color pairs | Warm vs cool colors, color wheel for clothes, hair color for skin tone | ✅ P1 |
| Quiz landing page | 4 near-identical landing pages | One stronger quiz page (what the questions ask, what you get, quiz vs photo vs professional, more FAQs) | ✅ |
| Trust | Testimonials, contact | About expanded (method, sources, editorial standards); privacy policy covers photos | ✅ (contact details still needed) |

## Our advantages to keep

- One URL per intent. They have 4+ overlapping posts for some seasons
  (cannibalisation) and off-topic posts (data-viz, PowerPoint palettes).
- The photo never leaves the device; no sign-up.
- Quiz and photo tool share one scoring engine and admin-editable prototypes.
- Original palettes with hex codes, copy and PNG download on every season page.
- No invented reviews or rating schema.

## Fixes made to existing pages

- `/12-color-seasons/`: broken `Jacksonx27s` entity fixed; "most common
  season" FAQ now useful; new 16-season FAQ.
- Homepage: hero offers both the photo analysis and the quiz; links to the
  skin tone and family hubs.
- Menu: AI Color Analysis and Skin Tone added.
- SEO: WebApplication schema for the photo tool pages; llms.txt lists the
  photo tool; brand in titles, Open Graph and schema is now "Color Analysis".

## Next (P2, in order of search volume)

1. Expand the new guides to 1,200+ words with original images (skin tone
   chart graphic, drape photos) — the competitor's ranking pages are long.
2. 16-season types: Soft Winter, Soft Spring, Deep Summer, Cool Spring, Warm
   Summer (+ `/16-season-color-analysis/` hub).
3. Celebrities by season (Deep Winter, Soft Summer, True Summer, Bright
   Spring, True Spring, Deep Autumn, Light Spring) using illustrations, not
   celebrity photos.
4. Comparisons: Deep Winter vs True Winter, True vs Soft Summer, Light vs
   Soft Summer, Soft vs True Autumn.
5. "What colors go with green / navy / blue", best colors for pale skin,
   olive skin colors, cool and warm undertone color lists, Asian skin tones.
6. Gold or silver jewelry test, high vs low contrast, color analysis for men,
   professional color analysis cost, foundation shade finder.
7. Regenerate OG and palette images with the new brand (`npm run build:images`).
