/**
 * Headless-browser checks against the static preview (see render-preview.php).
 *
 *   php dev/render-preview.php
 *   python3 -m http.server 8123 &            # from the repo root
 *   NODE_PATH=$(npm root -g) SHOTS=/some/dir node dev/browser-test.js
 */
const { chromium } = require( 'playwright' );

const BASE = process.env.BASE || 'http://127.0.0.1:8123/dev/preview/';
const SHOTS = process.env.SHOTS || '.';
let failed = 0;

function check( name, ok, detail ) {
	console.log( ( ok ? 'PASS' : 'FAIL' ) + '  ' + name + ( ok || ! detail ? '' : '  -> ' + detail ) );
	if ( ! ok ) {
		failed++;
	}
}

( async () => {
	const browser = await chromium.launch( { args: [ '--autoplay-policy=no-user-gesture-required' ] } );
	const errors = [];

	/* ---------------- Desktop ---------------- */
	const ctx = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
	const page = await ctx.newPage();
	page.on( 'pageerror', ( e ) => errors.push( String( e ) ) );

	await page.goto( BASE + 'index.html' );
	await page.screenshot( { path: `${ SHOTS }/home-desktop.png`, fullPage: true } );

	check( 'home: hero present with featured play button', ( await page.locator( '.hf-hero .hf-play--xl' ).count() ) === 1 );
	check( 'home: track rows rendered', ( await page.locator( '.hf-track' ).count() ) >= 3 );
	check( 'home: instrument + artist cards', ( await page.locator( '.hf-cards--instrument li' ).count() ) === 4 && ( await page.locator( '.hf-cards--artist li' ).count() ) === 3 );
	check( 'home: no horizontal overflow', await page.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth ) );
	check( 'home: player hidden before first play', await page.locator( '#hf-player' ).isHidden() );

	// Play the featured recording (audio file).
	await page.click( '.hf-hero .hf-play--xl' );
	await page.waitForSelector( '#hf-player:not([hidden])' );
	await page.waitForFunction( () => document.getElementById( 'hf-player' ).classList.contains( 'is-playing' ), null, { timeout: 5000 } ).catch( () => {} );
	check( 'play: player bar shown and playing', await page.evaluate( () => document.getElementById( 'hf-player' ).classList.contains( 'is-playing' ) ) );
	check( 'play: title in bar', ( await page.textContent( '#hf-player-title' ) ) === 'Sample Song One' );
	check( 'play: hero button shows pause state', await page.locator( '.hf-hero .hf-play--xl.is-playing' ).count() === 1 );
	check( 'play: body padded for bar', await page.evaluate( () => parseFloat( getComputedStyle( document.body ).paddingBottom ) >= 70 ) );

	await page.waitForTimeout( 2200 );
	const t = await page.textContent( '#hf-time-cur' );
	check( 'play: progress advances', t !== '0:00', t );
	await page.screenshot( { path: `${ SHOTS }/home-playing.png` } );

	// Pause / resume through the bar.
	const playing = () => document.getElementById( 'hf-player' ).classList.contains( 'is-playing' );
	await page.click( '#hf-toggle' );
	check( 'pause: bar toggles to paused', await page.waitForFunction( () => ! document.getElementById( 'hf-player' ).classList.contains( 'is-playing' ), null, { timeout: 3000 } ).then( () => true, () => false ) );
	await page.click( '#hf-toggle' );
	check( 'resume: playing again', await page.waitForFunction( playing, null, { timeout: 3000 } ).then( () => true, () => false ) );

	// Navigate while playing: no reload, music keeps going.
	await page.evaluate( () => {
		window.__marker = 'same-document';
	} );
	await page.click( '.hf-nav__list a[href="music.html"]' );
	await page.waitForURL( '**/music.html' );
	await page.waitForSelector( '.hf-filters' );
	check( 'swap: still same document (no reload)', ( await page.evaluate( () => window.__marker ) ) === 'same-document' );
	check( 'swap: title updated', ( await page.title() ) === 'Music' );
	check( 'swap: still playing', await page.evaluate( () => document.getElementById( 'hf-player' ).classList.contains( 'is-playing' ) ) );
	check( 'swap: current row highlighted on new page', await page.locator( '.hf-track.is-playing' ).count() === 1 );
	check( 'swap: nav marks Music current', ( await page.getAttribute( '.hf-nav__list a[href="music.html"]', 'aria-current' ) ) === 'page' );
	await page.screenshot( { path: `${ SHOTS }/music-playing.png`, fullPage: true } );

	// Back button restores previous page and keeps playing.
	await page.goBack();
	await page.waitForSelector( '.hf-hero' );
	check( 'back: home restored while playing', await page.evaluate( () => document.getElementById( 'hf-player' ).classList.contains( 'is-playing' ) ) );
	await page.goForward();
	await page.waitForSelector( '.hf-filters' );

	// A hero play has no queue; starting from a list row builds one from the playable rows.
	check( 'queue: single track has next disabled', await page.evaluate( () => document.getElementById( 'hf-next' ).disabled ) );
	await page.click( '.hf-track:has-text("Sample Song Three") .hf-play' );
	check( 'queue: row play enables next/prev', await page.evaluate( () => ! document.getElementById( 'hf-next' ).disabled && ! document.getElementById( 'hf-prev' ).disabled ) );
	check( 'queue: playing the Song Three row', ( await page.textContent( '#hf-player-title' ) ).startsWith( 'Sample Song Three' ) );
	await page.click( '#hf-next' );
	check( 'queue: next skips unplayable rows and wraps', ( await page.textContent( '#hf-player-title' ) ) === 'Sample Song One' );
	await page.click( '#hf-prev' );
	check( 'queue: prev goes back', ( await page.textContent( '#hf-player-title' ) ).startsWith( 'Sample Song Three' ) );

	// Song without media shows "coming soon" and no play button.
	await page.goto( BASE + 'song-33.html' );
	check( 'no media: shows coming soon, no play button', ( await page.locator( '.hf-hero .hf-play--soon' ).count() ) === 1 && ( await page.locator( '.hf-hero button[data-hf-play]' ).count() ) === 0 );
	await page.screenshot( { path: `${ SHOTS }/song-nomedia.png`, fullPage: true } );

	// Song page with facts.
	await page.goto( BASE + 'song-30.html' );
	check( 'song: facts and note rendered', ( await page.locator( '.hf-facts dt' ).count() ) >= 4 && ( await page.locator( '.hf-note' ).count() ) === 1 );
	check( 'song: related list present', ( await page.locator( '.hf-list-heading' ).count() ) === 1 );
	await page.screenshot( { path: `${ SHOTS }/song-desktop.png`, fullPage: true } );

	// YouTube track: panel must be visible (YouTube terms), and failure is reported, not silent.
	await page.goto( BASE + 'music.html' );
	await page.click( '.hf-track:has-text("Sample Song Two") .hf-play' );
	await page.waitForTimeout( 2500 );
	const yt = await page.locator( '#hf-yt' ).boundingBox();
	check( 'youtube: video panel visible and at least 200x200', !! yt && yt.width >= 200 && yt.height >= 200, JSON.stringify( yt ) );
	const sub = await page.textContent( '#hf-player-sub' );
	check( 'youtube: offline failure is reported to the user', /could not load youtube|unavailable/i.test( sub ), sub );

	// Switching back to a file track hides the panel.
	await page.click( '.hf-track:has-text("Sample Artist One") .hf-play >> nth=0' ).catch( () => {} );
	await page.click( '.hf-track:has-text("Sample Song One") .hf-play' );
	await page.waitForTimeout( 400 );
	check( 'youtube panel hidden for audio file tracks', await page.locator( '#hf-yt' ).isHidden() );

	// Close.
	await page.click( '#hf-close' );
	check( 'close: player hidden, padding removed', ( await page.locator( '#hf-player' ).isHidden() ) && ! ( await page.evaluate( () => document.body.classList.contains( 'hf-has-player' ) ) ) );

	// Other pages (visual only).
	for ( const p of [ 'instruments', 'artists', 'artist-20', 'instrument-10' ] ) {
		await page.goto( BASE + p + '.html' );
		await page.screenshot( { path: `${ SHOTS }/${ p }-desktop.png`, fullPage: true } );
		check( p + ': no horizontal overflow', await page.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth ) );
	}

	/* ---------------- Mobile ---------------- */
	const mctx = await browser.newContext( { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true } );
	const m = await mctx.newPage();
	m.on( 'pageerror', ( e ) => errors.push( String( e ) ) );
	await m.goto( BASE + 'index.html' );
	check( 'mobile: no horizontal overflow (home)', await m.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth ) );
	check( 'mobile: menu toggle visible, links hidden', ( await m.locator( '.hf-nav__toggle' ).isVisible() ) && ! ( await m.locator( '.hf-nav__list' ).isVisible() ) );
	await m.tap( '.hf-nav__toggle' );
	check( 'mobile: menu opens', await m.locator( '.hf-nav__list' ).isVisible() );
	await m.screenshot( { path: `${ SHOTS }/home-mobile-menu.png` } );
	await m.keyboard.press( 'Escape' );
	check( 'mobile: Escape closes menu', ! ( await m.locator( '.hf-nav__list' ).isVisible() ) );
	await m.screenshot( { path: `${ SHOTS }/home-mobile.png`, fullPage: true } );

	await m.tap( '.hf-hero .hf-play--xl' );
	await m.waitForSelector( '#hf-player:not([hidden])' );
	await m.screenshot( { path: `${ SHOTS }/home-mobile-playing.png` } );
	check( 'mobile: player bar fits viewport', await m.evaluate( () => document.getElementById( 'hf-player' ).getBoundingClientRect().right <= window.innerWidth + 1 ) );
	await m.goto( BASE + 'music.html' );
	check( 'mobile: no horizontal overflow (music)', await m.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth ) );
	await m.screenshot( { path: `${ SHOTS }/music-mobile.png`, fullPage: true } );

	check( 'no uncaught page errors', errors.length === 0, errors.join( ' | ' ) );

	await browser.close();
	console.log( failed ? `\n${ failed } FAILED` : '\nAll passed' );
	process.exit( failed ? 1 : 0 );
} )();
