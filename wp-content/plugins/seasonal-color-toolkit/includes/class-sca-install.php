<?php
/**
 * Database tables and default data.
 *
 * @package SeasonalColorToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin tables with dbDelta, then loads the
 * curated defaults from data/defaults.json when the tables are empty.
 * No table stores visitor answers.
 */
class SCA_Install {

	/**
	 * Table names with the site's own prefix (never a hardcoded wp_).
	 *
	 * @return array<string,string>
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'questions' => $wpdb->prefix . 'sca_questions',
			'answers'   => $wpdb->prefix . 'sca_answers',
			'seasons'   => $wpdb->prefix . 'sca_seasons',
			'colors'    => $wpdb->prefix . 'sca_palette_colors',
		);
	}

	/** Activation: tables, defaults, rewrite rules. */
	public static function activate() {
		self::create_tables();
		self::seed_defaults();
		SCA_Post_Types::register();
		flush_rewrite_rules();
	}

	/** Runs the schema upgrade when the stored DB version is older. */
	public static function maybe_upgrade() {
		if ( get_option( 'sca_db_version' ) !== SCA_DB_VERSION ) {
			self::create_tables();
			self::seed_defaults();
		}
	}

	/** Creates or updates the four tables. dbDelta needs this exact SQL layout. */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t       = self::tables();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$t['questions']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  question_key varchar(64) NOT NULL,
  question_text text NOT NULL,
  help_text text NULL,
  question_type varchar(20) NOT NULL DEFAULT 'choice',
  dimension varchar(20) NOT NULL DEFAULT '',
  weight decimal(5,2) NOT NULL DEFAULT 1.00,
  display_order int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY question_key (question_key)
) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['answers']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  question_id bigint(20) unsigned NOT NULL,
  answer_key varchar(64) NOT NULL,
  answer_text text NOT NULL,
  warmth_score decimal(4,2) NULL,
  depth_score decimal(4,2) NULL,
  chroma_score decimal(4,2) NULL,
  contrast_score decimal(4,2) NULL,
  answer_weight decimal(5,2) NOT NULL DEFAULT 1.00,
  swatches varchar(255) NOT NULL DEFAULT '',
  display_order int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  KEY question_id (question_id)
) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['seasons']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  season_slug varchar(64) NOT NULL,
  season_name varchar(100) NOT NULL,
  season_family varchar(20) NOT NULL,
  warmth_target decimal(4,2) NOT NULL DEFAULT 0.00,
  depth_target decimal(4,2) NOT NULL DEFAULT 0.00,
  chroma_target decimal(4,2) NOT NULL DEFAULT 0.00,
  contrast_target decimal(4,2) NOT NULL DEFAULT 0.00,
  alt_names varchar(255) NOT NULL DEFAULT '',
  neighbors varchar(255) NOT NULL DEFAULT '',
  description text NULL,
  display_order int(11) NOT NULL DEFAULT 0,
  is_active tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (id),
  UNIQUE KEY season_slug (season_slug)
) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$t['colors']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  season_id bigint(20) unsigned NOT NULL,
  color_name varchar(100) NOT NULL,
  hex_code char(7) NOT NULL,
  color_category varchar(20) NOT NULL DEFAULT 'core',
  display_order int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY season_id (season_id)
) $charset;"
		);

		update_option( 'sca_db_version', SCA_DB_VERSION );
	}

	/** @return array|null Decoded data/defaults.json. */
	public static function defaults() {
		$file = SCA_DIR . 'data/defaults.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $data ) ? $data : null;
	}

	/** Loads the defaults into empty tables. Never overwrites edited data. */
	public static function seed_defaults() {
		global $wpdb;
		$t    = self::tables();
		$data = self::defaults();
		if ( ! $data ) {
			return;
		}

		if ( false === get_option( 'sca_settings' ) ) {
			add_option(
				'sca_settings',
				array(
					'weights'             => $data['settings']['weights'],
					'thresholds'          => $data['settings']['thresholds'],
					'breadcrumbs'         => 1,
					'delete_on_uninstall' => 0,
				)
			);
		}

		if ( false === get_option( 'sca_trends' ) && ! empty( $data['trends'] ) ) {
			$trends = array();
			foreach ( $data['trends'] as $tr ) {
				$trends[] = array_merge( $tr, array( 'active' => 1 ) );
			}
			add_option( 'sca_trends', $trends, '', false );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['seasons']}" ) ) {
			foreach ( $data['seasons'] as $i => $s ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$t['seasons'],
					array(
						'season_slug'     => $s['slug'],
						'season_name'     => $s['name'],
						'season_family'   => $s['family'],
						'warmth_target'   => $s['warmth'],
						'depth_target'    => $s['depth'],
						'chroma_target'   => $s['chroma'],
						'contrast_target' => $s['contrast'],
						'alt_names'       => $s['alt_names'],
						'neighbors'       => $s['neighbors'],
						'description'     => $s['description'],
						'display_order'   => $i,
						'is_active'       => 1,
					),
					array( '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%d', '%d' )
				);
				$season_id = (int) $wpdb->insert_id;
				foreach ( $data['palettes'][ $s['slug'] ] ?? array() as $j => $c ) {
					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$t['colors'],
						array(
							'season_id'      => $season_id,
							'color_name'     => $c[0],
							'hex_code'       => strtoupper( $c[1] ),
							'color_category' => $c[2],
							'display_order'  => $j,
						),
						array( '%d', '%s', '%s', '%s', '%d' )
					);
				}
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['questions']}" ) ) {
			$now = current_time( 'mysql', true );
			foreach ( $data['questions'] as $i => $q ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$t['questions'],
					array(
						'question_key'  => $q['key'],
						'question_text' => $q['text'],
						'help_text'     => $q['help'],
						'question_type' => $q['type'],
						'dimension'     => $q['dimension'],
						'weight'        => $q['weight'],
						'display_order' => $i,
						'is_active'     => 1,
						'created_at'    => $now,
						'updated_at'    => $now,
					),
					array( '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s', '%s' )
				);
				$question_id = (int) $wpdb->insert_id;
				foreach ( $q['answers'] as $j => $a ) {
					list( $key, $text, $scores, $swatches ) = $a;
					$row = array(
						'question_id'   => $question_id,
						'answer_key'    => $key,
						'answer_text'   => $text,
						'answer_weight' => 1,
						'swatches'      => implode( ',', $swatches ),
						'display_order' => $j,
						'is_active'     => 1,
					);
					$fmt = array( '%d', '%s', '%s', '%f', '%s', '%d', '%d' );
					foreach ( array( 'warmth', 'depth', 'chroma', 'contrast' ) as $dim ) {
						if ( isset( $scores[ $dim ] ) ) {
							$row[ $dim . '_score' ] = $scores[ $dim ];
							$fmt[]                  = '%f';
						}
					}
					$wpdb->insert( $t['answers'], $row, $fmt ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}
			}
		}
		SCA_Repository::flush();
	}
}
