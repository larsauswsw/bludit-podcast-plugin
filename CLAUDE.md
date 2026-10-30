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
  `metadata.json`, `languages/`).
- Noch kein veröffentlichtes Release — die Version bleibt bis dahin `1.0.0`.
- Projektsprache Deutsch (Doku, Roadmap, UI-Texte); Commit-Messages Englisch
  wie in der bisherigen Historie.
- Das Repository ist **öffentlich**.

## Roadmap & Review

Geplante Arbeit liegt in `docs/roadmap/` (Index: `docs/roadmap/README.md`),
jede Aufgabe mit stabiler ID, Akzeptanzkriterien und GitHub-Issue. Wird
„Feature NNN“ verlangt, zuerst den passenden Abschnitt dort lesen. Befunde und
API-Abgleich: `docs/review/2026-10-30-code-review.md`.

## Bludit-API (verifiziert gegen 3.22.0)

- Seiten anlegen/ändern/löschen über `createPage()`, `editPage()`,
  `deletePage()` (setzen Autor, `reindexTags()`, Hooks, Syslog; `editPage()`
  prüft, dass der Schlüssel existiert) — nicht `$pages->add/edit/delete` direkt.
- `$page->tags(true)` liefert `tagKey => Name`; ohne Argument einen String.
- `$page->contentRaw()` = Rohtext, `content()` = gerendertes HTML.
- `$page->uuid()` ist stabil, der Page-Key ändert sich mit dem Slug.
- `Plugin::post()`, `Pages::add/edit` und `getValue()` arbeiten mit bereits
  HTML-escapten Werten (`Sanitize::html`); Rohwert: `getValue($f, false)`.
- URLs: `DOMAIN_BASE`, `DOMAIN_UPLOADS`, `$this->domainPath()`,
  `$this->includeJS()`; Routing: `$this->webhook()` — nie `DOMAIN` + Pfad.
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
  Im Frontend (`beforeAll()`) wird **kein** CSRF-Token geprüft.
- Player-Position: Hooks `pageBegin`/`pageEnd`.
- Podlove Web Player liegt lokal in `bl-plugins/podcast/player/`
  (`Podcast::PODLOVE_VERSION`, Herkunft und Prüfsummen in `player/SOURCE.md`).
  Aktualisieren nur über `scripts/update-podlove-player.sh`; keine
  HTML-Seiten aus dem Paket (z. B. `share.html`) übernehmen. Der Player rendert
  `summary` per `innerHTML` — nur HTML-escapten Text übergeben; Entities nie
  nach `strip_tags()` dekodieren (Regel 4).

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
