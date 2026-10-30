#!/usr/bin/env bash
# Übernimmt eine Version des Podlove Web Players aus der npm-Registry nach
# bl-plugins/podcast/player/ — mit Integritätsprüfung und Datei-Allowlist.
#
# Aufruf: scripts/update-podlove-player.sh 5.13.0
# Benötigt: bash, curl, openssl, tar, php (CLI)
set -euo pipefail

VERSION="${1:-}"
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Aufruf: $0 <version>  (z. B. 5.13.0)" >&2
    exit 1
fi

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="$REPO_ROOT/bl-plugins/podcast/player"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# Tarball-URL und Integrität aus den Registry-Metadaten
curl -fsS "https://registry.npmjs.org/@podlove%2fweb-player/$VERSION" -o "$WORK/meta.json"
read -r TARBALL INTEGRITY < <(php -r '
    $m = json_decode(file_get_contents($argv[1]), true);
    echo $m["dist"]["tarball"], " ", $m["dist"]["integrity"], PHP_EOL;
' "$WORK/meta.json")

if [[ "$TARBALL" != "https://registry.npmjs.org/@podlove/web-player/-/web-player-$VERSION.tgz" ]]; then
    echo "Unerwartete Tarball-URL: $TARBALL" >&2
    exit 1
fi

curl -fsS "$TARBALL" -o "$WORK/package.tgz"
ACTUAL="sha512-$(openssl dgst -sha512 -binary "$WORK/package.tgz" | openssl base64 -A)"
if [[ "$ACTUAL" != "$INTEGRITY" ]]; then
    echo "Integritätsprüfung fehlgeschlagen: $ACTUAL != $INTEGRITY" >&2
    exit 1
fi

mkdir "$WORK/extract"
tar -xzf "$WORK/package.tgz" -C "$WORK/extract" --no-same-owner --no-same-permissions
cd "$WORK/extract/package"

# Allowlist: Einstieg, Layouts, Player- und Button-Bundle; keine Maps, keine HTML-Seiten
find . -type f \( -name "embed.js" -o -name "embed.js.LICENSE.txt" \
                 -o -name "variant-*.js" -o -name "variant-*.js.LICENSE.txt" \
                 -o -path "./$VERSION/player/*" -o -path "./$VERSION/button/*" \) \
       ! -name "*.map" ! -name "*.html" ! -name "share*" ! -path "./extensions/*" \
     | LC_ALL=C sort > "$WORK/files.txt"

if grep -vE '^\./[A-Za-z0-9._/-]+$' "$WORK/files.txt" | grep -q . || grep -q '\.\.' "$WORK/files.txt"; then
    echo "Unerwarteter Dateiname im Paket" >&2
    exit 1
fi
if grep '\.svg$' "$WORK/files.txt" | xargs -r grep -lEi '<script|[[:space:]]on[a-z]+=|foreignObject|javascript:' | grep -q .; then
    echo "SVG mit Skript-Inhalt gefunden — bitte manuell prüfen" >&2
    exit 1
fi

# Bisherige Player-Dateien ersetzen, LICENSE und SOURCE.md behalten
find "$TARGET" -mindepth 1 -maxdepth 1 ! -name LICENSE ! -name SOURCE.md -exec rm -rf {} +
tar -cf - -T "$WORK/files.txt" | tar -xf - -C "$TARGET"

cd "$TARGET"
find . -type f ! -name CHECKSUMS.sha256 ! -name SOURCE.md | sed 's|^\./||' | LC_ALL=C sort \
    | xargs sha256sum > CHECKSUMS.sha256

echo "Podlove Web Player $VERSION übernommen ($(wc -l < CHECKSUMS.sha256) Dateien)."
echo "Jetzt PODLOVE_VERSION in bl-plugins/podcast/plugin.php und player/SOURCE.md anpassen."
