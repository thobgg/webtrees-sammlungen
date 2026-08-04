<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression zu Issue #17. Das Modul hat mehrere Darstellungszweige, die per
 * Copy-Paste entstanden sind und auseinandergelaufen waren:
 *
 *  - „Foto-Raster" und „Gemischt" gaben den Seitenzähler aus, hatten aber nie
 *    eine Blätternavigation – man sah „Seite 1 von 3" und kam nicht weiter.
 *  - Die Kachel-Klassen `archiv-blur-bg` und `archiv-photo-main` standen seit
 *    1.0.0 im Markup, ohne dass es je CSS-Regeln dazu gab. Der Hintergrund
 *    erschien dadurch in Originalgröße statt formatfüllend und weichgezeichnet;
 *    dieselbe Datei war zweimal zu sehen.
 */
final class AnsichtenTest extends TestCase
{
    private const NAVIGATION = '_seitennavigation.phtml';

    private static function views(): string
    {
        return dirname(__DIR__, 2) . '/resources/views/';
    }

    /**
     * @return list<array{string}>
     */
    public static function ansichten(): array
    {
        return [
            ['partials/_detail-ordner.phtml'],
            ['partials/_detail-manuell.phtml'],
        ];
    }

    /**
     * Wer den Seitenzähler zeigt, muss auch einen Weg auf die nächste Seite
     * anbieten – in JEDEM Zweig, nicht nur in dem, der zuerst gebaut wurde.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('ansichten')]
    public function testJederZweigMitSeitenzaehlerHatEineNavigation(string $view): void
    {
        $inhalt = (string) file_get_contents(self::views() . $view);

        $zaehler = substr_count($inhalt, 'Seite %s von %s');
        $navi    = substr_count($inhalt, self::NAVIGATION);

        self::assertGreaterThan(0, $zaehler, $view . ': kein Seitenzähler gefunden.');
        self::assertSame(
            $zaehler,
            $navi,
            $view . ": {$zaehler} Zweig(e) mit Seitenzähler, aber {$navi} Navigation(en) – "
                . 'ein Zweig lässt den Benutzer auf Seite 1 sitzen.'
        );
    }

    /** Die Navigation darf nur noch an einer Stelle stehen, sonst driftet sie wieder. */
    public function testNavigationsMarkupLiegtNurImPartial(): void
    {
        $dateien = glob(self::views() . 'partials/*.phtml') ?: [];
        $mitMarkup = [];

        foreach ($dateien as $datei) {
            if (str_contains((string) file_get_contents($datei), '<ul class="pagination">')) {
                $mitMarkup[] = basename($datei);
            }
        }

        self::assertSame([self::NAVIGATION], $mitMarkup);
    }

    /**
     * Jede im Markup vergebene `archiv-*`-Klasse muss entweder eine CSS-Regel
     * haben oder vom JavaScript benutzt werden. Ohne beides ist sie wirkungslos
     * – und wenn das Markup wie beim Foto-Raster auf die Formatierung angewiesen
     * ist, sieht die Seite kaputt aus.
     */
    public function testKlassenImMarkupSindDefiniertOderWerdenBenutzt(): void
    {
        $verwendet = [];

        foreach (glob(self::views() . '{,partials/}*.phtml', GLOB_BRACE) ?: [] as $datei) {
            preg_match_all('/class="([^"]*)"/', (string) file_get_contents($datei), $treffer);

            foreach ($treffer[1] as $attribut) {
                foreach (preg_split('/\s+/', $attribut) ?: [] as $klasse) {
                    if (str_starts_with($klasse, 'archiv-')) {
                        $verwendet[$klasse] = true;
                    }
                }
            }
        }

        $css = (string) file_get_contents(self::views() . 'sammlungen.phtml');
        $js  = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/js/sammlung-galerie.js');

        $verwaist = array_filter(
            array_keys($verwendet),
            static fn (string $k): bool => !str_contains($css, '.' . $k) && !str_contains($js, $k)
        );

        self::assertSame(
            [],
            array_values($verwaist),
            'Klassen ohne CSS-Regel und ohne JavaScript-Bezug: ' . implode(', ', $verwaist)
        );
    }

    /** Die beiden Klassen aus Issue #17 namentlich – sie tragen die Raster-Kachel. */
    public function testRasterKachelIstFormatiert(): void
    {
        $css = (string) file_get_contents(self::views() . 'sammlungen.phtml');

        self::assertMatchesRegularExpression('/\.archiv-blur-bg\s*\{[^}]*background-size:\s*cover/s', $css);
        self::assertMatchesRegularExpression('/\.archiv-photo-main\s*\{[^}]*object-fit:\s*contain/s', $css);
    }
}
