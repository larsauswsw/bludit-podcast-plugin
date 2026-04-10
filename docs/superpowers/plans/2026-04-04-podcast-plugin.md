# Podcast Plugin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Bludit 3.19+ plugin that manages podcast episodes as Bludit pages, provides an iTunes-compatible RSS feed, supports local + external audio, and gives admins/editors a full episode management UI accessible from the admin sidebar.

**Architecture:** Episodes are stored as standard Bludit pages tagged `podcast` with podcast metadata in custom fields. The plugin adds a sidebar link to its configure page, intercepts the feed URL in `beforeAll()` to output iTunes RSS XML, and handles episode CRUD via AJAX also intercepted in `beforeAll()` — avoiding conflicts with Bludit's own form handler.

**Tech Stack:** PHP 7.4+, Bludit 3.19 Plugin API, vanilla JavaScript ES6, Font Awesome (bundled in Bludit admin)

---

## File Map

| File | Responsibility |
|---|---|
| `bl-plugins/podcast/plugin.php` | Plugin class: all hooks, feed, CRUD logic |
| `bl-plugins/podcast/js/podcast-admin.js` | Admin UI: toggles, AJAX, upload/URL switching |
| `bl-plugins/podcast/metadata/episodes.json` | Ordered list of episode page keys |

---

### Task 1: Plugin scaffold

**Files:**
- Create: `bl-plugins/podcast/plugin.php`
- Create: `bl-plugins/podcast/metadata/episodes.json`

- [ ] **Step 1: Create episodes.json**

```json
[]
```

- [ ] **Step 2: Create plugin.php with class shell**

```php
<?php
class Podcast extends Plugin {

    public function init() {
        $this->dbFields = [
            'feedSlug'    => 'podcast.xml',
            'feedTitle'   => '',
            'feedUrl'     => '',
            'siteUrl'     => '',
            'feedDesc'    => '',
            'feedLang'    => 'de-de',
            'author'      => '',
            'email'       => '',
            'category'    => '',
            'cover'       => '',
            'explicit'    => 'no',
            'maxUploadMB' => '512',
        ];
    }

    public function install($position = 0) {
        parent::install($position);

        $dirs = [
            PATH_UPLOADS . 'podcast' . DS,
            PATH_UPLOADS . 'podcast' . DS . 'audio' . DS,
            PATH_UPLOADS . 'podcast' . DS . 'images' . DS,
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $guard = $dir . 'index.php';
            if (!file_exists($guard)) {
                file_put_contents($guard, '<?php // silence');
            }
        }

        $mapFile = $this->pluginPath() . 'metadata' . DS . 'episodes.json';
        if (!file_exists($mapFile)) {
            file_put_contents($mapFile, '[]');
        }
    }

    public function uninstall() {
        parent::uninstall();
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function pluginPath() {
        return PATH_PLUGINS . 'podcast' . DS;
    }

    private function episodesFile() {
        return $this->pluginPath() . 'metadata' . DS . 'episodes.json';
    }

    private function loadEpisodesMap() {
        $raw = file_get_contents($this->episodesFile());
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function saveEpisodesMap(array $map) {
        file_put_contents($this->episodesFile(), json_encode(array_values($map)));
    }

    private function sanitizeFilename($name) {
        $name = mb_strtolower($name, 'UTF-8');
        $name = preg_replace('/\s+/', '-', $name);
        $name = preg_replace('/[^a-z0-9\-_\.]/', '', $name);
        return trim($name, '-');
    }

    private function audioMimeType($ext) {
        $map = [
            'mp3'  => 'audio/mpeg',
            'm4a'  => 'audio/x-m4a',
            'ogg'  => 'audio/ogg',
            'wav'  => 'audio/wav',
        ];
        return isset($map[$ext]) ? $map[$ext] : 'audio/mpeg';
    }
}
```

- [ ] **Step 3: Activate plugin in Bludit admin**

Open `https://[your-site]/admin/plugins`, find "Podcast", activate it.
Expected: No PHP errors, plugin listed as active.

- [ ] **Step 4: Verify upload directories were created**

Check that these directories exist:
- `bl-content/uploads/podcast/`
- `bl-content/uploads/podcast/audio/`
- `bl-content/uploads/podcast/images/`

Each should contain a `index.php` file.

---

### Task 2: Admin sidebar entry

**Files:**
- Modify: `bl-plugins/podcast/plugin.php` — add `adminSidebar()`

- [ ] **Step 1: Add adminSidebar() method to plugin class**

```php
public function adminSidebar() {
    $url = HTML_PATH_ADMIN_ROOT . 'configure-plugin/podcast';
    return '<li><a href="' . $url . '"><span class="fa fa-microphone"></span> Podcast</a></li>';
}
```

- [ ] **Step 2: Verify in browser**

Open the Bludit admin. Expected: "Podcast" with microphone icon appears in the left sidebar for admin and editor roles. Clicking it leads to the plugin settings page.

---

### Task 3: Channel settings form

**Files:**
- Modify: `bl-plugins/podcast/plugin.php` — add `form()`, `formSave()`

- [ ] **Step 1: Add form() method**

