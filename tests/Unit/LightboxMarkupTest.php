<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sammlungen\ViewModel\SammlungenViewModel;

/**
 * Regression zu Issue #13: ein Wappen, das an mehrere hundert Personen haengt,
 * liess das Bild in der Lightbox verschwinden. Die Kopfzeile ist flex-shrink-0;
 * die ungekuerzte Namensliste brach dort auf dutzende Zeilen um und quetschte
 * den darunter liegenden Bildbereich (flex-grow-1, min-height:0) auf Hoehe 0.
 */
final class LightboxMarkupTest extends TestCase
{
    private static function views(): string
    {
        return dirname(__DIR__, 2) . '/resources/views/partials/';
    }

    private static function lies(string $datei): string
    {
        return (string) file_get_contents(self::views() . $datei);
    }

    /**
     * Sicherheitsnetz im Markup: was auch immer das JS in die Meta-Zeile
     * schreibt, es darf die Kopfzeile nicht wachsen lassen.
     */
    public function testMetaZeileIstGekuerzt(): void
    {
        $treffer = [];
        preg_match('/<div id="archiv-lb-meta"[^>]*>/', self::lies('_lightbox.phtml'), $treffer);

        self::assertNotEmpty($treffer, 'Meta-Zeile der Lightbox nicht gefunden.');
        self::assertStringContainsString('text-truncate', $treffer[0]);
    }

    /**
     * Die Lightbox lag als Byte-genaue Kopie zusaetzlich in _detail-ordner.phtml.
     * Zwei Kopien bedeuten doppelte Element-IDs und einen Fix, der nur an einer
     * Stelle ankommt – genau die Datei, die Issue #13 ausloest.
     */
    public function testLightboxExistiertNurEinmal(): void
    {
        $dateien = glob(self::views() . '*.phtml') ?: [];
        $mitId   = [];

        foreach ($dateien as $datei) {
            if (str_contains((string) file_get_contents($datei), 'id="archiv-lb-meta"')) {
                $mitId[] = basename($datei);
            }
        }

        self::assertSame(['_lightbox.phtml'], $mitId);
    }

    /**
     * Jede Galerie-Variante baut ihr data-info-JSON selbst. Fehlt dort
     * personen_gesamt, zeigt die Lightbox "25 Personen" statt "25 und 275
     * weitere" – die Kuerzung waere unsichtbar und damit irrefuehrend.
     */
    public function testAlleGalerienLiefernDieGesamtzahlDerPersonen(): void
    {
        foreach (['_detail-ordner.phtml', '_detail-manuell.phtml'] as $datei) {
            $inhalt = self::lies($datei);

            // Nur die JSON-Schluessel zaehlen ('personen' => ...), nicht die
            // PHP-Array-Zugriffe wie $bild['personen'].
            $mitNamen  = preg_match_all("/'personen'\s*=>/", $inhalt);
            $mitGesamt = preg_match_all("/'personen_gesamt'\s*=>/", $inhalt);

            self::assertGreaterThan(0, $mitNamen, $datei . ': keine data-info-Struktur gefunden.');
            self::assertSame(
                $mitNamen,
                $mitGesamt,
                $datei . ': nicht jede data-info-Struktur fuehrt personen_gesamt mit.'
            );
        }
    }

    /**
     * Regression zu Issue #15: der data-info-Schluessel 'personen' speist das
     * Eingabefeld der Seitenleiste, und dessen Inhalt wird beim Speichern in
     * den Dateikopf geschrieben. Stand dort die webtrees-Verknuepfung statt der
     * im Bild hinterlegten Namen, ueberschrieb ein Klick auf "In Datei
     * speichern" die Personenliste der Originaldatei.
     */
    public function testEingabefeldWirdAusDenDateiDatenGespeist(): void
    {
        foreach (['_detail-ordner.phtml', '_detail-manuell.phtml'] as $datei) {
            $inhalt = self::lies($datei);

            preg_match_all("/'personen'\s*=>\s*([^,]+),/", $inhalt, $treffer);

            self::assertNotEmpty($treffer[1], $datei . ': keine data-info-Struktur gefunden.');

            foreach ($treffer[1] as $quelle) {
                // Erlaubt sowohl $bild['exif']['personen'] als auch eine vorher
                // gesetzte Variable wie $exifPersonen – verboten ist die
                // webtrees-Liste ($bild['personen'] / $personen).
                self::assertStringContainsStringIgnoringCase(
                    'exif',
                    $quelle,
                    $datei . ": 'personen' muss aus den EXIF-Daten kommen, nicht aus webtrees – "
                        . 'sonst schreibt die Seitenleiste webtrees-Namen in die Bilddatei. Gefunden: ' . $quelle
                );
            }
        }
    }

    /**
     * Gegenstueck: die webtrees-Verknuepfungen gehoeren in wt_personen, wo die
     * Seitenleiste und die Abgleich-Sektion sie erwarten.
     */
    public function testWebtreesPersonenBleibenImEigenenSchluessel(): void
    {
        foreach (['_detail-ordner.phtml'] as $datei) {
            $inhalt = self::lies($datei);

            self::assertGreaterThan(
                0,
                preg_match_all("/'wt_personen'\s*=>/", $inhalt),
                $datei . ': wt_personen fehlt.'
            );
        }
    }

    /** Die Kappung muss greifen, bevor die Namen ueberhaupt ins Markup gelangen. */
    public function testObergrenzeIstGesetztUndPlausibel(): void
    {
        self::assertGreaterThan(0, SammlungenViewModel::MAX_PERSONEN_JE_BILD);
        self::assertLessThanOrEqual(50, SammlungenViewModel::MAX_PERSONEN_JE_BILD);
    }
}
