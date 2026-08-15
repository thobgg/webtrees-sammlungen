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
