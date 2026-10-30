# Podlove Web Player (lokal gehostet)

Dieser Ordner enthält eine unveränderte Teilmenge des npm-Pakets
[`@podlove/web-player`](https://www.npmjs.com/package/@podlove/web-player).
Das Plugin liefert den Player von hier aus, damit beim Aufruf einer
Episodenseite keine Verbindung zu einem Podlove-CDN entsteht.

| | |
|---|---|
| Paket | `@podlove/web-player` |
| Version | `5.13.0` (veröffentlicht 2024-12-07) |
| Tarball | `https://registry.npmjs.org/@podlove/web-player/-/web-player-5.13.0.tgz` |
| Integrität (npm) | `sha512-SJnK2aGZoINX8Q+LPse/YYmvDr/8bQUJaWrdl3eq30XyNaCDrGBmdFtx8c8DPP9fvpg1y9iraWOXvZFTJpmAZA==` |
| Lizenz | MIT, siehe [`LICENSE`](./LICENSE) (Podlove Project); Lizenzen gebündelter Bibliotheken in den `*.LICENSE.txt`-Dateien |

## Enthalten

- `embed.js` — Einstiegsskript (`window.podlovePlayer`)
- `variant-*.js` — Player-Layouts
- `5.13.0/player/` — Player-Bundle (Skripte, Chunks, CSS)
- `5.13.0/button/` — Abo-Button (Skripte, CSS, Client-Icons)

Prüfsummen aller Dateien: [`CHECKSUMS.sha256`](./CHECKSUMS.sha256)
(`sha256sum -c CHECKSUMS.sha256`).

## Bewusst weggelassen

- `share.html`, `share.js` — eigenständige Share-Seite, die Episode und
  Konfiguration aus URL-Parametern lädt. Auf derselben Domain wie der
  Bludit-Admin wäre sie unnötige Angriffsfläche; das Plugin konfiguriert keinen
  Share-Outlet.
- `extensions/` — vom Plugin nicht genutzt.
- `*.map`, `5.13.0/player/report.html` — nur für Entwicklung.
- `README.md`, `package.json`.

## Aktualisieren

```sh
scripts/update-podlove-player.sh <version>
```

Das Skript lädt das Paket von der npm-Registry, prüft die SHA-512-Integrität
gegen die Registry-Metadaten, übernimmt nur die oben genannten Dateien und
erzeugt `CHECKSUMS.sha256` neu. Danach `PODLOVE_VERSION` in `plugin.php` und
diese Datei anpassen und den Diff prüfen (Sicherheitsregeln in `CLAUDE.md`,
insbesondere neue Dateien oder HTML-Seiten).