```php
public function form() {
    ob_start();
    ?>
    <div class="podcast-settings">
        <h4>Podcast-Kanaleinstellungen</h4>
        <div class="form-group">
            <label>Feed-Dateiname (z.B. podcast.xml)</label>
            <input type="text" class="form-control" name="feedSlug"
                   value="<?php echo $this->getValue('feedSlug') ?>">
        </div>
        <div class="form-group">
            <label>Podcast-Titel</label>
            <input type="text" class="form-control" name="feedTitle"
                   value="<?php echo $this->getValue('feedTitle') ?>">
        </div>
        <div class="form-group">
            <label>Feed-URL (vollständig, z.B. https://example.com/podcast.xml)</label>
            <input type="text" class="form-control" name="feedUrl"
                   value="<?php echo $this->getValue('feedUrl') ?>">
        </div>
        <div class="form-group">
            <label>Website-URL</label>
            <input type="text" class="form-control" name="siteUrl"
                   value="<?php echo $this->getValue('siteUrl') ?: DOMAIN ?>">
        </div>
        <div class="form-group">
            <label>Beschreibung</label>
            <textarea class="form-control" name="feedDesc" rows="3"><?php
                echo $this->getValue('feedDesc') ?></textarea>
        </div>
        <div class="form-group">
            <label>Sprache (z.B. de-de)</label>
            <input type="text" class="form-control" name="feedLang"
                   value="<?php echo $this->getValue('feedLang') ?>">
        </div>
        <div class="form-group">
            <label>Autor</label>
            <input type="text" class="form-control" name="author"
                   value="<?php echo $this->getValue('author') ?>">
        </div>
        <div class="form-group">
            <label>E-Mail (managingEditor)</label>
            <input type="email" class="form-control" name="email"
                   value="<?php echo $this->getValue('email') ?>">
        </div>
        <div class="form-group">
            <label>iTunes-Kategorie (z.B. Society &amp; Culture)</label>
            <input type="text" class="form-control" name="category"
                   value="<?php echo $this->getValue('category') ?>">
        </div>
        <div class="form-group">
            <label>Cover-URL (1400×1400px JPG/PNG)</label>
            <input type="text" class="form-control" name="cover"
                   value="<?php echo $this->getValue('cover') ?>">
        </div>
        <div class="form-group">
            <label>Explicit</label>
            <select class="form-control" name="explicit">
                <option value="no" <?php echo $this->getValue('explicit') === 'no' ? 'selected' : '' ?>>Nein</option>
                <option value="yes" <?php echo $this->getValue('explicit') === 'yes' ? 'selected' : '' ?>>Ja</option>
            </select>
        </div>
        <div class="form-group">
            <label>Max. Upload-Größe (MB)</label>
            <input type="number" class="form-control" name="maxUploadMB"
                   value="<?php echo $this->getValue('maxUploadMB') ?: 512 ?>">
        </div>
    </div>

    <hr>
    <?php echo $this->renderEpisodeSection(); ?>
    <?php
    return ob_get_clean();
}
```

- [ ] **Step 2: Add formSave() method**

```php
public function formSave() {
    $settingsKeys = array_keys($this->dbFields);
    foreach ($settingsKeys as $key) {
        if (isset($_POST[$key])) {
            $this->setValue($key, Sanitize::html($_POST[$key]));
        }
    }
    $this->db->save();
}
```

- [ ] **Step 3: Add placeholder for renderEpisodeSection() (needed by form())**

```php
private function renderEpisodeSection() {
    return '<p><em>Episodenverwaltung folgt...</em></p>';
}
```

- [ ] **Step 4: Verify settings form**

Open `https://[your-site]/admin/configure-plugin/podcast`. 
Expected: All channel settings fields appear. Fill them in and save. Reload page — values should persist.

---

### Task 4: RSS feed generation

**Files:**
- Modify: `bl-plugins/podcast/plugin.php` — add `beforeAll()`, `outputFeed()`

- [ ] **Step 1: Add beforeAll() with feed detection**

```php
public function beforeAll() {
    global $login;

    // ── AJAX API ─────────────────────────────────────────────────────
    if (!empty($_POST['podcast_api_action'])) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$login->isLogged() || !in_array($login->role(), ['admin', 'editor'])) {
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $this->handleApiRequest(Sanitize::html($_POST['podcast_api_action']));
        exit;
    }

    // ── RSS feed ──────────────────────────────────────────────────────
    $requestPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $feedSlug    = $this->getValue('feedSlug') ?: 'podcast.xml';
    $feedPath    = '/' . ltrim($feedSlug, '/');

    if ($requestPath === rtrim($feedPath, '/') || $requestPath . '/' === $feedPath) {
        $this->outputFeed();
        exit;
    }
}
```

- [ ] **Step 2: Add outputFeed() method**

