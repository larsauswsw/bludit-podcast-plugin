# Podlove Web Player – Design Spec
Date: 2026-04-10

## Goal

Embed the Podlove Web Player v5 on individual podcast episode pages so visitors can listen directly on the site. The solution must work independently of the active Bludit theme.

## Scope

- Single episode pages only (not list/archive pages)
- Frontend (public website), not the admin UI
- Plugin-only change – no theme modifications required

## When the Player is Shown

All three conditions must be true:

1. Bludit is in `single` mode (`$url->whereAmI() === 'page'`)
2. The current page has the tag `podcast`
3. The episode's metadata in `episodes.json` contains a non-empty `audio_url`

## Implementation

### Hook: `siteHead()`

Conditionally outputs the Podlove CDN script tag:

```html
<script src="https://cdn.podlove.org/web-player/5.x/embed.js"></script>
```

Only added when the three conditions above are met. No script is added on non-podcast pages.

### Hook: `siteBodyEnd()`

When conditions are met, outputs:

1. A `<div id="podlove-episode-player"></div>` container (placed at end of body)
2. An inline `<script>` that:
   - Finds the content container using a prioritized list of CSS selectors
   - Moves the player div before the content container's first child (before calling the player)
   - Then calls `podlovePlayer('#podlove-episode-player', episode, config)`
   - This order ensures the player renders in the correct position immediately

### Theme-Independent Positioning

JavaScript tries selectors in order, using the first match:

```
article .post-content
article .entry-content
article .content
article
.post-content
.entry-content
main
#content
body
```

The player `<div>` is moved with `container.insertBefore(playerDiv, container.firstChild)`.
If no container is found, the player stays at the bottom of `<body>` (graceful fallback).

### Episode Config (built in PHP from existing data)

```json
{
  "show": {
    "title": "<feedTitle from plugin settings>",
    "subtitle": "",
    "summary": "<feedDesc from plugin settings>",
    "poster": "<cover from plugin settings>",
    "link": "<siteUrl from plugin settings>"
  },
  "title": "<page title>",
  "subtitle": "",
  "summary": "<page content, tags stripped>",
  "publicationDate": "<ISO 8601 date>",
  "duration": "<itunes_duration from episodes.json>",
  "poster": "<itunes_image from episodes.json, falls back to show poster>",
  "audio": [
    {
      "url": "<audio_url from episodes.json>",
      "mimeType": "<derived from file extension>",
      "size": <audio_length from episodes.json>,
      "title": "Audio"
    }
  ]
}
```

### Player Config (built in PHP from plugin settings)

```json
{
  "subscribe-button": {
    "feed": "<full feed URL from plugin settings>"
  }
}
```

If `feedUrl` is empty in settings, the `subscribe-button` key is omitted.

## Data Sources

| Player field | Source |
|---|---|
| Show title | Plugin setting `feedTitle` |
| Show cover | Plugin setting `cover` |
| Show description | Plugin setting `feedDesc` |
| Feed URL | Plugin setting `feedUrl` |
| Episode title | `$page->title()` |
| Episode summary | `strip_tags($page->content())` |
| Publication date | `$page->date('c')` (ISO 8601) |
| Audio URL | `episodes.json` → `audio_url` |
| Audio length | `episodes.json` → `audio_length` |
| Audio MIME type | Derived from `audio_url` extension via existing `audioMimeType()` helper |
| Duration | `episodes.json` → `itunes_duration` |
| Episode poster | `episodes.json` → `itunes_image` (falls back to show cover) |

## Files Changed

| File | Change |
|---|---|
| `bl-plugins/podcast/plugin.php` | Add `siteHead()` and `siteBodyEnd()` methods |

No JS file needed – player initialization is output as an inline script by `siteBodyEnd()`.
No theme files modified.
