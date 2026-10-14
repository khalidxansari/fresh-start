<?php
/**
 * Custom fields for songs, artists and instruments.
 *
 * Field definitions live in hf_meta_fields() so the editor meta box, the
 * sanitiser and the markdown importer all share one source of truth.
 *
 * @package hunza-folk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, array<string, array<string, mixed>>> Fields keyed by post type, then by short key.
 */
function hf_meta_fields() {
	return array(
		'song'       => array(
			'title_ur'       => array( 'label' => 'Title in Urdu', 'type' => 'text' ),
			'language'       => array( 'label' => 'Language (e.g. Burushaski, Shina, Wakhi)', 'type' => 'text' ),
			'location'       => array( 'label' => 'Recorded where (village / valley)', 'type' => 'text' ),
			'recording_year' => array( 'label' => 'Recording year', 'type' => 'year' ),
			'cultural_note'  => array( 'label' => 'Cultural note (why this song exists)', 'type' => 'textarea' ),
			'media_type'     => array(
				'label'   => 'Media source',
				'type'    => 'select',
				'options' => array(
					'youtube' => 'YouTube video',
					'file'    => 'Audio file (MP3 / M4A / OGG)',
				),
			),
			'youtube_id'     => array( 'label' => 'YouTube link or video ID', 'type' => 'youtube' ),
			'audio_file'     => array( 'label' => 'Audio file URL (copy from Media Library)', 'type' => 'url' ),
			'performers'     => array( 'label' => 'Performers', 'type' => 'posts', 'post_type' => 'artist', 'multiple' => true ),
			'instruments'    => array( 'label' => 'Instruments', 'type' => 'posts', 'post_type' => 'instrument', 'multiple' => true ),
			'featured'       => array( 'label' => 'Feature on the home page', 'type' => 'bool' ),
		),
		'artist'     => array(
			'role' => array( 'label' => 'Role in the community (e.g. singer, rubab player)', 'type' => 'text' ),
		),
		'instrument' => array(
			'local_name'      => array( 'label' => 'Local name / script', 'type' => 'text' ),
			'instrument_type' => array( 'label' => 'Type (e.g. drum, wind, string)', 'type' => 'text' ),
		),
	);
}

/**
 * Read a single field. Multi-value fields return an array of ints.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Short key, without the hf_ prefix.
 * @return mixed
 */
function hf_get( $post_id, $key ) {
	$post_type = get_post_type( $post_id );
	$fields    = hf_meta_fields();
	$def       = isset( $fields[ $post_type ][ $key ] ) ? $fields[ $post_type ][ $key ] : null;
	if ( $def && 'posts' === $def['type'] ) {
		return array_values( array_filter( array_map( 'absint', get_post_meta( $post_id, 'hf_' . $key, false ) ) ) );
	}
	return get_post_meta( $post_id, 'hf_' . $key, true );
}

/**
 * Pull a YouTube video ID out of a URL, or accept a bare ID.
 *
 * @param string $value URL or ID.
 * @return string Valid 11 character ID, or an empty string.
 */
