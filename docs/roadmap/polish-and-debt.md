# Sonstiges & technische Schulden

Metadaten, Aufräumen, Tests, Doku und Release. Siehe [README.md](./README.md)
für Status-/Prioritäts-Legende.

## 034 · `metadata.json`, Kompatibilität & PHP-8.2-Deprecations

Status: done · Priorität: hoch · Aufwand: S · Bereich: `metadata.json`, `plugin.php` (`init()`), README · Issue: [#35](https://github.com/larsauswsw/bludit-podcast-plugin/issues/35)

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
  8.3), mit PHP 9 ein Fehler. Sichtbar wird sie im Normalbetrieb nicht: Bludit
  setzt `display_errors` auf 0 und meldet nur `E_ERROR` (`boot/init.php`). Die
  Werte werden ohnehin aus `metadata.json`/`languages/` gelesen.
- README nennt PHP 7.4; Bludit 3.22 verlangt PHP ≥ 8.0 (`install.php:11`).

**Akzeptanzkriterien**

- [x] `compatible` listet die getesteten Versionen: `"3.19,3.20,3.21,3.22"` —
      kein Hinweis „Update“/„möglicherweise nicht unterstützt“ mehr.
- [x] `metadata.json`: `author` `larsauswsw`, `email` bewusst leer, `website`
      = öffentliches Repo `larsauswsw/bludit-plugin-podcast`, `version` `1.0.0`.
      `releaseDate` und `license` folgen mit dem Release (038) bzw. der
      Lizenzentscheidung.
- [x] `init()` setzt nur noch `dbFields`; Version wird nur in `metadata.json` gepflegt
      und bleibt bis zur ersten Veröffentlichung `1.0.0`.
- [x] Mit `error_reporting=E_ALL` unter PHP 8.3 keine Meldungen (Bludits
      Einstellung dafür lokal überschrieben; Admin-Liste, Einstellungen,
      Plugin-Liste, API, Feed, Episoden- und Startseite; vorher je Anfrage vier
      Deprecations).
- [x] README: Voraussetzungen „Bludit 3.19+ (getestet bis 3.22), PHP 8.0+“ (mit 011).

**Betroffene Dateien**

- `bl-plugins/podcast/metadata.json`, `bl-plugins/podcast/plugin.php`,
  `bl-plugins/podcast/README.md`

---

## 035 · Toten Code & Repo-Altlasten entfernen

