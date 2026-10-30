# Frontend & Player

Darstellung der Episoden auf der Website. Siehe [README.md](./README.md) für
Status-/Prioritäts-Legende.

## 028 · DSGVO: Podlove-Player lokal hosten / 2-Klick-Lösung

Status: done · Priorität: hoch · Aufwand: M · Bereich: `plugin.php` (`siteHead()`, `siteBodyEnd()`), Einstellungen · Issue: [#29](https://github.com/larsauswsw/bludit-podcast-plugin/issues/29)

**Beschreibung**

Auf jeder Episodenseite wird `https://cdn.podlove.org/web-player/5.x/embed.js`
ohne Einwilligung geladen (`plugin.php:276`); der Player lädt weitere Assets vom
CDN nach. Dabei wird die IP-Adresse jedes Besuchers an einen Drittanbieter
übertragen — für eine deutsche Website ist das ohne Einwilligung bzw.
Rechtsgrundlage problematisch und muss mindestens in der Datenschutzerklärung
stehen. Zudem fehlt eine Fallback-Wiedergabe, wenn das CDN blockiert oder nicht
erreichbar ist.

Sicherheitsaspekt: Das Skript wird mit wandernder Version (`5.x`) und ohne
Subresource Integrity geladen und läuft im selben Origin wie die Website
**und** der Admin-Bereich. Ein kompromittiertes oder manipuliertes CDN-Skript
könnte im Browser eines angemeldeten Admins, der eine Episodenseite öffnet,
Admin-Aktionen ausführen.

**Akzeptanzkriterien**

- [x] Einstellung „Player auf Episodenseiten“: Podlove Web Player lokal
      (Standard; Podlove 5.13.0 in `player/`, geladen über die `base`-Option) /
      einfacher HTML5-Player. Unbekannte Werte fallen auf „lokal“ zurück.
- [x] ~~Option „CDN“ mit 2-Klick-Lösung und SRI~~ — bewusst verworfen: SRI
      schützt nur `embed.js`, nicht die vom Player dynamisch nachgeladenen
      Bundles; lokal ausgeliefert entfällt sowohl die Drittanbieter-Verbindung
      als auch das Lieferketten-Risiko, damit braucht es keine 2-Klick-Lösung.
- [x] Fallback: HTML5-`<audio controls preload="none">` mit Download-Link,
      sichtbar ohne JavaScript, bis der Podlove-Player geladen ist, und dauerhaft,
      wenn er nicht lädt.
- [x] Mitgelieferter Player ist eine feste, per npm-Integritätshash geprüfte
      Version; `player/SOURCE.md` dokumentiert Herkunft, Lizenz und weggelassene
      Dateien (u. a. `share.html`), `player/CHECKSUMS.sha256` alle Prüfsummen;
      `scripts/update-podlove-player.sh` aktualisiert reproduzierbar.
- [x] README enthält einen Hinweistext für die Datenschutzerklärung.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/README.md`, neu:
  `bl-plugins/podcast/player/`, `scripts/update-podlove-player.sh`

---

## 029 · Player über `pageBegin`-Hook statt DOM-Selektoren einbinden

Status: idea · Priorität: mittel · Aufwand: S · Bereich: `plugin.php` (`siteBodyEnd()`) · Issue: [#30](https://github.com/larsauswsw/bludit-podcast-plugin/issues/30)

**Beschreibung**

Der Player wird am Ende des Body ausgegeben und per JavaScript mit einer
Selektorliste (`article .post-content`, …, `main`) an den Seitenanfang
verschoben (`plugin.php:333–363`). Das ist theme-abhängig und erzeugt
Layout-Sprünge. Bludit hat dafür die Hooks `pageBegin`/`pageEnd` (Doku
„Hooks list“), die alle mitgelieferten Themes (alternative, blogx, flavor,
popeye) innerhalb des Seiteninhalts aufrufen. Achtung: `pageBegin` wird in
Listenansichten pro Seite aufgerufen — nur bei `$url->whereAmI() === 'page'`
ausgeben.

**Akzeptanzkriterien**

- [ ] Player-Container wird im `pageBegin`-Hook (Einstellung: oben/unten →
      `pageBegin`/`pageEnd`) ausgegeben.
- [ ] Fallback über `siteBodyEnd` nur, wenn das Theme die Hooks nicht aufruft.
- [ ] Optional: Platzhalter `{{ podcast-player }}` im Inhalt zur freien Positionierung.
- [ ] Getestet mit allen vier Core-Themes.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 030 · Kapitelmarken & Transkripte

Status: idea · Priorität: niedrig · Aufwand: M · Bereich: `plugin.php` (Episodenformular, Player-Config, Feed) · Issue: [#31](https://github.com/larsauswsw/bludit-podcast-plugin/issues/31)

**Beschreibung**

Der Podlove-Player unterstützt Kapitel (`chapters`) und Transkripte
(`transcripts`), Podcasting 2.0 die Feed-Elemente `podcast:chapters` und
`podcast:transcript` (siehe 019). Die Design-Spec hatte Kapitelmarken als
„nicht im Scope“ markiert — sie gehören aber zu den meistgenutzten
Player-Funktionen.

**Akzeptanzkriterien**

- [ ] Kapitel als Textfeld (`00:00:00 Intro` pro Zeile) im Episodenformular.
- [ ] Kapitel erscheinen im Podlove-Player und als JSON-Chapters-Datei im Feed.
- [ ] Transkript-Upload (WebVTT/SRT) pro Episode, im Player und im Feed eingebunden.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/js/podcast-admin.js`

---

## 031 · Episodenarchiv & Sidebar-Widget „Neueste Episoden“

Status: idea · Priorität: niedrig · Aufwand: M · Bereich: `plugin.php` (`siteSidebar()`, ggf. Webhook) · Issue: [#32](https://github.com/larsauswsw/bludit-podcast-plugin/issues/32)

**Beschreibung**

Es gibt keine Übersicht aller Episoden außer `/tag/podcast` (die erst mit 003
zuverlässig funktioniert) und keine Abo-Möglichkeit außerhalb des Players.
Bludit bietet dafür den `siteSidebar`-Hook (Positionierung über die
Plugin-Reihenfolge).

**Akzeptanzkriterien**

- [ ] Sidebar-Widget mit den neuesten N Episoden, Cover und Abo-Links (aus 017).
- [ ] Optional: Archivseite (z. B. `/podcast`) per `webhook()` mit allen
      Episoden, gruppiert nach Staffel.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 032 · SEO: JSON-LD `PodcastEpisode` & OpenGraph-Audio

Status: idea · Priorität: niedrig · Aufwand: S · Bereich: `plugin.php` (`siteHead()`) · Issue: [#33](https://github.com/larsauswsw/bludit-podcast-plugin/issues/33)

**Beschreibung**

Suchmaschinen und Social-Media-Vorschauen erkennen die Seiten nicht als
Podcast-Episoden. schema.org bietet `PodcastEpisode`/`PodcastSeries`,
OpenGraph `og:audio`. Das Core-Plugin „opengraph“ kennt nur Bilder.

**Akzeptanzkriterien**

- [ ] Auf Episodenseiten JSON-LD `PodcastEpisode` (Name, Datum, Dauer,
      `associatedMedia`, `partOfSeries`).
- [ ] `og:audio`, `og:audio:type` und Episodenbild als `og:image`, falls kein
      Seitencover gesetzt ist.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 033 · Datenschutzfreundliche Download-Statistik

Status: idea · Priorität: niedrig · Aufwand: L · Bereich: neuer Webhook, Admin-Dashboard · Issue: [#34](https://github.com/larsauswsw/bludit-podcast-plugin/issues/34)

**Beschreibung**

Die Spec schließt Statistiken aus — trotzdem ist „Wie viele hören zu?“ die
häufigste Frage von Podcastern. Möglich ohne externe Dienste: Enclosure-URLs
zeigen auf einen Plugin-Webhook (`/podcast-media/<datei>`), der zählt und per
302 auf die Datei umleitet. Gezählt wird nach IAB-ähnlichen Regeln (pro Tag,
gehashte IP + User-Agent, Bots raus), ohne Klar-IP zu speichern.

**Akzeptanzkriterien**

- [ ] Opt-in-Einstellung „Downloads zählen“; Enclosure-URLs bleiben ohne Opt-in
      unverändert.
- [ ] Zählung pro Episode und Tag, Duplikate innerhalb 24 h werden ignoriert,
      keine personenbezogenen Daten im Klartext.
- [ ] Übersicht im Admin (`dashboard`-Hook oder Podcast-Seite).
- [ ] Optional: Prefix-Unterstützung für externe Dienste (Podtrac, OP3).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, neue Datei für Statistik-Speicherung im Workspace

---

## 041 · Podlove Subscribe Button frei platzieren

Status: idea · Priorität: mittel · Aufwand: M · Bereich: `plugin.php` (`siteHead()`, Platzhalter bzw. `siteSidebar()`), Einstellungen · Issue: [#48](https://github.com/larsauswsw/bludit-podcast-plugin/issues/48)

**Beschreibung**

Der Abo-Button erscheint bisher nur im Podlove-Player auf Episodenseiten
(Option `subscribe-button`, sobald eine Feed-URL gesetzt ist). Zusätzlich soll
der Podlove Subscribe Button eigenständig an einer Stelle der Website stehen
können — z. B. auf einer Seite „Über den Podcast“, auf der Startseite oder in
der Sidebar. Er öffnet eine Auswahl gängiger Podcast-Apps und bietet die
Feed-URL zum Kopieren an.

Das mit 028 mitgelieferte Podlove-Paket enthält den Button bereits lokal:
`player/5.13.0/button/embed.js` stellt `window.podloveSubscribeButton` bereit
und lädt seine Skripte, Styles und App-Icons aus `player/5.13.0/button/`. Es
braucht also keinen neuen Drittanbieter-Code und kein CDN. Die genaue
Aufruf-Signatur und die Konfiguration (Feed, Client-Liste, Theme) sind bei der
Umsetzung am Bundle zu prüfen.

**Offene Entscheidung: Position**

1. **Platzhalter im Seiteninhalt** (Vorschlag), z. B. `{{ podcast-subscribe }}`
   — funktioniert in jedem Theme und auf jeder Seite; Ersetzung beim Rendern
   (`beforeSiteLoad`, wie in der Bludit-Doku „Plugins and pages“ bzw. dem
   Core-Plugin „Custom fields parser“).
2. **Sidebar-Widget** über `siteSidebar` — nur in Themes mit Sidebar (von den
   Core-Themes ruft nur „blogx“ den Hook auf); passt zu 031.
3. **Automatisch auf Episodenseiten** unter dem Player (`pageEnd`, siehe 029).

**Akzeptanzkriterien**

- [ ] Position(en) gemäß Entscheidung oben; per Einstellung ein-/ausschaltbar.
- [ ] Der Button nutzt ausschließlich das lokal mitgelieferte Bundle
      (`player/`, `Podcast::PODLOVE_VERSION`); `embed.js` des Buttons wird nur
      auf Seiten geladen, die den Button tatsächlich enthalten.
- [ ] Feed-URL aus den Kanaleinstellungen (bzw. automatisch nach 017);
      ohne gültige `http(s)`-Feed-URL wird kein Button ausgegeben.
- [ ] Optional: Podcast-Titel, Cover und Client-Liste/IDs (z. B. Apple-Podcasts-
      oder Spotify-Link aus 017) werden an den Button übergeben.
- [ ] Fallback ohne JavaScript: einfacher Link „Podcast abonnieren“ auf die
      Feed-URL.
- [ ] Sicherheit (`CLAUDE.md`): Konfiguration nur aus Plugin-Einstellungen,
      nicht aus dem Seiteninhalt; Ausgabe per
      `json_encode(…, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)`;
      der Platzhalter wird nur als feste Zeichenkette erkannt.
- [ ] Datenschutz: Beim Seitenaufruf entstehen nur Anfragen an die eigene
      Domain (im Netzwerk-Tab geprüft); App-Links öffnen erst nach Klick.
- [ ] Funktioniert auch, wenn auf derselben Seite der Podlove-Player läuft.
- [ ] README dokumentiert Platzhalter bzw. Widget und die Einstellungen.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/README.md`