```php
private function outputFeed() {
    global $pages;

    // Load all published pages tagged 'podcast'
    $allPages  = $pages->getAll(false, 'published');
    $episodes  = [];
    foreach ($allPages as $page) {
        $tags = $page->tags();
        if (in_array('podcast', (array) $tags)) {
            $episodes[] = $page;
        }
    }

    // Sort newest first
    usort($episodes, function ($a, $b) {
        return strtotime($b->datePublished()) - strtotime($a->datePublished());
    });

    $title    = htmlspecialchars($this->getValue('feedTitle'), ENT_XML1);
    $feedUrl  = htmlspecialchars($this->getValue('feedUrl'), ENT_XML1);
    $siteUrl  = htmlspecialchars($this->getValue('siteUrl') ?: DOMAIN, ENT_XML1);
    $desc     = htmlspecialchars($this->getValue('feedDesc'), ENT_XML1);
    $lang     = htmlspecialchars($this->getValue('feedLang') ?: 'de-de', ENT_XML1);
    $author   = htmlspecialchars($this->getValue('author'), ENT_XML1);
    $email    = htmlspecialchars($this->getValue('email'), ENT_XML1);
    $category = htmlspecialchars($this->getValue('category'), ENT_XML1);
    $cover    = htmlspecialchars($this->getValue('cover'), ENT_XML1);
    $explicit = $this->getValue('explicit') === 'yes' ? 'yes' : 'no';
    $buildDate = date('r');

    header('Content-Type: application/rss+xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    ?>
<rss version="2.0"
    xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
    xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?php echo $title ?></title>
    <link><?php echo $siteUrl ?></link>
    <description><?php echo $desc ?></description>
    <language><?php echo $lang ?></language>
    <?php if ($email): ?><managingEditor><?php echo $email ?></managingEditor><?php endif ?>
    <lastBuildDate><?php echo $buildDate ?></lastBuildDate>
    <generator>Bludit Podcast Plugin</generator>
    <atom:link href="<?php echo $feedUrl ?>" rel="self" type="application/rss+xml" />
    <itunes:author><?php echo $author ?></itunes:author>
    <itunes:owner>
      <itunes:name><?php echo $author ?></itunes:name>
      <?php if ($email): ?><itunes:email><?php echo $email ?></itunes:email><?php endif ?>
    </itunes:owner>
    <?php if ($cover): ?><itunes:image href="<?php echo $cover ?>" /><?php endif ?>
    <?php if ($category): ?><itunes:category text="<?php echo $category ?>" /><?php endif ?>
    <itunes:explicit><?php echo $explicit ?></itunes:explicit>
    <?php foreach ($episodes as $page):
        $c      = $page->custom();
        $c      = is_array($c) ? $c : [];
        $get    = function ($key) use ($c) { return isset($c[$key]) ? $c[$key] : ''; };

        $audioUrl    = htmlspecialchars($get('audio_url'), ENT_XML1);
        $audioLength = (int) $get('audio_length');
        $audioExt    = strtolower(pathinfo($audioUrl, PATHINFO_EXTENSION));
        $mime        = $this->audioMimeType($audioExt);
        $epTitle     = htmlspecialchars($page->title(), ENT_XML1);
        $epDesc      = htmlspecialchars(strip_tags($page->content()), ENT_XML1);
        $epLink      = htmlspecialchars($page->permalink(), ENT_XML1);
        $epGuid      = $page->key();
        $epDate      = $page->datePublished('r');
        $epNum       = htmlspecialchars($get('itunes_episode'), ENT_XML1);
        $epSeason    = htmlspecialchars($get('itunes_season'), ENT_XML1);
        $epDuration  = htmlspecialchars($get('itunes_duration'), ENT_XML1);
        $epType      = htmlspecialchars($get('itunes_type') ?: 'full', ENT_XML1);
        $epExplicit  = $get('itunes_explicit') === 'yes' ? 'yes' : 'no';
        $epImage     = htmlspecialchars($get('itunes_image'), ENT_XML1);
        if (!$audioUrl) continue;
    ?>
    <item>
      <title><?php echo $epTitle ?></title>
      <description><?php echo $epDesc ?></description>
      <itunes:summary><?php echo $epDesc ?></itunes:summary>
      <?php if ($epNum): ?><itunes:episode><?php echo $epNum ?></itunes:episode><?php endif ?>
      <?php if ($epSeason): ?><itunes:season><?php echo $epSeason ?></itunes:season><?php endif ?>
      <?php if ($epDuration): ?><itunes:duration><?php echo $epDuration ?></itunes:duration><?php endif ?>
      <itunes:episodeType><?php echo $epType ?></itunes:episodeType>
      <itunes:explicit><?php echo $epExplicit ?></itunes:explicit>
      <?php if ($epImage): ?><itunes:image href="<?php echo $epImage ?>" /><?php endif ?>
      <enclosure url="<?php echo $audioUrl ?>" length="<?php echo $audioLength ?>" type="<?php echo $mime ?>" />
      <link><?php echo $epLink ?></link>
      <guid isPermaLink="false"><?php echo $epGuid ?></guid>
      <pubDate><?php echo $epDate ?></pubDate>
    </item>
    <?php endforeach ?>
  </channel>
</rss>
    <?php
}
```

- [ ] **Step 3: Verify feed**

Save channel settings with a feed slug of `podcast.xml`. Open `https://[your-site]/podcast.xml`.
Expected: Valid RSS/XML response, no PHP errors. Empty `<channel>` (no episodes yet).

---

### Task 5: Episode helpers + list view

**Files:**
- Modify: `bl-plugins/podcast/plugin.php` — add `getEpisodes()`, replace `renderEpisodeSection()`

