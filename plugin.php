<?php

// Podcast Plugin Grundgerüst für Bludit.
// Enthält Konfigurationsfelder und eine einfache Settings-Form.
class PodcastPlugin extends Plugin
{
    public function init()
    {
        // Default-Konfiguration
        $this->dbFields = [
            'feedTitle' => 'Mein Podcast',
            'feedDescription' => 'Podcast Beschreibung',
            'author' => '',
            'coverImage' => '',
            'itemsLimit' => 20,
            'episodesDirectory' => 'content/episodes', // relativer Pfad
            'parentPageSlug' => '',        // optional: Elternseite für Episoden-Seiten
            'submissionPageSlug' => ''     // optional: Seite für Frontend-Episodenformular
        ];
    }

    // Einstellungen im Admin-Panel
    public function form()
    {
        $html = '<div class="podcast-ep">';
        $html .= '<div class="podcast-ep-head"><span class="podcast-title"><strong>Allgemeine Einstellungen</strong></span><span class="podcast-toggle">▼</span></div>';
        $html .= '<div class="podcast-ep-body">';
        $html .= '<label for="feedTitle">Feed Titel</label>';
        $html .= '<input id="feedTitle" name="feedTitle" type="text" value="' . $this->xml($this->getValue('feedTitle')) . '">';

        $html .= '<label for="feedDescription">Feed Beschreibung</label>';
        $html .= '<textarea id="feedDescription" name="feedDescription">' . $this->xml($this->getValue('feedDescription')) . '</textarea>';

        $html .= '<label for="author">Autor</label>';
        $html .= '<input id="author" name="author" type="text" value="' . $this->xml($this->getValue('author')) . '">';

        $html .= '<label for="coverImage">Cover-Bild URL</label>';
        $html .= '<input id="coverImage" name="coverImage" type="text" value="' . $this->xml($this->getValue('coverImage')) . '">';

        $html .= '<label for="itemsLimit">Episoden-Limit</label>';
        $html .= '<input id="itemsLimit" name="itemsLimit" type="number" min="1" value="' . $this->xml($this->getValue('itemsLimit')) . '">';

        $html .= '<label for="episodesDirectory">Episoden-Ordner</label>';
        $html .= '<input id="episodesDirectory" name="episodesDirectory" type="text" value="' . $this->xml($this->getValue('episodesDirectory')) . '">';

        $html .= '<label for="parentPageSlug">Elternseite (Slug, optional)</label>';
        $html .= '<input id="parentPageSlug" name="parentPageSlug" type="text" value="' . $this->xml($this->getValue('parentPageSlug')) . '">';

        $html .= '<label for="submissionPageSlug">Frontend-Formular-Seite (Slug, optional)</label>';
        $html .= '<input id="submissionPageSlug" name="submissionPageSlug" type="text" value="' . $this->xml($this->getValue('submissionPageSlug')) . '">';
        $html .= '<small>Slug der Bludit-Seite, auf der eingeloggte Nicht-Admins Episoden anlegen k&ouml;nnen.</small>';
        $html .= '</div>'; // body
        $html .= '</div>'; // wrapper

        // Einfache Admin-UI zum Anlegen einer Episode (legt eine JSON-Datei an)
        $html .= '<hr>';
        $html .= '<div class="podcast-new-ep podcast-ep">';
        $html .= '<div class="podcast-ep-head"><span class="podcast-title"><strong>Neue Episode anlegen</strong></span><span class="podcast-toggle">▼</span></div>';
        $html .= '<div class="podcast-ep-body">';
        $html .= '<p>Erstellt eine JSON-Datei im Episoden-Ordner.</p>';
        $html .= '<div>';
        $html .= '<input type="hidden" name="podcast_new_episode" value="1">';
        $html .= '<label for="epTitle">Titel</label>';
        $html .= '<input id="epTitle" name="epTitle" type="text" value="">';

        $html .= '<label for="epAudio">Audio-URL (mp3)</label>';
        $html .= '<input id="epAudio" name="epAudio" type="text" value="">';

        $html .= '<label for="epDate">Datum (leer = jetzt)</label>';
        $html .= '<input id="epDate" name="epDate" type="text" placeholder="2025-12-08T10:00:00Z">';

        $html .= '<label for="epSummary">Beschreibung</label>';
        $html .= '<textarea id="epSummary" name="epSummary"></textarea>';

        $html .= '<label for="epGuid">GUID (leer = automatisch)</label>';
        $html .= '<input id="epGuid" name="epGuid" type="text" value="">';

        $html .= '<button type="submit" class="btn btn-primary">Episode speichern</button>';
        $html .= '</div>'; // inner form wrapper
        $html .= '</div>'; // body
        $html .= '</div>'; // new ep wrapper

        // Verwaltung bestehender Episoden (bearbeiten / löschen)
        $html .= '<hr>';
        $html .= '<h3>Bestehende Episoden</h3>';
        $episodes = $this->adminEpisodes();
        if (empty($episodes)) {
            $html .= '<p>Keine Episoden gefunden.</p>';
        } else {
            $html .= '<p>Bearbeite Felder und speichere. Zum Löschen Checkbox markieren. Klick auf den Pfeil klappt die Episode auf/zu.</p>';
            $html .= '<style>.podcast-ep{border:1px solid #ccc;padding:10px;margin-bottom:10px;} .podcast-ep-head{display:flex;align-items:center;cursor:pointer;gap:8px;} .podcast-ep-head .podcast-title{flex:1;} .podcast-ep-body{margin-top:8px;display:none;} .podcast-ep.open .podcast-ep-body{display:block;} .podcast-toggle{width:20px;text-align:center;}</style>';
            $html .= '<script>
                document.addEventListener("DOMContentLoaded", function(){
                    document.querySelectorAll(".podcast-ep-head").forEach(function(head){
                        head.addEventListener("click", function(){
                            var box = head.closest(".podcast-ep");
                            box.classList.toggle("open");
                            var icon = head.querySelector(".podcast-toggle");
                            if (icon) { icon.textContent = box.classList.contains("open") ? "▲" : "▼"; }
                        });
                        var icon = head.querySelector(".podcast-toggle");
                        if (icon) { icon.textContent = "▼"; }
                    });
                });
            </script>';
            foreach ($episodes as $ep) {
                $slug = $this->xml($ep['_slug']);
                $html .= '<div class="podcast-ep">';
                $html .= '<div class="podcast-ep-head"><span class="podcast-title"><strong>' . $this->xml($ep['title'] ?? $slug) . '</strong></span><span class="podcast-toggle">▼</span></div>';
                $html .= '<div class="podcast-ep-body">';
                $html .= '<label>Titel</label>';
                $html .= '<input name="epEdit[' . $slug . '][title]" type="text" value="' . $this->xml($ep['title'] ?? '') . '">';

                $html .= '<label>Audio-URL</label>';
                $html .= '<input name="epEdit[' . $slug . '][audioUrl]" type="text" value="' . $this->xml($ep['audioUrl'] ?? '') . '">';

                $html .= '<label>Datum</label>';
                $html .= '<input name="epEdit[' . $slug . '][date]" type="text" value="' . $this->xml($ep['date'] ?? '') . '">';

                $html .= '<label>Beschreibung</label>';
                $html .= '<textarea name="epEdit[' . $slug . '][summary]">' . $this->xml($ep['summary'] ?? '') . '</textarea>';

                $html .= '<label>GUID</label>';
                $html .= '<input name="epEdit[' . $slug . '][guid]" type="text" value="' . $this->xml($ep['guid'] ?? '') . '">';

                $html .= '<div>';
                $html .= '<label><input type="checkbox" name="epDelete[]" value="' . $slug . '"> Löschen</label>';
                $html .= '</div>';

                $html .= '</div>'; // body
                $html .= '</div>'; // ep
            }
            $html .= '<button type="submit" class="btn btn-primary">Änderungen speichern</button>';
        }

        return $html;
    }

