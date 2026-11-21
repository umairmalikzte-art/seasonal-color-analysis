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
		wp_register_style( 'sca', SCA_URL . 'assets/css/sca.css', array(), self::ver( 'assets/css/sca.css' ) );
		wp_register_script( 'sca-engine', SCA_URL . 'assets/js/sca-engine.js', array(), self::ver( 'assets/js/sca-engine.js' ), $defer );
		wp_register_script( 'sca-palette', SCA_URL . 'assets/js/sca-palette.js', array(), self::ver( 'assets/js/sca-palette.js' ), $defer );
		wp_register_script( 'sca-quiz', SCA_URL . 'assets/js/sca-quiz.js', array( 'sca-engine', 'sca-palette' ), self::ver( 'assets/js/sca-quiz.js' ), $defer );
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
		wp_enqueue_style( 'sca' );
		if ( has_shortcode( $content, 'sca_quiz' ) ) {
			self::enqueue_quiz();
		}
		foreach ( array( 'sca_palette', 'sca_palette_explorer', 'sca_compare' ) as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				self::enqueue_palette();
			}
		}
	}

	/** Palette tools: copy, copy all, PNG download, explorer tabs. */
	public static function enqueue_palette() {
		wp_enqueue_style( 'sca' );
		wp_enqueue_script( 'sca-palette' );
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
}
