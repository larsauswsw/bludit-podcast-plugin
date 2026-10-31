# Installations-Checkliste: Podcast-Plugin auf echter Bludit-Installation

Test auf einer laufenden Bludit-Installation (Apache, Bludit 3.x) nach einem
Plugin-Update. Alles ist per Browser und SSH machbar, auch vom Handy aus.
Erster Durchlauf: 2026-10-31 mit Stand `main` nach #52 (Teil F nach #55) —
Ergebnis unten abgehakt.

## Vorbereitung (SSH)

```sh
BLUDIT=/var/www/html            # Bludit-Ordner anpassen
SITE=https://deine-domain.tld   # Website-Adresse anpassen
```

Sicherung und Update — den Plugin-Ordner **über** den alten kopieren, nicht
vorher löschen (sonst geht eine alte `metadata/episodes.json` verloren). Das
Plugin dabei **nicht deaktivieren**: Bludit löscht sonst die Kanaleinstellungen
und den Workspace.

```sh
tar czf ~/podcast-backup-$(date +%F).tgz -C "$BLUDIT" bl-plugins/podcast bl-content/databases
cd /tmp && rm -rf podcast-main && mkdir podcast-main && \
curl -fsSL https://github.com/larsauswsw/bludit-podcast-plugin/archive/refs/heads/main.tar.gz \
  | tar xz -C podcast-main --strip-components=1 && \
cp -R podcast-main/bl-plugins/podcast/. "$BLUDIT/bl-plugins/podcast/"
chown -R "$(stat -c '%U:%G' "$BLUDIT/bl-content")" "$BLUDIT/bl-plugins/podcast"
cd "$BLUDIT/bl-plugins/podcast/player" && sha256sum -c --quiet CHECKSUMS.sha256 && echo OK
```

Zurück zum alten Stand: `tar xzf ~/podcast-backup-*.tgz -C "$BLUDIT"`

## A · Datenübernahme
- [x] A1 Als Admin **Admin → Podcast** öffnen: Liste mit allen bisherigen Episoden und Werten.
- [x] A2 **Einstellungen → Allgemein → Custom fields**: acht `podcast_*`-Felder, eigene Felder unverändert.
- [x] A3 Alte Datei weg und nicht abrufbar:
  ```sh
  ls $BLUDIT/bl-plugins/podcast/metadata 2>/dev/null || echo "Ordner weg – gut"
  ls $BLUDIT/bl-content/workspaces/podcast/
  curl -s -o /dev/null -w "%{http_code}\n" $SITE/bl-plugins/podcast/metadata/episodes.json
  ```
  Erwartet: „Ordner weg – gut“, `episodes-legacy-….php` im Workspace (nur wenn es vorher eine `episodes.json` gab), `404`.

