# Code-Review Podcast-Plugin — 2026-10-30

**Geprüfter Stand:** `main` @ `7e71b6f` (Plugin-Version 1.0.0)
**Referenz:** Bludit-Quellcode Tag `3.22.0` (`bludit/bludit`) und die offizielle
Entwickler-Doku (`bludit/documentation-english`: *Plugin basics*, *Hooks list*,
*Admin panel: Controller and View*, *Pages: After creation*, *Custom fields*,
*Translate a plugin*, *Predefined variables*).
**Methode:** Statische Analyse aller Dateien, Abgleich jeder verwendeten
Bludit-API mit dem Kernel-Code, Nachstellen der Escaping-/Tag-Logik und der
PHP-8.2-Deprecations mit PHP 8.3 (CLI). Eine laufende Bludit-Instanz wurde
nicht aufgesetzt.

Die Maßnahmen stehen als Features mit Akzeptanzkriterien in
[`docs/roadmap/`](../roadmap/README.md); die IDs unten verweisen dorthin.

## Zusammenfassung

Das Plugin ist kompakt und gut lesbar, nutzt die richtigen Grundbausteine
(`adminView()`/`adminSidebar()` seit 3.13, `beforeAll()` für den Feed,
`siteHead()`/`siteBodyEnd()` für den Player) und ist in der Ausgabe weitgehend
XSS-sicher (`json_encode` mit `JSON_HEX_TAG`, `textContent` im JS). Die
Kernprobleme liegen an den Stellen, an denen es Bludit-Konventionen umgeht:

1. **Doppeltes Escaping** zerstört Titel und Kategorien im Feed und korrumpiert
   Werte bei jedem Speichern weiter (001).
2. **`$page->tags()` liefert einen String** — Episoden mit einem zweiten Tag
   verschwinden aus Feed, Liste und Player (002).
3. **`$pages->add/edit/delete` statt `createPage/editPage/deletePage`** —
   kein Autor, kein Tag-Index, keine Hooks für andere Plugins (003).
4. **Datenhaltung im Plugin-Ordner, indiziert nach Slug** — Update oder
   Slug-Änderung lässt Episoden still aus dem Feed fallen (005, 006).
5. **CSRF-Token wird nicht geprüft**, weil die API im Frontend-Bootstrap läuft
   (010).

## Abgleich mit der Bludit-API

