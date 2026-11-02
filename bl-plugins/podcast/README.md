# Podcast Plugin for Bludit

A podcast plugin for [Bludit](https://www.bludit.com/) CMS. Manage episodes in the Bludit admin panel, publish an RSS feed with iTunes tags, and play episodes on your site with an embedded audio player — all without touching your theme files.

## Features

- **Episodes are Bludit pages** with the tag `podcast`; episode details are stored as Bludit custom fields, so episodes work with your theme, categories, tags and the Bludit editor
- **Episode management** — create, edit and delete episodes on a dedicated admin page that matches the Bludit admin look and works on phones
- **File upload or URL** — upload audio (MP3, M4A, OGG, WAV) and episode artwork (JPG, PNG) or link to external files; uploads are checked for size, file type and content
- **RSS feed** with `<itunes:*>` tags for episode, season, type, duration, explicit and artwork
- **Embedded audio player** — [Podlove Web Player 5](https://podlove.org/podlove-web-player/), served from your own server (no CDN), placed inside the page content of every episode; a plain HTML5 player is available as an alternative and as fallback
- **Apple Podcasts ready** — category from Apple's list, podcast type, cover check (square, 1400–3000 px, RGB), show notes as HTML, file sizes of external audio, Podcasting 2.0 `podcast:guid` and `podcast:locked`
- **Subscribe button** in the player with podcast apps and your links on Apple Podcasts, Spotify, Pocket Casts, Deezer and Castbox
- **Role-based access** — admins and editors can manage episodes; channel settings are admin-only
- **English and German** — the admin interface follows the language of your Bludit site
- Works with Bludit installed in a subdirectory

---

## Requirements

- **Bludit** 3.19 or newer (tested up to 3.22)
- **PHP** 8.0 or newer (required by Bludit 3.22) with the **fileinfo** extension (enabled by default; needed to check uploaded files)
- A web server with PHP support (Apache with mod_php or PHP-FPM, Nginx with PHP-FPM)

---

## Installation

### 1. Download the plugin

Download `podcast-<version>.zip` from the [releases page](https://github.com/larsauswsw/bludit-plugin-podcast/releases) and unzip it. It contains the `podcast` folder.

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
2. Go to **Settings → Plugins** (German: *Einstellungen → Plugins*).
3. Find **Podcast** in the list and click **Activate** (*Aktivieren*).

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

### Updating the plugin

Download the new release and copy its `podcast` folder over the existing `bl-plugins/podcast/` folder. Episodes, settings and uploads are stored in `bl-content/`, not in the plugin folder, so they are kept. The changes of each version are listed in the [changelog](https://github.com/larsauswsw/bludit-plugin-podcast/blob/main/CHANGELOG.md).

---

## Channel Settings

The admin interface follows the language of your Bludit site: English or German (Germany, Austria, Switzerland); other languages show English. Names below are the English ones.

Before publishing episodes, configure your podcast channel: **Admin → Podcast → Channel settings** (admins only), or **Settings → Plugins → Podcast → Settings**.

The settings are grouped like Bludit's own settings pages.

| Group | Setting | Description | Example |
|---|---|---|---|
| **Podcast** | **Podcast title** | The name of your podcast | `My Podcast` |
| | **Description** | Short summary of your podcast | `A show about...` |
| | **Author** | Podcast author name | `Jane Doe` |
| | **Language** | RSS language code | `en-us`, `de-de` |
| | **Cover URL** | URL of your podcast artwork; Apple requires a square JPG/PNG in RGB with 1400–3000 px. Also used as the RSS `<image>`. A cover uploaded to this site is checked and the form names any problem | `https://example.com/cover.jpg` |
| | **Upload cover** | Upload a cover (admins only). It is checked before it is stored — not square, too small/large or CMYK is refused with a message — and entered as *Cover URL* with a preview; then click **Save** | – |
| | **Copyright** | Optional copyright notice (`<copyright>`) | `© 2026 Jane Doe` |
| **Apple Podcasts & directories** | **Category** | Apple Podcasts category with optional subcategory, chosen from Apple's list (in German with the English name added; the feed contains the English names, subcategory nested). A previous free-text entry is kept if it matches an Apple category | `Kids & Family → Parenting` |
| | **Podcast type** | `<itunes:type>`: episodic (newest first) or serial (in order, oldest first) | Episodic |
| | **Explicit** | Whether the podcast contains explicit content (`true`/`false` in the feed) | `No` |
| | **Lock feed against transfer** | `<podcast:locked>`: *Yes* means other platforms only take over the podcast after ownership is confirmed via the owner email. Set to *No* before moving to another host | `No` |
| | **Owner email** | Owner email (`itunes:owner`, `managingEditor`). Public in the feed; directories such as Apple Podcasts and Spotify use it to verify ownership | `jane@example.com` |
| | **Apple Podcasts**, **Spotify**, **Pocket Casts**, **Deezer**, **Castbox** | Optional: the podcast's public link on that service (in the app: *Share → Copy link*). Shown in the player's subscribe button. The Apple link also adds Castro and – if no own link is set – Pocket Casts | `https://podcasts.apple.com/us/podcast/my-podcast/id1234567890` |
| **Feed** | **Feed file name** | Path at which the RSS feed is served | `podcast.xml` |
| | **Feed address** | Read-only: the public feed URL to submit to Apple Podcasts, Spotify etc., with a link to open the feed | `https://example.com/podcast.xml` |
| | **Podcast GUID** | Read-only: permanent podcast ID (`<podcast:guid>`), generated once from the feed URL (UUIDv5 as defined by Podcasting 2.0) and kept when the feed moves | `5c3f1a80-…` |
| | **Different feed URL (optional)** | Only if the feed is served from another address (e.g. a CDN); leave empty to use the site address | `https://cdn.example.com/podcast.xml` |
| | **Website URL** | Your podcast website; leave empty for the Bludit site | `https://example.com` |
| **Website & player** | **Player on episode pages** | Podlove Web Player (served locally) or a simple HTML5 player | Podlove |
| | **Player position** | At the start of the page content (below the title) or at the end | start |
| **Uploads** | **Max. upload size (MB)** | Plugin-level upload limit; the server's PHP limits apply as well | `512` |

The feed URL is built from the Bludit site address and the feed filename (subdirectory installations included). URLs that do not start with `http://` or `https://` are not used; the form marks them. Every page of the site links to the feed in its `<head>` (`<link rel="alternate" type="application/rss+xml">`), so browsers and podcast apps can find it from the website address.

Click **Save** after making changes.

---

## Managing Episodes

Go to **Admin → Podcast** (microphone icon in the sidebar). The list shows all episodes, sorted by episode number (highest first), with badges for drafts (*Draft*), scheduled (*Scheduled*), sticky (*Sticky*) and static (*Static*) episodes. It works on phones as well.

### Creating a new episode

1. Click **New episode**.
2. Fill in the episode details:

| Field | Description |
|---|---|
| **Title** | Episode title (required) |
| **Description** | Show notes / episode description (required) |
| **Episode** | Episode number, used for `<itunes:episode>` |
| **Season** | Season, used for `<itunes:season>` |
| **Type** | Full, Trailer or Bonus (`full`, `trailer`, `bonus` in the feed) |
| **Explicit** | Explicit flag for this episode |
| **Duration** | `HH:MM:SS`, `MM:SS` or total seconds |
| **Episode image** | External URL or file upload (JPG, PNG) with a preview; the player falls back to the channel cover. An uploaded image that does not meet Apple's cover rules is saved with a warning |
| **Audio** | External URL or file upload (MP3, M4A, OGG, WAV) — required. Apple Podcasts does not play WAV or OGG; the form shows a hint. For an external URL the plugin asks the server for the file size when saving (see below) |
| **Publication date** | Publication date and time, in the website's time zone |
| **Status** | *Published* or *Draft* |

3. Click **Save**. The episode appears in the list and, if published, on your site.

A published episode with a date in the future becomes *Scheduled* in Bludit and appears on the site and in the feed at that time. If you set an episode to *Sticky* or *Static* in the Bludit editor, the podcast form keeps that type.

### Editing an episode

Click the episode title, or open the **☰** menu of the episode and choose **Edit**. The same menu offers **View** and **Open in the Bludit editor**. Tags you add in the Bludit editor are kept when you save in the podcast form.

### Deleting an episode

Open the **☰** menu and choose **Delete**, then confirm. The episode disappears from your site and the feed. Episodes that have child pages cannot be deleted from the plugin (like in Bludit's own content list).

### Where episode data is stored

Episode metadata (audio URL, file size, duration, episode/season number, type, explicit flag, episode image) is stored as **Bludit custom fields** directly in each page (`podcast_audio_url`, `podcast_audio_length`, `podcast_duration`, `podcast_episode`, `podcast_season`, `podcast_type`, `podcast_explicit`, `podcast_image`). The plugin registers these fields automatically under **Settings → General → Custom fields**, labelled in the site language at that time; existing custom fields are left untouched.

- The data lives in `bl-content/` together with your pages, so it is included in normal Bludit backups, survives plugin updates and follows the page when its slug changes.
- The fields also appear in the Bludit editor under **Options → Custom** for every page. Pages only become episodes when they are tagged `podcast`; any user who can edit a page in Bludit can also edit these fields there. Bludit does not validate custom fields in its editor: invalid values (e.g. a duration like `47:99` or a non-`http(s)` URL) are saved there, but ignored by the feed and the player. The plugin's edit form shows the stored value and asks for a valid one when you save.
- Deactivating the plugin does **not** delete episode data.

---

## RSS Feed

The feed is served at the configured feed filename, e.g. `https://example.com/podcast.xml` (also when Bludit runs in a subdirectory).

The feed includes:
- Standard RSS 2.0 channel and item elements with `<itunes:*>` tags (episode, season, type, duration, explicit, artwork)
- Podcasting 2.0: `<podcast:guid>` and `<podcast:locked>` (namespace `xmlns:podcast`, as the PSP-1 standard requires)
- Apple Podcasts channel tags: category and subcategory from Apple's list, `<itunes:type>`, `<itunes:explicit>true|false</itunes:explicit>`, optional `<copyright>` and the RSS `<image>` from the cover
- `<enclosure>` tags with file size and MIME type for each episode
- Show notes: `<content:encoded>` with the episode text as HTML (paragraphs, lists, links, emphasis; scripts, styles, embeds and attributes are removed, links only `http(s)`/`mailto`), `<description>` with the text without tags (max. 4000 characters)

**File size of external audio URLs:** when an episode with an external audio URL is saved, the plugin requests the size from that server (`HEAD`, or a 1-byte `Range` request if `HEAD` is not supported). Only `http`/`https` on ports 80/443 to public addresses is requested; internal networks, redirects to them and more than three redirects are refused, the request is limited to 10 seconds. If the size cannot be determined, the episode is saved anyway with a hint above the list, and the feed shows `length="0"`. Uploaded files and unchanged URLs keep their stored size.
- All publicly visible episodes — published, sticky and static; drafts and scheduled episodes are excluded, as are episodes without audio
- Episodes sorted by date, newest first

**Submit your feed** to podcast directories, for example:
- [Apple Podcasts Connect](https://podcastsconnect.apple.com/)
- [Spotify for Creators](https://creators.spotify.com/)

---

## Frontend Player

The [Podlove Web Player](https://podlove.org/podlove-web-player/) is added to every episode page automatically — no changes to your theme are required. The plugin inserts it into the page content, so it appears wherever your theme prints the content: at the start (below the title, default) or at the end, see **Player position**. To place it anywhere else, write a line `{{ podcast-player }}` into the episode text; the player appears exactly there. The placeholder is removed on list pages and in the feed.

- **Served locally:** the plugin ships Podlove Web Player 5.13.0 in `bl-plugins/podcast/player/` (MIT license, unmodified subset of the npm package; provenance and checksums in `player/SOURCE.md`). Visitors' browsers do **not** connect to a Podlove CDN.
- **Fallback:** a plain HTML5 `<audio>` player with a download link is shown until the Podlove player has loaded, and stays visible if JavaScript is disabled or the player cannot load.
- **Simple HTML5 player:** choose *Player on episode pages → Simple HTML5 player* in the channel settings to skip the Podlove player entirely.
- The player shows a subscribe button for the podcast feed. It lists the services whose link is set in the channel settings (Apple Podcasts, Spotify, Pocket Casts, Castro, Deezer, Castbox), podcast apps that subscribe via the feed URL (Overcast, Downcast, AntennaPod, Podcast Addict and others, filtered by the visitor's platform), Player.fm and the plain RSS link.

### Privacy policy note

The player itself sends no data to third parties. The audio file is loaded from wherever its URL points when a visitor presses play (the HTML5 player uses `preload="none"`). If your audio files are hosted on another server (e.g. a podcast hoster or CDN), mention that host in your privacy policy. Example text (German):

> Auf Episodenseiten binden wir den Podlove Web Player ein, der von unserem eigenen Server ausgeliefert wird; dabei werden keine Daten an Dritte übertragen. Beim Abspielen wird die Audiodatei von [Anbieter / unser Server] geladen; dabei wird Ihre IP-Adresse an diesen Server übermittelt, soweit dies für die Auslieferung technisch erforderlich ist.

---

## Troubleshooting

**"… file is larger than … MB" or "Upload too large: …" when saving**

The file exceeds the plugin setting or PHP's `upload_max_filesize` / `post_max_size`. The episode form shows the effective limit below the audio field. See [Configure upload limits](#4-configure-upload-limits-important-for-audio-files) above.

**"The request is too large for the web server (HTTP 413)"**

The web server rejected the request before PHP saw it. Raise its limit as well (Apache `LimitRequestBody`, Nginx `client_max_body_size`).

**"Session expired or security token invalid"**

Your Bludit session has expired. Log in again and repeat the action.

**"Server error (HTTP 500)"**

A PHP error occurred. Check the PHP error log and `bl-content/databases/syslog.php`.

**Feed is empty or missing episodes**

- Make sure your episodes are published (not *Draft*) and their date is not in the future.
- Check that the feed filename in the channel settings matches the URL you are accessing.
- Verify that the episode has audio — episodes without an audio URL are excluded from the feed.

**Player does not appear on episode pages**

- The episode must have an `http(s)` audio URL in its custom field `podcast_audio_url`.
- The page must have the tag `podcast` (set automatically when you create episodes through the plugin; other tags may be added).
- Check the browser console for errors loading `bl-plugins/podcast/player/embed.js`; if it fails, the HTML5 fallback player is shown instead.

---

## License

The plugin is released under the [MIT License](LICENSE). The bundled Podlove Web Player in `player/` is MIT-licensed as well (see `player/LICENSE`).
