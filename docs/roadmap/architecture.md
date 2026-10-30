# Architektur & Datenhaltung

Größere Umbauten, die das Plugin näher an die Bludit-Konventionen bringen.
Siehe [README.md](./README.md) für Status-/Prioritäts-Legende.

## 012 · Episoden-Metadaten als Bludit Custom Fields speichern

Status: idea · Priorität: mittel · Aufwand: L · Bereich: `plugin.php` (Install, Speichern, Feed, Player), Migration · Issue: [#13](https://github.com/larsauswsw/bludit-podcast-plugin/issues/13)

**Beschreibung**

Die Design-Spec (`docs/superpowers/specs/2026-04-04-podcast-plugin-design.md`)
sah vor, Episoden-Daten als **Custom Fields der Seite** zu speichern; umgesetzt
wurde eine separate `episodes.json`. Bludit unterstützt Custom Fields nativ
(Doku „Custom fields“; `Pages::$dbFields['custom']`, `$page->custom('feld')`,
`$site->customFields()`). Vorteile:

- Daten wandern mit der Seite mit (Slug-Änderung, Backup von `bl-content/`),
  löst 005 und 006 dauerhaft.
- Felder erscheinen im Bludit-Editor unter „Optionen → Custom“ — Episoden lassen
  sich auch dort pflegen.
- Die Bludit-REST-API (`bl-plugins/api`) kann Episoden inklusive Metadaten
  anlegen (z. B. für Automatisierung).

Hinweise: Custom Fields kennen nur `string` und `bool`; `Pages::add()` erwartet,
dass jedes übergebene Feld in `$site->customFields()` definiert ist.

**Akzeptanzkriterien**

- [ ] `install()` ergänzt die vorhandene Custom-Fields-Definition der Site um die
      Podcast-Felder (bestehende Felder bleiben unverändert) und ruft
      `$pages->setCustomFields()` auf.
- [ ] Speichern, Feed und Player lesen/schreiben über `custom` bzw. `$page->custom()`.
- [ ] Vorhandene Daten aus `episodes.json` (eigene Testinstallationen) werden
      einmalig übernommen, danach wird die Datei umbenannt statt gelöscht. Wird
      012 vor dem 1.0-Release umgesetzt, braucht das Plugin keine dauerhafte
      Migrationslogik.
- [ ] Deaktivieren des Plugins löscht keine Episoden-Daten.
- [ ] README dokumentiert die Felder und die Pflege über den Bludit-Editor.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/README.md`

---

## 013 · Feed cachen, HTTP-Caching & Performance

Status: idea · Priorität: mittel · Aufwand: M · Bereich: `plugin.php` (`outputFeed()`, `getEpisodes()`, Metadaten-Helfer) · Issue: [#14](https://github.com/larsauswsw/bludit-podcast-plugin/issues/14)

**Beschreibung**

Podcast-Apps rufen den Feed sehr häufig ab. Aktuell wird er bei **jedem** Abruf
komplett neu gebaut:

- `new Page($key)` für **alle** Seiten der Site (`plugin.php:375–382`).
- `getEpisodeMeta()` liest und dekodiert `episodes.json` bei **jedem** Aufruf neu
  — in `getEpisodes()` sogar im `usort`-Vergleicher, also O(n log n) Dateizugriffe
  (`plugin.php:971–980`).
- `lastBuildDate` ist immer „jetzt“ (`plugin.php:399`), es gibt weder
  `Last-Modified` noch `ETag` → keine `304 Not Modified`.

Das Core-RSS-Plugin zeigt das Bludit-Muster: Feed bei
`afterPageCreate`/`afterPageModify`/`afterPageDelete` und `post()` in den
Workspace schreiben, in `beforeAll()` nur ausliefern.

**Akzeptanzkriterien**

- [ ] Feed wird bei Änderungen neu erzeugt und als Datei ausgeliefert.
- [ ] `lastBuildDate`/`Last-Modified` = jüngste Änderung; `ETag` gesetzt;
      `If-None-Match`/`If-Modified-Since` liefern `304`.
- [ ] Metadaten werden pro Request höchstens einmal geladen (Memoization).
- [ ] Episoden-Keys kommen im Feed aus dem Tag-Index (`$tags->getList('podcast', 1, -1)`,
      setzt 003 voraus), in der Admin-Liste (inkl. Entwürfe) aus einem Filter über
      `$pages->getDB(false)` — statt `new Page()` für jede Seite der Site.
- [ ] Optional: Einstellung „Max. Episoden im Feed“.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 014 · Mehrsprachigkeit der Admin-UI (de_DE/en über `$L`)

Status: idea · Priorität: mittel · Aufwand: M · Bereich: `plugin.php`, `podcast-admin.js`, `languages/` · Issue: [#15](https://github.com/larsauswsw/bludit-podcast-plugin/issues/15)

**Beschreibung**

Alle Texte der Oberfläche sind fest auf Deutsch im Code, das README ist auf
Englisch. Zusätzlich heißt die deutsche Sprachdatei `languages/de.json` —
Bludit lädt aber `languages/<Sitesprache>.json`, also `de_DE.json`
(bzw. `de_AT`, `de_CH`), sonst `en.json` (`bl-kernel/boot/rules/60.plugins.php:81–84`).
Die deutsche Datei wird daher nie verwendet.

**Akzeptanzkriterien**

- [ ] Alle UI-Texte über `$L->get('…')` mit Schlüsseln in `en.json` und `de_DE.json`
      (ggf. Kopien für `de_AT.json`/`de_CH.json`).
- [ ] Texte für das JS werden per `json_encode` als Objekt übergeben.
- [ ] Deutsche Bludit-Installation zeigt deutsche, englische zeigt englische Texte.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`,
  `bl-plugins/podcast/languages/*`

---

## 015 · Konfigurierbare Episoden-Zuordnung (Tag oder Kategorie)

Status: idea · Priorität: niedrig · Aufwand: M · Bereich: `plugin.php` (Einstellungen, `pageHasPodcastTag()`) · Issue: [#16](https://github.com/larsauswsw/bludit-podcast-plugin/issues/16)

**Beschreibung**

Episoden werden ausschließlich am fest verdrahteten Tag `podcast` erkannt. Ein
normaler Blogartikel *über* Podcasts mit diesem Tag taucht in der
Episodenverwaltung auf und kann dort gelöscht werden; ohne Audio fehlt er
zumindest im Feed. Sinnvoller wäre eine einstellbare Zuordnung (Tag-Name,
Kategorie oder Elternseite) — die Vorversion des Plugins kannte bereits
Kategorie und Elternseite.

**Akzeptanzkriterien**

- [ ] Einstellung „Episoden erkennen über: Tag / Kategorie“ mit Namensfeld.
- [ ] Optional: Elternseite für neue Episoden.
- [ ] Seiten ohne Audio-Metadaten werden in der Verwaltung als „keine Episode“
      gekennzeichnet und nicht zum Löschen angeboten.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`
