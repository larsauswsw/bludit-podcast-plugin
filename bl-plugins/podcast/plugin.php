<?php
/**
 * Podcast Plugin für Bludit 3.19+
 * Verwaltet Podcast-Episoden als Bludit-Seiten (Tag: podcast),
 * generiert einen iTunes-kompatiblen RSS 2.0 Feed und bietet
 * eine Admin-UI für Admins und Editoren.
 */
class Podcast extends Plugin {

    // ═══════════════════════════════════════════════════════════════════
    // BLUDIT PLUGIN LIFECYCLE
    // ═══════════════════════════════════════════════════════════════════

    public function init() {
        $this->name        = 'Podcast';
        $this->description = 'Podcast Plugin: Episodenverwaltung, iTunes-Feed, Datei-Upload';
        $this->author      = 'Lars Miesner';
        $this->version     = '1.0.0';

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

        // Verzeichnisse anlegen
        $dirs = [
            PATH_UPLOADS . 'podcast' . DS,
            PATH_UPLOADS . 'podcast' . DS . 'audio' . DS,
            PATH_UPLOADS . 'podcast' . DS . 'images' . DS,
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            // Directory listing verhindern
            $guard = $dir . 'index.php';
            if (!file_exists($guard)) {
                file_put_contents($guard, '<?php // silence');
            }
        }

        // episodes.json initialisieren
        $mapFile = $this->pluginPath() . 'metadata' . DS . 'episodes.json';
        if (!file_exists($mapFile)) {
            file_put_contents($mapFile, '[]');
        }
    }

    public function uninstall() {
        parent::uninstall();
    }

    // ═══════════════════════════════════════════════════════════════════
    // ADMIN NAVIGATION
    // ═══════════════════════════════════════════════════════════════════

    public function adminSidebar() {
        // URL-Format: /admin/plugin/podcast → Bludit ruft adminView() auf
        $url = HTML_PATH_ADMIN_ROOT . 'plugin/podcast';
        return '<a class="nav-link" href="' . $url . '"><span class="fa fa-microphone"></span> Podcast</a>';
    }

