# Podcast Plugin für Bludit 3.19+ — Design-Dokument

**Datum:** 2026-04-04  
**Status:** Genehmigt  

---

## Überblick

Ein Bludit-Plugin zur vollständigen Podcast-Verwaltung. Episoden werden als Bludit-Seiten gespeichert (Tag `podcast`, Custom Fields für Metadaten). Das Plugin stellt eine eigene Admin-Oberfläche bereit, generiert einen iTunes-kompatiblen RSS 2.0 Feed und unterstützt lokale Datei-Uploads sowie externe Audio-URLs.

---

## 1. Architektur & Dateistruktur

```
bl-plugins/podcast/
├── plugin.php                  # Haupt-Plugin-Klasse (Hooks, Feed, Einstellungen)
├── admin/
│   ├── episodes.php            # Episodenliste (Admin-UI)
│   ├── episode-form.php        # Anlegen / Bearbeiten einer Episode
│   └── settings.php            # Kanaleinstellungen (eingebunden via form()-Hook)
├── js/
│   └── podcast-admin.js        # Upload-Handling im Admin
└── metadata/
    └── episodes.json           # Mapping: episode-slug → bludit-page-key
```

### Datenhaltung

- Jede Episode ist eine Bludit-Seite mit Tag `podcast`
- Podcast-spezifische Daten werden als Custom Fields der Seite gespeichert:
  - `itunes_episode` — Episodennummer
  - `itunes_season` — Staffelnummer
  - `itunes_duration` — Dauer (HH:MM:SS)
  - `itunes_explicit` — ja/nein
  - `itunes_type` — full / trailer / bonus
  - `itunes_image` — URL zum Episodenbild
  - `audio_url` — URL zur Audiodatei (lokal oder extern)
  - `audio_length` — Dateigröße in Bytes (0 bei externer URL)
- `metadata/episodes.json` speichert nur das Slug→Page-Key-Mapping für schnellen Zugriff; kein Datenduplikat

### Berechtigungen

- Admin-Bereich zugänglich für Rollen `admin` und `editor`
- Zugriffskontrolle in jeder Admin-PHP-Datei via `$login->role()`

---

## 2. Admin-UI & Episodenverwaltung

### Seitenleisten-Eintrag

- Hook: `adminSidebar()`
- Eintrag "Podcast" erscheint für `admin` und `editor`
- Link führt zu `admin/episodes.php`

### Episodenliste (`admin/episodes.php`)

- Tabelle mit Spalten: Episodennummer, Titel, Staffel, Typ, Veröffentlichungsdatum, Status
- Aktionen pro Zeile: Bearbeiten, Löschen
- Button "Neue Episode" oben rechts
- **Löschen:** entfernt die Bludit-Seite vollständig + bereinigt `episodes.json`

### Episodenformular (`admin/episode-form.php`)

| Feld | Typ | Pflicht |
|---|---|---|
| Titel | Text | ja |
| Beschreibung | Textarea | ja |
| Episodennummer | Number | nein |
| Staffel | Number | nein |
| Episodentyp | Select: full / trailer / bonus | ja |
| Explicit | Checkbox | nein |
| Dauer | Text (HH:MM:SS) | nein |
| Episodenbild | Datei-Upload oder URL | nein |
| Audio-Datei | Datei-Upload **oder** externe URL | ja |
| Veröffentlichungsdatum | Datetime | ja |
| Status | Select: veröffentlicht / Entwurf | ja |

### Speichern-Flow

1. Formular abgesendet → Rollenprüfung
2. Bludit-Seite anlegen/aktualisieren (Titel + Beschreibung als Seiteninhalt)
3. Custom Fields setzen
4. Tag `podcast` automatisch anhängen
5. Bei lokalem Upload: Datei nach `bl-content/uploads/podcast/audio/` verschieben, Dateigröße ermitteln
6. Mapping in `episodes.json` aktualisieren

---

## 3. iTunes-Feed & Kanaleinstellungen

### Feed-URL

- Standard: `/podcast.xml`
- Konfigurierbar im Plugin-Einstellungsformular

### Kanaleinstellungen (Plugin-Einstellungsformular `admin/settings.php`)

