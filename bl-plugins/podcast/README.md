# Podcast Plugin for Bludit

A full-featured podcast management plugin for [Bludit](https://www.bludit.com/) CMS. Manage podcast episodes through a dedicated admin interface, automatically generate an iTunes/Apple Podcasts-compatible RSS feed, and display episodes with an embedded audio player — all without touching your theme files.

## Features

- **Episode management** — create, edit, and delete episodes from a dedicated admin page
- **File upload or URL** — upload audio files (MP3, M4A, OGG, WAV) directly or link to an external URL
- **Episode cover images** — upload an image per episode or use an external URL
- **iTunes-compatible RSS feed** — includes all required `<itunes:*>` tags for Apple Podcasts, Spotify, and other directories
- **Embedded audio player** — [Podlove Web Player 5](https://podlove.org/podlove-web-player/), served from your own server (no CDN), is injected automatically on every episode page; a plain HTML5 player is available as an alternative and as fallback
- **Role-based access** — both admins and editors can manage episodes; channel settings are admin-only

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
2. Go to **Plugins** in the sidebar.
3. Find **Podcast** in the list and click **Activate**.

### 4. Configure upload limits (important for audio files)

PHP's default upload limits (`upload_max_filesize = 2M`, `post_max_size = 8M`) are too small for audio files. You must increase them **before** uploading audio.

The best place to configure this is in your **server configuration**, outside the Bludit directory, so the setting survives Bludit updates.

**Option A — Apache VirtualHost** (recommended):

Add to your site's VirtualHost block:
```apache
<VirtualHost *:80>
    # ... your existing config ...
    php_value upload_max_filesize 512M
    php_value post_max_size 520M
</VirtualHost>
```
Then reload Apache: `sudo systemctl reload apache2`

**Option B — PHP-FPM pool config**:

Find your pool config (e.g. `/etc/php/8.x/fpm/pool.d/www.conf`) and add:
```ini
php_admin_value[upload_max_filesize] = 512M
php_admin_value[post_max_size] = 520M
```
Then restart PHP-FPM: `sudo systemctl restart php8.x-fpm`

**Option C — `.user.ini`** in your Bludit webroot (needs re-adding after Bludit updates):
```ini
upload_max_filesize = 512M
post_max_size = 520M
```

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

Before publishing any episodes, configure your podcast channel. Go to **Admin → Plugins → Podcast → Channel Settings** (gear icon).

| Setting | Description | Example |
|---|---|---|
| **Feed filename** | Path at which the RSS feed is served | `podcast.xml` |
| **Podcast title** | The name of your podcast | `My Podcast` |
| **Feed URL** | Full public URL to the RSS feed | `https://example.com/podcast.xml` |
| **Website URL** | Your podcast website | `https://example.com` |
| **Description** | Short summary of your podcast | `A show about...` |
| **Language** | RSS language code | `en-us`, `de-de` |
| **Author** | Podcast author name | `Jane Doe` |
| **E-Mail** | Contact email (used as `managingEditor`) | `jane@example.com` |
| **iTunes category** | Apple Podcasts category | `Society & Culture` |
| **Cover image URL** | URL to your podcast artwork (1400×1400 px JPG/PNG recommended) | `https://example.com/cover.jpg` |
| **Explicit** | Whether the podcast contains explicit content | `No` |
| **Player on episode pages** | Podlove Web Player (served locally) or a plain HTML5 player | Podlove |
| **Max upload size (MB)** | Plugin-level upload limit (must not exceed your server's PHP limits) | `512` |

Click **Save** after making changes.

---

## Managing Episodes

Go to **Admin → Podcast** (microphone icon in the sidebar).

### Creating a new episode

1. Click **New Episode**.
2. Fill in the episode details:

| Field | Description |
|---|---|
| **Title** | Episode title (required) |
| **Show notes** | Full episode description / transcript |
| **Episode number** | Used for `<itunes:episode>` |
| **Season** | Used for `<itunes:season>` |
| **Type** | `full`, `trailer`, or `bonus` |
| **Explicit** | Override for this episode |
| **Duration** | Format: `HH:MM:SS` or total seconds |
| **Date** | Publication date and time |
| **Status** | `Published` or `Draft` (drafts are excluded from the feed) |

3. **Audio**: choose between:
   - **External URL** — paste a direct link to an MP3/M4A/OGG/WAV file
   - **File upload** — select a local audio file to upload to your server

4. **Cover image** (optional): choose between external URL or file upload. Falls back to the channel cover if left empty.

5. Click **Save**. The episode appears in the list and on the frontend immediately (if status is Published).

### Editing an episode

Click the **edit icon** (pencil) next to the episode in the list. The form loads with all existing data pre-filled. Make your changes and click **Save**.

### Deleting an episode

Click the **delete icon** (trash) next to the episode. You will be asked to confirm. Deleting an episode removes it from the feed and from your site.

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

Once channel settings are saved, the feed is available at the URL you configured (e.g. `https://example.com/podcast.xml`).

The feed includes:
- Standard RSS 2.0 channel and item elements
- All `<itunes:*>` namespace tags required by Apple Podcasts
- `<enclosure>` tags with file size and MIME type for each episode
- Only published episodes (drafts are excluded)
- Episodes sorted by episode number (descending), then by date

**Submit your feed** to podcast directories:
- [Apple Podcasts Connect](https://podcastsconnect.apple.com/)
- [Spotify for Podcasters](https://podcasters.spotify.com/)
- [Google Podcasts Manager](https://podcastsmanager.google.com/)

---

## Frontend Player

The [Podlove Web Player](https://podlove.org/podlove-web-player/) is embedded automatically at the top of every episode page — no changes to your theme are required.

- **Served locally:** the plugin ships Podlove Web Player 5.13.0 in `bl-plugins/podcast/player/` (MIT license, unmodified subset of the npm package; provenance and checksums in `player/SOURCE.md`). Visitors' browsers do **not** connect to a Podlove CDN.
- **Fallback:** a plain HTML5 `<audio>` player with a download link is shown until the Podlove player has loaded, and stays visible if JavaScript is disabled or the player cannot load.
- **Plain HTML5 player:** choose *Player on episode pages → Plain HTML5 player* in the channel settings to skip the Podlove player entirely.
- If a Feed URL is set in the channel settings, a subscribe button appears in the player.

To update the bundled player, run `scripts/update-podlove-player.sh <version>` (verifies the npm integrity hash and copies only the needed files).

### Privacy policy note

The player itself sends no data to third parties. The audio file is loaded from wherever its URL points when a visitor presses play (the HTML5 player uses `preload="none"`). If your audio files are hosted on another server (e.g. a podcast hoster or CDN), mention that host in your privacy policy. Example text (German):

> Auf Episodenseiten binden wir den Podlove Web Player ein, der von unserem eigenen Server ausgeliefert wird; dabei werden keine Daten an Dritte übertragen. Beim Abspielen wird die Audiodatei von [Anbieter / unser Server] geladen; dabei wird Ihre IP-Adresse an diesen Server übermittelt, soweit dies für die Auslieferung technisch erforderlich ist.

---

## Troubleshooting

**"Audio URL or file is required" error when saving**

Your audio file exceeds PHP's `upload_max_filesize` limit. See [Configure upload limits](#4-configure-upload-limits-important-for-audio-files) above.

**"Network error" when saving**

Usually caused by the combined upload size (image + audio) exceeding PHP's `post_max_size`. Increase `post_max_size` as described above. Also check that `display_errors` is off in production, as PHP warnings can break the JSON response.

**Feed is empty or missing episodes**

- Make sure your episodes have status **Published** (not Draft).
- Check that the feed filename in channel settings matches the URL you are accessing.
- Verify that the audio URL field is filled — episodes without an audio URL are excluded from the feed.

**Player does not appear on episode pages**

- The episode must have an `http(s)` audio URL in its custom field `podcast_audio_url`.
- After updating from an older version, open **Admin → Podcast** once so existing data is migrated (see *Updating from a version with `metadata/episodes.json`*).
- The page must be tagged `podcast` (this is done automatically when you create episodes through the plugin).
- Check the browser console for errors loading `bl-plugins/podcast/player/embed.js`; if it fails, the HTML5 fallback player is shown instead.

---

## License

MIT
