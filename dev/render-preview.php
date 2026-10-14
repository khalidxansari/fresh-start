<?php
/**
 * Offline preview renderer.
 *
 * WordPress itself can't run in every environment, so this stubs the handful
 * of WordPress functions the theme's block renderers use, loads fixture data,
 * and writes static HTML pages to dev/preview/. The PHP renderers, templates,
 * stylesheet and scripts are the real theme files; only the database is fake.
 * It does NOT test queries or the WordPress editor.
 *
 * Run: php dev/render-preview.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HF_VERSION', 'dev' );
define( 'HF_URI', '../../wp-content/themes/hunza-folk' );
$theme = dirname( __DIR__ ) . '/wp-content/themes/hunza-folk';
$out   = __DIR__ . '/preview';
@mkdir( $out . '/img', 0777, true );

/* ---------- Fixture data ---------- */

$GLOBALS['hf_db'] = array(
	10 => array( 'type' => 'instrument', 'title' => 'Dadang', 'excerpt' => 'A large frame drum that opens celebrations.', 'thumb' => 'img/drum.svg', 'meta' => array( 'hf_instrument_type' => 'drum', 'hf_local_name' => 'Dadang' ) ),
	11 => array( 'type' => 'instrument', 'title' => 'Damal', 'excerpt' => 'Small kettle drums played as a pair.', 'meta' => array( 'hf_instrument_type' => 'drum' ) ),
	12 => array( 'type' => 'instrument', 'title' => 'Suranae', 'excerpt' => 'A double-reed wind instrument.', 'meta' => array() ),
	13 => array( 'type' => 'instrument', 'title' => 'Gabi', 'excerpt' => '', 'meta' => array() ),
	20 => array( 'type' => 'artist', 'title' => 'Sample Artist One', 'excerpt' => 'Placeholder profile.', 'thumb' => 'img/person.svg', 'meta' => array( 'hf_role' => 'Singer and drummer' ) ),
	21 => array( 'type' => 'artist', 'title' => 'Sample Artist Two', 'excerpt' => 'Placeholder profile.', 'meta' => array( 'hf_role' => 'Suranae player' ) ),
	22 => array( 'type' => 'artist', 'title' => 'Sample Artist Three', 'excerpt' => '', 'meta' => array() ),
	30 => array(
		'type' => 'song', 'title' => 'Sample Song One', 'thumb' => 'img/hero.svg',
		'excerpt' => '', 'meta' => array(
			'hf_title_ur' => 'نمونہ گانا', 'hf_language' => 'Burushaski', 'hf_location' => 'Placeholder village', 'hf_recording_year' => 2015,
			'hf_cultural_note' => "Placeholder cultural note.\n\nWhy the song exists.", 'hf_media_type' => 'file', 'hf_audio_file' => 'audio/tone-a.wav',
			'hf_performers' => array( 20 ), 'hf_instruments' => array( 10, 11 ), 'hf_featured' => 1,
		),
	),
	31 => array( 'type' => 'song', 'title' => 'Sample Song Two', 'excerpt' => '', 'meta' => array( 'hf_title_ur' => 'نمونہ ۲', 'hf_language' => 'Shina', 'hf_location' => 'Placeholder valley', 'hf_recording_year' => 2018, 'hf_media_type' => 'youtube', 'hf_youtube_id' => 'aaaaaaaaaaa', 'hf_performers' => array( 21, 22 ), 'hf_instruments' => array( 12 ) ) ),
	32 => array( 'type' => 'song', 'title' => 'Sample Song Three with a deliberately long title to test truncation in a row', 'excerpt' => '', 'meta' => array( 'hf_language' => 'Wakhi', 'hf_location' => 'Placeholder village', 'hf_recording_year' => 2012, 'hf_media_type' => 'file', 'hf_audio_file' => 'audio/tone-b.wav', 'hf_performers' => array( 22 ), 'hf_instruments' => array( 10 ) ) ),
	33 => array( 'type' => 'song', 'title' => 'Sample Song Four', 'excerpt' => '', 'meta' => array( 'hf_language' => 'Burushaski', 'hf_performers' => array( 20 ), 'hf_instruments' => array() ) ),
);
$GLOBALS['hf_current'] = 0;
$GLOBALS['hf_ctx']     = array( 'archive' => '', 'singular' => '', 'page' => '' );
$GLOBALS['hf_hooks']   = array();
$GLOBALS['hf_blocks']  = array();
$GLOBALS['wpdb']       = new class() {
	public $postmeta = 'postmeta';
	public $posts    = 'posts';
	public function prepare( $q ) {
		return $q;
	}
	public function get_col( $q ) {
		$langs = array();
		foreach ( $GLOBALS['hf_db'] as $p ) {
			if ( 'song' === $p['type'] && ! empty( $p['meta']['hf_language'] ) ) {
				$langs[] = $p['meta']['hf_language'];
			}
		}
		sort( $langs );
		return array_values( array_unique( $langs ) );
	}
};

