<?php
/**
 * Hunza Folk theme bootstrap.
 *
 * @package hunza-folk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HF_VERSION', '1.0.0' );
define( 'HF_DIR', get_template_directory() );
define( 'HF_URI', get_template_directory_uri() );

require_once HF_DIR . '/inc/meta.php';
require_once HF_DIR . '/inc/post-types.php';
require_once HF_DIR . '/inc/blocks.php';
require_once HF_DIR . '/inc/markdown.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once HF_DIR . '/inc/importer.php';
}

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'custom-logo' );
		add_image_size( 'hf-card', 640, 480, true );
		add_image_size( 'hf-hero', 1920, 1080, true );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style( 'hunza-folk', get_stylesheet_uri(), array(), HF_VERSION );
		wp_enqueue_script( 'hunza-folk-player', HF_URI . '/assets/js/player.js', array(), HF_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		wp_enqueue_script( 'hunza-folk-site', HF_URI . '/assets/js/site.js', array( 'hunza-folk-player' ), HF_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
);

/**
 * Templates use root-relative links such as href="/music/". Prefix them with
 * the site's sub-directory when WordPress is not installed at the domain root.
 */
add_filter(
	'render_block',
	static function ( $content ) {
		$path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$path = untrailingslashit( $path );
		if ( '' === $path || false === strpos( $content, 'href="/' ) ) {
			return $content;
		}
		return preg_replace( '#href="/(?!/)#', 'href="' . esc_attr( $path ) . '/', $content );
	}
);

/**
 * The player bar lives in the footer so it exists on every page.
 */
add_action( 'wp_footer', 'hf_render_player_bar' );
