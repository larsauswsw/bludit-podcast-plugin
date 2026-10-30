# Podcast-Plugin Roadmap

Strukturierte Sammlung von Fehlerbehebungen und Feature-Ideen für das Bludit
Podcast-Plugin. Ziel dieses Ordners: eine Doku, die sowohl beim Lesen im Repo
funktioniert als auch direkt als Auftrag für eine Claude-Code-Session taugt —
jedes Feature hat eine stabile ID, klare Akzeptanzkriterien und die vermutlich
betroffenen Dateien.

Jedes Feature ist zusätzlich als GitHub Issue angelegt (Labels `priority:*`,
`phase:*` und `effort:*`, dazu `bug`/`security`/`enhancement`) — die Issues
sind der Ort für Status/Fortschritt-Tracking über die GitHub-Oberfläche, die
`.md`-Dateien hier sind die inhaltliche Quelle der Wahrheit (Beschreibung,
Akzeptanzkriterien, betroffene Dateien).

Grundlage ist das Code-Review vom 2026-10-30
([../review/2026-10-30-code-review.md](../review/2026-10-30-code-review.md)),
geprüft gegen den Bludit-Quellcode **3.22.0** und die offizielle
Entwickler-Doku (`bludit/documentation-english`).

## Wie benutzen

- **Ein Feature umsetzen:** Issue oder Abschnitt raussuchen (z. B. „001 · Doppeltes
  HTML-Escaping …“ in `fixes.md`) und einer Claude-Code-Session als Auftrag geben,
  z. B.: *„Setze Feature 001 aus docs/roadmap/fixes.md um.“*
- **Priorisieren/verwerfen:** Feld `Priorität` in der jeweiligen `.md`-Datei anpassen, oder
  das Issue kommentieren/schließen (`state_reason: not_planned` für Ablehnung).
- **Als erledigt markieren:** `Status` in der `.md`-Datei auf `done` setzen und das
  zugehörige GitHub Issue schließen.

## Sicherheit als Definition of Done

