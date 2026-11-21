<?php
/**
 * HTML building blocks shared by shortcodes and admin preview.
 *
 * @package SeasonalColorToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every method returns escaped HTML.
 */
class SCA_Render {

	/** Dimension → [label, low end, high end]. */
	public static function dimension_meta() {
		return array(
			'warmth'   => array( __( 'Temperature', 'seasonal-color-toolkit' ), __( 'Cool', 'seasonal-color-toolkit' ), __( 'Warm', 'seasonal-color-toolkit' ) ),
			'depth'    => array( __( 'Depth', 'seasonal-color-toolkit' ), __( 'Light', 'seasonal-color-toolkit' ), __( 'Deep', 'seasonal-color-toolkit' ) ),
			'chroma'   => array( __( 'Chroma', 'seasonal-color-toolkit' ), __( 'Soft', 'seasonal-color-toolkit' ), __( 'Bright', 'seasonal-color-toolkit' ) ),
			'contrast' => array( __( 'Contrast', 'seasonal-color-toolkit' ), __( 'Low', 'seasonal-color-toolkit' ), __( 'High', 'seasonal-color-toolkit' ) ),
		);
	}

	/**
	 * Plain-language label for a value between -1 and 1 (same bands as the JS engine).
	 *
	 * @param string $dim   Dimension.
	 * @param float  $value Value.
	 * @return string
	 */
	public static function label( $dim, $value ) {
		$labels = array(
			'warmth'   => array( 'Cool', 'Cool-neutral', 'Neutral', 'Warm-neutral', 'Warm' ),
			'depth'    => array( 'Light', 'Light to medium', 'Medium', 'Medium to deep', 'Deep' ),
			'chroma'   => array( 'Soft', 'Soft to medium', 'Medium', 'Clear', 'Bright' ),
			'contrast' => array( 'Low', 'Low to medium', 'Medium', 'Medium to high', 'High' ),
		);
		$v = (float) $value;
		if ( $v < -0.5 ) {
			$i = 0;
		} elseif ( $v < -0.15 ) {
			$i = 1;
		} elseif ( $v <= 0.15 ) {
			$i = 2;
		} elseif ( $v <= 0.5 ) {
			$i = 3;
		} else {
			$i = 4;
		}
		return $labels[ $dim ][ $i ] ?? '';
	}

	/** @return string Family display name. */
	public static function family_name( $family ) {
		$names = array(
			'spring' => __( 'Spring', 'seasonal-color-toolkit' ),
			'summer' => __( 'Summer', 'seasonal-color-toolkit' ),
			'autumn' => __( 'Autumn', 'seasonal-color-toolkit' ),
			'winter' => __( 'Winter', 'seasonal-color-toolkit' ),
		);
		return $names[ $family ] ?? ucfirst( (string) $family );
	}

	/**
	 * Readable text color for a swatch (WCAG luminance).
	 *
	 * @param string $hex Hex color.
	 * @return string
	 */
	public static function ink( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 6 !== strlen( $hex ) ) {
			return '#222222';
		}
		$c = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v   = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$c[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		$l = 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
		return $l > 0.36 ? '#222222' : '#ffffff';
	}

	/**
	 * Four meters for a season or a quiz result.
	 *
	 * @param array $values warmth/depth/chroma/contrast values.
	 * @return string
	 */
	public static function meters( array $values ) {
		$html = '<dl class="sca-meters">';
		foreach ( self::dimension_meta() as $dim => $meta ) {
			$v    = max( -1, min( 1, (float) ( $values[ $dim ] ?? 0 ) ) );
			$pos  = round( ( $v + 1 ) * 50, 1 );
			$html .= '<div class="sca-meter">';
			$html .= '<dt>' . esc_html( $meta[0] ) . '</dt>';
			$html .= '<dd><span class="sca-meter__value">' . esc_html( self::label( $dim, $v ) ) . '</span>';
			$html .= '<span class="sca-meter__track sca-meter__track--' . esc_attr( $dim ) . '" aria-hidden="true"><span class="sca-meter__dot" style="left:' . esc_attr( $pos ) . '%"></span></span>';
			$html .= '<span class="sca-meter__ends" aria-hidden="true"><span>' . esc_html( $meta[1] ) . '</span><span>' . esc_html( $meta[2] ) . '</span></span></dd>';
			$html .= '</div>';
		}
		return $html . '</dl>';
	}

	/** @return array{warmth:float,depth:float,chroma:float,contrast:float} */
	public static function season_values( array $season ) {
		return array(
			'warmth'   => $season['warmth_target'],
			'depth'    => $season['depth_target'],
			'chroma'   => $season['chroma_target'],
			'contrast' => $season['contrast_target'],
		);
	}

	/**
	 * Small decorative strip of colors.
	 *
	 * @param array $colors Palette rows.
	 * @param int   $max    Max colors.
	 * @return string
	 */
	public static function strip( array $colors, $max = 8 ) {
		$html = '<span class="sca-strip" aria-hidden="true">';
		$n    = 0;
		foreach ( $colors as $c ) {
			if ( 'avoid' === $c['color_category'] || 'neutral' === $c['color_category'] ) {
				continue;
			}
			$hex = sanitize_hex_color( $c['hex_code'] );
			if ( ! $hex ) {
				continue;
			}
			$html .= '<span style="background:' . esc_attr( $hex ) . '"></span>';
			if ( ++$n >= $max ) {
				break;
			}
		}
		return $html . '</span>';
	}