| Feld | Beschreibung |
|---|---|
| Podcast-Titel | Kanalname |
| Feed-URL | Vollständige URL zum Feed |
| Website-URL | Kanal-Link |
| Beschreibung | Kurzbeschreibung des Podcasts |
| Sprache | z.B. `de-de` |
| Autor | Name des Autors |
| E-Mail (managingEditor) | Kontakt-E-Mail |
| iTunes-Kategorie | z.B. "Society & Culture" |
| Podcast-Cover | URL oder Upload (1400×1400px JPG/PNG empfohlen) |
| Explicit | ja / nein |
| Maximale Upload-Größe | in MB, Standard 512 |

### Feed-Generierung

- Hook: `beforeAll()`
- Prüft ob angefragte URL der konfigurierten Feed-URL entspricht
- Lädt alle Bludit-Seiten mit Tag `podcast` und Status `published`
- Sortiert nach Veröffentlichungsdatum (neueste zuerst)
- Mappt Custom Fields auf iTunes-Elemente
- Setzt Header `Content-Type: application/rss+xml; charset=UTF-8`
- Gibt XML aus und ruft `exit` auf

### Feed-Struktur (Auszug)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
  xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
  xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>Podcast-Titel</title>
    <link>https://example.com</link>
    <description>Beschreibung</description>
    <language>de-de</language>
    <managingEditor>lars@example.com</managingEditor>
    <atom:link href="https://example.com/podcast.xml" rel="self" type="application/rss+xml" />
    <itunes:author>Lars Miesner</itunes:author>
    <itunes:owner>
      <itunes:name>Lars Miesner</itunes:name>
      <itunes:email>lars@example.com</itunes:email>
    </itunes:owner>
    <itunes:image href="https://example.com/cover.jpg" />
    <itunes:category text="Society &amp; Culture" />
    <itunes:explicit>no</itunes:explicit>
    <item>
      <title>Episode 1: Anfang</title>
      <description>Beschreibung der Episode</description>
      <itunes:summary>Beschreibung der Episode</itunes:summary>
      <itunes:episode>1</itunes:episode>
      <itunes:season>1</itunes:season>
      <itunes:duration>00:45:30</itunes:duration>
      <itunes:episodeType>full</itunes:episodeType>
      <itunes:explicit>no</itunes:explicit>
      <itunes:image href="https://example.com/episode1.jpg" />
      <enclosure url="https://example.com/episode1.mp3" length="12345678" type="audio/mpeg" />
      <link>https://example.com/episode-1</link>
      <guid isPermaLink="false">eindeutiger-slug</guid>
      <pubDate>Mon, 08 Dec 2025 15:08:33 +0100</pubDate>
    </item>
  </channel>
</rss>
```

---

## 4. Datei-Upload & Verzeichnisse

### Audio-Uploads

- Zielverzeichnis: `bl-content/uploads/podcast/audio/`
- Erlaubte Dateitypen: `mp3`, `m4a`, `ogg`, `wav`
- Maximale Dateigröße: konfigurierbar (Standard 512 MB)
- Dateinamensbereinigung: Leerzeichen → `-`, Sonderzeichen entfernt, Kleinschreibung
- `audio_length` wird automatisch via `filesize()` ermittelt
- Externe URL: direkt übernommen, `audio_length = 0`

### Bild-Uploads

- Zielverzeichnis: `bl-content/uploads/podcast/images/`
- Erlaubte Typen: `jpg`, `jpeg`, `png`
- Für Kanal-Cover (Kanaleinstellungen) und Episodenbild (Formular)

### Verzeichnisschutz

- Beide Upload-Verzeichnisse erhalten eine leere `index.php` (verhindert Directory-Listing)

---

## 5. Hooks-Übersicht

| Hook | Zweck |
|---|---|
| `install()` | Upload-Verzeichnisse anlegen, `episodes.json` initialisieren |
| `uninstall()` | Aufräumen (optional, Seiten bleiben erhalten) |
| `beforeAll()` | Feed-URL abfangen, XML ausgeben |
| `adminSidebar()` | "Podcast"-Eintrag in der Admin-Navigation |
| `form()` | Kanaleinstellungen-Formular rendern |
| `formSave()` | Kanaleinstellungen speichern |

---

## 6. Nicht im Scope

- Kommentar-Verwaltung für Episodenseiten (Bludit-Standard übernimmt das)
- Statistiken / Download-Tracking
- Automatische Kapitelmarken
- Mehrsprachigkeit der Admin-UI
