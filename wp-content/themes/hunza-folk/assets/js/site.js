/**
 * Site behaviour: mobile menu, and in-place page swaps while audio is playing
 * so the player is never interrupted by a full page load.
 */
( function () {
	'use strict';

	/* ---------- Mobile menu ---------- */

	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest ? e.target.closest( '.hf-nav__toggle' ) : null;
		if ( toggle ) {
			var open = toggle.getAttribute( 'aria-expanded' ) !== 'true';
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			document.body.classList.toggle( 'hf-nav-open', open );
			return;
		}
		if ( document.body.classList.contains( 'hf-nav-open' ) && ! ( e.target.closest && e.target.closest( '.hf-nav' ) ) ) {
			closeMenu();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && document.body.classList.contains( 'hf-nav-open' ) ) {
			closeMenu();
			var t = document.querySelector( '.hf-nav__toggle' );
			if ( t ) {
				t.focus();
			}
		}
	} );

	function closeMenu() {
		document.body.classList.remove( 'hf-nav-open' );
		var t = document.querySelector( '.hf-nav__toggle' );
		if ( t ) {
			t.setAttribute( 'aria-expanded', 'false' );
		}
	}

	/* ---------- Keep playing while browsing ---------- */

	var skip = /\/(wp-admin|wp-login\.php|wp-json|wp-content|feed)(\/|$|\?)/;

	function swappable( a, e ) {
		if ( ! window.hfPlayer || ! window.hfPlayer.isActive() ) {
			return false;
		}
		if ( e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey ) {
			return false;
		}
		if ( ( a.target && a.target !== '_self' ) || a.hasAttribute( 'download' ) ) {
			return false;
		}
		var url = new URL( a.href, location.href );
		if ( url.origin !== location.origin || skip.test( url.pathname ) ) {
			return false;
		}
		if ( url.pathname === location.pathname && url.search === location.search ) {
			return false; // Same page or an in-page anchor.
		}
		return true;
	}

	function adopt( doc ) {
		var head = document.head;
		doc.querySelectorAll( 'link[rel="stylesheet"]' ).forEach( function ( l ) {
			if ( ! head.querySelector( 'link[href="' + l.getAttribute( 'href' ) + '"]' ) ) {
				head.appendChild( l.cloneNode( true ) );
			}
		} );
		doc.querySelectorAll( 'style[id]' ).forEach( function ( s ) {
			if ( ! document.getElementById( s.id ) ) {
				head.appendChild( s.cloneNode( true ) );
			}
		} );
	}

	function markCurrent() {
		var path = location.pathname.replace( /\/+$/, '' );
		document.querySelectorAll( '.hf-nav__list a' ).forEach( function ( a ) {
			var p = new URL( a.href ).pathname.replace( /\/+$/, '' );
			if ( p && path.indexOf( p ) === 0 && p !== '' ) {
				a.setAttribute( 'aria-current', 'page' );
			} else {
				a.removeAttribute( 'aria-current' );
			}
		} );
	}

	function go( href, push ) {
		fetch( href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'hf-swap' } } )
			.then( function ( r ) {
				var type = r.headers.get( 'content-type' ) || '';
				if ( ! r.ok || type.indexOf( 'text/html' ) === -1 ) {
					throw new Error( 'not swappable' );
				}
				return r.text();
			} )
			.then( function ( html ) {
				var doc = new DOMParser().parseFromString( html, 'text/html' );
				var next = doc.querySelector( 'main' );
				var cur = document.querySelector( 'main' );
				if ( ! next || ! cur ) {
					throw new Error( 'no main' );
				}
				adopt( doc );
				cur.replaceWith( document.importNode( next, true ) );
				document.title = doc.title;
				document.body.className = doc.body.className + ' hf-has-player';
				if ( push ) {
					history.pushState( { hf: true }, '', href );
				}
				window.scrollTo( 0, 0 );
				closeMenu();
				markCurrent();
				window.hfPlayer.sync();
				document.dispatchEvent( new CustomEvent( 'hf:navigated' ) );
				var main = document.querySelector( 'main' );
				if ( main ) {
					main.setAttribute( 'tabindex', '-1' );
					main.focus( { preventScroll: true } );
				}
			} )
			.catch( function () {
				location.href = href;
			} );
	}

	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest ? e.target.closest( 'a[href]' ) : null;
		if ( a && swappable( a, e ) ) {
			e.preventDefault();
			go( a.href, true );
		}
	} );

	window.addEventListener( 'popstate', function () {
		if ( window.hfPlayer && window.hfPlayer.isActive() ) {
			go( location.href, false );
		} else {
			location.reload();
		}
	} );
} )();
