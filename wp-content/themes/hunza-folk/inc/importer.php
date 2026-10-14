<?php
/**
 * WP-CLI importer for the markdown "Website Copy" files.
 *
 *   wp hunza import <folder> [--status=draft|publish] [--dry-run]
 *
 * Every .md file needs front matter with at least `type` (song, artist,
 * instrument, story or page) and `title`. Existing posts with the same type
 * and slug are updated, so the command is safe to run repeatedly. See the
 * theme README for the full list of front matter keys.
 *
 * @package hunza-folk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front matter key => field key, per post type. Keys not listed here are ignored.
 *
 * @return array<string, array<string, string>>
 */
function hf_import_field_map() {
	return array(
		'song'       => array(
			'title_ur'    => 'title_ur',
			'language'    => 'language',
			'location'    => 'location',
			'year'        => 'recording_year',
			'note'        => 'cultural_note',
			'youtube'     => 'youtube_id',
			'audio'       => 'audio_file',
			'performers'  => 'performers',
			'instruments' => 'instruments',
			'featured'    => 'featured',
		),
		'artist'     => array(
			'role' => 'role',
		),
		'instrument' => array(
			'local_name'      => 'local_name',
			'instrument_type' => 'instrument_type',
		),
	);
}

/**
 * Read every markdown file under a folder. No WordPress calls, so it can be tested alone.
 *
 * @param string $dir Folder to scan recursively.
 * @return array<int, array{file:string, meta:array, body:string}> Entries, import-ordered.
 */
function hf_import_collect( $dir ) {
	$entries  = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || 'md' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		list( $meta, $body ) = hf_md_split( (string) file_get_contents( $file->getPathname() ) );
		$entries[]           = array(
			'file' => $file->getPathname(),
			'meta' => $meta,
			'body' => $body,
		);
	}
	// Instruments and artists first so songs can link to them by name.
	$rank = array( 'instrument' => 0, 'artist' => 1, 'song' => 2, 'story' => 3, 'page' => 4 );
	usort(
		$entries,
		static function ( $a, $b ) use ( $rank ) {
			$ra = isset( $a['meta']['type'], $rank[ $a['meta']['type'] ] ) ? $rank[ $a['meta']['type'] ] : 9;
			$rb = isset( $b['meta']['type'], $rank[ $b['meta']['type'] ] ) ? $rank[ $b['meta']['type'] ] : 9;
			return $ra <=> $rb ?: strcmp( $a['file'], $b['file'] );
		}
	);
	return $entries;
}

/**
 * Problems that stop an entry from being imported.
 *
 * @param array $entry Collected entry.
 * @return string[] Error messages; empty when valid.
 */
