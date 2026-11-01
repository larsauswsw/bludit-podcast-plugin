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

    // Nur auf der Podcast-Seite aktiv
    if (!list) return;

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

    function clearForm() {
        peKey.value      = '';
        peTitle.value    = '';
        peContent.value  = '';
        peEpisode.value  = '';
        peSeason.value   = '';
        peType.value     = 'full';
        peExplicit.value = 'no';
        peDuration.value = '';
        peDate.value     = new Date().toISOString().slice(0, 16);
        setStatus('published');
        peImageUrl.value = '';
        peAudioUrl.value = '';
        peImageFile.value = '';
        peAudioFile.value = '';
        updateFileLabel(peImageFile);
        updateFileLabel(peAudioFile);
        setMode('pe-audio-mode', 'url');
        setMode('pe-image-mode', 'url');
    }

    // Fixiert/statisch gibt es im Formular nur, wenn die Episode diesen Typ schon
    // hat (im Bludit-Editor gesetzt); dann als zusätzliche Option, damit er bleibt.
    var EXTRA_STATUS = { sticky: 'Fixiert', static: 'Statisch' };

    function setStatus(type) {
        var extra = peStatus.querySelector('option[data-extra]');
        if (extra) peStatus.removeChild(extra);
        if (EXTRA_STATUS.hasOwnProperty(type)) {
            var opt = document.createElement('option');
            opt.value = type;
            opt.textContent = EXTRA_STATUS[type];
            opt.setAttribute('data-extra', '');
            peStatus.appendChild(opt);
        }
        // „Geplant“ = veröffentlicht mit Datum in der Zukunft (setzt Bludit)
        peStatus.value = type === 'draft' || EXTRA_STATUS.hasOwnProperty(type) ? type : 'published';
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

    // Die API antwortet mit JSON. Lehnt Bludit die Anfrage ab (Sitzung abgelaufen,
    // Token ungültig), leitet es auf die Login-Seite um — dann kommt HTML zurück.
    function readJson(response) {
        var type = response.headers.get('Content-Type') || '';
        if (type.indexOf('application/json') === -1) {
            var err = new Error(SESSION_ERROR);
            err.isSession = true;
            throw err;
        }
        return response.json();
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
            });
        });

    [peImageFile, peAudioFile].forEach(function (input) {
        input.addEventListener('change', function () {
            updateFileLabel(input);
        });
    });

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
        peDate.value     = d.date     || new Date().toISOString().slice(0, 16);
        setStatus(d.status || 'published');
        peImageUrl.value = d.image    || '';
        peAudioUrl.value = d.audioUrl || '';
        // Gespeicherte Dateien stehen als URL im Feld; clearForm() hat den
        // Modus bereits auf „URL“ gesetzt
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
                alert(err && err.isSession ? SESSION_ERROR : 'Netzwerkfehler beim Löschen.');
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
                location.reload();
            })
            .catch(function (err) {
                setSaving(false);
                showError(err && err.isSession ? SESSION_ERROR : 'Netzwerkfehler beim Speichern.');
            });
    });

    // ── init visibility ───────────────────────────────────────────────

    updateModeVisibility('pe-audio-mode');
    updateModeVisibility('pe-image-mode');

}());
