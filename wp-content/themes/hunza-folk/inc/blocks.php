<?php
/**
 * Server-rendered blocks and the markup helpers behind them.
 *
 * @package hunza-folk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Playable media for a song.
 *
 * @param int $post_id Song ID.
 * @return array{kind:string,src:string}|null
 */
function hf_media( $post_id ) {
	$kind = hf_get( $post_id, 'media_type' ) ?: 'youtube';
	if ( 'file' === $kind ) {
		$src = (string) hf_get( $post_id, 'audio_file' );
		if ( $src ) {
			return array( 'kind' => 'file', 'src' => $src );
		}
	}
	$yt = (string) hf_get( $post_id, 'youtube_id' );
	if ( $yt ) {
		return array( 'kind' => 'youtube', 'src' => $yt );
	}
	return null;
}

/**
 * Cover image URL for a song, artist or instrument.
 *
 * @param int    $post_id Post ID.
 * @param string $size    Registered image size.
 * @return string URL, or an empty string.
 */
function hf_cover_url( $post_id, $size = 'hf-card' ) {
	$url = get_the_post_thumbnail_url( $post_id, $size );
	if ( $url ) {
		return $url;
	}
	if ( 'song' === get_post_type( $post_id ) ) {
		$media = hf_media( $post_id );
		if ( $media && 'youtube' === $media['kind'] ) {
			return 'https://i.ytimg.com/vi/' . $media['src'] . '/hqdefault.jpg';
		}
	}
	return '';
}

/**
 * @param int[] $ids Post IDs.
 * @return string[] Titles of published posts.
 */
function hf_titles( $ids ) {
	$out = array();
	foreach ( (array) $ids as $id ) {
		if ( 'publish' === get_post_status( $id ) ) {
			$out[ $id ] = get_the_title( $id );
		}
	}
	return $out;
}

/**
 * @param int[] $ids Post IDs.
 * @return string HTML list of links, comma separated.
 */
function hf_linked_titles( $ids ) {
	$links = array();
	foreach ( hf_titles( $ids ) as $id => $title ) {
		$links[] = '<a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $title ) . '</a>';
	}
	return implode( ', ', $links );
}

/**
 * The play control for a song. Songs without media get an inert badge.
 *
 * @param int    $post_id Song ID.
 * @param string $class   Extra CSS class.
 * @return string
 */
function hf_play_button( $post_id, $class = '' ) {
	$media = hf_media( $post_id );
	$title = get_the_title( $post_id );
	if ( ! $media ) {
		return '<span class="hf-play hf-play--soon ' . esc_attr( $class ) . '" role="img" aria-label="' . esc_attr( 'Recording coming soon: ' . $title ) . '" title="Recording coming soon">' . hf_icon( 'play' ) . '</span>';
	}
	$sub = implode( ', ', hf_titles( hf_get( $post_id, 'performers' ) ) );
	return sprintf(
		'<button type="button" class="hf-play %1$s" data-hf-play data-id="%2$d" data-kind="%3$s" data-src="%4$s" data-title="%5$s" data-sub="%6$s" data-cover="%7$s" data-url="%8$s" aria-label="%9$s">%10$s%11$s</button>',
		esc_attr( $class ),
		(int) $post_id,
		esc_attr( $media['kind'] ),
		esc_attr( $media['src'] ),
		esc_attr( $title ),
		esc_attr( $sub ),
		esc_url( hf_cover_url( $post_id ) ),
		esc_url( get_permalink( $post_id ) ),
		esc_attr( 'Play ' . $title ),
		hf_icon( 'play' ),
		hf_icon( 'pause' )
	);
}

/**
 * @param string $name play|pause.
 * @return string Inline SVG.
 */