Status: done · Priorität: niedrig · Aufwand: S · Bereich: `plugin.php`, `podcast-admin.js`, Repo-Wurzel · Issue: [#36](https://github.com/larsauswsw/bludit-podcast-plugin/issues/36)

**Beschreibung**

- ~~`renderEpisodesPage()` wird nirgends aufgerufen; `beforeAdminLoad()` ist
  leer~~ — mit 010 entfernt. ~~`uninstall()` ruft nur `parent::uninstall()` auf~~ — entfernt.
- ~~`form()` verlinkt auf `admin/podcast-episodes`~~ — mit 042 behoben.
- ~~`podcast-admin.js`: `d.audioUrl ? 'url' : 'url'` — beide Zweige gleich~~ — entfernt.
- ~~`podcast.xml` in der Repo-Wurzel~~ — gelöscht (alter Beispiel-Feed).
- ~~`.claude/settings.local.json` ist eingecheckt~~ — aus dem Repo entfernt,
  in `.gitignore` (enthielt nur lokale Freigaben, keine Geheimnisse).
- ~~Bootstrap-3-Klassen im Bootstrap-4-Admin~~ — mit 042 behoben.

**Akzeptanzkriterien**

- [x] Toter Code und leere Hooks entfernt (Link in `form()` mit 042 korrigiert).
      Keine weiteren ungenutzten Funktionen (geprüft: übrig sind nur Bludit-Hooks).
- [x] `podcast.xml` gelöscht, `.claude/settings.local.json` aus dem Repo
      entfernt und in `.gitignore` aufgenommen.
- [x] Formular nutzt Bootstrap-4-Markup (`form-check form-check-inline`, mit 042).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`,
  `podcast.xml`, `.gitignore`, `.claude/settings.local.json`

---

## 036 · Automatisierte Tests & CI

Status: done · Priorität: hoch · Aufwand: M · Bereich: neu: `.github/workflows/`, `tests/` · Issue: [#37](https://github.com/larsauswsw/bludit-podcast-plugin/issues/37)

**Beschreibung**

Es gibt weder Tests noch CI. Die gefundenen Fehler (Escaping, Tag-Erkennung,
Deprecations) wären mit einfachen Checks früh aufgefallen.

**Akzeptanzkriterien**

- [x] GitHub Action: `php -l` für PHP 8.0–8.4, PHPStan (Level 5) mit Stubs für
      die Bludit-Klassen. *(Statt Stubs liest PHPStan Bludit 3.22 selbst,
      `scanDirectories`; nur Laufzeit-Konstanten in `tests/phpstan-constants.php`.)*
- [x] Unit-Tests (PHPUnit) für reine Helfer: Feed-Escaping, Tag-Erkennung,
      Dauer-Parsing, Dateinamen-Bereinigung, MIME-Zuordnung. *(Dazu Kategorien,
      Shownotes, CDATA, Feed-Adresse, GUID, Datum, Abo-Links, Cover-Prüfung.)*
- [x] Integrationstest: Bludit 3.22 im Container installieren, Plugin aktivieren,
      Episode anlegen, Feed abrufen und gegen das RSS-/iTunes-Schema prüfen.
      *(PHP-Testserver mit frisch installiertem Bludit 3.22 im CI-Job; Feed per
      XPath auf Apples Pflichtelemente geprüft, ein offizielles Schema gibt es
      nicht.)*
- [x] ESLint für `podcast-admin.js` (mit Regel gegen `innerHTML` & Co., CLAUDE.md Regel 4).
- [x] Sicherheitsprüfung in CI: ~~Psalm-Taint-Analyse (`--taint-analysis`) für
      `plugin.php`~~, Secret-Scan; ein Fund lässt den Build fehlschlagen.
      *(Psalm-Taint vom Inhaber am 2026-11-01 verworfen: viele Fehlalarme bei
      Bludit-Code; die gezielten Sicherheitstests decken die Regeln ab.
      Secret-Scan: gitleaks über die ganze Historie.)*
- [x] Sicherheits-Tests für die Regeln aus `CLAUDE.md`: manipulierte
      Seitenschlüssel, fehlender CSRF-Token, falsche Rolle, Upload mit falschem
      MIME-Typ, Array-Parameter, SSRF-Ziele (`127.0.0.1`, `169.254.169.254`).
      *(Teil 1 als Unit-Tests: SSRF-Ziele und -Weiterleitungen, MIME/Endung,
      Array-Werte, Pfade außerhalb der Upload-Ordner, Rolle beim Cover-Upload.
      Teil 2 über HTTP gegen Bludit (`tests/Integration/`): Anfrage ohne
      CSRF-Token beendet die Sitzung und ändert nichts, Redakteur ohne Zugriff
      auf Kanaleinstellungen und Cover, Seitenschlüssel mit `../` und fremde
      Seiten abgelehnt, Arrays, Upload mit falschem Inhalt, unbekannte Aktion,
      Shownotes ohne Skript im Feed.)*

**Betroffene Dateien**

- neu: `.github/workflows/ci.yml`, `tests/`, `phpstan.neon`, `composer.json` (nur dev)

---

## 037 · README, Specs & CLAUDE.md aktualisieren

Status: done · Priorität: niedrig · Aufwand: S · Bereich: `README.md`, `docs/superpowers/`, `CLAUDE.md` · Issue: [#38](https://github.com/larsauswsw/bludit-podcast-plugin/issues/38)

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

- [x] README entspricht dem tatsächlichen Verhalten; Google Podcasts entfernt
      (2026-11-01: deutsche Menünamen, ☰-Menü, Status-Typen, Feed-Inhalt und
      -Sortierung, Player-Position und Platzhalter, aktuelle Fehlermeldungen;
      „License: MIT“ ersetzt durch „noch nicht entschieden, alle Rechte
      vorbehalten“, Podlove-Player bleibt MIT).
- [x] Specs mit Hinweis „umgesetzt abweichend: …“; Pläne als historisch markiert.
- [x] Neue `CLAUDE.md` mit verifizierter API-Übersicht, Sicherheitsregeln und
      Verweis auf diese Roadmap (angelegt 2026-10-30).

**Betroffene Dateien**

- `bl-plugins/podcast/README.md`, `docs/superpowers/specs/*`, `CLAUDE.md` (neu)

---

## 038 · Release-Prozess: CHANGELOG & ZIP-Paket

Status: in-progress · Priorität: mittel · Aufwand: M · Bereich: neu: `public/`, `CHANGELOG.md`, Release-Skript · Issue: [#39](https://github.com/larsauswsw/bludit-podcast-plugin/issues/39)

**Beschreibung**

Installiert wird durch Kopieren des Plugin-Ordners. Ein Release-ZIP, das nur
`bl-plugins/podcast/` enthält (ohne Daten-Dateien, siehe 006), plus CHANGELOG
macht Updates nachvollziehbar und sicher. Optional später: Eintrag im
Bludit-Plugin-Verzeichnis.

**Repository-Aufteilung (entschieden 2026-11-01)**

- **Entwicklung:** dieses Repository `larsauswsw/bludit-podcast-plugin`
  (CLAUDE.md, Doku, Roadmap, Issues). Wird mit dem Release 1.0 privat
  (GitHub → Settings → Change repository visibility, nur durch den Inhaber).
- **Öffentlich:** `larsauswsw/bludit-plugin-podcast` im Stil der
  Bludit-Community-Plugins (Vorbild `medicamin/bludit-plugin-admin-numeric-pagination`):
  `README.md` (Englisch mit kurzem deutschen Abschnitt), Screenshot(s) und der
  Ordner `podcast/` (= Plugin-Ordner). Kein Entwicklungsverlauf: je Version ein
  Commit plus GitHub-Release mit `podcast-<version>.zip`. Nie CLAUDE.md, Doku,
  Roadmap oder Skripte dorthin.
- **Bis zur Lizenzentscheidung** (Verkauf möglich) nur Schaufenster: README und
  Screenshots, kein Code, kein Release. Ohne Lizenz gilt „alle Rechte
  vorbehalten“. Der mitgelieferte Podlove-Player bleibt MIT-lizenziert
  (`player/LICENSE` muss im Paket bleiben).
- **Lizenzmodell (offen, später zu entscheiden):** (a) alles unter einer
  offenen Lizenz wie MIT — Verkauf möglich, aber jeder Käufer darf
  weitergeben; Einnahmen eher über Support/Updates; (b) eigene kommerzielle
  Lizenz für das ganze Plugin; (c) kostenlose Grundversion (offen, im
  öffentlichen Repo) plus kostenpflichtige Pro-Variante, z. B. als eigenes
  Zusatz-Plugin mit eigener Lizenz, vertrieben außerhalb von GitHub. Für (c)
  vorher festlegen, welche Funktionen in Pro gehören und welche Erweiterungs-
  punkte die Grundversion dafür braucht.
- **Quelle der öffentlichen Dateien:** `public/` in diesem Repository.
- **Nach dem Privatstellen** lädt der Update-Befehl auf dem Server `main` nur
  noch mit Lesetoken (fine-grained, nur dieses Repository, nur „Contents: Read“);
  Releases kommen als ZIP aus dem öffentlichen Repo.

**Akzeptanzkriterien**

- [x] Öffentliches Schaufenster-Repo mit README und Screenshots aus `public/`
      (angelegt 2026-11-01, Inhaber hat das leere Repo erstellt; Claude darf
      keine Repos anlegen).
- [x] Lizenz entschieden; `license` in `metadata.json` und Lizenzdatei bzw.
      -hinweis im öffentlichen Repo. (2026-11-02: **MIT**, Variante (a); Text in
      `bl-plugins/podcast/LICENSE`, liegt im ZIP und wird ins öffentliche Repo
      kopiert.)
- [x] `CHANGELOG.md` nach „Keep a Changelog“, Version in `metadata.json` synchron;
      erster Eintrag ist `1.0.0` (erstes Release).
- [x] Ein Skript (oder eine GitHub Action) überträgt bei einem Release
      `bl-plugins/podcast/` und `public/` ins öffentliche Repo, setzt
      `releaseDate` in `metadata.json`, legt Tag und GitHub-Release mit
      `podcast-<version>.zip` an.
- [x] README verlinkt auf die Releases als bevorzugten Installationsweg.
- [ ] Mit dem Release 1.0 ist dieses Repository privat; Update-Befehl für Tests
      mit Lesetoken dokumentiert (Installationstest, Vorbereitung — erledigt
      2026-11-02, GitHub-API mit fine-grained Token, „Contents: Read-only“).

**Umsetzung (Teil, 2026-11-02):** `CHANGELOG.md` auf Englisch, weil es mit
ins öffentliche Repo und in die Release-Texte geht; Änderungen stehen bis zum
Release unter `## [Unreleased]`. `scripts/release.sh` in zwei Schritten:
`prepare [<version>]` setzt Version, `releaseDate` und den CHANGELOG-Abschnitt
auf dem Branch `release/<version>` (PR, CI, Merge — der Release-Commit läuft
durch die CI); `publish` baut auf `main` `podcast-<version>.zip` per
`git archive` nur aus `bl-plugins/podcast/` (versionierte Dateien, keine
Entwicklungsdateien), ersetzt den Inhalt des öffentlichen Repos durch
`podcast/`, `public/` und `CHANGELOG.md` (ein Commit je Version), setzt dort und
hier das Tag `v<version>` und legt per `gh` das Release mit dem ZIP und dem
CHANGELOG-Abschnitt an. Veröffentlicht wird nur mit Lizenz (`license` in
`metadata.json` und `bl-plugins/podcast/LICENSE`), grüner CI auf dem Commit und
Schreibrecht; `--dry-run` baut alles lokal und warnt nur. `ReleaseTest` prüft,
dass `metadata.json` und CHANGELOG zusammenpassen. Getestet in einer Sandbox
(lokales origin, nachgebautes öffentliches Repo, `gh`-Attrappe); der erste
echte Lauf ist das Release 1.0.0. READMEs (2026-11-02): öffentliche README
mit Installation aus dem Release, Update-Hinweis und den neuen Funktionen;
Plugin-README mit den englischen Bezeichnungen der Oberfläche, Abschnitt
„Updating the plugin“, ohne Hinweise auf Entwicklungsversionen und interne
Skripte. Lizenz MIT (Lizenzabschnitte beider READMEs). Offen: Release
ausführen, Privatstellen.

**Betroffene Dateien**

- neu: `public/README.md`, `public/*.png`, `CHANGELOG.md`, Release-Skript bzw.
  `.github/workflows/release.yml`; `bl-plugins/podcast/metadata.json`
