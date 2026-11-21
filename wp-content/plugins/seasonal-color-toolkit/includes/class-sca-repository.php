<?php
/**
 * Read access to seasons, palettes, questions and settings.
 *
 * @package SeasonalColorToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * All reads are cached for the request. Writes happen in SCA_Admin, which
 * calls flush() afterwards.
 */
class SCA_Repository {

	const DIMENSIONS = array( 'warmth', 'depth', 'chroma', 'contrast' );
	const FAMILIES   = array( 'spring', 'summer', 'autumn', 'winter' );
	const CATEGORIES = array( 'neutral', 'core', 'accent', 'avoid' );

	/** @var array Request cache. */
	private static $cache = array();

	/** Clears the request cache. */
	public static function flush() {
		self::$cache = array();
	}

	/**
	 * Seasons ordered by display order.
	 *
	 * @param bool $active_only Only active seasons.
	 * @return array<int,array>
	 */
	public static function seasons( $active_only = true ) {
		$key = 'seasons_' . (int) $active_only;
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}
		global $wpdb;
		$t     = SCA_Install::tables();
		$where = $active_only ? 'WHERE is_active = 1' : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input; table name from prefix.
		$rows = (array) $wpdb->get_results( "SELECT * FROM {$t['seasons']} $where ORDER BY display_order ASC, id ASC", ARRAY_A );
		foreach ( $rows as &$r ) {
			$r['id'] = (int) $r['id'];
			foreach ( self::DIMENSIONS as $d ) {
				$r[ $d . '_target' ] = (float) $r[ $d . '_target' ];
			}
		}
		self::$cache[ $key ] = $rows;
		return $rows;
	}

	/**
	 * @param string $slug Season slug.
	 * @return array|null
	 */
	public static function season( $slug ) {
		foreach ( self::seasons( false ) as $s ) {
			if ( $s['season_slug'] === $slug ) {
				return $s;
			}
		}
		return null;
	}

	/**
	 * Palette colors of one season.
	 *
	 * @param int $season_id Season ID.
	 * @return array<int,array>
	 */
	public static function palette( $season_id ) {
		$all = self::all_palettes();
		return $all[ (int) $season_id ] ?? array();
	}

	/** @return array<int,array> Colors grouped by season ID. */
	public static function all_palettes() {
		if ( isset( self::$cache['palettes'] ) ) {
			return self::$cache['palettes'];
		}
		global $wpdb;
		$t = SCA_Install::tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results( "SELECT * FROM {$t['colors']} ORDER BY season_id ASC, display_order ASC, id ASC", ARRAY_A );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r['season_id'] ][] = $r;
		}
		self::$cache['palettes'] = $out;
		return $out;
	}

	/**
	 * Questions with their answers.
	 *
	 * @param bool $active_only Only active questions and answers.
	 * @return array<int,array>
	 */
	public static function questions( $active_only = true ) {
		$key = 'questions_' . (int) $active_only;
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}
		global $wpdb;
		$t     = SCA_Install::tables();
		$where = $active_only ? 'WHERE is_active = 1' : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$questions = (array) $wpdb->get_results( "SELECT * FROM {$t['questions']} $where ORDER BY display_order ASC, id ASC", ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$answers = (array) $wpdb->get_results( "SELECT * FROM {$t['answers']} $where ORDER BY question_id ASC, display_order ASC, id ASC", ARRAY_A );
		$by_q    = array();
		foreach ( $answers as $a ) {
			$by_q[ (int) $a['question_id'] ][] = $a;
		}
		foreach ( $questions as &$q ) {
			$q['id']      = (int) $q['id'];
			$q['answers'] = $by_q[ $q['id'] ] ?? array();
		}
		self::$cache[ $key ] = $questions;
		return $questions;
	}

	/** @return array Plugin settings merged with defaults. */
	public static function settings() {
		$s = get_option( 'sca_settings', array() );
		$s = is_array( $s ) ? $s : array();
		return array_replace_recursive(
			array(
				'weights'             => array(
					'warmth'   => 1.0,
					'depth'    => 1.0,
					'chroma'   => 1.0,
					'contrast' => 0.7,
				),
				'thresholds'          => array(
					'margin'            => 0.08,
					'strong_distance'   => 0.45,
					'moderate_distance' => 0.8,
					'min_answered'      => 10,
				),
				'breadcrumbs'         => 1,
				'delete_on_uninstall' => 0,
			),
			$s
		);
	}

	/**
	 * Published season guide post for a season slug, if any.
	 *
	 * @param string $slug Season slug.
	 * @return WP_Post|null
	 */
	public static function season_post( $slug ) {
		$key = 'post_' . $slug;
		if ( array_key_exists( $key, self::$cache ) ) {
			return self::$cache[ $key ];
		}
		$posts               = get_posts(
			array(
				'post_type'   => 'color_season',
				'post_status' => 'publish',
				'numberposts' => 1,
				'meta_key'    => '_sca_season_slug', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'  => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		self::$cache[ $key ] = $posts ? $posts[0] : null;
		return self::$cache[ $key ];
	}

	/**
	 * URL of a season guide.
	 *
	 * @param string $slug Season slug.
	 * @return string
	 */
	public static function season_url( $slug ) {
		$post = self::season_post( $slug );
		return $post ? get_permalink( $post ) : home_url( '/' . $slug . '-color-palette/' );
	}

	/**
	 * Short summaries for the quiz result, from the season post meta.
	 *
	 * @param string $slug Season slug.
	 * @return array{clothing:string,makeup:string}
	 */
	public static function season_summaries( $slug ) {
		$post = self::season_post( $slug );
		return array(
			'clothing' => $post ? (string) get_post_meta( $post->ID, '_sca_clothing_summary', true ) : '',
			'makeup'   => $post ? (string) get_post_meta( $post->ID, '_sca_makeup_summary', true ) : '',
		);
	}

	/**
	 * Trend colors (admin-editable option, seeded from defaults.json).
	 *
	 * @param bool $active_only Only active trends.
	 * @return array<int,array{slug:string,name:string,hex:string,collection:string,source:string,note:string,active:int}>
	 */
	public static function trends( $active_only = true ) {
		$list = get_option( 'sca_trends', array() );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $t ) {
			$hex = sanitize_hex_color( $t['hex'] ?? '' );
			if ( ! $hex || empty( $t['name'] ) || ( $active_only && empty( $t['active'] ) ) ) {
				continue;
			}
			$out[] = array(
				'slug'       => sanitize_title( $t['name'] ),
				'name'       => (string) $t['name'],
				'hex'        => strtoupper( $hex ),
				'collection' => (string) ( $t['collection'] ?? '' ),
				'source'     => (string) ( $t['source'] ?? '' ),
				'note'       => (string) ( $t['note'] ?? '' ),
				'active'     => empty( $t['active'] ) ? 0 : 1,
			);
		}
		return $out;
	}

	/** Comma list → trimmed array. */
	public static function split( $value ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) );
	}

	/**
	 * Everything the browser needs: seasons with palettes, active questions
	 * with answer scores, weights and thresholds. Contains no visitor data.
	 *
	 * @param bool $with_questions Include the quiz questions.
	 * @return array
	 */
	public static function frontend_config( $with_questions = true ) {
		$settings = self::settings();
		$seasons  = array();
		foreach ( self::seasons() as $s ) {
			$colors = array();
			foreach ( self::palette( $s['id'] ) as $c ) {
				$colors[] = array(
					'name'     => $c['color_name'],
					'hex'      => $c['hex_code'],
					'category' => $c['color_category'],
				);
			}
			$seasons[] = array_merge(
				array(
					'slug'        => $s['season_slug'],
					'name'        => $s['season_name'],
					'family'      => $s['season_family'],
					'warmth'      => $s['warmth_target'],
					'depth'       => $s['depth_target'],
					'chroma'      => $s['chroma_target'],
					'contrast'    => $s['contrast_target'],
					'altNames'    => self::split( $s['alt_names'] ),
					'neighbors'   => self::split( $s['neighbors'] ),
					'description' => (string) $s['description'],
					'url'         => self::season_url( $s['season_slug'] ),
					'palette'     => $colors,
				),
				$with_questions ? self::season_summaries( $s['season_slug'] ) : array()
			);
		}

		$questions = array();
		if ( $with_questions ) {
			foreach ( self::questions() as $q ) {
				$answers = array();
				foreach ( $q['answers'] as $a ) {
					$scores = array();
					foreach ( self::DIMENSIONS as $d ) {
						if ( null !== $a[ $d . '_score' ] && '' !== $a[ $d . '_score' ] ) {
							$scores[ $d ] = (float) $a[ $d . '_score' ];
						}
					}
					$answers[] = array(
						'key'      => $a['answer_key'],
						'text'     => $a['answer_text'],
						'scores'   => $scores,
						'weight'   => (float) $a['answer_weight'],
						'swatches' => self::split( $a['swatches'] ),
					);
				}
				if ( $answers ) {
					$questions[] = array(
						'key'     => $q['question_key'],
						'text'    => $q['question_text'],
						'help'    => (string) $q['help_text'],
						'weight'  => (float) $q['weight'],
						'answers' => $answers,
					);
				}
			}
		}

		return array(
			'settings'  => array(
				'weights'    => array_map( 'floatval', $settings['weights'] ),
				'thresholds' => array_map( 'floatval', $settings['thresholds'] ),
			),
			'seasons'   => $seasons,
			'questions' => $questions,
			'quizUrl'    => self::page_url( 'seasonal-color-analysis-quiz' ),
			'compareUrl' => self::page_url( 'compare-seasons' ),
			'generatorUrl' => self::page_url( 'seasonal-color-palette-generator' ),
		);
	}

	/**
	 * URL of a page by slug, falling back to home_url.
	 *
	 * @param string $slug Page slug.
	 * @return string
	 */
	public static function page_url( $slug ) {
		$page = get_page_by_path( $slug );
		return $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
	}
}
