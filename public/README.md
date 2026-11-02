# Bludit Plugin: Podcast

![Episode list in the Bludit admin panel](screenshot.png)

Run a podcast with [Bludit](https://www.bludit.com): manage episodes in the admin panel, publish an RSS feed that is ready for Apple Podcasts and Spotify, and play episodes on your site with the Podlove Web Player – served from your own server.

## Features

- **Episodes are regular Bludit pages** with the tag `podcast`, so they work with your theme, categories, tags and the Bludit editor. Episode details (number, season, type, duration, audio, artwork) are stored as Bludit custom fields.
- **Admin page** to create, edit and delete episodes – drafts and scheduled episodes included. Upload audio and episode artwork or link external files; uploads are checked for size, file type and content. Matches the Bludit admin look and works on phones.
- **Channel settings** with a cover upload that checks Apple's requirements, the Apple Podcasts category list, podcast type and links to your podcast on Apple Podcasts, Spotify, Pocket Casts, Deezer and Castbox.
- **RSS feed** at `/podcast.xml` (configurable) with iTunes tags, show notes as HTML, file sizes of external audio files and Podcasting 2.0 tags (`podcast:guid`, `podcast:locked`).
- **Player on episode pages:** Podlove Web Player 5 served from your own server – no CDN, no third-party requests – with a subscribe button for podcast apps and services, or a simple HTML5 player.
- **English and German:** the admin interface follows the language of your Bludit site.
- Works with Bludit installed in a subdirectory.

## Screenshots

**Episode page with the Podlove Web Player**

![Podlove Web Player on an episode page](screenshot-player.png)

| Episode list on a phone | Editing an episode on a phone |
|---|---|
| ![Episode list on a phone](screenshot-mobile-list.png) | ![Episode form on a phone](screenshot-mobile-form.png) |

## Installation

1. Download `podcast-<version>.zip` from the [latest release](https://github.com/larsauswsw/bludit-plugin-podcast/releases/latest) and unzip it.
2. Copy the `podcast` folder into `bl-plugins/` of your Bludit installation.
3. In the Bludit admin panel, go to **Settings → Plugins** and activate **Podcast**.
4. Open **Podcast → Channel settings**, fill in your podcast details and submit the feed address shown there to Apple Podcasts, Spotify and other directories.

Audio files are usually larger than PHP's default upload limit of 2 MB. The [documentation](podcast/README.md#4-configure-upload-limits-important-for-audio-files) explains how to raise it, along with all settings, the feed and the player.

**Updating:** copy the `podcast` folder of the new release over the existing one. Episodes, settings and uploads are stored in `bl-content/` and are kept. See the [changelog](CHANGELOG.md) for what changed.

## Requirements

- Bludit 3.19 – 3.22
- PHP 8.0 or newer with the `fileinfo` extension (for uploads)

## License

The plugin is released under the [MIT License](LICENSE). The bundled Podlove Web Player is MIT-licensed as well (`podcast/player/LICENSE`).

---

## Deutsch

Podcast-Plugin für [Bludit](https://www.bludit.com): Episoden im Admin-Bereich verwalten, RSS-Feed für Apple Podcasts und Spotify, Podlove Web Player direkt vom eigenen Server – ohne CDN und ohne Drittanbieter. Episoden sind normale Bludit-Seiten mit dem Tag `podcast`. Die Oberfläche folgt der Sprache der Bludit-Website: Deutsch oder Englisch.

**Installation:** `podcast-<version>.zip` aus dem [neuesten Release](https://github.com/larsauswsw/bludit-plugin-podcast/releases/latest) entpacken, den Ordner `podcast` nach `bl-plugins/` kopieren und unter **Einstellungen → Plugins** aktivieren. Für Updates den Ordner einfach ersetzen; Episoden und Einstellungen bleiben erhalten. Voraussetzungen: Bludit 3.19–3.22, PHP 8.0 oder neuer. Lizenz: MIT.
