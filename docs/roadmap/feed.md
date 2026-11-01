# RSS-Feed & Podcast-Verzeichnisse

Damit der Feed in Apple Podcasts, Spotify & Co. sauber angenommen wird und
langfristig wartbar bleibt. Siehe [README.md](./README.md) für
Status-/Prioritäts-Legende.

## 016 · Apple-Podcasts-Konformität des Feeds

Status: done · Priorität: hoch · Aufwand: M · Bereich: `plugin.php` (`outputFeed()`, `form()`, `handleSaveEpisode()`) · Issue: [#17](https://github.com/larsauswsw/bludit-podcast-plugin/issues/17)

**Beschreibung**

Der Feed enthält die Grundelemente, weicht aber an mehreren Stellen von den
aktuellen Apple-Anforderungen ab bzw. lässt empfohlene Elemente weg:

- `<itunes:explicit>` wird als `yes`/`no` ausgegeben; Apple spezifiziert
  `true`/`false`.
- Kategorie ist ein Freitextfeld ohne Unterkategorie — Tippfehler führen zur
  Ablehnung (siehe auch 001 für das `&`-Problem). Apple hat eine feste Liste
  (Kategorie → Unterkategorie). Im Feed stehen immer die **englischen**
  Originalnamen, Unterkategorien verschachtelt
  (`<itunes:category text="Kids &amp; Family"><itunes:category text="Parenting"/></itunes:category>`);
  Apple übersetzt sie in seinen Apps selbst und wertet nur die erste Kategorie
  aus. Eine Freitext-Unterkategorie wie `Sports > Tennis` erkennt Apple nicht.

**Paket 2026-11-02:** 016 zusammen mit 017 (automatische Feed-URL) und 043
(Kanaleinstellungen gruppieren) umsetzen — alle drei bauen `form()` um.
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

- [x] `<itunes:explicit>true|false</itunes:explicit>` auf Kanal- und Episodenebene.
- [x] Kategorie + Unterkategorie als Auswahlliste nach Apple-Kategorieliste
      (vor der Umsetzung gegen Apples aktuelle Liste prüfen). Anzeige deutsch
      mit englischem Namen, z. B. „Kinder & Familie (Kids & Family) →
      Elternschaft (Parenting)“; gespeichert und im Feed nur der englische Name.
      Bisheriger Freitext wird übernommen, wenn er einer Apple-Kategorie
      entspricht, sonst fordert das Formular eine Auswahl.
- [x] Neue Kanaleinstellungen: `itunes:type`, Copyright; RSS-`<image>` wird aus
      dem Cover erzeugt.
- [x] Shownotes als `<content:encoded><![CDATA[…]]></content:encoded>` mit HTML,
      `<description>` als Klartext-Kurzfassung; `<itunes:summary>` entfällt.
- [x] Bei externer Audio-URL wird die Dateigröße beim Speichern per
      `HEAD`-Request (`Content-Length`) ermittelt, Fehler werden angezeigt.
      Der Request ist gegen SSRF abgesichert (CLAUDE.md, Sicherheitsregel 8):
      nur `http`/`https`, keine privaten/Loopback-/Link-Local-Ziele nach
      DNS-Auflösung, Redirects werden erneut geprüft, kurzer Timeout.
- [x] Hinweis/Warnung beim Upload von `wav`/`ogg`.
- [x] Feed besteht den Podbase- bzw. Cast-Feed-Validator ohne Fehler
      (Installationstest R65, 2026-11-01: nach #84 ohne Fehler; verbleibende
      Hinweise zu Dauer, Episodenbild und Transkript sind optional).

**Umsetzung Teil 1 (Kanal):** `APPLE_CATEGORIES` mit 19 Kategorien und 91
Unterkategorien, abgeglichen mit Apples Seite „Apple Podcasts-Kategorien“
(podcasters.apple.com/support/1691, Stand 2026, vom Inhaber als PDF
bereitgestellt); deutsche Namen wie dort, z. B. „Kinder und Familie →
Kindererziehung (Parenting)“. Gespeichert im
bisherigen Feld `category` als „Kategorie > Unterkategorie“, damit passende
Freitexte (auch `Sports > Tennis`) ohne Umwandlung gelten; sonst Hinweis im
Formular und keine Kategorie im Feed. Neue Felder `showType` und `copyright`,
Cover nur mit `http(s)` (`coverUrl()`), auch für den Player. Die README-Angabe
zur Sortierung passt bereits: Die Admin-Liste sortiert nach Folgennummer, der
Feed nach Datum.

**Umsetzung Teil 2 (Episoden):** `<content:encoded>` aus `shownotesHtml()`
(DOMDocument, Whitelist `SHOWNOTES_TAGS`, alle Attribute entfernt, `href` nur
`http(s)`/`mailto`, Pfade ab „/“ mit Website-Adresse; Skripte, Stile, Einbettungen
entfallen) als CDATA (`cdata()`: ungültiges UTF-8 und Steuerzeichen entfernt,
„]]>“ geteilt). `<description>` aus `episodeTextSummary()`: Text ohne Tags,
Entities bleiben (Apple liest das Feld als HTML), höchstens 4000 Zeichen.
Dateigröße: hochgeladene Datei per `filesize()`, unveränderte URL behält die
gespeicherte Größe (vorher setzte jedes Bearbeiten sie auf 0), sonst
`remoteAudioLength()`: HEAD, ersatzweise GET mit `Range: bytes=0-0`; nur
`http`/`https` auf Port 80/443, Servername mit Buchstaben-Endung oder IP, alle
aufgelösten IPs öffentlich (`isPublicIp()`: PHP-Filter plus Sonderbereiche wie
100.64/10, NAT64, 6to4), Verbindung per `CURLOPT_RESOLVE` an die geprüfte IP
gebunden, höchstens drei Weiterleitungen, jede neu geprüft, kein Proxy aus der
Umgebung, 10 s, vom Inhalt höchstens 1 KB. Schlägt das fehl, wird trotzdem
gespeichert; der Hinweis erscheint über der Episodenliste (über
`sessionStorage`, nur als Text). Hinweis zu `wav`/`ogg` im Formular für Datei
und URL.

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`, `bl-plugins/podcast/README.md`

---

## 017 · Feed-Discovery & automatische Feed-URL

Status: done · Priorität: niedrig · Aufwand: S · Bereich: `plugin.php` (`siteHead()`, `form()`) · Issue: [#18](https://github.com/larsauswsw/bludit-podcast-plugin/issues/18)

**Beschreibung**

Die Feed-URL muss zusätzlich zum Dateinamen von Hand eingetragen werden
(`feedUrl` + `feedSlug`), obwohl sie sich aus `DOMAIN_BASE . feedSlug` ergibt.
Außerdem fehlt das `<link rel="alternate" type="application/rss+xml">` im
`<head>`, über das Browser und Podcast-Apps den Feed automatisch finden — das
Core-RSS-Plugin macht genau das in `siteHead()`.

Folge des leeren Felds heute: Ohne Feed-URL fehlen der Abo-Button im Player
und `<atom:link rel="self">` im Feed (auf der Live-Seite am 2026-11-01 so
gesehen). Umsetzung zusammen mit 016 und 043.

**Akzeptanzkriterien**

- [x] Ist `feedUrl` leer, wird `DOMAIN_BASE . feedSlug` verwendet (Feld bleibt
      als Override, z. B. für FeedBurner/CDN) — für Abo-Button und
      `<atom:link rel="self">`.
- [x] `siteHead()` gibt auf allen Seiten einen Discovery-Link mit Podcast-Titel aus.
- [x] Einstellungsseite zeigt die fertige Feed-URL als Link zum Kopieren.
- [x] Optional: Felder für Apple-/Spotify-/Podcast-Index-Links, die als
      Abo-Buttons im Player und im Widget (031) erscheinen.

**Umsetzung:** `feedUrl()` liefert die abweichende Feed-URL, wenn sie mit
`http(s)://` beginnt, sonst `localFeedUrl()` (`DOMAIN_BASE` + `feedSlug()`,
Pfadteile URL-kodiert). Genutzt für Abo-Button, `<atom:link rel="self">` (jetzt
immer im Feed) und den Discovery-Link in `siteHead()` (Titel: Podcast-Titel,
sonst Website-Titel). Die Einstellungsseite zeigt die Adresse in einem
schreibgeschützten Feld mit Link „Feed öffnen“. Website-URL analog über
`siteUrl()`; eine eingetragene URL ohne `http(s)://` wird nicht verwendet und
im Formular markiert.

