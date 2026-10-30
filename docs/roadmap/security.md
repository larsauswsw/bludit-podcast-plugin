# Sicherheit

Absicherung der Episoden-API und der Uploads. Siehe [README.md](./README.md) für
Status-/Prioritäts-Legende.

## 010 · CSRF-Schutz: Episoden-API in `adminController()` verlagern

Status: idea · Priorität: hoch · Aufwand: M · Bereich: `plugin.php` (`beforeAll()`, `adminView()`, `handleApiRequest()`), `podcast-admin.js` · Issue: [#11](https://github.com/larsauswsw/bludit-podcast-plugin/issues/11)

**Beschreibung**

Die Episoden-API (Speichern, Löschen, Upload) läuft im **Frontend**-Hook
`beforeAll()` (`plugin.php:229–251`). Das JS schickt zwar `tokenCSRF` mit, der
Token wird aber **nirgends geprüft**: Die CSRF-Prüfung von Bludit
(`bl-kernel/boot/rules/99.security.php`) wird nur im Admin-Bootstrap geladen,
nicht in `bl-kernel/boot/site.php`. Schutz besteht derzeit nur indirekt über das
`SameSite=Lax`-Session-Cookie — das hilft nicht bei Angriffen von derselben
Site (z. B. Subdomains, andere Plugins mit XSS) und ist kein bewusst gewählter
Mechanismus.

Bludit bietet seit 3.13 einen offiziellen Weg (Doku „Admin panel: Controller and
View via plugins“): `adminController()` wird für `/admin/plugin/podcast`
aufgerufen, **nachdem** Session, Login und CSRF-Prüfung gelaufen sind.

**Akzeptanzkriterien**

- [ ] Speichern/Löschen/Upload laufen als POST auf `DOMAIN_ADMIN . 'plugin/podcast'`
      und werden in `adminController()` verarbeitet (JSON-Antwort + `exit`).
- [x] Ohne gültigen `tokenCSRF` wird nichts geändert (vorgezogen: Prüfung per
      `$security->validateTokenCSRF()` in `beforeAll()`, getestet).
- [ ] Die Rollenprüfung (`admin`, `editor`) bleibt erhalten, nutzt
      `checkRole(['admin','editor'], false)`.
- [ ] `beforeAll()` enthält nur noch die Feed-Auslieferung.
- [x] Fehlermeldungen geben keine Server-Pfade preis; unerwartete Fehler landen
      im Bludit-Log.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`

---

## 011 · Upload- und Eingabe-Härtung

Status: idea · Priorität: mittel · Aufwand: S · Bereich: `plugin.php` (`handleUpload()`, `handleSaveEpisode()`, Metadaten-Helfer) · Issue: [#12](https://github.com/larsauswsw/bludit-podcast-plugin/issues/12)

**Beschreibung**

- Uploads werden nur anhand der Dateiendung geprüft (`plugin.php:915–929`),
  nicht anhand des Inhalts (MIME-Typ).
- `audio_url` und `image_url` werden ungeprüft übernommen (`plugin.php:790`,
  `811`) — auch `javascript:`- oder `file:`-URLs.
- Metadaten-Felder (`itunes_episode`, `itunes_season`, `itunes_type`,
  `itunes_explicit`, `itunes_duration`) werden nicht auf erlaubte Werte geprüft.
- `episodes.json` wird ohne Sperre gelesen und geschrieben
  (`plugin.php:1026–1045`) — zwei gleichzeitige Speichervorgänge können
  Einträge verlieren.
- Upload-Verzeichnisse bekommen nur eine `index.php` gegen Directory-Listing,
  aber keine Sperre gegen Skriptausführung.

**Akzeptanzkriterien**

- [ ] MIME-Prüfung per `finfo` (Audio: `audio/*`; Bilder: `image/jpeg`, `image/png`).
- [x] Nur `http(s)://`-URLs werden akzeptiert.
- [ ] Episode/Staffel sind positive Ganzzahlen, Typ ∈ `full|trailer|bonus`,
      Explicit ∈ `true|false`, Dauer im Format `HH:MM:SS`, `MM:SS` oder Sekunden.
- [ ] Schreiben der Metadaten mit `LOCK_EX` (bzw. entfällt mit 012).
- [ ] Upload-Ordner enthalten eine `.htaccess`, die Skriptausführung verbietet;
      README nennt das Nginx-Äquivalent.
- [ ] Hochgeladene Dateien bekommen einen zufälligen Namensanteil
      (`bin2hex(random_bytes(8))`), damit Audio unveröffentlichter Episoden nicht
      über den Dateinamen erraten werden kann.
- [x] Alle Request-Parameter werden auf Typ `string` geprüft; Arrays
      (`title[]=…`) führen zu einer sauberen Fehlermeldung statt zu einem
      PHP-Fatal-Error.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/README.md`

---

## 039 · Pfad-Manipulation über ungeprüften Seitenschlüssel

Status: done · Priorität: hoch · Aufwand: S · Bereich: `plugin.php` (`beforeAll()`, `handleSaveEpisode()`) · Issue: [#42](https://github.com/larsauswsw/bludit-podcast-plugin/issues/42)

**Beschreibung**

Gefunden bei der Sicherheitsprüfung am 2026-10-30, behoben in
[#41](https://github.com/larsauswsw/bludit-podcast-plugin/pull/41) vor dem
ersten Release.

`handleSaveEpisode()` reichte den Parameter `key` nur HTML-escaped an
`$pages->edit()` weiter (`plugin.php:770`, `837–843` im Stand `7e71b6f`).
`Pages::edit()` prüft die Existenz des Schlüssels nicht — das macht erst der
Helfer `editPage()` — und verschiebt bei geändertem Schlüssel das
Seitenverzeichnis per `Filesystem::mv(PATH_PAGES . $key, PATH_PAGES . $newKey)`.
Mit `../` im Schlüssel ließen sich so beliebige vom Webserver beschreibbare
Dateien verschieben, z. B. `bl-content/databases/site.php`. Fehlt diese Datei,
leitet Bludits `index.php` auf `install.php` um, und die Website kann von
jedem neu eingerichtet werden (inkl. neuem Admin-Passwort).

Voraussetzung: angemeldeter Nutzer mit Rolle `editor` oder `admin`. Da das
CSRF-Token nicht geprüft wurde, reichte auch eine Anfrage von derselben Site
im Browser eines solchen Nutzers (Cross-Site-Anfragen blockierte das
`SameSite=Lax`-Cookie).

**Akzeptanzkriterien**

- [x] Bearbeiten nur, wenn `$pages->exists($key)` und die Seite den Tag
      `podcast` hat; geprüft vor jedem Upload.
- [x] Speichern über `editPage()`; schlägt es fehl, gibt es eine Fehlermeldung
      und keine Metadaten-Änderung.
- [x] CSRF-Token wird für alle API-Aktionen geprüft.
- [x] Parameter werden nur als String akzeptiert, URLs nur mit `http(s)`.
- [x] Gegenprobe: Der alte Code reicht `../databases/site.php` auch ohne Token
      an `$pages->edit()` durch, der neue lehnt ihn ab (Stub-Harness).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`