	/**
	 * Palette grid with copy buttons, grouped by category.
	 *
	 * @param array $season     Season row.
	 * @param array $categories Categories to show.
	 * @param bool  $tools      Show "copy all" and "download".
	 * @return string
	 */
	public static function palette( array $season, array $categories, $tools = true ) {
		$colors = SCA_Repository::palette( $season['id'] );
		$titles = array(
			'neutral' => __( 'Neutrals', 'seasonal-color-toolkit' ),
			'core'    => __( 'Core colors', 'seasonal-color-toolkit' ),
			'accent'  => __( 'Accents', 'seasonal-color-toolkit' ),
			'avoid'   => __( 'Colors to wear with care', 'seasonal-color-toolkit' ),
		);
		$json   = array();
		$groups = array();
		foreach ( $colors as $c ) {
			$hex = sanitize_hex_color( $c['hex_code'] );
			if ( ! $hex || ! in_array( $c['color_category'], $categories, true ) ) {
				continue;
			}
			$groups[ $c['color_category'] ][] = array( $c['color_name'], strtoupper( $hex ) );
			if ( 'avoid' !== $c['color_category'] ) {
				$json[] = array(
					'name'     => $c['color_name'],
					'hex'      => strtoupper( $hex ),
					'category' => $c['color_category'],
				);
			}
		}
		if ( ! $groups ) {
			return '';
		}

		$only_avoid = array( 'avoid' ) === array_keys( $groups );
		$html       = '<div class="sca-palette' . ( $only_avoid ? ' sca-palette--avoid' : '' ) . '" data-season="' . esc_attr( $season['season_slug'] ) . '" data-name="' . esc_attr( $season['season_name'] ) . '" data-palette="' . esc_attr( wp_json_encode( $json ) ) . '">';
		foreach ( array( 'neutral', 'core', 'accent', 'avoid' ) as $cat ) {
			if ( empty( $groups[ $cat ] ) ) {
				continue;
			}
			if ( count( $groups ) > 1 ) {
				$html .= '<p class="sca-palette__group">' . esc_html( $titles[ $cat ] ) . '</p>';
			}
			$html .= '<ul class="sca-swatches" role="list">';
			foreach ( $groups[ $cat ] as $c ) {
				list( $name, $hex ) = $c;
				$html              .= '<li class="sca-swatch">';
				$html              .= '<button type="button" class="sca-swatch__chip" data-hex="' . esc_attr( $hex ) . '" style="background:' . esc_attr( $hex ) . ';color:' . esc_attr( self::ink( $hex ) ) . '">';
				/* translators: 1: color name, 2: hex code */
				$html .= '<span class="screen-reader-text">' . esc_html( sprintf( __( 'Copy %1$s, %2$s', 'seasonal-color-toolkit' ), $name, $hex ) ) . '</span>';
				$html .= '<span class="sca-swatch__copied" aria-hidden="true">' . esc_html__( 'Copied', 'seasonal-color-toolkit' ) . '</span></button>';
				$html .= '<span class="sca-swatch__name">' . esc_html( $name ) . '</span>';
				$html .= '<code class="sca-swatch__hex">' . esc_html( $hex ) . '</code></li>';
			}
			$html .= '</ul>';
		}
		if ( $tools && $json ) {
			$html .= '<div class="sca-palette__tools">';
			$html .= '<button type="button" class="sca-btn sca-btn--ghost" data-sca-copy-all>' . esc_html__( 'Copy all hex codes', 'seasonal-color-toolkit' ) . '</button>';
			$html .= '<button type="button" class="sca-btn sca-btn--ghost" data-sca-download>' . esc_html__( 'Download palette (PNG)', 'seasonal-color-toolkit' ) . '</button>';
			$html .= '<span class="sca-palette__status" role="status" aria-live="polite"></span>';
			$html .= '</div>';
		}
		return $html . '</div>';
	}

	/**
	 * Card linking to a season guide.
	 *
	 * @param array  $season Season row.
	 * @param string $tag    Heading tag.
	 * @return string
	 */
	public static function season_card( array $season, $tag = 'h3' ) {
		$tag  = in_array( $tag, array( 'h2', 'h3', 'h4' ), true ) ? $tag : 'h3';
		$url  = SCA_Repository::season_url( $season['season_slug'] );
		$html = '<article class="sca-card sca-card--season sca-family--' . esc_attr( $season['season_family'] ) . '">';
		$html .= self::strip( SCA_Repository::palette( $season['id'] ), 8 );
		$html .= '<' . $tag . ' class="sca-card__title"><a href="' . esc_url( $url ) . '">' . esc_html( $season['season_name'] ) . '</a></' . $tag . '>';
		$html .= '<p class="sca-card__meta">' . esc_html( self::label( 'warmth', $season['warmth_target'] ) . ' · ' . self::label( 'depth', $season['depth_target'] ) . ' · ' . self::label( 'chroma', $season['chroma_target'] ) ) . '</p>';
		$html .= '</article>';
		return $html;
	}
}