Ein Feature gilt erst als `done`, wenn der Diff gegen die Sicherheitsregeln in
[`CLAUDE.md`](../../CLAUDE.md#sicherheitsregeln-verbindlich-für-jede-änderung)
geprüft ist und keine bekannte Schwachstelle enthält. Wird dabei eine Lücke im
bestehenden Code gefunden, wird sie im selben Change behoben. Das Repository
ist öffentlich: Schwachstellen werden erst nach dem Fix mit Details
dokumentiert.

## Legende

**Status:** `idea` (Vorschlag, noch nicht bewertet) · `planned` (priorisiert, als Nächstes dran) ·
`in-progress` (in Arbeit) · `done` (umgesetzt) · `rejected` (bewusst verworfen)

**Priorität:** `hoch` · `mittel` · `niedrig` — meine erste Einschätzung, gerne überschreiben.

**Aufwand:** `S` (Stunden, lokal begrenzt) · `M` (ca. 0,5–2 Tage, ein bis zwei Dateien) ·
`L` (mehrtägig, Datenmodell-/Architekturänderung oder Migration nötig)

## Übersicht nach Phase

| Datei | Thema | Features |
|---|---|---|
| [fixes.md](./fixes.md) | Fehler & Bludit-API-Konformität | 001–009 |
| [security.md](./security.md) | Sicherheit | 010–011, 039–040 |
| [architecture.md](./architecture.md) | Architektur & Datenhaltung | 012–015 |
| [feed.md](./feed.md) | RSS-Feed & Podcast-Verzeichnisse | 016–019 |
| [admin-ux.md](./admin-ux.md) | Admin-Oberfläche & Workflow | 020–027 |
| [frontend.md](./frontend.md) | Frontend & Player | 028–033 |
| [polish-and-debt.md](./polish-and-debt.md) | Sonstiges & technische Schulden | 034–038 |

## Alle Features im Überblick

| ID | Feature | Priorität | Aufwand | Issue |
|---|---|---|---|---|
| 001 | Doppeltes HTML-Escaping in Feed, Formularen und Titeln | hoch | S | [#2](https://github.com/larsauswsw/bludit-podcast-plugin/issues/2) |
| 002 | Episoden mit mehreren Tags werden nicht erkannt | hoch | S | [#3](https://github.com/larsauswsw/bludit-podcast-plugin/issues/3) |
| 003 | Seiten über `createPage()`/`editPage()`/`deletePage()` verwalten | hoch | S | [#4](https://github.com/larsauswsw/bludit-podcast-plugin/issues/4) |
| 004 | Unterverzeichnis-Installationen unterstützen (`DOMAIN` → `DOMAIN_BASE`/`webhook()`) | hoch | S | [#5](https://github.com/larsauswsw/bludit-podcast-plugin/issues/5) |
| 005 | Episoden-Metadaten an UUID statt Page-Key binden (Slug-Änderung, GUID) | hoch | M | [#6](https://github.com/larsauswsw/bludit-podcast-plugin/issues/6) ✅ |
| 006 | Datenverlust bei Plugin-Update: `episodes.json` aus dem Plugin-Ordner verlagern | hoch | S | [#7](https://github.com/larsauswsw/bludit-podcast-plugin/issues/7) ✅ |
| 007 | Bearbeiten überschreibt Tags, Seitentyp und Inhalt | mittel | S | [#8](https://github.com/larsauswsw/bludit-podcast-plugin/issues/8) |
| 008 | Upload-Fehler klar melden statt stumm verwerfen | mittel | S | [#9](https://github.com/larsauswsw/bludit-podcast-plugin/issues/9) |
| 009 | Datum/Zeitzone im Episodenformular korrigieren | niedrig | S | [#10](https://github.com/larsauswsw/bludit-podcast-plugin/issues/10) |
| 010 | CSRF-Schutz: Episoden-API in `adminController()` verlagern | hoch | M | [#11](https://github.com/larsauswsw/bludit-podcast-plugin/issues/11) |
| 011 | Upload- und Eingabe-Härtung | mittel | S | [#12](https://github.com/larsauswsw/bludit-podcast-plugin/issues/12) |
| 012 | Episoden-Metadaten als Bludit Custom Fields speichern | mittel | L | [#13](https://github.com/larsauswsw/bludit-podcast-plugin/issues/13) ✅ |
| 013 | Feed cachen, HTTP-Caching & Performance | mittel | M | [#14](https://github.com/larsauswsw/bludit-podcast-plugin/issues/14) |
| 014 | Mehrsprachigkeit der Admin-UI (de_DE/en über `$L`) | mittel | M | [#15](https://github.com/larsauswsw/bludit-podcast-plugin/issues/15) |
| 015 | Konfigurierbare Episoden-Zuordnung (Tag oder Kategorie) | niedrig | M | [#16](https://github.com/larsauswsw/bludit-podcast-plugin/issues/16) |
| 016 | Apple-Podcasts-Konformität des Feeds | hoch | M | [#17](https://github.com/larsauswsw/bludit-podcast-plugin/issues/17) |
| 017 | Feed-Discovery & automatische Feed-URL | niedrig | S | [#18](https://github.com/larsauswsw/bludit-podcast-plugin/issues/18) |
| 018 | Feed-Umzug & Steuerung (`new-feed-url`, `block`, `complete`) | niedrig | S | [#19](https://github.com/larsauswsw/bludit-podcast-plugin/issues/19) |
| 019 | Podcasting-2.0-Namespace (`podcast:*`) | niedrig | M | [#20](https://github.com/larsauswsw/bludit-podcast-plugin/issues/20) |
| 020 | Upload-Fortschritt & Chunked Upload für große Audiodateien | mittel | M | [#21](https://github.com/larsauswsw/bludit-podcast-plugin/issues/21) |
| 021 | Dauer & Dateigröße automatisch aus der Audiodatei ermitteln | mittel | M | [#22](https://github.com/larsauswsw/bludit-podcast-plugin/issues/22) |
| 022 | Kanal-Cover per Upload & Bildvalidierung | mittel | S | [#23](https://github.com/larsauswsw/bludit-podcast-plugin/issues/23) |
| 023 | Nächste Episodennummer automatisch vorschlagen | niedrig | S | [#24](https://github.com/larsauswsw/bludit-podcast-plugin/issues/24) |
| 024 | Episodenliste: Status, Suche, Filter, Warnungen | niedrig | M | [#25](https://github.com/larsauswsw/bludit-podcast-plugin/issues/25) |
| 025 | Medienverwaltung: Dateien aufräumen und wiederverwenden | niedrig | M | [#26](https://github.com/larsauswsw/bludit-podcast-plugin/issues/26) |
| 026 | Shownotes-Editor mit Markdown & Vorschau | niedrig | M | [#27](https://github.com/larsauswsw/bludit-podcast-plugin/issues/27) |
| 027 | Einreichung durch Autoren mit Freigabe-Workflow | niedrig | L | [#28](https://github.com/larsauswsw/bludit-podcast-plugin/issues/28) |
| 028 | DSGVO: Podlove-Player lokal hosten / 2-Klick-Lösung | hoch | M | [#29](https://github.com/larsauswsw/bludit-podcast-plugin/issues/29) ✅ |
| 029 | Player über `pageBegin`-Hook statt DOM-Selektoren einbinden | mittel | S | [#30](https://github.com/larsauswsw/bludit-podcast-plugin/issues/30) |
| 030 | Kapitelmarken & Transkripte | niedrig | M | [#31](https://github.com/larsauswsw/bludit-podcast-plugin/issues/31) |
| 031 | Episodenarchiv & Sidebar-Widget „Neueste Episoden“ | niedrig | M | [#32](https://github.com/larsauswsw/bludit-podcast-plugin/issues/32) |
| 032 | SEO: JSON-LD `PodcastEpisode` & OpenGraph-Audio | niedrig | S | [#33](https://github.com/larsauswsw/bludit-podcast-plugin/issues/33) |
| 033 | Datenschutzfreundliche Download-Statistik | niedrig | L | [#34](https://github.com/larsauswsw/bludit-podcast-plugin/issues/34) |
| 034 | `metadata.json`, Kompatibilität & PHP-8.2-Deprecations | hoch | S | [#35](https://github.com/larsauswsw/bludit-podcast-plugin/issues/35) |
| 035 | Toten Code & Repo-Altlasten entfernen | niedrig | S | [#36](https://github.com/larsauswsw/bludit-podcast-plugin/issues/36) |
| 036 | Automatisierte Tests & CI | mittel | M | [#37](https://github.com/larsauswsw/bludit-podcast-plugin/issues/37) |
| 037 | README, Specs & CLAUDE.md aktualisieren | niedrig | S | [#38](https://github.com/larsauswsw/bludit-podcast-plugin/issues/38) |
| 038 | Release-Prozess: CHANGELOG & ZIP-Paket | niedrig | S | [#39](https://github.com/larsauswsw/bludit-podcast-plugin/issues/39) |
| 039 | Pfad-Manipulation über ungeprüften Seitenschlüssel | hoch | S | [#42](https://github.com/larsauswsw/bludit-podcast-plugin/issues/42) ✅ |
| 040 | Episodenbeschreibung im Player als HTML ausgeführt | mittel | S | [#46](https://github.com/larsauswsw/bludit-podcast-plugin/issues/46) ✅ |

Alle Einträge starten mit `Status: idea` — priorisieren/verwerfen ist bewusst
der nächste Schritt für dich.

Bereits umgesetzt (`Status: done`): 039 (Sicherheitsfix, #41); 012 Custom Fields
mit 005 und 006 (behebt S3); 028 lokal gehosteter Player (behebt S4) mit 040 (S8, #45). Teilweise
vorgezogen: CSRF-Prüfung aus 010, URL-/Typprüfung aus 011, `CLAUDE.md` aus 037.

## Vorschlag für die Reihenfolge

Es gibt noch kein veröffentlichtes Release. Alles Folgende fließt in das erste
Release **1.0**; die Version bleibt bis zur ersten Veröffentlichung `1.0.0`.

1. **Pflicht vor dem 1.0-Release** — Fehler, die Feed oder Daten beschädigen,
   plus Sicherheit: 001, 002, 003, 004, 005, 006, 010, 034. Danach liefert der
   Feed korrekte Titel und Kategorien, Episoden mit mehreren Tags tauchen auf,
   und weder Plugin-Update noch Slug-Änderung lassen Episoden verschwinden.
2. **Für 1.0 empfohlen** — Verzeichnis-Tauglichkeit und Datenschutz:
   007, 008, 011, 016, 028, 029, 035, 037.
3. **Nach 1.0 (1.x) – Komfort:** 020, 021, 022, 023, 013, 014, 017, danach der Rest.

**Datenhaltung vor 1.0 entscheiden:** Solange nichts veröffentlicht ist, gibt es
keine fremden Installationen, deren Daten übernommen werden müssten — ein
Wechsel auf Bludit Custom Fields (012) ist jetzt so günstig wie nie wieder.
Entweder 012 direkt vor 1.0 umsetzen (dann entfallen 005/006 als
Zwischenlösung weitgehend), oder 005/006 als kleine Lösung für 1.0 und 012
später mit Datenübernahme.

**Verwandte Features:** 010 (API in `adminController()`) löst nebenbei auch den
Unterverzeichnis-Teil von 004 für die API-URL.