| Bereich | Bludit (Doku / Kernel 3.22) | Plugin | Bewertung | ID |
|---|---|---|---|---|
| Plugin-Metadaten | `metadata.json` mit `author`, `email`, `website`, `version`, `releaseDate`, `license`, `compatible`; Name/Beschreibung aus `languages/` | Platzhalter-Autor, `compatible: "3.0"`, Felder fehlen; zusätzlich dynamische Properties in `init()` | ✗ Badge „Update“ in 3.19–3.22, PHP-8.2-Deprecations | 034 |
| Sprachdateien | `languages/<Sitesprache>.json`, z. B. `de_DE.json`, Fallback `en.json` | `de.json` | ✗ wird nie geladen | 014 |
| Einstellungen | `form()` + `Plugin::post()` speichert `dbFields` sanitized; `getValue()` liefert sanitized Wert | `post()` korrekt genutzt, aber Werte im Formular erneut escaped | ✗ fortschreitendes Escaping | 001 |
| Admin-Seite | `adminSidebar()`, `adminView()`, `adminController()` unter `/admin/plugin/<name>` | `adminSidebar()`/`adminView()` korrekt; kein `adminController()` | ✓ / Verbesserung möglich | 010 |
| CSRF | `99.security.php` prüft `tokenCSRF` bei jedem Admin-POST; JS-Variable `tokenCSRF` | Token wird gesendet, aber im Frontend-`beforeAll()` nicht geprüft | ✗ nur durch `SameSite=Lax` abgefedert | 010 |
| Feed-Routing | `$this->webhook('pfad')` (berücksichtigt `HTML_PATH_ROOT`), siehe Core-RSS-Plugin | Eigener Vergleich gegen `REQUEST_URI` | ✗ bricht in Unterordnern | 004 |
| URLs/Pfade | `DOMAIN_BASE`, `DOMAIN_UPLOADS`, `$this->domainPath()`, `$this->includeJS()` | `DOMAIN` (ohne Unterordner) + harte Pfade | ✗ bricht in Unterordnern | 004 |
| Seiten anlegen/ändern/löschen | `createPage()`, `editPage()`, `deletePage()` (Autor, `reindexTags()`, Hooks, Syslog); das Core-API-Plugin nutzt sie ebenfalls | `$pages->add()`, `edit()`, `delete()` direkt | ✗ leerer Autor, Tag-Index veraltet, keine Hooks | 003 |
| Tags lesen | `$page->tags()` → String; `$page->tags(true)` → Array | `(array) $page->tags()` | ✗ nur bei genau einem Tag korrekt | 002 |
| Inhalt lesen | `contentRaw()` = Rohtext, `content()` = gerendertes HTML | Bearbeiten lädt `content()` | ✗ Markdown wird beim Speichern zu HTML | 007 |
| Stabile ID | `$page->uuid()` (Core-RSS-Plugin nutzt es als GUID) | Page-Key als GUID und Metadaten-Schlüssel | ✗ ändert sich mit dem Slug | 005 |
| Seitentypen | `published`, `static`, `sticky`, `draft`, `scheduled`, `autosave`; Zukunftsdatum → `scheduled` | nur `published`/`draft` | ✗ Typ geht beim Bearbeiten verloren | 007 |
| Plugin-Daten | `$this->workspace()` (`bl-content/workspaces/…`), Custom Fields in der Seite | `bl-plugins/podcast/metadata/episodes.json` | ✗ Update überschreibt Daten | 006, 012 |
| Custom Fields | seit 3.13 nativ (`custom`, `$page->custom()`) | nicht genutzt, obwohl die Spec es vorsah | Verbesserung | 012 |
| Feed-Erzeugung | Core-RSS: Datei im Workspace, neu erzeugt bei `afterPage*`-Hooks | bei jedem Abruf komplett neu, kein HTTP-Caching | Performance | 013 |
| Player-Position | Hooks `pageBegin`/`pageEnd` in allen Core-Themes | DOM-Selektoren + `siteBodyEnd` | funktioniert, aber fragil | 029 |
| Feed-Discovery | Core-RSS: `<link rel="alternate">` in `siteHead()` | fehlt | Verbesserung | 017 |
| Admin-Styling | Admin-Theme „booty“ = Bootstrap 4.6 | Bootstrap-3-Klassen (`label-*`, `btn-xs`, `radio-inline`) | ✗ teils ungestylt | 024, 035 |
| PHP-Version | Bludit 3.22 verlangt PHP ≥ 8.0 | README nennt 7.4 | Doku | 034 |

## Befunde nach Schwere

### Hoch

- **001 Doppeltes Escaping** — `plugin.php:766` (`Sanitize::html` vor
  `Pages::add`, das selbst escaped), `389–397`/`450` (Feed), `134–179`
  (Einstellungsformular), `588–598` (`data-*`). Nachgestellt:
  `Tom & Jerry` → gespeichert `Tom &amp;amp; Jerry` → Feed
  `Tom &amp;amp;amp; Jerry`; Kategorie im Feed `Society &amp;amp; Culture`.
- **002 Tag-Erkennung** — `plugin.php:990`; `Page::tags()` gibt
  `"podcast,Technik"` zurück, `(array)` macht daraus ein Element.
- **003 Low-Level-Pages-API** — `plugin.php:840`, `846`, `872`.
- **004 Unterordner** — `plugin.php:104`, `115–116`, `215`/`254–260`, `920`, `924`.
- **005 Slug als Schlüssel/GUID** — `plugin.php:454`, `1030–1045`.
- **006 Daten im Plugin-Ordner** — `plugin.php:57–60`, `1009–1011`; das Repo
  liefert `metadata/episodes.json` mit `[]` aus.
- **010 CSRF** — `plugin.php:229–251`; `bl-kernel/boot/site.php` lädt
  `99.security.php` nicht.
- **034 Metadaten/Deprecations** — `metadata.json`, `plugin.php:15–18`.

### Mittel

- **007** Tags werden überschrieben (`plugin.php:833`), Typ-Mapping
  (`768`, `580–584`), `content()` statt `contentRaw()` (`590`).
- **008** Stille Upload-Fehler (`911–913`, `927–929`); `post_max_size`-Prüfung
  greift für jeden Frontend-POST (`218–227`); JS wertet Nicht-JSON als
  Netzwerkfehler (`podcast-admin.js:157`, `219`).
