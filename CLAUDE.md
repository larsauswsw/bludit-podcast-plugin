# CLAUDE.md

Projektkontext für Claude Code in diesem Repository.

## Projekt

Podcast-Plugin für das Flat-File-CMS **Bludit** (PHP). Episoden sind
Bludit-Seiten mit dem Tag `podcast`; ihre Metadaten liegen als Bludit Custom
Fields `podcast_*` in der Seite (Zuordnung: `Podcast::CUSTOM_FIELDS`, Lesen nur
über `getEpisodeMeta()`, das dekodiert und per `normalizeMeta()` prüft). Das
Plugin bietet eine Admin-Oberfläche, einen iTunes-kompatiblen RSS-Feed und
bindet den Podlove Web Player ein.

- Ziel: Bludit **3.19–3.22**, PHP **≥ 8.0** (Bludit 3.22 verlangt 8.0).
- Code: `bl-plugins/podcast/` (`plugin.php`, `js/podcast-admin.js`,
  `css/podcast-admin.css`, `metadata.json`, `languages/`). Sprachdateien heißen
  wie Bludits Sitesprache (`de_DE.json`, `de_AT.json`, `de_CH.json`, Fallback
  `en.json`) — `de.json` würde nie geladen.
- Noch kein veröffentlichtes Release — die Version bleibt bis dahin `1.0.0`.
- Projektsprache Deutsch (Doku, Roadmap, UI-Texte); Commit-Messages Englisch
  wie in der bisherigen Historie.
- Das Repository ist **öffentlich**, bis zum Release 1.0; danach privat.
  Öffentlich ist dann nur `larsauswsw/bludit-plugin-podcast`: README und
  Screenshots aus `public/`, nach der Lizenzentscheidung auch der Plugin-Ordner
  und Release-ZIPs (Roadmap 038). Dorthin nie CLAUDE.md, Doku, Roadmap, Skripte
  oder Server-Details des Nutzers.

## Roadmap & Review

Geplante Arbeit liegt in `docs/roadmap/` (Index: `docs/roadmap/README.md`),
jede Aufgabe mit stabiler ID, Akzeptanzkriterien und GitHub-Issue. Wird
„Feature NNN“ verlangt, zuerst den passenden Abschnitt dort lesen. Befunde und
API-Abgleich: `docs/review/2026-10-30-code-review.md`. Test auf einer echten
Installation (vor Releases bzw. nach größeren Änderungen):
`docs/superpowers/checklists/2026-10-31-installationstest.md` — neue Funktionen
dort um passende Schritte ergänzen.

## Bludit-API (verifiziert gegen 3.22.0)

- Seiten anlegen/ändern/löschen über `createPage()`, `editPage()`,
  `deletePage()` (setzen Autor, `reindexTags()`, Hooks, Syslog; `editPage()`
  prüft, dass der Schlüssel existiert) — nicht `$pages->add/edit/delete` direkt.
  Im Plugin: neue Episoden mit `slug` aus dem Rohtitel
  (`generateKey($title, false, true)`); beim Bearbeiten keine `tags` senden
  (bleiben erhalten), aber `parent` (sonst verschiebt `Pages::edit()` die
  Unterseite); Episoden mit Unterseiten nicht löschen (`getChildren()`).
- Episode erkennen: `pageHasPodcastTag()` prüft den Schlüssel `podcast` in
  `$page->tags(true)` — auch neben weiteren Tags. Öffentlich (Feed, Link
  „Anschauen“) sind die Typen `published`, `sticky`, `static`; `scheduled`
  entsteht bei `published` mit Zukunftsdatum. Das Formular speichert nur
  `published`/`draft` oder den vorhandenen Sondertyp `sticky`/`static`.
- `$page->tags(true)` liefert `tagKey => Name`; ohne Argument einen String.
- `$page->contentRaw()` = Rohtext, `content()` = gerendertes HTML.
- `$page->uuid()` ist stabil, der Page-Key ändert sich mit dem Slug.
- `Plugin::post()`, `Pages::add/edit` und `getValue()` arbeiten mit bereits
  HTML-escapten Werten (`Sanitize::html`); Rohwert: `getValue($f, false)`.
  Im Plugin: an Bludit roh übergeben (nie vorher `Sanitize::html`), dekodiert
  lesen über `setting()` bzw. `plainTitle()`, Feed-Werte über `xml()` ausgeben.
- URLs: `DOMAIN_BASE`, `DOMAIN_ADMIN`, `DOMAIN_UPLOADS`, `$this->domainPath()`;
  Skript/CSS des Plugins über `assetUrl()` (Änderungszeit als Cache-Version);
  Routing: `$this->webhook()` — nie `DOMAIN` + Pfad.
