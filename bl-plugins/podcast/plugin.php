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

    public function __construct() {
        parent::__construct();
        $this->rejectOversizedApiPost();
    }

    public function init() {
        // Name, Beschreibung, Autor und Version liest Bludit aus metadata.json
        // bzw. languages/*.json. Eigene Properties dafür wären „dynamic
        // properties“: seit PHP 8.2 veraltet, mit PHP 9 ein Fehler.
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
            'showType'    => 'episodic',
            'copyright'   => '',
            // Podcasting 2.0: Kennung einmal erzeugt und gespeichert, Sperre (yes/no)
            'podcastGuid' => '',
            'feedLocked'  => 'no',
            'maxUploadMB' => '512',
            'playerMode'  => 'podlove',
            'playerPosition' => 'top',
            // Links zum Podcast bei Diensten (Abo-Button, SUBSCRIBE_SERVICES)
            'linkApple'       => '',
            'linkSpotify'     => '',
            'linkPocketCasts' => '',
            'linkDeezer'      => '',
            'linkCastbox'     => '',
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
     * Bludit hat hier Session, Login und bei POST das CSRF-Token
     * (rules/99.security.php) bereits geprüft.
     *
     * - POST mit ?podcast_api=1: Episoden-API (Speichern, Löschen, Upload) → JSON
     * - sonst: nach einem Plugin-Update Custom Fields einrichten, Altdaten übernehmen
     */
    public function adminController() {
        $allowed = checkRole(['admin', 'editor'], false);

        if ($this->isApiRequest()) {
            $this->sendJson($allowed
                ? $this->runApiAction($this->postString($_POST, 'podcast_api_action'))
                : ['error' => 'Keine Berechtigung.']);
        }

        if (!$allowed) {
            return;
        }
        if ($this->ensureCustomFields()) {
            $this->migrateLegacyMeta();
        }
        $this->ensurePodcastGuid();
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
        // API im Admin-Bereich (adminController). Kennung in der URL, damit auch
        // zu große Uploads (PHP verwirft dann den POST-Body) erkannt werden.
        $apiUrl = DOMAIN_ADMIN . 'plugin/podcast?podcast_api=1';
        ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($this->assetUrl('css/podcast-admin.css'), ENT_QUOTES) ?>">

        <h2 class="mt-0 mb-3">
          <span class="fa fa-microphone" style="font-size: 0.9em;"></span><span>Podcast – Episoden</span>
        </h2>

        <?php
        try {
            echo $this->renderEpisodeSection($login->role() === 'admin');
            echo $this->renderEpisodeForm($apiUrl);
        } catch (Exception $e) {
            Log::set(__METHOD__ . LOG_SEP . $e->getMessage());
            echo '<div class="alert alert-danger">Die Episoden konnten nicht geladen werden. Details stehen im Bludit-Log.</div>';
        }
        ?>

        <script>var PODCAST_API_URL = <?php echo json_encode($apiUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
        <script src="<?php echo htmlspecialchars($this->assetUrl('js/podcast-admin.js'), ENT_QUOTES) ?>"></script>
        <?php
        return ob_get_clean();
    }

    /**
     * URL einer Datei aus dem Plugin-Ordner (fester Pfad aus dem Code, keine
     * Benutzereingabe) mit Änderungszeit als Cache-Version, damit Browser nach
     * einem Plugin-Update nicht das alte Skript oder CSS verwenden.
     */
    private function assetUrl($relPath) {
        $file    = $this->phpPath() . $relPath;
        $version = is_file($file) ? (string) filemtime($file) : (string) $this->version();
        return $this->domainPath() . $relPath . '?version=' . rawurlencode($version);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SETTINGS FORM
    // ═══════════════════════════════════════════════════════════════════

    public function form() {
        // Aufbau wie bei Bludits eigenen Plugins: Felder ohne eigene Klassen,
        // gestylt über .plugin-form (bl-kernel/admin/views/configure-plugin.php),
        // Hinweise als span.tip, Gruppen mit Bludits Zwischenüberschrift
        // (Bootstrap::formTitle wie in den Bludit-Einstellungen). Beschriftungen
        // und Hinweise sind feste Texte. Werte dekodiert lesen und genau einmal
        // escapen (Plugin::post() escaped beim Speichern).
        $esc  = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES); };
        $text = function ($name, $label, $tip = '', $value = null, $attrs = '') use ($esc) {
            if ($value === null) {
                $value = $this->setting($name);
            }
            return '<div>'
                . '<label for="podcast-' . $name . '">' . $label . '</label>'
                . '<input type="text" id="podcast-' . $name . '" name="' . $name . '" value="'
                . $esc($value) . '"' . $attrs . '>'
                . ($tip !== '' ? '<span class="tip">' . $tip . '</span>' : '')
                . '</div>';
        };
        $group = function ($title) {
            return Bootstrap::formTitle(['title' => $title]);
        };
        // Eingetragene URL, die nicht mit http(s):// beginnt, wird nicht verwendet
        $ignoredUrl = function ($name) {
            $value = trim($this->setting($name));
            return $value !== '' && !$this->isHttpUrl($value)
                ? ' <strong>Wird nicht verwendet: Die Adresse muss mit http:// oder https:// beginnen.</strong>'
                : '';
        };
        $feedUrl = $this->feedUrl();

        ob_start();
        ?>
        <link rel="stylesheet" href="<?php echo $esc($this->assetUrl('css/podcast-admin.css')) ?>">

        <div class="alert alert-primary" role="alert">
            Kanal- und Feed-Einstellungen des Podcasts. Episoden werden unter
            <a href="<?php echo HTML_PATH_ADMIN_ROOT ?>plugin/podcast">Podcast – Episoden</a> verwaltet.
        </div>

        <?php
        echo $group('Podcast');
        echo $text('feedTitle', 'Podcast-Titel');
        ?>
        <div>
            <label for="podcast-feedDesc">Beschreibung</label>
            <textarea id="podcast-feedDesc" name="feedDesc" rows="3"><?php
                echo $esc($this->setting('feedDesc')) ?></textarea>
        </div>
        <?php
        echo $text('author', 'Autor');
        echo $text('feedLang', 'Sprache', 'z. B. de-de');
        // Ein hochgeladenes Cover dieser Website lässt sich prüfen, eine fremde Adresse nicht
        $coverUrl      = $this->coverUrl();
        $coverPath     = $coverUrl !== '' ? $this->localImagePath($coverUrl) : '';
        $coverProblems = $coverPath !== '' ? $this->coverProblems($coverPath) : [];
        echo $text('cover', 'Cover-URL', 'Apple verlangt ein quadratisches Bild mit 1400 bis 3000 px Kantenlänge, JPG oder PNG in RGB.'
            . $ignoredUrl('cover')
            . ($coverProblems ? ' <strong>Das eingetragene Cover passt nicht: ' . $esc(implode(' ', $coverProblems)) . '</strong>' : ''),
            null, ' inputmode="url"');
        $limits = $this->uploadLimits();
        ?>
        <div>
            <label for="podcast-cover-file">Cover hochladen</label>
            <input type="file" id="podcast-cover-file" accept=".jpg,.jpeg,.png"
                   data-api-url="<?php echo $esc(DOMAIN_ADMIN . 'plugin/podcast?podcast_api=1') ?>"
                   data-max-file="<?php echo (int) $limits['file'] ?>"<?php echo $limits['enabled'] ? '' : ' disabled' ?>>
            <span class="tip" id="podcast-cover-status" role="status">Wird beim Hochladen geprüft und in die Cover-URL übernommen; danach „Speichern“.</span>
            <img id="podcast-cover-preview" alt="Vorschau des Covers"<?php echo $coverUrl !== '' ? ' src="' . $esc($coverUrl) . '"' : ' hidden' ?>>
        </div>
        <?php
        echo $text('copyright', 'Copyright', 'Optional, z. B. © 2026 Max Mustermann');

        echo $group('Apple Podcasts &amp; Verzeichnisse');
        echo $this->categorySelect();
        ?>
        <div>
            <label for="podcast-showType">Art des Podcasts</label>
            <select id="podcast-showType" name="showType">
                <option value="episodic" <?php echo $this->showType() === 'episodic' ? 'selected' : '' ?>>Episodisch – neueste Folge zuerst</option>
                <option value="serial"   <?php echo $this->showType() === 'serial'   ? 'selected' : '' ?>>Seriell – Folgen der Reihe nach, älteste zuerst</option>
            </select>
            <span class="tip">Seriell passt zu Staffeln, die man von Anfang an hört; dann Staffel und Folge bei jeder Episode angeben.</span>
        </div>
        <div>
            <label for="podcast-explicit">Explicit</label>
            <select id="podcast-explicit" name="explicit">
                <option value="no"  <?php echo $this->getValue('explicit') !== 'yes' ? 'selected' : '' ?>>Nein</option>
                <option value="yes" <?php echo $this->getValue('explicit') === 'yes' ? 'selected' : '' ?>>Ja</option>
            </select>
        </div>
        <div>
            <label for="podcast-feedLocked">Feed gegen Übernahme sperren</label>
            <select id="podcast-feedLocked" name="feedLocked">
                <option value="no"  <?php echo !$this->feedLocked() ? 'selected' : '' ?>>Nein</option>
                <option value="yes" <?php echo $this->feedLocked()  ? 'selected' : '' ?>>Ja</option>
            </select>
            <span class="tip">Ja: Andere Plattformen übernehmen den Podcast erst, wenn über die E-Mail des Inhabers bestätigt ist, wem er gehört (<code>podcast:locked</code>). Vor einem Umzug zu einem anderen Anbieter auf Nein stellen.</span>
        </div>
        <?php
        echo $text('email', 'E-Mail des Inhabers',
            'Steht öffentlich im Feed. Verzeichnisse wie Apple Podcasts und Spotify prüfen darüber, wem der Podcast gehört.',
            null, ' inputmode="email" autocomplete="email"');

        // Links zum Podcast bei Diensten: erscheinen im Abo-Button des Players
        $serviceTips = [
            'linkApple'       => 'Links zum Podcast erscheinen im Abo-Button des Players (in der App: Teilen → Link kopieren). '
                . 'Mit dem Apple-Link erscheinen auch Castro (iPhone) und Pocket Casts.',
            'linkPocketCasts' => 'Leer lassen, um Pocket Casts über den Apple-Link zu verbinden.',
        ];
        foreach (self::SUBSCRIBE_SERVICES as $field => $service) {
            $tip = (isset($serviceTips[$field]) ? $serviceTips[$field] . ' ' : '') . 'Beispiel: ' . $esc($service[3]);
            if (trim($this->setting($field)) !== '' && $this->serviceId($field) === '') {
                $tip .= ' <strong>Wird nicht verwendet: Der Link hat nicht die erwartete Form.</strong>';
            }
            echo $text($field, $service[1], $tip, null, ' inputmode="url"');
        }

        echo $group('Feed');
        echo $text('feedSlug', 'Feed-Dateiname', 'z. B. podcast.xml');
        ?>
        <div>
            <label for="podcast-feedAddress">Adresse des Feeds</label>
            <!-- Nur zum Kopieren: ohne name, wird nicht gespeichert -->
            <input type="text" id="podcast-feedAddress" value="<?php echo $esc($feedUrl) ?>" readonly>
            <span class="tip">Diese Adresse bei Apple Podcasts, Spotify und anderen Verzeichnissen eintragen.
                <a href="<?php echo $esc($feedUrl) ?>" target="_blank" rel="noopener">Feed öffnen</a></span>
        </div>
        <div>
            <label for="podcast-guidDisplay">Podcast-GUID</label>
            <input type="text" id="podcast-guidDisplay" value="<?php echo $esc($this->podcastGuid()) ?>" readonly>
            <span class="tip">Dauerhafte Kennung des Podcasts (<code>podcast:guid</code>), beim ersten Speichern aus der Feed-Adresse erzeugt. Sie bleibt auch bei einem Umzug gleich.</span>
        </div>
        <?php
        echo $text('feedUrl', 'Abweichende Feed-URL (optional)',
            'Nur nötig, wenn der Feed über eine andere Adresse ausgeliefert wird, z. B. ein CDN. Leer lassen für die Adresse oben.' . $ignoredUrl('feedUrl'),
            null, ' inputmode="url" placeholder="' . $esc($this->localFeedUrl()) . '"');
        echo $text('siteUrl', 'Website-URL',
            'Leer lassen für diese Bludit-Website.' . $ignoredUrl('siteUrl'),
            null, ' inputmode="url" placeholder="' . $esc(DOMAIN_BASE) . '"');

        echo $group('Website &amp; Player');
        ?>
        <div>
            <label for="podcast-playerMode">Player auf Episodenseiten</label>
            <select id="podcast-playerMode" name="playerMode">
                <option value="podlove" <?php echo $this->playerMode() === 'podlove' ? 'selected' : '' ?>>Podlove Web Player (lokal gehostet, ohne Drittanbieter)</option>
                <option value="html5"   <?php echo $this->playerMode() === 'html5'   ? 'selected' : '' ?>>Einfacher HTML5-Player</option>
            </select>
        </div>
        <div>
            <label for="podcast-playerPosition">Position des Players</label>
            <select id="podcast-playerPosition" name="playerPosition">
                <option value="top"    <?php echo $this->playerPosition() === 'top'    ? 'selected' : '' ?>>Am Anfang des Inhalts (unter dem Titel)</option>
                <option value="bottom" <?php echo $this->playerPosition() === 'bottom' ? 'selected' : '' ?>>Am Ende des Inhalts</option>
            </select>
            <span class="tip">Für eine eigene Stelle <code>{{ podcast-player }}</code> in den Text der Episode schreiben.</span>
        </div>
        <?php
        echo $group('Uploads');
        echo $text('maxUploadMB', 'Max. Upload-Größe (MB)', 'Die PHP-Limits des Servers gelten zusätzlich.',
            (int) ($this->getValue('maxUploadMB') ?: 512), ' inputmode="numeric" pattern="[0-9]*"');
        ?>
        <script src="<?php echo $esc($this->assetUrl('js/podcast-admin.js')) ?>"></script>
        <?php
        return ob_get_clean();
    }

    /**
     * Speichert die Kanaleinstellungen (parent::post(): dbFields aus $_POST;
     * Bludit ruft post() auf, nicht formSave()) und legt beim ersten Speichern
     * die Podcast-GUID fest.
     */
    public function post() {
        $saved = parent::post();
        $this->ensurePodcastGuid();
        return $saved;
    }

    // ═══════════════════════════════════════════════════════════════════
    // BEFORE ALL: FEED
    // ═══════════════════════════════════════════════════════════════════

    public function beforeAll() {
        // webhook() vergleicht mit HTML_PATH_ROOT (auch bei Bludit im Unterordner)
        // und ignoriert Parameter wie ?nocache=…
        if ($this->webhook($this->feedSlug())) {
            $this->outputFeed();
            exit;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // PLAYER AUF EPISODENSEITEN
    // ═══════════════════════════════════════════════════════════════════

    /** Platzhalter, mit dem man den Player frei im Inhalt positioniert. */
    private const PLAYER_PLACEHOLDER = '{{ podcast-player }}';

    /**
     * Setzt den Player in den Seiteninhalt — so steht er in jedem Theme dort,
     * wo das Theme den Inhalt ausgibt (zwischen Titel und Text bzw. am Ende),
     * ohne nachträgliches Verschieben per JavaScript. Muster wie Bludits
     * custom-fields-parser: $page->setField('content', …) in beforeSiteLoad().
     */
    public function beforeSiteLoad() {
        $this->injectPlayer();
        $this->removePlaceholdersInLists();
    }

    public function siteHead() {
        $this->removePlaceholdersInLists();

        // Feed-Discovery auf allen Seiten (wie das Core-RSS-Plugin), damit
        // Browser und Podcast-Apps den Feed über die Website-Adresse finden
        global $site;
        $feedTitle = $this->setting('feedTitle') ?: Sanitize::htmlDecode((string) $site->title());
        echo '<link rel="alternate" type="application/rss+xml" title="'
            . htmlspecialchars($feedTitle, ENT_QUOTES, 'UTF-8') . '" href="'
            . htmlspecialchars($this->feedUrl(), ENT_QUOTES, 'UTF-8') . '">' . "\n";

        $page = $this->currentEpisode();
        if (!$page) return;

        // Ein anderes Plugin (z. B. custom-fields-parser) kann den Inhalt in
        // beforeSiteLoad() neu aus dem Rohtext aufgebaut haben — siteHead()
        // läuft danach und vor der Ausgabe des Inhalts.
        $this->injectPlayer();

        if ($this->playerMode() !== 'podlove') return;

        // Lokal ausgelieferter Player (player/SOURCE.md) — keine Verbindung zu einem CDN
        $src = $this->playerBaseUrl() . 'embed.js?version=' . self::PODLOVE_VERSION;
        echo '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
    }

    /** Aktuelle Seite, wenn sie eine Episode mit Audio ist, sonst null. */
    private function currentEpisode() {
        global $url, $page;

        if (!($url instanceof Url) || $url->whereAmI() !== 'page') return null;
        if (!$page instanceof Page || !$this->pageHasPodcastTag($page)) return null;
        $meta = $this->getEpisodeMeta($page);
        return empty($meta['audio_url']) ? null : $page;
    }

    /** Fügt den Player einmal in den Inhalt der aktuellen Episode ein. */
    private function injectPlayer() {
        $page = $this->currentEpisode();
        if (!$page) return;

        $content = (string) $page->content();
        if (strpos($content, 'id="podcast-episode-player"') !== false) return;

        $player = $this->playerHtml($page);
        // Platzhalter: Parsedown setzt eine allein stehende Zeile in <p>…</p>
        $wrapped = '<p>' . self::PLAYER_PLACEHOLDER . '</p>';
        if (strpos($content, $wrapped) !== false) {
            $content = $this->replaceOnce($wrapped, $player, $content);
        } elseif (strpos($content, self::PLAYER_PLACEHOLDER) !== false) {
            $content = $this->replaceOnce(self::PLAYER_PLACEHOLDER, $player, $content);
        } elseif ($this->playerPosition() === 'bottom') {
            $content = $content . "\n" . $player;
        } else {
            $content = $player . "\n" . $content;
        }
        // Weitere Platzhalter entfernen (nur ein Player pro Seite)
        $content = str_replace([$wrapped, self::PLAYER_PLACEHOLDER], '', $content);
        $page->setField('content', $content);
    }

    /**
     * In Listen (Startseite, Tag-, Kategorieseiten) gibt es keinen Player —
     * dort den Platzhalter aus Vorschau und Inhalt entfernen.
     */
    private function removePlaceholdersInLists() {
        global $url, $content;

        if (!($url instanceof Url) || $url->whereAmI() === 'page' || !is_array($content)) return;
        foreach ($content as $listPage) {
            if (!$listPage instanceof Page || !$this->pageHasPodcastTag($listPage)) continue;
            $html = (string) $listPage->content();
            if (strpos($html, self::PLAYER_PLACEHOLDER) === false) continue;
            $html = str_replace(['<p>' . self::PLAYER_PLACEHOLDER . '</p>', self::PLAYER_PLACEHOLDER], '', $html);
            // Leerer Wert ließe Page::content() den Rohtext neu laden
            $listPage->setField('content', $html === '' ? ' ' : $html);
        }
    }

    private function replaceOnce($search, $replace, $subject) {
        $pos = strpos($subject, $search);
        return $pos === false ? $subject : substr_replace($subject, $replace, $pos, strlen($search));
    }

    /** Text einer Seite ohne HTML und ohne Player-Platzhalter (Feed, Player-Beschreibung). */
    private function contentWithoutPlaceholder($page) {
        return str_replace(self::PLAYER_PLACEHOLDER, '', (string) $page->content());
    }

    /** Player-Container: Podlove-Ziel plus HTML5-Fallback (bleibt, bis Podlove geladen ist). */
    private function playerHtml($page) {
        $audioAttr = htmlspecialchars($this->getEpisodeMeta($page)['audio_url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html  = '<div id="podcast-episode-player" class="podcast-episode-player">';
        if ($this->playerMode() === 'podlove') {
            $html .= '<div id="podlove-episode-player"></div>';
        }
        $html .= '<div class="podcast-audio-fallback">'
            . '<audio controls preload="none" src="' . $audioAttr . '" style="width:100%"></audio>'
            . '<p><a href="' . $audioAttr . '" download>Episode herunterladen</a></p>'
            . '</div></div>';
        return $html;
    }

    /** Startet den Podlove-Player (embed.js ist im Kopf geladen). */
    public function siteBodyEnd() {
        $page = $this->currentEpisode();
        if (!$page || $this->playerMode() !== 'podlove') return;

        $meta = $this->getEpisodeMeta($page);
        $get  = function ($k) use ($meta) { return isset($meta[$k]) ? $meta[$k] : ''; };

        $audioUrl    = $get('audio_url');
        $audioLength = (int) $get('audio_length');
        $audioExt    = strtolower(pathinfo((string) parse_url($audioUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mime        = $this->audioMimeType($audioExt);
        $duration    = $get('itunes_duration');
        $poster      = $get('itunes_image') ?: $this->coverUrl();
        // Podlove rendert die Beschreibung als HTML — daher als escapten Text übergeben.
        // Titel zeigt er als Text an: Klartext übergeben (json_encode escaped für JavaScript).
        // Vom eigenen Inhalt nur den Text, ohne den eingefügten Player.
        $summary     = trim(html_entity_decode(strip_tags($this->contentWithoutPlaceholder($this->freshPage($page))), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $summaryHtml = nl2br(htmlspecialchars($summary, ENT_QUOTES | ENT_HTML5, 'UTF-8'), false);
        $showSummary = nl2br(htmlspecialchars($this->setting('feedDesc'), ENT_QUOTES | ENT_HTML5, 'UTF-8'), false);

        $episodeConfig = [
            'show' => [
                'title'    => $this->setting('feedTitle'),
                'subtitle' => '',
                'summary'  => $showSummary,
                'poster'   => $this->coverUrl(),
                'link'     => $this->siteUrl(),
            ],
            'title'           => $this->plainTitle($page),
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

        // Assets (Layouts, Player-Bundle, Abo-Button) aus dem Plugin-Ordner laden;
        // Abo-Button immer mit der Feed-Adresse (auch ohne eingetragene Feed-URL)
        $feedUrl = $this->feedUrl();
        $playerConfig = [
            'base'             => $this->playerBaseUrl(),
            'subscribe-button' => ['feed' => $feedUrl, 'clients' => $this->subscribeClients($feedUrl)],
        ];

        $flags       = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $episodeJson = json_encode($episodeConfig, $flags);
        $playerJson  = json_encode($playerConfig,  $flags);
        if ($episodeJson === false || $playerJson === false) return;
        ?>
        <script>
        (function () {
            // HTML5-Fallback bleibt sichtbar, bis der Podlove-Player geladen ist
            var target = document.getElementById('podlove-episode-player');
            if (!target || typeof window.podlovePlayer !== 'function') return;
            var fallback = document.querySelector('#podcast-episode-player .podcast-audio-fallback');
            Promise.resolve(window.podlovePlayer(
                '#podlove-episode-player',
                <?php echo $episodeJson ?>,
                <?php echo $playerJson ?>
            )).then(function () {
                if (fallback && fallback.parentNode) {
                    fallback.parentNode.removeChild(fallback);
                }
            }, function () {});
        }());
        </script>
        <?php
    }

    /** Neues Page-Objekt derselben Seite: Inhalt ohne den eingefügten Player. */
    private function freshPage($page) {
        try {
            return new Page($page->key());
        } catch (Exception $e) {
            return $page;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // RSS FEED GENERATION
    // ═══════════════════════════════════════════════════════════════════

    private function outputFeed() {
        global $pages;

        // Alle öffentlich sichtbaren Seiten mit Tag 'podcast' (wie in der
        // Episodenliste: veröffentlicht, fixiert, statisch); geplante und
        // Entwürfe nicht. getPublishedDB() allein liefert nur 'published'.
        $keys     = array_merge($pages->getPublishedDB(true), $pages->getStickyDB(true), $pages->getStaticDB(true));
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

        // Einstellungen und Titel sind HTML-escaped gespeichert: dekodieren, dann
        // genau einmal für XML escapen
        $title     = $this->xml($this->setting('feedTitle'));
        $feedUrl   = $this->xml($this->feedUrl());
        $siteUrl   = $this->xml($this->siteUrl());
        $desc      = $this->xml($this->setting('feedDesc'));
        $lang      = $this->xml($this->setting('feedLang') ?: 'de-de');
        $author    = $this->xml($this->setting('author'));
        $email     = $this->xml($this->setting('email'));
        $category  = $this->appleCategory();
        $cover     = $this->xml($this->coverUrl());
        $copyright = $this->xml($this->setting('copyright'));
        // Apple erwartet true/false (nicht mehr yes/no)
        $explicit  = $this->getValue('explicit') === 'yes' ? 'true' : 'false';
        $buildDate = date('r');

        header('Content-Type: application/rss+xml; charset=UTF-8');
        // Kein Output vor diesem Punkt — ob_start() wurde ggf. von Bludit gestartet
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0"' . "\n";
        echo '    xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"' . "\n";
        echo '    xmlns:content="http://purl.org/rss/1.0/modules/content/"' . "\n";
        // Podcasting-2.0-Namespace: von PSP-1 verlangt; Elemente daraus folgen mit 019
        echo '    xmlns:podcast="https://podcastindex.org/namespace/1.0"' . "\n";
        echo '    xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo '<channel>' . "\n";
        echo '  <title>' . $title . '</title>' . "\n";
        echo '  <link>' . $siteUrl . '</link>' . "\n";
        echo '  <description>' . $desc . '</description>' . "\n";
        echo '  <language>' . $lang . '</language>' . "\n";
        if ($copyright !== '') {
            echo '  <copyright>' . $copyright . '</copyright>' . "\n";
        }
        if ($email) {
            echo '  <managingEditor>' . $email . '</managingEditor>' . "\n";
        }
        echo '  <lastBuildDate>' . $buildDate . '</lastBuildDate>' . "\n";
        echo '  <generator>Bludit Podcast Plugin</generator>' . "\n";
        echo '  <atom:link href="' . $feedUrl . '" rel="self" type="application/rss+xml" />' . "\n";
        echo '  <itunes:author>' . $author . '</itunes:author>' . "\n";
        echo '  <itunes:owner>' . "\n";
        echo '    <itunes:name>' . $author . '</itunes:name>' . "\n";
        if ($email) {
            echo '    <itunes:email>' . $email . '</itunes:email>' . "\n";
        }
        echo '  </itunes:owner>' . "\n";
        if ($cover) {
            echo '  <itunes:image href="' . $cover . '" />' . "\n";
            // RSS-2.0-Bild für Apps und Reader ohne iTunes-Namespace
            echo '  <image>' . "\n";
            echo '    <url>' . $cover . '</url>' . "\n";
            echo '    <title>' . $title . '</title>' . "\n";
            echo '    <link>' . $siteUrl . '</link>' . "\n";
            echo '  </image>' . "\n";
        }
        // Englische Originalnamen, Unterkategorie verschachtelt (Apple-Vorgabe)
        if ($category) {
            if ($category[1] !== '') {
                echo '  <itunes:category text="' . $this->xml($category[0]) . '">' . "\n";
                echo '    <itunes:category text="' . $this->xml($category[1]) . '" />' . "\n";
                echo '  </itunes:category>' . "\n";
            } else {
                echo '  <itunes:category text="' . $this->xml($category[0]) . '" />' . "\n";
            }
        }
        echo '  <itunes:type>' . $this->showType() . '</itunes:type>' . "\n";
        echo '  <itunes:explicit>' . $explicit . '</itunes:explicit>' . "\n";
        // Podcasting 2.0 (Roadmap 019): Kennung und Sperre gegen Übernahme
        echo '  <podcast:guid>' . $this->xml($this->podcastGuid()) . '</podcast:guid>' . "\n";
        $ownerEmail = filter_var($this->setting('email'), FILTER_VALIDATE_EMAIL);
        echo '  <podcast:locked' . ($ownerEmail !== false ? ' owner="' . $this->xml($ownerEmail) . '"' : '') . '>'
            . ($this->feedLocked() ? 'yes' : 'no') . '</podcast:locked>' . "\n";

        foreach ($episodes as $page) {
            $meta = $this->getEpisodeMeta($page);
            $get  = function ($k) use ($meta) { return isset($meta[$k]) ? $meta[$k] : ''; };

            $audioUrl    = $get('audio_url');
            if (!$audioUrl) continue;

            $audioLength = (int) $get('audio_length');
            $audioExt    = strtolower(pathinfo(parse_url($audioUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $mime        = $this->audioMimeType($audioExt);

            $epTitle    = $this->xml($this->plainTitle($page));
            // <description>: Text ohne Tags (Entities bleiben, Apple liest ihn als
            // HTML), höchstens 4000 Zeichen; <content:encoded>: Shownotes als
            // bereinigtes HTML (Absätze, Listen, Links)
            $epHtml     = $this->contentWithoutPlaceholder($page);
            $epNotes    = $this->shownotesHtml($epHtml);
            $epDesc     = $this->xml($this->episodeTextSummary($epNotes !== '' ? $epNotes : $epHtml));
            $epLink     = $this->xml($page->permalink());
            $epAudioUrl = $this->xml($audioUrl);
            $epGuid     = $this->xml($page->uuid());
            $epDate     = $page->date('r');
            $epNum      = $this->xml($get('itunes_episode'));
            $epSeason   = $this->xml($get('itunes_season'));
            $epDur      = $this->xml($get('itunes_duration'));
            $epType     = $this->xml($get('itunes_type') ?: 'full');
            $epExpl     = $get('itunes_explicit') === 'yes' ? 'true' : 'false';
            $epImage    = $this->xml($get('itunes_image'));

            echo '  <item>' . "\n";
            echo '    <title>' . $epTitle . '</title>' . "\n";
            echo '    <description>' . $epDesc . '</description>' . "\n";
            if ($epNotes !== '') {
                echo '    <content:encoded>' . $this->cdata($epNotes) . '</content:encoded>' . "\n";
            }
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

    /**
     * Episodenliste nach dem Muster von Bludits Inhaltsliste
     * (bl-kernel/admin/views/content.php): Titel mit Datum darunter,
     * Nebenspalten erst ab mittlerer Breite, Aktionen im ☰-Menü.
     */
    private function renderEpisodeSection($canConfigure = false) {
        global $pages;

        $episodes = $this->getEpisodes();
        // Status-Badges nur für Episoden, die (noch) nicht öffentlich sind
        $publicTypes = ['published', 'sticky', 'static'];
        ob_start();
        ?>
        <div id="podcast-manager">
          <div id="podcast-episode-list">
            <div class="d-flex flex-wrap">
              <a href="#" id="podcast-new-btn" class="mr-4 mb-2" role="button">
                <span class="fa fa-plus mr-1"></span>Neue Episode
              </a>
              <?php if ($canConfigure): ?>
              <a href="<?php echo HTML_PATH_ADMIN_ROOT ?>configure-plugin/Podcast" class="mb-2">
                <span class="fa fa-cog mr-1"></span>Kanaleinstellungen
              </a>
              <?php endif ?>
            </div>

            <?php if (empty($episodes)): ?>
              <p class="mt-4 text-muted">Noch keine Episoden vorhanden.</p>
            <?php else: ?>
              <table class="table mt-3">
                <thead>
                  <tr>
                    <th class="border-0" scope="col">Titel</th>
                    <th class="border-0 text-center d-none d-md-table-cell" scope="col">Staffel</th>
                    <th class="border-0 text-center d-none d-md-table-cell" scope="col">Folge</th>
                    <th class="border-0 d-none d-lg-table-cell" scope="col">Typ</th>
                    <th class="border-0 text-center" scope="col">Aktionen</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($episodes as $ep):
                      $meta   = $this->getEpisodeMeta($ep);
                      $get    = function ($k) use ($meta) { return isset($meta[$k]) ? (string) $meta[$k] : ''; };
                      // Formular mit den gespeicherten Werten vorbelegen, auch wenn sie ungültig sind
                      $stored = $this->getStoredEpisodeMeta($ep);
                      $raw    = function ($k) use ($stored) { return isset($stored[$k]) ? $stored[$k] : ''; };

                      $title    = $this->plainTitle($ep);
                      // Löschen wie in Bludits Inhaltsliste nur ohne Unterseiten
                      $canDelete = empty($pages->getChildren($ep->key()));
                      $type     = $ep->type();
                      $isPublic = in_array($type, $publicTypes, true);
                      $season   = $get('itunes_season');
                      $number   = $get('itunes_episode');
                      $numbers  = [];
                      if ($season !== '') $numbers[] = 'Staffel ' . $season;
                      if ($number !== '') $numbers[] = 'Folge ' . $number;
                  ?>
                  <tr class="podcast-episode-row"
                      data-key="<?php echo htmlspecialchars($ep->key(), ENT_QUOTES) ?>"
                      data-title="<?php echo htmlspecialchars($title, ENT_QUOTES) ?>"
                      data-content="<?php echo htmlspecialchars($ep->contentRaw(), ENT_QUOTES) ?>"
                      data-episode="<?php echo htmlspecialchars($raw('itunes_episode'), ENT_QUOTES) ?>"
                      data-season="<?php echo htmlspecialchars($raw('itunes_season'), ENT_QUOTES) ?>"
                      data-type="<?php echo htmlspecialchars($get('itunes_type') ?: 'full', ENT_QUOTES) ?>"
                      data-explicit="<?php echo htmlspecialchars($get('itunes_explicit') ?: 'no', ENT_QUOTES) ?>"
                      data-duration="<?php echo htmlspecialchars($raw('itunes_duration'), ENT_QUOTES) ?>"
                      data-image="<?php echo htmlspecialchars($raw('itunes_image'), ENT_QUOTES) ?>"
                      data-audio-url="<?php echo htmlspecialchars($raw('audio_url'), ENT_QUOTES) ?>"
                      data-status="<?php echo htmlspecialchars($type, ENT_QUOTES) ?>"
                      data-date="<?php echo $ep->date('Y-m-d\TH:i') ?>">
                    <td class="pt-3">
                      <div class="text-break">
                        <a href="#" class="podcast-edit-btn podcast-episode-title" role="button">
                          <?php if ($title !== ''): ?>
                            <?php echo htmlspecialchars($title, ENT_QUOTES) ?>
                          <?php else: ?>
                            <span class="label-empty-title">Kein Titel</span>
                          <?php endif ?>
                        </a>
                        <?php if ($type === 'scheduled'): ?>
                          <span class="badge badge-info align-middle ml-1">Geplant</span>
                        <?php elseif ($type === 'sticky'): ?>
                          <span class="badge badge-warning align-middle ml-1"><i class="fa fa-thumb-tack"></i> Fixiert</span>
                        <?php elseif ($type === 'static'): ?>
                          <span class="badge badge-light align-middle ml-1">Statisch</span>
                        <?php elseif (!$isPublic): ?>
                          <span class="badge badge-secondary align-middle ml-1">Entwurf</span>
                        <?php endif ?>
                      </div>
                      <p class="podcast-episode-meta m-0 text-uppercase text-muted">
                        <?php echo $type === 'scheduled' ? $ep->date('d.m.Y, H:i \U\h\r') : $ep->date('d.m.Y') ?><?php if ($numbers): ?><span class="d-md-none"> · <?php echo htmlspecialchars(implode(' · ', $numbers)) ?></span><?php endif ?>
                      </p>
                    </td>
                    <td class="pt-3 text-center d-none d-md-table-cell"><?php echo htmlspecialchars($season) ?></td>
                    <td class="pt-3 text-center d-none d-md-table-cell"><?php echo htmlspecialchars($number) ?></td>
                    <td class="pt-3 d-none d-lg-table-cell"><?php echo htmlspecialchars($get('itunes_type') ?: 'full') ?></td>
                    <td class="pt-3 text-center align-middle">
                      <div class="dropdown actionsDropdown">
                        <button class="btn btn-link text-secondary p-1 actionsDropdownToggle" type="button"
                                data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false"
                                title="Aktionen"
                                aria-label="Aktionen für <?php echo htmlspecialchars($title !== '' ? $title : 'Episode ohne Titel', ENT_QUOTES) ?>">
                          <i class="fa fa-bars"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                          <?php if ($isPublic): ?>
                          <a class="dropdown-item" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($ep->permalink(), ENT_QUOTES) ?>">
                            <i class="fa fa-desktop fa-fw mr-2"></i>Anschauen
                          </a>
                          <?php endif ?>
                          <a href="#" class="dropdown-item podcast-edit-btn" role="button">
                            <i class="fa fa-edit fa-fw mr-2"></i>Bearbeiten
                          </a>
                          <a class="dropdown-item" href="<?php echo HTML_PATH_ADMIN_ROOT ?>edit-content/<?php echo htmlspecialchars($ep->key(), ENT_QUOTES) ?>">
                            <i class="fa fa-pencil fa-fw mr-2"></i>Im Bludit-Editor öffnen
                          </a>
                          <?php if ($canDelete): ?>
                          <div class="dropdown-divider"></div>
                          <a href="#" class="dropdown-item text-danger podcast-delete-btn" role="button">
                            <i class="fa fa-trash fa-fw mr-2"></i>Löschen
                          </a>
                          <?php endif ?>
                        </div>
                      </div>
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
        $limits = $this->uploadLimits();
        ob_start();
        ?>
        <div id="podcast-episode-form" hidden>
          <!-- Aktionen oben rechts neben dem Titel wie in Bludits eigenen Formularen
               (views/settings.php, edit-user.php: float-right, btn-sm) -->
          <div class="align-middle clearfix">
            <div class="float-right mt-1 ml-2 mb-2">
              <span id="podcast-saving" class="text-muted small mr-2" role="status" hidden>
                <span class="fa fa-spinner fa-spin"></span> Wird gespeichert&hellip;
              </span>
              <button type="button" class="btn btn-primary btn-sm" id="podcast-save-btn">Speichern</button>
              <button type="button" class="btn btn-secondary btn-sm" id="podcast-cancel-btn">Abbrechen</button>
            </div>
            <h4 id="podcast-form-title" class="mb-3">Neue Episode</h4>
          </div>

          <div id="podcast-save-error" class="alert alert-danger" role="alert" hidden></div>
          <input type="hidden" id="pe-key" value="">

          <div class="form-group">
            <label for="pe-title">Titel *</label>
            <input type="text" class="form-control" id="pe-title">
          </div>
          <div class="form-group">
            <label for="pe-content">Beschreibung *</label>
            <textarea class="form-control" id="pe-content" rows="4"></textarea>
          </div>

          <div class="form-row">
            <div class="form-group col-6 col-md-3">
              <label for="pe-episode">Folge</label>
              <input type="number" class="form-control" id="pe-episode" min="0" inputmode="numeric">
            </div>
            <div class="form-group col-6 col-md-3">
              <label for="pe-season">Staffel</label>
              <input type="number" class="form-control" id="pe-season" min="0" inputmode="numeric">
            </div>
            <div class="form-group col-6 col-md-3">
              <label for="pe-type">Typ</label>
              <select class="custom-select" id="pe-type">
                <option value="full">full</option>
                <option value="trailer">trailer</option>
                <option value="bonus">bonus</option>
              </select>
            </div>
            <div class="form-group col-6 col-md-3">
              <label for="pe-explicit">Explicit</label>
              <select class="custom-select" id="pe-explicit">
                <option value="no">Nein</option>
                <option value="yes">Ja</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="pe-duration">Dauer (HH:MM:SS)</label>
            <input type="text" class="form-control" id="pe-duration" placeholder="00:45:00">
          </div>

          <div class="form-group">
            <label id="pe-image-label">Episodenbild</label>
            <div role="radiogroup" aria-labelledby="pe-image-label">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="pe-image-mode" id="pe-image-mode-url" value="url" checked>
                <label class="form-check-label" for="pe-image-mode-url">URL</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="pe-image-mode" id="pe-image-mode-file" value="file">
                <label class="form-check-label" for="pe-image-mode-file">Datei hochladen</label>
              </div>
            </div>
            <input type="text" class="form-control mt-2" id="pe-image-url" placeholder="https://..."
                   inputmode="url" aria-labelledby="pe-image-label">
            <div class="custom-file mt-2" id="pe-image-file-wrap" hidden>
              <input type="file" class="custom-file-input" id="pe-image-file" accept=".jpg,.jpeg,.png">
              <label class="custom-file-label" for="pe-image-file" data-browse="Durchsuchen"
                     data-placeholder="Bild auswählen …">Bild auswählen …</label>
            </div>
            <img id="pe-image-preview" class="mt-2 border rounded" alt="Vorschau des Episodenbilds" hidden>
          </div>

          <div class="form-group">
            <label id="pe-audio-label">Audio *</label>
            <div role="radiogroup" aria-labelledby="pe-audio-label">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="pe-audio-mode" id="pe-audio-mode-url" value="url" checked>
                <label class="form-check-label" for="pe-audio-mode-url">Externe URL</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="pe-audio-mode" id="pe-audio-mode-file" value="file">
                <label class="form-check-label" for="pe-audio-mode-file">Datei hochladen</label>
              </div>
            </div>
            <input type="text" class="form-control mt-2" id="pe-audio-url" placeholder="https://..."
                   inputmode="url" aria-labelledby="pe-audio-label">
            <div class="custom-file mt-2" id="pe-audio-file-wrap" hidden>
              <input type="file" class="custom-file-input" id="pe-audio-file" accept=".mp3,.m4a,.ogg,.wav">
              <label class="custom-file-label" for="pe-audio-file" data-browse="Durchsuchen"
                     data-placeholder="Audiodatei auswählen …">Audiodatei auswählen …</label>
            </div>
            <div class="alert alert-warning small py-2 px-3 mt-2 mb-0" id="pe-audio-format-hint" role="status" hidden>
              Apple Podcasts spielt WAV- und OGG-Dateien nicht ab. Für Podcast-Verzeichnisse besser MP3 oder M4A verwenden.
            </div>
            <small class="form-text text-muted" id="pe-upload-limit"
                   data-max-file="<?php echo (int) $limits['file'] ?>"
                   data-max-post="<?php echo (int) $limits['post'] ?>">
              <?php if (!$limits['enabled']): ?>
                Datei-Uploads sind auf dem Server deaktiviert (<code>file_uploads = Off</code>) — bitte Audio-URL verwenden.
              <?php else: ?>
                Max. <?php echo $this->formatMB($limits['file']) ?> MB pro Datei<?php if ($limits['server']): ?>
                — vom Server begrenzt (<code>upload_max_filesize = <?php echo htmlspecialchars((string) ini_get('upload_max_filesize'), ENT_QUOTES) ?></code>,
                <code>post_max_size = <?php echo htmlspecialchars((string) ini_get('post_max_size'), ENT_QUOTES) ?></code>;
                Plugin-Einstellung: <?php echo $this->formatMB($limits['plugin']) ?> MB). Für größere Dateien die PHP-Limits erhöhen, siehe README.
                <?php endif ?>
              <?php endif ?>
            </small>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="pe-date">Veröffentlichungsdatum *</label>
              <input type="datetime-local" class="form-control" id="pe-date" required
                     value="<?php echo date('Y-m-d\TH:i') ?>"
                     data-timezone="<?php echo htmlspecialchars(date_default_timezone_get(), ENT_QUOTES) ?>"
                     aria-describedby="pe-date-help">
              <small id="pe-date-help" class="form-text text-muted">
                Zeitzone der Website: <?php echo htmlspecialchars(date_default_timezone_get(), ENT_QUOTES) ?>.
                Ein Datum in der Zukunft plant die Episode.
              </small>
            </div>
            <div class="form-group col-md-6">
              <label for="pe-status">Status</label>
              <select class="custom-select" id="pe-status">
                <option value="published">Veröffentlicht</option>
                <option value="draft">Entwurf</option>
              </select>
            </div>
          </div>

        </div>
        <?php
        return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════
    // EPISODE CRUD API (aufgerufen aus beforeAll)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Führt eine API-Aktion aus. Nur aus adminController() aufrufen (Login,
     * Rolle und CSRF-Token sind dort geprüft).
     */
    private function runApiAction($action) {
        try {
            switch ($action) {
                case 'save_episode':
                    return $this->handleSaveEpisode($_POST, $_FILES);
                case 'delete_episode':
                    return $this->handleDeleteEpisode($this->postString($_POST, 'episode_key'));
                case 'upload_cover':
                    return $this->handleCoverUpload($_FILES);
                default:
                    return ['error' => 'Unbekannte Aktion'];
            }
        } catch (RuntimeException $e) {
            // Eigene, für Nutzer formulierte Fehlermeldungen
            return ['error' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::set(__METHOD__ . LOG_SEP . $e->getMessage());
            return ['error' => 'Interner Fehler. Details stehen im Bludit-Log.'];
        }
    }

    private function isApiRequest() {
        return (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')
            && isset($_GET['podcast_api']);
    }

    /** Antwortet mit JSON und beendet die Anfrage; verwirft vorherige Ausgaben (z. B. PHP-Warnungen). */
    private function sendJson(array $data) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Überschreitet ein Upload post_max_size, verwirft PHP den ganzen POST-Body
     * samt tokenCSRF. Bludits CSRF-Prüfung im Admin-Bereich würde den Nutzer
     * dann abmelden. Daher läuft diese Prüfung schon beim Laden des Plugins
     * (60.plugins.php, vor 99.security.php) und antwortet mit einer klaren
     * Meldung — nur für angemeldete Nutzer, ohne etwas zu ändern.
     */
    private function rejectOversizedApiPost() {
        global $url, $login;

        if (!$this->isApiRequest() || !($url instanceof Url) || $url->whereAmI() !== 'admin') {
            return;
        }
        if (strtolower(trim($url->slug(), '/')) !== 'plugin/podcast' || !$this->installed()) {
            return;
        }
        $postMaxBytes = $this->parseBytes(ini_get('post_max_size'));
        $contentLen   = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($postMaxBytes <= 0 || $contentLen <= $postMaxBytes) {
            return;
        }
        if (!($login instanceof Login) || !$login->isLogged()) {
            return;
        }
        $limitMB = round($postMaxBytes / 1024 / 1024, 0);
        $this->sendJson(['error' => 'Upload zu groß: Gesamtgröße überschreitet das Server-Limit von ' . $limitMB . ' MB. Bitte kleinere Dateien wählen oder Audio-URL verwenden.']);
    }

    private function handleSaveEpisode($post, $files = []) {
        global $pages;

        // Roh übergeben: Pages::add()/edit() escapen selbst (Sanitize::html)
        $title   = trim($this->postString($post, 'title'));
        $content = $this->postString($post, 'content');
        $date    = $this->episodeDate($this->postString($post, 'date'));
        $key     = $this->postString($post, 'key');

        if (!$title) {
            return ['error' => 'Titel ist erforderlich'];
        }
        // Pflichtfeld: Pages::edit() ignoriert leeren Inhalt, ein geleertes Feld
        // würde sonst stillschweigend den alten Text behalten
        if ($content === '') {
            return ['error' => 'Bitte eine Beschreibung eingeben.'];
        }
        if ($date === null) {
            return ['error' => 'Bitte ein gültiges Veröffentlichungsdatum eingeben.'];
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

        // Status: veröffentlicht oder Entwurf. „Geplant“ setzt Bludit selbst bei
        // veröffentlicht + Datum in der Zukunft. Fixiert/statisch nur, wenn die
        // Episode diesen Typ schon hat (im Bludit-Editor gesetzt) — bleibt dann.
        $allowedStatus = ['published', 'draft'];
        if ($page && in_array($page->type(), ['sticky', 'static'], true)) {
            $allowedStatus[] = $page->type();
        }
        $postedStatus = $this->postString($post, 'status');
        $status       = in_array($postedStatus, $allowedStatus, true) ? $postedStatus : 'published';

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
        $imageUrl      = '';
        $imageProblems = [];
        if (!empty($files['image_file'])) {
            $imgErr = $files['image_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            if (!is_int($imgErr)) {
                return ['error' => 'Ungültiger Bild-Upload'];
            }
            if ($imgErr === UPLOAD_ERR_OK) {
                $imageUrl = $this->handleUpload('image_file', 'image');
                // Episodenbilder nur mit Warnung: Apple zeigt dann das Kanal-Cover
                $imagePath = $this->localImagePath($imageUrl);
                $imageProblems = $imagePath !== '' ? $this->coverProblems($imagePath) : [];
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

        // Dateigröße für <enclosure length>, wenn nicht gerade hochgeladen:
        // unveränderte URL behält die gespeicherte Größe, eigene Uploads per
        // filesize(), sonst Abfrage beim fremden Server (remoteAudioLength())
        $warning = '';
        if ($audioLength === 0) {
            $stored       = $page ? $this->getEpisodeMeta($page) : [];
            $storedLength = isset($stored['audio_length']) ? (int) $stored['audio_length'] : 0;
            if ($storedLength > 0 && isset($stored['audio_url']) && $stored['audio_url'] === $audioUrl) {
                $audioLength = $storedLength;
            } elseif (($localLength = $this->localAudioLength($audioUrl)) > 0) {
                $audioLength = $localLength;
            } else {
                $probe = $this->remoteAudioLength($audioUrl);
                if (isset($probe['length'])) {
                    $audioLength = $probe['length'];
                } else {
                    $warning = 'Episode gespeichert, aber die Dateigröße der Audio-URL ließ sich nicht ermitteln: '
                        . $probe['error'] . ' Im Feed steht sie dann als 0; beim nächsten Speichern wird es erneut versucht.';
                }
            }
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
            'date'    => $date,
            'custom'  => $this->customFieldArgs($page, $meta),
        ];

        // Über Bludits createPage()/editPage(): setzen den Autor, indizieren Tags
        // und Kategorien neu, lösen die Hooks aus (u. a. Core-RSS, Sitemap) und
        // schreiben ins Systemlog
        if ($key !== '') {
            // Bestehende Episode bearbeiten (Key wurde oben geprüft). Ohne 'tags'
            // behält Bludit die vorhandenen Tags; 'podcast' ist darunter (geprüft).
            $pageArgs['key'] = $key;
            // Ohne 'parent' würde Pages::edit() eine Unterseite an die oberste
            // Ebene verschieben (neuer Schlüssel, neue URL)
            $parentKey = $page->parentKey();
            if (is_string($parentKey) && $parentKey !== '') {
                $pageArgs['parent'] = $parentKey;
            }
            $newKey = editPage($pageArgs);
        } else {
            $pageArgs['tags'] = 'podcast';
            // URL wie im Bludit-Editor aus dem Rohtitel; sonst bildet Pages::add()
            // sie aus dem escapten Titel („tom-amp-jerry“)
            $pageArgs['slug'] = $pages->generateKey($title, false, true);
            $newKey = createPage($pageArgs);
        }
        if (!$newKey) {
            return ['error' => 'Episode konnte nicht gespeichert werden. Details stehen im Bludit-Log.'];
        }
        $result = ['success' => true, 'key' => $newKey];
        if ($imageProblems) {
            $warning = trim($warning . ' Episodenbild gespeichert, aber es passt nicht zu Apples Vorgaben: '
                . implode(' ', $imageProblems) . ' Apple zeigt dann womöglich nur das Kanal-Cover.');
        }
        if ($warning !== '') {
            $result['warning'] = $warning;
        }
        return $result;
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

        // Pages::delete() löscht den Ordner samt Unterseiten-Ordnern, deren Einträge
        // blieben verwaist zurück — wie Bludits Inhaltsliste nur ohne Unterseiten
        if (!empty($pages->getChildren($key))) {
            return ['error' => 'Die Episode hat Unterseiten und kann hier nicht gelöscht werden.'];
        }

        if (!deletePage($key)) {
            return ['error' => 'Episode konnte nicht gelöscht werden. Details stehen im Bludit-Log.'];
        }

        return ['success' => true];
    }

    /**
     * Tatsächliches Upload-Limit: Minimum aus Plugin-Einstellung,
     * upload_max_filesize (pro Datei) und post_max_size (ganze Anfrage).
     * PHP-Werte von 0 bedeuten „unbegrenzt“.
     */
    private function uploadLimits() {
        $plugin  = (int) ($this->getValue('maxUploadMB') ?: 512) * 1024 * 1024;
        $file    = $this->parseBytes((string) ini_get('upload_max_filesize'));
        $post    = $this->parseBytes((string) ini_get('post_max_size'));
        $perFile = $plugin;
        foreach ([$file, $post] as $serverLimit) {
            if ($serverLimit > 0) {
                $perFile = min($perFile, $serverLimit);
            }
        }
        return [
            'file'    => $perFile,
            'post'    => max(0, $post),
            'plugin'  => $plugin,
            'server'  => $perFile < $plugin,
            'enabled' => (bool) ini_get('file_uploads'),
        ];
    }

    // ── Cover-Upload und Bildprüfung (Roadmap 022)

    /** Apple: quadratisch, Kantenlänge in Pixeln. */
    private const COVER_MIN_PX = 1400;
    private const COVER_MAX_PX = 3000;

    /**
     * Lädt ein Kanal-Cover hoch (nur Admins, wie die Kanaleinstellungen). Es
     * wird vor dem Speichern geprüft; ein Bild, das Apple ablehnen würde, wird
     * nicht gespeichert. Liefert die URL für das Feld „Cover-URL“.
     */
    private function handleCoverUpload(array $files) {
        if (!checkRole(['admin'], false)) {
            return ['error' => 'Das Cover dürfen nur Admins ändern.'];
        }
        $error = $files['cover_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!is_int($error)) {
            return ['error' => 'Ungültiger Bild-Upload'];
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['error' => 'Bitte ein Bild auswählen.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['error' => $this->uploadErrorMessage($error, 'Bild')];
        }
        $this->validateUpload($files['cover_file'], 'image');
        $problems = $this->coverProblems($files['cover_file']['tmp_name']);
        if ($problems) {
            return ['error' => 'Das Bild eignet sich nicht als Cover für Apple Podcasts: ' . implode(' ', $problems)
                . ' Verlangt: quadratisch, ' . self::COVER_MIN_PX . ' bis ' . self::COVER_MAX_PX . ' px, JPG oder PNG in RGB.'];
        }
        $url = $this->handleUpload('cover_file', 'image');
        if ($url === '') {
            return ['error' => 'Upload fehlgeschlagen.'];
        }
        return ['success' => true, 'url' => $url];
    }

    /** Abweichungen eines Bildes von Apples Cover-Vorgaben als Sätze; leer = passt. */
    private function coverProblems($path) {
        $info = is_string($path) && is_file($path) ? @getimagesize($path) : false;
        if (!$info) {
            return ['Die Bildgröße ließ sich nicht lesen.'];
        }
        [$width, $height] = $info;
        $size     = $width . ' × ' . $height . ' px';
        $problems = [];
        if ($width !== $height) {
            $problems[] = 'Es ist nicht quadratisch (' . $size . ').';
        } elseif ($width < self::COVER_MIN_PX) {
            $problems[] = 'Es ist zu klein (' . $size . ').';
        } elseif ($width > self::COVER_MAX_PX) {
            $problems[] = 'Es ist zu groß (' . $size . ').';
        }
        if ($info[2] === IMAGETYPE_JPEG && ($info['channels'] ?? 3) === 4) {
            $problems[] = 'Es ist im CMYK-Farbraum gespeichert, Apple verlangt RGB.';
        }
        return $problems;
    }

    /** Pfad eines hochgeladenen Bildes dieser Website anhand seiner URL, sonst ''. */
    private function localImagePath($url) {
        $prefix = DOMAIN_UPLOADS . 'podcast/images/';
        if (!is_string($url) || strpos($url, $prefix) !== 0) {
            return '';
        }
        $name = substr($url, strlen($prefix));
        if (preg_match('/^[a-z0-9_-]+\.(?:jpe?g|png)$/D', $name) !== 1) {
            return '';
        }
        $dir  = realpath(PATH_UPLOADS . 'podcast' . DS . 'images');
        $file = realpath(PATH_UPLOADS . 'podcast' . DS . 'images' . DS . $name);
        if ($dir === false || $file === false || strpos($file, $dir . DS) !== 0 || !is_file($file)) {
            return '';
        }
        return $file;
    }

    /**
     * Bytes als MB mit höchstens einer Nachkommastelle, z. B. „2“ oder „0,5“.
     */
    private function formatMB($bytes) {
        $mb = number_format($bytes / 1048576, 1, ',', '');
        return substr($mb, -2) === ',0' ? substr($mb, 0, -2) : $mb;
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
    /**
     * Episode = Seite mit dem Tag „podcast“, auch neben weiteren Tags.
     * tags(true) liefert tagKey => Name; der Schlüssel ist klein geschrieben
     * (Text::cleanUrl), „Podcast“ zählt also mit.
     */
    private function pageHasPodcastTag($page) {
        $tags = $page->tags(true);
        return is_array($tags) && array_key_exists('podcast', $tags);
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
     * Datum aus dem Feld datetime-local (JJJJ-MM-TTTHH:MM, optional mit
     * Sekunden) im Bludit-Format DB_DATE_FORMAT; Bludit liest es in der
     * Zeitzone der Website. Null bei leerem oder ungültigem Wert (z. B. 31.02.)
     * statt stillschweigend 1970 oder „jetzt“. Geprüft wird in UTC, damit eine
     * Uhrzeit in der Lücke der Sommerzeitumstellung nicht verschoben wird.
     */
    private function episodeDate($value) {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/D', $value) !== 1) {
            return null;
        }
        $format = strlen($value) === 16 ? 'Y-m-d\TH:i' : 'Y-m-d\TH:i:s';
        $parsed = DateTime::createFromFormat('!' . $format, $value, new DateTimeZone('UTC'));
        if ($parsed === false || $parsed->format($format) !== $value) {
            return null;
        }
        return $parsed->format(DB_DATE_FORMAT);
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
     * Apps für den Abo-Button, die den Podcast allein über die Feed-Adresse
     * abonnieren (IDs aus player/5.13.0/button/list.js). Ohne Liste bleibt das
     * Fenster „Abonnieren bei“ leer; der Player zeigt davon nur die Apps für
     * die Plattform des Besuchers. Dienste, deren Link die ID des Podcasts beim
     * Dienst braucht, stehen in SUBSCRIBE_SERVICES — der Player zieht ihren
     * Web-Link der App vor und würde ohne ID ins Leere verlinken. Nicht
     * enthalten: eingestellte Apps (Google Podcasts, Instacast, BeyondPod,
     * Podscout).
     */
    private const SUBSCRIBE_APPS = [
        'antenna-pod', 'podcast-addict', 'podcast-republic', 'procast',
        'overcast', 'downcast', 'i-catcher', 'podcat', 'rss-radio',
        'gpodder', 'clementine',
    ];

    /**
     * Dienste für den Abo-Button, die die ID des Podcasts beim Dienst brauchen:
     * Einstellung => [Podlove-Client-ID, Bezeichnung, Muster des öffentlichen
     * Links (Gruppe 1 = ID für den Player), Beispiel]. Gespeichert wird der
     * Link, wie ihn die App beim Teilen liefert; an den Player geht nur die ID
     * (Ziffern, Buchstaben, Bindestrich). Der Player baut daraus wieder einen
     * https-Link zum Dienst.
     */
    private const SUBSCRIBE_SERVICES = [
        'linkApple' => ['apple-podcasts', 'Apple Podcasts',
            '~^https://(?:podcasts|itunes)\.apple\.com/(?:[a-z]{2}/)?podcast/(?:[^/?#]+/)?(id\d+)/?(?:[?#].*)?$~Di',
            'https://podcasts.apple.com/de/podcast/mein-podcast/id1234567890'],
        'linkSpotify' => ['spotify', 'Spotify',
            '~^https://open\.spotify\.com/(?:intl-[a-z-]+/)?show/([A-Za-z0-9]{22})/?(?:[?#].*)?$~Di',
            'https://open.spotify.com/show/0123456789abcdefABCDEF'],
        'linkPocketCasts' => ['pocket-casts', 'Pocket Casts',
            '~^https://pca\.st/((?:itunes/\d+)|(?:podcast/[0-9a-f-]{36})|[A-Za-z0-9_-]+)/?(?:[?#].*)?$~Di',
            'https://pca.st/abcd1234'],
        'linkDeezer' => ['deezer', 'Deezer',
            '~^https://(?:www\.)?deezer\.com/(?:[a-z]{2}/)?show/(\d+)/?(?:[?#].*)?$~Di',
            'https://www.deezer.com/de/show/1234567'],
        'linkCastbox' => ['castbox', 'Castbox',
            '~^https://castbox\.fm/(?:channel|ch)/(?:[^/?#]*-)?(id\d+)/?(?:[?#].*)?$~Di',
            'https://castbox.fm/channel/Mein-Podcast-id1234567'],
    ];

    /** ID des Podcasts beim Dienst aus dem eingetragenen Link, sonst ''. */
    private function serviceId($field) {
        if (!isset(self::SUBSCRIBE_SERVICES[$field])) {
            return '';
        }
        $link = trim($this->setting($field));
        return $link !== '' && preg_match(self::SUBSCRIBE_SERVICES[$field][2], $link, $m) === 1 ? $m[1] : '';
    }

    /**
     * Einträge für den Abo-Button: Dienste mit eingetragenem Link zuerst, dann
     * die Apps (SUBSCRIBE_APPS), Player.fm und RSS. Der Player zeigt nur die
     * zur Plattform des Besuchers passenden.
     */
    private function subscribeClients($feedUrl) {
        $ids = [];
        foreach (self::SUBSCRIBE_SERVICES as $field => $service) {
            $ids[$service[0]] = $this->serviceId($field);
        }
        $appleId = $ids['apple-podcasts'];
        // Ohne eigenen Link verbindet Pocket Casts über die Apple-ID (pca.st/itunes/…)
        if ($ids['pocket-casts'] === '' && $appleId !== '') {
            $ids['pocket-casts'] = 'itunes/' . substr($appleId, 2);
        }

        $clients = [];
        foreach ($ids as $client => $id) {
            if ($id === '') {
                continue;
            }
            $clients[] = ['id' => $client, 'service' => $id];
            // Castro (iPhone) verlinkt ebenfalls über die Apple-ID
            if ($client === 'pocket-casts' && $appleId !== '') {
                $clients[] = ['id' => 'castro', 'service' => $appleId];
            }
        }
        foreach (self::SUBSCRIBE_APPS as $id) {
            $clients[] = ['id' => $id];
        }
        // Web-Varianten, deren Link aus „service“ entsteht: hier die Feed-Adresse
        $clients[] = ['id' => 'player-fm', 'service' => $feedUrl];
        $clients[] = ['id' => 'rss',       'service' => $feedUrl];
        return $clients;
    }

    /**
     * Gewählter Player; unbekannte Werte fallen auf den lokalen Podlove-Player zurück.
     */
    private function playerMode() {
        return $this->getValue('playerMode') === 'html5' ? 'html5' : 'podlove';
    }

    /** Position des Players im Inhalt; unbekannte Werte → am Anfang. */
    private function playerPosition() {
        return $this->getValue('playerPosition') === 'bottom' ? 'bottom' : 'top';
    }

    // ── Shownotes im Feed (Roadmap 016)

    /** Elemente, die in <content:encoded> bleiben (Apple: p, ol, ul, li, a; dazu Hervorhebungen). */
    private const SHOWNOTES_TAGS = ['p', 'br', 'a', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre'];

    /** Elemente, die samt Inhalt entfallen. */
    private const SHOWNOTES_DROP = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math',
        'noscript', 'form', 'button', 'select', 'textarea', 'input', 'audio', 'video', 'canvas', 'head', 'title'];

    /**
     * Shownotes für <content:encoded>: das HTML der Episode, reduziert auf
     * SHOWNOTES_TAGS ohne Attribute; Links behalten nur href mit http(s) oder
     * mailto (Pfade wie „/ueber“ werden zur vollen Adresse). Andere Elemente
     * werden durch ihren Inhalt ersetzt, SHOWNOTES_DROP entfällt ganz.
     */
    private function shownotesHtml($html) {
        $html = trim((string) $html);
        if ($html === '' || !class_exists('DOMDocument')) {
            return '';
        }
        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok   = $doc->loadHTML('<?xml encoding="UTF-8"?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $ok ? $doc->getElementsByTagName('div')->item(0) : null;
        if (!$root) {
            return '';
        }

        $clean = function (DOMNode $node) use (&$clean) {
            for ($child = $node->firstChild; $child !== null; $child = $next) {
                $next = $child->nextSibling;
                if ($child instanceof DOMText) {
                    continue;
                }
                if (!($child instanceof DOMElement)) {
                    $node->removeChild($child);  // Kommentare, Verarbeitungsanweisungen
                    continue;
                }
                $tag = strtolower($child->nodeName);
                if (in_array($tag, self::SHOWNOTES_DROP, true)) {
                    $node->removeChild($child);
                    continue;
                }
                $clean($child);
                $href = $tag === 'a' ? $this->shownotesHref($child->getAttribute('href')) : '';
                // Nicht erlaubte Elemente und Links ohne gültiges Ziel durch ihren Inhalt ersetzen
                if (!in_array($tag, self::SHOWNOTES_TAGS, true) || ($tag === 'a' && $href === '')) {
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $child->removeAttribute($attribute->nodeName);
                }
                if ($href !== '') {
                    $child->setAttribute('href', $href);
                }
            }
        };
        $clean($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    /** Link-Ziel für die Shownotes: http(s) und mailto; Pfade ab „/“ mit Website-Adresse; sonst ''. */
    private function shownotesHref($href) {
        $href = trim((string) $href);
        if ($href === '') {
            return '';
        }
        if (strpos($href, '/') === 0 && strpos($href, '//') !== 0) {
            $base = parse_url(DOMAIN_BASE);
            if (!empty($base['scheme']) && !empty($base['host'])) {
                $href = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '') . $href;
            }
        }
        if ($this->isHttpUrl($href)) {
            return $href;
        }
        return preg_match('/^mailto:[^\s<>"]+$/iD', $href) === 1 ? $href : '';
    }

    /**
     * Text der Episode für <description>: ohne Tags, Leerraum zusammengefasst,
     * höchstens $max Zeichen (Apple-Grenze 4000). Entities bleiben erhalten —
     * Apple und viele Apps lesen <description> als HTML.
     */
    private function episodeTextSummary($html, $max = 4000) {
        // Skripte und Stile samt Inhalt entfernen, Blockgrenzen als Leerzeichen,
        // damit Absätze nicht zusammenkleben
        $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1\s*>~is', ' ', (string) $html);
        $html = preg_replace('~</?(?:p|br|li|ul|ol|h[1-6]|div|blockquote|pre|tr|td|th)\b[^>]*>~i', ' ', (string) $html);
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $html)));
        if (mb_strlen($text, 'UTF-8') > $max) {
            // Am Wortende kürzen, damit keine Entity zerschnitten wird
            $cut  = mb_substr($text, 0, $max - 1, 'UTF-8');
            $text = rtrim((string) preg_replace('/\s+\S*$/u', '', $cut)) . '…';
        }
        return $text;
    }

    /** HTML als CDATA-Abschnitt: ungültiges UTF-8 und in XML unzulässige Zeichen entfernt, „]]>“ geteilt. */
    private function cdata($html) {
        $html = mb_scrub((string) $html, 'UTF-8');
        $html = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $html);
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $html) . ']]>';
    }

    // ── Dateigröße externer Audio-URLs (Roadmap 016, SSRF-Schutz nach Regel 8)

    /** Weiterleitungen, denen gefolgt wird (jede wird neu geprüft). */
    private const REMOTE_MAX_REDIRECTS = 3;

    /** Gesamtzeit für die Abfrage in Sekunden. */
    private const REMOTE_TIMEOUT = 10;

    /** Größe einer hochgeladenen Audiodatei dieser Website anhand ihrer URL, sonst 0. */
    private function localAudioLength($url) {
        $prefix = DOMAIN_UPLOADS . 'podcast/audio/';
        if (!is_string($url) || strpos($url, $prefix) !== 0) {
            return 0;
        }
        $name = substr($url, strlen($prefix));
        if (preg_match('/^[a-z0-9_-]+\.(?:mp3|m4a|ogg|wav)$/D', $name) !== 1) {
            return 0;
        }
        $dir  = realpath(PATH_UPLOADS . 'podcast' . DS . 'audio');
        $file = realpath(PATH_UPLOADS . 'podcast' . DS . 'audio' . DS . $name);
        if ($dir === false || $file === false || strpos($file, $dir . DS) !== 0 || !is_file($file)) {
            return 0;
        }
        return (int) filesize($file);
    }

    /**
     * Dateigröße einer externen Audio-URL: HEAD (Content-Length), ersatzweise
     * GET mit „Range: bytes=0-0“ (Content-Range). Liefert ['length' => int]
     * oder ['error' => Text zum Anzeigen]. Schutz vor Anfragen ins interne
     * Netz: nur http/https auf Port 80/443, alle Ziel-IPs nach DNS-Auflösung
     * öffentlich, Verbindung an die geprüfte IP gebunden, Weiterleitungen
     * einzeln geprüft, kein Proxy aus der Umgebung, kurze Zeitgrenze, vom
     * Inhalt höchstens 1 KB gelesen.
     */
    private function remoteAudioLength($url) {
        if (!function_exists('curl_init')) {
            return ['error' => 'Die PHP-Erweiterung curl fehlt auf dem Server.'];
        }
        $deadline = microtime(true) + self::REMOTE_TIMEOUT;
        for ($hop = 0; $hop <= self::REMOTE_MAX_REDIRECTS; $hop++) {
            $target = $this->publicTarget($url);
            if (isset($target['error'])) {
                return $target;
            }
            $head = $this->httpProbe($url, $target, false, $deadline);
            if (isset($head['error'])) {
                return $head;
            }
            if ($head['location'] !== null) {
                $url = $head['location'];
                continue;
            }
            if ($head['length'] > 0) {
                return ['length' => $head['length']];
            }
            // HEAD ohne Länge oder nicht erlaubt: ein Byte anfordern
            $range = $this->httpProbe($url, $target, true, $deadline);
            if (isset($range['error'])) {
                return $range;
            }
            if ($range['location'] !== null) {
                $url = $range['location'];
                continue;
            }
            if ($range['length'] > 0) {
                return ['length' => $range['length']];
            }
            $status = $range['status'] ?: $head['status'];
            return ['error' => $status >= 400
                ? 'Der Server antwortet mit HTTP ' . $status . '.'
                : 'Der Server nennt keine Dateigröße.'];
        }
        return ['error' => 'Zu viele Weiterleitungen.'];
    }

    /**
     * Prüft eine Adresse vor der Abfrage: Schema, Port, Servername und alle
     * aufgelösten IPs. Liefert ['host', 'port', 'ip'] oder ['error' => Text].
     */
    private function publicTarget($url) {
        if (!is_string($url) || !$this->isHttpUrl($url)) {
            return ['error' => 'Die Adresse muss mit http:// oder https:// beginnen.'];
        }
        $parts  = parse_url($url);
        $scheme = strtolower($parts['scheme']);
        $host   = strtolower(rtrim($parts['host'], '.'));
        $port   = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (isset($parts['user']) || isset($parts['pass'])) {
            return ['error' => 'Adressen mit Zugangsdaten werden nicht abgefragt.'];
        }
        if (!in_array($port, [80, 443], true)) {
            return ['error' => 'Abgefragt werden nur die Standard-Ports 80 und 443.'];
        }
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP) !== false) {
            $ips = [$literal];
        } else {
            // Nur echte Servernamen (Endung mit Buchstaben); Zahlenformen wie
            // „2130706433“ oder „0x7f.1“ legen Programme unterschiedlich aus
            if (preg_match('/^[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z][a-z0-9-]*$/D', $host) !== 1) {
                return ['error' => 'Der Servername ist ungültig.'];
            }
            $ips     = [];
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ip'])) {
                        $ips[] = $record['ip'];
                    }
                    if (isset($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
            if (!$ips) {
                $ips = gethostbynamel($host) ?: [];
            }
        }
        if (!$ips) {
            return ['error' => 'Der Servername lässt sich nicht auflösen.'];
        }
        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                return ['error' => 'Die Adresse zeigt auf ein internes Netz und wird nicht abgefragt.'];
            }
        }
        // IPv4 bevorzugen, wenn vorhanden
        $ipv4 = array_values(array_filter($ips, function ($ip) { return strpos($ip, ':') === false; }));
        return ['host' => $host, 'port' => $port, 'ip' => $ipv4 ? $ipv4[0] : $ips[0]];
    }

    /** Öffentliche Unicast-Adresse? Private, Loopback-, Link-Local- und Sonderbereiche: nein. */
    private function isPublicIp($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (defined('FILTER_FLAG_GLOBAL_RANGE')) {
            $flags |= FILTER_FLAG_GLOBAL_RANGE;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
            return false;
        }
        // Ergänzend, da ältere PHP-Versionen nicht alle Bereiche kennen
        $blocked = ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
            '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24',
            '224.0.0.0/4', '240.0.0.0/4', '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48',
            '100::/64', '2001::/23', '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8'];
        foreach ($blocked as $cidr) {
            if ($this->ipInRange($ip, $cidr)) {
                return false;
            }
        }
        return true;
    }

    /** Liegt die IP im Bereich (CIDR, IPv4 oder IPv6)? */
    private function ipInRange($ip, $cidr) {
        [$net, $bits] = explode('/', $cidr);
        $ipBin  = @inet_pton($ip);
        $netBin = @inet_pton($net);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }
        $bits  = (int) $bits;
        $bytes = intdiv($bits, 8);
        $rest  = $bits % 8;
        if (substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            return false;
        }
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }

    /**
     * Eine Anfrage an die geprüfte IP: HEAD oder GET mit „Range: bytes=0-0“.
     * Liefert ['status', 'length', 'location'] oder ['error' => Text].
     */
    private function httpProbe($url, array $target, $range, $deadline) {
        $timeout = (int) ceil($deadline - microtime(true));
        if ($timeout < 1) {
            return ['error' => 'Zeitüberschreitung.'];
        }
        $headers  = [];
        $received = 0;
        $options  = [
            CURLOPT_NOBODY         => !$range,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_PROXY          => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'Bludit-Podcast-Plugin',
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
                if (count($headers) < 100) {
                    $headers[] = $line;
                }
                return strlen($line);
            },
            // Inhalt nicht speichern; nach 1 KB abbrechen
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) use (&$received) {
                $received += strlen($data);
                return $received > 1024 ? 0 : strlen($data);
            },
        ];
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
        } else {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if (filter_var(trim($target['host'], '[]'), FILTER_VALIDATE_IP) === false) {
            // Verbindung an die geprüfte IP binden (kein zweites, anderes DNS-Ergebnis)
            $ip = strpos($target['ip'], ':') !== false ? '[' . $target['ip'] . ']' : $target['ip'];
            $options[CURLOPT_RESOLVE] = [$target['host'] . ':' . $target['port'] . ':' . $ip];
        }
        if ($range) {
            $options[CURLOPT_HTTPHEADER] = ['Range: bytes=0-0'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        curl_exec($ch);
        $errno  = curl_errno($ch);
        $error  = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        // Abbruch nach 1 KB (CURLE_WRITE_ERROR) ist gewollt, wenn eine Antwort da ist
        if ($errno !== 0 && !($errno === 23 && $status > 0)) {
            Log::set(__METHOD__ . LOG_SEP . 'curl ' . $errno . ': ' . $error);
            return ['error' => 'Der Server ist nicht erreichbar (Verbindungsfehler oder Zeitüberschreitung).'];
        }

        $contentLength = null;
        $contentRange  = null;
        $location      = null;
        foreach ($headers as $line) {
            if (stripos($line, 'HTTP/') === 0) {
                $contentLength = $contentRange = $location = null;  // neuer Antwortblock (z. B. nach 100 Continue)
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name  = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            if ($name === 'content-length') {
                $contentLength = $value;
            } elseif ($name === 'content-range') {
                $contentRange = $value;
            } elseif ($name === 'location') {
                $location = $value;
            }
        }

        $toInt  = function ($v) {
            $n = is_string($v) ? filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
            return $n === false ? 0 : $n;
        };
        $length = 0;
        if ($range && $status === 206 && is_string($contentRange)
            && preg_match('~^bytes\s+\d+-\d+/(\d+)$~iD', $contentRange, $m) === 1) {
            $length = $toInt($m[1]);
        } elseif ($status >= 200 && $status < 300 && $status !== 206) {
            $length = $toInt($contentLength);
        }
        $next = null;
        if ($status >= 300 && $status < 400 && is_string($location) && $location !== '') {
            $next = $this->resolveLocation($url, $location);
        }
        return ['status' => $status, 'length' => $length, 'location' => $next];
    }

    /** Ziel einer Weiterleitung als absolute URL (wird danach erneut geprüft). */
    private function resolveLocation($base, $location) {
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $location) === 1) {
            return $location;
        }
        $parts  = parse_url($base);
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (strpos($location, '//') === 0) {
            return $parts['scheme'] . ':' . $location;
        }
        if (strpos($location, '/') === 0) {
            return $origin . $location;
        }
        $dir = isset($parts['path']) ? (string) preg_replace('~[^/]*$~', '', $parts['path']) : '/';
        return $origin . ($dir !== '' ? $dir : '/') . $location;
    }

    // ── Apple-Kanalangaben (Roadmap 016)

    /**
     * Apple-Podcasts-Kategorien: englischer Name (so im Feed) => [deutscher
     * Name, [Unterkategorie englisch => deutsch]]. Namen und Reihenfolge wie auf
     * Apples Seite „Apple Podcasts-Kategorien“ (podcasters.apple.com/support/1691,
     * Stand 2026), deutsche Namen wie in Apple Podcasts.
     */
    private const APPLE_CATEGORIES = [
        'Arts' => ['Kunst', ['Books' => 'Bücher', 'Design' => 'Design', 'Fashion & Beauty' => 'Mode und Schönheit',
            'Food' => 'Essen', 'Performing Arts' => 'Darstellende Kunst', 'Visual Arts' => 'Bildende Kunst']],
        'Business' => ['Wirtschaft', ['Careers' => 'Karriere', 'Entrepreneurship' => 'Firmengründung',
            'Investing' => 'Geldanlage', 'Management' => 'Management', 'Marketing' => 'Marketing',
            'Non-Profit' => 'Gemeinnützig']],
        'Comedy' => ['Comedy', ['Comedy Interviews' => 'Comedy-Interviews', 'Improv' => 'Impro-Comedy',
            'Stand-Up' => 'Stand-Up-Comedy']],
        'Education' => ['Bildung', ['Courses' => 'Kurse', 'How To' => 'So geht’s',
            'Language Learning' => 'Sprachen lernen', 'Self-Improvement' => 'Selbstverwirklichung']],
        'Fiction' => ['Fiktion', ['Comedy Fiction' => 'Comedy-Fiction', 'Drama' => 'Drama',
            'Science Fiction' => 'Science-Fiction']],
        'Government' => ['Regierung', []],
        'History' => ['Geschichte', []],
        'Health & Fitness' => ['Gesundheit und Fitness', ['Alternative Health' => 'Alternative Therapien',
            'Fitness' => 'Fitness', 'Medicine' => 'Medizin', 'Mental Health' => 'Mentale Gesundheit',
            'Nutrition' => 'Ernährung', 'Sexuality' => 'Sexualität']],
        'Kids & Family' => ['Kinder und Familie', ['Education for Kids' => 'Bildung für Kinder',
            'Parenting' => 'Kindererziehung', 'Pets & Animals' => 'Haus- und Wildtiere',
            'Stories for Kids' => 'Geschichten für Kinder']],
        'Leisure' => ['Freizeit', ['Animation & Manga' => 'Animation und Manga', 'Automotive' => 'Rund ums Auto',
            'Aviation' => 'Luftfahrt', 'Crafts' => 'Basteln', 'Games' => 'Spiele', 'Hobbies' => 'Hobbys',
            'Home & Garden' => 'Heim und Garten', 'Video Games' => 'Videospiele']],
        'Music' => ['Musik', ['Music Commentary' => 'Musikrezensionen', 'Music History' => 'Musikgeschichte',
            'Music Interviews' => 'Musikinterviews']],
        'News' => ['Nachrichten', ['Business News' => 'Wirtschaftsnachrichten', 'Daily News' => 'Nachrichten des Tages',
            'Entertainment News' => 'Neues aus der Unterhaltung', 'News Commentary' => 'Kommentare',
            'Politics' => 'Politik', 'Sports News' => 'Sportnews', 'Tech News' => 'Neues aus der Technik']],
        'Religion & Spirituality' => ['Religion und Spiritualität', ['Buddhism' => 'Buddhismus',
            'Christianity' => 'Christentum', 'Hinduism' => 'Hinduismus', 'Islam' => 'Islam', 'Judaism' => 'Judentum',
            'Religion' => 'Religion', 'Spirituality' => 'Spiritualität']],
        'Science' => ['Wissenschaft', ['Astronomy' => 'Astronomie', 'Chemistry' => 'Chemie',
            'Earth Sciences' => 'Geowissenschaften', 'Life Sciences' => 'Biowissenschaften',
            'Mathematics' => 'Mathematik', 'Natural Sciences' => 'Naturwissenschaften', 'Nature' => 'Natur',
            'Physics' => 'Physik', 'Social Sciences' => 'Sozialwissenschaften']],
        'Society & Culture' => ['Gesellschaft und Kultur', ['Documentary' => 'Dokumentation',
            'Personal Journals' => 'Tagebücher', 'Philosophy' => 'Philosophie',
            'Places & Travel' => 'Reisen und Orte', 'Relationships' => 'Beziehungen']],
        'Sports' => ['Sport', ['Baseball' => 'Baseball', 'Basketball' => 'Basketball', 'Cricket' => 'Cricket',
            'Fantasy Sports' => 'Fantasy Sport', 'Football' => 'American Football', 'Golf' => 'Golf',
            'Hockey' => 'Eishockey', 'Rugby' => 'Rugby', 'Running' => 'Laufen', 'Soccer' => 'Fußball',
            'Swimming' => 'Schwimmen', 'Tennis' => 'Tennis', 'Volleyball' => 'Volleyball',
            'Wilderness' => 'Abenteuer Natur', 'Wrestling' => 'Wrestling']],
        'Technology' => ['Technologie', []],
        'True Crime' => ['Wahre Kriminalfälle', []],
        'TV & Film' => ['TV und Film', ['After Shows' => 'Backstage', 'Film History' => 'Filmgeschichte',
            'Film Interviews' => 'Filminterviews', 'Film Reviews' => 'Filmrezensionen', 'TV Reviews' => 'TV-Rezensionen']],
    ];

    /**
     * Gewählte Apple-Kategorie als [Kategorie, Unterkategorie] mit den englischen
     * Namen aus APPLE_CATEGORIES (Unterkategorie '' = keine), null ohne gültige
     * Auswahl. Gespeichert als „Kategorie > Unterkategorie“; ein früherer
     * Freitext gilt, wenn er so einer Kategorie entspricht (Groß-/Kleinschreibung egal).
     */
    private function appleCategory() {
        $value = trim($this->setting('category'));
        if ($value === '') {
            return null;
        }
        $parts = array_map('trim', explode('>', $value, 2));
        foreach (self::APPLE_CATEGORIES as $category => $info) {
            if (strcasecmp($category, $parts[0]) !== 0) {
                continue;
            }
            if (!isset($parts[1]) || $parts[1] === '') {
                return [$category, ''];
            }
            foreach (array_keys($info[1]) as $sub) {
                if (strcasecmp($sub, $parts[1]) === 0) {
                    return [$category, $sub];
                }
            }
            return null;
        }
        return null;
    }

    /**
     * Auswahlliste der Apple-Kategorien für form(): deutsch angezeigt mit dem
     * englischen Namen, nach deutscher Bezeichnung sortiert, je Kategorie eine
     * Gruppe mit der Kategorie selbst und ihren Unterkategorien.
     */
    private function categorySelect() {
        $esc      = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES); };
        $selected = $this->appleCategory();
        $current  = $selected ? $selected[0] . ($selected[1] !== '' ? ' > ' . $selected[1] : '') : '';
        $option   = function ($value, $label) use ($esc, $current) {
            return '<option value="' . $esc($value) . '"' . ($value === $current ? ' selected' : '') . '>'
                . $esc($label) . '</option>';
        };
        $byLabel  = function ($a, $b) { return strnatcasecmp($a, $b); };
        // Englischen Namen nur zeigen, wenn er sich vom deutschen unterscheidet
        $name     = function ($de, $en) { return $de === $en ? $de : $de . ' (' . $en . ')'; };

        $categories = [];
        foreach (self::APPLE_CATEGORIES as $category => $info) {
            $categories[$category] = $info[0];
        }
        uasort($categories, $byLabel);

        $html = '<div><label for="podcast-category">Kategorie</label>'
            . '<select id="podcast-category" name="category">'
            . $option('', 'Bitte wählen …');
        foreach ($categories as $category => $label) {
            $html .= '<optgroup label="' . $esc($name($label, $category)) . '">'
                . $option($category, $name($label, $category));
            $subs = self::APPLE_CATEGORIES[$category][1];
            uasort($subs, $byLabel);
            foreach ($subs as $sub => $subLabel) {
                $html .= $option($category . ' > ' . $sub, $label . ' → ' . $name($subLabel, $sub));
            }
            $html .= '</optgroup>';
        }
        $html .= '</select><span class="tip">Apple wertet die Kategorie für Suche und Charts aus; im Feed steht der englische Name.';
        $stored = trim($this->setting('category'));
        if ($stored !== '' && $selected === null) {
            $html .= ' <strong>Bisheriger Eintrag „' . $esc($stored) . '“ ist keine Apple-Kategorie – bitte eine auswählen.'
                . ' Bis dahin steht keine Kategorie im Feed.</strong>';
        }
        return $html . '</span></div>';
    }

    /** Art des Podcasts für <itunes:type>: episodic (Standard) oder serial. */
    private function showType() {
        return $this->getValue('showType') === 'serial' ? 'serial' : 'episodic';
    }

    /** Cover-Adresse, wenn sie mit http(s):// beginnt, sonst ''. */
    private function coverUrl() {
        $url = trim($this->setting('cover'));
        return $url !== '' && $this->isHttpUrl($url) ? $url : '';
    }

    // ── Podcasting 2.0 (Roadmap 019)

    /** Namensraum für podcast:guid laut Podcasting-2.0-Standard (UUIDv5). */
    private const PODCAST_GUID_NAMESPACE = 'ead4c236-bf58-58c6-a2c6-a6b28d128cb6';

    /**
     * podcast:guid: die gespeicherte Kennung, sonst (vor dem ersten Speichern)
     * die aus der aktuellen Feed-Adresse berechnete. UUIDv5 aus der Adresse
     * ohne Schema und ohne Schrägstriche am Ende.
     */
    private function podcastGuid() {
        $stored = $this->setting('podcastGuid');
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $stored) === 1) {
            return $stored;
        }
        $name = rtrim((string) preg_replace('~^[a-z][a-z0-9+.-]*://~i', '', $this->feedUrl()), '/');
        return $this->uuidV5(self::PODCAST_GUID_NAMESPACE, $name);
    }

    /** Legt die Podcast-GUID einmalig fest, damit sie sich mit der Feed-Adresse nicht mehr ändert. */
    private function ensurePodcastGuid() {
        if ($this->setting('podcastGuid') === '') {
            $this->setField('podcastGuid', $this->podcastGuid());
        }
    }

    /** UUID Version 5 (SHA-1, RFC 4122) aus Namensraum-UUID und Name. */
    private function uuidV5($namespace, $name) {
        $hash = sha1(hex2bin(str_replace('-', '', $namespace)) . $name);
        return sprintf('%s-%s-%04x-%04x-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12));
    }

    /** Feed gegen Übernahme gesperrt (podcast:locked yes)? Standard: nein. */
    private function feedLocked() {
        return $this->getValue('feedLocked') === 'yes';
    }

    // ── Feed- und Website-Adresse (Roadmap 017)

    /** Feed-Dateiname aus den Einstellungen, ohne führenden Schrägstrich. */
    private function feedSlug() {
        $slug = ltrim(trim($this->setting('feedSlug')), '/');
        return $slug !== '' ? $slug : 'podcast.xml';
    }

    /**
     * Feed-Adresse auf dieser Website: DOMAIN_BASE (auch bei Bludit im
     * Unterordner) und der Dateiname, jeder Pfadteil URL-kodiert — webhook()
     * vergleicht mit dem dekodierten Pfad.
     */
    private function localFeedUrl() {
        return DOMAIN_BASE . implode('/', array_map('rawurlencode', explode('/', $this->feedSlug())));
    }

    /**
     * Öffentliche Feed-Adresse für Abo-Button, Discovery-Link und
     * <atom:link rel="self">: die abweichende Feed-URL (z. B. CDN), wenn sie
     * mit http(s):// beginnt, sonst die Adresse auf dieser Website.
     */
    private function feedUrl() {
        $url = trim($this->setting('feedUrl'));
        return $url !== '' && $this->isHttpUrl($url) ? $url : $this->localFeedUrl();
    }

    /** Website des Podcasts: Einstellung, wenn sie mit http(s):// beginnt, sonst die Bludit-Website. */
    private function siteUrl() {
        $url = trim($this->setting('siteUrl'));
        return $url !== '' && $this->isHttpUrl($url) ? $url : DOMAIN_BASE;
    }

    // ── Escaping: Bludit speichert Texte HTML-escaped (Sanitize::html). Gelesen
    // wird dekodiert, ausgegeben genau einmal passend zum Ziel (Regel 4).

    /** Text-Einstellung als Klartext (Plugin::post() hat sie escaped gespeichert). */
    private function setting($field) {
        $value = $this->getValue($field, false);
        return is_string($value) ? $value : '';
    }

    /** Seitentitel als Klartext (Pages::add()/edit() haben ihn escaped gespeichert). */
    private function plainTitle($page) {
        $title = $page->title();
        return is_string($title) ? Sanitize::htmlDecode($title) : '';
    }

    /**
     * Klartext für XML-Inhalt und -Attribute, genau einmal escaped. Ungültiges
     * UTF-8 und in XML 1.0 unzulässige Zeichen werden ersetzt, damit ein
     * einzelner Wert nicht den ganzen Feed unlesbar macht.
     */
    private function xml($value) {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED, 'UTF-8');
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
        return $this->normalizeMeta($this->getStoredEpisodeMeta($page));
    }

    /**
     * Gespeicherte Werte dekodiert, aber ungeprüft — nur zum Vorbelegen des
     * Bearbeiten-Formulars, damit ungültige Werte aus dem Bludit-Editor sichtbar
     * bleiben und beim Speichern gemeldet werden. Für Feed/Player getEpisodeMeta().
     */
    private function getStoredEpisodeMeta($page) {
        $stored = [];
        foreach (self::CUSTOM_FIELDS as $metaKey => $field) {
            // custom() liefert false für nicht gesetzte Felder; bool nur bei Explicit
            $value = $page->custom($field);
            if ($metaKey === 'itunes_explicit' && is_bool($value)) {
                $value = $value ? 'yes' : 'no';
            }
            $stored[$metaKey] = is_string($value) ? Sanitize::htmlDecode($value) : '';
        }
        return $stored;
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