function hf_icon( $name ) {
	$paths = array(
		'play'  => '<path d="M8 5v14l11-7z"/>',
		'pause' => '<path d="M6 5h4v14H6zM14 5h4v14h-4z"/>',
	);
	$class = 'hf-icon hf-icon--' . $name;
	return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * One row in a track list.
 *
 * @param int $post_id Song ID.
 * @return string
 */
function hf_song_row( $post_id ) {
	$ur         = (string) hf_get( $post_id, 'title_ur' );
	$performers = hf_linked_titles( hf_get( $post_id, 'performers' ) );
	$instr      = implode( ', ', hf_titles( hf_get( $post_id, 'instruments' ) ) );
	$where      = array_filter( array( hf_get( $post_id, 'location' ), hf_get( $post_id, 'recording_year' ) ) );

	$html  = '<li class="hf-track">';
	$html .= hf_play_button( $post_id );
	$html .= '<a class="hf-track__main" href="' . esc_url( get_permalink( $post_id ) ) . '">';
	$html .= '<span class="hf-track__title">' . esc_html( get_the_title( $post_id ) ) . '</span>';
	if ( $ur ) {
		$html .= '<span class="hf-track__ur" lang="ur" dir="rtl">' . esc_html( $ur ) . '</span>';
	}
	$html .= '</a>';
	$html .= '<span class="hf-track__who">' . $performers . ( $performers && $instr ? ' <span class="hf-sep" aria-hidden="true">·</span> ' : '' ) . esc_html( $instr ) . '</span>';
	$html .= '<span class="hf-track__where">' . esc_html( implode( ' · ', $where ) ) . '</span>';
	$html .= '<span class="hf-eq" aria-hidden="true"><i></i><i></i><i></i></span>';
	$html .= '</li>';
	return $html;
}

/**
 * @param WP_Block|null $block Block instance (for context).
 * @return int Current post ID.
 */
function hf_current_id( $block = null ) {
	if ( $block && ! empty( $block->context['postId'] ) ) {
		return (int) $block->context['postId'];
	}
	return (int) get_the_ID();
}

/* -------------------------------------------------------------------------
 * Block renderers
 * ---------------------------------------------------------------------- */

/**
 * Playable list of songs. Supports filters, related mode and pagination.
 *
 * @param array         $attrs Block attributes.
 * @param string        $content Unused.
 * @param WP_Block|null $block Block instance.
 * @return string
 */
function hf_render_song_list( $attrs, $content = '', $block = null ) {
	$count    = isset( $attrs['count'] ) ? (int) $attrs['count'] : 6;
	$paginate = ! empty( $attrs['paginate'] );
	$paged    = $paginate ? max( 1, (int) get_query_var( 'paged' ) ) : 1;

	$args = array(
		'post_type'      => 'song',
		'post_status'    => 'publish',
		'posts_per_page' => $count,
		'paged'          => $paged,
		'no_found_rows'  => ! $paginate,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

	$meta_query = array();
	$active     = array();

	if ( ! empty( $attrs['filters'] ) ) {
		// phpcs:disable WordPress.Security.NonceVerification -- public read-only filters.
		$active['instrument'] = isset( $_GET['instrument'] ) ? absint( $_GET['instrument'] ) : 0;
		$active['performer']  = isset( $_GET['performer'] ) ? absint( $_GET['performer'] ) : 0;
		$active['language']   = isset( $_GET['language'] ) ? sanitize_text_field( wp_unslash( $_GET['language'] ) ) : '';
		// phpcs:enable
		if ( $active['instrument'] ) {
			$meta_query[] = array( 'key' => 'hf_instruments', 'value' => $active['instrument'] );
		}
		if ( $active['performer'] ) {
			$meta_query[] = array( 'key' => 'hf_performers', 'value' => $active['performer'] );
		}
		if ( $active['language'] ) {
			$meta_query[] = array( 'key' => 'hf_language', 'value' => $active['language'] );
		}
	}

	if ( ! empty( $attrs['related'] ) ) {
		$current = hf_current_id( $block );
		$type    = get_post_type( $current );
		if ( 'artist' === $type ) {
			$meta_query[] = array( 'key' => 'hf_performers', 'value' => $current );
		} elseif ( 'instrument' === $type ) {
			$meta_query[] = array( 'key' => 'hf_instruments', 'value' => $current );
		} elseif ( 'song' === $type ) {
			$performers  = hf_get( $current, 'performers' );
			$instruments = hf_get( $current, 'instruments' );
			$or          = array( 'relation' => 'OR' );
			if ( $performers ) {
				$or[] = array( 'key' => 'hf_performers', 'value' => $performers, 'compare' => 'IN' );
			}
			if ( $instruments ) {
				$or[] = array( 'key' => 'hf_instruments', 'value' => $instruments, 'compare' => 'IN' );
			}
			if ( count( $or ) < 2 ) {
				return '';
			}
			$meta_query[]          = $or;
			$args['post__not_in'] = array( $current );
		} else {
			return '';
		}
	}

	if ( $meta_query ) {
		$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	$query = new WP_Query( $args );
	$out   = '';

	if ( ! empty( $attrs['filters'] ) ) {
		$out .= hf_render_song_filters( $active );
	}

	if ( ! $query->have_posts() ) {
		wp_reset_postdata();
		if ( ! empty( $attrs['related'] ) ) {
			return '';
		}
		$msg = ! empty( $attrs['filters'] ) && array_filter( $active ) ? 'No recordings match those filters yet.' : 'Recordings are being added. Check back soon.';
		return $out . '<p class="hf-empty">' . esc_html( $msg ) . '</p>';
	}

	if ( ! empty( $attrs['heading'] ) ) {
		$out .= '<h2 class="hf-list-heading">' . esc_html( $attrs['heading'] ) . '</h2>';
	}
	$out .= '<ol class="hf-tracklist" data-hf-queue>';
	foreach ( $query->posts as $song ) {
		$out .= hf_song_row( $song->ID );
	}
	$out .= '</ol>';

	if ( $paginate && $query->max_num_pages > 1 ) {
		$out .= '<nav class="hf-pagination" aria-label="Recordings pages">' . paginate_links(
			array(
				'base'      => trailingslashit( get_post_type_archive_link( 'song' ) ) . 'page/%#%/',
				'format'    => '',
				'current'   => $paged,
				'total'     => $query->max_num_pages,
				'add_args'  => array_filter( $active ),
				'prev_text' => 'Previous',
				'next_text' => 'Next',
			)
		) . '</nav>';
	}
	wp_reset_postdata();

	return $out;
}

/**
 * @param array<string, mixed> $active Active filter values.
 * @return string
 */
function hf_render_song_filters( $active ) {
	global $wpdb;
	$languages = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value <> '' AND p.post_type = %s AND p.post_status = 'publish' ORDER BY pm.meta_value ASC",
			'hf_language',
			'song'
		)
	);

	$build = static function ( $name, $label, $options, $selected ) {
		$html = '<label class="hf-filter"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '"><option value="">All</option>';
		foreach ( $options as $value => $text ) {
			$html .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( (string) $selected, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		return $html . '</select></label>';
	};

	$instruments = hf_titles( get_posts( array( 'post_type' => 'instrument', 'numberposts' => 100, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) ) );
	$performers  = hf_titles( get_posts( array( 'post_type' => 'artist', 'numberposts' => 100, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC' ) ) );
	$langs       = array_combine( $languages, $languages ) ?: array();

	$html  = '<form class="hf-filters" method="get" action="' . esc_url( get_post_type_archive_link( 'song' ) ) . '">';
	$html .= $build( 'language', 'Language', $langs, $active['language'] );
	$html .= $build( 'instrument', 'Instrument', $instruments, $active['instrument'] );
	$html .= $build( 'performer', 'Performer', $performers, $active['performer'] );
	$html .= '<button type="submit" class="hf-btn hf-btn--ghost">Filter</button>';
	if ( array_filter( $active ) ) {
		$html .= '<a class="hf-filters__reset" href="' . esc_url( get_post_type_archive_link( 'song' ) ) . '">Clear</a>';
	}
	return $html . '</form>';
}

/**
 * Home page hero built around one featured recording.
 *
 * @return string
 */
function hf_render_featured_song() {
	$songs = get_posts(
		array(
			'post_type'   => 'song',
			'numberposts' => 1,
			'meta_key'    => 'hf_featured', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => 1,             // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	if ( ! $songs ) {
		$songs = get_posts( array( 'post_type' => 'song', 'numberposts' => 1 ) );
	}

	$bg = '';
	$out = '<section class="hf-hero alignfull">';
	if ( $songs ) {
		$bg = hf_cover_url( $songs[0]->ID, 'hf-hero' );
	}
	if ( $bg ) {
		$out .= '<div class="hf-hero__bg" style="background-image:url(' . esc_url( $bg ) . ')"></div>';
	}
	$out .= '<div class="hf-hero__inner">';
	$out .= '<p class="hf-eyebrow">Music from the mountains</p>';
	$out .= '<h1 class="hf-hero__title">The sound of a valley, kept alive.</h1>';
	$out .= '<p class="hf-hero__lede">A digital archive of the folk music of Hunza Valley: its instruments, its musicians and its songs. Free to listen to, always.</p>';

	if ( $songs ) {
		$song = $songs[0];
		$out .= '<div class="hf-hero__now">';
		$out .= hf_play_button( $song->ID, 'hf-play--xl' );
		$out .= '<div class="hf-hero__track"><span class="hf-hero__label">Featured recording</span>';
		$out .= '<a class="hf-hero__song" href="' . esc_url( get_permalink( $song ) ) . '">' . esc_html( get_the_title( $song ) ) . '</a>';
		$who = implode( ', ', hf_titles( hf_get( $song->ID, 'performers' ) ) );
		if ( $who ) {
			$out .= '<span class="hf-hero__who">' . esc_html( $who ) . '</span>';
		}
		$out .= '</div></div>';
	}
	$out .= '<p class="hf-hero__links"><a class="hf-btn" href="' . esc_url( get_post_type_archive_link( 'song' ) ) . '">Browse all recordings</a> <a class="hf-btn hf-btn--ghost" href="' . esc_url( home_url( '/about/' ) ) . '">Our story</a></p>';
	$out .= '</div></section>';
	return $out;
}

/**
 * Grid of artist / instrument / story cards.
 *
 * @param array $attrs Block attributes.
 * @return string
 */
function hf_render_card_grid( $attrs ) {
	$type = isset( $attrs['postType'] ) ? $attrs['postType'] : 'instrument';
	if ( ! in_array( $type, array( 'artist', 'instrument', 'story' ), true ) ) {
		return '';
	}
	$posts = get_posts(
		array(
			'post_type'   => $type,
			'numberposts' => isset( $attrs['count'] ) ? (int) $attrs['count'] : 8,
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);
	if ( ! $posts ) {
		return '<p class="hf-empty">Coming soon.</p>';
	}

	$out = '<ul class="hf-cards hf-cards--' . esc_attr( $type ) . '">';
	foreach ( $posts as $post ) {
		$img   = hf_cover_url( $post->ID );
		$kick  = '';
		if ( 'artist' === $type ) {
			$kick = (string) hf_get( $post->ID, 'role' );
		} elseif ( 'instrument' === $type ) {
			$kick = (string) hf_get( $post->ID, 'instrument_type' );
		}
		$out .= '<li><a class="hf-card" href="' . esc_url( get_permalink( $post ) ) . '">';
		$out .= '<span class="hf-card__img">';
		if ( $img ) {
			$out .= '<img src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async">';
		} else {
			$out .= '<span class="hf-card__initial" aria-hidden="true">' . esc_html( mb_substr( get_the_title( $post ), 0, 1 ) ) . '</span>';
		}
		$out .= '</span><span class="hf-card__body">';
		if ( $kick ) {
			$out .= '<span class="hf-card__kicker">' . esc_html( $kick ) . '</span>';
		}
		$out .= '<span class="hf-card__title">' . esc_html( get_the_title( $post ) ) . '</span>';
		$text = wp_trim_words( get_the_excerpt( $post ), 18 );
		if ( $text ) {
			$out .= '<span class="hf-card__text">' . esc_html( $text ) . '</span>';
		}
		$out .= '</span></a></li>';
	}
	return $out . '</ul>';
}

/**
 * Hero for a single song: title, performers and the big play button.
 *
 * @param array         $attrs Unused.
 * @param string        $content Unused.
 * @param WP_Block|null $block Block instance.
 * @return string
 */
function hf_render_song_hero( $attrs, $content = '', $block = null ) {
	$id = hf_current_id( $block );
	if ( 'song' !== get_post_type( $id ) ) {
		return '';
	}
	$bg = hf_cover_url( $id, 'hf-hero' );
	$ur = (string) hf_get( $id, 'title_ur' );
	$who = implode( ', ', hf_titles( hf_get( $id, 'performers' ) ) );

	$out = '<section class="hf-hero hf-hero--song alignfull">';
	if ( $bg ) {
		$out .= '<div class="hf-hero__bg" style="background-image:url(' . esc_url( $bg ) . ')"></div>';
	}
	$out .= '<div class="hf-hero__inner"><p class="hf-eyebrow">Recording</p>';
	$out .= '<h1 class="hf-hero__title">' . esc_html( get_the_title( $id ) ) . '</h1>';
	if ( $ur ) {
		$out .= '<p class="hf-hero__ur" lang="ur" dir="rtl">' . esc_html( $ur ) . '</p>';
	}
	$out .= '<div class="hf-hero__now">' . hf_play_button( $id, 'hf-play--xl' );
	$out .= '<div class="hf-hero__track">';
	if ( $who ) {
		$out .= '<span class="hf-hero__who">' . esc_html( $who ) . '</span>';
	}
	if ( ! hf_media( $id ) ) {
		$out .= '<span class="hf-hero__label">Recording coming soon</span>';
	}
	$out .= '</div></div></div></section>';
	return $out;
}

/**
 * Fact sheet and cultural note for a song.
 *
 * @param array         $attrs Unused.
 * @param string        $content Unused.
 * @param WP_Block|null $block Block instance.
 * @return string
 */
function hf_render_song_facts( $attrs, $content = '', $block = null ) {
	$id = hf_current_id( $block );
	if ( 'song' !== get_post_type( $id ) ) {
		return '';
	}
	$rows = array(
		'Performers' => hf_linked_titles( hf_get( $id, 'performers' ) ),
		'Instruments' => hf_linked_titles( hf_get( $id, 'instruments' ) ),
		'Language'   => esc_html( (string) hf_get( $id, 'language' ) ),
		'Recorded in' => esc_html( (string) hf_get( $id, 'location' ) ),
		'Year'       => esc_html( (string) hf_get( $id, 'recording_year' ) ),
	);
	$out = '<div class="hf-facts"><dl>';
	foreach ( $rows as $label => $value ) {
		if ( '' !== $value ) {
			$out .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . $value . '</dd></div>';
		}
	}
	$out .= '</dl>';
	$media = hf_media( $id );
	if ( $media && 'youtube' === $media['kind'] ) {
		$out .= '<p><a href="' . esc_url( 'https://www.youtube.com/watch?v=' . $media['src'] ) . '" rel="noopener" target="_blank">Watch on YouTube</a></p>';
	}
	$out .= '</div>';

	$note = (string) hf_get( $id, 'cultural_note' );
	if ( $note ) {
		$out .= '<aside class="hf-note"><h2>Why this song exists</h2>' . wpautop( esc_html( $note ) ) . '</aside>';
	}
	return $out;
}

/**
 * Header for an artist or instrument page.
 *
 * @param array         $attrs Unused.
 * @param string        $content Unused.
 * @param WP_Block|null $block Block instance.
 * @return string
 */
function hf_render_entity_header( $attrs, $content = '', $block = null ) {
	$id   = hf_current_id( $block );
	$type = get_post_type( $id );
	if ( ! in_array( $type, array( 'artist', 'instrument' ), true ) ) {
		return '';
	}
	$img = hf_cover_url( $id, 'large' );
	$sub = 'artist' === $type
		? array( hf_get( $id, 'role' ) )
		: array( hf_get( $id, 'local_name' ), hf_get( $id, 'instrument_type' ) );

	$out = '<header class="hf-entity alignwide"><div class="hf-entity__media">';
	if ( $img ) {
		$out .= '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title( $id ) ) . '">';
	} else {
		$out .= '<span class="hf-card__initial" aria-hidden="true">' . esc_html( mb_substr( get_the_title( $id ), 0, 1 ) ) . '</span>';
	}
	$out .= '</div><div class="hf-entity__text"><p class="hf-eyebrow">' . esc_html( ucfirst( $type ) ) . '</p>';
	$out .= '<h1>' . esc_html( get_the_title( $id ) ) . '</h1>';
	$sub  = implode( ' · ', array_filter( array_map( 'strval', $sub ) ) );
	if ( $sub ) {
		$out .= '<p class="hf-entity__sub">' . esc_html( $sub ) . '</p>';
	}
	return $out . '</div></header>';
}

/**
 * Primary navigation with a mobile toggle.
 *
 * @return string
 */
function hf_render_site_nav() {
	$items = array(
		array( 'Music', get_post_type_archive_link( 'song' ), is_post_type_archive( 'song' ) || is_singular( 'song' ) ),
		array( 'Artists', get_post_type_archive_link( 'artist' ), is_post_type_archive( 'artist' ) || is_singular( 'artist' ) ),
		array( 'Instruments', get_post_type_archive_link( 'instrument' ), is_post_type_archive( 'instrument' ) || is_singular( 'instrument' ) ),
	);
	$stories = wp_count_posts( 'story' );
	if ( $stories && (int) $stories->publish > 0 ) {
		$items[] = array( 'Folklore', get_post_type_archive_link( 'story' ), is_post_type_archive( 'story' ) || is_singular( 'story' ) );
	}
	$items[] = array( 'About', home_url( '/about/' ), is_page( 'about' ) );

	$out  = '<nav class="hf-nav" aria-label="Primary">';
	$out .= '<button type="button" class="hf-nav__toggle" aria-expanded="false" aria-controls="hf-nav-list"><span class="screen-reader-text">Menu</span><span class="hf-nav__bars" aria-hidden="true"></span></button>';
	$out .= '<ul class="hf-nav__list" id="hf-nav-list">';
	foreach ( $items as $item ) {
		$out .= '<li><a href="' . esc_url( $item[1] ) . '"' . ( $item[2] ? ' aria-current="page"' : '' ) . '>' . esc_html( $item[0] ) . '</a></li>';
	}
	$out .= '<li class="hf-nav__cta"><a class="hf-btn hf-btn--small" href="' . esc_url( home_url( '/support/' ) ) . '"' . ( is_page( 'support' ) ? ' aria-current="page"' : '' ) . '>Support</a></li>';
	return $out . '</ul></nav>';
}

/**
 * The persistent player bar, printed once in the footer.
 */
function hf_render_player_bar() {
	?>
	<div class="hf-yt" id="hf-yt" hidden>
		<div id="hf-yt-holder"></div>
	</div>
	<div class="hf-player" id="hf-player" role="region" aria-label="Audio player" hidden>
		<div class="hf-player__cover" id="hf-player-cover" aria-hidden="true"></div>
		<div class="hf-player__info">
			<a class="hf-player__title" id="hf-player-title" href="#"></a>
			<span class="hf-player__sub" id="hf-player-sub"></span>
		</div>
		<div class="hf-player__controls">
			<button type="button" class="hf-ctl" id="hf-prev" aria-label="Previous recording"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 5h2v14H6zM9.5 12 19 5v14z"/></svg></button>
			<button type="button" class="hf-ctl hf-ctl--main" id="hf-toggle" aria-label="Play"><?php echo hf_icon( 'play' ) . hf_icon( 'pause' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<button type="button" class="hf-ctl" id="hf-next" aria-label="Next recording"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16 5h2v14h-2zM5 5l9.5 7L5 19z"/></svg></button>
		</div>
		<div class="hf-player__seek">
			<span id="hf-time-cur">0:00</span>
			<input type="range" id="hf-seek" min="0" max="100" step="0.1" value="0" aria-label="Seek">
			<span id="hf-time-dur">0:00</span>
		</div>
		<button type="button" class="hf-ctl hf-ctl--close" id="hf-close" aria-label="Close player"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.4 5 12 10.6 17.6 5 19 6.4 13.4 12 19 17.6 17.6 19 12 13.4 6.4 19 5 17.6 10.6 12 5 6.4z"/></svg></button>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Registration
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		$context = array( 'postId', 'postType' );
		$blocks  = array(
			'hunza/song-list'      => array(
				'callback'   => 'hf_render_song_list',
				'attributes' => array(
					'count'    => array( 'type' => 'integer', 'default' => 6 ),
					'filters'  => array( 'type' => 'boolean', 'default' => false ),
					'related'  => array( 'type' => 'boolean', 'default' => false ),
					'paginate' => array( 'type' => 'boolean', 'default' => false ),
					'heading'  => array( 'type' => 'string', 'default' => '' ),
				),
			),
			'hunza/featured-song'  => array( 'callback' => 'hf_render_featured_song' ),
			'hunza/card-grid'      => array(
				'callback'   => 'hf_render_card_grid',
				'attributes' => array(
					'postType' => array( 'type' => 'string', 'default' => 'instrument' ),
					'count'    => array( 'type' => 'integer', 'default' => 8 ),
				),
			),
			'hunza/song-hero'      => array( 'callback' => 'hf_render_song_hero' ),
			'hunza/song-facts'     => array( 'callback' => 'hf_render_song_facts' ),
			'hunza/entity-header'  => array( 'callback' => 'hf_render_entity_header' ),
			'hunza/site-nav'       => array( 'callback' => 'hf_render_site_nav' ),
		);
		foreach ( $blocks as $name => $def ) {
			register_block_type(
				$name,
				array(
					'api_version'     => 3,
					'title'           => ucwords( str_replace( '-', ' ', substr( $name, 6 ) ) ),
					'category'        => 'theme',
					'attributes'      => isset( $def['attributes'] ) ? $def['attributes'] : array(),
					'uses_context'    => $context,
					'render_callback' => $def['callback'],
				)
			);
		}
	}
);

/**
 * The blocks render on the server. This small script registers them in the
 * editor so the Site Editor previews them instead of showing "unsupported".
 */
add_action(
	'enqueue_block_editor_assets',
	static function () {
		wp_enqueue_script(
			'hunza-folk-editor',
			HF_URI . '/assets/js/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor' ),
			HF_VERSION,
			true
		);
	}
);
