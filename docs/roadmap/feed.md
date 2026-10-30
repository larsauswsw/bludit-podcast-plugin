# RSS-Feed & Podcast-Verzeichnisse

Damit der Feed in Apple Podcasts, Spotify & Co. sauber angenommen wird und
langfristig wartbar bleibt. Siehe [README.md](./README.md) für
Status-/Prioritäts-Legende.

## 016 · Apple-Podcasts-Konformität des Feeds

Status: idea · Priorität: hoch · Aufwand: M · Bereich: `plugin.php` (`outputFeed()`, `form()`, `handleSaveEpisode()`) · Issue: [#17](https://github.com/larsauswsw/bludit-podcast-plugin/issues/17)

**Beschreibung**

Der Feed enthält die Grundelemente, weicht aber an mehreren Stellen von den
aktuellen Apple-Anforderungen ab bzw. lässt empfohlene Elemente weg:

- `<itunes:explicit>` wird als `yes`/`no` ausgegeben; Apple spezifiziert
  `true`/`false`.
- Kategorie ist ein Freitextfeld ohne Unterkategorie — Tippfehler führen zur
  Ablehnung (siehe auch 001 für das `&`-Problem).
- Es fehlen `<itunes:type>` (episodic/serial), `<copyright>`, das RSS-`<image>`
  und `<content:encoded>` für formatierte Shownotes; `<itunes:summary>` ist
  veraltet.
- Die Beschreibung wird per `strip_tags` zu einem einzigen Textblock
  (`plugin.php:451`) — Absätze und Links gehen verloren.
- Externe Audio-URLs haben `length="0"` im `<enclosure>`.
- `wav` und `ogg` sind erlaubt, werden von Apple Podcasts aber nicht
  unterstützt (`audio/x-m4a` für m4a ist ok).
- Die Kanal-Sortierung im README („nach Episodennummer“) stimmt nicht mit dem
  Feed (nach Datum) überein.

**Akzeptanzkriterien**

- [ ] `<itunes:explicit>true|false</itunes:explicit>` auf Kanal- und Episodenebene.
- [ ] Kategorie + Unterkategorie als Auswahlliste nach Apple-Kategorieliste.
- [ ] Neue Kanaleinstellungen: `itunes:type`, Copyright; RSS-`<image>` wird aus
      dem Cover erzeugt.
- [ ] Shownotes als `<content:encoded><![CDATA[…]]></content:encoded>` mit HTML,
      `<description>` als Klartext-Kurzfassung; `<itunes:summary>` entfällt.
- [ ] Bei externer Audio-URL wird die Dateigröße beim Speichern per
      `HEAD`-Request (`Content-Length`) ermittelt, Fehler werden angezeigt.
      Der Request ist gegen SSRF abgesichert (CLAUDE.md, Sicherheitsregel 8):
      nur `http`/`https`, keine privaten/Loopback-/Link-Local-Ziele nach
      DNS-Auflösung, Redirects werden erneut geprüft, kurzer Timeout.
- [ ] Hinweis/Warnung beim Upload von `wav`/`ogg`.
- [ ] Feed besteht den Podbase- bzw. Cast-Feed-Validator ohne Fehler.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/README.md`

---

## 017 · Feed-Discovery & automatische Feed-URL

Status: idea · Priorität: niedrig · Aufwand: S · Bereich: `plugin.php` (`siteHead()`, `form()`) · Issue: [#18](https://github.com/larsauswsw/bludit-podcast-plugin/issues/18)

**Beschreibung**

Die Feed-URL muss zusätzlich zum Dateinamen von Hand eingetragen werden
(`feedUrl` + `feedSlug`), obwohl sie sich aus `DOMAIN_BASE . feedSlug` ergibt.
Außerdem fehlt das `<link rel="alternate" type="application/rss+xml">` im
`<head>`, über das Browser und Podcast-Apps den Feed automatisch finden — das
Core-RSS-Plugin macht genau das in `siteHead()`.

**Akzeptanzkriterien**

- [ ] Ist `feedUrl` leer, wird `DOMAIN_BASE . feedSlug` verwendet (Feld bleibt
      als Override, z. B. für FeedBurner/CDN).
- [ ] `siteHead()` gibt auf allen Seiten einen Discovery-Link mit Podcast-Titel aus.
- [ ] Einstellungsseite zeigt die fertige Feed-URL als Link zum Kopieren.
- [ ] Optional: Felder für Apple-/Spotify-/Podcast-Index-Links, die als
      Abo-Buttons im Player und im Widget (031) erscheinen.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 018 · Feed-Umzug & Steuerung (`new-feed-url`, `block`, `complete`)

Status: idea · Priorität: niedrig · Aufwand: S · Bereich: `plugin.php` (`form()`, `outputFeed()`) · Issue: [#19](https://github.com/larsauswsw/bludit-podcast-plugin/issues/19)

**Beschreibung**

Für den Lebenszyklus eines Podcasts fehlen Steuerelemente: Ein Umzug auf eine
neue Feed-URL (z. B. Domainwechsel) braucht `<itunes:new-feed-url>` und einen
301-Redirect vom alten Pfad; ein beendeter Podcast `<itunes:complete>`; ein
vorübergehend versteckter `<itunes:block>`.

**Akzeptanzkriterien**

- [ ] Einstellungen „Neue Feed-URL“, „Podcast abgeschlossen“, „In Verzeichnissen
      verbergen“ mit den entsprechenden Feed-Elementen.
- [ ] Optional: alter Feed-Pfad leitet per 301 auf den neuen um.
- [ ] Pro Episode „In Verzeichnissen verbergen“ (`<itunes:block>` auf Item-Ebene).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`

---

## 019 · Podcasting-2.0-Namespace (`podcast:*`)

Status: idea · Priorität: niedrig · Aufwand: M · Bereich: `plugin.php` (`outputFeed()`, Einstellungen, Episodenformular) · Issue: [#20](https://github.com/larsauswsw/bludit-podcast-plugin/issues/20)

**Beschreibung**

Der offene Podcasting-2.0-Standard (podcastindex.org) wird von vielen
modernen Apps unterstützt (z. B. Podcast Addict, Fountain, Castamatic, Apple für
Transkripte). Sinnvolle erste Elemente:

- `<podcast:guid>` (stabile Kanal-ID, UUIDv5 aus der Feed-URL),
- `<podcast:locked>` (Schutz vor Feed-Übernahme),
- `<podcast:funding>` (Unterstützer-Link),
- `<podcast:transcript>` und `<podcast:chapters>` (zusammen mit 030),
- `<podcast:person>` (Hosts/Gäste).

**Akzeptanzkriterien**

- [ ] Namespace `xmlns:podcast="https://podcastindex.org/namespace/1.0"` eingebunden.
- [ ] `podcast:guid`, `podcast:locked` und `podcast:funding` konfigurierbar.
- [ ] Transkript- und Kapitel-URLs pro Episode (sobald 030 umgesetzt ist).
- [ ] Feed validiert weiterhin bei Apple (unbekannte Namespaces werden ignoriert).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`
