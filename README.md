# Podcast Plugin (Skeleton)

Grundgerüst für ein Podcast-Plugin in Bludit. Dateien liegen in `bl-plugins/podcast/`.

## Dateien
- `metadata.json` – Plugin-Metadaten für Bludit.
- `plugin.php` – Plugin-Klasse mit Basis-Konfiguration und Settings-Form.
- `languages/en_US.json`, `languages/de_DE.json` – Sprachstrings.

## Episoden-Dateien (Beispiel)
Legen Sie JSON-Dateien im Ordner `content/episodes` an, z. B. `episode-001.json`:
```json
{
  "title": "Episode 1",
  "date": "2025-12-08T10:00:00Z",
  "audioUrl": "https://example.com/audio/episode1.mp3",
  "duration": "12:34",
  "summary": "Kurzbeschreibung",
  "cover": "https://example.com/img/cover1.jpg",
  "guid": "episode-1"
}
```

## Nächste Schritte
1. Routing/Feed-Ausgabe in `beforeAll()` ergänzen (z. B. `/podcast.xml`) und `loadEpisodes()` verwenden.
2. RSS-Generator ergänzen (Channel-Metadaten aus den Plugin-Settings, Items aus `loadEpisodes()`).
3. Fehlerhandling/Validierung für Episoden-Dateien erweitern (z. B. Pflichtfelder, Logging).
4. Admin-UI: Neues Episode-Form plus Bearbeiten/Löschen bestehender Episoden (JSON-Dateien im Ordner).
5. Optional: Beim Speichern/Löschen einer Episode wird automatisch eine Bludit-Seite mit gleichem Slug angelegt/aktualisiert/gelöscht; Elternseite kann über `parentPageSlug` gesetzt werden. Die Seite enthält auch einen Podlove Web Player für die Audio-URL.
6. CSS/JS nur bei Bedarf im Frontend laden.
7. Eigene Kategorie erstellen (lassen) und alle Episoden damit Taggen


