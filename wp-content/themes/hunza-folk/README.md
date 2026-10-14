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

## Development

The theme can't run without WordPress, so `dev/` has an offline preview that stubs the database and renders the real templates and renderers to static pages:

```
php dev/test-import.php                 # markdown parser and importer scanning
php dev/render-preview.php              # writes dev/preview/*.html
python3 -m http.server 8123             # from the repo root
NODE_PATH=$(npm root -g) SHOTS=/tmp node dev/browser-test.js
```

The preview does not exercise real WordPress queries, the editor, or `wp hunza import` against a database. Check those on a staging site first.
