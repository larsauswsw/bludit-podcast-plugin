# Fehler & Bludit-API-Konformität

Fehler, die im Review gegen den Bludit-3.22-Quellcode und die Entwickler-Doku
gefunden wurden. Alle Punkte sind mit Code-Stellen belegt. Siehe
[README.md](./README.md) für Status-/Prioritäts-Legende.

## 001 · Doppeltes HTML-Escaping in Feed, Formularen und Titeln

Status: idea · Priorität: hoch · Aufwand: S · Bereich: `plugin.php` (`form()`, `outputFeed()`, `renderEpisodeSection()`, `handleSaveEpisode()`) · Issue: [#2](https://github.com/larsauswsw/bludit-podcast-plugin/issues/2)

**Beschreibung**

Bludit speichert Texte bereits HTML-escaped: `Plugin::post()` und
`Pages::add()/edit()` rufen intern `Sanitize::html()` auf, `getValue()` und
`$page->title()` liefern den escapten Wert zurück. Das Plugin escaped an
mehreren Stellen zusätzlich:

- `handleSaveEpisode()` ruft `Sanitize::html($post['title'])` auf
  (`plugin.php:766`), danach escaped `Pages::add()` noch einmal →
  „Tom & Jerry“ wird als `Tom &amp;amp; Jerry` gespeichert.
- `outputFeed()` escaped Kanal- und Episodenwerte erneut mit
  `htmlspecialchars(..., ENT_XML1)` (`plugin.php:389–397`, `450`) → im Feed
  steht `Tom &amp;amp;amp; Jerry`; die Kategorie „Society & Culture“ wird zu
  `Society &amp;amp; Culture` und ist damit **keine gültige Apple-Kategorie**.
- `form()` gibt `htmlspecialchars($this->getValue(...))` in `value="…"` aus
  (`plugin.php:134–179`), die Edit-Buttons ebenso in `data-*`-Attributen
  (`plugin.php:588–598`) → bei **jedem Speichern** kommt eine weitere
  Escape-Ebene dazu (fortschreitende Datenkorruption).

Nachgestellt mit der Kernel-Logik (`Sanitize::html` + Plugin-Code):
`Tom & Jerry` → gespeichert `Tom &amp;amp; Jerry` → Feed `Tom &amp;amp;amp; Jerry`.

**Akzeptanzkriterien**

- [ ] Rohwerte werden genau einmal escaped: an `createPage()`/`editPage()`
      unescaped übergeben (Bludit escaped selbst).
- [ ] Im Feed: `getValue($field, false)` bzw. `html_entity_decode($page->title())`
      lesen und erst dann mit `htmlspecialchars(..., ENT_XML1)` ausgeben.
- [ ] Im Einstellungsformular `getValue()` direkt ausgeben (wie die
      Core-Plugins, z. B. `bl-plugins/rss/plugin.php`), in `data-*`-Attributen
      den dekodierten Wert escapen.
- [ ] Mit „Tom & Jerry“ und „Society & Culture“ getestet: Website, Admin-Formular
      (mehrfach speichern) und Feed zeigen den Text korrekt; Feed besteht einen
      Validator (z. B. Podbase/Cast Feed Validator).
- [ ] Optional: einmalige Bereinigung bereits mehrfach escapter Titel/Einstellungen.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`
- `bl-plugins/podcast/js/podcast-admin.js` (nur falls Attributwerte anders übergeben werden)

---

## 002 · Episoden mit mehreren Tags werden nicht erkannt

Status: idea · Priorität: hoch · Aufwand: S · Bereich: `plugin.php` (`pageHasPodcastTag()`) · Issue: [#3](https://github.com/larsauswsw/bludit-podcast-plugin/issues/3)

**Beschreibung**

`Page::tags()` liefert ohne Argument einen **kommagetrennten String**
(`implode(',', $tags)`, `bl-kernel/pagex.class.php:258–272`). Das Plugin castet
ihn mit `(array)` (`plugin.php:990`) und vergleicht das einzige Element mit
`'podcast'`. Hat eine Episode zusätzlich einen weiteren Tag (z. B. im
Bludit-Editor ergänzt), ist das Element `"podcast,Technik"` → die Episode
verschwindet aus Feed, Admin-Liste und Player.

**Akzeptanzkriterien**

- [ ] `pageHasPodcastTag()` nutzt `$page->tags(true)` (Array `tagKey => Name`)
      und prüft `isset($tags['podcast'])`.
- [ ] Die Sonderbehandlung für Tag-Objekte (gibt es in Bludit 3.x nicht) entfällt.
- [ ] Eine Episode mit den Tags „podcast, Technik“ erscheint im Feed, in der
      Admin-Liste und bekommt den Player.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 003 · Seiten über `createPage()`/`editPage()`/`deletePage()` verwalten

Status: idea · Priorität: hoch · Aufwand: S · Bereich: `plugin.php` (`handleSaveEpisode()`, `handleDeleteEpisode()`) · Issue: [#4](https://github.com/larsauswsw/bludit-podcast-plugin/issues/4)

**Beschreibung**

Das Plugin ruft die Low-Level-Methoden `$pages->add()`, `$pages->edit()` und
`$pages->delete()` direkt auf (`plugin.php:840`, `846`, `872`). Bludit kapselt
diese in `createPage()`, `editPage()` und `deletePage()`
(`bl-kernel/functions.php`), die zusätzlich:

- den **Autor** setzen (`$args['username'] = Session::get('username')`) — ohne
  ihn ist `username` leer, `$page->user()` liefert `false` und Themes, die den
  Autor anzeigen, zeigen nichts oder werfen Fehler,
- `reindexTags()`/`reindexCategories()` aufrufen — ohne das bleibt
  `/tag/podcast` leer bzw. veraltet,
- die Hooks `afterPageCreate`/`afterPageModify`/`afterPageDelete` auslösen —
  ohne sie werden u. a. das Core-RSS-Plugin und die Sitemap nicht aktualisiert,
- einen Eintrag im System-Log schreiben.

Bludits eigenes REST-API-Plugin (`bl-plugins/api/plugin.php`) nutzt ebenfalls
`createPage()`/`editPage()`.

**Akzeptanzkriterien**

- [ ] Anlegen/Bearbeiten/Löschen laufen über `createPage()`, `editPage()`,
      `deletePage()`; Rückgabewert `false` wird als Fehler gemeldet.
- [ ] Neue Episoden haben den angemeldeten Nutzer als Autor.
- [ ] `/tag/podcast` listet neue Episoden sofort; `rss.xml`/`sitemap.xml` des
      Core-Plugins enthalten sie.
- [ ] Im Bludit-Systemlog erscheinen Einträge für Anlegen/Bearbeiten/Löschen.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 004 · Unterverzeichnis-Installationen unterstützen (`DOMAIN` → `DOMAIN_BASE`/`webhook()`)

Status: idea · Priorität: hoch · Aufwand: S · Bereich: `plugin.php` (`adminView()`, `beforeAll()`, `handleUpload()`) · Issue: [#5](https://github.com/larsauswsw/bludit-podcast-plugin/issues/5)

**Beschreibung**

`DOMAIN` enthält nur Schema + Host (`Site::domain()`), **ohne** den Pfad einer
Installation in einem Unterordner (z. B. `https://example.com/blog/`). Damit
brechen bei Unterordner-Installationen:

- die API-URL `PODCAST_API_URL = DOMAIN` (`plugin.php:104`, `115`),
- die Einbindung `DOMAIN . '/bl-plugins/podcast/js/podcast-admin.js'` (`plugin.php:116`),
- die Upload-URLs `DOMAIN . '/bl-content/uploads/podcast/…'` (`plugin.php:920`, `924`),
- der Feed-Pfadvergleich gegen `REQUEST_URI` (`plugin.php:215`, `254–260`).

**Akzeptanzkriterien**

- [ ] JS über `$this->includeJS('podcast-admin.js')` bzw. `$this->domainPath()`
      einbinden (inkl. Cache-Busting über `?version=`).
- [x] Upload-URLs aus `DOMAIN_UPLOADS` bilden (mit 011).
- [ ] Feed über `$this->webhook($feedSlug)` erkennen (wie `bl-plugins/rss`).
- [x] API-URL aus `DOMAIN_BASE` (mit S6; nach 010 ggf. `DOMAIN_ADMIN . 'plugin/podcast'`).
- [ ] Getestet mit einer Installation unter `/blog/`: Feed, Speichern, Upload
      und Player funktionieren.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 005 · Episoden-Metadaten an UUID statt Page-Key binden (Slug-Änderung, GUID)

Status: done · Priorität: hoch · Aufwand: M · Bereich: `plugin.php` (Metadaten-Helfer, `outputFeed()`) · Issue: [#6](https://github.com/larsauswsw/bludit-podcast-plugin/issues/6)

**Beschreibung**

`episodes.json` ist nach dem **Page-Key** (= Slug) indiziert. Ändert jemand im
Bludit-Editor den Slug oder die Elternseite, ändert sich der Key
(`Pages::edit()` → `generateKey()`), die Metadaten verwaisen und die Episode
verschwindet ohne Fehlermeldung aus Feed und Player. Gleichzeitig ist der Key
die `<guid>` im Feed (`plugin.php:454`) — ein neuer Slug erzeugt eine neue GUID,
Podcast-Apps laden die Episode doppelt. Bludit vergibt pro Seite eine stabile
`uuid`, die auch das Core-RSS-Plugin als GUID nutzt.

**Akzeptanzkriterien**

Umgesetzt zusammen mit 012: Die Metadaten liegen als Custom Fields in der Seite
und wandern bei einer Slug-Änderung automatisch mit — ein eigener Index nach
UUID ist damit überflüssig.

- [x] ~~Metadaten werden nach `$page->uuid()` indiziert~~ — ersetzt durch Custom
      Fields (012); Altdaten werden beim ersten Öffnen der Podcast-Seite übernommen.
- [x] Die GUID im Feed ist die Seiten-UUID (`$page->uuid()`, von Bludit beim
      Anlegen vergeben und unveränderlich). Da noch kein Feed veröffentlicht ist,
      braucht es keinen Übergang für bestehende Abonnenten.
- [x] Slug-Änderung im Bludit-Editor: Episode bleibt im Feed, GUID bleibt gleich.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 006 · Datenverlust bei Plugin-Update: `episodes.json` aus dem Plugin-Ordner verlagern

Status: done · Priorität: hoch · Aufwand: S · Bereich: `plugin.php` (`install()`, `episodesFile()`), Repo · Issue: [#7](https://github.com/larsauswsw/bludit-podcast-plugin/issues/7)

**Beschreibung**

Alle Episoden-Metadaten (Audio-URL, Dauer, Nummer …) liegen in
`bl-plugins/podcast/metadata/episodes.json` — also im Code-Ordner des Plugins.
Das Repo liefert diese Datei mit `[]` aus. Wer laut README „den `podcast`-Ordner
nach `bl-plugins/` kopiert“, **überschreibt beim Update alle Metadaten**; alle
Episoden fallen aus dem Feed. Außerdem ist `bl-plugins/` bei vielen Backups nicht
dabei, `bl-content/` schon. Bludit sieht für Plugin-Daten
`$this->workspace()` (`bl-content/workspaces/<plugin>/`) vor.

Achtung: `Plugin::uninstall()` löscht den Workspace beim Deaktivieren.

**Akzeptanzkriterien**

Umgesetzt zusammen mit 012 (statt Workspace direkt Custom Fields).

- [x] Metadaten liegen in `bl-content/` (Custom Fields in der Seiten-Datenbank),
      `metadata/episodes.json` ist aus dem Repo entfernt und in `.gitignore`.
- [x] Eine vorhandene alte Datei (eigene Testinstallation) wird beim ersten
      Öffnen der Podcast-Seite übernommen und danach gelöscht.
- [x] Der neue Speicherort ist per Webserver nicht abrufbar: Seiten-Datenbank
      und Backup sind `.php`-Dateien mit Bludits Schutzzeile — unabhängig von
      `.htaccess`, also auch unter Nginx.
- [x] Deaktivieren/Reaktivieren vernichtet keine Daten (sie liegen in den Seiten).
- [x] README beschreibt den sicheren Update-Weg.
- [x] Durch 012 (Custom Fields) ersetzt.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/metadata/episodes.json`,
  `.gitignore`, `bl-plugins/podcast/README.md`

---

## 007 · Bearbeiten überschreibt Tags, Seitentyp und Inhalt

Status: idea · Priorität: mittel · Aufwand: S · Bereich: `plugin.php` (`handleSaveEpisode()`, `renderEpisodeSection()`), `podcast-admin.js` · Issue: [#8](https://github.com/larsauswsw/bludit-podcast-plugin/issues/8)

**Beschreibung**

Beim Bearbeiten über die Plugin-Oberfläche gehen Änderungen verloren, die im
Bludit-Editor gemacht wurden:

- `tags => 'podcast'` (`plugin.php:833`) ersetzt **alle** vorhandenen Tags.
- Der Status kennt nur `published`/`draft` (`plugin.php:768`) — Seiten vom Typ
  `sticky`, `static` oder `scheduled` werden beim Speichern zu `published`;
  `scheduled` wird in der Liste als „Entwurf“ angezeigt (`plugin.php:580–584`),
  und `pe-status` bekommt einen Wert, den das `<select>` nicht kennt.
- Das Formular lädt `$ep->content()` (`plugin.php:590`), also das bereits
  **gerenderte HTML** statt `contentRaw()` → Markdown wird bei jedem Speichern in
  HTML umgewandelt.
- Ein geleerter Beschreibungstext wird nicht gespeichert, weil `Pages::edit()`
  leeren Inhalt ignoriert.

**Akzeptanzkriterien**

- [ ] Bestehende Tags bleiben erhalten, `podcast` wird nur ergänzt.
- [ ] Der Status bildet `published`, `draft`, `scheduled` (und unverändert
      `sticky`/`static`) korrekt ab und wird korrekt angezeigt.
- [x] Das Formular nutzt `contentRaw()`; Markdown bleibt nach mehrfachem Speichern
      unverändert (vorgezogen nach Test auf echter Installation).
- [ ] Verhalten bei leerer Beschreibung ist definiert (Pflichtfeld laut Spec).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`

---

## 008 · Upload-Fehler klar melden statt stumm verwerfen

Status: idea · Priorität: mittel · Aufwand: S · Bereich: `plugin.php` (`beforeAll()`, `handleUpload()`), `podcast-admin.js` · Issue: [#9](https://github.com/larsauswsw/bludit-podcast-plugin/issues/9)

**Beschreibung**

- `handleUpload()` gibt bei zu großer Datei oder falscher Endung still `''`
  zurück (`plugin.php:911–913`, `927–929`). Beim Audio kommt dann die
  irreführende Meldung „Audio-URL oder -Datei ist erforderlich“, ein Bild wird
  kommentarlos verworfen.
- Die `post_max_size`-Prüfung in `beforeAll()` (`plugin.php:218–227`) greift bei
  **jedem** zu großen POST auf **jede** Frontend-URL und antwortet mit
  Podcast-JSON — das kapert auch Formulare anderer Plugins.
- `podcast-admin.js` ruft `r.json()` ohne Statusprüfung auf; eine PHP-Fehlerseite
  wird als „Netzwerkfehler“ gemeldet.

**Akzeptanzkriterien**

- [x] Größen- und Typfehler liefern eine eigene, verständliche Fehlermeldung
      (inkl. erlaubter Endungen und Limit) — mit 011 umgesetzt.
- [x] Die `post_max_size`-Erkennung greift nur für Podcast-API-Requests
      (z. B. Erkennung über URL/Query-Parameter, da `$_POST` dann leer ist),
      antwortet erst nach der Login-Prüfung und behandelt `post_max_size = 0`
      (unbegrenzt) korrekt (vorher wurde dann jeder Frontend-POST abgewiesen).
      Umgesetzt mit S6: Kennung `?podcast_api=1` in der API-URL, nur bei POST.
- [ ] Das JS prüft `response.ok`/Content-Type und zeigt bei Nicht-JSON eine
      sinnvolle Meldung.
- [ ] Das Upload-Limit im Formular zeigt das Minimum aus Plugin-Einstellung,
      `upload_max_filesize` und `post_max_size`.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`

---

## 009 · Datum/Zeitzone im Episodenformular korrigieren

Status: idea · Priorität: niedrig · Aufwand: S · Bereich: `podcast-admin.js`, `plugin.php` (`handleSaveEpisode()`) · Issue: [#10](https://github.com/larsauswsw/bludit-podcast-plugin/issues/10)

**Beschreibung**

`new Date().toISOString().slice(0, 16)` (`podcast-admin.js:59`, `133`) liefert
**UTC**, nicht die lokale Zeit — „Neue Episode“ ist in Deutschland 1–2 Stunden
in der Vergangenheit vorbelegt. Serverseitig fällt ein ungültiges Datum über
`date('…', strtotime(...))` (`plugin.php:773`) auf 1970 zurück, statt einen
Fehler zu melden. Zukunftsdaten machen die Episode (korrekt) zu `scheduled`,
das wird aber nirgends angezeigt (siehe 007).

**Akzeptanzkriterien**

- [ ] Vorbelegung nutzt die lokale Zeit des Browsers bzw. die Bludit-Zeitzone.
- [ ] Ungültiges Datum führt zu einer Fehlermeldung statt zu 1970.
- [ ] Nach dem Speichern mit Zukunftsdatum zeigt die Liste „geplant“ mit Datum.

**Betroffene Dateien**

- `bl-plugins/podcast/js/podcast-admin.js`, `bl-plugins/podcast/plugin.php`
