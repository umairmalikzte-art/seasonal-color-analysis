<?php
/**
 * Front-end shortcodes. They work with any theme.
 *
 * @package SeasonalColorToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes:
 * [sca_quiz] [sca_quiz_preview] [sca_season_families] [sca_popular_palettes]
 * [sca_season_profile] [sca_palette] [sca_season_table] [sca_palette_explorer]
 * [sca_compare] [sca_related_seasons] [sca_content_list]
 */
class SCA_Shortcodes {

	/** Hooks. */
	public static function init() {
		$codes = array(
			'sca_quiz'             => 'quiz',
			'sca_quiz_preview'     => 'quiz_preview',
			'sca_quiz_start'       => 'quiz_start',
			'sca_season_image'     => 'season_image',
			'sca_season_families'  => 'season_families',
			'sca_popular_palettes' => 'popular_palettes',
			'sca_season_profile'   => 'season_profile',
			'sca_palette'          => 'palette',
			'sca_season_table'     => 'season_table',
			'sca_palette_explorer' => 'palette_explorer',
			'sca_compare'          => 'compare',
			'sca_related_seasons'  => 'related_seasons',
			'sca_content_list'     => 'content_list',
			'sca_generator'        => 'generator',
			'sca_trend_table'      => 'trend_table',
			'sca_photo'            => 'photo',
			'sca_swatches'         => 'swatches',
		);
		foreach ( $codes as $tag => $method ) {
			add_shortcode( $tag, array( __CLASS__, $method ) );
		}
		add_filter( 'the_content', array( __CLASS__, 'breadcrumbs' ), 5 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}


	/**
	 * Season from the shortcode attribute or from the current season post.
	 *
	 * @param array $atts Attributes.
	 * @return array|null
	 */
	private static function season_from( $atts ) {
		$slug = isset( $atts['season'] ) ? sanitize_key( $atts['season'] ) : '';
		if ( ! $slug && is_singular( 'color_season' ) ) {
			$slug = (string) get_post_meta( get_the_ID(), '_sca_season_slug', true );
		}
		return $slug ? SCA_Repository::season( $slug ) : null;
	}

	/** [sca_quiz] The quiz app. All answers stay in the browser. */
	public static function quiz() {
		SCA_Assets::enqueue_quiz();
		$html  = '<div class="sca-quiz" id="sca-quiz" data-sca-quiz>';
		$html .= '<noscript><p class="sca-notice">' . esc_html__( 'The quiz needs JavaScript. You can still browse all 12 season palettes.', 'seasonal-color-toolkit' ) . ' <a href="' . esc_url( SCA_Repository::page_url( '12-color-seasons' ) ) . '">' . esc_html__( 'See the 12 color seasons', 'seasonal-color-toolkit' ) . '</a></p></noscript>';
		$html .= '<div class="sca-quiz__loading" aria-hidden="true"></div>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * [sca_quiz_start] The first quiz question as plain links, for the
	 * homepage hero. Choosing an answer opens the quiz at question 2. The
	 * answer travels in the URL fragment (#start=), which browsers never send
	 * to the server, so nothing is logged. No JavaScript is needed here.
	 */
	public static function quiz_start() {
		$questions = SCA_Repository::questions();
		if ( ! $questions ) {
			return '';
		}
		$q     = $questions[0];
		$url   = SCA_Repository::page_url( 'seasonal-color-analysis-quiz' );
		$count = count( $questions );
		$html  = '<div class="sca-start">';
		/* translators: %d: number of questions */
		$html .= '<p class="sca-start__meta"><span class="sca-badge">' . esc_html__( 'Free quiz', 'seasonal-color-toolkit' ) . '</span> ' . esc_html( sprintf( __( '%d questions · about 3 minutes · no photo', 'seasonal-color-toolkit' ), $count ) ) . '</p>';
		/* translators: %d: number of questions */
		$html .= '<p class="sca-start__count">' . esc_html( sprintf( __( 'Question 1 of %d', 'seasonal-color-toolkit' ), $count ) ) . '</p>';
		$html .= '<p class="sca-start__question" id="sca-start-q">' . esc_html( $q['question_text'] ) . '</p>';
		$html .= '<ul class="sca-start__options" role="list" aria-labelledby="sca-start-q">';
		foreach ( $q['answers'] as $a ) {
			$sw    = SCA_Repository::split( $a['swatches'] );
			$html .= '<li><a href="' . esc_url( $url . '#start=' . $q['question_key'] . ':' . $a['answer_key'] ) . '" rel="nofollow">';
			$html .= $sw ? '<span class="sca-start__dot" style="background:' . esc_attr( (string) sanitize_hex_color( $sw[0] ) ) . '" aria-hidden="true"></span>' : '<span class="sca-start__dot sca-start__dot--empty" aria-hidden="true"></span>';
			$html .= '<span>' . esc_html( $a['answer_text'] ) . '</span></a></li>';
		}
		$html .= '</ul><p class="sca-start__privacy">' . esc_html__( 'No sign-up or email. Your answers stay on your device.', 'seasonal-color-toolkit' ) . '</p></div>';
		return $html;
	}

	/** [sca_quiz_preview] Homepage card that introduces the quiz. */
	public static function quiz_preview() {
		$count = count( SCA_Repository::questions() );
		$url   = SCA_Repository::page_url( 'seasonal-color-analysis-quiz' );
		$html  = '<div class="sca-preview">';
		$html .= '<p class="sca-badge">' . esc_html__( 'Free quiz', 'seasonal-color-toolkit' ) . '</p>';
		$html .= '<ul class="sca-preview__facts" role="list">';
		/* translators: %d: number of questions */
		$html .= '<li><strong>' . esc_html( sprintf( _n( '%d question', '%d questions', $count, 'seasonal-color-toolkit' ), $count ) ) . '</strong> ' . esc_html__( 'about 3 minutes', 'seasonal-color-toolkit' ) . '</li>';
		$html .= '<li><strong>' . esc_html__( 'No photo', 'seasonal-color-toolkit' ) . '</strong> ' . esc_html__( 'required', 'seasonal-color-toolkit' ) . '</li>';
		$html .= '<li><strong>' . esc_html__( 'No sign-up', 'seasonal-color-toolkit' ) . '</strong> ' . esc_html__( 'and no email', 'seasonal-color-toolkit' ) . '</li>';
		$html .= '<li><strong>' . esc_html__( 'Private', 'seasonal-color-toolkit' ) . '</strong> ' . esc_html__( 'answers stay on your device', 'seasonal-color-toolkit' ) . '</li>';
		$html .= '</ul>';
		$html .= '<a class="sca-btn sca-btn--primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Start the quiz', 'seasonal-color-toolkit' ) . '</a>';
		$html .= '</div>';
		return $html;
	}

	/** [sca_season_families] Four families with their three sub-seasons. */
	public static function season_families() {
		$blurbs = array(
			'spring' => __( 'Warm and clear. Fresh, golden colors.', 'seasonal-color-toolkit' ),
			'summer' => __( 'Cool and soft. Gentle, blue-based colors.', 'seasonal-color-toolkit' ),
			'autumn' => __( 'Warm and muted. Rich, earthy colors.', 'seasonal-color-toolkit' ),
			'winter' => __( 'Cool and clear. Bold, high-contrast colors.', 'seasonal-color-toolkit' ),
		);
		$by     = array();
		foreach ( SCA_Repository::seasons() as $s ) {
			$by[ $s['season_family'] ][] = $s;
		}
		$html = '<div class="sca-families">';
		foreach ( SCA_Repository::FAMILIES as $family ) {
			if ( empty( $by[ $family ] ) ) {
				continue;
			}
			$html .= '<section class="sca-family sca-family--' . esc_attr( $family ) . '">';
			$html .= '<h3 class="sca-family__title">' . esc_html( SCA_Render::family_name( $family ) ) . '</h3>';
			$html .= '<p class="sca-family__blurb">' . esc_html( $blurbs[ $family ] ) . '</p><ul class="sca-family__list" role="list">';
			foreach ( $by[ $family ] as $s ) {
				$html .= '<li><a href="' . esc_url( SCA_Repository::season_url( $s['season_slug'] ) ) . '">' . SCA_Render::strip( SCA_Repository::palette( $s['id'] ), 5 ) . '<span>' . esc_html( $s['season_name'] ) . '</span></a></li>';
			}
			$html .= '</ul></section>';
		}
		return $html . '</div>';
	}

	/** [sca_popular_palettes seasons="soft-autumn,..."] */
	public static function popular_palettes( $atts ) {
		$atts  = shortcode_atts( array( 'seasons' => 'soft-autumn,soft-summer,deep-winter,true-autumn,light-spring,bright-winter' ), $atts, 'sca_popular_palettes' );
		$html  = '<div class="sca-cards">';
		foreach ( SCA_Repository::split( $atts['seasons'] ) as $slug ) {
			$s = SCA_Repository::season( sanitize_key( $slug ) );
			if ( $s ) {
				$html .= SCA_Render::season_card( $s );
			}
		}
		return $html . '</div>';
	}

	/** [sca_season_profile season="soft-autumn"] Family, alternative names and the four meters. */
	public static function season_profile( $atts ) {
		$s = self::season_from( (array) $atts );
		if ( ! $s ) {
			return '';
		}
		$alt  = SCA_Repository::split( $s['alt_names'] );
		$html = '<div class="sca-profile sca-family--' . esc_attr( $s['season_family'] ) . '">';
		$html .= '<p class="sca-profile__tags"><span class="sca-badge">' . esc_html( SCA_Render::family_name( $s['season_family'] ) ) . ' ' . esc_html__( 'family', 'seasonal-color-toolkit' ) . '</span>';
		if ( $alt ) {
			/* translators: %s: alternative season names */
			$html .= ' <span class="sca-profile__alt">' . esc_html( sprintf( __( 'Also called %s', 'seasonal-color-toolkit' ), implode( ', ', $alt ) ) ) . '</span>';
		}
		$html .= '</p>' . SCA_Render::meters( SCA_Render::season_values( $s ) );

		// At a glance: short, quotable facts (featured snippets, AI answers).
		$names = array(
			'core'    => array(),
			'neutral' => array(),
			'avoid'   => array(),
		);
		foreach ( SCA_Repository::palette( $s['id'] ) as $c ) {
			if ( isset( $names[ $c['color_category'] ] ) ) {
				$names[ $c['color_category'] ][] = $c['color_name'];
			}
		}
		$facts = array(
			__( 'Family', 'seasonal-color-toolkit' )           => SCA_Render::family_name( $s['season_family'] ),
			__( 'Signature colors', 'seasonal-color-toolkit' ) => implode( ', ', array_slice( $names['core'], 0, 5 ) ),
			__( 'Best neutrals', 'seasonal-color-toolkit' )    => implode( ', ', array_slice( $names['neutral'], 0, 5 ) ),
			__( 'Wear with care', 'seasonal-color-toolkit' )   => implode( ', ', $names['avoid'] ),
		);
		/* translators: %s: season */
		$html .= '<p class="sca-profile__title">' . esc_html( sprintf( __( '%s at a glance', 'seasonal-color-toolkit' ), $s['season_name'] ) ) . '</p><dl class="sca-facts">';
		foreach ( $facts as $label => $value ) {
			if ( '' !== $value ) {
				$html .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}
		}
		$html .= '</dl></div>';
		return $html;
	}

	/** [sca_palette season="soft-autumn" show="neutral,core,accent" tools="yes"] */
	public static function palette( $atts ) {
		$atts = shortcode_atts(
			array(
				'season' => '',
				'show'   => 'neutral,core,accent',
				'tools'  => 'yes',
			),
			$atts,
			'sca_palette'
		);
		$s    = self::season_from( $atts );
		if ( ! $s ) {
			return '';
		}
		SCA_Assets::enqueue_palette();
		$cats = array_intersect( SCA_Repository::split( $atts['show'] ), SCA_Repository::CATEGORIES );
		return SCA_Render::palette( $s, array_values( $cats ), 'yes' === $atts['tools'] );
	}

	/**
	 * [sca_season_image season=""] Original palette graphic (WebP, lazy) with
	 * names and hex codes, for readers who save or pin palettes.
	 */
	public static function season_image( $atts ) {
		$s = self::season_from( (array) $atts );
		if ( ! $s ) {
			return '';
		}
		$rel = 'assets/img/seasons/' . $s['season_slug'] . '-palette.webp';
		if ( ! file_exists( SCA_DIR . $rel ) ) {
			return '';
		}
		/* translators: %s: season */
		$alt  = sprintf( __( '%s color palette: neutrals, core colors and accents with color names and hex codes', 'seasonal-color-toolkit' ), $s['season_name'] );
		$html = '<figure class="sca-figure"><img src="' . esc_url( SCA_URL . $rel ) . '" width="1200" height="900" loading="lazy" decoding="async" alt="' . esc_attr( $alt ) . '">';
		/* translators: %s: season */
		$html .= '<figcaption>' . esc_html( sprintf( __( 'The %s palette as one image. Save it to your phone to use while shopping.', 'seasonal-color-toolkit' ), $s['season_name'] ) ) . '</figcaption></figure>';
		return $html;
	}

	/** [sca_season_table] All 12 seasons with their four dimensions. */
	public static function season_table() {
		$html  = '<div class="sca-table-wrap" tabindex="0" role="region" aria-label="' . esc_attr__( 'The 12 color seasons compared', 'seasonal-color-toolkit' ) . '"><table class="sca-table">';
		$html .= '<thead><tr><th scope="col">' . esc_html__( 'Season', 'seasonal-color-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Temperature', 'seasonal-color-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Depth', 'seasonal-color-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Chroma', 'seasonal-color-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Contrast', 'seasonal-color-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Also called', 'seasonal-color-toolkit' ) . '</th></tr></thead><tbody>';
		foreach ( SCA_Repository::seasons() as $s ) {
			$html .= '<tr><th scope="row"><a href="' . esc_url( SCA_Repository::season_url( $s['season_slug'] ) ) . '">' . esc_html( $s['season_name'] ) . '</a></th>';
			foreach ( SCA_Repository::DIMENSIONS as $d ) {
				$html .= '<td>' . esc_html( SCA_Render::label( $d, $s[ $d . '_target' ] ) ) . '</td>';
			}
			$alt   = SCA_Repository::split( $s['alt_names'] );
			$html .= '<td>' . esc_html( $alt ? implode( ', ', $alt ) : '–' ) . '</td></tr>';
		}
		return $html . '</tbody></table></div>';
	}

	/** [sca_palette_explorer] Pick a season, see and copy its palette. */
	public static function palette_explorer() {
		SCA_Assets::enqueue_palette();
		$seasons = SCA_Repository::seasons();
		if ( ! $seasons ) {
			return '';
		}
		$html  = '<div class="sca-explorer" data-sca-explorer>';
		$html .= '<div class="sca-explorer__tabs" role="tablist" aria-label="' . esc_attr__( 'Choose a season', 'seasonal-color-toolkit' ) . '">';
		foreach ( $seasons as $i => $s ) {
			$id    = 'sca-explorer-' . $s['season_slug'];
			$html .= '<button type="button" role="tab" class="sca-family--' . esc_attr( $s['season_family'] ) . '" id="' . esc_attr( $id ) . '-tab" aria-controls="' . esc_attr( $id ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '" tabindex="' . ( 0 === $i ? '0' : '-1' ) . '">' . esc_html( $s['season_name'] ) . '</button>';
		}
		$html .= '</div>';
		foreach ( $seasons as $i => $s ) {
			$id    = 'sca-explorer-' . $s['season_slug'];
			$html .= '<div class="sca-explorer__panel" role="tabpanel" id="' . esc_attr( $id ) . '" aria-labelledby="' . esc_attr( $id ) . '-tab"' . ( 0 === $i ? '' : ' hidden' ) . '>';
			$html .= '<p class="sca-explorer__desc">' . esc_html( $s['description'] ) . ' <a href="' . esc_url( SCA_Repository::season_url( $s['season_slug'] ) ) . '">' . esc_html( sprintf( /* translators: %s: season */ __( 'Read the %s guide', 'seasonal-color-toolkit' ), $s['season_name'] ) ) . '</a></p>';
			$html .= SCA_Render::palette( $s, array( 'neutral', 'core', 'accent' ) );
			$html .= '</div>';
		}
		return $html . '</div>';
	}

	/**
	 * [sca_compare a="soft-autumn" b="soft-summer"] Two seasons side by side.
	 * Works without JavaScript: the form reloads the page with ?a=&b=.
	 */
	public static function compare( $atts ) {
		$atts = shortcode_atts(
			array(
				'a'    => 'soft-autumn',
				'b'    => 'soft-summer',
				'form' => 'yes',
			),
			$atts,
			'sca_compare'
		);
		$form = 'yes' === $atts['form'];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET view, no state change.
		$a = $form && isset( $_GET['a'] ) ? sanitize_key( wp_unslash( $_GET['a'] ) ) : sanitize_key( $atts['a'] );
		$b = $form && isset( $_GET['b'] ) ? sanitize_key( wp_unslash( $_GET['b'] ) ) : sanitize_key( $atts['b'] );
		// phpcs:enable
		$sa = SCA_Repository::season( $a );
		$sb = SCA_Repository::season( $b );
		if ( ! $sa || ! $sb ) {
			$sa = SCA_Repository::season( 'soft-autumn' );
			$sb = SCA_Repository::season( 'soft-summer' );
		}
		if ( ! $sa || ! $sb ) {
			return '';
		}
		SCA_Assets::enqueue_palette();

		$html = '<div class="sca-compare">';
		if ( $form ) {
			$html .= '<form class="sca-compare__form" method="get" action="' . esc_url( get_permalink() ) . '#sca-compare-result">';
			foreach ( array(
				'a' => array( __( 'First season', 'seasonal-color-toolkit' ), $sa ),
				'b' => array( __( 'Second season', 'seasonal-color-toolkit' ), $sb ),
			) as $field => $f ) {
				$html .= '<label class="sca-field"><span>' . esc_html( $f[0] ) . '</span><select name="' . esc_attr( $field ) . '">';
				foreach ( SCA_Repository::seasons() as $s ) {
					$html .= '<option value="' . esc_attr( $s['season_slug'] ) . '"' . selected( $s['season_slug'], $f[1]['season_slug'], false ) . '>' . esc_html( $s['season_name'] ) . '</option>';
				}
				$html .= '</select></label>';
			}
			$html .= '<button class="sca-btn sca-btn--primary" type="submit">' . esc_html__( 'Compare', 'seasonal-color-toolkit' ) . '</button></form>';
		}

		$html .= '<div id="sca-compare-result" class="sca-compare__result">';
		$html .= '<div class="sca-table-wrap"><table class="sca-table sca-table--compare"><thead><tr><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Dimension', 'seasonal-color-toolkit' ) . '</span></th>';
		$html .= '<th scope="col"><a href="' . esc_url( SCA_Repository::season_url( $sa['season_slug'] ) ) . '">' . esc_html( $sa['season_name'] ) . '</a></th>';
		$html .= '<th scope="col"><a href="' . esc_url( SCA_Repository::season_url( $sb['season_slug'] ) ) . '">' . esc_html( $sb['season_name'] ) . '</a></th></tr></thead><tbody>';
		$html .= '<tr><th scope="row">' . esc_html__( 'Family', 'seasonal-color-toolkit' ) . '</th><td>' . esc_html( SCA_Render::family_name( $sa['season_family'] ) ) . '</td><td>' . esc_html( SCA_Render::family_name( $sb['season_family'] ) ) . '</td></tr>';
		foreach ( SCA_Render::dimension_meta() as $dim => $meta ) {
			$html .= '<tr><th scope="row">' . esc_html( $meta[0] ) . '</th><td>' . esc_html( SCA_Render::label( $dim, $sa[ $dim . '_target' ] ) ) . '</td><td>' . esc_html( SCA_Render::label( $dim, $sb[ $dim . '_target' ] ) ) . '</td></tr>';
		}
		$html .= '</tbody></table></div>';

		list( $same, $diff ) = self::compare_points( $sa, $sb );
		$html               .= '<div class="sca-compare__points">';
		$html               .= '<div><h3>' . esc_html__( 'What they share', 'seasonal-color-toolkit' ) . '</h3><ul>';
		foreach ( $same ? $same : array( __( 'Very little: these seasons sit far apart.', 'seasonal-color-toolkit' ) ) as $p ) {
			$html .= '<li>' . esc_html( $p ) . '</li>';
		}
		$html .= '</ul></div><div><h3>' . esc_html__( 'How they differ', 'seasonal-color-toolkit' ) . '</h3><ul>';
		foreach ( $diff ? $diff : array( __( 'They are the same season.', 'seasonal-color-toolkit' ) ) as $p ) {
			$html .= '<li>' . esc_html( $p ) . '</li>';
		}
		$html .= '</ul></div></div>';

		$html .= '<div class="sca-compare__palettes">';
		foreach ( array( $sa, $sb ) as $s ) {
			$html .= '<section><h3><a href="' . esc_url( SCA_Repository::season_url( $s['season_slug'] ) ) . '">' . esc_html( $s['season_name'] ) . '</a></h3>';
			$html .= SCA_Render::palette( $s, array( 'neutral', 'core', 'accent' ), false ) . '</section>';
		}
		$html .= '</div><p class="sca-note">' . esc_html__( 'Palettes are a guide. Whether a color suits you depends on how it looks next to your face in daylight, not on its hex code alone.', 'seasonal-color-toolkit' ) . '</p>';
		return $html . '</div></div>';
	}

	/**
	 * Similarities and differences between two seasons, from their prototypes.
	 *
	 * @return array{0:string[],1:string[]}
	 */
	public static function compare_points( array $a, array $b ) {
		$words = array(
			'warmth'   => array( 'warmer', 'cooler', 'temperature' ),
			'depth'    => array( 'deeper', 'lighter', 'depth' ),
			'chroma'   => array( 'brighter', 'softer', 'chroma (brightness)' ),
			'contrast' => array( 'higher in contrast', 'lower in contrast', 'contrast level' ),
		);
		$same  = array();
		$diff  = array();
		if ( $a['season_family'] === $b['season_family'] ) {
			/* translators: %s: family */
			$same[] = sprintf( __( 'Both belong to the %s family.', 'seasonal-color-toolkit' ), SCA_Render::family_name( $a['season_family'] ) );
		}
		foreach ( $words as $dim => $w ) {
			$va = (float) $a[ $dim . '_target' ];
			$vb = (float) $b[ $dim . '_target' ];
			$la = SCA_Render::label( $dim, $va );
			if ( abs( $va - $vb ) < 0.3 ) {
				/* translators: 1: dimension, 2: label */
				$same[] = sprintf( __( 'A similar %1$s (%2$s).', 'seasonal-color-toolkit' ), $w[2], strtolower( $la ) );
			} else {
				/* translators: 1: season, 2: comparative, 3: season */
				$diff[] = sprintf( __( '%1$s is %2$s than %3$s.', 'seasonal-color-toolkit' ), $a['season_name'], $va > $vb ? $w[0] : $w[1], $b['season_name'] );
			}
		}
		return array( $same, $diff );
	}

	/**
	 * [sca_generator] Season palette generator with harmonies and trends,
	 * plus a "check my colors" tab (PRD tool C). Runs in the browser.
	 */
	public static function generator() {
		SCA_Assets::enqueue_generator();
		$seasons = SCA_Repository::seasons();
		$trends  = SCA_Repository::trends();
		$harm    = array(
			'analogous'     => __( 'Analogous', 'seasonal-color-toolkit' ),
			'tonal'         => __( 'Tonal', 'seasonal-color-toolkit' ),
			'complementary' => __( 'Complementary', 'seasonal-color-toolkit' ),
			'triadic'       => __( 'Triadic', 'seasonal-color-toolkit' ),
			'neutral'       => __( 'Neutral + pop', 'seasonal-color-toolkit' ),
		);

		$html  = '<div class="sca-gen" data-sca-generator><div class="sca-gen__top"><div class="sca-gen__pick">';
		$html .= '<label class="sca-field sca-gen__season"><span>' . esc_html__( 'Your season', 'seasonal-color-toolkit' ) . '</span><select data-gen-season>';
		foreach ( SCA_Repository::FAMILIES as $family ) {
			$html .= '<optgroup label="' . esc_attr( SCA_Render::family_name( $family ) ) . '">';
			foreach ( $seasons as $s ) {
				if ( $s['season_family'] === $family ) {
					$html .= '<option value="' . esc_attr( $s['season_slug'] ) . '"' . selected( 'soft-autumn', $s['season_slug'], false ) . '>' . esc_html( $s['season_name'] ) . '</option>';
				}
			}
			$html .= '</optgroup>';
		}
		$html .= '</select></label>';
		$html .= '<p class="sca-gen__hint">' . sprintf(
			/* translators: %s: quiz link */
			esc_html__( 'Not sure? %s', 'seasonal-color-toolkit' ),
			'<a href="' . esc_url( SCA_Repository::page_url( 'seasonal-color-analysis-quiz' ) ) . '">' . esc_html__( 'Take the quiz first', 'seasonal-color-toolkit' ) . '</a>'
		) . '</p></div>';

		$html .= '<div class="sca-gen__tabs" role="tablist" aria-label="' . esc_attr__( 'Generator mode', 'seasonal-color-toolkit' ) . '">';
		$html .= '<button type="button" role="tab" id="sca-gen-tab-1" aria-controls="sca-gen-panel-1" aria-selected="true">' . esc_html__( 'Generate outfit colors', 'seasonal-color-toolkit' ) . '</button>';
		$html .= '<button type="button" role="tab" id="sca-gen-tab-2" aria-controls="sca-gen-panel-2" aria-selected="false" tabindex="-1">' . esc_html__( 'Check my colors', 'seasonal-color-toolkit' ) . '</button></div></div>';

		// Panel 1: generator.
		$html .= '<div class="sca-gen__panel" role="tabpanel" id="sca-gen-panel-1" aria-labelledby="sca-gen-tab-1"><div class="sca-gen__layout">';
		$side  = '<div class="sca-gen__side"><div class="sca-gen__controls"><fieldset class="sca-gen__harmony"><legend>' . esc_html__( 'Color harmony', 'seasonal-color-toolkit' ) . '</legend>';
		foreach ( $harm as $key => $label ) {
			$side .= '<label class="sca-pill"><input type="radio" name="sca-harmony" value="' . esc_attr( $key ) . '"' . checked( 'analogous', $key, false ) . '><span>' . esc_html( $label ) . '</span></label>';
		}
		$side .= '</fieldset>';
		if ( $trends ) {
			$side .= '<label class="sca-field"><span>' . esc_html__( 'Start from a trend color (optional)', 'seasonal-color-toolkit' ) . '</span><select data-gen-trend><option value="">' . esc_html__( 'No trend: surprise me', 'seasonal-color-toolkit' ) . '</option>';
			$groups = array();
			foreach ( $trends as $t ) {
				$groups[ $t['collection'] ][] = $t;
			}
			foreach ( $groups as $collection => $list ) {
				$side .= '<optgroup label="' . esc_attr( $collection ) . '">';
				foreach ( $list as $t ) {
					$side .= '<option value="' . esc_attr( $t['slug'] ) . '">' . esc_html( $t['name'] ) . '</option>';
				}
				$side .= '</optgroup>';
			}
			$side .= '</select></label>';
		}
		$side .= '</div>';
		$side .= '<div class="sca-gen__trend" data-gen-trend-out hidden></div></div>';
		$html .= '<div class="sca-gen__main">';
		$html .= '<div class="sca-gen__result" data-gen-result aria-live="polite"><noscript><p class="sca-notice">' . esc_html__( 'The generator needs JavaScript. You can still browse every season palette.', 'seasonal-color-toolkit' ) . ' <a href="' . esc_url( SCA_Repository::page_url( '12-color-seasons' ) . '#palettes' ) . '">' . esc_html__( 'See all palettes', 'seasonal-color-toolkit' ) . '</a></p></noscript></div>';
		$html .= '<div class="sca-gen__actions"><button type="button" class="sca-btn sca-btn--primary" data-gen-shuffle>' . esc_html__( 'Generate new combination', 'seasonal-color-toolkit' ) . '</button>';
		$html .= '<button type="button" class="sca-btn sca-btn--ghost" data-gen-copy>' . esc_html__( 'Copy codes', 'seasonal-color-toolkit' ) . '</button>';
		$html .= '<button type="button" class="sca-btn sca-btn--ghost" data-gen-download>' . esc_html__( 'Download (PNG)', 'seasonal-color-toolkit' ) . '</button>';
		$html .= '<span class="sca-palette__status" data-gen-status role="status" aria-live="polite"></span></div>';
		$html .= '</div>' . $side . '</div></div>';

		// Panel 2: check my colors.
		$html .= '<div class="sca-gen__panel" role="tabpanel" id="sca-gen-panel-2" aria-labelledby="sca-gen-tab-2" hidden>';
		$html .= '<p>' . esc_html__( 'Pick up to three colors, for example from clothes you own or want to buy, to see how close they are to your season\'s palette.', 'seasonal-color-toolkit' ) . '</p><div class="sca-check__inputs">';
		foreach ( array( '#2f45a8', '#e0703f', '#f0eee9' ) as $i => $default ) {
			/* translators: %d: color number */
			$label = sprintf( __( 'Color %d', 'seasonal-color-toolkit' ), $i + 1 );
			$html .= '<div class="sca-check__input"><label><span>' . esc_html( $label ) . '</span><input type="color" value="' . esc_attr( $default ) . '" data-check-color></label>';
			/* translators: %d: color number */
			$html .= '<input type="text" value="' . esc_attr( strtoupper( $default ) ) . '" data-check-hex maxlength="7" spellcheck="false" aria-label="' . esc_attr( sprintf( __( 'Color %d hex code', 'seasonal-color-toolkit' ), $i + 1 ) ) . '"></div>';
		}
		$html .= '</div><div data-check-out aria-live="polite"></div>';
		$html .= '<p class="sca-note">' . esc_html__( 'Closeness to a palette is measured on screen colors only. It is a guide: whether a color suits you depends on how it looks next to your face in daylight, not on its hex code alone.', 'seasonal-color-toolkit' ) . '</p></div>';

		return $html . '</div>';
	}

	/**
	 * [sca_trend_table] Each trend color, its source and the seasons it suits
	 * most closely. Rendered on the server so search engines can read it.
	 */
	public static function trend_table() {
		$trends  = SCA_Repository::trends();
		$seasons = SCA_Repository::seasons();
		if ( ! $trends || ! $seasons ) {
			return '';
		}
		$html  = '<div class="sca-table-wrap" tabindex="0" role="region" aria-label="' . esc_attr__( 'Trend colors by season', 'seasonal-color-toolkit' ) . '"><table class="sca-table sca-table--trends">';
		$html .= '<thead><tr><th scope="col">' . esc_html__( 'Trend color', 'seasonal-color-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Source', 'seasonal-color-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Suits most closely', 'seasonal-color-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Furthest from', 'seasonal-color-toolkit' ) . '</th></tr></thead><tbody>';
		foreach ( $trends as $t ) {
			$fits = array();
			foreach ( $seasons as $s ) {
				$n = SCA_Render::nearest( $s, $t['hex'] );
				if ( $n ) {
					$fits[] = array( $s, $n['distance'] );
				}
			}
			usort(
				$fits,
				static function ( $a, $b ) {
					return $a[1] <=> $b[1];
				}
			);
			$best = array_slice( $fits, 0, 3 );
			$far  = end( $fits );
			$html .= '<tr><th scope="row"><span class="sca-gen__dot sca-gen__dot--small" style="background:' . esc_attr( $t['hex'] ) . '" aria-hidden="true"></span> ' . esc_html( $t['name'] ) . '</th>';
			$html .= '<td>' . ( $t['source'] ? '<a href="' . esc_url( $t['source'] ) . '" rel="noopener nofollow" target="_blank">' . esc_html( $t['collection'] ) . '</a>' : esc_html( $t['collection'] ) ) . '</td><td>';
			$links = array();
			foreach ( $best as $b ) {
				$links[] = '<a href="' . esc_url( SCA_Repository::season_url( $b[0]['season_slug'] ) ) . '">' . esc_html( $b[0]['season_name'] ) . '</a>';
			}
			$html .= implode( ', ', $links ) . '</td><td>' . esc_html( $far ? $far[0]['season_name'] : '–' ) . '</td></tr>';
		}
		$html .= '</tbody></table></div>';
		$html .= '<p class="sca-note">' . esc_html__( 'Trend names and sources are factual. The hex values are on-screen approximations, so colors in stores will vary. "Suits most closely" means the season palette has a very similar color.', 'seasonal-color-toolkit' ) . '</p>';
		return $html;
	}

	/** [sca_related_seasons season=""] Neighbours and family members. */
	public static function related_seasons( $atts ) {
		$s = self::season_from( (array) $atts );
		if ( ! $s ) {
			return '';
		}
		$slugs = SCA_Repository::split( $s['neighbors'] );
		foreach ( SCA_Repository::seasons() as $other ) {
			if ( $other['season_family'] === $s['season_family'] && $other['season_slug'] !== $s['season_slug'] ) {
				$slugs[] = $other['season_slug'];
			}
		}
		$html = '<div class="sca-cards sca-cards--small">';
		foreach ( array_unique( $slugs ) as $slug ) {
			$o = SCA_Repository::season( $slug );
			if ( $o ) {
				$html .= SCA_Render::season_card( $o );
			}
		}
		return $html . '</div>';
	}

	/**
	 * [sca_photo] AI color analysis from a selfie. The photo is analysed in
	 * the browser and never uploaded.
	 */
	public static function photo() {
		SCA_Assets::enqueue_photo();
		$tips = array(
			array( __( 'Daylight', 'seasonal-color-toolkit' ), __( 'from a window, not yellow indoor bulbs', 'seasonal-color-toolkit' ) ),
			array( __( 'No filter', 'seasonal-color-toolkit' ), __( 'no beauty mode or heavy makeup', 'seasonal-color-toolkit' ) ),
			array( __( 'Hair visible', 'seasonal-color-toolkit' ), __( 'at the hairline, eyes open', 'seasonal-color-toolkit' ) ),
		);
		$questions = array(
			'veins'   => array( __( 'Veins on your inner wrist look…', 'seasonal-color-toolkit' ), array( 'blue' => __( 'Blue or purple', 'seasonal-color-toolkit' ), 'green' => __( 'Green or olive', 'seasonal-color-toolkit' ), 'both' => __( 'Both / can\'t tell', 'seasonal-color-toolkit' ) ) ),
			'metal'   => array( __( 'Which jewelry flatters you more?', 'seasonal-color-toolkit' ), array( 'silver' => __( 'Silver or platinum', 'seasonal-color-toolkit' ), 'gold' => __( 'Gold', 'seasonal-color-toolkit' ), 'both' => __( 'Both look fine', 'seasonal-color-toolkit' ) ) ),
			'sun'     => array( __( 'In the sun, your skin usually…', 'seasonal-color-toolkit' ), array( 'burn' => __( 'Burns or turns pink', 'seasonal-color-toolkit' ), 'tan' => __( 'Tans golden easily', 'seasonal-color-toolkit' ), 'both' => __( 'Burns, then tans', 'seasonal-color-toolkit' ) ) ),
			'white'   => array( __( 'Which white looks better next to your face?', 'seasonal-color-toolkit' ), array( 'bright' => __( 'Pure bright white', 'seasonal-color-toolkit' ), 'cream' => __( 'Cream or ivory', 'seasonal-color-toolkit' ), 'both' => __( 'No difference', 'seasonal-color-toolkit' ) ) ),
			'hairnow' => array( __( 'Your hair in the photo is…', 'seasonal-color-toolkit' ), array( 'natural' => __( 'My natural color', 'seasonal-color-toolkit' ), 'dyed' => __( 'Dyed or highlighted', 'seasonal-color-toolkit' ), 'gray' => __( 'Mostly gray', 'seasonal-color-toolkit' ) ) ),
		);

		$h  = '<div class="sca-ph" id="ai-color-analysis-tool">';
		$h .= '<ol class="sca-ph-progress" aria-label="' . esc_attr__( 'Steps', 'seasonal-color-toolkit' ) . '"><li class="is-active">' . esc_html__( 'Photo', 'seasonal-color-toolkit' ) . '</li><li>' . esc_html__( 'Check', 'seasonal-color-toolkit' ) . '</li><li>' . esc_html__( 'Refine', 'seasonal-color-toolkit' ) . '</li><li>' . esc_html__( 'Result', 'seasonal-color-toolkit' ) . '</li></ol>';

		// Step 1: photo.
		$h .= '<section class="sca-ph-step" data-step="1"><div class="sca-ph-drop">';
		$h .= '<svg viewBox="0 0 48 48" width="48" height="48" aria-hidden="true"><circle cx="24" cy="18" r="9" fill="none" stroke="currentColor" stroke-width="2.5"/><path d="M8 42c2.5-8 9-12 16-12s13.5 4 16 12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>';
		$h .= '<h3>' . esc_html__( 'Upload a selfie or use your camera', 'seasonal-color-toolkit' ) . '</h3>';
		$h .= '<p>' . esc_html__( 'Face a window in daylight, without makeup or filters, with your hair off your face.', 'seasonal-color-toolkit' ) . '</p>';
		$h .= '<div class="sca-ph-actions"><label class="sca-btn sca-btn--primary sca-ph-upload">' . esc_html__( 'Choose a photo', 'seasonal-color-toolkit' ) . '<input class="sca-ph-file" type="file" accept="image/*"></label>';
		$h .= '<button type="button" class="sca-btn sca-btn--ghost" data-act="camera">' . esc_html__( 'Use my camera', 'seasonal-color-toolkit' ) . '</button></div>';
		$h .= '<p class="sca-ph-private"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3z" fill="none" stroke="currentColor" stroke-width="2"/></svg>' . esc_html__( 'Your photo never leaves your device. It is analysed in your browser and is not uploaded or stored.', 'seasonal-color-toolkit' ) . '</p>';
		$h .= '</div><div class="sca-ph-cam" hidden><video class="sca-ph-video" playsinline muted></video><div class="sca-ph-actions"><button type="button" class="sca-btn sca-btn--primary" data-act="snap">' . esc_html__( 'Take photo', 'seasonal-color-toolkit' ) . '</button><button type="button" class="sca-btn sca-btn--ghost" data-act="cam-cancel">' . esc_html__( 'Cancel', 'seasonal-color-toolkit' ) . '</button></div></div>';
		$h .= '<ul class="sca-ph-tips" role="list">';
		foreach ( $tips as $t ) {
			$h .= '<li><b>' . esc_html( $t[0] ) . '</b>' . esc_html( $t[1] ) . '</li>';
		}
		$h .= '</ul></section>';

		// Step 2: check samples.
		$h .= '<section class="sca-ph-step" data-step="2" hidden><div class="sca-ph-check"><div><canvas class="sca-ph-photo" aria-label="' . esc_attr__( 'Your photo with the sampled points', 'seasonal-color-toolkit' ) . '"></canvas></div><div>';
		$h .= '<p class="sca-ph-status" role="status" aria-live="polite"></p>';
		$h .= '<div class="sca-ph-manual" hidden><p>' . esc_html__( 'Tap the photo to pick each color:', 'seasonal-color-toolkit' ) . '</p><div class="sca-ph-taps">';
		foreach ( array( 'skin' => __( '1. Skin (cheek)', 'seasonal-color-toolkit' ), 'hair' => __( '2. Hair (roots)', 'seasonal-color-toolkit' ), 'eyes' => __( '3. Eye (iris)', 'seasonal-color-toolkit' ), 'sclera' => __( '4. Eye white (optional)', 'seasonal-color-toolkit' ) ) as $k => $label ) {
			$h .= '<button type="button" data-tap="' . esc_attr( $k ) . '" aria-pressed="false">' . esc_html( $label ) . '</button>';
		}
		$h .= '</div></div><div class="sca-ph-samples"></div><div class="sca-ph-quality"></div>';
		$h .= '<div class="sca-ph-actions"><button type="button" class="sca-btn sca-btn--primary sca-ph-next" data-act="next" disabled>' . esc_html__( 'Continue', 'seasonal-color-toolkit' ) . '</button><button type="button" class="sca-btn sca-btn--ghost" data-act="manual">' . esc_html__( 'Pick colors myself', 'seasonal-color-toolkit' ) . '</button><button type="button" class="sca-btn sca-btn--ghost" data-act="back">' . esc_html__( 'Use another photo', 'seasonal-color-toolkit' ) . '</button></div>';
		$h .= '</div></div></section>';

		// Step 3: refine.
		$h .= '<section class="sca-ph-step" data-step="3" hidden><h3>' . esc_html__( 'Five quick questions to sharpen your result', 'seasonal-color-toolkit' ) . '</h3><p class="sca-ph-help">' . esc_html__( 'Cameras shift skin tone. Your answers correct for that. You can skip them.', 'seasonal-color-toolkit' ) . '</p>';
		foreach ( $questions as $name => $q ) {
			$h .= '<fieldset class="sca-ph-q"><legend>' . esc_html( $q[0] ) . '</legend>';
			foreach ( $q[1] as $val => $label ) {
				$h .= '<label><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '"> ' . esc_html( $label ) . '</label>';
			}
			$h .= '</fieldset>';
		}
		$h .= '<div class="sca-ph-actions"><button type="button" class="sca-btn sca-btn--primary" data-act="result">' . esc_html__( 'Show my color season', 'seasonal-color-toolkit' ) . '</button><button type="button" class="sca-btn sca-btn--ghost" data-act="skip">' . esc_html__( 'Skip the questions', 'seasonal-color-toolkit' ) . '</button></div></section>';

		// Step 4: result.
		$h .= '<section class="sca-ph-step" data-step="4" hidden><div class="sca-ph-result" aria-live="polite"></div></section>';
		$h .= '<noscript><p>' . esc_html__( 'The AI color analysis needs JavaScript. You can take the quiz instead.', 'seasonal-color-toolkit' ) . '</p></noscript>';
		return $h . '</div>';
	}

	/**
	 * [sca_swatches colors="Porcelain:#F6E3D7,Ivory:#F1D6C1" caption=""]
	 * Static named swatches for guides (skin tone charts, color pairs).
	 */
	public static function swatches( $atts ) {
		$atts = shortcode_atts( array( 'colors' => '', 'caption' => '' ), $atts, 'sca_swatches' );
		SCA_Assets::style();
		$html = '<figure class="sca-swatches"><ul class="sca-swatches__list" role="list">';
		foreach ( SCA_Repository::split( $atts['colors'] ) as $item ) {
			$parts = explode( ':', $item );
			$hex   = sanitize_hex_color( trim( (string) end( $parts ) ) );
			if ( ! $hex ) {
				continue;
			}
			$name  = count( $parts ) > 1 ? trim( $parts[0] ) : '';
			$html .= '<li><span class="sca-swatches__chip" style="background:' . esc_attr( $hex ) . '"></span><span class="sca-swatches__name">' . esc_html( $name ) . '</span><code>' . esc_html( strtoupper( $hex ) ) . '</code></li>';
		}
		$html .= '</ul>';
		if ( '' !== $atts['caption'] ) {
			$html .= '<figcaption>' . esc_html( $atts['caption'] ) . '</figcaption>';
		}
		return $html . '</figure>';
	}

	/** [sca_content_list type="color_guide,season_comparison"] Linked list of published guides. */
	public static function content_list( $atts ) {
		$atts  = shortcode_atts( array( 'type' => 'color_guide,season_comparison' ), $atts, 'sca_content_list' );
		$types = array_intersect( SCA_Repository::split( $atts['type'] ), SCA_Post_Types::TYPES );
		$posts = get_posts(
			array(
				'post_type'   => array_values( $types ),
				'post_status' => 'publish',
				'numberposts' => 50,
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
			)
		);
		if ( ! $posts ) {
			return '';
		}
		$html = '<ul class="sca-guides" role="list">';
		foreach ( $posts as $p ) {
			$html .= '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a>';
			if ( has_excerpt( $p ) ) {
				$html .= '<p>' . esc_html( get_the_excerpt( $p ) ) . '</p>';
			}
			$html .= '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * Breadcrumbs above the content of every inner page (PRD 15).
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function breadcrumbs( $content ) {
		if ( ! is_singular() || is_front_page() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$settings = SCA_Repository::settings();
		if ( empty( $settings['breadcrumbs'] ) ) {
			return $content;
		}
		$trail  = array( array( __( 'Home', 'seasonal-color-toolkit' ), home_url( '/' ) ) );
		$parent = array(
			'color_season'      => '12-color-seasons',
			'color_guide'       => 'color-guides',
			'season_comparison' => 'compare-seasons',
		);
		$type   = get_post_type();
		if ( isset( $parent[ $type ] ) ) {
			$page = get_page_by_path( $parent[ $type ] );
			if ( $page ) {
				$trail[] = array( get_the_title( $page ), get_permalink( $page ) );
			}
		}
		$trail[] = array( get_the_title(), '' );

		$html = '<nav class="sca-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'seasonal-color-toolkit' ) . '"><ol>';
		foreach ( $trail as $item ) {
			$html .= $item[1]
				? '<li><a href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a></li>'
				: '<li><span aria-current="page">' . esc_html( $item[0] ) . '</span></li>';
		}
		$html .= '</ol></nav>';
		if ( 'page' !== $type || in_array( get_post_field( 'post_name' ), array( '12-color-seasons', 'seasonal-color-palette-generator', 'seasonal-color-analysis-quiz' ), true ) ) {
			/* translators: %s: date */
			$html .= '<p class="sca-updated">' . sprintf( esc_html__( 'Last updated %s', 'seasonal-color-toolkit' ), '<time datetime="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . esc_html( get_the_modified_date( 'F j, Y' ) ) . '</time>' ) . '</p>';
		}
		return $html . $content;
	}

	/**
	 * Comparison tool results with ?a=&b= are not indexed (the canonical page is).
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_singular() && ( isset( $_GET['a'] ) || isset( $_GET['b'] ) || isset( $_GET['season'] ) || isset( $_GET['harmony'] ) || isset( $_GET['trend'] ) ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
}
