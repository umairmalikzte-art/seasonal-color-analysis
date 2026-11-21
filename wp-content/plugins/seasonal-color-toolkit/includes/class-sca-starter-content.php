<?php
/**
 * Starter content: season guides, guides, a comparison and the core pages.
 * Structure follows docs/KEYWORD-RESEARCH.md (one URL per search intent).
 *
 * @package SeasonalColorToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates content that does not exist yet. Never overwrites a post that is
 * already there, so editors' changes are safe.
 */
class SCA_Starter_Content {

	/* ---------- Block helpers ---------- */

	private static function p( $html ) {
		return "<!-- wp:paragraph -->\n<p>" . $html . "</p>\n<!-- /wp:paragraph -->\n\n";
	}

	private static function h( $text, $level = 2, $anchor = '' ) {
		$attrs = 2 === $level ? array() : array( 'level' => $level );
		if ( $anchor ) {
			$attrs['anchor'] = $anchor;
		}
		$json = $attrs ? ' ' . wp_json_encode( $attrs ) : '';
		$id   = $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '';
		return "<!-- wp:heading$json -->\n<h$level class=\"wp-block-heading\"$id>" . $text . "</h$level>\n<!-- /wp:heading -->\n\n";
	}

	private static function ul( array $items, $ordered = false ) {
		$tag   = $ordered ? 'ol' : 'ul';
		$attrs = $ordered ? ' {"ordered":true}' : '';
		$out   = "<!-- wp:list$attrs -->\n<$tag class=\"wp-block-list\">";
		foreach ( $items as $item ) {
			$out .= "<!-- wp:list-item -->\n<li>" . $item . "</li>\n<!-- /wp:list-item -->";
		}
		return $out . "</$tag>\n<!-- /wp:list -->\n\n";
	}

	private static function sc( $shortcode ) {
		return "<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->\n\n";
	}

	private static function faqs( array $faqs ) {
		$out = self::h( 'Frequently asked questions' );
		foreach ( $faqs as $f ) {
			$out .= self::h( esc_html( $f[0] ), 3 ) . self::p( $f[1] );
		}
		return $out;
	}

