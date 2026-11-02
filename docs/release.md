# Release erstellen

Anleitung für den Inhaber: wie eine neue Version des Plugins veröffentlicht
wird – jetzt für 1.0.0 und künftig. Technische Details: `scripts/release.sh`
(Kommentarkopf) und Roadmap 038.

## Überblick

- **Dieses Repository** (`bludit-podcast-plugin`, ab 1.0 privat): Code, Tests,
  Doku, Roadmap. Hier wird entwickelt.
- **Öffentliches Repository** (`bludit-plugin-podcast`): README, Screenshots,
  `CHANGELOG.md`, `LICENSE`, der Plugin-Ordner `podcast/` und je Version ein
  GitHub-Release mit `podcast-<version>.zip`. Dorthin kommt nur, was das
  Release überträgt – nie CLAUDE.md, Doku, Roadmap oder Skripte.

Ein Release hat immer zwei Schritte:

1. **Vorbereiten** (Claude): Version und Datum in `metadata.json` und im
   CHANGELOG setzen, als PR. Du mergst ihn.
2. **Veröffentlichen** (du): die GitHub Action „Release“ starten – zuerst als
   Probelauf, dann richtig. Geht im Browser, auch am Handy.

## Einmalig einrichten: Token für die Action

Die Action braucht einen Schlüssel, mit dem sie ins öffentliche Repository
schreiben darf. Am Handy im Browser, notfalls mit „Desktop-Version“:

1. GitHub → Profilbild → **Settings → Developer settings → Personal access
   tokens → Fine-grained tokens → Generate new token**.
2. Name z. B. `podcast-release`, **Expiration** z. B. 1 Jahr (Datum notieren).
3. **Repository access → Only select repositories →
   `larsauswsw/bludit-plugin-podcast`** (nur dieses).
4. **Permissions → Repository permissions → Contents: Read and write**.
   „Metadata: Read-only“ setzt GitHub selbst. Sonst nichts.
5. **Generate token** und den Token kopieren (wird nur einmal angezeigt).
6. Dieses Repository `bludit-podcast-plugin` → **Settings → Secrets and
   variables → Actions → New repository secret**: Name `PUBLIC_REPO_TOKEN`,
   Wert = der Token → **Add secret**.

Läuft der Token ab, dieselben Schritte wiederholen und das Secret ersetzen.

## Release 1.0.0 (jetzt)

Voraussetzung: PR „Release 1.0.0“ ist gemergt und die CI auf `main` ist grün
(Repository → **Actions** → oberster Lauf „CI“ mit grünem Haken).

1. **Probelauf:** Repository → **Actions** → links **Release** → **Run
   workflow** → Branch `main`, Haken bei „Probelauf“ lassen → **Run workflow**.
2. Den Lauf öffnen. Im Schritt **Release** stehen Warnungen (sollte keine
   geben), die Dateien für das öffentliche Repo und die ZIP-Größe. Unten unter
   **Artifacts** liegt `release` mit dem ZIP und dem Release-Text zum Ansehen.
3. **Veröffentlichen:** noch einmal **Run workflow**, diesmal den Haken bei
   „Probelauf“ **entfernen**. Am Ende steht „Veröffentlicht: …/releases/tag/v1.0.0“.
4. **Prüfen:** `github.com/larsauswsw/bludit-plugin-podcast` zeigt README,
   `podcast/` und rechts unter **Releases** „1.0.0“ mit dem ZIP.
5. **Repository privat stellen:** dieses Repository → **Settings → General →
   Danger Zone → Change repository visibility → Make private**.

Am Rechner geht Schritt 1–3 auch mit dem Skript (siehe unten).

## Künftige Releases

1. **Während der Arbeit:** Jede Änderung, die Nutzer betrifft, trägt Claude im
   PR in `CHANGELOG.md` unter `## [Unreleased]` ein (Englisch, ohne Interna).
2. **Version festlegen** – nach „Semantic Versioning“:
   - nur Fehler behoben → `1.0.1`
   - neue Funktionen, alles Bisherige läuft weiter → `1.1.0`
   - Umstellung, bei der Nutzer etwas anpassen müssen → `2.0.0`
3. **Claude beauftragen:** „Bereite Release 1.1.0 vor.“ Claude prüft den
   CHANGELOG, führt `scripts/release.sh prepare 1.1.0` aus und legt den PR
   „Release 1.1.0“ an. Du mergst ihn, sobald die CI grün ist.
4. **Veröffentlichen** wie oben: Action „Release“ mit Probelauf, dann ohne.

Das Release nimmt immer den aktuellen Stand von `main`. Zwischen Merge des
Release-PRs und dem Veröffentlichen daher nichts anderes mergen.

## Am Rechner statt mit der Action

Benötigt: `git`, PHP, `gh` (angemeldet mit Schreibrecht auf
`bludit-plugin-podcast`, einmalig `gh auth login` und `gh auth setup-git`).

```sh
git switch main && git pull
scripts/release.sh publish --dry-run   # Probelauf, ZIP in build/release/
scripts/release.sh publish             # veröffentlichen
```

## Wenn etwas schiefgeht

- **„CI ist für … nicht grün (oder noch nicht fertig)“:** Die CI auf `main`
  läuft noch oder ist rot. Warten bzw. Claude fragen.
- **„Secret PUBLIC_REPO_TOKEN fehlt“ oder „kein Schreibrecht“:** Token
  einrichten bzw. erneuern (siehe oben).
- **„Tag v… gibt es schon“:** Diese Version ist schon veröffentlicht. Für eine
  Korrektur eine neue Version vorbereiten (z. B. `1.0.1`), nicht dieselbe
  noch einmal.
- **Abbruch mitten im Veröffentlichen** (z. B. Release fehlt, Tag aber da):
  Nichts von Hand löschen – Claude den Link zum Lauf geben.
- **Das Plugin aktualisieren auf dem eigenen Server:** ZIP aus dem Release
  herunterladen und den Ordner `podcast` über `bl-plugins/podcast` kopieren
  (Episoden und Einstellungen bleiben erhalten). Für Tests mit dem Stand von
  `main` siehe die Installations-Checkliste (Vorbereitung, mit Lesetoken).
