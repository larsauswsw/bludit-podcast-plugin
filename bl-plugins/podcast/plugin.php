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
            'playerMode'  => 'podlove',
        ];
    }

    public function install($position = 0) {
        parent::install($position);

        // Upload-Verzeichnisse anlegen und absichern
        foreach (['', 'audio' . DS, 'images' . DS] as $sub) {
            $this->prepareUploadDir(PATH_UPLOADS . 'podcast' . DS . $sub);
        }

        // Episoden-Metadaten liegen als Custom Fields in den Seiten
        if ($this->ensureCustomFields()) {
            $this->migrateLegacyMeta();
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
     * Läuft für /admin/plugin/podcast vor adminView(), nur für angemeldete Nutzer.
     * Richtet nach einem Plugin-Update die Custom Fields ein und übernimmt Altdaten.
     */
    public function adminController() {
        global $login;

        if (!in_array($login->role(), ['admin', 'editor'], true)) {
            return;
        }
        if ($this->ensureCustomFields()) {
            $this->migrateLegacyMeta();
        }
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
          // Kennung in der URL, damit auch zu große Uploads als API-Anfrage erkannt werden
          $apiUrl = DOMAIN_BASE . '?podcast_api=1';
          try {
              echo $this->renderEpisodeSection();
              echo $this->renderEpisodeForm($apiUrl);
          } catch (Exception $e) {
              echo '<div class="alert alert-danger">Fehler: '
                  . htmlspecialchars($e->getMessage()) . '</div>';
          }
          ?>
        </div>

        <script>var PODCAST_API_URL = <?php echo json_encode($apiUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
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
                <label>Player auf Episodenseiten</label>
                <select class="form-control" name="playerMode">
                    <option value="podlove" <?php echo $this->playerMode() === 'podlove' ? 'selected' : '' ?>>Podlove Web Player (lokal gehostet, ohne Drittanbieter)</option>
                    <option value="html5"   <?php echo $this->playerMode() === 'html5'   ? 'selected' : '' ?>>Einfacher HTML5-Player</option>
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
        // Erkennung auch über ?podcast_api=1 in der URL: Bei überschrittenem
        // post_max_size verwirft PHP den POST-Body samt podcast_api_action.
        $method       = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
        $isApiRequest = $method === 'POST' && (isset($_GET['podcast_api']) || !empty($_POST['podcast_api_action']));

        if ($isApiRequest) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            // Stray PHP warnings/notices would break JSON — suppress output, capture cleanly
            ob_start();
            header('Content-Type: application/json; charset=utf-8');
            // Login aus Session lesen (funktioniert auch im Frontend-Kontext)
            $loginCheck = new Login();
            if (!$loginCheck->isLogged() || !in_array($loginCheck->role(), ['admin', 'editor'], true)) {
                ob_end_clean();
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
            // post_max_size überschritten: $_POST und $_FILES sind leer (0 = unbegrenzt)
            $postMaxBytes = $this->parseBytes(ini_get('post_max_size'));
            $contentLen   = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
            if ($postMaxBytes > 0 && $contentLen > $postMaxBytes) {
                ob_end_clean();
                $limitMB = round($postMaxBytes / 1024 / 1024, 0);
                echo json_encode(['error' => 'Upload zu groß: Gesamtgröße überschreitet das Server-Limit von ' . $limitMB . ' MB. Bitte kleinere Dateien wählen oder Audio-URL verwenden.']);
                exit;
            }
            // Im Frontend prüft Bludit kein CSRF-Token — daher hier selbst
            global $security;
            if (!$security->validateTokenCSRF($this->postString($_POST, 'tokenCSRF'))) {
                ob_end_clean();
                Log::set(__METHOD__ . LOG_SEP . 'Invalid tokenCSRF for podcast API request.');
                echo json_encode(['error' => 'Sicherheits-Token ungültig. Bitte die Seite neu laden.']);
                exit;
            }
            $action = $this->postString($_POST, 'podcast_api_action');
            try {
                $this->handleApiRequest($action);
            } catch (RuntimeException $e) {
                // Eigene, für Nutzer formulierte Fehlermeldungen
                echo json_encode(['error' => $e->getMessage()]);
            } catch (Throwable $e) {
                Log::set(__METHOD__ . LOG_SEP . $e->getMessage());
                echo json_encode(['error' => 'Interner Fehler. Details stehen im Bludit-Log.']);
            }
            ob_end_flush();
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

        $meta = $this->getEpisodeMeta($page);
        if (empty($meta['audio_url'])) return;
        if ($this->playerMode() !== 'podlove') return;

        // Lokal ausgelieferter Player (player/SOURCE.md) — keine Verbindung zu einem CDN
        $src = $this->playerBaseUrl() . 'embed.js?version=' . self::PODLOVE_VERSION;
        echo '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
    }

    public function siteBodyEnd() {
        global $url, $page;

        if ($url->whereAmI() !== 'page') return;
        if (!$page instanceof Page || !$this->pageHasPodcastTag($page)) return;

        $meta = $this->getEpisodeMeta($page);
        if (empty($meta['audio_url'])) return;

        $get = function ($k) use ($meta) { return isset($meta[$k]) ? $meta[$k] : ''; };

        $audioUrl    = $get('audio_url');
        $audioLength = (int) $get('audio_length');
        $audioExt    = strtolower(pathinfo(parse_url($audioUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mime        = $this->audioMimeType($audioExt);
        $duration    = $get('itunes_duration');
        $poster      = $get('itunes_image') ?: $this->getValue('cover');
        $mode        = $this->playerMode();
        // Podlove rendert die Beschreibung als HTML — daher als escapten Text übergeben
        $summary     = trim(html_entity_decode(strip_tags($page->content()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $summaryHtml = nl2br(htmlspecialchars($summary, ENT_QUOTES | ENT_HTML5, 'UTF-8'), false);

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
            'summary'         => $summaryHtml,
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

        // Assets (Layouts, Player-Bundle, Abo-Button) aus dem Plugin-Ordner laden
        $playerConfig = ['base' => $this->playerBaseUrl()];
        $feedUrl = $this->getValue('feedUrl');
        if ($feedUrl) {
            $playerConfig['subscribe-button'] = ['feed' => $feedUrl];
        }

        $flags       = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $episodeJson = json_encode($episodeConfig, $flags);
        $playerJson  = json_encode($playerConfig,  $flags);
        if ($episodeJson === false || $playerJson === false) return;

        $audioAttr = htmlspecialchars($audioUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        ?>
        <div id="podcast-episode-player">
          <?php if ($mode === 'podlove'): ?>
          <div id="podlove-episode-player"></div>
          <?php endif ?>
          <div class="podcast-audio-fallback">
            <audio controls preload="none" src="<?php echo $audioAttr ?>" style="width:100%"></audio>
            <p><a href="<?php echo $audioAttr ?>" download>Episode herunterladen</a></p>
          </div>
        </div>
        <script>
        (function () {
            var playerDiv = document.getElementById('podcast-episode-player');
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
            <?php if ($mode === 'podlove'): ?>
            // HTML5-Fallback bleibt sichtbar, bis der Podlove-Player geladen ist
            if (typeof window.podlovePlayer === 'function') {
                var fallback = playerDiv.querySelector('.podcast-audio-fallback');
                Promise.resolve(window.podlovePlayer(
                    '#podlove-episode-player',
                    <?php echo $episodeJson ?>,
                    <?php echo $playerJson ?>
                )).then(function () {
                    if (fallback && fallback.parentNode) {
                        fallback.parentNode.removeChild(fallback);
                    }
                }, function () {});
            }
            <?php endif ?>
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
            $meta = $this->getEpisodeMeta($page);
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
            $epGuid     = htmlspecialchars($page->uuid(),                                         ENT_XML1, 'UTF-8');
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
                      $meta = $this->getEpisodeMeta($ep);
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
                echo json_encode($this->handleDeleteEpisode($this->postString($_POST, 'episode_key')));
                break;
            default:
                echo json_encode(['error' => 'Unbekannte Aktion']);
        }
    }

    private function handleSaveEpisode($post, $files = []) {
        global $pages;

        $title   = trim(Sanitize::html($this->postString($post, 'title')));
        $content = $this->postString($post, 'content');
        $status  = $this->postString($post, 'status') === 'draft' ? 'draft' : 'published';
        $rawDate = $this->postString($post, 'date') ?: date('Y-m-d\TH:i');
        $key     = $this->postString($post, 'key');

        // datetime-local → Bludit-kompatibles Format
        $date = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $rawDate)));

        if (!$title) {
            return ['error' => 'Titel ist erforderlich'];
        }

        // Nur bestehende Podcast-Episoden dürfen bearbeitet werden — vor jedem Upload prüfen
        $page = null;
        if ($key !== '') {
            if (!$pages->exists($key)) {
                return ['error' => 'Episode nicht gefunden'];
            }
            $page = new Page($key);
            if (!$this->pageHasPodcastTag($page)) {
                return ['error' => 'Seite gehört nicht zum Podcast'];
            }
        }

        if (!$this->ensureCustomFields()) {
            return ['error' => 'Podcast-Felder konnten nicht eingerichtet werden. Details stehen im Bludit-Log.'];
        }

        $imageUrlInput = $this->postString($post, 'image_url');
        $audioUrlInput = $this->postString($post, 'audio_url');
        if ($imageUrlInput !== '' && !$this->isHttpUrl($imageUrlInput)) {
            return ['error' => 'Bild-URL muss mit http:// oder https:// beginnen'];
        }
        if ($audioUrlInput !== '' && !$this->isHttpUrl($audioUrlInput)) {
            return ['error' => 'Audio-URL muss mit http:// oder https:// beginnen'];
        }

        $duration = $this->postString($post, 'duration');
        if ($duration !== '' && !$this->isValidDuration($duration)) {
            return ['error' => 'Dauer bitte als HH:MM:SS, MM:SS oder in Sekunden angeben'];
        }

        // Episodenbild
        $imageUrl = '';
        if (!empty($files['image_file'])) {
            $imgErr = $files['image_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            if (!is_int($imgErr)) {
                return ['error' => 'Ungültiger Bild-Upload'];
            }
            if ($imgErr === UPLOAD_ERR_OK) {
                $imageUrl = $this->handleUpload('image_file', 'image');
            } elseif ($imgErr !== UPLOAD_ERR_NO_FILE) {
                return ['error' => $this->uploadErrorMessage($imgErr, 'Bild')];
            }
        }
        if (!$imageUrl) {
            $imageUrl = $imageUrlInput;
        }

        // Audio
        $audioUrl    = '';
        $audioLength = 0;
        if (!empty($files['audio_file'])) {
            $audErr = $files['audio_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            if (!is_int($audErr)) {
                return ['error' => 'Ungültiger Audio-Upload'];
            }
            if ($audErr === UPLOAD_ERR_OK) {
                $audioUrl = $this->handleUpload('audio_file', 'audio');
                $localPath = PATH_UPLOADS . 'podcast' . DS . 'audio' . DS . basename($audioUrl);
                if ($audioUrl !== '' && is_file($localPath)) {
                    $audioLength = (int) filesize($localPath);
                }
            } elseif ($audErr !== UPLOAD_ERR_NO_FILE) {
                return ['error' => $this->uploadErrorMessage($audErr, 'Audio')];
            }
        }
        if (!$audioUrl) {
            $audioUrl = $audioUrlInput;
        }

        if (!$audioUrl) {
            return ['error' => 'Audio-URL oder -Datei ist erforderlich'];
        }

        $episodeNum = $this->postString($post, 'episode');
        $seasonNum  = $this->postString($post, 'season');
        foreach ([$episodeNum, $seasonNum] as $number) {
            if ($number !== '' && filter_var($number, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
                return ['error' => 'Episode und Staffel müssen ganze Zahlen ab 0 sein'];
            }
        }
        $type = $this->postString($post, 'type');

        $meta = [
            'itunes_episode'  => $episodeNum,
            'itunes_season'   => $seasonNum,
            'itunes_type'     => in_array($type, ['full', 'trailer', 'bonus'], true) ? $type : 'full',
            'itunes_explicit' => $this->postString($post, 'explicit') === 'yes' ? 'yes' : 'no',
            'itunes_duration' => $duration,
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
            'custom'  => $this->customFieldArgs($page, $meta),
        ];

        if ($key !== '') {
            // Bestehende Episode bearbeiten (Key wurde oben geprüft)
            $pageArgs['key'] = $key;
            $newKey = editPage($pageArgs);
        } else {
            // Neue Episode anlegen
            $newKey = $pages->add($pageArgs);
        }
        if (!$newKey) {
            return ['error' => 'Episode konnte nicht gespeichert werden'];
        }
        return ['success' => true, 'key' => $newKey];
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

        return ['success' => true];
    }

    private function parseBytes($val) {
        $val  = trim($val);
        $unit = strtolower(substr($val, -1));
        $num  = (int) $val;
        switch ($unit) {
            case 'g': return $num * 1024 * 1024 * 1024;
            case 'm': return $num * 1024 * 1024;
            case 'k': return $num * 1024;
            default:  return $num;
        }
    }

    private function uploadErrorMessage($code, $label) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => $label . '-Datei zu groß (Server-Limit: ' . ini_get('upload_max_filesize') . '). Bitte kleinere Datei wählen oder URL verwenden.',
            UPLOAD_ERR_FORM_SIZE  => $label . '-Datei überschreitet die Formularbegrenzung.',
            UPLOAD_ERR_PARTIAL    => $label . '-Datei wurde nur teilweise hochgeladen. Bitte erneut versuchen.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server-Konfigurationsfehler: Kein temporäres Verzeichnis.',
            UPLOAD_ERR_CANT_WRITE => 'Server-Konfigurationsfehler: Datei konnte nicht geschrieben werden.',
            UPLOAD_ERR_EXTENSION  => 'Upload durch Server-Konfiguration blockiert.',
        ];
        return $messages[$code] ?? $label . '-Upload fehlgeschlagen (Code: ' . $code . ')';
    }

    // Erlaubte Upload-Endungen je Typ und die per finfo akzeptierten MIME-Typen
    private const UPLOAD_TYPES = [
        'audio' => [
            'mp3' => ['audio/mpeg'],
            'm4a' => ['audio/x-m4a', 'audio/mp4', 'video/mp4'],
            'ogg' => ['audio/ogg', 'application/ogg'],
            'wav' => ['audio/x-wav', 'audio/wav', 'audio/vnd.wave'],
        ],
        'image' => [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
        ],
    ];

    /**
     * Speichert einen geprüften Upload und liefert seine URL.
     * Fehler werden als RuntimeException mit Nutzer-Meldung gemeldet.
     */
    private function handleUpload($fileField, $type) {
        if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
            return '';
        }

        $file  = $_FILES[$fileField];
        $label = $type === 'audio' ? 'Audio' : 'Bild';
        $ext   = $this->validateUpload($file, $type);

        $dir = PATH_UPLOADS . 'podcast' . DS . ($type === 'audio' ? 'audio' : 'images') . DS;
        if (!$this->prepareUploadDir($dir)) {
            throw new RuntimeException($label . '-Upload fehlgeschlagen: Upload-Ordner nicht beschreibbar.');
        }

        // Zufälliger Namensanteil: Dateien unveröffentlichter Episoden sind nicht erratbar
        $baseName = substr($this->sanitizeFilename(pathinfo($file['name'], PATHINFO_FILENAME)), 0, 80);
        do {
            $filename = $baseName . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        } while (file_exists($dir . $filename));

        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            Log::set(__METHOD__ . LOG_SEP . 'move_uploaded_file failed: ' . $dir . $filename);
            throw new RuntimeException('Upload fehlgeschlagen: Datei konnte nicht gespeichert werden.');
        }

        return DOMAIN_UPLOADS . 'podcast/' . ($type === 'audio' ? 'audio' : 'images') . '/' . $filename;
    }

    /**
     * Prüft Größe, Endung und tatsächlichen Inhalt (finfo) eines Uploads.
     * Liefert die normalisierte Endung oder wirft eine RuntimeException.
     */
    private function validateUpload(array $file, $type) {
        $label   = $type === 'audio' ? 'Audio' : 'Bild';
        $allowed = self::UPLOAD_TYPES[$type];
        $maxMB   = (int) ($this->getValue('maxUploadMB') ?: 512);

        if (!is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null) || !is_int($file['size'] ?? null)) {
            throw new RuntimeException('Ungültiger ' . $label . '-Upload.');
        }
        if ($file['size'] > $maxMB * 1024 * 1024) {
            throw new RuntimeException($label . '-Datei ist größer als ' . $maxMB . ' MB (Einstellung „Max. Upload-Größe“).');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            throw new RuntimeException($label . '-Datei hat eine nicht erlaubte Endung. Erlaubt: ' . implode(', ', array_keys($allowed)) . '.');
        }

        if (!class_exists('finfo')) {
            Log::set(__METHOD__ . LOG_SEP . 'PHP extension fileinfo missing, upload rejected.');
            throw new RuntimeException('Upload nicht möglich: Die PHP-Erweiterung „fileinfo“ fehlt auf dem Server.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $isImage = $type !== 'image' || in_array(@getimagesize($file['tmp_name'])[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true);
        if (!in_array($mime, $allowed[$ext], true) || !$isImage) {
            Log::set(__METHOD__ . LOG_SEP . 'Rejected upload, extension ' . $ext . ', detected ' . $mime);
            throw new RuntimeException($label . '-Datei passt inhaltlich nicht zur Endung .' . $ext . '.');
        }

        return $ext;
    }

    /**
     * Legt ein Upload-Verzeichnis an und sichert es ab: index.php gegen
     * Directory-Listing, .htaccess gegen Skriptausführung (Apache).
     * Vorhandene Dateien werden nicht überschrieben.
     */
    private function prepareUploadDir($dir) {
        if (!is_dir($dir) && !mkdir($dir, DIR_PERMISSIONS, true)) {
            return false;
        }
        $files = [
            'index.php' => '<?php // silence',
            '.htaccess' => implode("\n", [
                '# Podcast-Plugin: keine Skriptausführung im Upload-Ordner',
                '<IfModule mod_mime.c>',
                '    RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pht .phps',
                '    RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pht .phps',
                '</IfModule>',
                '<IfModule mod_rewrite.c>',
                '    RewriteEngine On',
                '    RewriteRule \.(?:php\d*|phtml|phar|pht|phps|pl|py|cgi|sh|shtml|html?|svgz?|xml)$ - [F,NC]',
                '</IfModule>',
                '<IfModule mod_headers.c>',
                '    Header set X-Content-Type-Options "nosniff"',
                '</IfModule>',
                '',
            ]),
        ];
        foreach ($files as $name => $content) {
            if (!file_exists($dir . $name) && file_put_contents($dir . $name, $content, LOCK_EX) === false) {
                Log::set(__METHOD__ . LOG_SEP . 'Could not write ' . $name . ' in upload directory.');
            }
        }
        return is_writable($dir);
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
            $metaA = $this->getEpisodeMeta($a);
            $metaB = $this->getEpisodeMeta($b);
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

    /**
     * Liefert einen Request-Parameter als getrimmten String.
     * Fehlende Werte und Arrays (z. B. title[]=…) ergeben ''.
     */
    private function postString(array $post, $field) {
        return (isset($post[$field]) && is_string($post[$field])) ? trim($post[$field]) : '';
    }

    /**
     * Akzeptiert nur absolute http(s)-URLs mit Host.
     */
    private function isHttpUrl($url) {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host   = (string) parse_url($url, PHP_URL_HOST);
        return in_array($scheme, ['http', 'https'], true) && $host !== '';
    }

    /**
     * Dauer im iTunes-Format: Sekunden, MM:SS oder HH:MM:SS.
     */
    private function isValidDuration($value) {
        return is_string($value) && preg_match('/^(?:\d{1,6}|\d{1,3}:[0-5]\d|\d{1,3}:[0-5]\d:[0-5]\d)$/D', $value) === 1;
    }

    // Version des mitgelieferten Podlove Web Players (player/SOURCE.md)
    private const PODLOVE_VERSION = '5.13.0';

    /**
     * Gewählter Player; unbekannte Werte fallen auf den lokalen Podlove-Player zurück.
     */
    private function playerMode() {
        return $this->getValue('playerMode') === 'html5' ? 'html5' : 'podlove';
    }

    private function playerBaseUrl() {
        return $this->domainPath() . 'player/';
    }

    // ═══════════════════════════════════════════════════════════════════
    // EPISODEN-METADATEN (Bludit Custom Fields)
    // ═══════════════════════════════════════════════════════════════════

    // Interner Metadaten-Schlüssel → Name des Custom Fields der Seite
    private const CUSTOM_FIELDS = [
        'audio_url'       => 'podcast_audio_url',
        'audio_length'    => 'podcast_audio_length',
        'itunes_duration' => 'podcast_duration',
        'itunes_episode'  => 'podcast_episode',
        'itunes_season'   => 'podcast_season',
        'itunes_type'     => 'podcast_type',
        'itunes_explicit' => 'podcast_explicit',
        'itunes_image'    => 'podcast_image',
    ];

    /**
     * Definitionen der Podcast-Felder für die Custom-Fields-Einstellung der Site.
     */
    private function customFieldDefinitions() {
        $labels = [
            'podcast_audio_url'    => 'Podcast: Audio-URL',
            'podcast_audio_length' => 'Podcast: Dateigröße (Bytes)',
            'podcast_duration'     => 'Podcast: Dauer (HH:MM:SS)',
            'podcast_episode'      => 'Podcast: Episode #',
            'podcast_season'       => 'Podcast: Staffel',
            'podcast_type'         => 'Podcast: Typ (full/trailer/bonus)',
            'podcast_explicit'     => 'Podcast: Explicit',
            'podcast_image'        => 'Podcast: Episodenbild-URL',
        ];
        $definitions = [];
        foreach ($labels as $field => $label) {
            $definitions[$field] = [
                'type'  => $field === 'podcast_explicit' ? 'bool' : 'string',
                'label' => $label,
                'tip'   => 'Wird vom Podcast-Plugin gepflegt (Admin → Podcast).',
            ];
        }
        return $definitions;
    }

    /**
     * Ergänzt fehlende Podcast-Felder in der Custom-Fields-Definition der Site
     * (wie Einstellungen → Allgemein → Custom fields). Vorhandene Felder bleiben unverändert.
     */
    private function ensureCustomFields() {
        global $site, $pages;

        $fields = $site->customFields();
        if (!is_array($fields)) {
            Log::set(__METHOD__ . LOG_SEP . 'Site custom fields are not valid JSON, podcast fields not added.');
            return false;
        }

        $missing = array_diff_key($this->customFieldDefinitions(), $fields);
        if (empty($missing)) {
            return true;
        }

        $json = json_encode($fields + $missing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || !$pages->setCustomFields($json)) {
            Log::set(__METHOD__ . LOG_SEP . 'Could not add podcast custom fields.');
            return false;
        }
        return (bool) $site->set(['customFields' => $json]);
    }

    /**
     * Liest die Metadaten einer Episode aus ihren Custom Fields (unescaped und geprüft).
     */
    private function getEpisodeMeta($page) {
        $raw = [];
        foreach (self::CUSTOM_FIELDS as $metaKey => $field) {
            $value = $page->custom($field);
            $raw[$metaKey] = is_string($value) ? Sanitize::htmlDecode($value) : $value;
        }
        return $this->normalizeMeta($raw);
    }

    /**
     * Bringt Metadaten in eine gültige Form. Werte können auch über den
     * Bludit-Editor oder aus Altdaten kommen — Ungültiges wird geleert.
     */
    private function normalizeMeta(array $raw) {
        $value = function ($k) use ($raw) {
            return (isset($raw[$k]) && is_scalar($raw[$k])) ? trim((string) $raw[$k]) : '';
        };
        $number = function ($v) {
            return filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) !== false ? $v : '';
        };
        $url = function ($v) {
            return $this->isHttpUrl($v) ? $v : '';
        };
        $type = $value('itunes_type');

        return [
            'audio_url'       => $url($value('audio_url')),
            'audio_length'    => $number($value('audio_length')) ?: '0',
            'itunes_duration' => $this->isValidDuration($value('itunes_duration')) ? $value('itunes_duration') : '',
            'itunes_episode'  => $number($value('itunes_episode')),
            'itunes_season'   => $number($value('itunes_season')),
            'itunes_type'     => in_array($type, ['full', 'trailer', 'bonus'], true) ? $type : 'full',
            'itunes_explicit' => in_array($value('itunes_explicit'), ['yes', '1', 'true'], true) ? 'yes' : 'no',
            'itunes_image'    => $url($value('itunes_image')),
        ];
    }

    /**
     * Baut das 'custom'-Argument für editPage()/Pages::add(). Bludit ersetzt dabei
     * alle Custom Fields einer Seite, daher werden die übrigen Felder mit ihrem
     * aktuellen Wert (bzw. Standardwert) mitgegeben.
     */
    private function customFieldArgs($page, array $meta) {
        global $site;

        $args = [];
        foreach ((array) $site->customFields() as $field => $options) {
            $value = $page ? $page->custom($field) : false;
            if ($value === false) {
                $value = isset($options['default']) ? $options['default'] : '';
            }
            // Gespeicherte Werte sind escaped, Bludit escaped beim Speichern erneut
            $args[$field] = is_string($value) ? Sanitize::htmlDecode($value) : (is_scalar($value) ? $value : '');
        }
        foreach (self::CUSTOM_FIELDS as $metaKey => $field) {
            $args[$field] = $metaKey === 'itunes_explicit'
                ? ($meta[$metaKey] === 'yes' ? '1' : '')
                : (string) $meta[$metaKey];
        }
        return $args;
    }

    /**
     * Übernimmt Metadaten aus der früheren bl-plugins/podcast/metadata/episodes.json
     * in die Custom Fields der Seiten. Die Datei lag per HTTP abrufbar im Plugin-Ordner;
     * sie wird danach als geschütztes Backup in den Workspace verschoben.
     * Mehrfaches Ausführen ist unschädlich, bereits gepflegte Episoden bleiben unverändert.
     */
    private function migrateLegacyMeta() {
        global $pages;

        $legacyFile = $this->phpPath() . 'metadata' . DS . 'episodes.json';
        if (!file_exists($legacyFile)) {
            return;
        }

        $raw      = (string) file_get_contents($legacyFile);
        $data     = json_decode($raw, true);
        $migrated = 0;
        if (is_array($data)) {
            foreach ($data as $key => $legacy) {
                if (!is_string($key) || !is_array($legacy) || !$pages->exists($key)) {
                    continue;
                }
                $page = new Page($key);
                if (!$this->pageHasPodcastTag($page) || $this->getEpisodeMeta($page)['audio_url'] !== '') {
                    continue;
                }
                // Altdaten speicherten Textfelder HTML-escaped, URLs roh
                foreach (['itunes_episode', 'itunes_season', 'itunes_type', 'itunes_explicit', 'itunes_duration'] as $escaped) {
                    if (isset($legacy[$escaped]) && is_string($legacy[$escaped])) {
                        $legacy[$escaped] = Sanitize::htmlDecode($legacy[$escaped]);
                    }
                }
                $meta   = $this->normalizeMeta($legacy);
                $row    = $pages->getPageDB($key);
                $custom = (isset($row['custom']) && is_array($row['custom'])) ? $row['custom'] : [];
                foreach (self::CUSTOM_FIELDS as $metaKey => $field) {
                    // Gleiches Format wie Pages::add(): escaped String bzw. bool
                    $custom[$field] = ['value' => $metaKey === 'itunes_explicit'
                        ? $meta[$metaKey] === 'yes'
                        : Sanitize::html($meta[$metaKey])];
                }
                if ($pages->setField($key, 'custom', $custom)) {
                    $migrated++;
                }
            }
        }

        // Backup als .php mit Bludit-Schutzzeile: auch ohne .htaccess nicht abrufbar.
        // Neu kodiert mit JSON_HEX_TAG, damit kein "<?" aus den Altdaten in die Datei gelangt.
        $payload   = is_array($data) ? $data : ['raw_base64' => base64_encode($raw)];
        $json      = json_encode($payload, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $workspace = $this->workspace();
        if (!is_dir($workspace)) {
            mkdir($workspace, DIR_PERMISSIONS, true);
        }
        $backup = $workspace . 'episodes-legacy-' . date('YmdHis') . '.php';
        if ($json === false || file_put_contents($backup, "<?php defined('BLUDIT') or die('Bludit CMS.'); ?>\n" . $json, LOCK_EX) === false) {
            Log::set(__METHOD__ . LOG_SEP . 'Backup of legacy episodes.json failed, file kept.');
            return;
        }
        if (!unlink($legacyFile)) {
            Log::set(__METHOD__ . LOG_SEP . 'Could not delete legacy episodes.json.');
            return;
        }
        $legacyDir = dirname($legacyFile);
        $entries   = scandir($legacyDir);
        if ($entries !== false && count($entries) === 2) {
            rmdir($legacyDir);
        }
        Log::set(__METHOD__ . LOG_SEP . 'Migrated ' . $migrated . ' episodes from legacy episodes.json.');
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
