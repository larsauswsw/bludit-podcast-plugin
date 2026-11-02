#!/usr/bin/env bash
# Release des Podcast-Plugins (Roadmap 038) in zwei Schritten.
#
#   scripts/release.sh prepare [<version>]
#     Auf aktuellem main: setzt in metadata.json die Version (falls angegeben)
#     und releaseDate (heute), macht im CHANGELOG aus „## [Unreleased]“ den
#     Abschnitt „## [<version>] - <datum>“ (mit neuem, leerem Unreleased davor)
#     und committet das auf dem neuen Branch release/<version>.
#     Danach: Branch pushen, PR, grüne CI, mergen.
#
#   scripts/release.sh publish [--dry-run]
#     Auf main nach dem Merge: baut podcast-<version>.zip aus dem Plugin-Ordner
#     (nur versionierte Dateien), überträgt Plugin-Ordner (als podcast/),
#     README, Screenshots und Lizenz aus public/ sowie CHANGELOG.md als einen
#     Commit „Release <version>“ ins öffentliche Repo, setzt dort und hier das
#     Tag v<version> und legt dort das GitHub-Release mit dem ZIP und dem
#     CHANGELOG-Abschnitt als Text an.
#     --dry-run: baut alles nur lokal (ZIP und Release-Text in build/release/),
#     meldet fehlende Voraussetzungen als Warnung und pusht nichts.
#
# Veröffentlicht wird nur mit Lizenz („license“ in metadata.json und
# bl-plugins/podcast/LICENSE; die Datei liegt im ZIP und wird zusätzlich als
# LICENSE ins Hauptverzeichnis des öffentlichen Repos kopiert) und grüner CI.
#
# Benötigt: bash, git, php (CLI), tar; für publish ohne --dry-run zusätzlich
# gh (angemeldet, Schreibrecht auf das öffentliche Repo, Lesen der CI hier)
# und git-Zugangsdaten für den Push dorthin (z. B. nach „gh auth setup-git“).
# PUBLIC_REPO, PUBLIC_URL und DEV_REPO lassen sich für Tests überschreiben.
# Ohne Rechner: GitHub Action „Release“ (.github/workflows/release.yml),
# Anleitung in docs/release.md.
set -euo pipefail

PUBLIC_REPO="${PUBLIC_REPO:-larsauswsw/bludit-plugin-podcast}"
PUBLIC_URL="${PUBLIC_URL:-https://github.com/$PUBLIC_REPO.git}"
DEV_REPO="${DEV_REPO:-larsauswsw/bludit-podcast-plugin}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
META="bl-plugins/podcast/metadata.json"
OUT="$ROOT/build/release"

die()  { echo "Fehler: $*" >&2; exit 1; }
warn() { echo "Warnung: $*" >&2; }
git_() { git -C "$ROOT" "$@"; }

# Hilfe: der Kommentarkopf dieser Datei
usage() {
    awk 'NR > 1 && /^#/ { sub(/^# ?/, ""); print; next } NR > 1 { exit }' "$0"
    exit 1
}

# Feld aus metadata.json (Arbeitsverzeichnis), nur Zeichenketten
meta() {
    php -r '$m = json_decode((string) file_get_contents($argv[1]), true);
        $v = is_array($m) && isset($m[$argv[2]]) ? $m[$argv[2]] : "";
        echo is_string($v) ? $v : "";' "$ROOT/$META" "$1"
}

