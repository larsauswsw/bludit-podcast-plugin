# Frontend & Player

Darstellung der Episoden auf der Website. Siehe [README.md](./README.md) für
Status-/Prioritäts-Legende.

## 028 · DSGVO: Podlove-Player lokal hosten / 2-Klick-Lösung

Status: idea · Priorität: hoch · Aufwand: M · Bereich: `plugin.php` (`siteHead()`, `siteBodyEnd()`), Einstellungen · Issue: [#29](https://github.com/larsauswsw/bludit-podcast-plugin/issues/29)

**Beschreibung**

Auf jeder Episodenseite wird `https://cdn.podlove.org/web-player/5.x/embed.js`
ohne Einwilligung geladen (`plugin.php:276`); der Player lädt weitere Assets vom
CDN nach. Dabei wird die IP-Adresse jedes Besuchers an einen Drittanbieter
übertragen — für eine deutsche Website ist das ohne Einwilligung bzw.
Rechtsgrundlage problematisch und muss mindestens in der Datenschutzerklärung
stehen. Zudem fehlt eine Fallback-Wiedergabe, wenn das CDN blockiert oder nicht
erreichbar ist.

**Akzeptanzkriterien**

- [ ] Einstellung „Player-Quelle“: lokal (Assets im Plugin-Ordner bzw.
      `bl-content/`, Podlove-`base`-Option) / CDN / einfacher HTML5-Player.
- [ ] Bei „CDN“ optional 2-Klick-Lösung: Platzhalter mit Hinweis, Player lädt
      erst nach Klick.
- [ ] `<noscript>`/Fallback: HTML5-`<audio controls>` mit Download-Link.
- [ ] README enthält einen Hinweistext für die Datenschutzerklärung.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/README.md`, ggf. neuer
  Ordner `bl-plugins/podcast/player/`

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
