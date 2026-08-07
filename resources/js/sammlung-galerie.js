/**
 * Sammlungen – Galerie + Lightbox + Sidebar + Abgleich
 *
 * Erwartet vor dem Laden gesetzt:
 *   window.archivConfig = {
 *       toggleRoute:           '...',   // POST sammlung-medium
 *       renameRoute:           '...',   // POST datei-umbenennen
 *       csrf:                  '...',   // CSRF-Token
 *       individualUrlTemplate: '...',   // URL mit _XREF_ als Platzhalter
 *       texte:                 {...},   // übersetzte Oberflächentexte
 *   }
 *
 * JavaScript kann I18N::translate() nicht aufrufen. Alle Texte, die dieses
 * Skript zur Laufzeit einsetzt, kommen deshalb übersetzt aus `texte`; die
 * Vorgaben unten greifen nur, wenn die Seite sie nicht mitgibt.
 */

document.addEventListener('DOMContentLoaded', function () {
    const cfg = window.archivConfig || {};
    // Uebersetzte Texte aus der Konfiguration; faellt auf den deutschen
    // Quelltext zurueck, falls die Seite sie nicht mitgibt.
    const T = Object.assign({
        beschreibungTitel: 'Beschreibung / Titel', personen: 'Personen',
        leer: '(leer)', keine: '(keine)', inExifUebernehmen: '→ in EXIF übernehmen',
        undWeitere: '… und %s weitere', insgesamt: '… (%s insgesamt)',
        speichern: 'Speichern…', gespeichert: 'Gespeichert',
        umbenennen: 'Umbenennen…', umbenannt: 'Umbenannt',
        fehler: 'Fehler', netzwerkfehler: 'Netzwerkfehler'
    }, cfg.texte || {});
    const platzhalter = (text, wert) => String(text).replace('%s', wert);
    const items = [...document.querySelectorAll('.archiv-gallery-item')];
    if (items.length === 0) return;

    let modal = null;
    let current = 0;

    const toggleRoute = cfg.toggleRoute || '';
    const renameRoute = cfg.renameRoute || '';
    const csrf = cfg.csrf || '';
    const img = document.getElementById('archiv-lb-img');
    const caption = document.getElementById('archiv-lb-caption');
    const meta = document.getElementById('archiv-lb-meta');
    const link = document.getElementById('archiv-lb-link');
    const fullsize = document.getElementById('archiv-lb-fullsize');
    const sidebar = document.getElementById('archiv-lb-sidebar');
    const editBtn = document.getElementById('archiv-lb-edit-btn');
    const status = document.getElementById('archiv-edit-status');
    const thumbstrip = document.getElementById('archiv-lb-thumbstrip');

    // Thumbnail-Streifen aufbauen
    items.forEach((el, idx) => {
        const info = JSON.parse(el.dataset.info || '{}');
        const thumb = info.thumb || el.dataset.full;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'archiv-lb-thumb flex-shrink-0 p-0 border-0 bg-transparent';
        btn.dataset.idx = idx;
        btn.innerHTML = `<img src="${thumb}" alt="" loading="lazy"
            style="width:54px;height:54px;object-fit:cover;display:block;border-radius:3px;opacity:.5;transition:opacity .15s,outline .1s">`;
        btn.addEventListener('click', () => show(idx));
        thumbstrip.appendChild(btn);
    });

    function getModal() {
        if (!modal) modal = new bootstrap.Modal(document.getElementById('archiv-lightbox'));
        return modal;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function pfadAusUrl(url) {
        try { return new URL(url).searchParams.get('pfad') || ''; }
        catch { return url.includes('pfad=') ? decodeURIComponent(url.split('pfad=')[1].split('&')[0]) : ''; }
    }

    function personUrl(xref) {
        return (cfg.individualUrlTemplate || '').replace('_XREF_', xref);
    }

    function show(idx) {
        current = (idx + items.length) % items.length;
        const d = items[current].dataset;
        const info = JSON.parse(d.info || '{}');

        img.src = d.full;
        img.alt = d.title;
        caption.textContent = d.title;
        if (fullsize) fullsize.href = d.full;

        // + Zu Sammlung Button: nur für importierte Fotos
        const sammlungBtn = document.getElementById('archiv-lb-sammlung-btn');
        if (sammlungBtn) {
            const mId = info.media ? true : false;
            sammlungBtn.classList.toggle('d-none', !mId);

            const inSammlungen = info.in_sammlungen || [];
            document.querySelectorAll('.archiv-sammlung-toggle').forEach(btn => {
                const cid = parseInt(btn.dataset.cid);
                const drin = inSammlungen.includes(cid);
                btn.querySelector('.archiv-sammlung-check').textContent = drin ? '✓' : '○';
                btn.dataset.drin = drin ? '1' : '0';
            });
        }

        const parts = [];
        if (info.datum) parts.push('📅 ' + info.datum);
        // Bevorzugt die in webtrees verknüpften Personen; nur wenn es keine
        // gibt, die im Dateikopf hinterlegten Namen (info.personen).
        // Nur die ersten Namen: die Kopfzeile darf nicht schrumpfen, eine
        // ungekürzte Liste (z. B. ein Wappen an 300 Personen) würde umbrechen
        // und den Bildbereich darunter auf Höhe 0 quetschen.
        const wtNamen = (info.wt_personen || []).map(p => p.name);
        const personen = wtNamen.length ? wtNamen : (info.personen || []);
        if (personen.length) {
            const gesamt = wtNamen.length
                ? (info.personen_gesamt || wtNamen.length)
                : personen.length;
            const rest = gesamt - Math.min(3, personen.length);
            parts.push('👤 ' + personen.slice(0, 3).join(', ') + (rest > 0 ? ' +' + rest : ''));
        }
        meta.textContent = parts.join('  ·  ');

        link.classList.toggle('d-none', !info.media);
        if (info.media) link.href = info.media;

        // Thumbnail-Streifen: aktiven markieren + einblenden
        thumbstrip.querySelectorAll('.archiv-lb-thumb img').forEach((t, i) => {
            const aktiv = i === current;
            t.style.opacity = aktiv ? '1' : '.45';
            t.style.outline = aktiv ? '2px solid #fff' : 'none';
        });
        const aktivThumb = thumbstrip.querySelectorAll('.archiv-lb-thumb')[current];
        if (aktivThumb) aktivThumb.scrollIntoView({ inline: 'nearest', block: 'nearest' });

        if (sidebar && !sidebar.classList.contains('d-none')) {
            fillSidebar(d, info);
        }
    }

    function fillSidebar(d, info) {
        const exifBeschr = info.exif_beschreibung || '';
        document.getElementById('archiv-edit-beschreibung').value = exifBeschr;
        document.getElementById('archiv-edit-datum').value = info.datum_iso || '';
        document.getElementById('archiv-edit-personen').value = (info.personen || []).join(', ');
        document.getElementById('archiv-edit-keywords').value = (info.keywords || []).join(', ');
        if (status) status.textContent = '';

        // Dateiinfo
        const dateiSec = document.getElementById('archiv-datei-section');
        const dateiInfo = document.getElementById('archiv-datei-info');
        if (info.breite && dateiSec) {
            const kb = info.groesse_kb >= 1024
                ? (info.groesse_kb / 1024).toFixed(1) + ' MB'
                : info.groesse_kb + ' KB';
            dateiInfo.innerHTML =
                `📐 ${info.breite} × ${info.hoehe} px<br>💾 ${kb} · ${info.format || ''}`;
            dateiSec.classList.remove('d-none');
        } else if (dateiSec) {
            dateiSec.classList.add('d-none');
        }

        // Dateiname anzeigen + Rename-Vorbereitung
        const dateiNameText = document.getElementById('archiv-datei-name-text');
        const dateiNameDiv = document.getElementById('archiv-datei-name');
        const renameForm = document.getElementById('archiv-datei-rename-form');
        const aktuellerPfad = pfadAusUrl(d.full);
        if (dateiNameText && aktuellerPfad) {
            const dateiname = aktuellerPfad.split('/').pop();
            dateiNameText.textContent = dateiname;
            dateiNameText.title = aktuellerPfad;
            if (renameForm) renameForm.classList.add('d-none');
            if (dateiNameDiv) dateiNameDiv.classList.remove('d-none');
        }

        // Abgleich EXIF ↔ webtrees
        const abgleichSec = document.getElementById('archiv-abgleich-section');
        const abgleichList = document.getElementById('archiv-abgleich-list');
        if (abgleichSec && info.wt_titel !== undefined) {
            const diffs = [];
            const wtTitel = info.wt_titel || '';
            if (exifBeschr !== wtTitel && (exifBeschr || wtTitel)) {
                diffs.push({
                    feld: T.beschreibungTitel,
                    exif: exifBeschr || T.leer,
                    wt: wtTitel || T.leer,
                    btn: wtTitel ? wtTitel : null,
                    ziel: 'archiv-edit-beschreibung',
                });
            }
            const wtListe = (info.wt_personen || []).map(p => p.name);
            // Bei sehr vielen Verknüpfungen liegt nur ein Ausschnitt vor.
            // Dann keine Übernahme anbieten – sie würde die vollständige Liste
            // im Dateikopf durch den Ausschnitt ersetzen.
            const wtGekuerzt = (info.personen_gesamt || wtListe.length) > wtListe.length;
            const exifP = (info.personen || []).slice().sort().join(', ');
            const wtP = wtListe.slice().sort().join(', ');
            if (exifP !== wtP && (exifP || wtP)) {
                diffs.push({
                    feld: T.personen,
                    exif: exifP || T.keine,
                    wt: wtGekuerzt
                        ? wtP + ' ' + platzhalter(T.insgesamt, info.personen_gesamt)
                        : (wtP || T.keine),
                    btn: (wtP && !wtGekuerzt) ? wtP : null,
                    ziel: 'archiv-edit-personen',
                });
            }

            if (diffs.length) {
                abgleichList.innerHTML = diffs.map((d, i) => `
                    <div class="mb-3 pb-2 border-bottom border-secondary">
                        <div class="text-warning fw-semibold mb-1">${escapeHtml(d.feld)}</div>
                        <div class="text-white-50" style="font-size:.7rem">
                            <span class="badge bg-secondary me-1">EXIF</span> ${escapeHtml(d.exif)}<br>
                            <span class="badge bg-info me-1">webtrees</span> ${escapeHtml(d.wt)}
                        </div>
                        ${d.btn ? `<button type="button" class="btn btn-sm btn-outline-warning mt-1 abgleich-take"
                                  data-idx="${i}" style="font-size:.7rem">${escapeHtml(T.inExifUebernehmen)}</button>` : ''}
                    </div>
                `).join('');
                abgleichList.querySelectorAll('.abgleich-take').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const d = diffs[parseInt(btn.dataset.idx)];
                        const el = document.getElementById(d.ziel);
                        if (el && d.btn) {
                            el.value = d.btn;
                            el.style.background = '#3a3';
                            setTimeout(() => el.style.background = '', 600);
                        }
                    });
                });
                abgleichSec.classList.remove('d-none');
            } else {
                abgleichSec.classList.add('d-none');
            }
        }

        const wtSection = document.getElementById('archiv-wt-section');
        const wtPersonen = document.getElementById('archiv-wt-personen');
        const wtNotiz = document.getElementById('archiv-wt-notiz');
        const wtLink = document.getElementById('archiv-wt-link');

        const wtP = info.wt_personen || [];
        const hat = wtP.length || info.wt_notiz || info.wt_edit;

        if (hat) {
            wtSection.classList.remove('d-none');
            // wtP ist serverseitig gedeckelt (MAX_PERSONEN_JE_BILD). Den Rest
            // nicht ausgeben, sondern auf die webtrees-Medienseite verweisen –
            // dort stehen ohnehin alle Verknüpfungen.
            const gesamtP = info.personen_gesamt || wtP.length;
            let html = wtP.map(p =>
                `<a href="${escapeHtml(personUrl(p.xref))}" class="d-flex align-items-center gap-1 text-decoration-none mb-1"
                    style="color:#90cdf4;font-size:.85rem">
                    👤 ${escapeHtml(p.name)}
                 </a>`
            ).join('');
            if (gesamtP > wtP.length) {
                const weitere = gesamtP - wtP.length;
                html += info.wt_edit
                    ? `<a href="${escapeHtml(info.wt_edit)}" class="d-block text-decoration-none mb-1"
                          style="color:#90cdf4;font-size:.8rem">${escapeHtml(platzhalter(T.undWeitere, weitere))}</a>`
                    : `<div class="text-white-50 mb-1" style="font-size:.8rem">${escapeHtml(platzhalter(T.undWeitere, weitere))}</div>`;
            }
            wtPersonen.innerHTML = html;
            wtNotiz.textContent = info.wt_notiz || '';
            if (info.wt_edit) {
                wtLink.href = info.wt_edit;
                wtLink.classList.remove('d-none');
            } else {
                wtLink.classList.add('d-none');
            }
        } else {
            wtSection.classList.add('d-none');
        }
    }

    // Galerie-Klick
    items.forEach((el, idx) => {
        el.addEventListener('click', e => {
            e.preventDefault();
            show(idx);
            getModal().show();
        });
    });

    document.getElementById('archiv-lb-prev').addEventListener('click', () => show(current - 1));
    document.getElementById('archiv-lb-next').addEventListener('click', () => show(current + 1));

    document.getElementById('archiv-lightbox').addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft') show(current - 1);
        if (e.key === 'ArrowRight') show(current + 1);
    });

    // + Zu Sammlung Toggle
    document.querySelectorAll('.archiv-sammlung-toggle').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const cid = btn.dataset.cid;
            const drin = btn.dataset.drin === '1';
            const aktion = drin ? 'entfernen' : 'hinzufuegen';
            const d = items[current].dataset;
            const info = JSON.parse(d.info || '{}');
            const mId = info.media ? new URL(info.media).searchParams.get('xref') : null;
            if (!mId) return;

            const body = new FormData();
            body.append('_csrf', csrf);
            body.append('collection_id', cid);
            body.append('m_id', mId);
            body.append('aktion', aktion);

            try {
                const res = await fetch(toggleRoute, { method: 'POST', body });
                const json = await res.json();
                if (json.ok) {
                    const neuDrin = json.istDrin;
                    btn.querySelector('.archiv-sammlung-check').textContent = neuDrin ? '✓' : '○';
                    btn.dataset.drin = neuDrin ? '1' : '0';
                    const infoObj = JSON.parse(d.info || '{}');
                    const cidNum = parseInt(cid);
                    if (neuDrin) {
                        infoObj.in_sammlungen = [...(infoObj.in_sammlungen || []), cidNum];
                    } else {
                        infoObj.in_sammlungen = (infoObj.in_sammlungen || []).filter(c => c !== cidNum);
                    }
                    items[current].dataset.info = JSON.stringify(infoObj);
                }
            } catch { /* Netzwerkfehler ignorieren */ }
        });
    });

    // Seitenleiste öffnen
    editBtn?.addEventListener('click', () => {
        const d = items[current].dataset;
        const info = JSON.parse(d.info || '{}');
        fillSidebar(d, info);
        sidebar.classList.remove('d-none');
    });

    document.getElementById('archiv-edit-cancel')?.addEventListener('click', () => {
        sidebar.classList.add('d-none');
    });

    // Datei umbenennen
    document.getElementById('archiv-datei-rename-btn')?.addEventListener('click', () => {
        const nameDiv = document.getElementById('archiv-datei-name');
        const form = document.getElementById('archiv-datei-rename-form');
        const input = document.getElementById('archiv-datei-rename-input');
        const aktName = document.getElementById('archiv-datei-name-text').textContent;
        input.value = aktName;
        nameDiv.classList.add('d-none');
        form.classList.remove('d-none');
        input.focus();
        input.select();
    });

    document.getElementById('archiv-datei-rename-cancel')?.addEventListener('click', () => {
        document.getElementById('archiv-datei-rename-form').classList.add('d-none');
        document.getElementById('archiv-datei-name').classList.remove('d-none');
    });

    document.getElementById('archiv-datei-rename-save')?.addEventListener('click', async () => {
        const input = document.getElementById('archiv-datei-rename-input');
        const statusEl = document.getElementById('archiv-datei-rename-status');
        const csrfEl = document.getElementById('archiv-edit-csrf');
        const csrfTok = csrfEl ? csrfEl.value : csrf;
        const altPfad = pfadAusUrl(items[current].dataset.full);
        const neuName = input.value.trim();
        if (!neuName || !altPfad) return;

        statusEl.textContent = '⏳ ' + T.umbenennen;
        statusEl.style.color = 'white';

        const body = new FormData();
        body.append('_csrf', csrfTok);
        body.append('pfad', altPfad);
        body.append('neuer_name', neuName);

        try {
            const res = await fetch(renameRoute, { method: 'POST', body });
            const json = await res.json();
            if (json.ok) {
                statusEl.textContent = '✓ ' + T.umbenannt;
                statusEl.style.color = '#90ee90';
                const neuPfad = json.neu_pfad;
                const baseUrl = items[current].dataset.full.split('pfad=')[0];
                const neuFull = baseUrl + 'pfad=' + encodeURIComponent(neuPfad);
                items[current].dataset.full = neuFull;
                document.getElementById('archiv-datei-name-text').textContent = json.neu_name;
                img.src = neuFull;
                setTimeout(() => {
                    document.getElementById('archiv-datei-rename-form').classList.add('d-none');
                    document.getElementById('archiv-datei-name').classList.remove('d-none');
                    statusEl.textContent = '';
                }, 1500);
            } else {
                statusEl.textContent = '✗ ' + (json.fehler || T.fehler);
                statusEl.style.color = '#ff6b6b';
            }
        } catch (err) {
            statusEl.textContent = '✗ ' + T.netzwerkfehler;
            statusEl.style.color = '#ff6b6b';
        }
    });

    // EXIF speichern
    document.getElementById('archiv-edit-save')?.addEventListener('click', async () => {
        const route = document.getElementById('archiv-edit-route').value;
        const csrfEl = document.getElementById('archiv-edit-csrf');
        const csrfTok = csrfEl ? csrfEl.value : csrf;
        const pfad = pfadAusUrl(items[current].dataset.full);

        const body = new FormData();
        body.append('_csrf', csrfTok);
        body.append('pfad', pfad);
        body.append('beschreibung', document.getElementById('archiv-edit-beschreibung').value);
        body.append('datum', document.getElementById('archiv-edit-datum').value);
        body.append('personen', document.getElementById('archiv-edit-personen').value);
        body.append('keywords', document.getElementById('archiv-edit-keywords').value);

        status.textContent = '⏳ ' + T.speichern;
        status.style.color = 'white';

        try {
            const res = await fetch(route, { method: 'POST', body });
            const json = await res.json();
            if (json.ok) {
                status.textContent = '✓ ' + T.gespeichert;
                status.style.color = '#90ee90';
                const neu = document.getElementById('archiv-edit-beschreibung').value;
                if (neu) {
                    caption.textContent = neu;
                    items[current].dataset.title = neu;
                    const card = items[current].closest('.col')?.querySelector('.card-body div');
                    if (card) card.textContent = neu;
                }
            } else {
                status.textContent = '✗ ' + (json.fehler || T.fehler);
                status.style.color = '#ff6b6b';
            }
        } catch {
            status.textContent = '✗ ' + T.netzwerkfehler;
            status.style.color = '#ff6b6b';
        }
    });
});
