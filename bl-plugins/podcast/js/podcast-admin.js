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

    // Nur auf der Podcast-Seite aktiv
    if (!list) return;

    // ── helpers ───────────────────────────────────────────────────────

    function showForm(title) {
        formTitle.textContent  = title;
        list.style.display     = 'none';
        form.style.display     = 'block';
        errorBox.style.display = 'none';
    }

    function showList() {
        form.style.display = 'none';
        list.style.display = 'block';
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
        peStatus.value   = 'published';
        peImageUrl.value = '';
        peAudioUrl.value = '';
        peImageFile.value = '';
        peAudioFile.value = '';
        setMode('pe-audio-mode', 'url');
        setMode('pe-image-mode', 'url');
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
            peAudioUrl.style.display  = isUrl ? 'block' : 'none';
            peAudioFile.style.display = isUrl ? 'none'  : 'block';
        } else {
            peImageUrl.style.display  = isUrl ? 'block' : 'none';
            peImageFile.style.display = isUrl ? 'none'  : 'block';
        }
    }

    function showError(msg) {
        errorBox.textContent   = msg;
        errorBox.style.display = 'block';
    }

    function setSaving(on) {
        saveBtn.disabled     = on;
        saving.style.display = on ? 'inline' : 'none';
    }

    // ── radio toggle listeners ────────────────────────────────────────

    document.querySelectorAll('input[name="pe-audio-mode"], input[name="pe-image-mode"]')
        .forEach(function (r) {
            r.addEventListener('change', function () {
                updateModeVisibility(r.name);
            });
        });

    // ── new episode ───────────────────────────────────────────────────

    newBtn.addEventListener('click', function () {
        clearForm();
        showForm('Neue Episode');
    });

    // ── cancel ────────────────────────────────────────────────────────

    cancelBtn.addEventListener('click', showList);

    // ── edit ──────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.podcast-edit-btn');
        if (!btn) return;
        var d = btn.dataset;
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
        peStatus.value   = d.status   || 'published';
        peImageUrl.value = d.image    || '';
        peAudioUrl.value = d.audioUrl || '';
        // Audio/Bild-Modus setzen
        setMode('pe-audio-mode', d.audioUrl ? 'url' : 'url');
        setMode('pe-image-mode', d.image    ? 'url' : 'url');
        showForm('Episode bearbeiten');
    });

    // ── delete ────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.podcast-delete-btn');
        if (!btn) return;
        if (!confirm('Episode "' + btn.dataset.title + '" wirklich löschen?')) return;

        var body = new URLSearchParams({
            tokenCSRF:          (typeof tokenCSRF !== 'undefined' ? tokenCSRF : ''),
            podcast_api_action: 'delete_episode',
            episode_key:        btn.dataset.key
        });

        fetch(PODCAST_API_URL, { method: 'POST', body: body })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    alert('Fehler: ' + data.error);
                    return;
                }
                location.reload();
            })
            .catch(function () {
                alert('Netzwerkfehler beim Löschen.');
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
        if (audioMode === 'url' && !audioUrl) {
            showError('Bitte Audio-URL eingeben oder eine Datei hochladen.');
            return;
        }
        if (audioMode === 'file' && !audioFile) {
            showError('Bitte eine Audiodatei auswählen.');
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
        errorBox.style.display = 'none';

        fetch(PODCAST_API_URL, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                setSaving(false);
                if (data.error) {
                    showError(data.error);
                    return;
                }
                location.reload();
            })
            .catch(function () {
                setSaving(false);
                showError('Netzwerkfehler beim Speichern.');
            });
    });

    // ── init visibility ───────────────────────────────────────────────

    updateModeVisibility('pe-audio-mode');
    updateModeVisibility('pe-image-mode');

}());