- [ ] **Step 1: Add getEpisodes() helper**

```php
private function getEpisodes() {
    global $pages;
    $allPages = $pages->getAll(false, false);
    $episodes = [];
    foreach ($allPages as $page) {
        $tags = $page->tags();
        if (in_array('podcast', (array) $tags)) {
            $episodes[] = $page;
        }
    }
    usort($episodes, function ($a, $b) {
        $numA = (int) $a->custom('itunes_episode');
        $numB = (int) $b->custom('itunes_episode');
        if ($numA === $numB) {
            return strtotime($b->datePublished()) - strtotime($a->datePublished());
        }
        return $numB - $numA;
    });
    return $episodes;
}
```

- [ ] **Step 2: Replace renderEpisodeSection() with episode list**

```php
private function renderEpisodeSection() {
    $episodes   = $this->getEpisodes();
    $adminRoot  = HTML_PATH_ADMIN_ROOT;
    $pluginUrl  = HTML_PATH_PLUGINS . 'podcast/';
    ob_start();
    ?>
    <div id="podcast-manager">
      <div id="podcast-episode-list">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <h4 style="margin:0">Episoden</h4>
          <button type="button" class="btn btn-sm btn-primary" id="podcast-new-btn">
            + Neue Episode
          </button>
        </div>
        <?php if (empty($episodes)): ?>
          <p class="text-muted">Noch keine Episoden vorhanden.</p>
        <?php else: ?>
          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th>#</th><th>Titel</th><th>Staffel</th><th>Typ</th>
                <th>Datum</th><th>Status</th><th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($episodes as $ep):
                  $c       = $ep->custom();
                  $c       = is_array($c) ? $c : [];
                  $get     = function ($k) use ($c) { return isset($c[$k]) ? $c[$k] : ''; };
              ?>
              <tr>
                <td><?php echo htmlspecialchars($get('itunes_episode')) ?></td>
                <td><?php echo htmlspecialchars($ep->title()) ?></td>
                <td><?php echo htmlspecialchars($get('itunes_season')) ?></td>
                <td><?php echo htmlspecialchars($get('itunes_type') ?: 'full') ?></td>
                <td><?php echo $ep->datePublished('d.m.Y') ?></td>
                <td><?php echo $ep->status() === 'published' ? '<span class="label label-success">veröffentlicht</span>' : '<span class="label label-default">Entwurf</span>' ?></td>
                <td style="white-space:nowrap">
                  <button type="button" class="btn btn-xs btn-default podcast-edit-btn"
                    data-key="<?php echo $ep->key() ?>"
                    data-title="<?php echo htmlspecialchars($ep->title(), ENT_QUOTES) ?>"
                    data-content="<?php echo htmlspecialchars($ep->content(), ENT_QUOTES) ?>"
                    data-episode="<?php echo htmlspecialchars($get('itunes_episode'), ENT_QUOTES) ?>"
                    data-season="<?php echo htmlspecialchars($get('itunes_season'), ENT_QUOTES) ?>"
                    data-type="<?php echo htmlspecialchars($get('itunes_type') ?: 'full', ENT_QUOTES) ?>"
                    data-explicit="<?php echo htmlspecialchars($get('itunes_explicit') ?: 'no', ENT_QUOTES) ?>"
                    data-duration="<?php echo htmlspecialchars($get('itunes_duration'), ENT_QUOTES) ?>"
                    data-image="<?php echo htmlspecialchars($get('itunes_image'), ENT_QUOTES) ?>"
                    data-audio-url="<?php echo htmlspecialchars($get('audio_url'), ENT_QUOTES) ?>"
                    data-status="<?php echo $ep->status() ?>"
                    data-date="<?php echo $ep->datePublished('Y-m-d\TH:i') ?>">
                    Bearbeiten
                  </button>
                  <button type="button" class="btn btn-xs btn-danger podcast-delete-btn"
                    data-key="<?php echo $ep->key() ?>"
                    data-title="<?php echo htmlspecialchars($ep->title(), ENT_QUOTES) ?>">
                    Löschen
                  </button>
                </td>
              </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        <?php endif ?>
      </div>

      <?php echo $this->renderEpisodeForm() ?>
    </div>
    <?php
    return ob_get_clean();
}
```

- [ ] **Step 3: Add empty renderEpisodeForm() stub**

```php
private function renderEpisodeForm() {
    return '<div id="podcast-episode-form" style="display:none"><p>Formular folgt...</p></div>';
}
```

- [ ] **Step 4: Verify episode list**

Open `https://[your-site]/admin/configure-plugin/podcast`.
Expected: "Episoden" section shows below channel settings with "Neue Episode" button and "Noch keine Episoden vorhanden." message.

---

### Task 6: Episode create/edit form

**Files:**
- Modify: `bl-plugins/podcast/plugin.php` — replace `renderEpisodeForm()`

- [ ] **Step 1: Replace renderEpisodeForm() with full form**