is_version() { [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; }

# Sauberes Arbeitsverzeichnis auf main, gleich mit origin/main
main_problems() {
    git_ fetch -q origin main || echo "origin/main ließ sich nicht abrufen"
    [[ -z "$(git_ status --porcelain)" ]] || echo "Arbeitsverzeichnis hat ungespeicherte Änderungen"
    [[ "$(git_ symbolic-ref --short -q HEAD || true)" == main ]] || echo "nicht auf dem Branch main"
    [[ "$(git_ rev-parse HEAD)" == "$(git_ rev-parse origin/main 2>/dev/null || true)" ]] \
        || echo "main ist nicht auf dem Stand von origin/main"
}

# Text eines CHANGELOG-Abschnitts (ohne Überschrift) aus dem Commit
changelog_section() {
    git_ show "HEAD:CHANGELOG.md" 2>/dev/null | awk -v h="$1" '
        index($0, h) == 1 { found = 1; next }
        found && /^## \[/ { exit }
        found { print }
    ' | sed '/./,$!d'
}

# ─── prepare ────────────────────────────────────────────────────────────────

prepare() {
    local version="${1:-}" current released today problems
    current="$(meta version)"
    released="$(meta releaseDate)"
    if [[ -z "$version" ]]; then
        [[ -z "$released" ]] || die "Version $current ist schon veröffentlicht ($released); neue Version angeben."
        version="$current"
    fi
    is_version "$version" || die "Ungültige Version „$version“ (erwartet z. B. 1.0.1)."

    problems="$(main_problems)"
    [[ -z "$problems" ]] || die "$problems"
    git_ rev-parse -q --verify "refs/tags/v$version" >/dev/null && die "Tag v$version gibt es schon."
    git_ ls-remote --exit-code --tags origin "refs/tags/v$version" >/dev/null 2>&1 && die "Tag v$version gibt es schon auf origin."
    git_ rev-parse -q --verify "refs/heads/release/$version" >/dev/null && die "Branch release/$version gibt es schon."

    today="$(date +%F)"
    # CHANGELOG: Unreleased mit Inhalt wird zur Version, davor ein leeres Unreleased
    php -r '
        [$file, $version, $date] = array_slice($argv, 1);
        $text = (string) file_get_contents($file);
        if (preg_match("/^## \[" . preg_quote($version, "/") . "\]/m", $text)) {
            fwrite(STDERR, "CHANGELOG hat schon einen Abschnitt [$version].\n"); exit(1);
        }
        if (!preg_match("/^## \[Unreleased\][ \t]*\n(.*?)(?=^## \[|\z)/ms", $text, $m) || trim($m[1]) === "") {
            fwrite(STDERR, "CHANGELOG: Abschnitt ## [Unreleased] fehlt oder ist leer.\n"); exit(1);
        }
        $text = preg_replace("/^## \[Unreleased\][ \t]*$/m", "## [Unreleased]\n\n## [$version] - $date", $text, 1);
        file_put_contents($file, $text);
    ' "$ROOT/CHANGELOG.md" "$version" "$today" || exit 1

    # metadata.json: Version und Datum, Format wie bisher (4 Leerzeichen)
    php -r '
        [$file, $version, $date] = array_slice($argv, 1);
        $m = json_decode((string) file_get_contents($file), true);
        if (!is_array($m)) { fwrite(STDERR, "metadata.json ist kein gültiges JSON.\n"); exit(1); }
        $m["version"] = $version;
        $m["releaseDate"] = $date;
        file_put_contents($file, json_encode($m, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    ' "$ROOT/$META" "$version" "$today" || exit 1

    git_ switch -q -c "release/$version"
    git_ add CHANGELOG.md "$META"
    git_ commit -q -m "Release $version"
    echo "Release $version vorbereitet (Branch release/$version):"
    git_ --no-pager show --stat --format='  %s' HEAD | sed 's/^/  /'
    cat <<EOF

Nächste Schritte:
  git push -u origin release/$version
  PR anlegen, grüne CI abwarten, mergen
  git switch main && git pull
  scripts/release.sh publish --dry-run    # prüfen
  scripts/release.sh publish
EOF
}

# ─── publish ────────────────────────────────────────────────────────────────

publish() {
    local dry=0
    case "${1:-}" in
        "") ;;
        --dry-run) dry=1 ;;
        *) usage ;;
    esac

    local problems=() version released license head file
    version="$(meta version)"
    released="$(meta releaseDate)"
    license="$(meta license)"
    head="$(git_ rev-parse HEAD)"

    while IFS= read -r line; do [[ -n "$line" ]] && problems+=("$line"); done < <(main_problems)
    is_version "$version" || problems+=("metadata.json: ungültige Version „$version“")
    if [[ -z "$released" ]]; then
        problems+=("metadata.json: releaseDate fehlt (zuerst scripts/release.sh prepare)")
    elif ! git_ show "HEAD:CHANGELOG.md" 2>/dev/null | grep -qxF "## [$version] - $released"; then
        problems+=("CHANGELOG.md: Abschnitt „## [$version] - $released“ fehlt")
    fi
    [[ -n "$license" ]] || problems+=("metadata.json: license ist leer (Lizenz noch nicht entschieden)")
    git_ cat-file -e "HEAD:bl-plugins/podcast/LICENSE" 2>/dev/null \
        || problems+=("bl-plugins/podcast/LICENSE fehlt (Lizenztext)")
    git_ rev-parse -q --verify "refs/tags/v$version" >/dev/null && problems+=("Tag v$version gibt es hier schon")
    git ls-remote --exit-code --tags "$PUBLIC_URL" "refs/tags/v$version" >/dev/null 2>&1 \
        && problems+=("Tag v$version gibt es im öffentlichen Repo schon")
    for file in plugin.php metadata.json languages/en.json player/LICENSE; do
        git_ cat-file -e "HEAD:bl-plugins/podcast/$file" 2>/dev/null || problems+=("Plugin-Ordner: $file fehlt")
    done

    if (( ! dry )); then
        command -v gh >/dev/null || problems+=("gh ist nicht installiert")
        if command -v gh >/dev/null; then
            gh auth status >/dev/null 2>&1 || problems+=("gh ist nicht angemeldet (gh auth login)")
            case "$(gh repo view "$PUBLIC_REPO" --json viewerPermission -q .viewerPermission 2>/dev/null || true)" in
                ADMIN|MAINTAIN|WRITE) ;;
                *) problems+=("kein Schreibrecht auf $PUBLIC_REPO") ;;
            esac
            # GH_CI_TOKEN: eigener Token zum Lesen der CI (GitHub Action), sonst der von gh
            [[ "$(GH_TOKEN="${GH_CI_TOKEN:-${GH_TOKEN:-}}" gh run list --repo "$DEV_REPO" --workflow ci.yml --commit "$head" --json conclusion \
                -q '.[0].conclusion' 2>/dev/null || true)" == success ]] \
                || problems+=("CI ist für ${head:0:7} nicht grün (oder noch nicht fertig)")
        fi
    fi

    if (( ${#problems[@]} )); then
        if (( dry )); then
            for line in "${problems[@]}"; do warn "$line"; done
            echo
        else
            printf 'Fehler: %s\n' "${problems[@]}" >&2
            exit 1
        fi
    fi

    local work zip notes pub branch
    work="$(mktemp -d)"
    # shellcheck disable=SC2064  # Pfad jetzt einsetzen: $work ist beim Beenden nicht mehr gesetzt
    trap "rm -rf '$work'" EXIT
    mkdir -p "$OUT"
    zip="$OUT/podcast-$version.zip"
    notes="$OUT/release-notes-$version.md"

    # ZIP und Release-Text aus dem Commit, nicht aus dem Arbeitsverzeichnis
    git_ archive --format=zip --prefix=podcast/ -o "$zip" "HEAD:bl-plugins/podcast"
    changelog_section "## [$version]" > "$notes"
    # Probelauf vor „prepare“: Vorschau mit dem Unreleased-Abschnitt
    if [[ ! -s "$notes" ]] && (( dry )); then
        changelog_section "## [Unreleased]" > "$notes"
    fi
    [[ -s "$notes" ]] || echo "Siehe CHANGELOG.md." > "$notes"

    # Öffentliches Repo: Inhalt vollständig aus diesem Commit ersetzen
    pub="$work/public"
    git clone -q "$PUBLIC_URL" "$pub"
    branch="$(git -C "$pub" symbolic-ref --short -q HEAD || echo main)"
    git -C "$pub" rm -rq --ignore-unmatch .
    git_ archive --format=tar "HEAD:public" | tar -x -C "$pub"
    git_ archive --format=tar --prefix=podcast/ "HEAD:bl-plugins/podcast" | tar -x -C "$pub"
    git_ show "HEAD:CHANGELOG.md" > "$pub/CHANGELOG.md"
    git_ show "HEAD:bl-plugins/podcast/LICENSE" > "$pub/LICENSE" 2>/dev/null || true
    git -C "$pub" add -A
    git -C "$pub" diff --cached --quiet && die "Keine Änderung gegenüber dem öffentlichen Repo."
    git -C "$pub" commit -q -m "Release $version"

    echo "Öffentliches Repo ($PUBLIC_REPO, Branch $branch), Commit „Release $version“:"
    git -C "$pub" --no-pager show --stat=100 --format= HEAD -- . ':(exclude)podcast/player' | sed 's/^/  /'
    echo "   podcast/player/: $(git -C "$pub" ls-files podcast/player | wc -l | tr -d ' ') Dateien (Podlove Web Player)"
    echo
    echo "ZIP:          $zip ($(git_ ls-tree -r --name-only "HEAD:bl-plugins/podcast" | wc -l | tr -d ' ') Dateien)"
    echo "Release-Text: $notes"

    if (( dry )); then
        echo
        echo "Probelauf: nichts gepusht, kein Tag, kein Release."
        return
    fi

    git -C "$pub" tag -a "v$version" -m "Release $version"
    git -C "$pub" push -q --atomic origin "HEAD:$branch" "v$version"
    gh release create "v$version" "$zip" --repo "$PUBLIC_REPO" --verify-tag \
        --title "$version" --notes-file "$notes"
    git_ tag -a "v$version" -m "Release $version" "$head"
    git_ push -q origin "v$version"
    echo
    echo "Veröffentlicht: https://github.com/$PUBLIC_REPO/releases/tag/v$version"
}

case "${1:-}" in
    prepare) shift; prepare "$@" ;;
    publish) shift; publish "$@" ;;
    *) usage ;;
esac