    /**
     * Wird vom Admin-Theme gerendert wenn URL = /admin/plugin/podcast.
     * Das Admin-Chrome (Sidebar, Header, CSS/JS) kommt automatisch von Bludit.
     */
    public function adminView() {
        global $layout, $login;

        $layout['title'] = 'Podcast – Episoden';

        if (!in_array($login->role(), ['admin', 'editor'])) {
            return '<div class="alert alert-danger">Zugriff verweigert.</div>';
        }

        ob_start();
        ?>
        <div class="container-fluid">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><span class="fa fa-microphone"></span> Podcast – Episoden</h2>
            <?php if ($login->role() === 'admin'): ?>
            <a href="<?php echo HTML_PATH_ADMIN_ROOT ?>configure-plugin/Podcast"
               class="btn btn-sm btn-outline-secondary">
              <span class="fa fa-gear"></span> Kanaleinstellungen
            </a>
            <?php endif ?>
          </div>

          <?php
          $apiUrl = DOMAIN;
          try {
              echo $this->renderEpisodeSection();
              echo $this->renderEpisodeForm($apiUrl);
          } catch (Exception $e) {
              echo '<div class="alert alert-danger">Fehler: '
                  . htmlspecialchars($e->getMessage()) . '</div>';
          }
          ?>
        </div>

        <script>var PODCAST_API_URL = <?php echo json_encode($apiUrl) ?>;</script>
        <script src="<?php echo DOMAIN ?>/bl-plugins/podcast/js/podcast-admin.js"></script>
        <?php
        return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════
    // SETTINGS FORM
    // ═══════════════════════════════════════════════════════════════════

    public function form() {
        ob_start();
        ?>
        <div class="podcast-settings" id="podcast-settings-wrap">
            <h4>Podcast-Kanaleinstellungen</h4>

            <div class="form-group">
                <label>Feed-Dateiname (z.B. podcast.xml)</label>
                <input type="text" class="form-control" name="feedSlug"
                       value="<?php echo htmlspecialchars($this->getValue('feedSlug'), ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label>Podcast-Titel</label>
                <input type="text" class="form-control" name="feedTitle"
                       value="<?php echo htmlspecialchars($this->getValue('feedTitle'), ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label>Feed-URL (vollständig, z.B. https://example.com/podcast.xml)</label>
                <input type="text" class="form-control" name="feedUrl"
                       value="<?php echo htmlspecialchars($this->getValue('feedUrl'), ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label>Website-URL</label>
                <input type="text" class="form-control" name="siteUrl"
                       value="<?php echo htmlspecialchars($this->getValue('siteUrl') ?: DOMAIN, ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label>Beschreibung</label>
                <textarea class="form-control" name="feedDesc" rows="3"><?php
                    echo htmlspecialchars($this->getValue('feedDesc')) ?></textarea>
            </div>
            <div class="form-group">
                <label>Sprache (z.B. de-de)</label>
                <input type="text" class="form-control" name="feedLang"
                       value="<?php echo htmlspecialchars($this->getValue('feedLang'), ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label>Autor</label>
                <input type="text" class="form-control" name="author"
                       value="<?php echo htmlspecialchars($this->getValue('author'), ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label>E-Mail (managingEditor)</label>
                <input type="email" class="form-control" name="email"
                       value="<?php echo htmlspecialchars($this->getValue('email'), ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label>iTunes-Kategorie (z.B. Society &amp; Culture)</label>
                <input type="text" class="form-control" name="category"
                       value="<?php echo htmlspecialchars($this->getValue('category'), ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label>Cover-URL (1400×1400px JPG/PNG empfohlen)</label>
                <input type="text" class="form-control" name="cover"
                       value="<?php echo htmlspecialchars($this->getValue('cover'), ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
                <label>Explicit</label>
                <select class="form-control" name="explicit">
                    <option value="no"  <?php echo $this->getValue('explicit') !== 'yes' ? 'selected' : '' ?>>Nein</option>
                    <option value="yes" <?php echo $this->getValue('explicit') === 'yes' ? 'selected' : '' ?>>Ja</option>
                </select>
            </div>
            <div class="form-group">
                <label>Max. Upload-Größe (MB)</label>
                <input type="number" class="form-control" name="maxUploadMB"
                       value="<?php echo (int) ($this->getValue('maxUploadMB') ?: 512) ?>">
            </div>
        </div>

        <hr>
        <p class="text-muted">
            <span class="fa fa-info-circle"></span>
            Episoden verwalten:
            <a href="<?php echo HTML_PATH_ADMIN_ROOT ?>podcast-episodes">
                Zur Episodenverwaltung
            </a>
        </p>
        <?php
        return ob_get_clean();
    }

    // Kanaleinstellungen werden von parent::post() gespeichert (dbFields aus $_POST)
    // Kein formSave() nötig — Bludit ruft post() auf, nicht formSave()

    // ═══════════════════════════════════════════════════════════════════
    // BEFORE ALL: FEED + AJAX API
    // ═══════════════════════════════════════════════════════════════════

    public function beforeAll() {
        $requestPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        // ── AJAX API ─────────────────────────────────────────────────
        if (!empty($_POST['podcast_api_action'])) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            // Login aus Session lesen (funktioniert auch im Frontend-Kontext)
            $loginCheck = new Login();
            if (!$loginCheck->isLogged() || !in_array($loginCheck->role(), ['admin', 'editor'])) {
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
            $action = htmlspecialchars(strip_tags($_POST['podcast_api_action']), ENT_QUOTES, 'UTF-8');
            try {
                $this->handleApiRequest($action);
            } catch (Throwable $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
        }

        // ── RSS-Feed ──────────────────────────────────────────────────
        $feedSlug = $this->getValue('feedSlug') ?: 'podcast.xml';
        $feedPath = rtrim('/' . ltrim($feedSlug, '/'), '/');

        if ($requestPath === $feedPath) {
            $this->outputFeed();
            exit;
        }
    }

    public function beforeAdminLoad() {
        // Nicht mehr benötigt – API läuft über beforeAll()
    }

    public function siteHead() {
        global $url, $page;

        if ($url->whereAmI() !== 'page') return;
        if (!$page instanceof Page || !$this->pageHasPodcastTag($page)) return;

        $meta = $this->getEpisodeMeta($page->key());
        if (empty($meta['audio_url'])) return;

        echo '<script src="https://cdn.podlove.org/web-player/5.x/embed.js"></script>' . "\n";
    }

    public function siteBodyEnd() {
        global $url, $page;

        if ($url->whereAmI() !== 'page') return;
        if (!$page instanceof Page || !$this->pageHasPodcastTag($page)) return;

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
            'summary'         => html_entity_decode(strip_tags($page->content()), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'publicationDate' => ($ts = strtotime($page->dateRaw())) !== false ? date('c', $ts) : '',
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

        $flags       = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
        $episodeJson = json_encode($episodeConfig, $flags);
        $playerJson  = json_encode($playerConfig,  $flags);
        if ($episodeJson === false || $playerJson === false) return;

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

    // ═══════════════════════════════════════════════════════════════════
    // RSS FEED GENERATION
    // ═══════════════════════════════════════════════════════════════════

    private function outputFeed() {
        global $pages;

        // Alle veröffentlichten Seiten mit Tag 'podcast' laden
        $keys     = $pages->getPublishedDB(true);
        $episodes = [];
        foreach ($keys as $key) {
            $page = new Page($key);
            if ($this->pageHasPodcastTag($page)) {
                $episodes[] = $page;
            }
        }

        // Neueste zuerst
        usort($episodes, function ($a, $b) {
            return strtotime($b->dateRaw()) - strtotime($a->dateRaw());
        });

        $title     = htmlspecialchars($this->getValue('feedTitle'),           ENT_XML1, 'UTF-8');
        $feedUrl   = htmlspecialchars($this->getValue('feedUrl'),             ENT_XML1, 'UTF-8');
        $siteUrl   = htmlspecialchars($this->getValue('siteUrl') ?: DOMAIN,  ENT_XML1, 'UTF-8');
        $desc      = htmlspecialchars($this->getValue('feedDesc'),            ENT_XML1, 'UTF-8');
        $lang      = htmlspecialchars($this->getValue('feedLang') ?: 'de-de', ENT_XML1, 'UTF-8');
        $author    = htmlspecialchars($this->getValue('author'),              ENT_XML1, 'UTF-8');
        $email     = htmlspecialchars($this->getValue('email'),               ENT_XML1, 'UTF-8');
        $category  = htmlspecialchars($this->getValue('category'),            ENT_XML1, 'UTF-8');
        $cover     = htmlspecialchars($this->getValue('cover'),               ENT_XML1, 'UTF-8');
        $explicit  = $this->getValue('explicit') === 'yes' ? 'yes' : 'no';
        $buildDate = date('r');

        header('Content-Type: application/rss+xml; charset=UTF-8');
        // Kein Output vor diesem Punkt — ob_start() wurde ggf. von Bludit gestartet
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0"' . "\n";
        echo '    xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"' . "\n";
        echo '    xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo '<channel>' . "\n";
        echo '  <title>' . $title . '</title>' . "\n";
        echo '  <link>' . $siteUrl . '</link>' . "\n";
        echo '  <description>' . $desc . '</description>' . "\n";
        echo '  <language>' . $lang . '</language>' . "\n";
        if ($email) {
            echo '  <managingEditor>' . $email . '</managingEditor>' . "\n";
        }
        echo '  <lastBuildDate>' . $buildDate . '</lastBuildDate>' . "\n";
        echo '  <generator>Bludit Podcast Plugin</generator>' . "\n";
        if ($feedUrl) {
            echo '  <atom:link href="' . $feedUrl . '" rel="self" type="application/rss+xml" />' . "\n";
        }
        echo '  <itunes:author>' . $author . '</itunes:author>' . "\n";
        echo '  <itunes:owner>' . "\n";
        echo '    <itunes:name>' . $author . '</itunes:name>' . "\n";
        if ($email) {
            echo '    <itunes:email>' . $email . '</itunes:email>' . "\n";
        }
        echo '  </itunes:owner>' . "\n";
        if ($cover) {
            echo '  <itunes:image href="' . $cover . '" />' . "\n";
        }
        if ($category) {
            echo '  <itunes:category text="' . $category . '" />' . "\n";
        }
        echo '  <itunes:explicit>' . $explicit . '</itunes:explicit>' . "\n";

        foreach ($episodes as $page) {
            $meta = $this->getEpisodeMeta($page->key());
            $get  = function ($k) use ($meta) { return isset($meta[$k]) ? $meta[$k] : ''; };

            $audioUrl    = $get('audio_url');
            if (!$audioUrl) continue;

            $audioLength = (int) $get('audio_length');
            $audioExt    = strtolower(pathinfo(parse_url($audioUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $mime        = $this->audioMimeType($audioExt);

            $epTitle    = htmlspecialchars($page->title(),                                        ENT_XML1, 'UTF-8');
            $epDesc     = htmlspecialchars(strip_tags($page->content()),                          ENT_XML1, 'UTF-8');
            $epLink     = htmlspecialchars($page->permalink(),                                    ENT_XML1, 'UTF-8');
            $epAudioUrl = htmlspecialchars($audioUrl,                                             ENT_XML1, 'UTF-8');
            $epGuid     = htmlspecialchars($page->key(),                                          ENT_XML1, 'UTF-8');
            $epDate     = $page->date('r');
            $epNum      = htmlspecialchars($get('itunes_episode'),                                ENT_XML1, 'UTF-8');
            $epSeason   = htmlspecialchars($get('itunes_season'),                                 ENT_XML1, 'UTF-8');
            $epDur      = htmlspecialchars($get('itunes_duration'),                               ENT_XML1, 'UTF-8');
            $epType     = htmlspecialchars($get('itunes_type') ?: 'full',                         ENT_XML1, 'UTF-8');
            $epExpl     = $get('itunes_explicit') === 'yes' ? 'yes' : 'no';
            $epImage    = htmlspecialchars($get('itunes_image'),                                   ENT_XML1, 'UTF-8');

            echo '  <item>' . "\n";
            echo '    <title>' . $epTitle . '</title>' . "\n";
            echo '    <description>' . $epDesc . '</description>' . "\n";
            echo '    <itunes:summary>' . $epDesc . '</itunes:summary>' . "\n";
            if ($epNum)    echo '    <itunes:episode>'    . $epNum    . '</itunes:episode>'    . "\n";
            if ($epSeason) echo '    <itunes:season>'     . $epSeason . '</itunes:season>'     . "\n";
            if ($epDur)    echo '    <itunes:duration>'   . $epDur    . '</itunes:duration>'   . "\n";
            echo '    <itunes:episodeType>' . $epType . '</itunes:episodeType>' . "\n";
            echo '    <itunes:explicit>'   . $epExpl . '</itunes:explicit>'   . "\n";
            if ($epImage)  echo '    <itunes:image href="' . $epImage . '" />' . "\n";
            echo '    <enclosure url="' . $epAudioUrl . '" length="' . $audioLength . '" type="' . $mime . '" />' . "\n";
            echo '    <link>' . $epLink . '</link>' . "\n";
            echo '    <guid isPermaLink="false">' . $epGuid . '</guid>' . "\n";
            echo '    <pubDate>' . $epDate . '</pubDate>' . "\n";
            echo '  </item>' . "\n";
        }

        echo '</channel>' . "\n";
        echo '</rss>' . "\n";
    }

    // ═══════════════════════════════════════════════════════════════════
    // EPISODE ADMIN UI
    // ═══════════════════════════════════════════════════════════════════

    private function renderEpisodesPage() {
        global $login;

        while (ob_get_level() > 0) ob_end_clean();

        $adminRoot = HTML_PATH_ADMIN_ROOT;
        $coreCSS   = DOMAIN_CORE_CSS;
        $coreJS    = DOMAIN_CORE_JS;
        $apiUrl    = DOMAIN;
        $role      = $login->role();

        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex,nofollow">
    <title>Podcast – Episoden</title>
    <link rel="stylesheet" href="<?php echo $coreCSS ?>bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo $coreCSS ?>line-awesome/css/line-awesome-font-awesome.min.css">
    <style>
        body { background: #f5f6fa; }
        .podcast-wrap { max-width: 960px; margin: 30px auto; padding: 0 15px; }
        .podcast-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .back-link { font-size: 0.9em; color: #666; }
        .back-link:hover { color: #333; text-decoration: none; }
    </style>
</head>
<body>
<div class="podcast-wrap">
    <div class="podcast-header">
        <h3><span class="fa fa-microphone"></span> Podcast – Episoden</h3>
        <a class="back-link" href="<?php echo $adminRoot ?>dashboard">
            <span class="fa fa-arrow-left"></span> Dashboard
        </a>
    </div>

    <?php echo $this->renderEpisodeSection(); ?>
    <?php echo $this->renderEpisodeForm($apiUrl); ?>
</div>

<script src="<?php echo $coreJS ?>jquery.min.js"></script>
<script src="<?php echo $coreJS ?>bootstrap.bundle.min.js"></script>
<script>var PODCAST_API_URL = <?php echo json_encode($apiUrl) ?>;</script>
<script src="<?php echo DOMAIN ?>/bl-plugins/podcast/js/podcast-admin.js"></script>
</body>
</html>
        <?php
    }

    private function renderEpisodeSection() {
        $episodes = $this->getEpisodes();
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
                    <th>#</th>
                    <th>Titel</th>
                    <th>Staffel</th>
                    <th>Typ</th>
                    <th>Datum</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($episodes as $ep):
                      $meta = $this->getEpisodeMeta($ep->key());
                      $get  = function ($k) use ($meta) { return isset($meta[$k]) ? $meta[$k] : ''; };
                  ?>
                  <tr>
                    <td><?php echo htmlspecialchars($get('itunes_episode')) ?></td>
                    <td><?php echo htmlspecialchars($ep->title()) ?></td>
                    <td><?php echo htmlspecialchars($get('itunes_season')) ?></td>
                    <td><?php echo htmlspecialchars($get('itunes_type') ?: 'full') ?></td>
                    <td><?php echo $ep->date('d.m.Y') ?></td>
                    <td>
                      <?php if ($ep->type() === 'published'): ?>
                        <span class="label label-success">veröffentlicht</span>
                      <?php else: ?>
                        <span class="label label-default">Entwurf</span>
                      <?php endif ?>
                    </td>
                    <td style="white-space:nowrap">
                      <button type="button" class="btn btn-xs btn-default podcast-edit-btn"
                        data-key="<?php echo htmlspecialchars($ep->key(), ENT_QUOTES) ?>"
                        data-title="<?php echo htmlspecialchars($ep->title(), ENT_QUOTES) ?>"
                        data-content="<?php echo htmlspecialchars($ep->content(), ENT_QUOTES) ?>"
                        data-episode="<?php echo htmlspecialchars($get('itunes_episode'), ENT_QUOTES) ?>"
                        data-season="<?php echo htmlspecialchars($get('itunes_season'), ENT_QUOTES) ?>"
                        data-type="<?php echo htmlspecialchars($get('itunes_type') ?: 'full', ENT_QUOTES) ?>"
                        data-explicit="<?php echo htmlspecialchars($get('itunes_explicit') ?: 'no', ENT_QUOTES) ?>"
                        data-duration="<?php echo htmlspecialchars($get('itunes_duration'), ENT_QUOTES) ?>"
                        data-image="<?php echo htmlspecialchars($get('itunes_image'), ENT_QUOTES) ?>"
                        data-audio-url="<?php echo htmlspecialchars($get('audio_url'), ENT_QUOTES) ?>"
                        data-status="<?php echo htmlspecialchars($ep->type(), ENT_QUOTES) ?>"
                        data-date="<?php echo $ep->date('Y-m-d\TH:i') ?>">
                        Bearbeiten
                      </button>
                      <button type="button" class="btn btn-xs btn-danger podcast-delete-btn"
                        data-key="<?php echo htmlspecialchars($ep->key(), ENT_QUOTES) ?>"
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

        </div>
        <?php
        return ob_get_clean();
    }

    private function renderEpisodeForm($apiUrl = '') {
        ob_start();
        ?>
        <div id="podcast-episode-form" style="display:none;margin-top:20px">
          <h4 id="podcast-form-title">Neue Episode</h4>
          <input type="hidden" id="pe-key" value="">

          <div class="form-group">
            <label>Titel *</label>
            <input type="text" class="form-control" id="pe-title">
          </div>
          <div class="form-group">
            <label>Beschreibung *</label>
            <textarea class="form-control" id="pe-content" rows="4"></textarea>
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
            <input type="text" class="form-control" id="pe-image-url"
                   placeholder="https://..." style="margin-top:6px">
            <input type="file" class="form-control" id="pe-image-file"
                   accept=".jpg,.jpeg,.png" style="display:none;margin-top:6px">
          </div>

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
            <input type="text" class="form-control" id="pe-audio-url"
                   placeholder="https://..." style="margin-top:6px">
            <input type="file" class="form-control" id="pe-audio-file"
                   accept=".mp3,.m4a,.ogg,.wav" style="display:none;margin-top:6px">
            <small class="text-muted">
              Max. <?php echo (int) ($this->getValue('maxUploadMB') ?: 512) ?> MB
            </small>
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

          <button type="button" class="btn btn-primary" id="podcast-save-btn">
            Speichern
          </button>
          <button type="button" class="btn btn-default" id="podcast-cancel-btn" style="margin-left:8px">
            Abbrechen
          </button>
          <span id="podcast-saving" style="display:none;margin-left:10px">
            <span class="fa fa-spinner fa-spin"></span> Wird gespeichert&hellip;
          </span>
        </div>
        <?php
        return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════
    // EPISODE CRUD API (aufgerufen aus beforeAll)
    // ═══════════════════════════════════════════════════════════════════

    private function handleApiRequest($action) {
        switch ($action) {
            case 'save_episode':
                echo json_encode($this->handleSaveEpisode($_POST, $_FILES));
                break;
            case 'delete_episode':
                $key = isset($_POST['episode_key']) ? htmlspecialchars(strip_tags($_POST['episode_key']), ENT_QUOTES, 'UTF-8') : '';
                echo json_encode($this->handleDeleteEpisode($key));
                break;
            default:
                echo json_encode(['error' => 'Unknown action: ' . $action]);
        }
    }

    private function handleSaveEpisode($post, $files = []) {
        global $pages;

        $title   = isset($post['title'])   ? trim(Sanitize::html($post['title']))  : '';
        $content = isset($post['content']) ? trim($post['content'])                : '';
        $status  = (isset($post['status']) && $post['status'] === 'draft') ? 'draft' : 'published';
        $rawDate = isset($post['date'])    ? trim($post['date'])                   : date('Y-m-d\TH:i');
        $key     = isset($post['key'])     ? trim(Sanitize::html($post['key']))    : '';

        // datetime-local → Bludit-kompatibles Format
        $date = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $rawDate)));

        if (!$title) {
            return ['error' => 'Titel ist erforderlich'];
        }

        // Episodenbild
        $imageUrl = '';
        if (!empty($files['image_file']) && $files['image_file']['error'] === UPLOAD_ERR_OK) {
            $imageUrl = $this->handleUpload('image_file', 'image');
        }
        if (!$imageUrl && !empty($post['image_url'])) {
            $imageUrl = trim($post['image_url']);
        }

        // Audio
        $audioUrl    = '';
        $audioLength = 0;
        if (!empty($files['audio_file']) && $files['audio_file']['error'] === UPLOAD_ERR_OK) {
            $audioUrl = $this->handleUpload('audio_file', 'audio');
            if ($audioUrl) {
                $localPath = PATH_UPLOADS . 'podcast' . DS . 'audio' . DS . basename($audioUrl);
                if (file_exists($localPath)) {
                    $audioLength = (int) filesize($localPath);
                }
            }
        }
        if (!$audioUrl && !empty($post['audio_url'])) {
            $audioUrl = trim($post['audio_url']);
        }

        if (!$audioUrl) {
            return ['error' => 'Audio-URL oder -Datei ist erforderlich'];
        }

        $meta = [
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
            'type'    => $status,
            'tags'    => 'podcast',
            'date'    => $date,
        ];

        if ($key) {
            // Bestehende Episode bearbeiten
            $pageArgs['key'] = $key;
            $newKey = $pages->edit($pageArgs);
            $saveKey = $newKey ?: $key;
            $this->setEpisodeMeta($saveKey, $meta);
            return ['success' => true, 'key' => $saveKey];
        } else {
            // Neue Episode anlegen
            $newKey = $pages->add($pageArgs);
            if ($newKey) {
                $this->setEpisodeMeta($newKey, $meta);
            }
            return ['success' => true, 'key' => $newKey];
        }
    }

    private function handleDeleteEpisode($key) {
        global $pages;

        if (!$key) {
            return ['error' => 'Kein Schlüssel angegeben'];
        }

        if (!$pages->exists($key)) {
            return ['error' => 'Episode nicht gefunden'];
        }

        $page = new Page($key);

        // Nur Seiten mit Tag 'podcast' löschen (Sicherheit)
        if (!$this->pageHasPodcastTag($page)) {
            return ['error' => 'Seite gehört nicht zum Podcast'];
        }

        $pages->delete($key);
        $this->deleteEpisodeMeta($key);

        return ['success' => true];
    }

    private function handleUpload($fileField, $type) {
        if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
            return '';
        }

        $file     = $_FILES[$fileField];
        $maxMB    = (int) ($this->getValue('maxUploadMB') ?: 512);
        $maxBytes = $maxMB * 1024 * 1024;

        if ($file['size'] > $maxBytes) {
            return '';
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($type === 'audio') {
            $allowed = ['mp3', 'm4a', 'ogg', 'wav'];
            $dir     = PATH_UPLOADS . 'podcast' . DS . 'audio' . DS;
            $urlBase = DOMAIN . '/bl-content/uploads/podcast/audio/';
        } else {
            $allowed = ['jpg', 'jpeg', 'png'];
            $dir     = PATH_UPLOADS . 'podcast' . DS . 'images' . DS;
            $urlBase = DOMAIN . '/bl-content/uploads/podcast/images/';
        }

        if (!in_array($ext, $allowed)) {
            return '';
        }

        // Verzeichnis anlegen falls es nicht existiert
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $baseName = $this->sanitizeFilename(pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = $baseName . '.' . $ext;

        // Doppelte Dateinamen verhindern
        $i = 1;
        while (file_exists($dir . $filename)) {
            $filename = $baseName . '-' . $i . '.' . $ext;
            $i++;
        }

        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            throw new RuntimeException('Upload fehlgeschlagen: Datei konnte nicht gespeichert werden (' . $dir . $filename . ')');
        }

        return $urlBase . $filename;
    }

    // ═══════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════

    private function getEpisodes() {
        global $pages;

        $keys     = $pages->getDB(true);
        $episodes = [];
        foreach ($keys as $key) {
            $page = new Page($key);
            if ($page->type() === 'autosave') continue;
            if ($this->pageHasPodcastTag($page)) {
                $episodes[] = $page;
            }
        }

        // Sortierung: Episodennummer absteigend, gleiche Nummer nach Datum
        usort($episodes, function ($a, $b) {
            $metaA = $this->getEpisodeMeta($a->key());
            $metaB = $this->getEpisodeMeta($b->key());
            $numA  = isset($metaA['itunes_episode']) ? (int) $metaA['itunes_episode'] : 0;
            $numB  = isset($metaB['itunes_episode']) ? (int) $metaB['itunes_episode'] : 0;
            if ($numA !== $numB) {
                return $numB - $numA;
            }
            return strtotime($b->dateRaw()) - strtotime($a->dateRaw());
        });

        return $episodes;
    }

    /**
     * Prüft ob eine Seite den Tag 'podcast' hat.
     * Unterstützt sowohl String-Arrays als auch Tag-Objekt-Arrays (Bludit 3.x).
     */
    private function pageHasPodcastTag($page) {
        $tags = (array) $page->tags();
        foreach ($tags as $tag) {
            if (is_object($tag)) {
                // Tag-Objekt: key() oder name() verwenden
                $tagKey = method_exists($tag, 'key') ? $tag->key() : '';
                if ($tagKey === 'podcast') return true;
                $tagName = method_exists($tag, 'name') ? strtolower($tag->name()) : '';
                if ($tagName === 'podcast') return true;
            } else {
                if ((string) $tag === 'podcast') return true;
            }
        }
        return false;
    }

    private function pluginPath() {
        return PATH_PLUGINS . 'podcast' . DS;
    }

    private function episodesFile() {
        return $this->pluginPath() . 'metadata' . DS . 'episodes.json';
    }

    private function loadAllMeta() {
        $file = $this->episodesFile();
        if (!file_exists($file)) {
            return [];
        }
        $data = json_decode(file_get_contents($file), true);
        // Migriere altes Format (Array von Keys) zu neuem Format (assoziatives Array)
        if (isset($data[0]) || (is_array($data) && array_values($data) === $data && !empty($data))) {
            return [];
        }
        return is_array($data) ? $data : [];
    }

    private function saveAllMeta(array $meta) {
        file_put_contents($this->episodesFile(), json_encode($meta, JSON_PRETTY_PRINT));
    }

    private function getEpisodeMeta($key) {
        $all = $this->loadAllMeta();
        return isset($all[$key]) ? $all[$key] : [];
    }

    private function setEpisodeMeta($key, array $meta) {
        $all        = $this->loadAllMeta();
        $all[$key]  = $meta;
        $this->saveAllMeta($all);
    }

    private function deleteEpisodeMeta($key) {
        $all = $this->loadAllMeta();
        unset($all[$key]);
        $this->saveAllMeta($all);
    }

    private function sanitizeFilename($name) {
        $name = mb_strtolower($name, 'UTF-8');
        $name = preg_replace('/\s+/', '-', $name);
        $name = preg_replace('/[^a-z0-9\-_]/', '', $name);
        $name = trim($name, '-');
        return $name ?: 'upload';
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
