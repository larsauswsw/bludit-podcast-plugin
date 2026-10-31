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

Die Liste zeigt alle Episoden ohne Pagination. Status-Badges nutzen
Bootstrap-3-Klassen (`label label-success`, `btn-xs`, `btn-default`), das
Admin-Theme „booty“ nutzt Bootstrap 4 — die Badges erscheinen ungestylt. Es gibt
keinen Hinweis, ob eine Episode im Feed landet (z. B. fehlende Audio-URL,
`scheduled`, fehlender Podcast-Tag).

**Akzeptanzkriterien**

- [ ] Bootstrap-4-Klassen (`badge badge-success`, `btn-sm`, `btn-secondary`).
- [ ] Status „veröffentlicht / geplant / Entwurf“ korrekt (siehe 007).
- [ ] Spalte bzw. Icon „im Feed“ mit Tooltip, warum nicht.
- [ ] Suche nach Titel, Filter nach Staffel/Status, Pagination ab ~25 Einträgen.
- [ ] Links „Seite ansehen“ und „Im Bludit-Editor öffnen“.

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