- Plugin-Daten: Custom Fields oder `$this->workspace()` (per `.htaccess`
  gesperrt; `Plugin::uninstall()` löscht ihn). Dateien im Workspace als `.php`
  mit Schutzzeile `<?php defined('BLUDIT') or die('Bludit CMS.'); ?>` anlegen.
- Custom Fields: Definition per `$pages->setCustomFields($json)` +
  `$site->set(['customFields' => $json])`. `Pages::add/edit` mit `custom`
  ersetzt **alle** Custom-Werte der Seite und wirft bei undefiniertem Feld
  (`settype`) — daher immer alle Site-Felder mitgeben (`customFieldArgs()`).
  `Pages::edit()` ohne `parent` verschiebt Unterseiten an die oberste Ebene;
  für reine Wertänderungen `$pages->setField()` nutzen.
- Admin-Seiten: `adminController()`/`adminView()` unter `/admin/plugin/podcast`;
  dort sind Session, Login und CSRF-Prüfung (`99.security.php`) schon gelaufen.
  Im Frontend (`beforeAll()`) wird **kein** CSRF-Token geprüft — dort läuft
  nur der Feed (`webhook()`). Die Episoden-API ist POST auf
  `plugin/podcast?podcast_api=1` in `adminController()` (`runApiAction()`,
  `sendJson()`). Ein ungültiges Token meldet Bludit ab; zu große Uploads
  (leerer POST-Body) fängt `rejectOversizedApiPost()` im Konstruktor vorher ab.
- Uploads nur über `handleUpload()`: prüft per `validateUpload()` Größe,
  Endung und Inhalt (`finfo`, `Podcast::UPLOAD_TYPES`), vergibt einen
  Zufallsnamen und sichert den Ordner per `prepareUploadDir()` (`index.php`,
  `.htaccess` nur mit FileInfo-Direktiven, sonst droht Fehler 500).
- Admin-Optik: Theme „booty“ = Bootstrap 4.6. Markup wie Bludit selbst —
  Auswahlfelder `custom-select`, Dateifelder `custom-file`, Radios/Checkboxen
  `form-check`, Listen wie `views/content.php` (Nebenspalten
  `d-none d-md-table-cell`, Aktionen im ☰-Menü `actionsDropdown`), Seitenkopf
  wie `Bootstrap::pageTitle()`, Formular-Aktionen („Speichern“, „Abbrechen“)
  oben rechts neben dem Titel wie `views/settings.php` (`float-right`,
  `btn-sm`), nie am Seitenende. `form-control` nur für Text- und Zahlenfelder
  (Datumsfeld mit Ausgleich im CSS): booty vergrößert dort den Innenabstand
  bei fester Höhe, Auswahl- und Dateifelder werden sonst abgeschnitten.
  Plugin-Einstellungen (`form()`) ohne eigene Klassen, gestylt über
  `.plugin-form`, Gruppen per `Bootstrap::formTitle()`. Eigenes CSS nur in
  `css/podcast-admin.css`.
- Apple-Kategorie nur über `appleCategory()` (Whitelist `APPLE_CATEGORIES`,
  gespeichert als „Kategorie > Unterkategorie“, englische Namen), Cover über
  `coverUrl()` (nur `http(s)`), `itunes:explicit` als `true`/`false`.
- Shownotes im Feed nur über `shownotesHtml()` (DOM-Whitelist `SHOWNOTES_TAGS`,
  keine Attribute außer geprüftem `href`) in `cdata()`; `<description>` über
  `episodeTextSummary()`. Dateigröße externer Audio-URLs nur über
  `remoteAudioLength()` (SSRF-Schutz nach Regel 8: `publicTarget()`,
  `isPublicIp()`, an die geprüfte IP gebunden, Weiterleitungen einzeln geprüft).
- `podcast:guid` über `podcastGuid()`: einmal per `ensurePodcastGuid()` (in
  `post()` und `adminController()`) aus der Feed-Adresse erzeugt und
  gespeichert, danach unverändert; `podcast:locked` aus `feedLocked()`.
- Feed-Adresse nur über `feedUrl()` (abweichende URL mit `http(s)://`, sonst
  `localFeedUrl()` = `DOMAIN_BASE` + `feedSlug()`), Website über `siteUrl()` —
  nie `setting('feedUrl')`/`setting('siteUrl')` direkt ausgeben.
- Player-Position: im Seiteninhalt — `beforeSiteLoad()` → `injectPlayer()` →
  `$page->setField('content', …)` (wie Bludits custom-fields-parser), erneut in
  `siteHead()`, falls ein anderes Plugin den Inhalt neu gebaut hat. Einstellung
  `playerPosition` (`top`/`bottom`), Platzhalter `{{ podcast-player }}`; in
  Listen, Feed und Player-Beschreibung wird er entfernt. `pageBegin` liegt in
  den Core-Themes vor dem Titel, daher nicht genutzt. `siteBodyEnd()` startet nur
  den Podlove-Player.