```php
private function renderEpisodeForm() {
    $apiUrl = DOMAIN;
    ob_start();
    ?>
    <div id="podcast-episode-form" style="display:none;margin-top:20px">
      <h4 id="podcast-form-title">Neue Episode</h4>
      <input type="hidden" id="pe-key" value="">

      <div class="form-group">
        <label>Titel *</label>
        <input type="text" class="form-control" id="pe-title" required>
      </div>
      <div class="form-group">
        <label>Beschreibung *</label>
        <textarea class="form-control" id="pe-content" rows="4" required></textarea>
      </div>
      <div class="row">
        <div class="col-sm-3">
          <div class="form-group">
            <label>Episode #</label>
            <input type="number" class="form-control" id="pe-episode" min="0">
          </div>
        </div>
        <div class="col-sm-3">
          <div class="form-group">
            <label>Staffel</label>
            <input type="number" class="form-control" id="pe-season" min="0">
          </div>
        </div>
        <div class="col-sm-3">
          <div class="form-group">
            <label>Typ</label>
            <select class="form-control" id="pe-type">
              <option value="full">full</option>
              <option value="trailer">trailer</option>
              <option value="bonus">bonus</option>
            </select>
          </div>
        </div>
        <div class="col-sm-3">
          <div class="form-group">
            <label>Explicit</label>
            <select class="form-control" id="pe-explicit">
              <option value="no">Nein</option>
              <option value="yes">Ja</option>
            </select>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label>Dauer (HH:MM:SS)</label>
        <input type="text" class="form-control" id="pe-duration" placeholder="00:45:00">
      </div>

      <!-- Episode image -->
      <div class="form-group">
        <label>Episodenbild</label>
        <div>
          <label class="radio-inline">
            <input type="radio" name="pe-image-mode" value="url" checked> URL
          </label>
          <label class="radio-inline">
            <input type="radio" name="pe-image-mode" value="file"> Datei hochladen
          </label>
        </div>
        <input type="text" class="form-control" id="pe-image-url" placeholder="https://" style="margin-top:6px">
        <input type="file" class="form-control" id="pe-image-file" accept=".jpg,.jpeg,.png" style="display:none;margin-top:6px">
      </div>

      <!-- Audio -->
      <div class="form-group">
        <label>Audio *</label>
        <div>
          <label class="radio-inline">
            <input type="radio" name="pe-audio-mode" value="url" checked> Externe URL
          </label>
          <label class="radio-inline">
            <input type="radio" name="pe-audio-mode" value="file"> Datei hochladen
          </label>
        </div>
        <input type="text" class="form-control" id="pe-audio-url" placeholder="https://..." style="margin-top:6px">
        <input type="file" class="form-control" id="pe-audio-file" accept=".mp3,.m4a,.ogg,.wav" style="display:none;margin-top:6px">
        <small class="text-muted">Max. <?php echo $this->getValue('maxUploadMB') ?: 512 ?> MB</small>
      </div>

      <div class="row">
        <div class="col-sm-6">
          <div class="form-group">
            <label>Veröffentlichungsdatum *</label>
            <input type="datetime-local" class="form-control" id="pe-date"
                   value="<?php echo date('Y-m-d\TH:i') ?>">
          </div>
        </div>
        <div class="col-sm-6">
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" id="pe-status">
              <option value="published">Veröffentlicht</option>
              <option value="draft">Entwurf</option>
            </select>
          </div>
        </div>
      </div>

      <div id="podcast-save-error" class="alert alert-danger" style="display:none"></div>

      <button type="button" class="btn btn-primary" id="podcast-save-btn">Speichern</button>
      <button type="button" class="btn btn-default" id="podcast-cancel-btn" style="margin-left:8px">Abbrechen</button>
      <span id="podcast-saving" style="display:none;margin-left:10px">
        <span class="fa fa-spinner fa-spin"></span> Wird gespeichert...
      </span>
    </div>

    <script>
    var PODCAST_API_URL = <?php echo json_encode(DOMAIN) ?>;
    </script>
    <script src="<?php echo HTML_PATH_PLUGINS ?>podcast/js/podcast-admin.js"></script>
    <?php
    return ob_get_clean();
}
```

- [ ] **Step 2: Verify form renders**

Open `https://[your-site]/admin/configure-plugin/podcast`.
Expected: Episode form section exists (hidden). "Neue Episode" button visible. No JS errors in browser console.

---

### Task 7: Episode save AJAX handler

**Files:**
- Modify: `bl-plugins/podcast/plugin.php` — add `handleApiRequest()`, `handleSaveEpisode()`, `handleUpload()`

- [ ] **Step 1: Add handleApiRequest()**

```php
private function handleApiRequest($action) {
    switch ($action) {
        case 'save_episode':
            echo json_encode($this->handleSaveEpisode($_POST, $_FILES));
            break;
        case 'delete_episode':
            $key = isset($_POST['episode_key']) ? Sanitize::html($_POST['episode_key']) : '';
            echo json_encode($this->handleDeleteEpisode($key));
            break;
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
}
```

- [ ] **Step 2: Add handleUpload()**