## B · Episoden anlegen, bearbeiten, löschen
- [x] B4 Neue Episode mit **Audio-Upload** und Dauer `00:45:00`: erscheint in der Liste.
- [x] B5 Bearbeiten: Textfeld zeigt den eingegebenen Text (ohne `<p>`), Änderungen bleiben nach erneutem Öffnen. *(Erst fehlerhaft, behoben in #52.)*
- [x] B6 Bludit-Editor → Episode → Optionen → **Custom**: Podcast-Felder mit den Werten befüllt.
- [x] B7 Ungültige Dauer `45:99` im Podcast-Formular → Fehlermeldung „Dauer bitte als HH:MM:SS, MM:SS oder in Sekunden angeben“. Im Bludit-Editor eingetragen wird sie gespeichert, im Podcast-Formular angezeigt und beim Speichern dort beanstandet; Feed und Player ignorieren sie. *(Formular-Anzeige behoben in #52.)*
- [x] B8 Slug im Bludit-Editor ändern (Optionen → **SEO** → Friendly URL): Episode bleibt in Liste und Feed.
- [x] B9 Episode löschen: weg aus Liste und Feed. Feed immer am Cache vorbei prüfen — Online-Validatoren zeigen oft eine zwischengespeicherte Fassung:
  ```sh
  curl -s "$SITE/podcast.xml?t=$(date +%s)" | grep "<title>"
  ```

## C · Uploads
- [x] C10 Dateinamen und Schutzdateien:
  ```sh
  ls -la $BLUDIT/bl-content/uploads/podcast/audio/
  ```
  Erwartet: `name-<16 Hex-Zeichen>.mp3`, dazu `.htaccess` und `index.php`. Fehlen die Schutzdateien (z. B. nach manuellem Aufräumen), legt das Plugin sie beim nächsten Upload neu an — so im Test bestätigt.
- [x] C11 Kein PHP im Upload-Ordner:
  ```sh
  echo '<?php echo "UNSICHER";' > $BLUDIT/bl-content/uploads/podcast/audio/test.php
  curl -s -w "\n%{http_code}\n" $SITE/bl-content/uploads/podcast/audio/test.php
  rm $BLUDIT/bl-content/uploads/podcast/audio/test.php
  ```
  Erwartet: `403`, ohne „UNSICHER“ (Nginx: Regel aus dem README nötig).
- [ ] C12 *(optional, übersprungen)* Foto als Audio hochladen → „Audio-Datei passt inhaltlich nicht zur Endung“.

Aufräumen nur der Mediendateien (Schutzdateien bleiben):
`rm -f $BLUDIT/bl-content/uploads/podcast/audio/*.{mp3,m4a,ogg,wav}` — bis die
Medienverwaltung (Roadmap 025) da ist.

## D · Feed
- [x] D13
  ```sh
  curl -s "$SITE/podcast.xml?t=$(date +%s)" | grep -E "<title>|<guid|<enclosure|<itunes:duration"
  ```
  Erwartet: `guid` ist die Seiten-UUID (32 Hex-Zeichen), `enclosure` mit Audio-URL inkl. Zufallsanteil und `length`, Dauer nur wenn eingetragen.

## E · Player auf der Episodenseite
- [x] E14 Episodenseite im Browser: Podlove-Player erscheint über dem Inhalt.
- [x] E15 Nichts vom CDN (Episoden-URL wird aus dem Feed gelesen):
  ```sh
  EP=$(curl -s "$SITE/podcast.xml?t=$(date +%s)" | grep -oE "<link>[^<]+</link>" | sed -n 2p | sed -E 's#</?link>##g')
  echo "$EP"
  curl -s "$EP" | grep -oE "cdn\.podlove[^\"]*|podcast/player/embed\.js[^\"]*"
  ```
  Erwartet: nur `podcast/player/embed.js?version=5.13.0`.
- [x] E16 Beschreibung `&lt;b&gt;fett&lt;/b&gt;` speichern; der Player bekommt sie als Text:
  ```sh
  curl -s "$EP" | grep -oE '"summary":"[^"]{0,60}'
  ```
  Erwartet (zweite Zeile, Episode): `&lt;b&gt;fett…`, nicht `<b>`. Im kompakten Handy-Layout zeigt der Player die Beschreibung nicht an; der Text unter dem Player ist der normale Seiteninhalt des Themes.
- [x] E17 Kanaleinstellungen → „Player auf Episodenseiten“ → **Einfacher HTML5-Player**: schlichter Player mit „Episode herunterladen“; `curl -s "$EP" | grep -cE "embed\.js|podlovePlayer\("` ergibt `0`. Danach zurück auf Podlove.

## F · Zu großer Upload
- [x] F18 Unter dem Audio-Feld steht das tatsächliche Limit, z. B. „Max. 2 MB pro Datei — vom Server begrenzt (upload_max_filesize = 2M, post_max_size = 8M …)“ — nicht einfach die Plugin-Einstellung. *(Live geprüft: nach Erhöhen auf 512M/520M „Max. 512 MB pro Datei“ ohne Server-Hinweis. Die Anzeige im begrenzten Fall nur im Harness getestet.)*
- [ ] F19 Datei über dem Limit wählen und speichern → sofort „Die Datei „…“ (X MB) ist größer als das Upload-Limit von Y MB.“, ohne dass hochgeladen wird. *(Nicht live geprüft, Limits waren schon erhöht; in Node mit 7 Fällen getestet.)*
- [x] F20 Rückfallebene ohne Prüfung im Browser (Version vor #55, Limits 2M/8M): zu große Datei → serverseitige Meldung „zu groß“ statt „Netzwerkfehler“. *(Live geprüft.)*

Die PHP-Limits der Website (nicht der Kommandozeile) prüfen:
```sh
F=limit-$RANDOM.php
echo '<?php echo ini_get("post_max_size"), " / ", ini_get("upload_max_filesize");' > $BLUDIT/$F
curl -s $SITE/$F; echo
rm $BLUDIT/$F
```
Erhöhen bei PHP-FPM: in der Pool-Datei (z. B. `/etc/php/8.3/fpm/pool.d/www.conf`) `php_admin_value[upload_max_filesize] = 512M`, `php_admin_value[post_max_size] = 520M`, `php_admin_value[max_input_time] = 600`, dann `php-fpm8.3 -t && systemctl reload php8.3-fpm`. `php_value` in Apache-Konfiguration/`.htaccess` wirkt nur mit mod_php.

## Bekannte offene Punkte (keine Testfehler)
- „&“ in Titeln oder Kategorie im Feed doppelt escaped (001).
- Episoden mit weiterem Tag neben `podcast` fehlen in Liste, Feed und Player (002).
- Neue Episoden ohne Autor; `rss.xml`, Sitemap und `/tag/podcast` erfahren nichts von Anlegen/Löschen (003).
- Bludit im Unterordner: Admin-JavaScript und Feed-Pfad noch nicht angepasst (Rest von 004).
- Hinweis „Update“ in der Plugin-Liste (034).

Bei Fehlern: `tail -n 20 $BLUDIT/bl-content/databases/syslog.php` und das PHP-Error-Log.