function hf_parse_youtube_id( $value ) {
	$value = trim( (string) $value );
	if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $value ) ) {
		return $value;
	}
	if ( preg_match( '#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})#', $value, $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * Sanitise a raw value for a field definition.
 *
 * @param array<string, mixed> $def   Field definition.
 * @param mixed                $value Raw value.
 * @return mixed Scalar, or an array of ints for multi-value fields.
 */
function hf_sanitize_field( $def, $value ) {
	switch ( $def['type'] ) {
		case 'text':
			return sanitize_text_field( (string) $value );
		case 'textarea':
			return sanitize_textarea_field( (string) $value );
		case 'year':
			$year = absint( $value );
			return ( $year >= 1900 && $year <= 2100 ) ? $year : '';
		case 'select':
			$value = (string) $value;
			return isset( $def['options'][ $value ] ) ? $value : (string) array_key_first( $def['options'] );
		case 'youtube':
			return hf_parse_youtube_id( $value );
		case 'url':
			$url = esc_url_raw( (string) $value, array( 'http', 'https' ) );
			return $url;
		case 'bool':
			return $value ? 1 : 0;
		case 'posts':
			$ids = array();
			foreach ( (array) $value as $id ) {
				$id = absint( $id );
				if ( $id && get_post_type( $id ) === $def['post_type'] ) {
					$ids[] = $id;
				}
			}
			return array_values( array_unique( $ids ) );
	}
	return '';
}

/**
 * Store a sanitised value. Multi-value fields use one meta row per ID so they
 * can be queried with an exact match.
 *
 * @param int                  $post_id Post ID.
 * @param string               $key     Short key.
 * @param array<string, mixed> $def     Field definition.
 * @param mixed                $value   Already-sanitised value.
 */
function hf_store_field( $post_id, $key, $def, $value ) {
	$meta_key = 'hf_' . $key;
	delete_post_meta( $post_id, $meta_key );
	if ( 'posts' === $def['type'] ) {
		foreach ( $value as $id ) {
			add_post_meta( $post_id, $meta_key, $id );
		}
		return;
	}
	if ( '' !== $value && 0 !== $value ) {
		update_post_meta( $post_id, $meta_key, $value );
	}
}

add_action(
	'add_meta_boxes',
	static function () {
		foreach ( array_keys( hf_meta_fields() ) as $post_type ) {
			add_meta_box( 'hf-details', 'Hunza Folk details', 'hf_render_meta_box', $post_type, 'normal', 'high' );
		}
	}
);

/**
 * @param WP_Post $post Post being edited.
 */
function hf_render_meta_box( $post ) {
	$fields = hf_meta_fields();
	if ( empty( $fields[ $post->post_type ] ) ) {
		return;
	}
	wp_nonce_field( 'hf_save_meta', 'hf_meta_nonce' );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( $fields[ $post->post_type ] as $key => $def ) {
		$id    = 'hf_' . $key;
		$value = hf_get( $post->ID, $key );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $def['label'] ) . '</label></th><td>';
		switch ( $def['type'] ) {
			case 'textarea':
				echo '<textarea class="large-text" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">';
				foreach ( $def['options'] as $opt => $label ) {
					echo '<option value="' . esc_attr( $opt ) . '"' . selected( $value ?: array_key_first( $def['options'] ), $opt, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				break;
			case 'bool':
				echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="1"' . checked( (bool) $value, true, false ) . ' />';
				break;
			case 'posts':
				$choices = get_posts(
					array(
						'post_type'        => $def['post_type'],
						'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
						'numberposts'      => 200,
						'orderby'          => 'title',
						'order'            => 'ASC',
						'suppress_filters' => true,
					)
				);
				echo '<select multiple size="6" class="widefat" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '[]">';
				foreach ( $choices as $choice ) {
					echo '<option value="' . esc_attr( (string) $choice->ID ) . '"' . selected( in_array( $choice->ID, (array) $value, true ), true, false ) . '>' . esc_html( get_the_title( $choice ) ) . '</option>';
				}
				echo '</select><p class="description">Hold Ctrl / Cmd to pick more than one.</p>';
				break;
			default:
				$type = 'url' === $def['type'] ? 'url' : 'text';
				$show = 'youtube' === $def['type'] && $value ? 'https://youtu.be/' . $value : $value;
				echo '<input class="large-text" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" value="' . esc_attr( (string) $show ) . '" />';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

add_action(
	'save_post',
	static function ( $post_id, $post ) {
		if ( ! isset( $_POST['hf_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hf_meta_nonce'] ) ), 'hf_save_meta' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$fields = hf_meta_fields();
		if ( empty( $fields[ $post->post_type ] ) ) {
			return;
		}
		foreach ( $fields[ $post->post_type ] as $key => $def ) {
			$raw = isset( $_POST[ 'hf_' . $key ] ) ? wp_unslash( $_POST[ 'hf_' . $key ] ) : ( 'posts' === $def['type'] ? array() : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised in hf_sanitize_field().
			hf_store_field( $post_id, $key, $def, hf_sanitize_field( $def, $raw ) );
		}
	},
	10,
	2
);