- **011** Nur Endungsprüfung bei Uploads, keine URL-Schema-Prüfung,
  `episodes.json` ohne Lock, Fehlermeldung mit Server-Pfad (`947`).
- **013** O(n log n) Dateizugriffe beim Sortieren (`971–980`),
  `new Page()` für alle Seiten (`375–382`, `960–968`).
- **016** `itunes:explicit` als `yes/no`, Freitext-Kategorie, fehlende
  Kanal-Elemente, `length="0"` bei externen URLs.
- **028** Podlove-CDN ohne Einwilligung (`plugin.php:276`).

### Niedrig

- **009** UTC statt lokaler Zeit (`podcast-admin.js:59`, `133`), Datum-Fallback
  1970 (`plugin.php:773`).
- **035** Toter Code (`renderEpisodesPage()`, `beforeAdminLoad()`), toter Link
  (`plugin.php:199`), No-op-Ternary (`podcast-admin.js:138–139`),
  `podcast.xml` (0 Byte) und `.claude/settings.local.json` im Repo.
- **037** README/Specs weichen vom Code ab.

## Was gut ist

- Klare Struktur der Plugin-Klasse mit Abschnitten; Hooks sind bewusst gewählt.
- Ausgabe im Admin konsequent mit `htmlspecialchars` bzw. `textContent`;
  Player-Konfiguration per `json_encode(..., JSON_HEX_TAG)` sicher eingebettet.
- Löschen prüft, dass die Seite wirklich eine Podcast-Episode ist.
- Upload-Dateinamen werden bereinigt, Kollisionen hochgezählt, Endungen per
  Whitelist begrenzt; Upload-Fehlercodes werden verständlich übersetzt.
- Feed liefert die wichtigsten iTunes-Elemente und überspringt Episoden ohne Audio.

## Nachtrag: Sicherheitsprüfung (2026-10-30)

Gezielte Prüfung auf Schwachstellen nach dem Review. Seitdem gelten die
verbindlichen Sicherheitsregeln in [`CLAUDE.md`](../../CLAUDE.md) für jede
Änderung. Geprüft wurde wieder gegen den Kernel-Code von Bludit 3.22.0; S1
wurde zusätzlich mit einem Stub-Harness gegen alten und neuen Code geprüft.

| # | Schwere | Befund | Stand |
|---|---|---|---|
| S1 | kritisch | Ungeprüfter Seitenschlüssel beim Speichern → `Pages::edit()` verschiebt Dateien außerhalb von `bl-content/pages/` (z. B. `databases/site.php` → Bludit startet den Installer → Übernahme der Website). Benötigt Rolle `editor`/`admin`. | **behoben** in #41 (039, #42) |
| S2 | hoch | CSRF-Token wurde im Frontend-Hook nicht geprüft; nur `SameSite=Lax` schützte. Verstärkte S1. | **behoben** in #41; Umbau auf `adminController()` offen (010) |
| S3 | mittel | `bl-plugins/podcast/metadata/episodes.json` ist per HTTP abrufbar (Bludits `.htaccess` sperrt nur `bl-content/{databases,workspaces,pages,tmp}`) — Entwürfe und geplante Episoden inkl. Audio-URL einsehbar. | **behoben** mit 012 (Custom Fields; Altdatei wird als geschütztes Backup verschoben und gelöscht) |
| S4 | mittel | Podlove-Skript vom CDN mit wandernder Version `5.x`, ohne SRI, im selben Origin wie der Admin-Bereich. | offen (028) |
| S5 | niedrig–mittel | Uploads nur per Endung geprüft; vorhersagbare Dateinamen (Audio unveröffentlichter Episoden erratbar). | offen (011); URL-Schema-Prüfung in #41 erledigt |
| S6 | niedrig | Server-Pfade in Fehlermeldungen, Fatal Error bei Array-Parametern; `post_max_size`-Antwort auch für Nicht-Angemeldete, bei `post_max_size = 0` werden alle Frontend-POSTs abgewiesen. | Pfade/Arrays **behoben** in #41; Rest offen (008) |
| S7 | vorbeugend | Geplante `HEAD`-Requests auf externe Audio-URLs brauchen SSRF-Schutz. | als Kriterium in 016/021 |

Nicht als Schwachstelle gewertet: Rollen `admin`/`editor` dürfen in Bludit
HTML/JavaScript in Seiteninhalte schreiben (Bludit-Verhalten).