/* ---------- WordPress function stubs ---------- */

function add_action( $hook, $cb ) {
	$GLOBALS['hf_hooks'][ $hook ][] = $cb;
}
function add_filter( $hook, $cb ) {}
function register_block_type( $name, $args ) {
	$GLOBALS['hf_blocks'][ $name ] = $args['render_callback'];
}
function get_template_directory() {
	return dirname( __DIR__ ) . '/wp-content/themes/hunza-folk';
}
function get_template_directory_uri() {
	return HF_URI;
}
function hf_post( $id ) {
	return $GLOBALS['hf_db'][ $id ] ?? null;
}
function get_post_type( $id ) {
	$p = hf_post( $id );
	return $p ? $p['type'] : false;
}
function get_the_title( $id ) {
	$p = is_object( $id ) ? hf_post( $id->ID ) : hf_post( $id );
	return $p ? $p['title'] : '';
}
function get_post_status( $id ) {
	return hf_post( $id ) ? 'publish' : false;
}
function get_the_ID() {
	return $GLOBALS['hf_current'];
}
function get_permalink( $id ) {
	$id = is_object( $id ) ? $id->ID : $id;
	return get_post_type( $id ) . '-' . $id . '.html';
}
function get_post_type_archive_link( $type ) {
	return array( 'song' => 'music.html', 'artist' => 'artists.html', 'instrument' => 'instruments.html', 'story' => 'folklore.html' )[ $type ];
}
function home_url( $path = '' ) {
	return 'index.html' . ( '/about/' === $path ? '#about' : ( '/support/' === $path ? '#support' : '' ) );
}
function get_the_post_thumbnail_url( $id ) {
	$p = hf_post( $id );
	return $p && ! empty( $p['thumb'] ) ? $p['thumb'] : false;
}
function get_post_meta( $id, $key, $single = false ) {
	$p = hf_post( $id );
	$v = $p && isset( $p['meta'][ $key ] ) ? $p['meta'][ $key ] : null;
	if ( $single ) {
		return null === $v ? '' : ( is_array( $v ) ? ( $v[0] ?? '' ) : $v );
	}
	return null === $v ? array() : (array) $v;
}
function get_the_excerpt( $post ) {
	$p = hf_post( is_object( $post ) ? $post->ID : $post );
	return $p ? $p['excerpt'] : '';
}
function wp_trim_words( $t, $n ) {
	return implode( ' ', array_slice( explode( ' ', $t ), 0, $n ) );
}
function wpautop( $t ) {
	return '<p>' . implode( '</p><p>', preg_split( "/\n{2,}/", trim( $t ) ) ) . '</p>';
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function selected( $a, $b, $echo = true ) {
	return (string) $a === (string) $b ? ' selected="selected"' : '';
}
function is_post_type_archive( $t ) {
	return $GLOBALS['hf_ctx']['archive'] === $t;
}
function is_singular( $t ) {
	return $GLOBALS['hf_ctx']['singular'] === $t;
}
function is_page( $s ) {
	return $GLOBALS['hf_ctx']['page'] === $s;
}
function wp_count_posts( $t ) {
	return (object) array( 'publish' => 0 );
}
function get_query_var( $v ) {
	return 1;
}
function paginate_links( $a ) {
	return '';
}
function trailingslashit( $s ) {
	return rtrim( $s, '/' ) . '/';
}
function sanitize_text_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function wp_unslash( $s ) {
	return $s;
}
function absint( $n ) {
	return abs( (int) $n );
}
function wp_reset_postdata() {}

function hf_matches( $id, $clause ) {
	if ( isset( $clause['relation'] ) ) {
		$results = array();
		foreach ( $clause as $k => $c ) {
			if ( 'relation' !== $k ) {
				$results[] = hf_matches( $id, $c );
			}
		}
		return 'OR' === $clause['relation'] ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}
	$have = array_map( 'strval', get_post_meta( $id, $clause['key'], false ) );
	$want = array_map( 'strval', (array) $clause['value'] );
	return (bool) array_intersect( $have, $want );
}
function hf_filter_ids( $args ) {
	$ids = array();
	foreach ( $GLOBALS['hf_db'] as $id => $p ) {
		if ( ! empty( $args['post_type'] ) && $p['type'] !== $args['post_type'] ) {
			continue;
		}
		if ( ! empty( $args['post__not_in'] ) && in_array( $id, $args['post__not_in'], true ) ) {
			continue;
		}
		if ( ! empty( $args['meta_query'] ) && ! hf_matches( $id, $args['meta_query'] ) ) {
			continue;
		}
		if ( ! empty( $args['meta_key'] ) && ! in_array( (string) $args['meta_value'], array_map( 'strval', get_post_meta( $id, $args['meta_key'], false ) ), true ) ) {
			continue;
		}
		$ids[] = $id;
	}
	if ( isset( $args['orderby'] ) && 'title' === $args['orderby'] ) {
		usort( $ids, static fn( $a, $b ) => strcmp( $GLOBALS['hf_db'][ $a ]['title'], $GLOBALS['hf_db'][ $b ]['title'] ) );
	}
	$n = $args['numberposts'] ?? $args['posts_per_page'] ?? -1;
	return $n > 0 ? array_slice( $ids, 0, $n ) : $ids;
}
function get_posts( $args ) {
	$ids = hf_filter_ids( $args );
	if ( ( $args['fields'] ?? '' ) === 'ids' ) {
		return $ids;
	}
	return array_map( static fn( $id ) => (object) array( 'ID' => $id ), $ids );
}
class WP_Query {
	public $posts = array();
	public $max_num_pages = 1;
	public function __construct( $args ) {
		$this->posts = array_map( static fn( $id ) => (object) array( 'ID' => $id ), hf_filter_ids( $args ) );
	}
	public function have_posts() {
		return (bool) $this->posts;
	}
}

require $theme . '/inc/meta.php';
require $theme . '/inc/blocks.php';
foreach ( $GLOBALS['hf_hooks']['init'] as $cb ) {
	$cb();
}

/* ---------- Rendering ---------- */

/** Replace the block comments in an HTML template with rendered output. */
function hf_render_template( $html, $theme ) {
	$html = preg_replace_callback(
		'#<!-- wp:template-part \{"slug":"([a-z-]+)"[^}]*\} /-->#',
		static fn( $m ) => hf_render_template( file_get_contents( "$theme/parts/{$m[1]}.html" ), $theme ),
		$html
	);
	$html = preg_replace_callback(
		'#<!-- wp:(hunza/[a-z-]+)(?: (\{.*?\}))? /-->#',
		static function ( $m ) {
			$attrs = ! empty( $m[2] ) ? json_decode( $m[2], true ) : array();
			$block = (object) array( 'context' => array( 'postId' => $GLOBALS['hf_current'] ) );
			return call_user_func( $GLOBALS['hf_blocks'][ $m[1] ], $attrs, '', $block );
		},
		$html
	);
	return $html;
}

/** Global-style variables WordPress would print from theme.json. */
function hf_preset_css( $theme ) {
	$json = json_decode( file_get_contents( "$theme/theme.json" ), true );
	$css  = ':root{';
	foreach ( $json['settings']['color']['palette'] as $c ) {
		$css .= '--wp--preset--color--' . $c['slug'] . ':' . $c['color'] . ';';
	}
	foreach ( $json['settings']['typography']['fontFamilies'] as $f ) {
		$css .= '--wp--preset--font-family--' . $f['slug'] . ':' . $f['fontFamily'] . ';';
	}
	foreach ( $json['settings']['typography']['fontSizes'] as $s ) {
		$size = isset( $s['fluid'] ) ? 'clamp(' . $s['fluid']['min'] . ',3.2vw,' . $s['fluid']['max'] . ')' : $s['size'];
		$css .= '--wp--preset--font-size--' . $s['slug'] . ':' . $size . ';';
	}
	$css .= '}body{margin:0;background:var(--wp--preset--color--ink);color:var(--wp--preset--color--text);font-family:var(--wp--preset--font-family--body);font-size:var(--wp--preset--font-size--base);line-height:1.65}';
	$css .= 'a{color:var(--wp--preset--color--accent-strong)}h1,h2,h3{font-family:var(--wp--preset--font-family--display);line-height:1.1;margin:0 0 .5em}h1{font-size:var(--wp--preset--font-size--xx-large)}h2{font-size:var(--wp--preset--font-size--x-large)}';
	$css .= '.wp-block-button__link{display:inline-block;padding:.8rem 1.5rem;border-radius:999px;background:var(--wp--preset--color--accent);color:var(--wp--preset--color--ink);font-weight:700;text-decoration:none}.wp-block-buttons{display:flex;gap:.75rem}';
	return $css;
}

function hf_page( $file, $template, $ctx, $current, $title ) {
	global $theme, $out;
	$GLOBALS['hf_ctx']     = array_merge( array( 'archive' => '', 'singular' => '', 'page' => '' ), $ctx );
	$GLOBALS['hf_current'] = $current;
	$body                  = hf_render_template( file_get_contents( "$theme/templates/$template.html" ), $theme );
	$body                  = preg_replace( '#<!-- /?wp:[^>]*-->#', '', $body );
	ob_start();
	hf_render_player_bar();
	$player = ob_get_clean();
	$html   = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title><style id="preset">' . hf_preset_css( $theme ) . '</style><link rel="stylesheet" href="../../wp-content/themes/hunza-folk/style.css"></head><body class="' . esc_attr( $ctx['class'] ?? '' ) . '">' . $body . $player
		. '<script src="../../wp-content/themes/hunza-folk/assets/js/player.js"></script><script src="../../wp-content/themes/hunza-folk/assets/js/site.js"></script></body></html>';
	// Header brand: no site-logo / site-title blocks outside WordPress.
	$html = preg_replace( '#<div class="wp-block-group hf-brand">\s*</div>#', '<div class="wp-block-group hf-brand"><p class="wp-block-site-title"><a href="index.html">Hunza Folk</a></p></div>', $html );
	file_put_contents( "$out/$file", $html );
	echo "wrote $file\n";
}

hf_page( 'index.html', 'front-page', array(), 0, 'Hunza Folk' );
hf_page( 'music.html', 'archive-song', array( 'archive' => 'song' ), 0, 'Music' );
hf_page( 'song-30.html', 'single-song', array( 'singular' => 'song' ), 30, 'Sample Song One' );
hf_page( 'song-33.html', 'single-song', array( 'singular' => 'song' ), 33, 'Sample Song Four' );
hf_page( 'instruments.html', 'archive-instrument', array( 'archive' => 'instrument' ), 0, 'Instruments' );
hf_page( 'artists.html', 'archive-artist', array( 'archive' => 'artist' ), 0, 'Artists' );
hf_page( 'artist-20.html', 'single-artist', array( 'singular' => 'artist' ), 20, 'Sample Artist One' );
hf_page( 'instrument-10.html', 'single-instrument', array( 'singular' => 'instrument' ), 10, 'Dadang' );
