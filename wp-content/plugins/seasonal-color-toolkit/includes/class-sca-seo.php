<?php
/**
 * On-page and technical SEO: titles, meta, Open Graph, JSON-LD, robots,
 * sitemap, llms.txt and head clean-up.
 *
 * The PRD allows one SEO plugin (Rank Math or Yoast). When either is
 * active, the parts they own (titles, meta, Open Graph, schema, sitemap)
 * switch off here, so nothing is output twice.
 *
 * @package SeasonalColorToolkit
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO module.
 */
class SCA_SEO {

	const BRAND = 'Seasonal Color Analysis';

	/** Hooks. */
	public static function init() {
		// Always on: technical clean-up and robots rules.
		add_action( 'init', array( __CLASS__, 'cleanup_head' ) );
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_archives' ), 5 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'llms_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'llms_txt' ), 1 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'no_llms_redirect' ) );

		if ( self::seo_plugin_active() ) {
			return;
		}
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head_tags' ), 2 );
		add_action( 'wp_head', array( __CLASS__, 'schema' ), 30 );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'sitemap_providers' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_exclude' ), 10, 2 );
	}

	/** @return bool Yoast SEO or Rank Math is active. */
	public static function seo_plugin_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
	}

	/* ------------------------------------------------------------------ */
	/* Titles, descriptions, images                                         */
	/* ------------------------------------------------------------------ */

	/** Adds the brand only when the title stays at 60 characters or fewer. */
	private static function with_brand( $title ) {
		$branded = $title . ' | ' . self::BRAND;
		return ( function_exists( 'mb_strlen' ) ? mb_strlen( $branded ) : strlen( $branded ) ) <= 60 ? $branded : $title;
	}

	/** @return string The SEO title for the current request. */
	public static function current_title() {
		if ( is_front_page() ) {
			$custom = get_post_meta( (int) get_option( 'page_on_front' ), '_sca_seo_title', true );
			return $custom ? $custom : self::BRAND . ': Free Quiz & 12 Season Palettes';
		}
		if ( is_singular() ) {
			$custom = get_post_meta( get_queried_object_id(), '_sca_seo_title', true );
			return $custom ? $custom : self::with_brand( single_post_title( '', false ) );
		}
		if ( is_404() ) {
			return self::with_brand( 'Page Not Found' );
		}
		if ( is_search() ) {
			return self::with_brand( 'Search' );
		}
		return self::BRAND;
	}

	/** Filter for the document title. */
	public static function title( $title ) {
		return self::current_title();
	}

	/** @return string Meta description for the current request. */
	public static function current_description() {
		$post = is_front_page() ? get_post( (int) get_option( 'page_on_front' ) ) : ( is_singular() ? get_queried_object() : null );
		if ( $post instanceof WP_Post && '' !== trim( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}
		return 'Find your colors with a free seasonal color analysis quiz, 12 season palettes with hex codes, a palette generator and practical styling guides.';
	}

	/** @return array{url:string,width:int,height:int,alt:string} Social image. */
	public static function current_image() {
		if ( is_singular( 'color_season' ) ) {
			$slug = (string) get_post_meta( get_queried_object_id(), '_sca_season_slug', true );
			$file = 'assets/img/seasons/og-' . $slug . '.png';
			if ( $slug && file_exists( SCA_DIR . $file ) ) {
				$season = SCA_Repository::season( $slug );
				return array(
					'url'    => SCA_URL . $file,
					'width'  => 1200,
					'height' => 630,
					/* translators: %s: season */
					'alt'    => sprintf( __( '%s color palette swatches', 'seasonal-color-toolkit' ), $season ? $season['season_name'] : '' ),
				);
			}
		}
		return array(
			'url'    => SCA_URL . 'assets/img/og-default.png',
			'width'  => 1200,
			'height' => 630,
			'alt'    => __( 'Seasonal color analysis: the 12 season palettes', 'seasonal-color-toolkit' ),
		);
	}

	/** @return string Canonical URL of the current request (without query). */
	public static function canonical() {
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		if ( is_singular() ) {
			return (string) get_permalink( get_queried_object_id() );
		}
		return '';
	}

	/** Meta description, Open Graph and Twitter tags. Core prints the canonical. */
	public static function head_tags() {
		$title = self::current_title();
		$desc  = self::current_description();
		$img   = self::current_image();
		$url   = self::canonical();
		$type  = is_singular( array( 'color_season', 'color_guide', 'season_comparison' ) ) ? 'article' : 'website';

		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		$og = array(
			'og:type'         => $type,
			'og:site_name'    => self::BRAND,
			'og:locale'       => 'en_US',
			'og:title'        => $title,
			'og:description'  => $desc,
			'og:url'          => $url,
			'og:image'        => $img['url'],
			'og:image:width'  => (string) $img['width'],
			'og:image:height' => (string) $img['height'],
			'og:image:alt'    => $img['alt'],
		);
		foreach ( $og as $property => $content ) {
			if ( '' !== $content ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
		if ( 'article' === $type ) {
			echo '<meta property="article:modified_time" content="' . esc_attr( get_post_modified_time( 'c', true ) ) . '">' . "\n";
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		echo '<link rel="icon" href="' . esc_url( get_stylesheet_directory_uri() . '/assets/img/favicon.svg' ) . '" type="image/svg+xml">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( get_stylesheet_directory_uri() . '/assets/img/logo-512.png' ) . '">' . "\n";
	}

	/* ------------------------------------------------------------------ */
	/* Structured data                                                      */
	/* ------------------------------------------------------------------ */

	/** Verified English Wikipedia entities used in about/mentions. */
	public static function entities() {
		$w = 'https://en.wikipedia.org/wiki/';
		return array(
			'color_analysis' => array( 'Color analysis', $w . 'Color_analysis_(art)' ),
			'munsell'        => array( 'Munsell color system', $w . 'Munsell_color_system' ),
			'hue'            => array( 'Hue', $w . 'Hue' ),
			'lightness'      => array( 'Lightness', $w . 'Lightness' ),
			'chroma'         => array( 'Colorfulness', $w . 'Colorfulness' ),
			'contrast'       => array( 'Contrast (vision)', $w . 'Contrast_(vision)' ),
			'skin'           => array( 'Human skin color', $w . 'Human_skin_color' ),
			'color_theory'   => array( 'Color theory', $w . 'Color_theory' ),
			'color_scheme'   => array( 'Color scheme', $w . 'Color_scheme' ),
			'complementary'  => array( 'Complementary colors', $w . 'Complementary_colors' ),
			'analogous'      => array( 'Analogous colors', $w . 'Analogous_colors' ),
			'color_wheel'    => array( 'Color wheel', $w . 'Color_wheel' ),
			'oklab'          => array( 'Oklab color space', $w . 'Oklab_color_space' ),
			'pantone'        => array( 'Pantone', $w . 'Pantone' ),
			'wgsn'           => array( 'WGSN', $w . 'WGSN' ),
			'itten'          => array( 'Johannes Itten', $w . 'Johannes_Itten' ),
			'jackson'        => array( 'Carole Jackson', $w . 'Carole_Jackson' ),
		);
	}

	/** @return array schema.org Thing for an entity key. */
	private static function thing( $key ) {
		$e = self::entities()[ $key ];
		return array(
			'@type'  => 'Thing',
			'name'   => $e[0],
			'sameAs' => $e[1],
		);
	}

	/**
	 * FAQ pairs from the visible content: the H3/paragraph pairs under the
	 * "Frequently asked questions" H2. Schema always matches what users see.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	public static function faqs_from_content( $content ) {
		$pos = stripos( $content, 'Frequently asked questions</h2>' );
		if ( false === $pos ) {
			return array();
		}
		$section = substr( $content, $pos );
		$next    = stripos( $section, '<h2', 10 );
		if ( false !== $next ) {
			$section = substr( $section, 0, $next );
		}
		preg_match_all( '#<h3[^>]*>(.*?)</h3>\s*(?:<!--[^>]*-->\s*)*<p[^>]*>(.*?)</p>#s', $section, $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $pair ) {
			$q = trim( wp_strip_all_tags( $pair[1] ) );
			$a = trim( wp_strip_all_tags( $pair[2] ) );
			if ( '' !== $q && '' !== $a ) {
				$out[] = array( html_entity_decode( $q, ENT_QUOTES ), html_entity_decode( $a, ENT_QUOTES ) );
			}
		}
		return $out;
	}

	/** Breadcrumb trail for the current page: [[name, url], ...]. */
	public static function breadcrumb_trail() {
		if ( is_front_page() || ! is_singular() ) {
			return array();
		}
		$trail  = array( array( 'Home', home_url( '/' ) ) );
		$parent = array(
			'color_season'      => '12-color-seasons',
			'color_guide'       => 'color-guides',
			'season_comparison' => 'compare-seasons',
		);
		$type   = get_post_type();
		if ( isset( $parent[ $type ] ) ) {
			$page = get_page_by_path( $parent[ $type ] );
			if ( $page ) {
				$trail[] = array( get_the_title( $page ), get_permalink( $page ) );
			}
		}
		$trail[] = array( single_post_title( '', false ), get_permalink() );
		return $trail;
	}

	/** Builds the JSON-LD @graph for the current page. */
	public static function graph() {
		$home = home_url( '/' );
		$url  = self::canonical();
		if ( ! $url ) {
			return array();
		}
		$org_id  = $home . '#organization';
		$site_id = $home . '#website';
		$page_id = $url . '#webpage';
		$img     = self::current_image();
		$post    = is_front_page() ? get_post( (int) get_option( 'page_on_front' ) ) : get_queried_object();
		$content = $post instanceof WP_Post ? $post->post_content : '';
		$name    = is_front_page() ? self::BRAND : single_post_title( '', false );
		$slug    = $post instanceof WP_Post ? $post->post_name : '';

		$graph   = array();
		$graph[] = array(
			'@type' => 'Organization',
			'@id'   => $org_id,
			'name'  => self::BRAND,
			'url'   => $home,
			'logo'  => array(
				'@type'  => 'ImageObject',
				'url'    => get_stylesheet_directory_uri() . '/assets/img/logo-512.png',
				'width'  => 512,
				'height' => 512,
			),
		);
		$graph[] = array(
			'@type'      => 'WebSite',
			'@id'        => $site_id,
			'url'        => $home,
			'name'       => self::BRAND,
			'description' => 'Find Your Colors. Understand Your Style.',
			'publisher'  => array( '@id' => $org_id ),
			'inLanguage' => 'en-US',
		);

		$page_type = 'WebPage';
		if ( '12-color-seasons' === $slug || 'color-guides' === $slug || 'compare-seasons' === $slug ) {
			$page_type = 'CollectionPage';
		} elseif ( 'about' === $slug ) {
			$page_type = 'AboutPage';
		}
		$webpage = array(
			'@type'              => $page_type,
			'@id'                => $page_id,
			'url'                => $url,
			'name'               => self::current_title(),
			'description'        => self::current_description(),
			'isPartOf'           => array( '@id' => $site_id ),
			'inLanguage'         => 'en-US',
			'primaryImageOfPage' => array(
				'@type' => 'ImageObject',
				'url'   => $img['url'],
			),
		);
		if ( $post instanceof WP_Post ) {
			$webpage['datePublished'] = get_post_time( 'c', true, $post );
			$webpage['dateModified']  = get_post_modified_time( 'c', true, $post );
		}
		$trail = self::breadcrumb_trail();
		if ( $trail ) {
			$webpage['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
			$items                 = array();
			foreach ( $trail as $i => $item ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $item[0],
					'item'     => $item[1],
				);
			}
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $url . '#breadcrumb',
				'itemListElement' => $items,
			);
		}

		// Page-specific types.
		if ( is_front_page() ) {
			$webpage['about']    = self::thing( 'color_analysis' );
			$webpage['mentions'] = array( self::thing( 'munsell' ), self::thing( 'hue' ), self::thing( 'lightness' ), self::thing( 'chroma' ), self::thing( 'contrast' ), self::thing( 'skin' ) );
		} elseif ( 'seasonal-color-analysis-quiz' === $slug || 'seasonal-color-palette-generator' === $slug ) {
			$is_quiz = 'seasonal-color-analysis-quiz' === $slug;
			$graph[] = array(
				'@type'               => 'WebApplication',
				'@id'                 => $url . '#app',
				'name'                => $is_quiz ? 'Seasonal Color Analysis Quiz' : 'Seasonal Color Palette Generator',
				'url'                 => $url,
				'applicationCategory' => 'LifestyleApplication',
				'operatingSystem'     => 'Any (web browser)',
				'browserRequirements' => 'Requires JavaScript',
				'isAccessibleForFree' => true,
				'offers'              => array(
					'@type'         => 'Offer',
					'price'         => '0',
					'priceCurrency' => 'USD',
				),
				'featureList'         => $is_quiz
					? array( '15 questions, no photo or sign-up', 'Likely season with the closest alternative', 'Uncertain results are flagged', 'Palette with copyable hex codes and PNG download', 'Answers stay in the browser' )
					: array( 'Outfit palettes from 12 curated season palettes', 'Tonal, analogous, complementary, triadic and neutral + pop harmonies', 'Trend colors mapped to each season', 'Check any color against a season palette', 'Copy hex codes and download PNG' ),
				'publisher'           => array( '@id' => $org_id ),
				'about'               => self::thing( 'color_analysis' ),
			);
			$webpage['mainEntity'] = array( '@id' => $url . '#app' );
			if ( ! $is_quiz ) {
				$webpage['mentions'] = array( self::thing( 'color_scheme' ), self::thing( 'complementary' ), self::thing( 'analogous' ), self::thing( 'color_wheel' ), self::thing( 'oklab' ), self::thing( 'pantone' ), self::thing( 'wgsn' ) );
			}
		} elseif ( '12-color-seasons' === $slug ) {
			$list = array();
			foreach ( SCA_Repository::seasons() as $i => $s ) {
				$list[] = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $s['season_name'],
					'url'      => SCA_Repository::season_url( $s['season_slug'] ),
				);
			}
			$webpage['mainEntity'] = array(
				'@type'           => 'ItemList',
				'name'            => 'The 12 color seasons',
				'numberOfItems'   => count( $list ),
				'itemListElement' => $list,
			);
			$webpage['about']      = self::thing( 'color_analysis' );
			$webpage['mentions']   = array( self::thing( 'munsell' ), self::thing( 'itten' ), self::thing( 'jackson' ) );
		} elseif ( is_singular( array( 'color_season', 'color_guide', 'season_comparison' ) ) ) {
			$article = array(
				'@type'            => 'Article',
				'@id'              => $url . '#article',
				'headline'         => $name,
				'description'      => self::current_description(),
				'image'            => array(
					'@type'  => 'ImageObject',
					'url'    => $img['url'],
					'width'  => $img['width'],
					'height' => $img['height'],
				),
				'datePublished'    => get_post_time( 'c', true, $post ),
				'dateModified'     => get_post_modified_time( 'c', true, $post ),
				'author'           => array( '@id' => $org_id ),
				'publisher'        => array( '@id' => $org_id ),
				'mainEntityOfPage' => array( '@id' => $page_id ),
				'inLanguage'       => 'en-US',
			);
			$set = array(
				'@type' => 'DefinedTermSet',
				'@id'   => SCA_Repository::page_url( '12-color-seasons' ) . '#set',
				'name'  => 'The 12 color seasons',
				'url'   => SCA_Repository::page_url( '12-color-seasons' ),
			);
			if ( is_singular( 'color_season' ) ) {
				$season = SCA_Repository::season( (string) get_post_meta( $post->ID, '_sca_season_slug', true ) );
				if ( $season ) {
					$term = array(
						'@type'            => 'DefinedTerm',
						'name'             => $season['season_name'],
						'description'      => $season['description'],
						'inDefinedTermSet' => $set,
					);
					$alt  = SCA_Repository::split( $season['alt_names'] );
					if ( $alt ) {
						$term['alternateName'] = $alt;
					}
					$article['about']    = $term;
					$article['mentions'] = array( self::thing( 'color_analysis' ), self::thing( 'skin' ) );
				}
			} elseif ( is_singular( 'season_comparison' ) ) {
				$terms = array();
				foreach ( SCA_Repository::seasons() as $s ) {
					if ( false !== strpos( $slug, $s['season_slug'] ) ) {
						$terms[] = array(
							'@type'            => 'DefinedTerm',
							'name'             => $s['season_name'],
							'url'              => SCA_Repository::season_url( $s['season_slug'] ),
							'inDefinedTermSet' => $set,
						);
					}
				}
				$article['about'] = $terms;
			} else {
				$article['about']    = self::thing( 'color_analysis' );
				$article['mentions'] = 'warm-vs-cool-undertone' === $slug ? array( self::thing( 'skin' ), self::thing( 'hue' ) ) : array( self::thing( 'hue' ), self::thing( 'lightness' ), self::thing( 'chroma' ), self::thing( 'contrast' ) );
			}
			$graph[] = $article;
		}
		$graph[] = $webpage;

		$faqs = self::faqs_from_content( do_blocks( $content ) );
		if ( $faqs ) {
			$entities = array();
			foreach ( $faqs as $f ) {
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => $f[0],
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $f[1],
					),
				);
			}
			$graph[] = array(
				'@type'      => 'FAQPage',
				'@id'        => $url . '#faq',
				'url'        => $url,
				'isPartOf'   => array( '@id' => $site_id ),
				'mainEntity' => $entities,
			);
		}
		return $graph;
	}

	/** Prints the JSON-LD graph. */
	public static function schema() {
		if ( is_404() || is_search() ) {
			return;
		}
		$graph = self::graph();
		if ( ! $graph ) {
			return;
		}
		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . "</script>\n";
	}

	/* ------------------------------------------------------------------ */
	/* Technical                                                            */
	/* ------------------------------------------------------------------ */

	/** Removes head clutter that adds bytes and leaks versions. */
	public static function cleanup_head() {
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		add_filter( 'emoji_svg_url', '__return_false' );
	}

	/** noindex for thin or duplicate views. */
	public static function robots( $robots ) {
		if ( is_search() || is_404() || is_attachment() || is_author() || is_date() || is_paged() || is_feed() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/** Author and date archives have no unique content: send them home. */
	public static function redirect_archives() {
		if ( is_author() || is_date() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/** robots.txt additions. Core adds the sitemap line. */
	public static function robots_txt( $output, $public ) {
		if ( ! $public ) {
			return $output;
		}
		// Keep the rules inside the User-agent group, before the Sitemap line.
		$extra = "Disallow: /?s=\nDisallow: /search/";
		$pos   = strpos( $output, "\n\nSitemap:" );
		$pos   = false === $pos ? strpos( $output, "\nSitemap:" ) : $pos;
		return false === $pos ? $output . $extra . "\n" : substr( $output, 0, $pos ) . "\n" . $extra . substr( $output, $pos );
	}

	/** Only posts, pages and our post types in the sitemap. */
	public static function sitemap_providers( $provider, $name ) {
		return in_array( $name, array( 'users', 'taxonomies' ), true ) ? false : $provider;
	}

	/** The privacy policy has no search value. */
	public static function sitemap_exclude( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$privacy = (int) get_option( 'wp_page_for_privacy_policy' );
			if ( $privacy ) {
				$args['post__not_in'] = array_merge( $args['post__not_in'] ?? array(), array( $privacy ) );
			}
		}
		return $args;
	}

	/* ---------- llms.txt ---------- */

	public static function llms_rewrite() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?sca_llms=1', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'sca_llms';
		return $vars;
	}

	public static function no_llms_redirect( $redirect ) {
		return get_query_var( 'sca_llms' ) ? false : $redirect;
	}

	/** Markdown map of the site for AI assistants. */
	public static function llms_txt() {
		if ( ! get_query_var( 'sca_llms' ) ) {
			return;
		}
		$lines   = array( '# ' . self::BRAND, '', '> Free seasonal color analysis: a 15-question quiz, 12 season palettes with hex codes, a palette generator with trend colors, a season comparison tool and styling guides. Quiz answers stay in the browser.', '' );
		$lines[] = '## Tools';
		foreach ( array( 'seasonal-color-analysis-quiz', 'seasonal-color-palette-generator', 'compare-seasons' ) as $p ) {
			$page = get_page_by_path( $p );
			if ( $page ) {
				$lines[] = '- [' . get_the_title( $page ) . '](' . get_permalink( $page ) . '): ' . wp_strip_all_tags( $page->post_excerpt );
			}
		}
		$lines[] = '';
		$lines[] = '## The 12 seasons';
		foreach ( SCA_Repository::seasons() as $s ) {
			$alt     = SCA_Repository::split( $s['alt_names'] );
			$lines[] = '- [' . $s['season_name'] . '](' . SCA_Repository::season_url( $s['season_slug'] ) . ')' . ( $alt ? ' (also ' . implode( ', ', $alt ) . ')' : '' ) . ': ' . $s['description'];
		}
		$lines[] = '';
		$lines[] = '## Guides';
		foreach ( get_posts( array( 'post_type' => array( 'color_guide', 'season_comparison' ), 'numberposts' => 50, 'post_status' => 'publish' ) ) as $g ) {
			$lines[] = '- [' . get_the_title( $g ) . '](' . get_permalink( $g ) . '): ' . wp_strip_all_tags( $g->post_excerpt );
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo implode( "\n", $lines ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain built from stripped titles and excerpts.
		exit;
	}
}
