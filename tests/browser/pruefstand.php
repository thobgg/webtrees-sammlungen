<?php

declare(strict_types=1);

/**
 * Baut eine Testseite fuer die Bedienung der Lightbox auf Beruehrungsgeraeten.
 *
 *     php tests/browser/pruefstand.php [zielverzeichnis] [webtrees-wurzel]
 *
 * Die Seite zieht Markup, Stylesheet und Skript aus den echten Moduldateien -
 * eine Kopie waere nach dem naechsten Umbau still veraltet und wuerde
 * "bestanden" melden, waehrend das Modul kaputt ist. Nur die Fotos sind
 * erzeugte Raster; damit laesst sich am Geraet messen, ohne dass echte
 * Familienbilder durch die Gegend gehen.
 *
 * Danach:
 *     php -S 127.0.0.1:8099 -t <zielverzeichnis>
 *     adb reverse tcp:8099 tcp:8099          # Telefon per USB
 * und im Browser des Geraets http://127.0.0.1:8099/index.html#auto oeffnen.
 * Der Selbsttest laeuft von allein und schreibt sein Ergebnis auf den Schirm.
 */

$ziel     = $argv[1] ?? sys_get_temp_dir() . '/sammlungen-pruefstand';
$webtrees = $argv[2] ?? dirname(__DIR__, 4);
$modul    = dirname(__DIR__, 2);

foreach (['', '/js', '/css', '/img'] as $unter) {
    if (!is_dir($ziel . $unter) && !mkdir($ziel . $unter, 0o775, true)) {
        fwrite(STDERR, "Verzeichnis nicht anlegbar: {$ziel}{$unter}\n");
        exit(1);
    }
}

// --- Bootstrap von webtrees, nicht aus dem Netz -----------------------------
// Das Buendel ist fuer Schreibrichtungen aufbereitet ([dir] .modal-dialog) und
// setzt Hilfsklassen mit !important. Beides hat schon Fehler verursacht, die
// mit einem CDN-Bootstrap unsichtbar geblieben waeren.
$bundle = [
    "{$webtrees}/public/js/vendor.min.js"   => "{$ziel}/js/vendor.min.js",
    "{$webtrees}/public/css/vendor.min.css" => "{$ziel}/css/vendor.min.css",
];
foreach ($bundle as $von => $nach) {
    if (!is_file($von)) {
        fwrite(STDERR, "Nicht gefunden: {$von}\nwebtrees-Wurzel als zweites Argument angeben.\n");
        exit(1);
    }
    copy($von, $nach);
}

copy("{$modul}/resources/js/sammlung-galerie.js", "{$ziel}/js/sammlung-galerie.js");
copy(__DIR__ . '/selbsttest.js', "{$ziel}/js/selbsttest.js");

// --- Testbilder: Raster mit feinen Ziffern, quer und hoch -------------------
$anzahl = 5;
for ($n = 1; $n <= $anzahl; $n++) {
    $quer = $n % 2 === 1;
    $b    = $quer ? 1800 : 1200;
    $h    = $quer ? 1200 : 1800;

    $im = imagecreatetruecolor($b, $h);
    imagefill($im, 0, 0, (int) imagecolorallocate($im, 235, 228, 214));
    $grau = (int) imagecolorallocate($im, 170, 160, 145);
    for ($x = 0; $x < $b; $x += 50) {
        imageline($im, $x, 0, $x, $h, $grau);
    }
    for ($y = 0; $y < $h; $y += 50) {
        imageline($im, 0, $y, $b, $y, $grau);
    }
    $sw = (int) imagecolorallocate($im, 20, 20, 20);
    // Feine Ziffern: erst vergroessert lesbar - so sieht man, ob der Zoom
    // wirklich Aufloesung zeigt und nicht nur skaliert.
    for ($y = 100; $y < $h - 40; $y += 200) {
        for ($x = 60; $x < $b - 60; $x += 200) {
            imagestring($im, 2, $x, $y, sprintf('%d-%d', intdiv($x, 200), intdiv($y, 200)), $sw);
        }
    }
    imagestring($im, 5, 40, 30, sprintf(
        'TESTBILD %d  %s  %dx%d',
        $n,
        $quer ? 'quer 3:2' : 'hoch 2:3',
        $b,
        $h
    ), $sw);
    imagerectangle($im, 5, 5, $b - 6, $h - 6, $sw);
    imagejpeg($im, "{$ziel}/img/test{$n}.jpg", 82);

    $klein = imagescale($im, 160);
    if ($klein !== false) {
        imagejpeg($klein, "{$ziel}/img/thumb{$n}.jpg", 75);
    }
}