    // Verarbeitet Form-Submission (Settings + neue Episode)
    public function post()
    {
        // Standardverhalten (speichert dbFields)
        parent::post();

        // Löschung
        if (!empty($_POST['epDelete']) && is_array($_POST['epDelete'])) {
            foreach ($_POST['epDelete'] as $slug) {
                $slug = $this->slugify($slug);
                $path = $this->episodePathBySlug($slug);
                if (is_file($path)) {
                    @unlink($path);
                    $this->deleteEpisodePage($slug);
                }
            }
        }

        // Bearbeitung
        if (!empty($_POST['epEdit']) && is_array($_POST['epEdit'])) {
            foreach ($_POST['epEdit'] as $slug => $data) {
                $slug = $this->slugify($slug);
                $path = $this->episodePathBySlug($slug);
                $title = trim($data['title'] ?? '');
                $audio = trim($data['audioUrl'] ?? '');
                $date = trim($data['date'] ?? '');
                $summary = trim($data['summary'] ?? '');
                $guid = trim($data['guid'] ?? '');

                if ($title && $audio && is_file($path)) {
                    $payload = [
                        'title' => $title,
                        'audioUrl' => $audio,
                        'date' => $date ?: date(DATE_ATOM),
                        'summary' => $summary,
                        'guid' => $guid ?: md5($title . $audio)
                    ];
                    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    @file_put_contents($path, $json);
                    $this->syncEpisodePage($slug, $payload);
                }
            }
        }

        if (isset($_POST['podcast_new_episode']) && $_POST['podcast_new_episode'] === '1') {
            $title = trim($_POST['epTitle'] ?? '');
            $audio = trim($_POST['epAudio'] ?? '');
            $date = trim($_POST['epDate'] ?? '');
            $summary = trim($_POST['epSummary'] ?? '');
            $guid = trim($_POST['epGuid'] ?? '');

            if ($title && $audio) {
                $fileName = $this->slugify($title);
                $targetDir = $this->episodesPath();
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0775, true);
                }

                $data = [
                    'title' => $title,
                    'audioUrl' => $audio,
                    'date' => $date ?: date(DATE_ATOM),
                    'summary' => $summary,
                    'guid' => $guid ?: md5($title . $audio)
                ];

                $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                @file_put_contents($targetDir . '/' . $fileName . '.json', $json);
                $this->syncEpisodePage($fileName, $data);
            }
        }

        return true;
    }

    // Feed-Ausgabe (/podcast.xml) + Frontend-Episodenformular verarbeiten
    public function beforeAll()
    {
        // Liefert den Podcast-Feed unter /podcast.xml
        if ($this->webhook('podcast.xml')) {
            header('Content-Type: application/rss+xml; charset=UTF-8');
            echo $this->renderFeed();
            exit;
        }

        // Frontend-Formular: POST von eingeloggten Nicht-Admins verarbeiten
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['podcast_frontend_submit'])) {
            $this->handleFrontendSubmit();
        }
    }

    // Zeigt das Frontend-Formular am Ende der konfigurierten Seite an
    public function siteBodyEnd()
    {
        $submissionSlug = trim($this->getValue('submissionPageSlug'));
        if (empty($submissionSlug)) {
            return;
        }

        global $page, $login;
        if (!isset($login) || !$login->isLogged()) {
            return;
        }
        if (!isset($page) || !is_object($page)) {
            return;
        }
        if ($page->slug() !== $submissionSlug) {
            return;
        }

        echo $this->renderFrontendForm();
    }

    // Verarbeitet die Frontend-Formular-Submission (eingeloggte Nicht-Admins)
    private function handleFrontendSubmit()
    {
        global $login;

        // Nur für eingeloggte Nutzer
        if (!isset($login) || !$login->isLogged()) {
            return;
        }

        // CSRF-Nonce prüfen
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $nonce = $_POST['podcast_nonce'] ?? '';
        if (empty($_SESSION['podcast_nonce']) || !hash_equals($_SESSION['podcast_nonce'], $nonce)) {
            return;
        }
        unset($_SESSION['podcast_nonce']); // Einmalverwendung

        $title   = trim($_POST['epTitle']   ?? '');
        $audio   = trim($_POST['epAudio']   ?? '');
        $date    = trim($_POST['epDate']    ?? '');
        $summary = trim($_POST['epSummary'] ?? '');
        $guid    = trim($_POST['epGuid']    ?? '');

        if (!$title || !$audio) {
            return;
        }

        $fileName  = $this->slugify($title);
        $targetDir = $this->episodesPath();
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $data = [
            'title'    => $title,
            'audioUrl' => $audio,
            'date'     => $date ?: date(DATE_ATOM),
            'summary'  => $summary,
            'guid'     => $guid ?: md5($title . $audio)
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        @file_put_contents($targetDir . '/' . $fileName . '.json', $json);
        $this->syncEpisodePage($fileName, $data);

        // Nach dem Speichern zurück zur selben Seite leiten (PRG-Pattern)
        $submissionSlug = trim($this->getValue('submissionPageSlug'));
        $redirect = DOMAIN_BASE . $submissionSlug . '/?podcast_saved=1';
        header('Location: ' . $redirect);
        exit;
    }

    // Generiert das HTML-Formular für das Frontend
    private function renderFrontendForm()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['podcast_nonce'] = $nonce;

        $saved = !empty($_GET['podcast_saved']);

        $html  = '<div class="podcast-frontend-form">';
        $html .= '<h2>Neue Episode einreichen</h2>';

        if ($saved) {
            $html .= '<p style="color:green;font-weight:bold;">Episode wurde gespeichert!</p>';
        }

        $html .= '<form method="post">';
        $html .= '<input type="hidden" name="podcast_frontend_submit" value="1">';
        $html .= '<input type="hidden" name="podcast_nonce" value="' . $this->xml($nonce) . '">';

        $html .= '<label for="fe_epTitle">Titel *</label>';
        $html .= '<input id="fe_epTitle" name="epTitle" type="text" required>';

        $html .= '<label for="fe_epAudio">Audio-URL (mp3) *</label>';
        $html .= '<input id="fe_epAudio" name="epAudio" type="url" required>';

        $html .= '<label for="fe_epDate">Datum (leer = jetzt)</label>';
        $html .= '<input id="fe_epDate" name="epDate" type="text" placeholder="2025-12-08T10:00:00Z">';

        $html .= '<label for="fe_epSummary">Beschreibung</label>';
        $html .= '<textarea id="fe_epSummary" name="epSummary"></textarea>';

        $html .= '<label for="fe_epGuid">GUID (leer = automatisch)</label>';
        $html .= '<input id="fe_epGuid" name="epGuid" type="text">';

        $html .= '<button type="submit">Episode speichern</button>';
        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Lädt Episoden-Dateien aus dem konfigurierten Ordner.
     * Erwartet JSON-Dateien mit Feldern:
     * {
     *   "title": "Episode 1",
     *   "date": "2025-12-08T10:00:00Z",
     *   "audioUrl": "https://example.com/audio/episode1.mp3",
     *   "duration": "12:34",
     *   "summary": "Kurzbeschreibung",
     *   "cover": "https://example.com/img/cover1.jpg",
     *   "guid": "episode-1"
     * }
     */
    public function loadEpisodes()
    {
        $dir = $this->episodesPath();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.json');
        if (!$files) {
            return [];
        }

        $episodes = [];
        foreach ($files as $file) {
            $episode = $this->parseEpisodeFile($file);
            if ($episode) {
                $episodes[] = $episode;
            }
        }

        // Neueste zuerst
        usort($episodes, function ($a, $b) {
            return strtotime($b['date'] ?? 0) <=> strtotime($a['date'] ?? 0);
        });

        return array_slice($episodes, 0, (int) $this->getValue('itemsLimit'));
    }

    private function episodesPath()
    {
        // Pfad relativ zum Bludit-Root
        return PATH_ROOT . $this->getValue('episodesDirectory');
    }

    private function episodePathBySlug($slug)
    {
        return rtrim($this->episodesPath(), '/\\') . '/' . $slug . '.json';
    }

    private function parseEpisodeFile($file)
    {
        $json = @file_get_contents($file);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        // Minimalfelder prüfen
        if (empty($data['title']) || empty($data['audioUrl'])) {
            return null;
        }

        // Defaults ergänzen
        $data['date'] = $data['date'] ?? date(DATE_ATOM, filemtime($file));
        $data['guid'] = $data['guid'] ?? md5($data['title'] . $data['audioUrl']);
        // Optional gespeicherter pageKey
        if (!empty($data['pageKey'])) {
            $data['pageKey'] = (string) $data['pageKey'];
        }

        return $data;
    }

    private function adminEpisodes()
    {
        $dir = $this->episodesPath();
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.json');
        if (!$files) {
            return [];
        }
        $episodes = [];
        foreach ($files as $file) {
            $ep = $this->parseEpisodeFile($file);
            if ($ep) {
                $ep['_file'] = $file;
                $ep['_slug'] = basename($file, '.json');
                $episodes[] = $ep;
            }
        }
        usort($episodes, function ($a, $b) {
            return strtotime($b['date'] ?? 0) <=> strtotime($a['date'] ?? 0);
        });
        return $episodes;
    }

    private function renderFeed()
    {
        $episodes = $this->loadEpisodes();
        $channelTitle = $this->getValue('feedTitle');
        $channelDesc = $this->getValue('feedDescription');
        $channelLink = DOMAIN_BASE; // Basis-URL der Site

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<rss version="2.0">';
        $xml .= '<channel>';
        $xml .= '<title>' . $this->xml($channelTitle) . '</title>';
        $xml .= '<link>' . $this->xml($channelLink) . '</link>';
        $xml .= '<description>' . $this->xml($channelDesc) . '</description>';
        $xml .= '<lastBuildDate>' . $this->xml(date(DATE_RSS)) . '</lastBuildDate>';
        $xml .= '<generator>Podcast Plugin</generator>';

        foreach ($episodes as $item) {
            $xml .= '<item>';
            $xml .= '<title>' . $this->xml($item['title'] ?? '') . '</title>';
            if (!empty($item['summary'])) {
                $xml .= '<description>' . $this->xml($item['summary']) . '</description>';
            }
            if (!empty($item['audioUrl'])) {
                // length="0" als Fallback – RSS-Spec verlangt das Attribut, Datei-Größe ist serverseitig unbekannt
                $xml .= '<enclosure url="' . $this->xml($item['audioUrl']) . '" type="audio/mpeg" length="0" />';
                $xml .= '<link>' . $this->xml($item['audioUrl']) . '</link>';
            }
            if (!empty($item['guid'])) {
                $xml .= '<guid isPermaLink="false">' . $this->xml($item['guid']) . '</guid>';
            }
            if (!empty($item['date'])) {
                $xml .= '<pubDate>' . $this->xml(date(DATE_RSS, strtotime($item['date']))) . '</pubDate>';
            }
            $xml .= '</item>';
        }

        $xml .= '</channel>';
        $xml .= '</rss>';
        return $xml;
    }

    private function xml($value)
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function slugify($text)
    {
        // Deutsche Umlaute und Sonderzeichen transliterieren
        $text = str_replace(
            ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'],
            ['ae', 'oe', 'ue', 'ae', 'oe', 'ue', 'ss'],
            $text
        );
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        return $text ?: 'episode';
    }

    private function syncEpisodePage($slug, $data)
    {
        // Legt eine Bludit-Seite für die Episode an oder aktualisiert sie
        global $pages;

        if (!isset($pages) || !is_object($pages)) {
            return; // Safety: falls Pages-Objekt nicht verfügbar ist
        }

        $parent = trim($this->getValue('parentPageSlug'));
        $contentParts = [];
        if (!empty($data['audioUrl'])) {
            $contentParts[] = $this->podlovePlayerEmbed($slug, $data);
        }
        if (!empty($data['summary'])) {
            $contentParts[] = '<p>' . $this->xml($data['summary']) . '</p>';
        }
        $content = implode("\n\n", $contentParts);

        $fields = [
            'title' => $data['title'] ?? $slug,
            'content' => $content,
            'status' => 'published',
            'slug' => $slug
        ];

        if ($parent !== '') {
            $fields['parent'] = $parent;
        }

        if (!empty($data['date'])) {
            $fields['date'] = $data['date'];
        }

        // Seite aktualisieren, falls pageKey bekannt; sonst mit Slug anlegen
        $pageKey = $this->loadEpisodePageKey($slug) ?? $slug;
        $fields['key'] = $pageKey;

        $edited = false;
        if ($this->pageExists($pageKey)) {
            $edited = (bool) $pages->edit($fields);
        }

        if (!$edited) {
            // Neu anlegen, merkt sich den tatsächlich verwendeten Key (kann bei Kollision abweichen)
            $newKey = $pages->add($fields);
            if (!empty($newKey)) {
                $pageKey = $newKey;
            }
        }

        // pageKey im Episoden-JSON persistieren
        $this->persistEpisodePageKey($slug, $pageKey);
    }

    private function deleteEpisodePage($slug)
    {
        global $pages;

        if (!isset($pages) || !is_object($pages)) {
            return; // Safety
        }

        // Bevorzugt mit gespeichertem pageKey löschen
        $pageKey = $this->loadEpisodePageKey($slug) ?? $slug;
        if ($this->pageExists($pageKey)) {
            $pages->delete($pageKey);
        }
    }

    private function podlovePlayerEmbed($slug, $data)
    {
        if (empty($data['audioUrl'])) {
            return '';
        }

        $id = 'podlove-player-' . $this->slugify($slug);
        $config = [
            'title' => $data['title'] ?? $slug,
            'show' => [
                'title' => $this->getValue('feedTitle')
            ],
            'poster' => $data['cover'] ?? $this->getValue('coverImage'),
            'audio' => [
                [
                    'url' => $data['audioUrl'],
                    'mimeType' => 'audio/mpeg'
                ]
            ]
        ];
        // JSON_HEX_TAG verhindert, dass </script> den Script-Block bricht (XSS)
        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

        $html = '';
        $html .= '<div id="' . $this->xml($id) . '"></div>';
        $html .= '<script src="https://cdn.podlove.org/web-player/5.x/embed.js"></script>';
        $html .= '<script>window.podlovePlayer("#' . $this->xml($id) . '",' . $json . ');</script>';
        return $html;
    }

    private function pageExists($key)
    {
        global $pages;
        if (!isset($pages) || !is_object($pages)) {
            return false;
        }
        if (method_exists($pages, 'exists')) {
            return (bool) $pages->exists($key);
        }
        return false;
    }

    private function persistEpisodePageKey($slug, $pageKey)
    {
        $path = $this->episodePathBySlug($slug);
        if (!is_file($path)) {
            return;
        }
        $json = @file_get_contents($path);
        if ($json === false) {
            return;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return;
        }
        $data['pageKey'] = $pageKey;
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        @file_put_contents($path, $encoded);
    }

    private function loadEpisodePageKey($slug)
    {
        $path = $this->episodePathBySlug($slug);
        if (!is_file($path)) {
            return null;
        }
        $json = @file_get_contents($path);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        return !empty($data['pageKey']) ? $data['pageKey'] : null;
    }
}


