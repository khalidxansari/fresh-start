<?php
/**
 * Custom post types: songs, artists, instruments and folklore stories.
 *
 * @package hunza-folk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_switch_theme',
	static function () {
		flush_rewrite_rules();
	}
);

add_action(
	'init',
	static function () {
		$types = array(
			'song'       => array( 'Songs', 'Song', 'music', 'dashicons-format-audio' ),
			'artist'     => array( 'Artists', 'Artist', 'artists', 'dashicons-groups' ),
			'instrument' => array( 'Instruments', 'Instrument', 'instruments', 'dashicons-playlist-audio' ),
			'story'      => array( 'Folklore', 'Story', 'folklore', 'dashicons-book-alt' ),
		);

		foreach ( $types as $type => $info ) {
			list( $plural, $singular, $slug, $icon ) = $info;
			register_post_type(
				$type,
				array(
					'labels'       => array(
						'name'          => $plural,
						'singular_name' => $singular,
						'add_new_item'  => 'Add new ' . strtolower( $singular ),
						'edit_item'     => 'Edit ' . strtolower( $singular ),
						'all_items'     => 'All ' . strtolower( $plural ),
					),
					'public'       => true,
					'has_archive'  => $slug,
					'rewrite'      => array( 'slug' => $slug, 'with_front' => false ),
					'show_in_rest' => true,
					'menu_icon'    => $icon,
					'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
				)
			);
		}
	}
);

/**
 * The music archive lists 20 songs per page (see the song-list block). Make the
 * main query agree so pages past the last one return a 404 instead of an empty list.
 */
add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( 'song' ) ) {
			$query->set( 'posts_per_page', 20 );
		}
	}
);
