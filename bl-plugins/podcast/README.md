# Podcast Plugin for Bludit

A podcast plugin for [Bludit](https://www.bludit.com/) CMS. Manage episodes in the Bludit admin panel, publish an RSS feed with iTunes tags, and play episodes on your site with an embedded audio player — all without touching your theme files.

## Features

- **Episodes are Bludit pages** with the tag `podcast`; episode details are stored as Bludit custom fields, so episodes work with your theme, categories, tags and the Bludit editor
- **Episode management** — create, edit and delete episodes on a dedicated admin page that matches the Bludit admin look and works on phones
- **File upload or URL** — upload audio (MP3, M4A, OGG, WAV) and episode artwork (JPG, PNG) or link to external files; uploads are checked for size, file type and content
- **RSS feed** with `<itunes:*>` tags for episode, season, type, duration, explicit and artwork
- **Embedded audio player** — [Podlove Web Player 5](https://podlove.org/podlove-web-player/), served from your own server (no CDN), placed inside the page content of every episode; a plain HTML5 player is available as an alternative and as fallback
- **Role-based access** — admins and editors can manage episodes; channel settings are admin-only
- Works with Bludit installed in a subdirectory

---

## Requirements

- **Bludit** 3.19 or newer (tested up to 3.22)
- **PHP** 8.0 or newer (required by Bludit 3.22) with the **fileinfo** extension (enabled by default; needed to check uploaded files)
- A web server with PHP support (Apache with mod_php or PHP-FPM, Nginx with PHP-FPM)

---

## Installation

### 1. Download the plugin

Download or clone this repository. You only need the `podcast` folder.

### 2. Copy to your Bludit installation

Copy the `podcast` folder into the `bl-plugins/` directory of your Bludit installation:

```
your-bludit/
└── bl-plugins/
    └── podcast/          ← place the folder here
        ├── plugin.php
        ├── metadata.json
        ├── js/
        └── ...
```

### 3. Activate the plugin

1. Log in to your Bludit admin panel.
2. Go to **Einstellungen → Plugins** (*Settings → Plugins*).
3. Find **Podcast** in the list and click **Aktivieren** (*Activate*).

### 4. Configure upload limits (important for audio files)

PHP's default upload limits (`upload_max_filesize = 2M`, `post_max_size = 8M`) are too small for audio files. You must increase them **before** uploading audio.

The best place to configure this is in your **server configuration**, outside the Bludit directory, so the setting survives Bludit updates.

**Option A — Apache VirtualHost** (only with **mod_php**; with PHP-FPM Apache rejects `php_value` — use Option B). Check with `apache2ctl -M | grep -E "php|proxy_fcgi"`:

Add to your site's VirtualHost block:
```apache
<VirtualHost *:80>
    # ... your existing config ...
    php_value upload_max_filesize 512M
    php_value post_max_size 520M
</VirtualHost>
```
Then reload Apache: `sudo systemctl reload apache2`

**Option B — PHP-FPM pool config** (recommended with PHP-FPM; `max_input_time` gives large uploads on slow connections enough time):

Find your pool config (e.g. `/etc/php/8.x/fpm/pool.d/www.conf`) and add:
```ini
php_admin_value[upload_max_filesize] = 512M
php_admin_value[post_max_size] = 520M
php_admin_value[max_input_time] = 600
```
Then restart PHP-FPM: `sudo systemctl restart php8.x-fpm`

**Option C — `.user.ini`** in your Bludit webroot (needs re-adding after Bludit updates):
```ini
upload_max_filesize = 512M
post_max_size = 520M
```

The episode form shows the effective limit (the smallest of the plugin setting, `upload_max_filesize` and `post_max_size`) and checks the file size in the browser before uploading.

### 5. Upload folder protection

Uploaded files are stored in `bl-content/uploads/podcast/audio/` and `bl-content/uploads/podcast/images/`.

- Every upload is checked by extension **and** by its actual content (PHP `fileinfo`; images additionally via `getimagesize`). Allowed: MP3, M4A, OGG, WAV for audio; JPG, PNG for images. SVG and HTML are never accepted.
- File names get a random part (e.g. `episode-1-3f9c0a7b12de4e5f.mp3`), so files of unpublished episodes cannot be guessed. Files uploaded with earlier versions keep their names.
- The plugin places an `index.php` (no directory listing) and an `.htaccess` in each upload folder. On **Apache** the `.htaccess` blocks scripts and HTML/SVG files and sends `X-Content-Type-Options: nosniff`.
- On **Nginx**, `.htaccess` files are ignored. Add an equivalent rule to your server block:

```nginx
location ^~ /bl-content/uploads/podcast/ {
    add_header X-Content-Type-Options "nosniff" always;
    location ~* \.(?:php\d*|phtml|phar|pht|phps|pl|py|cgi|sh|shtml|html?|svgz?|xml)$ {
        deny all;
    }
}
```

(Adjust the path if Bludit runs in a subdirectory.)

---

## Channel Settings

The admin interface is in German; menu names are given in German below.

Before publishing episodes, configure your podcast channel: **Admin → Podcast → Kanaleinstellungen** (admins only), or **Einstellungen → Plugins → Podcast → Einstellungen**.

The settings are grouped like Bludit's own settings pages.

| Group | Setting | Description | Example |
|---|---|---|---|
| **Podcast** | **Podcast-Titel** | The name of your podcast | `My Podcast` |
| | **Beschreibung** | Short summary of your podcast | `A show about...` |
| | **Autor** | Podcast author name | `Jane Doe` |
| | **Sprache** | RSS language code | `en-us`, `de-de` |
| | **Cover-URL** | URL of your podcast artwork (1400×1400 px JPG/PNG recommended) | `https://example.com/cover.jpg` |
| **Apple Podcasts & Verzeichnisse** | **Kategorie** | Apple Podcasts category (English name) | `Society & Culture` |
| | **Explicit** | Whether the podcast contains explicit content | `Nein` |
| | **E-Mail des Inhabers** | Owner email (`itunes:owner`, `managingEditor`). Public in the feed; directories such as Apple Podcasts and Spotify use it to verify ownership | `jane@example.com` |
| | **Apple Podcasts**, **Spotify**, **Pocket Casts**, **Deezer**, **Castbox** | Optional: the podcast's public link on that service (in the app: *Share → Copy link*). Shown in the player's subscribe button. The Apple link also adds Castro and – if no own link is set – Pocket Casts | `https://podcasts.apple.com/de/podcast/my-podcast/id1234567890` |
| **Feed** | **Feed-Dateiname** | Path at which the RSS feed is served | `podcast.xml` |
| | **Adresse des Feeds** | Read-only: the public feed URL to submit to Apple Podcasts, Spotify etc., with a link to open the feed | `https://example.com/podcast.xml` |
| | **Abweichende Feed-URL (optional)** | Only if the feed is served from another address (e.g. a CDN); leave empty to use the site address | `https://cdn.example.com/podcast.xml` |
| | **Website-URL** | Your podcast website; leave empty for the Bludit site | `https://example.com` |
| **Website & Player** | **Player auf Episodenseiten** | Podlove Web Player (served locally) or a plain HTML5 player | Podlove |
| | **Position des Players** | At the start of the page content (below the title) or at the end | start |
| **Uploads** | **Max. Upload-Größe (MB)** | Plugin-level upload limit; the server's PHP limits apply as well | `512` |

The feed URL is built from the Bludit site address and the feed filename (subdirectory installations included). URLs that do not start with `http://` or `https://` are not used; the form marks them. Every page of the site links to the feed in its `<head>` (`<link rel="alternate" type="application/rss+xml">`), so browsers and podcast apps can find it from the website address.

Click **Speichern** after making changes.

---

## Managing Episodes

Go to **Admin → Podcast** (microphone icon in the sidebar). The list shows all episodes, sorted by episode number (highest first), with badges for drafts (*Entwurf*), scheduled (*Geplant*), sticky (*Fixiert*) and static (*Statisch*) episodes. It works on phones as well.

### Creating a new episode

1. Click **Neue Episode**.
2. Fill in the episode details:

| Field | Description |
|---|---|
| **Titel** | Episode title (required) |
| **Beschreibung** | Show notes / episode description (required) |
| **Folge** | Episode number, used for `<itunes:episode>` |
| **Staffel** | Season, used for `<itunes:season>` |
| **Typ** | `full`, `trailer` or `bonus` |
| **Explicit** | Explicit flag for this episode |
| **Dauer** | `HH:MM:SS`, `MM:SS` or total seconds |
| **Episodenbild** | External URL or file upload (JPG, PNG); the player falls back to the channel cover |
| **Audio** | External URL or file upload (MP3, M4A, OGG, WAV) — required |
| **Veröffentlichungsdatum** | Publication date and time |
| **Status** | *Veröffentlicht* (published) or *Entwurf* (draft) |

3. Click **Speichern**. The episode appears in the list and, if published, on your site.

A published episode with a date in the future becomes *Geplant* (scheduled) in Bludit and appears on the site and in the feed at that time. If you set an episode to *Fixiert* (sticky) or *Statisch* (static) in the Bludit editor, the podcast form keeps that type.

### Editing an episode

Click the episode title, or open the **☰** menu of the episode and choose **Bearbeiten**. The same menu offers **Anschauen** (view the page) and **Im Bludit-Editor öffnen**. Tags you add in the Bludit editor are kept when you save in the podcast form.

### Deleting an episode

Open the **☰** menu and choose **Löschen**, then confirm. The episode disappears from your site and the feed. Episodes that have child pages cannot be deleted from the plugin (like in Bludit's own content list).

### Where episode data is stored

Episode metadata (audio URL, file size, duration, episode/season number, type, explicit flag, episode image) is stored as **Bludit custom fields** directly in each page (`podcast_audio_url`, `podcast_audio_length`, `podcast_duration`, `podcast_episode`, `podcast_season`, `podcast_type`, `podcast_explicit`, `podcast_image`). The plugin registers these fields automatically under **Settings → General → Custom fields**; existing custom fields are left untouched.

- The data lives in `bl-content/` together with your pages, so it is included in normal Bludit backups, survives plugin updates and follows the page when its slug changes.
- The fields also appear in the Bludit editor under **Options → Custom** for every page. Pages only become episodes when they are tagged `podcast`; any user who can edit a page in Bludit can also edit these fields there. Bludit does not validate custom fields in its editor: invalid values (e.g. a duration like `47:99` or a non-`http(s)` URL) are saved there, but ignored by the feed and the player. The plugin's edit form shows the stored value and asks for a valid one when you save.
- Deactivating the plugin does **not** delete episode data.

### Updating from a version with `metadata/episodes.json`

Earlier development versions stored metadata in `bl-plugins/podcast/metadata/episodes.json`, a location that is reachable over HTTP. To migrate:

1. Copy the new plugin files **over** the existing `podcast` folder — do not delete the old folder first, otherwise `episodes.json` is lost.
2. Open **Admin → Podcast** once as admin or editor.

The plugin then copies the data into the custom fields, moves the old file as a protected backup to `bl-content/workspaces/podcast/episodes-legacy-<date>.php` and deletes `metadata/episodes.json`. Episodes that already have an audio URL in their custom fields are not overwritten. Note: Bludit deletes the workspace folder (and with it this backup) when the plugin is deactivated.

---

## RSS Feed

The feed is served at the configured feed filename, e.g. `https://example.com/podcast.xml` (also when Bludit runs in a subdirectory).

The feed includes:
- Standard RSS 2.0 channel and item elements with `<itunes:*>` tags (episode, season, type, duration, explicit, artwork)
- `<enclosure>` tags with file size and MIME type for each episode
- All publicly visible episodes — published, sticky and static; drafts and scheduled episodes are excluded, as are episodes without audio
- Episodes sorted by date, newest first

**Submit your feed** to podcast directories, for example:
- [Apple Podcasts Connect](https://podcastsconnect.apple.com/)
- [Spotify for Creators](https://creators.spotify.com/)

---

## Frontend Player

The [Podlove Web Player](https://podlove.org/podlove-web-player/) is added to every episode page automatically — no changes to your theme are required. The plugin inserts it into the page content, so it appears wherever your theme prints the content: at the start (below the title, default) or at the end, see **Position des Players**. To place it anywhere else, write a line `{{ podcast-player }}` into the episode text; the player appears exactly there. The placeholder is removed on list pages and in the feed.

- **Served locally:** the plugin ships Podlove Web Player 5.13.0 in `bl-plugins/podcast/player/` (MIT license, unmodified subset of the npm package; provenance and checksums in `player/SOURCE.md`). Visitors' browsers do **not** connect to a Podlove CDN.
- **Fallback:** a plain HTML5 `<audio>` player with a download link is shown until the Podlove player has loaded, and stays visible if JavaScript is disabled or the player cannot load.
- **Plain HTML5 player:** choose *Player auf Episodenseiten → Einfacher HTML5-Player* in the channel settings to skip the Podlove player entirely.
- The player shows a subscribe button for the podcast feed. It lists the services whose link is set in the channel settings (Apple Podcasts, Spotify, Pocket Casts, Castro, Deezer, Castbox), podcast apps that subscribe via the feed URL (Overcast, Downcast, AntennaPod, Podcast Addict and others, filtered by the visitor's platform), Player.fm and the plain RSS link.

To update the bundled player, run `scripts/update-podlove-player.sh <version>` from the development repository (verifies the npm integrity hash and copies only the needed files).

### Privacy policy note

The player itself sends no data to third parties. The audio file is loaded from wherever its URL points when a visitor presses play (the HTML5 player uses `preload="none"`). If your audio files are hosted on another server (e.g. a podcast hoster or CDN), mention that host in your privacy policy. Example text (German):

> Auf Episodenseiten binden wir den Podlove Web Player ein, der von unserem eigenen Server ausgeliefert wird; dabei werden keine Daten an Dritte übertragen. Beim Abspielen wird die Audiodatei von [Anbieter / unser Server] geladen; dabei wird Ihre IP-Adresse an diesen Server übermittelt, soweit dies für die Auslieferung technisch erforderlich ist.

---

## Troubleshooting

**"…-Datei ist größer als … MB" or "Upload zu groß: …" when saving**

The file exceeds the plugin setting or PHP's `upload_max_filesize` / `post_max_size`. The episode form shows the effective limit below the audio field. See [Configure upload limits](#4-configure-upload-limits-important-for-audio-files) above.

**"Die Anfrage ist für den Webserver zu groß (HTTP 413)"**

The web server rejected the request before PHP saw it. Raise its limit as well (Apache `LimitRequestBody`, Nginx `client_max_body_size`).

**"Sitzung abgelaufen oder Sicherheits-Token ungültig"**

Your Bludit session has expired. Log in again and repeat the action.

**"Serverfehler (HTTP 500)"**

A PHP error occurred. Check the PHP error log and `bl-content/databases/syslog.php`.

**Feed is empty or missing episodes**

- Make sure your episodes are published (not *Entwurf*) and their date is not in the future.
- Check that the feed filename in the channel settings matches the URL you are accessing.
- Verify that the episode has audio — episodes without an audio URL are excluded from the feed.

**Player does not appear on episode pages**

- The episode must have an `http(s)` audio URL in its custom field `podcast_audio_url`.
- After updating from an older version, open **Admin → Podcast** once so existing data is migrated (see *Updating from a version with `metadata/episodes.json`*).
- The page must have the tag `podcast` (set automatically when you create episodes through the plugin; other tags may be added).
- Check the browser console for errors loading `bl-plugins/podcast/player/embed.js`; if it fails, the HTML5 fallback player is shown instead.

---

## License

The license has not been decided yet; until it is announced, all rights are reserved. The bundled Podlove Web Player in `player/` is MIT-licensed (see `player/LICENSE`).
