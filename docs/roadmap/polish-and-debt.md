# Sonstiges & technische Schulden

Metadaten, Aufräumen, Tests, Doku und Release. Siehe [README.md](./README.md)
für Status-/Prioritäts-Legende.

## 034 · `metadata.json`, Kompatibilität & PHP-8.2-Deprecations

Status: idea · Priorität: hoch · Aufwand: S · Bereich: `metadata.json`, `plugin.php` (`init()`), README · Issue: [#35](https://github.com/larsauswsw/bludit-podcast-plugin/issues/35)

**Beschreibung**

- `"compatible": "3.0"` — `Plugin::isCompatible()` vergleicht Major **und**
  Minor mit `BLUDIT_VERSION`. Unter 3.19–3.22 zeigt Bludit deshalb in der
  Plugin-Liste den Badge „Update“ und in den Einstellungen „This plugin may not
  be supported by this version of Bludit“.
- `"author": "Plugin Author"` ist ein Platzhalter; `email`, `website`,
  `releaseDate`, `license` fehlen (siehe Doku „Plugin basics“).
- `init()` setzt `$this->name`, `$this->description`, `$this->author`,
  `$this->version` (`plugin.php:15–18`). Diese Properties gibt es in der
  Bludit-Klasse `Plugin` nicht — ab PHP 8.2 erzeugt das je eine
  „Creation of dynamic property … is deprecated“-Meldung (nachgestellt mit PHP
  8.3), die bei `display_errors=On` JSON-Antworten und den Feed zerstört. Die
  Werte werden ohnehin aus `metadata.json`/`languages/` gelesen.
- README nennt PHP 7.4; Bludit 3.22 verlangt PHP ≥ 8.0 (`install.php:11`).

**Akzeptanzkriterien**

- [ ] `compatible` listet die getesteten Versionen, z. B. `"3.19,3.20,3.21,3.22"`.
- [ ] `metadata.json` enthält echte Werte für `author`, `email`, `website`,
      `version`, `releaseDate`, `license`.
- [ ] `init()` setzt nur noch `dbFields`; Version wird nur in `metadata.json` gepflegt.
- [ ] Mit `error_reporting=E_ALL` unter PHP 8.2+ keine Deprecation-Meldungen.
- [ ] README: Voraussetzungen „Bludit 3.19+ (getestet bis 3.22), PHP 8.0+“.

**Betroffene Dateien**

- `bl-plugins/podcast/metadata.json`, `bl-plugins/podcast/plugin.php`,
  `bl-plugins/podcast/README.md`

---

## 035 · Toten Code & Repo-Altlasten entfernen

Status: idea · Priorität: niedrig · Aufwand: S · Bereich: `plugin.php`, `podcast-admin.js`, Repo-Wurzel · Issue: [#36](https://github.com/larsauswsw/bludit-podcast-plugin/issues/36)

**Beschreibung**

- `renderEpisodesPage()` (`plugin.php:488–538`) wird nirgends aufgerufen.
- `beforeAdminLoad()` (`plugin.php:263–265`) ist leer, wird aber trotzdem als
  Hook registriert; `uninstall()` ruft nur `parent::uninstall()` auf.
- `form()` verlinkt auf `admin/podcast-episodes` (`plugin.php:199`) — diese Route
  gibt es nicht, richtig ist `admin/plugin/podcast`.
- `podcast-admin.js:138–139`: `d.audioUrl ? 'url' : 'url'` — beide Zweige gleich.
- `podcast.xml` (0 Byte) liegt in der Repo-Wurzel.
- `.claude/settings.local.json` ist eingecheckt (lokale Einstellungen).
- Bootstrap-3-Klassen (`radio-inline`, `btn-xs`, `btn-default`, `label-*`) im
  Bootstrap-4-Admin (siehe auch 024).

**Akzeptanzkriterien**

- [ ] Toter Code und leere Hooks entfernt, Link in `form()` korrigiert.
- [ ] `podcast.xml` gelöscht, `.claude/settings.local.json` aus dem Repo
      entfernt und in `.gitignore` aufgenommen.
- [ ] Formular nutzt Bootstrap-4-Markup (`form-check form-check-inline`).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`,
  `podcast.xml`, `.gitignore`, `.claude/settings.local.json`

---

## 036 · Automatisierte Tests & CI

Status: idea · Priorität: mittel · Aufwand: M · Bereich: neu: `.github/workflows/`, `tests/` · Issue: [#37](https://github.com/larsauswsw/bludit-podcast-plugin/issues/37)

**Beschreibung**

Es gibt weder Tests noch CI. Die gefundenen Fehler (Escaping, Tag-Erkennung,
Deprecations) wären mit einfachen Checks früh aufgefallen.

**Akzeptanzkriterien**

- [ ] GitHub Action: `php -l` für PHP 8.0–8.4, PHPStan (Level 5) mit Stubs für
      die Bludit-Klassen.
- [ ] Unit-Tests (PHPUnit) für reine Helfer: Feed-Escaping, Tag-Erkennung,
      Dauer-Parsing, Dateinamen-Bereinigung, MIME-Zuordnung.
- [ ] Integrationstest: Bludit 3.22 im Container installieren, Plugin aktivieren,
      Episode anlegen, Feed abrufen und gegen das RSS-/iTunes-Schema prüfen.
- [ ] ESLint für `podcast-admin.js`.

**Betroffene Dateien**

- neu: `.github/workflows/ci.yml`, `tests/`, `phpstan.neon`, `composer.json` (nur dev)

---

## 037 · README, Specs & CLAUDE.md aktualisieren

Status: idea · Priorität: niedrig · Aufwand: S · Bereich: `README.md`, `docs/superpowers/`, `CLAUDE.md` · Issue: [#38](https://github.com/larsauswsw/bludit-podcast-plugin/issues/38)

**Beschreibung**

- README beschreibt Dinge, die nicht zum Code passen: Sortierung im Feed „nach
  Episodennummer“ (tatsächlich nach Datum), „Edit-Icon (Stift)“ und
  „Papierkorb“ (tatsächlich Text-Buttons), Feld „Show notes“ (heißt im
  Formular „Beschreibung“), Kanaleinstellungen über ein Zahnrad unter
  „Plugins“ (der Button sitzt auf der Podcast-Seite); Google Podcasts
  (seit 2024 eingestellt) wird noch als Ziel genannt.
- Die Design-Spec beschreibt Custom Fields und `admin/*.php`-Dateien, die es so
  nicht gibt.
- `CLAUDE.md` wurde bei der Umstrukturierung gelöscht; die alte enthielt
  veraltete API-Annahmen (z. B. „`$this->xml()` ist eine Plugin-Hilfsmethode“ —
  gibt es in der Basisklasse nicht).

**Akzeptanzkriterien**

- [ ] README entspricht dem tatsächlichen Verhalten; Google Podcasts entfernt.
- [ ] Specs mit Hinweis „umgesetzt abweichend: …“ oder aktualisiert.
- [ ] Neue `CLAUDE.md` mit verifizierter API-Übersicht (siehe
      `docs/review/2026-10-30-code-review.md`) und Verweis auf diese Roadmap.

**Betroffene Dateien**

- `bl-plugins/podcast/README.md`, `docs/superpowers/specs/*`, `CLAUDE.md` (neu)

---

## 038 · Release-Prozess: CHANGELOG & ZIP-Paket

Status: idea · Priorität: niedrig · Aufwand: S · Bereich: neu: `CHANGELOG.md`, `.github/workflows/release.yml` · Issue: [#39](https://github.com/larsauswsw/bludit-podcast-plugin/issues/39)

**Beschreibung**

Installiert wird durch Kopieren des Plugin-Ordners. Ein Release-ZIP, das nur
`bl-plugins/podcast/` enthält (ohne Daten-Dateien, siehe 006), plus CHANGELOG
macht Updates nachvollziehbar und sicher. Optional später: Eintrag im
Bludit-Plugin-Verzeichnis.

**Akzeptanzkriterien**

- [ ] `CHANGELOG.md` nach „Keep a Changelog“, Version in `metadata.json` synchron.
- [ ] GitHub Action baut bei Tag `v*` ein `podcast-<version>.zip` und hängt es an
      das Release.
- [ ] README verlinkt auf die Releases als bevorzugten Installationsweg.

**Betroffene Dateien**

- neu: `CHANGELOG.md`, `.github/workflows/release.yml`; `bl-plugins/podcast/README.md`
