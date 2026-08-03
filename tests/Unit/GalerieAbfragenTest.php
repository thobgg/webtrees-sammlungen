<?php

declare(strict_types=1);

namespace Sammlungen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sammlungen\Service\CollectionService;
use Sammlungen\ViewModel\SammlungenViewModel;

/**
 * Haelt fest, dass die Galerie ihre Daten seitenweise holt und nicht je Bild.
 * Ohne DB-Testbasis laesst sich die Abfragezahl nicht messen – geprueft wird
 * deshalb, dass die je-Bild-Methoden nicht in der Bildschleife stehen.
 */
final class GalerieAbfragenTest extends TestCase
{
    private static function viewModelQuelle(): string
    {
        $datei = (new ReflectionClass(SammlungenViewModel::class))->getFileName();

        return (string) file_get_contents((string) $datei);
    }

    /**
     * Die Zugehoerigkeit zu Sammlungen wurde je Bild einzeln abgefragt – bei
     * 200 Eintraegen pro Seite also 200 Abfragen fuer eine Information, die in
     * eine passt.
     */
    public function testSammlungszugehoerigkeitWirdNichtJeBildAbgefragt(): void
    {
        self::assertStringNotContainsString(
            'sammlungenDesPfades(',
            self::viewModelQuelle(),
            'Der ViewModel fragt wieder je Bild ab – sammlungenFuerPfade() holt die ganze Seite auf einmal.'
        );
    }

    /** Gegenstueck: die Sammelabfrage muss tatsaechlich benutzt werden. */
    public function testSammelabfrageWirdVerwendet(): void
    {
        self::assertStringContainsString('sammlungenFuerPfade(', self::viewModelQuelle());
    }

    /**
     * Ohne Pfade darf keine Abfrage abgesetzt werden – der Fall tritt bei jeder
     * leeren Sammlung ein. Der Service laesst sich hier ohne Konstruktor bauen,
     * weil die leere Eingabe vor dem Datenbankzugriff zurueckkehrt.
     */
    public function testLeereEingabeErzeugtKeineAbfrage(): void
    {
        $service = (new ReflectionClass(CollectionService::class))->newInstanceWithoutConstructor();

        self::assertSame([], $service->sammlungenFuerPfade(
            (new ReflectionClass(\Fisharebest\Webtrees\Tree::class))->newInstanceWithoutConstructor(),
            []
        ));
    }

    /**
     * Auch die Sichtbarkeitspruefung darf nicht je Person eine Abfrage
     * ausloesen: die Datensaetze kommen gebuendelt und gehen durch den
     * Factory-Mapper, der das GEDCOM aus der Zeile nimmt (Issue #16).
     */
    public function testSichtbarkeitspruefungLaedtGebuendelt(): void
    {
        $datei  = (new ReflectionClass(CollectionService::class))->getFileName();
        $quelle = (string) file_get_contents((string) $datei);

        self::assertStringContainsString('->mapper($tree)', $quelle);
        self::assertStringContainsString("whereIn('i_id'", $quelle);
    }
}
