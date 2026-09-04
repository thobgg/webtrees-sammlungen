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
 * Rural (jon48, Ordner myartjaub_ruraltheme) aus dessen Quellen: 35 im Menue,
 * 24 im Aufklappmenue.
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

    /**
     * Kein ausdrueckliches "kein Symbol" als Grundzustand. Ohne Regel zeichnet
     * der Browser ohnehin nichts - Themes ohne Symbole bleiben sauber. Ein
     * `content:none` haette aber Vorrang vor den generischen Ersatzsymbolen,
     * die Rural (jon48) jedem fremden Menue gibt: dort stand unser Eintrag als
     * einziger ohne Symbol in der Leiste (Rueckmeldung von Bernat Banyuls).
     */
    public function testKeinGrundzustandDerFremdeErsatzsymboleUeberstimmt(): void
    {
        self::assertStringNotContainsString('content:none', self::quelle());
    }

    /** Je Theme dessen eigene Groesse - die Zahlen stammen aus deren CSS. */
    public function testGroessenJeTheme(): void
    {
        $reflexion = new ReflectionClass(SammlungenModule::class);
        /** @var array<string,int> $groessen */
        $groessen = $reflexion->getConstant('SYMBOLGROESSE');

        self::assertSame(
            ['webtrees' => 50, 'colors' => 40, 'xenea' => 28, 'clouds' => 22, '_myartjaub_ruraltheme_' => 35],
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

    /**
     * Ein Aufklappmenue klappt beim Klick auf, statt zu springen - ohne einen
     * eigenen Eintrag waere der kurze Weg zur Galerie verloren. Und der Eintrag
     * heisst nicht noch einmal "Collections", sonst stuende dasselbe Wort
     * zweimal fast gleich untereinander.
     */
    public function testUntermenueBehaeltDenWegZurGalerie(): void
    {
        $quelle = self::quelle();

        self::assertStringContainsString("I18N::translate('Overview')", $quelle);
        self::assertStringContainsString("I18N::translate('Manage collections')", $quelle);
        self::assertStringContainsString("I18N::translate('Settings')", $quelle);
    }

    /** Nur Verwalter bekommen das Aufklappmenue; fuer alle anderen ein Klick. */
    public function testNurVerwalterBekommenDasUntermenue(): void
    {
        self::assertMatchesRegularExpression(
            '/if \(!Auth::isAdmin\(\)\) \{\s*return new Menu\(/',
            self::quelle(),
            'Das Untermenue erscheint auch fuer normale Mitglieder.'
        );
    }

    /**
     * Im Aufklappmenue fuehren die Kernmenues kleinere Symbole - und Colors und
     * Clouds gar keine. Gemessen an der Instanz.
     */
    public function testUntermenueSymboleNurWoDasThemeWelcheHat(): void
    {
        $groessen = (new ReflectionClass(SammlungenModule::class))
            ->getConstant('SYMBOLGROESSE_UNTERMENUE');

        self::assertSame(['webtrees' => 24, 'xenea' => 22, '_myartjaub_ruraltheme_' => 24], $groessen);
    }
}