- Podlove Web Player liegt lokal in `bl-plugins/podcast/player/`
  (`Podcast::PODLOVE_VERSION`, Herkunft und Prüfsummen in `player/SOURCE.md`).
  Aktualisieren nur über `scripts/update-podlove-player.sh`; keine
  HTML-Seiten aus dem Paket (z. B. `share.html`) übernehmen. Der Player rendert
  `summary` per `innerHTML` — nur HTML-escapten Text übergeben; Entities nie
  nach `strip_tags()` dekodieren (Regel 4). Abo-Button: `clients` aus
  `subscribeClients()` — ohne `clients` bleibt sein Fenster leer. Dienste mit
  Web-Variante (Apple Podcasts, Pocket Casts …) nur mit ihrer Podcast-ID
  (`SUBSCRIBE_SERVICES`, aus dem eingetragenen Link per Muster gelesen), denn
  der Player zieht den Web-Link der App vor; Apps über die Feed-Adresse
  (`SUBSCRIBE_APPS`), `player-fm`/`rss` mit der Feed-Adresse als `service`.

## Sicherheitsregeln (verbindlich für jede Änderung)

Jede Änderung muss frei von bekannten Schwachstellen sein. Wird beim Arbeiten
eine Schwachstelle entdeckt — auch in unverändertem Code —, wird sie gemeldet
und möglichst im selben Change behoben, nicht nur notiert.

1. **Eingaben sind nicht vertrauenswürdig.** Alles aus `$_POST`, `$_GET`,
   `$_FILES`, `$_SERVER`, Custom-Field-Werten (auch über den Bludit-Editor
   änderbar) und Altdaten auf Typ prüfen (`is_string`,
   Arrays ablehnen), Aufzählungen per Whitelist, Zahlen per `filter_var`.
2. **Keine Benutzereingaben in Dateipfaden.** Seitenschlüssel nur verwenden,
   wenn `$pages->exists($key)` und die Seite eine Podcast-Episode ist.
   Dateinamen nur über `sanitizeFilename()`; zusammengesetzte Pfade mit
   `realpath()` gegen das erlaubte Zielverzeichnis prüfen.
3. **Schreibende Aktionen** nur mit gültigem CSRF-Token (über
   `adminController()` oder `$security->validateTokenCSRF()`) und
   Rollenprüfung (`checkRole(['admin','editor'], false)`).
4. **Ausgabe kontextgerecht und genau einmal escapen:** HTML
   `htmlspecialchars(…, ENT_QUOTES)` auf dekodierte Werte, XML `ENT_XML1`,
   JavaScript `json_encode(…, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)`,
   im JS nur `textContent`, nie `innerHTML` mit Daten.
5. **URLs** aus Eingaben nur mit Schema `http`/`https` akzeptieren.
6. **Uploads:** Endungs-Whitelist **und** MIME-Prüfung (`finfo`), kein
   SVG/HTML, Größenlimit, zufälliger Namensanteil, Ablage in Ordnern ohne
   Skriptausführung.
7. **Keine Daten an per HTTP erreichbaren Orten** (z. B. `bl-plugins/`);
   Workspace oder Custom Fields verwenden.
8. **Ausgehende Requests (SSRF):** nur `http`/`https`, Ziel-IP nach
   DNS-Auflösung gegen private, Loopback- und Link-Local-Bereiche prüfen,
   Redirects nicht blind folgen, Timeout und Größenlimit setzen.
9. **Drittanbieter-Ressourcen** lokal ausliefern oder mit fester Version und
   Subresource Integrity (`integrity=…`) einbinden.
10. **Fehlermeldungen** ohne Server-Pfade und Interna; Details per `Log::set()`
    ins Bludit-Log.
11. **Keine Geheimnisse** im Repository; `.claude/settings.local.json` nicht
    committen.
12. **Vor jedem Commit:** Diff gegen diese Liste prüfen (Security-Review),
    `php -l` auf geänderte PHP-Dateien; Ergebnis im PR/Commit kurz nennen.
13. **Öffentliches Repo:** Schwachstellen nicht mit Ausnutzungsdetails in
    Issues, Commits oder Doku beschreiben, bevor der Fix gemergt ist; den Fix
    neutral benennen.

Akzeptiertes Bludit-Verhalten: Rollen `admin` und `editor` dürfen in Bludit
HTML/JavaScript in Seiteninhalte schreiben. Das Plugin darf darüber hinaus
keine Rechte gewähren (z. B. Dateizugriff, Zugriff auf fremde Seiten außerhalb
der Bludit-Regeln).