	private static function button( $text, $url, $class = '' ) {
		return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"' . esc_attr( $class ) . '"} --><div class="wp-block-button ' . esc_attr( $class ) . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->' . "\n\n";
	}

	private static function link( $text, $path ) {
		return '<a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $text ) . '</a>';
	}

	/** Replaces [[Text|/path/]] with internal links. */
	public static function links( $html ) {
		return (string) preg_replace_callback(
			'#\[\[([^|\]]+)\|(/[^\]]*)\]\]#',
			static function ( $m ) {
				return self::link( $m[1], $m[2] );
			},
			$html
		);
	}

	private static function table( array $head, array $rows ) {
		$out = '<!-- wp:table {"className":"sca-table"} -->' . "\n" . '<figure class="wp-block-table sca-table"><table><thead><tr>';
		foreach ( $head as $cell ) {
			$out .= '<th>' . $cell . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$out .= '<tr>';
			foreach ( $row as $cell ) {
				$out .= '<td>' . $cell . '</td>';
			}
			$out .= '</tr>';
		}
		return $out . '</tbody></table></figure>' . "\n" . '<!-- /wp:table -->' . "\n\n";
	}

	/**
	 * Renders a list of content blocks (see includes/starter/guides.php).
	 *
	 * @param array $blocks Blocks.
	 * @return string
	 */
	private static function blocks( array $blocks ) {
		$out = '';
		foreach ( $blocks as $b ) {
			switch ( $b[0] ) {
				case 'p':
					$out .= self::p( self::links( $b[1] ) );
					break;
				case 'h2':
				case 'h3':
					$out .= self::h( esc_html( $b[1] ), 'h3' === $b[0] ? 3 : 2, isset( $b[3] ) ? $b[3] : '' );
					break;
				case 'ul':
				case 'ol':
					$out .= self::ul( array_map( array( __CLASS__, 'links' ), $b[1] ), 'ol' === $b[0] );
					break;
				case 'sc':
					$out .= self::sc( $b[1] );
					break;
				case 'table':
					$out .= self::table( $b[1], array_map( static function ( $row ) { return array_map( array( __CLASS__, 'links' ), $row ); }, $b[2] ) );
					break;
				case 'faq':
					$out .= self::faqs( array_map( static function ( $f ) { return array( $f[0], self::links( $f[1] ) ); }, $b[1] ) );
					break;
			}
		}
		return $out;
	}

	/* ---------- Install ---------- */

	/** Creates everything that is missing. Returns the front page ID. */
	public static function install() {
		$seasons = include SCA_DIR . 'includes/starter/seasons.php';
		$order   = 0;
		foreach ( SCA_Repository::seasons( false ) as $s ) {
			$slug = $s['season_slug'];
			if ( empty( $seasons[ $slug ] ) || SCA_Repository::season_post( $slug ) ) {
				continue;
			}
			$c  = $seasons[ $slug ];
			$id = self::insert(
				'color_season',
				$slug . '-color-palette',
				$s['season_name'] . ' Color Palette',
				self::season_content( $s, $c ),
				$c['meta'],
				$order++
			);
			if ( $id ) {
				update_post_meta( $id, '_sca_season_slug', $slug );
				update_post_meta( $id, '_sca_clothing_summary', $c['clothing_summary'] );
				update_post_meta( $id, '_sca_makeup_summary', $c['makeup_summary'] );
			}
			SCA_Repository::flush();
		}

		self::insert( 'color_guide', 'seasonal-color-analysis-guide', 'How to Do Seasonal Color Analysis at Home', self::guide_at_home(), 'A step-by-step guide to seasonal color analysis at home: undertone, depth, chroma and contrast, the drape test, and common mistakes.', 1 );
		self::insert( 'color_guide', 'warm-vs-cool-undertone', 'Warm vs Cool Undertone: How to Tell Yours', self::guide_undertone(), 'Six simple tests to tell a warm, cool, neutral or olive undertone, and what your undertone means for your color season.', 2 );
		foreach ( self::guides() as $slug => $g ) {
			self::insert( 'color_guide', $slug, $g['title'], self::blocks( $g['blocks'] ), $g['excerpt'], $g['order'] );
		}
		self::insert( 'season_comparison', 'soft-autumn-vs-soft-summer', 'Soft Autumn vs Soft Summer: How to Tell the Difference', self::compare_soft(), 'Soft Autumn vs Soft Summer: what the two muted seasons share, the one key difference, palettes side by side and tests to try at home.', 1 );

		$home = self::insert( 'page', 'home', 'Color Analysis', self::home(), 'Free color analysis: find your color season from a selfie with AI or a 15-question quiz, then explore 12 season palettes with hex codes and styling guides.', 0 );
		self::insert( 'page', 'ai-color-analysis', 'AI Color Analysis', self::ai_page(), 'Free AI color analysis from a selfie. Finds your undertone, skin tone, depth, chroma and contrast and matches you to one of 12 seasons. Your photo is never uploaded.', 1 );
		self::insert( 'page', 'seasonal-color-analysis-quiz', 'Free Seasonal Color Analysis Quiz', self::quiz_page(), 'Take a free 15-question color analysis quiz to answer "what season am I?". No photo, no sign-up. Get your likely season, the closest alternative and your palette.', 1 );
		self::insert( 'page', '12-color-seasons', 'The 12 Color Seasons', self::seasons_page(), 'The 12 color seasons explained: how each one differs in temperature, depth, chroma and contrast, with every palette and its hex codes.', 2 );
		self::insert( 'page', 'seasonal-color-palette-generator', 'Seasonal Color Palette Generator', self::generator_page(), 'Generate outfit color combinations from your season\'s palette, see how to wear the 2026 and 2027 trend colors, and check any color you own.', 3 );
		self::insert( 'page', 'compare-seasons', 'Compare Seasons', self::compare_page(), 'Compare any two of the 12 color seasons side by side: temperature, depth, chroma, contrast and both palettes.', 3 );
		self::insert( 'page', 'color-guides', 'Color Guides', self::guides_page(), 'Guides to color analysis: skin tones, undertones, the four season families, how to wear color and how to tell similar seasons apart.', 4 );
		self::insert( 'page', 'about', 'About', self::about_page(), 'Who is behind this site, how the quiz and palettes were made, and what a free online color analysis can and cannot tell you.', 5 );
		self::insert( 'page', 'privacy-policy', 'Privacy Policy', self::privacy_page(), 'How this site handles your data: quiz answers stay in your browser and are never stored or sent to analytics.', 6 );

		self::seo_titles();

		$front = $home ? $home : ( get_page_by_path( 'home' ) ? get_page_by_path( 'home' )->ID : 0 );
		if ( $front && 'page' !== get_option( 'show_on_front' ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $front );
		}
		$privacy = get_page_by_path( 'privacy-policy' );
		if ( $privacy ) {
			update_option( 'wp_page_for_privacy_policy', $privacy->ID );
		}
		return $front;
	}

	/** SEO titles (60 characters or fewer, keyword first). Only set when empty. */
	private static function seo_titles() {
		$titles = array(
			'home'                             => 'Color Analysis: Free AI Photo Test & Seasonal Quiz',
			'ai-color-analysis'                => 'AI Color Analysis: Free From a Photo, Private',
			'seasonal-color-analysis-quiz'     => 'Color Analysis Quiz: What Season Am I? (Free)',
			'12-color-seasons'                 => 'The 12 Color Seasons Explained, With Every Palette',
			'seasonal-color-palette-generator' => 'Seasonal Color Palette Generator for Outfits & Trends',
			'warm-vs-cool-undertone'           => 'Warm vs Cool Undertone: 6 Easy Tests to Tell Yours',
			'seasonal-color-analysis-guide'    => 'How to Do Seasonal Color Analysis at Home (6 Steps)',
			'soft-autumn-vs-soft-summer'       => 'Soft Autumn vs Soft Summer: How to Tell the Difference',
		);
		foreach ( self::guides() as $slug => $g ) {
			$titles[ $slug ] = $g['seo'];
		}
		foreach ( SCA_Repository::seasons( false ) as $s ) {
			$titles[ $s['season_slug'] . '-color-palette' ] = $s['season_name'] . ' Color Palette: Hex Codes, Clothes & Makeup';
		}
		foreach ( $titles as $slug => $title ) {
			$found = get_posts(
				array(
					'name'        => $slug,
					'post_type'   => array_merge( array( 'page' ), SCA_Post_Types::TYPES ),
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			if ( $found && '' === (string) get_post_meta( $found[0], '_sca_seo_title', true ) ) {
				update_post_meta( $found[0], '_sca_seo_title', $title );
			}
		}
	}

	/** @return array Guide definitions from includes/starter/guides.php. */
	private static function guides() {
		static $guides = null;
		if ( null === $guides ) {
			$guides = include SCA_DIR . 'includes/starter/guides.php';
		}
		return $guides;
	}

	/**
	 * Inserts a post unless one with the slug already exists.
	 *
	 * @return int New post ID, or 0 when it already existed.
	 */
	private static function insert( $type, $slug, $title, $content, $excerpt, $order ) {
		$existing = get_posts(
			array(
				'name'        => $slug,
				'post_type'   => $type,
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		if ( $existing ) {
			return 0;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_status'  => 'publish',
				'menu_order'   => $order,
				'post_author'  => 1,
			),
			true
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/* ---------- Season guide ---------- */

	private static function season_content( array $s, array $c ) {
		$name     = $s['season_name'];
		$slug     = $s['season_slug'];
		$neighbor = SCA_Repository::split( $s['neighbors'] );
		$first    = $neighbor ? SCA_Repository::season( $neighbor[0] ) : null;

		$out  = self::p( esc_html( $c['summary'] ) );
		$out .= self::sc( '[sca_season_profile season="' . $slug . '"]' );
		$out .= self::h( esc_html( $name ) . ' color palette with hex codes' );
		$out .= self::p( 'These are curated colors for ' . esc_html( $name ) . ', grouped into neutrals, core colors and accents. Select a swatch to copy its hex code, or download the whole palette as an image.' );
		$out .= self::sc( '[sca_palette season="' . $slug . '"]' );
		$out .= self::sc( '[sca_season_image season="' . $slug . '"]' );
		$out .= self::h( esc_html( $name ) . ' characteristics' );
		$out .= self::p( 'People with ' . esc_html( $name ) . ' coloring often have some of these traits. No single feature decides a season: the overall balance of temperature, depth, chroma and contrast does.' );
		$out .= self::ul( array_map( 'esc_html', $c['look'] ) );
		$out .= self::h( 'Best clothing colors for ' . esc_html( $name ) );
		$out .= self::p( esc_html( $c['clothing'] ) );
		$out .= self::h( 'Best neutrals' );
		$out .= self::p( esc_html( $c['neutrals'] ) );
		$out .= self::h( 'Colors to wear with care' );
		$out .= self::p( esc_html( $c['care'] ) );
		$out .= self::sc( '[sca_palette season="' . $slug . '" show="avoid" tools="no"]' );
		$out .= self::h( esc_html( $name ) . ' makeup' );
		$out .= self::ul( array_map( 'esc_html', $c['makeup'] ) );
		$out .= self::h( 'Hair color ideas' );
		$out .= self::ul( array_map( 'esc_html', $c['hair'] ) );
		$out .= self::h( 'Jewelry and metals' );
		$out .= self::p( esc_html( $c['jewelry'] ) );
		$out .= self::h( 'Outfit combinations' );
		$out .= self::ul( array_map( 'esc_html', $c['outfits'] ) );
		$out .= self::p( 'For more combinations, ' . '<a href="' . esc_url( home_url( '/seasonal-color-palette-generator/?season=' . $slug ) ) . '" rel="nofollow">' . esc_html( 'generate ' . $name . ' outfit colors' ) . '</a>.' );
		if ( $first ) {
			$out .= self::h( 'Is it ' . esc_html( $name ) . ' or ' . esc_html( $first['season_name'] ) . '?' );
			$cmp  = 'compare-seasons/?a=' . $slug . '&b=' . $first['season_slug'];
			$pair = array( $slug, $first['season_slug'] );
			sort( $pair );
			if ( array( 'soft-autumn', 'soft-summer' ) === $pair ) {
				$more = self::link( 'Read the full Soft Autumn vs Soft Summer comparison', '/soft-autumn-vs-soft-summer/' );
			} else {
				$more = '<a href="' . esc_url( home_url( '/' . $cmp ) ) . '" rel="nofollow">' . esc_html( 'Compare ' . $name . ' and ' . $first['season_name'] . ' side by side' ) . '</a>';
			}
			$out .= self::p( esc_html( $c['versus'] ) . ' ' . $more . '.' );
		}
		$out .= self::faqs(
			array_map(
				static function ( $f ) {
					return array( $f[0], esc_html( $f[1] ) );
				},
				$c['faqs']
			)
		);
		$out .= self::h( 'Related seasons' );
		$out .= self::sc( '[sca_related_seasons season="' . $slug . '"]' );
		$out .= self::p( 'Not sure ' . esc_html( $name ) . ' is your season? ' . self::link( 'Take the free seasonal color analysis quiz', '/seasonal-color-analysis-quiz/' ) . ' or ' . self::link( 'see all 12 color seasons', '/12-color-seasons/' ) . '.' );
		return $out;
	}

	/* ---------- Pages ---------- */

	private static function home() {
		$quiz = home_url( '/seasonal-color-analysis-quiz/' );
		$ai   = home_url( '/ai-color-analysis/' );
		$out  = '<!-- wp:group {"className":"sca-hero","layout":{"type":"constrained"}} --><div class="wp-block-group sca-hero"><!-- wp:columns {"className":"sca-hero__grid"} --><div class="wp-block-columns sca-hero__grid"><!-- wp:column {"className":"sca-hero__text"} --><div class="wp-block-column sca-hero__text">';
		$out .= "<!-- wp:heading {\"level\":1} -->\n<h1 class=\"wp-block-heading\">Free Color Analysis: Find the Colors That Suit You</h1>\n<!-- /wp:heading -->\n\n";
		$out .= "<!-- wp:paragraph {\"className\":\"sca-hero__lead\"} -->\n<p class=\"sca-hero__lead\">Get your personal color analysis in minutes. Upload a selfie for an AI color analysis, or answer 15 quick questions. Both are free, private and need no sign-up.</p>\n<!-- /wp:paragraph -->\n\n";
		$out .= '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-fill"} --><div class="wp-block-button is-style-fill"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $ai ) . '">Analyze My Photo</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $quiz ) . '">Take the Quiz</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
		$out .= '</div><!-- /wp:column --><!-- wp:column {"className":"sca-hero__tool"} --><div class="wp-block-column sca-hero__tool">' . self::sc( '[sca_quiz_start]' ) . '</div><!-- /wp:column --></div><!-- /wp:columns -->';
		$out .= '</div><!-- /wp:group -->' . "\n\n";

		$out .= self::p( '<strong>Color analysis</strong> (also spelled <strong>colour analysis</strong>) is a way of finding the clothing, makeup and hair colors that flatter you by looking at four qualities of your natural coloring: undertone, depth, chroma and contrast. <strong>Seasonal color analysis</strong> sorts the results into four color families, Spring, Summer, Autumn and Winter, and then into 12 seasons, each with its own palette.' );

		$out .= self::h( 'Two free ways to get your color analysis' );
		$out .= '<!-- wp:columns {"className":"sca-dimensions"} --><div class="wp-block-columns sca-dimensions">';
		$out .= '<!-- wp:column --><div class="wp-block-column">' . self::h( 'AI color analysis from a photo', 3 ) . self::p( 'Upload a selfie or use your camera. The AI finds your face, corrects the light color using the whites of your eyes, and measures your skin, hair and eyes. You get your season, your top three matches with a confidence score, your skin tone and undertone, and a virtual drape to test colors under your face. ' . self::link( 'Analyze my photo', '/ai-color-analysis/' ) . '.' ) . '</div><!-- /wp:column -->';
		$out .= '<!-- wp:column --><div class="wp-block-column">' . self::h( 'Color analysis quiz', 3 ) . self::p( 'Answer 15 questions about your natural hair, eyes, skin and how colors look on you. It takes about three minutes and works well when you cannot take a daylight photo. ' . self::link( 'Take the color analysis quiz', '/seasonal-color-analysis-quiz/' ) . '.' ) . '</div><!-- /wp:column -->';
		$out .= '</div><!-- /wp:columns -->' . "\n\n";
		$out .= self::p( 'Your photo and your answers never leave your device. For the most reliable result, do both: when they agree, you can be confident.' );

		$out .= self::h( 'The four color families and 12 seasons' );
		$out .= self::p( 'Spring and Autumn lean warm. Summer and Winter lean cool. Inside each family, three seasons differ in how light, deep, soft or bright their colors are. ' . self::link( 'Read how the 12 color seasons work', '/12-color-seasons/' ) . ', or open a family: ' . self::link( 'Spring', '/spring-color-palette/' ) . ', ' . self::link( 'Summer', '/summer-color-palette/' ) . ', ' . self::link( 'Autumn', '/autumn-color-palette/' ) . ' and ' . self::link( 'Winter', '/winter-color-palette/' ) . '.' );
		$out .= self::sc( '[sca_season_families]' );

		$out .= self::h( 'Popular color palettes' );
		$out .= self::sc( '[sca_popular_palettes]' );
		$out .= self::p( 'Turn any palette into outfit combinations, or see how to wear this year\'s trend colors, with the ' . self::link( 'seasonal color palette generator', '/seasonal-color-palette-generator/' ) . '.' );

		$out .= self::h( 'How color analysis works' );
		$out .= self::p( 'Every season is a combination of four qualities. The AI measures them from your photo; the quiz estimates them from your answers. Both then find the season whose profile is closest.' );
		$out .= '<!-- wp:columns {"className":"sca-dimensions"} --><div class="wp-block-columns sca-dimensions">';
		foreach ( array(
			array( 'Undertone', 'The warm (golden, peach) or cool (pink, blue) cast under the surface of your skin. Undertone is not the same as how light or dark your skin is. ' . self::link( 'Test your undertone', '/undertone-test/' ) . '.' ),
			array( 'Depth', 'How light or deep your overall coloring is: your hair, eyes and skin together. It decides whether pastels or deep shades look more natural.' ),
			array( 'Chroma', 'How clear or soft your coloring looks. Clear coloring suits saturated colors; soft coloring suits muted, slightly grayed colors.' ),
			array( 'Contrast', 'The difference in lightness between your hair, skin and eyes. High contrast can carry bold color pairs; low contrast looks best with blended ones.' ),
		) as $col ) {
			$out .= '<!-- wp:column --><div class="wp-block-column">' . self::h( $col[0], 3 ) . self::p( $col[1] ) . '</div><!-- /wp:column -->';
		}
		$out .= '</div><!-- /wp:columns -->' . "\n\n";

		$out .= self::h( 'Skin tone and undertone guides' );
		$out .= self::p( 'Your skin tone is the starting point of any personal color analysis. Find yours on the ' . self::link( 'skin tone chart', '/skin-tone-chart/' ) . ', or read the guide for your skin: ' . self::link( 'fair', '/fair-skin/' ) . ', ' . self::link( 'pale', '/pale-skin/' ) . ', ' . self::link( 'medium', '/medium-skin-tone/' ) . ', ' . self::link( 'olive', '/olive-skin-tone/' ) . ', ' . self::link( 'golden', '/yellow-golden-skin-tone/' ) . ' and ' . self::link( 'brown', '/brown-skin-tone/' ) . '. Not sure if you are warm, cool or neutral? Try the ' . self::link( 'vein test', '/vein-test-undertone/' ) . ' or the ' . self::link( 'photo skin tone test', '/skin-tone-test/' ) . '.' );

		$out .= self::h( 'Commonly confused seasons' );
		$out .= self::p( 'Neighbouring seasons share two or three qualities, so they are easy to mix up. Compare them side by side before you decide.' );
		$out .= self::ul(
			array(
				self::link( 'Soft Autumn vs Soft Summer', '/soft-autumn-vs-soft-summer/' ) . ': both muted, one warm and one cool.',
				'<a href="' . esc_url( home_url( '/compare-seasons/?a=light-spring&b=light-summer' ) ) . '" rel="nofollow">Light Spring vs Light Summer</a>: both light and delicate.',
				'<a href="' . esc_url( home_url( '/compare-seasons/?a=bright-spring&b=bright-winter' ) ) . '" rel="nofollow">Bright Spring vs Bright Winter</a>: both clear and high in contrast.',
				'<a href="' . esc_url( home_url( '/compare-seasons/?a=deep-autumn&b=deep-winter' ) ) . '" rel="nofollow">Deep Autumn vs Deep Winter</a>: both deep and rich.',
				self::link( 'Compare any two seasons', '/compare-seasons/' ) . '.',
			)
		);

		$out .= self::h( 'Color guides' );
		$out .= self::sc( '[sca_content_list type="color_guide"]' );

		$out .= self::faqs(
			array(
				array( 'What is color analysis?', 'Color analysis, or personal color analysis, is a way of choosing clothing, makeup and hair colors by matching them to your natural coloring. The 12-season system describes each person by undertone, depth, chroma and contrast, and groups the results into Spring, Summer, Autumn and Winter.' ),
				array( 'How do I find my color season?', 'Upload a selfie to our ' . self::link( 'free AI color analysis', '/ai-color-analysis/' ) . ', take ' . self::link( 'the 15-question quiz', '/seasonal-color-analysis-quiz/' ) . ', or compare fabrics near your face in daylight with ' . self::link( 'our at-home guide', '/seasonal-color-analysis-guide/' ) . '.' ),
				array( 'Is online color analysis accurate?', 'It gives a likely season, not a certainty. A photo analysis depends on light and camera; a quiz depends on how well you judge your own coloring. Use the result as a starting point and test the palette in daylight.' ),
				array( 'Is the AI color analysis free?', 'Yes. There is no sign-up and no paid upgrade, and your photo is analysed in your browser, never uploaded.' ),
				array( 'What are the 12 color seasons?', 'Light, True and Bright Spring; Light, True and Soft Summer; Soft, True and Deep Autumn; and Deep, True and Bright Winter. ' . self::link( 'See all 12 seasons compared', '/12-color-seasons/' ) . '.' ),
				array( 'Can my season change over time?', 'Your undertone stays the same, but hair color, gray hair and a tan can change your depth and contrast. Many people find they move to a neighbouring season, for example from True to Soft.' ),
				array( 'How much does a professional color analysis cost?', 'In-person draping sessions usually cost from about $100 to over $400 in the US and UK, depending on the analyst and city. A free online analysis is a good first step before you decide whether to book one.' ),
			)
		);

		$out .= '<!-- wp:group {"className":"sca-cta","layout":{"type":"constrained"}} --><div class="wp-block-group sca-cta">';
		$out .= self::h( 'Get your free color analysis' );
		$out .= self::p( 'A selfie or 15 questions. No sign-up, nothing stored.' );
		$out .= '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-fill"} --><div class="wp-block-button is-style-fill"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $ai ) . '">Analyze My Photo</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $quiz ) . '">Take the Quiz</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
		$out .= '</div><!-- /wp:group -->';
		return $out;
	}

	private static function ai_page() {
		$out  = self::p( '<strong>Upload a selfie and get your color season in under a minute.</strong> This free AI color analysis reads your skin, hair and eye color, corrects for your lighting, and matches you to one of the 12 color seasons. You get a confidence score, your two closest alternatives, your skin tone and undertone, and a virtual drape to see colors under your face. The analysis runs in your browser, so your photo is never uploaded.' );
		$out .= self::sc( '[sca_photo]' );
		$out .= self::h( 'How the AI color analysis works' );
		$out .= self::ul(
			array(
				'<strong>Finds your face.</strong> A face-landmark model maps 478 points, so the tool samples the right places: both cheeks, forehead and chin for skin, the hairline and temples for hair, and the iris for eye color.',
				'<strong>Removes shadow and shine.</strong> Each sample drops its darkest and brightest pixels, so a shadow under your cheekbone or a shiny forehead does not change the result.',
				'<strong>Corrects your lighting.</strong> Indoor bulbs make everyone look warmer and phone cameras shift color. The whites of your eyes are used as a neutral reference to remove the color cast before anything is measured.',
				'<strong>Measures four qualities.</strong> Undertone (warm or cool), depth (light or deep), chroma (soft or clear) and contrast (the difference in lightness between your hair, skin and eyes), the same qualities a professional analyst judges.',
				'<strong>Matches you to a season.</strong> Your profile is compared with all 12 seasons using the same season profiles as ' . self::link( 'our quiz', '/seasonal-color-analysis-quiz/' ) . '. You see your best match, a match percentage for the top three, and a confidence score that drops when the photo has poor light.',
				'<strong>Lets you check it.</strong> Hold any color from your palette under your face, or use split view to compare a best color with one to wear with care.',
			),
			true
		);
		$out .= self::h( 'How to take a photo for accurate color analysis' );
		$out .= self::ul(
			array(
				'<strong>Use daylight.</strong> Face a window during the day. Avoid direct sun, yellow lamps and flash.',
				'<strong>Turn off filters.</strong> Beauty mode, portrait lighting and warm filters change your skin tone.',
				'<strong>Go bare-faced if you can.</strong> Foundation and bronzer hide your real undertone.',
				'<strong>Show your hair and eyes.</strong> Pull hair back so the hairline is visible, and keep both eyes open.',
				'<strong>Wear white or gray.</strong> A strong color near your face reflects onto your skin.',
			)
		);
		$out .= self::h( 'What makes this AI color analysis different' );
		$out .= self::table(
			array( 'Feature', 'This tool', 'Typical color analysis apps' ),
			array(
				array( 'Privacy', 'Photo stays on your device', 'Photo uploaded to a server' ),
				array( 'Price', 'Free, no sign-up', 'Often paid, or an email is required' ),
				array( 'Lighting', 'Corrected automatically from your eye whites', 'Rarely explained' ),
				array( 'Result', 'Season, top 3 with match %, confidence, skin tone, undertone and four qualities', 'A season name' ),
				array( 'Checking the result', 'Virtual draping with split view', 'No way to test' ),
				array( 'Palette', '12 best colors and colors to wear with care, hex codes, downloadable card', 'Varies' ),
			)
		);
		$out .= self::h( 'Photo analysis or quiz: which is more accurate?' );
		$out .= self::p( 'Both give an estimate. A photo is better at depth and contrast, because it measures your real colors. A quiz is better at undertone when your light is poor, because you can answer from experience, such as whether you burn or tan. Do both: run this photo analysis, then ' . self::link( 'take the free quiz', '/seasonal-color-analysis-quiz/' ) . '. If they agree, you can be confident. If they disagree, ' . self::link( 'compare the two seasons', '/compare-seasons/' ) . ' and test both palettes in daylight.' );
		$out .= self::h( 'What your result means' );
		$out .= self::p( 'Your season is a shortcut to the colors that harmonize with your natural coloring. Spring and Autumn lean warm; Summer and Winter lean cool. Within each family the three seasons differ by their strongest quality: light, deep, soft, bright or true. Read ' . self::link( 'how the 12 color seasons work', '/12-color-seasons/' ) . ', then open your season page for the full palette, makeup, hair colors and outfits. Your skin tone and undertone link to the ' . self::link( 'skin tone chart', '/skin-tone-chart/' ) . ' and ' . self::link( 'undertone guides', '/warm-vs-cool-undertone/' ) . '.' );
		$out .= self::faqs(
			array(
				array( 'Is this AI color analysis free?', 'Yes. There is no sign-up, no email and no paid upgrade.' ),
				array( 'Is my photo uploaded or stored?', 'No. The face model downloads to your browser and the analysis happens on your device. Your photo is never sent to our server and is gone when you close the page.' ),
				array( 'How accurate is AI color analysis from a photo?', 'It is a strong starting point, not a final answer. Camera sensors, light and makeup all change how color looks. The tool corrects for lighting and shows a confidence score so you know how much to trust the result.' ),
				array( 'Does it work for every skin tone?', 'Yes. Undertone is measured separately from depth, so deep skin can be warm, cool or neutral, just like fair skin.' ),
				array( 'Can I use a photo with makeup or dyed hair?', 'You can, but the result is less reliable. Choose "dyed or highlighted" in the questions so your hair counts for less, and use a bare-faced photo if you can.' ),
				array( 'Why did I get a different season from another app?', 'Neighbouring seasons share two or three qualities, so small differences in light or camera can tip you from one to the other. Look at your top three: the right season is usually among them.' ),
				array( 'Is there a color analysis app I need to download?', 'No. This works in any modern phone or desktop browser. Add the page to your home screen if you want it like an app.' ),
			)
		);
		return $out;
	}

	private static function quiz_page() {
		$out  = self::p( 'Answer 15 short questions about your natural coloring and how colors look on you to answer the question <strong>"what color season am I?"</strong>. You will get your likely season, the closest alternative and your palette with hex codes. No photo, no sign-up, and your answers stay on your device. Prefer a photo? Try the ' . self::link( 'AI color analysis', '/ai-color-analysis/' ) . '.' );
		$out .= self::sc( '[sca_quiz]' );
		$out .= self::h( 'How the quiz works' );
		$out .= self::p( 'Each answer moves your profile along four scales: temperature (cool to warm), depth (light to deep), chroma (soft to bright) and contrast (low to high). The quiz averages your answers on each scale, then finds the season whose typical profile is closest to yours. The second-closest season is shown as your alternative.' );
		$out .= self::p( 'If two seasons are almost equally close, the result says so and suggests comparing both. If you answer "Not sure" to many questions, the result is marked as tentative.' );
		$out .= self::h( 'What the questions ask' );
		$out .= self::ul(
			array(
				'<strong>Hair:</strong> your natural hair color today and as a child. Hair is the biggest clue to depth and contrast.',
				'<strong>Eyes:</strong> the color and clarity of your eyes, which help tell clear seasons from soft ones.',
				'<strong>Skin:</strong> how your skin reacts to the sun and how it looks next to white, gold and silver.',
				'<strong>Colors on you:</strong> which colors people compliment you in, and which make you look tired.',
			)
		);
		$out .= self::h( 'What you get' );
		$out .= self::ul(
			array(
				'Your likely season out of 12, and its family (Spring, Summer, Autumn or Winter).',
				'Your closest alternative season and how strong the match is.',
				'Your four color qualities, shown as simple scales.',
				'Your palette with copyable hex codes and a PNG download, plus colors to wear with care.',
				'Clothing and makeup tips and a link to your full season guide.',
			)
		);
		$out .= self::h( 'How accurate is a color analysis quiz?' );
		$out .= self::p( 'A quiz is a guide, not a professional color analysis. It relies on how you see your own coloring, and lighting, makeup, tanning and hair dye all change what you see. For the best answers, look in daylight near a window, without makeup. Then test colors from your result next to your face. ' . self::link( 'Our at-home guide', '/seasonal-color-analysis-guide/' ) . ' explains how, and the ' . self::link( 'AI photo analysis', '/ai-color-analysis/' ) . ' gives you a second opinion.' );
		$out .= self::h( 'Quiz, photo or professional?' );
		$out .= self::table(
			array( 'Method', 'Best for', 'Cost' ),
			array(
				array( 'This quiz', 'A quick estimate without a photo', 'Free' ),
				array( self::link( 'AI color analysis', '/ai-color-analysis/' ), 'Measuring depth and contrast from your real colors', 'Free' ),
				array( self::link( 'At-home drape test', '/seasonal-color-analysis-guide/' ), 'Confirming between two seasons', 'Free' ),
				array( 'Professional draping', 'A precise answer when you sit between seasons', 'About $100–$400+' ),
			)
		);
		$out .= self::faqs(
			array(
				array( 'How long does the quiz take?', 'About three minutes for 15 questions.' ),
				array( 'Is the quiz free?', 'Yes. There is no sign-up, no email and no paid upgrade.' ),
				array( 'Do you store my answers?', 'No. The quiz runs in your browser. Your answers are not sent to our server or to analytics tools. A shared link contains only the season name.' ),
				array( 'What if I get two seasons?', 'Many people sit between two neighbouring seasons. Try colors from both palettes and keep the ones that make your skin look clearer.' ),
				array( 'What season am I if I look good in orange?', 'Orange usually suits warm seasons: True Spring and Bright Spring in clear orange, True Autumn and Deep Autumn in burnt orange and rust.' ),
				array( 'Is there a color analysis test with a photo?', 'Yes. Our ' . self::link( 'AI color analysis', '/ai-color-analysis/' ) . ' works from a selfie and never uploads it.' ),
			)
		);
		return $out;
	}

	private static function seasons_page() {
		$out  = self::p( 'The <strong>12 color seasons</strong> are an extended version of the classic four-season color analysis. Each family, Spring, Summer, Autumn and Winter, is split into three seasons by its strongest quality: light, deep, soft, bright or true (the warmest or coolest). The table shows how all 12 compare.' );
		$out .= self::sc( '[sca_season_table]' );
		$out .= self::h( 'The four families' );
		$out .= self::sc( '[sca_season_families]' );
		$out .= self::h( 'All 12 color palettes', 2, 'palettes' );
		$out .= self::p( 'Choose a season to see its palette. Select a swatch to copy its hex code, copy the whole palette, or download it as an image.' );
		$out .= self::sc( '[sca_palette_explorer]' );
		$out .= self::p( 'Want ready-made combinations? ' . self::link( 'Try the seasonal color palette generator', '/seasonal-color-palette-generator/' ) . '.' );
		$out .= self::h( 'A short history of seasonal color analysis' );
		$out .= self::p( 'The idea that some colors flatter a person more than others is old, but the seasonal system has a few clear milestones. The Bauhaus teacher <strong>Johannes Itten</strong> observed that his students tended to prefer colors that echoed their own coloring, and he linked color harmonies to the four seasons in his teaching (his book <em>The Art of Color</em> was published in 1961). In the United States, <strong>Suzanne Caygill</strong> built seasonal palettes for clients in the mid-20th century. <strong>Carole Jackson</strong>\'s best-seller <em>Color Me Beautiful</em> (1980) made the four-season system popular worldwide.' );
		$out .= self::p( 'From the 1990s, analysts split each season into three to give people between two families a closer palette. That is the 12-season system used on this site. Its three main dimensions match the three dimensions of the <strong>Munsell color system</strong>: hue (warm or cool), value (light or deep) and chroma (bright or soft). Contrast describes how those dimensions differ between your hair, skin and eyes.' );
		$out .= self::h( 'How the seasons connect' );
		$out .= self::p( 'Each season borders others that share its strongest quality. Soft Autumn and Soft Summer are both muted; Bright Spring and Bright Winter are both clear; Deep Autumn and Deep Winter are both deep; Light Spring and Light Summer are both light. True Spring borders True Autumn through warmth, and True Summer borders True Winter through coolness. This is why the quiz always shows a closest alternative.' );
		$out .= self::p( 'Some sites use other names for the same seasons. Deep is also called Dark, True Summer and True Winter are also called Cool, True Spring and True Autumn are also called Warm, and Bright is also called Clear.' );
		$out .= self::faqs(
			array(
				array( 'What is the difference between the 4-season and 12-season systems?', 'The 4-season system only separates warm from cool and light from deep. The 12-season system adds chroma (soft or bright), so people who are between two families get a closer palette.' ),
				array( 'Which season is the most common?', 'No large, reliable study counts how many people belong to each season, so we do not give percentages. In practice, color analysts most often report the soft and neutral-leaning seasons, such as Soft Summer and Soft Autumn, because most people have moderate contrast. Bright and True seasons are less common.' ),
					array( 'Is there a 16-season system?', 'Yes. Some analysts add four more types, such as Soft Winter, Soft Spring, Deep Summer and Warm Summer. They sit between the 12 seasons used here: Soft Winter is close to Soft Summer and Deep Winter, and Deep Summer is close to True Summer and Soft Summer.' ),
				array( 'Are Deep Winter and Dark Winter the same?', 'Yes. Deep and Dark are two names for the same seasons.' ),
			)
		);
		return $out;
	}

	private static function generator_page() {
		$out  = self::p( 'Build <strong>outfit color combinations from your season\'s palette</strong>, and see how to wear this year\'s trend colors in your season.' );
		$out .= self::sc( '[sca_generator]' );
		$out .= self::h( 'How the generator works' );
		$out .= self::p( 'Choose your season and a color harmony, and the generator builds a five-color outfit palette from your season\'s curated colors. Start from a current trend color to see your season\'s version of it, or switch to "Check my colors" to test clothes you already own. Every suggested color comes from your palette, never from random generation.' );
		$out .= self::p( 'The generator picks a main color from your season and then chooses supporting colors by their position on the color wheel, using a perceptual color space (OKLab) so that the steps look even to the eye.' );
		$out .= self::ul(
			array(
				'<strong>Tonal:</strong> shades of one color family, light to deep. Calm and elegant, and easy for low-contrast seasons.',
				'<strong>Analogous:</strong> neighbours on the color wheel, such as teal, jade and green. Harmonious and low risk.',
				'<strong>Complementary:</strong> a color with its opposite, such as rust with teal. Lively; keep the opposite color small.',
				'<strong>Triadic:</strong> three colors evenly spaced around the wheel. Playful; best for clear and bright seasons.',
				'<strong>Neutral + pop:</strong> three neutrals with one saturated color and an accent. The easiest everyday formula.',
			)
		);
		$out .= self::p( 'Lock any color you like and generate again to replace only the others. Copy the hex codes or download the palette as an image to take shopping.' );
		$out .= self::h( 'How to wear this year\'s trend colors in your season' );
		$out .= self::p( 'Trend colors are chosen for everyone, so they will not suit every season equally. The table shows which seasons have a very similar color in their palette. If a trend is far from yours, wear your season\'s closest version, or keep the trend color away from your face, in shoes, bags or trousers.' );
		$out .= self::sc( '[sca_trend_table]' );
		$out .= self::faqs(
			array(
				array( 'Is this a random color palette generator?', 'No. Unlike general palette generators for designers, every color comes from your season\'s curated palette. The harmony rule only decides which of those colors go together.' ),
				array( 'Can I wear a trend color that is not in my season?', 'Yes. Wear your season\'s closest version near your face, or wear the trend color further from your face. The generator shows your version for each trend.' ),
				array( 'Where do the trend colors come from?', 'From published color forecasts such as the Pantone Color of the Year and the WGSN and Coloro Colour of the Year and Key Colours. Each trend links to its source. The hex values here are on-screen approximations.' ),
				array( 'How do I check clothes I already own?', 'Use the "Check my colors" tab. Pick up to three colors with the color picker or type a hex code, and see the closest color in your palette.' ),
			)
		);
		return $out;
	}

	private static function compare_page() {
		$out  = self::p( 'Choose any two of the 12 seasons to see them side by side: their temperature, depth, chroma and contrast, what they share, how they differ, and both palettes.' );
		$out .= self::sc( '[sca_compare]' );
		$out .= self::h( 'Written comparisons' );
		$out .= self::sc( '[sca_content_list type="season_comparison"]' );
		return $out;
	}

	private static function guides_page() {
		$out  = self::p( 'Practical guides for finding and using your colors, grouped by topic.' );
		$out .= self::h( 'Find your colors' );
		$out .= self::ul(
			array(
				self::link( 'AI color analysis from a photo', '/ai-color-analysis/' ) . ': upload a selfie, get your season.',
				self::link( 'Color analysis quiz', '/seasonal-color-analysis-quiz/' ) . ': 15 questions, about three minutes.',
				self::link( 'How to do seasonal color analysis at home', '/seasonal-color-analysis-guide/' ) . ': the drape test step by step.',
			)
		);
		$out .= self::h( 'Skin tone' );
		$out .= self::ul(
			array(
				self::link( 'Skin tone chart', '/skin-tone-chart/' ),
				self::link( 'Skin tone test from a photo', '/skin-tone-test/' ),
				self::link( 'Fair skin', '/fair-skin/' ) . ' and ' . self::link( 'pale skin', '/pale-skin/' ),
				self::link( 'Medium and beige skin', '/medium-skin-tone/' ),
				self::link( 'Olive skin', '/olive-skin-tone/' ),
				self::link( 'Yellow and golden skin', '/yellow-golden-skin-tone/' ),
				self::link( 'Brown skin', '/brown-skin-tone/' ) . ' and ' . self::link( 'the best colors for dark skin', '/best-colors-for-dark-skin/' ),
			)
		);
		$out .= self::h( 'Undertone' );
		$out .= self::ul(
			array(
				self::link( 'Warm vs cool undertone', '/warm-vs-cool-undertone/' ),
				self::link( 'Undertone test (7 ways)', '/undertone-test/' ),
				self::link( 'Vein test', '/vein-test-undertone/' ),
				self::link( 'Neutral undertone', '/neutral-undertone/' ),
			)
		);
		$out .= self::h( 'Season families' );
		$out .= self::ul(
			array(
				self::link( 'Spring color palette', '/spring-color-palette/' ),
				self::link( 'Summer color palette', '/summer-color-palette/' ),
				self::link( 'Autumn color palette', '/autumn-color-palette/' ),
				self::link( 'Winter color palette', '/winter-color-palette/' ),
				self::link( 'All 12 color seasons', '/12-color-seasons/' ),
			)
		);
		$out .= self::h( 'Wearing color' );
		$out .= self::ul(
			array(
				self::link( 'Warm vs cool colors', '/warm-vs-cool-colors/' ),
				self::link( 'Color wheel for clothes', '/color-wheel-for-clothes/' ),
				self::link( 'Best hair color for your skin tone', '/best-hair-color-for-skin-tone/' ),
				self::link( 'Seasonal color palette generator', '/seasonal-color-palette-generator/' ),
			)
		);
		$out .= self::h( 'Comparisons' );
		$out .= self::sc( '[sca_content_list type="season_comparison"]' );
		$out .= self::p( 'Ready to find your season? ' . self::link( 'Analyze your photo', '/ai-color-analysis/' ) . ' or ' . self::link( 'take the free quiz', '/seasonal-color-analysis-quiz/' ) . '.' );
		return $out;
	}

	private static function about_page() {
		$out  = self::p( '<strong>Color Analysis</strong> helps you find the colors that suit your natural coloring, with a free AI photo analysis, a quiz, 12 season palettes and plain-language guides. Everything here is free, the tools work without an account, and your photo and answers never leave your device.' );
		$out .= self::h( 'How we made the palettes' );
		$out .= self::p( 'Each palette was put together by hand for this site to match its season\'s temperature, depth, chroma and contrast. The colors are not generated at random and are not copied from other sites. Palettes are a guide: there is no single official palette for any season, and different color analysts draw the lines a little differently.' );
		$out .= self::h( 'How the quiz works' );
		$out .= self::p( 'The quiz turns your answers into four scores and finds the closest of 12 season profiles. It shows the closest alternative and says when a result is uncertain. It does not claim the accuracy of an in-person draping session with a trained analyst. ' . self::link( 'Read how the quiz works', '/seasonal-color-analysis-quiz/' ) . '.' );
		$out .= self::h( 'How the AI color analysis works' );
		$out .= self::p( 'The photo tool runs a face-landmark model in your browser, samples your skin, hair and eyes, corrects the color of your light using the whites of your eyes, and converts the colors to the CIELAB color space. It measures the same four qualities as the quiz and ranks seasons with the same profiles, so the two tools agree with each other. The method is tested against reference colors for every season. ' . self::link( 'Try the AI color analysis', '/ai-color-analysis/' ) . '.' );
		$out .= self::h( 'Our method and sources' );
		$out .= self::p( 'The 12-season system follows the tradition of Johannes Itten, Suzanne Caygill and Carole Jackson\'s <em>Color Me Beautiful</em>, extended into 12 seasons by later analysts. Its dimensions match the Munsell color system: hue, value and chroma. Skin tone depth levels follow the widely used Fitzpatrick-style scale. ' . self::link( 'Read a short history of seasonal color analysis', '/12-color-seasons/' ) . '.' );
		$out .= self::h( 'Editorial standards' );
		$out .= self::ul(
			array(
				'We write every page ourselves and update it when we find better information.',
				'We do not publish invented statistics, reviews, testimonials or ratings.',
				'Images and palette graphics are our own or properly licensed. We do not use celebrity photos.',
				'We are not paid to recommend products on this site.',
				'Each guide shows when it was last updated.',
			)
		);
		$out .= self::h( 'Privacy' );
		$out .= self::p( 'Your quiz answers and photos are processed in your browser and are never sent to our server. ' . self::link( 'Read the privacy policy', '/privacy-policy/' ) . '.' );
		$out .= self::h( 'Contact' );
		$out .= self::p( 'Contact details will be added before launch.' );
		return $out;
	}

	private static function privacy_page() {
		$out  = self::p( 'This policy explains what data this site collects and why. It will be reviewed before launch.' );
		$out .= self::h( 'Quiz answers' );
		$out .= self::p( 'The color analysis quiz runs entirely in your browser. Your answers are not sent to our server, are not stored in a database, and are not passed to analytics or advertising tools. If you share a result, the link contains only the name of the season.' );
		$out .= self::h( 'Photos in the AI color analysis' );
		$out .= self::p( 'The AI color analysis and skin tone test analyse your photo inside your browser. The photo is not uploaded to our server, not stored and not shared. To detect your face, your browser downloads a face-landmark model and its code from public content delivery networks (jsDelivr and Google Cloud Storage). Only those files are downloaded; your photo is never sent. Camera access is used only while you take a photo, and only after you allow it.' );
		$out .= self::h( 'No accounts or emails' );
		$out .= self::p( 'You do not need an account or an email address to use the site.' );
		$out .= self::h( 'Analytics' );
		$out .= self::p( 'If we use analytics (such as Google Analytics), it measures page visits and whether a quiz or photo analysis was started or completed, together with the resulting season name. It never receives your individual answers or your photo. This section will list the tools in use before launch.' );
		$out .= self::h( 'Server logs and cookies' );
		$out .= self::p( 'Like most websites, our hosting provider keeps standard server logs (such as IP address and browser type) for security. The site itself does not set cookies for visitors who are not logged in.' );
		$out .= self::h( 'Contact' );
		$out .= self::p( 'Contact details will be added before launch.' );
		return $out;
	}

	/* ---------- Guides ---------- */

	private static function guide_at_home() {
		$out  = self::p( 'You can do a basic seasonal color analysis at home in about 30 minutes. Work in daylight, without makeup, and judge four things in order: <strong>undertone, depth, chroma and contrast</strong>. Then test fabrics or clothes near your face to confirm. If you want a quick estimate first, ' . self::link( 'take the free quiz', '/seasonal-color-analysis-quiz/' ) . '. For what seasonal color analysis is, see ' . self::link( 'our overview', '/' ) . '.' );
		$out .= self::h( 'What you need' );
		$out .= self::ul( array( 'A mirror next to a window with indirect daylight (not direct sun, not lamps).', 'A clean face, without makeup or self-tan.', 'A white towel or headband to cover dyed hair.', 'Fabrics or clothes in a few test colors: pure white and ivory, black and brown, a bright color and a muted one.', 'Your phone camera, for a black-and-white photo.' ) );
		$out .= self::h( 'Step 1: Find your undertone' );
		$out .= self::p( 'Undertone is the warm or cool cast under the surface of your skin. Compare gold and silver jewelry, pure white and ivory fabric, and warm coral and cool pink near your face. Look at your skin, not the item: the right one makes your skin look clearer and more even, the wrong one makes it look gray, yellow or red. ' . self::link( 'Our undertone guide', '/warm-vs-cool-undertone/' ) . ' explains six tests in detail.' );
		$out .= self::h( 'Step 2: Judge your depth' );
		$out .= self::p( 'Depth is how light or deep your coloring is overall. Consider your natural hair, eyes and skin together. Then compare a pastel and a deep shade of the same color, such as powder blue and navy. Light seasons look best in the pastel; deep seasons in the dark shade.' );
		$out .= self::h( 'Step 3: Judge your chroma' );
		$out .= self::p( 'Chroma is how clear or soft your coloring is. Hold a bright, saturated color (such as fuchsia or emerald) and a muted, dusty one (such as dusty rose or sage) near your face. If the bright one makes your eyes sparkle, you are clear; if it seems to stand out in front of you while the muted one looks natural, you are soft.' );
		$out .= self::h( 'Step 4: Check your contrast' );
		$out .= self::p( 'Take a black-and-white photo of your face with your natural hair visible. A big difference between the lightest and darkest parts means high contrast; similar grays mean low contrast. High contrast often points to Winter or Bright Spring; low contrast to Soft and Light seasons.' );
		$out .= self::h( 'Step 5: Do a drape test' );
		$out .= self::p( 'Hold each test fabric under your chin, one at a time. The flattering ones even out your skin, soften shadows and make your eyes look brighter. The unflattering ones add shadows, redness or a gray cast. Compare in pairs and keep the winner each time.' );
		$out .= self::h( 'Step 6: Put it together' );
		$out .= self::p( 'Choose the family from your undertone and chroma: warm and clear is Spring, cool and soft is Summer, warm and soft is Autumn, cool and clear is Winter. Then choose the season from your strongest quality. For example, a cool, soft and medium person is likely Soft Summer or True Summer. ' . self::link( 'See all 12 seasons compared', '/12-color-seasons/' ) . '.' );
		$out .= self::h( 'Common mistakes' );
		$out .= self::ul( array( 'Judging under warm indoor light, which makes everyone look warmer.', 'Choosing your favorite colors instead of the ones that flatter you.', 'Using dyed hair instead of your natural color.', 'Deciding from one feature, such as blue eyes or red hair. Seasons come from the overall balance.', 'Confusing depth with undertone: deep skin can be cool, and fair skin can be warm.' ) );
		$out .= self::h( 'When to see a professional' );
		$out .= self::p( 'If you keep landing between two seasons, a trained color analyst with a full set of drapes can help. An at-home analysis and an online quiz are good starting points, but neither is as precise as an in-person draping.' );
		$out .= self::faqs(
			array(
				array( 'Can I do color analysis without a professional?', 'Yes. Daylight, a mirror and a few fabrics are enough for a good estimate. A professional draping is more precise when you are between seasons.' ),
				array( 'What time of day is best?', 'Late morning or early afternoon, near a window with indirect daylight.' ),
				array( 'Does my phone camera work for color analysis?', 'For contrast, yes: a black-and-white photo works well. For undertone, phone cameras adjust colors automatically, so trust the mirror in daylight more than a photo.' ),
			)
		);
		return $out;
	}

	private static function guide_undertone() {
		$out  = self::p( 'Your <strong>undertone</strong> is the cast under the surface of your skin. A <strong>warm undertone</strong> looks golden, peach or yellow; a <strong>cool undertone</strong> looks pink, rosy or bluish. A <strong>neutral undertone</strong> is a balance of both, and an <strong>olive undertone</strong> has a greenish or gray-gold cast. Undertone does not change with a tan and is not the same as how light or dark your skin is.' );
		$out .= self::h( 'Six tests for warm or cool undertones' );
		$out .= self::p( 'No single test is reliable on its own. Do several and look for a pattern.' );
		$out .= self::h( '1. The vein test', 3 );
		$out .= self::p( 'Look at the veins on your inner wrist in daylight. Mostly blue or purple veins suggest cool; mostly green veins suggest warm; a mix suggests neutral. This test is the least reliable, especially on deeper skin.' );
		$out .= self::h( '2. The jewelry test', 3 );
		$out .= self::p( 'Hold yellow gold and silver near your face. If gold makes your skin look warmer and healthier, you likely have a warm undertone. If silver makes it look clearer, you likely have a cool one. If both look equally good, you may be neutral.' );
		$out .= self::h( '3. White vs ivory', 3 );
		$out .= self::p( 'Pure, bright white tends to flatter cool undertones; ivory and cream tend to flatter warm ones. Watch for which one makes your skin look sallow or gray.' );
		$out .= self::h( '4. The sun test', 3 );
		$out .= self::p( 'Skin that burns pink and rarely tans often leans cool. Skin that tans easily to golden or bronze often leans warm. Deeper skin can tan without burning at either undertone, so use this test with the others.' );
		$out .= self::h( '5. Pink vs coral', 3 );
		$out .= self::p( 'Compare a cool pink and a warm coral lipstick, blush or top. Cool undertones usually look fresher in pink; warm undertones in coral.' );
		$out .= self::h( '6. Navy vs brown', 3 );
		$out .= self::p( 'As a basic color, navy tends to suit cool undertones and warm chocolate brown tends to suit warm ones.' );
		$out .= self::h( 'Neutral and olive undertones' );
		$out .= self::p( 'If your tests split evenly, you are likely neutral, and seasons such as Soft Summer, Soft Autumn, Bright Spring and Bright Winter often fit. Olive skin has a green or gray-gold cast. It is often muted, which can point to the Soft seasons, but olive skin appears in many seasons.' );
		$out .= self::h( 'Undertone is not skin depth' );
		$out .= self::p( 'Fair skin can be warm and deep skin can be cool. Depth affects whether light or deep colors suit you; undertone affects whether warm or cool colors do. The 12 seasons combine both.' );
		$out .= self::h( 'What your undertone means for your season' );
		$out .= self::ul(
			array(
				'<strong>Warm:</strong> usually Spring or Autumn. See ' . self::link( 'True Spring', '/true-spring-color-palette/' ) . ' and ' . self::link( 'True Autumn', '/true-autumn-color-palette/' ) . '.',
				'<strong>Cool:</strong> usually Summer or Winter. See ' . self::link( 'True Summer', '/true-summer-color-palette/' ) . ' and ' . self::link( 'True Winter', '/true-winter-color-palette/' ) . '.',
				'<strong>Neutral or olive:</strong> often a Soft, Light, Bright or Deep season, where another quality matters more than temperature.',
			)
		);
		$out .= self::p( 'Undertone is only one of four qualities. ' . self::link( 'Take the free quiz', '/seasonal-color-analysis-quiz/' ) . ' to combine it with depth, chroma and contrast.' );
		$out .= self::faqs(
			array(
				array( 'Can my undertone change?', 'No. A tan, redness or illness can change your surface tone, but your undertone stays the same.' ),
				array( 'Is olive skin warm or cool?', 'Olive is its own cast. Many olive-skinned people are warm-neutral, but some lean cool. Use the jewelry and white tests.' ),
				array( 'What if every test gives a different answer?', 'Then you are probably neutral, and depth or chroma decide your season more than temperature does.' ),
			)
		);
		return $out;
	}

	private static function compare_soft() {
		$out  = self::p( '<strong>Soft Autumn and Soft Summer are both muted, medium and low in contrast. The difference is temperature: Soft Autumn leans warm and Soft Summer leans cool.</strong> If sage, camel and terracotta make your skin look even, you are closer to Soft Autumn. If dusty rose, slate blue and soft white do, you are closer to Soft Summer.' );
		$out .= self::sc( '[sca_compare a="soft-autumn" b="soft-summer" form="no"]' );
		$out .= self::h( 'What they share' );
		$out .= self::p( 'Both seasons have soft coloring: hair, skin and eyes blend into each other, and nothing looks stark. Bright, saturated colors and black tend to overpower both. Both look best in colors that seem to have a little gray mixed in, worn in close, tonal combinations.' );
		$out .= self::h( 'The key difference: temperature' );
		$out .= self::p( 'Soft Autumn colors have a golden base: sage, moss, olive, soft terracotta, golden wheat and soft teal. Soft Summer colors have a blue or gray base: dusty rose, mauve, slate blue, steel blue and gray sage. Because both are close to neutral, the difference is subtle, which is why these two seasons are often confused.' );
		$out .= self::h( 'Tests to try at home' );
		$out .= self::ul(
			array(
				'<strong>Ivory vs soft white:</strong> ivory flatters Soft Autumn; soft white flatters Soft Summer.',
				'<strong>Camel vs taupe gray:</strong> camel warms up Soft Autumn; taupe gray suits Soft Summer.',
				'<strong>Terracotta vs dusty rose:</strong> hold both near your face and watch which one evens your skin.',
				'<strong>Brushed gold vs pewter:</strong> warm metals suit Soft Autumn, cool metals suit Soft Summer.',
			)
		);
		$out .= self::h( 'If you are still unsure' );
		$out .= self::p( 'Some people sit right on the line between the two. Start with the shared colors, such as soft teal, gray sage and mushroom, and add warmer or cooler colors one at a time. You can also ' . self::link( 'take the quiz', '/seasonal-color-analysis-quiz/' ) . ' and look at the match strength and the alternative.' );
		$out .= self::p( 'Read the full guides: ' . self::link( 'Soft Autumn color palette', '/soft-autumn-color-palette/' ) . ' and ' . self::link( 'Soft Summer color palette', '/soft-summer-color-palette/' ) . '.' );
		$out .= self::faqs(
			array(
				array( 'Is Soft Autumn warm or cool?', 'Warm-neutral. It is soft first and warm second.' ),
				array( 'Is Soft Summer warm or cool?', 'Cool-neutral. It is soft first and cool second.' ),
				array( 'Can a Soft Summer wear Soft Autumn colors?', 'Some shared colors work for both, such as soft teal and gray sage. Clearly golden colors like mustard and camel usually suit Soft Autumn better.' ),
			)
		);
		return $out;
	}
}
