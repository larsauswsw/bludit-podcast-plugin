# Podlove Web Player Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Embed the Podlove Web Player v5 on individual podcast episode pages so visitors can listen directly on the site.

**Architecture:** Two new plugin hook methods (`siteHead` + `siteBodyEnd`) in `plugin.php` detect whether the current page is a podcast episode, then inject the Podlove CDN script and an inline player initialization script. JavaScript moves the player div before the content area using a prioritized list of CSS selectors, making the solution theme-independent.

**Tech Stack:** PHP 8, Bludit 3.19 plugin API, Podlove Web Player v5 (CDN)

---

## File Map

| File | Change |
|---|---|
| `bl-plugins/podcast/plugin.php` | Add `siteHead()` and `siteBodyEnd()` methods |

---

### Task 1: Add `siteHead()` – load Podlove CDN script on episode pages

**Files:**
- Modify: `bl-plugins/podcast/plugin.php` (add method after `beforeAdminLoad()`)

- [ ] **Step 1: Add the `siteHead()` method**

Add the following method directly after the `beforeAdminLoad()` method in `plugin.php`:

```php
public function siteHead() {
    global $url, $page;

    if ($url->whereAmI() !== 'page') return;
    if (!isset($page) || !$this->pageHasPodcastTag($page)) return;

    $meta = $this->getEpisodeMeta($page->key());
    if (empty($meta['audio_url'])) return;

    echo '<script src="https://cdn.podlove.org/web-player/5.x/embed.js"></script>' . "\n";
}
```

- [ ] **Step 2: Verify PHP syntax**

Run:
```bash
php -l bl-plugins/podcast/plugin.php
```
Expected output: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add bl-plugins/podcast/plugin.php
git commit -m "feat(podcast): add siteHead hook to load Podlove CDN on episode pages"
```

---

### Task 2: Add `siteBodyEnd()` – inject player container and initialization

**Files:**
- Modify: `bl-plugins/podcast/plugin.php` (add method after `siteHead()`)

- [ ] **Step 1: Add the `siteBodyEnd()` method**

Add directly after `siteHead()`:

```php
public function siteBodyEnd() {
    global $url, $page;

    if ($url->whereAmI() !== 'page') return;
    if (!isset($page) || !$this->pageHasPodcastTag($page)) return;

    $meta = $this->getEpisodeMeta($page->key());
    if (empty($meta['audio_url'])) return;

    $get = function ($k) use ($meta) { return isset($meta[$k]) ? $meta[$k] : ''; };

    $audioUrl    = $get('audio_url');
    $audioLength = (int) $get('audio_length');
    $audioExt    = strtolower(pathinfo(parse_url($audioUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
    $mime        = $this->audioMimeType($audioExt);
    $duration    = $get('itunes_duration');
    $poster      = $get('itunes_image') ?: $this->getValue('cover');

    $episodeConfig = [
        'show' => [
            'title'    => $this->getValue('feedTitle'),
            'subtitle' => '',
            'summary'  => $this->getValue('feedDesc'),
            'poster'   => $this->getValue('cover'),
            'link'     => $this->getValue('siteUrl') ?: DOMAIN,
        ],
        'title'           => $page->title(),
        'subtitle'        => '',
        'summary'         => strip_tags($page->content()),
        'publicationDate' => $page->date('c'),
        'duration'        => $duration,
        'poster'          => $poster,
        'audio'           => [
            [
                'url'      => $audioUrl,
                'mimeType' => $mime,
                'size'     => $audioLength,
                'title'    => 'Audio',
            ],
        ],
    ];

    $playerConfig = [];
    $feedUrl = $this->getValue('feedUrl');
    if ($feedUrl) {
        $playerConfig['subscribe-button'] = ['feed' => $feedUrl];
    }

    $episodeJson = json_encode($episodeConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $playerJson  = json_encode($playerConfig,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    ?>
    <div id="podlove-episode-player"></div>
    <script>
    (function () {
        var playerDiv = document.getElementById('podlove-episode-player');
        var selectors = [
            'article .post-content',
            'article .entry-content',
            'article .content',
            'article',
            '.post-content',
            '.entry-content',
            'main',
            '#content'
        ];
        var container = null;
        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el) { container = el; break; }
        }
        if (container) {
            container.insertBefore(playerDiv, container.firstChild);
        }
        if (typeof window.podlovePlayer === 'function') {
            window.podlovePlayer(
                '#podlove-episode-player',
                <?php echo $episodeJson ?>,
                <?php echo $playerJson ?>
            );
        }
    }());
    </script>
    <?php
}
```

- [ ] **Step 2: Verify PHP syntax**

Run:
```bash
php -l bl-plugins/podcast/plugin.php
```
Expected output: `No syntax errors detected`

- [ ] **Step 3: Deploy and test manually**

1. Open an episode page on the site (e.g. `https://bewusst-papa.de/<episode-slug>/`)
2. Open browser DevTools → Network tab
3. Confirm `embed.js` is loaded from `cdn.podlove.org`
4. Confirm the Podlove player appears **above** the episode text
5. Press play – audio should start

If the player appears at the bottom instead of above the text:
- Open DevTools → Console
- Run `document.querySelector('article')` – if null, the theme uses a different selector
- Add the correct selector to the `selectors` array in the script

- [ ] **Step 4: Verify player does NOT appear on non-episode pages**

1. Open the site homepage
2. Open DevTools → Network
3. Confirm `embed.js` is NOT loaded

- [ ] **Step 5: Commit**

```bash
git add bl-plugins/podcast/plugin.php
git commit -m "feat(podcast): embed Podlove Web Player v5 on episode pages"
```