// --- Markup und Stylesheet aus den echten Dateien ---------------------------
$lightbox = (string) file_get_contents("{$modul}/resources/views/partials/_lightbox.phtml");
$lightbox = explode('<script>', $lightbox)[0];                        // Konfigurationsblock weg
$lightbox = (string) preg_replace('/<\?=.*?\?>/s', 'X', $lightbox);   // Ausgaben durch Platzhalter
$lightbox = (string) preg_replace('/<\?php.*?\?>/s', '', $lightbox);  // Logik weg, Markup bleibt

$ansicht = (string) file_get_contents("{$modul}/resources/views/sammlungen.phtml");
$css     = explode('</style>', explode('<style>', $ansicht)[1] ?? '')[0];

$kacheln = '';
for ($n = 1; $n <= $anzahl; $n++) {
    $info = json_encode([
        'thumb'           => "img/thumb{$n}.jpg",
        'media'           => '',
        'datum'           => sprintf('19%02d-05-0%d', 20 + $n, $n),
        'wt_personen'     => [['name' => "Testperson {$n}", 'xref' => "X{$n}"]],
        'personen_gesamt' => $n + 2,
        'personen'        => [],
        'keywords'        => ['Testbild'],
        'in_sammlungen'   => [],
        'breite'          => $n % 2 === 1 ? 1800 : 1200,
        'hoehe'           => $n % 2 === 1 ? 1200 : 1800,
        'groesse_kb'      => 183,
        'format'          => 'JPEG',
    ], JSON_THROW_ON_ERROR);

    $kacheln .= sprintf(
        '<div class="archiv-gallery-item" data-full="img/test%d.jpg" data-title="Testbild %d" '
        . 'data-info=\'%s\'><img src="img/thumb%d.jpg" style="width:96px;cursor:pointer" alt=""></div>',
        $n,
        $n,
        $info,
        $n
    );
}

// dir und data-bs-theme wie im webtrees-Layout: ohne dir greift
// `[dir=ltr] .modal{left:0}` nicht und der Dialog steht daneben - ein Fehler
// der Attrappe, der schon einmal als Modulfehler durchging.
$seite = <<<HTML
<!doctype html>
<html dir="ltr" lang="de" data-bs-theme="light"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Sammlungen – Prüfstand</title>
<link rel="stylesheet" href="css/vendor.min.css">
<style>
{$css}
body { font-family: sans-serif; padding: .5rem; }
</style>
</head><body>
<h5>Sammlungen – Prüfstand</h5>
<button id="selbsttest" class="btn btn-primary btn-sm mb-2">Selbsttest starten</button>
<div id="bericht" style="position:fixed;top:0;left:0;right:0;z-index:5000;
     background:rgba(255,255,255,.94);font:11px/1.3 monospace;padding:2px 4px;
     pointer-events:none"></div>
<p class="small text-muted">Kachel antippen öffnet die Lightbox. Doppeltippen vergrößert,
zwei Finger zoomen, ein Finger schiebt, Wischen blättert nur bei 1x, einmal tippen
räumt die Leisten weg.</p>
<div class="d-flex flex-wrap gap-1">{$kacheln}</div>
{$lightbox}
<script src="js/vendor.min.js"></script>
<script>
window.archivConfig = { toggleRoute: '', renameRoute: '', csrf: '',
  individualUrlTemplate: '#_XREF_', texte: {} };
</script>
<script src="js/sammlung-galerie.js"></script>
<script src="js/selbsttest.js"></script>
</body></html>
HTML;

file_put_contents("{$ziel}/index.html", $seite);

echo "Prüfstand gebaut: {$ziel}\n";
echo "  php -S 127.0.0.1:8099 -t {$ziel}\n";
echo "  adb reverse tcp:8099 tcp:8099\n";
echo "  dann http://127.0.0.1:8099/index.html#auto am Gerät öffnen\n";