```php
private function handleUpload($fileField, $type) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    $file   = $_FILES[$fileField];
    $maxMB  = (int) ($this->getValue('maxUploadMB') ?: 512);
    $maxBytes = $maxMB * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return '';
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($type === 'audio') {
        $allowed = ['mp3', 'm4a', 'ogg', 'wav'];
        $dir     = PATH_UPLOADS . 'podcast' . DS . 'audio' . DS;
        $urlBase = HTML_PATH_UPLOADS . 'podcast/audio/';
    } else {
        $allowed = ['jpg', 'jpeg', 'png'];
        $dir     = PATH_UPLOADS . 'podcast' . DS . 'images' . DS;
        $urlBase = HTML_PATH_UPLOADS . 'podcast/images/';
    }
    if (!in_array($ext, $allowed)) {
        return '';
    }
    $filename = $this->sanitizeFilename(pathinfo($file['name'], PATHINFO_FILENAME)) . '.' . $ext;
    // Prevent overwrite
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $i    = 1;
    while (file_exists($dir . $filename)) {
        $filename = $base . '-' . $i . '.' . $ext;
        $i++;
    }
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return '';
    }
    return $urlBase . $filename;
}
```

- [ ] **Step 3: Add handleSaveEpisode()**

```php
private function handleSaveEpisode($post, $files = []) {
    global $pages;

    $title   = isset($post['title'])   ? trim(Sanitize::html($post['title']))   : '';
    $content = isset($post['content']) ? trim($post['content'])                 : '';
    $status  = isset($post['status'])  && $post['status'] === 'draft' ? 'draft' : 'published';
    $date    = isset($post['date'])    ? trim(Sanitize::html($post['date']))     : date('Y-m-d H:i:s');
    // Convert datetime-local format (2025-12-08T15:00) to Bludit format
    $date    = str_replace('T', ' ', $date) . ':00';
    $key     = isset($post['key'])     ? trim(Sanitize::html($post['key']))      : '';

    if (!$title) {
        return ['error' => 'Titel ist erforderlich'];
    }

    // Handle image
    $imageUrl = '';
    if (!empty($files['image_file']) && $files['image_file']['error'] === UPLOAD_ERR_OK) {
        $imageUrl = $this->handleUpload('image_file', 'image');
    }
    if (!$imageUrl && !empty($post['image_url'])) {
        $imageUrl = trim($post['image_url']);
    }

    // Handle audio
    $audioUrl    = '';
    $audioLength = 0;
    if (!empty($files['audio_file']) && $files['audio_file']['error'] === UPLOAD_ERR_OK) {
        $audioUrl = $this->handleUpload('audio_file', 'audio');
        if ($audioUrl) {
            $localPath = PATH_UPLOADS . 'podcast' . DS . 'audio' . DS . basename($audioUrl);
            if (file_exists($localPath)) {
                $audioLength = filesize($localPath);
            }
        }
    }
    if (!$audioUrl && !empty($post['audio_url'])) {
        $audioUrl = trim($post['audio_url']);
    }

    if (!$audioUrl) {
        return ['error' => 'Audio-URL oder -Datei ist erforderlich'];
    }

    $custom = [
        'itunes_episode'  => isset($post['episode'])  ? Sanitize::html($post['episode'])  : '',
        'itunes_season'   => isset($post['season'])   ? Sanitize::html($post['season'])   : '',
        'itunes_type'     => isset($post['type'])     ? Sanitize::html($post['type'])     : 'full',
        'itunes_explicit' => isset($post['explicit']) ? Sanitize::html($post['explicit']) : 'no',
        'itunes_duration' => isset($post['duration']) ? Sanitize::html($post['duration']) : '',
        'itunes_image'    => $imageUrl,
        'audio_url'       => $audioUrl,
        'audio_length'    => (string) $audioLength,
    ];

    $pageArgs = [
        'title'   => $title,
        'content' => $content,
        'status'  => $status,
        'tags'    => 'podcast',
        'date'    => $date,
        'custom'  => $custom,
    ];

    if ($key) {
        // Edit existing page
        $pageArgs['key'] = $key;
        $pages->edit($pageArgs);
        return ['success' => true, 'key' => $key];
    } else {
        // Create new page
        $newKey = $pages->addNew($pageArgs);
        // Update episodes.json
        $map = $this->loadEpisodesMap();
        if ($newKey && !in_array($newKey, $map)) {
            $map[] = $newKey;
            $this->saveEpisodesMap($map);
        }
        return ['success' => true, 'key' => $newKey];
    }
}
```

- [ ] **Step 4: Verify save via browser console**

Open browser DevTools on the configure-plugin page. In Console:
```javascript
fetch('/', {
  method: 'POST',
  body: new URLSearchParams({
    podcast_api_action: 'save_episode',
    title: 'Test Episode',
    content: 'Test Beschreibung',
    audio_url: 'https://example.com/test.mp3',
    type: 'full',
    status: 'published',
    date: '2026-04-04T12:00'
  })
}).then(r => r.json()).then(console.log)
```
Expected: `{success: true, key: "test-episode"}` (or similar slug).
Verify in Bludit pages list that the page was created with tag `podcast`.

---

### Task 8: Episode delete AJAX handler

**Files:**
- Modify: `bl-plugins/podcast/plugin.php` — add `handleDeleteEpisode()`

- [ ] **Step 1: Add handleDeleteEpisode()**

