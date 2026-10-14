<?php
/**
 * Minimal markdown support for the content importer.
 *
 * Deliberately WordPress-free (plain PHP only) so it can be unit tested with
 * the PHP CLI. Raw HTML in the source is escaped, never passed through.
 *
 * Supported: YAML-style front matter (scalars, inline lists, dash lists),
 * headings, paragraphs, bullet / numbered lists, block quotes, horizontal
 * rules, **bold**, *italic*, `code` and [links](https://example.com).
 *
 * @package hunza-folk
 */

/**
 * Split a markdown file into front matter and body.
 *
 * @param string $source Raw file contents.
 * @return array{0: array<string, string|string[]>, 1: string} [ meta, body ].
 */
function hf_md_split( $source ) {
	$source = preg_replace( '/^\xEF\xBB\xBF/', '', str_replace( "\r\n", "\n", $source ) );
	if ( ! preg_match( '/\A---[ \t]*\n(.*?)\n---[ \t]*(?:\n|\z)(.*)\z/s', $source, $m ) ) {
		return array( array(), trim( $source ) );
	}
	return array( hf_md_parse_front_matter( $m[1] ), trim( $m[2] ) );
}

/**
 * @param string $yaml Front matter block without the --- fences.
 * @return array<string, string|string[]>
 */
function hf_md_parse_front_matter( $yaml ) {
	$meta = array();
	$key  = null;
	foreach ( explode( "\n", $yaml ) as $line ) {
		if ( '' === trim( $line ) || '#' === substr( ltrim( $line ), 0, 1 ) ) {
			continue;
		}
		if ( null !== $key && preg_match( '/^\s+-\s+(.*)$/', $line, $m ) ) {
			if ( ! is_array( $meta[ $key ] ) ) {
				$meta[ $key ] = array();
			}
			$meta[ $key ][] = hf_md_scalar( $m[1] );
			continue;
		}
		if ( preg_match( '/^([A-Za-z_][A-Za-z0-9_-]*)\s*:\s*(.*)$/', $line, $m ) ) {
			$key   = strtolower( $m[1] );
			$value = trim( $m[2] );
			if ( '' === $value ) {
				$meta[ $key ] = array();
			} elseif ( '[' === $value[0] && ']' === substr( $value, -1 ) ) {
				$inner        = trim( substr( $value, 1, -1 ) );
				$meta[ $key ] = '' === $inner ? array() : array_map( 'hf_md_scalar', hf_md_split_list( $inner ) );
			} else {
				$meta[ $key ] = hf_md_scalar( $value );
			}
		}
	}
	return $meta;
}

/**
 * Split "a, b, 'c, d'" on commas that are not inside quotes.
 *
 * @param string $text Inline list contents.
 * @return string[]
 */
function hf_md_split_list( $text ) {
	$parts = array();
	$buf   = '';
	$quote = '';
	foreach ( str_split( $text ) as $ch ) {
		if ( '' !== $quote ) {
			$buf .= $ch;
			if ( $ch === $quote ) {
				$quote = '';
			}
		} elseif ( '"' === $ch || "'" === $ch ) {
			$quote = $ch;
			$buf  .= $ch;
		} elseif ( ',' === $ch ) {
			$parts[] = $buf;
			$buf     = '';
		} else {
			$buf .= $ch;
		}
	}
	$parts[] = $buf;
	return $parts;
}

/**
 * @param string $value Raw scalar, possibly quoted.
 * @return string
 */
function hf_md_scalar( $value ) {
	$value = trim( $value );
	$len   = strlen( $value );
	if ( $len >= 2 && ( '"' === $value[0] || "'" === $value[0] ) && $value[ $len - 1 ] === $value[0] ) {
		return substr( $value, 1, -1 );
	}
	return $value;
}

/**
 * Inline formatting on text that has NOT been escaped yet.
 *
 * @param string $text Plain text.
 * @return string Safe HTML.
 */
function hf_md_inline( $text ) {
	$html = htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	$html = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );
	$html = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html );
	$html = preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $html );
	$html = preg_replace_callback(
		'/\[([^\]]+)\]\(([^)\s]+)\)/',
		static function ( $m ) {
			$url = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
			if ( ! preg_match( '#^(https?://|mailto:|/|\#)#i', $url ) ) {
				return $m[1];
			}
			$external = preg_match( '#^https?://#i', $url ) ? ' rel="noopener"' : '';
			return '<a href="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) . '"' . $external . '>' . $m[1] . '</a>';
		},
		$html
	);
	return $html;
}

/**
 * Convert a markdown body to WordPress block markup.
 *
 * @param string $markdown Body without front matter.
 * @return string
 */
function hf_md_to_blocks( $markdown ) {
	$markdown = str_replace( "\r\n", "\n", trim( $markdown ) );
	if ( '' === $markdown ) {
		return '';
	}
	$out = array();
	foreach ( preg_split( '/\n{2,}/', $markdown ) as $chunk ) {
		$chunk = trim( $chunk, "\n" );
		if ( '' === trim( $chunk ) ) {
			continue;
		}
		$lines = explode( "\n", $chunk );

		if ( preg_match( '/^(#{1,6})\s+(.*)$/', $lines[0], $m ) && 1 === count( $lines ) ) {
			$level = strlen( $m[1] );
			$out[] = '<!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading">' . hf_md_inline( trim( $m[2] ) ) . '</h' . $level . '><!-- /wp:heading -->';
		} elseif ( preg_match( '/^(?:-{3,}|\*{3,}|_{3,})$/', trim( $chunk ) ) ) {
			$out[] = '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
		} elseif ( preg_match( '/^\s*[-*+]\s+/', $lines[0] ) ) {
			$out[] = hf_md_list( $lines, false );
		} elseif ( preg_match( '/^\s*\d+[.)]\s+/', $lines[0] ) ) {
			$out[] = hf_md_list( $lines, true );
		} elseif ( preg_match( '/^>\s?/', $lines[0] ) ) {
			$inner = array();
			foreach ( $lines as $line ) {
				$inner[] = preg_replace( '/^>\s?/', '', $line );
			}
			$out[] = '<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>' . hf_md_inline( implode( ' ', $inner ) ) . '</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->';
		} else {
			// A heading directly followed by text in one chunk: split it.
			if ( preg_match( '/^(#{1,6})\s+(.*)$/', $lines[0], $m ) ) {
				$level = strlen( $m[1] );
				$out[] = '<!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading">' . hf_md_inline( trim( $m[2] ) ) . '</h' . $level . '><!-- /wp:heading -->';
				array_shift( $lines );
			}
			$text  = trim( implode( ' ', array_map( 'trim', $lines ) ) );
			$out[] = '<!-- wp:paragraph --><p>' . hf_md_inline( $text ) . '</p><!-- /wp:paragraph -->';
		}
	}
	return implode( "\n\n", $out );
}

/**
 * @param string[] $lines   Lines of a list chunk.
 * @param bool     $ordered Whether the list is numbered.
 * @return string
 */
function hf_md_list( $lines, $ordered ) {
	$tag   = $ordered ? 'ol' : 'ul';
	$attrs = $ordered ? ' {"ordered":true}' : '';
	$items = '';
	foreach ( $lines as $line ) {
		$text   = preg_replace( '/^\s*(?:[-*+]|\d+[.)])\s+/', '', $line );
		$items .= '<!-- wp:list-item --><li>' . hf_md_inline( trim( $text ) ) . '</li><!-- /wp:list-item -->';
	}
	return '<!-- wp:list' . $attrs . ' --><' . $tag . ' class="wp-block-list">' . $items . '</' . $tag . '><!-- /wp:list -->';
}
