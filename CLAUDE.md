# Bludit Podcast Plugin – Projektkontext für Claude

## Projektübersicht

Bludit-Plugin (PHP) für Podcast-Verwaltung und RSS-Feed-Ausgabe.
Zielversion: **Bludit 3.x** (aktuell 3.18.2 – es gibt keine 4.0).

## Dateistruktur

```
plugin.php        – Haupt-Plugin-Klasse (PodcastPlugin extends Plugin)
metadata.json     – Plugin-Metadaten (Name, Version, compatible: "3.0")
languages/        – Sprachdateien (derzeit leer/Platzhalter)
README.md         – Nutzerdokumentation
```

## Bludit Plugin-API (verifiziert gegen 3.18.2)

### Hooks (implementiert)
| Hook | Zweck |
|------|-------|
| `init()` | `$this->dbFields` definieren |
| `form()` | HTML für Admin-Einstellungsformular zurückgeben |
| `post()` | POST-Verarbeitung im Admin-Panel |
| `beforeAll()` | Läuft vor allem – für Webhook/Feed-Ausgabe und Frontend-POST |
| `siteBodyEnd()` | Frontend-Formular am Seitenende einblenden |
| `siteSidebar()` | Sidebar-Widget ausgeben |

### Globale Objekte
- `$login->isLogged()` – Nutzer eingeloggt?
- `$login->role()` – Rolle als String: `admin`, `editor`, `author`, `contributor`, `moderator`, `viewer`
- **Kein** `$login->isAdmin()` – Rollenprüfung immer über `$login->role()`
- `$pages->add($fields)` – Seite anlegen, gibt neuen Key zurück
- `$pages->edit($fields)` – Seite bearbeiten (Feld `key` erforderlich)
- `$pages->delete($key)` – Seite löschen
- `$pages->exists($key)` – Seite vorhanden?

### Konstanten
- `PATH_ROOT` – absoluter Dateisystempfad zum Bludit-Root (mit DS am Ende)
- `DOMAIN_BASE` – absolute URL zur Site (z.B. `https://example.com/`)
- `DOMAIN_PLUGINS`, `DOMAIN_UPLOADS` etc. – weitere URL-Konstanten

### Plugin-Hilfsmethoden (Basisklasse)
- `$this->getValue('field')` – Gespeicherten Wert lesen
- `$this->xml($str)` – `htmlspecialchars` für XML/HTML-Ausgabe
- `$this->webhook('pfad')` – URL-Endpoint abfangen (gibt true zurück wenn Pfad matched)
- `$this->domainPath()` – URL zum Plugin-Verzeichnis

## Konfigurationsfelder (`dbFields`)

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `feedTitle` | string | RSS-Feed-Titel |
| `feedDescription` | string | RSS-Feed-Beschreibung |
| `author` | string | Podcast-Autor |
| `coverImage` | string | Cover-Bild URL |
| `itemsLimit` | int | Max. Episoden im Feed |
| `episodesDirectory` | string | Relativer Pfad zum Episoden-Ordner (z.B. `content/episodes`) |
| `parentPageSlug` | string | Elternseite für auto-erstellte Episode-Seiten (optional) |
| `submissionPageSlug` | string | Slug der Seite mit Frontend-Einreichungsformular |
| `sidebarRoles` | string | Kommagetrennte Rollen für Sidebar-Link (leer = alle eingeloggten) |

## Funktionen im Überblick

### RSS-Feed
- URL: `/podcast.xml` (via `webhook('podcast.xml')` in `beforeAll()`)
- Episoden kommen aus JSON-Dateien im `episodesDirectory`

### Episoden-JSON-Format
```json
{
  "title": "Episode 1",
  "audioUrl": "https://example.com/audio/ep1.mp3",
  "date": "2025-12-08T10:00:00Z",
  "summary": "Beschreibung",
  "guid": "episode-1",
  "cover": "https://example.com/cover.jpg",
  "pageKey": "episode-1"
}
```
`pageKey` wird automatisch ergänzt, wenn eine Bludit-Seite für die Episode angelegt wird.

### Admin-Panel (form/post)
- Allgemeine Einstellungen speichern
- Neue Episode anlegen (→ JSON-Datei + Bludit-Seite)
- Bestehende Episoden bearbeiten/löschen
- Accordion-UI mit CSS/JS inline

### Frontend-Formular (`siteBodyEnd`)
- Erscheint auf der Seite mit dem konfigurierten `submissionPageSlug`
- Nur für eingeloggte Nutzer sichtbar
- CSRF-Schutz via Session-Nonce
- Nach Absenden: PRG-Pattern (Redirect mit `?podcast_saved=1`)

### Sidebar-Link (`siteSidebar`)
- Zeigt Link "Neue Episode einreichen" in der Theme-Sidebar
- Nur wenn `submissionPageSlug` gesetzt und Nutzer eingeloggt ist
- Rollenfilter via `sidebarRoles` (kommagetrennt, leer = alle)

### Bludit-Seiten-Sync (`syncEpisodePage`)
- Legt automatisch eine Bludit-Seite pro Episode an oder aktualisiert sie
- Bettet Podlove Web Player 5.x per `<script>` ein
- Speichert `pageKey` zurück in die JSON-Datei

## Git-Branches

- Entwicklungsbranch: `claude/review-podcast-plugin-cHK1F`
- Push immer auf diesen Branch

## Offene Punkte / nächste Schritte

- [ ] Sprachdateien in `languages/` befüllen (de_DE, en)
- [ ] `siteHead()` für eigene CSS-Klassen (Frontend-Formular-Styling)
- [ ] Dateigröße für RSS `<enclosure length="">` berechnen (aktuell `0`)
- [ ] Validierung der Audio-URL im Frontend (Mime-Type-Check)
- [ ] Episode-Reihenfolge im Admin per Drag & Drop oder manuell steuerbar
- [ ] Unterstützung für weitere Audio-Formate (ogg, m4a)
