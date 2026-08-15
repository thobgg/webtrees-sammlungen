<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Die Lightbox soll auf dem Telefon wie eine Foto-App zu bedienen sein:
 * kneifen zum Vergroessern, ein Finger schiebt, wischen blaettert.
 *
 * Gesten lassen sich in PHP nicht ausfuehren; geprueft wird deshalb der
 * Vertrag, an dem die Bedienung haengt und der beim Umbauen leicht
 * verlorengeht. Die Gesten selbst laufen am Geraet gegen dieselben Dateien.
 */
final class GestenTest extends TestCase
{
    private static function js(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/resources/js/sammlung-galerie.js');
    }

    private static function css(): string
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/sammlungen.phtml');
        $teil = explode('<style>', $view)[1] ?? '';

        return explode('</style>', $teil)[0];
    }

    /**
     * Ohne { passive: false } darf preventDefault() nicht greifen: der Browser
     * rollt dann die Seite, waehrend man das Bild schiebt.
     */
    public function testBeruehrungenWerdenNichtPassivGebunden(): void
    {
        $js = self::js();

        foreach (['touchstart', 'touchmove', 'touchend'] as $ereignis) {
            self::assertMatchesRegularExpression(
                "/addEventListener\('{$ereignis}'.*?passive:\s*false/s",
                $js,
                $ereignis . ' ist nicht als nicht-passiv gebunden.'
            );
        }
    }

    /**
     * touch-action gehoert auf die Bildflaeche, nicht nur auf das Bild: die
     * Handler haengen an der Flaeche, und neben einem Querformat-Foto ist
     * schwarzer Rand, auf dem sonst der Browser die Geste abfaengt.
     */
    public function testFlaecheGibtDieGestenAnUnsWeiter(): void
    {
        self::assertStringContainsString("flaeche.style.touchAction = 'none'", self::js());
    }

    /**
     * Sonst zeigt das naechste Foto den Ausschnitt des vorigen.
     */
    public function testBildwechselSetztDenZoomZurueck(): void
    {
        $js = self::js();
        $pos = strpos($js, 'zoomZuruecksetzen();');
        $neu = strpos($js, 'img.src = d.full;');

        self::assertIsInt($pos, 'show() setzt den Zoom nicht zurueck.');
        self::assertIsInt($neu);
        self::assertLessThan($neu, $pos, 'Der Zoom wird erst nach dem Bildwechsel zurueckgesetzt.');
    }

    /**
     * Blaettern nur bei unvergroessertem Bild – sonst waere jedes Verschieben
     * ein Bildwechsel.
     */
    public function testGewischtWirdNurOhneZoom(): void
    {
        self::assertMatchesRegularExpression(
            "/modus === 'wischen' && skala === 1/",
            self::js()
        );
    }

    /**
     * Beide Leisten tragen Bootstraps Hilfsklasse `d-flex`, und die
     * Hilfsklassen sind mit !important gesetzt. Ohne Gegengewicht bleibt die
     * Kopfzeile stehen, obwohl die Regel greift - am Geraet gemessen.
     */
    public function testKahlerZustandSchlaegtDieHilfsklassen(): void
    {
        self::assertMatchesRegularExpression(
            '/#archiv-lightbox\.archiv-kahl[^{]*\{\s*display:\s*none\s*!important/',
            self::css(),
            'Die Leisten verschwinden nicht: d-flex gewinnt.'
        );
    }

    /**
     * Ohne Kopfzeile faellt der Schliesser weg. Die Zurueck-Taste des Telefons
     * ist kein Ersatz, die verlaesst im Browser die Seite.
     */
    public function testImKahlenZustandBleibtEinSchliesser(): void
    {
        $markup  = (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/views/partials/_lightbox.phtml'
        );
        $treffer = [];
        preg_match('/<button id="archiv-lb-zu".*?>/s', $markup, $treffer);

        self::assertNotEmpty($treffer, 'Ersatz-Schliesser fehlt im Markup.');
        self::assertStringContainsString('data-bs-dismiss="modal"', $treffer[0]);
        self::assertMatchesRegularExpression(
            '/#archiv-lightbox\.archiv-kahl \.archiv-lb-zu\s*\{[^}]*display:\s*block/',
            self::css()
        );
    }

    /**
     * Der Zug muss warten, bis ein zweiter Tipp ausgeschlossen ist - sonst
     * blinkt die Leiste bei jedem Doppeltipp kurz auf.
     */
    public function testEinzelnerTippWartetAufDenDoppeltipp(): void
    {
        $js = self::js();

        self::assertStringContainsString('tippUhr = setTimeout(kahlUmschalten', $js);
        self::assertMatchesRegularExpression(
            '/clearTimeout\(tippUhr\);\s*umschalten\(/',
            $js,
            'Der Doppeltipp bricht den anstehenden Zug nicht ab.'
        );
    }

    /**
     * Adressleiste oben und Systemleiste unten gehoeren dem Browser; ohne
     * Vollbild bleibt der Rahmen stehen, auch wenn unsere eigenen Leisten weg
     * sind. Und das Vollbild muss wieder aus, sonst haengt das Telefon nach dem
     * Schliessen in einer leeren Vollbildseite.
     */
    public function testKahlerZustandNimmtDasVollbildMit(): void
    {
        $js = self::js();

        self::assertMatchesRegularExpression(
            '/const kahl = lightbox\.classList\.toggle\(\'archiv-kahl\'\);\s*vollbild\(kahl\);/',
            $js,
            'Das Vollbild haengt nicht am kahlen Zustand.'
        );
        self::assertMatchesRegularExpression(
            "/'hidden\.bs\.modal'.*?vollbild\(false\)/s",
            $js,
            'Beim Schliessen bleibt das Vollbild stehen.'
        );
        self::assertMatchesRegularExpression(
            "/'fullscreenchange'.*?classList\.remove\('archiv-kahl'\)/s",
            $js,
            'Verlaesst man das Vollbild mit der Systemgeste, bleiben die Leisten weg.'
        );
    }

    /**
     * Wer die Leisten beim letzten Bild weggetippt hat, sucht sie sonst beim
     * naechsten Oeffnen.
     */
    public function testSchliessenRaeumtDenKahlenZustandAb(): void
    {
        self::assertMatchesRegularExpression(
            "/'hidden\.bs\.modal'.*?classList\.remove\('archiv-kahl'\)/s",
            self::js()
        );
    }

    /**
     * Das Bootstrap-Buendel von webtrees ist fuer Schreibrichtungen aufbereitet
     * und setzt den Dialogabstand ueber `[dir] .modal-dialog`. Attribut plus
     * Klasse schlaegt eine einzelne Klasse: ein `margin` auf .archiv-lb-dialog
     * kommt nie an, der Dialog stand um 8 px versetzt und ragte rechts hinaus.
     */
    public function testDialogAbstandUeberBootstrapsStellschraube(): void
    {
        $css = self::css();

        self::assertMatchesRegularExpression(
            '/#archiv-lightbox\s*\{[^}]*--bs-modal-margin:\s*0/',
            $css,
            'Der Dialogabstand wird auf dem Telefon nicht auf 0 gesetzt.'
        );
        self::assertDoesNotMatchRegularExpression(
            '/\.archiv-lb-dialog\s*\{[^}]*margin\s*:/',
            $css,
            'margin auf .archiv-lb-dialog wirkt nicht – das Buendel gewinnt.'
        );
    }
}
