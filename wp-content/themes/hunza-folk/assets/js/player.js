/**
 * Hunza Folk persistent player.
 *
 * Any element with [data-hf-play] starts playback. Supports YouTube (through
 * the IFrame API, with the video kept visible as YouTube's terms require) and
 * plain audio files. Exposes window.hfPlayer for site.js.
 */
( function () {
	'use strict';

	var bar = document.getElementById( 'hf-player' );
	if ( ! bar ) {
		return;
	}

	var $ = function ( id ) {
		return document.getElementById( id );
	};
	var els = {
		cover: $( 'hf-player-cover' ),
		title: $( 'hf-player-title' ),
		sub: $( 'hf-player-sub' ),
		toggle: $( 'hf-toggle' ),
		prev: $( 'hf-prev' ),
		next: $( 'hf-next' ),
		close: $( 'hf-close' ),
		seek: $( 'hf-seek' ),
		cur: $( 'hf-time-cur' ),
		dur: $( 'hf-time-dur' ),
		yt: $( 'hf-yt' ),
	};

	var audio = new Audio();
	audio.preload = 'none';

	var state = {
		queue: [],
		index: -1,
		track: null,
		playing: false,
		yt: null,
		ytReady: false,
		ytLoading: false,
		pending: null,
		seeking: false,
		timer: null,
	};

	function fmt( s ) {
		if ( ! isFinite( s ) || s < 0 ) {
			s = 0;
		}
		var m = Math.floor( s / 60 );
		var r = Math.floor( s % 60 );
		return m + ':' + ( r < 10 ? '0' : '' ) + r;
	}

	function trackFrom( btn ) {
		return {
			id: btn.getAttribute( 'data-id' ),
			kind: btn.getAttribute( 'data-kind' ),
			src: btn.getAttribute( 'data-src' ),
			title: btn.getAttribute( 'data-title' ) || '',
			sub: btn.getAttribute( 'data-sub' ) || '',
			cover: btn.getAttribute( 'data-cover' ) || '',
			url: btn.getAttribute( 'data-url' ) || '#',
		};
	}

	/* ---------- YouTube ---------- */

	function loadYouTubeApi( then ) {
		if ( state.ytReady ) {
			then();
			return;
		}
		state.pending = then;
		if ( state.ytLoading ) {
			return;
		}
		state.ytLoading = true;
		var previous = window.onYouTubeIframeAPIReady;
		window.onYouTubeIframeAPIReady = function () {
			if ( typeof previous === 'function' ) {
				previous();
			}
			state.ytReady = true;
			var cb = state.pending;
			state.pending = null;
			if ( cb ) {
				cb();
			}
		};
		var s = document.createElement( 'script' );
		s.src = 'https://www.youtube.com/iframe_api';
		s.async = true;
		s.onerror = function () {
			state.ytLoading = false;
			setPlaying( false );
			els.sub.textContent = 'Could not load YouTube. Open the recording page to watch there.';
		};
		document.head.appendChild( s );
	}

	function playYouTube( id ) {
		els.yt.hidden = false;
		loadYouTubeApi( function () {
			if ( state.yt ) {
				state.yt.loadVideoById( id );
				return;
			}
			state.yt = new window.YT.Player( 'hf-yt-holder', {
				host: 'https://www.youtube-nocookie.com',
				videoId: id,
				width: '100%',
				height: '100%',
				playerVars: { autoplay: 1, playsinline: 1, rel: 0, modestbranding: 1 },
				events: {
					onReady: function ( e ) {
						e.target.playVideo();
					},
					onStateChange: function ( e ) {
						var S = window.YT.PlayerState;
						if ( e.data === S.PLAYING ) {
							setPlaying( true );
						} else if ( e.data === S.PAUSED ) {
							setPlaying( false );
						} else if ( e.data === S.ENDED ) {
							setPlaying( false );
							step( 1, true );
						}
					},
					onError: function () {
						setPlaying( false );
						els.sub.textContent = 'This video is unavailable. Open the recording page for details.';
					},
				},
			} );
		} );
	}

	/* ---------- Playback control ---------- */

	function stopAll() {
		audio.pause();
		if ( state.yt && state.yt.pauseVideo ) {
			try {
				state.yt.pauseVideo();
			} catch ( e ) {}
		}
	}

	function load( track, queue, index ) {
		stopAll();
		state.track = track;
		state.queue = queue || [ track ];
		state.index = index || 0;

		bar.hidden = false;
		document.body.classList.add( 'hf-has-player' );
		els.title.textContent = track.title;
		els.title.href = track.url;
		els.sub.textContent = track.sub;
		els.cover.style.backgroundImage = track.cover ? 'url("' + track.cover.replace( /"/g, '%22' ) + '")' : '';
		els.seek.value = 0;
		els.cur.textContent = '0:00';
		els.dur.textContent = '0:00';
		els.prev.disabled = state.queue.length < 2;
		els.next.disabled = state.queue.length < 2;

		if ( track.kind === 'youtube' ) {
			audio.removeAttribute( 'src' );
			playYouTube( track.src );
		} else {
			els.yt.hidden = true;
			audio.src = track.src;
			var p = audio.play();
			if ( p && p.catch ) {
				p.catch( function () {
					setPlaying( false );
				} );
			}
		}
		setMediaSession( track );
		sync();
	}

	function toggle() {
		if ( ! state.track ) {
			return;
		}
		if ( state.track.kind === 'youtube' ) {
			if ( ! state.yt || ! state.yt.getPlayerState ) {
				return;
			}
			if ( state.playing ) {
				state.yt.pauseVideo();
			} else {
				state.yt.playVideo();
			}
			return;
		}
		if ( audio.paused ) {
			audio.play();
		} else {
			audio.pause();
		}
	}

	function step( dir, auto ) {
		var q = state.queue;
		if ( q.length < 2 ) {
			return;
		}
		var i = state.index + dir;
		if ( i < 0 || i >= q.length ) {
			if ( auto ) {
				return;
			}
			i = ( i + q.length ) % q.length;
		}
		load( q[ i ], q, i );
	}

	function close() {
		stopAll();
		if ( state.yt && state.yt.stopVideo ) {
			try {
				state.yt.stopVideo();
			} catch ( e ) {}
		}
		state.track = null;
		state.playing = false;
		bar.hidden = true;
		els.yt.hidden = true;
		document.body.classList.remove( 'hf-has-player' );
		sync();
	}

	function setPlaying( on ) {
		state.playing = on;
		bar.classList.toggle( 'is-playing', on );
		els.toggle.setAttribute( 'aria-label', on ? 'Pause' : 'Play' );
		if ( 'mediaSession' in navigator ) {
			navigator.mediaSession.playbackState = on ? 'playing' : 'paused';
		}
		sync();
	}

	/** Mirror the current track and play state onto every play button and row. */
	function sync() {
		var id = state.track ? state.track.id : null;
		var buttons = document.querySelectorAll( '[data-hf-play]' );
		for ( var i = 0; i < buttons.length; i++ ) {
			var b = buttons[ i ];
			var current = id !== null && b.getAttribute( 'data-id' ) === id;
			b.classList.toggle( 'is-current', current );
			b.classList.toggle( 'is-playing', current && state.playing );
			b.setAttribute( 'aria-pressed', current && state.playing ? 'true' : 'false' );
			var row = b.closest( '.hf-track' );
			if ( row ) {
				row.classList.toggle( 'is-current', current );
				row.classList.toggle( 'is-playing', current && state.playing );
			}
		}
	}

	function setMediaSession( track ) {
		if ( ! ( 'mediaSession' in navigator ) || typeof MediaMetadata === 'undefined' ) {
			return;
		}
		navigator.mediaSession.metadata = new MediaMetadata( {
			title: track.title,
			artist: track.sub,
			album: 'Hunza Folk',
			artwork: track.cover ? [ { src: track.cover } ] : [],
		} );
		navigator.mediaSession.setActionHandler( 'play', toggle );
		navigator.mediaSession.setActionHandler( 'pause', toggle );
		navigator.mediaSession.setActionHandler( 'previoustrack', function () {
			step( -1 );
		} );
		navigator.mediaSession.setActionHandler( 'nexttrack', function () {
			step( 1 );
		} );
	}

	/* ---------- Progress ---------- */

	function position() {
		if ( ! state.track ) {
			return { t: 0, d: 0 };
		}
		if ( state.track.kind === 'youtube' ) {
			if ( state.yt && state.yt.getCurrentTime ) {
				return { t: state.yt.getCurrentTime(), d: state.yt.getDuration() };
			}
			return { t: 0, d: 0 };
		}
		return { t: audio.currentTime, d: audio.duration };
	}

	function tick() {
		if ( state.seeking ) {
			return;
		}
		var p = position();
		els.cur.textContent = fmt( p.t );
		els.dur.textContent = fmt( p.d );
		els.seek.value = p.d > 0 ? ( p.t / p.d ) * 100 : 0;
	}
	state.timer = window.setInterval( tick, 500 );

	els.seek.addEventListener( 'input', function () {
		state.seeking = true;
		var p = position();
		els.cur.textContent = fmt( ( els.seek.value / 100 ) * p.d );
	} );
	els.seek.addEventListener( 'change', function () {
		var p = position();
		var t = ( els.seek.value / 100 ) * p.d;
		if ( state.track && state.track.kind === 'youtube' ) {
			if ( state.yt && state.yt.seekTo ) {
				state.yt.seekTo( t, true );
			}
		} else if ( isFinite( t ) ) {
			audio.currentTime = t;
		}
		state.seeking = false;
	} );

	audio.addEventListener( 'play', function () {
		setPlaying( true );
	} );
	audio.addEventListener( 'pause', function () {
		setPlaying( false );
	} );
	audio.addEventListener( 'ended', function () {
		setPlaying( false );
		step( 1, true );
	} );
	audio.addEventListener( 'error', function () {
		if ( audio.getAttribute( 'src' ) ) {
			setPlaying( false );
			els.sub.textContent = 'This audio file could not be played.';
		}
	} );

	/* ---------- Wiring ---------- */

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '[data-hf-play]' ) : null;
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		var track = trackFrom( btn );
		if ( state.track && state.track.id === track.id ) {
			toggle();
			return;
		}
		var scope = btn.closest( '[data-hf-queue]' );
		var queue = [];
		var index = 0;
		if ( scope ) {
			var list = scope.querySelectorAll( '[data-hf-play]' );
			for ( var i = 0; i < list.length; i++ ) {
				queue.push( trackFrom( list[ i ] ) );
				if ( list[ i ] === btn ) {
					index = i;
				}
			}
		} else {
			queue = [ track ];
		}
		load( track, queue, index );
	} );

	els.toggle.addEventListener( 'click', toggle );
	els.prev.addEventListener( 'click', function () {
		step( -1 );
	} );
	els.next.addEventListener( 'click', function () {
		step( 1 );
	} );
	els.close.addEventListener( 'click', close );

	window.hfPlayer = {
		sync: sync,
		isActive: function () {
			return !! state.track;
		},
	};
} )();
