<?php
/**
 * Admin screens: dashboard, seasons, palettes, questions, answers, scoring,
 * comparisons and settings.
 *
 * @package SeasonalColorToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every form posts to admin-post.php with a nonce, and every handler checks
 * the manage_options capability. All writes use $wpdb->insert/update/delete
 * or prepared statements.
 */
class SCA_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'sca-dashboard';

	/** Hooks. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'season', 'palette', 'question', 'answers', 'scoring', 'settings', 'delete_question', 'starter', 'trends' ) as $action ) {
			add_action( 'admin_post_sca_save_' . $action, array( __CLASS__, 'save_' . $action ) );
		}
	}

	/** Menu and submenus. */
	public static function menu() {
		add_menu_page( __( 'Seasonal Color Toolkit', 'seasonal-color-toolkit' ), __( 'Color Toolkit', 'seasonal-color-toolkit' ), self::CAP, self::SLUG, array( __CLASS__, 'page_dashboard' ), 'dashicons-art', 26 );
		$pages = array(
			self::SLUG          => array( __( 'Dashboard', 'seasonal-color-toolkit' ), 'page_dashboard' ),
			'sca-seasons'       => array( __( 'Seasons', 'seasonal-color-toolkit' ), 'page_seasons' ),
			'sca-palettes'      => array( __( 'Palettes', 'seasonal-color-toolkit' ), 'page_palettes' ),
			'sca-questions'     => array( __( 'Quiz Questions', 'seasonal-color-toolkit' ), 'page_questions' ),
			'sca-answers'       => array( __( 'Quiz Answers', 'seasonal-color-toolkit' ), 'page_answers' ),
			'sca-scoring'       => array( __( 'Scoring Settings', 'seasonal-color-toolkit' ), 'page_scoring' ),
			'sca-trends'        => array( __( 'Trends', 'seasonal-color-toolkit' ), 'page_trends' ),
			'sca-comparisons'   => array( __( 'Comparisons', 'seasonal-color-toolkit' ), 'page_comparisons' ),
			'sca-settings'      => array( __( 'Plugin Settings', 'seasonal-color-toolkit' ), 'page_settings' ),
		);
		foreach ( $pages as $slug => $p ) {
			add_submenu_page( self::SLUG, $p[0], $p[0], self::CAP, $slug, array( __CLASS__, $p[1] ) );
		}
	}

	/** Admin CSS and, on the dashboard, the engine for the live preview. */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'sca-' ) ) {
			return;
		}
		wp_add_inline_style(
			'common',
			'.sca-admin .widefat input[type=text],.sca-admin .widefat input[type=number]{width:100%}.sca-admin .sca-swatch-preview{display:inline-block;width:22px;height:22px;border-radius:4px;border:1px solid #ccc;vertical-align:middle}.sca-admin .sca-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin:16px 0}.sca-admin .sca-cards div{background:#fff;border:1px solid #dcdcde;padding:12px 16px;border-radius:6px}.sca-admin .sca-cards strong{display:block;font-size:24px}.sca-admin .sca-preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}.sca-admin .sca-preview-out{background:#fff;border:1px solid #dcdcde;padding:12px 16px;margin-top:12px}'
		);
		if ( 'toplevel_page_' . self::SLUG === $hook ) {
			wp_enqueue_script( 'sca-engine', SCA_URL . 'assets/js/sca-engine.js', array(), SCA_VERSION, true );
			wp_add_inline_script( 'sca-engine', 'window.SCA_CONFIG = ' . wp_json_encode( SCA_Repository::frontend_config( true ) ) . ';' );
			wp_add_inline_script( 'sca-engine', self::preview_js() );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                              */
	/* ------------------------------------------------------------------ */

	/** Checks capability and nonce for a handler. */
	private static function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'seasonal-color-toolkit' ), 403 );
		}
		check_admin_referer( $action );
	}

	/** Redirect back with a notice. */
	private static function back( $page, $args = array() ) {
		SCA_Repository::flush();
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page, 'updated' => 1 ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Clamped float from POST, or null when the field is empty. */
	private static function score( $value, $min = -1, $max = 1 ) {
		$value = is_string( $value ) ? trim( $value ) : $value;
		if ( '' === $value || null === $value ) {
			return null;
		}
		return max( $min, min( $max, (float) $value ) );
	}

	/** @return array POST value as array, unslashed. */
	private static function post_array( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in guard(); each field is sanitized where used.
		return isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : array();
	}

	/** @return string Sanitized POST text. */
	private static function post_text( $key, $textarea = false ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in guard().
		$v = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return $textarea ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
	}

	/** Comma-separated hex list → sanitized. */
	private static function hex_list( $value ) {
		$out = array();
		foreach ( SCA_Repository::split( $value ) as $hex ) {
			$hex = sanitize_hex_color( '#' . ltrim( $hex, '#' ) );
			if ( $hex ) {
				$out[] = strtoupper( $hex );
			}
		}
		return implode( ',', $out );
	}

	/** Page header with notice. */
	private static function header( $title, $intro = '' ) {
		echo '<div class="wrap sca-admin"><h1>' . esc_html( $title ) . '</h1>';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'seasonal-color-toolkit' ) . '</p></div>';
		}
		if ( $intro ) {
			echo '<p>' . esc_html( $intro ) . '</p>';
		}
	}

	/** Hidden fields for a form. */
	private static function form_open( $action, $extra = array() ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( 'sca_save_' . $action ) . '">';
		wp_nonce_field( 'sca_save_' . $action );
		foreach ( $extra as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
	}

	/** @return int Request integer. */
	private static function get_int( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing
		return isset( $_REQUEST[ $key ] ) ? absint( $_REQUEST[ $key ] ) : 0;
	}

	/* ------------------------------------------------------------------ */
	/* Dashboard                                                            */
	/* ------------------------------------------------------------------ */

	/** Dashboard with counts, links and a live scoring preview. */
	public static function page_dashboard() {
		$seasons   = SCA_Repository::seasons( false );
		$questions = SCA_Repository::questions( false );
		$active_q  = count( array_filter( $questions, static function ( $q ) { return (int) $q['is_active']; } ) );
		$colors    = 0;
		foreach ( SCA_Repository::all_palettes() as $list ) {
			$colors += count( $list );
		}
		self::header( __( 'Seasonal Color Toolkit', 'seasonal-color-toolkit' ), __( 'Edit seasons, palettes, quiz questions and scoring without code. Quiz answers are scored in the visitor\'s browser and are never stored.', 'seasonal-color-toolkit' ) );

		echo '<div class="sca-cards">';
		printf( '<div><strong>%d</strong>%s</div>', count( $seasons ), esc_html__( 'seasons', 'seasonal-color-toolkit' ) );
		printf( '<div><strong>%d</strong>%s</div>', (int) $colors, esc_html__( 'palette colors', 'seasonal-color-toolkit' ) );
		printf( '<div><strong>%d / %d</strong>%s</div>', (int) $active_q, count( $questions ), esc_html__( 'active questions', 'seasonal-color-toolkit' ) );
		echo '</div>';

		echo '<p><a class="button button-primary" href="' . esc_url( SCA_Repository::page_url( 'seasonal-color-analysis-quiz' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the quiz', 'seasonal-color-toolkit' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( SCA_Repository::page_url( '12-color-seasons' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the 12 seasons page', 'seasonal-color-toolkit' ) . '</a></p>';

		echo '<h2>' . esc_html__( 'Scoring preview', 'seasonal-color-toolkit' ) . '</h2>';
		echo '<p>' . esc_html__( 'Choose answers to see the result the visitor would get, with the current questions, prototypes and weights. Use it to check the thresholds against your own test cases.', 'seasonal-color-toolkit' ) . '</p>';
		echo '<div id="sca-preview" class="sca-preview-grid"></div><div id="sca-preview-out" class="sca-preview-out"></div>';

		echo '<h2>' . esc_html__( 'Starter content', 'seasonal-color-toolkit' ) . '</h2>';
		echo '<p>' . esc_html__( 'Creates any missing season guides, guides and pages from the plugin\'s starter content. Existing content is never overwritten.', 'seasonal-color-toolkit' ) . '</p>';
		self::form_open( 'starter' );
		submit_button( __( 'Create missing starter content', 'seasonal-color-toolkit' ), 'secondary' );
		echo '</form></div>';
	}

	/** Inline JS for the dashboard preview (uses SCAEngine). */
	private static function preview_js() {
		return <<<'JS'
(function(){var c=window.SCA_CONFIG,E=window.SCAEngine,box=document.getElementById('sca-preview'),out=document.getElementById('sca-preview-out');if(!c||!E||!box)return;
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
c.questions.forEach(function(q){var l=document.createElement('label');l.innerHTML='<strong>'+esc(q.text)+'</strong><br>';var s=document.createElement('select');s.name=q.key;s.innerHTML='<option value="">—</option>'+q.answers.map(function(a){return '<option value="'+esc(a.key)+'">'+esc(a.text)+'</option>';}).join('');l.appendChild(s);box.appendChild(l);});
function run(){var a={};box.querySelectorAll('select').forEach(function(s){if(s.value)a[s.name]=s.value;});var r=E.score(c,a);
out.innerHTML='<p><strong>'+esc(r.primary.name)+'</strong> ('+r.strength+(r.uncertain?', uncertain':'')+(r.incomplete?', incomplete':'')+') · alternative: '+esc(r.alternative?r.alternative.name:'–')+' · answered '+r.answered+'/'+r.total+'</p><p>Warmth '+r.dims.warmth.toFixed(2)+' · Depth '+r.dims.depth.toFixed(2)+' · Chroma '+r.dims.chroma.toFixed(2)+' · Contrast '+r.dims.contrast.toFixed(2)+'</p><ol>'+r.ranking.slice(0,4).map(function(x){return '<li>'+esc(x.name)+' – distance '+x.distance.toFixed(3)+'</li>';}).join('')+'</ol>';}
box.addEventListener('change',run);run();})();
JS;
	}

	/** Creates missing starter content. */
	public static function save_starter() {
		self::guard( 'sca_save_starter' );
		SCA_Starter_Content::install();
		self::back( self::SLUG );
	}

	/* ------------------------------------------------------------------ */
	/* Seasons                                                              */
	/* ------------------------------------------------------------------ */

	/** Seasons list and edit form. */
	public static function page_seasons() {
		$id = self::get_int( 'id' );
		self::header( __( 'Seasons', 'seasonal-color-toolkit' ), __( 'Each season is a prototype in four dimensions between -1 and +1. The quiz picks the closest prototype.', 'seasonal-color-toolkit' ) );

		if ( $id ) {
			$season = null;
			foreach ( SCA_Repository::seasons( false ) as $s ) {
				if ( $s['id'] === $id ) {
					$season = $s;
				}
			}
			if ( $season ) {
				self::form_open( 'season', array( 'id' => $id ) );
				echo '<table class="form-table" role="presentation">';
				self::row_text( 'season_name', __( 'Name', 'seasonal-color-toolkit' ), $season['season_name'] );
				echo '<tr><th scope="row"><label for="season_family">' . esc_html__( 'Family', 'seasonal-color-toolkit' ) . '</label></th><td><select id="season_family" name="season_family">';
				foreach ( SCA_Repository::FAMILIES as $f ) {
					echo '<option value="' . esc_attr( $f ) . '"' . selected( $f, $season['season_family'], false ) . '>' . esc_html( ucfirst( $f ) ) . '</option>';
				}
				echo '</select></td></tr>';
				foreach ( SCA_Repository::DIMENSIONS as $d ) {
					/* translators: %s: dimension */
					self::row_number( $d . '_target', sprintf( __( '%s target (-1 to 1)', 'seasonal-color-toolkit' ), ucfirst( $d ) ), $season[ $d . '_target' ] );
				}
				self::row_text( 'alt_names', __( 'Alternative names (comma separated)', 'seasonal-color-toolkit' ), $season['alt_names'] );
				self::row_text( 'neighbors', __( 'Neighbour season slugs (comma separated)', 'seasonal-color-toolkit' ), $season['neighbors'] );
				echo '<tr><th scope="row"><label for="description">' . esc_html__( 'Short description', 'seasonal-color-toolkit' ) . '</label></th><td><textarea id="description" name="description" rows="3" class="large-text">' . esc_textarea( $season['description'] ) . '</textarea></td></tr>';
				self::row_number( 'display_order', __( 'Order', 'seasonal-color-toolkit' ), $season['display_order'], 1 );
				echo '<tr><th scope="row">' . esc_html__( 'Active', 'seasonal-color-toolkit' ) . '</th><td><label><input type="checkbox" name="is_active" value="1"' . checked( 1, (int) $season['is_active'], false ) . '> ' . esc_html__( 'Can be returned by the quiz and shown on the site', 'seasonal-color-toolkit' ) . '</label></td></tr>';
				echo '</table>';
				submit_button();
				echo '</form><p><a href="' . esc_url( admin_url( 'admin.php?page=sca-seasons' ) ) . '">&larr; ' . esc_html__( 'All seasons', 'seasonal-color-toolkit' ) . '</a></p></div>';
				return;
			}
		}

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Season', 'seasonal-color-toolkit' ) . '</th><th>' . esc_html__( 'Family', 'seasonal-color-toolkit' ) . '</th><th>Warmth</th><th>Depth</th><th>Chroma</th><th>Contrast</th><th>' . esc_html__( 'Active', 'seasonal-color-toolkit' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( SCA_Repository::seasons( false ) as $s ) {
			echo '<tr><td><strong>' . esc_html( $s['season_name'] ) . '</strong></td><td>' . esc_html( ucfirst( $s['season_family'] ) ) . '</td>';
			foreach ( SCA_Repository::DIMENSIONS as $d ) {
				echo '<td>' . esc_html( number_format( $s[ $d . '_target' ], 2 ) ) . '</td>';
			}
			echo '<td>' . ( (int) $s['is_active'] ? '✓' : '–' ) . '</td>';
			echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=sca-seasons&id=' . $s['id'] ) ) . '">' . esc_html__( 'Edit', 'seasonal-color-toolkit' ) . '</a> · <a href="' . esc_url( admin_url( 'admin.php?page=sca-palettes&season=' . $s['id'] ) ) . '">' . esc_html__( 'Palette', 'seasonal-color-toolkit' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/** Saves a season. */
	public static function save_season() {
		self::guard( 'sca_save_season' );
		global $wpdb;
		$id     = self::get_int( 'id' );
		$family = self::post_text( 'season_family' );
		$row    = array(
			'season_name'   => self::post_text( 'season_name' ),
			'season_family' => in_array( $family, SCA_Repository::FAMILIES, true ) ? $family : 'spring',
			'alt_names'     => self::post_text( 'alt_names' ),
			'neighbors'     => implode( ',', array_map( 'sanitize_key', SCA_Repository::split( self::post_text( 'neighbors' ) ) ) ),
			'description'   => self::post_text( 'description', true ),
			'display_order' => (int) self::post_text( 'display_order' ),
			'is_active'     => self::post_text( 'is_active' ) ? 1 : 0,
		);
		foreach ( SCA_Repository::DIMENSIONS as $d ) {
			$row[ $d . '_target' ] = (float) self::score( self::post_text( $d . '_target' ) );
		}
		$wpdb->update( SCA_Install::tables()['seasons'], $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::back( 'sca-seasons', array( 'id' => $id ) );
	}

	/* ------------------------------------------------------------------ */
	/* Palettes                                                             */
	/* ------------------------------------------------------------------ */

	/** Palette editor for one season. */
	public static function page_palettes() {
		$seasons = SCA_Repository::seasons( false );
		$id      = self::get_int( 'season' );
		$id      = $id ? $id : ( $seasons ? $seasons[0]['id'] : 0 );
		self::header( __( 'Palettes', 'seasonal-color-toolkit' ), __( 'Curated colors for each season. Neutrals, core colors and accents appear in the palette; "avoid" colors appear under "Colors to wear with care". Aim for 20–30 wearable colors.', 'seasonal-color-toolkit' ) );

		echo '<form method="get"><input type="hidden" name="page" value="sca-palettes"><label>' . esc_html__( 'Season', 'seasonal-color-toolkit' ) . ' <select name="season" onchange="this.form.submit()">';
		foreach ( $seasons as $s ) {
			echo '<option value="' . esc_attr( $s['id'] ) . '"' . selected( $s['id'], $id, false ) . '>' . esc_html( $s['season_name'] ) . '</option>';
		}
		echo '</select></label> <noscript><button class="button">' . esc_html__( 'Show', 'seasonal-color-toolkit' ) . '</button></noscript></form><br>';

		self::form_open( 'palette', array( 'season_id' => $id ) );
		echo '<table class="widefat striped"><thead><tr><th style="width:40px"></th><th>' . esc_html__( 'Name', 'seasonal-color-toolkit' ) . '</th><th style="width:120px">HEX</th><th style="width:140px">' . esc_html__( 'Category', 'seasonal-color-toolkit' ) . '</th><th style="width:80px">' . esc_html__( 'Order', 'seasonal-color-toolkit' ) . '</th><th style="width:70px">' . esc_html__( 'Delete', 'seasonal-color-toolkit' ) . '</th></tr></thead><tbody>';
		$rows = SCA_Repository::palette( $id );
		for ( $i = 0; $i < 4; $i++ ) {
			$rows[] = array(
				'id'             => 0,
				'color_name'     => '',
				'hex_code'       => '',
				'color_category' => 'core',
				'display_order'  => count( $rows ),
			);
		}
		foreach ( $rows as $i => $c ) {
			$n = 'colors[' . $i . ']';
			echo '<tr><td><span class="sca-swatch-preview" style="background:' . esc_attr( (string) sanitize_hex_color( $c['hex_code'] ) ) . '"></span><input type="hidden" name="' . esc_attr( $n ) . '[id]" value="' . esc_attr( $c['id'] ) . '"></td>';
			echo '<td><input type="text" name="' . esc_attr( $n ) . '[name]" value="' . esc_attr( $c['color_name'] ) . '" aria-label="' . esc_attr__( 'Color name', 'seasonal-color-toolkit' ) . '"></td>';
			echo '<td><input type="text" name="' . esc_attr( $n ) . '[hex]" value="' . esc_attr( $c['hex_code'] ) . '" pattern="#?[0-9A-Fa-f]{6}" placeholder="#A3AE8A" aria-label="HEX"></td><td><select name="' . esc_attr( $n ) . '[category]" aria-label="' . esc_attr__( 'Category', 'seasonal-color-toolkit' ) . '">';
			foreach ( SCA_Repository::CATEGORIES as $cat ) {
				echo '<option value="' . esc_attr( $cat ) . '"' . selected( $cat, $c['color_category'], false ) . '>' . esc_html( ucfirst( $cat ) ) . '</option>';
			}
			echo '</select></td><td><input type="number" name="' . esc_attr( $n ) . '[order]" value="' . esc_attr( $c['display_order'] ) . '" aria-label="' . esc_attr__( 'Order', 'seasonal-color-toolkit' ) . '"></td>';
			echo '<td>' . ( $c['id'] ? '<input type="checkbox" name="' . esc_attr( $n ) . '[delete]" value="1" aria-label="' . esc_attr__( 'Delete', 'seasonal-color-toolkit' ) . '">' : '' ) . '</td></tr>';
		}
		echo '</tbody></table><p class="description">' . esc_html__( 'Fill in an empty row to add a color. Rows with an invalid HEX code are skipped.', 'seasonal-color-toolkit' ) . '</p>';
		submit_button( __( 'Save palette', 'seasonal-color-toolkit' ) );
		echo '</form></div>';
	}

	/** Saves a palette. */
	public static function save_palette() {
		self::guard( 'sca_save_palette' );
		global $wpdb;
		$table     = SCA_Install::tables()['colors'];
		$season_id = self::get_int( 'season_id' );
		foreach ( self::post_array( 'colors' ) as $c ) {
			$id  = absint( $c['id'] ?? 0 );
			$hex = sanitize_hex_color( '#' . ltrim( (string) ( $c['hex'] ?? '' ), '#' ) );
			if ( $id && ! empty( $c['delete'] ) ) {
				$wpdb->delete( $table, array( 'id' => $id, 'season_id' => $season_id ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				continue;
			}
			$name = sanitize_text_field( (string) ( $c['name'] ?? '' ) );
			if ( ! $hex || '' === $name ) {
				continue;
			}
			$cat = in_array( $c['category'] ?? '', SCA_Repository::CATEGORIES, true ) ? $c['category'] : 'core';
			$row = array(
				'season_id'      => $season_id,
				'color_name'     => $name,
				'hex_code'       => strtoupper( $hex ),
				'color_category' => $cat,
				'display_order'  => (int) ( $c['order'] ?? 0 ),
			);
			if ( $id ) {
				$wpdb->update( $table, $row, array( 'id' => $id, 'season_id' => $season_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} else {
				$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		self::back( 'sca-palettes', array( 'season' => $season_id ) );
	}

	/* ------------------------------------------------------------------ */
	/* Questions                                                            */
	/* ------------------------------------------------------------------ */

	/** Questions list and edit form. */
	public static function page_questions() {
		$id = self::get_int( 'id' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$new = isset( $_GET['new'] );
		self::header( __( 'Quiz Questions', 'seasonal-color-toolkit' ), __( 'One question per screen, in this order. The weight sets how much a question counts (1 = normal). Every question should have a "Not sure" answer.', 'seasonal-color-toolkit' ) );

		$q = null;
		foreach ( SCA_Repository::questions( false ) as $row ) {
			if ( $row['id'] === $id ) {
				$q = $row;
			}
		}
		if ( $q || $new ) {
			$q = $q ? $q : array(
				'id'            => 0,
				'question_key'  => '',
				'question_text' => '',
				'help_text'     => '',
				'question_type' => 'choice',
				'dimension'     => 'warmth',
				'weight'        => 1,
				'display_order' => count( SCA_Repository::questions( false ) ),
				'is_active'     => 1,
			);
			self::form_open( 'question', array( 'id' => $q['id'] ) );
			echo '<table class="form-table" role="presentation">';
			self::row_text( 'question_key', __( 'Key (letters, numbers, underscores)', 'seasonal-color-toolkit' ), $q['question_key'] );
			self::row_text( 'question_text', __( 'Question', 'seasonal-color-toolkit' ), $q['question_text'] );
			echo '<tr><th scope="row"><label for="help_text">' . esc_html__( 'Help text', 'seasonal-color-toolkit' ) . '</label></th><td><textarea id="help_text" name="help_text" rows="2" class="large-text">' . esc_textarea( (string) $q['help_text'] ) . '</textarea></td></tr>';
			echo '<tr><th scope="row"><label for="dimension">' . esc_html__( 'Main dimension (for reference)', 'seasonal-color-toolkit' ) . '</label></th><td><select id="dimension" name="dimension">';
			foreach ( SCA_Repository::DIMENSIONS as $d ) {
				echo '<option value="' . esc_attr( $d ) . '"' . selected( $d, $q['dimension'], false ) . '>' . esc_html( ucfirst( $d ) ) . '</option>';
			}
			echo '</select></td></tr>';
			self::row_number( 'weight', __( 'Weight (0–5)', 'seasonal-color-toolkit' ), $q['weight'] );
			self::row_number( 'display_order', __( 'Order', 'seasonal-color-toolkit' ), $q['display_order'], 1 );
			echo '<tr><th scope="row">' . esc_html__( 'Active', 'seasonal-color-toolkit' ) . '</th><td><label><input type="checkbox" name="is_active" value="1"' . checked( 1, (int) $q['is_active'], false ) . '> ' . esc_html__( 'Shown in the quiz', 'seasonal-color-toolkit' ) . '</label></td></tr>';
			echo '</table>';
			submit_button();
			echo '</form>';
			if ( $q['id'] ) {
				echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=sca-answers&question=' . $q['id'] ) ) . '">' . esc_html__( 'Edit the answers and scores', 'seasonal-color-toolkit' ) . '</a></p>';
				self::form_open( 'delete_question', array( 'id' => $q['id'] ) );
				submit_button( __( 'Delete this question and its answers', 'seasonal-color-toolkit' ), 'delete', 'submit', false, array( 'onclick' => "return confirm('Delete this question?');" ) );
				echo '</form>';
			}
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=sca-questions' ) ) . '">&larr; ' . esc_html__( 'All questions', 'seasonal-color-toolkit' ) . '</a></p></div>';
			return;
		}

		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=sca-questions&new=1' ) ) . '">' . esc_html__( 'Add question', 'seasonal-color-toolkit' ) . '</a></p>';
		echo '<table class="widefat striped"><thead><tr><th>#</th><th>' . esc_html__( 'Question', 'seasonal-color-toolkit' ) . '</th><th>' . esc_html__( 'Dimension', 'seasonal-color-toolkit' ) . '</th><th>' . esc_html__( 'Weight', 'seasonal-color-toolkit' ) . '</th><th>' . esc_html__( 'Answers', 'seasonal-color-toolkit' ) . '</th><th>' . esc_html__( 'Active', 'seasonal-color-toolkit' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( SCA_Repository::questions( false ) as $row ) {
			echo '<tr><td>' . esc_html( $row['display_order'] ) . '</td><td><strong>' . esc_html( $row['question_text'] ) . '</strong><br><code>' . esc_html( $row['question_key'] ) . '</code></td><td>' . esc_html( $row['dimension'] ) . '</td><td>' . esc_html( $row['weight'] ) . '</td><td>' . count( $row['answers'] ) . '</td><td>' . ( (int) $row['is_active'] ? '✓' : '–' ) . '</td>';
			echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=sca-questions&id=' . $row['id'] ) ) . '">' . esc_html__( 'Edit', 'seasonal-color-toolkit' ) . '</a> · <a href="' . esc_url( admin_url( 'admin.php?page=sca-answers&question=' . $row['id'] ) ) . '">' . esc_html__( 'Answers', 'seasonal-color-toolkit' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/** Saves a question. */
	public static function save_question() {
		self::guard( 'sca_save_question' );
		global $wpdb;
		$table = SCA_Install::tables()['questions'];
		$id    = self::get_int( 'id' );
		$dim   = self::post_text( 'dimension' );
		$key   = preg_replace( '/[^a-z0-9_]/', '', strtolower( self::post_text( 'question_key' ) ) );
		$text  = self::post_text( 'question_text' );
		if ( '' === $key || '' === $text ) {
			wp_die( esc_html__( 'The key and the question are required.', 'seasonal-color-toolkit' ), 400, array( 'back_link' => true ) );
		}
		$row = array(
			'question_key'  => $key,
			'question_text' => $text,
			'help_text'     => self::post_text( 'help_text', true ),
			'question_type' => 'choice',
			'dimension'     => in_array( $dim, SCA_Repository::DIMENSIONS, true ) ? $dim : 'warmth',
			'weight'        => (float) self::score( self::post_text( 'weight' ), 0, 5 ),
			'display_order' => (int) self::post_text( 'display_order' ),
			'is_active'     => self::post_text( 'is_active' ) ? 1 : 0,
			'updated_at'    => current_time( 'mysql', true ),
		);
		if ( $id ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$row['created_at'] = $row['updated_at'];
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$id = (int) $wpdb->insert_id;
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				SCA_Install::tables()['answers'],
				array(
					'question_id'   => $id,
					'answer_key'    => 'unsure',
					'answer_text'   => 'Not sure',
					'answer_weight' => 1,
					'display_order' => 99,
					'is_active'     => 1,
				)
			);
		}
		self::back( 'sca-questions', array( 'id' => $id ) );
	}

	/** Deletes a question and its answers. */
	public static function save_delete_question() {
		self::guard( 'sca_save_delete_question' );
		global $wpdb;
		$id = self::get_int( 'id' );
		$t  = SCA_Install::tables();
		$wpdb->delete( $t['answers'], array( 'question_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $t['questions'], array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::back( 'sca-questions' );
	}

	/* ------------------------------------------------------------------ */
	/* Answers                                                              */
	/* ------------------------------------------------------------------ */

	/** Answers and scores of one question. */
	public static function page_answers() {
		$questions = SCA_Repository::questions( false );
		$qid       = self::get_int( 'question' );
		$qid       = $qid ? $qid : ( $questions ? $questions[0]['id'] : 0 );
		self::header( __( 'Quiz Answers', 'seasonal-color-toolkit' ), __( 'Scores run from -1 to +1. Leave a score empty when the answer says nothing about that dimension. Warmth: -1 cool, +1 warm. Depth: -1 light, +1 deep. Chroma: -1 soft, +1 bright. Contrast: -1 low, +1 high. Swatches are comma-separated HEX codes shown next to the answer.', 'seasonal-color-toolkit' ) );

		echo '<form method="get"><input type="hidden" name="page" value="sca-answers"><label>' . esc_html__( 'Question', 'seasonal-color-toolkit' ) . ' <select name="question" onchange="this.form.submit()">';
		$current = null;
		foreach ( $questions as $q ) {
			if ( $q['id'] === $qid ) {
				$current = $q;
			}
			echo '<option value="' . esc_attr( $q['id'] ) . '"' . selected( $q['id'], $qid, false ) . '>' . esc_html( $q['question_text'] ) . '</option>';
		}
		echo '</select></label> <noscript><button class="button">' . esc_html__( 'Show', 'seasonal-color-toolkit' ) . '</button></noscript></form><br>';
		if ( ! $current ) {
			echo '</div>';
			return;
		}

		self::form_open( 'answers', array( 'question_id' => $qid ) );
		echo '<table class="widefat striped"><thead><tr><th style="width:110px">' . esc_html__( 'Key', 'seasonal-color-toolkit' ) . '</th><th>' . esc_html__( 'Answer', 'seasonal-color-toolkit' ) . '</th><th style="width:80px">Warmth</th><th style="width:80px">Depth</th><th style="width:80px">Chroma</th><th style="width:80px">Contrast</th><th style="width:70px">' . esc_html__( 'Weight', 'seasonal-color-toolkit' ) . '</th><th style="width:170px">' . esc_html__( 'Swatches', 'seasonal-color-toolkit' ) . '</th><th style="width:60px">' . esc_html__( 'Order', 'seasonal-color-toolkit' ) . '</th><th style="width:55px">' . esc_html__( 'Active', 'seasonal-color-toolkit' ) . '</th><th style="width:55px">' . esc_html__( 'Delete', 'seasonal-color-toolkit' ) . '</th></tr></thead><tbody>';
		$rows = $current['answers'];
		for ( $i = 0; $i < 2; $i++ ) {
			$rows[] = array(
				'id'             => 0,
				'answer_key'     => '',
				'answer_text'    => '',
				'warmth_score'   => null,
				'depth_score'    => null,
				'chroma_score'   => null,
				'contrast_score' => null,
				'answer_weight'  => 1,
				'swatches'       => '',
				'display_order'  => count( $rows ),
				'is_active'      => 1,
			);
		}
		foreach ( $rows as $i => $a ) {
			$n = 'answers[' . $i . ']';
			echo '<tr><td><input type="hidden" name="' . esc_attr( $n ) . '[id]" value="' . esc_attr( $a['id'] ) . '"><input type="text" name="' . esc_attr( $n ) . '[key]" value="' . esc_attr( $a['answer_key'] ) . '" aria-label="' . esc_attr__( 'Key', 'seasonal-color-toolkit' ) . '"></td>';
			echo '<td><input type="text" name="' . esc_attr( $n ) . '[text]" value="' . esc_attr( $a['answer_text'] ) . '" aria-label="' . esc_attr__( 'Answer', 'seasonal-color-toolkit' ) . '"></td>';
			foreach ( SCA_Repository::DIMENSIONS as $d ) {
				$v = $a[ $d . '_score' ];
				echo '<td><input type="number" step="0.05" min="-1" max="1" name="' . esc_attr( $n . '[' . $d . ']' ) . '" value="' . esc_attr( null === $v ? '' : (float) $v ) . '" aria-label="' . esc_attr( ucfirst( $d ) ) . '"></td>';
			}
			echo '<td><input type="number" step="0.1" min="0" max="5" name="' . esc_attr( $n ) . '[weight]" value="' . esc_attr( (float) $a['answer_weight'] ) . '" aria-label="' . esc_attr__( 'Weight', 'seasonal-color-toolkit' ) . '"></td>';
			echo '<td><input type="text" name="' . esc_attr( $n ) . '[swatches]" value="' . esc_attr( $a['swatches'] ) . '" aria-label="' . esc_attr__( 'Swatches', 'seasonal-color-toolkit' ) . '"></td>';
			echo '<td><input type="number" name="' . esc_attr( $n ) . '[order]" value="' . esc_attr( $a['display_order'] ) . '" aria-label="' . esc_attr__( 'Order', 'seasonal-color-toolkit' ) . '"></td>';
			echo '<td><input type="checkbox" name="' . esc_attr( $n ) . '[active]" value="1"' . checked( 1, (int) $a['is_active'], false ) . ' aria-label="' . esc_attr__( 'Active', 'seasonal-color-toolkit' ) . '"></td>';
			echo '<td>' . ( $a['id'] ? '<input type="checkbox" name="' . esc_attr( $n ) . '[delete]" value="1" aria-label="' . esc_attr__( 'Delete', 'seasonal-color-toolkit' ) . '">' : '' ) . '</td></tr>';
		}
		echo '</tbody></table><p class="description">' . esc_html__( 'Fill in an empty row to add an answer.', 'seasonal-color-toolkit' ) . '</p>';
		submit_button( __( 'Save answers', 'seasonal-color-toolkit' ) );
		echo '</form></div>';
	}

	/** Saves the answers of a question. */
	public static function save_answers() {
		self::guard( 'sca_save_answers' );
		global $wpdb;
		$table = SCA_Install::tables()['answers'];
		$qid   = self::get_int( 'question_id' );
		foreach ( self::post_array( 'answers' ) as $a ) {
			$id = absint( $a['id'] ?? 0 );
			if ( $id && ! empty( $a['delete'] ) ) {
				$wpdb->delete( $table, array( 'id' => $id, 'question_id' => $qid ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				continue;
			}
			$key  = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $a['key'] ?? '' ) ) );
			$text = sanitize_text_field( (string) ( $a['text'] ?? '' ) );
			if ( '' === $key || '' === $text ) {
				continue;
			}
			$row = array(
				'question_id'   => $qid,
				'answer_key'    => $key,
				'answer_text'   => $text,
				'answer_weight' => (float) self::score( $a['weight'] ?? 1, 0, 5 ),
				'swatches'      => self::hex_list( $a['swatches'] ?? '' ),
				'display_order' => (int) ( $a['order'] ?? 0 ),
				'is_active'     => empty( $a['active'] ) ? 0 : 1,
			);
			foreach ( SCA_Repository::DIMENSIONS as $d ) {
				$row[ $d . '_score' ] = self::score( $a[ $d ] ?? '' );
			}
			if ( $id ) {
				$wpdb->update( $table, $row, array( 'id' => $id, 'question_id' => $qid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} else {
				$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		self::back( 'sca-answers', array( 'question' => $qid ) );
	}

	/* ------------------------------------------------------------------ */
	/* Scoring, comparisons, settings                                       */
	/* ------------------------------------------------------------------ */

	/** Dimension weights and thresholds. */
	public static function page_scoring() {
		$s = SCA_Repository::settings();
		self::header( __( 'Scoring Settings', 'seasonal-color-toolkit' ), __( 'Distance = √(Σ weight × difference²) over the four dimensions. Lower is closer. Check changes with the scoring preview on the dashboard and the test cases in the repository (npm test, npm run calibrate).', 'seasonal-color-toolkit' ) );
		self::form_open( 'scoring' );
		echo '<h2>' . esc_html__( 'Dimension weights', 'seasonal-color-toolkit' ) . '</h2><table class="form-table" role="presentation">';
		foreach ( SCA_Repository::DIMENSIONS as $d ) {
			self::row_number( 'weights[' . $d . ']', ucfirst( $d ), $s['weights'][ $d ] );
		}
		echo '</table><h2>' . esc_html__( 'Result thresholds', 'seasonal-color-toolkit' ) . '</h2><table class="form-table" role="presentation">';
		self::row_number( 'thresholds[margin]', __( 'Uncertain when the top two are closer than', 'seasonal-color-toolkit' ), $s['thresholds']['margin'] );
		self::row_number( 'thresholds[strong_distance]', __( 'Strong match: distance at most', 'seasonal-color-toolkit' ), $s['thresholds']['strong_distance'] );
		self::row_number( 'thresholds[moderate_distance]', __( 'Moderate match: distance at most', 'seasonal-color-toolkit' ), $s['thresholds']['moderate_distance'] );
		self::row_number( 'thresholds[min_answered]', __( 'Tentative when fewer clear answers than', 'seasonal-color-toolkit' ), $s['thresholds']['min_answered'], 1 );
		echo '</table>';
		submit_button();
		echo '</form></div>';
	}

	/** Saves weights and thresholds. */
	public static function save_scoring() {
		self::guard( 'sca_save_scoring' );
		$s = SCA_Repository::settings();
		$w = self::post_array( 'weights' );
		$t = self::post_array( 'thresholds' );
		foreach ( SCA_Repository::DIMENSIONS as $d ) {
			$s['weights'][ $d ] = (float) self::score( $w[ $d ] ?? 1, 0, 5 );
		}
		$s['thresholds']['margin']            = (float) self::score( $t['margin'] ?? 0.08, 0, 2 );
		$s['thresholds']['strong_distance']   = (float) self::score( $t['strong_distance'] ?? 0.45, 0, 5 );
		$s['thresholds']['moderate_distance'] = (float) self::score( $t['moderate_distance'] ?? 0.8, 0, 5 );
		$s['thresholds']['min_answered']      = (int) self::score( $t['min_answered'] ?? 10, 0, 100 );
		update_option( 'sca_settings', $s );
		self::back( 'sca-scoring' );
	}

	/** Trend colors used by the palette generator and the trend table. */
	public static function page_trends() {
		self::header( __( 'Trends', 'seasonal-color-toolkit' ), __( 'Trend colors for the palette generator. Add a source for every trend and update the list each season. Hex values are on-screen approximations unless the source publishes one.', 'seasonal-color-toolkit' ) );
		$rows = SCA_Repository::trends( false );
		for ( $i = 0; $i < 3; $i++ ) {
			$rows[] = array( 'name' => '', 'hex' => '', 'collection' => '', 'source' => '', 'note' => '', 'active' => 1 );
		}
		self::form_open( 'trends' );
		echo '<table class="widefat striped"><thead><tr><th style="width:36px"></th><th>' . esc_html__( 'Name', 'seasonal-color-toolkit' ) . '</th><th style="width:110px">HEX</th><th>' . esc_html__( 'Collection', 'seasonal-color-toolkit' ) . '</th><th>' . esc_html__( 'Source URL', 'seasonal-color-toolkit' ) . '</th><th>' . esc_html__( 'Note', 'seasonal-color-toolkit' ) . '</th><th style="width:55px">' . esc_html__( 'Active', 'seasonal-color-toolkit' ) . '</th><th style="width:55px">' . esc_html__( 'Delete', 'seasonal-color-toolkit' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $i => $t ) {
			$n = 'trends[' . $i . ']';
			echo '<tr><td><span class="sca-swatch-preview" style="background:' . esc_attr( (string) sanitize_hex_color( $t['hex'] ) ) . '"></span></td>';
			foreach ( array( 'name', 'hex', 'collection', 'source', 'note' ) as $f ) {
				echo '<td><input type="text" name="' . esc_attr( $n . '[' . $f . ']' ) . '" value="' . esc_attr( $t[ $f ] ) . '" aria-label="' . esc_attr( ucfirst( $f ) ) . '"></td>';
			}
			echo '<td><input type="checkbox" name="' . esc_attr( $n ) . '[active]" value="1"' . checked( 1, (int) $t['active'], false ) . ' aria-label="' . esc_attr__( 'Active', 'seasonal-color-toolkit' ) . '"></td>';
			echo '<td>' . ( '' !== $t['name'] ? '<input type="checkbox" name="' . esc_attr( $n ) . '[delete]" value="1" aria-label="' . esc_attr__( 'Delete', 'seasonal-color-toolkit' ) . '">' : '' ) . '</td></tr>';
		}
		echo '</tbody></table><p class="description">' . esc_html__( 'Fill in an empty row to add a trend. Rows without a name or a valid HEX code are skipped.', 'seasonal-color-toolkit' ) . '</p>';
		submit_button( __( 'Save trends', 'seasonal-color-toolkit' ) );
		echo '</form></div>';
	}

	/** Saves the trend list. */
	public static function save_trends() {
		self::guard( 'sca_save_trends' );
		$out = array();
		foreach ( self::post_array( 'trends' ) as $t ) {
			$name = sanitize_text_field( (string) ( $t['name'] ?? '' ) );
			$hex  = sanitize_hex_color( '#' . ltrim( (string) ( $t['hex'] ?? '' ), '#' ) );
			if ( ! empty( $t['delete'] ) || '' === $name || ! $hex ) {
				continue;
			}
			$out[] = array(
				'name'       => $name,
				'hex'        => strtoupper( $hex ),
				'collection' => sanitize_text_field( (string) ( $t['collection'] ?? '' ) ),
				'source'     => esc_url_raw( (string) ( $t['source'] ?? '' ) ),
				'note'       => sanitize_text_field( (string) ( $t['note'] ?? '' ) ),
				'active'     => empty( $t['active'] ) ? 0 : 1,
			);
		}
		update_option( 'sca_trends', $out, false );
		self::back( 'sca-trends' );
	}

	/** Comparisons are a post type; this page links to it and to the tool. */
	public static function page_comparisons() {
		self::header( __( 'Comparisons', 'seasonal-color-toolkit' ), __( 'Written comparisons are posts of the "Comparison" type. The comparison tool on the Compare Seasons page works for any two seasons and needs no setup.', 'seasonal-color-toolkit' ) );
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'post-new.php?post_type=season_comparison' ) ) . '">' . esc_html__( 'Add comparison', 'seasonal-color-toolkit' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=season_comparison' ) ) . '">' . esc_html__( 'All comparisons', 'seasonal-color-toolkit' ) . '</a> <a class="button" href="' . esc_url( SCA_Repository::page_url( 'compare-seasons' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the comparison tool', 'seasonal-color-toolkit' ) . '</a></p>';
		echo '<p>' . esc_html__( 'Use the shortcode [sca_compare a="soft-autumn" b="soft-summer" form="no"] inside a comparison to show both palettes and the dimension table.', 'seasonal-color-toolkit' ) . '</p></div>';
	}

	/** General settings. */
	public static function page_settings() {
		$s = SCA_Repository::settings();
		self::header( __( 'Plugin Settings', 'seasonal-color-toolkit' ) );
		self::form_open( 'settings' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'Breadcrumbs', 'seasonal-color-toolkit' ) . '</th><td><label><input type="checkbox" name="breadcrumbs" value="1"' . checked( 1, (int) $s['breadcrumbs'], false ) . '> ' . esc_html__( 'Show breadcrumbs above the content of inner pages (turn off if your SEO plugin or theme shows them).', 'seasonal-color-toolkit' ) . '</label></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Uninstall', 'seasonal-color-toolkit' ) . '</th><td><label><input type="checkbox" name="delete_on_uninstall" value="1"' . checked( 1, (int) $s['delete_on_uninstall'], false ) . '> ' . esc_html__( 'Delete the plugin tables and settings when the plugin is deleted.', 'seasonal-color-toolkit' ) . '</label></td></tr>';
		echo '</table>';
		submit_button();
		echo '</form><h2>' . esc_html__( 'Shortcodes', 'seasonal-color-toolkit' ) . '</h2><p><code>[sca_quiz]</code> <code>[sca_quiz_preview]</code> <code>[sca_season_families]</code> <code>[sca_popular_palettes seasons="soft-autumn,deep-winter"]</code> <code>[sca_season_profile season="soft-autumn"]</code> <code>[sca_palette season="soft-autumn" show="neutral,core,accent"]</code> <code>[sca_season_table]</code> <code>[sca_palette_explorer]</code> <code>[sca_compare a="soft-autumn" b="soft-summer"]</code> <code>[sca_related_seasons]</code> <code>[sca_content_list type="color_guide"]</code> <code>[sca_generator]</code> <code>[sca_trend_table]</code></p></div>';
	}

	/** Saves general settings. */
	public static function save_settings() {
		self::guard( 'sca_save_settings' );
		$s                        = SCA_Repository::settings();
		$s['breadcrumbs']         = self::post_text( 'breadcrumbs' ) ? 1 : 0;
		$s['delete_on_uninstall'] = self::post_text( 'delete_on_uninstall' ) ? 1 : 0;
		update_option( 'sca_settings', $s );
		self::back( 'sca-settings' );
	}

	/* ------------------------------------------------------------------ */
	/* Form rows                                                            */
	/* ------------------------------------------------------------------ */

	private static function row_text( $name, $label, $value ) {
		$id = sanitize_key( $name );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"></td></tr>';
	}

	private static function row_number( $name, $label, $value, $step = 0.01 ) {
		$id = sanitize_key( $name );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td><input type="number" step="' . esc_attr( $step ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"></td></tr>';
	}
}
