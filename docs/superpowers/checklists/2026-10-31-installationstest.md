# Installations-Checkliste: Podcast-Plugin auf echter Bludit-Installation

Test auf einer laufenden Bludit-Installation (Apache, Bludit 3.x) nach einem
Plugin-Update. Alles ist per Browser und SSH machbar, auch vom Handy aus.
Erster Durchlauf: 2026-10-31 mit Stand `main` nach #52 (Teil F nach #55) —
Ergebnis unten abgehakt. Teil G am 2026-11-01 nach #58 auf dem iPhone, Teil H nach #60, Teil I nach #62, Teil J nach #64, Teil K nach #66, Teil L nach #68, Teil M nach #70, Teil N nach #72, Teil O nach #76, Teil P nach #78.

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

## G · Admin auf dem Smartphone (042)
Auf einem iPhone und möglichst einem Android-Gerät, Hochformat. Vorher die Seite neu laden (das Skript hat eine neue Cache-Version).
- [x] G21 **Admin → Podcast**: Überschrift „Podcast – Episoden“ ohne Umbruch neben einem Knopf, darunter „Neue Episode“ und „Kanaleinstellungen“ als Links. Liste ohne seitliches Scrollen: Titel, darunter Datum mit „Staffel · Folge“, rechts ☰.
- [x] G22 ☰ öffnet das Menü mit Anschauen (nur bei veröffentlichten Episoden), Bearbeiten, Im Bludit-Editor öffnen, Löschen; Tippen auf den Titel öffnet ebenfalls das Formular. Episode ohne Titel erscheint als „Kein Titel“, Entwurf mit Badge „Entwurf“.
- [x] G23 Formular: Typ, Explicit und Status zeigen ihren Text vollständig; Folge/Staffel und Typ/Explicit stehen paarweise nebeneinander; das Datum steht linksbündig und mittig im Feld. *(Nach #58 saß das Datum zu weit oben; mit #59 auf dem iPhone in Ordnung.)*
- [x] G24 Audio → **Datei hochladen**: Feld mit „Durchsuchen“, nach der Auswahl erscheint der Dateiname (lange Namen mit „…“); Speichern lädt hoch wie bisher.
- [x] G25 **Kanaleinstellungen** sehen aus wie bei anderen Bludit-Plugins (Hinweise klein unter den Feldern); Speichern übernimmt die Werte, der Link im blauen Kasten führt zur Episodenliste.
- [x] G26 Am Desktop: Liste mit Spalten Staffel, Folge, Typ; sonst wie Bludits **Inhalte**-Liste.

## H · Sonderzeichen (001)
- [x] H27 **Kanaleinstellungen**: Podcast-Titel `Tipps & Tricks "Test"`, Kategorie `Society & Culture` eintragen, zweimal speichern. Die Felder zeigen danach genau diesen Text, ohne `&amp;`.
- [x] H28 Episode mit Titel `Tom & Jerry "Folge" <1>` anlegen, im Formular öffnen und unverändert zweimal speichern: Formular, Liste und Episodenseite zeigen den Titel unverändert, auch im Player.
- [x] H29 Feed am Cache vorbei: Titel und Kategorie stehen genau einmal escaped drin, ein Validator (z. B. Podbase) meldet keinen Fehler:
  ```sh
  curl -s "$SITE/podcast.xml?nocache=$RANDOM" | grep -E "<title>|itunes:category"
  ```
  Erwartet: `<title>Tipps &amp; Tricks &quot;Test&quot;</title>`, `<itunes:category text="Society &amp; Culture" />` — nicht `&amp;amp;`. *(Live geprüft, Validator ohne Fehler.)*
- [x] H30 Altwerte aus der Zeit vor 001 suchen (mehrfach escaped). Jede Fundstelle einmal im Formular korrigieren und speichern; danach liefert der Befehl nichts mehr:
  ```sh
  grep -nE '&amp;(amp|quot|lt|gt|#0?39);' "$BLUDIT/bl-content/databases/plugins/podcast/db.php" "$BLUDIT/bl-content/databases/pages.php"
  ```
  Danach die Testwerte aus H27/H28 wieder zurücksetzen bzw. die Testepisode löschen. *(Live: keine Altwerte gefunden.)*

## I · Tags, Autor, Unterseiten (002, 003)
- [x] I31 Im **Bludit-Editor** einer Episode einen zweiten Tag ergänzen (z. B. `Technik`): Die Episode bleibt in **Admin → Podcast**, im Feed und hat den Player. Per curl (URL der Episode in `EP`): `curl -s "$SITE/podcast.xml?nocache=$RANDOM" | grep -c "${EP##*/}"` und `curl -s "$EP?nocache=$RANDOM" | grep -c 'id="podcast-episode-player"'` ergeben je `1`.
- [x] I32 Diese Episode im Podcast-Formular bearbeiten und speichern: Im Bludit-Editor stehen danach weiterhin beide Tags.
- [x] I33 Neue Testepisode `Neu & frisch` anlegen: URL ist `…/neu-frisch` (ohne `amp`); im Bludit-Editor ist ein Autor gesetzt; `$SITE/tag/podcast` listet sie; **Einstellungen → Allgemein → Systemprotokoll** bzw. Dashboard zeigt „Neuer Inhalt erstellt“. Falls die Plugins **RSS** bzw. **Sitemap** aktiv sind, steht sie in `$SITE/rss.xml` bzw. `$SITE/sitemap.xml`. *(Live: Dashboard zeigt „Neu &amp; frisch“ — Bludit-Fehler, siehe unten.)*
- [x] I34 Testepisode im Podcast-Admin löschen: verschwindet aus Liste, Feed, `/tag/podcast` und `rss.xml`; im Systemprotokoll steht „Inhalt gelöscht“.
- [ ] I35 *(nur falls vorhanden; Live nicht zutreffend, keine Unterseiten — lokal getestet)* Eine Episode, die Unterseite einer anderen Seite ist, im Podcast-Formular speichern: Ihre URL bleibt gleich. Eine Episode mit Unterseiten hat im ☰-Menü kein „Löschen“.

## J · Episoden-API im Admin-Bereich, Feed-Adresse (004, 010)
Nach dem Update eine offene Seite **Admin → Podcast** neu laden (neue API-Adresse).
- [x] J36 Episode mit **Audio-Upload** anlegen, bearbeiten und wieder löschen: alles ohne Fehlermeldung. In den Entwicklertools des Browsers (Netzwerk) gehen die Anfragen an `…/admin/plugin/podcast?podcast_api=1`.
- [x] J37 Feed mit und ohne Parameter erreichbar, beide Male `200` und `application/rss+xml`:
  ```sh
  curl -s -o /dev/null -w "%{http_code} %{content_type}\n" "$SITE/podcast.xml"
  curl -s -o /dev/null -w "%{http_code} %{content_type}\n" "$SITE/podcast.xml?nocache=$RANDOM"
  ```
- [x] J38 Der alte API-Weg über die Website ändert nichts mehr (erwartet: HTML der Startseite, kein JSON):
  ```sh
  curl -s -X POST "$SITE/?podcast_api=1" -d "podcast_api_action=delete_episode" | head -c 80; echo
  ```
- [ ] J39 *(optional; live übersprungen, lokal getestet)* Zu große Datei ohne Browser-Prüfung (wie F20, z. B. Version vor #55 im Browser-Cache oder Limits kurz senken): Meldung „Upload zu groß …“, man bleibt angemeldet.

Lokal zusätzlich getestet (nicht auf der Live-Seite nötig): Bludit unter `/blog/`, Rolle „Autor“ (bekommt „Keine Berechtigung.“), abgelaufene Sitzung (Hinweis statt „Netzwerkfehler“).

## K · Plugin-Metadaten (034)
- [x] K40 **Einstellungen → Plugins**: Beim Podcast-Plugin kein Hinweis „Update“, Autor `larsauswsw`. In den Kanaleinstellungen kein gelber Kasten „möglicherweise nicht unterstützt“.
- [x] K41 Bei deutscher Website-Sprache steht in der Plugin-Liste die deutsche Beschreibung „Macht aus Bludit-Seiten einen Podcast – mit Episodenverwaltung, Podcast-Feed und Podlove Web Player.“ (vorher englisch, weil `de.json` statt `de_DE.json`).

## L · Status und Beschreibung (007)
- [x] L42 Episode mit Datum in der Zukunft und Status „Veröffentlicht“ speichern: In der Liste Badge „Geplant“, im Feed noch nicht; im Formular steht „Veröffentlicht“, nach erneutem Speichern bleibt sie geplant.
- [x] L43 Beschreibung leeren und speichern → Meldung „Bitte eine Beschreibung eingeben.“, nichts wird geändert.
- [x] L44 *(nur falls vorhanden; live getestet)* Eine im Bludit-Editor fixierte Episode: Badge „Fixiert“, im Formular Status „Fixiert“, bleibt nach dem Speichern fixiert und steht im Feed.

## M · Player im Seiteninhalt (029)
- [x] M45 Episodenseite mit deinem Theme: Player steht zwischen Titel und Text (bzw. am Anfang des Inhaltsbereichs), ohne beim Laden zu springen; genau ein Player.
- [x] M46 **Kanaleinstellungen → Position des Players → Am Ende des Inhalts**: Player steht unter dem Text. Danach wieder auf „Am Anfang“.
- [x] M47 In den Text einer Episode eine Zeile `{{ podcast-player }}` schreiben: Der Player steht genau dort. Auf der Startseite und im Feed ist der Platzhalter nicht zu sehen:
  ```sh
  curl -s "$SITE/?nocache=$RANDOM" | grep -c "podcast-player"
  curl -s "$SITE/podcast.xml?nocache=$RANDOM" | grep -c "podcast-player"
  ```
  Erwartet: beide `0`. Danach die Zeile wieder entfernen.

## N · Fehlermeldungen (008)
- [x] N48 **Admin → Podcast** in zwei Browser-Tabs öffnen. Im zweiten Tab abmelden, im ersten eine Episode öffnen und speichern → Meldung „Sitzung abgelaufen oder Sicherheits-Token ungültig …“ statt „Netzwerkfehler“. Danach neu anmelden.

## O · Datum und Zeitzone (009)
- [x] O49 **Neue Episode**: Das Datum ist mit der aktuellen Uhrzeit vorbelegt (vorher 1–2 Stunden zu früh). Unter dem Feld steht „Zeitzone der Website: …“ wie unter **Einstellungen → Allgemein → Sprache → Zeitzone**.
- [x] O50 *(am Computer)* Im Datumsfeld einen Teil löschen (z. B. die Minuten) und speichern → Meldung „Bitte ein gültiges Veröffentlichungsdatum eingeben.“, nichts wird gespeichert.
- [x] O51 Episode mit Datum in der Zukunft (z. B. morgen 06:00) und Status „Veröffentlicht“ speichern → in der Liste Badge „Geplant“, darunter Datum mit Uhrzeit („…, 06:00 Uhr“). Beim Bearbeiten steht dieselbe Uhrzeit im Feld. Danach Datum zurücksetzen oder Episode löschen.

## P · Kanaleinstellungen und Feed-Adresse (043, 017)
- [x] P52 **Kanaleinstellungen**: fünf Gruppen mit Zwischenüberschrift (Podcast · Apple Podcasts & Verzeichnisse · Feed · Website & Player · Uploads). Alle bisherigen Werte stehen noch drin. Auf dem Handy ohne seitliches Scrollen.
- [x] P53 Gruppe **Feed**: „Adresse des Feeds“ zeigt `$SITE/podcast.xml` (grau hinterlegt, nicht änderbar); „Feed öffnen“ öffnet den Feed. Steht unter „Abweichende Feed-URL“ ein Wert, der nicht nötig ist: notieren, leeren und speichern — die Adresse bleibt gleich.
- [x] P54 Feed und Discovery-Link:
  ```sh
  curl -s "$SITE/podcast.xml?t=$(date +%s)" | grep "atom:link"
  curl -s "$SITE/?t=$(date +%s)" | grep "application/rss+xml"
  ```
  Erwartet: `<atom:link href="$SITE/podcast.xml" rel="self" …>` (vorher ohne Feed-URL nicht vorhanden); auf der Startseite ein `<link rel="alternate" …>` mit dem Podcast-Titel und dieser Adresse (daneben ggf. der des Core-RSS-Plugins).
- [ ] P55 Episodenseite: Der Podlove-Player zeigt den Abo-Button. „Abonnieren“ öffnet „Abonnieren bei“ mit Apps für das Gerät (auf dem iPhone u. a. Overcast und Downcast, auf Android u. a. AntennaPod), dazu Player.fm und RSS; „RSS“ öffnet den Feed. *(Nach #78 war das Fenster leer.)*
- [x] P56 Unter „Abweichende Feed-URL“ `feed.xml` (ohne `https://`) speichern → Hinweis „Wird nicht verwendet …“, „Adresse des Feeds“ bleibt `$SITE/podcast.xml`. Danach das Feld wieder leeren.

## Bekannte offene Punkte (keine Testfehler)
- Dashboard „Aktivitäten“ zeigt `&` in Titeln als `&amp;`: Bludit-Fehler (`syslog.class.php` speichert die Notiz escaped, `admin/views/dashboard.php` escaped erneut), betrifft auch Seiten aus dem Bludit-Editor; vom Plugin nicht behebbar.

Bei Fehlern: `tail -n 20 $BLUDIT/bl-content/databases/syslog.php` und das PHP-Error-Log.
