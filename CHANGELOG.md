# Changelog

All notable changes to the Podcast plugin for Bludit are documented in this
file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the plugin uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

First public release.

### Added

- Episodes are regular Bludit pages with the tag `podcast`. Audio, duration,
  episode and season number, episode type, explicit flag and artwork are
  stored as Bludit custom fields, so episodes work with your theme, categories,
  tags and the Bludit editor.
- Admin page **Podcast** for admins and editors: create, edit and delete
  episodes in the look of the Bludit admin, also on phones. Drafts, scheduled,
  sticky and static episodes are supported; dates use the website's time zone.
- Audio (MP3, M4A, OGG, WAV) and episode artwork (JPG, PNG) by URL or upload.
  The upload limits of the server are shown and checked before uploading.
- Channel settings (admins only): title, description, author, language, cover
  (URL or upload, checked against Apple's requirements), copyright, Apple
  Podcasts category, podcast type (episodic or serial), explicit flag, owner
  email, feed lock, and links to the podcast on Apple Podcasts, Spotify,
  Pocket Casts, Deezer and Castbox.
- RSS feed with iTunes tags that passes Apple's checks: show notes as HTML,
  file size of external audio files, `podcast:guid` and `podcast:locked`
  (Podcasting 2.0), a feed discovery link on every page and an optional
  different feed URL, e.g. for a CDN.
- Podlove Web Player 5.13, served from your own server without a CDN, placed
  in the page content above or below the text or at `{{ podcast-player }}`,
  with a subscribe button for podcast apps and the services above. A simple
  HTML5 player is available as an alternative and as fallback.
- Admin interface in English and German (Germany, Austria, Switzerland),
  following the language of the Bludit site.
- Works with Bludit 3.19 to 3.22, also in a subdirectory, and PHP 8.0 or newer.

### Security

- Changes to episodes go through the Bludit admin area with its login, role
  and CSRF checks.
- Uploads are checked for size, file extension and content, stored with a
  random file name and in folders that do not run scripts.
- The file size of external audio files is only requested from public
  addresses, with a time limit.
- All output is escaped for its context; show notes in the feed are limited to
  a safe set of HTML tags.
