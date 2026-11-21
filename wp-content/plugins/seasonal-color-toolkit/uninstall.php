<?php
/**
 * Removes plugin tables and settings, only if the admin opted in.
 *
 * @package SeasonalColorToolkit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$sca_settings = get_option( 'sca_settings', array() );
if ( empty( $sca_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'sca_questions', 'sca_answers', 'sca_seasons', 'sca_palette_colors' ) as $sca_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . $sca_table ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
}
delete_option( 'sca_settings' );
delete_option( 'sca_db_version' );
