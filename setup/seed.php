<?php
/**
 * Local setup for WordPress Playground (run by blueprint.json).
 * Safe to run again: the plugin never overwrites existing content.
 *
 * @package SeasonalColorToolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_set_current_user( 1 );

// Remove the demo content (Hello world, Sample Page, the draft Privacy Policy),
// so the starter content can use those slugs.
foreach ( array( 1, 2, 3 ) as $demo_id ) {
	$demo = get_post( $demo_id );
	if ( $demo && in_array( $demo->post_name, array( "hello-world", "sample-page", "privacy-policy" ), true ) ) {
		wp_delete_post( $demo_id, true );
	}
}
wp_delete_comment( 1, true );

// Tables, default seasons, palettes and quiz (also done on activation).
SCA_Install::create_tables();
SCA_Install::seed_defaults();
SCA_Post_Types::register();

$front = SCA_Starter_Content::install();

// Primary menu: Home, AI Color Analysis, Take the Quiz, 12 Color Seasons, Skin Tone,
// Palette Generator, Compare Seasons, Color Guides, About.
$menu_name = 'Primary';
$menu      = wp_get_nav_menu_object( $menu_name );
if ( ! $menu ) {
	$menu_id = wp_create_nav_menu( $menu_name );
	$items   = array(
		array( "Home", home_url( "/" ), "" ),
		array( "AI Color Analysis", home_url( "/ai-color-analysis/" ), "" ),
		array( "Take the Quiz", home_url( "/seasonal-color-analysis-quiz/" ), "" ),
		array( "12 Color Seasons", home_url( "/12-color-seasons/" ), "" ),
		array( "Skin Tone", home_url( "/skin-tone-chart/" ), "" ),
		array( "Palette Generator", home_url( "/seasonal-color-palette-generator/" ), "" ),
		array( "Compare Seasons", home_url( "/compare-seasons/" ), "" ),
		array( "Color Guides", home_url( "/color-guides/" ), "" ),
		array( "About", home_url( "/about/" ), "" ),
	);
	foreach ( $items as $i => $item ) {
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'    => $item[0],
				'menu-item-url'      => $item[1],
				'menu-item-classes'  => $item[2],
				'menu-item-status'   => 'publish',
				'menu-item-position' => $i + 1,
			)
		);
	}
	$locations            = get_theme_mod( 'nav_menu_locations', array() );
	$locations['primary'] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}

update_option( 'blog_public', 1 );
update_option( 'default_comment_status', 'closed' );
flush_rewrite_rules();
