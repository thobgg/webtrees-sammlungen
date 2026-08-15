<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sammlungen\Dto\Symbole;

/**
 * webtrees liefert kein vollstaendiges FontAwesome aus, sondern die Auswahl,
 * die es selbst in `resources/views/icons/` benutzt. Ein Name ausserhalb dieser
 * Auswahl erscheint nicht als nichts, sondern als gestrichelter Kreis mit
 * Fragezeichen - am Geraet gesehen bei "Freier Bestand" und beim Medienobjekt.
 *
 * Der Test haelt zweierlei fest: dass jeder Name, den das Modul ausgibt, aus
 * dieser Auswahl stammt, und dass im Markup keine rohen Namen mehr stehen, die
 * daran vorbeigehen.
 */
final class SymboleTest extends TestCase
{
    /** @return list<string> */
    private static function markupDateien(): array
    {
        $wurzel = dirname(__DIR__, 2);

        return array_merge(
            glob($wurzel . '/resources/views/*.phtml') ?: [],
            glob($wurzel . '/resources/views/partials/*.phtml') ?: [],
        );
    }

    public function testJederErsatzIstVerfuegbar(): void
    {
        $reflexion = new \ReflectionClass(Symbole::class);
        /** @var array<string,string> $ersatz */
        $ersatz = $reflexion->getConstant('ERSATZ');

        foreach ($ersatz as $wunsch => $dafuer) {
            self::assertContains(
                $dafuer,
                Symbole::VERFUEGBAR,
                sprintf('Ersatz fuer %s ist selbst nicht vorhanden: %s', $wunsch, $dafuer)
            );
        }
    }

    public function testUnbekanntesFaelltAufEinVorhandenesZurueck(): void
    {
        foreach (['fa-gibtsnicht', 'einhorn', '', 'fa-'] as $wunsch) {
            self::assertContains(
                Symbole::fa($wunsch),
                Symbole::VERFUEGBAR,
                sprintf('"%s" liefert ein Symbol, das webtrees nicht zeichnet.', $wunsch)
            );
        }
    }

    /** Nutzer tragen Namen in der Verwaltung von Hand ein - mal mit, mal ohne Vorsatz. */
    public function testNamenMitUndOhneVorsatz(): void
    {
        self::assertSame('fa-folder', Symbole::fa('folder'));
        self::assertSame('fa-folder', Symbole::fa('fa-folder'));
        self::assertSame('fa-folder', Symbole::fa('  FA-Folder '));
    }

    /**
     * Der eigentliche Waechter: im Markup darf kein fester Name stehen, den
     * webtrees nicht kennt. Dynamische Werte laufen ueber Symbole::fa().
     */
    public function testMarkupBenutztNurVorhandeneNamen(): void
    {
        $unbekannt = [];

        foreach (self::markupDateien() as $datei) {
            $inhalt = (string) file_get_contents($datei);
            preg_match_all('/class="fas (fa-[a-z0-9-]+)/', $inhalt, $treffer);

            foreach ($treffer[1] as $name) {
                if (!in_array($name, Symbole::VERFUEGBAR, true)) {
                    $unbekannt[] = basename($datei) . ': ' . $name;
                }
            }
        }

        self::assertSame([], array_unique($unbekannt), 'Symbole, die als Fragezeichen erscheinen');
    }
}