```php
private function handleDeleteEpisode($key) {
    global $pages;

    if (!$key) {
        return ['error' => 'Kein Schlüssel angegeben'];
    }

    $page = $pages->getByKey($key);
    if (!$page) {
        return ['error' => 'Episode nicht gefunden'];
    }

    // Only delete pages that have the podcast tag
    $tags = $page->tags();
    if (!in_array('podcast', (array) $tags)) {
        return ['error' => 'Seite gehört nicht zum Podcast'];
    }

    $pages->delete($key);

    // Remove from episodes.json
    $map = $this->loadEpisodesMap();
    $map = array_filter($map, function ($k) use ($key) { return $k !== $key; });
    $this->saveEpisodesMap(array_values($map));

    return ['success' => true];
}
```

- [ ] **Step 2: Verify delete**

Using the episode key from Task 7 Step 4, run in browser console:
```javascript
fetch('/', {
  method: 'POST',
  body: new URLSearchParams({
    podcast_api_action: 'delete_episode',
    episode_key: 'test-episode'
  })
}).then(r => r.json()).then(console.log)
```
Expected: `{success: true}`. Verify in Bludit pages list that the page is gone.

---

### Task 9: JavaScript admin UI

**Files:**
- Create: `bl-plugins/podcast/js/podcast-admin.js`

- [ ] **Step 1: Create podcast-admin.js**

```javascript
(function () {
    'use strict';

    // ── DOM refs ──────────────────────────────────────────────────────
    var list        = document.getElementById('podcast-episode-list');
    var form        = document.getElementById('podcast-episode-form');
    var formTitle   = document.getElementById('podcast-form-title');
    var newBtn      = document.getElementById('podcast-new-btn');
    var saveBtn     = document.getElementById('podcast-save-btn');
    var cancelBtn   = document.getElementById('podcast-cancel-btn');
    var saving      = document.getElementById('podcast-saving');
    var errorBox    = document.getElementById('podcast-save-error');

    var peKey       = document.getElementById('pe-key');
    var peTitle     = document.getElementById('pe-title');
    var peContent   = document.getElementById('pe-content');
    var peEpisode   = document.getElementById('pe-episode');
    var peSeason    = document.getElementById('pe-season');
    var peType      = document.getElementById('pe-type');
    var peExplicit  = document.getElementById('pe-explicit');
    var peDuration  = document.getElementById('pe-duration');
    var peDate      = document.getElementById('pe-date');
    var peStatus    = document.getElementById('pe-status');
    var peImageUrl  = document.getElementById('pe-image-url');
    var peImageFile = document.getElementById('pe-image-file');
    var peAudioUrl  = document.getElementById('pe-audio-url');
    var peAudioFile = document.getElementById('pe-audio-file');

    if (!list) return; // not on podcast page

    // ── helpers ───────────────────────────────────────────────────────
    function showForm(title) {
        formTitle.textContent = title;
        list.style.display  = 'none';
        form.style.display  = 'block';
        errorBox.style.display = 'none';
    }

    function showList() {
        form.style.display  = 'none';
        list.style.display  = 'block';
    }

    function clearForm() {
        peKey.value      = '';
        peTitle.value    = '';
        peContent.value  = '';
        peEpisode.value  = '';
        peSeason.value   = '';
        peType.value     = 'full';
        peExplicit.value = 'no';
        peDuration.value = '';
        peDate.value     = new Date().toISOString().slice(0, 16);
        peStatus.value   = 'published';
        peImageUrl.value = '';
        peAudioUrl.value = '';
        // Reset radio to URL mode
        setMode('pe-audio-mode', 'url');
        setMode('pe-image-mode', 'url');
    }

    function setMode(radioName, value) {
        var radios = document.querySelectorAll('input[name="' + radioName + '"]');
        radios.forEach(function (r) { r.checked = (r.value === value); });
        updateModeVisibility(radioName);
    }

    function updateModeVisibility(radioName) {
        var selected = document.querySelector('input[name="' + radioName + '"]:checked');
        if (!selected) return;
        var mode = selected.value;
        if (radioName === 'pe-audio-mode') {
            peAudioUrl.style.display  = mode === 'url'  ? 'block' : 'none';
            peAudioFile.style.display = mode === 'file' ? 'block' : 'none';
        } else {
            peImageUrl.style.display  = mode === 'url'  ? 'block' : 'none';
            peImageFile.style.display = mode === 'file' ? 'block' : 'none';
        }
    }

    function showError(msg) {
        errorBox.textContent    = msg;
        errorBox.style.display  = 'block';
    }

    function setSaving(on) {
        saveBtn.disabled        = on;
        saving.style.display    = on ? 'inline' : 'none';
    }

    // ── radio toggle listeners ────────────────────────────────────────
    document.querySelectorAll('input[name="pe-audio-mode"], input[name="pe-image-mode"]')
        .forEach(function (r) {
            r.addEventListener('change', function () {
                updateModeVisibility(r.name);
            });
        });

    // ── new episode ───────────────────────────────────────────────────
    newBtn.addEventListener('click', function () {
        clearForm();
        showForm('Neue Episode');
    });

    // ── cancel ────────────────────────────────────────────────────────
    cancelBtn.addEventListener('click', showList);

    // ── edit ──────────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.podcast-edit-btn');
        if (!btn) return;
        var d = btn.dataset;
        clearForm();
        peKey.value      = d.key;
        peTitle.value    = d.title;
        peContent.value  = d.content;
        peEpisode.value  = d.episode;
        peSeason.value   = d.season;
        peType.value     = d.type     || 'full';
        peExplicit.value = d.explicit || 'no';
        peDuration.value = d.duration;
        peImageUrl.value = d.image;
        peAudioUrl.value = d.audioUrl;
        peDate.value     = d.date;
        peStatus.value   = d.status   || 'published';
        if (d.audioUrl) setMode('pe-audio-mode', 'url');
        if (d.image)    setMode('pe-image-mode', 'url');
        showForm('Episode bearbeiten');
    });

    // ── delete ────────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.podcast-delete-btn');
        if (!btn) return;
        if (!confirm('Episode "' + btn.dataset.title + '" wirklich löschen?')) return;
        var body = new URLSearchParams({
            podcast_api_action: 'delete_episode',
            episode_key: btn.dataset.key
        });
        fetch(PODCAST_API_URL, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) { alert('Fehler: ' + data.error); return; }
                location.reload();
            })
            .catch(function () { alert('Netzwerkfehler beim Löschen.'); });
    });

    // ── save ──────────────────────────────────────────────────────────
    saveBtn.addEventListener('click', function () {
        var title = peTitle.value.trim();
        var audioMode = document.querySelector('input[name="pe-audio-mode"]:checked').value;
        var audioUrl  = peAudioUrl.value.trim();
        var audioFile = peAudioFile.files[0];

        if (!title) { showError('Bitte Titel eingeben.'); return; }
        if (audioMode === 'url' && !audioUrl) {
            showError('Bitte Audio-URL eingeben oder eine Datei hochladen.');
            return;
        }
        if (audioMode === 'file' && !audioFile) {
            showError('Bitte eine Audiodatei auswählen.');
            return;
        }

        var imageMode = document.querySelector('input[name="pe-image-mode"]:checked').value;
        var fd = new FormData();
        fd.append('podcast_api_action', 'save_episode');
        fd.append('key',      peKey.value);
        fd.append('title',    title);
        fd.append('content',  peContent.value.trim());
        fd.append('episode',  peEpisode.value);
        fd.append('season',   peSeason.value);
        fd.append('type',     peType.value);
        fd.append('explicit', peExplicit.value);
        fd.append('duration', peDuration.value.trim());
        fd.append('date',     peDate.value);
        fd.append('status',   peStatus.value);
        fd.append('image_url', imageMode === 'url' ? peImageUrl.value.trim() : '');
        fd.append('audio_url', audioMode === 'url' ? audioUrl : '');
        if (imageMode === 'file' && peImageFile.files[0]) {
            fd.append('image_file', peImageFile.files[0]);
        }
        if (audioMode === 'file' && audioFile) {
            fd.append('audio_file', audioFile);
        }

        setSaving(true);
        fetch(PODCAST_API_URL, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                setSaving(false);
                if (data.error) { showError(data.error); return; }
                location.reload();
            })
            .catch(function () {
                setSaving(false);
                showError('Netzwerkfehler beim Speichern.');
            });
    });

    // init visibility
    updateModeVisibility('pe-audio-mode');
    updateModeVisibility('pe-image-mode');
}());
```

