# Podcast Plugin for Bludit

A full-featured podcast management plugin for [Bludit](https://www.bludit.com/) CMS. Manage podcast episodes through a dedicated admin interface, automatically generate an iTunes/Apple Podcasts-compatible RSS feed, and display episodes with an embedded audio player — all without touching your theme files.

## Features

- **Episode management** — create, edit, and delete episodes from a dedicated admin page
- **File upload or URL** — upload audio files (MP3, M4A, OGG, WAV) directly or link to an external URL
- **Episode cover images** — upload an image per episode or use an external URL
- **iTunes-compatible RSS feed** — includes all required `<itunes:*>` tags for Apple Podcasts, Spotify, and other directories
- **Embedded audio player** — [Podlove Web Player 5](https://podlove.org/podlove-web-player/) is injected automatically on every episode page, no theme changes needed
- **Role-based access** — both admins and editors can manage episodes; channel settings are admin-only

---

## Requirements

- **Bludit** 3.19 or newer
- **PHP** 7.4 or newer
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

The [Podlove Web Player](https://podlove.org/podlove-web-player/) is embedded automatically at the top of every episode page — no changes to your theme are required. The player loads from the Podlove CDN and requires an internet connection.

If you have a subscribe button configured (Feed URL is set in channel settings), a subscribe button will appear in the player.

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

- The episode must have an audio URL set in its metadata.
- The page must be tagged `podcast` (this is done automatically when you create episodes through the plugin).
- Check browser console for errors loading the Podlove CDN script.

---

## License

MIT
