<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sammlungen\Repository\SammlungenRepository;

/**
 * Der freie Bestand ist die Menge, fuer die es das Modul gibt: was im Archiv
 * liegt und an niemandem haengt.
 *
 * Bis 1.3.3 galt dafuer irgendeine `OBJE`-Zeile als Einbindung. In webtrees
 * darf die aber genauso in einer Quelle, einer Notiz oder einem Repositorium
 * stehen. Wer seine Registerscans an Quellen haengt - der Normalfall bei
 * Kirchenbuechern und Standesamtsregistern -, bekam einen leeren freien
 * Bestand gemeldet, obwohl an keiner Person etwas hing. Die Ansicht behauptete
 * dabei woertlich, alles sei "mit Personen oder Familien verknuepft".
 *
 * Ohne DB-Testbasis laesst sich die Abfrage nicht ausfuehren; geprueft wird
 * deshalb, dass die Regel im Quelltext steht und an einer Stelle steht.
 */
final class FreierBestandTest extends TestCase
{
    private static function quelle(): string
    {
        $datei = (new ReflectionClass(SammlungenRepository::class))->getFileName();

        return (string) file_get_contents((string) $datei);
    }

    /** Personen und Familien zaehlen - und sonst nichts. */
    public function testRegelPrueftPersonenUndFamilien(): void
    {
        $quelle = self::quelle();

        self::assertStringContainsString('nurOhnePersonOderFamilie', $quelle);
        self::assertStringContainsString("leftJoin('individuals AS i'", $quelle);
        self::assertStringContainsString("leftJoin('families AS f'", $quelle);
        self::assertMatchesRegularExpression(
            '/whereNotNull\(\'i\.i_id\'\)->orWhereNotNull\(\'f\.f_id\'\)/',
            $quelle,
            'Die Regel akzeptiert keine Familienverknuepfung mehr.'
        );
    }

    /**
     * Die alte Regel darf nirgends ueberleben: sie zaehlt eine Quellenzeile
     * als Einbindung in den Stammbaum.
     */
    public function testAlteRegelIstVerschwunden(): void
    {
        self::assertStringNotContainsString(
            "whereNull('lnk.l_to')",
            self::quelle(),
            'Irgendeine OBJE-Zeile gilt wieder als Einbindung.'
        );
    }

    /** Zaehlung, Auflistung und Vorschau muessen dieselbe Menge meinen. */
    public function testZaehlungUndListeBenutzenDieselbeRegel(): void
    {
        $quelle  = self::quelle();
        $treffer = preg_match_all('/nurOhnePersonOderFamilie\(/', $quelle);

        // einmal die Definition, dreimal die Anwendung: Zaehlung, Auflistung
        // und Vorschaubilder. Die dritte Stelle hat dieser Test gefunden.
        self::assertSame(4, $treffer, 'Eine der Abfragen laeuft an der Regel vorbei.');
    }

    /**
     * Die Aussage der leeren Ansicht muss zur Regel passen - sie war der
     * eigentliche Fehler: gezaehlt wurde etwas anderes als behauptet.
     */
    public function testAussageDerLeerenAnsichtPasstZurRegel(): void
    {
        $views = dirname(__DIR__, 2) . '/resources/views/partials/';

        foreach (['_uebersicht.phtml', '_unverknuepft-uebersicht.phtml'] as $datei) {
            self::assertStringContainsString(
                'All media objects are linked to individuals or families.',
                (string) file_get_contents($views . $datei),
                $datei . ' behauptet etwas anderes als die Abfrage prueft.'
            );
        }
    }
}
