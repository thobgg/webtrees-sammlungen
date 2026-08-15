/**
 * Selbsttest fuer die Gesten in sammlung-galerie.js.
 *
 * Das Telefon haengt nur per ADB dran, kein Debugger. Statt Zahlen aus
 * Screenshots zu raten, treibt diese Datei die echten Handler mit
 * synthetischen Beruehrungen an und schreibt Bestanden/Gescheitert gross
 * auf den Schirm - ein Screenshot genuegt dann als Beleg.
 */
(function () {
    const schlaf = ms => new Promise(r => setTimeout(r, ms));
    const bild   = () => document.getElementById('archiv-lb-img');
    const flaeche = () => bild().parentElement;

    // Nicht am Text der style-Eigenschaft schnitzen: Chromium schreibt dort
    // schon mal 2.28882e-05px, und ein Zahlenmuster ohne Exponent liest das
    // als "kein Treffer" - der Pruefstand meldete Fehler, die keine waren.
    // DOMMatrix rechnet, was der Browser tatsaechlich anwendet.
    function matrix() {
        return new DOMMatrix(getComputedStyle(bild()).transform);
    }
    function skalaVon() {
        return Math.round(matrix().a * 1000) / 1000;
    }
    function verschubVon() {
        const m = matrix();
        return { x: Math.round(m.e * 100) / 100, y: Math.round(m.f * 100) / 100 };
    }

    function beruehren(typ, punkte) {
        const ziel = bild();
        const liste = punkte.map((p, i) => new Touch({
            identifier: i, target: ziel,
            clientX: p.x, clientY: p.y, pageX: p.x, pageY: p.y,
            screenX: p.x, screenY: p.y
        }));
        const rest = typ === 'touchend' ? [] : liste;
        ziel.dispatchEvent(new TouchEvent(typ, {
            bubbles: true, cancelable: true,
            touches: rest, targetTouches: rest, changedTouches: liste
        }));
    }

    const mitte = () => {
        const r = flaeche().getBoundingClientRect();
        return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
    };

    async function tipp(x, y) {
        beruehren('touchstart', [{ x, y }]);
        await schlaf(30);
        beruehren('touchend', [{ x, y }]);
    }
    async function doppeltipp(x, y) {
        await tipp(x, y);
        await schlaf(90);
        await tipp(x, y);
        await schlaf(320);           // Uebergang .18s abwarten
    }
    async function kneifen(faktor) {
        const m = mitte();
        const a = 60, b = a * faktor;
        beruehren('touchstart', [{ x: m.x - a, y: m.y }, { x: m.x + a, y: m.y }]);
        for (let s = 1; s <= 5; s++) {
            const d = a + (b - a) * s / 5;
            await schlaf(20);
            beruehren('touchmove', [{ x: m.x - d, y: m.y }, { x: m.x + d, y: m.y }]);
        }
        beruehren('touchend', [{ x: m.x - b, y: m.y }]);
        await schlaf(280);
    }
    async function ziehen(dx, dy, dauer) {
        const m = mitte();
        beruehren('touchstart', [{ x: m.x, y: m.y }]);
        const schritte = 6;
        for (let s = 1; s <= schritte; s++) {
            await schlaf(dauer / schritte);
            beruehren('touchmove', [{ x: m.x + dx * s / schritte, y: m.y + dy * s / schritte }]);
        }
        beruehren('touchend', [{ x: m.x + dx, y: m.y + dy }]);
        await schlaf(120);
    }

    const zeilen = [];
    function pruefe(name, bedingung, gemessen) {
        zeilen.push((bedingung ? '✔ ' : '✘ ') + name + '  [' + gemessen + ']');
        malen();
    }
    function malen() {
        const k = document.getElementById('bericht');
        k.innerHTML = zeilen.map(z =>
            '<div style="color:' + (z[0] === '✔' ? '#0a0' : '#c00') + '">' + z + '</div>'
        ).join('');
    }

    async function lauf() {
        zeilen.length = 0;
        // Lightbox oeffnen
        document.querySelectorAll('.archiv-gallery-item')[0].click();
        await schlaf(700);

        const r = flaeche().getBoundingClientRect();
        const bildR = bild().getBoundingClientRect();
        pruefe('Lightbox offen, Bildflaeche misst',
            r.width > 100 && r.height > 100,
            Math.round(r.width) + 'x' + Math.round(r.height) +
            ', Bild ' + Math.round(bildR.width) + 'x' + Math.round(bildR.height));

        // 1. Doppeltipp vergroessert Richtung Tippunkt
        const zielX = r.left + r.width * 0.25, zielY = r.top + r.height * 0.5;
        await doppeltipp(zielX, zielY);
        const s1 = skalaVon(), v1 = verschubVon();
        pruefe('Doppeltipp vergroessert auf 2,5x', Math.abs(s1 - 2.5) < 0.01, 'skala=' + s1);
        pruefe('Doppeltipp faehrt nach links zum Tippunkt', v1.x > 5,
            'verschub x=' + Math.round(v1.x));

        // 2. Wischen darf im Zoom nicht blaettern
        const vorher = bild().getAttribute('src');
        await ziehen(-120, 0, 200);
        pruefe('Wischen im Zoom blaettert nicht', bild().getAttribute('src') === vorher,
            'src unveraendert=' + (bild().getAttribute('src') === vorher));
        const v2 = verschubVon();
        pruefe('Ein Finger schiebt das vergroesserte Bild', v2.x < v1.x - 20,
            'x ' + Math.round(v1.x) + ' -> ' + Math.round(v2.x));

        // 3. Grenzen: weit ueber den Rand hinaus ziehen
        await ziehen(-2000, 0, 200);
        const v3 = verschubVon();
        const maxX = Math.max(0, (bild().offsetWidth * skalaVon() - flaeche().clientWidth) / 2);
        pruefe('Bild bleibt im Rahmen', v3.x >= -maxX - 1,
            'x=' + Math.round(v3.x) + ' grenze=' + Math.round(-maxX));

        // 4. Zweiter Doppeltipp setzt zurueck
        await doppeltipp(zielX, zielY);
        pruefe('Doppeltipp setzt zurueck', skalaVon() === 1 && verschubVon().x === 0,
            'skala=' + skalaVon());

        // 5. Kneifen vergroessert
        await kneifen(2.2);
        const s5 = skalaVon();
        pruefe('Zwei Finger vergroessern', s5 > 1.8, 'skala=' + s5.toFixed(2));

        // 6. Bildwechsel setzt den Zoom zurueck
        document.getElementById('archiv-lb-next').click();
        await schlaf(200);
        pruefe('Bildwechsel setzt Zoom zurueck', skalaVon() === 1,
            'skala=' + skalaVon() + ' transform=' + (bild().style.transform || 'keine'));

        // 7. Wischen bei 1x blaettert
        const vorWisch = bild().getAttribute('src');
        await ziehen(-140, 10, 220);
        await schlaf(200);
        pruefe('Wischen bei 1x blaettert weiter', bild().getAttribute('src') !== vorWisch,
            vorWisch.split('/').pop() + ' -> ' + bild().getAttribute('src').split('/').pop());

        // 8. Kurzes Wackeln darf nicht blaettern
        const vorWackel = bild().getAttribute('src');
        await ziehen(-20, 0, 150);
        pruefe('Kurzes Wackeln blaettert nicht', bild().getAttribute('src') === vorWackel,
            'src unveraendert');

        // 9. Einmal tippen raeumt die Leisten weg
        const lb = document.getElementById('archiv-lightbox');
        const vorKahl = Math.round(flaeche().getBoundingClientRect().height);
        await tipp(mitte().x, mitte().y);
        await schlaf(450);
        const nachKahl = Math.round(flaeche().getBoundingClientRect().height);
        pruefe('Einmal tippen raeumt die Leisten weg', lb.classList.contains('archiv-kahl'),
            'Bildhoehe ' + vorKahl + ' -> ' + nachKahl + ' (+' + (nachKahl - vorKahl) + ')');
        pruefe('Ersatz-Schliesser erscheint',
            getComputedStyle(document.getElementById('archiv-lb-zu')).display !== 'none',
            'display=' + getComputedStyle(document.getElementById('archiv-lb-zu')).display);

        // 10. Nochmal tippen holt sie zurueck
        await tipp(mitte().x, mitte().y);
        await schlaf(450);
        pruefe('Nochmal tippen holt sie zurueck', !lb.classList.contains('archiv-kahl'),
            'Bildhoehe ' + Math.round(flaeche().getBoundingClientRect().height));

        // 11. Doppeltippen darf die Leisten nicht anfassen
        await doppeltipp(mitte().x, mitte().y);
        await schlaf(400);
        pruefe('Doppeltippen laesst die Leisten stehen', !lb.classList.contains('archiv-kahl'),
            'skala=' + skalaVon());
        await doppeltipp(mitte().x, mitte().y);   // Zoom wieder zurueck
        await schlaf(400);

        // 12. Geometrie: sitzt der Dialog buendig, sind die Knoepfe drin?
        const mc = document.querySelector('#archiv-lightbox .modal-content').getBoundingClientRect();
        const zu = document.querySelector('#archiv-lightbox .btn-close').getBoundingClientRect();
        const kopf = document.querySelector('#archiv-lightbox .archiv-lb-kopf').getBoundingClientRect();
        const streifen = document.getElementById('archiv-lb-thumbstrip').getBoundingClientRect();
        pruefe('Dialog sitzt buendig', Math.round(mc.left) === 0 && Math.round(mc.right) === innerWidth,
            'links=' + Math.round(mc.left) + ' rechts=' + Math.round(mc.right) + ' fenster=' + innerWidth);
        pruefe('Schliessknopf ganz sichtbar', zu.right <= innerWidth + 1,
            'rechts=' + Math.round(zu.right) + ' fenster=' + innerWidth);
        zeilen.push('· Kopf ' + Math.round(kopf.height) + 'px, Streifen ' +
            Math.round(streifen.height) + 'px, Fenster ' + innerWidth + 'x' + innerHeight);

        zeilen.push('— fertig —');
        malen();
    }

    document.addEventListener('DOMContentLoaded', function () {
        const knopf = document.getElementById('selbsttest');
        knopf.addEventListener('click', () => lauf().catch(e => {
            zeilen.push('✘ Abbruch: ' + e.message); malen();
        }));
        if (location.hash === '#auto') setTimeout(() => lauf(), 800);
    });
})();
