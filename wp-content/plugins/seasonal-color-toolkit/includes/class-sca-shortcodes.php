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
			'sca_season_families'  => 'season_families',
			'sca_popular_palettes' => 'popular_palettes',
			'sca_season_profile'   => 'season_profile',
			'sca_palette'          => 'palette',
			'sca_season_table'     => 'season_table',
			'sca_palette_explorer' => 'palette_explorer',
			'sca_compare'          => 'compare',
			'sca_related_seasons'  => 'related_seasons',
			'sca_content_list'     => 'content_list',
		);
		foreach ( $codes as $tag => $method ) {
			add_shortcode( $tag, array( __CLASS__, $method ) );
		}
		add_filter( 'the_content', array( __CLASS__, 'breadcrumbs' ), 5 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'wp_head', array( __CLASS__, 'meta_description' ), 1 );
	}

	/**
	 * Fallback meta description from the excerpt. Skipped when Yoast SEO or
	 * Rank Math is active, because they own this tag (PRD 10).
	 */
	public static function meta_description() {
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( $post instanceof WP_Post && '' !== $post->post_excerpt ) {
			echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $post->post_excerpt ) ) . '">' . "\n";
		}
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
		$html .= '</p>' . SCA_Render::meters( SCA_Render::season_values( $s ) ) . '</div>';
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
		return $html . '</ol></nav>' . $content;
	}

	/**
	 * Comparison tool results with ?a=&b= are not indexed (the canonical page is).
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_singular() && ( isset( $_GET['a'] ) || isset( $_GET['b'] ) || isset( $_GET['season'] ) ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
}
