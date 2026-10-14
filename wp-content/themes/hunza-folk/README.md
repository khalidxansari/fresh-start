# Hunza Folk theme

A dark, listening-first WordPress block theme for the Hunza Folk music archive. It needs WordPress 6.4+ and PHP 7.4+. It has no build step and no plugin dependencies.

## What it does

- **Listening comes first.** The home page opens on a featured recording with a large play button. Songs appear as playable rows, not cards.
- **Persistent player.** A bar at the bottom keeps playing while visitors browse. Play buttons work anywhere on the site. Recordings can be YouTube videos or audio files.
- **Content types.** Songs, Artists, Instruments and Folklore (stories), with fields for performers, instruments, location, year, language, Urdu title and a cultural note.
- **Songs link to artists and instruments.** Each artist and instrument page lists the recordings that feature them.
- **Importer.** `wp hunza import` loads your markdown "Website Copy" files.

## Install

1. Zip the `hunza-folk` folder, or copy it to `wp-content/themes/`.
2. In WordPress go to **Appearance > Themes** and activate **Hunza Folk**. Activation flushes permalinks, so the `/music/`, `/artists/` and `/instruments/` URLs work straight away.
3. Create pages with the slugs `about` and `support`. The header links to them (the importer below can create them).
4. Upload your logo under **Appearance > Editor > Header** (the Site Logo block). The theme inverts the logo to white for the dark background. If your logo is already light, set `--hf-logo-filter: none;` in `style.css`.
5. Open **Appearance > Editor > Footer** and **Patterns** to set the real social media links. They currently point to the platforms' home pages.

## Import your copy

Put your markdown files in a folder and run:

```
wp hunza import /path/to/Website-Copy --dry-run     # preview, changes nothing
wp hunza import /path/to/Website-Copy               # creates drafts
wp hunza import /path/to/Website-Copy --status=publish
```

Posts are created as **drafts** by default, so nothing goes live unreviewed. Running it again updates posts with the same type and slug.

Each file starts with front matter. `type` and `title` are required:

```
---
type: song                  # song | artist | instrument | story | page
title: Song title
title_ur: اردو عنوان
language: Burushaski
location: Village or valley
year: 2015
performers: [Artist Name]   # must match artist titles exactly
instruments: [Dadang]       # must match instrument titles exactly
youtube: https://youtu.be/XXXXXXXXXXX
# audio: https://example.com/wp-content/uploads/song.mp3
note: Why this song exists.
featured: true              # shown in the home page hero
---

Body text in markdown.
```

| type | extra keys |
| --- | --- |
| artist | `role` |
| instrument | `local_name`, `instrument_type` |
| page | `slug` |

Any type also accepts `slug` and `excerpt`. Files are imported instruments first, then artists, songs, stories and pages, so songs can link to them by name. Raw HTML in markdown is escaped, not published. The `content/` folder holds placeholder examples. **Replace them with your real copy.**

## Audio

A song with no media shows "Recording coming soon". Set one of:

- `youtube`: the video plays in the player bar. YouTube requires its video to stay visible, so a small video panel appears above the bar while a YouTube recording plays. This is deliberate.
- `audio`: a direct URL to an MP3, M4A or OGG file, for example one from the Media Library.

The site does not depend on Spotify or DistroKid.

## Editing songs

Open a song, scroll to the bottom of the editor and click **Meta Boxes** (WordPress keeps that panel collapsed). The **Hunza Folk details** box holds the Urdu title, language, location, year, cultural note, media source, YouTube link or audio URL, performers, instruments, and the "Feature on the home page" tick box. You can paste a full YouTube link; the theme stores just the video ID. Edit the home page layout and the footer links under **Appearance > Editor**.

## Testing

The theme has been run and tested in real WordPress (7.1.3, SQLite). To build the same throwaway site yourself:

```
dev/setup-wp-test-site.sh /some/empty/folder 8090
php -S 127.0.0.1:8090 -t /some/empty/folder/site /some/empty/folder/router.php
BASE=http://127.0.0.1:8090 ADMIN_PASS=admin-test-pw SHOTS=/tmp \
  NODE_PATH=$(npm root -g) node dev/wp-browser-test.js
```

That script installs WordPress and WP-CLI with Composer and fetches the SQLite plugin from GitHub, so it needs network access to packagist.org and github.com. The browser test logs in, edits songs through the real editor, plays audio, checks navigation while playing, filters, 404s, and that the Site Editor loads every template.

There is also an offline preview that stubs the database. It is faster, but it does not run WordPress, so use the real-site test for anything that touches queries or the editor:

```
php dev/test-import.php                 # markdown parser and importer scanning
php dev/render-preview.php              # writes dev/preview/*.html
python3 -m http.server 8123             # from the repo root
NODE_PATH=$(npm root -g) SHOTS=/tmp node dev/browser-test.js
```

## Known limits

- Not yet tested on a live host, with a caching plugin, or with YouTube playback itself. The sandbox could not reach YouTube, so only YouTube's failure path was exercised.
- The social and support links are placeholders until you set them.
- Filtering uses the `hf_instrument`, `hf_performer` and `hf_language` URL parameters. Bare names like `?instrument=` clash with WordPress's own query variables.