function hf_import_validate( $entry ) {
	$errors = array();
	$meta   = $entry['meta'];
	$types  = array( 'song', 'artist', 'instrument', 'story', 'page' );
	if ( empty( $meta['type'] ) || ! in_array( $meta['type'], $types, true ) ) {
		$errors[] = 'missing or unknown `type` (use ' . implode( ', ', $types ) . ')';
	}
	if ( empty( $meta['title'] ) || ! is_string( $meta['title'] ) ) {
		$errors[] = 'missing `title`';
	}
	return $errors;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Import Hunza Folk content from markdown files.
	 */
	class HF_Import_Command {

		/**
		 * Import markdown files.
		 *
		 * ## OPTIONS
		 *
		 * <folder>
		 * : Folder containing .md files (searched recursively).
		 *
		 * [--status=<status>]
		 * : Status for newly created posts. Default draft, so nothing goes live unreviewed.
		 * ---
		 * default: draft
		 * options:
		 *   - draft
		 *   - publish
		 * ---
		 *
		 * [--dry-run]
		 * : Show what would happen without changing anything.
		 *
		 * @param string[]             $args       Positional args.
		 * @param array<string, mixed> $assoc_args Flags.
		 */
		public function import( $args, $assoc_args ) {
			$dir = realpath( $args[0] );
			if ( ! $dir || ! is_dir( $dir ) ) {
				WP_CLI::error( 'Folder not found: ' . $args[0] );
			}
			$dry     = ! empty( $assoc_args['dry-run'] );
			$status  = isset( $assoc_args['status'] ) && 'publish' === $assoc_args['status'] ? 'publish' : 'draft';
			$map     = hf_import_field_map();
			$fields  = hf_meta_fields();
			$ids     = array(); // type => lowercase title => ID.
			$counts  = array( 'created' => 0, 'updated' => 0, 'skipped' => 0 );
			$entries = hf_import_collect( $dir );

			if ( ! $entries ) {
				WP_CLI::error( 'No .md files found in ' . $dir );
			}

			foreach ( $entries as $entry ) {
				$label  = basename( $entry['file'] );
				$errors = hf_import_validate( $entry );
				if ( $errors ) {
					WP_CLI::warning( "$label skipped: " . implode( '; ', $errors ) );
					++$counts['skipped'];
					continue;
				}

				$meta = $entry['meta'];
				$type = $meta['type'];
				$slug = sanitize_title( ! empty( $meta['slug'] ) ? $meta['slug'] : $meta['title'] );
				$wp_type  = 'page' === $type ? 'page' : $type;
				$existing = get_page_by_path( $slug, OBJECT, $wp_type );

				$postarr = array(
					'post_type'    => $wp_type,
					'post_title'   => sanitize_text_field( $meta['title'] ),
					'post_name'    => $slug,
					'post_content' => hf_md_to_blocks( $entry['body'] ),
					'post_excerpt' => isset( $meta['excerpt'] ) && is_string( $meta['excerpt'] ) ? sanitize_textarea_field( $meta['excerpt'] ) : '',
					'post_status'  => $existing ? $existing->post_status : $status,
				);
				if ( $existing ) {
					$postarr['ID'] = $existing->ID;
				}

				$action = $existing ? 'update' : 'create';
				if ( $dry ) {
					WP_CLI::log( sprintf( '[dry-run] would %s %s "%s" from %s', $action, $type, $meta['title'], $label ) );
					$ids[ $type ][ strtolower( $meta['title'] ) ] = $existing ? $existing->ID : 0;
					++$counts[ $existing ? 'updated' : 'created' ];
					continue;
				}

				$post_id = wp_insert_post( wp_slash( $postarr ), true );
				if ( is_wp_error( $post_id ) ) {
					WP_CLI::warning( "$label failed: " . $post_id->get_error_message() );
					++$counts['skipped'];
					continue;
				}
				$ids[ $type ][ strtolower( $meta['title'] ) ] = $post_id;

				if ( isset( $map[ $type ] ) ) {
					$this->save_fields( $post_id, $type, $meta, $map[ $type ], $fields[ $type ], $ids, $label );
				}
				++$counts[ $existing ? 'updated' : 'created' ];
				WP_CLI::log( ( $existing ? 'Updated ' : 'Created ' ) . "$type \"{$meta['title']}\" (#$post_id)" );
			}

			WP_CLI::success( sprintf( '%d created, %d updated, %d skipped%s.', $counts['created'], $counts['updated'], $counts['skipped'], $dry ? ' (dry run, nothing written)' : '' ) );
		}

		/**
		 * @param int                  $post_id Post ID.
		 * @param string               $type    Post type.
		 * @param array                $meta    Front matter.
		 * @param array<string,string> $map     Front matter key => field key.
		 * @param array                $defs    Field definitions for the type.
		 * @param array                $ids     Known IDs by type and lowercase title.
		 * @param string               $label   File name for messages.
		 */
		private function save_fields( $post_id, $type, $meta, $map, $defs, $ids, $label ) {
			$values = array();
			foreach ( $map as $front_key => $field_key ) {
				if ( ! isset( $meta[ $front_key ] ) ) {
					continue;
				}
				$def   = $defs[ $field_key ];
				$value = $meta[ $front_key ];
				if ( 'posts' === $def['type'] ) {
					$resolved = array();
					foreach ( (array) $value as $name ) {
						$found = $this->find_id( $def['post_type'], (string) $name, $ids );
						if ( $found ) {
							$resolved[] = $found;
						} else {
							WP_CLI::warning( "$label: no {$def['post_type']} named \"$name\" (add that file or create it first)" );
						}
					}
					$value = $resolved;
				} elseif ( 'bool' === $def['type'] ) {
					$value = in_array( strtolower( (string) $value ), array( '1', 'true', 'yes' ), true );
				}
				$clean = hf_sanitize_field( $def, is_array( $value ) ? $value : (string) $value );
				if ( 'posts' !== $def['type'] && '' === $clean && '' !== trim( (string) $value ) && 'bool' !== $def['type'] ) {
					WP_CLI::warning( "$label: ignored invalid `$front_key` value \"$value\"" );
				}
				$values[ $field_key ] = $clean;
			}

			if ( 'song' === $type ) {
				$values['media_type'] = ! empty( $values['youtube_id'] ) || empty( $values['audio_file'] ) ? 'youtube' : 'file';
			}
			foreach ( $values as $field_key => $value ) {
				hf_store_field( $post_id, $field_key, $defs[ $field_key ], $value );
			}
		}

		/**
		 * @param string $post_type Post type to search.
		 * @param string $title     Title as written in the markdown.
		 * @param array  $ids       Known IDs by type and lowercase title.
		 * @return int ID or 0.
		 */
		private function find_id( $post_type, $title, $ids ) {
			$key = strtolower( trim( $title ) );
			if ( ! empty( $ids[ $post_type ][ $key ] ) ) {
				return (int) $ids[ $post_type ][ $key ];
			}
			$found = get_posts(
				array(
					'post_type'   => $post_type,
					'post_status' => 'any',
					'title'       => trim( $title ),
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			return $found ? (int) $found[0] : 0;
		}
	}

	WP_CLI::add_command( 'hunza', 'HF_Import_Command' );
}
