/**
 * Podcast Plugin Admin UI
 * Handles: episode list ↔ form toggle, URL/file switching, AJAX save/delete
 */
(function () {
    'use strict';

    // ── DOM refs ──────────────────────────────────────────────────────
    var list      = document.getElementById('podcast-episode-list');
    var form      = document.getElementById('podcast-episode-form');
    var formTitle = document.getElementById('podcast-form-title');
    var newBtn    = document.getElementById('podcast-new-btn');
    var saveBtn   = document.getElementById('podcast-save-btn');
    var cancelBtn = document.getElementById('podcast-cancel-btn');
    var saving    = document.getElementById('podcast-saving');
    var errorBox  = document.getElementById('podcast-save-error');

    var peKey       = document.getElementById('pe-key');
    var peTitle     = document.getElementById('pe-title');
    var peContent   = document.getElementById('pe-content');
    var peEpisode   = document.getElementById('pe-episode');
    var peSeason    = document.getElementById('pe-season');
    var peType      = document.getElementById('pe-type');
    var peExplicit  = document.getElementById('pe-explicit');
    var peDuration  = document.getElementById('pe-duration');
    var peDate      = document.getElementById('pe-date');
    var peStatus    = document.getElementById('pe-status');
    var peImageUrl  = document.getElementById('pe-image-url');
    var peImageFile = document.getElementById('pe-image-file');
    var peAudioUrl  = document.getElementById('pe-audio-url');
    var peAudioFile = document.getElementById('pe-audio-file');
    var peImageFileWrap = document.getElementById('pe-image-file-wrap');
    var peAudioFileWrap = document.getElementById('pe-audio-file-wrap');
    var peFormatHint    = document.getElementById('pe-audio-format-hint');
    var peImagePreview  = document.getElementById('pe-image-preview');
    var previewObjectUrl = null;

    // ── Kanaleinstellungen: Cover hochladen (Roadmap 022) ─────────────
    // Lädt das Bild über die Episoden-API hoch (dort geprüft) und trägt die
    // Adresse in „Cover-URL“ ein; gespeichert wird mit Bludits „Speichern“.
    var coverFile = document.getElementById('podcast-cover-file');
    if (coverFile) {
        var coverUrl     = document.getElementById('podcast-cover');
        var coverStatus  = document.getElementById('podcast-cover-status');
        var coverPreview = document.getElementById('podcast-cover-preview');
        var setCoverStatus = function (text, isError) {
            coverStatus.textContent = text;
            coverStatus.classList.toggle('podcast-tip-error', !!isError);
        };
        coverFile.addEventListener('change', function () {
            var file = coverFile.files && coverFile.files[0];
            if (!file) return;
            var maxFile = parseInt(coverFile.dataset.maxFile, 10) || 0;
            if (maxFile && file.size > maxFile) {
                setCoverStatus('Die Datei ist größer als das Upload-Limit von ' +
                    (maxFile / 1048576).toFixed(1).replace(/\.0$/, '').replace('.', ',') + ' MB.', true);
                coverFile.value = '';
                return;
            }
            var fd = new FormData();
            fd.append('tokenCSRF', (typeof tokenCSRF !== 'undefined' ? tokenCSRF : ''));
            fd.append('podcast_api_action', 'upload_cover');
            fd.append('cover_file', file);
            coverFile.disabled = true;
            setCoverStatus('Wird hochgeladen und geprüft …', false);
            fetch(coverFile.dataset.apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (response) {
                    return response.json().catch(function () {
                        throw new Error('Unerwartete Antwort vom Server. Bitte die Seite neu laden.');
                    });
                })
                .then(function (data) {
                    if (!data || data.error || !data.url) {
                        throw new Error(data && data.error ? data.error : 'Upload fehlgeschlagen.');
                    }
                    coverUrl.value = data.url;
                    coverPreview.src = data.url;
                    coverPreview.hidden = false;
                    setCoverStatus('Hochgeladen und geprüft. Zum Übernehmen oben „Speichern“ klicken.', false);
                })
                .catch(function (err) {
                    setCoverStatus(err && err.message ? err.message : 'Netzwerkfehler beim Hochladen.', true);
                })
                .then(function () {
                    coverFile.disabled = false;
                    coverFile.value = '';
                });
        });
    }

    // Nur auf der Podcast-Seite aktiv
    if (!list) return;

    // Hinweis nach dem Speichern (z. B. Dateigröße nicht ermittelt): übersteht
    // das Neuladen der Seite in sessionStorage, Anzeige nur als Text
    var NOTICE_KEY = 'podcast-admin-notice';
    function showStoredNotice() {
        var text = null;
        try {
            text = window.sessionStorage.getItem(NOTICE_KEY);
            window.sessionStorage.removeItem(NOTICE_KEY);
        } catch (e) { /* Speicher nicht verfügbar */ }
        if (!text) return;
        var box = document.createElement('div');
        box.className = 'alert alert-warning mt-3';
        box.setAttribute('role', 'alert');
        box.textContent = text;
        list.insertBefore(box, list.firstChild);
    }
    showStoredNotice();

    // ── helpers ───────────────────────────────────────────────────────

    function showForm(title) {
        formTitle.textContent = title;
        list.hidden     = true;
        form.hidden     = false;
        errorBox.hidden = true;
        window.scrollTo(0, 0);
    }

    function showList() {
        form.hidden = true;
        list.hidden = false;
    }

    // Zeigt im Upload-Feld den gewählten Dateinamen (nur als Text) oder den Platzhalter
    function updateFileLabel(input) {
        var label = document.querySelector('label[for="' + input.id + '"]');
        if (!label) return;
        label.textContent = input.files && input.files[0]
            ? input.files[0].name
            : (label.dataset.placeholder || '');
    }

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    // „Jetzt“ für das Datumsfeld (datetime-local) in der Zeitzone der Website:
    // so liest der Server das Datum. toISOString() wäre UTC. Ohne Intl oder
    // bei unbekannter Zeitzone gilt die Uhrzeit des Browsers.
    function nowForDateInput() {
        var now = new Date();
        var tz  = peDate.dataset.timezone;
        if (tz && window.Intl && Intl.DateTimeFormat.prototype.formatToParts) {
            try {
                var p = {};
                new Intl.DateTimeFormat('en-GB', {
                    timeZone: tz, hour12: false,
                    year: 'numeric', month: '2-digit', day: '2-digit',
                    hour: '2-digit', minute: '2-digit'
                }).formatToParts(now).forEach(function (part) { p[part.type] = part.value; });
                // Ältere Browser liefern um Mitternacht „24“
                var value = p.year + '-' + p.month + '-' + p.day + 'T' +
                    (p.hour === '24' ? '00' : p.hour) + ':' + p.minute;
                if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) return value;
            } catch (e) { /* unbekannte Zeitzone */ }
        }
        return now.getFullYear() + '-' + pad2(now.getMonth() + 1) + '-' + pad2(now.getDate()) +
            'T' + pad2(now.getHours()) + ':' + pad2(now.getMinutes());
    }

    // Apple Podcasts spielt WAV/OGG nicht ab: Hinweis bei gewählter Datei oder URL
    function updateFormatHint() {
        if (!peFormatHint) return;
        var mode = document.querySelector('input[name="pe-audio-mode"]:checked');
        var name;
        if (mode && mode.value === 'file') {
            name = peAudioFile.files && peAudioFile.files[0] ? peAudioFile.files[0].name : '';
        } else {
            name = peAudioUrl.value.split(/[?#]/)[0];
        }
        peFormatHint.hidden = !/\.(wav|ogg|oga|opus)$/i.test(name.trim());
    }

    // Vorschau des Episodenbilds: gewählte Datei oder http(s)-Adresse
    function updateImagePreview() {
        if (!peImagePreview) return;
        if (previewObjectUrl) {
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }
        var mode = document.querySelector('input[name="pe-image-mode"]:checked');
        var src  = '';
        if (mode && mode.value === 'file') {
            if (peImageFile.files && peImageFile.files[0] && window.URL && URL.createObjectURL) {
                previewObjectUrl = URL.createObjectURL(peImageFile.files[0]);
                src = previewObjectUrl;
            }
        } else if (/^https?:\/\/\S+$/i.test(peImageUrl.value.trim())) {
            src = peImageUrl.value.trim();
        }
        if (src) {
            peImagePreview.src = src;
            peImagePreview.hidden = false;
        } else {
            peImagePreview.hidden = true;
            peImagePreview.removeAttribute('src');
        }
    }

    function clearForm() {
        peKey.value      = '';
        peTitle.value    = '';
        peContent.value  = '';
        peEpisode.value  = '';
        peSeason.value   = '';
        peType.value     = 'full';
        peExplicit.value = 'no';
        peDuration.value = '';
        peDate.value     = nowForDateInput();
        setStatus('published');
        peImageUrl.value = '';
        peAudioUrl.value = '';
        peImageFile.value = '';
        peAudioFile.value = '';
        updateFileLabel(peImageFile);
        updateFileLabel(peAudioFile);
        setMode('pe-audio-mode', 'url');
        setMode('pe-image-mode', 'url');
        updateFormatHint();
        updateImagePreview();
    }

    // Fixiert/statisch gibt es im Formular nur, wenn die Episode diesen Typ schon
    // hat (im Bludit-Editor gesetzt); dann als zusätzliche Option, damit er bleibt.
    var EXTRA_STATUS = { sticky: 'Fixiert', static: 'Statisch' };

    function setStatus(type) {
        var extra = peStatus.querySelector('option[data-extra]');
        if (extra) peStatus.removeChild(extra);
        if (Object.prototype.hasOwnProperty.call(EXTRA_STATUS, type)) {
            var opt = document.createElement('option');
            opt.value = type;
            opt.textContent = EXTRA_STATUS[type];
            opt.setAttribute('data-extra', '');
            peStatus.appendChild(opt);
        }
        // „Geplant“ = veröffentlicht mit Datum in der Zukunft (setzt Bludit)
        peStatus.value = type === 'draft' || Object.prototype.hasOwnProperty.call(EXTRA_STATUS, type) ? type : 'published';
    }

    function setMode(radioName, value) {
        var radios = document.querySelectorAll('input[name="' + radioName + '"]');
        radios.forEach(function (r) { r.checked = (r.value === value); });
        updateModeVisibility(radioName);
    }

    function updateModeVisibility(radioName) {
        var selected = document.querySelector('input[name="' + radioName + '"]:checked');
        if (!selected) return;
        var isUrl = selected.value === 'url';
        if (radioName === 'pe-audio-mode') {
            peAudioUrl.hidden      = !isUrl;
            peAudioFileWrap.hidden = isUrl;
        } else {
            peImageUrl.hidden      = !isUrl;
            peImageFileWrap.hidden = isUrl;
        }
    }

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.hidden      = false;
    }

    function formatMB(bytes) {
        return (bytes / 1048576).toFixed(1).replace(/\.0$/, '').replace('.', ',') + ' MB';
    }

    // Liefert eine Fehlermeldung, wenn eine Datei oder alle zusammen zu groß sind
    function checkUploadLimits(files) {
        var limitEl = document.getElementById('pe-upload-limit');
        if (!limitEl || !files.length) return '';
        var maxFile = parseInt(limitEl.dataset.maxFile, 10) || 0;
        var maxPost = parseInt(limitEl.dataset.maxPost, 10) || 0;
        var total   = 0;
        for (var i = 0; i < files.length; i++) {
            if (maxFile && files[i].size > maxFile) {
                return 'Die Datei „' + files[i].name + '“ (' + formatMB(files[i].size) +
                    ') ist größer als das Upload-Limit von ' + formatMB(maxFile) + '.';
            }
            total += files[i].size;
        }
        if (maxPost && total > maxPost) {
            return 'Audio und Bild zusammen (' + formatMB(total) + ') überschreiten das Server-Limit von ' +
                formatMB(maxPost) + '. Bitte einzeln hochladen oder eine URL verwenden.';
        }
        return '';
    }

    var SESSION_ERROR = 'Sitzung abgelaufen oder Sicherheits-Token ungültig. ' +
        'Bitte neu anmelden und es noch einmal versuchen.';

    // Die API antwortet immer mit JSON. Alles andere kommt nicht vom Plugin:
    // - Umleitung zur Anmeldung: Bludit hat die Anfrage abgelehnt (Sitzung
    //   abgelaufen, Token ungültig)
    // - HTTP 413: Der Webserver selbst lehnt die Größe ab (vor PHP)
    // - sonstiger Fehlerstatus: z. B. PHP-Fehler (500)
    function readJson(response) {
        var type = response.headers.get('Content-Type') || '';
        if (type.indexOf('application/json') !== -1) {
            return response.json();
        }
        var msg;
        if (response.redirected && /\/login\/?$/.test(new URL(response.url).pathname)) {
            msg = SESSION_ERROR;
        } else if (response.status === 413) {
            msg = 'Die Anfrage ist für den Webserver zu groß (HTTP 413). Bitte eine kleinere Datei ' +
                'oder eine Audio-URL verwenden; das Limit legt die Server-Konfiguration fest ' +
                '(z. B. LimitRequestBody bei Apache, client_max_body_size bei Nginx).';
        } else if (!response.ok) {
            msg = 'Serverfehler (HTTP ' + response.status + '). Details stehen im Bludit- bzw. PHP-Fehlerlog.';
        } else {
            msg = 'Unerwartete Antwort vom Server. Bitte die Seite neu laden und es noch einmal versuchen.';
        }
        var err = new Error(msg);
        err.isServerResponse = true;
        throw err;
    }

    function setSaving(on) {
        saveBtn.disabled = on;
        saving.hidden    = !on;
    }

    // ── radio toggle listeners ────────────────────────────────────────

    document.querySelectorAll('input[name="pe-audio-mode"], input[name="pe-image-mode"]')
        .forEach(function (r) {
            r.addEventListener('change', function () {
                updateModeVisibility(r.name);
                updateFormatHint();
                updateImagePreview();
            });
        });

    [peImageFile, peAudioFile].forEach(function (input) {
        input.addEventListener('change', function () {
            updateFileLabel(input);
            updateFormatHint();
            updateImagePreview();
        });
    });
    peAudioUrl.addEventListener('input', updateFormatHint);
    peImageUrl.addEventListener('input', updateImagePreview);

    // ── new episode ───────────────────────────────────────────────────

    newBtn.addEventListener('click', function (e) {
        e.preventDefault();
        clearForm();
        showForm('Neue Episode');
    });

    // ── cancel ────────────────────────────────────────────────────────

    cancelBtn.addEventListener('click', showList);

    // ── edit ──────────────────────────────────────────────────────────

    // Daten der Episode stehen an der Tabellenzeile (Titel-Link und Menüeintrag)
    function episodeRow(btn) {
        return btn.closest('.podcast-episode-row');
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.podcast-edit-btn');
        var row = btn && episodeRow(btn);
        if (!row) return;
        e.preventDefault();
        var d = row.dataset;
        clearForm();
        peKey.value      = d.key      || '';
        peTitle.value    = d.title    || '';
        peContent.value  = d.content  || '';
        peEpisode.value  = d.episode  || '';
        peSeason.value   = d.season   || '';
        peType.value     = d.type     || 'full';
        peExplicit.value = d.explicit || 'no';
        peDuration.value = d.duration || '';
        peDate.value     = d.date     || nowForDateInput();
        setStatus(d.status || 'published');
        peImageUrl.value = d.image    || '';
        peAudioUrl.value = d.audioUrl || '';
        // Gespeicherte Dateien stehen als URL im Feld; clearForm() hat den
        // Modus bereits auf „URL“ gesetzt
        updateFormatHint();
        updateImagePreview();
        showForm('Episode bearbeiten');
    });

    // ── delete ────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.podcast-delete-btn');
        var row = btn && episodeRow(btn);
        if (!row) return;
        e.preventDefault();
        var title = row.dataset.title;
        var question = title ? 'Episode „' + title + '“ wirklich löschen?' : 'Episode ohne Titel wirklich löschen?';
        if (!confirm(question)) return;

        var body = new URLSearchParams({
            tokenCSRF:          (typeof tokenCSRF !== 'undefined' ? tokenCSRF : ''),
            podcast_api_action: 'delete_episode',
            episode_key:        row.dataset.key
        });

        fetch(PODCAST_API_URL, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(readJson)
            .then(function (data) {
                if (data.error) {
                    alert('Fehler: ' + data.error);
                    return;
                }
                location.reload();
            })
            .catch(function (err) {
                alert(err && err.isServerResponse ? err.message : 'Netzwerkfehler beim Löschen.');
            });
    });

    // ── save ──────────────────────────────────────────────────────────

    saveBtn.addEventListener('click', function () {
        var title     = peTitle.value.trim();
        var audioMode = document.querySelector('input[name="pe-audio-mode"]:checked').value;
        var imageMode = document.querySelector('input[name="pe-image-mode"]:checked').value;
        var audioUrl  = peAudioUrl.value.trim();
        var audioFile = peAudioFile.files[0];

        if (!title) {
            showError('Bitte Titel eingeben.');
            return;
        }
        if (!peContent.value.trim()) {
            showError('Bitte eine Beschreibung eingeben.');
            return;
        }
        if (audioMode === 'url' && !audioUrl) {
            showError('Bitte Audio-URL eingeben oder eine Datei hochladen.');
            return;
        }
        if (audioMode === 'file' && !audioFile) {
            showError('Bitte eine Audiodatei auswählen.');
            return;
        }
        // Unvollständige Eingaben liefert das Feld als leeren Wert
        if (!peDate.value) {
            showError('Bitte ein gültiges Veröffentlichungsdatum eingeben.');
            return;
        }

        // Upload-Limits vor dem Hochladen prüfen (Werte vom Server, 0 = unbegrenzt)
        var uploads = [];
        if (audioMode === 'file' && audioFile) uploads.push(audioFile);
        if (imageMode === 'file' && peImageFile.files[0]) uploads.push(peImageFile.files[0]);
        var limitMsg = checkUploadLimits(uploads);
        if (limitMsg) {
            showError(limitMsg);
            return;
        }

        var fd = new FormData();
        fd.append('tokenCSRF',          (typeof tokenCSRF !== 'undefined' ? tokenCSRF : ''));
        fd.append('podcast_api_action', 'save_episode');
        fd.append('key',       peKey.value);
        fd.append('title',     title);
        fd.append('content',   peContent.value.trim());
        fd.append('episode',   peEpisode.value);
        fd.append('season',    peSeason.value);
        fd.append('type',      peType.value);
        fd.append('explicit',  peExplicit.value);
        fd.append('duration',  peDuration.value.trim());
        fd.append('date',      peDate.value);
        fd.append('status',    peStatus.value);
        fd.append('image_url', imageMode === 'url' ? peImageUrl.value.trim() : '');
        fd.append('audio_url', audioMode === 'url' ? audioUrl : '');

        if (imageMode === 'file' && peImageFile.files[0]) {
            fd.append('image_file', peImageFile.files[0]);
        }
        if (audioMode === 'file' && audioFile) {
            fd.append('audio_file', audioFile);
        }

        setSaving(true);
        errorBox.hidden = true;

        fetch(PODCAST_API_URL, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(readJson)
            .then(function (data) {
                setSaving(false);
                if (data.error) {
                    showError(data.error);
                    return;
                }
                if (data.warning) {
                    try {
                        window.sessionStorage.setItem(NOTICE_KEY, String(data.warning));
                    } catch (e) {
                        window.alert(String(data.warning));
                    }
                }
                location.reload();
            })
            .catch(function (err) {
                setSaving(false);
                showError(err && err.isServerResponse ? err.message : 'Netzwerkfehler beim Speichern.');
            });
    });

    // ── init visibility ───────────────────────────────────────────────

    updateModeVisibility('pe-audio-mode');
    updateModeVisibility('pe-image-mode');

}());
