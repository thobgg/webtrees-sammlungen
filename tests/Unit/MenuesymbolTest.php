<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sammlungen\SammlungenModule;

/**
 * Regression zu Issue #22, gemeldet von @ro-la.
 *
 * Das Menuesymbol wurde ueber `content: url(...)` in *jedes* Theme gedrueckt,
 * fest auf 50 Pixel. Eingefuegter Inhalt laesst sich per CSS nicht groessern
 * oder kleinern - das Theme konnte sich also nicht wehren. In Colors war das
 * Symbol dadurch fast doppelt so gross wie die uebrigen, und in Themes ganz
 * ohne Menuesymbole (minimal, F.A.B., Potts Modern) stand ein Bild mitten in
 * einer reinen Textzeile.
 *
 * Am Geraet gemessen, Groesse der Symbole des jeweiligen Themes:
 * webtrees 50, Colors 40, Xenea 28, Clouds 22, minimal und F.A.B. keine.
 */
final class MenuesymbolTest extends TestCase
{
    private static function quelle(): string
    {
        $datei = (new ReflectionClass(SammlungenModule::class))->getFileName();

        return (string) file_get_contents((string) $datei);
    }

    /** Das Symbol muss skalierbar sein, sonst bestimmt es das Theme mit. */
    public function testSymbolIstEinHintergrundbildUndKeinInhalt(): void
    {
        $quelle = self::quelle();

        self::assertStringNotContainsString('content:url(', $quelle);
        self::assertStringContainsString('background:url(', $quelle);
    }

    /** Wer ein Theme ohne Menuesymbole benutzt, soll auch bei uns keines sehen. */
    public function testGrundzustandIstOhneSymbol(): void
    {
        self::assertMatchesRegularExpression('/\{content:none\}/', self::quelle());
    }

    /** Je Theme dessen eigene Groesse - die Zahlen stammen aus deren CSS. */
    public function testGroessenJeTheme(): void
    {
        $reflexion = new ReflectionClass(SammlungenModule::class);
        /** @var array<string,int> $groessen */
        $groessen = $reflexion->getConstant('SYMBOLGROESSE');

        self::assertSame(
            ['webtrees' => 50, 'colors' => 40, 'xenea' => 28, 'clouds' => 22],
            $groessen
        );
    }

    /** Gezielt wird ueber die Theme-Klasse am body, die webtrees dort setzt. */
    public function testZieltAufDieThemeKlasse(): void
    {
        self::assertStringContainsString(".wt-theme-' . \$theme", self::quelle());
    }

    /** Flaches SVG statt Foto: es steht neben gezeichneten Symbolen. */
    public function testSymbolIstEinSvg(): void
    {
        $wurzel = dirname(__DIR__, 2);

        self::assertFileExists($wurzel . '/resources/menu-icon.svg');
        self::assertStringContainsString('menu-icon.svg', self::quelle());
    }
}
