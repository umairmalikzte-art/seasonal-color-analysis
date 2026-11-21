<?php
/**
 * Scripts and styles. Quiz and palette scripts load only on pages that use them.
 *
 * @package SeasonalColorToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Asset loading.
 */
class SCA_Assets {

	/** @var bool Config already printed. */
	private static $config_done = false;

	/** Hooks. */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_for_content' ), 20 );
	}

	/** @var bool Inline CSS already added. */
	private static $css_done = false;

	/** @return string Minified file when it exists and SCRIPT_DEBUG is off. */
	private static function file( $rel ) {
		$min = preg_replace( '/\.js$/', '.min.js', $rel );
		return ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) && file_exists( SCA_DIR . $min ) ) ? $min : $rel;
	}

	/** Adds the component CSS inline, minified (no render-blocking request). */
	public static function style() {
		wp_enqueue_style( 'sca' );
		if ( self::$css_done ) {
			return;
		}
		self::$css_done = true;
		$css = (string) file_get_contents( SCA_DIR . 'assets/css/sca.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = (string) preg_replace( '/\s+/', ' ', $css );
		$css = str_replace( array( ' {', '{ ', '; ', ' }', ': ', ', ' ), array( '{', '{', ';', '}', ':', ',' ), $css );
		wp_add_inline_style( 'sca', trim( $css ) );
	}

	/** @return string Version string that changes with the file. */
	private static function ver( $rel ) {
		$file = SCA_DIR . $rel;
		return file_exists( $file ) ? SCA_VERSION . '.' . filemtime( $file ) : SCA_VERSION;
	}

	/** Registers everything. */
	public static function register() {
		$defer = array(
			'strategy'  => 'defer',
			'in_footer' => true,
		);
		wp_register_style( 'sca', false, array(), SCA_VERSION );
		wp_register_script( 'sca-engine', SCA_URL . self::file( 'assets/js/sca-engine.js' ), array(), self::ver( 'assets/js/sca-engine.js' ), $defer );
		wp_register_script( 'sca-palette', SCA_URL . self::file( 'assets/js/sca-palette.js' ), array(), self::ver( 'assets/js/sca-palette.js' ), $defer );
		wp_register_script( 'sca-color', SCA_URL . self::file( 'assets/js/sca-color.js' ), array(), self::ver( 'assets/js/sca-color.js' ), $defer );
		wp_register_script( 'sca-generator', SCA_URL . self::file( 'assets/js/sca-generator.js' ), array( 'sca-color', 'sca-palette' ), self::ver( 'assets/js/sca-generator.js' ), $defer );
		wp_register_script( 'sca-photo-measure', SCA_URL . self::file( 'assets/js/sca-photo-measure.js' ), array(), self::ver( 'assets/js/sca-photo-measure.js' ), $defer );
		wp_register_script( 'sca-photo', SCA_URL . self::file( 'assets/js/sca-photo.js' ), array( 'sca-engine', 'sca-photo-measure' ), self::ver( 'assets/js/sca-photo.js' ), $defer );
		wp_register_script( 'sca-quiz', SCA_URL . self::file( 'assets/js/sca-quiz.js' ), array( 'sca-engine', 'sca-palette' ), self::ver( 'assets/js/sca-quiz.js' ), $defer );
	}

	/**
	 * The stylesheet loads in the head on pages whose content uses a
	 * shortcode, so there is no layout shift. Scripts follow the same rule.
	 */
	public static function enqueue_for_content() {
		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}
		$content = (string) $post->post_content;
		if ( false === strpos( $content, '[sca_' ) ) {
			return;
		}
		self::style();
		if ( has_shortcode( $content, 'sca_quiz' ) ) {
			self::enqueue_quiz();
		}
		if ( has_shortcode( $content, 'sca_generator' ) ) {
			self::enqueue_generator();
		}
		if ( has_shortcode( $content, 'sca_photo' ) ) {
			self::enqueue_photo();
		}
		foreach ( array( 'sca_palette', 'sca_palette_explorer', 'sca_compare' ) as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				self::enqueue_palette();
			}
		}
	}

	/** Palette tools: copy, copy all, PNG download, explorer tabs. */
	public static function enqueue_palette() {
		self::style();
		wp_enqueue_script( 'sca-palette' );
	}

	/** @var bool Generator config already printed. */
	private static $gen_done = false;

	/** Palette generator and color checker, with seasons and trends. */
	public static function enqueue_generator() {
		self::enqueue_palette();
		wp_enqueue_script( 'sca-generator' );
		if ( ! self::$gen_done ) {
			self::$gen_done = true;
			$config = SCA_Repository::frontend_config( false );
			$data   = array(
				'seasons'       => $config['seasons'],
				'trends'        => SCA_Repository::trends(),
				'defaultSeason' => 'soft-autumn',
			);
			wp_add_inline_script( 'sca-generator', 'window.SCA_GEN = ' . wp_json_encode( $data ) . ';', 'before' );
		}
	}

	/** Quiz app plus its configuration (questions, scores, seasons). */
	public static function enqueue_quiz() {
		self::enqueue_palette();
		wp_enqueue_script( 'sca-quiz' );
		if ( ! self::$config_done ) {
			self::$config_done = true;
			wp_add_inline_script( 'sca-quiz', 'window.SCA_CONFIG = ' . wp_json_encode( SCA_Repository::frontend_config( true ) ) . ';', 'before' );
		}
	}

	/** @var bool Photo tool config and CSS already added. */
	private static $photo_done = false;

	/** AI color analysis: measurement, engine, UI, its CSS and the season data. */
	public static function enqueue_photo() {
		self::style();
		wp_enqueue_script( 'sca-photo' );
		if ( self::$photo_done ) {
			return;
		}
		self::$photo_done = true;
		$css = (string) file_get_contents( SCA_DIR . 'assets/css/sca-photo.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = (string) preg_replace( '/\s+/', ' ', $css );
		wp_add_inline_style( 'sca', trim( $css ) );
		$config = SCA_Repository::frontend_config( false );
		$guide  = static function ( $slug ) {
			$found = get_posts( array( 'name' => $slug, 'post_type' => 'color_guide', 'post_status' => 'publish', 'numberposts' => 1 ) );
			return $found ? get_permalink( $found[0] ) : '';
		};
		$data = array(
			'seasons'    => $config['seasons'],
			'settings'   => $config['settings'],
			'quizUrl'    => $config['quizUrl'],
			'compareUrl' => $config['compareUrl'],
			'site'       => wp_parse_url( home_url(), PHP_URL_HOST ),
			'guides'     => array(
				'skin'      => $guide( 'skin-tone-chart' ),
				'undertone' => $guide( 'warm-vs-cool-undertone' ),
			),
		);
		wp_add_inline_script( 'sca-photo', 'window.SCA_PHOTO = ' . wp_json_encode( $data ) . ';', 'before' );
	}
}
