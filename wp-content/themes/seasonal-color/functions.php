<?php
/**
 * Seasonal Color child theme.
 * Design only. Quiz, palettes and data live in the Seasonal Color Toolkit plugin.
 *
 * @package SeasonalColor
 */

defined( 'ABSPATH' ) || exit;

/**
 * The child stylesheet is small, so it is printed inline after the parent
 * styles (priority 100, after GeneratePress adds its dynamic CSS). That
 * removes one render-blocking request.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_dequeue_style( 'generate-child' );
		$css = (string) file_get_contents( get_stylesheet_directory() . '/style.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = preg_replace( '/\s+/', ' ', $css );
		$handle = wp_style_is( 'generate-style', 'enqueued' ) ? 'generate-style' : 'wp-block-library';
		wp_add_inline_style( $handle, trim( $css ) );
	},
	100
);

/** The homepage has its own H1 in the hero, so the theme title is hidden there. */
add_filter(
	'generate_show_title',
	static function ( $show ) {
		return is_front_page() ? false : $show;
	}
);

/** Switch to the mobile menu below 1100px: the main menu has eight items. */
add_filter(
	'generate_mobile_menu_media_query',
	static function () {
		return '(max-width: 1100px)';
	}
);

/** No sidebar anywhere: the layout is editorial and tool-first. */
add_filter(
	'generate_sidebar_layout',
	static function () {
		return 'no-sidebar';
	}
);

/** Footer text and links. */
add_filter(
	'generate_copyright',
	static function () {
		$links = array(
			'12-color-seasons'             => __( '12 Color Seasons', 'seasonal-color' ),
			'seasonal-color-analysis-quiz' => __( 'Quiz', 'seasonal-color' ),
			'seasonal-color-palette-generator' => __( 'Palette Generator', 'seasonal-color' ),
			'color-guides'                 => __( 'Color Guides', 'seasonal-color' ),
			'about'                        => __( 'About', 'seasonal-color' ),
			'privacy-policy'               => __( 'Privacy Policy', 'seasonal-color' ),
		);
		$html  = '<ul class="sca-footer-links">';
		foreach ( $links as $slug => $label ) {
			$html .= '<li><a href="' . esc_url( home_url( '/' . $slug . '/' ) ) . '">' . esc_html( $label ) . '</a></li>';
		}
		$html .= '</ul>';
		/* translators: %s: year */
		$html .= '<span>' . esc_html( sprintf( __( '© %s Color Analysis. Palettes are a guide, not a professional color analysis.', 'seasonal-color' ), gmdate( 'Y' ) ) ) . '</span>';
		return $html;
	}
);

/** Load only the CSS of blocks that a page uses (smaller, faster first paint). */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/** Logged-out visitors never need dashicons. */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
		}
	},
	100
);

/** Helpful 404 page: links to the main tools instead of a dead end. */
add_filter(
	'generate_404_title',
	static function () {
		return __( 'This page could not be found', 'seasonal-color' );
	}
);
add_filter(
	'generate_404_text',
	static function () {
		$links = array(
			'seasonal-color-analysis-quiz'     => __( 'Take the free color analysis quiz', 'seasonal-color' ),
			'12-color-seasons'                 => __( 'See all 12 color seasons', 'seasonal-color' ),
			'seasonal-color-palette-generator' => __( 'Open the palette generator', 'seasonal-color' ),
		);
		$html  = esc_html__( 'The link may be old or mistyped. Try one of these instead:', 'seasonal-color' ) . '<ul>';
		foreach ( $links as $slug => $label ) {
			$html .= '<li><a href="' . esc_url( home_url( '/' . $slug . '/' ) ) . '">' . esc_html( $label ) . '</a></li>';
		}
		return $html . '</ul>';
	}
);

/** Theme supports. */
add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
	}
);
