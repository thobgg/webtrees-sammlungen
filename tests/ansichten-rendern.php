<?php
/**
 * Rendert alle Ansichten des Moduls ohne Datenbank – mit ECHTER
 * Uebersetzungslogik: die .mo wird gelesen und jeder Text durch sprintf()
 * geschickt, genau wie webtrees es tut (app/I18N.php:341-345).
 *
 * Laeuft als eigener Prozess, weil die I18N-Attrappe sonst mit der echten
 * Klasse aus dem webtrees-Autoloader kollidiert. AnsichtenRenderTest ruft ihn.
 *
 * Warum es das gibt: v1.2.6 stuerzte auf jeder Galerie ab, weil ein
 * uebersetzter Text einen Platzhalter ohne Argument hatte. Weder die
 * Unit-Tests noch die statische Analyse haben das bemerkt – sie pruefen
 * Struktur, nicht Laufzeit.
 *
 * Aufruf: php tests/ansichten-rendern.php <modulverzeichnis> <sprache>
 */

namespace Fisharebest\Webtrees {
    class I18N
    {
        public static array $texte = [];

        private static function uebersetze(string $s): string
        {
            return self::$texte[$s] ?? $s;
        }

        /** Wie webtrees: immer durch sprintf – auch ohne Argumente. */
        public static function translate(string $message, ...$args): string
        {
            return sprintf(self::uebersetze($message), ...$args);
        }

        public static function plural(string $e, string $m, int $n, ...$args): string
        {
            return sprintf(self::uebersetze($n === 1 ? $e : $m), ...$args);
        }

        public static function number(int|float $n): string
        {
            return (string) $n;
        }
    }
}

namespace Fisharebest\Webtrees\Http\RequestHandlers {
    class MediaPage {}
    class MediaFileThumbnail {}
    class IndividualPage {}
}

namespace {
    use Fisharebest\Webtrees\I18N;

    $modul   = rtrim($argv[1] ?? '', '/');
    $sprache = $argv[2] ?? 'de';

    /** Minimaler .mo-Leser – kein Autoloader, damit nichts mit webtrees kollidiert. */
    function mo_lesen(string $pfad): array {
        $roh = (string) file_get_contents($pfad);
        $magie = unpack('V', substr($roh, 0, 4))[1];
        $F = $magie === 0x950412de ? 'V' : 'N';
        $anzahl = unpack($F, substr($roh, 8, 4))[1];
        $oOff   = unpack($F, substr($roh, 12, 4))[1];
        $tOff   = unpack($F, substr($roh, 16, 4))[1];
        $texte = [];
        for ($i = 0; $i < $anzahl; $i++) {
            $o = unpack($F . 'len/' . $F . 'off', substr($roh, $oOff + $i * 8, 8));
            $t = unpack($F . 'len/' . $F . 'off', substr($roh, $tOff + $i * 8, 8));
            $quelle = substr($roh, $o['off'], $o['len']);
            $ziel   = substr($roh, $t['off'], $t['len']);
            if ($quelle === '') { continue; }
            $qTeile = explode("\0", $quelle);
            $zTeile = explode("\0", $ziel);
            $texte[$qTeile[0]] = $zTeile[0];
            if (isset($qTeile[1])) { $texte[$qTeile[1]] = $zTeile[1] ?? $zTeile[0]; }
        }
        return $texte;
    }

    $mo = $modul . '/resources/lang/' . $sprache . '.mo';
    if (is_file($mo)) {
        I18N::$texte = mo_lesen($mo);
    }
    echo "Sprache {$sprache}: " . count(I18N::$texte) . " Texte geladen\n";

