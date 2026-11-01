# Admin-Oberfläche & Workflow

Komfort beim Anlegen und Pflegen von Episoden. Siehe [README.md](./README.md)
für Status-/Prioritäts-Legende.

## 020 · Upload-Fortschritt & Chunked Upload für große Audiodateien

Status: idea · Priorität: mittel · Aufwand: M · Bereich: `podcast-admin.js`, `plugin.php` (Upload-Handling) · Issue: [#21](https://github.com/larsauswsw/bludit-podcast-plugin/issues/21)

**Beschreibung**

Audiodateien sind oft 50–300 MB groß. Der Upload läuft per `fetch` ohne
Fortschrittsanzeige — bei langsamen Leitungen sieht es minutenlang wie ein
Hänger aus. Außerdem muss der Server-Admin `upload_max_filesize` und
`post_max_size` hochsetzen (README, Abschnitt 4), was bei Shared Hosting oft
nicht geht.

**Akzeptanzkriterien**

- [ ] Fortschrittsbalken (Prozent, MB) per `XMLHttpRequest.upload.onprogress`.
- [ ] Optional: Upload in Stücken (z. B. 5 MB), serverseitig zusammengesetzt —
      funktioniert dann auch mit den PHP-Standardlimits.
- [ ] Abbrechen-Button; abgebrochene Teil-Uploads werden aufgeräumt.

**Betroffene Dateien**

- `bl-plugins/podcast/js/podcast-admin.js`, `bl-plugins/podcast/plugin.php`

---

## 021 · Dauer & Dateigröße automatisch aus der Audiodatei ermitteln

Status: idea · Priorität: mittel · Aufwand: M · Bereich: `plugin.php` (`handleSaveEpisode()`), `podcast-admin.js` · Issue: [#22](https://github.com/larsauswsw/bludit-podcast-plugin/issues/22)

**Beschreibung**

Die Dauer muss von Hand im Format `HH:MM:SS` eingetragen werden und wird nicht
geprüft; bei externen URLs fehlt die Dateigröße (siehe 016). Beides lässt sich
ermitteln: im Browser über ein `<audio>`-Element (`loadedmetadata` →
`duration`) vor dem Upload, oder serverseitig z. B. mit getID3.

**Akzeptanzkriterien**

- [ ] Nach Auswahl einer Datei bzw. Eingabe einer URL wird die Dauer automatisch
      vorgeschlagen (überschreibbar).
- [ ] Dauer wird validiert und einheitlich gespeichert.
- [ ] Bei lokalen Dateien wird `audio_length` immer aus `filesize()` gesetzt,
      bei externen per `HEAD`-Request — mit demselben SSRF-Schutz wie in 016
      (gemeinsame Hilfsfunktion).
- [ ] Serverseitige Analyse (z. B. getID3) läuft nur auf bereits validierten
      Uploads; die Bibliothek ist in fester, aktueller Version eingebunden.

**Betroffene Dateien**

- `bl-plugins/podcast/js/podcast-admin.js`, `bl-plugins/podcast/plugin.php`

---

## 022 · Kanal-Cover per Upload & Bildvalidierung

Status: idea · Priorität: mittel · Aufwand: S · Bereich: `plugin.php` (`form()`, `post()`, `handleUpload()`) · Issue: [#23](https://github.com/larsauswsw/bludit-podcast-plugin/issues/23)

**Beschreibung**

Die Spec sieht für das Podcast-Cover „URL oder Upload“ vor, die
Kanaleinstellungen bieten aber nur ein URL-Feld. Apple verlangt quadratische
Cover mit 1400–3000 px Kantenlänge (JPG/PNG, RGB) — das wird weder beim Kanal-
noch beim Episodenbild geprüft.

**Akzeptanzkriterien**

- [ ] Cover-Upload in den Kanaleinstellungen (eigene `post()`-Implementierung,
      ruft `parent::post()` auf) mit Vorschaubild.
- [ ] Prüfung per `getimagesize()`: quadratisch, 1400–3000 px; Warnung statt
      harter Ablehnung bei Episodenbildern.
- [ ] Vorschaubild auch im Episodenformular.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 023 · Nächste Episodennummer automatisch vorschlagen

Status: idea · Priorität: niedrig · Aufwand: S · Bereich: `plugin.php` (`renderEpisodeForm()`), `podcast-admin.js` · Issue: [#24](https://github.com/larsauswsw/bludit-podcast-plugin/issues/24)

**Beschreibung**

Bei „Neue Episode“ sind Nummer und Staffel leer. Sinnvoll ist: höchste
vorhandene Episodennummer + 1 und die zuletzt verwendete Staffel vorbelegen.
Doppelte Nummern innerhalb einer Staffel sollten eine Warnung auslösen.

**Akzeptanzkriterien**

- [ ] Neue Episode: Nummer = max + 1, Staffel = Staffel der neuesten Episode.
- [ ] Beim Speichern Warnung bei doppelter Nummer in derselben Staffel.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`

---

## 024 · Episodenliste: Status, Suche, Filter, Warnungen

Status: idea · Priorität: niedrig · Aufwand: M · Bereich: `plugin.php` (`renderEpisodeSection()`) · Issue: [#25](https://github.com/larsauswsw/bludit-podcast-plugin/issues/25)

**Beschreibung**

Die Liste zeigt alle Episoden ohne Pagination. Es gibt keinen Hinweis, ob eine
Episode im Feed landet (z. B. fehlende Audio-URL, `scheduled`, fehlender
Podcast-Tag). Aufbau im Bludit-Stil, Bootstrap-4-Badges und die Links
„Anschauen“/„Im Bludit-Editor öffnen“ kamen mit 042.

**Akzeptanzkriterien**

- [x] Bootstrap-4-Klassen (mit 042).
- [ ] Status „veröffentlicht / geplant / Entwurf“ korrekt (siehe 007; die Liste
      zeigt „geplant“ seit 042, das Formular kennt den Typ noch nicht).
- [ ] Spalte bzw. Icon „im Feed“ mit Tooltip, warum nicht.
- [ ] Suche nach Titel, Filter nach Staffel/Status, Pagination ab ~25 Einträgen.
- [x] Links „Seite ansehen“ und „Im Bludit-Editor öffnen“ (mit 042).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 025 · Medienverwaltung im Admin: Übersicht, aufräumen, wiederverwenden

Status: idea · Priorität: mittel · Aufwand: M · Bereich: `plugin.php` (Admin-View, `handleDeleteEpisode()`, `handleUpload()`), `podcast-admin.js` · Issue: [#26](https://github.com/larsauswsw/bludit-podcast-plugin/issues/26)

**Beschreibung**

Hochgeladene Audio- und Bilddateien bleiben in `bl-content/uploads/podcast/`
liegen, wenn eine Episode gelöscht oder ihre Datei ersetzt wird — bei Audio
summiert sich das schnell auf Gigabytes. Aufräumen geht bisher nur per Shell.
Beim Test auf einer echten Installation (2026-10-31) wurden dabei mit
`rm -rf *` auch die Schutzdateien (`index.php`, `.htaccess`) gelöscht; das
Plugin legt sie zwar vor dem nächsten Upload neu an, bis dahin ist der Ordner
aber ungeschützt. Umgekehrt lässt sich eine schon hochgeladene Datei nicht
erneut auswählen.

Idee: ein eigener Bereich „Medien“ unter Admin → Podcast.

**Akzeptanzkriterien**

- [ ] Bereich „Medien“ (Rollen `admin`, `editor`): Liste aller Dateien in
      `uploads/podcast/audio/` und `images/` mit Name, Typ, Größe, Datum und
      Verwendung (welche Episode(n), ermittelt über die Custom Fields) sowie
      die belegte Gesamtgröße.
- [ ] Filter „Unbenutzt“; Einzel- und Sammel-Löschen für unbenutzte Dateien.
      Benutzte Dateien lassen sich nicht löschen, ohne die Episode anzupassen.
- [ ] Beim Löschen einer Episode oder beim Ersetzen ihrer Datei Rückfrage
      „Zugehörige Dateien ebenfalls löschen?“ — nur für Dateien, die keine
      andere Episode nutzt.
- [ ] Im Episodenformular eine vorhandene Datei aus der Mediathek wählen (Audio
      und Bild); Dateigröße und Dauer (sofern bekannt) werden übernommen.
- [ ] Optional: Datei direkt in der Mediathek hochladen oder ersetzen.
- [ ] Schutzdateien (`index.php`, `.htaccess`) werden nie angezeigt oder
      gelöscht; fehlen sie, legt das Öffnen der Medienseite sie neu an
      (`prepareUploadDir()`).
- [ ] Sicherheit (`CLAUDE.md`): Löschen nur per POST mit CSRF-Token und
      Rollenprüfung; Dateinamen nur über `basename()` und die Endungs-Allowlist
      aus `UPLOAD_TYPES`, Pfade per `realpath()` gegen den Upload-Ordner geprüft;
      Fehlermeldungen ohne Server-Pfade.
- [ ] README empfiehlt, Dateien über die Medienverwaltung statt per Shell
      aufzuräumen.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`,
  `bl-plugins/podcast/README.md`

---

## 026 · Shownotes-Editor mit Markdown & Vorschau

Status: idea · Priorität: niedrig · Aufwand: M · Bereich: `plugin.php` (`renderEpisodeForm()`), `podcast-admin.js` · Issue: [#27](https://github.com/larsauswsw/bludit-podcast-plugin/issues/27)

**Beschreibung**

Shownotes sind ein schlichtes `<textarea>`. Links, Zeitmarken und Listen sind
für Shownotes typisch. Bludit bringt mit EasyMDE und TinyMCE eigene Editoren als
Plugins mit; alternativ reicht ein Link „Im Bludit-Editor öffnen“
(`/admin/edit-content/<key>`), sobald die Metadaten den Editor-Weg vertragen
(siehe 012).

**Akzeptanzkriterien**

- [ ] Markdown-Unterstützung mit Vorschau (oder Nutzung des aktiven Bludit-Editors).
- [ ] Zeitmarken (`12:34`) werden im Frontend zu Sprungmarken im Player (mit 030).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`

---

## 027 · Einreichung durch Autoren mit Freigabe-Workflow

Status: idea · Priorität: niedrig · Aufwand: L · Bereich: `plugin.php` (Rollen, Admin-View) · Issue: [#28](https://github.com/larsauswsw/bludit-podcast-plugin/issues/28)

**Beschreibung**

Die Vorversion des Plugins (vor der Umstrukturierung im April 2026) hatte eine
Einreichung für weitere Rollen inkl. Sidebar-Widget mit Rollensteuerung. Aktuell
dürfen nur `admin` und `editor` Episoden verwalten. Für Podcasts mit mehreren
Beteiligten wäre ein Workflow sinnvoll: `author` legt Entwürfe an, `editor`/
`admin` gibt frei.

**Akzeptanzkriterien**

- [ ] Einstellbare Rollen, die Episoden anlegen dürfen (Standard: admin, editor).
- [ ] Rollen ohne Freigaberecht können nur Entwürfe anlegen und nur eigene
      Episoden bearbeiten.
- [ ] Liste „Zur Freigabe“ für `editor`/`admin`.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`

---

## 042 · Admin-Oberfläche im Bludit-Look und mobil nutzbar

Status: done · Priorität: hoch · Aufwand: M · Bereich: `plugin.php` (`adminView()`, `form()`, `renderEpisodeSection()`, `renderEpisodeForm()`), `podcast-admin.js`, `css/podcast-admin.css` · Issue: [#57](https://github.com/larsauswsw/bludit-podcast-plugin/issues/57)

**Beschreibung**

Das Plugin soll sich in den Bludit-Admin einfügen statt wie ein Fremdkörper
auszusehen, und auf dem Smartphone bedienbar sein. Beim Test auf einem iPhone
(2026-11-01) waren die Auswahlfelder (Typ, Explicit, Status) abgeschnitten, der
„Datei auswählen“-Knopf ragte aus seinem Feld, das Datum saß zu tief, und die
Episodenliste lief rechts aus dem Bildschirm.

Ursache (geprüft gegen Bludit 3.22): Das Admin-Theme „booty“ setzt für
`.form-control` größere Innenabstände und einen 2-px-Rahmen, lässt aber die feste
Bootstrap-4-Höhe stehen. Für Text reicht das, Auswahl-, Datei- und Datumsfelder
werden abgeschnitten. Bludit selbst nutzt dafür `custom-select` und
`custom-file`, in Plugin-Einstellungen schlichte Felder ohne Klassen (gestylt
über `.plugin-form`). Listen (`views/content.php`) zeigen Titel mit Datum
darunter, blenden Nebenspalten auf kleinen Bildschirmen aus
(`d-none d-md-table-cell`) und legen Aktionen in ein ☰-Menü
(`actionsDropdown`). Dazu kamen Bootstrap-3-Klassen (`label-*`, `btn-xs`,
`btn-default`, `radio-inline`), die im Bootstrap-4-Admin ungestylt bleiben.

**Akzeptanzkriterien**

- [x] Seitenkopf wie `Bootstrap::pageTitle()`; „Neue Episode“ und
      „Kanaleinstellungen“ als Links mit Icon wie auf Bludits Benutzerseite,
      kein eigener `container-fluid`.
- [x] Episodenliste nach dem Muster von Bludits Inhaltsliste: Titel als Link
      zum Bearbeiten, Datum (mobil auch Staffel/Folge) darunter, Nebenspalten
      erst ab mittlerer Breite, „Kein Titel“ bei leerem Titel, Status als
      Bootstrap-4-Badge (`Entwurf`, `geplant`), Aktionen im ☰-Menü
      (Anschauen, Bearbeiten, Im Bludit-Editor öffnen, Löschen).
- [x] Episodenformular: Auswahlfelder `custom-select`, Datei-Uploads
      `custom-file` mit Dateinamen-Anzeige, Radio-Buttons
      `form-check form-check-inline`, Hinweise als `form-text`, Buttons
      `btn-primary`/`btn-secondary`; Zahlenfelder auf dem Handy paarweise.
- [x] Kanaleinstellungen (`form()`) im Stil der Bludit-Plugins: Felder ohne
      eigene Klassen, Hinweise als `span.tip`, Einleitung als
      `alert alert-primary`, Link zur Episodenverwaltung korrigiert
      (`admin/plugin/podcast`).
- [x] Keine Inline-Styles mehr für Layout; Ein-/Ausblenden per `hidden`. Eigenes
      CSS nur, wo Bludit keine Lösung hat (Datumsfeld, lange Dateinamen), in
      `css/podcast-admin.css`; Skript und CSS über `domainPath()` mit
      Cache-Version eingebunden.
- [ ] Auf einem echten iPhone/Android-Gerät geprüft (Installationstest,
      Abschnitt „Mobil“).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`,
  `bl-plugins/podcast/css/podcast-admin.css` (neu),
  `docs/superpowers/checklists/2026-10-31-installationstest.md`