Nachtrag Installationstest P55: Der Abo-Button braucht eine Liste `clients`,
sonst bleibt sein Fenster leer. Übergeben werden Apps, die über die
Feed-Adresse abonnieren (`Podcast::SUBSCRIBE_APPS`), plus Player.fm und RSS.
Dienste, deren Link die ID des Podcasts beim Dienst braucht (optionaler Punkt
oben): Felder für die öffentlichen Links zu Apple Podcasts, Spotify, Pocket
Casts, Deezer und Castbox in der Gruppe „Apple Podcasts & Verzeichnisse“;
`serviceId()` liest die ID per Muster (nur https und die Domain des Dienstes),
ein unpassender Link wird markiert und nicht verwendet. Der Apple-Link bringt
Castro mit und – ohne eigenen Link – Pocket Casts über `pca.st/itunes/<ID>`.
Podcast Index (nicht im Podlove-Paket) und das Widget (031) stehen aus.

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

Status: in-progress · Priorität: niedrig · Aufwand: M · Bereich: `plugin.php` (`outputFeed()`, Einstellungen, Episodenformular) · Issue: [#20](https://github.com/larsauswsw/bludit-podcast-plugin/issues/20)

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

- [x] Namespace `xmlns:podcast="https://podcastindex.org/namespace/1.0"` eingebunden
      (vorgezogen mit 016: PSP-1 verlangt ihn, Validator-Befund im Installationstest R65).
- [ ] `podcast:guid`, `podcast:locked` und `podcast:funding` konfigurierbar.
      Umgesetzt (vorgezogen, weil der PSP-1-Test einen unbenutzten Namespace
      nicht erkennt): `podcast:guid` als UUIDv5 aus der Feed-Adresse ohne
      Schema, beim ersten Speichern bzw. Öffnen der Episodenliste festgelegt und
      danach unverändert (geprüft am Beispiel aus dem Standard);
      `podcast:locked` als Einstellung, Standard „Nein“, `owner` aus der
      E-Mail des Inhabers. Offen: `podcast:funding`.
- [ ] Transkript- und Kapitel-URLs pro Episode (sobald 030 umgesetzt ist).
- [ ] Feed validiert weiterhin bei Apple (unbekannte Namespaces werden ignoriert).

**Betroffene Dateien**

- `bl-plugins/podcast/plugin.php`