- [ ] **Step 2: Verify full workflow**

1. Open `https://[your-site]/admin/configure-plugin/podcast`
2. Click "Neue Episode" → form appears
3. Fill in: Titel, Beschreibung, Audio-URL, Typ, Datum
4. Click "Speichern" → spinner shows → page reloads → episode appears in list
5. Click "Bearbeiten" → form pre-fills with episode data
6. Change a field, save → episode updates in list
7. Click "Löschen" → confirm → episode removed from list
8. Open `https://[your-site]/podcast.xml` → episode appears in feed

---

## Self-Review

### Spec coverage check

| Requirement | Task |
|---|---|
| Episoden als Bludit-Seiten mit Tag `podcast` | Task 7 |
| Custom Fields (episode, season, duration, explicit, type, image) | Task 7 |
| Admin-Sidebar-Eintrag | Task 2 |
| Nur admin + editor haben Zugriff | Task 7 (beforeAll auth check) |
| iTunes-kompatibler RSS 2.0 Feed | Task 4 |
| Alle iTunes-Felder im Feed | Task 4 |
| Kanaleinstellungen konfigurierbar | Task 3 |
| Feed-URL konfigurierbar | Task 3 + 4 |
| Lokaler Datei-Upload (Audio + Bild) | Task 7 (handleUpload) |
| Externe URL als Audio-Quelle | Task 7 |
| Episodenbild pro Episode | Task 7 |
| Upload-Verzeichnisse mit index.php | Task 1 (install) |
| Seite wird bei Episode-Erstellung angelegt | Task 7 |
| Seite wird bei Löschen entfernt | Task 8 |
| episodes.json Mapping | Task 7 + 8 |
| JavaScript Upload/URL Toggle | Task 9 |
| Episodenliste in Admin | Task 5 |
| Episodenformular mit allen Feldern | Task 6 |

All requirements covered. No gaps found.
