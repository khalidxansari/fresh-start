<?php
/**
 * Plain-PHP tests for the markdown parser and the importer's file scanning.
 * Run: php dev/test-import.php
 */

define( 'ABSPATH', __DIR__ . '/' );
$theme = dirname( __DIR__ ) . '/wp-content/themes/hunza-folk';
require $theme . '/inc/markdown.php';
require $theme . '/inc/importer.php';

$failed = 0;
function check( $name, $ok, $detail = '' ) {
	global $failed;
	echo ( $ok ? 'PASS' : 'FAIL' ) . "  $name" . ( $ok ? '' : "  -> $detail" ) . "\n";
	if ( ! $ok ) {
		++$failed;
	}
}

// Front matter.
list( $meta, $body ) = hf_md_split( "---\ntitle: \"Quoted: title\"\ntype: song\nperformers: [A B, 'C, D']\ninstruments:\n  - Dadang\n  - Damal\n# comment\nyear: 2015\n---\n\nHello **world**." );
check( 'quoted scalar', 'Quoted: title' === $meta['title'] );
check( 'inline list with quoted comma', array( 'A B', 'C, D' ) === $meta['performers'], json_encode( $meta['performers'] ) );
check( 'dash list', array( 'Dadang', 'Damal' ) === $meta['instruments'] );
check( 'comment skipped', ! isset( $meta['#'] ) && '2015' === $meta['year'] );
check( 'body split', 'Hello **world**.' === $body );

list( $none, $plain ) = hf_md_split( "No front matter\r\nhere" );
check( 'no front matter', array() === $none && "No front matter\nhere" === $plain );

// Markdown to blocks.
$blocks = hf_md_to_blocks( "## Heading\n\nA *soft* and **strong** line with [a link](https://example.com).\n\n- one\n- two\n\n1. first\n2. second\n\n> quoted\n\n---" );
check( 'heading block', false !== strpos( $blocks, '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Heading</h2>' ) );
check( 'inline formatting', false !== strpos( $blocks, '<em>soft</em>' ) && false !== strpos( $blocks, '<strong>strong</strong>' ) );
check( 'link with rel', false !== strpos( $blocks, '<a href="https://example.com" rel="noopener">a link</a>' ) );
check( 'bullet list', false !== strpos( $blocks, '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>one</li>' ) );
check( 'ordered list', false !== strpos( $blocks, '<!-- wp:list {"ordered":true} --><ol' ) );
check( 'quote', false !== strpos( $blocks, 'wp:quote' ) );
check( 'separator', false !== strpos( $blocks, 'wp:separator' ) );

// Safety: raw HTML and dangerous URLs must not survive.
$evil = hf_md_to_blocks( "<script>alert(1)</script> [x](javascript:alert(1)) [y](data:text/html,hi)" );
check( 'script escaped', false === strpos( $evil, '<script>' ) && false !== strpos( $evil, '&lt;script&gt;' ) );
check( 'javascript: link dropped', false === stripos( $evil, 'href="javascript' ) && false === stripos( $evil, 'href="data' ) );

// Heading immediately followed by a paragraph in one chunk.
$mixed = hf_md_to_blocks( "## Title\nBody line" );
check( 'heading then text in one chunk', false !== strpos( $mixed, '<h2' ) && false !== strpos( $mixed, '<p>Body line</p>' ) );

// Scanning the shipped sample content.
$entries = hf_import_collect( $theme . '/content' );
$types   = array_count_values( array_map( static fn( $e ) => $e['meta']['type'] ?? '?', $entries ) );
check( 'sample content counts', array( 'instrument' => 8, 'artist' => 3, 'song' => 3, 'page' => 2 ) === $types, json_encode( $types ) );
check( 'import order: instruments before songs', 'instrument' === $entries[0]['meta']['type'] && 'page' === end( $entries )['meta']['type'] );
$invalid = array_filter( array_map( 'hf_import_validate', $entries ) );
check( 'all sample files valid', array() === $invalid, json_encode( $invalid ) );

$song = null;
foreach ( $entries as $e ) {
	if ( 'Sample Song One' === $e['meta']['title'] ) {
		$song = $e;
	}
}
check( 'song front matter parsed', $song && array( 'Sample Artist One' ) === $song['meta']['performers'] && array( 'Dadang', 'Damal' ) === $song['meta']['instruments'] && 'true' === $song['meta']['featured'] );
check( 'urdu title preserved', $song && 'نمونہ گانا' === $song['meta']['title_ur'] );

check( 'invalid entry rejected', array() !== hf_import_validate( array( 'meta' => array( 'type' => 'banana' ), 'body' => '' ) ) );

echo $failed ? "\n$failed FAILED\n" : "\nAll passed\n";
exit( $failed ? 1 : 0 );