    function route(string $n, array $p = []): string { return '/r/' . $n . '?' . http_build_query($p); }
    function e(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES); }
    function csrf_token(): string { return 'tok'; }
    function nl2br_(string $s): string { return nl2br($s); }

    $tree = new class { public function name(): string { return 't1'; } public function id(): int { return 1; } };
    $treeName = 't1';
    $istAdmin = true;
    $toggleRoute = '/toggle';

    $sammlung = new class {
        public $farbe = '#abc'; public $icon = 'folder'; public $name = 'Test';
        public $beschreibung = 'Text'; public $ordner = 'Fotos'; public $slug = 'test';
        public $ansicht = 'raster'; public $id = 1; public $anzahl = 24;
        public $vorschauXrefs = []; public $reihenfolge = 0; public $aktiv = true;
        public function icon(): string { return 'fa-folder'; }
        public function slug(): string { return 'test'; }
    };
    $manuelleGalerien = [$sammlung];

    $bild = [
        'pfad' => 'a/b.jpg', 'datei' => 'b.jpg', 'format' => 'jpg', 'm_id' => 'M1', 'titel' => 'T',
        'exif' => ['beschreibung' => 'B', 'datum' => '1923', 'datum_iso' => '1923',
                   'personen' => ['X'], 'keywords' => ['K'],
                   'breite' => 10, 'hoehe' => 10, 'groesse_kb' => 1],
        'personen' => ['P'], 'personen_gesamt' => 300,
        'wt' => ['titel' => 'WT', 'notiz' => 'N', 'personen' => [['name' => 'P', 'xref' => 'I1']]],
        'in_sammlungen' => [1],
    ];
    $dok = ['pfad' => 'a/c.pdf', 'datei' => 'c.pdf', 'format' => 'pdf', 'm_id' => null, 'titel' => ''];

    $ansichten = [
        'ordner/raster'   => ['istRaster' => true,  'istGemischt' => false, 'istBild' => true],
        'ordner/gemischt' => ['istRaster' => false, 'istGemischt' => true,  'istBild' => true],
        'ordner/foto'     => ['istRaster' => false, 'istGemischt' => false, 'istBild' => true],
        'ordner/dokument' => ['istRaster' => false, 'istGemischt' => false, 'istBild' => false],
    ];

    $fehler = 0;

    foreach ($ansichten as $name => $flags) {
        foreach ([1, 3] as $seiten) {
            $aktive = array_merge([
                'typ' => 'ordner', 'sammlung' => $sammlung, 'anzahl' => 24, 'datei_anzahl' => 24,
                'seite' => 1, 'seiten_gesamt' => $seiten, 'per_seite' => 10,
                'bilder' => [$bild], 'weitere' => [$dok], 'dokumente' => [$dok], 'alle' => [$dok],
            ], $flags);

            ob_start();
            try {
                include $modul . '/resources/views/partials/_detail-ordner.phtml';
                $html = ob_get_clean();
                printf("  %-18s %d Seite(n): ok (%d Zeichen)\n", $name, $seiten, strlen($html));
            } catch (\Throwable $ex) {
                ob_end_clean();
                printf("  %-18s %d Seite(n): FEHLER %s – %s @ %s:%d\n", $name, $seiten,
                    get_class($ex), $ex->getMessage(), basename($ex->getFile()), $ex->getLine());
                $fehler++;
            }
        }
    }

    // Manuelle Galerie
    foreach ([1, 3] as $seiten) {
        $aktive = ['typ' => 'manuell', 'sammlung' => $sammlung, 'anzahl' => 5,
                   'seite' => 1, 'seiten_gesamt' => $seiten, 'bilder' => [$bild]];
        ob_start();
        try {
            include $modul . '/resources/views/partials/_detail-manuell.phtml';
            $html = ob_get_clean();
            printf("  %-18s %d Seite(n): ok (%d Zeichen)\n", 'manuell', $seiten, strlen($html));
        } catch (\Throwable $ex) {
            ob_end_clean();
            printf("  %-18s %d Seite(n): FEHLER %s – %s @ %s:%d\n", 'manuell', $seiten,
                get_class($ex), $ex->getMessage(), basename($ex->getFile()), $ex->getLine());
            $fehler++;
        }
    }

    exit($fehler > 0 ? 1 : 0);
}
