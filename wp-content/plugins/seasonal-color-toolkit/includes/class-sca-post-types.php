<?php
/**
 * Custom post types with root-level URLs.
 *
 * @package SeasonalColorToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers color_season, color_guide and season_comparison. The PRD asks
 * for URLs without a post type base (/soft-autumn-color-palette/), so the
 * permalinks are rewritten to the root and single-segment requests that do
 * not match a page are resolved against these post types.
 */
class SCA_Post_Types {

	const TYPES = array( 'color_season', 'color_guide', 'season_comparison' );

	/** Hooks. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'post_type_link', array( __CLASS__, 'permalink' ), 10, 2 );
		add_filter( 'request', array( __CLASS__, 'route' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_base' ) );
	}

	/** Registers the three post types. */
	public static function register() {
		$common = array(
			'public'       => true,
			'show_in_rest' => true,
			'has_archive'  => false,
			'rewrite'      => false,
			'query_var'    => true,
			'show_in_menu' => 'sca-dashboard',
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
		);

		register_post_type(
			'color_season',
			array_merge(
				$common,
				array(
					'labels' => array(
						'name'          => __( 'Season Guides', 'seasonal-color-toolkit' ),
						'singular_name' => __( 'Season Guide', 'seasonal-color-toolkit' ),
						'add_new_item'  => __( 'Add Season Guide', 'seasonal-color-toolkit' ),
						'edit_item'     => __( 'Edit Season Guide', 'seasonal-color-toolkit' ),
						'all_items'     => __( 'Season Guides', 'seasonal-color-toolkit' ),
					),
				)
			)
		);
		register_post_type(
			'color_guide',
			array_merge(
				$common,
				array(
					'labels' => array(
						'name'          => __( 'Color Guides', 'seasonal-color-toolkit' ),
						'singular_name' => __( 'Color Guide', 'seasonal-color-toolkit' ),
						'add_new_item'  => __( 'Add Color Guide', 'seasonal-color-toolkit' ),
						'edit_item'     => __( 'Edit Color Guide', 'seasonal-color-toolkit' ),
						'all_items'     => __( 'Color Guides', 'seasonal-color-toolkit' ),
					),
				)
			)
		);
		register_post_type(
			'season_comparison',
			array_merge(
				$common,
				array(
					'labels' => array(
						'name'          => __( 'Comparisons', 'seasonal-color-toolkit' ),
						'singular_name' => __( 'Comparison', 'seasonal-color-toolkit' ),
						'add_new_item'  => __( 'Add Comparison', 'seasonal-color-toolkit' ),
						'edit_item'     => __( 'Edit Comparison', 'seasonal-color-toolkit' ),
						'all_items'     => __( 'Comparisons', 'seasonal-color-toolkit' ),
					),
				)
			)
		);

		register_post_meta(
			'color_season',
			'_sca_season_slug',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_key',
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
		foreach ( array( '_sca_clothing_summary', '_sca_makeup_summary' ) as $meta ) {
			register_post_meta(
				'color_season',
				$meta,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_textarea_field',
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Root-level permalink for published posts of our types.
	 *
	 * @param string  $link Default link.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function permalink( $link, $post ) {
		if ( in_array( $post->post_type, self::TYPES, true ) && 'publish' === $post->post_status && $post->post_name ) {
			return home_url( user_trailingslashit( $post->post_name ) );
		}
		return $link;
	}

	/**
	 * Resolves /slug/ to one of our posts when no page or post has that slug.
	 *
	 * @param array $q Query vars.
	 * @return array
	 */
	public static function route( $q ) {
		if ( is_admin() || isset( $q['post_type'] ) || isset( $q['p'] ) || isset( $q['page_id'] ) ) {
			return $q;
		}
		$slug = '';
		if ( ! empty( $q['pagename'] ) ) {
			$slug = $q['pagename'];
		} elseif ( ! empty( $q['name'] ) ) {
			$slug = $q['name'];
		}
		if ( ! $slug || false !== strpos( $slug, '/' ) ) {
			return $q;
		}
		$slug = sanitize_title( $slug );
		if ( get_page_by_path( $slug ) ) {
			return $q;
		}
		$found = get_posts(
			array(
				'name'        => $slug,
				'post_type'   => self::TYPES,
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		if ( ! $found ) {
			return $q;
		}
		$type = get_post_type( $found[0] );
		$keep = array_intersect_key( $q, array_flip( array( 'preview', 'page', 'paged', 'cpage' ) ) );
		return array_merge(
			$keep,
			array(
				'post_type' => $type,
				'name'      => $slug,
				$type       => $slug,
			)
		);
	}

	/** Sends ?color_season=slug style URLs to the clean URL. */
	public static function redirect_base() {
		// /color-palettes/ was merged into the hub (duplicate intent, see docs/KEYWORD-RESEARCH.md).
		if ( is_404() ) {
			$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( 'color-palettes' === $path ) {
				wp_safe_redirect( home_url( '/12-color-seasons/#palettes' ), 301 );
				exit;
			}
		}
		if ( ! is_singular( self::TYPES ) || is_preview() ) {
			return;
		}
		$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( self::TYPES as $type ) {
			if ( false !== strpos( $request, $type . '=' ) ) {
				wp_safe_redirect( get_permalink(), 301 );
				exit;
			}
		}
	}
}
