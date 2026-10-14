/**
 * End-to-end checks against a real WordPress install with the theme active and
 * the sample content imported (see the theme README, "Testing in WordPress").
 *
 *   BASE=http://127.0.0.1:8090 ADMIN_USER=admin ADMIN_PASS=... \
 *   NODE_PATH=$(npm root -g) node dev/wp-browser-test.js
 *
 * Needs an audio file reachable at $BASE/wp-content/uploads/tone-a.wav.
 */
const { chromium } = require( 'playwright' );

const BASE = process.env.BASE || 'http://127.0.0.1:8090';
const USER = process.env.ADMIN_USER || 'admin';
const PASS = process.env.ADMIN_PASS || 'admin';
const SHOTS = process.env.SHOTS || '.';
let failed = 0;

function check( name, ok, detail ) {
	console.log( ( ok ? 'PASS' : 'FAIL' ) + '  ' + name + ( ok || ! detail ? '' : '  -> ' + detail ) );
	if ( ! ok ) {
		failed++;
	}
}

async function postId( page, slug, type ) {
	const html = await ( await page.request.get( `${ BASE }/wp-json/wp/v2/${ type }?slug=${ slug }` ) ).json();
	return html[ 0 ] && html[ 0 ].id;
}

( async () => {
	const browser = await chromium.launch( { args: [ '--autoplay-policy=no-user-gesture-required' ] } );
	const ctx = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( 'pageerror: ' + e ) );

	/* ---------- Admin: log in and edit a song through the real editor ---------- */
	await page.goto( `${ BASE }/wp-login.php` );
	await page.fill( '#user_login', USER );
	await page.fill( '#user_pass', PASS );
	await page.click( '#wp-submit' );
	await page.waitForURL( '**/wp-admin/**' );
	check( 'admin: logged in', page.url().includes( '/wp-admin' ) );

	const songThree = await postId( page, 'sample-song-three', 'song' );
	const songOne = await postId( page, 'sample-song-one', 'song' );
	check( 'rest: custom post types exposed', !! songThree && !! songOne, `${ songOne } ${ songThree }` );

	async function editSong( id, fill ) {
		await page.goto( `${ BASE }/wp-admin/post.php?post=${ id }&action=edit` );
		await page.waitForSelector( '.editor-header', { timeout: 60000 } );
		await page.waitForTimeout( 1500 );
		// Dismiss the welcome guide if shown, then expand the collapsed "Meta Boxes" panel.
		const close = page.locator( '.components-modal__header button[aria-label="Close"]' );
		if ( await close.count() ) {
			await close.first().click();
		}
		const toggle = page.locator( 'button[aria-expanded]:has-text("Meta Boxes")' ).first();
		if ( ( await toggle.getAttribute( 'aria-expanded' ) ) === 'false' ) {
			await toggle.click( { position: { x: 30, y: 10 } } );
		}
		await page.waitForSelector( '#hf-details', { state: 'visible', timeout: 30000 } );
		await fill();
		await page.screenshot( { path: `${ SHOTS }/admin-metabox.png`, fullPage: true } );
		await page.click( 'button.editor-post-publish-button' );
		await page.waitForSelector( '.components-snackbar', { timeout: 30000 } );
		await page.waitForTimeout( 2500 ); // let the meta box save request finish.
	}

	await editSong( songThree, async () => {
		check( 'admin: Hunza Folk details meta box present with all song fields', ( await page.locator( '#hf-details [name^="hf_"]' ).count() ) >= 10 );
		await page.selectOption( '#hf_media_type', 'file' );
		await page.fill( '#hf_audio_file', `${ BASE }/wp-content/uploads/tone-a.wav` );
		await page.check( '#hf_featured' );
	} );
	await editSong( songOne, async () => {
		await page.selectOption( '#hf_media_type', 'youtube' );
		await page.fill( '#hf_youtube_id', 'https://www.youtube.com/watch?v=aaaaaaaaaaa&t=3s' );
		await page.uncheck( '#hf_featured' );
	} );

	// Read back through the front end instead of the database: featured hero and play data attributes.
	await page.goto( `${ BASE }/` );
	check( 'save: featured flag moved to Sample Song Three', ( await page.locator( '.hf-hero__song' ).textContent() ) === 'Sample Song Three' );
	check( 'save: audio-file song has a play button with the file URL', ( await page.locator( '.hf-hero button[data-hf-play]' ).getAttribute( 'data-src' ) ).endsWith( 'tone-a.wav' ) );
	await page.goto( `${ BASE }/music/` );
	const yt = await page.locator( '.hf-track:has-text("Sample Song One") button[data-hf-play]' ).getAttribute( 'data-src' );
	check( 'save: YouTube URL was sanitised to a bare video ID', yt === 'aaaaaaaaaaa', yt );

	/* ---------- Front end: playback and persistent navigation ---------- */
	await page.goto( `${ BASE }/` );
	await page.screenshot( { path: `${ SHOTS }/wp-home.png`, fullPage: true } );
	check( 'layout: no strip between header and hero', await page.evaluate( () => {
		const h = document.querySelector( 'header.wp-block-template-part' ).getBoundingClientRect();
		const hero = document.querySelector( '.hf-hero' ).getBoundingClientRect();
		return Math.abs( hero.top - h.bottom ) < 1;
	} ) );
	await page.click( '.hf-hero .hf-play--xl' );
	await page.waitForSelector( '#hf-player:not([hidden])' );
	await page.waitForTimeout( 2200 );
	check( 'play: audio file plays and progress advances', ( await page.evaluate( () => document.getElementById( 'hf-player' ).classList.contains( 'is-playing' ) ) ) && ( await page.textContent( '#hf-time-cur' ) ) !== '0:00' );

	await page.evaluate( () => {
		window.__marker = 'same-document';
	} );
	await page.click( '.hf-nav__list a:has-text("Instruments")' );
	await page.waitForURL( '**/instruments/' );
	await page.waitForSelector( '.hf-cards--instrument' );
	check( 'swap: navigated without reload while playing', ( await page.evaluate( () => window.__marker ) ) === 'same-document' && ( await page.evaluate( () => document.getElementById( 'hf-player' ).classList.contains( 'is-playing' ) ) ) );
	check( 'swap: page title updated', ( await page.title() ).startsWith( 'Instruments' ) );
	await page.click( '.hf-cards--instrument a:has-text("Dadang")' );
	await page.waitForURL( '**/instruments/dadang/' );
	await page.waitForSelector( '.hf-entity' );
	check( 'swap: single instrument page, still playing', await page.evaluate( () => document.getElementById( 'hf-player' ).classList.contains( 'is-playing' ) ) );
	check( 'related: Dadang lists songs that feature it (real meta query)', ( await page.locator( '.hf-tracklist .hf-track' ).count() ) >= 2 );
	await page.screenshot( { path: `${ SHOTS }/wp-instrument.png`, fullPage: true } );

	/* ---------- Filters use real meta queries ---------- */
	await page.goto( `${ BASE }/music/` );
	check( 'archive: filters rendered with real options', ( await page.locator( '.hf-filter select[name="hf_language"] option' ).count() ) >= 4 );
	await page.goto( `${ BASE }/music/?hf_language=Shina` );
	check( 'filter: language=Shina shows only the Shina song', ( await page.locator( '.hf-track' ).count() ) === 1 && ( await page.locator( '.hf-track' ).first().textContent() ).includes( 'Sample Song Two' ) );
	const dadang = await postId( page, 'dadang', 'instrument' );
	await page.goto( `${ BASE }/music/?hf_instrument=${ dadang }` );
	check( 'filter: instrument=Dadang shows the two Dadang songs', ( await page.locator( '.hf-track' ).count() ) === 2 );
	await page.goto( `${ BASE }/music/` );
	await page.selectOption( 'select[name="hf_instrument"]', { label: 'Dadang' } );
	await page.click( '.hf-filters button[type="submit"]' );
	await page.waitForURL( '**hf_instrument=**' );
	check( 'filter form: choosing Dadang and pressing Filter works (not a 404)', ( await page.locator( '.hf-track' ).count() ) === 2 && ( await page.title() ).startsWith( 'Songs' ), await page.title() );
	check( 'filter form: selection is remembered and Clear link shown', ( await page.locator( 'select[name="hf_instrument"] option:checked' ).textContent() ) === 'Dadang' && ( await page.locator( '.hf-filters__reset' ).count() ) === 1 );
	await page.goto( `${ BASE }/music/?hf_language=Nothing` );
	check( 'filter: no match shows a friendly message', ( await page.locator( '.hf-empty' ).count() ) === 1 );

	/* ---------- Pages ---------- */
	for ( const [ path, sel ] of [ [ '/about/', 'h1' ], [ '/support/', 'h1' ], [ '/artists/', '.hf-cards--artist' ], [ '/folklore/', '.hf-empty' ], [ '/music/sample-song-two/', '.hf-hero--song' ], [ '/nope-404/', 'h1' ] ] ) {
		const res = await page.goto( BASE + path );
		check( `page ${ path } renders (${ res.status() })`, ( await page.locator( sel ).count() ) >= 1 );
	}
	const nf = await page.goto( BASE + '/nope-404/' );
	check( '404 template used with correct status', nf.status() === 404 && ( await page.textContent( 'h1' ) ).includes( 'gone quiet' ) );

	/* ---------- Site Editor loads the templates ---------- */
	await page.goto( `${ BASE }/wp-admin/site-editor.php?p=%2Fwp_template%2Fhunza-folk%2F%2Ffront-page&canvas=edit` );
	await page.waitForSelector( 'iframe[name="editor-canvas"]', { timeout: 60000 } );
	const frame = page.frameLocator( 'iframe[name="editor-canvas"]' );
	await frame.locator( '.hf-hero, [data-type="hunza/featured-song"]' ).first().waitFor( { timeout: 60000 } );
	await page.waitForTimeout( 3000 );
	const guide = page.locator( '.components-modal__header button[aria-label="Close"]' );
	if ( await guide.count() ) {
		await guide.first().click();
	}
	await page.waitForTimeout( 500 );
	await page.screenshot( { path: `${ SHOTS }/wp-site-editor.png` } );
	check( 'site editor: theme stylesheet applied to the preview (nav list unstyled bullets gone)', ( await frame.locator( '.hf-nav__list' ).first().evaluate( ( el ) => getComputedStyle( el ).listStyleType ) ) === 'none' );
	const unsupported = await frame.locator( '.block-editor-warning, .wp-block-missing' ).count();
	check( 'site editor: front-page template loads with no invalid/unsupported blocks', unsupported === 0, String( unsupported ) );
	check( 'site editor: server-rendered hero shown in editor', ( await frame.locator( '.hf-hero__title' ).count() ) >= 1 );

	check( 'no uncaught page errors', errors.length === 0, errors.join( ' | ' ) );
	await browser.close();
	console.log( failed ? `\n${ failed } FAILED` : '\nAll passed' );
	process.exit( failed ? 1 : 0 );
} )();
